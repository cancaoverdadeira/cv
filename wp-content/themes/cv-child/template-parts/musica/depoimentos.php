<?php
// cancao-verdadeira-child/template-parts/musica/depoimentos.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// "💬 O que os ouvintes sentiram" (v15.25.0, item 2.5): depoimentos aprovados
// em cartões (texto, nome e cidade) e o formulário "✍️ Contar o que senti"
// (texto, nome, cidade opcional, autorização e campo-isca contra robôs).
// Os dados e as regras ficam no plugin (CV_Depoimentos); o envio é por AJAX
// em assets/js/cv-depoimentos.js. Todo depoimento passa por aprovação.
// Chamado por single-musica.php com $args['music_id'].
// Sem o plugin (CV_Depoimentos), não mostra nada.

if ( ! defined( 'ABSPATH' ) ) { exit; }
if ( ! class_exists( 'CV_Depoimentos' ) ) { return; }

$music_id = isset( $args['music_id'] ) ? (int) $args['music_id'] : 0;
if ( ! $music_id || 'publish' !== get_post_status( $music_id ) ) { return; }

$lista = CV_Depoimentos::aprovados( $music_id, 12 );
$user  = wp_get_current_user();
?>
                    <!-- Depoimentos dos ouvintes (v15.25.0) -->
                    <section class="cv-depo" id="depoimentos" aria-labelledby="cv-depo-titulo">
                        <h2 id="cv-depo-titulo">💬 O que os ouvintes <span>sentiram</span></h2>

                        <?php if ( $lista ) : ?>
                        <div class="cv-depo-lista">
                            <?php foreach ( $lista as $c ) : $cidade = CV_Depoimentos::cidade( $c ); ?>
                            <figure class="cv-depo-cartao">
                                <blockquote><?php echo nl2br( esc_html( $c->comment_content ) ); ?></blockquote>
                                <figcaption>
                                    <strong><?php echo esc_html( $c->comment_author ); ?></strong><?php if ( $cidade ) : ?><span> · <?php echo esc_html( $cidade ); ?></span><?php endif; ?>
                                </figcaption>
                            </figure>
                            <?php endforeach; ?>
                        </div>
                        <?php else : ?>
                        <p class="cv-depo-vazio">Esta música lembrou alguém, um lugar ou um tempo bom? Seja a primeira pessoa a contar. 💛</p>
                        <?php endif; ?>

                        <details class="cv-depo-form-caixa">
                            <summary class="cv-btn cv-btn-primary">✍️ Contar o que senti</summary>
                            <form class="cv-depo-form" novalidate>
                                <input type="hidden" name="music_id" value="<?php echo (int) $music_id; ?>">
                                <div class="cv-depo-isca" aria-hidden="true">
                                    <label>Não preencha: <input type="text" name="site" tabindex="-1" autocomplete="off"></label>
                                </div>

                                <label class="cv-depo-campo">
                                    <span>O que esta música despertou em você?</span>
                                    <textarea name="texto" rows="5" maxlength="<?php echo (int) CV_Depoimentos::MAX_LETRAS; ?>" required
                                              placeholder="Ex.: Essa música me lembrou meu pai cantando na varanda..."></textarea>
                                    <small class="cv-depo-conta"><b>0</b> de <?php echo (int) CV_Depoimentos::MAX_LETRAS; ?> letras</small>
                                </label>

                                <div class="cv-depo-linha">
                                    <label class="cv-depo-campo">
                                        <span>Seu nome</span>
                                        <input type="text" name="nome" maxlength="60" required autocomplete="given-name"
                                               value="<?php echo esc_attr( $user->exists() ? $user->display_name : '' ); ?>">
                                    </label>
                                    <label class="cv-depo-campo">
                                        <span>Sua cidade <em>(opcional)</em></span>
                                        <input type="text" name="cidade" maxlength="60" placeholder="Ex.: Belo Horizonte/MG" autocomplete="address-level2">
                                    </label>
                                </div>

                                <label class="cv-depo-aceite">
                                    <input type="checkbox" name="aceite" value="1" required>
                                    <span>Autorizo publicar meu depoimento com meu nome e minha cidade nesta página.</span>
                                </label>

                                <p class="cv-depo-aviso">Os depoimentos aparecem depois de lidos pela equipe da Canção Verdadeira.</p>
                                <button type="submit" class="cv-btn cv-btn-primary">💌 Enviar depoimento</button>
                                <p class="cv-depo-msg" role="status" aria-live="polite"></p>
                            </form>
                        </details>
                    </section>
