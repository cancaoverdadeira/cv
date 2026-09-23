<?php
// cancao-verdadeira-child/template-parts/card-musica.php
// Gerado em: 2026-06-21 23:45:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Card reutilizável de música. Chamado pelos templates de listagem,
// home e ranking. Recebe $args via set_query_var() ou usa the_post().
// Variáveis disponíveis (todas opcionais com fallback automático):
//   $music_id   — ID do post (padrão: get_the_ID())
//   $show_rank  — bool: exibir posição no ranking (padrão: false)
// v15.4.0: removida a etiqueta de gênero (o site é todo sertanejo).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Resolve ID e dados.
// v15.5.0: get_template_part() NÃO enxerga variáveis do arquivo que chama;
// o ID precisa vir em $args (get_template_part(..., null, array('music_id' => X))).
// Antes, fora de um loop the_post(), todos os cards mostravam a mesma música.
$music_id   = isset($args['music_id'])  ? (int) $args['music_id']   : get_the_ID();
$show_rank  = isset($args['show_rank']) ? (bool) $args['show_rank'] : false;

if ( ! $music_id ) { return; }

$youtube_url = get_post_meta( $music_id, '_cv_youtube_url', true );
$audio_url   = get_post_meta( $music_id, '_cv_audio_url',   true ); // v15.5.0: era usada sem ser definida
$compositor  = get_post_meta( $music_id, '_cv_compositor',  true );
$artista     = get_post_meta( $music_id, '_cv_artista',     true );
$plays       = (int) get_post_meta( $music_id, '_cv_plays_total', true );
$favoritos   = (int) get_post_meta( $music_id, '_cv_favorites',   true );
$avg         = (float) get_post_meta( $music_id, '_cv_avg_rating', true );
$titulo      = get_the_title( $music_id );
$url         = get_permalink( $music_id );
$user_id     = get_current_user_id();

// Verificação de favorito (usa o plugin)
$is_fav = false;
if ( $user_id && class_exists('CV_Favorites') ) {
    $is_fav = CV_Favorites::is_favorite( $user_id, $music_id );
}

// Posição no ranking
$posicao = 0;
if ( $show_rank && class_exists('CV_Ranking') ) {
    $posicao = CV_Ranking::get_position( $music_id );
}

// Indicador de tendência
$trend = '';
if ( $show_rank && class_exists('CV_Ranking') ) {
    $trend = CV_Ranking::get_trend( $music_id );
}

// Capa da música
$cover = cv_cover_url( $music_id );

// YouTube ID para o player
$yt_id = cv_youtube_id( $youtube_url );
?>

<div class="cv-card"
     data-music-id="<?php echo esc_attr($music_id); ?>"
     data-audio-url="<?php echo esc_attr($audio_url); ?>"
     data-youtube-id="<?php echo esc_attr($yt_id); ?>"
     data-youtube-url="<?php echo esc_attr($youtube_url); ?>"
     data-title="<?php echo esc_attr($titulo); ?>"
     data-artista="<?php echo esc_attr($artista ?: $compositor); ?>"
     data-cover="<?php echo esc_attr($cover); ?>">

    <!-- Capa com overlay de play -->
    <a href="<?php echo esc_url($url); ?>"
       class="cv-card-capa-link"
       aria-label="<?php echo esc_attr("Ouvir $titulo"); ?>">
        <div class="cv-card-capa"
             style="background-image:url('<?php echo esc_url($cover); ?>')"
             role="img"
             aria-label="<?php echo esc_attr("Capa de $titulo"); ?>">

            <?php if ( $posicao && $posicao <= 10 ) : ?>
            <span class="cv-card-posicao">#<?php echo esc_html($posicao); ?></span>
            <?php endif; ?>

            <?php if ( $trend && $trend !== 'same' ) :
                $trend_map = array(
                    'up'   => array('↑', 'cv-trend-up',   'Subindo no ranking'),
                    'down' => array('↓', 'cv-trend-down', 'Caindo no ranking'),
                    'new'  => array('★', 'cv-trend-new',  'Novo no ranking'),
                );
                $td = $trend_map[$trend] ?? null;
                if ($td) :
            ?>
            <span class="<?php echo esc_attr($td[1]); ?>"
                  title="<?php echo esc_attr($td[2]); ?>"
                  style="position:absolute;top:8px;right:8px;font-size:12px;font-weight:700;
                         background:rgba(59,36,24,0.45);padding:2px 6px;border-radius:4px">
                <?php echo $td[0]; ?>
            </span>
            <?php endif; endif; ?>

            <div class="cv-card-play-overlay">
                <span class="cv-card-play-icon" aria-hidden="true">▶</span>
            </div>
        </div>
    </a>

    <!-- Informações -->
    <div class="cv-card-info">

        <a href="<?php echo esc_url($url); ?>"
           class="cv-card-titulo"
           title="<?php echo esc_attr($titulo); ?>">
            <?php echo esc_html($titulo); ?>
        </a>

        <?php if ( $artista || $compositor ) : ?>
        <p class="cv-card-artista">
            <?php echo esc_html( $artista ?: $compositor ); ?>
        </p>
        <?php endif; ?>

        <!-- Ações e estatísticas -->
        <div class="cv-card-acoes">

            <?php if ( ! class_exists('CV_Launch') || CV_Launch::plays_visible($plays) ) : ?>
            <span class="cv-card-stat" title="Plays">
                ▶ <span class="cv-play-count"
                         data-music-id="<?php echo esc_attr($music_id); ?>">
                    <?php echo number_format($plays); ?>
                </span>
            </span>
            <?php else : echo CV_Launch::badge(); endif; ?>

            <button class="cv-btn-favorite <?php echo $is_fav ? 'cv-favorited' : ''; ?>"
                    data-music-id="<?php echo esc_attr($music_id); ?>"
                    aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
                    aria-label="<?php echo $is_fav ? 'Remover dos favoritos' : 'Adicionar aos favoritos'; ?>"
                    title="Favoritar">
                ❤
                <span class="cv-fav-count"
                      data-music-id="<?php echo esc_attr($music_id); ?>">
                    <?php echo class_exists('CV_Launch') ? CV_Launch::fav_label($favoritos) : $favoritos; ?>
                </span>
            </button>

            <?php
            // A média só aparece com avaliações suficientes (modo lançamento).
            $rating_count = class_exists('CV_Ratings') ? (int) CV_Ratings::get_count($music_id) : PHP_INT_MAX;
            if ( $avg > 0 && ( ! class_exists('CV_Launch') || CV_Launch::ratings_visible($rating_count) ) ) : ?>
            <span class="cv-card-stat" title="Avaliação média">
                ★ <?php echo number_format($avg, 1); ?>
            </span>
            <?php endif; ?>

            <button class="cv-btn-add-playlist"
                    data-music-id="<?php echo esc_attr($music_id); ?>"
                    aria-label="Adicionar à playlist"
                    title="Adicionar à playlist">
                ＋
            </button>

        </div>
    </div>

</div>
