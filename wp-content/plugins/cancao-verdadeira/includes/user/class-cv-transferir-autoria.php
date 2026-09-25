<?php
// cancao-verdadeira/includes/user/class-cv-transferir-autoria.php
// Criado em: 26/09/2026 (plugin v2.52.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Ferramenta "🔁 Transferir autoria" (painel → Usuários): passa TUDO o que é
// de um usuário para outro, sem excluir ninguém — posts, páginas, músicas,
// mídias, revisões e rascunhos (post_author) e os dados próprios do site
// (playlists, favoritos, notas, plays e comentários de trecho, via
// CV_Usuario_Exclusao::transferir). Pedido do Eduardo: trocar a autoria de
// "Admin" para "cv"; depois disso o Admin pode ser excluído sem perguntas.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Transferir_Autoria {

    public static function init() {
        add_action( 'wp_ajax_cv_transferir_autoria', array( __CLASS__, 'ajax' ) );
        add_action( 'admin_enqueue_scripts',         array( __CLASS__, 'enqueue' ) );
    }

    public static function enqueue() {
        if ( 'cv-users' !== sanitize_key( $_GET['page'] ?? '' ) ) { return; }
        wp_enqueue_script( 'cv-transferir', CV_PLUGIN_URL . 'assets/js/admin-transferir.js', array( 'jquery' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-transferir', 'window.cvTransf = ' . wp_json_encode( array(
            'nonce'   => wp_create_nonce( 'cv_admin_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) ) . ';', 'before' );
    }

    /** Quantos itens (de qualquer tipo, fora rascunhos automáticos) cada usuário tem. */
    private static function contagem() {
        global $wpdb;
        $c = array();
        foreach ( $wpdb->get_results( "SELECT post_author, COUNT(*) n FROM {$wpdb->posts} WHERE post_status <> 'auto-draft' GROUP BY post_author" ) as $r ) {
            $c[ (int) $r->post_author ] = (int) $r->n;
        }
        return $c;
    }

    /** Caixa mostrada na tela Usuários do painel. */
    public static function caixa() {
        $cont   = self::contagem();
        $todos  = get_users( array( 'orderby' => 'display_name', 'number' => 200 ) );
        $destin = get_users( array( 'role__in' => array( 'administrator', 'editor' ), 'orderby' => 'display_name' ) );
        ob_start();
        ?>
        <div class="cv-section" id="cv-transferir" style="margin-top:24px;border:1px solid #F2A51A;background:#FFFBF2">
            <h2 class="cv-section-title">🔁 Transferir autoria</h2>
            <p style="font-size:13px;color:#6B4C3B;margin-top:0">
                Passa <strong>tudo</strong> de um usuário para outro: posts, páginas, músicas, imagens, rascunhos e revisões, além das playlists, favoritos, notas e plays.
                <strong>Ninguém é excluído.</strong> Depois de transferir, o usuário antigo fica sem conteúdo e pode ser excluído com segurança em Usuários (WordPress).
            </p>
            <div style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end">
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600">De (quem sai)
                    <select id="cv-transf-de" class="cv-input" style="min-width:240px">
                        <?php foreach ( $todos as $u ) : ?>
                        <option value="<?php echo (int) $u->ID; ?>" <?php selected( 'admin' === strtolower( $u->user_login ) ); ?>><?php echo esc_html( $u->display_name . ' (' . $u->user_login . ') — ' . ( $cont[ $u->ID ] ?? 0 ) . ' itens' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <span style="font-size:22px;padding-bottom:6px">→</span>
                <label style="display:flex;flex-direction:column;gap:4px;font-weight:600">Para (quem assume)
                    <select id="cv-transf-para" class="cv-input" style="min-width:240px">
                        <?php foreach ( $destin as $u ) : ?>
                        <option value="<?php echo (int) $u->ID; ?>" <?php selected( 'cv' === strtolower( $u->user_login ) ); ?>><?php echo esc_html( $u->display_name . ' (' . $u->user_login . ')' ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" id="cv-transf-btn" class="cv-btn cv-btn-primary">🔁 Transferir tudo</button>
            </div>
            <div id="cv-transf-msg" style="display:none;margin-top:12px;padding:10px 14px;border-radius:8px"></div>
        </div>
        <?php
        return ob_get_clean();
    }

    public static function ajax() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
        $de   = absint( $_POST['de'] ?? 0 );
        $para = absint( $_POST['para'] ?? 0 );
        $r = self::transferir( $de, $para );
        if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
        wp_send_json_success( array( 'message' => sprintf(
            '%d item(ns) do site passaram para %s%s. O usuário de origem ficou sem conteúdo e já pode ser excluído em Usuários (WordPress).',
            $r['posts'], get_userdata( $para )->display_name,
            $r['dados'] ? ' (e também ' . $r['dados'] . ' registros de playlists, favoritos, notas e plays)' : ''
        ) ) );
    }

    /** Faz a transferência. Devolve array( posts, dados ) ou WP_Error. */
    public static function transferir( $de, $para ) {
        $u_de   = get_userdata( $de );
        $u_para = get_userdata( $para );
        if ( ! $u_de || ! $u_para ) { return new WP_Error( 'usuario', 'Escolha os dois usuários.' ); }
        if ( $de === $para )        { return new WP_Error( 'mesmo', 'Escolha usuários diferentes.' ); }
        if ( ! user_can( $para, 'edit_posts' ) ) { return new WP_Error( 'papel', 'Quem assume precisa ser administrador ou editor.' ); }

        global $wpdb;
        $ids = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_author = %d", $de ) );
        $n   = (int) $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->posts} SET post_author = %d WHERE post_author = %d", $para, $de ) );
        foreach ( $ids as $id ) { clean_post_cache( (int) $id ); }

        $dados = 0;
        if ( class_exists( 'CV_Usuario_Exclusao' ) ) {
            $dados = array_sum( CV_Usuario_Exclusao::transferir( $de, $para ) );
        }
        if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
        return array( 'posts' => $n, 'dados' => (int) $dados );
    }
}

CV_Transferir_Autoria::init();
