<?php
/*
 * Template Name: Playlists da Comunidade
 */
// cancao-verdadeira-child/templates/page-playlists-comunidade.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página "Playlists da Comunidade" (/playlists-da-comunidade/), criada em
// 14/10/2026 (tema v15.41.0, plugin v2.67.0): as playlists 🌐 públicas de
// todos os ouvintes, mais novas primeiro, com capa, dono (link para o perfil)
// e "▶ Tocar" (handler .cv-pl-tocar no cv-theme.js). 24 por página (?pg=2).
// A página é criada pelo plugin (CV_Playlists::criar_pagina_comunidade).
// v15.42.0: cartão compacto em fila (como "Suas playlists"): capa pequena, nome,
// "N músicas · por Fulana", só o ▶ e os nomes de até 3 músicas embaixo
// (plugin v2.67.1). Cabem muito mais playlists na tela do celular.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$por_pagina = 24;
$pagina     = max( 1, absint( $_GET['pg'] ?? 1 ) );
$tem_plugin = class_exists( 'CV_Playlists' ) && method_exists( 'CV_Playlists', 'get_public_playlists' );
$playlists  = $tem_plugin ? CV_Playlists::get_public_playlists( $por_pagina, $pagina ) : array();
$total      = $tem_plugin ? CV_Playlists::count_public_playlists() : 0;
$paginas    = (int) ceil( $total / $por_pagina );

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => '🌐 Feitas pelos ouvintes',
            'titulo'    => 'Playlists da',
            'destaque'  => 'Comunidade',
            'subtitulo' => 'Seleções de músicas que os nossos ouvintes quiseram dividir com você.',
        ) );
        ?>

        <section class="cv-section" aria-label="Playlists públicas">

            <?php if ( empty( $playlists ) ) : ?>
            <div class="cv-empty">
                🎶 Ainda não há playlists públicas. Que tal ser o primeiro?<br>
                Crie a sua em <strong>Minhas Playlists</strong> e marque <strong>🌐 Pública</strong>.
                <div style="margin-top:16px">
                    <a class="cv-btn cv-btn-primary"
                       href="<?php echo esc_url( is_user_logged_in() ? home_url( '/minhas-playlists/' ) : cv_register_url() ); ?>">
                        <?php echo is_user_logged_in() ? '📋 Minhas Playlists' : 'Criar minha conta'; ?>
                    </a>
                </div>
            </div>
            <?php else : ?>

            <div class="cv-comunidade-grade">
                <?php foreach ( $playlists as $pl ) :
                    $musicas = ! empty( $pl->musicas ) ? (array) $pl->musicas : array();
                ?>
                <article class="cv-comunidade-card">
                    <div class="cv-comunidade-capa">
                        <?php if ( $pl->capa ) : ?>
                        <img src="<?php echo esc_url( $pl->capa ); ?>" alt="" loading="lazy" />
                        <?php else : ?>
                        <span aria-hidden="true">📋</span>
                        <?php endif; ?>
                    </div>
                    <div class="cv-comunidade-corpo">
                        <h2 class="cv-comunidade-nome"><?php echo esc_html( $pl->name ); ?></h2>
                        <div class="cv-comunidade-info">
                            <?php echo (int) $pl->count; ?> música<?php echo 1 !== (int) $pl->count ? 's' : ''; ?>
                            <?php if ( $pl->autor ) : ?>
                            · por
                            <?php if ( $pl->perfil ) : ?>
                            <a href="<?php echo esc_url( $pl->perfil ); ?>"><?php echo esc_html( $pl->autor ); ?></a>
                            <?php else : ?>
                            <?php echo esc_html( $pl->autor ); ?>
                            <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="cv-pl-tocar cv-comunidade-play"
                            data-playlist="<?php echo (int) $pl->id; ?>"
                            aria-label="Tocar a playlist <?php echo esc_attr( $pl->name ); ?>" title="Tocar">▶</button>
                    <?php if ( $musicas ) : ?>
                    <ul class="cv-comunidade-musicas">
                        <?php foreach ( $musicas as $titulo ) : ?>
                        <li>♪ <?php echo esc_html( $titulo ); ?></li>
                        <?php endforeach; ?>
                        <?php if ( (int) $pl->count > count( $musicas ) ) : ?>
                        <li class="cv-comunidade-mais">e mais <?php echo (int) $pl->count - count( $musicas ); ?></li>
                        <?php endif; ?>
                    </ul>
                    <?php endif; ?>
                </article>
                <?php endforeach; ?>
            </div>

            <?php if ( $paginas > 1 ) : ?>
            <nav class="cv-comunidade-paginas" aria-label="Páginas">
                <?php if ( $pagina > 1 ) : ?>
                <a class="cv-btn" href="<?php echo esc_url( add_query_arg( 'pg', $pagina - 1 ) ); ?>">← Anteriores</a>
                <?php endif; ?>
                <span>Página <?php echo (int) $pagina; ?> de <?php echo (int) $paginas; ?></span>
                <?php if ( $pagina < $paginas ) : ?>
                <a class="cv-btn" href="<?php echo esc_url( add_query_arg( 'pg', $pagina + 1 ) ); ?>">Próximas →</a>
                <?php endif; ?>
            </nav>
            <?php endif; ?>

            <?php endif; ?>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_footer(); ?>
