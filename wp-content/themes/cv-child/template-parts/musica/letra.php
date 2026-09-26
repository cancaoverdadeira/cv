<?php
// cancao-verdadeira-child/template-parts/musica/letra.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Letra da música, botão "Copiar letra" e o balão "Comentar este trecho"
// que aparece ao selecionar parte da letra.
// Chamado por single-musica.php com get_template_part( ..., null, $args ).
// v15.10.0: separado do single-musica.php (refatoração, fase 6).
// v15.21.0: botões A− / A+ (tamanho da letra, lembrado no aparelho) e o bloco
// "📖 Por trás da canção" abaixo da letra (campo _cv_historia do plugin).
// v15.23.0: "🎸 Cifra simples" entre a letra e a história (parte cifra.php).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Dados vêm de $args (get_template_part não compartilha variáveis).
$letra    = isset( $args['letra'] ) ? $args['letra'] : '';
$historia = isset( $args['historia'] ) ? trim( $args['historia'] ) : '';
$autor    = ! empty( $args['compositor'] ) ? $args['compositor'] : '';
?>
                    <!-- Letra -->
                    <div class="cv-musica-letra-section">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;flex-wrap:wrap;gap:10px">
                            <h2 style="font-family:var(--font-display);font-size:18px;font-weight:700;margin:0;color:var(--cv-gold)">
                                📝 Letra
                            </h2>
                            <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                                <div class="cv-letra-tamanho" role="group" aria-label="Tamanho da letra">
                                    <span class="cv-letra-tamanho-rotulo">Tamanho:</span>
                                    <button type="button" id="cv-letra-menor" aria-label="Diminuir a letra" title="Diminuir a letra">A−</button>
                                    <button type="button" id="cv-letra-maior" aria-label="Aumentar a letra" title="Aumentar a letra" style="font-size:20px">A+</button>
                                </div>
                                <button id="cv-copy-letra-btn"
                                        class="cv-btn cv-btn-secondary cv-btn-sm"
                                        title="Copiar letra">
                                    📋 Copiar letra
                                </button>
                            </div>
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

                        <?php get_template_part( 'template-parts/musica/cifra', null, array( 'cifra' => isset( $args['cifra'] ) ? $args['cifra'] : '' ) ); ?>

                        <?php if ( '' !== $historia ) : ?>
                        <!-- Por trás da canção (v15.21.0) -->
                        <section class="cv-historia" aria-label="Por trás da canção">
                            <h2>📖 Por trás da canção</h2>
                            <div class="cv-historia-texto"><?php echo wpautop( esc_html( $historia ) ); ?></div>
                            <?php if ( $autor ) : ?><p class="cv-historia-assinatura">— <?php echo esc_html( $autor ); ?></p><?php endif; ?>
                        </section>
                        <?php endif; ?>

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
