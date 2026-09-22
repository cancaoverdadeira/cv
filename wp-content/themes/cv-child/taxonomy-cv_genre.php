<?php
// cancao-verdadeira-child/taxonomy-cv_genre.php
// Gerado em: 2026-06-22 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página de arquivo do gênero musical: /sertanejo-raiz/ /modao/ etc.
// Exibe banner do gênero, descrição, sub-categorias e grid de músicas
// com ranking próprio do gênero. URL gerada pelo WordPress automaticamente
// ao acessar um termo da taxonomia cv_genre.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$genero       = get_queried_object();
$genero_nome  = $genero ? $genero->name        : 'Gênero';
$genero_desc  = $genero ? $genero->description : '';
$genero_slug  = $genero ? $genero->slug        : '';
$total        = $genero ? $genero->count        : 0;
$paged        = max(1, get_query_var('paged', 1));

// Ícones e cores por gênero
$genero_cfg = array(
    'sertanejo-universitario' => array('icone' => '🎸', 'cor' => '#8B4513', 'desc' => 'Batida moderna, festivo e dançante'),
    'sertanejo-raiz'          => array('icone' => '🪗', 'cor' => '#556B2F', 'desc' => 'Tradição, viola e a essência do campo'),
    'sertanejo-romantico'     => array('icone' => '❤',  'cor' => '#8B0000', 'desc' => 'Amor, saudade e emoção'),
    'modao'                   => array('icone' => '🎩', 'cor' => '#4B3832', 'desc' => 'O sertanejo raiz em sua forma mais pura'),
    'sertanejo-gospel'        => array('icone' => '✝',  'cor' => '#4169E1', 'desc' => 'Fé e espiritualidade com sotaque sertanejo'),
    'sertanejo-sofrencia'     => array('icone' => '💔', 'cor' => '#696969', 'desc' => 'Paixão, dor e superação'),
    'sertanejo-pop'           => array('icone' => '🎤', 'cor' => '#9B59B6', 'desc' => 'O sertanejo que conquistou as cidades'),
);
$cfg = $genero_cfg[$genero_slug] ?? array('icone' => '🎵', 'cor' => '#D4A017', 'desc' => '');

// Descrição: usa a do banco ou o padrão
if ( ! $genero_desc ) { $genero_desc = $cfg['desc']; }

// Top deste gênero
$top_genero = class_exists('CV_Ranking') ? CV_Ranking::get_top(5, $genero_slug) : array();

// Query principal de músicas do gênero
$query = new WP_Query(array(
    'post_type'      => 'musica',
    'post_status'    => 'publish',
    'posts_per_page' => 20,
    'paged'          => $paged,
    'tax_query'      => array(array(
        'taxonomy' => 'cv_genre',
        'field'    => 'slug',
        'terms'    => $genero_slug,
    )),
    'meta_key'       => '_cv_plays_total',
    'orderby'        => 'meta_value_num',
    'order'          => 'DESC',
    'meta_query'     => array(
        array('key' => '_cv_ativo', 'value' => '1', 'compare' => '='),
    ),
));

// SEO
add_filter('document_title_parts', function($p) use ($genero_nome) {
    $p['title'] = $genero_nome . ' — ' . get_bloginfo('name');
    unset($p['site']);
    return $p;
});

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part('template-parts/topbar'); ?>

        <!-- Banner do gênero -->
        <div style="background:linear-gradient(135deg, <?php echo esc_attr($cfg['cor']); ?> 0%, #1a1a1a 60%);
                    padding:48px 36px 36px;position:relative;overflow:hidden"
             role="banner">

            <!-- Padrão decorativo de fundo -->
            <div style="position:absolute;right:-20px;top:-20px;font-size:180px;opacity:.06;
                        line-height:1;pointer-events:none;user-select:none">
                <?php echo $cfg['icone']; ?>
            </div>

            <!-- Breadcrumb -->
            <nav aria-label="Navegação estrutural" style="margin-bottom:16px">
                <ol style="list-style:none;padding:0;margin:0;display:flex;gap:6px;
                           font-size:12px;color:var(--cv-text-dim)">
                    <li><a href="<?php echo esc_url(home_url('/')); ?>"
                           style="color:var(--cv-text-dim);text-decoration:none">Início</a></li>
                    <li style="opacity:.5">/</li>
                    <li><a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                           style="color:var(--cv-text-dim);text-decoration:none">Músicas</a></li>
                    <li style="opacity:.5">/</li>
                    <li style="color:var(--cv-gold)"><?php echo esc_html($genero_nome); ?></li>
                </ol>
            </nav>

            <div style="font-size:48px;margin-bottom:12px"><?php echo $cfg['icone']; ?></div>

            <h1 style="font-family:var(--font-display);font-size:38px;font-weight:700;margin:0 0 8px">
                <?php echo esc_html($genero_nome); ?>
            </h1>

            <?php if ($genero_desc) : ?>
            <p style="color:var(--cv-text-muted);font-family:var(--font-body);font-style:italic;
                      font-size:16px;margin:0 0 20px;max-width:480px">
                <?php echo esc_html($genero_desc); ?>
            </p>
            <?php endif; ?>

            <div style="display:flex;gap:20px;flex-wrap:wrap">
                <div style="text-align:center;background:rgba(0,0,0,.3);border-radius:10px;padding:12px 20px">
                    <div style="font-family:var(--font-display);font-size:28px;font-weight:700;color:var(--cv-gold)">
                        <?php echo number_format($total); ?>
                    </div>
                    <div style="font-size:11px;color:var(--cv-text-muted);text-transform:uppercase;letter-spacing:.5px">
                        Música<?php echo $total !== 1 ? 's' : ''; ?>
                    </div>
                </div>
                <?php if (!empty($top_genero)) :
                    $total_plays_genero = array_sum(array_column($top_genero, 'plays_total'));
                ?>
                <div style="text-align:center;background:rgba(0,0,0,.3);border-radius:10px;padding:12px 20px">
                    <div style="font-family:var(--font-display);font-size:28px;font-weight:700;color:var(--cv-gold)">
                        <?php echo number_format($total_plays_genero); ?>
                    </div>
                    <div style="font-size:11px;color:var(--cv-text-muted);text-transform:uppercase;letter-spacing:.5px">
                        Plays
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Top 5 deste gênero -->
        <?php if ( ! empty($top_genero) ) : ?>
        <section class="cv-section cv-section-sm" aria-label="Top do gênero">
            <div class="cv-section-header">
                <h2 class="cv-section-title">
                    🏆 Top <span><?php echo esc_html($genero_nome); ?></span>
                </h2>
            </div>

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
                <?php foreach ($top_genero as $i => $m) :
                    $music_id   = $m->music_id;
                    $show_rank  = true;
                    $show_genre = false;
                    get_template_part('template-parts/card-musica');
                endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <!-- Todas as músicas do gênero -->
        <section class="cv-section" aria-label="Músicas do gênero">
            <div class="cv-section-header">
                <h2 class="cv-section-title">
                    Todas as músicas de <span><?php echo esc_html($genero_nome); ?></span>
                </h2>
                <span style="color:var(--cv-text-dim);font-size:13px">
                    <?php echo number_format($query->found_posts); ?> resultado<?php echo $query->found_posts !== 1 ? 's' : ''; ?>
                </span>
            </div>

            <?php if ($query->have_posts()) : ?>

            <div class="cv-grid">
                <?php while ($query->have_posts()) :
                    $query->the_post();
                    $music_id   = get_the_ID();
                    $show_rank  = false;
                    $show_genre = false;
                    get_template_part('template-parts/card-musica');
                endwhile;
                wp_reset_postdata(); ?>
            </div>

            <!-- Paginação -->
            <?php if ($query->max_num_pages > 1) : ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:36px;flex-wrap:wrap">
                <?php for ($i = 1; $i <= $query->max_num_pages; $i++) :
                    $url_p = $i === 1 ? get_term_link($genero) : get_term_link($genero) . 'page/' . $i . '/';
                    $ativo  = $i === $paged;
                ?>
                <a href="<?php echo esc_url($url_p); ?>"
                   style="display:inline-flex;align-items:center;justify-content:center;
                          width:38px;height:38px;border-radius:50%;font-size:13px;font-weight:700;
                          text-decoration:none;transition:all .2s;
                          background:<?php echo $ativo ? 'var(--cv-gold)' : 'var(--cv-bg-card)'; ?>;
                          color:<?php echo $ativo ? '#1a1a1a' : 'var(--cv-text-muted)'; ?>;
                          border:1px solid <?php echo $ativo ? 'var(--cv-gold)' : 'var(--cv-border)'; ?>">
                    <?php echo $i; ?>
                </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <?php else : ?>
            <div style="text-align:center;padding:48px;color:var(--cv-text-muted)">
                <p>Nenhuma música cadastrada neste gênero ainda.</p>
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-btn cv-btn-ghost" style="margin-top:16px">
                    ← Ver todas as músicas
                </a>
            </div>
            <?php endif; ?>
        </section>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>

</div>

<?php get_footer(); ?>
