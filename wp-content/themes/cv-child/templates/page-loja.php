<?php
/*
 * Template Name: Loja
 */
// cancao-verdadeira-child/templates/page-loja.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página "Loja" (/loja/), criada em 25/09/2026 (tema v15.17.0): banner da
// marca + vitrine dos itens (E-book, Caneca, Camiseta) desenhada pelo plugin
// (CV_Estoque_Area::vitrine). "Quero este" leva à Minha Área com o item já
// escolhido; o pagamento é só por PIX. Links: menu lateral e rodapé.

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => '🛍️ Loja oficial',
            'titulo'    => 'Leve a',
            'destaque'  => 'Canção Verdadeira com você',
            'subtitulo' => 'Canecas, camisetas e e-books com as nossas canções. Pagamento fácil por PIX.',
        ) );
        ?>

        <section class="cv-section" aria-label="Produtos">
            <?php
            if ( class_exists( 'CV_Estoque_Area' ) ) {
                echo CV_Estoque_Area::vitrine();
            } else {
                echo '<div class="cv-empty">A loja está temporariamente indisponível.</div>';
            }
            ?>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_footer(); ?>
