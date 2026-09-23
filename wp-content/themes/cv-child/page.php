<?php
// cancao-verdadeira-child/page.php
// Gerado em: 2026-06-22 03:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Template genérico para todas as páginas WordPress sem template específico.
// Usado por: Login, Cadastro, Meu Perfil, Contato e qualquer página criada
// manualmente. Aplica o layout completo do tema filho: sidebar + topbar +
// conteúdo centralizado + player. Sem este arquivo o WordPress usa o layout
// padrão do Astra, que não tem sidebar nem player.

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part('template-parts/topbar'); ?>

        <?php while ( have_posts() ) : the_post(); ?>

        <article style="max-width:760px;margin:0 auto;padding:48px 36px 60px">

            <?php
            // Cabeçalho com título (exceto em páginas que têm shortcode de form)
            // Detecta se a página tem shortcode de auth para omitir título redundante
            $content = get_the_content();
            $has_form_shortcode = (
                has_shortcode($content, 'cv_login_form')    ||
                has_shortcode($content, 'cv_register_form') ||
                has_shortcode($content, 'cv_profile_form')
            );
            ?>

            <?php if ( ! $has_form_shortcode ) : ?>
            <header style="margin-bottom:32px;padding-bottom:20px;
                           border-bottom:1px solid var(--cv-border-subtle)">
                <h1 style="font-family:var(--font-display);font-size:32px;
                           font-weight:700;margin:0;color:var(--cv-text)">
                    <?php the_title(); ?>
                </h1>
            </header>
            <?php endif; ?>

            <div class="cv-page-content"
                 style="color:var(--cv-text);line-height:1.8;font-size:15px">
                <?php the_content(); ?>
            </div>

        </article>

        <?php endwhile; ?>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>

</div>

<style>
/* Estilos do conteúdo genérico de página */
.cv-page-content h2 { font-family:var(--font-display); color:var(--cv-gold); margin:28px 0 12px; font-size:22px; }
.cv-page-content h3 { font-family:var(--font-display); color:var(--cv-text); margin:20px 0 10px; font-size:18px; }
.cv-page-content p  { color:var(--cv-text-muted); margin-bottom:16px; }
.cv-page-content a  { color:var(--cv-gold); }
.cv-page-content a:hover { color:var(--cv-gold-bright); }
.cv-page-content ul, .cv-page-content ol { color:var(--cv-text-muted); padding-left:20px; margin-bottom:16px; }
.cv-page-content li { margin-bottom:6px; }
.cv-page-content strong { color:var(--cv-text); }
.cv-page-content hr { border:none; border-top:1px solid var(--cv-border-subtle); margin:28px 0; }
.cv-page-content blockquote {
    border-left:3px solid var(--cv-gold);
    padding:12px 20px;
    margin:20px 0;
    background:rgba(242,165,26,0.07);
    border-radius:0 var(--cv-radius-sm) var(--cv-radius-sm) 0;
    color:var(--cv-text-muted);
    font-style:italic;
    font-family:var(--font-body);
}
/* Centraliza formulários de auth */
.cv-page-content .cv-auth-box,
.cv-page-content .cv-profile-box {
    margin: 0 auto;
}
</style>

<?php get_footer(); ?>
