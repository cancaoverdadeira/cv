<?php
/*
 * Template Name: Lista de Músicas
 */
// cancao-verdadeira-child/templates/page-lista-musicas.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// v15.45.0 (16/10/2026, pedido do Eduardo): uma página para cada seção da home
// (/novas/, /favoritas/, /mais-tocadas/, /melhor-avaliadas/). A home mostra só
// 1 linha de 4 cartões e o "Ver todas →" traz para cá. Qual lista mostrar vem
// do endereço da página (cv_listas_musicas() em functions.php). Até 48 músicas,
// na mesma grade de 4 da home, completando a última linha com "Em breve".

if ( ! defined( 'ABSPATH' ) ) { exit; }

$listas = cv_listas_musicas();
$slug   = get_post_field( 'post_name', get_queried_object_id() );
$lista  = $listas[ $slug ] ?? $listas['novas'];
$musicas = ( class_exists( 'CV_Ranking' ) && method_exists( 'CV_Ranking', $lista['metodo'] ) )
         ? call_user_func( array( 'CV_Ranking', $lista['metodo'] ), 48 ) : array();

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => $lista['icone'] . ' ' . $lista['destaque'],
            'titulo'    => $lista['titulo'],
            'destaque'  => $lista['destaque'],
            'subtitulo' => $lista['sub'],
        ) );
        ?>

        <section class="cv-section" aria-label="<?php echo esc_attr( $lista['titulo'] . ' ' . $lista['destaque'] ); ?>">
            <div class="cv-section-header">
                <h2 class="cv-section-title"><?php echo esc_html( $lista['icone'] ); ?> <span><?php echo esc_html( $lista['destaque'] ); ?></span></h2>
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cv-section-link">← Início</a>
            </div>
            <div class="cv-grid cv-grid-col-4 cv-grid-linha4">
                <?php foreach ( $musicas as $m ) :
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $m->music_id, 'show_rank' => false ) );
                endforeach; ?>
                <?php $falta = cv_completar_grade( count( $musicas ), 4 );
                if ( $falta ) { get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => $falta, 'icone' => $lista['icone'], 'texto' => 'Em breve', 'sem_grade' => true ) ); } ?>
            </div>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_footer(); ?>
