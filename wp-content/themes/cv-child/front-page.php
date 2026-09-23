<?php
// cancao-verdadeira-child/front-page.php
// Gerado em: 2026-06-22 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página inicial do site: hero com banner configurável, grade de gêneros,
// ranking Top 10, músicas recentes e seção de destaque. Chama todos os
// template-parts do Módulo 2: sidebar, topbar, player e footer.
// Dados vêm diretamente das classes do plugin — sem AJAX no carregamento.

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dados do plugin
$top_musicas    = class_exists('CV_Ranking') ? CV_Ranking::get_top(10)    : array();
// Modo lançamento: sem audiência real suficiente, o Top 10 vira a seleção
// editorial (músicas ⭐ Destaque), com título que deixa isso explícito.
$modo_selecao   = class_exists('CV_Launch') && ! CV_Launch::ranking_ready();
if ( $modo_selecao ) { $top_musicas = CV_Launch::selection(10); }
$recentes       = class_exists('CV_Ranking') ? CV_Ranking::get_recent(8)  : array();
$destaques      = class_exists('CV_Ranking') ? CV_Ranking::get_best(5)    : array();
$generos        = get_terms(array('taxonomy' => 'cv_genre', 'hide_empty' => false, 'orderby' => 'name'));

// Configurações do hero
$banner_url     = get_option('cv_banner_url', '');
$site_name      = get_bloginfo('name');
$site_desc      = get_bloginfo('description') ?: 'Letras que tocam o coração';

// Música em destaque no hero (primeira do top)
$hero_music     = ! empty($top_musicas) ? $top_musicas[0] : null;
$hero_cover     = $banner_url ?: ( $hero_music ? cv_cover_url($hero_music->music_id, 'cv-hero') : '' );

// Ícones por gênero
$genero_icones = array(
    'sertanejo-universitario' => array('icone' => '🎸', 'cor' => '#8B4513'),
    'sertanejo-raiz'          => array('icone' => '🪗', 'cor' => '#556B2F'),
    'sertanejo-romantico'     => array('icone' => '❤',  'cor' => '#F8E7E7'),
    'modao'                   => array('icone' => '🎩', 'cor' => '#F3EEED'),
    'sertanejo-gospel'        => array('icone' => '✝',  'cor' => '#4169E1'),
    'sertanejo-sofrencia'     => array('icone' => '💔', 'cor' => '#C9A27E'),
    'sertanejo-pop'           => array('icone' => '🎤', 'cor' => '#9B59B6'),
);

// SEO
add_filter('document_title_parts', function($p){ $p['title'] = get_bloginfo('name'); unset($p['site']); return $p; });

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part('template-parts/topbar'); ?>

        <!-- ══════════════════════════════════════════════════════
             HERO SECTION
        ══════════════════════════════════════════════════════ -->
        <section class="cv-hero" aria-label="Destaque">

            <?php if ( $hero_cover ) : ?>
            <div class="cv-hero-bg"
                 style="background-image:url('<?php echo esc_url($hero_cover); ?>')"
                 role="img"
                 aria-label="Banner Canção Verdadeira"></div>
            <?php endif; ?>

            <div class="cv-hero-overlay"></div>

            <div class="cv-hero-content">
                <div class="cv-hero-tag">
                    ★ <?php echo esc_html(strtoupper($site_name)); ?>
                </div>

                <h1 class="cv-hero-title">
                    Sertanejo<br>
                    <span style="color:var(--cv-gold)">a Raiz do<br>Coração</span>
                </h1>

                <p class="cv-hero-subtitle">
                    <?php echo esc_html($site_desc); ?> ❤
                </p>

                <div class="cv-hero-actions">
                    <!-- Botão de autoplay — aparece até o usuário clicar -->
                    <button id="cv-hero-play-btn"
                            class="cv-btn cv-btn-primary cv-btn-lg cv-hero-pulse"
                            style="display:none"
                            aria-label="Tocar músicas mais populares">
                        ▶ Tocar Agora
                    </button>
                    <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                       class="cv-btn cv-btn-ghost cv-btn-lg">
                        🎵 Explorar Músicas
                    </a>
                    <a href="<?php echo esc_url(home_url('/ranking/')); ?>"
                       class="cv-btn cv-btn-ghost cv-btn-lg" style="opacity:.7">
                        🏆 Ver Ranking
                    </a>
                </div>

                <?php if ( $hero_music ) : ?>
                <div style="margin-top:24px;display:flex;align-items:center;gap:12px">
                    <button class="cv-btn cv-btn-primary"
                            data-music-id="<?php echo esc_attr($hero_music->music_id); ?>"
                            data-youtube-id="<?php echo esc_attr(cv_youtube_id(get_post_meta($hero_music->music_id, '_cv_youtube_url', true))); ?>"
                            data-title="<?php echo esc_attr($hero_music->post_title); ?>"
                            data-cover="<?php echo esc_attr(cv_cover_url($hero_music->music_id)); ?>"
                            data-audio-url="<?php echo esc_attr(get_post_meta($hero_music->music_id, '_cv_audio_url', true)); ?>"
                            onclick="CV_Player && CV_Player.playById({musicId:this.dataset.musicId, audioUrl:this.dataset.audioUrl, youtubeId:this.dataset.youtubeId, title:this.dataset.title, cover:this.dataset.cover})"
                            style="border-radius:50%;width:52px;height:52px;padding:0;font-size:20px">
                        ▶
                    </button>
                    <div>
                        <div style="font-size:12px;color:var(--cv-text-dim);text-transform:uppercase;letter-spacing:1px">
                            #1 agora
                        </div>
                        <div style="font-size:14px;font-weight:700;color:var(--cv-text)">
                            <?php echo esc_html($hero_music->post_title); ?>
                        </div>
                        <?php if ($hero_music->artista) : ?>
                        <div style="font-size:12px;color:var(--cv-text-muted)">
                            <?php echo esc_html($hero_music->artista); ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

        </section>

        <!-- ══════════════════════════════════════════════════════
             GÊNEROS
        ══════════════════════════════════════════════════════ -->
        <?php if ( ! is_wp_error($generos) && ! empty($generos) ) : ?>
        <section class="cv-section cv-section-sm" aria-label="Gêneros musicais">
            <div class="cv-section-header">
                <h2 class="cv-section-title">Explorar <span>Gêneros</span></h2>
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-section-link">Ver tudo →</a>
            </div>

            <div class="cv-genre-grid">
                <?php foreach ( $generos as $genero ) :
                    $cfg   = $genero_icones[$genero->slug] ?? array('icone' => '🎵', 'cor' => '#B8700C');
                    $url   = get_term_link($genero);
                    $count = $genero->count;
                ?>
                <a href="<?php echo esc_url($url); ?>"
                   class="cv-genre-card"
                   aria-label="<?php echo esc_attr($genero->name . ' — ' . $count . ' músicas'); ?>">
                    <div class="cv-genre-card-bg"
                         style="background:linear-gradient(135deg, <?php echo esc_attr($cfg['cor']); ?> 0%, #FFFFFF 100%)"></div>
                    <div class="cv-genre-card-overlay"></div>
                    <div class="cv-genre-card-name">
                        <?php echo $cfg['icone']; ?>
                        <?php echo esc_html($genero->name); ?>
                        <div style="font-size:10px;font-weight:400;opacity:.7;margin-top:2px">
                            <?php echo $count; ?> música<?php echo $count !== 1 ? 's' : ''; ?>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════
             TOP 10 RANKING
        ══════════════════════════════════════════════════════ -->
        <?php if ( ! empty($top_musicas) ) : ?>
        <section class="cv-section" aria-label="Ranking">
            <div class="cv-section-header">
                <?php if ( $modo_selecao ) : ?>
                <h2 class="cv-section-title">⭐ Seleção da <span>Canção Verdadeira</span></h2>
                <?php else : ?>
                <h2 class="cv-section-title">🏆 Top <span>10</span></h2>
                <a href="<?php echo esc_url(home_url('/ranking/')); ?>"
                   class="cv-section-link">Ranking completo →</a>
                <?php endif; ?>
            </div>
            <?php if ( $modo_selecao ) : ?>
            <p class="cv-selecao-nota">Músicas escolhidas pela nossa equipe enquanto o ranking dos ouvintes se forma.</p>
            <?php endif; ?>

            <ul class="cv-ranking-lista" role="list">
                <?php foreach ( $top_musicas as $i => $m ) :
                    $posicao  = (int)($m->position ?? $i + 1);
                    $cover    = cv_cover_url($m->music_id);
                    $trend    = $m->trend ?? 'same';
                    $trend_icon = array(
                        'up'   => '<span class="cv-trend-up" title="Subindo">↑</span>',
                        'down' => '<span class="cv-trend-down" title="Caindo">↓</span>',
                        'new'  => '<span class="cv-trend-new" title="Novo">★</span>',
                        'same' => '',
                    );
                    $is_top3  = $posicao <= 3;
                ?>
                <li class="cv-ranking-item <?php echo $is_top3 ? 'cv-ranking-top3' : ''; ?>"
                    role="listitem"
                    data-music-id="<?php echo esc_attr($m->music_id); ?>"
                    data-audio-url="<?php echo esc_attr(get_post_meta($m->music_id, '_cv_audio_url', true)); ?>"
                    data-youtube-id="<?php echo esc_attr(cv_youtube_id(get_post_meta($m->music_id, '_cv_youtube_url', true))); ?>"
                    data-title="<?php echo esc_attr($m->post_title); ?>"
                    data-cover="<?php echo esc_attr($cover); ?>"
                    style="cursor:pointer">

                    <!-- Posição -->
                    <div class="cv-ranking-pos"
                         style="<?php echo $is_top3 ? 'color:var(--cv-gold);font-size:22px' : ''; ?>">
                        <?php if ($modo_selecao) : ?>★
                        <?php elseif ($posicao === 1) : ?>🥇
                        <?php elseif ($posicao === 2) : ?>🥈
                        <?php elseif ($posicao === 3) : ?>🥉
                        <?php else : ?>#<?php echo $posicao; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Capa -->
                    <div class="cv-ranking-capa"
                         style="background-image:url('<?php echo esc_url($cover); ?>')"
                         role="img"
                         aria-label="<?php echo esc_attr($m->post_title); ?>"></div>

                    <!-- Dados -->
                    <div class="cv-ranking-dados">
                        <a href="<?php echo esc_url(get_permalink($m->music_id)); ?>"
                           class="cv-ranking-titulo"
                           onclick="event.stopPropagation()">
                            <?php echo esc_html($m->post_title); ?>
                        </a>
                        <?php if ( ! empty($m->artista) ) : ?>
                        <div class="cv-ranking-artista">
                            <?php echo esc_html($m->artista); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Stats -->
                    <div class="cv-ranking-stats">
                        <?php if ( $modo_selecao ) : ?>
                        <?php echo CV_Launch::plays_visible($m->plays_total ?? 0)
                            ? '<span title="Plays">▶ ' . number_format($m->plays_total) . '</span>'
                            : CV_Launch::badge(); ?>
                        <?php else : ?>
                        <?php echo $trend_icon[$trend] ?? ''; ?>
                        <span title="Plays">▶ <?php echo number_format($m->plays_total ?? 0); ?></span>
                        <span title="Favoritos">❤ <?php echo number_format($m->favorites ?? 0); ?></span>
                        <span class="cv-ranking-score" title="Score">
                            <?php echo number_format(floatval($m->score ?? 0), 1); ?>
                        </span>
                        <?php endif; ?>
                    </div>

                </li>
                <?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════
             MÚSICAS RECENTES
        ══════════════════════════════════════════════════════ -->
        <?php if ( ! empty($recentes) ) : ?>
        <section class="cv-section" aria-label="Músicas recentes">
            <div class="cv-section-header">
                <h2 class="cv-section-title">🎵 Chegando <span>Agora</span></h2>
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-section-link">Ver todas →</a>
            </div>

            <div class="cv-grid">
                <?php foreach ( $recentes as $m ) :
                    $music_id   = $m->music_id;
                    $show_rank  = false;
                    $show_genre = true;
                    get_template_part('template-parts/card-musica');
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- ══════════════════════════════════════════════════════
             SEÇÃO VAZIA (sem músicas cadastradas ainda)
        ══════════════════════════════════════════════════════ -->
        <?php if ( empty($top_musicas) && empty($recentes) ) : ?>
        <section class="cv-section" style="text-align:center;padding:80px 36px">
            <div style="font-size:56px;margin-bottom:20px">🎵</div>
            <h2 style="font-family:var(--font-display);font-size:28px;color:var(--cv-gold);margin-bottom:12px">
                Bem-vindo ao <?php echo esc_html($site_name); ?>!
            </h2>
            <p style="color:var(--cv-text-muted);font-size:16px;max-width:480px;margin:0 auto 28px;font-family:var(--font-body);font-style:italic">
                A plataforma está pronta. Cadastre a primeira música no painel administrativo para ela aparecer aqui.
            </p>
            <?php if ( current_user_can('manage_options') ) : ?>
            <a href="<?php echo esc_url(admin_url('post-new.php?post_type=musica')); ?>"
               class="cv-btn cv-btn-primary cv-btn-lg">
                + Cadastrar Primeira Música
            </a>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main><!-- .cv-main -->

</div><!-- .cv-app -->

<!-- JS: click no item do ranking para tocar -->
<script>
jQuery(function($){

    // ── Autoplay na home ─────────────────────────────────────────
    // Estratégia: tenta silenciado (bypassa política do browser)
    // Quando player carrega, exibe botão pulsante para ativar som
    if (window.cvTheme && cvTheme.isFrontPage) {

        // Mostra botão imediatamente
        $('#cv-hero-play-btn').show();

        // Aguarda player estar pronto e tenta autoplay mudo
        var autoplayInterval = setInterval(function(){
            if (window.CV_Player && window.YT && window.YT.Player) {
                clearInterval(autoplayInterval);
                // Player já carregou via onYouTubeIframeAPIReady
                // Verifica se já está tocando (loadAndPlay chamado no onReady)
                setTimeout(function(){
                    if (!CV_Player.isPlaying()) {
                        // Não tocou — mostra botão destacado
                        $('#cv-hero-play-btn').addClass('cv-pulse-active');
                    } else {
                        // Está tocando — esconde botão
                        $('#cv-hero-play-btn').fadeOut(500);
                    }
                }, 3000);
            }
        }, 500);

        // Clique no botão hero: garante que toca com som
        $('#cv-hero-play-btn').on('click', function(){
            $(this).fadeOut(400);
            if (window.CV_Player) {
                if (CV_Player.isPlaying()) {
                    // Já está tocando mudo — só desmutar
                    try {
                        var yt = document.getElementById('cv-yt-player');
                        if (yt && yt.contentWindow) {
                            // Unmute via API publica
                        }
                    } catch(e) {}
                } else {
                    // Não está tocando — dispara
                    $('#cv-btn-play').trigger('click');
                }
            }
        });
    }

    // ── Click no ranking para tocar ──────────────────────────────
    $(document).on('click', '.cv-ranking-item[data-music-id]', function(e){
        if ($(e.target).is('a')) { return; }
        var d = $(this).data();
        if (d.musicId && d.youtubeId && window.CV_Player) {
            CV_Player.playById({
                musicId   : d.musicId,
                youtubeId : d.youtubeId,
                title     : d.title || '',
                cover     : d.cover || ''
            });
        }
    });
});
</script>

<!-- DEBUG PARALLAX: remover após confirmar que funciona -->
<script>
jQuery(function($){
    var $bg = $('.cv-hero-bg');
    console.log('[CV Parallax] cv-hero-bg encontrado:', $bg.length);
    console.log('[CV Parallax] cv-theme.js carregado:', typeof CV_Theme !== 'undefined');
    if ($bg.length) {
        console.log('[CV Parallax] Estilo inicial:', $bg[0].style.transform);
        console.log('[CV Parallax] Computed position:', window.getComputedStyle($bg[0]).position);
        // Testa o parallax manualmente
        setTimeout(function(){
            $bg[0].style.transform = 'translateY(30px)';
            console.log('[CV Parallax] Teste aplicado — o banner deve ter descido 30px');
            setTimeout(function(){
                $bg[0].style.transform = 'translateY(0px)';
                console.log('[CV Parallax] Teste revertido');
            }, 1500);
        }, 500);
    }
});
</script>

<style>
/* Botão hero pulsante — chama atenção para o play */
.cv-hero-pulse {
    animation: cv-hero-pulse 2s ease-in-out infinite;
    background: var(--cv-accent) !important;
    box-shadow: 0 0 0 0 rgba(242,165,26,0.91);
}
@keyframes cv-hero-pulse {
    0%   { box-shadow: 0 0 0 0 rgba(242,165,26,0.91); }
    50%  { box-shadow: 0 0 0 14px rgba(242,165,26,0); transform: translateY(-1px) scale(1.03); }
    100% { box-shadow: 0 0 0 0 rgba(242,165,26,0); }
}
.cv-pulse-active {
    font-size: 17px;
    padding: 16px 36px;
}
</style>

<?php get_footer(); ?>
