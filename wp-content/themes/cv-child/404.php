<?php
// cancao-verdadeira-child/404.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página de erro 404 no layout da home: menu lateral, barra do topo, hero com
// a marca (mesmo estilo .cv-hero--marca da home), o "4♪4" animado e a busca
// avançada (template-parts/busca-avancada.php → CV_Search/Relevanssi).
// Abaixo: atalhos por sentimento e "Que tal ouvir…" (Top/Seleção ou as mais
// recentes; cards "Em breve" enquanto não há músicas publicadas).
// O WordPress já devolve o status HTTP 404; o Rank Math marca como noindex.
// Gerado em: 2026-09-23 (tema v15.5.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }

$banner_url = get_option( 'cv_banner_url', '' );

// Sugestão de termo: as palavras do endereço que não existe (ex.: /saudade-na-porta/)
$caminho  = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
$sugestao = trim( preg_replace( '/[-_\/]+/', ' ', basename( untrailingslashit( $caminho ) ) ) );
$sugestao = mb_substr( sanitize_text_field( urldecode( $sugestao ) ), 0, 60 );

// Músicas para sugerir: seleção/top, senão as mais recentes
$sugeridas = array();
$titulo_sugeridas = 'Que tal <span>ouvir</span>…';
if ( class_exists( 'CV_Launch' ) && ! CV_Launch::ranking_ready() ) {
    $sugeridas = CV_Launch::selection( 5 );
} elseif ( class_exists( 'CV_Ranking' ) ) {
    $sugeridas = CV_Ranking::get_top( 5 );
}
if ( empty( $sugeridas ) && class_exists( 'CV_Ranking' ) ) {
    $sugeridas = CV_Ranking::get_recent( 5 );
}
$sentimentos = class_exists( 'CV_Sentimentos' ) ? CV_Sentimentos::get_all() : array();

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <section class="cv-hero cv-hero--marca cv-hero--404" aria-label="Página não encontrada">
            <div class="cv-hero-content">
                <div class="cv-hero-tag">★ Página não encontrada</div>
                <div class="cv-404-codigo" aria-hidden="true">4<span class="cv-404-nota">♪</span>4</div>
                <h1 class="cv-hero-title">Essa música <span class="cv-hero-title-destaque">saiu do tom</span></h1>
                <p class="cv-hero-subtitle">
                    O endereço não existe ou mudou de lugar. Procure pelo título, por um trecho da letra ou pelo compositor:
                </p>

                <?php get_template_part( 'template-parts/busca-avancada', null, array(
                    'termo'     => $sugestao,
                    'autofocus' => true,
                ) ); ?>

                <div class="cv-404-atalhos">
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cv-btn cv-btn-primary">🏠 Voltar ao início</a>
                    <a href="<?php echo esc_url( home_url( '/musicas/' ) ); ?>" class="cv-btn cv-btn-ghost">🎵 Todas as músicas</a>
                </div>
            </div>

            <?php if ( $banner_url ) : ?>
            <img class="cv-hero-marca"
                 src="<?php echo esc_url( $banner_url ); ?>"
                 alt="Canção Verdadeira"
                 width="1600" height="1131">
            <?php endif; ?>
        </section>

        <?php if ( $sentimentos && class_exists( 'CV_Search' ) ) : ?>
        <section class="cv-section cv-section-sm" aria-label="Buscar por sentimento">
            <div class="cv-busca-cabecalho"><h2>Buscar por <span>sentimento</span></h2></div>
            <div class="cv-busca-sentimentos">
                <?php foreach ( $sentimentos as $s ) : ?>
                <a href="<?php echo esc_url( add_query_arg( 'sentimento', $s->slug, CV_Search::url() ) ); ?>"
                   class="cv-busca-sentimento" style="--sent-cor:<?php echo esc_attr( $s->cor ?: '#C9A27E' ); ?>">
                    <span><?php echo esc_html( $s->icone ); ?></span> <?php echo esc_html( $s->nome ); ?>
                </a>
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="cv-section" aria-label="Sugestões de músicas">
            <div class="cv-section-header">
                <h2 class="cv-section-title"><?php echo wp_kses( $titulo_sugeridas, array( 'span' => array() ) ); ?></h2>
                <a href="<?php echo esc_url( home_url( '/musicas/' ) ); ?>" class="cv-section-link">Ver todas →</a>
            </div>
            <?php if ( $sugeridas ) : ?>
            <div class="cv-grid">
                <?php foreach ( $sugeridas as $m ) {
                    get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $m->music_id, 'show_rank' => false ) );
                } ?>
            </div>
            <?php else :
                get_template_part( 'template-parts/card-em-breve', null, array( 'quantidade' => 5, 'icone' => '🎵', 'texto' => 'Em breve' ) );
            endif; ?>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_footer(); ?>
