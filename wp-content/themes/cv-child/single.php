<?php
// cancao-verdadeira-child/single.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página de um post do blog (URL /blog/{post}/, montada pelo plugin CV_Blog).
// Sem este arquivo o WordPress usava o single.php do Astra, sem a sidebar,
// a topbar e o player do tema filho. Músicas continuam em single-musica.php.
// Mostra: trilha (Início / Blog / título), título, data e tempo de leitura,
// imagem destacada, conteúdo e "Leia também" com os 3 posts mais recentes.
// Gerado em: 2026-09-23 (tema v15.1.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }

$blog_term = get_term_by( 'slug', 'blog', 'category' );

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php while ( have_posts() ) : the_post();
            $palavras = str_word_count( wp_strip_all_tags( get_the_content() ) );
            $minutos  = max( 1, (int) ceil( $palavras / 200 ) );
        ?>

        <article class="cv-blog-post">

            <nav aria-label="Navegação estrutural" class="cv-blog-crumbs">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Início</a>
                <?php if ( $blog_term ) : ?>
                <span aria-hidden="true">/</span>
                <a href="<?php echo esc_url( get_term_link( $blog_term ) ); ?>"><?php echo esc_html( $blog_term->name ); ?></a>
                <?php endif; ?>
                <span aria-hidden="true">/</span>
                <span class="is-current"><?php the_title(); ?></span>
            </nav>

            <header class="cv-blog-post-head">
                <h1><?php the_title(); ?></h1>
                <div class="cv-blog-meta">
                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date() ); ?></time>
                    <span aria-hidden="true">·</span>
                    <span><?php echo esc_html( $minutos ); ?> min de leitura</span>
                </div>
            </header>

            <?php if ( has_post_thumbnail() ) : ?>
            <figure class="cv-blog-post-img">
                <?php the_post_thumbnail( 'large' ); ?>
            </figure>
            <?php endif; ?>

            <div class="cv-page-content cv-blog-content">
                <?php the_content(); ?>
            </div>

        </article>

        <?php
        $current_id = get_the_ID();
        endwhile;

        $relacionados = $blog_term ? get_posts( array(
            'post_type'      => 'post',
            'posts_per_page' => 3,
            'post__not_in'   => array( $current_id ),
            'cat'            => $blog_term->term_id,
        ) ) : array();
        ?>

        <?php if ( $relacionados ) : ?>
        <section class="cv-section" aria-label="Leia também">
            <div class="cv-section-header">
                <h2 class="cv-section-title">Leia <span>também</span></h2>
            </div>
            <div class="cv-blog-grid">
                <?php foreach ( $relacionados as $rel ) : ?>
                <article class="cv-blog-card">
                    <a href="<?php echo esc_url( get_permalink( $rel ) ); ?>" class="cv-blog-card-img" tabindex="-1" aria-hidden="true">
                        <?php if ( has_post_thumbnail( $rel ) ) : ?>
                            <?php echo get_the_post_thumbnail( $rel, 'medium_large', array( 'loading' => 'lazy' ) ); ?>
                        <?php else : ?>
                            <span class="cv-blog-card-ph">📝</span>
                        <?php endif; ?>
                    </a>
                    <div class="cv-blog-card-body">
                        <time datetime="<?php echo esc_attr( get_the_date( 'c', $rel ) ); ?>"><?php echo esc_html( get_the_date( '', $rel ) ); ?></time>
                        <h3><a href="<?php echo esc_url( get_permalink( $rel ) ); ?>"><?php echo esc_html( get_the_title( $rel ) ); ?></a></h3>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_template_part( 'template-parts/blog-styles' ); ?>

<?php get_footer(); ?>
