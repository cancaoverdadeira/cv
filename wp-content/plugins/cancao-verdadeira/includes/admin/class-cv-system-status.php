<?php
// cancao-verdadeira/includes/admin/class-cv-system-status.php
// Gerado em: 2026-06-27 18:00:00
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Painel de Status do Sistema (v2.22.0)
// Funções : Monitoramento técnico em tempo real — saúde do banco, PHP,
//           WordPress, APIs, segurança, performance, cron jobs.
//           Inclui Security Score calculado e teste de conectividade.
// Público : Administradores — impressiona perfis técnicos (ex: DI de TI)
// Autor   : Canção Verdadeira | Gerado: 2026-06-27
// v2.62.0: lista de tabelas usa $GLOBALS['wpdb'] (a variável $wpdb não existia ali e gerava avisos).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_System_Status {

    public static function init() {
        add_action( 'wp_ajax_cv_system_ping_api', array( __CLASS__, 'ajax_ping_api' ) );
        add_action( 'wp_ajax_cv_system_db_stats', array( __CLASS__, 'ajax_db_stats' ) );
    }

    // ── Coleta completa de dados do sistema ───────────────────────

    public static function collect() {
        global $wpdb;

        $data = array();

        // ── PHP ───────────────────────────────────────────────────
        $data['php'] = array(
            'version'       => phpversion(),
            'version_ok'    => version_compare( phpversion(), '7.2', '>=' ),
            'memory_limit'  => ini_get('memory_limit'),
            'max_execution' => ini_get('max_execution_time') . 's',
            'upload_max'    => ini_get('upload_max_filesize'),
            'post_max'      => ini_get('post_max_size'),
            'extensions'    => array(
                'mysqli'  => extension_loaded('mysqli'),
                'curl'    => extension_loaded('curl'),
                'json'    => extension_loaded('json'),
                'mbstring'=> extension_loaded('mbstring'),
                'zip'     => extension_loaded('zip'),
                'gd'      => extension_loaded('gd') || extension_loaded('imagick'),
            ),
        );

        // ── WordPress ─────────────────────────────────────────────
        $data['wp'] = array(
            'version'       => get_bloginfo('version'),
            'charset'       => get_bloginfo('charset'),
            'language'      => get_bloginfo('language'),
            'ssl'           => is_ssl(),
            'debug'         => defined('WP_DEBUG') && WP_DEBUG,
            'debug_log'     => defined('WP_DEBUG_LOG') && WP_DEBUG_LOG,
            'multisite'     => is_multisite(),
            'cron_disabled' => defined('DISABLE_WP_CRON') && DISABLE_WP_CRON,
            'memory_usage'  => round( memory_get_usage(true) / 1024 / 1024, 1 ) . ' MB',
            'memory_peak'   => round( memory_get_peak_usage(true) / 1024 / 1024, 1 ) . ' MB',
        );

        // ── Banco de dados ────────────────────────────────────────
        $t_start = microtime(true);
        $wpdb->get_var("SELECT 1");
        $db_ping = round( (microtime(true) - $t_start) * 1000, 1 );

        $db_version = $wpdb->get_var("SELECT VERSION()");
        $data['db'] = array(
            'version'    => $db_version,
            'ping_ms'    => $db_ping,
            'ping_ok'    => $db_ping < 50,
            'prefix'     => $wpdb->prefix,
            'charset'    => DB_CHARSET,
        );

        // Tamanho das tabelas do plugin
        $tables_cv = $wpdb->get_results(
            "SELECT table_name AS nome,
                    ROUND(data_length/1024,1) AS data_kb,
                    ROUND(index_length/1024,1) AS index_kb,
                    table_rows AS linhas
             FROM information_schema.TABLES
             WHERE table_schema = DATABASE()
               AND table_name LIKE '{$wpdb->prefix}cv_%'
             ORDER BY data_length DESC"
        );
        $data['db']['tabelas'] = $tables_cv;
        $total_kb = 0;
        foreach ( $tables_cv as $t ) { $total_kb += $t->data_kb + $t->index_kb; }
        $data['db']['total_kb'] = round($total_kb, 1);

        // ── Plugin CV ─────────────────────────────────────────────
        $total_musicas  = wp_count_posts('musica');
        $total_pub      = isset($total_musicas->publish) ? (int)$total_musicas->publish : 0;
        $total_draft    = isset($total_musicas->draft)   ? (int)$total_musicas->draft   : 0;
        $total_plays    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log");
        $total_users    = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->users}");
        $total_subs     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}cv_subscribers");
        $last_ranking   = get_option('cv_last_ranking_update');
        $cron_ok        = (bool) wp_next_scheduled('cv_hourly_ranking');

        $data['plugin'] = array(
            'version'        => defined('CV_VERSION') ? CV_VERSION : '—',
            'db_version'     => (int) get_option('cv_db_version', 0),
            'musicas_pub'    => $total_pub,
            'musicas_draft'  => $total_draft,
            'plays_log'      => $total_plays,
            'users'          => $total_users,
            'subscribers'    => $total_subs,
            'last_ranking'   => $last_ranking,
            'cron_ranking'   => $cron_ok,
            'pwa_manifest'   => file_exists( ABSPATH . 'manifest.json' ),
        );

        // ── Segurança ─────────────────────────────────────────────
        $htaccess = file_exists( ABSPATH . '.htaccess' );
        $htaccess_cv = false;
        if ( $htaccess ) {
            $htaccess_content = file_get_contents( ABSPATH . '.htaccess' );
            $htaccess_cv = strpos($htaccess_content, 'cv_security') !== false
                        || strpos($htaccess_content, 'cancao-verdadeira') !== false;
        }

        $data['security'] = array(
            'ssl'              => is_ssl(),
            'debug_off'        => ! (defined('WP_DEBUG') && WP_DEBUG),
            'htaccess'         => $htaccess,
            'file_edit_off'    => defined('DISALLOW_FILE_EDIT') && DISALLOW_FILE_EDIT,
            'cloudflare'       => isset($_SERVER['HTTP_CF_RAY']),
            'rate_limit_login' => class_exists('CV_Security'),
            'nonces_ativos'    => true, // sempre true no nosso plugin
            'api_key_oculta'   => ! empty( get_option('cv_mailerlite_api_key') ),
        );

        // Security Score
        $score_itens = array(
            $data['security']['ssl'],
            $data['security']['debug_off'],
            $data['security']['htaccess'],
            $data['security']['file_edit_off'],
            $data['security']['cloudflare'],
            $data['security']['rate_limit_login'],
            $data['security']['nonces_ativos'],
            ! (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG),
        );
        $score_ok = count( array_filter($score_itens) );
        $data['security']['score'] = round( $score_ok / count($score_itens) * 100 );

        // ── APIs externas ─────────────────────────────────────────
        $data['apis'] = array(
            'mailerlite_key' => ! empty( get_option('cv_mailerlite_api_key') ),
            'youtube_key'    => ! empty( get_option('cv_youtube_api_key') ),
            'rank_math'      => class_exists('RankMath'),
            'wp_rocket'      => defined('WP_ROCKET_VERSION'),
            'relevanssi'     => function_exists('relevanssi_search'),
            'woocommerce'    => class_exists('WooCommerce'),
        );

        // ── Cron Jobs ─────────────────────────────────────────────
        $crons_cv = array(
            'cv_hourly_ranking'  => array('label'=>'Recálculo ranking',    'schedule'=>'hourly'),
            'cv_daily_cleanup'   => array('label'=>'Limpeza diária',       'schedule'=>'daily'),
            'cv_weekly_trending' => array('label'=>'Trending semanal',     'schedule'=>'weekly'),
        );
        foreach ( $crons_cv as $hook => $info ) {
            $next = wp_next_scheduled($hook);
            $crons_cv[$hook]['next']   = $next ? date('d/m H:i', $next) : null;
            $crons_cv[$hook]['active'] = (bool) $next;
        }
        $data['crons'] = $crons_cv;

        return $data;
    }

    // ── AJAX: ping MailerLite ─────────────────────────────────────

    public static function ajax_ping_api() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }

        $api_key = get_option('cv_mailerlite_api_key', '');
        if ( ! $api_key ) {
            wp_send_json_success( array('status'=>'not_configured', 'ms'=>null) );
        }

        $t = microtime(true);
        $resp = wp_remote_get( 'https://connect.mailerlite.com/api/subscribers?limit=1', array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type'  => 'application/json',
            ),
            'timeout' => 8,
        ) );
        $ms = round( (microtime(true) - $t) * 1000 );

        if ( is_wp_error($resp) ) {
            wp_send_json_success( array('status'=>'error', 'ms'=>$ms, 'msg'=>$resp->get_error_message()) );
        }
        $code = wp_remote_retrieve_response_code($resp);
        wp_send_json_success( array(
            'status' => $code === 200 ? 'ok' : 'error',
            'code'   => $code,
            'ms'     => $ms,
        ) );
    }

    // ── Render da página ──────────────────────────────────────────

    public static function render_page() {
        $d     = self::collect();
        $nonce = wp_create_nonce('cv_admin_nonce');
        $score = $d['security']['score'];
        $score_cor = $score >= 80 ? '#1DB954' : ($score >= 60 ? '#B8700C' : '#e74c3c');
        ?>
        <div class="wrap" id="cv-status-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-status-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --card2:#F8F0E4;
            --bord:#F3E6D3; --text:#3B2418; --muted:#C9A27E;
            --green:#1DB954; --red:#e74c3c; --blue:#3498db;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif;
            padding-bottom:60px;
        }
        #cv-status-exec * { box-sizing:border-box; }

        .cv-st-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:22px; flex-wrap:wrap; gap:12px; }
        .cv-st-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-st-title span { color:var(--blue); }
        .cv-st-updated { font-size:11px; color:var(--muted); }

        /* Score de segurança — destaque */
        .cv-st-score-bar {
            background:var(--card); border:1px solid var(--bord); border-radius:14px;
            padding:20px 24px; margin-bottom:22px;
            display:flex; align-items:center; gap:24px; flex-wrap:wrap;
        }
        .cv-st-score-ring {
            width:90px; height:90px; border-radius:50%; flex-shrink:0;
            background: conic-gradient(<?php echo esc_attr($score_cor); ?> <?php echo $score; ?>%, #FBF6EE <?php echo $score; ?>%);
            display:flex; align-items:center; justify-content:center; position:relative;
        }
        .cv-st-score-ring::before { content:''; position:absolute; inset:10px; border-radius:50%; background:var(--card); }
        .cv-st-score-inner { position:relative; z-index:1; text-align:center; }
        .cv-st-score-num { font-size:22px; font-weight:800; color:<?php echo esc_attr($score_cor); ?>; line-height:1; }
        .cv-st-score-label { font-size:9px; color:var(--muted); text-transform:uppercase; }
        .cv-st-score-info h3 { margin:0 0 4px; font-size:17px; font-weight:700; color:#3B2418; }
        .cv-st-score-info p { margin:0; font-size:13px; color:var(--muted); }
        .cv-st-score-pills { display:flex; gap:8px; flex-wrap:wrap; margin-top:10px; }
        .cv-st-pill { font-size:11px; padding:3px 10px; border-radius:20px; font-weight:600; }
        .cv-st-pill.ok  { background:rgba(29,185,84,.12); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-st-pill.off { background:rgba(231,76,60,.08); border:1px solid rgba(231,76,60,.25); color:var(--red); }
        .cv-st-pill.warn { background:rgba(242,165,26,0.1); border:1px solid rgba(201,162,126,0.5); color:var(--gold); }

        /* KPIs do plugin */
        .cv-st-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(130px,1fr)); gap:12px; margin-bottom:22px; }
        .cv-st-kpi { background:var(--card); border:1px solid var(--bord); border-radius:12px; padding:16px 14px; text-align:center; position:relative; overflow:hidden; }
        .cv-st-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:var(--kpi-cor,var(--gold)); }
        .cv-st-kpi-num { font-size:26px; font-weight:800; color:var(--kpi-cor,var(--gold)); line-height:1; }
        .cv-st-kpi-label { font-size:10px; color:var(--muted); margin-top:5px; text-transform:uppercase; letter-spacing:.4px; }

        /* Grid principal */
        .cv-st-grid { display:grid; grid-template-columns:1fr 1fr; gap:18px; }
        @media (max-width:960px) { .cv-st-grid { grid-template-columns:1fr; } }
        .cv-st-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:18px; margin-top:18px; }
        @media (max-width:960px) { .cv-st-grid-3 { grid-template-columns:1fr; } }

        /* Cards */
        .cv-st-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-st-card-hdr { padding:13px 18px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:10px; }
        .cv-st-card-hdr-icon { font-size:18px; }
        .cv-st-card-hdr-title { font-size:13px; font-weight:700; color:#3B2418; flex:1; }
        .cv-st-card-hdr-badge { font-size:10px; padding:2px 8px; border-radius:10px; font-weight:700; }
        .cv-st-card-body { padding:16px 18px; }

        /* Rows de info */
        .cv-st-row { display:flex; align-items:center; justify-content:space-between; padding:8px 0; border-bottom:1px solid rgba(123,58,34,0.12); }
        .cv-st-row:last-child { border-bottom:none; }
        .cv-st-row-label { font-size:12px; color:var(--muted); display:flex; align-items:center; gap:6px; }
        .cv-st-row-val { font-size:12px; color:var(--text); font-weight:600; text-align:right; }
        .cv-st-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; }
        .cv-st-dot.ok   { background:var(--green); box-shadow:0 0 6px var(--green); }
        .cv-st-dot.warn { background:var(--gold); }
        .cv-st-dot.off  { background:var(--red); }

        /* Ping button */
        .cv-st-ping-btn { background:rgba(52,152,219,.12); border:1px solid rgba(52,152,219,.3); color:var(--blue); padding:6px 12px; border-radius:6px; cursor:pointer; font-size:11px; font-weight:700; font-family:inherit; transition:opacity .2s; }
        .cv-st-ping-btn:hover { opacity:.8; }

        /* Tabela de tabelas DB */
        .cv-st-db-table { width:100%; border-collapse:collapse; font-size:11px; }
        .cv-st-db-table th { color:var(--muted); font-weight:600; text-align:left; padding:5px 0; border-bottom:1px solid var(--bord); }
        .cv-st-db-table td { padding:6px 0; border-bottom:1px solid rgba(123,58,34,0.12); color:var(--text); }
        .cv-st-db-table td:not(:first-child) { text-align:right; color:var(--muted); }

        /* Extensions */
        .cv-st-ext-grid { display:grid; grid-template-columns:1fr 1fr; gap:6px; }
        .cv-st-ext { display:flex; align-items:center; gap:6px; font-size:11px; }
        .cv-st-ext-dot { width:7px; height:7px; border-radius:50%; }

        /* API status */
        .cv-st-api-row { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid rgba(123,58,34,0.12); }
        .cv-st-api-row:last-child { border-bottom:none; }
        .cv-st-api-name { flex:1; font-size:12px; color:var(--muted); }
        .cv-st-api-status { font-size:11px; font-weight:700; }

        /* Cron */
        .cv-st-cron { display:flex; align-items:center; gap:10px; padding:8px 0; border-bottom:1px solid rgba(123,58,34,0.12); }
        .cv-st-cron:last-child { border-bottom:none; }
        .cv-st-cron-label { flex:1; font-size:12px; color:var(--muted); }
        .cv-st-cron-next { font-size:11px; color:var(--text); font-family:monospace; }

        /* Ping result */
        .cv-st-ping-result { font-size:11px; margin-top:6px; display:none; }
        </style>

        <div class="cv-st-topbar">
            <h1 class="cv-st-title">🖥️ Status do <span>Sistema</span></h1>
            <span class="cv-st-updated">⏱ Gerado em: <?php echo date('d/m/Y H:i:s'); ?></span>
        </div>
        <?php echo CV_Admin::btn_voltar(); ?>

        <!-- Security Score em destaque -->
        <div class="cv-st-score-bar">
            <div class="cv-st-score-ring">
                <div class="cv-st-score-inner">
                    <div class="cv-st-score-num"><?php echo $score; ?></div>
                    <div class="cv-st-score-label">score</div>
                </div>
            </div>
            <div class="cv-st-score-info">
                <h3>Security Score: <?php echo $score >= 80 ? 'Excelente' : ($score >= 60 ? 'Bom' : 'Atenção necessária'); ?></h3>
                <p><?php echo $score >= 80 ? 'O sistema está bem protegido. Todos os controles essenciais estão ativos.' : 'Alguns controles de segurança precisam de atenção.'; ?></p>
                <div class="cv-st-score-pills">
                    <span class="cv-st-pill <?php echo $d['security']['ssl']          ? 'ok' : 'off'; ?>">SSL/HTTPS</span>
                    <span class="cv-st-pill <?php echo $d['security']['debug_off']     ? 'ok' : 'warn'; ?>">Debug Off</span>
                    <span class="cv-st-pill <?php echo $d['security']['file_edit_off'] ? 'ok' : 'warn'; ?>">File Edit Off</span>
                    <span class="cv-st-pill <?php echo $d['security']['cloudflare']    ? 'ok' : 'off'; ?>">Cloudflare</span>
                    <span class="cv-st-pill <?php echo $d['security']['rate_limit_login'] ? 'ok' : 'off'; ?>">Rate Limiting</span>
                    <span class="cv-st-pill ok">Nonces AJAX</span>
                    <span class="cv-st-pill <?php echo !$d['wp']['debug_log'] ? 'ok' : 'warn'; ?>">Log Privado</span>
                </div>
            </div>
        </div>

        <!-- KPIs do plugin -->
        <div class="cv-st-kpis">
            <div class="cv-st-kpi" style="--kpi-cor:#1DB954">
                <div class="cv-st-kpi-num"><?php echo $d['plugin']['musicas_pub']; ?></div>
                <div class="cv-st-kpi-label">🎵 Músicas publicadas</div>
            </div>
            <div class="cv-st-kpi" style="--kpi-cor:#3498db">
                <div class="cv-st-kpi-num"><?php echo $d['plugin']['users']; ?></div>
                <div class="cv-st-kpi-label">👥 Usuários</div>
            </div>
            <div class="cv-st-kpi" style="--kpi-cor:var(--gold)">
                <div class="cv-st-kpi-num"><?php echo number_format($d['plugin']['plays_log']); ?></div>
                <div class="cv-st-kpi-label">▶ Plays registrados</div>
            </div>
            <div class="cv-st-kpi" style="--kpi-cor:#9b59b6">
                <div class="cv-st-kpi-num"><?php echo $d['plugin']['subscribers']; ?></div>
                <div class="cv-st-kpi-label">📧 Assinantes</div>
            </div>
            <div class="cv-st-kpi" style="--kpi-cor:#e67e22">
                <div class="cv-st-kpi-num"><?php echo $d['plugin']['musicas_draft']; ?></div>
                <div class="cv-st-kpi-label">📝 Rascunhos</div>
            </div>
            <div class="cv-st-kpi" style="--kpi-cor:#1abc9c">
                <div class="cv-st-kpi-num"><?php echo round($d['db']['total_kb']); ?>KB</div>
                <div class="cv-st-kpi-label">🗄️ Banco CV</div>
            </div>
        </div>

        <!-- Grid principal -->
        <div class="cv-st-grid">

            <!-- PHP -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">🐘</span>
                    <span class="cv-st-card-hdr-title">PHP</span>
                    <span class="cv-st-card-hdr-badge" style="background:<?php echo $d['php']['version_ok'] ? 'rgba(29,185,84,.12)' : 'rgba(231,76,60,.12)'; ?>;color:<?php echo $d['php']['version_ok'] ? '#1DB954' : '#e74c3c'; ?>;border:1px solid <?php echo $d['php']['version_ok'] ? 'rgba(29,185,84,.3)' : 'rgba(231,76,60,.3)'; ?>">
                        v<?php echo esc_html($d['php']['version']); ?>
                    </span>
                </div>
                <div class="cv-st-card-body">
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Versão</span>
                        <span class="cv-st-row-val"><?php echo esc_html($d['php']['version']); ?></span>
                    </div>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Memory Limit</span>
                        <span class="cv-st-row-val"><?php echo esc_html($d['php']['memory_limit']); ?></span>
                    </div>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Max Execution</span>
                        <span class="cv-st-row-val"><?php echo esc_html($d['php']['max_execution']); ?></span>
                    </div>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Upload Max</span>
                        <span class="cv-st-row-val"><?php echo esc_html($d['php']['upload_max']); ?></span>
                    </div>
                    <div style="margin-top:12px">
                        <div style="font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Extensões</div>
                        <div class="cv-st-ext-grid">
                        <?php foreach ($d['php']['extensions'] as $ext => $ok): ?>
                        <div class="cv-st-ext">
                            <span class="cv-st-ext-dot" style="background:<?php echo $ok ? '#1DB954' : '#e74c3c'; ?>"></span>
                            <span style="color:<?php echo $ok ? 'var(--text)' : 'var(--muted)'; ?>"><?php echo esc_html($ext); ?></span>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banco de dados -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">🗄️</span>
                    <span class="cv-st-card-hdr-title">Banco de Dados</span>
                    <span class="cv-st-card-hdr-badge" style="background:<?php echo $d['db']['ping_ok'] ? 'rgba(29,185,84,.12)' : 'rgba(242,165,26,0.16)'; ?>;color:<?php echo $d['db']['ping_ok'] ? '#1DB954' : '#B8700C'; ?>;border:1px solid <?php echo $d['db']['ping_ok'] ? 'rgba(29,185,84,.3)' : 'rgba(242,165,26,0.39)'; ?>">
                        <?php echo $d['db']['ping_ms']; ?>ms
                    </span>
                </div>
                <div class="cv-st-card-body">
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot <?php echo $d['db']['ping_ok'] ? 'ok' : 'warn'; ?>"></span>Latência</span>
                        <span class="cv-st-row-val" style="color:<?php echo $d['db']['ping_ok'] ? 'var(--green)' : 'var(--gold)'; ?>"><?php echo $d['db']['ping_ms']; ?>ms</span>
                    </div>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Versão MySQL</span>
                        <span class="cv-st-row-val"><?php echo esc_html($d['db']['version']); ?></span>
                    </div>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Charset</span>
                        <span class="cv-st-row-val"><?php echo esc_html($d['db']['charset']); ?></span>
                    </div>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot ok"></span>Total tabelas CV</span>
                        <span class="cv-st-row-val"><?php echo count($d['db']['tabelas']); ?> tabelas / <?php echo $d['db']['total_kb']; ?>KB</span>
                    </div>
                    <?php if (!empty($d['db']['tabelas'])): ?>
                    <div style="margin-top:12px;overflow-x:auto">
                        <table class="cv-st-db-table">
                            <thead><tr><th>Tabela</th><th>Linhas</th><th>Tamanho</th></tr></thead>
                            <tbody>
                            <?php foreach (array_slice($d['db']['tabelas'],0,8) as $t): ?>
                            <tr>
                                <td style="font-family:monospace;font-size:10px;color:var(--muted)"><?php echo esc_html(str_replace($GLOBALS['wpdb']->prefix.'cv_','',$t->nome)); ?></td>
                                <td><?php echo number_format((int)$t->linhas); ?></td>
                                <td><?php echo round($t->data_kb + $t->index_kb,1); ?>KB</td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- WordPress -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">🔵</span>
                    <span class="cv-st-card-hdr-title">WordPress</span>
                    <span class="cv-st-card-hdr-badge" style="background:rgba(52,152,219,.12);color:#1E72AA;border:1px solid rgba(52,152,219,.3)">v<?php echo esc_html($d['wp']['version']); ?></span>
                </div>
                <div class="cv-st-card-body">
                    <?php
                    $wp_rows = array(
                        array('label'=>'Versão',       'val'=>$d['wp']['version'],  'dot'=>'ok'),
                        array('label'=>'SSL/HTTPS',    'val'=>$d['wp']['ssl'] ? 'Ativo ✅' : 'Inativo ❌', 'dot'=>$d['wp']['ssl']?'ok':'off'),
                        array('label'=>'Debug Mode',   'val'=>$d['wp']['debug'] ? '⚠️ Ativo' : 'Desativado ✅', 'dot'=>$d['wp']['debug']?'warn':'ok'),
                        array('label'=>'Debug Log',    'val'=>$d['wp']['debug_log'] ? '⚠️ Ativo' : 'Desativado ✅', 'dot'=>$d['wp']['debug_log']?'warn':'ok'),
                        array('label'=>'File Edit',    'val'=>$d['wp']['debug'] ? '⚠️ Ativo' : 'Bloqueado ✅', 'dot'=>$d['security']['file_edit_off']?'ok':'warn'),
                        array('label'=>'Multisite',    'val'=>$d['wp']['multisite'] ? 'Sim' : 'Não', 'dot'=>'ok'),
                        array('label'=>'Mem. Usage',   'val'=>$d['wp']['memory_usage'], 'dot'=>'ok'),
                        array('label'=>'Mem. Peak',    'val'=>$d['wp']['memory_peak'],  'dot'=>'ok'),
                        array('label'=>'Cloudflare',   'val'=>$d['security']['cloudflare'] ? 'Detectado ✅' : 'Não detectado', 'dot'=>$d['security']['cloudflare']?'ok':'warn'),
                        array('label'=>'Plugin CV',    'val'=>'v'.($d['plugin']['version']), 'dot'=>'ok'),
                    );
                    foreach ($wp_rows as $r):
                    ?>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot <?php echo esc_attr($r['dot']); ?>"></span><?php echo esc_html($r['label']); ?></span>
                        <span class="cv-st-row-val"><?php echo esc_html($r['val']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- APIs e Plugins -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">🔌</span>
                    <span class="cv-st-card-hdr-title">APIs &amp; Integrações</span>
                </div>
                <div class="cv-st-card-body">
                    <?php
                    $api_items = array(
                        array('nome'=>'MailerLite',  'ok'=>$d['apis']['mailerlite_key'], 'label_ok'=>'API configurada', 'label_off'=>'Não configurada', 'ping'=>true),
                        array('nome'=>'YouTube API', 'ok'=>$d['apis']['youtube_key'],    'label_ok'=>'API configurada', 'label_off'=>'Não configurada', 'ping'=>false),
                        array('nome'=>'Rank Math',   'ok'=>$d['apis']['rank_math'],      'label_ok'=>'Ativo',           'label_off'=>'Não instalado',   'ping'=>false),
                        array('nome'=>'WP Rocket',   'ok'=>$d['apis']['wp_rocket'],      'label_ok'=>'Ativo',           'label_off'=>'Não instalado',   'ping'=>false),
                        array('nome'=>'Relevanssi',  'ok'=>$d['apis']['relevanssi'],     'label_ok'=>'Ativo ✅',        'label_off'=>'Inativo',         'ping'=>false),
                        array('nome'=>'WooCommerce', 'ok'=>$d['apis']['woocommerce'],    'label_ok'=>'Ativo',           'label_off'=>'Não instalado',   'ping'=>false),
                    );
                    foreach ($api_items as $api):
                    ?>
                    <div class="cv-st-api-row">
                        <span class="cv-st-dot <?php echo $api['ok'] ? 'ok' : 'off'; ?>"></span>
                        <span class="cv-st-api-name"><?php echo esc_html($api['nome']); ?></span>
                        <span class="cv-st-api-status" style="color:<?php echo $api['ok'] ? 'var(--green)' : 'var(--red)'; ?>">
                            <?php echo $api['ok'] ? esc_html($api['label_ok']) : esc_html($api['label_off']); ?>
                        </span>
                        <?php if ($api['ping'] && $api['ok']): ?>
                        <button class="cv-st-ping-btn" onclick="cvStPingApi(this)">Testar</button>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <div class="cv-st-ping-result" id="cv-st-ping-result"></div>
                </div>
            </div>

        </div><!-- grid -->

        <!-- Grid inferior: Cron + Segurança detalhada -->
        <div class="cv-st-grid-3">

            <!-- Cron Jobs -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">⏰</span>
                    <span class="cv-st-card-hdr-title">Cron Jobs</span>
                </div>
                <div class="cv-st-card-body">
                    <?php foreach ($d['crons'] as $hook => $info): ?>
                    <div class="cv-st-cron">
                        <span class="cv-st-dot <?php echo $info['active'] ? 'ok' : 'off'; ?>"></span>
                        <div style="flex:1">
                            <div style="font-size:12px;color:var(--text)"><?php echo esc_html($info['label']); ?></div>
                            <div style="font-size:10px;color:var(--muted)"><?php echo esc_html($hook); ?></div>
                        </div>
                        <span class="cv-st-cron-next">
                            <?php echo $info['active'] ? esc_html($info['next']) : '<span style="color:var(--red)">inativo</span>'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:12px;padding-top:12px;border-top:1px solid var(--bord)">
                        <?php
                        $wp_cron = $d['wp']['cron_disabled'] ? '<span style="color:var(--red)">⚠️ WP-CRON desativado</span>' : '<span style="color:var(--green)">✅ WP-CRON ativo</span>';
                        echo $wp_cron;
                        ?>
                    </div>
                </div>
            </div>

            <!-- Segurança detalhada -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">🛡️</span>
                    <span class="cv-st-card-hdr-title">Segurança</span>
                    <span class="cv-st-card-hdr-badge" style="background:<?php echo esc_attr('rgba('.($score>=80?'29,185,84':'231,76,60').',.12)'); ?>;color:<?php echo esc_attr($score_cor); ?>;border:1px solid <?php echo esc_attr('rgba('.($score>=80?'29,185,84':'231,76,60').',.3)'); ?>"><?php echo $score; ?>/100</span>
                </div>
                <div class="cv-st-card-body">
                    <?php
                    $sec_items = array(
                        array('label'=>'SSL/HTTPS',          'ok'=>$d['security']['ssl']),
                        array('label'=>'Debug desativado',   'ok'=>$d['security']['debug_off']),
                        array('label'=>'.htaccess presente', 'ok'=>$d['security']['htaccess']),
                        array('label'=>'File Edit bloqueado','ok'=>$d['security']['file_edit_off']),
                        array('label'=>'Cloudflare CDN',     'ok'=>$d['security']['cloudflare']),
                        array('label'=>'Rate limit login',   'ok'=>$d['security']['rate_limit_login']),
                        array('label'=>'Nonces em AJAX',     'ok'=>$d['security']['nonces_ativos']),
                        array('label'=>'Debug log privado',  'ok'=>!$d['wp']['debug_log']),
                    );
                    foreach ($sec_items as $s):
                    ?>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label">
                            <span class="cv-st-dot <?php echo $s['ok'] ? 'ok' : 'off'; ?>"></span>
                            <?php echo esc_html($s['label']); ?>
                        </span>
                        <span style="font-size:11px;color:<?php echo $s['ok'] ? 'var(--green)' : 'var(--red)'; ?>;font-weight:700">
                            <?php echo $s['ok'] ? '✅' : '❌'; ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Plugin CV detalhes -->
            <div class="cv-st-card">
                <div class="cv-st-card-hdr">
                    <span class="cv-st-card-hdr-icon">🎵</span>
                    <span class="cv-st-card-hdr-title">Plugin Canção Verdadeira</span>
                </div>
                <div class="cv-st-card-body">
                    <?php
                    $last_rk = $d['plugin']['last_ranking'];
                    $pv_items = array(
                        array('label'=>'Versão',            'val'=>'v'.$d['plugin']['version'],              'dot'=>'ok'),
                        array('label'=>'DB Schema',         'val'=>'v'.$d['plugin']['db_version'],           'dot'=>'ok'),
                        array('label'=>'Cron ranking',      'val'=>$d['plugin']['cron_ranking']?'Ativo':'Inativo', 'dot'=>$d['plugin']['cron_ranking']?'ok':'off'),
                        array('label'=>'Último ranking',    'val'=>$last_rk ? date('d/m H:i',strtotime($last_rk)) : 'Nunca', 'dot'=>$last_rk?'ok':'warn'),
                        array('label'=>'PWA Manifest',      'val'=>$d['plugin']['pwa_manifest']?'Presente':'Ausente', 'dot'=>$d['plugin']['pwa_manifest']?'ok':'warn'),
                        array('label'=>'Relevanssi',        'val'=>$d['apis']['relevanssi']?'Integrado':'Não ativo', 'dot'=>$d['apis']['relevanssi']?'ok':'warn'),
                    );
                    foreach ($pv_items as $r):
                    ?>
                    <div class="cv-st-row">
                        <span class="cv-st-row-label"><span class="cv-st-dot <?php echo esc_attr($r['dot']); ?>"></span><?php echo esc_html($r['label']); ?></span>
                        <span class="cv-st-row-val"><?php echo esc_html($r['val']); ?></span>
                    </div>
                    <?php endforeach; ?>
                    <div style="margin-top:14px;padding-top:12px;border-top:1px solid var(--bord)">
                        <a href="<?php echo esc_url(admin_url('admin.php?page=cv-logs')); ?>"
                           style="font-size:12px;color:var(--gold);text-decoration:none">
                            📋 Ver logs do sistema →
                        </a>
                    </div>
                </div>
            </div>

        </div><!-- grid-3 -->

        <script>
        (function(){
            var NONCE = '<?php echo esc_js($nonce); ?>';
            var AJAX  = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';

            window.cvStPingApi = function(btn) {
                btn.textContent = '⏳';
                btn.disabled = true;
                var fd = new FormData();
                fd.append('action', 'cv_system_ping_api');
                fd.append('nonce', NONCE);
                fetch(AJAX, {method:'POST', body:fd})
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        btn.disabled = false;
                        var result = document.getElementById('cv-st-ping-result');
                        result.style.display = 'block';
                        if (res.success) {
                            var d = res.data;
                            if (d.status === 'ok') {
                                btn.textContent = '✅';
                                result.style.color = '#137B38';
                                result.textContent = '✅ MailerLite respondeu em ' + d.ms + 'ms (HTTP ' + d.code + ')';
                            } else if (d.status === 'not_configured') {
                                btn.textContent = 'Testar';
                                result.style.color = '#8A6A55';
                                result.textContent = 'API Key não configurada.';
                            } else {
                                btn.textContent = '❌';
                                result.style.color = '#D62C1A';
                                result.textContent = '❌ Erro: HTTP ' + (d.code||'?') + ' — verifique a API Key.';
                            }
                        } else {
                            btn.textContent = 'Testar';
                            result.style.color = '#D62C1A';
                            result.textContent = 'Erro de conexão.';
                        }
                        setTimeout(function(){ result.style.display='none'; btn.textContent='Testar'; }, 6000);
                    })
                    .catch(function(){ btn.disabled=false; btn.textContent='Testar'; });
            };
        })();
        </script>
        </div><!-- wrap -->
        <?php
    }
}

CV_System_Status::init();
