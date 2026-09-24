<?php
// cancao-verdadeira/includes/admin/advanced/trait-cv-adv-ranking-periodo.php
// Parte do CV_Advanced (trait CV_Adv_Ranking_Periodo): ranking por período (diário, semanal, mensal) com cache,
// indicadores ↑ ↓ =, AJAX cv_ranking_period e rota REST.
// v2.37.0: saiu de class-cv-advanced.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Advanced::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Adv_Ranking_Periodo {

    // ════════════════════════════════════════════════════════════════
    // 1 + 2. RANKING POR PERÍODO + INDICADORES ↑ ↓
    // ════════════════════════════════════════════════════════════════

    /**
     * Estende o método recalculate() do CV_Ranking para incluir
     * plays_24h, plays_30d e position_prev (indicadores de tendência).
     * Chamado via hook após o recálculo padrão do cron.
     */
    public static function recalculate_periods() {
        global $wpdb;

        $now     = current_time( 'mysql' );
        $ts      = current_time( 'timestamp' );
        $ago_24h = date( 'Y-m-d H:i:s', $ts - DAY_IN_SECONDS );
        $ago_30d = date( 'Y-m-d H:i:s', $ts - ( 30 * DAY_IN_SECONDS ) );

        // Busca músicas do ranking_cache OU diretamente dos posts se o cache estiver vazio
        $rows = $wpdb->get_results(
            "SELECT music_id, position FROM {$wpdb->prefix}cv_ranking_cache ORDER BY position ASC"
        );

        // Fallback: cache ainda não existe — busca músicas ativas diretamente
        if ( empty( $rows ) ) {
            $posts = get_posts( array(
                'post_type'      => 'musica',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
                'meta_query'     => array(
                    array( 'key' => CV_Fields::ATIVO, 'value' => '1', 'compare' => '=' ),
                ),
            ) );
            if ( empty( $posts ) ) { return; }
            $position = 1;
            foreach ( $posts as $id ) {
                $rows[] = (object) array( 'music_id' => $id, 'position' => $position++ );
            }
        }

        foreach ( $rows as $row ) {
            $id = (int) $row->music_id;

            $p24h = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
                 WHERE music_id = %d AND played_at >= %s",
                $id, $ago_24h
            ) );

            $p30d = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
                 WHERE music_id = %d AND played_at >= %s",
                $id, $ago_30d
            ) );

            // Salva posição anterior antes de atualizar
            $pos_prev = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT position FROM {$wpdb->prefix}cv_ranking_cache WHERE music_id = %d",
                $id
            ) );

            $wpdb->update(
                $wpdb->prefix . 'cv_ranking_cache',
                array(
                    'plays_24h'    => $p24h,
                    'plays_30d'    => $p30d,
                    'position_prev'=> $pos_prev,
                    'last_updated' => $now,
                ),
                array( 'music_id' => $id ),
                array( '%d', '%d', '%d', '%s' ),
                array( '%d' )
            );

            // Salva nos post_meta para acesso rápido nos templates
            update_post_meta( $id, '_cv_plays_24h', $p24h );
            update_post_meta( $id, '_cv_plays_30d', $p30d );
        }

        // Limpa caches de período
        delete_transient( 'cv_ranking_daily' );
        delete_transient( 'cv_ranking_weekly' );
        delete_transient( 'cv_ranking_monthly' );
    }

    /**
     * Retorna Top N músicas de um período específico.
     *
     * @param string $period  'daily' | 'weekly' | 'monthly'
     * @param int    $limit   Número de resultados (padrão 10)
     */
    public static function get_ranking_period( $period = 'weekly', $limit = 10 ) {
        $cache_key = 'cv_ranking_' . $period . '_' . $limit;
        $cached    = get_transient( $cache_key );
        if ( false !== $cached ) { return $cached; }

        global $wpdb;
        $ts = current_time( 'timestamp' );

        switch ( $period ) {
            case 'daily':
                $since   = date( 'Y-m-d H:i:s', $ts - DAY_IN_SECONDS );
                $col     = 'plays_24h';
                break;
            case 'monthly':
                $since   = date( 'Y-m-d H:i:s', $ts - ( 30 * DAY_IN_SECONDS ) );
                $col     = 'plays_30d';
                break;
            default: // weekly
                $since   = date( 'Y-m-d H:i:s', $ts - ( 7 * DAY_IN_SECONDS ) );
                $col     = 'plays_7d';
                break;
        }

        $results = $wpdb->get_results( $wpdb->prepare(
            "SELECT rc.*, p.post_title, p.post_name
             FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             WHERE p.post_status = 'publish'
               AND rc.{$col} > 0
             ORDER BY rc.{$col} DESC
             LIMIT %d",
            $limit
        ) );

        // Enriquece com URL, capa e indicador de tendência
        $enriched = array();
        foreach ( $results as $i => $row ) {
            $id      = (int) $row->music_id;
            $cover   = get_the_post_thumbnail_url( $id, 'cv-cover' );
            $yt_url  = get_post_meta( $id, CV_Fields::YOUTUBE_URL, true );
            if ( ! $cover && $yt_url ) {
                $m = CV_Fields::youtube_match( $yt_url );
                $cover = isset( $m[1] ) ? "https://img.youtube.com/vi/{$m[1]}/mqdefault.jpg" : '';
            }

            // Indicador de tendência
            $pos_atual = $i + 1;
            $pos_prev  = (int) ( $row->position_prev ?? 0 );
            $trend     = self::get_trend( $pos_atual, $pos_prev );

            $enriched[] = array(
                'music_id'     => $id,
                'post_title'   => $row->post_title,
                'post_name'    => $row->post_name,
                'url'          => get_permalink( $id ),
                'cover'        => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'artista'      => get_post_meta( $id, CV_Fields::ARTISTA,    true ),
                'compositor'   => get_post_meta( $id, CV_Fields::COMPOSITOR, true ),
                'plays_period' => (int) ( $row->$col ?? 0 ),
                'plays_total'  => (int) $row->plays_total,
                'score'        => (float) $row->score,
                'position'     => $pos_atual,
                'position_prev'=> $pos_prev,
                'trend'        => $trend['icon'],
                'trend_label'  => $trend['label'],
                'trend_class'  => $trend['class'],
            );
        }

        // Se não tem dados de período ainda, cai no ranking geral
        if ( empty( $enriched ) && class_exists( 'CV_Ranking' ) ) {
            $enriched = CV_Ranking::get_top( $limit );
            foreach ( $enriched as &$row ) {
                $row->trend       = '●';
                $row->trend_label = 'Novo';
                $row->trend_class = 'cv-trend-new';
            }
        }

        set_transient( $cache_key, $enriched, HOUR_IN_SECONDS );
        return $enriched;
    }

    /**
     * Calcula o indicador de tendência com base na variação de posição.
     */
    public static function get_trend( $pos_atual, $pos_prev ) {
        if ( ! $pos_prev || $pos_prev === 0 ) {
            return array( 'icon' => '●', 'label' => 'Novo', 'class' => 'cv-trend-new' );
        }
        $diff = $pos_prev - $pos_atual; // positivo = subiu, negativo = caiu
        if ( $diff > 2 ) {
            return array( 'icon' => '↑↑', 'label' => '+' . $diff . ' posições', 'class' => 'cv-trend-up-strong' );
        } elseif ( $diff > 0 ) {
            return array( 'icon' => '↑',  'label' => '+' . $diff,               'class' => 'cv-trend-up' );
        } elseif ( $diff < -2 ) {
            return array( 'icon' => '↓↓', 'label' => $diff . ' posições',       'class' => 'cv-trend-down-strong' );
        } elseif ( $diff < 0 ) {
            return array( 'icon' => '↓',  'label' => (string) $diff,            'class' => 'cv-trend-down' );
        }
        return array( 'icon' => '=', 'label' => 'Estável', 'class' => 'cv-trend-stable' );
    }

    /**
     * AJAX: retorna ranking de período para o tema.
     * Parâmetros POST: period (daily|weekly|monthly), limit
     */
    public static function ajax_ranking_period() {
        check_ajax_referer( 'cv_play_nonce', 'nonce' );

        $period = sanitize_text_field( $_POST['period'] ?? 'weekly' );
        $limit  = min( absint( $_POST['limit'] ?? 10 ), 50 );

        if ( ! in_array( $period, array( 'daily', 'weekly', 'monthly' ), true ) ) {
            $period = 'weekly';
        }

        $ranking = self::get_ranking_period( $period, $limit );
        wp_send_json_success( array( 'period' => $period, 'ranking' => $ranking ) );
    }

    /**
     * REST: /cv/v1/ranking/{period}
     */
    public static function register_rest_routes() {
        register_rest_route( 'cv/v1', '/ranking/(?P<period>daily|weekly|monthly)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_ranking_period' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'period' => array(
                    'validate_callback' => function( $v ) {
                        return in_array( $v, array( 'daily', 'weekly', 'monthly' ), true );
                    },
                ),
            ),
        ) );
    }

    public static function rest_ranking_period( $request ) {
        $period = $request['period'];
        $limit  = min( absint( $request->get_param( 'limit' ) ?? 10 ), 50 );
        return self::get_ranking_period( $period, $limit );
    }
}
