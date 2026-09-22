<?php
// cancao-verdadeira-plugin/includes/user/class-cv-public-profile.php
// Gerado em: 2025-06-02 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Perfil publico do usuario: rewrite rule /perfil/username/ mapeia
// para o template page-public-profile.php. Expoe metodos estaticos
// para buscar dados publicos (favoritos, playlists publicas, stats).
// Privacidade: apenas dados publicos sao exibidos.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Public_Profile {

    public static function init() {
        add_action( 'init',                  array( __CLASS__, 'add_rewrite' ) );
        add_filter( 'query_vars',            array( __CLASS__, 'add_query_var' ) );
        add_filter( 'template_include',      array( __CLASS__, 'load_template' ) );
    }

    public static function add_rewrite() {
        add_rewrite_rule(
            '^perfil/([^/]+)/?$',
            'index.php?cv_profile=$matches[1]',
            'top'
        );
    }

    public static function add_query_var( $vars ) {
        $vars[] = 'cv_profile';
        return $vars;
    }

    public static function load_template( $template ) {
        if ( get_query_var( 'cv_profile' ) ) {
            $custom = get_template_directory() . '/templates/page-public-profile.php';
            if ( file_exists( $custom ) ) { return $custom; }
        }
        return $template;
    }

    /**
     * Retorna dados publicos de um usuario pelo username.
     */
    public static function get_by_login( $login ) {
        $user = get_user_by( 'login', $login );
        if ( ! $user ) { return null; }

        global $wpdb;

        $fav_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE user_id = %d",
            $user->ID
        ) );

        $plays_history = get_user_meta( $user->ID, '_cv_play_history', true );
        $plays_count   = is_array( $plays_history ) ? count( $plays_history ) : 0;

        $playlists = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name,
             (SELECT COUNT(*) FROM {$wpdb->prefix}cv_playlist_items pi WHERE pi.playlist_id = p.id) AS cnt
             FROM {$wpdb->prefix}cv_playlists p
             WHERE user_id = %d AND is_public = 1
             ORDER BY created_at DESC LIMIT 6",
            $user->ID
        ) );

        $favorites = class_exists( 'CV_Favorites' )
            ? CV_Favorites::get_user_favorites( $user->ID, 8 )
            : array();

        return array(
            'id'          => $user->ID,
            'name'        => $user->display_name,
            'login'       => $user->user_login,
            'avatar'      => get_avatar_url( $user->ID, array( 'size' => 120 ) ),
            'registered'  => $user->user_registered,
            'fav_count'   => $fav_count,
            'plays_count' => $plays_count,
            'playlists'   => $playlists,
            'favorites'   => $favorites,
        );
    }
}

CV_Public_Profile::init();
