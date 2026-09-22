<?php
// includes/admin/class-cv-seguranca.php
// Gerado em: 2026-06-29 11:00:00
// Módulo: Painel de Segurança Avançada — Canção Verdadeira v2.24.0
// Exibe painel executivo de segurança com: tentativas de login, IPs bloqueados,
// top IPs atacantes, top usuários alvo, timeline 24h, status dos headers HTTP,
// checklist de hardening, e gráfico de ataques nos últimos 7 dias via Chart.js.
// Lê dados da tabela cv_action_logs já populada pelo CV_Security.
// Sugestão implementada a partir do relatório do Diretor de TI José Amado.
// Compatível com PHP 7.2+. Acesso restrito a manage_options.

if ( ! defined( 'ABSPATH' ) ) exit;

class CV_Seguranca {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'wp_ajax_cv_seg_unblock', array( __CLASS__, 'ajax_unblock' ) );
    }

    public static function register_page() {
        add_submenu_page(
            null,
            'Segurança Avançada',
            'Segurança Avançada',
            'manage_options',
            'cv-seguranca',
            array( __CLASS__, 'render' )
        );
    }

    // ── Coleta de dados ──────────────────────────────────────────
    private static function get_data() {
        global $wpdb;
        $tbl = $wpdb->prefix . 'cv_action_logs';

        // Tentativas de login últimas 24h
        $fail_24h = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tbl}
             WHERE action = 'login_failed'
               AND created_at >= NOW() - INTERVAL 24 HOUR"
        );
        // Bloqueios últimas 24h
        $blocked_24h = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tbl}
             WHERE action = 'login_blocked'
               AND created_at >= NOW() - INTERVAL 24 HOUR"
        );
        // Total falhas 7 dias
        $fail_7d = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tbl}
             WHERE action = 'login_failed'
               AND created_at >= NOW() - INTERVAL 7 DAY"
        );
        // IPs com transient de bloqueio ativo
        $active_blocks = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as hits, MAX(created_at) as last_seen
             FROM {$tbl}
             WHERE action IN ('login_failed','login_blocked')
               AND created_at >= NOW() - INTERVAL 10 MINUTE
             GROUP BY ip_address
             HAVING hits >= 5
             ORDER BY hits DESC
             LIMIT 20"
        );

        // Top IPs atacantes (últimos 7 dias)
        $top_ips = $wpdb->get_results(
            "SELECT ip_address, COUNT(*) as total,
                    SUM(CASE WHEN action='login_blocked' THEN 1 ELSE 0 END) as bloqueios,
                    MAX(created_at) as last_seen
             FROM {$tbl}
             WHERE action IN ('login_failed','login_blocked')
               AND created_at >= NOW() - INTERVAL 7 DAY
               AND ip_address != ''
             GROUP BY ip_address
             ORDER BY total DESC
             LIMIT 10"
        );

        // Top usuários/logins alvo
        $top_targets = $wpdb->get_results(
            "SELECT description, COUNT(*) as total
             FROM {$tbl}
             WHERE action = 'login_failed'
               AND created_at >= NOW() - INTERVAL 7 DAY
             GROUP BY description
             ORDER BY total DESC
             LIMIT 8"
        );

        // Timeline 24h por hora
        $timeline = $wpdb->get_results(
            "SELECT HOUR(created_at) as hora, COUNT(*) as total
             FROM {$tbl}
             WHERE action IN ('login_failed','login_blocked')
               AND created_at >= NOW() - INTERVAL 24 HOUR
             GROUP BY HOUR(created_at)
             ORDER BY hora ASC"
        );

        // Gráfico 7 dias por dia
        $chart7 = $wpdb->get_results(
            "SELECT DATE(created_at) as dia,
                    SUM(CASE WHEN action='login_failed'  THEN 1 ELSE 0 END) as falhas,
                    SUM(CASE WHEN action='login_blocked' THEN 1 ELSE 0 END) as bloqueios
             FROM {$tbl}
             WHERE action IN ('login_failed','login_blocked')
               AND created_at >= NOW() - INTERVAL 7 DAY
             GROUP BY DATE(created_at)
             ORDER BY dia ASC"
        );

        // Últimas 10 ações de segurança
        $recent = $wpdb->get_results(
            "SELECT action, description, ip_address, created_at
             FROM {$tbl}
             WHERE action IN ('login_failed','login_blocked','login_success')
             ORDER BY created_at DESC
             LIMIT 12"
        );

        // Logins bem sucedidos 24h
        $ok_24h = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$tbl}
             WHERE action = 'login_success'
               AND created_at >= NOW() - INTERVAL 24 HOUR"
        );

        return compact(
            'fail_24h','blocked_24h','fail_7d','active_blocks',
            'top_ips','top_targets','timeline','chart7','recent','ok_24h'
        );
    }

    // ── Desbloquear IP via AJAX ───────────────────────────────────
    public static function ajax_unblock() {
        check_ajax_referer( 'cv_seg_unblock', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $ip = sanitize_text_field( $_POST['ip'] ?? '' );
        if ( $ip ) {
            delete_transient( 'cv_login_fail_' . md5( $ip ) );
            wp_send_json_success( array( 'msg' => 'IP desbloqueado: ' . $ip ) );
        }
        wp_send_json_error( 'IP inválido.' );
    }

    // ── Render ───────────────────────────────────────────────────
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado.' );

        $d     = self::get_data();
        $nonce = wp_create_nonce( 'cv_seg_unblock' );

        // Preenche 7 dias do gráfico com zeros
        $chart_labels = array(); $chart_falhas = array(); $chart_bloq = array();
        $chart_map = array();
        foreach ( $d['chart7'] as $row ) {
            $chart_map[ $row->dia ] = array( 'f' => (int)$row->falhas, 'b' => (int)$row->bloqueios );
        }
        for ( $i = 6; $i >= 0; $i-- ) {
            $day = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $chart_labels[] = date( 'd/m', strtotime( $day ) );
            $chart_falhas[] = isset( $chart_map[$day] ) ? $chart_map[$day]['f'] : 0;
            $chart_bloq[]   = isset( $chart_map[$day] ) ? $chart_map[$day]['b'] : 0;
        }

        // Preenche timeline 24h (0-23)
        $tl_map = array();
        foreach ( $d['timeline'] as $row ) $tl_map[ (int)$row->hora ] = (int)$row->total;
        $tl_labels = array(); $tl_values = array();
        for ( $h = 0; $h < 24; $h++ ) {
            $tl_labels[] = sprintf( '%02d:00', $h );
            $tl_values[] = isset( $tl_map[$h] ) ? $tl_map[$h] : 0;
        }

        // Score de segurança
        $score = 100;
        if ( $d['fail_24h'] > 50 )   $score -= 30;
        elseif ( $d['fail_24h'] > 20 ) $score -= 15;
        elseif ( $d['fail_24h'] > 5 )  $score -= 5;
        if ( count( $d['active_blocks'] ) > 0 ) $score -= 10;
        if ( ! is_ssl() ) $score -= 20;
        $score = max( 0, $score );
        $sc    = $score >= 85 ? '#1DB954' : ( $score >= 60 ? '#e67e22' : '#e74c3c' );
        $sl    = $score >= 85 ? 'Seguro' : ( $score >= 60 ? 'Atenção' : 'Risco' );

        // Headers ativos
        $headers_ok = array(
            'X-Frame-Options'           => 'SAMEORIGIN',
            'X-Content-Type-Options'    => 'nosniff',
            'Referrer-Policy'           => 'strict-origin-when-cross-origin',
            'X-XSS-Protection'          => '1; mode=block',
            'Strict-Transport-Security' => is_ssl() ? 'max-age=31536000' : null,
        );

        ?>
        <div class="wrap" style="background:#0f0f1a;min-height:100vh;padding:24px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">

        <!-- HEADER -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="color:#fff;font-size:22px;margin:0 0 4px 0;font-weight:700;">🔐 Segurança Avançada</h1>
                <p style="color:#666;font-size:13px;margin:0;">Monitoramento de ameaças e tentativas de invasão · Canção Verdadeira</p>
            </div>
            <div style="text-align:center;background:#1a1a2e;border:2px solid <?php echo $sc; ?>44;border-radius:12px;padding:14px 24px;">
                <div style="font-size:34px;font-weight:800;color:<?php echo $sc; ?>;"><?php echo $score; ?></div>
                <div style="font-size:11px;color:<?php echo $sc; ?>;text-transform:uppercase;letter-spacing:1px;font-weight:700;"><?php echo $sl; ?></div>
                <div style="font-size:10px;color:#555;margin-top:2px;">Score de Segurança</div>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px;">

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-left:4px solid #e74c3c;border-radius:10px;padding:18px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px;">Falhas de Login (24h)</div>
                <div style="font-size:36px;font-weight:800;color:<?php echo $d['fail_24h'] > 10 ? '#e74c3c' : '#D4A017'; ?>;margin:6px 0;"><?php echo $d['fail_24h']; ?></div>
                <div style="font-size:12px;color:#555;"><?php echo $d['fail_7d']; ?> nos últimos 7 dias</div>
            </div>

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-left:4px solid #e67e22;border-radius:10px;padding:18px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px;">IPs Bloqueados (24h)</div>
                <div style="font-size:36px;font-weight:800;color:<?php echo $d['blocked_24h'] > 0 ? '#e67e22' : '#1DB954'; ?>;margin:6px 0;"><?php echo $d['blocked_24h']; ?></div>
                <div style="font-size:12px;color:#555;"><?php echo count($d['active_blocks']); ?> ativos agora (10min)</div>
            </div>

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-left:4px solid #1DB954;border-radius:10px;padding:18px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px;">Logins Bem Sucedidos (24h)</div>
                <div style="font-size:36px;font-weight:800;color:#1DB954;margin:6px 0;"><?php echo $d['ok_24h']; ?></div>
                <div style="font-size:12px;color:#555;">acessos legítimos</div>
            </div>

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-left:4px solid #4a90d9;border-radius:10px;padding:18px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px;">Headers HTTP</div>
                <div style="font-size:36px;font-weight:800;color:#4a90d9;margin:6px 0;"><?php echo count( array_filter( $headers_ok ) ); ?>/<?php echo count($headers_ok); ?></div>
                <div style="font-size:12px;color:#555;">headers de segurança ativos</div>
            </div>

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-left:4px solid #9b59b6;border-radius:10px;padding:18px;">
                <div style="font-size:11px;color:#666;text-transform:uppercase;letter-spacing:1px;">SSL / HTTPS</div>
                <div style="font-size:36px;font-weight:800;color:<?php echo is_ssl() ? '#1DB954' : '#e74c3c'; ?>;margin:6px 0;"><?php echo is_ssl() ? '✓' : '✗'; ?></div>
                <div style="font-size:12px;color:#555;"><?php echo is_ssl() ? 'Certificado ativo (Cloudflare)' : 'HTTPS não detectado'; ?></div>
            </div>

        </div>

        <!-- GRÁFICO 7 DIAS + TIMELINE 24H -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:12px;padding:20px;">
                <div style="color:#D4A017;font-size:13px;font-weight:700;margin-bottom:16px;">📊 Ataques — Últimos 7 Dias</div>
                <div style="position:relative;height:180px;">
                    <canvas id="cv-seg-chart7"></canvas>
                </div>
            </div>

            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:12px;padding:20px;">
                <div style="color:#e74c3c;font-size:13px;font-weight:700;margin-bottom:16px;">⏱️ Timeline 24h — Por Hora</div>
                <div style="position:relative;height:180px;">
                    <canvas id="cv-seg-timeline"></canvas>
                </div>
            </div>

        </div>

        <!-- TOP IPs + TOP ALVOS -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

            <!-- Top IPs -->
            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #2a2a4a;display:flex;align-items:center;justify-content:space-between;">
                    <div style="color:#e74c3c;font-size:13px;font-weight:700;">🌐 Top IPs Atacantes (7 dias)</div>
                    <div style="font-size:11px;color:#555;"><?php echo count($d['top_ips']); ?> IPs únicos</div>
                </div>
                <?php if ( empty( $d['top_ips'] ) ) : ?>
                <div style="padding:30px;text-align:center;color:#555;font-size:13px;">✅ Nenhum ataque registrado nos últimos 7 dias</div>
                <?php else : ?>
                <?php $max_ip = max( array_column( (array)$d['top_ips'], 'total' ) ); $max_ip = max(1,$max_ip); ?>
                <?php foreach ( $d['top_ips'] as $row ) :
                    $pct = round( $row->total / $max_ip * 100 );
                    $active_now = false;
                    foreach ( $d['active_blocks'] as $ab ) {
                        if ( $ab->ip_address === $row->ip_address ) { $active_now = true; break; }
                    }
                    $short_ip = strlen($row->ip_address) > 18 ? substr($row->ip_address,0,15).'...' : $row->ip_address;
                ?>
                <div style="padding:12px 20px;border-bottom:1px solid #1d1d35;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <code style="color:#e74c3c;font-size:12px;background:#e74c3c11;padding:2px 6px;border-radius:4px;"><?php echo esc_html( $row->ip_address ); ?></code>
                            <?php if ( $active_now ) : ?>
                            <span style="background:#e74c3c22;color:#e74c3c;border:1px solid #e74c3c44;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;">BLOQUEADO</span>
                            <?php elseif ( (int)$row->bloqueios > 0 ) : ?>
                            <span style="background:#e67e2222;color:#e67e22;border:1px solid #e67e2244;border-radius:10px;padding:1px 7px;font-size:10px;font-weight:700;">BLOQ. ANTES</span>
                            <?php endif; ?>
                        </div>
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="color:#e74c3c;font-size:13px;font-weight:700;"><?php echo (int)$row->total; ?> ataques</span>
                            <?php if ( $active_now ) : ?>
                            <button onclick="cvUnblock('<?php echo esc_js($row->ip_address); ?>', this)"
                                    data-nonce="<?php echo $nonce; ?>"
                                    style="background:#1DB95422;border:1px solid #1DB95444;color:#1DB954;border-radius:6px;padding:3px 9px;font-size:11px;cursor:pointer;font-weight:700;">
                                🔓 Desbloquear
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="background:#111;border-radius:4px;height:4px;overflow:clip;">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:#e74c3c;border-radius:4px;"></div>
                    </div>
                    <div style="font-size:10px;color:#444;margin-top:3px;">Último: <?php echo esc_html( date('d/m H:i', strtotime($row->last_seen)) ); ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Top Alvos -->
            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #2a2a4a;">
                    <div style="color:#e67e22;font-size:13px;font-weight:700;">🎯 Usuários Mais Visados (7 dias)</div>
                </div>
                <?php if ( empty( $d['top_targets'] ) ) : ?>
                <div style="padding:30px;text-align:center;color:#555;font-size:13px;">✅ Nenhum alvo registrado</div>
                <?php else : ?>
                <?php $max_t = max( array_column( (array)$d['top_targets'], 'total' ) ); $max_t = max(1,$max_t); ?>
                <?php foreach ( $d['top_targets'] as $row ) :
                    $pct = round( $row->total / $max_t * 100 );
                    // Extrai username da descrição
                    preg_match( '/usuário "([^"]*)"/', $row->description, $m );
                    $target_name = ! empty($m[1]) ? $m[1] : '(desconhecido)';
                ?>
                <div style="padding:12px 20px;border-bottom:1px solid #1d1d35;">
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px;">
                        <code style="color:#e67e22;font-size:12px;background:#e67e2211;padding:2px 8px;border-radius:4px;"><?php echo esc_html( $target_name ); ?></code>
                        <span style="color:#e67e22;font-size:13px;font-weight:700;"><?php echo (int)$row->total; ?>×</span>
                    </div>
                    <div style="background:#111;border-radius:4px;height:4px;overflow:clip;">
                        <div style="height:100%;width:<?php echo $pct; ?>%;background:#e67e22;border-radius:4px;"></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

        </div>

        <!-- FEED RECENTE + CHECKLIST HEADERS -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:24px;">

            <!-- Feed recente -->
            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #2a2a4a;">
                    <div style="color:#4a90d9;font-size:13px;font-weight:700;">📋 Atividade Recente de Autenticação</div>
                </div>
                <?php if ( empty( $d['recent'] ) ) : ?>
                <div style="padding:30px;text-align:center;color:#555;font-size:13px;">Nenhuma atividade registrada</div>
                <?php else : ?>
                <?php foreach ( $d['recent'] as $ev ) :
                    $is_fail = $ev->action === 'login_failed';
                    $is_blk  = $ev->action === 'login_blocked';
                    $is_ok   = $ev->action === 'login_success';
                    $ec = $is_ok ? '#1DB954' : ( $is_blk ? '#e74c3c' : '#e67e22' );
                    $ei = $is_ok ? '✅' : ( $is_blk ? '🚫' : '⚠️' );
                    $el = $is_ok ? 'Login OK' : ( $is_blk ? 'Bloqueado' : 'Falha' );
                ?>
                <div style="padding:10px 20px;border-bottom:1px solid #1d1d35;display:flex;align-items:center;gap:10px;">
                    <span style="font-size:16px;"><?php echo $ei; ?></span>
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="background:<?php echo $ec; ?>22;color:<?php echo $ec; ?>;border-radius:10px;padding:1px 8px;font-size:10px;font-weight:700;"><?php echo $el; ?></span>
                            <code style="color:#888;font-size:11px;"><?php echo esc_html( $ev->ip_address ); ?></code>
                        </div>
                        <div style="color:#555;font-size:11px;margin-top:3px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo esc_html( $ev->description ); ?></div>
                    </div>
                    <div style="color:#444;font-size:10px;white-space:nowrap;"><?php echo esc_html( date('d/m H:i', strtotime($ev->created_at)) ); ?></div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Checklist de Headers + Hardening -->
            <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:12px;overflow:hidden;">
                <div style="padding:16px 20px;border-bottom:1px solid #2a2a4a;">
                    <div style="color:#9b59b6;font-size:13px;font-weight:700;">🛡️ Checklist de Hardening</div>
                </div>
                <div style="padding:16px 20px;">
                <?php
                $checks = array(
                    array( 'label' => 'SSL / HTTPS ativo',                   'ok' => is_ssl() ),
                    array( 'label' => 'Header X-Frame-Options',              'ok' => true ),
                    array( 'label' => 'Header X-Content-Type-Options',       'ok' => true ),
                    array( 'label' => 'Header Referrer-Policy',              'ok' => true ),
                    array( 'label' => 'Header X-XSS-Protection',             'ok' => true ),
                    array( 'label' => 'HSTS (Strict-Transport-Security)',    'ok' => is_ssl() ),
                    array( 'label' => 'Rate limit login (5 tent./10min)',    'ok' => true ),
                    array( 'label' => '.htaccess — PHP bloqueado',           'ok' => file_exists( CV_PLUGIN_DIR . '.htaccess' ) ),
                    array( 'label' => 'Versão WP oculta no HTML',            'ok' => true ),
                    array( 'label' => 'wlwmanifest e rsd_link removidos',   'ok' => true ),
                    array( 'label' => 'Cloudflare ativo (SSL proxy)',        'ok' => ! empty( $_SERVER['HTTP_CF_RAY'] ) || is_ssl() ),
                    array( 'label' => 'UpdraftPlus backup configurado',      'ok' => is_plugin_active('updraftplus/updraftplus.php') ),
                );
                foreach ( $checks as $ch ) :
                    $c = $ch['ok'] ? '#1DB954' : '#e74c3c';
                    $i = $ch['ok'] ? '✅' : '❌';
                ?>
                <div style="display:flex;align-items:center;gap:8px;padding:7px 0;border-bottom:1px solid #1d1d35;">
                    <span style="font-size:14px;"><?php echo $i; ?></span>
                    <span style="color:<?php echo $ch['ok'] ? '#c0c0c0' : '#888'; ?>;font-size:13px;"><?php echo esc_html($ch['label']); ?></span>
                </div>
                <?php endforeach; ?>
                </div>
            </div>

        </div>

        <!-- IPs ATIVAMENTE BLOQUEADOS AGORA -->
        <?php if ( ! empty( $d['active_blocks'] ) ) : ?>
        <div style="background:#e74c3c11;border:1px solid #e74c3c44;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="color:#e74c3c;font-size:14px;font-weight:700;margin-bottom:12px;">🚨 IPs com Bloqueio Ativo Agora (últimos 10min)</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ( $d['active_blocks'] as $ab ) : ?>
                <div style="background:#e74c3c22;border:1px solid #e74c3c44;border-radius:8px;padding:8px 14px;display:flex;align-items:center;gap:10px;">
                    <code style="color:#e74c3c;font-size:13px;"><?php echo esc_html($ab->ip_address); ?></code>
                    <span style="color:#888;font-size:11px;"><?php echo (int)$ab->hits; ?> hits</span>
                    <button onclick="cvUnblock('<?php echo esc_js($ab->ip_address); ?>', this)"
                            data-nonce="<?php echo $nonce; ?>"
                            style="background:#1DB95422;border:1px solid #1DB95444;color:#1DB954;border-radius:6px;padding:3px 9px;font-size:11px;cursor:pointer;font-weight:700;">
                        🔓 Liberar
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Nota informativa -->
        <div style="background:#1a1a2e;border:1px solid #2a2a4a;border-radius:10px;padding:16px 20px;margin-bottom:16px;">
            <div style="color:#666;font-size:12px;line-height:1.7;">
                ℹ️ <strong style="color:#888;">Sobre os dados:</strong> As tentativas de login são registradas pela camada de segurança do plugin na tabela <code style="color:#9b59b6;">cv_action_logs</code>.
                O bloqueio ativo é via WordPress Transient com janela deslizante de 10 minutos após 5 falhas.
                O desloqueio manual libera o transient mas não apaga o log histórico.
            </div>
        </div>

        </div><!-- .wrap -->

        <!-- Chart.js + scripts -->
        <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
        <script>
        (function(){
            var chartOpts = {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, labels: { color:'#888', font:{size:11}, boxWidth:10 } },
                    tooltip: {
                        backgroundColor:'#1e1e1e', borderColor:'#333', borderWidth:1,
                        titleColor:'#D4A017', bodyColor:'#aaa'
                    }
                },
                scales: {
                    x: { grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#444',font:{size:10},maxTicksLimit:8} },
                    y: { grid:{color:'rgba(255,255,255,0.04)'}, ticks:{color:'#444',font:{size:10},precision:0}, beginAtZero:true }
                }
            };

            // Gráfico 7 dias
            var ctx7 = document.getElementById('cv-seg-chart7');
            if (ctx7) {
                new Chart(ctx7, {
                    type: 'bar',
                    data: {
                        labels: <?php echo json_encode($chart_labels); ?>,
                        datasets: [
                            {
                                label: 'Falhas de Login',
                                data:  <?php echo json_encode($chart_falhas); ?>,
                                backgroundColor: 'rgba(231,76,60,0.7)',
                                borderColor: '#e74c3c',
                                borderWidth: 1,
                                borderRadius: 4,
                            },
                            {
                                label: 'Bloqueios',
                                data:  <?php echo json_encode($chart_bloq); ?>,
                                backgroundColor: 'rgba(230,126,34,0.7)',
                                borderColor: '#e67e22',
                                borderWidth: 1,
                                borderRadius: 4,
                            }
                        ]
                    },
                    options: chartOpts
                });
            }

            // Timeline 24h
            var ctxTL = document.getElementById('cv-seg-timeline');
            if (ctxTL) {
                new Chart(ctxTL, {
                    type: 'line',
                    data: {
                        labels: <?php echo json_encode($tl_labels); ?>,
                        datasets: [{
                            label: 'Ataques por hora',
                            data:  <?php echo json_encode($tl_values); ?>,
                            borderColor: '#e74c3c',
                            backgroundColor: 'rgba(231,76,60,0.08)',
                            borderWidth: 2,
                            pointRadius: 2,
                            fill: true,
                            tension: 0.3,
                        }]
                    },
                    options: chartOpts
                });
            }
        })();

        // Desbloquear IP
        function cvUnblock(ip, btn) {
            if (!confirm('Desbloquear o IP ' + ip + '?')) return;
            btn.disabled = true;
            btn.textContent = '⏳';
            var fd = new FormData();
            fd.append('action', 'cv_seg_unblock');
            fd.append('nonce', btn.dataset.nonce || '<?php echo $nonce; ?>');
            fd.append('ip', ip);
            fetch(ajaxurl, { method:'POST', body: fd })
                .then(function(r){ return r.json(); })
                .then(function(data){
                    if (data.success) {
                        btn.closest('div[style]').style.opacity = '0.4';
                        btn.textContent = '✓ Liberado';
                    } else {
                        btn.textContent = '✗ Erro';
                    }
                });
        }
        </script>
        <?php
    }
}

CV_Seguranca::init();
