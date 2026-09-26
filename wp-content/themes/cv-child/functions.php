<?php
// cancao-verdadeira-child/functions.php
// Gerado em: 2026-07-28 21:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// v15.0.0 — functions.php do tema filho. Enfileira CSS e JS do filho sobre
// o Astra, desativa elementos do Astra desnecessários (header, footer,
// sidebar), registra helpers de URL (login, cadastro, dashboard), e expõe
// funções auxiliares usadas pelos templates PHP do tema filho.
// NESTA VERSÃO: removido o registro do WaveSurfer.js (motor antigo,
// causava as falhas da v13). Adicionado o registro da YouTube IFrame API,
// usada pelo novo motor do player em assets/js/cv-player.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CV_CHILD_VERSION', '15.24.0' );
define( 'CV_CHILD_DIR',     get_stylesheet_directory() );
define( 'CV_CHILD_URL',     get_stylesheet_directory_uri() );

// ── Suporte do tema ───────────────────────────────────────────────
add_action( 'after_setup_theme', 'cv_child_setup' );
function cv_child_setup() {
    add_theme_support( 'title-tag' );
    add_theme_support( 'post-thumbnails' );
    add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption' ) );
    add_theme_support( 'responsive-embeds' );
    add_theme_support( 'custom-logo', array( 'height' => 80, 'width' => 250, 'flex-width' => true ) );

    // Tamanhos de imagem do tema filho
    add_image_size( 'cv-card',    400, 400, true );
    add_image_size( 'cv-hero',   1280, 640, true );
    add_image_size( 'cv-thumb',   600, 400, true );

    // Menu de rodapé
    register_nav_menus( array(
        'cv-footer' => 'Rodapé — Links',
    ) );

    load_theme_textdomain( 'cancao-verdadeira-child', CV_CHILD_DIR . '/languages' );
}

// ── Desativa elementos do Astra que não usamos ────────────────────
add_action( 'after_setup_theme', 'cv_child_disable_astra_features', 99 );
function cv_child_disable_astra_features() {
    // Remove header padrão do Astra
    remove_action( 'astra_header',         'astra_header_markup' );
    remove_action( 'astra_masthead',        'astra_masthead_markup' );
    remove_action( 'wp_footer',             'astra_footer_markup' );

    // Remove sidebar padrão do Astra
    add_filter( 'astra_page_layout', function() { return 'no-sidebar'; } );

    // Garante que o Astra não adicione seu próprio header via hook
    add_filter( 'astra_primary_header_cart_icon', '__return_false' );
}

// ── Desativa metabox de layout do Astra no editor de posts ───────
add_filter( 'astra_show_meta_options', '__return_false' );

// ── Enqueue de assets ─────────────────────────────────────────────
add_action( 'wp_enqueue_scripts', 'cv_child_enqueue', 20 );
function cv_child_enqueue() {

    // Fontes Google
    wp_enqueue_style(
        'cv-fonts',
        'https://fonts.googleapis.com/css2?family=Oswald:wght@400;600;700&family=EB+Garamond:ital,wght@0,400;0,600;1,400&family=Lato:wght@300;400;700;900&display=swap',
        array(), null
    );

    // CSS do filho — ordem importa
    wp_enqueue_style( 'cv-variables',  CV_CHILD_URL . '/assets/css/cv-variables.css',  array(), CV_CHILD_VERSION );
    wp_enqueue_style( 'cv-layout',     CV_CHILD_URL . '/assets/css/cv-layout.css',     array('cv-variables'), CV_CHILD_VERSION );
    wp_enqueue_style( 'cv-components', CV_CHILD_URL . '/assets/css/cv-components.css', array('cv-layout'),    CV_CHILD_VERSION );

    // style.css do filho (identificação — quase vazio, mas deve ser enfileirado)
    wp_enqueue_style( 'cv-child-style', get_stylesheet_uri(), array('cv-components'), CV_CHILD_VERSION );

    // Ajustes visuais (player escuro, logo do menu) — fora dos CSS protegidos
    wp_enqueue_style( 'cv-ajustes', CV_CHILD_URL . '/assets/css/cv-ajustes.css', array('cv-child-style'), CV_CHILD_VERSION );
    // Estilos das páginas (antes em blocos <style> nos templates — v15.9.0)
    wp_enqueue_style( 'cv-paginas', CV_CHILD_URL . '/assets/css/cv-paginas.css', array('cv-ajustes'), CV_CHILD_VERSION );

    // JS do tema filho
    wp_enqueue_script(
        'cv-theme-js',
        CV_CHILD_URL . '/assets/js/cv-theme.js',
        array( 'jquery', 'cv-public-js' ), // cv-public-js vem do plugin
        CV_CHILD_VERSION,
        true
    );

    // YouTube IFrame API — script oficial, carrega assincronamente e chama
    // window.onYouTubeIframeAPIReady (definido em cv-player.js) quando pronto.
    wp_register_script( 'youtube-iframe-api', 'https://www.youtube.com/iframe_api', array(), null, true );
    wp_enqueue_script( 'youtube-iframe-api' );

    wp_enqueue_script(
        'cv-player-js',
        CV_CHILD_URL . '/assets/js/cv-player.js',
        array( 'jquery', 'cv-theme-js', 'youtube-iframe-api' ),
        CV_CHILD_VERSION,
        true
    );

    // JS da página da música (v15.10.0: antes era um <script> em linha no
    // single-musica.php). Precisa de cvPublic (plugin) e recebe cvMusica.
    if ( is_singular( 'musica' ) ) {
        wp_enqueue_script(
            'cv-musica-js',
            CV_CHILD_URL . '/assets/js/cv-musica.js',
            array( 'jquery', 'cv-public-js' ),
            CV_CHILD_VERSION,
            true
        );
        // v15.22.0: "Ofereça esta música" (cartão + dedicatória)
        wp_enqueue_script( 'cv-oferecer', CV_CHILD_URL . '/assets/js/cv-oferecer.js', array( 'jquery' ), CV_CHILD_VERSION, true );
        // v15.24.0: "🎤 Cantar junto" (letra em tela cheia, estrofe por estrofe)
        wp_enqueue_script( 'cv-cantar', CV_CHILD_URL . '/assets/js/cv-cantar.js', array( 'jquery', 'cv-musica-js' ), CV_CHILD_VERSION, true );
        wp_localize_script( 'cv-musica-js', 'cvMusica', array(
            'musicId' => (int) get_queried_object_id(),
            'playMs'  => ( defined( 'CV_PLAY_SECONDS' ) ? (int) CV_PLAY_SECONDS : 30 ) * 1000,
        ) );
    }

    // Dados do tema para o JS
    $login_page     = get_page_by_path( 'login' );
    $register_page  = get_page_by_path( 'cadastro' );
    $dashboard_page = get_page_by_path( 'minha-area' );
    $profile_page   = get_page_by_path( 'meu-perfil' );

    wp_localize_script( 'cv-theme-js', 'cvTheme', array(
        'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
        'restUrl'       => rest_url( 'cv/v1/' ),
        'siteUrl'       => home_url(),
        'isLoggedIn'    => is_user_logged_in() ? 1 : 0,
        'loginUrl'      => $login_page    ? get_permalink($login_page->ID)    : wp_login_url(),
        'registerUrl'   => $register_page ? get_permalink($register_page->ID) : wp_registration_url(),
        'dashboardUrl'  => $dashboard_page? get_permalink($dashboard_page->ID): home_url('/minha-area/'),
        'profileUrl'    => $profile_page  ? get_permalink($profile_page->ID)  : home_url('/meu-perfil/'),
        'isFrontPage'   => ( is_front_page() || is_home() ) ? 1 : 0,
        'currentMusicId'=> is_singular('musica') ? get_the_ID() : 0,
        'currentYtUrl'  => is_singular('musica') ? get_post_meta(get_the_ID(), '_cv_youtube_url', true) : '',
        'defaultCover'  => CV_CHILD_URL . '/assets/img/default-cover.svg',
        'playSeconds'   => (int) get_option('cv_play_seconds', 30),
    ) );
}

// ── Remove scripts/styles desnecessários do Astra ────────────────
add_action( 'wp_enqueue_scripts', 'cv_child_dequeue_astra', 100 );
function cv_child_dequeue_astra() {
    // Remove CSS de fontes padrão do Astra (usamos nossas próprias fontes)
    wp_dequeue_style( 'astra-google-fonts' );
    wp_deregister_style( 'astra-google-fonts' );
}

// ── Helpers de URL (usados nos templates PHP) ─────────────────────

function cv_login_url( $redirect = '' ) {
    $page = get_page_by_path( 'login' );
    if ( $page ) {
        $url = get_permalink( $page->ID );
        return $redirect ? add_query_arg( 'redirect_to', urlencode($redirect), $url ) : $url;
    }
    return wp_login_url( $redirect ?: get_permalink() );
}

function cv_register_url() {
    $page = get_page_by_path( 'cadastro' );
    return $page ? get_permalink( $page->ID ) : wp_registration_url();
}

function cv_dashboard_url() {
    $page = get_page_by_path( 'minha-area' );
    return $page ? get_permalink( $page->ID ) : home_url( '/minha-area/' );
}

function cv_profile_url() {
    $page = get_page_by_path( 'meu-perfil' );
    return $page ? get_permalink( $page->ID ) : home_url( '/meu-perfil/' );
}

function cv_logo_url() {
    $plugin_logo = get_option( 'cv_logo_url', '' );
    if ( $plugin_logo ) { return esc_url( $plugin_logo ); }
    $logo_id = get_theme_mod( 'custom_logo' );
    if ( $logo_id ) { return esc_url( wp_get_attachment_image_url( $logo_id, 'full' ) ); }
    return CV_CHILD_URL . '/assets/img/logo.svg';
}

function cv_banner_url() {
    $banner = get_option( 'cv_banner_url', '' );
    return $banner ? esc_url( $banner ) : CV_CHILD_URL . '/assets/img/banner.svg';
}

function cv_cover_url( $post_id = 0, $size = 'cv-card' ) {
    if ( ! $post_id ) { $post_id = get_the_ID(); }
    $url = get_the_post_thumbnail_url( $post_id, $size );
    if ( $url ) { return $url; }
    // Fallback: thumbnail YouTube (regra única do plugin, com fallback local)
    $thumb = cv_youtube_id( get_post_meta( $post_id, '_cv_youtube_url', true ) );
    if ( $thumb ) { return 'https://img.youtube.com/vi/' . $thumb . '/mqdefault.jpg'; }
    return CV_CHILD_URL . '/assets/img/default-cover.svg';
}

// v15.8.0: usa a regra única do plugin (CV_Fields::youtube_id); a cópia
// local só roda se o plugin estiver desativado.
function cv_youtube_id( $url ) {
    if ( class_exists( 'CV_Fields' ) ) { return CV_Fields::youtube_id( $url ); }
    if ( empty($url) || ! is_string($url) ) { return ''; }
    return preg_match( '~(?:[?&]v=|/embed/|youtu\.be/|/shorts/|/live/)([A-Za-z0-9_-]{11})~', $url, $m ) ? $m[1] : '';
}

// ── Compatibilidade com plugin cv-public-js ───────────────────────
// O plugin enfileira cv-public-js (prioridade 10). O tema filho depende dele.
// Garante que se o plugin não estiver ativo, não quebre.
// v15.5.1: rodava na prioridade 5, ANTES do plugin — registrava cv-public-js
// vazio e o WordPress ignorava o arquivo real do plugin (cv-public.js nunca
// carregava: favoritos, plays, playlists, busca…). Agora roda na 15: depois
// do plugin (10) e antes do tema enfileirar cv-theme-js (20).
add_action( 'wp_enqueue_scripts', 'cv_child_fallback_public_js', 15 );
function cv_child_fallback_public_js() {
    if ( ! wp_script_is( 'cv-public-js', 'registered' ) ) {
        wp_register_script( 'cv-public-js', false );
        // Cria objeto cvPublic mínimo para evitar erros JS
        wp_add_inline_script( 'cv-public-js', '
            window.cvPublic = window.cvPublic || {
                ajaxUrl: "' . admin_url('admin-ajax.php') . '",
                restUrl: "' . rest_url('cv/v1/') . '",
                isLoggedIn: ' . (is_user_logged_in() ? '1' : '0') . ',
                nonces: {},
                i18n: {}
            };
        ' );
    }
}

// ── SEO: título da página ─────────────────────────────────────────
add_filter( 'document_title_separator', function() { return '—'; } );
add_filter( 'document_title_parts', 'cv_child_title_parts' );
function cv_child_title_parts( $parts ) {
    if ( is_singular( 'musica' ) ) {
        global $post;
        $artista = get_post_meta( $post->ID, '_cv_artista', true );
        if ( $artista ) {
            $parts['title'] .= ' — ' . $artista;
        }
        $parts['site'] = 'Canção Verdadeira';
    }
    return $parts;
}

// ── Remove bloat padrão do WordPress ─────────────────────────────
add_action( 'init', 'cv_child_cleanup' );
function cv_child_cleanup() {
    remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
    remove_action( 'wp_print_styles', 'print_emoji_styles' );
    remove_action( 'wp_head', 'rsd_link' );
    remove_action( 'wp_head', 'wlwmanifest_link' );
    remove_action( 'wp_head', 'wp_generator' );
    remove_action( 'wp_head', 'wp_shortlink_wp_head' );
}

// ── Rewrite para perfil público ───────────────────────────────────
add_action( 'init', 'cv_child_rewrites' );
function cv_child_rewrites() {
    add_rewrite_rule( '^artista/([^/]+)/?$', 'index.php?pagename=artista&cv_artista=$matches[1]', 'top' );
    add_rewrite_tag( '%cv_artista%', '([^/]+)' );
}

// ── Logo no admin (opcional) ──────────────────────────────────────
add_action( 'wp_head', 'cv_child_logo_css_var', 99 );
function cv_child_logo_css_var() {
    $logo = cv_logo_url();
    echo '<style>:root{--cv-logo-url:url("' . esc_url( $logo ) . '");}</style>' . "\n";
}

// Autocomplete de busca: fica no plugin (CV_Features::ajax_autocomplete),
// que usa a busca unificada CV_Search (Relevanssi). A cópia que existia aqui
// nunca rodava (o plugin responde primeiro) e foi removida na v15.5.0.

// ── PWA: manifest e meta tags ─────────────────────────────────────
add_action( 'wp_head', 'cv_child_pwa_tags', 1 );
function cv_child_pwa_tags() { ?>
<meta name="theme-color" content="#B8700C">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Canção Verdadeira">
<?php }
