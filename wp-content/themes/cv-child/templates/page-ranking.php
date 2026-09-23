<?php
/*
 * Template Name: Ranking
 */
// cancao-verdadeira-child/templates/page-ranking.php
// Gerado em: 2026-06-22 02:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Ranking dinâmico público: Top Geral, 7 dias, 24h e 30 dias.
// v15.4.0: removida a aba "Por Gênero" (o site é todo sertanejo).
// Dados vindos de CV_Ranking::get_top(), get_by_period() e get_recent().
// Abas JS sem reload de página. Mobile-first.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$top_geral     = class_exists('CV_Ranking') ? CV_Ranking::get_top(20)              : array();
$top_24h       = class_exists('CV_Ranking') ? CV_Ranking::get_by_period('24h', 10) : array();
$top_7d        = class_exists('CV_Ranking') ? CV_Ranking::get_by_period('7d', 10)  : array();
$top_30d       = class_exists('CV_Ranking') ? CV_Ranking::get_by_period('30d', 10) : array();
$ultima_atuali = get_option('cv_ranking_last_update', '');
// Modo lançamento: sem audiência real suficiente, mostra a seleção editorial.
$modo_selecao  = class_exists('CV_Launch') && ! CV_Launch::ranking_ready();

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part('template-parts/topbar'); ?>

        <!-- Cabeçalho -->
        <div style="background:linear-gradient(135deg,#F8F3E7 0%,#FFFFFF 100%);
                    padding:40px 36px 28px;border-bottom:1px solid var(--cv-border-subtle)">
            <h1 style="font-family:var(--font-display);font-size:36px;font-weight:700;margin:0 0 8px">
                🏆 <span style="color:var(--cv-gold)">Ranking</span> de Músicas
            </h1>
            <p style="color:var(--cv-text-muted);margin:0;font-size:14px">
                Baseado em plays, favoritos e avaliações dos usuários
                <?php if ($ultima_atuali) : ?>
                · Atualizado <?php echo human_time_diff(strtotime($ultima_atuali), current_time('timestamp')); ?> atrás
                <?php endif; ?>
            </p>
        </div>

        <?php if ( $modo_selecao ) : ?>
        <div class="cv-section">
            <div class="cv-selecao-aviso">
                <strong>O ranking dos ouvintes está se formando.</strong>
                Ele aparece aqui assim que as músicas tiverem audiência suficiente.
                Enquanto isso, conheça a <strong>Seleção da Canção Verdadeira</strong>, escolhida pela nossa equipe.
            </div>
            <div class="cv-grid">
                <?php
                $selecao = CV_Launch::selection(20);
                $sel_q   = new WP_Query( array(
                    'post_type'      => 'musica',
                    'post__in'       => $selecao ? wp_list_pluck( $selecao, 'music_id' ) : array( 0 ),
                    'orderby'        => 'post__in',
                    'posts_per_page' => 20,
                ) );
                while ( $sel_q->have_posts() ) : $sel_q->the_post();
                    get_template_part('template-parts/card-musica');
                endwhile;
                wp_reset_postdata();
                if ( ! $selecao ) : ?>
                <div class="cv-empty">Nenhuma música em destaque ainda.</div>
                <?php endif; ?>
            </div>
        </div>
        <?php else : ?>

        <!-- Abas de período -->
        <div style="padding:0 36px;background:var(--cv-bg-card);
                    border-bottom:1px solid var(--cv-border-subtle)">
            <div class="cv-dash-tabs" role="tablist">
                <?php
                $abas = array(
                    array('id' => 'geral', 'label' => '🏆 Top Geral'),
                    array('id' => '7d',    'label' => '📅 7 dias'),
                    array('id' => '24h',   'label' => '⚡ 24 horas'),
                    array('id' => '30d',   'label' => '📆 30 dias'),
                );
                foreach ($abas as $i => $aba) : ?>
                <button class="cv-dash-tab <?php echo $i === 0 ? 'active' : ''; ?>"
                        data-tab="<?php echo esc_attr($aba['id']); ?>"
                        role="tab"
                        aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>">
                    <?php echo esc_html($aba['label']); ?>
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <div style="padding:28px 36px">

            <!-- Aba: Top Geral -->
            <div id="cv-rank-geral" class="cv-rank-panel">
                <?php if (!empty($top_geral)) : ?>
                <?php echo renderRankList($top_geral, true); ?>
                <?php else : ?>
                <div class="cv-empty">Nenhuma música no ranking ainda.</div>
                <?php endif; ?>
            </div>

            <!-- Aba: 7 dias -->
            <div id="cv-rank-7d" class="cv-rank-panel" style="display:none">
                <?php if (!empty($top_7d)) : ?>
                <?php echo renderRankList($top_7d, false, 'plays_7d'); ?>
                <?php else : ?>
                <div class="cv-empty">Sem dados suficientes para os últimos 7 dias.</div>
                <?php endif; ?>
            </div>

            <!-- Aba: 24h -->
            <div id="cv-rank-24h" class="cv-rank-panel" style="display:none">
                <?php if (!empty($top_24h)) : ?>
                <?php echo renderRankList($top_24h, false, 'plays_24h'); ?>
                <?php else : ?>
                <div class="cv-empty">Sem dados suficientes para as últimas 24 horas.</div>
                <?php endif; ?>
            </div>

            <!-- Aba: 30 dias -->
            <div id="cv-rank-30d" class="cv-rank-panel" style="display:none">
                <?php if (!empty($top_30d)) : ?>
                <?php echo renderRankList($top_30d, false, 'plays_30d'); ?>
                <?php else : ?>
                <div class="cv-empty">Sem dados suficientes para os últimos 30 dias.</div>
                <?php endif; ?>
            </div>

        </div>
        <?php endif; // modo_selecao ?>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>
    </main>
</div>

<style>
.cv-dash-tabs { display:flex; gap:4px; border-bottom:1px solid var(--cv-border-subtle); flex-wrap:wrap; }
.cv-dash-tab { background:none; border:none; color:var(--cv-text-muted); font-size:14px; font-weight:600; padding:12px 20px; cursor:pointer; border-bottom:2px solid transparent; transition:all .2s; white-space:nowrap; }
.cv-dash-tab:hover { color:var(--cv-text); }
.cv-dash-tab.active { color:var(--cv-gold); border-bottom-color:var(--cv-gold); }
</style>

<script>
jQuery(function($){
    // Abas
    $('.cv-dash-tab').on('click', function(){
        var tab = $(this).data('tab');
        $('.cv-dash-tab').removeClass('active').attr('aria-selected','false');
        $(this).addClass('active').attr('aria-selected','true');
        $('.cv-rank-panel').hide();
        $('#cv-rank-' + tab).show();
    });

    // Click em item do ranking
    $(document).on('click', '.cv-ranking-item', function(e){
        if ($(e.target).is('a')) return;
        var $a = $(this).find('.cv-ranking-titulo');
        if ($a.length) window.location.href = $a.attr('href');
    });
});
</script>

<?php
// Helper: renderiza a lista de ranking
function renderRankList($items, $show_score = true, $plays_field = 'plays_total') {
    if (empty($items)) return '';

    $trend_map = array(
        'up'   => '<span class="cv-trend-up" title="Subindo">↑</span>',
        'down' => '<span class="cv-trend-down" title="Caindo">↓</span>',
        'new'  => '<span class="cv-trend-new" title="Novo">★</span>',
        'same' => '',
    );

    $html = '<ul class="cv-ranking-lista" role="list">';
    foreach ($items as $i => $m) {
        $pos   = isset($m->position) && $m->position ? (int)$m->position : ($i + 1);
        $cover = cv_cover_url($m->music_id);
        $trend = $m->trend ?? 'same';
        $plays = isset($m->$plays_field) ? number_format((int)$m->$plays_field) : '—';
        $medal = $pos === 1 ? '🥇' : ($pos === 2 ? '🥈' : ($pos === 3 ? '🥉' : '#' . $pos));

        $html .= '<li class="cv-ranking-item ' . ($pos <= 3 ? 'cv-ranking-top3' : '') . '" '
               . 'role="listitem" style="cursor:pointer" '
               . 'data-url="' . esc_url(get_permalink($m->music_id)) . '">'
               . '<div class="cv-ranking-pos" style="' . ($pos <= 3 ? 'font-size:22px' : '') . '">' . $medal . '</div>'
               . '<div class="cv-ranking-capa" style="background-image:url(\'' . esc_url($cover) . '\')" role="img" aria-label="' . esc_attr($m->post_title) . '"></div>'
               . '<div class="cv-ranking-dados">'
               . '<a href="' . esc_url(get_permalink($m->music_id)) . '" class="cv-ranking-titulo" onclick="event.stopPropagation()">' . esc_html($m->post_title) . '</a>'
               . '<div class="cv-ranking-artista">' . esc_html($m->artista ?? '') . '</div>'
               . '</div>'
               . '<div class="cv-ranking-stats">'
               . ($trend_map[$trend] ?? '')
               . '<span title="Plays">▶ ' . $plays . '</span>'
               . '<span title="Favoritos">❤ ' . number_format((int)($m->favorites ?? 0)) . '</span>'
               . ($show_score ? '<span class="cv-ranking-score" title="Score">' . number_format((float)($m->score ?? 0), 1) . '</span>' : '')
               . '</div>'
               . '</li>';
    }
    $html .= '</ul>';
    return $html;
}
?>

<?php get_footer(); ?>
