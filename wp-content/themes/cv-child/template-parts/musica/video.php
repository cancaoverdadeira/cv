<?php
// cancao-verdadeira-child/template-parts/musica/video.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Vídeo do YouTube incorporado (só aparece se a música tiver link do YouTube).
// Chamado por single-musica.php com get_template_part( ..., null, $args ).
// v15.10.0: separado do single-musica.php (refatoração, fase 6).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dados vêm de $args (get_template_part não compartilha variáveis).
$youtube_url = isset( $args['youtube_url'] ) ? $args['youtube_url'] : '';
$yt_id       = isset( $args['yt_id'] ) ? $args['yt_id'] : '';
$titulo      = isset( $args['titulo'] ) ? $args['titulo'] : '';
?>
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
