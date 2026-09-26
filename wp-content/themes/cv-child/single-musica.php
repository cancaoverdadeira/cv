<?php
// cancao-verdadeira-child/single-musica.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página individual da música. Este arquivo só junta os dados da música
// e monta a página com as partes de template-parts/musica/ (hero, vídeo,
// letra, janela de comentário, coluna lateral, "Mais músicas").
// O JavaScript da página fica em assets/js/cv-musica.js (functions.php).
// v15.6.0: publicidade depois da letra (CV_Monetization).
// v15.10.0: dividido em partes (refatoração, fase 6) — HTML sem mudanças.
// v15.23.0: passa a cifra simples (_cv_cifra) para a parte da letra.

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! have_posts() ) { wp_redirect( home_url('/') ); exit; }
the_post();

$music_id    = get_the_ID();
$titulo      = get_the_title();
$compositor  = get_post_meta( $music_id, '_cv_compositor',  true );
$artista     = get_post_meta( $music_id, '_cv_artista',     true );
$album       = get_post_meta( $music_id, '_cv_album',       true );
$ano         = get_post_meta( $music_id, '_cv_ano',         true );
$youtube_url = get_post_meta( $music_id, '_cv_youtube_url', true );
$plays       = (int) get_post_meta( $music_id, '_cv_plays_total', true );
$favoritos   = (int) get_post_meta( $music_id, '_cv_favorites',   true );
$letra       = get_the_content();

// Capa
$cover  = cv_cover_url( $music_id, 'cv-hero' );
$yt_id      = cv_youtube_id( $youtube_url );
$audio_url   = get_post_meta( $music_id, '_cv_audio_url', true );

// Avaliação
$avg_rating  = class_exists('CV_Ratings') ? CV_Ratings::get_average( $music_id )          : 0;
$user_rating = class_exists('CV_Ratings') ? CV_Ratings::get_user_rating_value( $music_id ) : 0;
$rating_count= class_exists('CV_Ratings') ? CV_Ratings::get_count( $music_id )             : 0;

// Modo lançamento: contadores abaixo do mínimo não aparecem (ver CV_Launch)
$show_plays  = class_exists('CV_Launch') ? CV_Launch::plays_visible( $plays )          : $plays > 0;
$show_rating = class_exists('CV_Launch') ? CV_Launch::ratings_visible( $rating_count ) : true;
$fav_label   = class_exists('CV_Launch') ? CV_Launch::fav_label( $favoritos )         : (string) $favoritos;

// Favorito
$user_id = get_current_user_id();
$is_fav  = ( $user_id && class_exists('CV_Favorites') ) ? CV_Favorites::is_favorite( $user_id, $music_id ) : false;

// Posição no ranking
$posicao = class_exists('CV_Ranking') ? CV_Ranking::get_position( $music_id ) : 0;

// Redes sociais para compartilhar
$url_share    = urlencode( get_permalink() );
$titulo_share = urlencode( $titulo . ' — Canção Verdadeira' );
$share_links  = array(
    'whatsapp' => 'https://wa.me/?text=' . urlencode( $titulo . ' 🎵 ' . get_permalink() ),
    'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url_share,
    'twitter'  => 'https://twitter.com/intent/tweet?text=' . $titulo_share . '&url=' . $url_share,
    'telegram' => 'https://t.me/share/url?url=' . $url_share . '&text=' . $titulo_share,
);

// Tudo o que as partes precisam, num só pacote ($args de cada parte)
$dados = array(
    'music_id'     => $music_id,
    'titulo'       => $titulo,
    'compositor'   => $compositor,
    'artista'      => $artista,
    'album'        => $album,
    'ano'          => $ano,
    'youtube_url'  => $youtube_url,
    'yt_id'        => $yt_id,
    'audio_url'    => $audio_url,
    'cover'        => $cover,
    'letra'        => $letra,
    'historia'     => (string) get_post_meta( $music_id, '_cv_historia', true ), // v15.21.0
    'cifra'        => (string) get_post_meta( $music_id, '_cv_cifra', true ),    // v15.23.0
    'plays'        => $plays,
    'show_plays'   => $show_plays,
    'avg_rating'   => $avg_rating,
    'user_rating'  => $user_rating,
    'rating_count' => $rating_count,
    'show_rating'  => $show_rating,
    'fav_label'    => $fav_label,
    'is_fav'       => $is_fav,
    'posicao'      => $posicao,
    'share_links'  => $share_links,
);

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part('template-parts/topbar'); ?>

        <article class="cv-single-musica"
                 itemscope itemtype="https://schema.org/MusicComposition"
                 data-music-id="<?php echo esc_attr($music_id); ?>">

            <?php get_template_part( 'template-parts/musica/dedicatoria' ); // v15.22.0: veio de "Ofereça esta música" ?>
            <?php get_template_part( 'template-parts/musica/hero', null, $dados ); ?>

            <!-- ══════════════════════════════════════════════════
                 CORPO — LETRA + PAINEL LATERAL
            ══════════════════════════════════════════════════ -->
            <div class="cv-musica-body">

                <!-- Coluna principal: Player + Letra -->
                <div class="cv-musica-main">

                    <?php get_template_part( 'template-parts/musica/video', null, $dados ); ?>

                    <?php get_template_part( 'template-parts/musica/letra', null, $dados ); ?>

                    <!-- Publicidade depois da letra (regra do site: nunca no topo, sem
                         rotação): parágrafo curto → "Leia após a publicidade" → banner.
                         Só aparece se houver banner ativo nessa posição. -->
                    <?php if ( class_exists( 'CV_Monetization' ) ) { echo CV_Monetization::bloco_apos_letra( $music_id ); } ?>

                    <?php get_template_part( 'template-parts/musica/modal-trecho' ); ?>

                </div>

                <?php get_template_part( 'template-parts/musica/lateral', null, $dados ); ?>
            </div>

            <!-- ══════════════════════════════════════════════════
                 COMENTÁRIOS DE TRECHOS
            ══════════════════════════════════════════════════ -->
            <section class="cv-section" aria-label="Comentários de trechos">
                <h2 style="font-family:var(--font-display);font-size:20px;font-weight:700;
                            margin-bottom:20px;color:var(--cv-text)">
                    💬 Comentários de <span style="color:var(--cv-gold)">Trechos</span>
                </h2>

                <div id="cv-lyric-comments-list">
                    <p style="color:var(--cv-text-dim);font-size:13px">Carregando comentários...</p>
                </div>
            </section>

            <?php get_template_part( 'template-parts/musica/relacionadas', null, $dados ); ?>

        </article>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>
</div>

<?php get_footer(); ?>
