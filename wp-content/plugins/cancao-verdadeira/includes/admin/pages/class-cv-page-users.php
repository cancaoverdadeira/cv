<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-users.php
// Página "Usuários": gestão de usuários da plataforma.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Users {

    public static function render() {
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
        $cores = array('#4a90d9','#1db954','#D4A017','#9b59b6','#e74c3c','#e67e22','#1abc9c','#e91e63');
        ?>
        <div class="cv-admin-wrap cv-users-v2" style="max-width:1100px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif">
        <?php echo CV_Admin::btn_voltar(); ?>
        
        <style>
        .cv-users-v2 * { box-sizing:border-box; }
        /* Hero */
        .cv-usr-hero { background:linear-gradient(135deg,#0d1f0d 0%,#1a1a1a 100%);border:1px solid #1a3a1a;border-radius:14px;padding:22px 28px;margin-bottom:22px;display:flex;align-items:center;gap:20px; }
        .cv-usr-hero h1 { color:#1db954;font-size:20px;margin:0 0 2px;font-weight:800; }
        .cv-usr-hero p  { color:#555;font-size:12px;margin:0; }
        /* KPIs */
        .cv-usr-kpis { display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-bottom:22px; }
        @media(max-width:800px){.cv-usr-kpis{grid-template-columns:1fr 1fr;}}
        .cv-usr-kpi { background:#1a1a1a;border:1px solid #1a2e1a;border-radius:12px;padding:18px 16px;position:relative;overflow:hidden; }
        .cv-usr-kpi::after { content:"";position:absolute;bottom:0;left:0;right:0;height:3px;border-radius:0 0 12px 12px; }
        .cv-usr-kpi.k1::after{background:linear-gradient(90deg,#1db954,#22d460);}
        .cv-usr-kpi.k2::after{background:linear-gradient(90deg,#4a90d9,#5aa8f0);}
        .cv-usr-kpi.k3::after{background:linear-gradient(90deg,#D4A017,#FFD700);}
        .cv-usr-kpi.k4::after{background:linear-gradient(90deg,#9b59b6,#b278cc);}
        .cv-usr-kpi-icon  { font-size:18px;margin-bottom:6px;display:block; }
        .cv-usr-kpi-val   { font-size:28px;font-weight:800;color:#f0f0f0;line-height:1;margin-bottom:3px; }
        .cv-usr-kpi-label { font-size:11px;color:#555;text-transform:uppercase;letter-spacing:.4px; }
        .cv-usr-kpi-badge { position:absolute;top:12px;right:12px;font-size:10px;font-weight:700;padding:2px 7px;border-radius:20px; }
        .badge-green{background:rgba(29,185,84,.15);color:#1db954;}
        .badge-neu{background:rgba(255,255,255,.05);color:#444;}
        /* Mid grid */
        .cv-usr-mid { display:grid;grid-template-columns:1.6fr 1fr;gap:18px;margin-bottom:22px;align-items:start; }
        @media(max-width:900px){.cv-usr-mid{grid-template-columns:1fr;}}
        .cv-usr-panel { background:#1a1a1a;border:1px solid #1a2e1a;border-radius:12px;padding:20px 22px; }
        .cv-usr-panel h3 { font-size:12px;color:#555;text-transform:uppercase;letter-spacing:.6px;margin:0 0 14px;font-weight:600; }
        .cv-usr-panel h3 strong { color:#1db954; }
        #cv-usr-chart { width:100%;height:110px;max-height:110px; }
        /* Top ouvintes */
        .cv-top-ouv { display:flex;align-items:center;gap:10px;padding:8px 0;border-bottom:1px solid #141414; }
        .cv-top-ouv:last-child { border-bottom:none; }
        .cv-top-ouv-avatar { width:34px;height:34px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:800;flex-shrink:0; }
        .cv-top-ouv-info { flex:1;min-width:0; }
        .cv-top-ouv-name  { font-size:12px;font-weight:700;color:#ddd;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;margin-bottom:3px; }
        .cv-top-ouv-bar-bg { height:3px;background:#111;border-radius:3px; }
        .cv-top-ouv-bar-fill { height:100%;border-radius:3px;background:linear-gradient(90deg,#1db954,#22d460); }
        .cv-top-ouv-plays { font-size:11px;color:#1db954;font-weight:700;white-space:nowrap; }
        /* Toolbar */
        .cv-usr-toolbar { display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px; }
        .cv-usr-search { display:flex;gap:8px;align-items:center; }
        .cv-usr-search input { background:#111;border:1px solid #1a2e1a;color:#ddd;border-radius:6px;padding:7px 12px;font-size:12px;outline:none;min-width:220px; }
        .cv-usr-search input:focus { border-color:#1db954; }
        .cv-usr-btn { font-size:11px;padding:6px 14px;border-radius:6px;cursor:pointer;font-weight:600;border:1px solid #1a2e1a;background:#111;color:#666;transition:all .2s; }
        .cv-usr-btn:hover { border-color:#1db954;color:#1db954; }
        .cv-usr-btn.primary { background:#1db954;color:#111;border-color:#1db954; }
        .cv-usr-btn.primary:hover { background:#22d460; }
        .cv-usr-btn.danger { background:rgba(231,76,60,.12);color:#e74c3c;border-color:rgba(231,76,60,.25); }
        .cv-usr-btn.danger:hover { background:rgba(231,76,60,.2); }
        /* Tabela */
        .cv-usr-table { width:100%;border-collapse:collapse; }
        .cv-usr-table th { font-size:10px;text-transform:uppercase;letter-spacing:.5px;color:#333;padding:8px 10px;text-align:left;border-bottom:1px solid #1a1a1a; }
        .cv-usr-table td { padding:9px 10px;border-bottom:1px solid #141414;vertical-align:middle;font-size:13px; }
        .cv-usr-table tr:hover td { background:rgba(29,185,84,.03); }
        .cv-usr-table tr:last-child td { border-bottom:none; }
        .cv-usr-avatar-cell { width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800; }
        .cv-usr-name-cell { display:flex;align-items:center;gap:8px; }
        .cv-usr-role { font-size:10px;font-weight:700;padding:2px 8px;border-radius:20px;background:rgba(29,185,84,.1);color:#1db954; }
        .cv-usr-role.admin { background:rgba(212,160,23,.1);color:#D4A017; }
        .cv-usr-engage { display:flex;align-items:center;gap:4px;font-size:12px;color:#555; }
        .cv-usr-engage strong { color:#ddd; }
        .cv-usr-empty { text-align:center;padding:48px;color:#333; }
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
                <div style="font-size:28px;font-weight:800;color:#1db954;line-height:1"><?php echo number_format($total_users); ?></div>
                <div style="font-size:11px;color:#444;text-transform:uppercase;letter-spacing:.4px">usuários cadastrados</div>
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
                    <p style="color:#333;font-size:12px">Nenhum play registrado ainda.</p>
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
                <p style="color:#444"><?php echo $search?'Nenhum resultado para "'.esc_html($search).'".':'Nenhum usuário cadastrado ainda.';?></p>
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
                                <div style="font-weight:700;color:#ddd;font-size:13px"><?php echo esc_html($user->display_name);?></div>
                                <div style="font-size:10px;color:#444">@<?php echo esc_html($user->user_login);?></div>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:#666"><?php echo esc_html($user->user_email);?></td>
                    <td style="text-align:center">
                        <span class="cv-usr-role <?php echo $is_admin?'admin':'';?>"><?php echo esc_html($role);?></span>
                    </td>
                    <td style="text-align:center">
                        <div class="cv-usr-engage">
                            <span title="Plays">▶ <strong><?php echo $plays;?></strong></span>
                            <span style="color:#222">·</span>
                            <span title="Favoritos">❤ <strong><?php echo $favs;?></strong></span>
                            <span style="color:#222">·</span>
                            <span title="Playlists">📋 <strong><?php echo $pls;?></strong></span>
                        </div>
                    </td>
                    <td style="font-size:11px;color:#444"><?php echo esc_html($tempo);?></td>
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
                    plugins:{ legend:{display:false}, tooltip:{backgroundColor:'#1e1e1e',borderColor:'#1a2e1a',borderWidth:1,titleColor:'#1db954',bodyColor:'#aaa'}},
                    scales:{
                        x:{grid:{color:'rgba(255,255,255,0.03)'},ticks:{color:'#333',font:{size:10},maxTicksLimit:10}},
                        y:{grid:{color:'rgba(255,255,255,0.03)'},ticks:{color:'#333',font:{size:10},precision:0},beginAtZero:false}
                    }
                }
            }); }
        })();
        jQuery(function($){
            var nonce='<?php echo esc_js(wp_create_nonce("cv_admin_nonce"));?>';
            var ajax='<?php echo esc_js(admin_url("admin-ajax.php"));?>';
            function msg(t,ok){var $m=$('#cv-user-msg');$m.text(t).css({background:ok?'#1a2e1a':'#2e1a1a',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},3500);}
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
}
