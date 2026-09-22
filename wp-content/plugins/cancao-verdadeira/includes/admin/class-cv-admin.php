<?php
// cancao-verdadeira-plugin/includes/admin/class-cv-admin.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Classe base do painel administrativo: carrega assets do admin,
// registra opções e expõe funções auxiliares usadas pelas páginas admin.
// v1.9.2 — inclui ajax_delete_subscriber corretamente dentro da classe.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin {

    public static function init() {
        // AJAX: limpar cache dos gráficos
        add_action( 'wp_ajax_cv_clear_charts_cache', array( __CLASS__, 'ajax_clear_charts_cache' ) );
        add_action( 'wp_ajax_cv_clear_seo_cache', array( __CLASS__, 'ajax_clear_seo_cache' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_head',            array( __CLASS__, 'dark_mode_nova_musica' ) );
        add_action( 'admin_init',            array( __CLASS__, 'register_settings' ) );
        add_action( 'wp_ajax_cv_recalculate_ranking',       array( __CLASS__, 'ajax_recalculate' ) );
        add_action( 'wp_ajax_cv_clear_cache',               array( __CLASS__, 'ajax_clear_cache' ) );
        add_action( 'wp_ajax_cv_admin_get_playlist_items',  array( __CLASS__, 'ajax_get_playlist_items' ) );
        add_action( 'wp_ajax_cv_admin_reset_password',      array( __CLASS__, 'ajax_reset_password' ) );
        add_action( 'wp_ajax_cv_admin_delete_user',         array( __CLASS__, 'ajax_delete_user' ) );
        add_action( 'wp_ajax_cv_admin_delete_subscriber',   array( __CLASS__, 'ajax_delete_subscriber' ) );
    }

    // ── Helper: botão voltar ao Dashboard ───────────────────────
    public static function btn_voltar() {
        $url = admin_url('admin.php?page=cancao-verdadeira');
        $gold_bg  = 'rgba(212,160,23,.12)';
        $gold_hov = 'rgba(212,160,23,.22)';
        return '<a href="' . esc_url($url) . '" style="'
            . 'display:inline-flex;align-items:center;gap:6px;'
            . 'background:' . $gold_bg . ';border:1px solid rgba(212,160,23,.3);'
            . 'color:#D4A017;padding:7px 14px;border-radius:8px;text-decoration:none;'
            . 'font-size:12px;font-weight:600;margin-bottom:20px;'
            . 'transition:background .2s" '
            . 'onmouseover="this.style.background=&quot;' . $gold_hov . '&quot;" '
            . 'onmouseout="this.style.background=&quot;' . $gold_bg . '&quot;">'
            . '\u2190 Dashboard</a>';
    }

    public static function dark_mode_nova_musica() {
        global $pagenow, $post_type;
        // Aplicar dark mode apenas nas telas de edição/criação do CPT musica
        if ( ( $pagenow !== 'post.php' && $pagenow !== 'post-new.php' ) ) { return; }
        $type = $post_type ?? ( isset($_GET['post_type']) ? sanitize_key($_GET['post_type']) : '' );
        if ( ! $type ) {
            // Tentar via post ID
            if ( isset($_GET['post']) ) {
                $p = get_post( absint($_GET['post']) );
                if ( $p ) { $type = $p->post_type; }
            }
        }
        if ( $type !== 'musica' ) { return; }
        ?>
        <style id="cv-nova-musica-dark">
        /* Dark mode para tela Nova Música / Editar Música */
        body.wp-admin { background: #111 !important; }
        #wpwrap, #wpcontent, #wpbody, #wpbody-content { background: #111 !important; }
        #post-body, #post-body-content { background: #111 !important; }

        /* Título */
        #titlediv #title {
            background: #1a1a1a !important;
            border-color: #2a2a2a !important;
            color: #F5F0E0 !important;
            border-radius: 8px !important;
            font-size: 20px !important;
            padding: 10px 14px !important;
        }
        #titlediv #title:focus { border-color: #D4A017 !important; outline: none !important; }
        #titlediv #title-prompt-text { color: #555 !important; }
        #titlediv { background: #1a1a1a !important; border: 1px solid #2a2a2a !important; border-radius: 8px !important; padding: 12px !important; margin-bottom: 16px !important; }

        /* Metaboxes */
        .postbox {
            background: #1a1a1a !important;
            border: 1px solid #2a2a2a !important;
            border-radius: 8px !important;
            color: #e0e0e0 !important;
        }
        .postbox .postbox-header {
            background: #1e1e1e !important;
            border-bottom: 1px solid #2a2a2a !important;
            border-radius: 8px 8px 0 0 !important;
        }
        .postbox .postbox-header h2,
        .postbox .postbox-header .hndle { color: #D4A017 !important; font-size: 13px !important; }
        .postbox .inside { color: #e0e0e0 !important; }
        .postbox .handlediv button { color: #555 !important; }
        .postbox .handlediv button:focus { box-shadow: none !important; }

        /* Campos dentro dos metaboxes */
        .postbox input[type="text"],
        .postbox input[type="url"],
        .postbox input[type="number"],
        .postbox select,
        .postbox textarea {
            background: #111 !important;
            border: 1px solid #333 !important;
            color: #e0e0e0 !important;
            border-radius: 6px !important;
            padding: 7px 10px !important;
        }
        .postbox input:focus, .postbox select:focus, .postbox textarea:focus {
            border-color: #D4A017 !important;
            outline: none !important;
        }
        .postbox label { color: #aaa !important; font-size: 12px !important; }

        /* Sidebar (publish, taxonomias) */
        #side-sortables .postbox { background: #1a1a1a !important; }
        #submitdiv #publishing-action .button-primary {
            background: #D4A017 !important;
            border-color: #D4A017 !important;
            color: #000 !important;
            font-weight: 700 !important;
            border-radius: 6px !important;
        }
        #submitdiv { background: #1a1a1a !important; }
        #submitdiv .misc-pub-section { border-color: #2a2a2a !important; color: #aaa !important; }
        #submitdiv #save-action .button { background: #222 !important; border-color: #333 !important; color: #aaa !important; border-radius: 6px !important; }

        /* Editor TinyMCE wrapper */
        #wp-content-editor-tools { background: #1a1a1a !important; border-color: #2a2a2a !important; border-radius: 8px 8px 0 0 !important; }
        #wp-content-wrap { border-color: #2a2a2a !important; border-radius: 8px !important; }
        .mce-panel, .mce-toolbar { background: #1e1e1e !important; border-color: #2a2a2a !important; }
        .mce-btn { background: transparent !important; border-color: transparent !important; }
        .mce-btn:hover { background: #2a2a2a !important; }
        .mce-ico { color: #aaa !important; }

        /* Taxonomia checkboxes */
        .categorydiv, .tagsdiv { background: #1a1a1a !important; }
        .categorydiv .tabs-panel, .tagsdiv .tagchecklist { background: #111 !important; border-color: #2a2a2a !important; }
        .categorydiv label, .tagsdiv label { color: #ccc !important; }
        .categorydiv input[type="checkbox"], .tagsdiv input[type="checkbox"] { accent-color: #D4A017; }

        /* Permalink */
        #edit-slug-box { background: #1a1a1a !important; border-color: #2a2a2a !important; border-radius: 6px !important; padding: 8px 12px !important; }
        #editable-post-name { background: #111 !important; color: #D4A017 !important; border: 1px solid #333 !important; border-radius: 4px !important; }
        #view-post-btn a { color: #aaa !important; }
        .edit-slug-buttons .button { background: #222 !important; border-color: #333 !important; color: #aaa !important; }

        /* Imagem destacada */
        #postimagediv .inside { color: #aaa !important; }
        #postimagediv a { color: #D4A017 !important; }

        /* Notices e avisos */
        .notice { border-radius: 6px !important; border-left-color: #D4A017 !important; }
        </style>
        <?php
    }

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'cancao-verdadeira' ) === false
          && strpos( $hook, 'cv-' ) === false
          && 'post.php' !== $hook
          && 'post-new.php' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'cv-admin-style',
            CV_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            CV_VERSION
        );

        wp_enqueue_script(
            'cv-admin-js',
            CV_PLUGIN_URL . 'assets/js/admin.js',
            array( 'jquery', 'wp-util' ),
            CV_VERSION,
            true
        );

        wp_localize_script( 'cv-admin-js', 'cvAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cv_admin_nonce' ),
        ) );

        wp_enqueue_script(
            'chartjs',
            'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js',
            array(),
            '4.4.0',
            true
        );
    }

    public static function register_settings() {
        register_setting( 'cv_settings_group', 'cv_mailerlite_api_key',   array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'cv_settings_group', 'cv_mailerlite_group_id',  array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'cv_settings_group', 'cv_mailerlite_groups',    array( 'sanitize_callback' => array( __CLASS__, 'sanitize_array' ) ) );
        register_setting( 'cv_settings_group', 'cv_whatsapp_number',      array( 'sanitize_callback' => 'sanitize_text_field' ) );
        register_setting( 'cv_settings_group', 'cv_whatsapp_message',     array( 'sanitize_callback' => 'sanitize_textarea_field', 'default' => 'Olá! Vim do Canção Verdadeira e gostaria de saber mais.' ) );
        register_setting( 'cv_settings_group', 'cv_whatsapp_tooltip',     array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'Fale conosco no WhatsApp!' ) );
        register_setting( 'cv_settings_group', 'cv_whatsapp_pulse_delay', array( 'sanitize_callback' => 'absint', 'default' => 3 ) );
        register_setting( 'cv_settings_group', 'cv_play_seconds',         array( 'sanitize_callback' => 'absint', 'default' => 30 ) );
        register_setting( 'cv_settings_group', 'cv_logo_url',             array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'cv_settings_group', 'cv_banner_url',           array( 'sanitize_callback' => 'esc_url_raw' ) );
        register_setting( 'cv_settings_group', 'cv_ranking_interval',     array( 'sanitize_callback' => 'sanitize_text_field', 'default' => 'hourly' ) );
    }

    public static function sanitize_array( $input ) {
        if ( ! is_array( $input ) ) { return array(); }
        return array_map( 'sanitize_text_field', $input );
    }

    public static function ajax_recalculate() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        CV_Ranking::recalculate();
        wp_send_json_success( array(
            'message' => 'Ranking recalculado com sucesso!',
            'time'    => current_time( 'H:i:s' ),
        ) );
    }

    public static function ajax_clear_cache() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        delete_transient( 'cv_ranking_top' );
        delete_transient( 'cv_ranking_recent' );
        delete_transient( 'cv_ranking_best' );
        if ( function_exists( 'litespeed_purge_all' ) ) {
            litespeed_purge_all();
        }
        wp_send_json_success( array( 'message' => 'Cache limpo com sucesso!' ) );
    }

    public static function ajax_get_playlist_items() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        if ( ! $playlist_id ) {
            wp_send_json_error( array( 'message' => 'Playlist inválida.' ) );
        }
        global $wpdb;
        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT pi.music_id AS id, pi.sort_order, p.post_title AS title
             FROM {$wpdb->prefix}cv_playlist_items pi
             INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id
             WHERE pi.playlist_id = %d AND p.post_status = 'publish'
             ORDER BY pi.sort_order ASC",
            $playlist_id
        ) );
        wp_send_json_success( array( 'items' => $items ) );
    }

    public static function ajax_reset_password() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        $user_id = absint( $_POST['user_id'] ?? 0 );
        $user    = get_userdata( $user_id );
        if ( ! $user ) {
            wp_send_json_error( array( 'message' => 'Usuário não encontrado.' ) );
        }
        $result = retrieve_password( $user->user_login );
        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }
        wp_send_json_success( array( 'message' => 'E-mail enviado.' ) );
    }

    public static function ajax_delete_user() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        $user_id = absint( $_POST['user_id'] ?? 0 );
        if ( ! $user_id ) {
            wp_send_json_error( array( 'message' => 'ID inválido.' ) );
        }
        if ( $user_id === get_current_user_id() ) {
            wp_send_json_error( array( 'message' => 'Não é possível excluir sua própria conta.' ) );
        }
        if ( user_can( $user_id, 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Não é possível excluir administradores.' ) );
        }
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );
        wp_send_json_success( array( 'message' => 'Usuário excluído.' ) );
    }

    public static function ajax_delete_subscriber() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        global $wpdb;
        $id = absint( $_POST['sub_id'] ?? 0 );
        if ( ! $id ) {
            wp_send_json_error( array( 'message' => 'ID inválido.' ) );
        }
        $wpdb->delete(
            $wpdb->prefix . 'cv_subscribers',
            array( 'id' => $id ),
            array( '%d' )
        );
        wp_send_json_success( array( 'message' => 'Assinante removido.' ) );
    }

    public static function get_stats() {
        global $wpdb;

        $total_musicas = wp_count_posts( 'musica' )->publish;

        $total_users = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}"
        );

        $plays_24h = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE played_at >= %s",
            date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - DAY_IN_SECONDS )
        ) );

        $total_favorites = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites"
        );

        $subscribers = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_subscribers"
        );

        $top5 = $wpdb->get_results(
            "SELECT rc.music_id, p.post_title, rc.plays_total, rc.score
             FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             WHERE p.post_status = 'publish'
             ORDER BY rc.plays_total DESC
             LIMIT 5"
        );

        return array(
            'total_musicas'   => $total_musicas,
            'total_users'     => $total_users,
            'plays_24h'       => $plays_24h,
            'total_favorites' => $total_favorites,
            'subscribers'     => $subscribers,
            'top5'            => $top5,
            'last_ranking'    => get_option( 'cv_ranking_last_update', 'Nunca' ),
        );
    }
}

CV_Admin::init();