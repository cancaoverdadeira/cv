<?php
// cancao-verdadeira/includes/apoio/class-cv-pix.php
// Criado em: 25/09/2026 (plugin v2.48.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// PIX da Canção Verdadeira: guarda a conta (chave por e-mail, telefone ou
// aleatória, nome do recebedor e cidade) e GERA o código "PIX copia e cola"
// (BR Code, padrão EMV do Banco Central, com CRC16) para um valor e uma
// identificação (txid). O QR Code é desenhado no navegador a partir desse
// código (assets/js/cv-pix.js). Usado nos pedidos da Minha Área e na doação
// do rodapé ("Seja nosso colaborador"). Nenhum dado bancário além da chave.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Pix {

    const OPCAO = 'cv_pix';
    // Biblioteca do QR Code (MIT), versão fixa; só carrega quando um PIX é aberto.
    const QR_LIB = 'https://cdn.jsdelivr.net/npm/qrcode-generator@1.4.4/qrcode.min.js';

    public static function tipos_chave() {
        return array(
            'email'     => 'E-mail',
            'telefone'  => 'Telefone celular',
            'aleatoria' => 'Chave aleatória',
        );
    }

    public static function init() {
        add_action( 'wp_ajax_cv_pix_gerar',        array( __CLASS__, 'ajax_gerar' ) );
        add_action( 'wp_ajax_nopriv_cv_pix_gerar', array( __CLASS__, 'ajax_gerar' ) );
        add_action( 'wp_enqueue_scripts',          array( __CLASS__, 'enqueue' ), 30 );
        add_action( 'admin_enqueue_scripts',       array( __CLASS__, 'enqueue_admin' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // CONTA
    // ════════════════════════════════════════════════════════════════

    public static function config() {
        $c = get_option( self::OPCAO, array() );
        return wp_parse_args( is_array( $c ) ? $c : array(), array(
            'tipo'   => 'email',
            'chave'  => '',
            'nome'   => '',
            'cidade' => '',
            'ativo'  => 0,
        ) );
    }

    /** PIX pronto para uso: ligado e com chave, nome e cidade preenchidos. */
    public static function ativo() {
        $c = self::config();
        return ! empty( $c['ativo'] ) && '' !== $c['chave'] && '' !== $c['nome'] && '' !== $c['cidade'];
    }

    /**
     * Confere e normaliza a chave. E-mail: minúsculo. Telefone: +55DDDNÚMERO
     * (aceita com ou sem 55, com máscara). Aleatória: UUID de 36 caracteres.
     */
    public static function normalizar_chave( $tipo, $chave ) {
        $chave = trim( (string) $chave );
        switch ( $tipo ) {
            case 'email':
                $chave = strtolower( $chave );
                return is_email( $chave ) && strlen( $chave ) <= 77 ? $chave : new WP_Error( 'chave', 'E-mail inválido.' );
            case 'telefone':
                $num = preg_replace( '/\D/', '', $chave );
                if ( 0 === strpos( $num, '55' ) && strlen( $num ) >= 12 ) { $num = substr( $num, 2 ); }
                return preg_match( '/^[1-9]{2}9?\d{8}$/', $num ) ? '+55' . $num : new WP_Error( 'chave', 'Telefone inválido. Use DDD + número, ex.: (31) 99999-9999.' );
            case 'aleatoria':
                $chave = strtolower( $chave );
                return preg_match( '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $chave ) ? $chave : new WP_Error( 'chave', 'Chave aleatória inválida (formato 123e4567-e89b-12d3-a456-426614174000).' );
        }
        return new WP_Error( 'tipo', 'Tipo de chave inválido.' );
    }

    public static function salvar_config( $dados ) {
        $tipos = self::tipos_chave();
        $tipo  = isset( $dados['tipo'], $tipos[ $dados['tipo'] ] ) ? $dados['tipo'] : '';
        if ( ! $tipo ) { return new WP_Error( 'tipo', 'Escolha o tipo da chave.' ); }
        $chave = self::normalizar_chave( $tipo, isset( $dados['chave'] ) ? $dados['chave'] : '' );
        if ( is_wp_error( $chave ) ) { return $chave; }
        $nome   = self::texto_bc( isset( $dados['nome'] ) ? $dados['nome'] : '', 25 );
        $cidade = self::texto_bc( isset( $dados['cidade'] ) ? $dados['cidade'] : '', 15 );
        if ( '' === $nome )   { return new WP_Error( 'nome', 'Informe o nome do recebedor (como está no banco).' ); }
        if ( '' === $cidade ) { return new WP_Error( 'cidade', 'Informe a cidade.' ); }
        update_option( self::OPCAO, array(
            'tipo'   => $tipo,
            'chave'  => $chave,
            'nome'   => $nome,
            'cidade' => $cidade,
            'ativo'  => empty( $dados['ativo'] ) ? 0 : 1,
        ), false );
        return true;
    }

    // ════════════════════════════════════════════════════════════════
    // GERADOR DO CÓDIGO (BR Code / PIX copia e cola)
    // ════════════════════════════════════════════════════════════════

    /**
     * Monta o código PIX estático.
     * $valor: em reais (0 = a pessoa digita o valor no banco).
     * $txid:  identificação que aparece no extrato (até 25 letras/números).
     * $info:  mensagem curta para quem paga (opcional).
     */
    public static function payload( $valor = 0, $txid = '', $info = '' ) {
        $c = self::config();
        if ( '' === $c['chave'] ) { return ''; }

        $txid = substr( preg_replace( '/[^A-Za-z0-9]/', '', (string) $txid ), 0, 25 );
        if ( '' === $txid ) { $txid = '***'; }

        // Campo 26 (conta PIX) tem no máximo 99 caracteres: a mensagem se ajusta.
        $conta = self::campo( '00', 'br.gov.bcb.pix' ) . self::campo( '01', $c['chave'] );
        $info  = self::texto_bc( $info, 99 - strlen( $conta ) - 4, false );
        if ( '' !== $info ) { $conta .= self::campo( '02', $info ); }

        $codigo = self::campo( '00', '01' )
            . self::campo( '26', $conta )
            . self::campo( '52', '0000' )
            . self::campo( '53', '986' )
            . ( $valor > 0 ? self::campo( '54', number_format( (float) $valor, 2, '.', '' ) ) : '' )
            . self::campo( '58', 'BR' )
            . self::campo( '59', $c['nome'] )
            . self::campo( '60', $c['cidade'] )
            . self::campo( '62', self::campo( '05', $txid ) )
            . '6304';
        return $codigo . self::crc16( $codigo );
    }

    private static function campo( $id, $valor ) {
        return $id . str_pad( (string) strlen( $valor ), 2, '0', STR_PAD_LEFT ) . $valor;
    }

    /** CRC16-CCITT (polinômio 0x1021, início 0xFFFF), exigido pelo Banco Central. */
    public static function crc16( $texto ) {
        $crc = 0xFFFF;
        $len = strlen( $texto );
        for ( $i = 0; $i < $len; $i++ ) {
            $crc ^= ord( $texto[ $i ] ) << 8;
            for ( $b = 0; $b < 8; $b++ ) {
                $crc = ( $crc & 0x8000 ) ? ( ( $crc << 1 ) ^ 0x1021 ) : ( $crc << 1 );
                $crc &= 0xFFFF;
            }
        }
        return strtoupper( str_pad( dechex( $crc ), 4, '0', STR_PAD_LEFT ) );
    }

    /** Sem acentos e só caracteres aceitos pelos bancos; corta no limite. */
    public static function texto_bc( $texto, $max, $maiusculo = true ) {
        $texto = remove_accents( sanitize_text_field( (string) $texto ) );
        $texto = preg_replace( '/[^A-Za-z0-9 .,\-\/]/', '', $texto );
        $texto = trim( preg_replace( '/\s+/', ' ', $texto ) );
        if ( $maiusculo ) { $texto = strtoupper( $texto ); }
        return $max > 0 ? trim( substr( $texto, 0, $max ) ) : '';
    }

    public static function chave_para_exibir() {
        $c = self::config();
        return 'telefone' === $c['tipo'] ? preg_replace( '/^\+55(\d{2})(\d{4,5})(\d{4})$/', '($1) $2-$3', $c['chave'] ) : $c['chave'];
    }

    // ════════════════════════════════════════════════════════════════
    // HTML DO BLOCO "PAGAR COM PIX" (QR + copia e cola)
    // ════════════════════════════════════════════════════════════════

    // $teste = true: mostra mesmo com o PIX desligado (teste do painel).
    public static function bloco( $valor, $txid, $info = '', $titulo = '', $teste = false ) {
        if ( ! self::ativo() && ! ( $teste && '' !== self::config()['chave'] ) ) {
            return '<div class="cv-pix cv-pix-off">O pagamento por PIX ainda não está disponível. Vamos entrar em contato com você.</div>';
        }
        $codigo = self::payload( $valor, $txid, $info );
        $c      = self::config();
        ob_start();
        ?>
        <div class="cv-pix" data-pix="<?php echo esc_attr( $codigo ); ?>">
            <?php if ( $titulo ) : ?><div class="cv-pix-titulo"><?php echo esc_html( $titulo ); ?></div><?php endif; ?>
            <div class="cv-pix-corpo">
                <div class="cv-pix-qr" aria-label="QR Code do PIX"><span class="cv-pix-qr-carregando">Gerando QR Code…</span></div>
                <div class="cv-pix-dados">
                    <?php if ( $valor > 0 ) : ?>
                    <div class="cv-pix-valor">R$ <?php echo esc_html( number_format( (float) $valor, 2, ',', '.' ) ); ?></div>
                    <?php endif; ?>
                    <ol class="cv-pix-passos">
                        <li>Abra o aplicativo do seu banco e escolha <strong>PIX</strong>.</li>
                        <li><strong>Aponte a câmera</strong> para o QR Code ou use <strong>PIX Copia e Cola</strong>.</li>
                        <li>Confira o nome: <strong><?php echo esc_html( $c['nome'] ); ?></strong>.</li>
                    </ol>
                    <label class="cv-pix-rotulo">PIX Copia e Cola</label>
                    <div class="cv-pix-copia">
                        <input type="text" readonly value="<?php echo esc_attr( $codigo ); ?>" class="cv-pix-codigo" aria-label="Código PIX copia e cola" />
                        <button type="button" class="cv-btn cv-btn-primary cv-pix-copiar">📋 Copiar</button>
                    </div>
                    <div class="cv-pix-chave">Ou use a chave (<?php echo esc_html( strtolower( self::tipos_chave()[ $c['tipo'] ] ) ); ?>): <strong><?php echo esc_html( self::chave_para_exibir() ); ?></strong></div>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // AJAX — doação com valor escolhido (rodapé)
    // ════════════════════════════════════════════════════════════════

    public static function ajax_gerar() {
        check_ajax_referer( 'cv_pix_nonce', 'nonce' );
        if ( ! self::ativo() ) { wp_send_json_error( array( 'message' => 'PIX indisponível no momento.' ) ); }
        $valor = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['valor'] ?? '0' ) ) );
        if ( $valor < 0 || ( $valor > 0 && $valor < 1 ) || $valor > 10000 ) { wp_send_json_error( array( 'message' => 'Escolha um valor entre R$ 1 e R$ 10.000, ou deixe em branco.' ) ); }
        $valor = round( $valor, 2 );
        wp_send_json_success( array(
            'html' => self::bloco( $valor, 'CVDOACAO', 'Doacao Cancao Verdadeira', $valor > 0 ? 'Obrigado pelo seu apoio! 💛' : 'Obrigado! Digite o valor no seu banco 💛' ),
        ) );
    }

    // ════════════════════════════════════════════════════════════════
    // CSS e JS (o QR Code só é desenhado no navegador)
    // ════════════════════════════════════════════════════════════════

    private static function registrar() {
        wp_register_style( 'cv-pix', CV_PLUGIN_URL . 'assets/css/cv-pix.css', array(), CV_VERSION );
        wp_register_script( 'cv-pix', CV_PLUGIN_URL . 'assets/js/cv-pix.js', array( 'jquery' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-pix', 'window.cvPix = ' . wp_json_encode( array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cv_pix_nonce' ),
            'qrLib'   => self::QR_LIB,
        ) ) . ';', 'before' );
    }

    public static function enqueue() {
        self::registrar();
        wp_enqueue_style( 'cv-pix' );
        wp_enqueue_script( 'cv-pix' );
    }

    public static function enqueue_admin() {
        if ( ! in_array( sanitize_key( $_GET['page'] ?? '' ), array( 'cv-apoio', 'cv-estoque' ), true ) ) { return; }
        self::registrar();
        wp_enqueue_style( 'cv-pix' );
        wp_enqueue_script( 'cv-pix' );
    }
}

CV_Pix::init();
