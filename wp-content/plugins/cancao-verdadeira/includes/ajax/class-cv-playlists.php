<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-playlists.php
// Gerado em: 2025-06-01 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gerencia playlists dos usuários: criar, renomear, excluir,
// adicionar/remover músicas e reordenar por drag-and-drop.
// Todas as ações requerem usuário logado e nonce AJAX válido.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Playlists {

    public static function init() {
        add_action( 'wp_ajax_cv_playlist_create',       array( __CLASS__, 'create' ) );
        add_action( 'wp_ajax_cv_playlist_delete',       array( __CLASS__, 'delete_playlist' ) );
        add_action( 'wp_ajax_cv_playlist_rename',       array( __CLASS__, 'rename' ) );
        add_action( 'wp_ajax_cv_playlist_add_music',    array( __CLASS__, 'add_music' ) );
        add_action( 'wp_ajax_cv_playlist_remove_music', array( __CLASS__, 'remove_music' ) );
        add_action( 'wp_ajax_cv_playlist_reorder',      array( __CLASS__, 'reorder' ) );
        add_action( 'wp_ajax_cv_playlist_list',         array( __CLASS__, 'list_playlists' ) );
    }

    private static function require_login() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.', 'require_login' => true ) );
        }
    }

    public static function create() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $name        = sanitize_text_field( $_POST['name']        ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $is_public   = absint( $_POST['is_public']   ?? 0 );
        $is_featured = absint( $_POST['is_featured'] ?? 0 );
        $cover_url   = esc_url_raw( $_POST['cover_url'] ?? '' );
        $user_id     = get_current_user_id();

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Nome da playlist é obrigatório.' ) );
        }

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'cv_playlists',
            array(
                'user_id'     => $user_id,
                'name'        => $name,
                'description' => $description,
                'is_public'   => $is_public,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s' )
        );

        $new_id = $wpdb->insert_id;

        // Salva capa e destaque como meta da playlist (em wp_options com chave única)
        if ( $cover_url ) {
            update_option( 'cv_playlist_cover_' . $new_id, $cover_url );
        }
        if ( $is_featured ) {
            // Guarda lista de playlists em destaque
            $featured = get_option( 'cv_featured_playlists', array() );
            if ( ! is_array( $featured ) ) { $featured = array(); }
            $featured[] = $new_id;
            update_option( 'cv_featured_playlists', array_unique( $featured ) );
        }

        // Dispara hook para o sistema de logs registrar a criação
        do_action( 'cv_playlist_created', $new_id, $name );

        wp_send_json_success( array(
            'id'   => $new_id,
            'name' => $name,
        ) );
    }

    public static function delete_playlist() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $wpdb->delete( $wpdb->prefix . 'cv_playlist_items', array( 'playlist_id' => $playlist_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cv_playlists',      array( 'id' => $playlist_id ),          array( '%d' ) );

        wp_send_json_success();
    }

    public static function rename() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $name        = sanitize_text_field( $_POST['name'] ?? '' );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'cv_playlists',
            array( 'name' => $name ),
            array( 'id'   => $playlist_id ),
            array( '%s' ),
            array( '%d' )
        );

        wp_send_json_success( array( 'name' => $name ) );
    }

    public static function add_music() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $music_id    = absint( $_POST['music_id']    ?? 0 );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        if ( 'musica' !== get_post_type( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'Música inválida.' ) );
        }

        global $wpdb;

        // Verifica se já está na playlist
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_playlist_items WHERE playlist_id = %d AND music_id = %d",
            $playlist_id, $music_id
        ) );

        if ( $exists ) {
            wp_send_json_error( array( 'message' => 'Música já está nesta playlist.' ) );
        }

        $max_order = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$wpdb->prefix}cv_playlist_items WHERE playlist_id = %d",
            $playlist_id
        ) );

        $wpdb->insert(
            $wpdb->prefix . 'cv_playlist_items',
            array(
                'playlist_id' => $playlist_id,
                'music_id'    => $music_id,
                'sort_order'  => $max_order + 1,
                'added_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s' )
        );

        wp_send_json_success();
    }

    public static function remove_music() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $music_id    = absint( $_POST['music_id']    ?? 0 );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'cv_playlist_items',
            array( 'playlist_id' => $playlist_id, 'music_id' => $music_id ),
            array( '%d', '%d' )
        );

        wp_send_json_success();
    }

    public static function reorder() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $order       = $_POST['order'] ?? array();
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        foreach ( (array) $order as $index => $music_id ) {
            $music_id = absint( $music_id );
            if ( ! $music_id ) { continue; }

            $wpdb->update(
                $wpdb->prefix . 'cv_playlist_items',
                array( 'sort_order' => $index ),
                array( 'playlist_id' => $playlist_id, 'music_id' => $music_id ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        }

        wp_send_json_success();
    }

    public static function list_playlists() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $user_id = get_current_user_id();

        wp_send_json_success( self::get_user_playlists( $user_id ) );
    }

    /**
     * Retorna playlists de um usuário com músicas.
     */
    public static function get_user_playlists( $user_id ) {
        global $wpdb;

        $playlists = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_playlists WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        foreach ( $playlists as $playlist ) {
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT pi.music_id, p.post_title
                 FROM {$wpdb->prefix}cv_playlist_items pi
                 INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id
                 WHERE pi.playlist_id = %d AND p.post_status = 'publish'
                 ORDER BY pi.sort_order ASC",
                $playlist->id
            ) );

            $playlist->items = $items;
            $playlist->count = count( $items );
        }

        return $playlists;
    }

    private static function owns_playlist( $playlist_id, $user_id ) {
        global $wpdb;

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_playlists WHERE id = %d AND user_id = %d",
            $playlist_id,
            $user_id
        ) );
    }
}

CV_Playlists::init();
