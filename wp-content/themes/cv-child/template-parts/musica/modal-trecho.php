<?php
// cancao-verdadeira-child/template-parts/musica/modal-trecho.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Janela (modal) para escrever o comentário sobre o trecho selecionado.
// Não usa dados da música: o JavaScript (cv-musica.js) preenche o trecho.
// Chamado por single-musica.php com get_template_part( ..., null, $args ).
// v15.10.0: separado do single-musica.php (refatoração, fase 6).

if ( ! defined( 'ABSPATH' ) ) { exit; }

?>
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
