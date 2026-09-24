<?php
// cancao-verdadeira/includes/public/class-cv-launch.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Modo lançamento (substitui a antiga tela "Calibração")
// Ideia   : no começo o site não deve parecer zerado, mas também não pode
//           inventar números. Então:
//           1. Contadores abaixo do mínimo não aparecem; no lugar, o selo
//              "Lançamento". Quando a música passa do mínimo, o número real
//              aparece sozinho.
//           2. Enquanto poucas músicas têm audiência real, os blocos de
//              ranking mostram a "Seleção da Canção Verdadeira": músicas
//              marcadas como ⭐ Destaque, com rótulo editorial explícito.
// Ajustes : wp-admin → Canção Verdadeira → Configurações (seção Modo lançamento)
// Gerado  : 2026-09-23

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Launch {

    const OPTION    = 'cv_launch';
    const ORDER_KEY = CV_Fields::SELECAO_ORDEM;

    private static $defaults = array(
        'min_plays'   => 10, // plays reais para exibir o contador de plays
        'min_favs'    => 3,  // favoritos reais para exibir o contador de favoritos
        'min_ratings' => 3,  // avaliações reais para exibir a nota média
        'min_songs'   => 5,  // músicas com plays >= min_plays para o ranking real aparecer
    );

    public static function get( $key ) {
        $opts = wp_parse_args( (array) get_option( self::OPTION, array() ), self::$defaults );
        return max( 0, (int) $opts[ $key ] );
    }

    public static function defaults() {
        return self::$defaults;
    }

    public static function plays_visible( $plays ) {
        return (int) $plays >= max( 1, self::get( 'min_plays' ) );
    }

    public static function favs_visible( $favs ) {
        return (int) $favs >= max( 1, self::get( 'min_favs' ) );
    }

    public static function ratings_visible( $count ) {
        return (int) $count >= max( 1, self::get( 'min_ratings' ) );
    }

    // Texto do contador de favoritos: número real ou vazio (abaixo do mínimo).
    public static function fav_label( $favs ) {
        return self::favs_visible( $favs ) ? number_format_i18n( (int) $favs ) : '';
    }

    public static function badge() {
        return '<span class="cv-badge-lancamento" title="Música recém-chegada ao site">Lançamento</span>';
    }

    // O ranking real só aparece quando há músicas suficientes com audiência real.
    public static function ranking_ready() {
        $cached = get_transient( 'cv_launch_ranking_ready' );
        if ( false !== $cached ) { return '1' === $cached; }

        global $wpdb;
        $com_audiencia = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             WHERE p.post_status = 'publish' AND rc.plays_total >= %d",
            max( 1, self::get( 'min_plays' ) )
        ) );
        $ready = $com_audiencia >= max( 1, self::get( 'min_songs' ) );
        set_transient( 'cv_launch_ranking_ready', $ready ? '1' : '0', 15 * MINUTE_IN_SECONDS );
        return $ready;
    }

    // Músicas marcadas como ⭐ Destaque, na ordem definida pelo admin,
    // no mesmo formato de linha que CV_Ranking::get_top() devolve.
    public static function selection( $limit = 10 ) {
        $ids = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $limit ),
            'fields'         => 'ids',
            'meta_query'     => array(
                'destaque' => array( 'key' => CV_Fields::DESTAQUE, 'value' => '1' ),
                'ordem'    => array( 'key' => self::ORDER_KEY, 'type' => 'NUMERIC', 'compare' => 'EXISTS' ),
            ),
            'orderby'        => array( 'ordem' => 'ASC', 'date' => 'DESC' ),
        ) );
        // Destaques sem ordem definida entram no fim, dos mais novos para os mais antigos.
        if ( count( $ids ) < $limit ) {
            $ids = array_merge( $ids, get_posts( array(
                'post_type'      => 'musica',
                'post_status'    => 'publish',
                'posts_per_page' => absint( $limit ) - count( $ids ),
                'fields'         => 'ids',
                'post__not_in'   => $ids ? $ids : array( 0 ),
                'meta_query'     => array( array( 'key' => CV_Fields::DESTAQUE, 'value' => '1' ) ),
            ) ) );
        }

        $rows = array();
        foreach ( $ids as $i => $id ) {
            $rows[] = (object) array(
                'music_id'    => $id,
                'post_title'  => get_the_title( $id ),
                'post_name'   => get_post_field( 'post_name', $id ),
                'position'    => $i + 1,
                'plays_total' => (int) get_post_meta( $id, CV_Fields::PLAYS_TOTAL, true ),
                'favorites'   => (int) get_post_meta( $id, CV_Fields::FAVORITES, true ),
                'score'       => 0,
            );
        }
        return class_exists( 'CV_Ranking' ) ? CV_Ranking::enrich( $rows ) : $rows;
    }
}
