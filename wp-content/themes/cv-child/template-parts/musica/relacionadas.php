<?php
// cancao-verdadeira-child/template-parts/musica/relacionadas.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Seção "Mais músicas": as 6 músicas publicadas mais recentes, sem a atual,
// em cards (template-parts/card-musica.php). Some se não houver nenhuma.
// Chamado por single-musica.php com get_template_part( ..., null, $args ).
// v15.10.0: separado do single-musica.php (refatoração, fase 6); a consulta
// veio junto, porque só esta seção usa o resultado.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$music_id = isset( $args['music_id'] ) ? (int) $args['music_id'] : 0;

// Mais músicas (as mais recentes, excluindo a atual)
$rel_query = new WP_Query(array(
    'post_type'      => 'musica',
    'post_status'    => 'publish',
    'posts_per_page' => 6,
    'post__not_in'   => array($music_id),
    'orderby'        => 'date',
    'order'          => 'DESC',
    'no_found_rows'  => true,
));
$relacionadas = $rel_query->posts;
wp_reset_postdata();
?>
            <!-- ══════════════════════════════════════════════════
                 MÚSICAS RELACIONADAS
            ══════════════════════════════════════════════════ -->
            <?php if (!empty($relacionadas)) : ?>
            <section class="cv-section" aria-label="Músicas relacionadas">
                <div class="cv-section-header">
                    <h2 class="cv-section-title">
                        Mais <span>músicas</span>
                    </h2>
                    <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                       class="cv-section-link">Ver todas →</a>
                </div>
                <div class="cv-grid">
                    <?php foreach ($relacionadas as $post_rel) :
                        get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $post_rel->ID, 'show_rank' => false ) );
                    endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
