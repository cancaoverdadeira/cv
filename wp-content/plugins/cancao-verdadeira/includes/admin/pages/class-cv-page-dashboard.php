<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-dashboard.php
// Página "Dashboard": KPIs, gráfico de plays 30 dias, Top 5 e feed de atividade.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).
// v2.35.0: CSS e JS em assets/css|js/admin-dashboard.*

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Dashboard {

    public static function init() {
        // Prioridade 20: o CSS da tela sai depois do admin.css e o Chart.js já está registrado (CV_Admin, prioridade 10).
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ), 20 );
    }

    // ── CSS e JS da tela (v2.35.0: saíram do <style>/<script> em linha) ──

    public static function enqueue_assets() {
        if ( 'cancao-verdadeira' !== sanitize_key( $_GET['page'] ?? '' ) ) {
            return;
        }
        wp_enqueue_style(
            'cv-admin-dashboard',
            CV_PLUGIN_URL . 'assets/css/admin-dashboard.css',
            array(),
            CV_VERSION
        );
        // Só registra aqui: render() enfileira junto com os dados (window.cvDash).
        wp_register_script(
            'cv-admin-dashboard',
            CV_PLUGIN_URL . 'assets/js/admin-dashboard.js',
            array( 'jquery', 'chartjs' ),
            CV_VERSION,
            true
        );
    }

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
            if ( empty( get_post_meta( $pid, CV_Fields::YOUTUBE_URL, true ) ) ) $sem_yt++;
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

        <div class="cv-action-hub">
        <p class="cv-action-hub-title">Central de Ações</p>

        <!-- Músicas -->
        <div class="cv-action-group">
            <div class="cv-action-group-label"><span></span>🎵 Músicas <span></span></div>
            <div class="cv-action-btns">
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-musicas')); ?>" class="cv-ab cv-ab-gold">🗂 Gerenciar músicas</a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=musica')); ?>" class="cv-ab cv-ab-blue">🎵 Ver Músicas</a>
                <a href="<?php echo esc_url(admin_url('edit.php?post_type=musica&post_status=draft')); ?>" class="cv-ab cv-ab-orange">📝 Rascunhos</a>
                <a href="<?php echo esc_url(admin_url('post-new.php?post_type=musica')); ?>" class="cv-ab cv-ab-gray">+ Nova Música</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-youtube-import')); ?>" class="cv-ab cv-ab-red">▶ Importar YouTube</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-distribuicao')); ?>" class="cv-ab cv-ab-green">🚀 Distribuição</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-sentimentos')); ?>" class="cv-ab cv-ab-purple">🎭 Sentimentos</a>
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
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-appearance')); ?>" class="cv-ab cv-ab-gray">🎨 Aparência</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-settings')); ?>" class="cv-ab cv-ab-gray">⚙️ Configurações</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-roles')); ?>" class="cv-ab cv-ab-red">🔐 Permissões</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-seguranca')); ?>" class="cv-ab cv-ab-red">🛡️ Segurança</a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=cv-settings#cv-modo-lancamento')); ?>" class="cv-ab cv-ab-gray">🚀 Modo lançamento</a>
            </div>
        </div>

        </div><!-- .cv-action-hub -->

        <?php
        // JS da tela: assets/js/admin-dashboard.js (vai no rodapé).
        wp_enqueue_script( 'cv-admin-dashboard' );
        wp_add_inline_script( 'cv-admin-dashboard', 'window.cvDash = ' . wp_json_encode( array(
            'labels' => $chart_labels,
            'values' => $chart_values,
        ) ) . ';', 'before' );
        ?>

        </div><!-- .cv-dashboard-v2 -->
        <?php
    }
}

CV_Page_Dashboard::init();
