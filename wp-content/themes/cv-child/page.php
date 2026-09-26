<?php
// cancao-verdadeira-child/page.php
// Gerado em: 2026-06-22 03:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Template genérico para todas as páginas WordPress sem template específico.
// Usado por: Login, Cadastro, Meu Perfil, Contato e qualquer página criada
// manualmente. Aplica o layout completo do tema filho: sidebar + topbar +
// conteúdo centralizado + player. Sem este arquivo o WordPress usa o layout
// padrão do Astra, que não tem sidebar nem player.
// v15.14.0: Meu Perfil, Perfil do membro e Minha Conta ganham o banner da marca
// (template-parts/banner-pagina.php) no lugar do título simples.
// v15.35.0: Cadastro, Login e Recuperar senha também ganham o banner (antes o
// formulário aparecia solto, sem título nem explicação).

if ( ! defined( 'ABSPATH' ) ) { exit; }

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part('template-parts/topbar'); ?>

        <?php while ( have_posts() ) : the_post(); ?>

        <?php
        // Páginas de perfil/conta com banner: slug => textos
        $banners = array(
            'meu-perfil'  => array( 'tag' => '👤 Sua conta', 'titulo' => 'Meu',   'destaque' => 'Perfil', 'subtitulo' => 'Seus dados, sua foto e sua senha.' ),
            'membro'      => array( 'tag' => '👤 Perfil',    'titulo' => 'Perfil', 'destaque' => 'do Ouvinte', 'subtitulo' => 'Músicas favoritas, playlists e conquistas.' ),
            'minha-conta' => array( 'tag' => '⚙️ Sua conta', 'titulo' => 'Minha', 'destaque' => 'Conta',  'subtitulo' => 'Dados de acesso, privacidade e notificações.' ),
            // v15.35.0: Cadastro, Login e Recuperar senha ganham título e explicação
            'cadastro'        => array( 'tag' => '🎵 É grátis', 'titulo' => 'Crie sua', 'destaque' => 'conta', 'subtitulo' => 'Favorite músicas, monte suas playlists e receba as novidades. Leva só um minuto.' ),
            'login'           => array( 'tag' => '👋 Que bom te ver', 'titulo' => 'Entrar na', 'destaque' => 'sua conta', 'subtitulo' => 'Suas músicas favoritas e playlists estão esperando por você.' ),
            'recuperar-senha' => array( 'tag' => '🔑 Acesso', 'titulo' => 'Recuperar', 'destaque' => 'senha', 'subtitulo' => 'Digite o seu e-mail e enviamos um link para você criar uma senha nova.' ),
        );
        $slug   = get_post_field( 'post_name', get_the_ID() );
        $banner = isset( $banners[ $slug ] ) ? $banners[ $slug ] : null;
        if ( $banner ) {
            get_template_part( 'template-parts/banner-pagina', null, $banner );
        }
        ?>

        <article style="max-width:760px;margin:0 auto;padding:<?php echo $banner ? '28px' : '48px'; ?> 36px 60px">

            <?php
            // Cabeçalho com título (exceto em páginas que têm shortcode de form)
            // Detecta se a página tem shortcode de auth para omitir título redundante
            $content = get_the_content();
            $has_form_shortcode = (
                has_shortcode($content, 'cv_login_form')    ||
                has_shortcode($content, 'cv_register_form') ||
                has_shortcode($content, 'cv_profile_form')
            );
            ?>

            <?php if ( ! $has_form_shortcode && ! $banner ) : ?>
            <header style="margin-bottom:32px;padding-bottom:20px;
                           border-bottom:1px solid var(--cv-border-subtle)">
                <h1 style="font-family:var(--font-display);font-size:32px;
                           font-weight:700;margin:0;color:var(--cv-text)">
                    <?php the_title(); ?>
                </h1>
            </header>
            <?php endif; ?>

            <div class="cv-page-content"
                 style="color:var(--cv-text);line-height:1.8;font-size:15px">
                <?php the_content(); ?>
            </div>

        </article>

        <?php endwhile; ?>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>

    </main>

</div>


<?php get_footer(); ?>
