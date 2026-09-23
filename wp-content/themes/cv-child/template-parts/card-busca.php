<?php
// cancao-verdadeira-child/template-parts/card-busca.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Card de um resultado da busca: capa, título, artista/compositor e o trecho
// da letra com o termo buscado em destaque (<mark>), conforme a especificação.
// O trecho vem de CV_Search::trecho() (gerado pelo Relevanssi quando ativo).
// Serve para música e para post do blog (o post mostra "Blog" e a data).
// Uso: get_template_part( 'template-parts/card-busca', null, array( 'post' => $post, 'termo' => $termo ) );
// Gerado em: 2026-09-23 (tema v15.5.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }

$p     = isset( $args['post'] ) ? get_post( $args['post'] ) : null;
$termo = isset( $args['termo'] ) ? (string) $args['termo'] : '';
if ( ! $p ) { return; }

$is_musica = 'musica' === $p->post_type;
$url       = get_permalink( $p );
$titulo    = get_the_title( $p );
$trecho    = class_exists( 'CV_Search' ) ? CV_Search::trecho( $p, $termo ) : '';

if ( $is_musica ) {
    $capa   = function_exists( 'cv_cover_url' ) ? cv_cover_url( $p->ID ) : '';
    $artista    = get_post_meta( $p->ID, '_cv_artista', true );
    $compositor = get_post_meta( $p->ID, '_cv_compositor', true );
    $linha  = $artista ?: $compositor;
    if ( $artista && $compositor && $artista !== $compositor ) { $linha .= ' · ' . $compositor; }
} else {
    $capa  = get_the_post_thumbnail_url( $p, 'medium' ) ?: '';
    $linha = 'Blog · ' . get_the_date( '', $p );
}
?>
<a class="cv-resultado" href="<?php echo esc_url( $url ); ?>">
    <span class="cv-resultado-capa"<?php echo $capa ? ' style="background-image:url(\'' . esc_url( $capa ) . '\')"' : ''; ?>>
        <?php if ( ! $capa ) : ?><?php echo $is_musica ? '🎵' : '📝'; ?><?php endif; ?>
    </span>
    <span class="cv-resultado-info">
        <span class="cv-resultado-titulo"><?php echo esc_html( $titulo ); ?></span>
        <?php if ( $linha ) : ?><span class="cv-resultado-linha"><?php echo esc_html( $linha ); ?></span><?php endif; ?>
        <?php if ( $trecho ) : ?><span class="cv-resultado-trecho"><?php echo $trecho; // já sanitizado em CV_Search::trecho ?></span><?php endif; ?>
    </span>
</a>
