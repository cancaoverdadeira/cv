<?php
// cancao-verdadeira/includes/admin/class-cv-admin-sentimentos.php
// Gerado em: 2026-06-27 10:00:00
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Painel Executivo de Sentimentos (v2.16.0)
// Funções : Dashboard visual dos sentimentos — cards coloridos por emoção,
//           gráfico pizza distribuição, ranking de músicas por sentimento,
//           top sentimentos por engajamento, heat map de combinações.
// Visual  : Dark mode Spotify-style, Chart.js 4.4, animações CSS
// v2.34.0 : CSS e JS em assets/css|js/admin-sentimentos.*
// Autor   : Canção Verdadeira | Gerado: 2026-06-27

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin_Sentimentos {

    public static function init() {
        add_action( 'wp_ajax_cv_admin_sent_stats', array( __CLASS__, 'ajax_stats' ) );
        add_action( 'wp_ajax_cv_admin_sent_musicas', array( __CLASS__, 'ajax_musicas_sentimento' ) );
        // Prioridade 20: o CSS da tela sai depois do admin.css e o Chart.js já está registrado (CV_Admin, prioridade 10).
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
    }

    // ── CSS e JS da tela (v2.34.0: saíram do <style>/<script> em linha) ──

    public static function enqueue_assets() {
        if ( 'cv-sentimentos' !== sanitize_key( $_GET['page'] ?? '' ) ) {
            return;
        }
        wp_enqueue_style(
            'cv-admin-sentimentos',
            CV_PLUGIN_URL . 'assets/css/admin-sentimentos.css',
            array(),
            CV_VERSION
        );
        // Só registra aqui: render_page() enfileira junto com os dados (window.cvSent).
        wp_register_script(
            'cv-admin-sentimentos',
            CV_PLUGIN_URL . 'assets/js/admin-sentimentos.js',
            array( 'chartjs' ),
            CV_VERSION,
            true
        );
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
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = ms.musica_id AND pm.meta_key = '_cv_plays_total'
                 WHERE ms.sentimento_id = %d AND p.post_status = 'publish'",
                $sid
            ) );

            // Total de favoritos
            $favs = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(CAST(pm.meta_value AS UNSIGNED)),0)
                 FROM $rel ms
                 INNER JOIN $posts p ON p.ID = ms.musica_id
                 INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = ms.musica_id AND pm.meta_key = '_cv_favorites'
                 WHERE ms.sentimento_id = %d AND p.post_status = 'publish'",
                $sid
            ) );

            // Top 3 músicas (por plays)
            $top3 = $wpdb->get_results( $wpdb->prepare(
                "SELECT p.ID, p.post_title,
                        COALESCE(MAX(pm.meta_value),0) AS plays
                 FROM $rel ms
                 INNER JOIN $posts p ON p.ID = ms.musica_id
                 LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = ms.musica_id AND pm.meta_key = '_cv_plays_total'
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
             LEFT JOIN {$wpdb->postmeta} pm_plays  ON pm_plays.post_id  = ms.musica_id AND pm_plays.meta_key  = '_cv_plays_total'
             LEFT JOIN {$wpdb->postmeta} pm_favs   ON pm_favs.post_id   = ms.musica_id AND pm_favs.meta_key   = '_cv_favorites'
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
            <div style="font-size:16px;color:#3B2418;margin-bottom:8px">Nenhum sentimento cadastrado</div>
            <div style="font-size:13px;color:var(--cv-muted);margin-bottom:20px">Crie sentimentos e associe às músicas para ver o painel.</div>
            <a href="<?php echo esc_url( admin_url('admin.php?page=cv-sentimentos-crud') ); ?>"
               class="cv-sent-btn-manage">➕ Criar primeiro sentimento</a>
        </div>
        <?php endif; ?>

        </div><!-- wrap -->

        <?php
        // JS da tela: assets/js/admin-sentimentos.js (vai no rodapé).
        wp_enqueue_script( 'cv-admin-sentimentos' );
        wp_add_inline_script( 'cv-admin-sentimentos', 'window.cvSent = ' . wp_json_encode( array(
            'nonce'   => $nonce,
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'stats'   => $sentimentos,
        ) ) . ';', 'before' );
    }
}

CV_Admin_Sentimentos::init();
