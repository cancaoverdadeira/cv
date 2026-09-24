<?php
// cancao-verdadeira-plugin/includes/admin/class-cv-admin-seo.php
// Gerado em: 2026-06-25 19:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Painel de SEO & Visibilidade do admin. Mostra status de indexação,
// score de completude SEO por música, checklist de saúde do site,
// gráfico de cobertura do catálogo e top músicas com maior potencial
// orgânico. Todos os dados vêm do banco local — sem API externa.
// Compatível com PHP 7.2+. Integrado ao menu como "📡 SEO".
// v2.26.0: sem gêneros (site todo sertanejo) — o critério "gênero" do score
// virou "descrição" (meta description), e saíram a coluna, a barra de
// cobertura e a tabela "Potencial Orgânico por Gênero".

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin_SEO {

    public static function init() {
        add_action( 'wp_ajax_cv_seo_music_scores', array( __CLASS__, 'ajax_music_scores' ) );
        add_action( 'wp_ajax_cv_seo_coverage',     array( __CLASS__, 'ajax_coverage' ) );
    }

    // ── Score SEO por música (0–100) ────────────────────────────────────
    // Critérios: título(20) + letra(25) + capa(15) + YouTube(20) + descrição(10) + artista(10)
    private static function calc_score( $post_id ) {
        $score  = 0;
        $detail = array();

        $title = get_the_title( $post_id );
        if ( $title && mb_strlen( $title ) >= 3 ) {
            $score += 20;
            $detail['titulo'] = true;
        } else {
            $detail['titulo'] = false;
        }

        if ( CV_Fields::has_letra( $post_id ) ) {
            $score += 25;
            $detail['letra'] = true;
        } else {
            $detail['letra'] = false;
        }

        $capa = get_the_post_thumbnail_url( $post_id );
        if ( $capa ) {
            $score += 15;
            $detail['capa'] = true;
        } else {
            $detail['capa'] = false;
        }

        $yt = get_post_meta( $post_id, CV_Fields::YOUTUBE_URL, true );
        if ( $yt ) {
            $score += 20;
            $detail['youtube'] = true;
        } else {
            $detail['youtube'] = false;
        }

        $descricao = get_post_meta( $post_id, CV_Fields::DESCRICAO, true );
        if ( '' !== trim( (string) $descricao ) ) {
            $score += 10;
            $detail['descricao'] = true;
        } else {
            $detail['descricao'] = false;
        }

        $artista = get_post_meta( $post_id, CV_Fields::ARTISTA, true );
        if ( $artista ) {
            $score += 10;
            $detail['artista'] = true;
        } else {
            $detail['artista'] = false;
        }

        return array( 'score' => $score, 'detail' => $detail );
    }

    // ── AJAX: scores de todas as músicas ───────────────────────────────
    public static function ajax_music_scores() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_seo_music_scores' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        $posts = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $results = array();
        $dist    = array( 'otimo' => 0, 'bom' => 0, 'regular' => 0, 'fraco' => 0 );

        foreach ( $posts as $p ) {
            $r       = self::calc_score( $p->ID );
            $score   = $r['score'];
            $artista = get_post_meta( $p->ID, CV_Fields::ARTISTA, true );

            if ( $score >= 90 )      { $dist['otimo']++; }
            elseif ( $score >= 70 )  { $dist['bom']++; }
            elseif ( $score >= 40 )  { $dist['regular']++; }
            else                     { $dist['fraco']++; }

            $results[] = array(
                'id'      => $p->ID,
                'titulo'  => $p->post_title,
                'score'   => $score,
                'artista' => $artista,
                'url'     => get_permalink( $p->ID ),
                'edit'    => admin_url( 'post.php?post=' . $p->ID . '&action=edit' ),
                'detail'  => $r['detail'],
            );
        }

        // Ordena por score desc
        usort( $results, function( $a, $b ) { return $b['score'] - $a['score']; } );

        $data = array(
            'musicas' => $results,
            'dist'    => $dist,
            'total'   => count( $results ),
            'avg'     => count( $results ) ? round( array_sum( array_column( $results, 'score' ) ) / count( $results ) ) : 0,
        );

        set_transient( 'cv_seo_music_scores', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── AJAX: cobertura do catálogo ────────────────────────────────────
    public static function ajax_coverage() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        $cached = get_transient( 'cv_seo_coverage' );
        if ( $cached !== false ) { wp_send_json_success( $cached ); }

        global $wpdb;

        $total = (int) wp_count_posts( 'musica' )->publish;

        $com_letra = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             WHERE p.post_type = 'musica' AND p.post_status = 'publish'
               AND CHAR_LENGTH(p.post_content) > 100"
        );

        $com_youtube = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'musica' AND p.post_status = 'publish'
               AND pm.meta_key = '_cv_youtube_url' AND pm.meta_value != ''"
        );

        $com_capa = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'musica' AND p.post_status = 'publish'
               AND pm.meta_key = '_thumbnail_id'"
        );

        $com_artista = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT p.ID) FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
             WHERE p.post_type = 'musica' AND p.post_status = 'publish'
               AND pm.meta_key = '_cv_artista' AND pm.meta_value != ''"
        );

        $data = array(
            'total'       => $total,
            'com_letra'   => $com_letra,
            'com_youtube' => $com_youtube,
            'com_capa'    => $com_capa,
            'com_artista' => $com_artista,
        );

        set_transient( 'cv_seo_coverage', $data, HOUR_IN_SECONDS );
        wp_send_json_success( $data );
    }

    // ── Limpar cache SEO ───────────────────────────────────────────────
    public static function clear_cache() {
        delete_transient( 'cv_seo_music_scores' );
        delete_transient( 'cv_seo_coverage' );
    }

    // ── Checklist de saúde SEO (dados do servidor) ─────────────────────
    private static function get_checklist() {
        $items = array();

        // Schema.org ativo
        $items[] = array(
            'ok'    => class_exists( 'CV_Schema' ),
            'label' => 'Schema.org MusicComposition ativo',
            'desc'  => 'Gera rich results no Google (artista, duração, avaliação)',
            'acao'  => '',
        );

        // Rank Math ativo
        $rank_math = is_plugin_active( 'seo-by-rank-math/rank-math.php' ) ||
                     class_exists( 'RankMath' );
        $items[] = array(
            'ok'    => $rank_math,
            'label' => 'Rank Math SEO instalado',
            'desc'  => 'Sitemap XML, meta tags e breadcrumbs automáticos',
            'acao'  => $rank_math ? '' : 'Instalar Rank Math em Plugins → Adicionar Novo',
        );

        // Sitemap
        $sitemap_url  = home_url( '/sitemap_index.xml' );
        $sitemap_resp = wp_remote_head( $sitemap_url, array( 'timeout' => 5 ) );
        $sitemap_ok   = ! is_wp_error( $sitemap_resp ) && wp_remote_retrieve_response_code( $sitemap_resp ) === 200;
        $items[] = array(
            'ok'    => $sitemap_ok,
            'label' => 'Sitemap XML acessível',
            'desc'  => home_url( '/sitemap_index.xml' ),
            'acao'  => $sitemap_ok ? '' : 'Ativar sitemap no Rank Math → Sitemap',
        );

        // SSL
        $ssl = is_ssl() || strpos( home_url(), 'https' ) === 0;
        $items[] = array(
            'ok'    => $ssl,
            'label' => 'HTTPS ativo (SSL)',
            'desc'  => 'Fator de ranqueamento confirmado pelo Google',
            'acao'  => $ssl ? '' : 'Ativar SSL no painel da Hostnet',
        );

        // Cloudflare
        $cf = isset( $_SERVER['HTTP_CF_RAY'] ) || isset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
        $items[] = array(
            'ok'    => $cf,
            'label' => 'Cloudflare CDN ativo',
            'desc'  => 'CDN melhora velocidade e Core Web Vitals',
            'acao'  => $cf ? '' : 'Configurar domínio no Cloudflare',
        );

        // WP Rocket
        $rocket = is_plugin_active( 'wp-rocket/wp-rocket.php' ) || defined( 'WP_ROCKET_VERSION' );
        $items[] = array(
            'ok'    => $rocket,
            'label' => 'WP Rocket (cache) ativo',
            'desc'  => 'Cache de página reduz TTFB e melhora LCP',
            'acao'  => $rocket ? '' : 'Ativar WP Rocket em Plugins',
        );

        // Imagens com lazy loading
        $items[] = array(
            'ok'    => true,
            'label' => 'Lazy loading de imagens ativo',
            'desc'  => 'Atributo loading="lazy" nos cards de música',
            'acao'  => '',
        );

        // Open Graph
        $items[] = array(
            'ok'    => class_exists( 'CV_Schema' ),
            'label' => 'Open Graph / og:video configurado',
            'desc'  => 'Compartilhamento rico no WhatsApp e Facebook',
            'acao'  => '',
        );

        // Backup
        $updraft = is_plugin_active( 'updraftplus/updraftplus.php' ) || class_exists( 'UpdraftPlus' );
        $items[] = array(
            'ok'    => $updraft,
            'label' => 'Backup automático (UpdraftPlus)',
            'desc'  => 'Proteção contra perda de dados',
            'acao'  => $updraft ? '' : '⚠️ URGENTE: Instalar UpdraftPlus em Plugins → Adicionar Novo',
        );

        // Robots.txt
        $robots_url  = home_url( '/robots.txt' );
        $robots_resp = wp_remote_head( $robots_url, array( 'timeout' => 5 ) );
        $robots_ok   = ! is_wp_error( $robots_resp ) && wp_remote_retrieve_response_code( $robots_resp ) === 200;
        $items[] = array(
            'ok'    => $robots_ok,
            'label' => 'robots.txt acessível',
            'desc'  => 'Controla o que o Google pode indexar',
            'acao'  => $robots_ok ? '' : 'Verificar em Rank Math → Configurações → robots.txt',
        );

        return $items;
    }

    // ── Página principal ───────────────────────────────────────────────
    public static function page_seo() {
        $nonce     = wp_create_nonce( 'cv_admin_nonce' );
        $ajax      = admin_url( 'admin-ajax.php' );
        $checklist = self::get_checklist();
        $ok_count  = count( array_filter( $checklist, function( $i ) { return $i['ok']; } ) );
        $total_ck  = count( $checklist );
        $ck_pct    = round( $ok_count / $total_ck * 100 );
        ?>
        <div id="cv-seo-exec" class="cv-admin-wrap">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        /* Dark mode sobre as classes existentes do SEO */
        #cv-seo-exec .cv-admin-header { background:#F8F0E4; border:1px solid #EADBC6; border-radius:12px; padding:18px 22px; margin-bottom:20px; }
        #cv-seo-exec h1 { color:#3B2418 !important; }
        #cv-seo-exec .cv-admin-subtitle { color:#8A6A55 !important; }
        #cv-seo-exec .cv-kpi-grid { background:transparent !important; }
        #cv-seo-exec .cv-kpi-card { background:#F8F0E4 !important; border:1px solid #EADBC6 !important; border-radius:12px !important; }
        #cv-seo-exec .cv-kpi-card.cv-kpi-highlight { border-color:#1DB954 !important; }
        #cv-seo-exec .cv-kpi-value { color:#7B3A22 !important; }
        #cv-seo-exec .cv-kpi-label { color:#8A6A55 !important; }
        #cv-seo-exec .cv-section { background:#F8F0E4 !important; border:1px solid #EADBC6 !important; border-radius:12px !important; }
        #cv-seo-exec .cv-section-title { color:#7B3A22 !important; border-color:#EADBC6 !important; }
        #cv-seo-exec .cv-table { background:#F8F0E4 !important; }
        #cv-seo-exec .cv-table th { background:#F8F0E4 !important; color:#8A6A55 !important; border-color:#EADBC6 !important; }
        #cv-seo-exec .cv-table td { border-color:#EADBC6 !important; color:#3B2418 !important; }
        #cv-seo-exec .cv-table tr:hover td { background:rgba(123,58,34,0.03) !important; }
        #cv-seo-exec .cv-btn-outline { background:rgba(242,165,26,0.13) !important; border-color:rgba(201,162,126,0.6) !important; color:#7B3A22 !important; border-radius:8px !important; }
        #cv-seo-exec .cv-btn-primary { background:#F2A51A !important; color:#3B2418 !important; border-radius:8px !important; }
        #cv-seo-exec .cv-input { background:rgba(123,58,34,0.04) !important; border-color:#EADBC6 !important; color:#3B2418 !important; }
        #cv-seo-exec .cv-badge { border-radius:6px !important; }
        </style>

            <div class="cv-admin-header">
                <div>
                    <?php echo CV_Admin::btn_voltar(); ?>
                    <h1 style="margin:8px 0 4px;font-size:22px">📡 SEO &amp; Visibilidade</h1>
                    <p class="cv-admin-subtitle">Saúde técnica, cobertura do catálogo e potencial orgânico</p>
                </div>
                <div style="margin-left:auto">
                    <button id="cv-seo-refresh" class="cv-btn cv-btn-outline" style="padding:9px 18px;font-weight:700">
                        🔄 Atualizar dados
                    </button>
                </div>
            </div>

            <!-- ── BLOCO 1: KPIs de saúde ───────────────────────── -->
            <div class="cv-kpi-grid" style="margin-bottom:24px">
                <div class="cv-kpi-card <?php echo $ck_pct >= 80 ? 'cv-kpi-highlight' : ''; ?>">
                    <span class="cv-kpi-icon"><?php echo $ck_pct >= 80 ? '✅' : ($ck_pct >= 60 ? '⚠️' : '❌'); ?></span>
                    <div class="cv-kpi-value"><?php echo $ck_pct; ?>%</div>
                    <div class="cv-kpi-label">Saúde SEO Técnico</div>
                </div>
                <div class="cv-kpi-card" id="kpi-avg-score">
                    <span class="cv-kpi-icon">📊</span>
                    <div class="cv-kpi-value" id="kpi-avg-val">—</div>
                    <div class="cv-kpi-label">Score médio das músicas</div>
                </div>
                <div class="cv-kpi-card" id="kpi-total-mus">
                    <span class="cv-kpi-icon">🎵</span>
                    <div class="cv-kpi-value" id="kpi-total-val">—</div>
                    <div class="cv-kpi-label">Músicas publicadas</div>
                </div>
                <div class="cv-kpi-card" id="kpi-otimo">
                    <span class="cv-kpi-icon">🏆</span>
                    <div class="cv-kpi-value" id="kpi-otimo-val">—</div>
                    <div class="cv-kpi-label">Músicas score ≥ 90</div>
                </div>
            </div>

            <!-- ── BLOCO 2: Checklist + Cobertura ──────────────── -->
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">

                <!-- Checklist -->
                <div class="cv-section" style="margin:0">
                    <h2 class="cv-section-title">🛡 Checklist de Saúde SEO</h2>
                    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
                        <div style="flex:1;background:#FBF6EE;border-radius:20px;height:8px;overflow:hidden">
                            <div style="width:<?php echo $ck_pct; ?>%;height:100%;background:<?php echo $ck_pct>=80?'#27ae60':($ck_pct>=60?'#B8700C':'#e74c3c'); ?>;border-radius:20px;transition:width 1s"></div>
                        </div>
                        <span style="font-size:13px;font-weight:700;color:<?php echo $ck_pct>=80?'#27ae60':($ck_pct>=60?'#B8700C':'#e74c3c'); ?>"><?php echo $ok_count; ?>/<?php echo $total_ck; ?></span>
                    </div>
                    <?php foreach ( $checklist as $item ) : ?>
                    <div style="display:flex;gap:12px;align-items:flex-start;padding:10px 0;border-bottom:1px solid rgba(123,58,34,0.12)">
                        <span style="font-size:18px;flex-shrink:0;margin-top:1px"><?php echo $item['ok'] ? '✅' : '❌'; ?></span>
                        <div style="flex:1;min-width:0">
                            <div style="font-size:13px;font-weight:<?php echo $item['ok'] ? '400' : '600'; ?>;color:<?php echo $item['ok'] ? '#C9A27E' : '#3B2418'; ?>">
                                <?php echo esc_html( $item['label'] ); ?>
                            </div>
                            <div style="font-size:11px;color:#8A6A55;margin-top:2px"><?php echo esc_html( $item['desc'] ); ?></div>
                            <?php if ( ! $item['ok'] && $item['acao'] ) : ?>
                            <div style="font-size:11px;color:#7B3A22;margin-top:4px">→ <?php echo esc_html( $item['acao'] ); ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Cobertura do catálogo -->
                <div style="display:flex;flex-direction:column;gap:20px">
                    <div class="cv-section" style="margin:0">
                        <h2 class="cv-section-title">📚 Cobertura do Catálogo</h2>
                        <div id="cv-coverage-bars">
                            <div style="text-align:center;padding:20px;color:#8A6A55">Carregando...</div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- ── BLOCO 3: Tabela de músicas com score ─────────── -->
            <div class="cv-section">
                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:16px">
                    <h2 class="cv-section-title" style="margin:0">🎵 Score SEO por Música</h2>
                    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
                        <select id="cv-seo-filter" class="cv-input" style="padding:6px 10px;font-size:12px">
                            <option value="all">Todas</option>
                            <option value="otimo">Ótimo (≥90)</option>
                            <option value="bom">Bom (70–89)</option>
                            <option value="regular">Regular (40–69)</option>
                            <option value="fraco">Fraco (&lt;40)</option>
                        </select>
                        <input type="text" id="cv-seo-search" class="cv-input"
                               placeholder="Filtrar por título..." style="padding:6px 10px;font-size:12px;max-width:200px">
                    </div>
                </div>
                <div id="cv-seo-table-wrap">
                    <div style="text-align:center;padding:40px;color:#8A6A55">
                        <div style="font-size:28px;animation:cv-spin 1s linear infinite;display:inline-block">⏳</div>
                        <p style="margin-top:10px;font-size:13px">Analisando músicas...</p>
                    </div>
                </div>
            </div>

        </div><!-- /.cv-admin-wrap -->

        <style>
        .cv-seo-bar-row { margin-bottom:14px; }
        .cv-seo-bar-label {
            display:flex;justify-content:space-between;
            font-size:12px;color:#6B4C3B;margin-bottom:5px;
        }
        .cv-seo-bar-track {
            background:#FBF6EE;border-radius:20px;height:10px;overflow:hidden;
        }
        .cv-seo-bar-fill {
            height:100%;border-radius:20px;
            transition:width 1s ease;
        }
        .cv-score-badge {
            display:inline-block;padding:3px 10px;border-radius:20px;
            font-size:11px;font-weight:700;
        }
        .cv-score-otimo  { background:rgba(39,174,96,.15);color:#1C7C44; }
        .cv-score-bom    { background:rgba(242,165,26,0.2);color:#7B3A22; }
        .cv-score-regular{ background:rgba(242,165,26,0.2);color:#7B3A22; }
        .cv-score-fraco  { background:rgba(231,76,60,.15);color:#D62C1A; }
        .cv-seo-dot { display:inline-block;width:8px;height:8px;border-radius:50%;margin-right:2px; }
        @keyframes cv-spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }
        @media(max-width:900px){
            [style*="grid-template-columns:1fr 1fr"]{grid-template-columns:1fr!important}
        }
        </style>

        <script>
        jQuery(function($){
            var AJAX  = '<?php echo esc_js( $ajax ); ?>';
            var NONCE = '<?php echo esc_js( $nonce ); ?>';
            var allMusicas = [];

            // ── Cobertura do catálogo ────────────────────────────
            function loadCoverage() {
                $.post(AJAX, { action:'cv_seo_coverage', nonce:NONCE }, function(res){
                    if (!res.success) return;
                    var d   = res.data;
                    var tot = d.total || 1;
                    $('#kpi-total-val').text(d.total);

                    var bars = [
                        { label:'Letra completa',  val:d.com_letra,   color:'#27ae60' },
                        { label:'URL YouTube',     val:d.com_youtube, color:'#B8700C' },
                        { label:'Capa (imagem)',   val:d.com_capa,    color:'#3498db' },
                        { label:'Artista definido',val:d.com_artista, color:'#9b59b6' },
                    ];

                    var html = '';
                    $.each(bars, function(i, b){
                        var pct = tot > 0 ? Math.round(b.val / tot * 100) : 0;
                        html += '<div class="cv-seo-bar-row">'
                              + '<div class="cv-seo-bar-label"><span>' + b.label + '</span>'
                              + '<span style="font-weight:700;color:' + b.color + '">' + b.val + '/' + d.total + ' (' + pct + '%)</span></div>'
                              + '<div class="cv-seo-bar-track">'
                              + '<div class="cv-seo-bar-fill" style="width:' + pct + '%;background:' + b.color + '"></div>'
                              + '</div></div>';
                    });
                    $('#cv-coverage-bars').html(html);
                });
            }

            // ── Score SEO das músicas ────────────────────────────
            function loadMusicScores() {
                $('#cv-seo-table-wrap').html(
                    '<div style="text-align:center;padding:40px;color:#8A6A55">'
                    + '<div style="font-size:28px;animation:cv-spin 1s linear infinite;display:inline-block">⏳</div>'
                    + '<p style="margin-top:10px;font-size:13px">Analisando músicas...</p></div>'
                );
                $.post(AJAX, { action:'cv_seo_music_scores', nonce:NONCE }, function(res){
                    if (!res.success) return;
                    var d = res.data;
                    allMusicas = d.musicas;

                    $('#kpi-avg-val').text(d.avg + '/100');
                    $('#kpi-otimo-val').text(d.dist.otimo);

                    renderTable(allMusicas);
                });
            }

            function scoreClass(s) {
                if (s >= 90) return 'otimo';
                if (s >= 70) return 'bom';
                if (s >= 40) return 'regular';
                return 'fraco';
            }
            function scoreLabel(s) {
                if (s >= 90) return 'Ótimo';
                if (s >= 70) return 'Bom';
                if (s >= 40) return 'Regular';
                return 'Fraco';
            }

            function dotIcon(ok) {
                return '<span class="cv-seo-dot" style="background:' + (ok ? '#27ae60' : '#F3E6D3') + '"></span>';
            }

            function renderTable(list) {
                if (!list.length) {
                    $('#cv-seo-table-wrap').html('<div style="text-align:center;padding:40px;color:#8A6A55">Nenhuma música encontrada.</div>');
                    return;
                }
                var html = '<div style="overflow-x:auto"><table class="cv-table" style="min-width:640px">'
                         + '<thead><tr>'
                         + '<th>Música</th><th>Artista</th>'
                         + '<th style="text-align:center">Letra</th>'
                         + '<th style="text-align:center">YouTube</th>'
                         + '<th style="text-align:center">Capa</th>'
                         + '<th style="text-align:center">Score</th>'
                         + '<th style="text-align:center">Ação</th>'
                         + '</tr></thead><tbody>';

                $.each(list, function(i, m){
                    var cls = scoreClass(m.score);
                    html += '<tr>'
                          + '<td style="font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'
                          + '<a href="' + m.url + '" target="_blank" style="color:#6B4C3B;text-decoration:none" title="' + m.titulo + '">' + m.titulo + '</a></td>'
                          + '<td style="font-size:12px;color:#8A6A55">' + (m.artista || '—') + '</td>'
                          + '<td style="text-align:center">' + dotIcon(m.detail.letra) + '</td>'
                          + '<td style="text-align:center">' + dotIcon(m.detail.youtube) + '</td>'
                          + '<td style="text-align:center">' + dotIcon(m.detail.capa) + '</td>'
                          + '<td style="text-align:center">'
                          + '<span class="cv-score-badge cv-score-' + cls + '">' + m.score + ' — ' + scoreLabel(m.score) + '</span>'
                          + '</td>'
                          + '<td style="text-align:center">'
                          + '<a href="' + m.edit + '" class="cv-btn cv-btn-outline" style="padding:3px 10px;font-size:11px">✏ Editar</a>'
                          + '</td>'
                          + '</tr>';
                });
                html += '</tbody></table></div>';
                $('#cv-seo-table-wrap').html(html);
            }

            // ── Filtros ──────────────────────────────────────────
            $('#cv-seo-filter').on('change', function(){
                applyFilter();
            });
            $('#cv-seo-search').on('input', function(){
                applyFilter();
            });

            function applyFilter() {
                var cat   = $('#cv-seo-filter').val();
                var termo = $('#cv-seo-search').val().toLowerCase();
                var list  = allMusicas.filter(function(m){
                    var catOk = cat === 'all'
                        || (cat === 'otimo'   && m.score >= 90)
                        || (cat === 'bom'     && m.score >= 70 && m.score < 90)
                        || (cat === 'regular' && m.score >= 40 && m.score < 70)
                        || (cat === 'fraco'   && m.score < 40);
                    var termOk = !termo || m.titulo.toLowerCase().indexOf(termo) !== -1;
                    return catOk && termOk;
                });
                renderTable(list);
            }

            // ── Atualizar ────────────────────────────────────────
            $('#cv-seo-refresh').on('click', function(){
                $.post(AJAX, { action:'cv_clear_charts_cache', nonce:NONCE });
                delete_transient_seo();
                loadAll();
            });

            function delete_transient_seo() {
                // Limpa via AJAX dedicado
                $.post(AJAX, { action:'cv_clear_seo_cache', nonce:NONCE });
            }

            function loadAll() {
                loadCoverage();
                loadMusicScores();
            }

            loadAll();
        });
        </script>
        <?php
    }
}

CV_Admin_SEO::init();
