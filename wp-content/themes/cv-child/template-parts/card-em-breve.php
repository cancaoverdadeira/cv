<?php
// cancao-verdadeira-child/template-parts/card-em-breve.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Cards "Em breve" para as seções da home que ainda não têm conteúdo
// (Top 10, Chegando Agora, Mais Favoritadas e Blog). Mantêm o formato da
// grade para a home não ficar vazia enquanto as músicas não são publicadas.
// Uso: get_template_part( 'template-parts/card-em-breve', null, array(
//        'quantidade' => 4, 'icone' => '🎵', 'texto' => 'Em breve', 'formato' => 'musica'|'post' ) );
// Gerado em: 2026-09-23 (tema v15.2.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }

$quantidade = isset( $args['quantidade'] ) ? max( 1, (int) $args['quantidade'] ) : 4;
$icone      = isset( $args['icone'] )   ? $args['icone']   : '🎵';
$texto      = isset( $args['texto'] )   ? $args['texto']   : 'Em breve';
$formato    = isset( $args['formato'] ) ? $args['formato'] : 'musica';
$grid_class = 'post' === $formato ? 'cv-blog-grid' : 'cv-grid';
?>
<div class="<?php echo esc_attr( $grid_class ); ?> cv-em-breve-grid" aria-label="<?php echo esc_attr( $texto ); ?>">
    <?php for ( $i = 0; $i < $quantidade; $i++ ) : ?>
    <div class="cv-card-breve cv-card-breve-<?php echo esc_attr( $formato ); ?>" aria-hidden="<?php echo $i ? 'true' : 'false'; ?>">
        <div class="cv-card-breve-capa">
            <span class="cv-card-breve-icone"><?php echo esc_html( $icone ); ?></span>
            <span class="cv-card-breve-selo"><?php echo esc_html( $texto ); ?></span>
        </div>
        <div class="cv-card-breve-info">
            <span class="cv-card-breve-linha"></span>
            <span class="cv-card-breve-linha is-curta"></span>
        </div>
    </div>
    <?php endfor; ?>
</div>
