<?php
// cancao-verdadeira-child/template-parts/newsletter-janela.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Janela "📩 Receba novidades" (v15.61.0, pedido do Eduardo em 27/10/2026:
// muita gente se cadastrava sem se inscrever, porque a newsletter só
// aparecia no fim da página). Abre pelo botão do menu lateral (.cv-apoio-abrir,
// o mesmo abridor das janelas do plugin, assets/js/cv-apoio.js) e usa o
// formulário de template-parts/newsletter-form.php. Impressa uma vez por
// página em template-parts/footer-content.php.

if ( ! defined( 'ABSPATH' ) ) { exit; }
?>
<dialog id="cv-janela-newsletter" class="cv-janela cv-janela-newsletter" aria-labelledby="cv-newsletter-janela-titulo">
    <button type="button" class="cv-janela-fechar" aria-label="Fechar">✕</button>
    <h2 id="cv-newsletter-janela-titulo">📩 Receba as novidades</h2>
    <p class="cv-janela-intro">Avisamos você quando sair música nova, e mandamos de vez em quando uma notícia especial. Só isso.</p>
    <?php get_template_part( 'template-parts/newsletter-form' ); ?>
</dialog>
