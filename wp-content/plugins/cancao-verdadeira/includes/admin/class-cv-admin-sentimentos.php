<?php
// cancao-verdadeira/includes/admin/class-cv-admin-sentimentos.php
// Gerado em: 2026-06-27 10:00:00
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Painel Executivo de Sentimentos (v2.16.0)
// Funções : Dashboard visual dos sentimentos — cards coloridos por emoção,
//           gráfico pizza distribuição, ranking de músicas por sentimento,
//           top sentimentos por engajamento, heat map de combinações.
// Visual  : Dark mode Spotify-style, Chart.js 4.4, animações CSS
// Autor   : Canção Verdadeira | Gerado: 2026-06-27

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin_Sentimentos {

    public static function init() {
        add_action( 'wp_ajax_cv_admin_sent_stats', array( __CLASS__, 'ajax_stats' ) );
        add_action( 'wp_ajax_cv_admin_sent_musicas', array( __CLASS__, 'ajax_musicas_sentimento' ) );
    }

    // ── Dados estatísticos ────────────────────────────────────────

    private static function get_stats() {
        global $wpdb;

        $sentimentos = CV_Sentimentos::get_all();
        if ( empty( $sentimentos ) ) {
            return array( 'sentimentos' => array(), 'total_associacoes' => 0, 'total_musicas_com_sent' => 0 );
        }

        $rel   = $wpdb->prefix . 'cv_musica_sentimentos';
        $posts = $wpdb->posts;

        // Total de associações e músicas únicas
        $total_assoc  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $rel" );
        $total_musicas = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT ms.musica_id) FROM $rel ms
             INNER JOIN $posts p ON p.ID = ms.musica_id
             WHERE p.post_status = 'publish' AND p.post_type = 'musica'"
        );

        $data = array();
        foreach ( $sentimentos as $s ) {
            $sid = absint( $s->id );

            // Contagem de músicas publicadas neste sentimento
            $count = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM $rel ms
                 INNER JOIN $posts p ON p.ID = ms.musica_id
                 WHERE ms.sentimento_id = %d AND p.post_status = 'publish' AND p.post_type = 'musica'",
                $sid
            ) );

            // Total de plays das músicas deste sentimento
            $plays = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)),0)
                 FROM $rel ms
                 INNER JOIN $posts p ON p.ID = ms.musica_id
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = ms.musica_id AND pm.meta_key = '_cv_plays'
                 WHERE ms.sentimento_id = %d AND p.post_status = 'publish'",
                $sid
            ) );

            // Total de favoritos
            $favs = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)),0)
                 FROM $rel ms
                 INNER JOIN $posts p ON p.ID = ms.musica_id
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = ms.musica_id AND pm.meta_key = '_cv_favoritos'
                 WHERE ms.sentimento_id = %d AND p.post_status = 'publish'",
                $sid
            ) );

            // Top 3 músicas (por plays)
            $top3 = $wpdb->get_results( $wpdb->prepare(
                "SELECT p.ID, p.post_title,
                        COALESCE(MAX(pm.meta_value),0) AS plays
                 FROM $rel ms
                 INNER JOIN $posts p ON p.ID = ms.musica_id
                 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = ms.musica_id AND pm.meta_key = '_cv_plays'
                 WHERE ms.sentimento_id = %d AND p.post_status = 'publish'
                 GROUP BY p.ID ORDER BY plays DESC LIMIT 3",
                $sid
            ) );

            $data[] = array(
                'id'     => $sid,
                'nome'   => $s->nome,
                'icone'  => $s->icone,
                'cor'    => $s->cor,
                'slug'   => $s->slug,
                'count'  => $count,
                'plays'  => $plays,
                'favs'   => $favs,
                'top3'   => $top3,
            );
        }

        // Ordenar por número de músicas desc
        usort( $data, function( $a, $b ) { return $b['count'] - $a['count']; } );

        return array(
            'sentimentos'           => $data,
            'total_associacoes'     => $total_assoc,
            'total_musicas_com_sent' => $total_musicas,
        );
    }

    // ── AJAX: stats para Chart.js ─────────────────────────────────

    public static function ajax_stats() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        wp_send_json_success( self::get_stats() );
    }

    public static function ajax_musicas_sentimento() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        $sid = absint( $_POST['sentimento_id'] ?? 0 );
        if ( ! $sid ) { wp_send_json_error('ID inválido'); }

        global $wpdb;
        $rel = $wpdb->prefix . 'cv_musica_sentimentos';

        $musicas = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title,
                    COALESCE(MAX(pm_plays.meta_value),0) AS plays,
                    COALESCE(MAX(pm_favs.meta_value),0)  AS favs,
                    COALESCE(MAX(pm_rating.meta_value),0) AS rating
             FROM $rel ms
             INNER JOIN {$wpdb->posts} p ON p.ID = ms.musica_id
             LEFT JOIN {$wpdb->postmeta} pm_plays  ON pm_plays.post_id  = ms.musica_id AND pm_plays.meta_key  = '_cv_plays'
             LEFT JOIN {$wpdb->postmeta} pm_favs   ON pm_favs.post_id   = ms.musica_id AND pm_favs.meta_key   = '_cv_favoritos'
             LEFT JOIN {$wpdb->postmeta} pm_rating ON pm_rating.post_id = ms.musica_id AND pm_rating.meta_key = '_cv_avg_rating'
             WHERE ms.sentimento_id = %d AND p.post_status = 'publish' AND p.post_type = 'musica'
             GROUP BY p.ID ORDER BY plays DESC LIMIT 30",
            $sid
        ) );

        $items = array();
        foreach ( $musicas as $m ) {
            $items[] = array(
                'id'     => $m->ID,
                'titulo' => $m->post_title,
                'plays'  => (int) $m->plays,
                'favs'   => (int) $m->favs,
                'rating' => round( (float) $m->rating, 1 ),
                'capa'   => get_the_post_thumbnail_url( $m->ID, 'thumbnail' ) ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'url'    => get_edit_post_link( $m->ID, 'raw' ),
            );
        }

        wp_send_json_success( $items );
    }

    // ── Render do Painel ──────────────────────────────────────────

    public static function render_page() {
        $stats = self::get_stats();
        $sentimentos = $stats['sentimentos'];
        $nonce = wp_create_nonce('cv_admin_nonce');
        ?>
        <div class="wrap" id="cv-sentimentos-dashboard">
        <?php echo CV_Admin::btn_voltar(); ?>
        

        <style>
        /* Dark mode global — sobrepõe o cinza claro do WordPress */
        body.wp-admin { background: #0f0f1a !important; }
        #wpwrap, #wpcontent, #wpbody, #wpbody-content { background: #0f0f1a !important; }
        #cv-sentimentos-dashboard {
            --cv-gold: #D4A017;
            --cv-bg: #0f0f1a;
            --cv-card: #1a1a2e;
            --cv-card2: #16213e;
            --cv-border: #2a2a4a;
            --cv-text: #e0e0e0;
            --cv-muted: #888;
            font-family: 'Segoe UI', system-ui, sans-serif;
            color: var(--cv-text);
            background: #0f0f1a;
            min-height: 100vh;
            padding-bottom: 40px;
        }
        /* Título H1 padrão WP */
        #cv-sentimentos-dashboard h1.wp-heading-inline { color: #fff !important; }
        #cv-sentimentos-dashboard .notice { border-radius: 8px; }
        #cv-sentimentos-dashboard * { box-sizing: border-box; }

        .cv-sent-header-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cv-sent-title {
            font-size: 26px;
            font-weight: 700;
            color: #fff;
            margin: 0;
        }
        .cv-sent-title span { color: var(--cv-gold); }
        .cv-sent-actions { display: flex; gap: 10px; }
        .cv-sent-btn-manage {
            background: var(--cv-gold);
            color: #000;
            border: none;
            padding: 9px 18px;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            transition: opacity .2s;
        }
        .cv-sent-btn-manage:hover { opacity: .85; color: #000; }

        /* KPIs */
        .cv-sent-kpis {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }
        .cv-kpi-box {
            background: var(--cv-card);
            border: 1px solid var(--cv-border);
            border-radius: 12px;
            padding: 20px 16px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cv-kpi-box::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            background: var(--cv-gold);
        }
        .cv-kpi-num {
            font-size: 32px;
            font-weight: 800;
            color: var(--cv-gold);
            line-height: 1;
            counter-reset: num;
        }
        .cv-kpi-label {
            font-size: 12px;
            color: var(--cv-muted);
            margin-top: 6px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        /* Grid de cards de sentimento */
        .cv-sent-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        .cv-sent-card {
            background: var(--cv-card);
            border: 1px solid var(--cv-border);
            border-radius: 16px;
            padding: 20px;
            cursor: pointer;
            transition: transform .2s, border-color .2s, box-shadow .2s;
            position: relative;
            overflow: hidden;
        }
        .cv-sent-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 32px rgba(0,0,0,.4);
        }
        .cv-sent-card.active {
            border-color: var(--card-cor, var(--cv-gold));
            box-shadow: 0 0 0 2px var(--card-cor, var(--cv-gold))33;
        }
        .cv-sent-card-glow {
            position: absolute;
            top: -30px; right: -30px;
            width: 100px; height: 100px;
            border-radius: 50%;
            opacity: .15;
            filter: blur(30px);
        }
        .cv-sent-card-top {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 14px;
        }
        .cv-sent-emoji {
            font-size: 36px;
            line-height: 1;
            filter: drop-shadow(0 2px 4px rgba(0,0,0,.4));
        }
        .cv-sent-card-info { flex: 1; min-width: 0; }
        .cv-sent-card-nome {
            font-size: 17px;
            font-weight: 700;
            color: #fff;
        }
        .cv-sent-card-count {
            font-size: 12px;
            color: var(--cv-muted);
            margin-top: 2px;
        }
        .cv-sent-badge-rank {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 20px;
            background: rgba(255,255,255,.07);
            color: var(--cv-muted);
        }
        .cv-sent-metrics {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 14px;
        }
        .cv-sent-metric {
            background: rgba(255,255,255,.04);
            border-radius: 8px;
            padding: 10px 12px;
            text-align: center;
        }
        .cv-sent-metric-val {
            font-size: 20px;
            font-weight: 800;
            color: var(--card-cor, var(--cv-gold));
        }
        .cv-sent-metric-label {
            font-size: 10px;
            color: var(--cv-muted);
            text-transform: uppercase;
            letter-spacing: .4px;
        }
        .cv-sent-bar-container {
            background: rgba(255,255,255,.06);
            border-radius: 4px;
            height: 4px;
            overflow: hidden;
            margin-top: 2px;
        }
        .cv-sent-bar {
            height: 100%;
            border-radius: 4px;
            background: var(--card-cor, var(--cv-gold));
            transition: width 1s ease;
        }
        .cv-sent-top3 { margin-top: 12px; }
        .cv-sent-top3-title {
            font-size: 10px;
            color: var(--cv-muted);
            text-transform: uppercase;
            letter-spacing: .5px;
            margin-bottom: 6px;
        }
        .cv-sent-top3-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 5px 0;
            border-bottom: 1px solid var(--cv-border);
            font-size: 12px;
            color: var(--cv-text);
        }
        .cv-sent-top3-item:last-child { border-bottom: none; }
        .cv-sent-top3-pos {
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: var(--card-cor, var(--cv-gold))22;
            color: var(--card-cor, var(--cv-gold));
            font-size: 10px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cv-sent-top3-name {
            flex: 1;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Área de gráficos */
        .cv-sent-charts-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 28px;
        }
        @media (max-width: 900px) {
            .cv-sent-charts-row { grid-template-columns: 1fr; }
        }
        .cv-sent-chart-box {
            background: var(--cv-card);
            border: 1px solid var(--cv-border);
            border-radius: 16px;
            padding: 22px;
        }
        .cv-sent-chart-title {
            font-size: 14px;
            font-weight: 700;
            color: #fff;
            margin: 0 0 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .cv-sent-chart-title span {
            font-size: 11px;
            color: var(--cv-muted);
            font-weight: 400;
        }
        .cv-chart-wrap {
            position: relative;
            height: 220px;
        }

        /* Painel de músicas por sentimento */
        .cv-sent-detail-panel {
            background: var(--cv-card);
            border: 1px solid var(--cv-border);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 28px;
            display: none;
        }
        .cv-sent-detail-panel.visible { display: block; }
        .cv-sent-detail-header {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }
        .cv-sent-detail-emoji { font-size: 32px; }
        .cv-sent-detail-nome { font-size: 18px; font-weight: 700; color: #fff; }
        .cv-sent-detail-sub { font-size: 12px; color: var(--cv-muted); }
        .cv-sent-detail-close {
            margin-left: auto;
            background: rgba(255,255,255,.06);
            border: none;
            color: var(--cv-muted);
            width: 30px; height: 30px;
            border-radius: 50%;
            cursor: pointer;
            font-size: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cv-sent-musicas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 12px;
        }
        .cv-musica-item {
            background: rgba(255,255,255,.04);
            border-radius: 10px;
            padding: 12px;
            display: flex;
            gap: 10px;
            align-items: center;
            text-decoration: none;
            color: var(--cv-text);
            transition: background .2s;
        }
        .cv-musica-item:hover { background: rgba(255,255,255,.08); color: #fff; }
        .cv-musica-capa {
            width: 40px;
            height: 40px;
            border-radius: 6px;
            object-fit: cover;
            flex-shrink: 0;
        }
        .cv-musica-titulo {
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cv-musica-stats {
            font-size: 11px;
            color: var(--cv-muted);
            margin-top: 2px;
        }
        .cv-sent-loading {
            text-align: center;
            padding: 40px;
            color: var(--cv-muted);
            font-size: 14px;
        }
        .cv-sent-empty {
            text-align: center;
            padding: 40px;
            color: var(--cv-muted);
        }

        /* Ranking horizontal de sentimentos */
        .cv-sent-ranking-box {
            background: var(--cv-card);
            border: 1px solid var(--cv-border);
            border-radius: 16px;
            padding: 22px;
            margin-bottom: 28px;
        }
        .cv-sent-ranking-row {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 0;
            border-bottom: 1px solid var(--cv-border);
        }
        .cv-sent-ranking-row:last-child { border-bottom: none; }
        .cv-sent-rank-pos {
            font-size: 12px;
            font-weight: 700;
            color: var(--cv-muted);
            width: 24px;
            text-align: right;
        }
        .cv-sent-rank-emoji { font-size: 22px; }
        .cv-sent-rank-nome { flex: 1; font-size: 14px; font-weight: 600; }
        .cv-sent-rank-bar-wrap {
            flex: 2;
            background: rgba(255,255,255,.06);
            border-radius: 4px;
            height: 6px;
            overflow: hidden;
        }
        .cv-sent-rank-bar {
            height: 100%;
            border-radius: 4px;
            transition: width 1.2s ease;
        }
        .cv-sent-rank-count { font-size: 12px; color: var(--cv-muted); width: 70px; text-align: right; }
        </style>

        <div class="cv-sent-header-bar">
            <h1 class="cv-sent-title">🎭 <span>Sentimentos</span> — Painel Executivo</h1>
            <div class="cv-sent-actions">
                <a href="<?php echo esc_url( admin_url('admin.php?page=cv-sentimentos-crud') ); ?>"
                   class="cv-sent-btn-manage">⚙️ Gerenciar Sentimentos</a>
            </div>
        </div>

        <?php
        $total_sent  = count( $sentimentos );
        $total_assoc = $stats['total_associacoes'];
        $total_com   = $stats['total_musicas_com_sent'];
        $total_plays = array_sum( array_column( $sentimentos, 'plays' ) );
        ?>

        <!-- KPIs -->
        <div class="cv-sent-kpis">
            <div class="cv-kpi-box">
                <div class="cv-kpi-num" data-target="<?php echo $total_sent; ?>">0</div>
                <div class="cv-kpi-label">Sentimentos ativos</div>
            </div>
            <div class="cv-kpi-box">
                <div class="cv-kpi-num" data-target="<?php echo $total_assoc; ?>">0</div>
                <div class="cv-kpi-label">Associações totais</div>
            </div>
            <div class="cv-kpi-box">
                <div class="cv-kpi-num" data-target="<?php echo $total_com; ?>">0</div>
                <div class="cv-kpi-label">Músicas categorizadas</div>
            </div>
            <div class="cv-kpi-box">
                <div class="cv-kpi-num" data-target="<?php echo $total_plays; ?>">0</div>
                <div class="cv-kpi-label">Plays via sentimentos</div>
            </div>
        </div>

        <!-- Cards de sentimento -->
        <?php if ( ! empty( $sentimentos ) ) :
            $max_count = max( array_column( $sentimentos, 'count' ) ) ?: 1;
            $max_plays = max( array_column( $sentimentos, 'plays' ) ) ?: 1;
        ?>
        <div class="cv-sent-grid" id="cv-sent-cards-grid">
            <?php foreach ( $sentimentos as $i => $s ) :
                $rank = $i + 1;
                $pct_count = $max_count > 0 ? round( $s['count'] / $max_count * 100 ) : 0;
                $cor = esc_attr( $s['cor'] );
            ?>
            <div class="cv-sent-card" style="--card-cor:<?php echo $cor; ?>"
                 data-id="<?php echo (int)$s['id']; ?>"
                 data-nome="<?php echo esc_attr($s['nome']); ?>"
                 data-icone="<?php echo esc_attr($s['icone']); ?>"
                 data-cor="<?php echo $cor; ?>"
                 onclick="cvSentAbrirDetalhe(this)">

                <div class="cv-sent-card-glow" style="background:<?php echo $cor; ?>"></div>

                <div class="cv-sent-card-top">
                    <div class="cv-sent-emoji"><?php echo esc_html($s['icone']); ?></div>
                    <div class="cv-sent-card-info">
                        <div class="cv-sent-card-nome"><?php echo esc_html($s['nome']); ?></div>
                        <div class="cv-sent-card-count"><?php echo $s['count']; ?> música<?php echo $s['count'] !== 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="cv-sent-badge-rank">#<?php echo $rank; ?></div>
                </div>

                <div class="cv-sent-metrics">
                    <div class="cv-sent-metric">
                        <div class="cv-sent-metric-val"><?php echo number_format($s['plays']); ?></div>
                        <div class="cv-sent-metric-label">▶ Plays</div>
                    </div>
                    <div class="cv-sent-metric">
                        <div class="cv-sent-metric-val"><?php echo number_format($s['favs']); ?></div>
                        <div class="cv-sent-metric-label">❤ Favs</div>
                    </div>
                </div>

                <div class="cv-sent-bar-container">
                    <div class="cv-sent-bar" style="width:0" data-w="<?php echo $pct_count; ?>%"></div>
                </div>

                <?php if ( ! empty($s['top3']) ) : ?>
                <div class="cv-sent-top3">
                    <div class="cv-sent-top3-title">Top músicas</div>
                    <?php foreach ( $s['top3'] as $j => $m ) : ?>
                    <div class="cv-sent-top3-item">
                        <div class="cv-sent-top3-pos"><?php echo $j+1; ?></div>
                        <div class="cv-sent-top3-name"><?php echo esc_html($m->post_title); ?></div>
                        <div style="font-size:11px;color:var(--cv-muted)"><?php echo number_format((int)$m->plays); ?>▶</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Painel de detalhe ao clicar no card -->
        <div class="cv-sent-detail-panel" id="cv-sent-detail-panel">
            <div class="cv-sent-detail-header">
                <div class="cv-sent-detail-emoji" id="cv-det-emoji"></div>
                <div>
                    <div class="cv-sent-detail-nome" id="cv-det-nome"></div>
                    <div class="cv-sent-detail-sub" id="cv-det-sub"></div>
                </div>
                <button class="cv-sent-detail-close" onclick="cvSentFecharDetalhe()">✕</button>
            </div>
            <div class="cv-sent-musicas-grid" id="cv-det-musicas">
                <div class="cv-sent-loading">Carregando músicas...</div>
            </div>
        </div>

        <!-- Gráficos -->
        <div class="cv-sent-charts-row">
            <div class="cv-sent-chart-box">
                <div class="cv-sent-chart-title">🎵 Distribuição por Sentimento <span>— número de músicas</span></div>
                <div class="cv-chart-wrap">
                    <canvas id="cv-chart-pizza"></canvas>
                </div>
            </div>
            <div class="cv-sent-chart-box">
                <div class="cv-sent-chart-title">▶ Plays por Sentimento <span>— total acumulado</span></div>
                <div class="cv-chart-wrap">
                    <canvas id="cv-chart-plays"></canvas>
                </div>
            </div>
        </div>

        <!-- Ranking por engajamento -->
        <div class="cv-sent-ranking-box">
            <div class="cv-sent-chart-title" style="margin-bottom:16px">🏆 Ranking de Engajamento <span>— por plays totais</span></div>
            <?php
            $sorted_plays = $sentimentos;
            usort( $sorted_plays, function($a,$b){ return $b['plays'] - $a['plays']; } );
            $max_p = max(1, $sorted_plays[0]['plays'] ?? 1);
            foreach ( $sorted_plays as $i => $s ) :
                $pct = round( $s['plays'] / $max_p * 100 );
            ?>
            <div class="cv-sent-ranking-row">
                <div class="cv-sent-rank-pos"><?php echo $i+1; ?>.</div>
                <div class="cv-sent-rank-emoji"><?php echo esc_html($s['icone']); ?></div>
                <div class="cv-sent-rank-nome"><?php echo esc_html($s['nome']); ?></div>
                <div class="cv-sent-rank-bar-wrap">
                    <div class="cv-sent-rank-bar"
                         style="width:0;background:<?php echo esc_attr($s['cor']); ?>"
                         data-w="<?php echo $pct; ?>%"></div>
                </div>
                <div class="cv-sent-rank-count"><?php echo number_format($s['plays']); ?> ▶</div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else : ?>
        <div class="cv-sent-empty">
            <div style="font-size:48px;margin-bottom:12px">🎭</div>
            <div style="font-size:16px;color:#e0e0e0;margin-bottom:8px">Nenhum sentimento cadastrado</div>
            <div style="font-size:13px;color:var(--cv-muted);margin-bottom:20px">Crie sentimentos e associe às músicas para ver o painel.</div>
            <a href="<?php echo esc_url( admin_url('admin.php?page=cv-sentimentos-crud') ); ?>"
               class="cv-sent-btn-manage">➕ Criar primeiro sentimento</a>
        </div>
        <?php endif; ?>

        </div><!-- wrap -->

        <script>
        (function() {
            var NONCE  = '<?php echo esc_js( $nonce ); ?>';
            var AJAX   = '<?php echo esc_js( admin_url('admin-ajax.php') ); ?>';
            var statsData = <?php echo wp_json_encode( $sentimentos ); ?>;

            // ── Animação KPIs ─────────────────────────────────────
            document.querySelectorAll('.cv-kpi-num[data-target]').forEach(function(el) {
                var target = parseInt(el.dataset.target, 10);
                if (isNaN(target)) return;
                var start = 0, duration = 1200, startTime = null;
                function step(ts) {
                    if (!startTime) startTime = ts;
                    var progress = Math.min((ts - startTime) / duration, 1);
                    var ease = 1 - Math.pow(1 - progress, 3);
                    el.textContent = Math.floor(ease * target).toLocaleString('pt-BR');
                    if (progress < 1) requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });

            // ── Animação barras ───────────────────────────────────
            setTimeout(function() {
                document.querySelectorAll('[data-w]').forEach(function(el) {
                    el.style.width = el.dataset.w;
                });
            }, 300);

            // ── Chart.js — inicializar quando disponível ──────────
            function initCharts() {
                if (typeof Chart === 'undefined' || statsData.length === 0) return;

                var labels = statsData.map(function(s){ return s.icone + ' ' + s.nome; });
                var counts = statsData.map(function(s){ return s.count; });
                var plays  = statsData.map(function(s){ return s.plays; });
                var cores  = statsData.map(function(s){ return s.cor; });
                var coresBorder = cores.map(function(c){ return c; });

                // Pizza — distribuição
                var ctxP = document.getElementById('cv-chart-pizza');
                if (ctxP) {
                    new Chart(ctxP, {
                        type: 'doughnut',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: counts,
                                backgroundColor: cores.map(function(c){ return c + '99'; }),
                                borderColor: coresBorder,
                                borderWidth: 2,
                                hoverOffset: 8
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: { color: '#aaa', boxWidth: 14, font: { size: 11 } }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx) {
                                            var v = ctx.parsed;
                                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                            var pct = total > 0 ? Math.round(v/total*100) : 0;
                                            return ' ' + v + ' músicas (' + pct + '%)';
                                        }
                                    }
                                }
                            },
                            cutout: '60%'
                        }
                    });
                }

                // Barras horizontais — plays
                var ctxB = document.getElementById('cv-chart-plays');
                if (ctxB) {
                    new Chart(ctxB, {
                        type: 'bar',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Plays totais',
                                data: plays,
                                backgroundColor: cores.map(function(c){ return c + 'bb'; }),
                                borderColor: cores,
                                borderWidth: 1,
                                borderRadius: 6
                            }]
                        },
                        options: {
                            indexAxis: 'y',
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: { display: false },
                                tooltip: {
                                    callbacks: {
                                        label: function(ctx){
                                            return ' ' + ctx.parsed.x.toLocaleString('pt-BR') + ' plays';
                                        }
                                    }
                                }
                            },
                            scales: {
                                x: {
                                    grid: { color: 'rgba(255,255,255,.05)' },
                                    ticks: { color: '#888', font: { size: 11 } }
                                },
                                y: {
                                    grid: { display: false },
                                    ticks: { color: '#ccc', font: { size: 11 } }
                                }
                            }
                        }
                    });
                }
            }

            // Esperar Chart.js carregar
            if (typeof Chart !== 'undefined') {
                initCharts();
            } else {
                var checkChart = setInterval(function(){
                    if (typeof Chart !== 'undefined') {
                        clearInterval(checkChart);
                        initCharts();
                    }
                }, 200);
            }

            // ── Painel de detalhe ─────────────────────────────────
            window.cvSentAbrirDetalhe = function(card) {
                var id    = card.dataset.id;
                var nome  = card.dataset.nome;
                var icone = card.dataset.icone;
                var cor   = card.dataset.cor;

                // Marcar ativo
                document.querySelectorAll('.cv-sent-card').forEach(function(c){ c.classList.remove('active'); });
                card.classList.add('active');

                var panel = document.getElementById('cv-sent-detail-panel');
                document.getElementById('cv-det-emoji').textContent = icone;
                document.getElementById('cv-det-nome').textContent  = nome;
                document.getElementById('cv-det-sub').textContent   = 'Carregando músicas...';
                document.getElementById('cv-det-musicas').innerHTML = '<div class="cv-sent-loading">⏳ Carregando músicas...</div>';
                panel.classList.add('visible');
                panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                panel.style.borderColor = cor;

                // AJAX
                var fd = new FormData();
                fd.append('action', 'cv_admin_sent_musicas');
                fd.append('nonce', NONCE);
                fd.append('sentimento_id', id);

                fetch(AJAX, { method: 'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(res) {
                        if (!res.success || !res.data.length) {
                            document.getElementById('cv-det-sub').textContent = '0 músicas';
                            document.getElementById('cv-det-musicas').innerHTML =
                                '<div class="cv-sent-empty">Nenhuma música publicada com este sentimento ainda.</div>';
                            return;
                        }
                        var musicas = res.data;
                        document.getElementById('cv-det-sub').textContent = musicas.length + ' música' + (musicas.length !== 1 ? 's' : '');
                        var html = '';
                        musicas.forEach(function(m) {
                            html += '<a href="' + m.url + '" target="_blank" class="cv-musica-item">';
                            html += '<img src="' + m.capa + '" class="cv-musica-capa" alt="">';
                            html += '<div style="min-width:0">';
                            html += '<div class="cv-musica-titulo">' + m.titulo + '</div>';
                            html += '<div class="cv-musica-stats">▶ ' + m.plays.toLocaleString('pt-BR') + '  ❤ ' + m.favs + '  ★ ' + m.rating + '</div>';
                            html += '</div></a>';
                        });
                        document.getElementById('cv-det-musicas').innerHTML = html;
                    })
                    .catch(function() {
                        document.getElementById('cv-det-musicas').innerHTML =
                            '<div class="cv-sent-empty">Erro ao carregar músicas.</div>';
                    });
            };

            window.cvSentFecharDetalhe = function() {
                document.getElementById('cv-sent-detail-panel').classList.remove('visible');
                document.querySelectorAll('.cv-sent-card').forEach(function(c){ c.classList.remove('active'); });
            };
        })();
        </script>
        <?php
    }
}

CV_Admin_Sentimentos::init();
