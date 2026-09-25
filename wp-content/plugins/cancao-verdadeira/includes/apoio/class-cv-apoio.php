<?php
// cancao-verdadeira/includes/apoio/class-cv-apoio.php
// Criado em: 25/09/2026 (plugin v2.48.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// "Apoie a Canção Verdadeira" — só no rodapé do site, com duas janelas:
// 1) 🤝 Seja nosso parceiro: formulário para enviar músicas para divulgar,
//    propor gravar uma música do catálogo ou outra proposta. Grava em
//    cv_parcerias e avisa por e-mail (anti-spam: campo-isca + 3 envios/hora).
// 2) 💛 Seja nosso colaborador: doação de qualquer valor por PIX (CV_Pix).
// Painel: admin.php?page=cv-apoio, abas "💠 Conta PIX" e "🤝 Parcerias".
// HTML do painel em includes/apoio/views/; JS em assets/js/cv-apoio.js
// (rodapé) e assets/js/admin-apoio.js (painel).
// v2.49.0: o parceiro informa também nome artístico, cidade/UF e o título da
// música; depois do envio aparecem "📄 Nossas propostas" e "📝 Modelo de
// contrato" (só para divulgação), na tela e em Word (CV_Parceria_Docs).
// Nova aba no painel: "📄 Propostas e contrato".
// v2.50.0: aba "🎤 Envios de música" (CV_Envio_Admin, views/envios.php).
// v2.51.0: caixa "Apoie" também na Minha Área (caixa_area), com os dois botões
// grandes — abrem as mesmas janelas do rodapé.
// v2.52.0: 3º botão "🎤 Enviar minha música" (abre a aba de envio ou explica
// que primeiro vem a proposta de parceria).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Apoio {

    const EMAIL_PADRAO     = 'cancaoverdadeira@gmail.com';
    const LIMITE_POR_HORA  = 3;

    public static function tipos_parceria() {
        return array(
            'divulgar' => '🎵 Quero enviar minhas músicas para divulgação',
            'gravar'   => '🎤 Quero gravar uma música da Canção Verdadeira',
            'outro'    => '💬 Outra proposta',
        );
    }

    public static function status_parceria() {
        return array(
            'nova'      => '🆕 Nova',
            'conversa'  => '💬 Em conversa',
            'aceita'    => '✅ Aceita',
            'recusada'  => '✖ Recusada',
        );
    }

    public static function email() {
        $e = get_option( 'cv_apoio_email', self::EMAIL_PADRAO );
        return is_email( $e ) ? $e : self::EMAIL_PADRAO;
    }

    public static function init() {
        add_action( 'wp_ajax_cv_parceria_enviar',        array( __CLASS__, 'ajax_parceria' ) );
        add_action( 'wp_ajax_nopriv_cv_parceria_enviar', array( __CLASS__, 'ajax_parceria' ) );
        foreach ( array( 'pix_salvar', 'parceria_status', 'parceria_excluir', 'email' ) as $acao ) {
            add_action( 'wp_ajax_cv_apoio_' . $acao, array( __CLASS__, 'ajax_' . $acao ) );
        }
        add_action( 'wp_enqueue_scripts',    array( __CLASS__, 'enqueue' ), 31 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ) );
    }

    public static function enqueue() {
        wp_enqueue_style( 'cv-apoio', CV_PLUGIN_URL . 'assets/css/cv-apoio.css', array( 'cv-pix' ), CV_VERSION );
        wp_enqueue_script( 'cv-apoio', CV_PLUGIN_URL . 'assets/js/cv-apoio.js', array( 'jquery', 'cv-pix' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-apoio', 'window.cvApoio = ' . wp_json_encode( array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cv_parceria_nonce' ),
        ) ) . ';', 'before' );
    }

    public static function enqueue_admin() {
        if ( 'cv-apoio' !== sanitize_key( $_GET['page'] ?? '' ) ) { return; }
        wp_enqueue_script( 'cv-admin-apoio', CV_PLUGIN_URL . 'assets/js/admin-apoio.js', array( 'jquery', 'cv-pix' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-admin-apoio', 'window.cvApoioAdmin = ' . wp_json_encode( array(
            'nonce'   => wp_create_nonce( 'cv_admin_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) ) . ';', 'before' );
    }

    // ════════════════════════════════════════════════════════════════
    // RODAPÉ (chamado pelo tema em template-parts/footer-content.php)
    // ════════════════════════════════════════════════════════════════

    /** Coluna "Apoie" do rodapé: os dois botões que abrem as janelas. */
    public static function coluna_rodape() {
        ob_start();
        ?>
        <div class="cv-apoio-coluna">
            <h3 class="cv-footer-col-title">Apoie</h3>
            <ul class="cv-footer-links">
                <li><button type="button" class="cv-apoio-abrir" data-janela="cv-janela-parceiro">🤝 Seja nosso parceiro</button></li>
                <li><button type="button" class="cv-apoio-abrir" data-janela="cv-janela-colaborador">💛 Seja nosso colaborador</button></li>
            </ul>
        </div>
        <?php
        return ob_get_clean();
    }

    /** Caixa da Minha Área com os dois botões grandes (mesmas janelas do rodapé). */
    public static function caixa_area() {
        ob_start();
        ?>
        <section class="cv-apoio-area" aria-label="Apoie a Canção Verdadeira">
            <div class="cv-apoio-area-texto">
                <strong>💛 Apoie a Canção Verdadeira</strong>
                <span>Divulgue sua música com a gente ou ajude com qualquer valor por PIX.</span>
            </div>
            <div class="cv-apoio-area-botoes">
                <button type="button" class="cv-btn cv-btn-secondary cv-apoio-abrir" data-janela="cv-janela-parceiro">🤝 Seja nosso parceiro</button>
                <button type="button" class="cv-btn cv-btn-primary cv-apoio-abrir" data-janela="cv-janela-colaborador">💛 Seja nosso colaborador</button>
                <?php // v2.52.0: atalho para a aba de envio (ou explicação, se ainda não é parceiro) ?>
                <?php if ( class_exists( 'CV_Envio' ) && CV_Envio::pode_ver( get_current_user_id() ) ) : ?>
                <a class="cv-btn cv-btn-secondary" href="<?php echo esc_url( add_query_arg( 'aba', 'enviar', home_url( '/minha-area/' ) ) ); ?>" onclick="var t=document.querySelector('.cv-dash-tab[data-tab=enviar]');if(t){t.click();t.scrollIntoView({behavior:'smooth'});return false;}">🎤 Enviar minha música</a>
                <?php else : ?>
                <button type="button" class="cv-btn cv-btn-secondary cv-apoio-abrir" data-janela="cv-janela-parceiro" title="Primeiro envie a proposta; depois a aba de envio aparece aqui">🎤 Enviar minha música</button>
                <?php endif; ?>
            </div>
            <?php if ( ! ( class_exists( 'CV_Envio' ) && CV_Envio::pode_ver( get_current_user_id() ) ) ) : ?>
            <p class="cv-apoio-area-nota">🎤 Para enviar sua música: primeiro mande a proposta em "Seja nosso parceiro" (com o mesmo e-mail desta conta). Depois aparece a aba <strong>"Enviar música"</strong> aqui na sua Minha Área.</p>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    /** As duas janelas (<dialog>), impressas uma vez no fim da página. */
    public static function janelas() {
        $pix_ok = CV_Pix::ativo();
        ob_start();
        ?>
        <dialog id="cv-janela-parceiro" class="cv-janela" aria-labelledby="cv-janela-parceiro-titulo">
            <button type="button" class="cv-janela-fechar" aria-label="Fechar">✕</button>
            <h2 id="cv-janela-parceiro-titulo">🤝 Seja nosso parceiro</h2>
            <p class="cv-janela-intro">Você é cantor, dupla ou compositor? Envie suas músicas para divulgarmos, ou proponha gravar uma das canções da Canção Verdadeira. Respondemos por e-mail ou WhatsApp.</p>
            <form id="cv-parceria-form" class="cv-janela-form" novalidate>
                <fieldset class="cv-janela-tipos">
                    <legend>O que você propõe?</legend>
                    <?php $i = 0; foreach ( self::tipos_parceria() as $v => $l ) : ?>
                    <label class="cv-janela-opcao"><input type="radio" name="tipo" value="<?php echo esc_attr( $v ); ?>" <?php checked( 0 === $i++ ); ?>> <?php echo esc_html( $l ); ?></label>
                    <?php endforeach; ?>
                </fieldset>
                <div class="cv-janela-grade">
                    <label>Seu nome completo *<input type="text" name="nome" maxlength="120" required autocomplete="name"></label>
                    <label>Nome artístico<input type="text" name="nome_artistico" maxlength="120" placeholder="Como você é conhecido(a)"></label>
                    <label>E-mail *<input type="email" name="email" maxlength="191" required autocomplete="email"></label>
                    <label>WhatsApp<input type="tel" name="telefone" maxlength="30" autocomplete="tel" placeholder="(31) 99999-9999"></label>
                    <label>Cidade / UF<input type="text" name="cidade_uf" maxlength="80" placeholder="Ex.: Divinópolis/MG"></label>
                    <label>Título da música<input type="text" name="musica" maxlength="191" placeholder="Nome da música a divulgar"></label>
                    <label class="cv-janela-cheio-grade">Link da música ou do seu trabalho<input type="url" name="link" maxlength="255" placeholder="YouTube, Instagram, Google Drive…"></label>
                </div>
                <label class="cv-janela-cheio">Conte um pouco sobre você e a proposta *<textarea name="mensagem" rows="4" maxlength="2000" required></textarea></label>
                <label class="cv-janela-isca" aria-hidden="true">Site<input type="text" name="site" tabindex="-1" autocomplete="off"></label>
                <label class="cv-janela-aceite"><input type="checkbox" name="aceite" value="1" required> Autorizo a Canção Verdadeira a guardar estes dados para responder à minha proposta (LGPD).</label>
                <div class="cv-janela-msg" role="status" aria-live="polite"></div>
                <button type="submit" class="cv-btn cv-btn-primary cv-janela-enviar">Enviar proposta</button>
            </form>
            <div id="cv-parceria-docs" class="cv-parceria-docs" hidden>
                <p class="cv-janela-msg is-ok" id="cv-parceria-ok"></p>
                <p class="cv-janela-intro">Veja abaixo as nossas propostas e o modelo de contrato, já com os seus dados. Você pode ler aqui e baixar em Word.</p>
                <div class="cv-parceria-botoes">
                    <button type="button" class="cv-btn cv-btn-primary cv-parceria-doc" data-doc="propostas">📄 Nossas propostas</button>
                    <button type="button" class="cv-btn cv-btn-primary cv-parceria-doc" data-doc="contrato" id="cv-parceria-btn-contrato">📝 Modelo de contrato</button>
                </div>
                <p class="cv-parceria-sem-contrato" hidden>O contrato de gravação ou de outras propostas é combinado caso a caso: entraremos em contato.</p>
                <div id="cv-parceria-doc-area" class="cv-parceria-doc-area" hidden>
                    <div class="cv-parceria-doc-texto" tabindex="0"></div>
                    <a href="#" class="cv-btn cv-btn-primary cv-parceria-baixar" download>⬇️ Baixar em Word</a>
                </div>
                <p class="cv-parceria-validade">Os links para baixar ficam disponíveis por 7 dias.</p>
            </div>
        </dialog>

        <dialog id="cv-janela-colaborador" class="cv-janela" aria-labelledby="cv-janela-colaborador-titulo">
            <button type="button" class="cv-janela-fechar" aria-label="Fechar">✕</button>
            <h2 id="cv-janela-colaborador-titulo">💛 Seja nosso colaborador</h2>
            <p class="cv-janela-intro">A Canção Verdadeira é feita com carinho e sem patrocínio. Com qualquer valor, por PIX, você ajuda a gravar novas músicas e a manter o site no ar. Muito obrigado!</p>
            <?php if ( $pix_ok ) : ?>
            <div class="cv-doacao-valores" role="group" aria-label="Escolha um valor">
                <?php foreach ( array( 10, 20, 50, 100 ) as $v ) : ?>
                <button type="button" class="cv-doacao-valor" data-valor="<?php echo (int) $v; ?>">R$ <?php echo (int) $v; ?></button>
                <?php endforeach; ?>
            </div>
            <div class="cv-doacao-outro">
                <label>Outro valor (R$)<input type="text" inputmode="decimal" id="cv-doacao-valor" placeholder="Ex.: 15,00"></label>
                <button type="button" class="cv-btn cv-btn-primary" id="cv-doacao-gerar">Gerar PIX</button>
            </div>
            <p class="cv-doacao-livre"><button type="button" class="cv-link-botao" id="cv-doacao-sem-valor">Prefiro digitar o valor no meu banco</button></p>
            <div id="cv-doacao-resultado" aria-live="polite"></div>
            <?php else : ?>
            <p class="cv-janela-msg is-info">As doações por PIX estarão disponíveis em breve. Obrigado pelo carinho! 💛</p>
            <?php endif; ?>
        </dialog>
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // AJAX — formulário do parceiro (público)
    // ════════════════════════════════════════════════════════════════

    public static function ajax_parceria() {
        check_ajax_referer( 'cv_parceria_nonce', 'nonce' );
        $post = wp_unslash( $_POST );

        // Campo-isca: pessoas não veem, robôs preenchem.
        if ( ! empty( $post['site'] ) ) { wp_send_json_success( array( 'message' => 'Obrigado! Recebemos sua proposta.' ) ); }

        $chave = 'cv_parceria_' . md5( isset( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : '' );
        $envios = (int) get_transient( $chave );
        if ( $envios >= self::LIMITE_POR_HORA ) {
            wp_send_json_error( array( 'message' => 'Recebemos várias propostas deste aparelho. Tente de novo daqui a uma hora.' ) );
        }

        $tipos    = self::tipos_parceria();
        $tipo     = isset( $post['tipo'], $tipos[ $post['tipo'] ] ) ? $post['tipo'] : 'outro';
        $nome     = mb_substr( sanitize_text_field( $post['nome'] ?? '' ), 0, 120 );
        $email    = sanitize_email( $post['email'] ?? '' );
        $telefone = mb_substr( preg_replace( '/[^0-9()+\- ]/', '', $post['telefone'] ?? '' ), 0, 30 );
        $artistico = mb_substr( sanitize_text_field( $post['nome_artistico'] ?? '' ), 0, 120 );
        $cidade_uf = mb_substr( sanitize_text_field( $post['cidade_uf'] ?? '' ), 0, 80 );
        $musica    = mb_substr( sanitize_text_field( $post['musica'] ?? '' ), 0, 191 );
        $token     = wp_generate_password( 32, false, false ); // chave do link de download (7 dias)
        $link     = esc_url_raw( $post['link'] ?? '', array( 'http', 'https' ) );
        $mensagem = mb_substr( sanitize_textarea_field( $post['mensagem'] ?? '' ), 0, 2000 );

        if ( '' === $nome )             { wp_send_json_error( array( 'message' => 'Informe seu nome.' ) ); }
        if ( ! is_email( $email ) )     { wp_send_json_error( array( 'message' => 'Informe um e-mail válido.' ) ); }
        if ( mb_strlen( $mensagem ) < 10 ) { wp_send_json_error( array( 'message' => 'Conte um pouco mais sobre a proposta.' ) ); }
        if ( empty( $post['aceite'] ) ) { wp_send_json_error( array( 'message' => 'Marque a autorização para guardarmos seus dados.' ) ); }

        global $wpdb;
        $agora = current_time( 'mysql' );
        $ok = $wpdb->insert( $wpdb->prefix . 'cv_parcerias', array(
            'nome' => $nome, 'email' => $email, 'telefone' => $telefone,
            'nome_artistico' => $artistico, 'cidade_uf' => $cidade_uf, 'musica' => $musica, 'token' => $token,
            'tipo' => $tipo, 'link' => $link, 'mensagem' => $mensagem, 'status' => 'nova',
            'consentimento_em' => $agora, 'criado_em' => $agora,
        ), array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
        $id = (int) $wpdb->insert_id;
        if ( ! $ok ) { wp_send_json_error( array( 'message' => 'Não foi possível enviar agora. Tente de novo.' ) ); }
        set_transient( $chave, $envios + 1, HOUR_IN_SECONDS );

        wp_mail(
            self::email(),
            '🤝 Nova proposta de parceria: ' . $nome,
            sprintf(
                "Nova proposta pelo rodapé do site.\n\nTipo: %s\nNome: %s\nNome artístico: %s\nCidade/UF: %s\nMúsica: %s\nE-mail: %s\nWhatsApp: %s\nLink: %s\n\nMensagem:\n%s\n\nVer todas: %s",
                wp_strip_all_tags( $tipos[ $tipo ] ), $nome, $artistico ? $artistico : '—', $cidade_uf ? $cidade_uf : '—', $musica ? $musica : '—',
                $email, $telefone ? $telefone : '—', $link ? $link : '—', $mensagem,
                admin_url( 'admin.php?page=cv-apoio&aba=parcerias' )
            ),
            array( 'Reply-To: ' . $nome . ' <' . $email . '>' )
        );
        wp_send_json_success( array(
            'message'  => 'Obrigado, ' . $nome . '! Recebemos sua proposta e vamos responder em breve. 🎶',
            'id'       => $id,
            't'        => $token,
            'contrato' => 'divulgar' === $tipo,
        ) );
    }

    // ════════════════════════════════════════════════════════════════
    // PAINEL
    // ════════════════════════════════════════════════════════════════

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        global $wpdb;
        $abas = array( 'pix' => '💠 Conta PIX', 'parcerias' => '🤝 Parcerias', 'envios' => '🎤 Envios de música', 'documentos' => '📄 Propostas e contrato' );
        $a_revisar = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_envios WHERE status = 'enviado'" );
        $aba  = sanitize_key( $_GET['aba'] ?? 'pix' );
        if ( ! isset( $abas[ $aba ] ) ) { $aba = 'pix'; }
        $novas = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_parcerias WHERE status = 'nova'" );
        wp_enqueue_style( 'cv-admin-estoque', CV_PLUGIN_URL . 'assets/css/admin-estoque.css', array(), CV_VERSION ); // abas e selos
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>💠 PIX e Parcerias</h1>
                <p class="cv-admin-subtitle">Conta PIX (pedidos e doações) e propostas do "Seja nosso parceiro"</p>
            </div>
            <div id="cv-apoio-msg" class="cv-action-message" style="display:none"></div>
            <nav class="cv-est-abas" aria-label="Abas">
                <?php foreach ( $abas as $id => $rotulo ) : ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-apoio&aba=' . $id ) ); ?>" class="cv-est-aba<?php echo $aba === $id ? ' is-ativa' : ''; ?>"><?php echo esc_html( $rotulo ); ?><?php echo ( 'parcerias' === $id && $novas ) ? '<span class="cv-est-badge">' . $novas . '</span>' : ''; ?><?php echo ( 'envios' === $id && $a_revisar ) ? '<span class="cv-est-badge">' . $a_revisar . '</span>' : ''; ?></a>
                <?php endforeach; ?>
            </nav>
            <?php include CV_PLUGIN_DIR . 'includes/apoio/views/' . $aba . '.php'; ?>
        </div>
        <?php
    }

    private static function so_admin() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
    }

    public static function ajax_pix_salvar() {
        self::so_admin();
        $r = CV_Pix::salvar_config( wp_unslash( $_POST ) );
        if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
        wp_send_json_success( array( 'message' => 'Conta PIX salva.' ) );
    }

    public static function ajax_parceria_status() {
        self::so_admin();
        $st = sanitize_key( $_POST['status'] ?? '' );
        if ( ! array_key_exists( $st, self::status_parceria() ) ) { wp_send_json_error( array( 'message' => 'Situação inválida.' ) ); }
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'cv_parcerias', array( 'status' => $st ), array( 'id' => absint( $_POST['id'] ?? 0 ) ), array( '%s' ), array( '%d' ) );
        wp_send_json_success( array( 'message' => 'Situação atualizada.' ) );
    }

    public static function ajax_parceria_excluir() {
        self::so_admin();
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cv_parcerias', array( 'id' => absint( $_POST['id'] ?? 0 ) ), array( '%d' ) );
        wp_send_json_success( array( 'message' => 'Proposta excluída.' ) );
    }

    public static function ajax_email() {
        self::so_admin();
        $e = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email( $e ) ) { wp_send_json_error( array( 'message' => 'E-mail inválido.' ) ); }
        update_option( 'cv_apoio_email', $e, false );
        wp_send_json_success( array( 'message' => 'E-mail dos avisos salvo.' ) );
    }
}

CV_Apoio::init();
