<?php
// cancao-verdadeira/includes/admin/class-cv-admin-pages.php
// 2026-06-27 12:00
// Páginas do painel administrativo: Dashboard executivo, Ranking, Assinantes,
// Aparência, Configurações, Usuários, Logs, Permissões, YouTube Import.
// v2.0 — Dashboard completamente redesenhado: KPIs com crescimento,
// gráfico de plays 30 dias, Top 5 com barras visuais, feed de atividade,
// metas de progresso e status do sistema. Visual executivo dark premium.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin_Pages {

    public static function page_dashboard() {
        global $wpdb;
        $stats = CV_Admin::get_stats();

        // ── Dados extras para o novo dashboard ───────────────────────

        // Plays últimas 24h vs 48h (crescimento)
        $plays_24h   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE played_at >= NOW() - INTERVAL 24 HOUR" );
        $plays_48_24 = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE played_at >= NOW() - INTERVAL 48 HOUR AND played_at < NOW() - INTERVAL 24 HOUR" );
        $plays_pct   = $plays_48_24 > 0 ? round( ( ( $plays_24h - $plays_48_24 ) / $plays_48_24 ) * 100 ) : 0;

        // Usuários esta semana vs semana passada
        $users_7d    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= NOW() - INTERVAL 7 DAY" );
        $users_14_7  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= NOW() - INTERVAL 14 DAY AND user_registered < NOW() - INTERVAL 7 DAY" );
        $users_pct   = $users_14_7 > 0 ? round( ( ( $users_7d - $users_14_7 ) / $users_14_7 ) * 100 ) : 0;

        // Assinantes esta semana
        $subs_7d     = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_subscribers WHERE subscribed_at >= NOW() - INTERVAL 7 DAY" );

        // ── KPIs Editoriais (novos — v2.23.0) ────────────────────────
        $pub_ids = get_posts( array( 'post_type' => 'musica', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids' ) );
        $total_pub = count( $pub_ids );
        $sem_letra = 0; $sem_yt = 0; $sem_capa = 0;
        foreach ( $pub_ids as $pid ) {
            if ( ! CV_Fields::has_letra( $pid ) )                          $sem_letra++;
            if ( empty( get_post_meta( $pid, '_cv_youtube_url', true ) ) ) $sem_yt++;
            if ( ! has_post_thumbnail( $pid ) )                            $sem_capa++;
        }
        $score_editorial = $total_pub > 0 ? max( 0, round( 100 - ( ( $sem_letra * 30 + $sem_yt * 25 + $sem_capa * 15 ) / max(1,$total_pub) ) ) ) : 100;


        // Total plays histórico
        $plays_total = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log" );
        $plays_7d    = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log WHERE played_at >= NOW() - INTERVAL 7 DAY" );

        // Plays últimos 30 dias por dia (para gráfico)
        $plays_chart = $wpdb->get_results(
            "SELECT DATE(played_at) AS dia, COUNT(*) AS total
             FROM {$wpdb->prefix}cv_plays_log
             WHERE played_at >= NOW() - INTERVAL 30 DAY
             GROUP BY DATE(played_at)
             ORDER BY dia ASC"
        );
        // Preencher dias sem plays com zero
        $chart_labels = array();
        $chart_values = array();
        $plays_by_day = array();
        foreach ( $plays_chart as $row ) {
            $plays_by_day[ $row->dia ] = (int) $row->total;
        }
        for ( $i = 29; $i >= 0; $i-- ) {
            $day = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_labels[] = date( 'd/m', strtotime( $day ) );
            $chart_values[] = isset( $plays_by_day[ $day ] ) ? $plays_by_day[ $day ] : 0;
        }

        // Top 5 músicas com barra de progresso
        $top5 = $wpdb->get_results(
            "SELECT rc.music_id, rc.plays_total, rc.score, rc.position, p.post_title
             FROM {$wpdb->prefix}cv_ranking_cache rc
             LEFT JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             ORDER BY rc.plays_total DESC
             LIMIT 5"
        );
        $max_plays = ! empty( $top5 ) ? max( array_column( (array) $top5, 'plays_total' ) ) : 1;
        if ( $max_plays < 1 ) $max_plays = 1;

        // Feed de atividade recente (últimas 6 ações)
        $recent_plays = $wpdb->get_results(
            "SELECT p.played_at, po.post_title AS musica
             FROM {$wpdb->prefix}cv_plays_log p
             LEFT JOIN {$wpdb->posts} po ON po.ID = p.music_id
             ORDER BY p.played_at DESC LIMIT 3"
        );
        $recent_users = $wpdb->get_results(
            "SELECT display_name, user_registered FROM {$wpdb->users}
             ORDER BY user_registered DESC LIMIT 3"
        );

        // Metas de progresso
        $meta_musicas  = (int) get_option( 'cv_meta_musicas', 100 );
        $meta_assin    = (int) get_option( 'cv_meta_assinantes', 1000 );
        $pct_musicas   = min( 100, round( ( $stats['total_musicas'] / max( 1, $meta_musicas ) ) * 100 ) );
        $pct_assin     = min( 100, round( ( $stats['subscribers'] / max( 1, $meta_assin ) ) * 100 ) );

        // Status do sistema
        $last_cron     = get_option( 'cv_last_ranking_update', '' );
        $cron_ok       = $last_cron && ( time() - strtotime( $last_cron ) < 7200 );
        $last_seo_scan = get_option( 'cv_last_seo_scan', '' );

        ?>
        <div id="cv-admin-page" class="cv-admin-wrap cv-dashboard-v2">

        <style>
        .cv-dashboard-v2 { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; max-width: 1200px; }

        /* ── Hero ── */
        .cv-dash-hero {
            background: linear-gradient(135deg, #FFFFFF 0%, #F8F4E7 60%, #F8F5E7 100%);
            border: 1px solid #E0D7AE;
            border-radius: 16px;
            padding: 24px 32px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .cv-dash-hero-logo {
            width: 56px;
            height: 56px;
            object-fit: contain;
            border-radius: 10px;
            background: rgba(242,165,26,0.1);
            padding: 6px;
        }
        .cv-dash-hero-text h1 { color: #7B3A22; font-size: 20px; margin: 0 0 2px 0; font-weight: 800; }
        .cv-dash-hero-text p  { color: #8A6A55; font-size: 12px; margin: 0; }
        .cv-dash-hero-time    { margin-left: auto; text-align: right; color: #8A6A55; font-size: 12px; }
        .cv-dash-hero-time strong { color: #7B3A22; display: block; font-size: 18px; }

        /* ── KPIs ── */
        .cv-kpi-grid-v2 {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 16px;
            margin-bottom: 24px;
        }
        @media (max-width:1100px) { .cv-kpi-grid-v2 { grid-template-columns: repeat(3,1fr); } }
        @media (max-width:700px)  { .cv-kpi-grid-v2 { grid-template-columns: 1fr 1fr; } }

        .cv-kpi2 {
            background: #FFFFFF;
            border: 1px solid #EADBC6;
            border-radius: 12px;
            padding: 20px 18px 16px;
            position: relative;
            overflow: hidden;
            transition: border-color .2s, transform .2s;
        }
        .cv-kpi2:hover { border-color: #EADBC6; transform: translateY(-2px); }
        .cv-kpi2::after {
            content: "";
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 0 0 12px 12px;
        }
        .cv-kpi2.k-musicas::after  { background: linear-gradient(90deg,#F2A51A,#F2A51A); }
        .cv-kpi2.k-users::after    { background: linear-gradient(90deg,#4a90d9,#5aa8f0); }
        .cv-kpi2.k-plays::after    { background: linear-gradient(90deg,#1db954,#22d460); }
        .cv-kpi2.k-favs::after     { background: linear-gradient(90deg,#e74c3c,#ff6b6b); }
        .cv-kpi2.k-subs::after     { background: linear-gradient(90deg,#9b59b6,#b278cc); }

        .cv-kpi2-icon  { font-size: 22px; margin-bottom: 8px; display: block; }
        .cv-kpi2-value {
            font-size: 32px;
            font-weight: 800;
            color: #3B2418;
            line-height: 1;
            margin-bottom: 4px;
            font-variant-numeric: tabular-nums;
        }
        .cv-kpi2-label { font-size: 11px; color: #8A6A55; text-transform: uppercase; letter-spacing: .5px; }
        .cv-kpi2-badge {
            position: absolute;
            top: 14px; right: 14px;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
        }
        .cv-badge-up   { background: rgba(29,185,84,.15); color: #137B38; }
        .cv-badge-down { background: rgba(231,76,60,.15);  color: #D62C1A; }
        .cv-badge-neu  { background: rgba(123,58,34,0.07); color: #8A6A55; }

        /* ── Linha central: gráfico + top5 ── */
        .cv-dash-mid {
            display: grid;
            grid-template-columns: 1.6fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
            align-items: start;
        }
        @media (max-width:900px) { .cv-dash-mid { grid-template-columns: 1fr; } }

        .cv-dash-panel {
            background: #FFFFFF;
            border: 1px solid #EADBC6;
            border-radius: 12px;
            padding: 20px 22px;
            max-height: 280px;
            overflow-y: auto;
        }
        .cv-dash-panel h3 {
            font-size: 13px;
            color: #8A6A55;
            text-transform: uppercase;
            letter-spacing: .6px;
            margin: 0 0 16px 0;
            font-weight: 600;
        }
        .cv-dash-panel h3 strong { color: #7B3A22; }
        #cv-plays-chart { width: 100%; height: 140px; max-height: 140px; }

        /* Top 5 */
        .cv-top5-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 14px;
        }
        .cv-top5-item:last-child { margin-bottom: 0; }
        .cv-top5-pos {
            width: 22px;
            height: 22px;
            border-radius: 50%;
            background: rgba(242,165,26,0.16);
            color: #7B3A22;
            font-size: 11px;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cv-top5-pos.gold   { background: rgba(242,165,26,0.33); color: #7B3A22; }
        .cv-top5-pos.silver { background: rgba(123,58,34,0.17); color: #6B4C3B; }
        .cv-top5-pos.bronze { background: rgba(180,100,40,.15); color: #9C6126; }
        .cv-top5-info { flex: 1; min-width: 0; }
        .cv-top5-title {
            font-size: 12px;
            color: #3B2418;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 4px;
        }
        .cv-top5-bar-bg {
            height: 4px;
            background: #F8F0E4;
            border-radius: 4px;
            overflow: hidden;
        }
        .cv-top5-bar-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg,#F2A51A,#F2A51A);
            transition: width .6s ease;
        }
        .cv-top5-plays { font-size: 11px; color: #7B3A22; font-weight: 700; white-space: nowrap; }

        /* ── Linha inferior: feed + metas + status ── */
        .cv-dash-bot {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            margin-bottom: 24px;
        }
        @media (max-width:900px) { .cv-dash-bot { grid-template-columns: 1fr; } }

        /* Feed */
        .cv-feed-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 0;
            border-bottom: 1px solid #EADBC6;
            font-size: 12px;
        }
        .cv-feed-item:last-child { border-bottom: none; }
        .cv-feed-icon {
            width: 28px; height: 28px;
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            font-size: 13px;
            flex-shrink: 0;
        }
        .cv-feed-icon.play { background: rgba(29,185,84,.12); }
        .cv-feed-icon.user { background: rgba(74,144,217,.12); }
        .cv-feed-text { flex: 1; color: #6B4C3B; line-height: 1.4; }
        .cv-feed-text strong { color: #3B2418; }
        .cv-feed-time { color: #8A6A55; font-size: 10px; white-space: nowrap; }

        /* Metas */
        .cv-meta-item { margin-bottom: 18px; }
        .cv-meta-item:last-child { margin-bottom: 0; }
        .cv-meta-header { display: flex; justify-content: space-between; margin-bottom: 6px; font-size: 12px; }
        .cv-meta-name { color: #8A6A55; }
        .cv-meta-pct  { color: #7B3A22; font-weight: 700; }
        .cv-meta-bar-bg { height: 6px; background: #F8F0E4; border-radius: 6px; overflow: hidden; }
        .cv-meta-bar-fill {
            height: 100%;
            border-radius: 6px;
            background: linear-gradient(90deg,#F2A51A,#F2A51A);
            transition: width .8s ease;
        }
        .cv-meta-sub { font-size: 11px; color: #8A6A55; margin-top: 4px; }

        /* Status */
        .cv-status-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 9px 0;
            border-bottom: 1px solid #EADBC6;
            font-size: 12px;
            color: #8A6A55;
        }
        .cv-status-item:last-child { border-bottom: none; }
        .cv-status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            margin-right: 8px;
            display: inline-block;
            flex-shrink: 0;
        }
        .dot-ok  { background: #1db954; box-shadow: 0 0 6px rgba(29,185,84,.5); }
        .dot-warn{ background: #F2A51A; box-shadow: 0 0 6px rgba(242,165,26,0.65); }
        .dot-off { background: #F3E6D3; }
        .cv-status-val { color: #8A6A55; font-size: 11px; }

        /* Ações rápidas */
        .cv-quick-v2 {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            padding: 18px 0 4px;
            border-top: 1px solid #EADBC6;
        }
        .cv-quick-v2 .cv-btn { font-size: 12px; padding: 8px 16px; }
        </style>

        <!-- Hero -->
        <div class="cv-dash-hero">
            <img src="<?php echo esc_url( get_option( 'cv_logo_url', CV_PLUGIN_URL . 'assets/img/logo.png' ) ); ?>"
                 alt="Canção Verdadeira" class="cv-dash-hero-logo" />
            <div class="cv-dash-hero-text">
                <h1>Canção Verdadeira</h1>
                <p>Painel Executivo — visão geral da plataforma</p>
            </div>
            <div class="cv-dash-hero-time">
                <strong><?php echo date_i18n( 'H:i' ); ?></strong>
                <?php echo date_i18n( 'd \d\e F \d\e Y' ); ?>
            </div>
        </div>

        <!-- KPIs -->
        <div class="cv-kpi-grid-v2">

            <?php
            $badge_plays = $plays_pct > 0 ? 'up' : ( $plays_pct < 0 ? 'down' : 'neu' );
            $badge_users = $users_pct > 0 ? 'up' : ( $users_pct < 0 ? 'down' : 'neu' );
            $badge_plays_label = ( $plays_pct >= 0 ? '+' : '' ) . $plays_pct . '% vs ontem';
            $badge_users_label = ( $users_pct >= 0 ? '+' : '' ) . $users_pct . '% vs sem. ant.';
            ?>

            <div class="cv-kpi2 k-musicas">
                <span class="cv-kpi2-icon">🎵</span>
                <div class="cv-kpi2-value" data-target="<?php echo esc_attr( $stats['total_musicas'] ); ?>">0</div>
                <div class="cv-kpi2-label">Músicas</div>
            </div>

            <div class="cv-kpi2 k-users">
                <span class="cv-kpi2-badge cv-badge-<?php echo $badge_users; ?>"><?php echo esc_html( $badge_users_label ); ?></span>
                <span class="cv-kpi2-icon">👥</span>
                <div class="cv-kpi2-value" data-target="<?php echo esc_attr( $stats['total_users'] ); ?>">0</div>
                <div class="cv-kpi2-label">Usuários</div>
            </div>

            <div class="cv-kpi2 k-plays">
                <span class="cv-kpi2-badge cv-badge-<?php echo $badge_plays; ?>"><?php echo esc_html( $badge_plays_label ); ?></span>
                <span class="cv-kpi2-icon">▶</span>
                <div class="cv-kpi2-value" data-target="<?php echo esc_attr( $plays_24h ); ?>">0</div>
                <div class="cv-kpi2-label">Plays (24h)</div>
            </div>

            <div class="cv-kpi2 k-favs">
                <span class="cv-kpi2-icon">❤</span>
                <div class="cv-kpi2-value" data-target="<?php echo esc_attr( $stats['total_favorites'] ); ?>">0</div>
                <div class="cv-kpi2-label">Favoritos</div>
            </div>

            <div class="cv-kpi2 k-subs">
                <?php if ( $subs_7d > 0 ) : ?>
                <span class="cv-kpi2-badge cv-badge-up">+<?php echo $subs_7d; ?> esta semana</span>
                <?php endif; ?>
                <span class="cv-kpi2-icon">📧</span>
                <div class="cv-kpi2-value" data-target="<?php echo esc_attr( $stats['subscribers'] ); ?>">0</div>
                <div class="cv-kpi2-label">Assinantes</div>
            </div>

        </div>

        <!-- KPIs Editoriais — v2.23.0 -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;">
                <div style="font-size:24px;">📝</div>
                <div>
                    <div style="font-size:20px;font-weight:800;color:<?php echo $sem_letra > 0 ? '#e74c3c' : '#1DB954'; ?>;"><?php echo $sem_letra; ?></div>
                    <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.5px;">Sem Letra</div>
                </div>
            </div>

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;">
                <div style="font-size:24px;">▶️</div>
                <div>
                    <div style="font-size:20px;font-weight:800;color:<?php echo $sem_yt > 0 ? '#e74c3c' : '#1DB954'; ?>;"><?php echo $sem_yt; ?></div>
                    <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.5px;">Sem YouTube</div>
                </div>
            </div>

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;">
                <div style="font-size:24px;">🖼️</div>
                <div>
                    <div style="font-size:20px;font-weight:800;color:<?php echo $sem_capa > 0 ? '#e67e22' : '#1DB954'; ?>;"><?php echo $sem_capa; ?></div>
                    <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.5px;">Sem Capa</div>
                </div>
            </div>

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;">
                <div style="font-size:24px;">🧠</div>
                <div>
                    <div style="font-size:20px;font-weight:800;color:<?php echo $score_editorial >= 85 ? '#1DB954' : ( $score_editorial >= 60 ? '#e67e22' : '#e74c3c' ); ?>;"><?php echo $score_editorial; ?>%</div>
                    <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.5px;">Completude</div>
                </div>
            </div>

            <a href="<?php echo esc_url( admin_url('admin.php?page=cv-editorial') ); ?>"
               style="background:#D4A01711;border:1px solid #D4A01744;border-radius:10px;padding:16px;display:flex;align-items:center;gap:12px;text-decoration:none;">
                <div style="font-size:24px;">🔍</div>
                <div>
                    <div style="font-size:13px;font-weight:700;color:#7B3A22;">Ver Oportunidades</div>
                    <div style="font-size:11px;color:#8A6A55;">Inteligência Editorial</div>
                </div>
            </a>

        </div>

        <!-- Gráfico + Top 5 -->
        <div class="cv-dash-mid">

            <div class="cv-dash-panel">
                <h3>📈 Plays diários — <strong>últimos 30 dias</strong>
                    <span style="float:right;color:#137B38;font-size:12px;text-transform:none;letter-spacing:0">
                        <?php echo number_format( $plays_7d ); ?> esta semana
                    </span>
                </h3>
                <canvas id="cv-plays-chart"></canvas>
            </div>

            <div class="cv-dash-panel">
                <h3>🏆 Top 5 Mais Tocadas</h3>
                <?php if ( empty( $top5 ) ) : ?>
                    <p style="color:#8A6A55;font-size:13px">Nenhuma música rankeada ainda.</p>
                <?php else : ?>
                    <?php
                    $pos_classes = array( 'gold', 'silver', 'bronze', '', '' );
                    foreach ( $top5 as $i => $row ) :
                        $pct = $max_plays > 0 ? round( ( $row->plays_total / $max_plays ) * 100 ) : 0;
                    ?>
                    <div class="cv-top5-item">
                        <div class="cv-top5-pos <?php echo esc_attr( $pos_classes[ $i ] ?? '' ); ?>"><?php echo $i + 1; ?></div>
                        <div class="cv-top5-info">
                            <div class="cv-top5-title" title="<?php echo esc_attr( $row->post_title ); ?>"><?php echo esc_html( $row->post_title ); ?></div>
                            <div class="cv-top5-bar-bg">
                                <div class="cv-top5-bar-fill" style="width:<?php echo $pct; ?>%"></div>
                            </div>
                        </div>
                        <div class="cv-top5-plays"><?php echo number_format( $row->plays_total ); ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- Feed + Metas + Status -->
        <div class="cv-dash-bot">

            <!-- Feed de atividade -->
            <div class="cv-dash-panel">
                <h3>⚡ Atividade Recente</h3>
                <?php foreach ( $recent_plays as $play ) : ?>
                <div class="cv-feed-item">
                    <div class="cv-feed-icon play">▶</div>
                    <div class="cv-feed-text">Play em <strong><?php echo esc_html( $play->musica ); ?></strong></div>
                    <div class="cv-feed-time"><?php echo esc_html( human_time_diff( strtotime( $play->played_at ) ) ); ?> atrás</div>
                </div>
                <?php endforeach; ?>
                <?php foreach ( $recent_users as $user ) : ?>
                <div class="cv-feed-item">
                    <div class="cv-feed-icon user">👤</div>
                    <div class="cv-feed-text">Novo usuário: <strong><?php echo esc_html( $user->display_name ); ?></strong></div>
                    <div class="cv-feed-time"><?php echo esc_html( human_time_diff( strtotime( $user->user_registered ) ) ); ?> atrás</div>
                </div>
                <?php endforeach; ?>
                <?php if ( empty( $recent_plays ) && empty( $recent_users ) ) : ?>
                    <p style="color:#8A6A55;font-size:12px">Nenhuma atividade recente.</p>
                <?php endif; ?>
            </div>

            <!-- Metas de progresso -->
            <div class="cv-dash-panel">
                <h3>🎯 Metas da Plataforma</h3>

                <div class="cv-meta-item">
                    <div class="cv-meta-header">
                        <span class="cv-meta-name">🎵 Catálogo de músicas</span>
                        <span class="cv-meta-pct"><?php echo $pct_musicas; ?>%</span>
                    </div>
                    <div class="cv-meta-bar-bg">
                        <div class="cv-meta-bar-fill" style="width:<?php echo $pct_musicas; ?>%"></div>
                    </div>
                    <div class="cv-meta-sub"><?php echo number_format( $stats['total_musicas'] ); ?> de <?php echo number_format( $meta_musicas ); ?> músicas</div>
                </div>

                <div class="cv-meta-item">
                    <div class="cv-meta-header">
                        <span class="cv-meta-name">📧 Assinantes newsletter</span>
                        <span class="cv-meta-pct"><?php echo $pct_assin; ?>%</span>
                    </div>
                    <div class="cv-meta-bar-bg">
                        <div class="cv-meta-bar-fill" style="width:<?php echo $pct_assin; ?>%"></div>
                    </div>
                    <div class="cv-meta-sub"><?php echo number_format( $stats['subscribers'] ); ?> de <?php echo number_format( $meta_assin ); ?> assinantes</div>
                </div>

                <div class="cv-meta-item">
                    <div class="cv-meta-header">
                        <span class="cv-meta-name">▶ Plays totais</span>
                        <span class="cv-meta-pct"><?php echo min(100, round(($plays_total/max(1,10000))*100)); ?>%</span>
                    </div>
                    <div class="cv-meta-bar-bg">
                        <div class="cv-meta-bar-fill" style="width:<?php echo min(100, round(($plays_total/max(1,10000))*100)); ?>%"></div>
                    </div>
                    <div class="cv-meta-sub"><?php echo number_format( $plays_total ); ?> de 10.000 plays</div>
                </div>
            </div>

            <!-- Status do sistema -->
            <div class="cv-dash-panel">
                <h3>🔧 Status do Sistema</h3>

                <div class="cv-status-item">
                    <span><span class="cv-status-dot <?php echo $cron_ok ? 'dot-ok' : 'dot-warn'; ?>"></span>Ranking automático</span>
                    <span class="cv-status-val"><?php echo $cron_ok ? 'Ativo' : 'Verificar'; ?></span>
                </div>
                <div class="cv-status-item">
                    <span><span class="cv-status-dot dot-ok"></span>SSL / HTTPS</span>
                    <span class="cv-status-val"><?php echo is_ssl() ? 'Ativo' : '—'; ?></span>
                </div>
                <div class="cv-status-item">
                    <span><span class="cv-status-dot dot-ok"></span>Newsletter MailerLite</span>
                    <span class="cv-status-val"><?php echo get_option('cv_mailerlite_api_key') ? 'Configurada' : 'Pendente'; ?></span>
                </div>
                <div class="cv-status-item">
                    <span><span class="cv-status-dot <?php echo $last_seo_scan ? 'dot-ok' : 'dot-warn'; ?>"></span>Scan SEO</span>
                    <span class="cv-status-val"><?php echo $last_seo_scan ? date('d/m H:i', strtotime($last_seo_scan)) : 'Nunca'; ?></span>
                </div>
                <div class="cv-status-item">
                    <span><span class="cv-status-dot dot-ok"></span>Último ranking</span>
                    <span class="cv-status-val"><?php echo $last_cron ? date('d/m H:i', strtotime($last_cron)) : '—'; ?></span>
                </div>
                <div class="cv-status-item">
                    <span><span class="cv-status-dot dot-ok"></span>Versão do plugin</span>
                    <span class="cv-status-val">v<?php echo defined('CV_VERSION') ? CV_VERSION : '—'; ?></span>
                </div>
            </div>

        </div>

        <!-- ── Central de Ações ───────────────────────────────────── -->
        <div id="cv-action-message" style="display:none;padding:10px 16px;border-radius:6px;margin-bottom:16px;font-size:13px"></div>
        <style>
        .cv-action-hub { margin-top:24px; }
        .cv-action-hub-title { font-size:11px;font-weight:700;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin:0 0 12px 0; }
        .cv-action-group { background:#FFFFFF;border:1px solid #EADBC6;border-radius:12px;padding:18px 20px;margin-bottom:16px; }
        .cv-action-group-label { font-size:10px;font-weight:700;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;display:flex;align-items:center;gap:6px; }
        .cv-action-group-label span { display:inline-block;width:20px;height:1px;background:#F3E6D3; }
        .cv-action-btns { display:flex;flex-wrap:wrap;gap:10px; }
        .cv-ab { display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:8px;font-size:13px;font-weight:600;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:all .18s;white-space:nowrap; }
        .cv-ab:hover { transform:translateY(-1px);filter:brightness(1.1); }
        .cv-ab-primary   { background:#1DB954;border-color:#1DB954;color:#3B2418; }
        .cv-ab-gold      { background:#D4A01722;border-color:#C9A27E;color:#7B3A22; }
        .cv-ab-blue      { background:#4a90d922;border-color:#4a90d9;color:#2871BE; }
        .cv-ab-purple    { background:#9b59b622;border-color:#9b59b6;color:#9752B3; }
        .cv-ab-red       { background:#e74c3c22;border-color:#e74c3c;color:#D62C1A; }
        .cv-ab-gray      { background:#F8F0E4;border-color:#EADBC6;color:#6B4C3B; }
        .cv-ab-orange    { background:#e67e2222;border-color:#e67e22;color:#AD5C14; }
        </style>

        <div class="cv-action-hub">
        <p class="cv-action-hub-title">Central de Ações</p>

        <!-- Músicas -->
        <div class="cv-action-group">
            <div class="cv-action-group-label"><span></span>🎵 Músicas <span></span></div>
            <div class="cv-action-btns">
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=musica')); ?>" class="cv-ab cv-ab-blue">🎵 Ver Músicas</a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=musica&post_status=draft')); ?>" class="cv-ab cv-ab-orange">📝 Rascunhos</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-publicacao-rapida')); ?>" class="cv-ab cv-ab-gold">⚡ Publicação Acelerada</a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=musica')); ?>" class="cv-ab cv-ab-gray">+ Nova Música</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-youtube-import')); ?>" class="cv-ab cv-ab-red">▶ Importar YouTube</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-generos')); ?>" class="cv-ab cv-ab-gray">🎸 Gêneros</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-sentimentos')); ?>" class="cv-ab cv-ab-purple">🎭 Sentimentos</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-sentimentos-crud')); ?>" class="cv-ab cv-ab-gray">⚙️ Gerenciar Sentimentos</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-playlists')); ?>" class="cv-ab cv-ab-blue">📋 Playlists</a>
            </div>
        </div>

        <!-- Audiência -->
        <div class="cv-action-group">
            <div class="cv-action-group-label"><span></span>👥 Audiência <span></span></div>
            <div class="cv-action-btns">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-users')); ?>" class="cv-ab cv-ab-blue">👥 Usuários</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-subscribers')); ?>" class="cv-ab cv-ab-purple">📨 Email Marketing</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-email')); ?>" class="cv-ab cv-ab-purple">✉️ Templates de E-mail</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-social')); ?>" class="cv-ab cv-ab-blue">📱 Redes Sociais</a>
            </div>
        </div>

        <!-- Desempenho -->
        <div class="cv-action-group">
            <div class="cv-action-group-label"><span></span>📊 Desempenho <span></span></div>
            <div class="cv-action-btns">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-ranking')); ?>" class="cv-ab cv-ab-gold">🏆 Ranking</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-analytics')); ?>" class="cv-ab cv-ab-blue">📊 Analytics</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-system-status')); ?>" class="cv-ab cv-ab-blue">🖥️ Status do Sistema</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-banco-dados')); ?>" class="cv-ab cv-ab-purple">🗄️ Banco de Dados</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-editorial')); ?>" class="cv-ab cv-ab-gold">🧠 Int. Editorial</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-seo')); ?>" class="cv-ab cv-ab-blue">📡 SEO & Visibilidade</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-exports')); ?>" class="cv-ab cv-ab-gray">📤 Exportações</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-logs')); ?>" class="cv-ab cv-ab-gray">📋 Logs</a>
            </div>
        </div>

        <!-- Monetização -->
        <div class="cv-action-group">
            <div class="cv-action-group-label"><span></span>💰 Monetização <span></span></div>
            <div class="cv-action-btns">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-loja')); ?>" class="cv-ab cv-ab-orange">🛒 Loja</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-sorteios')); ?>" class="cv-ab cv-ab-orange">🎁 Sorteios</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-brindes')); ?>" class="cv-ab cv-ab-orange">🎀 Brindes</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-banners')); ?>" class="cv-ab cv-ab-orange">🖼️ Banners</a>
            </div>
        </div>

        <!-- Sistema -->
        <div class="cv-action-group">
            <div class="cv-action-group-label"><span></span>⚙️ Sistema <span></span></div>
            <div class="cv-action-btns">
                <button id="cv-btn-recalculate" class="cv-ab" style="background:#1DB95422;border-color:#1DB954;color:#137B38;border:1px solid #1DB954">🔄 Recalcular Ranking</button>
                <button id="cv-btn-clear-cache" class="cv-ab cv-ab-gray">🗑 Limpar Cache</button>
                <button id="cv-btn-recreate-pages" class="cv-ab cv-ab-gray" onclick="return confirm('⚠️ ATENÇÃO: Recriar páginas pode sobrescrever conteúdo personalizado. Confirma?')">📄 Recriar Páginas</button>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-appearance')); ?>" class="cv-ab cv-ab-gray">🎨 Aparência</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-settings')); ?>" class="cv-ab cv-ab-gray">⚙️ Configurações</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-roles')); ?>" class="cv-ab cv-ab-red">🔐 Permissões</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-seguranca')); ?>" class="cv-ab cv-ab-red">🛡️ Segurança</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-settings#cv-modo-lancamento')); ?>" class="cv-ab cv-ab-gray">🚀 Modo lançamento</a>
            </div>
        </div>

        </div><!-- .cv-action-hub -->

        <!-- Chart.js + animações -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function(){
            // Animação dos KPI values
            document.querySelectorAll('.cv-kpi2-value[data-target]').forEach(function(el){
                var target = parseInt(el.dataset.target, 10) || 0;
                var step   = Math.ceil(target / 40);
                var cur    = 0;
                var timer  = setInterval(function(){
                    cur = Math.min(cur + step, target);
                    el.textContent = cur.toLocaleString('pt-BR');
                    if (cur >= target) clearInterval(timer);
                }, 30);
            });

            // Gráfico de plays
            var ctx = document.getElementById('cv-plays-chart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode( $chart_labels ); ?>,
                        datasets: [{
                            label: 'Plays',
                            data:  <?php echo json_encode( $chart_values ); ?>,
                            borderColor: '#B8700C',
                            backgroundColor: 'rgba(242,165,26,0.1)',
                            borderWidth: 2,
                            pointRadius: 2,
                            pointHoverRadius: 5,
                            pointBackgroundColor: '#B8700C',
                            fill: true,
                            tension: 0.4,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        aspectRatio: 2.5,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                backgroundColor: '#FFFFFF',
                                borderColor: '#F3E6D3',
                                borderWidth: 1,
                                titleColor: '#B8700C',
                                bodyColor: '#C9A27E',
                                callbacks: {
                                    label: function(c){ return ' ' + c.parsed.y + ' plays'; }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(123,58,34,0.04)' },
                                ticks: { color: '#F3E6D3', font: { size: 10 }, maxTicksLimit: 10 }
                            },
                            y: {
                                grid: { color: 'rgba(123,58,34,0.04)' },
                                ticks: { color: '#F3E6D3', font: { size: 10 }, precision: 0 },
                                beginAtZero: true
                            }
                        }
                    }
                });
            }
        })();

        // Ações rápidas
        jQuery(function($){
            var nonce  = (typeof cvAdmin !== 'undefined') ? cvAdmin.nonce  : '';
            var ajaxUrl= (typeof cvAdmin !== 'undefined') ? cvAdmin.ajaxUrl : ajaxurl;

            function showMsg(txt, ok) {
                $('#cv-action-message')
                    .removeClass('cv-notice-success cv-notice-error')
                    .addClass('cv-notice ' + (ok ? 'cv-notice-success' : 'cv-notice-error'))
                    .text(txt).show();
                setTimeout(function(){ $('#cv-action-message').fadeOut(); }, 4000);
            }

            $('#cv-btn-recalculate').on('click', function(){
                var $b = $(this).prop('disabled',true).text('Recalculando...');
                $.post(ajaxUrl,{ action:'cv_recalculate_ranking', nonce:nonce }, function(r){
                    showMsg(r.success ? '✅ Ranking recalculado!' : '❌ Erro.', r.success);
                    $b.prop('disabled',false).text('🔄 Recalcular Ranking');
                });
            });
            $('#cv-btn-clear-cache').on('click', function(){
                var $b = $(this).prop('disabled',true).text('Limpando...');
                $.post(ajaxUrl,{ action:'cv_clear_cache', nonce:nonce }, function(r){
                    showMsg(r.success ? '✅ Cache limpo!' : '❌ Erro.', r.success);
                    $b.prop('disabled',false).text('🗑 Limpar Cache');
                });
            });
            $('#cv-btn-recreate-pages').on('click', function(){
                var $b = $(this).prop('disabled',true).text('Criando...');
                $.post(ajaxUrl,{ action:'cv_recreate_pages', nonce:nonce }, function(r){
                    showMsg(r.success ? r.data.message : (r.data.message || 'Erro.'), r.success);
                    $b.prop('disabled',false).text('📄 Recriar Páginas');
                });
            });
        });
        </script>

        </div><!-- .cv-dashboard-v2 -->
        <?php
    }

    public static function page_ranking() {
        global $wpdb;

        // Tenta carregar do cache; se vazio, dispara recálculo automático e busca fallback
        $ranking = $wpdb->get_results(
            "SELECT rc.*, p.post_title, p.post_name,
                    pm.meta_value AS capa
             FROM {$wpdb->prefix}cv_ranking_cache rc
             INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
             LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = rc.music_id AND pm.meta_key = '_cv_capa_url'
             WHERE p.post_status = 'publish'
             ORDER BY rc.position ASC LIMIT 50"
        );

        // Fallback: cache vazio → recalcula automaticamente e exibe por plays
        if ( empty( $ranking ) ) {
            if ( class_exists( 'CV_Ranking' ) ) {
                CV_Ranking::recalculate();
                // Tenta novamente após recálculo
                $ranking = $wpdb->get_results(
                    "SELECT rc.*, p.post_title, p.post_name,
                            pm.meta_value AS capa
                     FROM {$wpdb->prefix}cv_ranking_cache rc
                     INNER JOIN {$wpdb->posts} p ON p.ID = rc.music_id
                     LEFT JOIN {$wpdb->postmeta} pm ON pm.post_id = rc.music_id AND pm.meta_key = '_cv_capa_url'
                     WHERE p.post_status = 'publish'
                     ORDER BY rc.position ASC LIMIT 50"
                );
            }
            // Se ainda vazio (nenhuma música), monta ranking direto das músicas por plays
            if ( empty( $ranking ) ) {
                $ranking = $wpdb->get_results(
                    "SELECT
                         p.ID AS music_id,
                         p.post_title,
                         p.post_name,
                         ROW_NUMBER() OVER (ORDER BY CAST(pm_plays.meta_value AS UNSIGNED) DESC) AS position,
                         CAST(COALESCE(pm_plays.meta_value,'0') AS UNSIGNED) AS plays_total,
                         0 AS plays_7d,
                         CAST(COALESCE(pm_favs.meta_value,'0') AS UNSIGNED) AS favorites,
                         CAST(COALESCE(pm_avg.meta_value,'0') AS DECIMAL(4,2)) AS avg_rating,
                         CAST(COALESCE(pm_plays.meta_value,'0') AS DECIMAL(10,2)) AS score,
                         0 AS position_change,
                         pm_capa.meta_value AS capa
                     FROM {$wpdb->posts} p
                     LEFT JOIN {$wpdb->postmeta} pm_plays ON pm_plays.post_id = p.ID AND pm_plays.meta_key = '_cv_plays'
                     LEFT JOIN {$wpdb->postmeta} pm_favs  ON pm_favs.post_id  = p.ID AND pm_favs.meta_key  = '_cv_favorites'
                     LEFT JOIN {$wpdb->postmeta} pm_avg   ON pm_avg.post_id   = p.ID AND pm_avg.meta_key   = '_cv_avg_rating'
                     LEFT JOIN {$wpdb->postmeta} pm_capa  ON pm_capa.post_id  = p.ID AND pm_capa.meta_key  = '_cv_capa_url'
                     WHERE p.post_type = 'musica' AND p.post_status = 'publish'
                     ORDER BY plays_total DESC
                     LIMIT 50"
                );
            }
        }

        $ranking_7d = $wpdb->get_results(
            "SELECT music_id, COUNT(*) AS plays_periodo
             FROM {$wpdb->prefix}cv_plays_log
             WHERE played_at >= NOW() - INTERVAL 7 DAY
             GROUP BY music_id ORDER BY plays_periodo DESC LIMIT 10"
        );

        $ranking_30d = $wpdb->get_results(
            "SELECT pl.music_id, COUNT(*) AS plays_periodo, p.post_title
             FROM {$wpdb->prefix}cv_plays_log pl
             LEFT JOIN {$wpdb->posts} p ON p.ID = pl.music_id
             WHERE pl.played_at >= NOW() - INTERVAL 30 DAY
             GROUP BY pl.music_id ORDER BY plays_periodo DESC LIMIT 10"
        );

        $max_score  = ! empty( $ranking ) ? max( array_column( (array) $ranking, 'score' ) ) : 1;
        if ( $max_score < 1 ) $max_score = 1;
        $top3       = array_slice( (array) $ranking, 0, 3 );
        $last_update = get_option( 'cv_last_ranking_update', '' );
        ?>
        <div class="cv-admin-wrap cv-ranking-v2" style="max-width:1100px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <?php echo CV_Admin::btn_voltar(); ?>
        
        <style>
        .cv-ranking-v2 * { box-sizing:border-box; }
        .cv-rk-hero { background:linear-gradient(135deg,#FFFFFF 0%,#F8F5E7 100%);border:1px solid #E0DAAE;border-radius:14px;padding:20px 28px;margin-bottom:22px;display:flex;align-items:center;gap:16px; }
        .cv-rk-hero h1 { color:#7B3A22;font-size:20px;margin:0 0 2px;font-weight:800; }
        .cv-rk-hero p  { color:#8A6A55;font-size:12px;margin:0; }
        .cv-rk-hero-actions { margin-left:auto;display:flex;gap:10px;align-items:center; }
        .cv-podio { display:flex;align-items:flex-end;justify-content:center;gap:16px;margin-bottom:28px;padding:0 10px; }
        .cv-podio-item { flex:1;max-width:220px;background:#FFFFFF;border-radius:12px;padding:16px 12px 14px;text-align:center;border:1px solid #EADBC6;position:relative;transition:transform .2s; }
        .cv-podio-item:hover { transform:translateY(-3px); }
        .cv-podio-item.pos-1 { border-color:rgba(201,162,126,0.8);background:linear-gradient(180deg,#F8F5E7 0%,#FFFFFF 100%);box-shadow:0 0 30px rgba(242,165,26,0.1); }
        .cv-podio-item.pos-2 { border-color:rgba(123,58,34,0.32); }
        .cv-podio-item.pos-3 { border-color:rgba(180,100,40,.2); }
        .cv-podio-medal { font-size:28px;margin-bottom:8px;display:block; }
        .cv-podio-capa { width:56px;height:56px;border-radius:8px;object-fit:cover;margin:0 auto 8px;display:block;background:#F8F0E4;border:2px solid #EADBC6; }
        .cv-podio-item.pos-1 .cv-podio-capa { width:68px;height:68px;border-color:#C9A27E; }
        .cv-podio-title { font-size:12px;font-weight:700;color:#3B2418;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:6px; }
        .cv-podio-item.pos-1 .cv-podio-title { font-size:13px;color:#7B3A22; }
        .cv-podio-stats { font-size:11px;color:#8A6A55; }
        .cv-podio-stats strong { color:#7B3A22; }
        .cv-podio-score { position:absolute;top:10px;right:10px;font-size:10px;background:rgba(242,165,26,0.13);color:#7B3A22;border-radius:4px;padding:2px 6px;font-weight:700; }
        .cv-podio-bar { height:4px;border-radius:4px;margin-top:10px; }
        .pos-1 .cv-podio-bar { background:linear-gradient(90deg,#F2A51A,#F2A51A); }
        .pos-2 .cv-podio-bar { background:linear-gradient(90deg,#C9A27E,#ddd); }
        .pos-3 .cv-podio-bar { background:linear-gradient(90deg,#a0522d,#cd7f32); }
        .cv-rk-tabs { display:flex;gap:4px;margin-bottom:16px;border-bottom:1px solid #EADBC6; }
        .cv-rk-tab { padding:8px 16px;font-size:12px;font-weight:600;color:#8A6A55;cursor:pointer;border-bottom:2px solid transparent;margin-bottom:-1px;transition:color .2s,border-color .2s;background:none;border-top:none;border-left:none;border-right:none; }
        .cv-rk-tab:hover { color:#6B4C3B; }
        .cv-rk-tab.active { color:#7B3A22;border-bottom-color:#C9A27E; }
        .cv-rk-panel { display:none; }
        .cv-rk-panel.active { display:block; }
        .cv-rk-table { width:100%;border-collapse:collapse; }
        .cv-rk-table th { font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#8A6A55;padding:8px 10px;text-align:left;border-bottom:1px solid #EADBC6; }
        .cv-rk-table td { padding:10px;border-bottom:1px solid #EADBC6;vertical-align:middle;font-size:13px; }
        .cv-rk-table tr:hover td { background:rgba(123,58,34,0.03); }
        .cv-rk-table tr:last-child td { border-bottom:none; }
        .cv-rk-pos { font-size:13px;font-weight:800;color:#8A6A55;width:36px;text-align:center; }
        .cv-rk-pos.top3 { color:#7B3A22; }
        .cv-rk-var { font-size:11px;font-weight:700;padding:2px 5px;border-radius:4px;white-space:nowrap; }
        .cv-rk-var.up   { color:#137B38;background:rgba(29,185,84,.1); }
        .cv-rk-var.down { color:#D62C1A;background:rgba(231,76,60,.1); }
        .cv-rk-var.same { color:#8A6A55; }
        .cv-rk-capa { width:36px;height:36px;border-radius:6px;object-fit:cover;background:#F8F0E4;display:block; }
        .cv-rk-bar-bg { height:4px;background:#FFFFFF;border-radius:4px;margin-top:4px;min-width:60px; }
        .cv-rk-bar-fill { height:100%;border-radius:4px;background:linear-gradient(90deg,#F2A51A,#F2A51A); }
        .cv-rk-score-val { font-size:12px;font-weight:700;color:#7B3A22; }
        .cv-rk-stars { color:#7B3A22;font-size:11px; }
        .cv-rk-empty { text-align:center;padding:40px;color:#8A6A55;font-size:13px; }
        </style>

        <div class="cv-rk-hero">
            <div>
                <h1>🏆 Ranking de Músicas</h1>
                <p>Atualizado automaticamente a cada hora<?php if($last_update): ?> — último recálculo: <strong style="color:#7B3A22"><?php echo date('d/m/Y H:i',strtotime($last_update)); ?></strong><?php endif; ?></p>
            </div>
            <div class="cv-rk-hero-actions">
                <div id="cv-action-message" class="cv-action-message" style="display:none;margin:0"></div>
                <button id="cv-btn-recalculate" class="cv-btn cv-btn-primary" style="font-size:12px">🔄 Recalcular Agora</button>
            </div>
        </div>

        <?php if ( ! empty( $top3 ) ) :
        $medalhas   = array('🥇','🥈','🥉');
        $pos_classes= array('pos-1','pos-2','pos-3');
        $ordem      = array(1,0,2);
        ?>
        <div class="cv-podio">
        <?php foreach($ordem as $idx): if(!isset($top3[$idx]))continue; $row=$top3[$idx]; $capa=$row->capa?$row->capa:CV_PLUGIN_URL.'assets/img/default-cover.svg'; ?>
            <div class="cv-podio-item <?php echo $pos_classes[$idx]; ?>">
                <span class="cv-podio-medal"><?php echo $medalhas[$idx]; ?></span>
                <img src="<?php echo esc_url($capa); ?>" alt="" class="cv-podio-capa"/>
                <div class="cv-podio-title" title="<?php echo esc_attr($row->post_title); ?>"><?php echo esc_html($row->post_title); ?></div>
                <div class="cv-podio-stats"><strong><?php echo number_format($row->plays_total); ?></strong> plays · <strong><?php echo number_format($row->favorites); ?></strong> ❤</div>
                <div class="cv-podio-score"><?php echo number_format($row->score,1); ?> pts</div>
                <div class="cv-podio-bar"></div>
            </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="cv-rk-tabs">
            <button class="cv-rk-tab active" data-tab="geral">🌟 Ranking Geral</button>
            <button class="cv-rk-tab" data-tab="7d">📅 Últimos 7 dias</button>
            <button class="cv-rk-tab" data-tab="30d">📆 Últimos 30 dias</button>
        </div>

        <div class="cv-rk-panel active" id="cv-tab-geral">
        <?php if(empty($ranking)): ?>
            <div class="cv-rk-empty">Nenhuma música ranqueada. Clique em "Recalcular Agora".</div>
        <?php else: ?>
        <table class="cv-rk-table">
            <thead><tr>
                <th style="width:36px">#</th><th style="width:46px"></th><th>Música</th>
                <th style="width:60px">Var.</th><th style="text-align:right">Plays</th>
                <th style="text-align:right">7d</th><th style="text-align:right">❤</th>
                <th style="text-align:right">⭐</th><th style="min-width:100px">Score</th>
            </tr></thead>
            <tbody>
            <?php foreach($ranking as $row):
                $var=isset($row->position_change)&&$row->position_change?intval($row->position_change):0;
                $pct=$max_score>0?round(($row->score/$max_score)*100):0;
                $capa=$row->capa?$row->capa:CV_PLUGIN_URL.'assets/img/default-cover.svg';
            ?>
            <tr>
                <td class="cv-rk-pos <?php echo $row->position<=3?'top3':''; ?>"><?php echo intval($row->position); ?></td>
                <td><img src="<?php echo esc_url($capa); ?>" alt="" class="cv-rk-capa"/></td>
                <td><a href="<?php echo admin_url('post.php?post='.$row->music_id.'&action=edit'); ?>" style="color:#3B2418;text-decoration:none;font-weight:600" onmouseover="this.style.color='#7B3A22'" onmouseout="this.style.color='#3B2418'"><?php echo esc_html($row->post_title); ?></a></td>
                <td><?php if($var>0):?><span class="cv-rk-var up">↑<?php echo $var;?></span><?php elseif($var<0):?><span class="cv-rk-var down">↓<?php echo abs($var);?></span><?php else:?><span class="cv-rk-var same">—</span><?php endif;?></td>
                <td style="text-align:right;color:#6B4C3B"><?php echo number_format($row->plays_total);?></td>
                <td style="text-align:right;color:#8A6A55;font-size:12px"><?php echo number_format($row->plays_7d);?></td>
                <td style="text-align:right;color:#8A6A55;font-size:12px"><?php echo number_format($row->favorites);?></td>
                <td style="text-align:right"><span class="cv-rk-stars"><?php echo number_format($row->avg_rating,1);?>★</span></td>
                <td><div class="cv-rk-score-val"><?php echo number_format($row->score,1);?></div><div class="cv-rk-bar-bg"><div class="cv-rk-bar-fill" style="width:<?php echo $pct;?>%"></div></div></td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php endif;?>
        </div>

        <div class="cv-rk-panel" id="cv-tab-7d">
        <?php if(empty($ranking_7d)):?><div class="cv-rk-empty">Nenhum play nos últimos 7 dias.</div>
        <?php else:?>
        <table class="cv-rk-table">
            <thead><tr><th>#</th><th>Música</th><th style="text-align:right">Plays (7d)</th></tr></thead>
            <tbody>
            <?php foreach($ranking_7d as $i=>$row):
                $titulo=$wpdb->get_var($wpdb->prepare("SELECT post_title FROM {$wpdb->posts} WHERE ID=%d",$row->music_id));
            ?>
            <tr>
                <td class="cv-rk-pos <?php echo $i<3?'top3':'';?>"><?php echo $i+1;?></td>
                <td style="color:#3B2418;font-weight:600"><?php echo esc_html($titulo);?></td>
                <td style="text-align:right;color:#7B3A22;font-weight:700"><?php echo number_format($row->plays_periodo);?></td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php endif;?>
        </div>

        <div class="cv-rk-panel" id="cv-tab-30d">
        <?php if(empty($ranking_30d)):?><div class="cv-rk-empty">Nenhum play nos últimos 30 dias.</div>
        <?php else:?>
        <table class="cv-rk-table">
            <thead><tr><th>#</th><th>Música</th><th style="text-align:right">Plays (30d)</th></tr></thead>
            <tbody>
            <?php foreach($ranking_30d as $i=>$row):?>
            <tr>
                <td class="cv-rk-pos <?php echo $i<3?'top3':'';?>"><?php echo $i+1;?></td>
                <td style="color:#3B2418;font-weight:600"><?php echo esc_html($row->post_title);?></td>
                <td style="text-align:right;color:#7B3A22;font-weight:700"><?php echo number_format($row->plays_periodo);?></td>
            </tr>
            <?php endforeach;?>
            </tbody>
        </table>
        <?php endif;?>
        </div>

        <script>
        jQuery(function($){
            $('.cv-rk-tab').on('click',function(){ $('.cv-rk-tab').removeClass('active'); $('.cv-rk-panel').removeClass('active'); $(this).addClass('active'); $('#cv-tab-'+$(this).data('tab')).addClass('active'); });
            var nonce=(typeof cvAdmin!=='undefined')?cvAdmin.nonce:'';
            var ajaxUrl=(typeof cvAdmin!=='undefined')?cvAdmin.ajaxUrl:ajaxurl;
            $('#cv-btn-recalculate').on('click',function(){
                var $b=$(this).prop('disabled',true).text('Recalculando...');
                $.post(ajaxUrl,{action:'cv_recalculate_ranking',nonce:nonce},function(r){
                    var $m=$('#cv-action-message');
                    $m.removeClass('cv-notice-success cv-notice-error').addClass('cv-notice '+(r.success?'cv-notice-success':'cv-notice-error')).text(r.success?'✅ Recalculado!':'❌ Erro.').show();
                    setTimeout(function(){$m.fadeOut();location.reload();},1500);
                    $b.prop('disabled',false).text('🔄 Recalcular Agora');
                });
            });
        });
        </script>
        </div>
        <?php
    }

    public static function page_subscribers() {
        global $wpdb;

        // Export CSV direto
        if ( isset( $_GET['cv_export_sub'] ) && check_admin_referer( 'cv_export_sub' ) ) {
            $all   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_subscribers ORDER BY subscribed_at DESC" );
            $lines = array( implode(';', array('ID','Nome','E-mail','Gênero','Data')) );
            foreach ( $all as $s ) {
                $lines[] = implode(';', array($s->id, '"'.str_replace('"','""',$s->name).'"', $s->email, $s->genre?:'Geral', date('d/m/Y H:i',strtotime($s->subscribed_at))));
            }
            $csv = "ï»¿" . implode("
", $lines);
            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="cv-assinantes-'.date('Y-m-d').'.csv"');
            echo $csv; exit;
        }

        // Dados principais
        $search      = sanitize_text_field( $_GET['cv_sub_search'] ?? '' );
        $where       = $search ? $wpdb->prepare("WHERE (s.email LIKE %s OR s.name LIKE %s)",
                            '%'.$wpdb->esc_like($search).'%', '%'.$wpdb->esc_like($search).'%') : '';
        $table       = $wpdb->prefix . 'cv_subscribers';
        $table_news  = $wpdb->prefix . 'cv_newsletter';

        // Usa cv_newsletter se cv_subscribers vazio
        $use_table   = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table ? $table : $table_news;

        $subscribers = $wpdb->get_results("SELECT * FROM {$use_table} {$where} ORDER BY subscribed_at DESC LIMIT 300");
        $total       = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$use_table}");
        $total_7d    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$use_table} WHERE subscribed_at >= NOW() - INTERVAL 7 DAY");
        $total_30d   = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$use_table} WHERE subscribed_at >= NOW() - INTERVAL 30 DAY");

        // Crescimento diário últimos 30 dias (para gráfico)
        $crescimento = $wpdb->get_results(
            "SELECT DATE(subscribed_at) AS dia, COUNT(*) AS total
             FROM {$use_table}
             WHERE subscribed_at >= NOW() - INTERVAL 30 DAY
             GROUP BY DATE(subscribed_at) ORDER BY dia ASC"
        );
        $chart_labels = array(); $chart_values = array(); $by_day = array();
        foreach ($crescimento as $r) { $by_day[$r->dia] = (int)$r->total; }
        $acumulado = $total - $total_30d;
        $chart_acum = array();
        for ($i=29;$i>=0;$i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $chart_labels[] = date('d/m', strtotime($day));
            $acumulado += isset($by_day[$day]) ? $by_day[$day] : 0;
            $chart_values[] = $acumulado;
        }

        // Por gênero
        $por_genero = $wpdb->get_results(
            "SELECT COALESCE(NULLIF(genre,''),'Geral') AS genre, COUNT(*) AS total
             FROM {$use_table} GROUP BY genre ORDER BY total DESC LIMIT 6"
        );
        $taxa_crescimento = $total_30d > 0 ? round(($total_7d / max(1,$total_30d/4.3))*100 - 100) : 0;
        ?>
        <div class="cv-admin-wrap cv-subs-v2" style="max-width:1100px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <?php echo CV_Admin::btn_voltar(); ?>
        
        <style>
        .cv-subs-v2 * { box-sizing:border-box; }
        .cv-subs-hero { background:linear-gradient(135deg,#E7EEF8 0%,#FFFFFF 100%);border:1px solid #EADBC6;border-radius:14px;padding:22px 28px;margin-bottom:22px;display:flex;align-items:center;gap:20px; }
        .cv-subs-hero h1 { color:#2871BE;font-size:20px;margin:0 0 2px;font-weight:800; }
        .cv-subs-hero p { color:#8A6A55;font-size:12px;margin:0; }
        .cv-subs-kpis { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px; }
        @media(max-width:800px){.cv-subs-kpis{grid-template-columns:1fr 1fr;}}
        .cv-subs-kpi { background:#FFFFFF;border:1px solid #EADBC6;border-radius:12px;padding:18px 16px;position:relative;overflow:hidden; }
        .cv-subs-kpi::after { content:"";position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 12px 12px; }
        .cv-subs-kpi.k1::after{background:linear-gradient(90deg,#4a90d9,#5aa8f0);}
        .cv-subs-kpi.k2::after{background:linear-gradient(90deg,#1db954,#22d460);}
        .cv-subs-kpi.k3::after{background:linear-gradient(90deg,#F2A51A,#F2A51A);}
        .cv-subs-kpi.k4::after{background:linear-gradient(90deg,#9b59b6,#b278cc);}
        .cv-subs-kpi-icon { font-size:18px;margin-bottom:6px;display:block; }
        .cv-subs-kpi-val { font-size:28px;font-weight:800;color:#3B2418;line-height:1;margin-bottom:3px; }
        .cv-subs-kpi-label { font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.4px; }
        .cv-subs-kpi-badge { position:absolute;top:12px;right:12px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px; }
        .badge-up{background:rgba(29,185,84,.15);color:#137B38;}
        .badge-neu{background:rgba(123,58,34,0.06);color:#8A6A55;}
        .cv-subs-mid { display:grid;grid-template-columns:1.6fr 1fr;gap:18px;margin-bottom:22px;align-items:start; }
        @media(max-width:900px){.cv-subs-mid{grid-template-columns:1fr;}}
        .cv-subs-panel { background:#FFFFFF;border:1px solid #EADBC6;border-radius:12px;padding:20px 22px;max-height:260px;overflow-y:auto; }
        .cv-subs-panel h3 { font-size:12px;color:#8A6A55;text-transform:uppercase;letter-spacing:.6px;margin:0 0 14px;font-weight:600; }
        .cv-subs-panel h3 strong { color:#2871BE; }
        #cv-subs-chart { width:100%;height:110px;max-height:110px; }
        .cv-genre-bar { margin-bottom:12px; }
        .cv-genre-bar:last-child { margin-bottom:0; }
        .cv-genre-header { display:flex;justify-content:space-between;font-size:12px;margin-bottom:4px; }
        .cv-genre-name { color:#8A6A55; }
        .cv-genre-val { color:#2871BE;font-weight:700; }
        .cv-genre-bg { height:5px;background:#FBF6EE;border-radius:5px; }
        .cv-genre-fill { height:100%;border-radius:5px;background:linear-gradient(90deg,#4a90d9,#5aa8f0); }
        /* Tabela */
        .cv-subs-toolbar { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;margin-bottom:14px; }
        .cv-subs-search { display:flex;gap:8px;align-items:center; }
        .cv-subs-search input { background:#FBF6EE;border:1px solid #EADBC6;color:#3B2418;border-radius:6px;padding:7px 12px;font-size:12px;outline:none;min-width:220px; }
        .cv-subs-search input:focus { border-color:#4a90d9; }
        .cv-subs-actions { display:flex;gap:8px; }
        .cv-sub-btn { font-size:11px;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:600;border:1px solid #EADBC6;background:#FBF6EE;color:#8A6A55;transition:all .2s; }
        .cv-sub-btn:hover { border-color:#4a90d9;color:#2871BE; }
        .cv-sub-btn.primary { background:#4a90d9;color:#3B2418;border-color:#4a90d9; }
        .cv-sub-btn.primary:hover { background:#5aa8f0; }
        .cv-subs-table { width:100%;border-collapse:collapse; }
        .cv-subs-table th { font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#8A6A55;padding:8px 10px;text-align:left;border-bottom:1px solid #EADBC6; }
        .cv-subs-table td { padding:9px 10px;border-bottom:1px solid #EADBC6;vertical-align:middle;font-size:13px; }
        .cv-subs-table tr:hover td { background:rgba(74,144,217,.04); }
        .cv-subs-table tr:last-child td { border-bottom:none; }
        .cv-subs-avatar { width:30px;height:30px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;color:#3B2418;flex-shrink:0; }
        .cv-subs-name { display:flex;align-items:center;gap:8px; }
        .cv-genre-pill { font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(74,144,217,.12);color:#2871BE; }
        .cv-subs-empty { text-align:center;padding:48px;color:#8A6A55; }
        </style>

        <!-- Hero -->
        <div class="cv-subs-hero">
            <div style="font-size:40px;filter:drop-shadow(0 0 12px rgba(74,144,217,.3))">📧</div>
            <div>
                <h1>Central de Newsletter</h1>
                <p>Gerencie assinantes, visualize crescimento e exporte listas para o MailerLite.</p>
            </div>
            <div style="margin-left:auto;text-align:right">
                <div style="font-size:28px;font-weight:800;color:#2871BE;line-height:1"><?php echo number_format($total); ?></div>
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.4px">assinantes totais</div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="cv-subs-kpis">
            <div class="cv-subs-kpi k1">
                <span class="cv-subs-kpi-icon">👥</span>
                <div class="cv-subs-kpi-val" data-target="<?php echo $total; ?>">0</div>
                <div class="cv-subs-kpi-label">Total de assinantes</div>
            </div>
            <div class="cv-subs-kpi k2">
                <?php if($total_7d>0):?><span class="cv-subs-kpi-badge badge-up">+<?php echo $total_7d;?> esta semana</span><?php endif;?>
                <span class="cv-subs-kpi-icon">📅</span>
                <div class="cv-subs-kpi-val" data-target="<?php echo $total_7d; ?>">0</div>
                <div class="cv-subs-kpi-label">Novos (7 dias)</div>
            </div>
            <div class="cv-subs-kpi k3">
                <span class="cv-subs-kpi-icon">📆</span>
                <div class="cv-subs-kpi-val" data-target="<?php echo $total_30d; ?>">0</div>
                <div class="cv-subs-kpi-label">Novos (30 dias)</div>
            </div>
            <div class="cv-subs-kpi k4">
                <span class="cv-subs-kpi-badge <?php echo $taxa_crescimento>=0?'badge-up':'badge-neu'; ?>"><?php echo ($taxa_crescimento>=0?'+':'').$taxa_crescimento; ?>%</span>
                <span class="cv-subs-kpi-icon">📈</span>
                <div class="cv-subs-kpi-val"><?php echo count($por_genero); ?></div>
                <div class="cv-subs-kpi-label">Gêneros favoritos</div>
            </div>
        </div>

        <!-- Gráfico + Por gênero -->
        <div class="cv-subs-mid">
            <div class="cv-subs-panel">
                <h3>Crescimento acumulado — <strong>últimos 30 dias</strong></h3>
                <canvas id="cv-subs-chart"></canvas>
            </div>
            <div class="cv-subs-panel">
                <h3>Assinantes por <strong>gênero favorito</strong></h3>
                <?php if(empty($por_genero)):?>
                    <p style="color:#8A6A55;font-size:12px">Nenhum dado de gênero disponível.</p>
                <?php else:
                $max_g = max(array_column((array)$por_genero,'total'));
                $max_g = max(1,$max_g);
                foreach($por_genero as $g): $pct=round(($g->total/$max_g)*100); ?>
                <div class="cv-genre-bar">
                    <div class="cv-genre-header">
                        <span class="cv-genre-name"><?php echo esc_html($g->genre);?></span>
                        <span class="cv-genre-val"><?php echo number_format($g->total);?></span>
                    </div>
                    <div class="cv-genre-bg"><div class="cv-genre-fill" style="width:<?php echo $pct;?>%"></div></div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Tabela de assinantes -->
        <div class="cv-subs-panel" style="margin-bottom:0">
            <div class="cv-subs-toolbar">
                <form method="get" class="cv-subs-search">
                    <input type="hidden" name="page" value="cv-subscribers"/>
                    <input type="text" name="cv_sub_search" value="<?php echo esc_attr($search);?>" placeholder="Buscar por nome ou e-mail..."/>
                    <button type="submit" class="cv-sub-btn primary">🔍 Buscar</button>
                    <?php if($search):?><a href="<?php echo admin_url('admin.php?page=cv-subscribers');?>" class="cv-sub-btn">Limpar</a><?php endif;?>
                </form>
                <div class="cv-subs-actions">
                    <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=cv-subscribers&cv_export_sub=1'),'cv_export_sub'));?>" class="cv-sub-btn">⬇ CSV</a>
                    <button class="cv-sub-btn" onclick="var t=document.querySelectorAll('.cv-sub-email');var l=Array.from(t).map(function(e){return e.textContent;}).join('
');navigator.clipboard.writeText(l).then(function(){alert('✅ '+t.length+' e-mails copiados!');});">📋 Copiar e-mails</button>
                </div>
            </div>

            <?php if(empty($subscribers)):?>
            <div class="cv-subs-empty">
                <div style="font-size:36px;margin-bottom:10px">📭</div>
                <p style="color:#8A6A55"><?php echo $search?'Nenhum resultado para "'.esc_html($search).'".':'Nenhum assinante ainda. O formulário de newsletter está ativo no site!';?></p>
            </div>
            <?php else:?>
            <?php
            $cores = array('#4a90d9','#1db954','#B8700C','#9b59b6','#e74c3c','#e67e22','#1abc9c','#e91e63');
            ?>
            <table class="cv-subs-table">
                <thead><tr>
                    <th style="width:42px">#</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Gênero</th>
                    <th>Cadastro</th>
                    <th style="width:40px"></th>
                </tr></thead>
                <tbody>
                <?php foreach($subscribers as $i=>$sub):
                    $inicial = strtoupper(mb_substr($sub->name?:$sub->email, 0, 1));
                    $cor     = $cores[abs(crc32($sub->email)) % count($cores)];
                    $dias    = round((time()-strtotime($sub->subscribed_at))/86400);
                    $tempo   = $dias===0?'hoje':($dias===1?'ontem':$dias.' dias atrás');
                ?>
                <tr id="cv-sub-row-<?php echo esc_attr($sub->id);?>">
                    <td style="color:#8A6A55;font-size:11px"><?php echo $i+1;?></td>
                    <td>
                        <div class="cv-subs-name">
                            <div class="cv-subs-avatar" style="background:<?php echo $cor;?>1a;color:<?php echo $cor;?>;border:1px solid <?php echo $cor;?>33"><?php echo esc_html($inicial);?></div>
                            <span style="color:#3B2418;font-weight:600"><?php echo esc_html($sub->name?:$sub->email);?></span>
                        </div>
                    </td>
                    <td><span class="cv-sub-email" style="color:#8A6A55;font-size:12px"><?php echo esc_html($sub->email);?></span></td>
                    <td><?php if($sub->genre??''):?><span class="cv-genre-pill"><?php echo esc_html($sub->genre);?></span><?php else:?><span style="color:#8A6A55;font-size:11px">—</span><?php endif;?></td>
                    <td style="font-size:11px;color:#8A6A55"><?php echo esc_html($tempo);?></td>
                    <td>
                        <button class="cv-sub-delete" data-id="<?php echo esc_attr($sub->id);?>" data-email="<?php echo esc_attr($sub->email);?>"
                                style="background:none;border:none;color:#8A6A55;cursor:pointer;font-size:14px;padding:4px" title="Remover">🗑</button>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            <?php if(count($subscribers)>=300):?>
            <p style="text-align:center;color:#8A6A55;font-size:11px;margin-top:12px;padding-top:12px;border-top:1px solid #EADBC6">
                Exibindo 300 mais recentes. Exporte o CSV para ver todos.
            </p>
            <?php endif;?>
            <?php endif;?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function(){
            // Animar KPIs
            document.querySelectorAll('.cv-subs-kpi-val[data-target]').forEach(function(el){
                var t=parseInt(el.dataset.target,10)||0, s=Math.ceil(t/40), c=0;
                var tm=setInterval(function(){ c=Math.min(c+s,t); el.textContent=c.toLocaleString('pt-BR'); if(c>=t)clearInterval(tm); },30);
            });
            // Gráfico de crescimento
            var ctx=document.getElementById('cv-subs-chart');
            if(ctx){ new Chart(ctx,{
                type:'line',
                data:{ labels:<?php echo json_encode($chart_labels);?>, datasets:[{
                    label:'Assinantes acumulados',
                    data:<?php echo json_encode($chart_values);?>,
                    borderColor:'#4a90d9', backgroundColor:'rgba(74,144,217,0.08)',
                    borderWidth:2, pointRadius:2, pointHoverRadius:5,
                    pointBackgroundColor:'#4a90d9', fill:true, tension:0.4
                }]},
                options:{ responsive:true, maintainAspectRatio:false,
                    plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#FFFFFF',borderColor:'#F8F0E4',borderWidth:1,titleColor:'#4a90d9',bodyColor:'#C9A27E'}},
                    scales:{
                        x:{ grid:{color:'rgba(123,58,34,0.03)'}, ticks:{color:'#F3E6D3',font:{size:10},maxTicksLimit:10} },
                        y:{ grid:{color:'rgba(123,58,34,0.03)'}, ticks:{color:'#F3E6D3',font:{size:10},precision:0}, beginAtZero:false }
                    }
                }
            }); }
        })();
        jQuery(function($){
            var nonce='<?php echo esc_js(wp_create_nonce("cv_admin_nonce"));?>';
            $(document).on('click','.cv-sub-delete',function(){
                var id=$(this).data('id'), email=$(this).data('email');
                if(!confirm('Remover assinante '+email+'?')) return;
                var $row=$('#cv-sub-row-'+id);
                $.post('<?php echo esc_js(admin_url("admin-ajax.php"));?>',
                    {action:'cv_admin_delete_subscriber',nonce:nonce,sub_id:id},
                    function(r){ if(r.success){$row.fadeOut(300,function(){$(this).remove();});} else{alert('Erro ao remover.');} }
                );
            });
        });
        </script>
        </div>
        <?php
    }

        // ─── APARENCIA ────────────────────────────────────────────────
    // v1.4: wp_enqueue_media() chamado aqui para garantir que a
    // Media Library do WordPress carregue nesta pagina admin.
    public static function page_appearance() {
        wp_enqueue_media();

        if ( isset( $_POST['cv_save_appearance'] ) && check_admin_referer( 'cv_appearance_save' ) ) {
            if ( isset( $_POST['cv_logo_url'] ) ) {
                update_option( 'cv_logo_url', esc_url_raw( $_POST['cv_logo_url'] ) );
            }
            if ( isset( $_POST['cv_banner_url'] ) ) {
                update_option( 'cv_banner_url', esc_url_raw( $_POST['cv_banner_url'] ) );
            }
            $saved = true;
        } else {
            $saved = false;
        }

        $logo_url   = get_option( 'cv_logo_url',   '' );
        $banner_url = get_option( 'cv_banner_url', '' );
        $has_logo   = ! empty( $logo_url );
        $has_banner = ! empty( $banner_url );
        ?>
        <div class="wrap" id="cv-appearance-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-appearance-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --bord:#F3E6D3;
            --text:#3B2418; --muted:#C9A27E; --green:#1DB954;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif;
            padding-bottom:48px;
        }
        #cv-appearance-exec * { box-sizing:border-box; }
        .cv-ap-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-ap-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-ap-title span { color:var(--gold); }
        .cv-ap-grid { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
        @media (max-width:900px) { .cv-ap-grid { grid-template-columns:1fr; } }
        .cv-ap-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-ap-card-header { padding:16px 20px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:12px; }
        .cv-ap-card-icon { font-size:22px; }
        .cv-ap-card-title { font-size:15px; font-weight:700; color:#3B2418; }
        .cv-ap-card-sub { font-size:11px; color:var(--muted); margin-top:2px; }
        .cv-ap-card-body { padding:20px; }
        /* Preview logo */
        .cv-ap-logo-preview {
            background:linear-gradient(135deg,#FBF6EE,#FFFFFF);
            border:1px solid var(--bord); border-radius:10px;
            min-height:100px; display:flex; align-items:center; justify-content:center;
            margin-bottom:16px; padding:20px; position:relative; overflow:hidden;
        }
        .cv-ap-logo-preview::before {
            content:'Prévia do cabeçalho';
            position:absolute; top:8px; left:12px;
            font-size:9px; color:#8A6A55; text-transform:uppercase; letter-spacing:.5px;
        }
        .cv-ap-logo-preview img { max-width:240px; max-height:70px; object-fit:contain; }
        .cv-ap-logo-placeholder { color:#8A6A55; font-size:12px; text-align:center; }
        /* Preview banner */
        .cv-ap-banner-preview {
            border:1px solid var(--bord); border-radius:10px;
            height:140px; overflow:hidden; position:relative; margin-bottom:16px;
            background:#FBF6EE;
        }
        .cv-ap-banner-preview::before {
            content:'Prévia do banner home';
            position:absolute; top:8px; left:12px; z-index:2;
            font-size:9px; color:rgba(59,36,24,0.55); text-transform:uppercase; letter-spacing:.5px;
        }
        .cv-ap-banner-img {
            width:100%; height:100%; object-fit:cover;
            transition:transform .4s ease; transform:scale(1.05);
        }
        .cv-ap-banner-img:hover { transform:scale(1); }
        .cv-ap-banner-overlay {
            position:absolute; inset:0; z-index:1;
            background:linear-gradient(to bottom, transparent 40%, rgba(59,36,24,0.45) 100%);
            display:flex; align-items:flex-end; padding:12px 14px;
        }
        .cv-ap-banner-label { font-size:11px; color:rgba(59,36,24,0.6); }
        .cv-ap-banner-placeholder {
            display:flex; align-items:center; justify-content:center;
            height:100%; color:#8A6A55; font-size:12px; flex-direction:column; gap:8px;
        }
        /* Input area */
        .cv-ap-field { margin-bottom:14px; }
        .cv-ap-field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; }
        .cv-ap-input-row { display:flex; gap:8px; }
        .cv-ap-input { flex:1; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-ap-input:focus { border-color:var(--gold); }
        .cv-ap-btn-select { background:rgba(242,165,26,0.16); border:1px solid rgba(201,162,126,0.6); color:var(--gold); padding:9px 14px; border-radius:8px; cursor:pointer; font-size:12px; font-weight:600; white-space:nowrap; transition:background .2s; font-family:inherit; }
        .cv-ap-btn-select:hover { background:rgba(242,165,26,0.29); }
        .cv-ap-hint { font-size:11px; color:var(--muted); margin-top:4px; display:flex; align-items:center; gap:8px; }
        .cv-ap-badge { font-size:10px; padding:2px 7px; border-radius:10px; font-weight:600; }
        .cv-ap-badge.ok  { background:rgba(29,185,84,.12); color:var(--green); border:1px solid rgba(29,185,84,.3); }
        .cv-ap-badge.off { background:rgba(123,58,34,0.11); color:var(--muted); border:1px solid var(--bord); }
        /* Status geral */
        .cv-ap-status-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
        .cv-ap-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .cv-ap-pill.ok  { background:rgba(29,185,84,.1); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-ap-pill.off { background:rgba(123,58,34,0.09); border:1px solid var(--bord); color:var(--muted); }
        /* Save bar */
        .cv-ap-save-bar { margin-top:22px; background:var(--card); border:1px solid var(--bord); border-radius:14px; padding:16px 20px; display:flex; align-items:center; gap:12px; }
        .cv-ap-btn-save { background:var(--gold); color:#3B2418; border:none; padding:11px 28px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; transition:opacity .2s; font-family:inherit; }
        .cv-ap-btn-save:hover { opacity:.85; }
        .cv-ap-saved { color:var(--green); font-size:13px; <?php echo $saved ? '' : 'display:none'; ?> }
        /* Dica de dimensão */
        .cv-ap-dim { display:inline-flex; align-items:center; gap:4px; font-size:10px; color:#8A6A55; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:6px; padding:3px 8px; }
        </style>

        <div class="cv-ap-topbar">
            <h1 class="cv-ap-title">🎨 <span>Aparência</span></h1>
            <div class="cv-ap-status-row">
                <span class="cv-ap-pill <?php echo $has_logo ? 'ok' : 'off'; ?>">
                    <?php echo $has_logo ? '✅ Logo definido' : '⚪ Sem logo'; ?>
                </span>
                <span class="cv-ap-pill <?php echo $has_banner ? 'ok' : 'off'; ?>">
                    <?php echo $has_banner ? '✅ Banner definido' : '⚪ Sem banner'; ?>
                </span>
            </div>
        </div>

        <?php echo CV_Admin::btn_voltar(); ?>

        <form method="post" id="cv-ap-form">
            <?php wp_nonce_field( 'cv_appearance_save' ); ?>
            <input type="hidden" name="cv_save_appearance" value="1" />

            <div class="cv-ap-grid">

                <!-- Logo -->
                <div class="cv-ap-card">
                    <div class="cv-ap-card-header">
                        <span class="cv-ap-card-icon">🖼️</span>
                        <div>
                            <div class="cv-ap-card-title">Logo do Site</div>
                            <div class="cv-ap-card-sub">Exibido no cabeçalho e sidebar</div>
                        </div>
                    </div>
                    <div class="cv-ap-card-body">
                        <!-- Preview -->
                        <div class="cv-ap-logo-preview" id="cv-logo-preview-box">
                            <?php if ( $has_logo ) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" id="cv-logo-preview-img" />
                            <?php else : ?>
                            <div class="cv-ap-logo-placeholder" id="cv-logo-preview-img">
                                <span style="font-size:32px">🎵</span>
                                <span>Nenhum logo definido</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Input -->
                        <div class="cv-ap-field">
                            <label>URL da imagem</label>
                            <div class="cv-ap-input-row">
                                <input type="text" id="cv_logo_url" name="cv_logo_url"
                                       value="<?php echo esc_url($logo_url); ?>"
                                       class="cv-ap-input" placeholder="https://..." />
                                <button type="button" class="cv-ap-btn-select cv-media-btn" data-target="cv_logo_url" data-preview="cv-logo-preview">
                                    📁 Selecionar
                                </button>
                            </div>
                            <div class="cv-ap-hint">
                                <span class="cv-ap-dim">📐 Recomendado: 300×120px</span>
                                <span class="cv-ap-dim">🎨 PNG transparente</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banner -->
                <div class="cv-ap-card">
                    <div class="cv-ap-card-header">
                        <span class="cv-ap-card-icon">🖼️</span>
                        <div>
                            <div class="cv-ap-card-title">Banner da Home</div>
                            <div class="cv-ap-card-sub">Fundo do hero com efeito parallax</div>
                        </div>
                    </div>
                    <div class="cv-ap-card-body">
                        <!-- Preview -->
                        <div class="cv-ap-banner-preview">
                            <?php if ( $has_banner ) : ?>
                            <img src="<?php echo esc_url($banner_url); ?>" alt="Banner"
                                 class="cv-ap-banner-img" id="cv-banner-preview-img" />
                            <div class="cv-ap-banner-overlay">
                                <span class="cv-ap-banner-label">✨ Canção Verdadeira — banner ativo</span>
                            </div>
                            <?php else : ?>
                            <div class="cv-ap-banner-placeholder" id="cv-banner-placeholder">
                                <span style="font-size:28px">🌅</span>
                                <span>Nenhum banner definido</span>
                                <span style="font-size:10px;color:#8A6A55">Aparecerá um gradiente padrão no site</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Input -->
                        <div class="cv-ap-field">
                            <label>URL da imagem</label>
                            <div class="cv-ap-input-row">
                                <input type="text" id="cv_banner_url" name="cv_banner_url"
                                       value="<?php echo esc_url($banner_url); ?>"
                                       class="cv-ap-input" placeholder="https://..." />
                                <button type="button" class="cv-ap-btn-select cv-media-btn" data-target="cv_banner_url" data-preview="cv-banner-preview">
                                    📁 Selecionar
                                </button>
                            </div>
                            <div class="cv-ap-hint">
                                <span class="cv-ap-dim">📐 Recomendado: 1920×600px</span>
                                <span class="cv-ap-dim">📸 JPG ou WebP</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- grid -->

            <!-- Save bar -->
            <div class="cv-ap-save-bar">
                <button type="submit" class="cv-ap-btn-save">💾 Salvar Aparência</button>
                <span class="cv-ap-saved">✅ Aparência salva com sucesso!</span>
                <span style="font-size:12px;color:var(--muted);margin-left:auto">
                    As alterações aparecem no site imediatamente após salvar.
                </span>
            </div>
        </form>
        </div><!-- wrap -->

        <script>
        jQuery(function($){
            // Media uploader com preview em tempo real
            $('body').on('click', '.cv-media-btn', function(){
                var targetId  = $(this).data('target');
                var previewKey = $(this).data('preview');
                var frame = wp.media({
                    title: 'Selecionar Imagem',
                    button: { text: 'Usar esta imagem' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.url;
                    $('#' + targetId).val(url);
                    // Preview logo
                    if (previewKey === 'cv-logo-preview') {
                        var box = $('#cv-logo-preview-box');
                        box.find('.cv-ap-logo-placeholder').remove();
                        var img = box.find('img');
                        if (img.length) { img.attr('src', url); }
                        else { box.html('<img src="' + url + '" id="cv-logo-preview-img" style="max-width:240px;max-height:70px;object-fit:contain" />'); }
                    }
                    // Preview banner
                    if (previewKey === 'cv-banner-preview') {
                        var bimg = $('#cv-banner-preview-img');
                        var bph  = $('#cv-banner-placeholder');
                        if (bimg.length) { bimg.attr('src', url); }
                        else {
                            bph.replaceWith('<img src="' + url + '" id="cv-banner-preview-img" class="cv-ap-banner-img" />');
                        }
                    }
                });
                frame.open();
            });
        });
        </script>
        <?php
    }

    public static function page_settings() {
        // Salvar
        if ( isset( $_POST['cv_save_settings'] ) && check_admin_referer( 'cv_settings_save' ) ) {
            update_option( 'cv_mailerlite_api_key',   sanitize_text_field( isset($_POST['cv_mailerlite_api_key'])   ? $_POST['cv_mailerlite_api_key']   : '' ) );
            update_option( 'cv_mailerlite_group_id',  sanitize_text_field( isset($_POST['cv_mailerlite_group_id'])  ? $_POST['cv_mailerlite_group_id']  : '' ) );
            update_option( 'cv_whatsapp_number',      sanitize_text_field( isset($_POST['cv_whatsapp_number'])      ? $_POST['cv_whatsapp_number']      : '' ) );
            update_option( 'cv_whatsapp_message',     sanitize_textarea_field( isset($_POST['cv_whatsapp_message']) ? $_POST['cv_whatsapp_message']     : '' ) );
            update_option( 'cv_whatsapp_tooltip',     sanitize_text_field( isset($_POST['cv_whatsapp_tooltip'])     ? $_POST['cv_whatsapp_tooltip']     : '' ) );
            update_option( 'cv_whatsapp_pulse_delay', absint( isset($_POST['cv_whatsapp_pulse_delay']) ? $_POST['cv_whatsapp_pulse_delay'] : 3 ) );
            update_option( 'cv_play_seconds',         absint( isset($_POST['cv_play_seconds'])         ? $_POST['cv_play_seconds']         : 30 ) );
            $saved = true;
        } else {
            $saved = false;
        }

        $api_key      = get_option( 'cv_mailerlite_api_key',   '' );
        $group_id     = get_option( 'cv_mailerlite_group_id',  '' );
        $whatsapp     = get_option( 'cv_whatsapp_number',      '' );
        $wa_message   = get_option( 'cv_whatsapp_message',     'Olá! Vim do Canção Verdadeira e gostaria de saber mais.' );
        $wa_tooltip   = get_option( 'cv_whatsapp_tooltip',     'Fale conosco no WhatsApp!' );
        $wa_delay     = (int) get_option( 'cv_whatsapp_pulse_delay', 3 );
        $play_secs    = (int) get_option( 'cv_play_seconds', 30 );
        $api_ok       = ! empty($api_key);
        $wa_ok        = ! empty($whatsapp);
        ?>
        <div class="wrap" id="cv-settings-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-settings-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --card2:#F8F0E4;
            --bord:#F3E6D3; --text:#3B2418; --muted:#C9A27E; --green:#1DB954; --red:#e74c3c;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:48px;
        }
        #cv-settings-exec * { box-sizing:border-box; }
        .cv-set-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-set-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-set-title span { color:var(--gold); }
        .cv-set-status { display:flex; gap:10px; flex-wrap:wrap; }
        .cv-set-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .cv-set-pill.ok  { background:rgba(29,185,84,.12); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-set-pill.off { background:rgba(231,76,60,.1); border:1px solid rgba(231,76,60,.3); color:var(--red); }
        .cv-set-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media (max-width:900px) { .cv-set-grid { grid-template-columns:1fr; } }
        .cv-set-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-set-card-header { padding:16px 20px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:12px; }
        .cv-set-card-icon { font-size:22px; }
        .cv-set-card-title { font-size:15px; font-weight:700; color:#3B2418; }
        .cv-set-card-subtitle { font-size:11px; color:var(--muted); margin-top:2px; }
        .cv-set-card-body { padding:20px; display:flex; flex-direction:column; gap:16px; }
        .cv-set-field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .cv-set-input { width:100%; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-set-input:focus { border-color:var(--gold); }
        .cv-set-input::placeholder { color:#8A6A55; }
        .cv-set-hint { font-size:11px; color:var(--muted); margin-top:4px; }
        .cv-set-status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }
        .cv-set-status-dot.ok { background:var(--green); }
        .cv-set-status-dot.off { background:var(--red); }
        /* Preview WhatsApp */
        .cv-wa-preview { background:#E7F8F6; border-radius:12px; padding:14px 16px; margin-top:8px; }
        .cv-wa-preview-label { font-size:10px; color:rgba(59,36,24,0.55); margin-bottom:6px; text-transform:uppercase; letter-spacing:.5px; }
        .cv-wa-bubble { background:#dcf8c6; color:#3B2418; border-radius:8px 8px 0 8px; padding:8px 12px; font-size:12px; max-width:80%; margin-left:auto; word-break:break-word; }
        .cv-wa-bubble-time { font-size:10px; color:#8A6A55; text-align:right; margin-top:3px; }
        /* Slider visual */
        .cv-set-slider-wrap { display:flex; align-items:center; gap:12px; }
        .cv-set-slider { flex:1; accent-color:var(--gold); }
        .cv-set-slider-val { font-size:14px; font-weight:700; color:var(--gold); min-width:40px; text-align:center; }
        /* Save bar */
        .cv-set-save-bar { position:sticky; bottom:0; background:var(--card); border-top:1px solid var(--bord); padding:14px 20px; display:flex; align-items:center; gap:12px; border-radius:0 0 14px 14px; }
        .cv-set-btn-save { background:var(--gold); color:#3B2418; border:none; padding:11px 28px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; transition:opacity .2s; }
        .cv-set-btn-save:hover { opacity:.85; }
        .cv-set-saved-msg { color:var(--green); font-size:13px; display:none; }
        </style>

        <?php if ($saved) : ?>
        <script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('cv-saved-msg');if(m){m.style.display='block';setTimeout(function(){m.style.display='none';},3000);}});</script>
        <?php endif; ?>

        <div class="cv-set-topbar">
            <h1 class="cv-set-title">⚙️ <span>Configurações</span></h1>
            <div class="cv-set-status">
                <span class="cv-set-pill <?php echo $api_ok ? 'ok' : 'off'; ?>">
                    <?php echo $api_ok ? '✅' : '❌'; ?> MailerLite <?php echo $api_ok ? 'conectado' : 'não configurado'; ?>
                </span>
                <span class="cv-set-pill <?php echo $wa_ok ? 'ok' : 'off'; ?>">
                    <?php echo $wa_ok ? '✅' : '❌'; ?> WhatsApp <?php echo $wa_ok ? 'ativo' : 'não configurado'; ?>
                </span>
            </div>
        </div>

        <?php echo CV_Admin::btn_voltar(); ?>

        <form method="post" id="cv-settings-form">
            <?php wp_nonce_field('cv_settings_save'); ?>
            <input type="hidden" name="cv_save_settings" value="1" />

            <div class="cv-set-grid">

                <!-- MailerLite -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">📧</span>
                        <div>
                            <div class="cv-set-card-title">MailerLite</div>
                            <div class="cv-set-card-subtitle">
                                <span class="cv-set-status-dot <?php echo $api_ok ? 'ok' : 'off'; ?>"></span>
                                <?php echo $api_ok ? 'API configurada' : 'Não configurado'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cv-set-card-body">
                        <div class="cv-set-field">
                            <label>API Key</label>
                            <input type="password" name="cv_mailerlite_api_key" class="cv-set-input"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   placeholder="Sua chave de API do MailerLite" />
                            <div class="cv-set-hint">Encontre em: MailerLite → Integrações → API</div>
                        </div>
                        <div class="cv-set-field">
                            <label>Group ID (lista padrão)</label>
                            <input type="text" name="cv_mailerlite_group_id" class="cv-set-input"
                                   value="<?php echo esc_attr($group_id); ?>"
                                   placeholder="ID numérico do grupo" />
                            <div class="cv-set-hint">Novos assinantes serão adicionados neste grupo.</div>
                        </div>
                        <?php if ($api_ok) : ?>
                        <div style="background:rgba(29,185,84,.06);border:1px solid rgba(29,185,84,.2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--green)">
                            ✅ MailerLite ativo — e-mails automáticos habilitados
                        </div>
                        <?php else : ?>
                        <div style="background:rgba(231,76,60,.06);border:1px solid rgba(231,76,60,.2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--red)">
                            ⚠️ Configure a API Key para habilitar o e-mail marketing
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- WhatsApp -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">💬</span>
                        <div>
                            <div class="cv-set-card-title">WhatsApp Flutuante</div>
                            <div class="cv-set-card-subtitle">
                                <span class="cv-set-status-dot <?php echo $wa_ok ? 'ok' : 'off'; ?>"></span>
                                <?php echo $wa_ok ? 'Botão ativo no site' : 'Botão oculto'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cv-set-card-body">
                        <div class="cv-set-field">
                            <label>Número (com DDI)</label>
                            <input type="text" name="cv_whatsapp_number" class="cv-set-input"
                                   id="cv-wa-num"
                                   value="<?php echo esc_attr($whatsapp); ?>"
                                   placeholder="5531999999999" />
                            <div class="cv-set-hint">55 + DDD + número. Deixe vazio para ocultar o botão.</div>
                        </div>
                        <div class="cv-set-field">
                            <label>Mensagem pré-preenchida</label>
                            <textarea name="cv_whatsapp_message" rows="2" class="cv-set-input"
                                      id="cv-wa-msg"
                                      placeholder="Mensagem ao clicar no botão..."
                            ><?php echo esc_textarea($wa_message); ?></textarea>
                        </div>
                        <div class="cv-set-field">
                            <label>Preview da conversa</label>
                            <div class="cv-wa-preview">
                                <div class="cv-wa-preview-label">Como aparece para o visitante</div>
                                <div class="cv-wa-bubble" id="cv-wa-preview-bubble">
                                    <?php echo esc_html($wa_message ?: 'Mensagem aparecerá aqui...'); ?>
                                </div>
                                <div class="cv-wa-bubble-time">agora ✓✓</div>
                            </div>
                        </div>
                        <div class="cv-set-field">
                            <label>Delay de aparecimento — <span id="cv-wa-delay-val"><?php echo $wa_delay; ?></span>s</label>
                            <div class="cv-set-slider-wrap">
                                <input type="range" name="cv_whatsapp_pulse_delay" class="cv-set-slider"
                                       min="0" max="30" value="<?php echo $wa_delay; ?>"
                                       oninput="document.getElementById('cv-wa-delay-val').textContent=this.value" />
                                <span class="cv-set-slider-val"><?php echo $wa_delay; ?>s</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Player -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">▶️</span>
                        <div>
                            <div class="cv-set-card-title">Player de Áudio</div>
                            <div class="cv-set-card-subtitle">Regras de contagem de plays</div>
                        </div>
                    </div>
                    <div class="cv-set-card-body">
                        <div class="cv-set-field">
                            <label>Segundos mínimos para play válido — <span id="cv-ps-val"><?php echo $play_secs; ?></span>s</label>
                            <div class="cv-set-slider-wrap">
                                <input type="range" name="cv_play_seconds" class="cv-set-slider"
                                       min="5" max="120" value="<?php echo $play_secs; ?>"
                                       oninput="document.getElementById('cv-ps-val').textContent=this.value;document.getElementById('cv-ps-display').textContent=this.value" />
                                <span class="cv-set-slider-val" id="cv-ps-display"><?php echo $play_secs; ?>s</span>
                            </div>
                            <div class="cv-set-hint">Um play só é contabilizado após este tempo de reprodução contínua. Padrão: 30s</div>
                        </div>
                        <div style="background:rgba(242,165,26,0.08);border:1px solid rgba(201,162,126,0.4);border-radius:8px;padding:12px 14px">
                            <div style="font-size:12px;color:var(--gold);font-weight:600;margin-bottom:4px">ℹ️ Como funciona</div>
                            <div style="font-size:11px;color:var(--muted);line-height:1.5">
                                O sistema registra 1 play após <strong style="color:var(--text)"><?php echo $play_secs; ?> segundos</strong> de reprodução.
                                Plays do mesmo IP em menos de 60s são descartados (antifraude).
                                O ranking é recalculado automaticamente a cada hora.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações do sistema -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">🖥️</span>
                        <div>
                            <div class="cv-set-card-title">Status do Sistema</div>
                            <div class="cv-set-card-subtitle">Informações técnicas</div>
                        </div>
                    </div>
                    <div class="cv-set-card-body" style="gap:10px">
                        <?php
                        $infos = array(
                            array('label' => 'Plugin', 'value' => 'Canção Verdadeira v' . CV_VERSION, 'ok' => true),
                            array('label' => 'PHP', 'value' => phpversion(), 'ok' => version_compare(phpversion(),'7.2','>=')),
                            array('label' => 'WordPress', 'value' => get_bloginfo('version'), 'ok' => true),
                            array('label' => 'Charset', 'value' => get_bloginfo('charset'), 'ok' => true),
                            array('label' => 'URL do site', 'value' => get_site_url(), 'ok' => true),
                            array('label' => 'Debug ativo', 'value' => (defined('WP_DEBUG') && WP_DEBUG) ? 'Sim (desative em produção)' : 'Não', 'ok' => !(defined('WP_DEBUG') && WP_DEBUG)),
                        );
                        foreach ($infos as $info):
                        ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bord)">
                            <span style="font-size:12px;color:var(--muted)"><?php echo esc_html($info['label']); ?></span>
                            <span style="font-size:12px;color:<?php echo $info['ok'] ? 'var(--text)' : 'var(--red)'; ?>;font-weight:600">
                                <?php echo $info['ok'] ? '✅ ' : '⚠️ '; ?><?php echo esc_html($info['value']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- grid -->

            <!-- Save bar sticky -->
            <div class="cv-set-save-bar" style="margin-top:24px;border-radius:14px">
                <button type="submit" class="cv-set-btn-save">💾 Salvar Configurações</button>
                <span id="cv-saved-msg" class="cv-set-saved-msg">✅ Configurações salvas com sucesso!</span>
            </div>
        </form>

        <script>
        (function(){
            // Preview WhatsApp em tempo real
            var msg = document.getElementById('cv-wa-msg');
            var bubble = document.getElementById('cv-wa-preview-bubble');
            if (msg && bubble) {
                msg.addEventListener('input', function(){
                    bubble.textContent = this.value || 'Mensagem aparecerá aqui...';
                });
            }
            // Slider WhatsApp delay
            var slider = document.querySelector('input[name="cv_whatsapp_pulse_delay"]');
            var sliderVal = document.querySelector('.cv-set-slider-val');
            if (slider && sliderVal) {
                slider.addEventListener('input', function(){
                    sliderVal.textContent = this.value + 's';
                });
            }
        })();
        </script>
        </div>
        <?php
    }

    public static function page_playlists() {
        global $wpdb;

        // Busca todas as playlists (de todos os usuarios) para visao admin
        $playlists = $wpdb->get_results(
            "SELECT p.*, u.display_name AS user_name,
             (SELECT COUNT(*) FROM {$wpdb->prefix}cv_playlist_items pi WHERE pi.playlist_id = p.id) AS total_musicas
             FROM {$wpdb->prefix}cv_playlists p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             ORDER BY p.created_at DESC
             LIMIT 200"
        );

        // Busca musicas para o modal de adicao
        $musicas = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => array(
                array( 'key' => '_cv_ativo', 'value' => '1', 'compare' => '=' ),
            ),
        ) );
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        /* Dark nos campos do formulário de playlists */
        #cv-admin-page .cv-input {
            background:rgba(123,58,34,0.04) !important;
            border:1px solid #EADBC6 !important;
            color:#3B2418 !important;
            border-radius:8px !important;
        }
        #cv-admin-page .cv-input:focus { border-color:#C9A27E !important; outline:none !important; }
        #cv-admin-page .cv-form-label { color:#6B4C3B !important; font-size:11px !important; text-transform:uppercase; letter-spacing:.4px; }
        #cv-admin-page .cv-section { background:#F8F0E4 !important; border:1px solid #EADBC6 !important; border-radius:14px; padding:22px 24px !important; margin-bottom:18px; }
        #cv-admin-page .cv-section-title { color:#7B3A22; border-color:#EADBC6 !important; }
        #cv-admin-page .cv-admin-header { background:#F8F0E4; border:1px solid #EADBC6; border-radius:12px; padding:18px 22px; margin-bottom:18px; }
        #cv-admin-page h1 { color:#3B2418 !important; }
        #cv-admin-page .cv-admin-subtitle { color:#8A6A55 !important; }
        #cv-admin-page .cv-btn-primary { background:#F2A51A !important; color:#3B2418 !important; font-weight:700 !important; border-radius:8px !important; }
        #cv-admin-page .cv-btn-outline { background:rgba(123,58,34,0.06) !important; border:1px solid #EADBC6 !important; color:#6B4C3B !important; border-radius:8px !important; }
        #cv-admin-page .cv-table { background:#F8F0E4 !important; }
        #cv-admin-page .cv-table th { background:#F8F0E4 !important; color:#8A6A55 !important; border-color:#EADBC6 !important; }
        #cv-admin-page .cv-table td { border-color:#EADBC6 !important; color:#3B2418 !important; }
        #cv-admin-page .cv-table tr:hover td { background:rgba(123,58,34,0.03) !important; }
        </style>
        <?php echo CV_Admin::btn_voltar(); ?>

            <div class="cv-admin-header">
                <div>
                    <h1>📋 Playlists</h1>
                    <p class="cv-admin-subtitle">
                        <?php echo count( $playlists ); ?> playlist<?php echo count( $playlists ) !== 1 ? 's' : ''; ?> cadastrada<?php echo count( $playlists ) !== 1 ? 's' : ''; ?>
                    </p>
                </div>
                <div style="margin-left:auto">
                    <button id="cv-pl-btn-nova" class="cv-btn cv-btn-primary" data-open="1">
                        ✕ Ocultar Formulário
                    </button>
                </div>
            </div>

            <div id="cv-pl-msg" class="cv-action-message" style="display:none"></div>

            <!-- Formulário de nova playlist -->
            <div id="cv-pl-form-nova" class="cv-section" style="display:block">
                <h2 class="cv-section-title" style="color:#7B3A22;font-size:16px;margin-bottom:18px">🎵 Nova Playlist Oficial</h2>
                <div style="display:flex;flex-direction:column;gap:14px;max-width:560px">
                    <div class="cv-form-group">
                        <label class="cv-form-label">Nome da playlist <span style="color:#D62C1A">*</span></label>
                        <input type="text" id="cv-pl-nome" class="cv-input"
                               placeholder="Ex: Top Sertanejo Universitário" maxlength="100" />
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Descrição</label>
                        <textarea id="cv-pl-desc" class="cv-input" rows="2"
                                  placeholder="Descrição opcional da playlist"></textarea>
                    </div>
                    <div class="cv-form-group" style="display:flex;align-items:center;gap:10px">
                        <input type="checkbox" id="cv-pl-publica" style="width:18px;height:18px;accent-color:#B8700C" />
                        <label for="cv-pl-publica" class="cv-form-label" style="margin:0">
                            Playlist pública (visível para todos os usuários)
                        </label>
                    </div>
                    <div class="cv-form-group" style="display:flex;align-items:center;gap:10px">
                        <input type="checkbox" id="cv-pl-destaque" style="width:18px;height:18px;accent-color:#B8700C" />
                        <label for="cv-pl-destaque" class="cv-form-label" style="margin:0">
                            ⭐ Fixar na home (aparece na seção de playlists em destaque)
                        </label>
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Capa da playlist (URL da imagem)</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="text" id="cv-pl-capa-url" class="cv-input"
                                   placeholder="Cole a URL da imagem ou use o botão ao lado" style="flex:1" />
                            <button type="button" id="cv-pl-capa-media" class="cv-btn cv-btn-outline"
                                    style="padding:8px 12px;white-space:nowrap">
                                📁 Biblioteca
                            </button>
                        </div>
                        <div id="cv-pl-capa-preview" style="margin-top:8px;display:none">
                            <img id="cv-pl-capa-img" src="" alt="Preview da capa"
                                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #EADBC6" />
                        </div>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button id="cv-pl-salvar" class="cv-btn cv-btn-primary">💾 Criar Playlist</button>
                        <button id="cv-pl-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Tabela de playlists -->
            <div class="cv-section">
                <?php if ( empty( $playlists ) ) : ?>
                    <div style="background:rgba(242,165,26,0.07);border:1px dashed rgba(201,162,126,0.5);border-radius:14px;padding:40px;text-align:center;margin-bottom:8px">
                        <div style="font-size:48px;margin-bottom:14px">🎵</div>
                        <div style="font-size:17px;color:#3B2418;font-weight:700;margin-bottom:8px">Nenhuma playlist criada ainda</div>
                        <div style="font-size:13px;color:#8A6A55;margin-bottom:20px;line-height:1.6">
                            Playlists editoriais são curadas por você e aparecem para todos os usuários do site.<br>
                            Ideal para destacar os maiores hits, lançamentos e temáticas especiais.
                        </div>
                        <button onclick="document.getElementById('cv-pl-nome').focus()"
                                style="background:#F2A51A;color:#3B2418;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">
                            ↑ Preencha o formulário acima para criar a primeira
                        </button>
                    </div>
                <?php else : ?>
                <table class="cv-table" id="cv-pl-tabela">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Nome</th>
                            <th>Criada por</th>
                            <th style="text-align:center">Músicas</th>
                            <th style="text-align:center">Pública</th>
                            <th>Criada em</th>
                            <th style="text-align:center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $playlists as $i => $pl ) : ?>
                        <tr id="cv-pl-row-<?php echo esc_attr( $pl->id ); ?>">
                            <td style="color:#8A6A55;font-size:12px"><?php echo $i + 1; ?></td>
                            <td>
                                <strong style="color:var(--cv-text)"><?php echo esc_html( $pl->name ); ?></strong>
                                <?php if ( $pl->description ) : ?>
                                    <br><span style="font-size:11px;color:#8A6A55"><?php echo esc_html( mb_substr( $pl->description, 0, 60 ) ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#8A6A55;font-size:13px"><?php echo esc_html( $pl->user_name ?: 'Admin' ); ?></td>
                            <td style="text-align:center">
                                <span class="cv-badge-count" id="cv-pl-count-<?php echo esc_attr( $pl->id ); ?>">
                                    <?php echo (int) $pl->total_musicas; ?>
                                </span>
                            </td>
                            <td style="text-align:center">
                                <?php if ( $pl->is_public ) : ?>
                                    <span style="color:#1C7C44;font-size:18px" title="Pública">●</span>
                                <?php else : ?>
                                    <span style="color:#8A6A55;font-size:18px" title="Privada">○</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#8A6A55;font-size:12px">
                                <?php echo esc_html( date( 'd/m/Y', strtotime( $pl->created_at ) ) ); ?>
                            </td>
                            <td style="text-align:center">
                                <div style="display:flex;gap:6px;justify-content:center">
                                    <button class="cv-btn cv-btn-outline cv-pl-ver-musicas"
                                            data-id="<?php echo esc_attr( $pl->id ); ?>"
                                            data-nome="<?php echo esc_attr( $pl->name ); ?>"
                                            style="padding:4px 10px;font-size:11px">
                                        🎵 Músicas
                                    </button>
                                    <button class="cv-btn cv-btn-outline cv-pl-renomear"
                                            data-id="<?php echo esc_attr( $pl->id ); ?>"
                                            data-nome="<?php echo esc_attr( $pl->name ); ?>"
                                            style="padding:4px 10px;font-size:11px">
                                        ✏ Renomear
                                    </button>
                                    <button class="cv-btn cv-pl-excluir"
                                            data-id="<?php echo esc_attr( $pl->id ); ?>"
                                            style="padding:4px 10px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3)">
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div><!-- /.cv-admin-wrap -->

        <!-- Modal: gerenciar músicas da playlist -->
        <div id="cv-pl-modal" style="display:none;position:fixed;inset:0;background:rgba(59,36,24,0.45);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#FFFFFF;border:1px solid rgba(201,162,126,0.4);border-radius:16px;width:90%;max-width:680px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column">

                <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid rgba(123,58,34,0.12)">
                    <div>
                        <h3 id="cv-pl-modal-titulo" style="font-size:17px;font-weight:700;color:#3B2418;margin:0"></h3>
                        <span id="cv-pl-modal-count" style="font-size:12px;color:#8A6A55"></span>
                    </div>
                    <button id="cv-pl-modal-fechar" style="background:none;border:none;color:#8A6A55;font-size:20px;cursor:pointer;padding:4px 8px">✕</button>
                </div>

                <div style="display:flex;gap:0;flex:1;overflow:hidden">

                    <!-- Músicas na playlist -->
                    <div style="flex:1;overflow-y:auto;padding:16px;border-right:1px solid rgba(123,58,34,0.12)">
                        <p style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Na playlist</p>
                        <div id="cv-pl-musicas-lista" style="display:flex;flex-direction:column;gap:6px">
                            <p style="color:#8A6A55;font-size:13px">Carregando...</p>
                        </div>
                    </div>

                    <!-- Adicionar músicas -->
                    <div style="flex:1;overflow-y:auto;padding:16px">
                        <p style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Adicionar música</p>
                        <input type="text" id="cv-pl-busca-musica" placeholder="Filtrar músicas..."
                               style="width:100%;background:#FBF6EE;border:1px solid #EADBC6;border-radius:8px;padding:8px 12px;color:#3B2418;font-size:13px;outline:none;margin-bottom:10px;box-sizing:border-box" />
                        <div id="cv-pl-musicas-disponiveis" style="display:flex;flex-direction:column;gap:4px;max-height:320px;overflow-y:auto">
                            <?php foreach ( $musicas as $musica ) : ?>
                            <div class="cv-pl-musica-item"
                                 data-id="<?php echo esc_attr( $musica->ID ); ?>"
                                 data-titulo="<?php echo esc_attr( strtolower( $musica->post_title ) ); ?>"
                                 style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;background:rgba(123,58,34,0.03);border-radius:6px;cursor:pointer;transition:background .15s">
                                <span style="font-size:13px;color:#6B4C3B"><?php echo esc_html( $musica->post_title ); ?></span>
                                <button class="cv-pl-add-musica cv-btn cv-btn-outline"
                                        data-id="<?php echo esc_attr( $musica->ID ); ?>"
                                        style="padding:3px 10px;font-size:11px;flex-shrink:0">
                                    +
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce   = '<?php echo esc_js( wp_create_nonce( 'cv_playlist_nonce' ) ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var plAtual = 0;

            function showMsg(msg, ok) {
                var $m = $('#cv-pl-msg');
                $m.text(msg)
                  .css({ background: ok ? '#EBF4EB' : '#F4EBEB',
                         border: '1px solid ' + (ok ? '#2d6a2d' : '#6a2d2d'),
                         color:  ok ? '#7fce7f' : '#ce7f7f' })
                  .show();
                setTimeout(function(){ $m.fadeOut(); }, 3500);
            }

            // ── Nova playlist ──────────────────────────────────────
            $('#cv-pl-btn-nova').on('click', function(){
                var isOpen = $(this).data('open') == 1;
                if (isOpen) {
                    $('#cv-pl-form-nova').slideUp(180);
                    $(this).text('+ Nova Playlist Oficial').data('open', 0);
                } else {
                    $('#cv-pl-form-nova').slideDown(180);
                    $(this).text('✕ Ocultar Formulário').data('open', 1);
                }
            });
            $('#cv-pl-cancelar').on('click', function(){
                $('#cv-pl-form-nova').slideUp(180);
                $('#cv-pl-btn-nova').text('+ Nova Playlist Oficial').data('open', 0);
                $('#cv-pl-nome').val('');
                $('#cv-pl-desc').val('');
                $('#cv-pl-publica').prop('checked', false);
            });

            $('#cv-pl-salvar').on('click', function(){
                var nome = $.trim($('#cv-pl-nome').val());
                if (!nome) { alert('Informe o nome da playlist.'); return; }
                var $btn = $(this).prop('disabled', true).text('Criando...');

                $.post(ajaxUrl, {
                    action:      'cv_playlist_create',
                    nonce:       nonce,
                    name:        nome,
                    description: $.trim($('#cv-pl-desc').val()),
                    is_public:   $('#cv-pl-publica').is(':checked') ? 1 : 0,
                    is_featured: $('#cv-pl-destaque').is(':checked') ? 1 : 0,
                    cover_url:   $.trim($('#cv-pl-capa-url').val()),
                }, function(res){
                    if (res.success) {
                        showMsg('✅ Playlist "' + nome + '" criada!', true);
                        setTimeout(function(){ location.reload(); }, 1200);
                    } else {
                        showMsg('Erro: ' + (res.data && res.data.message ? res.data.message : 'tente novamente.'), false);
                        $btn.prop('disabled', false).text('💾 Criar Playlist');
                    }
                });
            });

            // ── Renomear ───────────────────────────────────────────
            $(document).on('click', '.cv-pl-renomear', function(){
                var id   = $(this).data('id');
                var nome = $(this).data('nome');
                var novo = prompt('Novo nome da playlist:', nome);
                if (!novo || !novo.trim() || novo.trim() === nome) return;

                $.post(ajaxUrl, {
                    action:      'cv_playlist_rename',
                    nonce:       nonce,
                    playlist_id: id,
                    name:        $.trim(novo),
                }, function(res){
                    if (res.success) {
                        showMsg('✅ Renomeada com sucesso!', true);
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        showMsg('Erro ao renomear.', false);
                    }
                });
            });

            // ── Excluir ────────────────────────────────────────────
            $(document).on('click', '.cv-pl-excluir', function(){
                var id = $(this).data('id');
                if (!confirm('Excluir esta playlist e todas as suas músicas? Esta ação não pode ser desfeita.')) return;

                $.post(ajaxUrl, {
                    action:      'cv_playlist_delete',
                    nonce:       nonce,
                    playlist_id: id,
                }, function(res){
                    if (res.success) {
                        $('#cv-pl-row-' + id).fadeOut(300, function(){ $(this).remove(); });
                        showMsg('✅ Playlist excluída.', true);
                    } else {
                        showMsg('Erro ao excluir.', false);
                    }
                });
            });

            // ── Modal: ver e gerenciar músicas ─────────────────────
            $(document).on('click', '.cv-pl-ver-musicas', function(){
                plAtual = $(this).data('id');
                var nome = $(this).data('nome');
                $('#cv-pl-modal-titulo').text('📋 ' + nome);
                $('#cv-pl-modal').css('display', 'flex');
                carregarMusicasPlaylist(plAtual);
            });

            $('#cv-pl-modal-fechar').on('click', function(){
                $('#cv-pl-modal').hide();
                plAtual = 0;
            });

            // Fecha ao clicar fora
            $('#cv-pl-modal').on('click', function(e){
                if ($(e.target).is('#cv-pl-modal')) { $(this).hide(); plAtual = 0; }
            });

            function carregarMusicasPlaylist(plId) {
                $('#cv-pl-musicas-lista').html('<p style="color:#8A6A55;font-size:13px">Carregando...</p>');

                // Busca músicas via endpoint REST do plugin
                $.get('<?php echo esc_js( rest_url( 'cv/v1/ranking/top' ) ); ?>', function(){})
                 .always(function(){
                    // Fallback: busca direta no banco via AJAX admin
                    $.post(ajaxUrl, {
                        action:      'cv_admin_get_playlist_items',
                        nonce:       '<?php echo esc_js( wp_create_nonce( "cv_admin_nonce" ) ); ?>',
                        playlist_id: plId,
                    }, function(res){
                        if (res.success && res.data.items) {
                            renderMusicasPlaylist(res.data.items, plId);
                            $('#cv-pl-modal-count').text(res.data.items.length + ' música(s)');
                            $('#cv-pl-count-' + plId).text(res.data.items.length);
                        } else {
                            $('#cv-pl-musicas-lista').html('<p style="color:#8A6A55;font-size:13px">Nenhuma música ainda.</p>');
                        }
                    });
                 });
            }

            function renderMusicasPlaylist(items, plId) {
                if (!items.length) {
                    $('#cv-pl-musicas-lista').html('<p style="color:#8A6A55;font-size:13px;font-style:italic">Playlist vazia. Adicione músicas ao lado.</p>');
                    return;
                }
                var html = '';
                $.each(items, function(i, item){
                    html += '<div style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:rgba(123,58,34,0.03);border-radius:6px">'
                          + '<span style="font-size:11px;color:#8A6A55;min-width:18px">' + (i+1) + '</span>'
                          + '<span style="flex:1;font-size:13px;color:#6B4C3B;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(item.title) + '</span>'
                          + '<button class="cv-pl-remover-musica cv-btn" data-pl="' + plId + '" data-music="' + item.id + '"'
                          + ' style="padding:3px 8px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3);flex-shrink:0">✕</button>'
                          + '</div>';
                });
                $('#cv-pl-musicas-lista').html(html);
            }

            // Adicionar música
            $(document).on('click', '.cv-pl-add-musica', function(e){
                e.stopPropagation();
                if (!plAtual) return;
                var musicId = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');

                $.post(ajaxUrl, {
                    action:      'cv_playlist_add_music',
                    nonce:       nonce,
                    playlist_id: plAtual,
                    music_id:    musicId,
                }, function(res){
                    if (res.success) {
                        carregarMusicasPlaylist(plAtual);
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Erro ao adicionar.');
                    }
                    $btn.prop('disabled', false).text('+');
                });
            });

            // Remover música
            $(document).on('click', '.cv-pl-remover-musica', function(){
                var plId    = $(this).data('pl');
                var musicId = $(this).data('music');
                var $btn    = $(this).prop('disabled', true).text('...');

                $.post(ajaxUrl, {
                    action:      'cv_playlist_remove_music',
                    nonce:       nonce,
                    playlist_id: plId,
                    music_id:    musicId,
                }, function(res){
                    if (res.success) {
                        carregarMusicasPlaylist(plId);
                    } else {
                        $btn.prop('disabled', false).text('✕');
                    }
                });
            });

            // Media Library para capa da playlist
            $('#cv-pl-capa-media').on('click', function(e){
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) {
                    alert('A biblioteca de mídia não está disponível. Cole a URL diretamente.');
                    return;
                }
                var frame = wp.media({
                    title:    'Escolher capa da playlist',
                    button:   { text: 'Usar esta imagem' },
                    multiple: false,
                    library:  { type: 'image' },
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#cv-pl-capa-url').val(attachment.url);
                    $('#cv-pl-capa-img').attr('src', attachment.url);
                    $('#cv-pl-capa-preview').show();
                });
                frame.open();
            });

            // Preview ao digitar URL manualmente
            $('#cv-pl-capa-url').on('change', function(){
                var url = $.trim($(this).val());
                if (url) {
                    $('#cv-pl-capa-img').attr('src', url);
                    $('#cv-pl-capa-preview').show();
                } else {
                    $('#cv-pl-capa-preview').hide();
                }
            });

            // Filtro de busca no modal
            $('#cv-pl-busca-musica').on('input', function(){
                var termo = $(this).val().toLowerCase();
                $('.cv-pl-musica-item').each(function(){
                    var titulo = $(this).data('titulo') || '';
                    $(this).toggle( titulo.indexOf(termo) !== -1 );
                });
            });

            function escHtml(str) {
                return $('<div>').text(str).html();
            }
        });
        </script>
        <?php
    }

    public static function page_users() {
        global $wpdb;

        $search = sanitize_text_field( $_GET['cv_user_search'] ?? '' );
        $args   = array( 'number' => 100, 'orderby' => 'registered', 'order' => 'DESC' );
        if ( $search ) {
            $args['search']         = '*' . $search . '*';
            $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
        }
        $users = get_users( $args );

        // KPIs gerais
        $total_users  = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $novos_7d     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= NOW() - INTERVAL 7 DAY");
        $novos_30d    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= NOW() - INTERVAL 30 DAY");
        $ativos_7d    = (int) $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$wpdb->prefix}cv_plays_log WHERE played_at >= NOW() - INTERVAL 7 DAY AND user_id > 0");

        // Plays e favoritos por usuário
        $user_ids   = wp_list_pluck( $users, 'ID' );
        $fav_counts = array(); $pl_counts = array(); $play_counts = array();
        if ( ! empty( $user_ids ) ) {
            $ids_in = implode( ',', array_map( 'intval', $user_ids ) );
            foreach ( $wpdb->get_results("SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_favorites WHERE user_id IN ($ids_in) GROUP BY user_id") as $r )
                $fav_counts[$r->user_id] = (int)$r->total;
            foreach ( $wpdb->get_results("SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_playlists WHERE user_id IN ($ids_in) GROUP BY user_id") as $r )
                $pl_counts[$r->user_id] = (int)$r->total;
            foreach ( $wpdb->get_results("SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_plays_log WHERE user_id IN ($ids_in) GROUP BY user_id") as $r )
                $play_counts[$r->user_id] = (int)$r->total;
        }

        // Top 3 ouvintes
        $top3_ids = array();
        if ( ! empty( $play_counts ) ) {
            arsort( $play_counts );
            $top3_ids = array_slice( array_keys( $play_counts ), 0, 3, true );
        }

        // Crescimento diário 30 dias
        $cresc = $wpdb->get_results("SELECT DATE(user_registered) AS dia, COUNT(*) AS total FROM {$wpdb->users} WHERE user_registered >= NOW() - INTERVAL 30 DAY GROUP BY DATE(user_registered) ORDER BY dia ASC");
        $by_day = array();
        foreach ($cresc as $r) $by_day[$r->dia] = (int)$r->total;
        $chart_labels = array(); $chart_values = array();
        $acum = $total_users - $novos_30d;
        for ($i=29;$i>=0;$i--) {
            $day = date('Y-m-d', strtotime("-{$i} days"));
            $chart_labels[] = date('d/m', strtotime($day));
            $acum += isset($by_day[$day]) ? $by_day[$day] : 0;
            $chart_values[] = $acum;
        }

        $max_plays = ! empty($play_counts) ? max($play_counts) : 1;
        $cores = array('#4a90d9','#1db954','#B8700C','#9b59b6','#e74c3c','#e67e22','#1abc9c','#e91e63');
        ?>
        <div class="cv-admin-wrap cv-users-v2" style="max-width:1100px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <?php echo CV_Admin::btn_voltar(); ?>
        
        <style>
        .cv-users-v2 * { box-sizing:border-box; }
        /* Hero */
        .cv-usr-hero { background:linear-gradient(135deg,#E9F6E9 0%,#FFFFFF 100%);border:1px solid #B2DCB2;border-radius:14px;padding:22px 28px;margin-bottom:22px;display:flex;align-items:center;gap:20px; }
        .cv-usr-hero h1 { color:#137B38;font-size:20px;margin:0 0 2px;font-weight:800; }
        .cv-usr-hero p  { color:#8A6A55;font-size:12px;margin:0; }
        /* KPIs */
        .cv-usr-kpis { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px; }
        @media(max-width:800px){.cv-usr-kpis{grid-template-columns:1fr 1fr;}}
        .cv-usr-kpi { background:#FFFFFF;border:1px solid #B7D6B7;border-radius:12px;padding:18px 16px;position:relative;overflow:hidden; }
        .cv-usr-kpi::after { content:"";position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 12px 12px; }
        .cv-usr-kpi.k1::after{background:linear-gradient(90deg,#1db954,#22d460);}
        .cv-usr-kpi.k2::after{background:linear-gradient(90deg,#4a90d9,#5aa8f0);}
        .cv-usr-kpi.k3::after{background:linear-gradient(90deg,#F2A51A,#F2A51A);}
        .cv-usr-kpi.k4::after{background:linear-gradient(90deg,#9b59b6,#b278cc);}
        .cv-usr-kpi-icon  { font-size:18px;margin-bottom:6px;display:block; }
        .cv-usr-kpi-val   { font-size:28px;font-weight:800;color:#3B2418;line-height:1;margin-bottom:3px; }
        .cv-usr-kpi-label { font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.4px; }
        .cv-usr-kpi-badge { position:absolute;top:12px;right:12px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px; }
        .badge-green{background:rgba(29,185,84,.15);color:#137B38;}
        .badge-neu{background:rgba(123,58,34,0.06);color:#8A6A55;}
        /* Mid grid */
        .cv-usr-mid { display:grid;grid-template-columns:1.6fr 1fr;gap:18px;margin-bottom:22px;align-items:start; }
        @media(max-width:900px){.cv-usr-mid{grid-template-columns:1fr;}}
        .cv-usr-panel { background:#FFFFFF;border:1px solid #B7D6B7;border-radius:12px;padding:20px 22px; }
        .cv-usr-panel h3 { font-size:12px;color:#8A6A55;text-transform:uppercase;letter-spacing:.6px;margin:0 0 14px;font-weight:600; }
        .cv-usr-panel h3 strong { color:#137B38; }
        #cv-usr-chart { width:100%;height:110px;max-height:110px; }
        /* Top ouvintes */
        .cv-top-ouv { display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #EADBC6; }
        .cv-top-ouv:last-child { border-bottom:none; }
        .cv-top-ouv-avatar { width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0; }
        .cv-top-ouv-info { flex:1;min-width:0; }
        .cv-top-ouv-name  { font-size:12px;font-weight:700;color:#3B2418;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px; }
        .cv-top-ouv-bar-bg { height:3px;background:#FBF6EE;border-radius:3px; }
        .cv-top-ouv-bar-fill { height:100%;border-radius:3px;background:linear-gradient(90deg,#1db954,#22d460); }
        .cv-top-ouv-plays { font-size:11px;color:#137B38;font-weight:700;white-space:nowrap; }
        /* Toolbar */
        .cv-usr-toolbar { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px; }
        .cv-usr-search { display:flex;gap:8px;align-items:center; }
        .cv-usr-search input { background:#FBF6EE;border:1px solid #B7D6B7;color:#3B2418;border-radius:6px;padding:7px 12px;font-size:12px;outline:none;min-width:220px; }
        .cv-usr-search input:focus { border-color:#1db954; }
        .cv-usr-btn { font-size:11px;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:600;border:1px solid #B7D6B7;background:#FBF6EE;color:#8A6A55;transition:all .2s; }
        .cv-usr-btn:hover { border-color:#1db954;color:#137B38; }
        .cv-usr-btn.primary { background:#1db954;color:#3B2418;border-color:#1db954; }
        .cv-usr-btn.primary:hover { background:#22d460; }
        .cv-usr-btn.danger { background:rgba(231,76,60,.12);color:#D62C1A;border-color:rgba(231,76,60,.25); }
        .cv-usr-btn.danger:hover { background:rgba(231,76,60,.2); }
        /* Tabela */
        .cv-usr-table { width:100%;border-collapse:collapse; }
        .cv-usr-table th { font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#8A6A55;padding:8px 10px;text-align:left;border-bottom:1px solid #EADBC6; }
        .cv-usr-table td { padding:9px 10px;border-bottom:1px solid #EADBC6;vertical-align:middle;font-size:13px; }
        .cv-usr-table tr:hover td { background:rgba(29,185,84,.03); }
        .cv-usr-table tr:last-child td { border-bottom:none; }
        .cv-usr-avatar-cell { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800; }
        .cv-usr-name-cell { display:flex;align-items:center;gap:8px; }
        .cv-usr-role { font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(29,185,84,.1);color:#137B38; }
        .cv-usr-role.admin { background:rgba(242,165,26,0.13);color:#7B3A22; }
        .cv-usr-engage { display:flex;align-items:center;gap:4px;font-size:12px;color:#8A6A55; }
        .cv-usr-engage strong { color:#3B2418; }
        .cv-usr-empty { text-align:center;padding:48px;color:#8A6A55; }
        .cv-usr-actions { display:flex;gap:4px;justify-content:center; }
        </style>

        <!-- Hero -->
        <div class="cv-usr-hero">
            <div style="font-size:40px;filter:drop-shadow(0 0 12px rgba(29,185,84,.3))">👥</div>
            <div>
                <h1>Gestão de Usuários</h1>
                <p>Acompanhe cadastros, engajamento e atividade da sua base de fãs.</p>
            </div>
            <div style="margin-left:auto;text-align:right">
                <div style="font-size:28px;font-weight:800;color:#137B38;line-height:1"><?php echo number_format($total_users); ?></div>
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.4px">usuários cadastrados</div>
            </div>
        </div>

        <!-- KPIs -->
        <div class="cv-usr-kpis">
            <div class="cv-usr-kpi k1">
                <span class="cv-usr-kpi-icon">👤</span>
                <div class="cv-usr-kpi-val" data-target="<?php echo $total_users; ?>">0</div>
                <div class="cv-usr-kpi-label">Total de usuários</div>
            </div>
            <div class="cv-usr-kpi k2">
                <?php if($novos_7d>0):?><span class="cv-usr-kpi-badge badge-green">+<?php echo $novos_7d;?> esta semana</span><?php endif;?>
                <span class="cv-usr-kpi-icon">🆕</span>
                <div class="cv-usr-kpi-val" data-target="<?php echo $novos_7d; ?>">0</div>
                <div class="cv-usr-kpi-label">Novos (7 dias)</div>
            </div>
            <div class="cv-usr-kpi k3">
                <span class="cv-usr-kpi-icon">📆</span>
                <div class="cv-usr-kpi-val" data-target="<?php echo $novos_30d; ?>">0</div>
                <div class="cv-usr-kpi-label">Novos (30 dias)</div>
            </div>
            <div class="cv-usr-kpi k4">
                <?php if($ativos_7d>0):?><span class="cv-usr-kpi-badge badge-green"><?php echo $ativos_7d;?> com plays</span><?php endif;?>
                <span class="cv-usr-kpi-icon">🎧</span>
                <div class="cv-usr-kpi-val" data-target="<?php echo $ativos_7d; ?>">0</div>
                <div class="cv-usr-kpi-label">Ativos (7 dias)</div>
            </div>
        </div>

        <!-- Gráfico + Top ouvintes -->
        <div class="cv-usr-mid">
            <div class="cv-usr-panel">
                <h3>Crescimento acumulado — <strong>últimos 30 dias</strong></h3>
                <canvas id="cv-usr-chart"></canvas>
            </div>
            <div class="cv-usr-panel">
                <h3>🎧 Top <strong>ouvintes</strong></h3>
                <?php if(empty($top3_ids)):?>
                    <p style="color:#8A6A55;font-size:12px">Nenhum play registrado ainda.</p>
                <?php else: foreach($top3_ids as $uid):
                    $u    = get_userdata($uid);
                    if(!$u) continue;
                    $plays = $play_counts[$uid] ?? 0;
                    $pct   = $max_plays > 0 ? round(($plays/$max_plays)*100) : 0;
                    $cor   = $cores[abs(crc32($u->user_email)) % count($cores)];
                    $ini   = strtoupper(mb_substr($u->display_name, 0, 1));
                ?>
                <div class="cv-top-ouv">
                    <div class="cv-top-ouv-avatar" style="background:<?php echo $cor;?>1a;color:<?php echo $cor;?>;border:1px solid <?php echo $cor;?>33"><?php echo esc_html($ini);?></div>
                    <div class="cv-top-ouv-info">
                        <div class="cv-top-ouv-name"><?php echo esc_html($u->display_name);?></div>
                        <div class="cv-top-ouv-bar-bg"><div class="cv-top-ouv-bar-fill" style="width:<?php echo $pct;?>%"></div></div>
                    </div>
                    <div class="cv-top-ouv-plays"><?php echo number_format($plays);?> plays</div>
                </div>
                <?php endforeach; endif;?>
            </div>
        </div>

        <!-- Tabela -->
        <div class="cv-usr-panel">
            <div class="cv-usr-toolbar">
                <form method="get" class="cv-usr-search">
                    <input type="hidden" name="page" value="cv-users"/>
                    <input type="text" name="cv_user_search" value="<?php echo esc_attr($search);?>" placeholder="Buscar por nome ou e-mail..."/>
                    <button type="submit" class="cv-usr-btn primary">🔍 Buscar</button>
                    <?php if($search):?><a href="<?php echo admin_url('admin.php?page=cv-users');?>" class="cv-usr-btn">Limpar</a><?php endif;?>
                </form>
                <button id="cv-export-csv" class="cv-usr-btn">⬇ Exportar CSV</button>
            </div>
            <div id="cv-user-msg" class="cv-action-message" style="display:none;margin-bottom:12px"></div>

            <?php if(empty($users)):?>
            <div class="cv-usr-empty">
                <div style="font-size:36px;margin-bottom:10px">👤</div>
                <p style="color:#8A6A55"><?php echo $search?'Nenhum resultado para "'.esc_html($search).'".':'Nenhum usuário cadastrado ainda.';?></p>
            </div>
            <?php else:?>
            <table class="cv-usr-table">
                <thead><tr>
                    <th style="width:42px"></th>
                    <th>Usuário</th>
                    <th>E-mail</th>
                    <th style="text-align:center">Papel</th>
                    <th style="text-align:center">Engajamento</th>
                    <th>Cadastro</th>
                    <th style="text-align:center">Ações</th>
                </tr></thead>
                <tbody>
                <?php foreach($users as $user):
                    $favs  = $fav_counts[$user->ID] ?? 0;
                    $pls   = $pl_counts[$user->ID]  ?? 0;
                    $plays = $play_counts[$user->ID] ?? 0;
                    $role  = implode(', ', $user->roles) ?: 'subscriber';
                    $ini   = strtoupper(mb_substr($user->display_name, 0, 1));
                    $cor   = $cores[abs(crc32($user->user_email)) % count($cores)];
                    $dias  = round((time()-strtotime($user->user_registered))/86400);
                    $tempo = $dias===0?'hoje':($dias===1?'ontem':$dias.'d atrás');
                    $is_admin = in_array('administrator', $user->roles);
                ?>
                <tr id="cv-user-row-<?php echo esc_attr($user->ID);?>">
                    <td>
                        <div class="cv-usr-avatar-cell" style="background:<?php echo $cor;?>1a;color:<?php echo $cor;?>;border:1px solid <?php echo $cor;?>33">
                            <?php echo esc_html($ini);?>
                        </div>
                    </td>
                    <td>
                        <div class="cv-usr-name-cell">
                            <div>
                                <div style="font-weight:700;color:#3B2418;font-size:13px"><?php echo esc_html($user->display_name);?></div>
                                <div style="font-size:10px;color:#8A6A55">@<?php echo esc_html($user->user_login);?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:#8A6A55"><?php echo esc_html($user->user_email);?></td>
                    <td style="text-align:center">
                        <span class="cv-usr-role <?php echo $is_admin?'admin':'';?>"><?php echo esc_html($role);?></span>
                    </td>
                    <td style="text-align:center">
                        <div class="cv-usr-engage">
                            <span title="Plays">▶ <strong><?php echo $plays;?></strong></span>
                            <span style="color:#8A6A55">·</span>
                            <span title="Favoritos">❤ <strong><?php echo $favs;?></strong></span>
                            <span style="color:#8A6A55">·</span>
                            <span title="Playlists">📋 <strong><?php echo $pls;?></strong></span>
                        </div>
                    </td>
                    <td style="font-size:11px;color:#8A6A55"><?php echo esc_html($tempo);?></td>
                    <td>
                        <div class="cv-usr-actions">
                            <a href="<?php echo esc_url(admin_url('user-edit.php?user_id='.$user->ID));?>" class="cv-usr-btn" title="Editar">✏</a>
                            <button class="cv-usr-btn cv-user-reset-pw" data-id="<?php echo esc_attr($user->ID);?>" data-email="<?php echo esc_attr($user->user_email);?>" title="Redefinir senha">🔑</button>
                            <?php if($user->ID !== get_current_user_id()):?>
                            <button class="cv-usr-btn danger cv-user-delete" data-id="<?php echo esc_attr($user->ID);?>" data-nome="<?php echo esc_attr($user->display_name);?>" title="Excluir">🗑</button>
                            <?php endif;?>
                        </div>
                    </td>
                </tr>
                <?php endforeach;?>
                </tbody>
            </table>
            <?php endif;?>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function(){
            document.querySelectorAll('.cv-usr-kpi-val[data-target]').forEach(function(el){
                var t=parseInt(el.dataset.target,10)||0,s=Math.ceil(t/40),c=0;
                var tm=setInterval(function(){c=Math.min(c+s,t);el.textContent=c.toLocaleString('pt-BR');if(c>=t)clearInterval(tm);},30);
            });
            var ctx=document.getElementById('cv-usr-chart');
            if(ctx){ new Chart(ctx,{
                type:'line',
                data:{ labels:<?php echo json_encode($chart_labels);?>, datasets:[{
                    label:'Usuários',data:<?php echo json_encode($chart_values);?>,
                    borderColor:'#1db954',backgroundColor:'rgba(29,185,84,0.07)',
                    borderWidth:2,pointRadius:2,pointHoverRadius:5,pointBackgroundColor:'#1db954',fill:true,tension:0.4
                }]},
                options:{ responsive:true,maintainAspectRatio:false,aspectRatio:2.5,
                    plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#FFFFFF',borderColor:'#EBF4EB',borderWidth:1,titleColor:'#1db954',bodyColor:'#C9A27E'}},
                    scales:{
                        x:{grid:{color:'rgba(123,58,34,0.03)'},ticks:{color:'#F3E6D3',font:{size:10},maxTicksLimit:10}},
                        y:{grid:{color:'rgba(123,58,34,0.03)'},ticks:{color:'#F3E6D3',font:{size:10},precision:0},beginAtZero:false}
                    }
                }
            }); }
        })();
        jQuery(function($){
            var nonce='<?php echo esc_js(wp_create_nonce("cv_admin_nonce"));?>';
            var ajax='<?php echo esc_js(admin_url("admin-ajax.php"));?>';
            function msg(t,ok){var $m=$('#cv-user-msg');$m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},3500);}
            $(document).on('click','.cv-user-reset-pw',function(){
                var id=$(this).data('id'),email=$(this).data('email');
                if(!confirm('Enviar e-mail de redefinição para '+email+'?')) return;
                $.post(ajax,{action:'cv_admin_reset_password',nonce:nonce,user_id:id},function(r){msg(r.success?'✅ E-mail enviado para '+email:'❌ Erro ao enviar.',r.success);});
            });
            $(document).on('click','.cv-user-delete',function(){
                var id=$(this).data('id'),nome=$(this).data('nome');
                if(!confirm('Excluir "'+nome+'"? Esta ação não pode ser desfeita.')) return;
                $.post(ajax,{action:'cv_admin_delete_user',nonce:nonce,user_id:id},function(r){
                    if(r.success){$('#cv-user-row-'+id).fadeOut(300,function(){$(this).remove();});msg('✅ Usuário excluído.',true);}
                    else{msg('❌ '+(r.data&&r.data.message?r.data.message:'Erro.'),false);}
                });
            });
            $('#cv-export-csv').on('click',function(){
                var form=$('<form method="post" action="'+ajax+'" style="display:none"><input name="action" value="cv_export_users_csv"/><input name="nonce" value="'+nonce+'"/></form>');
                $('body').append(form);form.submit();setTimeout(function(){form.remove();},3000);
                msg('✅ Download iniciado!',true);
            });
        });
        </script>
        </div>
        <?php
    }

        // ─── LOGS ────────────────────────────────────────────────────
    public static function page_logs() {
        global $wpdb;
        $logs = class_exists( 'CV_Advanced' ) ? CV_Advanced::get_logs( 200 ) : array();

        // Contadores por tipo
        $count_music   = 0; $count_auth = 0; $count_system = 0; $count_other = 0;
        foreach ( $logs as $l ) {
            $a = isset($l->action) ? $l->action : '';
            if ( strpos($a, 'music') !== false ) { $count_music++; }
            elseif ( strpos($a, 'login') !== false || strpos($a, 'logout') !== false ) { $count_auth++; }
            elseif ( strpos($a, 'system') !== false || strpos($a, 'cache') !== false || strpos($a, 'ranking') !== false ) { $count_system++; }
            else { $count_other++; }
        }

        // Atividade por hora (últimas 24h)
        $activity_by_hour = array_fill(0, 24, 0);
        $now = time();
        foreach ( $logs as $l ) {
            if ( empty($l->created_at) ) { continue; }
            $ts = strtotime($l->created_at);
            $diff_h = (int) floor(($now - $ts) / 3600);
            if ( $diff_h < 24 ) {
                $activity_by_hour[23 - $diff_h]++;
            }
        }

        $action_map = array(
            'music_published' => array('icon' => '🎵', 'label' => 'Música publicada',   'color' => '#1DB954'),
            'music_saved'     => array('icon' => '✏️',  'label' => 'Música salva',       'color' => '#B8700C'),
            'music_deleted'   => array('icon' => '🗑️',  'label' => 'Música excluída',    'color' => '#e74c3c'),
            'admin_login'     => array('icon' => '🔐',  'label' => 'Login admin',        'color' => '#3498db'),
            'admin_logout'    => array('icon' => '↩️',   'label' => 'Logout admin',       'color' => '#C9A27E'),
            'playlist_created'=> array('icon' => '📋',  'label' => 'Playlist criada',    'color' => '#9b59b6'),
            'cache_cleared'   => array('icon' => '🗑️',  'label' => 'Cache limpo',        'color' => '#e67e22'),
            'ranking_recalc'  => array('icon' => '🔄',  'label' => 'Ranking recalculado','color' => '#1DB954'),
        );
        ?>
        <div class="wrap" id="cv-logs-executive">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-logs-executive {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --bord:#F3E6D3;
            --text:#3B2418; --muted:#C9A27E; --green:#1DB954;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif;
            padding-bottom:40px;
        }
        #cv-logs-executive * { box-sizing:border-box; }
        .cv-log-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-log-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-log-title span { color:var(--gold); }
        .cv-log-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
        .cv-log-kpi { background:var(--card); border:1px solid var(--bord); border-radius:12px; padding:16px 14px; text-align:center; position:relative; overflow:hidden; }
        .cv-log-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:var(--kpi-cor,var(--gold)); }
        .cv-log-kpi-num { font-size:28px; font-weight:800; color:var(--kpi-cor,var(--gold)); line-height:1; }
        .cv-log-kpi-label { font-size:11px; color:var(--muted); margin-top:5px; text-transform:uppercase; letter-spacing:.4px; }
        /* Chart de atividade */
        .cv-log-chart-box { background:var(--card); border:1px solid var(--bord); border-radius:14px; padding:20px; margin-bottom:24px; }
        .cv-log-chart-title { font-size:13px; font-weight:700; color:#3B2418; margin:0 0 14px; }
        .cv-log-chart-title span { color:var(--muted); font-weight:400; font-size:11px; }
        .cv-log-bars { display:flex; align-items:flex-end; gap:3px; height:60px; }
        .cv-log-bar { flex:1; border-radius:3px 3px 0 0; background:var(--gold); opacity:.4; transition:opacity .2s; min-height:2px; }
        .cv-log-bar:hover { opacity:1; }
        .cv-log-bar.has-events { opacity:.8; }
        /* Filtros */
        .cv-log-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .cv-log-filter-btn { padding:6px 14px; border-radius:20px; border:1px solid var(--bord); background:transparent; color:var(--muted); font-size:12px; cursor:pointer; transition:all .2s; font-family:inherit; }
        .cv-log-filter-btn.active, .cv-log-filter-btn:hover { border-color:var(--gold); color:var(--gold); background:rgba(242,165,26,0.1); }
        /* Timeline */
        .cv-log-timeline { position:relative; }
        .cv-log-timeline::before { content:''; position:absolute; left:20px; top:0; bottom:0; width:2px; background:var(--bord); }
        .cv-log-entry { display:flex; gap:14px; padding:12px 0; position:relative; }
        .cv-log-entry:last-child .cv-log-entry-line { display:none; }
        .cv-log-dot { width:42px; flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:4px; }
        .cv-log-dot-circle { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; border:2px solid var(--bord); background:var(--card); z-index:1; position:relative; }
        .cv-log-dot-line { flex:1; width:2px; background:transparent; }
        .cv-log-body { flex:1; background:var(--card); border:1px solid var(--bord); border-radius:10px; padding:12px 14px; transition:border-color .2s; }
        .cv-log-body:hover { border-color:rgba(201,162,126,0.6); }
        .cv-log-body-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .cv-log-label { font-size:13px; font-weight:600; color:#3B2418; }
        .cv-log-badge { font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; }
        .cv-log-user { font-size:11px; color:var(--gold); margin-left:auto; }
        .cv-log-desc { font-size:12px; color:var(--muted); margin-top:5px; }
        .cv-log-meta { display:flex; gap:12px; margin-top:6px; font-size:10px; color:#8A6A55; }
        .cv-log-empty { text-align:center; padding:60px; color:var(--muted); }
        /* Categorias ocultas */
        .cv-log-entry[data-cat].hidden { display:none; }
        </style>

        <div class="cv-log-header">
            <h1 class="cv-log-title">📋 Logs <span>do Sistema</span></h1>
            <div style="font-size:12px;color:var(--muted)">Últimas <?php echo count($logs); ?> ações registradas</div>
        </div>

        <?php echo CV_Admin::btn_voltar(); ?>

        <!-- KPIs -->
        <div class="cv-log-kpis">
            <div class="cv-log-kpi" style="--kpi-cor:#1DB954">
                <div class="cv-log-kpi-num" data-target="<?php echo $count_music; ?>">0</div>
                <div class="cv-log-kpi-label">🎵 Músicas</div>
            </div>
            <div class="cv-log-kpi" style="--kpi-cor:#3498db">
                <div class="cv-log-kpi-num" data-target="<?php echo $count_auth; ?>">0</div>
                <div class="cv-log-kpi-label">🔐 Acessos</div>
            </div>
            <div class="cv-log-kpi" style="--kpi-cor:#e67e22">
                <div class="cv-log-kpi-num" data-target="<?php echo $count_system; ?>">0</div>
                <div class="cv-log-kpi-label">⚙️ Sistema</div>
            </div>
            <div class="cv-log-kpi" style="--kpi-cor:#9b59b6">
                <div class="cv-log-kpi-num" data-target="<?php echo $count_other; ?>">0</div>
                <div class="cv-log-kpi-label">📋 Outros</div>
            </div>
            <div class="cv-log-kpi" style="--kpi-cor:var(--gold)">
                <div class="cv-log-kpi-num" data-target="<?php echo count($logs); ?>">0</div>
                <div class="cv-log-kpi-label">Total</div>
            </div>
        </div>

        <!-- Mini gráfico de atividade 24h -->
        <div class="cv-log-chart-box">
            <div class="cv-log-chart-title">Atividade nas últimas 24h <span>— cada barra = 1 hora</span></div>
            <div class="cv-log-bars" id="cv-log-bars-wrap">
                <?php
                $max_h = max(1, max($activity_by_hour));
                foreach ($activity_by_hour as $h => $cnt):
                    $pct = round($cnt / $max_h * 100);
                    $has = $cnt > 0 ? 'has-events' : '';
                    $title = ($h == 23) ? 'Agora' : (23-$h).'h atrás';
                ?>
                <div class="cv-log-bar <?php echo $has; ?>"
                     style="height:<?php echo max(4,$pct); ?>%"
                     title="<?php echo $title; ?>: <?php echo $cnt; ?> evento(s)"></div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Filtros -->
        <div class="cv-log-filters">
            <button class="cv-log-filter-btn active" onclick="cvLogFiltrar(this,'all')">Todos</button>
            <button class="cv-log-filter-btn" onclick="cvLogFiltrar(this,'music')">🎵 Músicas</button>
            <button class="cv-log-filter-btn" onclick="cvLogFiltrar(this,'auth')">🔐 Acessos</button>
            <button class="cv-log-filter-btn" onclick="cvLogFiltrar(this,'system')">⚙️ Sistema</button>
            <button class="cv-log-filter-btn" onclick="cvLogFiltrar(this,'other')">📋 Outros</button>
        </div>

        <!-- Timeline -->
        <?php if ( empty($logs) ) : ?>
        <div class="cv-log-empty">
            <div style="font-size:48px;margin-bottom:12px">📋</div>
            <div style="font-size:16px;color:#3B2418;margin-bottom:6px">Nenhum log registrado ainda</div>
            <div style="font-size:13px">As ações serão registradas automaticamente conforme você usa o painel.</div>
        </div>
        <?php else : ?>
        <div class="cv-log-timeline" id="cv-log-timeline">
        <?php foreach ($logs as $log):
            $action = isset($log->action) ? $log->action : 'other';
            $map    = isset($action_map[$action]) ? $action_map[$action] : array('icon'=>'⚙️','label'=>$action,'color'=>'#C9A27E');
            $icon   = $map['icon']; $label = $map['label']; $cor = $map['color'];
            // Categoria para filtro
            if ( strpos($action,'music') !== false ) { $cat = 'music'; }
            elseif ( strpos($action,'login') !== false || strpos($action,'logout') !== false ) { $cat = 'auth'; }
            elseif ( strpos($action,'system') !== false || strpos($action,'cache') !== false || strpos($action,'ranking') !== false ) { $cat = 'system'; }
            else { $cat = 'other'; }
            $ts   = isset($log->created_at) ? strtotime($log->created_at) : 0;
            $data = $ts ? date('d/m/Y', $ts) : '';
            $hora = $ts ? date('H:i', $ts) : '';
            $user = isset($log->user_name) && $log->user_name ? $log->user_name : 'Sistema';
            $ip   = isset($log->ip_address) ? $log->ip_address : '';
            $desc = isset($log->description) ? $log->description : '';
        ?>
        <div class="cv-log-entry" data-cat="<?php echo esc_attr($cat); ?>">
            <div class="cv-log-dot">
                <div class="cv-log-dot-circle" style="border-color:<?php echo esc_attr($cor); ?>;background:<?php echo esc_attr($cor); ?>18">
                    <span style="font-size:10px"><?php echo $icon; ?></span>
                </div>
            </div>
            <div class="cv-log-body">
                <div class="cv-log-body-top">
                    <span class="cv-log-label"><?php echo esc_html($label); ?></span>
                    <span class="cv-log-badge" style="background:<?php echo esc_attr($cor); ?>22;color:<?php echo esc_attr($cor); ?>;border:1px solid <?php echo esc_attr($cor); ?>44">
                        <?php echo esc_html($cat); ?>
                    </span>
                    <span class="cv-log-user">👤 <?php echo esc_html($user); ?></span>
                </div>
                <?php if ($desc) : ?>
                <div class="cv-log-desc"><?php echo esc_html($desc); ?></div>
                <?php endif; ?>
                <div class="cv-log-meta">
                    <?php if ($data) : ?><span>📅 <?php echo esc_html($data); ?></span><?php endif; ?>
                    <?php if ($hora) : ?><span>🕐 <?php echo esc_html($hora); ?></span><?php endif; ?>
                    <?php if ($ip) : ?><span>🌐 <?php echo esc_html($ip); ?></span><?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <script>
        (function(){
            // Animação KPIs
            document.querySelectorAll('.cv-log-kpi-num[data-target]').forEach(function(el){
                var t = parseInt(el.dataset.target,10);
                if(!t){return;}
                var s=0,d=800,st=null;
                function step(ts){
                    if(!st)st=ts;
                    var p=Math.min((ts-st)/d,1);
                    el.textContent=Math.floor((1-Math.pow(1-p,3))*t);
                    if(p<1)requestAnimationFrame(step);
                }
                requestAnimationFrame(step);
            });
            // Filtro
            window.cvLogFiltrar = function(btn, cat) {
                document.querySelectorAll('.cv-log-filter-btn').forEach(function(b){b.classList.remove('active');});
                btn.classList.add('active');
                document.querySelectorAll('.cv-log-entry[data-cat]').forEach(function(e){
                    if(cat==='all'||e.dataset.cat===cat){e.classList.remove('hidden');}
                    else{e.classList.add('hidden');}
                });
            };
        })();
        </script>
        </div>
        <?php
    }

    // ─── PERMISSÕES ──────────────────────────────────────────────
    public static function page_roles() {
        // Salvar permissão
        if ( isset( $_POST['cv_save_role'] ) && check_admin_referer( 'cv_role_save' ) ) {
            if ( current_user_can( 'manage_options' ) ) {
                $target_uid  = absint( $_POST['cv_role_user_id'] );
                $target_role = sanitize_text_field( isset($_POST['cv_role_value']) ? $_POST['cv_role_value'] : '' );
                $allowed     = array( 'subscriber', 'cv_editor', 'cv_gerente', 'cv_master' );
                if ( in_array( $target_role, $allowed, true ) && $target_uid ) {
                    $user = new WP_User( $target_uid );
                    $user->set_role( $target_role );
                    $saved_role = true;
                }
            }
        }

        $users = get_users( array( 'number' => 200, 'orderby' => 'display_name' ) );

        $roles_def = array(
            'subscriber'    => array(
                'label' => 'Ouvinte', 'icon' => '🎧', 'cor' => '#3498db',
                'desc'  => 'Acessa o site, ouve músicas, cria playlists e favorita. Sem acesso ao painel admin.',
                'perms' => array('Ouvir músicas', 'Criar playlists', 'Favoritar', 'Avaliar com estrelas'),
            ),
            'cv_editor'  => array(
                'label' => 'Editor CV', 'icon' => '✏️', 'cor' => '#1DB954',
                'desc'  => 'Pode criar e editar músicas no painel. Ideal para colaboradores de conteúdo.',
                'perms' => array('Tudo do Ouvinte', 'Criar músicas', 'Editar músicas', 'Painel admin (restrito)'),
            ),
            'cv_gerente'  => array(
                'label' => 'Gerente CV', 'icon' => '🎛️', 'cor' => '#B8700C',
                'desc'  => 'Editor com poderes extras: excluir músicas, gerenciar playlists e usuários.',
                'perms' => array('Tudo do Editor', 'Excluir músicas', 'Gerenciar playlists', 'Ver usuários'),
            ),
            'cv_master'  => array(
                'label' => 'Master CV', 'icon' => '👑', 'cor' => '#9b59b6',
                'desc'  => 'Gerente + acesso a configurações, logs e recalcular ranking. Sem acesso ao WP nativo.',
                'perms' => array('Tudo do Gerente', 'Configurações', 'Ver logs', 'Recalcular ranking'),
            ),
            'administrator' => array(
                'label' => 'Administrador', 'icon' => '🔑', 'cor' => '#e74c3c',
                'desc'  => 'Acesso total ao WordPress. Não altere administradores por aqui — use o painel nativo do WP.',
                'perms' => array('Acesso total WP', 'Todos os painéis', 'Instalar plugins', 'Alterar temas'),
            ),
        );

        // Contagem por role
        $role_counts = array();
        foreach ($roles_def as $rk => $rd) {
            $role_counts[$rk] = count( get_users( array('role' => $rk, 'fields' => 'ID') ) );
        }
        ?>
        <div class="wrap" id="cv-roles-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-roles-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --bord:#F3E6D3;
            --text:#3B2418; --muted:#C9A27E;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:48px;
        }
        #cv-roles-exec * { box-sizing:border-box; }
        .cv-rol-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-rol-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-rol-title span { color:var(--gold); }
        /* Cards de role */
        .cv-rol-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:14px; margin-bottom:28px; }
        .cv-rol-card { background:var(--card); border:2px solid var(--bord); border-radius:14px; padding:18px; transition:border-color .2s,transform .15s; }
        .cv-rol-card:hover { transform:translateY(-2px); }
        .cv-rol-card-top { display:flex; align-items:center; gap:10px; margin-bottom:12px; }
        .cv-rol-icon { font-size:28px; }
        .cv-rol-name { font-size:15px; font-weight:700; color:#3B2418; }
        .cv-rol-count { font-size:11px; margin-top:2px; }
        .cv-rol-desc { font-size:12px; color:var(--muted); line-height:1.5; margin-bottom:12px; }
        .cv-rol-perms { display:flex; flex-direction:column; gap:4px; }
        .cv-rol-perm { font-size:11px; color:var(--muted); display:flex; align-items:center; gap:6px; }
        .cv-rol-perm::before { content:'✓'; font-size:10px; font-weight:700; }
        /* Formulário de atribuição */
        .cv-rol-assign-box { background:var(--card); border:1px solid var(--bord); border-radius:14px; padding:22px; }
        .cv-rol-assign-title { font-size:15px; font-weight:700; color:#3B2418; margin:0 0 18px; }
        .cv-rol-assign-grid { display:grid; grid-template-columns:1fr 1fr auto; gap:12px; align-items:end; }
        @media (max-width:800px) { .cv-rol-assign-grid { grid-template-columns:1fr; } }
        .cv-rol-field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .cv-rol-select { width:100%; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-rol-select:focus { border-color:var(--gold); }
        .cv-rol-select option { background:#F8F0E4; }
        .cv-rol-btn-save { background:var(--gold); color:#3B2418; border:none; padding:10px 20px; border-radius:8px; font-weight:700; font-size:13px; cursor:pointer; white-space:nowrap; font-family:inherit; transition:opacity .2s; }
        .cv-rol-btn-save:hover { opacity:.85; }
        .cv-rol-notice { background:rgba(29,185,84,.08); border:1px solid rgba(29,185,84,.3); color:#137B38; border-radius:8px; padding:10px 14px; font-size:13px; margin-bottom:18px; }
        .cv-rol-warn { font-size:11px; color:var(--muted); margin-top:14px; padding:10px 14px; background:rgba(231,76,60,.06); border:1px solid rgba(231,76,60,.15); border-radius:8px; }
        </style>

        <div class="cv-rol-topbar">
            <h1 class="cv-rol-title">🔐 <span>Permissões</span></h1>
        </div>
        <?php echo CV_Admin::btn_voltar(); ?>

        <?php if ( isset($saved_role) && $saved_role ) : ?>
        <div class="cv-rol-notice">✅ Permissão atualizada com sucesso!</div>
        <?php endif; ?>

        <!-- Cards de níveis -->
        <div class="cv-rol-grid">
        <?php foreach ($roles_def as $rk => $rd) :
            $cor   = $rd['cor'];
            $count = isset($role_counts[$rk]) ? $role_counts[$rk] : 0;
        ?>
        <div class="cv-rol-card" style="border-color:<?php echo esc_attr($cor); ?>33">
            <div class="cv-rol-card-top">
                <span class="cv-rol-icon"><?php echo $rd['icon']; ?></span>
                <div>
                    <div class="cv-rol-name" style="color:<?php echo esc_attr($cor); ?>"><?php echo esc_html($rd['label']); ?></div>
                    <div class="cv-rol-count" style="color:<?php echo esc_attr($cor); ?>99">
                        <?php echo $count; ?> usuário<?php echo $count !== 1 ? 's' : ''; ?>
                    </div>
                </div>
            </div>
            <div class="cv-rol-desc"><?php echo esc_html($rd['desc']); ?></div>
            <div class="cv-rol-perms">
            <?php foreach ($rd['perms'] as $perm) : ?>
                <div class="cv-rol-perm" style="--perm-cor:<?php echo esc_attr($cor); ?>;color:<?php echo esc_attr($cor); ?>aa">
                    <?php echo esc_html($perm); ?>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>

        <!-- Formulário de atribuição -->
        <div class="cv-rol-assign-box">
            <div class="cv-rol-assign-title">👤 Atribuir Nível a um Usuário</div>
            <form method="post">
                <?php wp_nonce_field( 'cv_role_save' ); ?>
                <div class="cv-rol-assign-grid">
                    <div class="cv-rol-field">
                        <label>Usuário</label>
                        <select name="cv_role_user_id" class="cv-rol-select">
                            <option value="">Selecione o usuário...</option>
                            <?php foreach ($users as $u) :
                                $roles_u = $u->roles;
                                $current = ! empty($roles_u) ? $roles_u[0] : 'subscriber';
                                $cur_label = isset($roles_def[$current]) ? $roles_def[$current]['label'] : $current;
                            ?>
                            <option value="<?php echo (int)$u->ID; ?>">
                                <?php echo esc_html($u->display_name); ?> — <?php echo esc_html($cur_label); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cv-rol-field">
                        <label>Novo nível</label>
                        <select name="cv_role_value" class="cv-rol-select">
                            <?php foreach ($roles_def as $rk => $rd) :
                                if ($rk === 'administrator') { continue; } // não expor admin aqui
                            ?>
                            <option value="<?php echo esc_attr($rk); ?>">
                                <?php echo $rd['icon']; ?> <?php echo esc_html($rd['label']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <button type="submit" name="cv_save_role" value="1" class="cv-rol-btn-save">
                            ✅ Atribuir
                        </button>
                    </div>
                </div>
                <div class="cv-rol-warn">
                    ⚠️ Administradores WordPress não aparecem na lista acima. Para alterar administradores, use o painel nativo do WordPress em Usuários → Todos os Usuários.
                </div>
            </form>
        </div>
        </div><!-- wrap -->
        <?php
    }

    // ─── IMPORTADOR YOUTUBE ───────────────────────────────────────
    public static function page_youtube_import() {
        wp_enqueue_media();

        $yt_key = get_option( 'cv_youtube_api_key', '' );
        if ( isset( $_POST['cv_save_yt_key'] ) && check_admin_referer( 'cv_yt_key_save' ) ) {
            $yt_key = sanitize_text_field( $_POST['cv_youtube_api_key'] );
            update_option( 'cv_youtube_api_key', $yt_key );
            $yt_saved = true;
        } else {
            $yt_saved = false;
        }
        $api_ok = ! empty( $yt_key );
        ?>
        <div class="wrap" id="cv-yt-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-yt-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --bord:#F3E6D3;
            --text:#3B2418; --muted:#C9A27E; --green:#1DB954; --red:#e74c3c; --yt:#FF0000;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:48px;
        }
        #cv-yt-exec * { box-sizing:border-box; }
        .cv-yt-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-yt-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-yt-title span { color:var(--yt); }
        .cv-yt-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .cv-yt-pill.ok  { background:rgba(29,185,84,.1); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-yt-pill.off { background:rgba(255,0,0,.08); border:1px solid rgba(255,0,0,.25); color:#DB0000; }
        /* Grid layout */
        .cv-yt-layout { display:grid; grid-template-columns:320px 1fr; gap:20px; align-items:start; }
        @media (max-width:1000px) { .cv-yt-layout { grid-template-columns:1fr; } }
        /* Cards laterais */
        .cv-yt-side { display:flex; flex-direction:column; gap:16px; }
        .cv-yt-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-yt-card-hdr { padding:14px 18px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:10px; }
        .cv-yt-card-hdr-icon { font-size:18px; }
        .cv-yt-card-hdr-title { font-size:14px; font-weight:700; color:#3B2418; }
        .cv-yt-card-body { padding:18px; }
        /* API form */
        .cv-yt-api-row { display:flex; gap:8px; }
        .cv-yt-input { flex:1; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-yt-input:focus { border-color:var(--gold); }
        .cv-yt-btn { padding:9px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; border:none; font-family:inherit; transition:opacity .2s; }
        .cv-yt-btn:hover { opacity:.85; }
        .cv-yt-btn-save { background:var(--gold); color:#3B2418; }
        .cv-yt-btn-primary { background:var(--yt); color:#3B2418; width:100%; justify-content:center; display:flex; align-items:center; gap:8px; padding:11px; }
        .cv-yt-hint { font-size:11px; color:var(--muted); margin-top:8px; line-height:1.5; }
        /* Busca */
        .cv-yt-search-row { display:flex; gap:8px; margin-bottom:14px; }
        .cv-yt-channel-input { flex:1; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:10px 14px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-yt-channel-input:focus { border-color:var(--yt); }
        /* Stats bar */
        .cv-yt-statsbar { display:flex; gap:16px; padding:12px 0; border-bottom:1px solid var(--bord); margin-bottom:16px; flex-wrap:wrap; }
        .cv-yt-stat { text-align:center; }
        .cv-yt-stat-num { font-size:20px; font-weight:800; color:var(--gold); }
        .cv-yt-stat-label { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        /* Progress */
        .cv-yt-progress-wrap { margin:14px 0; display:none; }
        .cv-yt-progress-bar { background:rgba(123,58,34,0.07); border-radius:6px; height:8px; overflow:hidden; margin-bottom:8px; }
        .cv-yt-progress-fill { height:100%; background:linear-gradient(90deg,var(--yt),#ff6b6b); width:0%; transition:width .3s; border-radius:6px; }
        .cv-yt-progress-txt { font-size:12px; color:var(--muted); }
        /* Result cards */
        .cv-yt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; }
        .cv-yt-video-card {
            background:rgba(123,58,34,0.03); border:1px solid var(--bord);
            border-radius:10px; overflow:hidden; cursor:pointer;
            transition:border-color .2s, transform .15s;
            position:relative;
        }
        .cv-yt-video-card:hover { border-color:rgba(201,162,126,0.8); transform:translateY(-2px); }
        .cv-yt-video-card.selected { border-color:var(--gold); background:rgba(242,165,26,0.08); }
        .cv-yt-video-card.imported { border-color:rgba(29,185,84,.4); opacity:.6; }
        .cv-yt-thumb { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; }
        .cv-yt-video-info { padding:10px; }
        .cv-yt-video-title { font-size:12px; font-weight:600; color:var(--text); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .cv-yt-video-date { font-size:10px; color:var(--muted); margin-top:4px; }
        .cv-yt-check { position:absolute; top:6px; right:6px; width:22px; height:22px; border-radius:50%; background:var(--gold); color:#3B2418; font-size:12px; font-weight:700; display:none; align-items:center; justify-content:center; }
        .cv-yt-video-card.selected .cv-yt-check { display:flex; }
        .cv-yt-imported-badge { position:absolute; top:6px; left:6px; background:rgba(29,185,84,.9); color:#3B2418; font-size:9px; font-weight:700; padding:2px 6px; border-radius:10px; }
        /* Status */
        .cv-yt-status-msg { font-size:13px; color:var(--gold); padding:10px 0; display:none; }
        .cv-yt-done-msg { display:none; }
        /* Toolbar */
        .cv-yt-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .cv-yt-btn-outline { background:rgba(123,58,34,0.06); border:1px solid var(--bord); color:var(--text); padding:7px 14px; }
        .cv-yt-btn-import { background:var(--yt); color:#3B2418; padding:9px 20px; }
        .cv-yt-selected-count { font-size:12px; color:var(--muted); }
        .cv-yt-loading { text-align:center; padding:40px; color:var(--muted); display:none; }
        .cv-yt-spinner { display:inline-block; width:24px; height:24px; border:3px solid rgba(123,58,34,0.16); border-top-color:var(--yt); border-radius:50%; animation:cv-spin .7s linear infinite; }
        @keyframes cv-spin { to { transform:rotate(360deg); } }
        </style>

        <div class="cv-yt-topbar">
            <h1 class="cv-yt-title">▶ Importar do <span>YouTube</span></h1>
            <span class="cv-yt-pill <?php echo $api_ok ? 'ok' : 'off'; ?>">
                <?php echo $api_ok ? '✅ API conectada' : '❌ API não configurada'; ?>
            </span>
        </div>
        <?php echo CV_Admin::btn_voltar(); ?>

        <div class="cv-yt-layout">

            <!-- Sidebar -->
            <div class="cv-yt-side">

                <!-- API Key -->
                <div class="cv-yt-card">
                    <div class="cv-yt-card-hdr">
                        <span class="cv-yt-card-hdr-icon">🔑</span>
                        <div class="cv-yt-card-hdr-title">Google API Key</div>
                    </div>
                    <div class="cv-yt-card-body">
                        <?php if ($yt_saved): ?>
                        <div style="background:rgba(29,185,84,.08);border:1px solid rgba(29,185,84,.25);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--green);margin-bottom:12px">✅ API Key salva!</div>
                        <?php endif; ?>
                        <form method="post">
                            <?php wp_nonce_field( 'cv_yt_key_save' ); ?>
                            <div class="cv-yt-api-row">
                                <input type="password" name="cv_youtube_api_key"
                                       value="<?php echo esc_attr($yt_key); ?>"
                                       class="cv-yt-input" placeholder="AIza..." />
                                <input type="hidden" name="cv_save_yt_key" value="1" />
                                <button type="submit" class="cv-yt-btn cv-yt-btn-save">💾</button>
                            </div>
                        </form>
                        <div class="cv-yt-hint">
                            <strong style="color:var(--text)">Como obter:</strong><br>
                            1. console.cloud.google.com<br>
                            2. Criar projeto → Ativar "YouTube Data API v3"<br>
                            3. Credenciais → Criar chave de API<br>
                            <span style="color:#8A6A55">Cota gratuita: 10.000 req/dia</span>
                        </div>
                    </div>
                </div>

                <?php if ($api_ok): ?>
                <!-- Busca por canal -->
                <div class="cv-yt-card">
                    <div class="cv-yt-card-hdr">
                        <span class="cv-yt-card-hdr-icon">📡</span>
                        <div class="cv-yt-card-hdr-title">Buscar Canal</div>
                    </div>
                    <div class="cv-yt-card-body">
                        <input type="text" id="cv-yt-channel" class="cv-yt-channel-input"
                               value="cancaoverdadeira" placeholder="Handle do canal (sem @)" />
                        <div style="margin-top:10px">
                            <button id="cv-yt-fetch" class="cv-yt-btn cv-yt-btn-primary">
                                <span>▶</span> Buscar Vídeos
                            </button>
                        </div>
                        <div class="cv-yt-hint">Digite o handle do canal (o que aparece após @) e clique em Buscar.</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="cv-yt-card">
                    <div class="cv-yt-card-body" style="text-align:center;padding:30px">
                        <div style="font-size:36px;margin-bottom:12px">🔑</div>
                        <div style="font-size:13px;color:var(--muted)">Configure a API Key ao lado para começar a importar vídeos do YouTube.</div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- side -->

            <!-- Área principal de resultados -->
            <div class="cv-yt-card" style="min-height:300px">
                <div class="cv-yt-card-hdr">
                    <span class="cv-yt-card-hdr-icon">🎬</span>
                    <div class="cv-yt-card-hdr-title">Vídeos do Canal</div>
                </div>
                <div class="cv-yt-card-body">

                    <div id="cv-yt-status" class="cv-yt-status-msg"></div>

                    <!-- Loading -->
                    <div class="cv-yt-loading" id="cv-yt-loading">
                        <div class="cv-yt-spinner"></div>
                        <div style="margin-top:12px;font-size:13px">Conectando ao YouTube...</div>
                    </div>

                    <!-- Stats + toolbar (ocultos até buscar) -->
                    <div id="cv-yt-results" style="display:none">
                        <div class="cv-yt-statsbar">
                            <div class="cv-yt-stat">
                                <div class="cv-yt-stat-num" id="cv-yt-total-count">0</div>
                                <div class="cv-yt-stat-label">Encontrados</div>
                            </div>
                            <div class="cv-yt-stat">
                                <div class="cv-yt-stat-num" id="cv-yt-new-count" style="color:var(--green)">0</div>
                                <div class="cv-yt-stat-label">Novos</div>
                            </div>
                            <div class="cv-yt-stat">
                                <div class="cv-yt-stat-num" id="cv-yt-sel-count" style="color:var(--yt)">0</div>
                                <div class="cv-yt-stat-label">Selecionados</div>
                            </div>
                        </div>

                        <div class="cv-yt-toolbar">
                            <button id="cv-yt-select-all" class="cv-yt-btn cv-yt-btn-outline">☑ Selecionar Todos</button>
                            <button id="cv-yt-select-none" class="cv-yt-btn cv-yt-btn-outline">☐ Limpar Seleção</button>
                            <button id="cv-yt-import-selected" class="cv-yt-btn cv-yt-btn-import">⬇ Importar Selecionados</button>
                        </div>

                        <!-- Progress bar -->
                        <div class="cv-yt-progress-wrap" id="cv-yt-progress">
                            <div class="cv-yt-progress-bar">
                                <div class="cv-yt-progress-fill" id="cv-yt-bar"></div>
                            </div>
                            <div class="cv-yt-progress-txt" id="cv-yt-progress-text"></div>
                        </div>

                        <!-- Resultado final -->
                        <div class="cv-yt-done-msg" id="cv-yt-done"></div>

                        <!-- Grid de vídeos -->
                        <div class="cv-yt-grid" id="cv-yt-list"></div>
                    </div>

                    <!-- Estado vazio inicial -->
                    <div id="cv-yt-empty" style="text-align:center;padding:60px 20px;color:var(--muted)">
                        <div style="font-size:48px;margin-bottom:14px">▶</div>
                        <div style="font-size:15px;color:#8A6A55;margin-bottom:6px">Nenhuma busca realizada</div>
                        <div style="font-size:12px">Insira o handle do canal e clique em Buscar Vídeos.</div>
                    </div>

                </div>
            </div>

        </div><!-- layout -->
        </div><!-- wrap -->

        <script>
        jQuery(function($){
            var videos  = [];
            var apiKey  = '<?php echo esc_js( $yt_key ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
            var nonce   = '<?php echo esc_js( wp_create_nonce("cv_admin_nonce") ); ?>';

            function updateSelCount() {
                var n = $('.cv-yt-video-card.selected:not(.imported)').length;
                $('#cv-yt-sel-count').text(n);
            }

            // Toggle seleção
            $(document).on('click', '.cv-yt-video-card:not(.imported)', function(){
                $(this).toggleClass('selected');
                updateSelCount();
            });

            $('#cv-yt-select-all').on('click', function(){
                $('.cv-yt-video-card:not(.imported)').addClass('selected');
                updateSelCount();
            });
            $('#cv-yt-select-none').on('click', function(){
                $('.cv-yt-video-card').removeClass('selected');
                updateSelCount();
            });

            // Buscar vídeos
            $('#cv-yt-fetch').on('click', function(){
                var channel = $('#cv-yt-channel').val().trim();
                if (!channel) return;
                videos = [];
                $('#cv-yt-results,#cv-yt-done,#cv-yt-empty').hide();
                $('#cv-yt-loading').show();
                $('#cv-yt-list').empty();
                $('#cv-yt-status').hide();
                $(this).prop('disabled', true).html('<span class="cv-yt-spinner"></span>');
                fetchChannelId(channel);
            });

            function fetchChannelId(handle) {
                $.getJSON(
                    'https://www.googleapis.com/youtube/v3/channels',
                    { part:'id,snippet', forHandle: handle, key: apiKey },
                    function(data){
                        if (!data.items || !data.items.length) {
                            showStatus('Canal não encontrado. Verifique o handle.', true);
                            resetBtn(); return;
                        }
                        fetchPlaylistId(data.items[0].id);
                    }
                ).fail(function(){ showStatus('Erro ao conectar ao YouTube. Verifique a API Key.', true); resetBtn(); });
            }

            function fetchPlaylistId(channelId) {
                $.getJSON(
                    'https://www.googleapis.com/youtube/v3/channels',
                    { part:'contentDetails', id: channelId, key: apiKey },
                    function(data){
                        var pid = data.items[0].contentDetails.relatedPlaylists.uploads;
                        fetchVideos(pid, null);
                    }
                );
            }

            function fetchVideos(playlistId, pageToken) {
                var params = { part:'snippet', playlistId: playlistId, maxResults: 50, key: apiKey };
                if (pageToken) { params.pageToken = pageToken; }
                $.getJSON('https://www.googleapis.com/youtube/v3/playlistItems', params, function(data){
                    data.items.forEach(function(item){
                        var s = item.snippet;
                        if (s.title === 'Private video' || s.title === 'Deleted video') return;
                        videos.push({
                            videoId : s.resourceId.videoId,
                            title   : s.title,
                            thumb   : (s.thumbnails.medium || s.thumbnails.default || {url:''}).url,
                            date    : s.publishedAt ? s.publishedAt.substr(0,10) : ''
                        });
                    });
                    if (data.nextPageToken && videos.length < 500) {
                        fetchVideos(playlistId, data.nextPageToken);
                    } else {
                        renderVideos();
                    }
                }).fail(function(){ showStatus('Erro ao buscar vídeos.', true); resetBtn(); });
            }

            function renderVideos() {
                $('#cv-yt-loading').hide();
                $('#cv-yt-empty').hide();
                var html = '';
                videos.forEach(function(v){
                    var ytUrl = 'https://www.youtube.com/watch?v=' + v.videoId;
                    html += '<div class="cv-yt-video-card" data-id="' + escHtml(v.videoId) + '">'
                          + '<div class="cv-yt-check">✓</div>'
                          + '<img src="' + escHtml(v.thumb) + '" class="cv-yt-thumb" loading="lazy" alt="">'
                          + '<div class="cv-yt-video-info">'
                          + '<div class="cv-yt-video-title">' + escHtml(v.title) + '</div>'
                          + '<div class="cv-yt-video-date">📅 ' + escHtml(v.date) + ' &nbsp; <a href="' + escHtml(ytUrl) + '" target="_blank" style="color:var(--yt);font-size:10px" onclick="event.stopPropagation()">▶ Ver</a></div>'
                          + '</div></div>';
                });
                $('#cv-yt-list').html(html);
                $('#cv-yt-total-count').text(videos.length);
                $('#cv-yt-new-count').text(videos.length);
                $('#cv-yt-sel-count').text(0);
                $('#cv-yt-results').show();
                resetBtn();
            }

            // Importar selecionados
            $('#cv-yt-import-selected').on('click', function(){
                var items = [];
                $('.cv-yt-video-card.selected:not(.imported)').each(function(){
                    var id = $(this).data('id');
                    var v  = videos.find(function(x){ return x.videoId === id; });
                    if (v) items.push(v);
                });
                if (!items.length) { alert('Selecione ao menos um vídeo.'); return; }
                $(this).prop('disabled', true);
                var done = 0, imported = 0, skipped = 0, falhas = 0, erros = [], total = items.length;
                $('#cv-yt-progress').show();
                $('#cv-yt-done').hide();

                function next() {
                    if (done >= total) {
                        $('#cv-yt-bar').css('width','100%');
                        $('#cv-yt-progress-text').text('Concluído!');
                        var corErros = falhas ? 'var(--red)' : 'var(--green)';
                        var bgErros  = falhas ? 'rgba(231,76,60,.08)' : 'rgba(29,185,84,.08)';
                        var bordErros = falhas ? 'rgba(231,76,60,.3)' : 'rgba(29,185,84,.3)';
                        var html = '<div style="background:' + bgErros + ';border:1px solid ' + bordErros + ';border-radius:10px;padding:14px 18px;font-size:13px;font-weight:600;color:' + corErros + ';margin-top:12px">'
                            + (falhas ? '⚠️ ' : '✅ ') + 'Importados: <strong>' + imported + '</strong> &nbsp;|&nbsp; Já existiam: <strong>' + skipped + '</strong>'
                            + (falhas ? ' &nbsp;|&nbsp; Falharam: <strong>' + falhas + '</strong>' : '') + '<br>';
                        if (falhas) {
                            html += '<div style="margin-top:8px;font-weight:400;font-size:12px;color:#DC1100;max-height:140px;overflow:auto">'
                                  + erros.map(escHtml).join('<br>') + '</div>';
                        }
                        html += '<a href="<?php echo esc_js( admin_url("admin.php?page=cv-publicacao-rapida") ); ?>" style="color:var(--gold);margin-top:8px;display:inline-block;font-size:12px">⚡ Ir para Publicação Acelerada →</a>'
                              + '</div>';
                        $('#cv-yt-done').html(html).show();
                        $('#cv-yt-import-selected').prop('disabled', false);
                        return;
                    }
                    var item = items[done];
                    var pct  = Math.round((done / total) * 100);
                    $('#cv-yt-bar').css('width', pct + '%');
                    $('#cv-yt-progress-text').text('Importando ' + (done+1) + ' de ' + total + ': ' + item.title);
                    var payload = {
                        url   : 'https://www.youtube.com/watch?v=' + item.videoId,
                        title : item.title,
                        thumb : item.thumb,
                        id    : item.videoId
                    };
                    $.ajax({
                        url: ajaxUrl, method: 'POST',
                        data: { action:'cv_import_youtube_video', nonce:nonce, video: JSON.stringify(payload) },
                        success: function(res){
                            if (res && res.success) {
                                if (res.data.skipped) {
                                    skipped++;
                                } else {
                                    imported++;
                                }
                                $('[data-id="' + item.videoId + '"]').addClass('imported').removeClass('selected');
                            } else {
                                falhas++;
                                var msg = (res && res.data && res.data.message) ? res.data.message : 'resposta inesperada do servidor';
                                erros.push(item.title + ': ' + msg);
                            }
                        },
                        error: function(jqXHR){
                            falhas++;
                            var txt = jqXHR.responseText || ('HTTP ' + jqXHR.status);
                            if (String(txt).trim() === '-1') {
                                txt = 'sessão expirada ou sem permissão — recarregue a página (F5) e tente de novo';
                            }
                            erros.push(item.title + ': ' + String(txt).substring(0, 200));
                        },
                        complete: function(){ done++; setTimeout(next, 300); }
                    });
                }
                next();
            });

            function showStatus(msg, err) {
                $('#cv-yt-status').text(msg).css('color', err ? 'var(--red)' : 'var(--gold)').show();
                $('#cv-yt-loading').hide();
                $('#cv-yt-empty').show();
            }
            function resetBtn() {
                $('#cv-yt-fetch').prop('disabled', false).html('<span>▶</span> Buscar Vídeos');
            }
            function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        });
        </script>
        <?php
    }
}

