<?php
// cancao-verdadeira-plugin/includes/realtime/class-cv-trending.php
// Gerado em: 2025-06-03 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Trending em tempo real: endpoint REST que retorna o Top 10 com
// score calculado nos ultimos 15 minutos. Usado pelo JS do tema
// via polling a cada 30 segundos para atualizar o ranking ao vivo.
// Cache de 15 segundos via transient para nao sobrecarregar o banco.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Trending {

    public static function init() {
        add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
    }

    public static function register_routes() {
        register_rest_route( 'cv/v1', '/trending', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'get_trending' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * Retorna Top 10 musicas com mais plays nos ultimos 15 minutos.
     * Cache de 15 segundos para balancear realtime vs performance.
     */
    public static function get_trending() {
        $cache_key = 'cv_trending_15m';
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return rest_ensure_response( $cached );
        }

        global $wpdb;

        $since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 900 ); // 15 min

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT pl.music_id,
                    COUNT(*) AS plays_15m,
                    p.post_title,
                    p.post_name
             FROM {$wpdb->prefix}cv_plays_log pl
             INNER JOIN {$wpdb->posts} p ON p.ID = pl.music_id
             WHERE pl.played_at >= %s
               AND p.post_status = 'publish'
             GROUP BY pl.music_id
             ORDER BY plays_15m DESC
             LIMIT 10",
            $since
        ) );

        $result = array();
        foreach ( $rows as $row ) {
            $cover   = get_the_post_thumbnail_url( $row->music_id, 'cv-cover' );
            $yt_url  = get_post_meta( $row->music_id, '_cv_youtube_url', true );
            $artista = get_post_meta( $row->music_id, '_cv_artista', true );

            if ( ! $cover && $yt_url ) {
                $vid   = self::yt_id( $yt_url );
                $cover = $vid ? "https://img.youtube.com/vi/{$vid}/mqdefault.jpg" : '';
            }

            $result[] = array(
                'id'        => (int) $row->music_id,
                'title'     => $row->post_title,
                'slug'      => $row->post_name,
                'url'       => get_permalink( $row->music_id ),
                'cover'     => $cover ?: '',
                'artista'   => $artista,
                'plays_15m' => (int) $row->plays_15m,
                'youtube_id'=> $yt_url ? self::yt_id( $yt_url ) : '',
            );
        }

        // Se nao houver atividade nos ultimos 15min, retorna Top 10 geral
        if ( empty( $result ) ) {
            $result = self::get_fallback_top();
        }

        set_transient( $cache_key, $result, 15 );

        return rest_ensure_response( $result );
    }

    private static function get_fallback_top() {
        if ( ! class_exists( 'CV_Ranking' ) ) { return array(); }
        $top = CV_Ranking::get_top( 10 );
        return array_map( function( $row ) {
            return array(
                'id'        => (int) $row->music_id,
                'title'     => $row->post_title,
                'slug'      => $row->post_name,
                'url'       => $row->url,
                'cover'     => $row->cover,
                'artista'   => $row->artista,
                'plays_15m' => 0,
                'youtube_id'=> '',
            );
        }, $top );
    }

    private static function yt_id( $url ) {
        preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $url, $m );
        return $m[1] ?? '';
    }
}

CV_Trending::init();
