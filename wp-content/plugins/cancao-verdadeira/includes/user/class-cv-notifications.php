<?php
// cancao-verdadeira-plugin/includes/user/class-cv-notifications.php
// Gerado em: 2025-06-02 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Sistema de notificacoes in-app para usuarios logados: nova musica
// de genero favorito, musica entrou no Top 10, marco de plays.
// Armazena em user_meta (_cv_notifications), limite de 30 por usuario.
// Endpoints AJAX: listar, marcar como lida e limpar todas.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Notifications {

    public static function init() {
        add_action( 'wp_ajax_cv_get_notifications',   array( __CLASS__, 'get_notifications' ) );
        add_action( 'wp_ajax_cv_mark_notif_read',     array( __CLASS__, 'mark_read' ) );
        add_action( 'wp_ajax_cv_clear_notifications', array( __CLASS__, 'clear_all' ) );

        // Dispara notificacao ao publicar musica nova
        add_action( 'publish_musica', array( __CLASS__, 'on_music_published' ), 10, 2 );
    }

    /**
     * Adiciona notificacao para um usuario.
     */
    public static function add( $user_id, $type, $message, $url = '', $icon = '🎵' ) {
        $notifications = get_user_meta( $user_id, '_cv_notifications', true );
        if ( ! is_array( $notifications ) ) { $notifications = array(); }

        array_unshift( $notifications, array(
            'id'      => uniqid( 'n', true ),
            'type'    => $type,
            'message' => $message,
            'url'     => $url,
            'icon'    => $icon,
            'read'    => false,
            'time'    => current_time( 'timestamp' ),
        ) );

        // Limite de 30 notificacoes
        $notifications = array_slice( $notifications, 0, 30 );
        update_user_meta( $user_id, '_cv_notifications', $notifications );
    }

    /**
     * Notifica usuarios cujo genero favorito tem nova musica.
     */
    public static function on_music_published( $post_id, $post ) {
        if ( 'publish' !== $post->post_status ) { return; }

        $genres = wp_get_post_terms( $post_id, 'cv_genre', array( 'fields' => 'slugs' ) );
        if ( is_wp_error( $genres ) || empty( $genres ) ) { return; }

        $url   = get_permalink( $post_id );
        $title = get_the_title( $post_id );

        // Busca usuarios com preferencia de genero salva
        $users = get_users( array(
            'meta_key'   => '_cv_favorite_genre',
            'meta_value' => $genres,
            'compare'    => 'IN',
            'fields'     => 'ID',
            'number'     => 200,
        ) );

        foreach ( $users as $user_id ) {
            self::add(
                $user_id,
                'new_music',
                'Nova música: "' . $title . '"',
                $url,
                '🎵'
            );
        }
    }

    /**
     * Notifica usuario quando musica favorita entra no Top 10.
     * Chamado pelo CV_Ranking::recalculate().
     */
    public static function notify_top10_entry( $music_id, $position ) {
        global $wpdb;

        $title = get_the_title( $music_id );
        $url   = get_permalink( $music_id );

        // Usuarios que favoritaram esta musica
        $user_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}cv_favorites WHERE music_id = %d",
            $music_id
        ) );

        foreach ( $user_ids as $user_id ) {
            self::add(
                (int) $user_id,
                'top10',
                '"' . $title . '" entrou no Top ' . $position . '! 🏆',
                $url,
                '🏆'
            );
        }
    }

    // ── Endpoints AJAX ────────────────────────────────────────────

    public static function get_notifications() {
        check_ajax_referer( 'cv_notif_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_success( array( 'notifications' => array(), 'unread' => 0 ) );
        }

        $user_id       = get_current_user_id();
        $notifications = get_user_meta( $user_id, '_cv_notifications', true );
        if ( ! is_array( $notifications ) ) { $notifications = array(); }

        $unread = count( array_filter( $notifications, function( $n ) {
            return empty( $n['read'] );
        } ) );

        // Formata tempo relativo
        foreach ( $notifications as &$n ) {
            $n['time_human'] = self::time_ago( $n['time'] );
        }
        unset( $n );

        wp_send_json_success( array(
            'notifications' => array_slice( $notifications, 0, 20 ),
            'unread'        => $unread,
        ) );
    }

    public static function mark_read() {
        check_ajax_referer( 'cv_notif_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error(); }

        $notif_id = sanitize_text_field( $_POST['notif_id'] ?? '' );
        $user_id  = get_current_user_id();

        $notifications = get_user_meta( $user_id, '_cv_notifications', true );
        if ( ! is_array( $notifications ) ) { wp_send_json_success(); }

        foreach ( $notifications as &$n ) {
            if ( $notif_id === 'all' || $n['id'] === $notif_id ) {
                $n['read'] = true;
            }
        }
        unset( $n );

        update_user_meta( $user_id, '_cv_notifications', $notifications );
        wp_send_json_success();
    }

    public static function clear_all() {
        check_ajax_referer( 'cv_notif_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error(); }
        delete_user_meta( get_current_user_id(), '_cv_notifications' );
        wp_send_json_success();
    }

    private static function time_ago( $timestamp ) {
        $diff = current_time( 'timestamp' ) - $timestamp;
        if ( $diff < 60 )     { return 'agora mesmo'; }
        if ( $diff < 3600 )   { return intval( $diff / 60 ) . ' min atrás'; }
        if ( $diff < 86400 )  { return intval( $diff / 3600 ) . 'h atrás'; }
        if ( $diff < 604800 ) { return intval( $diff / 86400 ) . 'd atrás'; }
        return date( 'd/m/Y', $timestamp );
    }
}

CV_Notifications::init();
