<?php
// cancao-verdadeira-child/template-parts/musica/lateral.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Coluna lateral: ficha técnica (+ "Ouça também em"), avaliação por
// estrelas, botões de compartilhar e "Adicionar à playlist".
// Chamado por single-musica.php com get_template_part( ..., null, $args ).
// v15.10.0: separado do single-musica.php (refatoração, fase 6).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dados vêm de $args (get_template_part não compartilha variáveis).
$compositor   = isset( $args['compositor'] ) ? $args['compositor'] : '';
$artista      = isset( $args['artista'] ) ? $args['artista'] : '';
$album        = isset( $args['album'] ) ? $args['album'] : '';
$ano          = isset( $args['ano'] ) ? $args['ano'] : '';
$music_id     = isset( $args['music_id'] ) ? $args['music_id'] : 0;
$avg_rating   = isset( $args['avg_rating'] ) ? $args['avg_rating'] : 0;
$show_rating  = isset( $args['show_rating'] ) ? $args['show_rating'] : '';
$rating_count = isset( $args['rating_count'] ) ? $args['rating_count'] : 0;
$user_rating  = isset( $args['user_rating'] ) ? $args['user_rating'] : 0;
$share_links  = isset( $args['share_links'] ) ? $args['share_links'] : array();
?>
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
