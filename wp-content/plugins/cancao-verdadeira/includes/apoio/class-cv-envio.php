<?php
// cancao-verdadeira/includes/apoio/class-cv-envio.php
// Criado em: 25/09/2026 (plugin v2.50.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// "Envio de música" do parceiro, em 4 etapas, na Minha Área (logado):
//   1) Contrato: PDF com ASSINATURA DIGITAL obrigatória (gov.br/ICP-Brasil),
//      conferida por CV_Assinatura_PDF (assinado, quem assinou, sem alteração).
//   2) Pagamento da taxa por PIX (mesmo QR/copia e cola do site, id CVDIV<nº>);
//      o parceiro marca "Já fiz o PIX" e pode anexar o comprovante.
//   3) Link do YouTube (confere o vídeo e se já não existe no site).
//   4) Título, descrição, letra e tags — com o "prompt de ajuda" para baixar.
// O admin aprova contrato e pagamento e, no fim, gera a música em RASCUNHO
// (CV_Envio_Admin). Arquivos ficam em cv-privado/, FORA da pasta pública do
// site quando a hospedagem permite (reserva: uploads/cv-privado com .htaccess),
// com nomes aleatórios, e só o admin baixa. Regras e AJAX aqui; HTML em
// CV_Envio_Area; painel em CV_Envio_Admin.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Envio {

    const MAX_ABERTOS     = 3;           // envios em andamento por pessoa
    const MAX_COMPROVANTE = 5242880;     // 5 MB

    public static function init() {
        foreach ( array( 'novo', 'contrato', 'pago', 'youtube', 'dados', 'excluir' ) as $acao ) {
            add_action( 'wp_ajax_cv_envio_' . $acao, array( __CLASS__, 'ajax_' . $acao ) );
        }
        add_action( 'wp_ajax_cv_envio_baixar', array( __CLASS__, 'baixar' ) ); // contrato-modelo e prompt
    }

    // ════════════════════════════════════════════════════════════════
    // DADOS
    // ════════════════════════════════════════════════════════════════

    private static function t() {
        global $wpdb;
        return $wpdb->prefix . 'cv_envios';
    }

    public static function envio( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE id = %d', $id ) );
    }

    public static function do_usuario( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM ' . self::t() . ' WHERE user_id = %d ORDER BY id DESC LIMIT 20', $user_id ) );
    }

    /** A proposta de parceria (rodapé) feita com o mesmo e-mail da conta. */
    public static function parceria_do_usuario( $user_id ) {
        global $wpdb;
        $u = get_userdata( $user_id );
        if ( ! $u ) { return null; }
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_parcerias WHERE email = %s AND status <> 'recusada' ORDER BY id DESC LIMIT 1",
            $u->user_email
        ) );
    }

    /** A aba aparece para quem já mandou proposta de parceria ou já tem envios. */
    public static function pode_ver( $user_id ) {
        return $user_id && ( self::parceria_do_usuario( $user_id ) || ! empty( self::do_usuario( $user_id ) ) );
    }

    private static function salvar( $id, $dados ) {
        global $wpdb;
        $dados['atualizado_em'] = current_time( 'mysql' );
        $wpdb->update( self::t(), $dados, array( 'id' => $id ) );
    }

    /** Taxa de divulgação em número (vem de CV_Parceria_Docs, ex.: "1.500,00"). */
    public static function valor_taxa() {
        $t = class_exists( 'CV_Parceria_Docs' ) ? CV_Parceria_Docs::config()['taxa'] : '';
        if ( '' === $t ) { return 0.0; }
        if ( false !== strpos( $t, ',' ) ) { $t = str_replace( array( '.', ',' ), array( '', '.' ), $t ); }
        return round( (float) $t, 2 );
    }

    public static function txid( $id ) {
        return 'CVDIV' . (int) $id;
    }

    // ════════════════════════════════════════════════════════════════
    // ARQUIVOS PRIVADOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Pasta privada dos contratos e comprovantes. Preferência: FORA da pasta
     * pública do site (ao lado dela), onde nenhum endereço da internet chega.
     * Reserva (hospedagem que não permite): uploads/cv-privado com bloqueio
     * por .htaccess (Apache) — no nginx o .htaccess não vale, e sobram os
     * nomes aleatórios. Conferido em 25/09/2026 no LocalWP (nginx).
     */
    public static function pasta() {
        static $dir = null;
        if ( null !== $dir ) { return $dir; }
        $fora = dirname( untrailingslashit( ABSPATH ) ) . '/cv-privado/envios';
        if ( is_dir( $fora ) || ( is_writable( dirname( untrailingslashit( ABSPATH ) ) ) && wp_mkdir_p( $fora ) ) ) {
            $dir = $fora;
        } else {
            $up  = wp_upload_dir();
            $dir = trailingslashit( $up['basedir'] ) . 'cv-privado/envios';
            if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); }
        }
        $raiz = dirname( $dir );
        if ( ! file_exists( $raiz . '/.htaccess' ) ) {
            file_put_contents( $raiz . '/.htaccess', "# Canção Verdadeira: arquivos privados (só o painel baixa)\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" );
        }
        foreach ( array( $raiz, $dir ) as $d ) {
            if ( ! file_exists( $d . '/index.php' ) ) { file_put_contents( $d . '/index.php', "<?php // Silêncio.\n" ); }
        }
        return $dir;
    }

    /** Fora da pasta pública? (mostrado no painel) */
    public static function pasta_fora_do_site() {
        return 0 !== strpos( wp_normalize_path( self::pasta() ), wp_normalize_path( ABSPATH ) );
    }

    public static function caminho( $relativo ) {
        $relativo = basename( (string) $relativo );
        return '' === $relativo ? '' : self::pasta() . '/' . $relativo;
    }

    /**
     * Confere e guarda um arquivo enviado. $tipos = extensões aceitas.
     * Confere também o começo do arquivo (PDF, JPG ou PNG de verdade).
     */
    private static function guardar_upload( $campo, $tipos, $max, $prefixo ) {
        if ( empty( $_FILES[ $campo ] ) || UPLOAD_ERR_OK !== (int) $_FILES[ $campo ]['error'] ) {
            return new WP_Error( 'arquivo', 'Escolha o arquivo antes de enviar.' );
        }
        $f = $_FILES[ $campo ];
        if ( (int) $f['size'] > $max ) {
            return new WP_Error( 'tamanho', 'O arquivo passa do limite de ' . size_format( $max ) . '.' );
        }
        $ext = strtolower( pathinfo( $f['name'], PATHINFO_EXTENSION ) );
        if ( 'jpeg' === $ext ) { $ext = 'jpg'; }
        if ( ! in_array( $ext, $tipos, true ) ) {
            return new WP_Error( 'tipo', 'Tipo de arquivo não aceito. Envie: ' . strtoupper( implode( ', ', $tipos ) ) . '.' );
        }
        $inicio = (string) file_get_contents( $f['tmp_name'], false, null, 0, 8 );
        $assina = array( 'pdf' => '%PDF-', 'jpg' => "\xFF\xD8\xFF", 'png' => "\x89PNG" );
        if ( 0 !== strpos( $inicio, $assina[ $ext ] ) ) {
            return new WP_Error( 'conteudo', 'O arquivo não parece ser um ' . strtoupper( $ext ) . ' de verdade.' );
        }
        $nome = $prefixo . '-' . wp_generate_password( 24, false, false ) . '.' . $ext;
        $dest = self::pasta() . '/' . $nome;
        if ( ! @move_uploaded_file( $f['tmp_name'], $dest ) ) {
            return new WP_Error( 'mover', 'Não foi possível guardar o arquivo. Tente de novo.' );
        }
        @chmod( $dest, 0640 );
        return $nome;
    }

    private static function apagar_arquivo( $relativo ) {
        $c = self::caminho( $relativo );
        if ( $c && is_file( $c ) ) { @unlink( $c ); }
    }

    // ════════════════════════════════════════════════════════════════
    // AJAX — PARCEIRO (Minha Área)
    // ════════════════════════════════════════════════════════════════

    /** Confere login, nonce e dono do envio. Devolve o envio (ou encerra com erro). */
    private static function envio_do_pedido( $precisa_etapa = 0 ) {
        check_ajax_referer( 'cv_envio_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! $uid ) { wp_send_json_error( array( 'message' => 'Entre na sua conta.' ) ); }
        $e = self::envio( absint( $_POST['envio_id'] ?? 0 ) );
        if ( ! $e || (int) $e->user_id !== $uid ) { wp_send_json_error( array( 'message' => 'Envio não encontrado.' ) ); }
        if ( in_array( $e->status, array( 'aprovado', 'recusado' ), true ) ) {
            wp_send_json_error( array( 'message' => 'Este envio já foi concluído.' ) );
        }
        if ( $precisa_etapa && (int) $e->etapa < $precisa_etapa ) {
            wp_send_json_error( array( 'message' => 'Conclua as etapas anteriores primeiro.' ) );
        }
        return $e;
    }

    private static function responder( $id, $msg ) {
        wp_send_json_success( array( 'message' => $msg, 'html' => CV_Envio_Area::cartao( self::envio( $id ) ) ) );
    }

    public static function ajax_novo() {
        check_ajax_referer( 'cv_envio_nonce', 'nonce' );
        $uid = get_current_user_id();
        if ( ! self::pode_ver( $uid ) ) {
            wp_send_json_error( array( 'message' => 'Primeiro envie uma proposta na página "Para artistas", com o mesmo e-mail da sua conta.' ) );
        }
        $abertos = 0;
        foreach ( self::do_usuario( $uid ) as $e ) { if ( in_array( $e->status, array( 'rascunho', 'enviado' ), true ) ) { $abertos++; } }
        if ( $abertos >= self::MAX_ABERTOS ) {
            wp_send_json_error( array( 'message' => 'Você já tem ' . $abertos . ' envios em andamento. Conclua um deles antes de começar outro.' ) );
        }
        global $wpdb;
        $parc = self::parceria_do_usuario( $uid );
        $wpdb->insert( self::t(), array(
            'user_id'     => $uid,
            'parceria_id' => $parc ? (int) $parc->id : 0,
            'artista'     => $parc && $parc->nome_artistico ? $parc->nome_artistico : '',
            'titulo'      => $parc && $parc->musica ? $parc->musica : '',
            'valor'       => self::valor_taxa(),
            'criado_em'   => current_time( 'mysql' ),
        ) );
        wp_send_json_success( array( 'message' => 'Envio iniciado.', 'html' => CV_Envio_Area::lista( $uid ) ) );
    }

    /** Etapa 1: contrato em PDF com assinatura digital obrigatória. */
    public static function ajax_contrato() {
        $e = self::envio_do_pedido();
        $nome = self::guardar_upload( 'arquivo', array( 'pdf' ), CV_Assinatura_PDF::TAMANHO_MAX, 'contrato-' . $e->id );
        if ( is_wp_error( $nome ) ) { wp_send_json_error( array( 'message' => $nome->get_error_message() ) ); }
        $r = CV_Assinatura_PDF::verificar( self::caminho( $nome ) );
        if ( ! CV_Assinatura_PDF::aceito( $r ) ) {
            self::apagar_arquivo( $nome );
            $msg = $r['mensagem'];
            if ( empty( $r['assinado'] ) ) { $msg .= ' A assinatura digital é obrigatória: veja o passo a passo acima (é grátis pelo gov.br).'; }
            wp_send_json_error( array( 'message' => $msg ) );
        }
        self::apagar_arquivo( $e->contrato_arquivo ); // troca o anterior, se houver
        self::salvar( $e->id, array(
            'contrato_arquivo' => $nome,
            'contrato_info'    => wp_json_encode( $r ),
            'contrato_status'  => 'pendente',
            'etapa'            => max( 2, (int) $e->etapa ),
        ) );
        self::avisar_admin( $e->id, 'Contrato assinado recebido' );
        self::responder( $e->id, '✅ ' . $r['mensagem'] . ' Agora é só fazer o PIX da taxa.' );
    }

    /** Etapa 2: "Já fiz o PIX" (comprovante opcional). */
    public static function ajax_pago() {
        $e = self::envio_do_pedido( 2 );
        $dados = array( 'pagamento_status' => 'confirmado' === $e->pagamento_status ? 'confirmado' : 'informado', 'etapa' => max( 3, (int) $e->etapa ) );
        if ( ! empty( $_FILES['arquivo']['name'] ) ) {
            $nome = self::guardar_upload( 'arquivo', array( 'pdf', 'jpg', 'png' ), self::MAX_COMPROVANTE, 'comprovante-' . $e->id );
            if ( is_wp_error( $nome ) ) { wp_send_json_error( array( 'message' => $nome->get_error_message() ) ); }
            self::apagar_arquivo( $e->comprovante_arquivo );
            $dados['comprovante_arquivo'] = $nome;
        }
        self::salvar( $e->id, $dados );
        self::avisar_admin( $e->id, 'PIX da taxa informado' );
        self::responder( $e->id, '✅ Obrigado! Vamos conferir o PIX. Enquanto isso, siga para o link do YouTube.' );
    }

    /** Etapa 3: link do YouTube. */
    public static function ajax_youtube() {
        $e   = self::envio_do_pedido( 3 );
        $url = esc_url_raw( trim( wp_unslash( $_POST['url'] ?? '' ) ) );
        $vid = CV_Fields::youtube_id( $url );
        if ( ! $vid ) { wp_send_json_error( array( 'message' => 'Link do YouTube inválido. Copie o endereço do vídeo (ex.: https://www.youtube.com/watch?v=...).' ) ); }
        if ( class_exists( 'CV_Youtube_Import' ) && CV_Youtube_Import::find_existing( $vid ) ) {
            wp_send_json_error( array( 'message' => 'Este vídeo já está cadastrado no site.' ) );
        }
        $titulo = class_exists( 'CV_Youtube_Import' ) ? CV_Youtube_Import::titulo_do_youtube( $vid ) : '';
        if ( '' === $titulo ) {
            wp_send_json_error( array( 'message' => 'Não encontramos o vídeo no YouTube. Confira se ele está público (ou "não listado") e tente de novo.' ) );
        }
        $dados = array( 'youtube_url' => 'https://www.youtube.com/watch?v=' . $vid, 'youtube_titulo' => $titulo, 'etapa' => max( 4, (int) $e->etapa ) );
        if ( '' === $e->titulo ) { $dados['titulo'] = mb_substr( $titulo, 0, 191 ); }
        self::salvar( $e->id, $dados );
        self::responder( $e->id, '✅ Vídeo encontrado: "' . $titulo . '".' );
    }

    /** Etapa 4: dados da música → envia para aprovação. */
    public static function ajax_dados() {
        $e = self::envio_do_pedido( 4 );
        $p = wp_unslash( $_POST );
        $titulo = mb_substr( sanitize_text_field( $p['titulo'] ?? '' ), 0, 191 );
        $letra  = sanitize_textarea_field( $p['letra'] ?? '' );
        $desc   = mb_substr( sanitize_textarea_field( $p['descricao'] ?? '' ), 0, 300 );
        $tags   = implode( ', ', array_slice( array_filter( array_map( 'trim', explode( ',', sanitize_text_field( $p['tags'] ?? '' ) ) ) ), 0, 8 ) );
        $dados  = array(
            'titulo' => $titulo, 'letra' => $letra, 'descricao' => $desc, 'tags' => $tags,
            'artista'    => mb_substr( sanitize_text_field( $p['artista'] ?? '' ), 0, 120 ),
            'compositor' => mb_substr( sanitize_text_field( $p['compositor'] ?? '' ), 0, 191 ),
        );
        if ( ! empty( $p['so_salvar'] ) ) {
            self::salvar( $e->id, $dados );
            self::responder( $e->id, 'Rascunho salvo. Você pode continuar depois.' );
        }
        if ( '' === $titulo )                                   { wp_send_json_error( array( 'message' => 'Informe o título da música.' ) ); }
        if ( '' === $dados['artista'] )                         { wp_send_json_error( array( 'message' => 'Informe o nome do artista ou dupla.' ) ); }
        if ( mb_strlen( $letra ) < CV_Fields::LETRA_MIN_CHARS ) { wp_send_json_error( array( 'message' => 'Cole a letra completa da música.' ) ); }
        if ( mb_strlen( $desc ) < 30 )                          { wp_send_json_error( array( 'message' => 'Escreva uma descrição com pelo menos uma frase (até 300 caracteres).' ) ); }
        $dados['status'] = 'enviado';
        $dados['etapa']  = 5;
        self::salvar( $e->id, $dados );
        self::avisar_admin( $e->id, 'Música enviada para aprovação' );
        self::responder( $e->id, '🎉 Música enviada! Assim que o contrato e o PIX forem conferidos, preparamos a página da sua música.' );
    }

    public static function ajax_excluir() {
        $e = self::envio_do_pedido();
        if ( 'rascunho' !== $e->status ) { wp_send_json_error( array( 'message' => 'Só é possível excluir um envio que ainda não foi enviado.' ) ); }
        self::apagar_arquivo( $e->contrato_arquivo );
        self::apagar_arquivo( $e->comprovante_arquivo );
        global $wpdb;
        $wpdb->delete( self::t(), array( 'id' => $e->id ), array( '%d' ) );
        wp_send_json_success( array( 'message' => 'Envio excluído.', 'html' => CV_Envio_Area::lista( get_current_user_id() ) ) );
    }

    // ════════════════════════════════════════════════════════════════
    // DOWNLOADS DO PARCEIRO: contrato-modelo preenchido e prompt de ajuda
    // ════════════════════════════════════════════════════════════════

    public static function baixar() {
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'cv_envio_nonce' ) || ! is_user_logged_in() ) {
            wp_die( 'Link expirado. Volte à Minha Área e tente de novo.', 'Canção Verdadeira', array( 'response' => 403 ) );
        }
        $e = self::envio( absint( $_GET['envio_id'] ?? 0 ) );
        if ( ! $e || ( (int) $e->user_id !== get_current_user_id() && ! current_user_can( 'manage_options' ) ) ) {
            wp_die( 'Envio não encontrado.', 'Canção Verdadeira', array( 'response' => 404 ) );
        }
        $doc = sanitize_key( $_GET['doc'] ?? '' );
        if ( 'contrato' === $doc ) {
            CV_Docx::enviar( 'contrato-divulgacao-' . sanitize_title( $e->artista ? $e->artista : 'parceiro' ) . '.docx', CV_Parceria_Docs::montar( 'contrato', self::dados_para_contrato( $e ) ) );
        }
        CV_Docx::enviar( 'prompt-youtube-' . sanitize_title( $e->titulo ? $e->titulo : 'musica' ) . '.docx', CV_Envio_Prompt::blocos( $e ) );
    }

    /** Dados da proposta (rodapé) + da conta, no formato que o contrato espera. */
    public static function dados_para_contrato( $e ) {
        $parc = $e->parceria_id ? $GLOBALS['wpdb']->get_row( $GLOBALS['wpdb']->prepare( "SELECT * FROM {$GLOBALS['wpdb']->prefix}cv_parcerias WHERE id = %d", $e->parceria_id ) ) : null;
        $u    = get_userdata( $e->user_id );
        return (object) array(
            'id'             => $parc ? (int) $parc->id : 0,
            'token'          => '',
            'nome'           => $parc ? $parc->nome : ( $u ? $u->display_name : '' ),
            'nome_artistico' => $e->artista ? $e->artista : ( $parc ? $parc->nome_artistico : '' ),
            'cidade_uf'      => $parc ? $parc->cidade_uf : '',
            'email'          => $u ? $u->user_email : '',
            'telefone'       => $parc ? $parc->telefone : '',
            'musica'         => $e->titulo ? $e->titulo : ( $parc ? $parc->musica : '' ),
            'link'           => $e->youtube_url,
        );
    }

    // ════════════════════════════════════════════════════════════════
    // AVISOS
    // ════════════════════════════════════════════════════════════════

    public static function avisar_admin( $id, $assunto ) {
        $e = self::envio( $id );
        $u = $e ? get_userdata( $e->user_id ) : null;
        if ( ! $e ) { return; }
        wp_mail(
            class_exists( 'CV_Apoio' ) ? CV_Apoio::email() : get_option( 'admin_email' ),
            '🎤 ' . $assunto . ' — envio #' . $e->id . ( $e->titulo ? ' (' . $e->titulo . ')' : '' ),
            sprintf( "%s.\n\nEnvio #%d de %s <%s>\nMúsica: %s\nArtista: %s\n\nConferir no painel: %s",
                $assunto, $e->id, $u ? $u->display_name : '—', $u ? $u->user_email : '—',
                $e->titulo ? $e->titulo : '—', $e->artista ? $e->artista : '—',
                admin_url( 'admin.php?page=cv-apoio&aba=envios' ) )
        );
    }
}

CV_Envio::init();
