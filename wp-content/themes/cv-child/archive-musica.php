<?php
// cancao-verdadeira-child/archive-musica.php
// Gerado em: 2026-06-22 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Catálogo completo de músicas com ordenação e paginação.
// URL: /musicas/ e /musicas/page/2/ etc.
// Ordenação via ?orderby=plays|recente|titulo|avaliacao
// v15.4.0: removido o filtro por gênero (o site é todo sertanejo).
// v15.14.0: banner da marca no topo (template-parts/banner-pagina.php).
// v15.28.0: grade em linhas de 4 cartões; a última linha é completada com "Em breve".

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Parâmetros de filtro
$orderby     = sanitize_key( $_GET['orderby'] ?? 'recente' );
$paged       = max(1, get_query_var('paged', 1));
$per_page    = 24;

// Opções de ordenação
$orderby_opts = array(
    'recente'   => 'Mais Recentes',
    'plays'     => 'Mais Tocadas',
    'avaliacao' => 'Melhor Avaliadas',
    'titulo'    => 'A → Z',
);

// Monta a query
$args = array(
    'post_type'      => 'musica',
    'post_status'    => 'publish',
    'posts_per_page' => $per_page,
    'paged'          => $paged,
    'meta_query'     => array(
        array('key' => '_cv_ativo', 'value' => '1', 'compare' => '='),
    ),
);

// Ordenação
switch ( $orderby ) {
    case 'plays':
        $args['meta_key'] = '_cv_plays_total';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
        break;
    case 'avaliacao':
        $args['meta_key'] = '_cv_avg_rating';
        $args['orderby']  = 'meta_value_num';
        $args['order']    = 'DESC';
        break;
    case 'titulo':
        $args['orderby'] = 'title';
        $args['order']   = 'ASC';
        break;
    default:
        $args['orderby'] = 'date';
        $args['order']   = 'DESC';
}

$query   = new WP_Query($args);
$total   = $query->found_posts;
$titulo_pag = 'Todas as Músicas';

// SEO
add_filter('document_title_parts', function($p) use ($titulo_pag) {
    $p['title'] = $titulo_pag . ' — ' . get_bloginfo('name');
    unset($p['site']);
    return $p;
});

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part('template-parts/topbar'); ?>

        <?php
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => $total > 0 ? '🎵 ' . number_format_i18n( $total ) . ( 1 === (int) $total ? ' música' : ' músicas' ) : '🎵 Catálogo',
            'titulo'    => 'Todas as',
            'destaque'  => 'Músicas',
            'subtitulo' => 'Letras sertanejas autorais para ouvir, cantar junto e guardar no coração.',
        ) );
        ?>

        <!-- Ordenação -->
        <div style="padding:20px 36px 4px">
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <span style="font-size:12px;color:var(--cv-text-dim);text-transform:uppercase;letter-spacing:.5px">
                    Ordenar por:
                </span>
                <?php foreach ($orderby_opts as $key => $label) :
                    $ativo = $orderby === $key;
                    $url_o = add_query_arg(array('orderby' => $key), home_url('/musicas/'));
                ?>
                <a href="<?php echo esc_url($url_o); ?>"
                   style="font-size:12px;font-weight:700;padding:5px 12px;border-radius:50px;text-decoration:none;
                          transition:all .2s;
                          background:<?php echo $ativo ? 'var(--cv-gold)' : 'rgba(123,58,34,0.07)'; ?>;
                          color:<?php echo $ativo ? '#FFFFFF' : 'var(--cv-text-muted)'; ?>;
                          border:1px solid <?php echo $ativo ? 'var(--cv-gold)' : 'var(--cv-border)'; ?>">
                    <?php echo esc_html($label); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Grade de músicas -->
        <div class="cv-section">
            <?php if ( $query->have_posts() ) : ?>

            <div class="cv-grid cv-grid-col-4 cv-grid-linha4">
                <?php while ( $query->have_posts() ) :
                    $query->the_post();
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => get_the_ID(), 'show_rank' => false ) );
                endwhile;
                wp_reset_postdata(); ?>
                <?php // v15.28.0: completa a última linha (só na última página) com "Em breve"
                if ( $paged >= (int) $query->max_num_pages ) { $falta = cv_completar_grade( $query->post_count, 4 ); if ( $falta ) { get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => $falta, 'icone' => '🎵', 'texto' => 'Em breve', 'sem_grade' => true ) ); } } ?>
            </div>

            <!-- Paginação -->
            <?php if ( $query->max_num_pages > 1 ) : ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:40px;flex-wrap:wrap">
                <?php
                $base_url = add_query_arg(array('orderby' => $orderby), home_url('/musicas/'));
                for ($i = 1; $i <= $query->max_num_pages; $i++) :
                    $url_pg = $i === 1 ? $base_url : add_query_arg('paged', $i, $base_url);
                    $ativo  = $i === $paged;
                ?>
                <a href="<?php echo esc_url($url_pg); ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;
                          width:38px;height:38px;border-radius:50%;font-size:13px;font-weight:700;
                          text-decoration:none;transition:all .2s;
                          background:<?php echo $ativo ? 'var(--cv-gold)' : 'var(--cv-bg-card)'; ?>;
                          color:<?php echo $ativo ? '#FFFFFF' : 'var(--cv-text-muted)'; ?>;
                          border:1px solid <?php echo $ativo ? 'var(--cv-gold)' : 'var(--cv-border)'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <?php else : ?>

            <!-- Nenhuma música encontrada (v15.28.0: uma linha de "Em breve" acima do aviso) -->
            <?php get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => 4, 'icone' => '🎵', 'texto' => 'Em breve' ) ); ?>
            <div style="text-align:center;padding:40px 20px 60px">
                <div style="font-size:48px;margin-bottom:16px">🎵</div>
                <h2 style="font-family:var(--font-display);color:var(--cv-gold);margin-bottom:8px">
                    Nenhuma música encontrada
                </h2>
                <p style="color:var(--cv-text-muted);margin-bottom:24px">
                    Ainda não há músicas publicadas. Volte em breve!
                </p>
            </div>

            <?php endif; ?>
        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>

</div>

<?php get_footer(); ?>
