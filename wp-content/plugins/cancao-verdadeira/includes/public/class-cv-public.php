<?php
// cancao-verdadeira/includes/public/class-cv-public.php
// Gerado em: 2026-06-21 21:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Responsável pelo front-end público: registra cv-cover, enfileira JS/CSS
// e expõe via cvPublic todos os nonces, URLs e configurações que o
// JavaScript precisa. v2.2 CORRIGIDO: (1) nonce de auth adicionado ao
// objeto cvPublic; (2) loginUrl aponta para /login/ nativo do plugin;
// (3) apply_filters('cv_public_js_data') agora é chamado — permite que
// CV_Auth injete registerUrl e dashboardUrl no mesmo objeto JS.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Public {

    public static function init() {
        add_action( 'after_setup_theme',  array( __CLASS__, 'register_image_sizes' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        // v2.27.1: reserva de cv-theme-js só DEPOIS do tema (prioridade 20).
        // Antes rodava junto do enqueue_assets (10) e registrava cv-theme-js
        // vazio, fazendo o WordPress ignorar o cv-theme.js real do tema.
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'fallback_theme_js' ), 25 );
    }

    public static function register_image_sizes() {
        add_image_size( 'cv-cover', 400, 400, true );
    }

    public static function enqueue_assets() {

        wp_enqueue_style(
            'cv-public-style',
            CV_PLUGIN_URL . 'assets/css/cv-public.css',
            array(),
            CV_VERSION
        );

        wp_enqueue_script(
            'cv-public-js',
            CV_PLUGIN_URL . 'assets/js/cv-public.js',
            array( 'jquery' ),
            CV_VERSION,
            true
        );

        $current_music_id = self::get_current_music_id();

        // URL de login: usa página nativa do plugin se existir, senão wp-login.php
        $login_page = get_page_by_path( 'login' );
        $login_url  = $login_page
            ? get_permalink( $login_page->ID )
            : wp_login_url( get_permalink() );

        $register_page = get_page_by_path( 'cadastro' );
        $register_url  = $register_page ? get_permalink( $register_page->ID ) : wp_registration_url();

        $dashboard_page = get_page_by_path( 'minha-area' );
        $dashboard_url  = $dashboard_page ? get_permalink( $dashboard_page->ID ) : home_url( '/minha-area/' );

        // Dados base — podem ser expandidos por outros módulos via filter
        $data = array(
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'restUrl'      => rest_url( 'cv/v1/' ),
            'nonces'       => array(
                'play'         => wp_create_nonce( 'cv_play_nonce' ),
                'favorite'     => wp_create_nonce( 'cv_favorite_nonce' ),
                'rating'       => wp_create_nonce( 'cv_rating_nonce' ),
                'playlist'     => wp_create_nonce( 'cv_playlist_nonce' ),
                'newsletter'   => wp_create_nonce( 'cv_newsletter_nonce' ),
                'autocomplete' => wp_create_nonce( 'cv_autocomplete_nonce' ),
                'notification' => wp_create_nonce( 'cv_notif_nonce' ),
                'lyricComment' => wp_create_nonce( 'cv_lyric_comment_nonce' ),
                'auth'         => wp_create_nonce( 'cv_auth_nonce' ),    // login/cadastro
                'profile'      => wp_create_nonce( 'cv_profile_nonce' ), // edição de perfil
            ),
            'playSeconds'       => (int) CV_PLAY_SECONDS,
            'currentMusicId'    => $current_music_id,
            'currentYoutubeUrl' => $current_music_id
                ? get_post_meta( $current_music_id, '_cv_youtube_url', true )
                : '',
            'isLoggedIn'    => is_user_logged_in(),
            'loginUrl'      => $login_url,
            'registerUrl'   => $register_url,
            'dashboardUrl'  => $dashboard_url,
            'defaultCover'  => CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            'i18n'          => array(
                'playError'       => 'Erro ao registrar reprodução.',
                'loginRequired'   => 'Faça login para usar esta função.',
                'favoriteAdded'   => 'Adicionado aos favoritos ❤',
                'favoriteRemoved' => 'Removido dos favoritos',
                'ratingSuccess'   => 'Avaliação salva!',
                'playlistAdded'   => 'Adicionado à playlist!',
                'newsletterOk'    => 'Inscrição realizada! 🎵',
                'newsletterError' => 'Erro ao assinar. Tente novamente.',
                'error'           => 'Ocorreu um erro. Tente novamente.',
            ),
        );

        // Permite que outros módulos (CV_Auth, etc.) adicionem dados ao objeto JS
        $data = apply_filters( 'cv_public_js_data', $data );

        wp_localize_script( 'cv-public-js', 'cvPublic', $data );
    }

    // Sem o tema filho, cria um cv-theme-js vazio para quem depende dele.
    public static function fallback_theme_js() {
        if ( ! wp_script_is( 'cv-theme-js', 'registered' ) ) {
            wp_register_script( 'cv-theme-js', false, array( 'cv-public-js' ), CV_VERSION, true );
            wp_enqueue_script( 'cv-theme-js' );
        }
    }

    private static function get_current_music_id() {
        if ( is_singular( 'musica' ) ) {
            return get_the_ID();
        }
        $id = get_query_var( 'cv_music_id', 0 );
        if ( $id && 'musica' === get_post_type( $id ) ) {
            return (int) $id;
        }
        return 0;
    }
}

CV_Public::init();
