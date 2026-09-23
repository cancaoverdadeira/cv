<?php
// cancao-verdadeira-plugin/includes/admin/class-cv-advanced.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Funcionalidades pós-MVP agrupadas em um único arquivo:
// 1. Ranking por período (diário, semanal, mensal) com cálculo e cache
// 2. Indicadores ↑ ↓ = de posição no ranking
// 3. Sistema de logs de ações administrativas
// 4. Níveis de permissão customizados (Editor / Gerente / Master)
// 5. Recomendação automática (melhores ranqueadas que o usuário não ouviu)
// 6. Exportação de usuários em CSV
// Compatível com PHP 7.2+. Toda lógica de negócio no plugin.
// v2.26.0: removidos o filtro por gênero do ranking e das recomendações.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Advanced {

    public static function init() {
        // Permissões: registra roles na ativação do plugin
        add_action( 'cv_activate_roles', array( __CLASS__, 'register_roles' ) );

        // Permissões: aplica capabilities nas páginas admin
        add_action( 'admin_menu', array( __CLASS__, 'adjust_menu_caps' ), 20 );

        // Logs: intercepta ações relevantes
        add_action( 'save_post_musica',          array( __CLASS__, 'log_music_save' ),    10, 2 );
        add_action( 'before_delete_post',        array( __CLASS__, 'log_music_delete' ),  10, 1 );
        add_action( 'wp_login',                  array( __CLASS__, 'log_login' ),         10, 2 );
        add_action( 'wp_logout',                 array( __CLASS__, 'log_logout' ) );
        add_action( 'cv_playlist_created',       array( __CLASS__, 'log_playlist' ),      10, 2 );

        // AJAX: ranking por período
        add_action( 'wp_ajax_cv_ranking_period',        array( __CLASS__, 'ajax_ranking_period' ) );
        add_action( 'wp_ajax_nopriv_cv_ranking_period', array( __CLASS__, 'ajax_ranking_period' ) );

        // AJAX: recomendação
        add_action( 'wp_ajax_cv_get_recommendations',        array( __CLASS__, 'ajax_recommendations' ) );
        add_action( 'wp_ajax_nopriv_cv_get_recommendations', array( __CLASS__, 'ajax_recommendations' ) );

        // AJAX: exportar CSV (só admin)
        add_action( 'wp_ajax_cv_export_users_csv', array( __CLASS__, 'ajax_export_users_csv' ) );

        // AJAX: ver logs
        add_action( 'wp_ajax_cv_get_logs', array( __CLASS__, 'ajax_get_logs' ) );

        // REST API: ranking por período
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }

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
                    array( 'key' => '_cv_ativo', 'value' => '1', 'compare' => '=' ),
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
            $yt_url  = get_post_meta( $id, '_cv_youtube_url', true );
            if ( ! $cover && $yt_url ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $yt_url, $m );
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
                'artista'      => get_post_meta( $id, '_cv_artista',    true ),
                'compositor'   => get_post_meta( $id, '_cv_compositor', true ),
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

    // ════════════════════════════════════════════════════════════════
    // 3. SISTEMA DE LOGS
    // ════════════════════════════════════════════════════════════════

    /**
     * Registra uma ação no log.
     *
     * @param string $action      Código da ação (ex: 'music_created')
     * @param string $description Descrição legível
     * @param string $obj_type    Tipo do objeto ('musica', 'playlist', 'user')
     * @param int    $obj_id      ID do objeto (0 se não aplicável)
     */
    public static function log( $action, $description, $obj_type = '', $obj_id = 0 ) {
        global $wpdb;

        $ip = '0.0.0.0';
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $candidate = trim( explode( ',', $_SERVER[ $key ] )[0] );
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    $ip = $candidate;
                    break;
                }
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'cv_action_logs',
            array(
                'user_id'     => get_current_user_id(),
                'action'      => sanitize_key( $action ),
                'object_type' => sanitize_text_field( $obj_type ),
                'object_id'   => absint( $obj_id ),
                'description' => sanitize_textarea_field( $description ),
                'ip_address'  => $ip,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        // Limpa logs com mais de 90 dias automaticamente
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}cv_action_logs WHERE created_at < %s",
            date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 90 * DAY_IN_SECONDS ) )
        ) );
    }

    // Hooks de log
    public static function log_music_save( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( 'musica' !== $post->post_type ) { return; }
        $action = ( get_post_status( $post_id ) === 'publish' ) ? 'music_published' : 'music_saved';
        self::log( $action, 'Música "' . $post->post_title . '" salva/publicada.', 'musica', $post_id );
    }

    public static function log_music_delete( $post_id ) {
        if ( 'musica' !== get_post_type( $post_id ) ) { return; }
        self::log( 'music_deleted', 'Música "' . get_the_title( $post_id ) . '" excluída.', 'musica', $post_id );
    }

    public static function log_login( $user_login, $user ) {
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'cv_gerente' ) ) {
            self::log( 'admin_login', 'Login de administrador: ' . $user_login, 'user', $user->ID );
        }
    }

    public static function log_logout() {
        $user = wp_get_current_user();
        if ( $user && $user->ID ) {
            self::log( 'admin_logout', 'Logout: ' . $user->user_login, 'user', $user->ID );
        }
    }

    public static function log_playlist( $playlist_id, $name ) {
        self::log( 'playlist_created', 'Playlist criada: "' . $name . '"', 'playlist', $playlist_id );
    }

    /**
     * Retorna os logs recentes para a página admin.
     */
    public static function get_logs( $limit = 50, $action = '', $user_id = 0 ) {
        global $wpdb;

        $where = array( '1=1' );
        $args  = array();

        if ( $action ) {
            $where[] = 'l.action = %s';
            $args[]  = $action;
        }
        if ( $user_id ) {
            $where[] = 'l.user_id = %d';
            $args[]  = $user_id;
        }

        $args[] = $limit;

        $sql = "SELECT l.*, u.display_name AS user_name, u.user_email
                FROM {$wpdb->prefix}cv_action_logs l
                LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY l.created_at DESC
                LIMIT %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
    }

    public static function ajax_get_logs() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'cv_master' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        $logs = self::get_logs(
            absint( $_POST['limit'] ?? 50 ),
            sanitize_text_field( $_POST['action_filter'] ?? '' ),
            absint( $_POST['user_id'] ?? 0 )
        );
        wp_send_json_success( array( 'logs' => $logs ) );
    }

    // ════════════════════════════════════════════════════════════════
    // 4. NÍVEIS DE PERMISSÃO
    // ════════════════════════════════════════════════════════════════

    /**
     * Registra os três roles customizados do Canção Verdadeira.
     * Chamado uma vez na ativação do plugin.
     *
     * Editor    — pode criar e editar músicas, mas não excluir nem ver configs
     * Gerente   — tudo do Editor + excluir músicas, gerenciar playlists e usuários
     * Master    — tudo do Gerente + configurações e logs (sem ser admin do WP)
     */
    public static function register_roles() {
        // Capabilities base do WordPress que vamos usar
        $base_caps = array(
            'read'                   => true,
            'upload_files'           => true,
        );

        // ── Editor ────────────────────────────────────────────────
        add_role( 'cv_editor', 'CV Editor', array_merge( $base_caps, array(
            'cv_edit_musicas'        => true,
            'cv_create_musicas'      => true,
            'cv_read_musicas'        => true,
            // Capabilities nativas para o CPT musica
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
        ) ) );

        // ── Gerente ────────────────────────────────────────────────
        add_role( 'cv_gerente', 'CV Gerente', array_merge( $base_caps, array(
            'cv_edit_musicas'        => true,
            'cv_create_musicas'      => true,
            'cv_delete_musicas'      => true,
            'cv_read_musicas'        => true,
            'cv_manage_playlists'    => true,
            'cv_manage_users'        => true,
            'cv_view_ranking'        => true,
            'cv_view_subscribers'    => true,
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_published_posts' => true,
            'upload_files'           => true,
        ) ) );

        // ── Master ─────────────────────────────────────────────────
        add_role( 'cv_master', 'CV Master', array_merge( $base_caps, array(
            'cv_edit_musicas'        => true,
            'cv_create_musicas'      => true,
            'cv_delete_musicas'      => true,
            'cv_read_musicas'        => true,
            'cv_manage_playlists'    => true,
            'cv_manage_users'        => true,
            'cv_manage_settings'     => true,
            'cv_view_logs'           => true,
            'cv_view_ranking'        => true,
            'cv_view_subscribers'    => true,
            'cv_recalculate_ranking' => true,
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_published_posts' => true,
            'upload_files'           => true,
            'manage_options'         => false, // não é admin WP
        ) ) );
    }

    /**
     * Remove os roles customizados (chamado na desativação do plugin).
     */
    public static function remove_roles() {
        remove_role( 'cv_editor' );
        remove_role( 'cv_gerente' );
        remove_role( 'cv_master' );
    }

    /**
     * Ajusta as capabilities exigidas nos submenus do painel
     * para que Gerentes e Masters também possam acessá-los.
     */
    public static function adjust_menu_caps() {
        global $submenu;
        if ( ! isset( $submenu['cancao-verdadeira'] ) ) { return; }

        foreach ( $submenu['cancao-verdadeira'] as &$item ) {
            // Gerente e Master podem acessar tudo exceto Configurações
            if ( isset( $item[1] ) && $item[1] === 'manage_options' ) {
                // Configurações só para admin e master
                if ( isset( $item[2] ) && $item[2] === 'cv-settings' ) {
                    $item[1] = current_user_can( 'manage_options' ) || current_user_can( 'cv_manage_settings' )
                               ? 'read' : 'manage_options';
                } else {
                    // Demais páginas: gerente e master podem acessar
                    if ( current_user_can( 'cv_gerente' ) || current_user_can( 'cv_master' ) ) {
                        $item[1] = 'read';
                    }
                }
            }
        }
    }

    /**
     * Verifica se o usuário atual pode executar uma ação do plugin.
     * Uso: CV_Advanced::can('edit_musicas')
     *
     * @param string $action  Ação sem prefixo 'cv_' (ex: 'edit_musicas')
     */
    public static function can( $action ) {
        return current_user_can( 'manage_options' )
            || current_user_can( 'cv_' . $action );
    }

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
            'meta_key'       => '_cv_score',
            'orderby'        => 'meta_value_num',
            'order'          => 'DESC',
            'meta_query'     => array(
                array( 'key' => '_cv_ativo', 'value' => '1', 'compare' => '=' ),
            ),
        );

        if ( ! empty( $heard_ids ) ) {
            $args['post__not_in'] = $heard_ids;
        }

        $posts  = get_posts( $args );
        $result = array();

        foreach ( array_slice( $posts, 0, $limit ) as $post ) {
            $yt_url  = get_post_meta( $post->ID, '_cv_youtube_url', true );
            $cover   = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover && $yt_url ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $yt_url, $m );
                $cover = isset( $m[1] ) ? "https://img.youtube.com/vi/{$m[1]}/mqdefault.jpg" : '';
            }

            $result[] = array(
                'id'        => $post->ID,
                'title'     => $post->post_title,
                'url'       => get_permalink( $post->ID ),
                'cover'     => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'artista'   => get_post_meta( $post->ID, '_cv_artista', true ),
                'score'     => (float) get_post_meta( $post->ID, '_cv_score', true ),
                'plays'     => (int) get_post_meta( $post->ID, '_cv_plays_total', true ),
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

    // ════════════════════════════════════════════════════════════════
    // 6. EXPORTAÇÃO DE USUÁRIOS CSV
    // ════════════════════════════════════════════════════════════════

    /**
     * AJAX: gera e faz download de um CSV com todos os usuários.
     * Inclui: nome, e-mail, papel, data de cadastro, favoritos, playlists, plays.
     */
    public static function ajax_export_users_csv() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'cv_master' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $users = get_users( array(
            'number'  => -1,
            'orderby' => 'registered',
            'order'   => 'ASC',
        ) );

        // Contadores em lote para performance
        $user_ids = wp_list_pluck( $users, 'ID' );
        $fav_map  = array();
        $pl_map   = array();

        if ( ! empty( $user_ids ) ) {
            $ids_in = implode( ',', array_map( 'intval', $user_ids ) );

            $favs = $wpdb->get_results(
                "SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_favorites
                 WHERE user_id IN ($ids_in) GROUP BY user_id"
            );
            foreach ( $favs as $r ) { $fav_map[ $r->user_id ] = (int) $r->total; }

            $pls = $wpdb->get_results(
                "SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_playlists
                 WHERE user_id IN ($ids_in) GROUP BY user_id"
            );
            foreach ( $pls as $r ) { $pl_map[ $r->user_id ] = (int) $r->total; }
        }

        // Monta CSV em memória
        $lines   = array();
        $lines[] = implode( ';', array(
            'ID', 'Nome', 'Login', 'E-mail', 'Papel',
            'Cadastro', 'Favoritos', 'Playlists', 'Plays (historico)',
        ) );

        foreach ( $users as $user ) {
            $history = get_user_meta( $user->ID, '_cv_play_history', true );
            $plays   = is_array( $history ) ? count( $history ) : 0;

            $lines[] = implode( ';', array(
                $user->ID,
                '"' . str_replace( '"', '""', $user->display_name ) . '"',
                $user->user_login,
                $user->user_email,
                implode( '/', $user->roles ),
                date( 'd/m/Y', strtotime( $user->user_registered ) ),
                $fav_map[ $user->ID ] ?? 0,
                $pl_map[ $user->ID ]  ?? 0,
                $plays,
            ) );
        }

        $csv      = "\xEF\xBB\xBF" . implode( "\n", $lines );
        $filename = 'cancao-verdadeira-usuarios-' . date( 'Y-m-d' ) . '.csv';

        // Download direto — evita limite de memória com base64+JSON
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $csv ) );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        echo $csv;
        exit;
    }
}

CV_Advanced::init();
