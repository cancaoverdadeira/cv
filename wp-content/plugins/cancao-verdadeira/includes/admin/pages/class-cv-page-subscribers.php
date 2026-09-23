<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-subscribers.php
// Página "Assinantes": lista e gestão de assinantes da newsletter.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Subscribers {

    public static function render() {
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
}
