<?php
// cancao-verdadeira-child/template-parts/musica/hero.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Topo da página da música: capa, título, artista, compositor, números
// (plays, nota, posição no ranking) e botões Ouvir e Favoritar.
// Chamado por single-musica.php com get_template_part( ..., null, $args ).
// v15.10.0: separado do single-musica.php (refatoração, fase 6).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dados vêm de $args (get_template_part não compartilha variáveis).
$cover        = isset( $args['cover'] ) ? $args['cover'] : '';
$titulo       = isset( $args['titulo'] ) ? $args['titulo'] : '';
$artista      = isset( $args['artista'] ) ? $args['artista'] : '';
$compositor   = isset( $args['compositor'] ) ? $args['compositor'] : '';
$show_plays   = isset( $args['show_plays'] ) ? $args['show_plays'] : '';
$plays        = isset( $args['plays'] ) ? $args['plays'] : 0;
$avg_rating   = isset( $args['avg_rating'] ) ? $args['avg_rating'] : 0;
$show_rating  = isset( $args['show_rating'] ) ? $args['show_rating'] : '';
$rating_count = isset( $args['rating_count'] ) ? $args['rating_count'] : 0;
$posicao      = isset( $args['posicao'] ) ? $args['posicao'] : 0;
$youtube_url  = isset( $args['youtube_url'] ) ? $args['youtube_url'] : '';
$music_id     = isset( $args['music_id'] ) ? $args['music_id'] : 0;
$audio_url    = isset( $args['audio_url'] ) ? $args['audio_url'] : '';
$yt_id        = isset( $args['yt_id'] ) ? $args['yt_id'] : '';
$is_fav       = isset( $args['is_fav'] ) ? $args['is_fav'] : '';
$fav_label    = isset( $args['fav_label'] ) ? $args['fav_label'] : '';
?>
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
