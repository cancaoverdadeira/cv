<?php
// cancao-verdadeira-child/category-blog.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Lista de posts do Blog (categoria slug "blog"). URL: /blog/ e /blog/page/2/.
// As URLs e o SEO (Descrição → meta description) vêm do plugin (CV_Blog);
// título, Open Graph e schema ficam com o Rank Math.
// Layout: sidebar + topbar + banner no padrão das páginas de gênero + grade
// de cards (imagem destacada, data, título, resumo) + paginação.
// Gerado em: 2026-09-23 (tema v15.1.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }

$blog      = get_queried_object();
$blog_nome = $blog ? $blog->name : 'Blog';
$blog_desc = $blog && $blog->description ? $blog->description : 'Histórias, bastidores das composições e novidades do sertanejo.';

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <div class="cv-blog-hero" role="banner">
            <nav aria-label="Navegação estrutural" class="cv-blog-crumbs">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a>
                <span aria-hidden="true">/</span>
                <span class="is-current"><?php echo esc_html( $blog_nome ); ?></span>
            </nav>
            <h1><?php echo esc_html( $blog_nome ); ?></h1>
            <p><?php echo esc_html( $blog_desc ); ?></p>
        </div>

        <section class="cv-section" aria-label="Posts do blog">
            <?php if ( have_posts() ) : ?>
            <div class="cv-blog-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                <article class="cv-blog-card">
                    <a href="<?php the_permalink(); ?>" class="cv-blog-card-img" tabindex="-1" aria-hidden="true">
                        <?php if ( has_post_thumbnail() ) : ?>
                            <?php the_post_thumbnail( 'medium_large', array( 'loading' => 'lazy' ) ); ?>
                        <?php else : ?>
                            <span class="cv-blog-card-ph">📝</span>
                        <?php endif; ?>
                    </a>
                    <div class="cv-blog-card-body">
                        <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p><?php echo esc_html( wp_trim_words( get_the_excerpt(), 28, '…' ) ); ?></p>
                        <a href="<?php the_permalink(); ?>" class="cv-blog-more">Ler post →</a>
                    </div>
                </article>
                <?php endwhile; ?>
            </div>

            <?php
            $links = paginate_links( array(
                'type'      => 'array',
                'prev_text' => '←',
                'next_text' => '→',
            ) );
            if ( $links ) :
            ?>
            <nav class="cv-blog-pages" aria-label="Paginação">
                <?php foreach ( $links as $link ) { echo $link; } ?>
            </nav>
            <?php endif; ?>

            <?php else : ?>
            <div class="cv-blog-empty">
                <div style="font-size:40px">📝</div>
                <p>Ainda não há posts por aqui. Volte em breve!</p>
                <a href="<?php echo esc_url( home_url( '/musicas/' ) ); ?>" class="cv-blog-more">Ver as músicas →</a>
            </div>
            <?php endif; ?>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_template_part( 'template-parts/blog-styles' ); ?>

<?php get_footer(); ?>
