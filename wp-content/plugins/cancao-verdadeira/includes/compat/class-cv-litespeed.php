<?php
// cancao-verdadeira-plugin/includes/compat/class-cv-litespeed.php
// Gerado em: 2025-06-01 12:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Compatibilidade com LiteSpeed Cache e WP Rocket:
// exclui admin-ajax.php do cache, define TTL 1h para CPT musica,
// purga automaticamente ao salvar post, protege JS do player de
// minificacao. Nao interfere se os plugins nao estiverem ativos.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Litespeed {

    public static function init() {
        add_action( 'save_post_musica',                array( __CLASS__, 'purge_on_save' ), 10, 1 );
        add_action( 'wp_ajax_cv_register_play',        array( __CLASS__, 'set_no_cache_header' ), 1 );
        add_action( 'wp_ajax_nopriv_cv_register_play', array( __CLASS__, 'set_no_cache_header' ), 1 );
        add_action( 'wp_ajax_cv_toggle_favorite',      array( __CLASS__, 'set_no_cache_header' ), 1 );
        add_action( 'wp_ajax_cv_subscribe',            array( __CLASS__, 'set_no_cache_header' ), 1 );
        add_action( 'wp_ajax_nopriv_cv_subscribe',     array( __CLASS__, 'set_no_cache_header' ), 1 );
        add_filter( 'litespeed_ttl',                   array( __CLASS__, 'custom_ttl' ) );
        add_filter( 'rocket_delay_js_exclusions',      array( __CLASS__, 'rocket_exclude_ajax' ) );
        add_filter( 'rocket_exclude_js',               array( __CLASS__, 'rocket_exclude_player_js' ) );
    }

    public static function set_no_cache_header() {
        if ( function_exists( 'litespeed_no_cache' ) ) { litespeed_no_cache(); }
        if ( ! headers_sent() ) {
            header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
            header( 'Pragma: no-cache' );
        }
    }

    public static function custom_ttl( $ttl ) {
        if ( is_singular( 'musica' ) || is_post_type_archive( 'musica' ) ) { return 3600; }
        return $ttl;
    }

    public static function purge_on_save( $post_id ) {
        if ( wp_is_post_revision( $post_id ) ) { return; }
        if ( function_exists( 'litespeed_purge_post' ) ) { litespeed_purge_post( $post_id ); }
        if ( function_exists( 'rocket_clean_post' ) )    { rocket_clean_post( $post_id ); }

        // Limpa todos os transients de ranking (qualquer limite e gênero)
        // O formato é: cv_ranking_top_{limit}_{genre} e cv_ranking_recent_{limit}
        global $wpdb;
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '\_transient\_cv\_ranking\_%'
                OR option_name LIKE '\_transient\_timeout\_cv\_ranking\_%'
                OR option_name LIKE '\_transient\_cv\_ranking\_period\_%'
                OR option_name LIKE '\_transient\_timeout\_cv\_ranking\_period\_%'"
        );
    }

    public static function rocket_exclude_ajax( $exclusions ) {
        $exclusions[] = 'admin-ajax.php';
        return $exclusions;
    }

    public static function rocket_exclude_player_js( $exclusions ) {
        if ( defined( 'CV_THEME_URL' ) ) {
            $exclusions[] = CV_THEME_URL . '/assets/js/player.js';
            $exclusions[] = CV_THEME_URL . '/assets/js/theme.js';
        }
        return $exclusions;
    }
}

CV_Litespeed::init();
