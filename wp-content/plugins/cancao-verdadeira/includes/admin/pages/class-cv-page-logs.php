<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-logs.php
// Página "Logs": visualização de logs do sistema.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Logs {

    public static function render() {
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
            'music_saved'     => array('icon' => '✏️',  'label' => 'Música salva',       'color' => '#D4A017'),
            'music_deleted'   => array('icon' => '🗑️',  'label' => 'Música excluída',    'color' => '#e74c3c'),
            'admin_login'     => array('icon' => '🔐',  'label' => 'Login admin',        'color' => '#3498db'),
            'admin_logout'    => array('icon' => '↩️',   'label' => 'Logout admin',       'color' => '#95a5a6'),
            'playlist_created'=> array('icon' => '📋',  'label' => 'Playlist criada',    'color' => '#9b59b6'),
            'cache_cleared'   => array('icon' => '🗑️',  'label' => 'Cache limpo',        'color' => '#e67e22'),
            'ranking_recalc'  => array('icon' => '🔄',  'label' => 'Ranking recalculado','color' => '#1DB954'),
        );
        ?>
        <div class="wrap" id="cv-logs-executive">
        <style>
        body.wp-admin { background:#0f0f1a !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#0f0f1a !important; }
        #cv-logs-executive {
            --gold:#D4A017; --bg:#0f0f1a; --card:#1a1a2e; --bord:#2a2a4a;
            --text:#e0e0e0; --muted:#888; --green:#1DB954;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif;
            padding-bottom:40px;
        }
        #cv-logs-executive * { box-sizing:border-box; }
        .cv-log-header { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-log-title { font-size:24px; font-weight:700; color:#fff; margin:0; }
        .cv-log-title span { color:var(--gold); }
        .cv-log-kpis { display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:14px; margin-bottom:24px; }
        .cv-log-kpi { background:var(--card); border:1px solid var(--bord); border-radius:12px; padding:16px 14px; text-align:center; position:relative; overflow:hidden; }
        .cv-log-kpi::before { content:''; position:absolute; top:0; left:0; right:0; height:3px; background:var(--kpi-cor,var(--gold)); }
        .cv-log-kpi-num { font-size:28px; font-weight:800; color:var(--kpi-cor,var(--gold)); line-height:1; }
        .cv-log-kpi-label { font-size:11px; color:var(--muted); margin-top:5px; text-transform:uppercase; letter-spacing:.4px; }
        /* Chart de atividade */
        .cv-log-chart-box { background:var(--card); border:1px solid var(--bord); border-radius:14px; padding:20px; margin-bottom:24px; }
        .cv-log-chart-title { font-size:13px; font-weight:700; color:#fff; margin:0 0 14px; }
        .cv-log-chart-title span { color:var(--muted); font-weight:400; font-size:11px; }
        .cv-log-bars { display:flex; align-items:flex-end; gap:3px; height:60px; }
        .cv-log-bar { flex:1; border-radius:3px 3px 0 0; background:var(--gold); opacity:.4; transition:opacity .2s; min-height:2px; }
        .cv-log-bar:hover { opacity:1; }
        .cv-log-bar.has-events { opacity:.8; }
        /* Filtros */
        .cv-log-filters { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:20px; }
        .cv-log-filter-btn { padding:6px 14px; border-radius:20px; border:1px solid var(--bord); background:transparent; color:var(--muted); font-size:12px; cursor:pointer; transition:all .2s; font-family:inherit; }
        .cv-log-filter-btn.active, .cv-log-filter-btn:hover { border-color:var(--gold); color:var(--gold); background:rgba(212,160,23,.08); }
        /* Timeline */
        .cv-log-timeline { position:relative; }
        .cv-log-timeline::before { content:''; position:absolute; left:20px; top:0; bottom:0; width:2px; background:var(--bord); }
        .cv-log-entry { display:flex; gap:14px; padding:12px 0; position:relative; }
        .cv-log-entry:last-child .cv-log-entry-line { display:none; }
        .cv-log-dot { width:42px; flex-shrink:0; display:flex; flex-direction:column; align-items:center; gap:4px; }
        .cv-log-dot-circle { width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:11px; border:2px solid var(--bord); background:var(--card); z-index:1; position:relative; }
        .cv-log-dot-line { flex:1; width:2px; background:transparent; }
        .cv-log-body { flex:1; background:var(--card); border:1px solid var(--bord); border-radius:10px; padding:12px 14px; transition:border-color .2s; }
        .cv-log-body:hover { border-color:rgba(212,160,23,.3); }
        .cv-log-body-top { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
        .cv-log-label { font-size:13px; font-weight:600; color:#fff; }
        .cv-log-badge { font-size:10px; padding:2px 8px; border-radius:10px; font-weight:600; }
        .cv-log-user { font-size:11px; color:var(--gold); margin-left:auto; }
        .cv-log-desc { font-size:12px; color:var(--muted); margin-top:5px; }
        .cv-log-meta { display:flex; gap:12px; margin-top:6px; font-size:10px; color:#555; }
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
            <div style="font-size:16px;color:#e0e0e0;margin-bottom:6px">Nenhum log registrado ainda</div>
            <div style="font-size:13px">As ações serão registradas automaticamente conforme você usa o painel.</div>
        </div>
        <?php else : ?>
        <div class="cv-log-timeline" id="cv-log-timeline">
        <?php foreach ($logs as $log):
            $action = isset($log->action) ? $log->action : 'other';
            $map    = isset($action_map[$action]) ? $action_map[$action] : array('icon'=>'⚙️','label'=>$action,'color'=>'#888');
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
}
