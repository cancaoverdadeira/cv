<?php
// cancao-verdadeira/includes/public/class-cv-depoimentos.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : "💬 Depoimentos dos ouvintes" (v2.55.0, item 2.5 da lista)
// Guarda  : nos comentários nativos do WordPress, tipo "cv_depoimento"
//           (cidade no meta "cv_cidade"). Todo depoimento chega AGUARDANDO
//           (comment_approved = 0) e só aparece no site depois de aprovado.
//           Apagar a música apaga os depoimentos dela (o WordPress já faz).
// Site    : AJAX "cv_depoimento_enviar" (com e sem login): nonce, campo-isca,
//           3 envios por hora por aparelho, 15 a 600 letras, autorização.
//           O tema mostra em template-parts/musica/depoimentos.php.
// Painel  : tela "cv-depoimentos" (Aguardando / Aprovados) com Aprovar,
//           Voltar para aguardando e Excluir (admin-post, com nonce).
//           Aviso por e-mail a cada depoimento novo; aprovar limpa o cache.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Depoimentos {

    const TIPO            = 'cv_depoimento';
    const META_CIDADE     = 'cv_cidade';
    const NONCE           = 'cv_depoimento_nonce';
    const LIMITE_POR_HORA = 3;
    const MIN_LETRAS      = 15;
    const MAX_LETRAS      = 600;

    public static function init() {
        add_action( 'wp_ajax_cv_depoimento_enviar',        array( __CLASS__, 'ajax_enviar' ) );
        add_action( 'wp_ajax_nopriv_cv_depoimento_enviar', array( __CLASS__, 'ajax_enviar' ) );
        add_action( 'admin_post_cv_depoimento_acao',       array( __CLASS__, 'acao_painel' ) );
        // Não conta depoimentos no "número de comentários" da música
        add_filter( 'pre_wp_update_comment_count_now', array( __CLASS__, 'contagem_sem_depoimentos' ), 10, 3 );
    }

    // ── Leitura (usada pelo tema e pelo painel) ───────────────────

    /** Depoimentos aprovados de uma música, do mais novo ao mais antigo. */
    public static function aprovados( $music_id, $limite = 12 ) {
        return get_comments( array(
            'post_id' => (int) $music_id,
            'type'    => self::TIPO,
            'status'  => 'approve',
            'number'  => (int) $limite,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        ) );
    }

    public static function contar( $status = 'hold', $music_id = 0 ) {
        $args = array( 'type' => self::TIPO, 'status' => $status, 'count' => true );
        if ( $music_id ) { $args['post_id'] = (int) $music_id; }
        return (int) get_comments( $args );
    }

    public static function cidade( $comment ) {
        return (string) get_comment_meta( $comment->comment_ID, self::META_CIDADE, true );
    }

    public static function contagem_sem_depoimentos( $novo, $antigo, $post_id ) {
        if ( null !== $novo || 'musica' !== get_post_type( $post_id ) ) { return $novo; }
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->comments} WHERE comment_post_ID = %d AND comment_approved = '1' AND comment_type <> %s",
            $post_id, self::TIPO
        ) );
    }

    // ── Envio pelo site ───────────────────────────────────────────

    public static function ajax_enviar() {
        check_ajax_referer( self::NONCE, 'nonce' );
        $post = wp_unslash( $_POST );

        // Campo-isca: pessoas não veem, robôs preenchem.
        if ( ! empty( $post['site'] ) ) { wp_send_json_success( array( 'message' => 'Obrigado! Seu depoimento vai aparecer depois de aprovado.' ) ); }

        $ip    = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : '';
        $chave = 'cv_depo_' . md5( $ip );
        $envios = (int) get_transient( $chave );
        if ( $envios >= self::LIMITE_POR_HORA ) {
            wp_send_json_error( array( 'message' => 'Recebemos vários depoimentos deste aparelho. Tente de novo daqui a uma hora.' ) );
        }

        $music_id = absint( $post['music_id'] ?? 0 );
        $texto    = trim( sanitize_textarea_field( $post['texto'] ?? '' ) );
        $nome     = mb_substr( trim( sanitize_text_field( $post['nome'] ?? '' ) ), 0, 60 );
        $cidade   = mb_substr( trim( sanitize_text_field( $post['cidade'] ?? '' ) ), 0, 60 );

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) || 'publish' !== get_post_status( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'Música não encontrada.' ) );
        }
        if ( mb_strlen( $texto ) < self::MIN_LETRAS ) { wp_send_json_error( array( 'message' => 'Conte um pouquinho mais (pelo menos ' . self::MIN_LETRAS . ' letras).' ) ); }
        if ( mb_strlen( $texto ) > self::MAX_LETRAS ) { wp_send_json_error( array( 'message' => 'O depoimento pode ter até ' . self::MAX_LETRAS . ' letras.' ) ); }
        if ( '' === $nome )             { wp_send_json_error( array( 'message' => 'Informe seu nome.' ) ); }
        if ( empty( $post['aceite'] ) ) { wp_send_json_error( array( 'message' => 'Marque a autorização para publicarmos o depoimento.' ) ); }

        $user = wp_get_current_user();
        $id = wp_insert_comment( array(
            'comment_post_ID'      => $music_id,
            'comment_type'         => self::TIPO,
            'comment_content'      => $texto,
            'comment_author'       => $nome,
            'comment_author_email' => $user->exists() ? $user->user_email : '',
            'comment_author_IP'    => $ip,
            'comment_agent'        => mb_substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' ), 0, 254 ),
            'user_id'              => $user->exists() ? (int) $user->ID : 0,
            'comment_approved'     => 0,
        ) );
        if ( ! $id ) { wp_send_json_error( array( 'message' => 'Não foi possível enviar agora. Tente de novo.' ) ); }
        if ( '' !== $cidade ) { add_comment_meta( $id, self::META_CIDADE, $cidade, true ); }
        set_transient( $chave, $envios + 1, HOUR_IN_SECONDS );

        $para = class_exists( 'CV_Apoio' ) ? CV_Apoio::email() : get_option( 'admin_email' );
        wp_mail(
            $para,
            '💬 Novo depoimento: ' . get_the_title( $music_id ),
            sprintf(
                "Chegou um depoimento novo no site (aguardando sua aprovação).\n\nMúsica: %s\nNome: %s\nCidade: %s\n\n%s\n\nAprovar ou excluir: %s",
                get_the_title( $music_id ), $nome, $cidade ? $cidade : '—', $texto,
                admin_url( 'admin.php?page=cv-depoimentos' )
            )
        );

        wp_send_json_success( array( 'message' => 'Obrigado, ' . $nome . '! 💛 Seu depoimento vai aparecer aqui depois de aprovado.' ) );
    }

    // ── Painel ────────────────────────────────────────────────────

    private static function link_acao( $acao, $id, $aba ) {
        return wp_nonce_url(
            admin_url( 'admin-post.php?action=cv_depoimento_acao&faz=' . $acao . '&id=' . (int) $id . '&aba=' . $aba ),
            'cv_depo_' . $acao . '_' . (int) $id
        );
    }

    public static function acao_painel() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
        $acao = sanitize_key( $_GET['faz'] ?? '' );
        $id   = absint( $_GET['id'] ?? 0 );
        $aba  = 'aprovados' === sanitize_key( $_GET['aba'] ?? '' ) ? 'aprovados' : 'aguardando';
        check_admin_referer( 'cv_depo_' . $acao . '_' . $id );

        $c = get_comment( $id );
        if ( ! $c || self::TIPO !== $c->comment_type ) { wp_die( 'Depoimento não encontrado.' ); }
        $post_id = (int) $c->comment_post_ID;
        $msg = '';
        if ( 'aprovar' === $acao )  { wp_set_comment_status( $id, 'approve' ); $msg = 'aprovado'; }
        if ( 'segurar' === $acao )  { wp_set_comment_status( $id, 'hold' );    $msg = 'segurado'; }
        if ( 'excluir' === $acao )  { wp_delete_comment( $id, true );          $msg = 'excluido'; }

        // Página da música em cache (WP Rocket) mostraria a lista antiga
        if ( class_exists( 'CV_Litespeed' ) ) { CV_Litespeed::limpar_post( $post_id ); }

        wp_safe_redirect( admin_url( 'admin.php?page=cv-depoimentos&aba=' . $aba . '&feito=' . $msg ) );
        exit;
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $aba = 'aprovados' === sanitize_key( $_GET['aba'] ?? '' ) ? 'aprovados' : 'aguardando';
        $feito = sanitize_key( $_GET['feito'] ?? '' );
        $avisos = array( 'aprovado' => '✅ Depoimento aprovado: já aparece na página da música.', 'segurado' => '↩ Depoimento voltou para "Aguardando" e saiu do site.', 'excluido' => '🗑 Depoimento excluído.' );
        $aguardando = self::contar( 'hold' );
        $lista = get_comments( array(
            'type'    => self::TIPO,
            'status'  => 'aprovados' === $aba ? 'approve' : 'hold',
            'number'  => 200,
            'orderby' => 'comment_date_gmt',
            'order'   => 'DESC',
        ) );
        wp_enqueue_style( 'cv-admin-estoque', CV_PLUGIN_URL . 'assets/css/admin-estoque.css', array(), CV_VERSION ); // abas e selos
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>💬 Depoimentos dos ouvintes</h1>
                <p class="cv-admin-subtitle">O que as pessoas contaram sobre cada música. Só aparece no site o que você aprovar.</p>
            </div>
            <?php if ( isset( $avisos[ $feito ] ) ) : ?>
            <div class="cv-action-message" style="display:block"><?php echo esc_html( $avisos[ $feito ] ); ?></div>
            <?php endif; ?>
            <nav class="cv-est-abas" aria-label="Abas">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-depoimentos' ) ); ?>" class="cv-est-aba<?php echo 'aguardando' === $aba ? ' is-ativa' : ''; ?>">⏳ Aguardando<?php echo $aguardando ? '<span class="cv-est-badge">' . (int) $aguardando . '</span>' : ''; ?></a>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-depoimentos&aba=aprovados' ) ); ?>" class="cv-est-aba<?php echo 'aprovados' === $aba ? ' is-ativa' : ''; ?>">✅ Aprovados</a>
            </nav>
            <div class="cv-section">
                <table class="cv-table">
                    <thead><tr><th>Data</th><th>Música</th><th>Pessoa</th><th>Depoimento</th><th style="text-align:center">Ações</th></tr></thead>
                    <tbody>
                    <?php if ( empty( $lista ) ) : ?>
                    <tr><td colspan="5" style="text-align:center;color:#8A6A55;padding:24px">
                        <?php echo 'aprovados' === $aba ? 'Nenhum depoimento aprovado ainda.' : 'Nenhum depoimento esperando. Eles chegam pelo "✍️ Contar o que senti" da página de cada música.'; ?>
                    </td></tr>
                    <?php endif; ?>
                    <?php foreach ( $lista as $c ) : $cidade = self::cidade( $c ); ?>
                    <tr>
                        <td style="white-space:nowrap"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $c->comment_date ) ); ?></td>
                        <td><a href="<?php echo esc_url( get_permalink( $c->comment_post_ID ) ); ?>" target="_blank" rel="noopener"><?php echo esc_html( get_the_title( $c->comment_post_ID ) ); ?></a></td>
                        <td><strong><?php echo esc_html( $c->comment_author ); ?></strong>
                            <?php if ( $cidade ) : ?><br><span style="font-size:12px;color:#8A6A55">📍 <?php echo esc_html( $cidade ); ?></span><?php endif; ?>
                            <?php if ( $c->user_id ) : ?><br><span style="font-size:12px;color:#8A6A55">👤 tem conta no site</span><?php endif; ?>
                        </td>
                        <td style="font-size:14px;color:#3B2418;max-width:460px;white-space:pre-line"><?php echo esc_html( $c->comment_content ); ?></td>
                        <td style="text-align:center;white-space:nowrap">
                            <?php if ( 'aguardando' === $aba ) : ?>
                            <a class="button button-primary" href="<?php echo esc_url( self::link_acao( 'aprovar', $c->comment_ID, $aba ) ); ?>">✅ Aprovar</a>
                            <?php else : ?>
                            <a class="button" href="<?php echo esc_url( self::link_acao( 'segurar', $c->comment_ID, $aba ) ); ?>">↩ Voltar para aguardando</a>
                            <?php endif; ?>
                            <a class="button" style="color:#C0392B" href="<?php echo esc_url( self::link_acao( 'excluir', $c->comment_ID, $aba ) ); ?>"
                               onclick="return confirm('Excluir este depoimento para sempre?');">🗑 Excluir</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }
}

CV_Depoimentos::init();
