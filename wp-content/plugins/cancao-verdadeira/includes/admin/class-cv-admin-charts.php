<?php
// cancao-verdadeira-plugin/includes/admin/class-cv-admin-charts.php
// Gerado em: 2026-06-25 18:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo de Analytics e Gráficos do painel administrativo.
// Fornece 8 gráficos Chart.js: plays diários (30d), plays por hora,
// gêneros mais ouvidos (pizza), crescimento de usuários (linha),
// tendência do ranking (barras), avaliação média por gênero (radar),
// favoritos acumulados (área) e top músicas comparativo (barras horizontais).
// Todos os dados são servidos via AJAX (wp_ajax) com cache de 1 hora.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin_Charts {

    public static function init() {
        add_action( 'wp_ajax_cv_chart_plays_daily',    array( __CLASS__, 'ajax_plays_daily' ) );
        add_action( 'wp_ajax_cv_chart_plays_hourly',   array( __CLASS__, 'ajax_plays_hourly' ) );
        add_action( 'wp_ajax_cv_chart_genres',         array( __CLASS__, 'ajax_genres' ) );
        add_action( 'wp_ajax_cv_chart_users_growth',   array( __CLASS__, 'ajax_users_growth' ) );
        add_action( 'wp_ajax_cv_chart_ranking_trend',  array( __CLASS__, 'ajax_ranking_trend' ) );
        add_action( 'wp_ajax_cv_chart_rating_genre',   array( __CLASS__, 'ajax_rating_genre' ) );
        add_action( 'wp_ajax_cv_chart_favorites',      array( __CLASS__, 'ajax_favorites' ) );
        add_action( 'wp_ajax_cv_chart_top_compare',    array( __CLASS__, 'ajax_top_compare' ) );
    }

    // ── Plays diários — últimos 30 dias ─────────────────────────────────
    public static function ajax_plays_daily() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_plays_daily' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT DATE(played_at) AS dia, COUNT(*) AS total
             FROM {$wpdb->prefix}cv_plays_log
             WHERE played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(played_at)
             ORDER BY dia ASC"
        );

        // Preenche todos os 30 dias (mesmo os sem play)
        $labels = array();
        $values = array();
        $map    = array();
        foreach ( $rows as $r ) { $map[ $r->dia ] = (int) $r->total; }
        for ( $i = 29; $i >= 0; $i-- ) {
            $d        = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $labels[] = date( 'd/m', strtotime( $d ) );
            $values[] = $map[ $d ] ?? 0;
        }

        $data = compact( 'labels', 'values' );
        set_transient( 'cv_chart_plays_daily', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Plays por hora do dia (média) ───────────────────────────────────
    public static function ajax_plays_hourly() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_plays_hourly' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT HOUR(played_at) AS hora, COUNT(*) AS total
             FROM {$wpdb->prefix}cv_plays_log
             WHERE played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY HOUR(played_at)
             ORDER BY hora ASC"
        );

        $map = array();
        foreach ( $rows as $r ) { $map[ (int) $r->hora ] = (int) $r->total; }

        $labels = array();
        $values = array();
        for ( $h = 0; $h < 24; $h++ ) {
            $labels[] = str_pad( $h, 2, '0', STR_PAD_LEFT ) . 'h';
            $values[] = $map[ $h ] ?? 0;
        }

        $data = compact( 'labels', 'values' );
        set_transient( 'cv_chart_plays_hourly', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Plays por gênero (pizza) ────────────────────────────────────────
    public static function ajax_genres() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_genres' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        // Plays por gênero via taxonomia
        $rows = $wpdb->get_results(
            "SELECT t.name AS genero, COUNT(pl.id) AS total
             FROM {$wpdb->prefix}cv_plays_log pl
             INNER JOIN {$wpdb->posts} p ON p.ID = pl.music_id
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'cv_genre'
             INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE pl.played_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY t.name
             ORDER BY total DESC"
        );

        // Fallback: conta músicas por gênero se não houver plays
        if ( empty( $rows ) ) {
            $rows = $wpdb->get_results(
                "SELECT t.name AS genero, COUNT(DISTINCT p.ID) AS total
                 FROM {$wpdb->posts} p
                 INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                 INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'cv_genre'
                 INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
                 WHERE p.post_type = 'musica' AND p.post_status = 'publish'
                 GROUP BY t.name
                 ORDER BY total DESC"
            );
        }

        $colors = array(
            '#D4A017', '#E8C547', '#8B4513', '#556B2F',
            '#8B0000', '#4169E1', '#696969', '#9B59B6',
        );

        $labels = array();
        $values = array();
        $bg     = array();
        foreach ( $rows as $i => $r ) {
            $labels[] = $r->genero;
            $values[] = (int) $r->total;
            $bg[]     = $colors[ $i % count( $colors ) ];
        }

        $data = compact( 'labels', 'values', 'bg' );
        set_transient( 'cv_chart_genres', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Crescimento de usuários — últimos 30 dias ───────────────────────
    public static function ajax_users_growth() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_users_growth' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT DATE(user_registered) AS dia, COUNT(*) AS total
             FROM {$wpdb->users}
             WHERE user_registered >= DATE_SUB(NOW(), INTERVAL 30 DAY)
             GROUP BY DATE(user_registered)
             ORDER BY dia ASC"
        );

        $map = array();
        foreach ( $rows as $r ) { $map[ $r->dia ] = (int) $r->total; }

        $labels    = array();
        $values    = array();
        $acumulado = array();
        $total_ant = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->users}
             WHERE user_registered < DATE_SUB(NOW(), INTERVAL 30 DAY)"
        );
        $acc = $total_ant;

        for ( $i = 29; $i >= 0; $i-- ) {
            $d        = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $novos    = $map[ $d ] ?? 0;
            $acc     += $novos;
            $labels[] = date( 'd/m', strtotime( $d ) );
            $values[] = $novos;
            $acumulado[] = $acc;
        }

        $data = compact( 'labels', 'values', 'acumulado' );
        set_transient( 'cv_chart_users_growth', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Tendência do ranking — top 10 scores ───────────────────────────
    public static function ajax_ranking_trend() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_ranking_trend' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT rc.music_id, p.post_title, rc.score, rc.plays_total, rc.favorites, rc.avg_rating
             FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             WHERE p.post_status = 'publish'
             ORDER BY rc.score DESC
             LIMIT 10"
        );

        $labels  = array();
        $scores  = array();
        $plays   = array();
        $favs    = array();
        foreach ( $rows as $r ) {
            // Trunca título longo
            $titulo   = mb_strlen( $r->post_title ) > 22
                ? mb_substr( $r->post_title, 0, 20 ) . '…'
                : $r->post_title;
            $labels[] = $titulo;
            $scores[] = round( (float) $r->score, 1 );
            $plays[]  = (int) $r->plays_total;
            $favs[]   = (int) $r->favorites;
        }

        $data = compact( 'labels', 'scores', 'plays', 'favs' );
        set_transient( 'cv_chart_ranking_trend', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Avaliação média por gênero (radar) ──────────────────────────────
    public static function ajax_rating_genre() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_rating_genre' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT t.name AS genero,
                    AVG(CAST(pm.meta_value AS DECIMAL(5,2))) AS avg_rating,
                    COUNT(p.ID) AS total_musicas
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_cv_avg_rating'
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'cv_genre'
             INNER JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
             WHERE p.post_type = 'musica' AND p.post_status = 'publish'
               AND pm.meta_value > 0
             GROUP BY t.name
             ORDER BY avg_rating DESC"
        );

        $labels  = array();
        $ratings = array();
        $counts  = array();
        foreach ( $rows as $r ) {
            $labels[]  = $r->genero;
            $ratings[] = round( (float) $r->avg_rating, 2 );
            $counts[]  = (int) $r->total_musicas;
        }

        $data = compact( 'labels', 'ratings', 'counts' );
        set_transient( 'cv_chart_rating_genre', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Favoritos acumulados — últimos 30 dias (área) ───────────────────
    public static function ajax_favorites() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_favorites' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;

        // Verifica se tabela tem coluna created_at
        $col = $wpdb->get_var(
            "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_NAME = '{$wpdb->prefix}cv_favorites'
               AND COLUMN_NAME = 'created_at'
               AND TABLE_SCHEMA = DATABASE()"
        );

        if ( $col ) {
            $rows = $wpdb->get_results(
                "SELECT DATE(created_at) AS dia, COUNT(*) AS total
                 FROM {$wpdb->prefix}cv_favorites
                 WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                 GROUP BY DATE(created_at)
                 ORDER BY dia ASC"
            );
        } else {
            // Sem timestamp — retorna total estático distribuído uniformemente
            $total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites" );
            $rows  = array();
        }

        $map = array();
        foreach ( $rows as $r ) { $map[ $r->dia ] = (int) $r->total; }

        $labels    = array();
        $values    = array();
        $acumulado = array();
        $acc       = 0;

        for ( $i = 29; $i >= 0; $i-- ) {
            $d        = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $novos    = $map[ $d ] ?? 0;
            $acc     += $novos;
            $labels[] = date( 'd/m', strtotime( $d ) );
            $values[] = $novos;
            $acumulado[] = $acc;
        }

        $data = compact( 'labels', 'values', 'acumulado' );
        set_transient( 'cv_chart_favorites', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Top 10 comparativo: plays vs favoritos (barras horiz.) ──────────
    public static function ajax_top_compare() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_chart_top_compare' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;
        $rows = $wpdb->get_results(
            "SELECT rc.music_id, p.post_title, rc.plays_total, rc.favorites, rc.avg_rating, rc.score
             FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             WHERE p.post_status = 'publish'
             ORDER BY rc.plays_total DESC
             LIMIT 10"
        );

        $labels  = array();
        $plays   = array();
        $favs    = array();
        $ratings = array();
        foreach ( $rows as $r ) {
            $titulo   = mb_strlen( $r->post_title ) > 25
                ? mb_substr( $r->post_title, 0, 23 ) . '…'
                : $r->post_title;
            $labels[] = $titulo;
            $plays[]  = (int) $r->plays_total;
            $favs[]   = (int) $r->favorites;
            $ratings[] = round( (float) $r->avg_rating, 1 );
        }

        $data = compact( 'labels', 'plays', 'favs', 'ratings' );
        set_transient( 'cv_chart_top_compare', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Limpa todos os caches de gráficos ───────────────────────────────
    public static function clear_cache() {
        $keys = array(
            'cv_chart_plays_daily', 'cv_chart_plays_hourly', 'cv_chart_genres',
            'cv_chart_users_growth', 'cv_chart_ranking_trend', 'cv_chart_rating_genre',
            'cv_chart_favorites', 'cv_chart_top_compare',
        );
        foreach ( $keys as $k ) { delete_transient( $k ); }
    }

    // ── Página Analytics no painel ──────────────────────────────────────
    public static function page_analytics() {
        global $wpdb;
        $nonce = wp_create_nonce( 'cv_admin_nonce' );
        $ajax  = admin_url( 'admin-ajax.php' );

        // KPIs em tempo real para o hero
        $plays_hoje  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE DATE(played_at)=CURDATE()");
        $plays_7d    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE played_at >= NOW()-INTERVAL 7 DAY");
        $plays_total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log");
        $musicas_pub = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='musica' AND post_status='publish'");
        $hora_pico   = $wpdb->get_var("SELECT HOUR(played_at) AS h FROM {$wpdb->prefix}cv_plays_log GROUP BY h ORDER BY COUNT(*) DESC LIMIT 1");
        $hora_pico   = $hora_pico !== null ? $hora_pico.'h' : '—';
        $genero_top  = $wpdb->get_var(
            "SELECT t.name FROM {$wpdb->prefix}cv_plays_log pl
             JOIN {$wpdb->posts} p ON p.ID=pl.music_id
             JOIN {$wpdb->term_relationships} tr ON tr.object_id=p.ID
             JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id=tr.term_taxonomy_id AND tt.taxonomy='cv_genre'
             JOIN {$wpdb->terms} t ON t.term_id=tt.term_id
             GROUP BY t.term_id ORDER BY COUNT(*) DESC LIMIT 1"
        );
        ?>
        <div class="cv-admin-wrap cv-analytics-v2" style="max-width:1200px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <?php echo CV_Admin::btn_voltar(); ?>
        
        <style>
        .cv-analytics-v2 * { box-sizing:border-box; }

        /* Hero */
        .cv-an-hero {
            background:linear-gradient(135deg,#0a0a1a 0%,#1a1a2e 50%,#1a1a1a 100%);
            border:1px solid #1a1a3a;border-radius:16px;padding:24px 32px;
            margin-bottom:24px;display:flex;align-items:center;gap:20px;
        }
        .cv-an-hero-left h1 { color:#7b68ee;font-size:22px;margin:0 0 2px;font-weight:800;letter-spacing:-.3px; }
        .cv-an-hero-left p  { color:#555;font-size:12px;margin:0; }
        .cv-an-hero-controls { margin-left:auto;display:flex;gap:10px;align-items:center; }
        .cv-an-select { background:#111;border:1px solid #1a1a3a;color:#aaa;border-radius:8px;padding:8px 14px;font-size:12px;outline:none;cursor:pointer; }
        .cv-an-select:focus { border-color:#7b68ee; }
        .cv-an-btn { font-size:11px;font-weight:600;padding:8px 16px;border-radius:8px;cursor:pointer;transition:all .2s;border:1px solid #1a1a3a;background:#111;color:#666; }
        .cv-an-btn:hover { border-color:#7b68ee;color:#7b68ee; }
        .cv-an-btn.primary { background:#7b68ee;color:#fff;border-color:#7b68ee; }
        .cv-an-btn.primary:hover { background:#9b88ff; }

        /* KPI strip */
        .cv-an-kpis {
            display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin-bottom:24px;
        }
        @media(max-width:900px){.cv-an-kpis{grid-template-columns:repeat(3,1fr);}}
        .cv-an-kpi {
            background:#1a1a1a;border:1px solid #1e1e2e;border-radius:12px;
            padding:16px 14px;position:relative;overflow:hidden;transition:transform .2s,border-color .2s;
        }
        .cv-an-kpi:hover { transform:translateY(-2px);border-color:#2e2e4e; }
        .cv-an-kpi::after { content:"";position:absolute;bottom:0;left:0;right:0;height:2px; }
        .cv-an-kpi.k1::after{background:linear-gradient(90deg,#D4A017,#FFD700);}
        .cv-an-kpi.k2::after{background:linear-gradient(90deg,#7b68ee,#9b88ff);}
        .cv-an-kpi.k3::after{background:linear-gradient(90deg,#1db954,#22d460);}
        .cv-an-kpi.k4::after{background:linear-gradient(90deg,#e74c3c,#ff6b6b);}
        .cv-an-kpi.k5::after{background:linear-gradient(90deg,#4a90d9,#5aa8f0);}
        .cv-an-kpi-icon  { font-size:16px;margin-bottom:6px;display:block; }
        .cv-an-kpi-val   { font-size:24px;font-weight:800;color:#f0f0f0;line-height:1;margin-bottom:2px; }
        .cv-an-kpi-label { font-size:10px;color:#444;text-transform:uppercase;letter-spacing:.5px; }

        /* Loading */
        .cv-an-loading { text-align:center;padding:80px 0;color:#555; }
        .cv-an-spinner { font-size:32px;animation:cv-spin2 1s linear infinite;display:inline-block;margin-bottom:10px; }
        @keyframes cv-spin2 { from{transform:rotate(0)} to{transform:rotate(360deg)} }

        /* Grid principal */
        .cv-an-row-hero  { display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px;align-items:start; }
        .cv-an-row-mid   { display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px;margin-bottom:20px;align-items:start; }
        .cv-an-row-bot   { display:grid;grid-template-columns:1.5fr 1fr;gap:20px;margin-bottom:20px;align-items:start; }
        @media(max-width:1000px){.cv-an-row-hero,.cv-an-row-mid,.cv-an-row-bot{grid-template-columns:1fr;}}

        /* Cards de gráfico */
        .cv-an-card {
            background:#1a1a1a;border:1px solid #1e1e2e;border-radius:14px;overflow:hidden;
        }
        .cv-an-card-header {
            padding:14px 20px 10px;border-bottom:1px solid #111;
            display:flex;align-items:center;justify-content:space-between;
        }
        .cv-an-card-title { font-size:13px;font-weight:700;color:#ddd; }
        .cv-an-card-sub   { font-size:11px;color:#555; }
        .cv-an-card-badge { font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px; }
        .badge-gold  { background:rgba(212,160,23,.12);color:#D4A017; }
        .badge-green { background:rgba(29,185,84,.12);color:#1db954; }
        .badge-purple{ background:rgba(123,104,238,.12);color:#7b68ee; }
        .badge-red   { background:rgba(231,76,60,.12);color:#e74c3c; }
        .badge-blue  { background:rgba(74,144,217,.12);color:#4a90d9; }

        .cv-an-card-body { padding:16px 20px 20px;position:relative; }
        .cv-an-card-body canvas { width:100%!important; }

        /* Tamanhos de canvas */
        .cv-an-h-lg  { height:200px; }
        .cv-an-h-md  { height:170px; }
        .cv-an-h-sm  { height:160px; }

        /* Seção separadora */
        .cv-an-section-title {
            font-size:10px;text-transform:uppercase;letter-spacing:.8px;color:#333;
            margin:0 0 14px;font-weight:700;padding-left:2px;
            display:flex;align-items:center;gap:8px;
        }
        .cv-an-section-title::after { content:"";flex:1;height:1px;background:#1a1a1a; }
        </style>

        <!-- Hero -->
        <div class="cv-an-hero">
            <div style="font-size:36px;filter:drop-shadow(0 0 16px rgba(123,104,238,.4))">📊</div>
            <div class="cv-an-hero-left">
                <h1>Analytics — Canção Verdadeira</h1>
                <p>Visão executiva completa · plays, usuários, gêneros, ranking e engajamento</p>
            </div>
            <div class="cv-an-hero-controls">
                <select id="cv-chart-period" class="cv-an-select">
                    <option value="30">Últimos 30 dias</option>
                    <option value="7">Últimos 7 dias</option>
                    <option value="90">Últimos 90 dias</option>
                </select>
                <button id="cv-charts-refresh" class="cv-an-btn primary">🔄 Atualizar</button>
                <button id="cv-charts-export-img" class="cv-an-btn">📥 Exportar</button>
            </div>
        </div>

        <!-- KPI Strip -->
        <div class="cv-an-kpis">
            <div class="cv-an-kpi k1">
                <span class="cv-an-kpi-icon">▶</span>
                <div class="cv-an-kpi-val"><?php echo number_format($plays_hoje); ?></div>
                <div class="cv-an-kpi-label">Plays hoje</div>
            </div>
            <div class="cv-an-kpi k2">
                <span class="cv-an-kpi-icon">📅</span>
                <div class="cv-an-kpi-val"><?php echo number_format($plays_7d); ?></div>
                <div class="cv-an-kpi-label">Plays 7 dias</div>
            </div>
            <div class="cv-an-kpi k3">
                <span class="cv-an-kpi-icon">🎵</span>
                <div class="cv-an-kpi-val"><?php echo number_format($musicas_pub); ?></div>
                <div class="cv-an-kpi-label">Músicas ativas</div>
            </div>
            <div class="cv-an-kpi k4">
                <span class="cv-an-kpi-icon">🕐</span>
                <div class="cv-an-kpi-val"><?php echo esc_html($hora_pico); ?></div>
                <div class="cv-an-kpi-label">Hora de pico</div>
            </div>
            <div class="cv-an-kpi k5">
                <span class="cv-an-kpi-icon">🎸</span>
                <div class="cv-an-kpi-val" style="font-size:14px;padding-top:4px"><?php echo esc_html($genero_top ?: '—'); ?></div>
                <div class="cv-an-kpi-label">Gênero líder</div>
            </div>
        </div>

        <!-- Loading -->
        <div id="cv-charts-loading" class="cv-an-loading">
            <div class="cv-an-spinner">⏳</div>
            <p style="font-size:13px">Carregando dados de analytics...</p>
        </div>

        <!-- Grid de gráficos -->
        <div id="cv-charts-wrap" style="display:none">

            <!-- LINHA 1: Plays diários (destaque) + Pico por hora -->
            <div class="cv-an-section-title">Reproduções</div>
            <div class="cv-an-row-hero">
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">📈 Plays Diários</span>
                        <span class="cv-an-card-badge badge-gold" id="cv-plays-total-label"></span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-lg">
                        <canvas id="chart-plays-daily"></canvas>
                    </div>
                </div>
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">🕐 Pico por Hora</span>
                        <span class="cv-an-card-badge badge-purple">Audiência</span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-lg">
                        <canvas id="chart-plays-hourly"></canvas>
                    </div>
                </div>
            </div>

            <!-- LINHA 2: Gêneros + Avaliação + Favoritos (3 colunas) -->
            <div class="cv-an-section-title">Conteúdo &amp; Engajamento</div>
            <div class="cv-an-row-mid">
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">🎸 Plays por Gênero</span>
                        <span class="cv-an-card-badge badge-gold">Pizza</span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-md">
                        <canvas id="chart-genres"></canvas>
                    </div>
                </div>
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">⭐ Avaliação por Gênero</span>
                        <span class="cv-an-card-badge badge-purple">Radar</span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-md">
                        <canvas id="chart-rating-genre"></canvas>
                    </div>
                </div>
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">❤ Favoritos Acumulados</span>
                        <span class="cv-an-card-badge badge-red">30 dias</span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-md">
                        <canvas id="chart-favorites"></canvas>
                    </div>
                </div>
            </div>

            <!-- LINHA 3: Usuários + Top Compare -->
            <div class="cv-an-section-title">Usuários &amp; Ranking</div>
            <div class="cv-an-row-bot">
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">👥 Crescimento de Usuários</span>
                        <span class="cv-an-card-badge badge-green" id="cv-users-total-label"></span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-md">
                        <canvas id="chart-users-growth"></canvas>
                    </div>
                </div>
                <div class="cv-an-card">
                    <div class="cv-an-card-header">
                        <span class="cv-an-card-title">🏆 Score das Top 10</span>
                        <span class="cv-an-card-badge badge-gold">Ranking</span>
                    </div>
                    <div class="cv-an-card-body cv-an-h-md">
                        <canvas id="chart-ranking-trend"></canvas>
                    </div>
                </div>
            </div>

            <!-- LINHA 4: Top Compare (largura total) -->
            <div class="cv-an-card" style="margin-bottom:24px">
                <div class="cv-an-card-header">
                    <span class="cv-an-card-title">🎵 Top 10 — Plays vs Favoritos</span>
                    <span class="cv-an-card-badge badge-blue">Comparativo</span>
                </div>
                <div class="cv-an-card-body cv-an-h-sm">
                    <canvas id="chart-top-compare"></canvas>
                </div>
            </div>

        </div><!-- /#cv-charts-wrap -->

        <script>
        jQuery(function($){
            var AJAX  = '<?php echo esc_js( $ajax ); ?>';
            var NONCE = '<?php echo esc_js( $nonce ); ?>';
            var GOLD='#D4A017',GOLD2='#FFD700',CREAM='#F5F0E0',PURPLE='#7b68ee',GREEN='#1db954',RED='#e74c3c',BLUE='#4a90d9';
            Chart.defaults.color = '#888';
            Chart.defaults.borderColor = 'rgba(255,255,255,0.04)';
            Chart.defaults.font.family = "'Lato','Arial',sans-serif";
            Chart.defaults.font.size   = 11;
            var charts = {};
            function destroy(id){ if(charts[id]){charts[id].destroy();delete charts[id];} }
            function grid(){ return {color:'rgba(255,255,255,0.04)',drawBorder:false}; }
            function ticks(){ return {color:'#444',maxRotation:0}; }
            function tip(accent){
                return {backgroundColor:'#111',borderColor:accent||'rgba(123,104,238,.3)',borderWidth:1,
                        titleColor:accent||PURPLE,bodyColor:'#aaa',padding:10};
            }

            function loadPlaysDaily(){
                $.post(AJAX,{action:'cv_chart_plays_daily',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    var total=d.values.reduce(function(a,b){return a+b;},0);
                    $('#cv-plays-total-label').text(total.toLocaleString('pt-BR')+' plays');
                    destroy('daily');
                    var ctx=document.getElementById('chart-plays-daily').getContext('2d');
                    var g=ctx.createLinearGradient(0,0,0,180);
                    g.addColorStop(0,'rgba(212,160,23,.35)');g.addColorStop(1,'rgba(212,160,23,0)');
                    charts['daily']=new Chart(ctx,{type:'line',
                        data:{labels:d.labels,datasets:[{label:'Plays',data:d.values,
                            borderColor:GOLD,backgroundColor:g,borderWidth:2,
                            pointBackgroundColor:GOLD,pointRadius:2,pointHoverRadius:5,fill:true,tension:.4}]},
                        options:{responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{display:false},tooltip:tip(GOLD)},
                            scales:{x:{grid:grid(),ticks:{...ticks(),maxTicksLimit:10}},y:{grid:grid(),ticks:ticks(),beginAtZero:true}}}
                    });
                });
            }
            function loadPlaysHourly(){
                $.post(AJAX,{action:'cv_chart_plays_hourly',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    destroy('hourly');
                    var ctx=document.getElementById('chart-plays-hourly').getContext('2d');
                    var max=Math.max.apply(null,d.values);
                    charts['hourly']=new Chart(ctx,{type:'bar',
                        data:{labels:d.labels,datasets:[{label:'Plays',data:d.values,
                            backgroundColor:d.values.map(function(v){return v===max?PURPLE:'rgba(123,104,238,.3)';}),
                            borderRadius:4}]},
                        options:{responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{display:false},tooltip:tip(PURPLE)},
                            scales:{x:{grid:{display:false},ticks:ticks()},y:{grid:grid(),ticks:ticks(),beginAtZero:true}}}
                    });
                });
            }
            function loadGenres(){
                $.post(AJAX,{action:'cv_chart_genres',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    destroy('genres');
                    var ctx=document.getElementById('chart-genres').getContext('2d');
                    charts['genres']=new Chart(ctx,{type:'doughnut',
                        data:{labels:d.labels,datasets:[{data:d.values,backgroundColor:d.bg,borderColor:'#1a1a1a',borderWidth:3}]},
                        options:{responsive:true,maintainAspectRatio:false,cutout:'58%',
                            plugins:{legend:{position:'right',labels:{color:'#888',boxWidth:10,padding:8,font:{size:10}}},tooltip:tip(GOLD)}}
                    });
                });
            }
            function loadUsersGrowth(){
                $.post(AJAX,{action:'cv_chart_users_growth',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    var tot=d.acumulado[d.acumulado.length-1]||0;
                    $('#cv-users-total-label').text(tot+' usuários');
                    destroy('users');
                    var ctx=document.getElementById('chart-users-growth').getContext('2d');
                    var g=ctx.createLinearGradient(0,0,0,150);
                    g.addColorStop(0,'rgba(29,185,84,.25)');g.addColorStop(1,'rgba(29,185,84,0)');
                    charts['users']=new Chart(ctx,{type:'line',
                        data:{labels:d.labels,datasets:[
                            {label:'Novos',data:d.values,borderColor:GREEN,backgroundColor:g,borderWidth:2,pointRadius:2,fill:true,tension:.4,yAxisID:'y'},
                            {label:'Acumulado',data:d.acumulado,borderColor:GOLD,backgroundColor:'transparent',borderWidth:1.5,borderDash:[4,4],pointRadius:0,fill:false,tension:.4,yAxisID:'y2'}
                        ]},
                        options:{responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{labels:{color:'#666',boxWidth:10,padding:8,font:{size:10}}},tooltip:tip(GREEN)},
                            scales:{x:{grid:grid(),ticks:{...ticks(),maxTicksLimit:8}},y:{grid:grid(),ticks:ticks(),beginAtZero:true,position:'left'},y2:{grid:{display:false},ticks:ticks(),position:'right'}}}
                    });
                });
            }
            function loadRankingTrend(){
                $.post(AJAX,{action:'cv_chart_ranking_trend',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    destroy('ranking');
                    var ctx=document.getElementById('chart-ranking-trend').getContext('2d');
                    charts['ranking']=new Chart(ctx,{type:'bar',
                        data:{labels:d.labels,datasets:[
                            {label:'Score',data:d.scores,backgroundColor:'rgba(212,160,23,.8)',borderRadius:4,borderSkipped:false},
                            {label:'Favs',data:d.favs,backgroundColor:'rgba(231,76,60,.55)',borderRadius:4,borderSkipped:false}
                        ]},
                        options:{responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{labels:{color:'#666',boxWidth:10,padding:8,font:{size:10}}},tooltip:tip(GOLD)},
                            scales:{x:{grid:{display:false},ticks:{...ticks(),maxRotation:30,font:{size:9}}},y:{grid:grid(),ticks:ticks(),beginAtZero:true}}}
                    });
                });
            }
            function loadRatingGenre(){
                $.post(AJAX,{action:'cv_chart_rating_genre',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    if(!d.labels.length){$('#chart-rating-genre').closest('.cv-an-card').find('.cv-an-card-body').html('<div style="padding:30px;text-align:center;color:#333;font-size:12px">Avalie músicas para ver este gráfico</div>');return;}
                    destroy('radar');
                    var ctx=document.getElementById('chart-rating-genre').getContext('2d');
                    charts['radar']=new Chart(ctx,{type:'radar',
                        data:{labels:d.labels,datasets:[{label:'Avaliação',data:d.ratings,borderColor:PURPLE,backgroundColor:'rgba(123,104,238,.12)',pointBackgroundColor:PURPLE,pointRadius:3,borderWidth:2}]},
                        options:{responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{display:false},tooltip:tip(PURPLE)},
                            scales:{r:{min:0,max:5,ticks:{stepSize:1,color:'#333',backdropColor:'transparent'},grid:{color:'rgba(255,255,255,0.04)'},pointLabels:{color:'#888',font:{size:10}},angleLines:{color:'rgba(255,255,255,0.04)'}}}}
                    });
                });
            }
            function loadFavorites(){
                $.post(AJAX,{action:'cv_chart_favorites',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    destroy('favs');
                    var ctx=document.getElementById('chart-favorites').getContext('2d');
                    var g=ctx.createLinearGradient(0,0,0,150);
                    g.addColorStop(0,'rgba(231,76,60,.3)');g.addColorStop(1,'rgba(231,76,60,0)');
                    charts['favs']=new Chart(ctx,{type:'line',
                        data:{labels:d.labels,datasets:[{label:'Favoritos',data:d.acumulado,borderColor:RED,backgroundColor:g,borderWidth:2,pointRadius:0,fill:true,tension:.4}]},
                        options:{responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{display:false},tooltip:tip(RED)},
                            scales:{x:{grid:grid(),ticks:{...ticks(),maxTicksLimit:8}},y:{grid:grid(),ticks:ticks(),beginAtZero:true}}}
                    });
                });
            }
            function loadTopCompare(){
                $.post(AJAX,{action:'cv_chart_top_compare',nonce:NONCE},function(res){
                    if(!res.success)return; var d=res.data;
                    destroy('compare');
                    var ctx=document.getElementById('chart-top-compare').getContext('2d');
                    charts['compare']=new Chart(ctx,{type:'bar',
                        data:{labels:d.labels,datasets:[
                            {label:'Plays',data:d.plays,backgroundColor:'rgba(212,160,23,.8)',borderRadius:3},
                            {label:'Favoritos',data:d.favs,backgroundColor:'rgba(231,76,60,.65)',borderRadius:3}
                        ]},
                        options:{indexAxis:'y',responsive:true,maintainAspectRatio:false,
                            plugins:{legend:{labels:{color:'#666',boxWidth:10,padding:8,font:{size:10}}},tooltip:tip(GOLD)},
                            scales:{x:{grid:grid(),ticks:ticks(),beginAtZero:true},y:{grid:{display:false},ticks:{...ticks(),font:{size:10}}}}}
                    });
                });
            }

            function loadAll(){
                $('#cv-charts-loading').show();
                $('#cv-charts-wrap').hide();
                var pending=8;
                function done(){ if(--pending<=0){$('#cv-charts-loading').hide();$('#cv-charts-wrap').fadeIn(400);} }
                function w(fn){ return function(){fn();setTimeout(done,900);}; }
                w(loadPlaysDaily)(); w(loadPlaysHourly)(); w(loadGenres)();
                w(loadUsersGrowth)(); w(loadRankingTrend)(); w(loadRatingGenre)();
                w(loadFavorites)(); w(loadTopCompare)();
            }

            loadAll();
            $('#cv-charts-refresh').on('click',function(){
                $.post(AJAX,{action:'cv_clear_charts_cache',nonce:NONCE},function(){loadAll();});
            });
            $('#cv-charts-export-img').on('click',function(){
                alert('Use Ctrl+P → Salvar como PDF para exportar o relatório completo.');
            });
        });
        </script>
        </div>
        <?php
    }
}

CV_Admin_Charts::init();
