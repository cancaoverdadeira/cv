<?php
// cancao-verdadeira-child/single-musica.php
// Gerado em: 2026-06-22 01:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página individual da música: player YouTube, letra completa com
// destaque de trecho para comentário, avaliação por estrelas,
// compartilhamento em redes sociais, comentários de trecho e
// "Mais músicas" (as mais recentes, sem a atual). Mobile-first e acessível.
// v15.4.0: removidas as referências a gênero (o site é todo sertanejo).
// v15.6.0: bloco de publicidade depois da letra (CV_Monetization).
// v15.7.0: "Ouça também em" (Spotify, Deezer…) na ficha, via CV_Distribuicao.

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
$descricao   = get_post_meta( $music_id, '_cv_descricao',   true ) ?: get_the_excerpt();
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
$trend   = class_exists('CV_Ranking') ? CV_Ranking::get_trend( $music_id )    : 'same';

// Mais músicas (as mais recentes, excluindo a atual)
$rel_query = new WP_Query(array(
    'post_type'      => 'musica',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'post__not_in'   => array($music_id),
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
));
$relacionadas = $rel_query->posts;
wp_reset_postdata();

// Redes sociais para compartilhar
$url_share    = urlencode( get_permalink() );
$titulo_share = urlencode( $titulo . ' — Canção Verdadeira' );
$share_links  = array(
    'whatsapp' => 'https://wa.me/?text=' . urlencode( $titulo . ' 🎵 ' . get_permalink() ),
    'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . $url_share,
    'twitter'  => 'https://twitter.com/intent/tweet?text=' . $titulo_share . '&url=' . $url_share,
    'telegram' => 'https://t.me/share/url?url=' . $url_share . '&text=' . $titulo_share,
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

            <!-- ══════════════════════════════════════════════════
                 HERO DA MÚSICA
            ══════════════════════════════════════════════════ -->
            <div class="cv-musica-hero"
                 style="background-image:url('<?php echo esc_url($cover); ?>')">
                <div class="cv-musica-hero-overlay"></div>

                <div class="cv-musica-hero-content">

                    <!-- Breadcrumb -->
                    <nav aria-label="Navegação" style="margin-bottom:16px">
                        <ol style="list-style:none;padding:0;margin:0;display:flex;gap:6px;flex-wrap:wrap;font-size:12px;color:rgba(59,36,24,0.55)">
                            <li><a href="<?php echo esc_url(home_url('/')); ?>" style="color:rgba(59,36,24,0.55);text-decoration:none">Início</a></li>
                            <li>/</li>
                            <li><a href="<?php echo esc_url(home_url('/musicas/')); ?>" style="color:rgba(59,36,24,0.55);text-decoration:none">Músicas</a></li>
                        </ol>
                    </nav>

                    <!-- Capa e dados -->
                    <div style="display:flex;gap:24px;align-items:flex-end;flex-wrap:wrap">

                        <!-- Capa -->
                        <div style="width:160px;height:160px;border-radius:var(--cv-radius);overflow:hidden;
                                    box-shadow:0 8px 32px rgba(123,58,34,0.15);flex-shrink:0;border:2px solid rgba(201,162,126,0.6)">
                            <img src="<?php echo esc_url($cover); ?>"
                                 alt="<?php echo esc_attr("Capa de $titulo"); ?>"
                                 itemprop="image"
                                 style="width:100%;height:100%;object-fit:cover"
                                 loading="eager" />
                        </div>

                        <!-- Informações -->
                        <div style="flex:1;min-width:220px">

                            <h1 style="font-family:var(--font-display);font-size:clamp(24px,4vw,40px);
                                       font-weight:700;color:#3B2418;margin:8px 0 6px;line-height:1.1"
                                itemprop="name">
                                <?php echo esc_html($titulo); ?>
                            </h1>

                            <?php if ($artista) : ?>
                            <p style="font-size:16px;color:rgba(59,36,24,0.8);margin:0 0 4px"
                               itemprop="lyricist" itemscope itemtype="https://schema.org/Person">
                                <span itemprop="name"><?php echo esc_html($artista); ?></span>
                            </p>
                            <?php endif; ?>

                            <?php if ($compositor && $compositor !== $artista) : ?>
                            <p style="font-size:13px;color:rgba(59,36,24,0.55);margin:0 0 12px"
                               itemprop="composer" itemscope itemtype="https://schema.org/Person">
                                Compositor: <span itemprop="name"><?php echo esc_html($compositor); ?></span>
                            </p>
                            <?php endif; ?>

                            <!-- Stats rápidas -->
                            <div style="display:flex;gap:16px;flex-wrap:wrap;margin-top:12px">
                                <?php if ($show_plays) : ?>
                                <span style="font-size:13px;color:rgba(59,36,24,0.6)">
                                    ▶ <?php echo number_format($plays); ?> plays
                                </span>
                                <?php elseif ( class_exists('CV_Launch') ) : echo CV_Launch::badge(); endif; ?>
                                <?php if ($avg_rating > 0 && $show_rating) : ?>
                                <span style="font-size:13px;color:var(--cv-gold)">
                                    ★ <?php echo number_format($avg_rating, 1); ?>/5
                                    <span style="color:rgba(59,36,24,0.55);font-size:11px">
                                        (<?php echo $rating_count; ?> avaliações)
                                    </span>
                                </span>
                                <?php endif; ?>
                                <?php if ($posicao) : ?>
                                <span style="font-size:13px;color:rgba(59,36,24,0.6)">
                                    🏆 #<?php echo $posicao; ?> no ranking
                                </span>
                                <?php endif; ?>
                            </div>

                            <!-- Ações principais -->
                            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:20px">

                                <?php if ($youtube_url) : ?>
                                <button class="cv-btn cv-btn-primary cv-btn-lg"
                                        id="cv-play-btn"
                                        data-music-id="<?php echo esc_attr($music_id); ?>"
                                        data-audio-url="<?php echo esc_attr($audio_url); ?>"
                                        data-youtube-id="<?php echo esc_attr($yt_id); ?>"
                                        data-title="<?php echo esc_attr($titulo); ?>"
                                        data-cover="<?php echo esc_attr($cover); ?>"
                                        data-artista="<?php echo esc_attr($artista); ?>">
                                    <?php echo $audio_url ? '▶ Ouvir Agora' : '▶ Ver Vídeo'; ?>
                                </button>
                                <?php endif; ?>

                                <button class="cv-btn cv-btn-ghost <?php echo $is_fav ? 'cv-favorited' : ''; ?>"
                                        id="cv-fav-btn"
                                        data-music-id="<?php echo esc_attr($music_id); ?>"
                                        aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
                                        style="<?php echo $is_fav ? 'background:rgba(231,76,60,.2);border-color:#e74c3c;color:#D62C1A' : ''; ?>">
                                    <?php echo $is_fav ? '❤ Favoritado' : '♡ Favoritar'; ?>
                                    <?php if ( '' !== $fav_label ) : ?><span id="cv-fav-count">(<?php echo esc_html( $fav_label ); ?>)</span><?php endif; ?>
                                </button>

                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════
                 CORPO — LETRA + PAINEL LATERAL
            ══════════════════════════════════════════════════ -->
            <div class="cv-musica-body">

                <!-- Coluna principal: Player + Letra -->
                <div class="cv-musica-main">

                    <!-- Player YouTube incorporado -->
                    <?php if ($youtube_url) : ?>
                    <div class="cv-musica-player-wrap">
                        <div style="font-size:12px;color:var(--cv-text-dim);margin-bottom:10px;
                                    display:flex;align-items:center;gap:8px">
                            <span>▶ Player</span>
                            <span style="height:1px;flex:1;background:var(--cv-border-subtle)"></span>
                        </div>
                        <div class="cv-yt-embed-wrapper">
                            <iframe id="cv-musica-iframe"
                                    src="https://www.youtube.com/embed/<?php echo esc_attr($yt_id); ?>?enablejsapi=1&rel=0&modestbranding=1"
                                    title="<?php echo esc_attr($titulo); ?>"
                                    frameborder="0"
                                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                                    allowfullscreen
                                    loading="lazy"
                                    style="width:100%;aspect-ratio:16/9;border-radius:var(--cv-radius);border:1px solid var(--cv-border-subtle)">
                            </iframe>
                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Letra -->
                    <div class="cv-musica-letra-section">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
                            <h2 style="font-family:var(--font-display);font-size:18px;font-weight:700;margin:0;color:var(--cv-gold)">
                                📝 Letra
                            </h2>
                            <button id="cv-copy-letra-btn"
                                    class="cv-btn cv-btn-secondary cv-btn-sm"
                                    title="Copiar letra">
                                📋 Copiar letra
                            </button>
                        </div>

                        <!-- Instrução de seleção de trecho -->
                        <?php if (is_user_logged_in()) : ?>
                        <div style="background:rgba(242,165,26,0.1);border:1px solid rgba(201,162,126,0.4);
                                    border-radius:var(--cv-radius-sm);padding:10px 14px;
                                    margin-bottom:16px;font-size:12px;color:var(--cv-text-muted)">
                            💡 Selecione qualquer trecho da letra para comentar sobre ele
                        </div>
                        <?php endif; ?>

                        <div class="cv-letra-conteudo"
                             id="cv-letra-conteudo"
                             itemprop="lyrics"
                             itemscope itemtype="https://schema.org/CreativeWork">
                            <div itemprop="text" style="font-family:var(--font-body);font-size:17px;
                                      line-height:2;color:var(--cv-text);
                                      white-space:pre-wrap;user-select:text">
                                <?php
                                // Preserva quebras de linha da letra mantendo o formato
                                $letra_limpa = strip_tags($letra, '<br><p><strong><em>');
                                $letra_limpa = str_replace("\r\n", "\n", $letra_limpa);
                                echo $letra_limpa;
                                ?>
                            </div>
                        </div>

                        <!-- Popup de comentário de trecho (aparece ao selecionar texto) -->
                        <div id="cv-trecho-popup"
                             style="display:none;position:absolute;background:#FFFFFF;
                                    border:1px solid var(--cv-gold);border-radius:var(--cv-radius-sm);
                                    padding:8px 12px;z-index:100;box-shadow:var(--cv-shadow);
                                    font-size:12px;white-space:nowrap">
                            <button id="cv-comentar-trecho-btn"
                                    style="background:none;border:none;color:var(--cv-gold);
                                           cursor:pointer;font-size:12px;font-weight:700;padding:0">
                                💬 Comentar este trecho
                            </button>
                        </div>
                    </div>

                    <!-- Publicidade depois da letra (regra do site: nunca no topo, sem
                         rotação): parágrafo curto → "Leia após a publicidade" → banner.
                         Só aparece se houver banner ativo nessa posição. -->
                    <?php if ( class_exists( 'CV_Monetization' ) ) { echo CV_Monetization::bloco_apos_letra( $music_id ); } ?>

                    <!-- Modal de comentário de trecho -->
                    <div id="cv-trecho-modal"
                         style="display:none;position:fixed;inset:0;background:rgba(59,36,24,0.45);
                                z-index:1000;display:none;align-items:center;justify-content:center;
                                padding:20px">
                        <div style="background:#FFFFFF;border:1px solid var(--cv-border);
                                    border-radius:var(--cv-radius);padding:28px;max-width:500px;
                                    width:100%;position:relative;box-shadow:var(--cv-shadow-lg)">
                            <button id="cv-trecho-modal-close"
                                    style="position:absolute;top:12px;right:16px;background:none;
                                           border:none;color:var(--cv-text-dim);font-size:22px;
                                           cursor:pointer;line-height:1">✕</button>
                            <h3 style="font-family:var(--font-display);font-size:18px;margin:0 0 16px;color:var(--cv-gold)">
                                💬 Comentar trecho
                            </h3>
                            <div id="cv-trecho-selecionado"
                                 style="background:#FBF6EE;border-left:3px solid var(--cv-gold);
                                        padding:10px 14px;border-radius:4px;
                                        font-family:var(--font-body);font-style:italic;
                                        color:var(--cv-text-muted);font-size:14px;
                                        margin-bottom:16px;line-height:1.6"></div>
                            <textarea id="cv-trecho-comment"
                                      class="cv-input"
                                      rows="3"
                                      placeholder="O que você acha deste trecho? Compartilhe sua interpretação..."
                                      style="margin-bottom:12px"></textarea>
                            <div id="cv-trecho-msg"
                                 style="display:none;font-size:13px;margin-bottom:10px"></div>
                            <div style="display:flex;gap:10px;justify-content:flex-end">
                                <button id="cv-trecho-modal-close2"
                                        class="cv-btn cv-btn-secondary cv-btn-sm">Cancelar</button>
                                <button id="cv-trecho-submit"
                                        class="cv-btn cv-btn-primary cv-btn-sm">Publicar</button>
                            </div>
                        </div>
                    </div>

                </div>

                <!-- Coluna lateral: infos, avaliação, compartilhar -->
                <aside class="cv-musica-aside" role="complementary">

                    <!-- Informações da música -->
                    <div class="cv-aside-card">
                        <h3 class="cv-aside-title">ℹ Informações</h3>
                        <dl style="margin:0;display:grid;gap:10px">
                            <?php
                            $infos = array(
                                'Compositor' => $compositor,
                                'Artista'    => $artista,
                                'Álbum'      => $album,
                                'Ano'        => $ano,
                            );
                            foreach ($infos as $label => $valor) :
                                if (!$valor) continue;
                            ?>
                            <div>
                                <dt style="font-size:11px;font-weight:700;text-transform:uppercase;
                                           letter-spacing:.5px;color:var(--cv-text-dim);margin-bottom:2px">
                                    <?php echo esc_html($label); ?>
                                </dt>
                                <dd style="margin:0;font-size:14px;color:var(--cv-text)">
                                    <?php echo esc_html($valor); ?>
                                </dd>
                            </div>
                            <?php endforeach; ?>
                        </dl>
                        <?php // v15.7.0: links das plataformas (CV_Distribuicao, só quando publicada)
                        if ( class_exists( 'CV_Distribuicao' ) ) { echo CV_Distribuicao::links_html( $music_id ); } ?>
                    </div>

                    <!-- Avaliação por estrelas -->
                    <div class="cv-aside-card">
                        <h3 class="cv-aside-title">⭐ Avaliação</h3>

                        <?php if ($avg_rating > 0 && $show_rating) : ?>
                        <div style="text-align:center;margin-bottom:14px">
                            <div style="font-family:var(--font-display);font-size:40px;
                                        font-weight:700;color:var(--cv-gold);line-height:1">
                                <?php echo number_format($avg_rating, 1); ?>
                            </div>
                            <div style="font-size:22px;color:var(--cv-gold);margin:4px 0">
                                <?php
                                for ($s = 1; $s <= 5; $s++) {
                                    echo $s <= round($avg_rating) ? '★' : '☆';
                                }
                                ?>
                            </div>
                            <div style="font-size:11px;color:var(--cv-text-dim)">
                                <?php echo number_format($rating_count); ?> avaliação<?php echo $rating_count !== 1 ? 'ões' : ''; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if (is_user_logged_in()) : ?>
                        <div>
                            <p style="font-size:12px;color:var(--cv-text-muted);
                                      margin:0 0 10px;text-align:center">
                                <?php echo $user_rating ? 'Sua avaliação:' : 'Avalie esta música:'; ?>
                            </p>
                            <div class="cv-stars" id="cv-stars-widget"
                                 data-music-id="<?php echo esc_attr($music_id); ?>"
                                 data-current="<?php echo esc_attr($user_rating); ?>"
                                 style="justify-content:center">
                                <?php for ($s = 1; $s <= 5; $s++) : ?>
                                <span class="cv-star <?php echo $s <= $user_rating ? 'active' : ''; ?>"
                                      data-value="<?php echo $s; ?>"
                                      role="radio"
                                      aria-label="<?php echo $s; ?> estrela<?php echo $s > 1 ? 's' : ''; ?>"
                                      tabindex="0">★</span>
                                <?php endfor; ?>
                            </div>
                            <div id="cv-rating-msg"
                                 style="display:none;font-size:12px;text-align:center;
                                        margin-top:8px;color:var(--cv-success)"></div>
                        </div>
                        <?php else : ?>
                        <p style="font-size:12px;color:var(--cv-text-dim);text-align:center;margin:0">
                            <a href="<?php echo esc_url(cv_login_url(get_permalink())); ?>"
                               style="color:var(--cv-gold)">Faça login</a> para avaliar
                        </p>
                        <?php endif; ?>
                    </div>

                    <!-- Compartilhar -->
                    <div class="cv-aside-card">
                        <h3 class="cv-aside-title">📤 Compartilhar</h3>
                        <div style="display:flex;flex-direction:column;gap:8px">
                            <a href="<?php echo esc_url($share_links['whatsapp']); ?>"
                               target="_blank" rel="noopener"
                               class="cv-share-btn" style="--share-color:#25D366">
                                💬 WhatsApp
                            </a>
                            <a href="<?php echo esc_url($share_links['facebook']); ?>"
                               target="_blank" rel="noopener"
                               class="cv-share-btn" style="--share-color:#1877F2">
                                📘 Facebook
                            </a>
                            <a href="<?php echo esc_url($share_links['twitter']); ?>"
                               target="_blank" rel="noopener"
                               class="cv-share-btn" style="--share-color:#FBF6EE">
                                ✕ Twitter/X
                            </a>
                            <a href="<?php echo esc_url($share_links['telegram']); ?>"
                               target="_blank" rel="noopener"
                               class="cv-share-btn" style="--share-color:#2CA5E0">
                                ✈ Telegram
                            </a>
                            <button id="cv-copy-link-btn"
                                    class="cv-share-btn"
                                    style="--share-color:#F3E6D3;border:none;cursor:pointer;
                                           width:100%;text-align:left"
                                    data-url="<?php echo esc_attr(get_permalink()); ?>">
                                🔗 Copiar link
                            </button>
                        </div>
                    </div>

                    <!-- Playlist -->
                    <?php if (is_user_logged_in()) : ?>
                    <div class="cv-aside-card">
                        <h3 class="cv-aside-title">📋 Playlists</h3>
                        <button class="cv-btn cv-btn-secondary cv-btn-full cv-btn-add-playlist"
                                data-music-id="<?php echo esc_attr($music_id); ?>">
                            + Adicionar à playlist
                        </button>
                    </div>
                    <?php endif; ?>

                </aside>
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

            <!-- ══════════════════════════════════════════════════
                 MÚSICAS RELACIONADAS
            ══════════════════════════════════════════════════ -->
            <?php if (!empty($relacionadas)) : ?>
            <section class="cv-section" aria-label="Músicas relacionadas">
                <div class="cv-section-header">
                    <h2 class="cv-section-title">
                        Mais <span>músicas</span>
                    </h2>
                    <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                       class="cv-section-link">Ver todas →</a>
                </div>
                <div class="cv-grid">
                    <?php foreach ($relacionadas as $post_rel) :
                        get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $post_rel->ID, 'show_rank' => false ) );
                    endforeach; ?>
                </div>
            </section>
            <?php endif; ?>

        </article>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>
</div>


<script>
jQuery(function($){
    var AJAX   = cvPublic.ajaxUrl;
    var nonces = cvPublic.nonces;
    var musicId = <?php echo (int) get_the_ID(); ?>;

    // ── Registra play ao carregar a página ──────────────────────
    if (cvPublic.isLoggedIn || true) { // registra para todos
        setTimeout(function(){
            $.post(AJAX, {
                action:   'cv_register_play',
                nonce:    nonces.play,
                music_id: musicId
            });
        }, <?php echo (int) CV_PLAY_SECONDS * 1000; ?>); // após N segundos
    }

    // ── Botão Play ───────────────────────────────────────────────
    $('#cv-play-btn').on('click', function(){
        var d = $(this).data();
        // Se tem MP3 cadastrado: usa WaveSurfer (player do rodapé)
        if (d.audioUrl && window.CV_Player) {
            CV_Player.playById({
                musicId  : d.musicId,
                audioUrl : d.audioUrl,
                youtubeId: d.youtubeId || '',
                title    : d.title,
                cover    : d.cover,
                artista  : d.artista || ''
            });
        } else {
            // Sem MP3: rola a página até o embed do YouTube
            var $iframe = $('#cv-musica-iframe');
            if ($iframe.length) {
                $('html,body').animate({ scrollTop: $iframe.offset().top - 80 }, 400);
                if(window.CV_Theme) CV_Theme.toast('🎵 Toque o vídeo abaixo para ouvir', 'info', 3000);
            }
        }
    });

    // ── Favoritar ────────────────────────────────────────────────
    $('#cv-fav-btn').on('click', function(){
        if (!cvPublic.isLoggedIn) {
            window.location.href = cvPublic.loginUrl + '?redirect_to=' + encodeURIComponent(window.location.href);
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(AJAX, { action:'cv_toggle_favorite', nonce:nonces.favorite, music_id:musicId }, function(res){
            if (res.success) {
                var fav = res.data.action === 'added';
                $btn.toggleClass('cv-favorited', fav)
                    .attr('aria-pressed', fav ? 'true' : 'false')
                    .css({
                        background: fav ? 'rgba(231,76,60,.2)'    : '',
                        borderColor: fav ? '#e74c3c'              : '',
                        color:       fav ? '#e74c3c'              : ''
                    });
                $btn.find('span, .cv-btn-text').remove();
                $btn.text(fav ? '❤ Favoritado' : '♡ Favoritar');
                if (res.data.favorites_label) { $btn.append(' <span id="cv-fav-count">(' + res.data.favorites_label + ')</span>'); }
                if(window.CV_Theme) CV_Theme.toast(fav ? '❤ Adicionado aos favoritos' : 'Removido dos favoritos', fav ? 'success' : 'info');
            }
            $btn.prop('disabled', false);
        });
    });

    // ── Avaliação por estrelas ───────────────────────────────────
    var $stars = $('#cv-stars-widget');
    $stars.on('mouseenter', '.cv-star', function(){
        var val = $(this).data('value');
        $stars.find('.cv-star').each(function(){
            $(this).toggleClass('active', $(this).data('value') <= val);
        });
    }).on('mouseleave', function(){
        var cur = parseInt($stars.data('current')) || 0;
        $stars.find('.cv-star').each(function(){
            $(this).toggleClass('active', $(this).data('value') <= cur);
        });
    }).on('click', '.cv-star', function(){
        var val = $(this).data('value');
        $stars.data('current', val);
        $.post(AJAX, { action:'cv_rate_music', nonce:nonces.rating, music_id:musicId, rating:val }, function(res){
            if (res.success) {
                $('#cv-rating-msg').text('✓ Avaliação salva!').show();
                if(window.CV_Theme) CV_Theme.toast('★ Avaliação salva!', 'success');
            }
        });
    });
    // Teclado
    $stars.on('keydown', '.cv-star', function(e){
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); }
    });

    // ── Copiar letra ─────────────────────────────────────────────
    $('#cv-copy-letra-btn').on('click', function(){
        var txt = $('#cv-letra-conteudo').text().trim();
        navigator.clipboard.writeText(txt).then(function(){
            if(window.CV_Theme) CV_Theme.toast('📋 Letra copiada!', 'success');
        }).catch(function(){
            // fallback
            var $ta = $('<textarea style="position:fixed;left:-9999px">').val(txt).appendTo('body').select();
            document.execCommand('copy');
            $ta.remove();
            if(window.CV_Theme) CV_Theme.toast('📋 Letra copiada!', 'success');
        });
    });

    // ── Copiar link ──────────────────────────────────────────────
    $('#cv-copy-link-btn').on('click', function(){
        var url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function(){
            if(window.CV_Theme) CV_Theme.toast('🔗 Link copiado!', 'success');
        });
    });

    // ── Seleção de trecho para comentar ──────────────────────────
    var $popup    = $('#cv-trecho-popup');
    var $modal    = $('#cv-trecho-modal');
    var $excerptD = $('#cv-trecho-selecionado');
    var selText   = '';

    $('#cv-letra-conteudo').on('mouseup touchend', function(){
        var sel = window.getSelection ? window.getSelection() : null;
        selText = sel ? sel.toString().trim() : '';

        if (selText.length > 5 && selText.length < 200 && cvPublic.isLoggedIn) {
            var range = sel.getRangeAt(0);
            var rect  = range.getBoundingClientRect();
            $popup.css({
                top : rect.top + window.scrollY - 40 + 'px',
                left: rect.left + 'px'
            }).show();
        } else {
            $popup.hide();
        }
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('#cv-trecho-popup').length) { $popup.hide(); }
    });

    $('#cv-comentar-trecho-btn').on('click', function(){
        if (!selText) return;
        $excerptD.text('"' + selText + '"');
        $('#cv-trecho-comment').val('');
        $('#cv-trecho-msg').hide();
        $popup.hide();
        $modal.css('display', 'flex');
    });

    function fecharModal() { $modal.css('display', 'none'); }
    $('#cv-trecho-modal-close, #cv-trecho-modal-close2').on('click', fecharModal);
    $modal.on('click', function(e){ if ($(e.target).is($modal)) fecharModal(); });

    $('#cv-trecho-submit').on('click', function(){
        var comment = $('#cv-trecho-comment').val().trim();
        if (!comment) {
            $('#cv-trecho-msg').css('color','var(--cv-error)').text('Escreva um comentário.').show();
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Enviando...');
        $.post(AJAX, {
            action:   'cv_save_lyric_comment',
            nonce:    nonces.lyricComment,
            music_id: musicId,
            excerpt:  selText,
            comment:  comment
        }, function(res){
            if (res.success) {
                $('#cv-trecho-msg').css('color','var(--cv-success)').text('✓ Comentário publicado!').show();
                setTimeout(function(){ fecharModal(); carregarComentarios(); }, 1000);
            } else {
                $('#cv-trecho-msg').css('color','var(--cv-error)').text(res.data && res.data.message ? res.data.message : 'Erro.').show();
            }
            $btn.prop('disabled', false).text('Publicar');
        });
    });

    // ── Carrega comentários de trechos ───────────────────────────
    function carregarComentarios(){
        $.post(AJAX, {
            action:   'cv_get_lyric_comments',
            nonce:    nonces.lyricComment,
            music_id: musicId
        }, function(res){
            var $list = $('#cv-lyric-comments-list');
            if (!res.success || !res.data.comments.length) {
                $list.html('<p style="color:var(--cv-text-dim);font-size:13px">Nenhum comentário ainda. Selecione um trecho da letra para ser o primeiro!</p>');
                return;
            }
            var html = '';
            res.data.comments.forEach(function(c){
                html += '<div class="cv-lyric-comment">';
                html += '<div class="cv-lyric-comment-excerpt">"' + c.excerpt + '"</div>';
                html += '<div class="cv-lyric-comment-body">' + c.comment + '</div>';
                html += '<div class="cv-lyric-comment-meta">';
                html += '<img src="' + c.avatar + '" alt="' + c.user_name + '">';
                html += '<span><strong>' + c.user_name + '</strong></span>';
                html += '<span>· ' + c.time_human + '</span>';
                if (c.can_delete) {
                    html += ' <button class="cv-del-comment" data-id="' + c.id + '" '
                          + 'style="background:none;border:none;color:var(--cv-error);'
                          + 'font-size:11px;cursor:pointer;margin-left:auto">🗑 Excluir</button>';
                }
                html += '</div></div>';
            });
            $list.html(html);
        });
    }

    // Excluir comentário
    $(document).on('click', '.cv-del-comment', function(){
        if (!confirm('Excluir este comentário?')) return;
        var id = $(this).data('id');
        var $item = $(this).closest('.cv-lyric-comment');
        $.post(AJAX, { action:'cv_delete_lyric_comment', nonce:nonces.lyricComment, id:id }, function(res){
            if (res.success) { $item.fadeOut(300, function(){ $(this).remove(); }); }
        });
    });

    carregarComentarios();
});
</script>

<?php get_footer(); ?>
