<?php
// cancao-verdadeira/includes/ranking/class-cv-ranking.php
// Gerado em: 2026-06-21 18:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gerencia o ranking dinâmico usando a fórmula:
// score = (avg_rating*2) + (plays_total*0.03) + (favorites*1.2) + (plays_7d*0.05)
// v2.1 — CORRIGIDO BUG B4: position_prev agora é gravado ANTES do recálculo.
// Indicadores ↑↓ passarão a funcionar corretamente. Adicionado: notificação
// automática via CV_Notifications quando música entra no Top 10, e método
// get_by_period() para o shortcode [cv_ranking_periodo].
// v2.25.1 — get_most_favorited(): seção "Mais Favoritadas" da home.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Ranking {

    /**
     * Recalcula o ranking de todas as músicas publicadas e ativas.
     * Chamado via WP-Cron (hourly) ou manualmente pelo painel admin.
     */
    public static function recalculate() {
        global $wpdb;

        $musicas = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'     => '_cv_ativo',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        ) );

        if ( empty( $musicas ) ) {
            return;
        }

        $now        = current_time( 'mysql' );
        $ts         = current_time( 'timestamp' );
        $seven_ago  = date( 'Y-m-d H:i:s', $ts - ( 7  * DAY_IN_SECONDS ) );
        $day_ago    = date( 'Y-m-d H:i:s', $ts - DAY_IN_SECONDS );
        $thirty_ago = date( 'Y-m-d H:i:s', $ts - ( 30 * DAY_IN_SECONDS ) );

        // CORRIGIDO BUG B4 — passo 1: lê posições atuais ANTES de recalcular
        // Sem este passo, position_prev era sempre igual a position,
        // tornando os indicadores ↑↓ permanentemente neutros.
        $prev_positions = $wpdb->get_results(
            "SELECT music_id, position FROM {$wpdb->prefix}cv_ranking_cache",
            OBJECT_K
        );

        $rows = array();

        foreach ( $musicas as $music_id ) {

            // Plays total (conta da tabela de log — fonte da verdade)
            $plays_total = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE music_id = %d",
                $music_id
            ) );

            // Plays últimas 24h
            $plays_24h = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
                 WHERE music_id = %d AND played_at >= %s",
                $music_id, $day_ago
            ) );

            // Plays últimos 7 dias
            $plays_7d = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
                 WHERE music_id = %d AND played_at >= %s",
                $music_id, $seven_ago
            ) );

            // Plays últimos 30 dias
            $plays_30d = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
                 WHERE music_id = %d AND played_at >= %s",
                $music_id, $thirty_ago
            ) );

            // Favoritos
            $favorites = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE music_id = %d",
                $music_id
            ) );

            // Média de avaliações
            $avg_rating = (float) $wpdb->get_var( $wpdb->prepare(
                "SELECT AVG(rating) FROM {$wpdb->prefix}cv_ratings WHERE music_id = %d",
                $music_id
            ) );
            if ( ! $avg_rating ) { $avg_rating = 0; }

            // Fórmula de score
            $score = ( $avg_rating  * 2    )
                   + ( $plays_total * 0.03 )
                   + ( $favorites   * 1.2  )
                   + ( $plays_7d    * 0.05 );

            // Atualiza post_meta para acesso rápido nos templates (sem query ao ranking_cache)
            update_post_meta( $music_id, '_cv_plays_total', $plays_total );
            update_post_meta( $music_id, '_cv_plays_7d',    $plays_7d );
            update_post_meta( $music_id, '_cv_favorites',   $favorites );
            update_post_meta( $music_id, '_cv_avg_rating',  round( $avg_rating, 2 ) );
            update_post_meta( $music_id, '_cv_score',       round( $score, 4 ) );

            $rows[ $music_id ] = array(
                'music_id'     => $music_id,
                'score'        => round( $score, 4 ),
                'plays_total'  => $plays_total,
                'plays_24h'    => $plays_24h,
                'plays_7d'     => $plays_7d,
                'plays_30d'    => $plays_30d,
                'favorites'    => $favorites,
                'avg_rating'   => round( $avg_rating, 2 ),
                'last_updated' => $now,
            );
        }

        // Ordena por score DESC para atribuir posições
        uasort( $rows, function( $a, $b ) {
            return $b['score'] <=> $a['score'];
        } );

        $position = 1;
        foreach ( $rows as $music_id => $data ) {

            // CORRIGIDO BUG B4 — passo 2: preserva posição anterior corretamente
            $data['position_prev'] = isset( $prev_positions[ $music_id ] )
                ? (int) $prev_positions[ $music_id ]->position
                : 0;

            $data['position'] = $position;

            $wpdb->replace(
                $wpdb->prefix . 'cv_ranking_cache',
                $data,
                array( '%d', '%f', '%d', '%d', '%d', '%d', '%d', '%f', '%d', '%d', '%s' )
            );

            // Notifica usuários quando música favorita entra no Top 10
            // Condição: posição nova <= 10 E posição anterior era > 10 (ou era nova)
            $prev_pos = $data['position_prev'];
            if ( $position <= 10 && ( $prev_pos > 10 || $prev_pos === 0 ) ) {
                if ( class_exists( 'CV_Notifications' ) ) {
                    CV_Notifications::notify_top10_entry( $music_id, $position );
                }
            }

            $position++;
        }

        // Limpa todos os transients de ranking para forçar recarregamento
        self::flush_cache();

        update_option( 'cv_ranking_last_update', $now );
    }

    /**
     * Top N músicas pelo score geral, com suporte a filtro por gênero.
     *
     * @param int    $limit  Número de resultados.
     * @param string $genre  Slug do gênero (opcional).
     * @return array
     */
    public static function get_top( $limit = 10, $genre = '' ) {
        $cache_key = 'cv_ranking_top_' . absint( $limit ) . '_' . sanitize_key( $genre );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        global $wpdb;

        if ( $genre ) {
            $results = self::get_top_by_genre( $limit, $genre );
        } else {
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT rc.*, p.post_title, p.post_name
                 FROM {$wpdb->prefix}cv_ranking_cache rc
                 INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
                 WHERE p.post_status = 'publish'
                 ORDER BY rc.score DESC
                 LIMIT %d",
                absint( $limit )
            ) );
        }

        $results = self::enrich_results( $results );
        set_transient( $cache_key, $results, HOUR_IN_SECONDS );
        return $results;
    }

    /**
     * Músicas mais recentes (para seção "Novidades").
     *
     * @param int $limit
     * @return array
     */
    public static function get_recent( $limit = 10 ) {
        $cache_key = 'cv_ranking_recent_' . absint( $limit );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        $posts = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $limit ),
            'orderby'        => 'date',
            'order'          => 'DESC',
            'meta_query'     => array(
                array(
                    'key'     => '_cv_ativo',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        ) );

        $results = array();
        foreach ( $posts as $post ) {
            $results[] = (object) array(
                'music_id'     => $post->ID,
                'post_title'   => $post->post_title,
                'post_name'    => $post->post_name,
                'plays_total'  => (int) get_post_meta( $post->ID, '_cv_plays_total', true ),
                'score'        => (float) get_post_meta( $post->ID, '_cv_score', true ),
                'favorites'    => (int) get_post_meta( $post->ID, '_cv_favorites', true ),
                'position'     => 0,
                'position_prev'=> 0,
            );
        }

        $results = self::enrich_results( $results );
        set_transient( $cache_key, $results, HOUR_IN_SECONDS );
        return $results;
    }

    /**
     * Músicas com mais favoritos (seção "Mais Favoritadas" da home).
     * Só entram músicas publicadas, ativas e com pelo menos 1 favorito.
     *
     * @param int $limit
     * @return array
     */
    public static function get_most_favorited( $limit = 8 ) {
        $cache_key = 'cv_ranking_favs_' . absint( $limit );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        $posts = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => absint( $limit ),
            'meta_key'       => CV_Fields::FAVORITES,
            'orderby'        => array( 'meta_value_num' => 'DESC', 'date' => 'DESC' ),
            'meta_query'     => array(
                array( 'key' => '_cv_ativo',           'value' => '1', 'compare' => '=' ),
                array( 'key' => CV_Fields::FAVORITES,  'value' => 0,   'compare' => '>', 'type' => 'NUMERIC' ),
            ),
        ) );

        $results = array();
        foreach ( $posts as $post ) {
            $results[] = (object) array(
                'music_id'     => $post->ID,
                'post_title'   => $post->post_title,
                'post_name'    => $post->post_name,
                'plays_total'  => (int) get_post_meta( $post->ID, '_cv_plays_total', true ),
                'score'        => (float) get_post_meta( $post->ID, '_cv_score', true ),
                'favorites'    => (int) get_post_meta( $post->ID, '_cv_favorites', true ),
                'position'     => 0,
                'position_prev'=> 0,
            );
        }

        $results = self::enrich_results( $results );
        set_transient( $cache_key, $results, HOUR_IN_SECONDS );
        return $results;
    }

    /**
     * Alias semântico de get_top() — melhores por score.
     *
     * @param int $limit
     * @return array
     */
    public static function get_best( $limit = 10 ) {
        return self::get_top( $limit );
    }

    /**
     * Ranking por período: 24h, 7d ou 30d.
     * Usado pelo shortcode [cv_ranking_periodo] e pela tela de ranking do admin.
     *
     * @param string $period  '24h' | '7d' | '30d'
     * @param int    $limit
     * @param string $genre   Slug do gênero (opcional)
     * @return array
     */
    public static function get_by_period( $period = '7d', $limit = 10, $genre = '' ) {
        $allowed = array( '24h', '7d', '30d' );
        if ( ! in_array( $period, $allowed, true ) ) { $period = '7d'; }

        $cache_key = 'cv_ranking_period_' . $period . '_' . absint( $limit ) . '_' . sanitize_key( $genre );
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        $col_map = array(
            '24h' => 'plays_24h',
            '7d'  => 'plays_7d',
            '30d' => 'plays_30d',
        );
        $col = $col_map[ $period ];

        global $wpdb;

        if ( $genre ) {
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT rc.*, p.post_title, p.post_name
                 FROM {$wpdb->prefix}cv_ranking_cache rc
                 INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
                 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = rc.music_id
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                 WHERE p.post_status = 'publish'
                   AND tt.taxonomy = 'cv_genre'
                   AND t.slug = %s
                 ORDER BY rc.{$col} DESC
                 LIMIT %d",
                sanitize_key( $genre ),
                absint( $limit )
            ) );
        } else {
            $results = $wpdb->get_results( $wpdb->prepare(
                "SELECT rc.*, p.post_title, p.post_name
                 FROM {$wpdb->prefix}cv_ranking_cache rc
                 INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
                 WHERE p.post_status = 'publish'
                 ORDER BY rc.{$col} DESC
                 LIMIT %d",
                absint( $limit )
            ) );
        }

        $results = self::enrich_results( $results );
        set_transient( $cache_key, $results, 15 * MINUTE_IN_SECONDS );
        return $results;
    }

    /**
     * Posição atual de uma música no ranking.
     *
     * @param int $music_id
     * @return int  0 se não estiver no ranking.
     */
    public static function get_position( $music_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT position, position_prev FROM {$wpdb->prefix}cv_ranking_cache WHERE music_id = %d",
            absint( $music_id )
        ) );
        return $row ? (int) $row->position : 0;
    }

    /**
     * Retorna indicador de tendência: 'up', 'down' ou 'same'.
     * Usa position_prev corrigido pelo Bug B4.
     *
     * @param int $music_id
     * @return string
     */
    public static function get_trend( $music_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT position, position_prev FROM {$wpdb->prefix}cv_ranking_cache WHERE music_id = %d",
            absint( $music_id )
        ) );

        if ( ! $row ) { return 'same'; }

        $pos      = (int) $row->position;
        $pos_prev = (int) $row->position_prev;

        if ( $pos_prev === 0 ) { return 'new'; }   // entrada nova no ranking
        if ( $pos < $pos_prev ) { return 'up'; }   // subiu (posição menor = melhor)
        if ( $pos > $pos_prev ) { return 'down'; } // caiu
        return 'same';
    }

    // ── Métodos privados ──────────────────────────────────────────

    /**
     * Top por gênero — join com taxonomias.
     */
    private static function get_top_by_genre( $limit, $genre ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT rc.*, p.post_title, p.post_name
             FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = rc.music_id
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
             INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE p.post_status = 'publish'
               AND tt.taxonomy = 'cv_genre'
               AND t.slug = %s
             ORDER BY rc.score DESC
             LIMIT %d",
            sanitize_key( $genre ),
            absint( $limit )
        ) );
    }

    /**
     * Enriquece resultados com URL, capa, gênero, compositor e artista.
     */
    // Acesso público ao enriquecimento (capa, gênero, artista, tendência),
    // usado pela "Seleção da Canção Verdadeira" do modo lançamento.
    public static function enrich( $results ) {
        return self::enrich_results( $results );
    }

    private static function enrich_results( $results ) {
        if ( empty( $results ) ) { return array(); }

        $enriched = array();
        foreach ( $results as $row ) {
            $id = isset( $row->music_id ) ? (int) $row->music_id : 0;
            if ( ! $id ) { continue; }

            $genres     = wp_get_post_terms( $id, 'cv_genre', array( 'fields' => 'names' ) );
            $cover      = get_the_post_thumbnail_url( $id, 'medium' );
            $youtube    = get_post_meta( $id, '_cv_youtube_url', true );
            $compositor = get_post_meta( $id, '_cv_compositor',  true );
            $artista    = get_post_meta( $id, '_cv_artista',     true );

            // Fallback da capa: thumbnail do YouTube
            if ( ! $cover && $youtube ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $youtube, $m );
                if ( ! empty( $m[1] ) ) {
                    $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg';
                }
            }

            // Indicador de tendência (usa position_prev corrigido)
            $trend = 'same';
            if ( isset( $row->position, $row->position_prev ) ) {
                $p = (int) $row->position;
                $pp = (int) $row->position_prev;
                if ( $pp === 0 ) { $trend = 'new'; }
                elseif ( $p < $pp ) { $trend = 'up'; }
                elseif ( $p > $pp ) { $trend = 'down'; }
            }

            $row->url        = get_permalink( $id );
            $row->cover      = $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg';
            $row->genre      = ( ! is_wp_error( $genres ) && $genres ) ? $genres[0] : '';
            $row->compositor = $compositor;
            $row->artista    = $artista;
            $row->youtube_id = '';
            if ( $youtube ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $youtube, $m );
                $row->youtube_id = $m[1] ?? '';
            }
            $row->audio_url = get_post_meta( $id, '_cv_audio_url', true ) ?: '';
            $row->trend = $trend;

            $enriched[] = $row;
        }

        return $enriched;
    }

    /**
     * Limpa todos os transients de cache de ranking.
     */
    public static function flush_cache() {
        global $wpdb;
        // Remove todos os transients do ranking com prefixo cv_ranking_
        $wpdb->query(
            "DELETE FROM {$wpdb->options}
             WHERE option_name LIKE '_transient_cv_ranking_%'
                OR option_name LIKE '_transient_timeout_cv_ranking_%'"
        );
    }
}
