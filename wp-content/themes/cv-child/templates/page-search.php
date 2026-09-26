<?php
/*
 * Template Name: Buscar Músicas
 */
// cancao-verdadeira-child/templates/page-search.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página de busca do site (/buscar-musicas/?q=termo&sentimento=&ordem=).
// v15.5.0: usa a busca unificada do plugin (CV_Search → Relevanssi, com
// título peso 10, letra peso 3 e ficha técnica peso 2), mostra cada música
// com o trecho da letra em destaque, filtra por sentimento e lista também os
// posts do blog que citam o termo. A busca nativa (/?s=) redireciona para cá.
// Sem termo: sugestões de sentimentos e as músicas mais recentes.
// v15.20.0: o topo virou o banner da marca, com a busca dentro dele.
// v15.28.0: "Chegando Agora" em linhas de 4 cartões, completando com "Em breve".

if ( ! defined( 'ABSPATH' ) ) { exit; }

$termo      = sanitize_text_field( wp_unslash( $_GET['q'] ?? '' ) );
$sentimento = sanitize_title( wp_unslash( $_GET['sentimento'] ?? '' ) );
$ordem      = sanitize_key( $_GET['ordem'] ?? 'relevancia' );
$paged      = max( 1, absint( $_GET['pg'] ?? 1 ) );
$por_pagina = 20;
$busca_ok   = class_exists( 'CV_Search' );
$buscou     = ( '' !== $termo || '' !== $sentimento );

$musicas = null;
$posts   = array();
if ( $busca_ok && $buscou ) {
    $musicas = CV_Search::query( array(
        'termo'      => $termo,
        'sentimento' => $sentimento,
        'ordem'      => $ordem,
        'pagina'     => $paged,
        'por_pagina' => $por_pagina,
    ) );
    if ( '' !== $termo && 1 === $paged ) {
        $posts = CV_Search::query( array( 'termo' => $termo, 'post_type' => 'post', 'por_pagina' => 3 ) )->posts;
    }
}

$sent_obj = ( $sentimento && class_exists( 'CV_Sentimentos' ) ) ? CV_Sentimentos::get_by_slug( $sentimento ) : null;

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php
        ob_start();
        get_template_part( 'template-parts/busca-avancada', null, array(
            'termo'      => $termo,
            'sentimento' => $sentimento,
            'ordem'      => $ordem,
            'autofocus'  => ! $buscou,
        ) );
        $form_busca = ob_get_clean();
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => '🔍 Buscar',
            'titulo'    => 'Encontre sua',
            'destaque'  => 'Canção',
            'subtitulo' => 'Pelo título, por um trecho da letra, pelo compositor ou pelo artista.',
            'acoes'     => $form_busca,
        ) );
        ?>

        <div class="cv-section">

            <?php if ( $musicas ) :
                $total = (int) $musicas->found_posts;
            ?>

            <div class="cv-busca-cabecalho">
                <h2>
                    <?php if ( '' !== $termo ) : ?>
                        Resultados para <span>“<?php echo esc_html( $termo ); ?>”</span>
                    <?php else : ?>
                        Músicas de <span><?php echo esc_html( $sent_obj ? $sent_obj->nome : $sentimento ); ?></span>
                    <?php endif; ?>
                    <?php if ( $sent_obj && '' !== $termo ) : ?>
                        <small>em <?php echo esc_html( trim( $sent_obj->icone . ' ' . $sent_obj->nome ) ); ?></small>
                    <?php endif; ?>
                </h2>
                <span class="cv-busca-total"><?php echo number_format_i18n( $total ); ?> música<?php echo 1 === $total ? '' : 's'; ?></span>
            </div>

            <?php if ( $musicas->have_posts() ) : ?>
            <div class="cv-resultados">
                <?php foreach ( $musicas->posts as $p ) {
                    get_template_part( 'template-parts/card-busca', null, array( 'post' => $p, 'termo' => $termo ) );
                } ?>
            </div>

            <?php if ( $musicas->max_num_pages > 1 ) :
                $links = paginate_links( array(
                    'base'      => add_query_arg( 'pg', '%#%' ),
                    'format'    => '',
                    'current'   => $paged,
                    'total'     => (int) $musicas->max_num_pages,
                    'type'      => 'array',
                    'prev_text' => '←',
                    'next_text' => '→',
                ) );
            ?>
            <nav class="cv-blog-pages" aria-label="Paginação"><?php foreach ( (array) $links as $l ) { echo $l; } ?></nav>
            <?php endif; ?>

            <?php else : ?>
            <div class="cv-busca-vazio">
                <div class="cv-busca-vazio-icone">🎵</div>
                <h3>Nenhuma música encontrada</h3>
                <p>Tente menos palavras, um trecho diferente da letra ou o nome do compositor.</p>
                <a href="<?php echo esc_url( home_url( '/musicas/' ) ); ?>" class="cv-btn cv-btn-ghost">Ver todas as músicas</a>
            </div>
            <?php endif; ?>

            <?php if ( $posts ) : ?>
            <div class="cv-busca-cabecalho cv-busca-blog">
                <h2>No <span>Blog</span></h2>
            </div>
            <div class="cv-resultados">
                <?php foreach ( $posts as $p ) {
                    get_template_part( 'template-parts/card-busca', null, array( 'post' => $p, 'termo' => $termo ) );
                } ?>
            </div>
            <?php endif; ?>

            <?php else : ?>

            <?php // Estado inicial: atalhos por sentimento + músicas recentes
            $sentimentos = class_exists( 'CV_Sentimentos' ) ? CV_Sentimentos::get_all() : array();
            $recentes    = class_exists( 'CV_Ranking' ) ? CV_Ranking::get_recent( 8 ) : array();
            ?>
            <?php if ( $sentimentos ) : ?>
            <div class="cv-busca-cabecalho"><h2>Buscar por <span>sentimento</span></h2></div>
            <div class="cv-busca-sentimentos">
                <?php foreach ( $sentimentos as $s ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'sentimento', $s->slug, CV_Search::url() ) ); ?>"
                   class="cv-busca-sentimento" style="--sent-cor:<?php echo esc_attr( $s->cor ?: '#C9A27E' ); ?>">
                    <span><?php echo esc_html( $s->icone ); ?></span> <?php echo esc_html( $s->nome ); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="cv-busca-cabecalho"><h2>🎵 Chegando <span>Agora</span></h2></div>
            <?php // v15.28.0: linhas de 4, completando com "Em breve" ?>
            <div class="cv-grid cv-grid-col-4 cv-grid-linha4">
                <?php foreach ( $recentes as $m ) {
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $m->music_id, 'show_rank' => false ) );
                } ?>
                <?php $falta = cv_completar_grade( count( $recentes ), 4 ); if ( $falta ) { get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => $falta, 'icone' => '🎵', 'texto' => 'Em breve', 'sem_grade' => true ) ); } ?>
            </div>

            <?php endif; ?>
        </div>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>
    </main>
</div>


<?php get_footer(); ?>
