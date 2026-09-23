<?php
// cancao-verdadeira-child/archive-musica.php
// Gerado em: 2026-06-22 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Catálogo completo de músicas com filtro por gênero, ordenação e
// paginação. URL: /musicas/ e /musicas/page/2/ etc.
// Filtros por gênero funcionam via query string ?genero=sertanejo-raiz
// Ordenação via ?orderby=plays|recente|titulo|avaliacao

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Parâmetros de filtro
$genero_slug = sanitize_key( get_query_var('cv_genre', $_GET['genero'] ?? '') );
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

// Filtro por gênero
if ( $genero_slug ) {
    $args['tax_query'] = array(
        array(
            'taxonomy' => 'cv_genre',
            'field'    => 'slug',
            'terms'    => $genero_slug,
        ),
    );
}

$query   = new WP_Query($args);
$total   = $query->found_posts;
$generos = get_terms(array('taxonomy' => 'cv_genre', 'hide_empty' => false, 'orderby' => 'name'));

// Gênero atual (para título)
$genero_atual = $genero_slug ? get_term_by('slug', $genero_slug, 'cv_genre') : null;
$titulo_pag   = $genero_atual ? $genero_atual->name : 'Todas as Músicas';

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

        <!-- Cabeçalho do catálogo -->
        <div style="background:linear-gradient(180deg,#FFFFFF 0%,var(--cv-bg) 100%);
                    padding:40px 36px 28px;border-bottom:1px solid var(--cv-border-subtle)">

            <h1 style="font-family:var(--font-display);font-size:32px;font-weight:700;margin:0 0 6px">
                🎵 <?php echo esc_html($titulo_pag); ?>
                <?php if ($total > 0) : ?>
                <span style="font-size:16px;font-weight:400;color:var(--cv-text-muted);margin-left:10px">
                    <?php echo number_format($total); ?> música<?php echo $total !== 1 ? 's' : ''; ?>
                </span>
                <?php endif; ?>
            </h1>

            <?php if ( $genero_atual && $genero_atual->description ) : ?>
            <p style="color:var(--cv-text-muted);font-family:var(--font-body);font-style:italic;margin:8px 0 0">
                <?php echo esc_html($genero_atual->description); ?>
            </p>
            <?php endif; ?>

            <!-- Filtros por gênero -->
            <?php if ( ! is_wp_error($generos) && ! empty($generos) ) : ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:20px">
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-genre-pill <?php echo ! $genero_slug ? 'cv-genre-pill-active' : ''; ?>">
                    🎵 Todas
                </a>
                <?php foreach ($generos as $g) :
                    $icones = array(
                        'sertanejo-universitario' => '🎸',
                        'sertanejo-raiz'          => '🪗',
                        'sertanejo-romantico'     => '❤',
                        'modao'                   => '🎩',
                        'sertanejo-gospel'        => '✝',
                        'sertanejo-sofrencia'     => '💔',
                    );
                    $ic = $icones[$g->slug] ?? '🎵';
                    $ativo = $genero_slug === $g->slug;
                    $url_g = add_query_arg(array('genero' => $g->slug, 'orderby' => $orderby), home_url('/musicas/'));
                ?>
                <a href="<?php echo esc_url($url_g); ?>"
                   class="cv-genre-pill <?php echo $ativo ? 'cv-genre-pill-active' : ''; ?>">
                    <?php echo $ic; ?> <?php echo esc_html($g->name); ?>
                    <span style="opacity:.6;font-size:10px">(<?php echo $g->count; ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Ordenação -->
            <div style="display:flex;align-items:center;gap:10px;margin-top:16px;flex-wrap:wrap">
                <span style="font-size:12px;color:var(--cv-text-dim);text-transform:uppercase;letter-spacing:.5px">
                    Ordenar por:
                </span>
                <?php foreach ($orderby_opts as $key => $label) :
                    $ativo = $orderby === $key;
                    $url_o = add_query_arg(array('genero' => $genero_slug, 'orderby' => $key), home_url('/musicas/'));
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

            <div class="cv-grid">
                <?php while ( $query->have_posts() ) :
                    $query->the_post();
                    $music_id   = get_the_ID();
                    $show_rank  = false;
                    $show_genre = ! $genero_slug; // só mostra gênero se não está filtrando
                    get_template_part('template-parts/card-musica');
                endwhile;
                wp_reset_postdata(); ?>
            </div>

            <!-- Paginação -->
            <?php if ( $query->max_num_pages > 1 ) : ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:40px;flex-wrap:wrap">
                <?php
                $base_url = add_query_arg(array('genero' => $genero_slug, 'orderby' => $orderby), home_url('/musicas/'));
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

            <!-- Nenhuma música encontrada -->
            <div style="text-align:center;padding:60px 20px">
                <div style="font-size:48px;margin-bottom:16px">🎵</div>
                <h2 style="font-family:var(--font-display);color:var(--cv-gold);margin-bottom:8px">
                    Nenhuma música encontrada
                </h2>
                <p style="color:var(--cv-text-muted);margin-bottom:24px">
                    <?php echo $genero_slug
                        ? 'Ainda não há músicas neste gênero. Volte em breve!'
                        : 'Ainda não há músicas cadastradas.'; ?>
                </p>
                <?php if ($genero_slug) : ?>
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-btn cv-btn-ghost">
                    ← Ver todas as músicas
                </a>
                <?php endif; ?>
            </div>

            <?php endif; ?>
        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>

</div>

<?php get_footer(); ?>
