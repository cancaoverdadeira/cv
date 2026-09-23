<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Catalog search covers native lyrics and legacy metadata without rewriting content. */
class CV_Search {
    public static function init() {
        add_filter( 'posts_search', array( __CLASS__, 'search_sql' ), 20, 2 );
    }

    public static function search_sql( $search, $query ) {
        if ( ! $query->get( 'cv_catalog_search' ) || ! $query->get( 's' ) ) { return $search; }
        global $wpdb;
        $term = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';
        $sql = $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s
            OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} cv_search_meta WHERE cv_search_meta.post_id = {$wpdb->posts}.ID
                AND cv_search_meta.meta_key IN ('_cv_artista','_cv_compositor') AND cv_search_meta.meta_value LIKE %s))",
            $term, $term, $term, $term
        );
        if ( ! is_user_logged_in() ) { $sql .= " AND {$wpdb->posts}.post_password = ''"; }
        return $sql;
    }
}
CV_Search::init();
