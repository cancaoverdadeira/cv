<?php
// cancao-verdadeira-child/template-parts/banner-pagina.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Banner das páginas internas (v15.14.0, 25/09/2026): o mesmo visual do hero
// da página inicial (.cv-hero--marca), em versão mais baixa (.cv-hero--pagina).
// Usado em: Todas as Músicas, Blog, Minhas Playlists, Notificações e nas
// páginas de perfil/conta (page.php). A imagem é a de Aparência → Banner
// (opção cv_banner_url); sem ela, o banner mostra só o texto.
// Uso: get_template_part( 'template-parts/banner-pagina', null, array(
//        'tag' => '★ …', 'titulo' => '…', 'destaque' => '…', 'subtitulo' => '…',
//        'acoes' => '<a …>…</a>' ) );   // 'acoes' é HTML montado pelo próprio tema

if ( ! defined( 'ABSPATH' ) ) { exit; }

$args = wp_parse_args( isset( $args ) ? $args : array(), array(
    'tag'       => '★ Sertanejo autoral',
    'titulo'    => get_the_title(),
    'destaque'  => '',
    'subtitulo' => '',
    'acoes'     => '',
) );
$banner_url = get_option( 'cv_banner_url', '' );
?>
<section class="cv-hero cv-hero--marca cv-hero--pagina<?php echo $banner_url ? '' : ' cv-hero--sem-marca'; ?>"
         aria-label="<?php echo esc_attr( wp_strip_all_tags( $args['titulo'] . ' ' . $args['destaque'] ) ); ?>">
    <div class="cv-hero-content">
        <?php if ( $args['tag'] ) : ?>
        <div class="cv-hero-tag"><?php echo esc_html( $args['tag'] ); ?></div>
        <?php endif; ?>

        <h1 class="cv-hero-title">
            <?php echo esc_html( $args['titulo'] ); ?>
            <?php if ( $args['destaque'] ) : ?>
            <span class="cv-hero-title-destaque"><?php echo esc_html( $args['destaque'] ); ?></span>
            <?php endif; ?>
        </h1>

        <?php if ( $args['subtitulo'] ) : ?>
        <p class="cv-hero-subtitle"><?php echo esc_html( $args['subtitulo'] ); ?></p>
        <?php endif; ?>

        <?php if ( $args['acoes'] ) : ?>
        <div class="cv-hero-actions"><?php echo $args['acoes']; // HTML do próprio tema ?></div>
        <?php endif; ?>
    </div>

    <?php if ( $banner_url ) : ?>
    <img class="cv-hero-marca"
         src="<?php echo esc_url( $banner_url ); ?>"
         alt="Canção Verdadeira"
         width="1600" height="1131">
    <?php endif; ?>
</section>
