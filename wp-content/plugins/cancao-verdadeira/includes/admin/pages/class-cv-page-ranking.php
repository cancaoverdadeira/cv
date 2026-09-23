<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-ranking.php
// Página "Ranking": listagem e ajustes do ranking de músicas.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Ranking {

    public static function render() {
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
}
