<?php
/*
 * Template Name: Buscar Músicas
 */
// cancao-verdadeira-child/templates/page-search.php
// Gerado em: 2026-06-22 02:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Busca avançada: campo principal, filtros por gênero e compositor,
// ordenação e resultados em grid. Busca via WP_Query com suporte a
// busca no conteúdo (letra). Resultados sem reload usando AJAX.

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Parâmetros da busca
$termo     = sanitize_text_field( $_GET['q']          ?? '' );
$genero    = sanitize_key(        $_GET['genero']      ?? '' );
$orderby   = sanitize_key(        $_GET['orderby']     ?? 'relevance' );
$paged     = max(1, absint(       $_GET['paged']       ?? 1 ));
$per_page  = 20;

$generos = get_terms(array('taxonomy' => 'cv_genre', 'hide_empty' => false, 'orderby' => 'name'));

// Query de busca
$results  = null;
$total    = 0;

if ( $termo || $genero ) {
    $args = array(
        'post_type'      => 'musica',
        'post_status'    => 'publish',
        'posts_per_page' => $per_page,
        'paged'          => $paged,
        'meta_query'     => array(
            array('key' => '_cv_ativo', 'value' => '1', 'compare' => '='),
        ),
    );

    if ( $termo ) {
        $args['s'] = $termo;
    }

    if ( $genero ) {
        $args['tax_query'] = array(array(
            'taxonomy' => 'cv_genre',
            'field'    => 'slug',
            'terms'    => $genero,
        ));
    }

    switch ($orderby) {
        case 'plays':
            $args['meta_key'] = '_cv_plays_total';
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'DESC';
            break;
        case 'recente':
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
            break;
        default: // relevance
            $args['orderby'] = $termo ? 'relevance' : 'date';
            $args['order']   = 'DESC';
    }

    $results = new WP_Query($args);
    $total   = $results->found_posts;
}

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part('template-parts/topbar'); ?>

        <!-- Campo de busca hero -->
        <div style="background:linear-gradient(180deg,#FFFFFF 0%,var(--cv-bg) 100%);
                    padding:48px 36px 36px">
            <h1 style="font-family:var(--font-display);font-size:32px;font-weight:700;
                       margin:0 0 24px;text-align:center">
                🔍 Buscar <span style="color:var(--cv-gold)">Músicas</span>
            </h1>

            <form method="GET" action="" style="max-width:640px;margin:0 auto">
                <div class="cv-search-wrap" style="position:relative;margin-bottom:16px">
                    <input type="text"
                           name="q"
                           class="cv-input cv-search-input"
                           id="cv-search-field"
                           value="<?php echo esc_attr($termo); ?>"
                           placeholder="Buscar por título, artista, compositor ou trecho da letra..."
                           autocomplete="off"
                           style="font-size:16px;padding:14px 48px 14px 18px" />
                    <button type="submit"
                            style="position:absolute;right:8px;top:50%;transform:translateY(-50%);
                                   background:var(--cv-accent);border:none;border-radius:var(--cv-radius-sm);
                                   padding:8px 14px;color:#3B2418;cursor:pointer;font-size:16px">
                        🔍
                    </button>
                    <div class="cv-autocomplete-list"
                         style="position:absolute;top:100%;left:0;right:0;z-index:50;margin-top:4px"></div>
                </div>

                <!-- Filtros -->
                <div style="display:flex;gap:10px;flex-wrap:wrap;justify-content:center">

                    <select name="genero"
                            style="padding:8px 14px;background:var(--cv-bg-elevated);border:1px solid var(--cv-border);
                                   border-radius:var(--cv-radius-sm);color:var(--cv-text);font-size:13px;cursor:pointer">
                        <option value="">Todos os gêneros</option>
                        <?php if (!is_wp_error($generos)) foreach ($generos as $g) : ?>
                        <option value="<?php echo esc_attr($g->slug); ?>"
                                <?php selected($genero, $g->slug); ?>>
                            <?php echo esc_html($g->name); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>

                    <select name="orderby"
                            style="padding:8px 14px;background:var(--cv-bg-elevated);border:1px solid var(--cv-border);
                                   border-radius:var(--cv-radius-sm);color:var(--cv-text);font-size:13px;cursor:pointer">
                        <option value="relevance" <?php selected($orderby,'relevance'); ?>>Mais relevantes</option>
                        <option value="plays"     <?php selected($orderby,'plays'); ?>>Mais tocadas</option>
                        <option value="recente"   <?php selected($orderby,'recente'); ?>>Mais recentes</option>
                    </select>

                    <button type="submit" class="cv-btn cv-btn-primary">
                        Buscar
                    </button>

                    <?php if ($termo || $genero) : ?>
                    <a href="<?php echo esc_url(home_url('/buscar-musicas/')); ?>"
                       class="cv-btn cv-btn-secondary">
                        Limpar
                    </a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Resultados -->
        <div class="cv-section">

            <?php if ($results !== null) : ?>

            <!-- Cabeçalho dos resultados -->
            <div style="display:flex;align-items:baseline;justify-content:space-between;
                        margin-bottom:20px;flex-wrap:wrap;gap:10px">
                <h2 style="font-size:16px;font-weight:700;margin:0;color:var(--cv-text)">
                    <?php if ($termo) : ?>
                    Resultados para <span style="color:var(--cv-gold)">"<?php echo esc_html($termo); ?>"</span>
                    <?php elseif ($genero) : ?>
                    Músicas de <span style="color:var(--cv-gold)"><?php echo esc_html(get_term_by('slug', $genero, 'cv_genre')->name ?? $genero); ?></span>
                    <?php endif; ?>
                </h2>
                <span style="color:var(--cv-text-dim);font-size:13px">
                    <?php echo number_format($total); ?> resultado<?php echo $total !== 1 ? 's' : ''; ?>
                </span>
            </div>

            <?php if ($results->have_posts()) : ?>

            <div class="cv-grid">
                <?php while ($results->have_posts()) :
                    $results->the_post();
                    $music_id   = get_the_ID();
                    $show_rank  = false;
                    $show_genre = ! $genero;
                    get_template_part('template-parts/card-musica');
                endwhile;
                wp_reset_postdata(); ?>
            </div>

            <!-- Paginação -->
            <?php if ($results->max_num_pages > 1) : ?>
            <div style="display:flex;justify-content:center;gap:8px;margin-top:36px;flex-wrap:wrap">
                <?php for ($i = 1; $i <= $results->max_num_pages; $i++) :
                    $url_p = add_query_arg(array('q' => $termo, 'genero' => $genero, 'orderby' => $orderby, 'paged' => $i), '');
                    $ativo = $i === $paged;
                ?>
                <a href="<?php echo esc_url($url_p); ?>"
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
            <!-- Nenhum resultado -->
            <div style="text-align:center;padding:60px 20px">
                <div style="font-size:48px;margin-bottom:16px">🔍</div>
                <h2 style="font-family:var(--font-display);color:var(--cv-gold);margin-bottom:8px">
                    Nenhum resultado encontrado
                </h2>
                <p style="color:var(--cv-text-muted);margin-bottom:24px">
                    Tente palavras diferentes ou explore por gênero
                </p>
                <a href="<?php echo esc_url(home_url('/musicas/')); ?>"
                   class="cv-btn cv-btn-ghost">
                    Ver todas as músicas
                </a>
            </div>
            <?php endif; ?>

            <?php else : ?>
            <!-- Estado inicial — sem busca -->
            <div style="text-align:center;padding:40px 20px">
                <?php if (!is_wp_error($generos) && !empty($generos)) : ?>
                <p style="color:var(--cv-text-muted);margin-bottom:24px;font-size:15px">
                    Ou explore por gênero:
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:10px;justify-content:center;max-width:600px;margin:0 auto">
                    <?php foreach ($generos as $g) :
                        $ic = array(
                            'sertanejo-universitario' => '🎸', 'sertanejo-raiz' => '🪗',
                            'sertanejo-romantico' => '❤', 'modao' => '🎩',
                            'sertanejo-gospel' => '✝', 'sertanejo-sofrencia' => '💔',
                        );
                    ?>
                    <a href="<?php echo esc_url(get_term_link($g)); ?>"
                       class="cv-genre-pill" style="font-size:14px;padding:10px 18px">
                        <?php echo $ic[$g->slug] ?? '🎵'; ?>
                        <?php echo esc_html($g->name); ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>
    </main>
</div>

<script>
jQuery(function($){
    // Submit ao pressionar Enter
    $('#cv-search-field').on('keypress', function(e){
        if (e.which === 13) { $(this).closest('form').submit(); }
    });
    // Foco automático no campo
    if (!$('#cv-search-field').val()) { $('#cv-search-field').focus(); }
});
</script>

<?php get_footer(); ?>
