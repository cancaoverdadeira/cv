<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-dashboard.php
// Página "Dashboard": KPIs, gráfico de plays 30 dias, Top 5 e feed de atividade.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Dashboard {

    public static function render() {
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
        .cv-ab-green     { background:#1DB95422;border-color:#1DB954;color:#137B38; } /* v2.29.0: Distribuição */
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-distribuicao')); ?>" class="cv-ab cv-ab-green">🚀 Distribuição</a>
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
}
