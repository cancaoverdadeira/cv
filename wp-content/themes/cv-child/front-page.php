<?php
// cancao-verdadeira-child/front-page.php
// Gerado em: 2026-06-22 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página inicial do site: hero com banner configurável,
// ranking Top 10, músicas recentes e seção de destaque. Chama todos os
// template-parts do Módulo 2: sidebar, topbar, player e footer.
// Dados vêm diretamente das classes do plugin — sem AJAX no carregamento.
// v15.2.0 (23/09/2026): banner da marca menor, no canto superior direito;
// seções Top 10, Chegando Agora e Mais Favoritadas sempre visíveis (cards
// "Em breve" enquanto não há conteúdo); 3 posts do Blog no fim da página.
// v15.4.0: removida a grade "Explorar Gêneros" (o site é todo sertanejo).
// v15.3.0: hero em duas colunas centralizadas (texto + marca maior, sem o
// vão entre eles), título numa linha só e maior, selo "Sertanejo autoral".
// v15.20.0: seções "🔥 Mais tocadas" e "⭐ Melhor avaliadas" depois de "Mais Favoritadas".
// v15.27.0 (pedido do Eduardo): "Mais Favoritadas" virou "Favoritas" e "Do Blog" virou
// "Blog"; Favoritas, Mais tocadas e Melhor avaliadas sempre em linhas de 4 cartões e o
// Blog em linhas de 3, completando com cartões "Em breve" (cv_completar_grade).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dados do plugin
$top_musicas    = class_exists('CV_Ranking') ? CV_Ranking::get_top(10)    : array();
// Modo lançamento: sem audiência real suficiente, o Top 10 vira a seleção
// editorial (músicas ⭐ Destaque), com título que deixa isso explícito.
$modo_selecao   = class_exists('CV_Launch') && ! CV_Launch::ranking_ready();
if ( $modo_selecao ) { $top_musicas = CV_Launch::selection(10); }
$recentes       = class_exists('CV_Ranking') ? CV_Ranking::get_recent(8)  : array();
$destaques      = class_exists('CV_Ranking') ? CV_Ranking::get_best(5)    : array();
// Mais Favoritadas: no modo lançamento os números ainda são baixos, então a
// seção mostra "Em breve" até o ranking dos ouvintes se formar.
$favoritas      = ( class_exists('CV_Ranking') && method_exists('CV_Ranking', 'get_most_favorited') && ! $modo_selecao )
                  ? CV_Ranking::get_most_favorited(8) : array();
// v15.20.0: "🔥 Mais tocadas" e "⭐ Melhor avaliadas" — só entram músicas
// com pelo menos 1 play / 1 avaliação de verdade; sem dados, "Em breve".
$mais_tocadas   = ( class_exists('CV_Ranking') && method_exists('CV_Ranking', 'get_most_played') ) ? CV_Ranking::get_most_played(8) : array();
$melhor_notas   = ( class_exists('CV_Ranking') && method_exists('CV_Ranking', 'get_best_rated') )  ? CV_Ranking::get_best_rated(8)  : array();
// Últimos 3 posts do Blog (capa = imagem destacada).
$blog_term      = get_term_by('slug', 'blog', 'category');
$blog_posts     = $blog_term ? get_posts(array(
    'post_type'      => 'post',
    'post_status'    => 'publish',
    'posts_per_page' => 3,
    'cat'            => $blog_term->term_id,
)) : array();

// v15.27.0: cv_completar_grade() fica em functions.php (usada também nas páginas internas)
$cv_linha_musicas = 4;
$cv_linha_blog    = 3;

// Configurações do hero
$banner_url     = get_option('cv_banner_url', '');
$site_name      = get_bloginfo('name');
$site_desc      = get_bloginfo('description') ?: 'Letras que tocam o coração';

// Música em destaque no hero (primeira do top)
$hero_music     = ! empty($top_musicas) ? $top_musicas[0] : null;
// O banner da marca (Aparência → Banner) aparece pequeno, no canto superior
// direito. Sem banner, a capa da música em destaque continua como fundo.
$hero_cover     = $banner_url ? '' : ( $hero_music ? cv_cover_url($hero_music->music_id, 'cv-hero') : '' );

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
        <section class="cv-hero<?php echo $banner_url ? ' cv-hero--marca' : ''; ?>" aria-label="Destaque">

            <?php if ( $hero_cover ) : ?>
            <div class="cv-hero-bg"
                 style="background-image:url('<?php echo esc_url($hero_cover); ?>')"
                 role="img"
                 aria-label="Banner Canção Verdadeira"></div>
            <?php endif; ?>

            <?php if ( $hero_cover ) : // a faixa de contraste só é útil sobre foto ?>
            <div class="cv-hero-overlay"></div>
            <?php endif; ?>


            <div class="cv-hero-content">
                <div class="cv-hero-tag">★ Sertanejo autoral</div>

                <h1 class="cv-hero-title">
                    Sertanejo <span class="cv-hero-title-destaque">a Raiz do Coração</span>
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

            <?php if ( $banner_url ) : ?>
            <img class="cv-hero-marca"
                 src="<?php echo esc_url($banner_url); ?>"
                 alt="<?php echo esc_attr__('Canção Verdadeira', 'cancao-verdadeira'); ?>"
                 width="1600" height="1131" fetchpriority="high">
            <?php endif; ?>

        </section>

        <!-- ══════════════════════════════════════════════════════
             TOP 10 RANKING
        ══════════════════════════════════════════════════════ -->
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
            <?php if ( empty($top_musicas) ) :
                get_template_part('template-parts/card-em-breve', null, array('quantidade' => 5, 'icone' => '🏆', 'texto' => 'Em breve'));
            else : ?>
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
            <?php endif; ?>
        </section>

        <!-- ══════════════════════════════════════════════════════
             MÚSICAS RECENTES
        ══════════════════════════════════════════════════════ -->
        <section class="cv-section" aria-label="Músicas recentes">
            <div class="cv-section-header">
                <h2 class="cv-section-title">🎵 Chegando <span>Agora</span></h2>
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-section-link">Ver todas →</a>
            </div>

            <?php if ( empty($recentes) ) :
                get_template_part('template-parts/card-em-breve', null, array('quantidade' => 5, 'icone' => '🎵', 'texto' => 'Em breve'));
            else : ?>
            <div class="cv-grid">
                <?php foreach ( $recentes as $m ) :
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $m->music_id, 'show_rank' => false ) );
                endforeach; ?>
            </div>
            <?php endif; ?>
        </section>

        <!-- ══════════════════════════════════════════════════════
             MAIS FAVORITADAS
        ══════════════════════════════════════════════════════ -->
        <section class="cv-section" aria-label="Favoritas">
            <div class="cv-section-header">
                <h2 class="cv-section-title">❤️ <span>Favoritas</span></h2>
            </div>
            <div class="cv-grid cv-grid-col-4 cv-grid-linha4">
                <?php foreach ( $favoritas as $m ) :
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $m->music_id, 'show_rank' => false ) );
                endforeach; ?>
                <?php $falta = cv_completar_grade( count( $favoritas ), $cv_linha_musicas );
                if ( $falta ) { get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => $falta, 'icone' => '❤️', 'texto' => 'Em breve', 'sem_grade' => true ) ); } ?>
            </div>
        </section>

        <?php
        // v15.20.0: duas seções no mesmo formato de "Favoritas"
        $secoes_extra = array(
            array( 'rotulo' => 'Mais tocadas',    'icone' => '🔥', 'titulo' => 'Mais <span>Tocadas</span>',    'lista' => $mais_tocadas ),
            array( 'rotulo' => 'Melhor avaliadas', 'icone' => '⭐', 'titulo' => 'Melhor <span>Avaliadas</span>', 'lista' => $melhor_notas ),
        );
        foreach ( $secoes_extra as $sx ) : ?>
        <section class="cv-section" aria-label="<?php echo esc_attr( $sx['rotulo'] ); ?>">
            <div class="cv-section-header">
                <h2 class="cv-section-title"><?php echo esc_html( $sx['icone'] ); ?> <?php echo wp_kses( $sx['titulo'], array( 'span' => array() ) ); ?></h2>
                <a href="<?php echo esc_url( home_url( '/ranking/' ) ); ?>" class="cv-section-link">Ver ranking →</a>
            </div>
            <div class="cv-grid cv-grid-col-4 cv-grid-linha4">
                <?php foreach ( $sx['lista'] as $m ) :
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $m->music_id, 'show_rank' => false ) );
                endforeach; ?>
                <?php $falta = cv_completar_grade( count( $sx['lista'] ), $cv_linha_musicas );
                if ( $falta ) { get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => $falta, 'icone' => $sx['icone'], 'texto' => 'Em breve', 'sem_grade' => true ) ); } ?>
            </div>
        </section>
        <?php endforeach; ?>

        <!-- ══════════════════════════════════════════════════════
             BLOG (3 posts mais recentes, completando com "Em breve")
        ══════════════════════════════════════════════════════ -->
        <section class="cv-section" aria-label="Blog">
            <div class="cv-section-header">
                <h2 class="cv-section-title">📝 <span>Blog</span></h2>
                <?php if ( $blog_posts && $blog_term ) : ?>
                <a href="<?php echo esc_url(get_term_link($blog_term)); ?>"
                   class="cv-section-link">Ver todos →</a>
                <?php endif; ?>
            </div>
            <?php if ( empty($blog_posts) ) :
                get_template_part('template-parts/card-em-breve', null, array('quantidade' => 3, 'icone' => '📝', 'texto' => 'Em breve', 'formato' => 'post'));
            else : ?>
            <div class="cv-blog-grid cv-blog-grid-home">
                <?php foreach ( $blog_posts as $bp ) : ?>
                <article class="cv-blog-card">
                    <a href="<?php echo esc_url(get_permalink($bp)); ?>" class="cv-blog-card-img" tabindex="-1" aria-hidden="true">
                        <?php if ( has_post_thumbnail($bp) ) : ?>
                            <?php echo get_the_post_thumbnail($bp, 'medium_large', array('loading' => 'lazy')); ?>
                        <?php else : ?>
                            <span class="cv-blog-card-ph">📝</span>
                        <?php endif; ?>
                    </a>
                    <div class="cv-blog-card-body">
                        <time datetime="<?php echo esc_attr(get_the_date('c', $bp)); ?>"><?php echo esc_html(get_the_date('', $bp)); ?></time>
                        <h3><a href="<?php echo esc_url(get_permalink($bp)); ?>"><?php echo esc_html(get_the_title($bp)); ?></a></h3>
                        <p><?php echo esc_html(wp_trim_words(get_the_excerpt($bp), 22, '…')); ?></p>
                        <a href="<?php echo esc_url(get_permalink($bp)); ?>" class="cv-blog-more">Ler post →</a>
                    </div>
                </article>
                <?php endforeach; ?>
                <?php $falta = cv_completar_grade( count( $blog_posts ), $cv_linha_blog );
                if ( $falta ) { get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => $falta, 'icone' => '📝', 'texto' => 'Em breve', 'formato' => 'post', 'sem_grade' => true ) ); } ?>
            </div>
            <?php endif; ?>
        </section>

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



<?php get_footer(); ?>
