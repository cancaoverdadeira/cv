<?php
// cancao-verdadeira/includes/user/class-cv-user-area.php
// Gerado em: 2026-06-21 20:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Área do usuário logado: endpoints AJAX completos para o dashboard.
// v2.1 — COMPLETADO: adicionados cv_get_user_favorites, cv_get_user_playlists
// e cv_get_user_stats. Antes só existia cv_get_user_history, deixando o
// dashboard sem dados de favoritos, playlists e estatísticas do usuário.
// Integra com MailerLite ao registrar via Ultimate Member ou nativo.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_User_Area {

    public static function init() {
        add_action( 'wp_ajax_cv_get_user_history',   array( __CLASS__, 'get_history' ) );
        add_action( 'wp_ajax_cv_get_user_favorites', array( __CLASS__, 'get_favorites' ) );
        add_action( 'wp_ajax_cv_get_user_playlists', array( __CLASS__, 'get_playlists' ) );
        add_action( 'wp_ajax_cv_get_user_stats',     array( __CLASS__, 'get_stats' ) );
        add_action( 'um_registration_complete',      array( __CLASS__, 'on_um_register' ), 10, 2 );
    }

    public static function get_history() {
        check_ajax_referer( 'cv_play_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }
        $user_id = get_current_user_id();
        $history = get_user_meta( $user_id, '_cv_play_history', true );
        if ( ! is_array( $history ) || empty( $history ) ) {
            wp_send_json_success( array( 'history' => array() ) );
            return;
        }
        $result = array();
        foreach ( array_slice( $history, 0, 20 ) as $item ) {
            $id   = absint( $item['id'] ?? 0 );
            if ( ! $id ) { continue; }
            $post = get_post( $id );
            if ( ! $post || 'publish' !== $post->post_status ) { continue; }
            $cover = get_the_post_thumbnail_url( $id, 'cv-cover' );
            if ( ! $cover ) {
                $yt = get_post_meta( $id, CV_Fields::YOUTUBE_URL, true );
                if ( $yt ) {
                    $m = CV_Fields::youtube_match( $yt );
                    if ( ! empty( $m[1] ) ) { $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg'; }
                }
            }
            $result[] = array(
                'id'      => $id,
                'title'   => $post->post_title,
                'url'     => get_permalink( $id ),
                'cover'   => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'artista' => get_post_meta( $id, CV_Fields::ARTISTA, true ),
                'played'  => $item['played'] ?? 0,
            );
        }
        wp_send_json_success( array( 'history' => $result ) );
    }

    public static function get_favorites() {
        check_ajax_referer( 'cv_favorite_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }
        $user_id = get_current_user_id();
        if ( ! class_exists( 'CV_Favorites' ) ) {
            wp_send_json_success( array( 'favorites' => array() ) );
            return;
        }
        $posts  = CV_Favorites::get_user_favorites( $user_id, 24 );
        $result = array();
        foreach ( $posts as $post ) {
            $cover = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover ) {
                $yt = get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );
                if ( $yt ) {
                    $m = CV_Fields::youtube_match( $yt );
                    if ( ! empty( $m[1] ) ) { $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg'; }
                }
            }
            $result[] = array(
                'id'      => $post->ID,
                'title'   => $post->post_title,
                'url'     => get_permalink( $post->ID ),
                'cover'   => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'artista' => get_post_meta( $post->ID, CV_Fields::ARTISTA, true ),
                'plays'   => (int) get_post_meta( $post->ID, CV_Fields::PLAYS_TOTAL, true ),
            );
        }
        wp_send_json_success( array( 'favorites' => $result ) );
    }

    public static function get_playlists() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }
        global $wpdb;
        $user_id   = get_current_user_id();
        $playlists = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.id, p.name, p.description, p.is_public, p.created_at,
                    COUNT(pi.id) AS total_musicas
             FROM {$wpdb->prefix}cv_playlists p
             LEFT JOIN {$wpdb->prefix}cv_playlist_items pi ON pi.playlist_id = p.id
             WHERE p.user_id = %d
             GROUP BY p.id
             ORDER BY p.created_at DESC LIMIT 20",
            $user_id
        ) );
        $result = array();
        foreach ( $playlists as $playlist ) {
            $first = $wpdb->get_var( $wpdb->prepare(
                "SELECT music_id FROM {$wpdb->prefix}cv_playlist_items
                 WHERE playlist_id = %d ORDER BY sort_order ASC LIMIT 1",
                $playlist->id
            ) );
            $cover = '';
            if ( $first ) {
                $cover = get_the_post_thumbnail_url( (int) $first, 'cv-cover' );
                if ( ! $cover ) {
                    $yt = get_post_meta( (int) $first, CV_Fields::YOUTUBE_URL, true );
                    if ( $yt ) {
                        $m = CV_Fields::youtube_match( $yt );
                        if ( ! empty( $m[1] ) ) { $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg'; }
                    }
                }
            }
            $result[] = array(
                'id'            => (int) $playlist->id,
                'name'          => $playlist->name,
                'description'   => $playlist->description,
                'is_public'     => (bool) $playlist->is_public,
                'total_musicas' => (int) $playlist->total_musicas,
                'cover'         => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'created_at'    => $playlist->created_at,
            );
        }
        wp_send_json_success( array( 'playlists' => $result ) );
    }

    public static function get_stats() {
        check_ajax_referer( 'cv_play_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }
        wp_send_json_success( self::get_user_data( get_current_user_id() ) );
    }

    public static function get_user_data( $user_id = 0 ) {
        if ( ! $user_id ) { $user_id = get_current_user_id(); }
        if ( ! $user_id ) { return array(); }
        $user    = get_userdata( $user_id );
        $history = get_user_meta( $user_id, '_cv_play_history', true );
        $history = is_array( $history ) ? $history : array();
        global $wpdb;
        $fav_count      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE user_id = %d", $user_id ) );
        $playlist_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_playlists WHERE user_id = %d", $user_id ) );
        $achievements   = get_user_meta( $user_id, '_cv_achievements', true );
        $achievements   = is_array( $achievements ) ? $achievements : array();
        $notifications  = get_user_meta( $user_id, '_cv_notifications', true );
        $notifications  = is_array( $notifications ) ? $notifications : array();
        $unread = count( array_filter( $notifications, function( $n ) { return empty( $n['read'] ); } ) );
        return array(
            'id'                   => $user_id,
            'name'                 => $user->display_name,
            'email'                => $user->user_email,
            'avatar'               => get_avatar_url( $user_id, array( 'size' => 120 ) ),
            'registered'           => $user->user_registered,
            'plays_total'          => count( $history ),
            'favorites'            => $fav_count,
            'playlists'            => $playlist_count,
            'achievements'         => count( $achievements ),
            'notifications_unread' => $unread,
        );
    }

    public static function on_um_register( $user_id, $args ) {
        $user     = get_userdata( $user_id );
        $email    = $user->user_email;
        $name     = $user->display_name;
        $api_key  = get_option( 'cv_mailerlite_api_key', '' );
        $group_id = get_option( 'cv_mailerlite_group_id', '' );
        if ( $api_key && $email ) {
            wp_remote_post( 'https://connect.mailerlite.com/api/subscribers', array(
                'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $api_key, 'Accept' => 'application/json' ),
                'body'    => wp_json_encode( array( 'email' => $email, 'fields' => array( 'name' => $name ), 'groups' => $group_id ? array( $group_id ) : array() ) ),
                'timeout' => 8, 'blocking' => false,
            ) );
        }
    }
}

CV_User_Area::init();
