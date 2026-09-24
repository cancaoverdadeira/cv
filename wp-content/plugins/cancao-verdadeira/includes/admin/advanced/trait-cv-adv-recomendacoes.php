<?php
// cancao-verdadeira/includes/admin/advanced/trait-cv-adv-recomendacoes.php
// Parte do CV_Advanced (trait CV_Adv_Recomendacoes): recomendação automática (melhores ranqueadas que o usuário
// ainda não ouviu), cache por usuário e AJAX cv_get_recommendations.
// v2.37.0: saiu de class-cv-advanced.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Advanced::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Adv_Recomendacoes {

    // ════════════════════════════════════════════════════════════════
    // 5. RECOMENDAÇÃO AUTOMÁTICA
    // ════════════════════════════════════════════════════════════════

    /**
     * Retorna músicas recomendadas para o usuário logado.
     * Algoritmo:
     *  1. Pega as últimas 20 músicas ouvidas (histórico)
     *  2. Exclui essas músicas das recomendações
     *  3. Retorna as melhores ranqueadas (score) entre as restantes
     *  4. Se não logado ou sem resultado: retorna o Top geral
     *
     * @param int $limit  Número de recomendações (padrão 6)
     */
    public static function get_recommendations( $user_id = 0, $limit = 6 ) {
        if ( ! $user_id ) { $user_id = get_current_user_id(); }

        // Fallback: sem usuário → top geral
        if ( ! $user_id ) {
            return class_exists( 'CV_Ranking' ) ? CV_Ranking::get_top( $limit ) : array();
        }

        $cache_key = 'cv_rec_' . $user_id . '_' . $limit;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        // 1. Histórico de plays do usuário
        $history = get_user_meta( $user_id, '_cv_play_history', true );
        $history = is_array( $history ) ? array_slice( $history, 0, 20 ) : array();

        // IDs já ouvidos (excluir das recomendações)
        $heard_ids = array_column( $history, 'id' );
        $heard_ids = array_map( 'intval', $heard_ids );

        // 2. Busca as melhores ranqueadas que o usuário não ouviu ainda
        $args = array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => $limit * 2, // busca mais para filtrar
            'meta_key'       => CV_Fields::SCORE,
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => array(
                array( 'key' => CV_Fields::ATIVO, 'value' => '1', 'compare' => '=' ),
            ),
        );

        if ( ! empty( $heard_ids ) ) {
            $args['post__not_in'] = $heard_ids;
        }

        $posts  = get_posts( $args );
        $result = array();

        foreach ( array_slice( $posts, 0, $limit ) as $post ) {
            $yt_url  = get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );
            $cover   = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover && $yt_url ) {
                $m = CV_Fields::youtube_match( $yt_url );
                $cover = isset( $m[1] ) ? "https://img.youtube.com/vi/{$m[1]}/mqdefault.jpg" : '';
            }

            $result[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'url'       => get_permalink( $post->ID ),
                'cover'     => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'artista'   => get_post_meta( $post->ID, CV_Fields::ARTISTA, true ),
                'score'     => (float) get_post_meta( $post->ID, CV_Fields::SCORE, true ),
                'plays'     => (int) get_post_meta( $post->ID, CV_Fields::PLAYS_TOTAL, true ),
                'youtube_url' => $yt_url,
            );
        }

        // Fallback: se não encontrou nada, retorna top geral
        if ( empty( $result ) && class_exists( 'CV_Ranking' ) ) {
            $result = CV_Ranking::get_top( $limit );
        }

        // Cache de 30 minutos (personalizadas, mudam com interações)
        set_transient( $cache_key, $result, 30 * MINUTE_IN_SECONDS );
        return $result;
    }

    /**
     * Limpa o cache de recomendação de um usuário.
     * Chamado após um play ou favorito.
     */
    public static function clear_user_recommendations( $user_id ) {
        // Limpa todos os tamanhos possíveis de cache de recomendação para o usuário
        global $wpdb;
        $pattern = '_transient_cv_rec_' . (int) $user_id . '_%';
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE %s
                OR option_name LIKE %s",
            $pattern,
            str_replace( '_transient_', '_transient_timeout_', $pattern )
        ) );
    }

    public static function ajax_recommendations() {
        // Nonce opcional para usuários logados; público pode buscar sem nonce
        if ( is_user_logged_in() ) {
            check_ajax_referer( 'cv_play_nonce', 'nonce' );
        }
        $limit   = min( absint( $_POST['limit'] ?? 6 ), 20 );
        $user_id = get_current_user_id();
        wp_send_json_success( array(
            'recommendations' => self::get_recommendations( $user_id, $limit ),
            'user_id'         => $user_id,
        ) );
    }
}
