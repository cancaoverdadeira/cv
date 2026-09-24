<?php
// cancao-verdadeira/includes/monetization/class-cv-monetization-pages.php
// Gerado em: 2026-06-14 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Páginas administrativas do módulo de monetização: Banners de Parceiros,
// Loja (produtos), Sorteios e Brindes. Cada página tem formulário de
// cadastro, listagem com ações e integração com a Media Library do WP.
// v2.36.0: o HTML de cada tela foi para includes/monetization/views/{tela}.php
//          e o JS para assets/js/admin-{tela}.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Monetization_Pages {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ), 15 );
    }

    public static function register_menus() {
        add_submenu_page(
            null, 'Banners de Parceiros', '📣 Banners',
            'manage_options', 'cv-banners', array( __CLASS__, 'page_banners' )
        );
        add_submenu_page(
            null, 'Loja', '🛒 Loja',
            'manage_options', 'cv-loja', array( __CLASS__, 'page_loja' )
        );
        add_submenu_page(
            null, 'Sorteios', '🎰 Sorteios',
            'manage_options', 'cv-sorteios', array( __CLASS__, 'page_sorteios' )
        );
        add_submenu_page(
            null, 'Brindes', '🎁 Brindes',
            'manage_options', 'cv-brindes', array( __CLASS__, 'page_brindes' )
        );
    }

    // JS de cada tela: assets/js/admin-{tela}.js, no rodapé (v2.36.0).
    private static function enqueue_js( $tela ) {
        $handle = 'cv-admin-' . $tela;
        wp_enqueue_script( $handle, CV_PLUGIN_URL . 'assets/js/admin-' . $tela . '.js', array( 'jquery' ), CV_VERSION, true );
        wp_add_inline_script( $handle, 'window.cvMon = ' . wp_json_encode( array(
            'nonce'   => wp_create_nonce( 'cv_admin_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) ) . ';', 'before' );
    }

    // ════════════════════════════════════════════════════════════════
    // BANNERS
    // ════════════════════════════════════════════════════════════════
    public static function page_banners() {
        include CV_PLUGIN_DIR . 'includes/monetization/views/banners.php';
        self::enqueue_js( 'banners' );
    }

    // ════════════════════════════════════════════════════════════════
    // LOJA
    // ════════════════════════════════════════════════════════════════
    public static function page_loja() {
        include CV_PLUGIN_DIR . 'includes/monetization/views/loja.php';
        self::enqueue_js( 'loja' );
    }

    // ════════════════════════════════════════════════════════════════
    // SORTEIOS
    // ════════════════════════════════════════════════════════════════
    public static function page_sorteios() {
        include CV_PLUGIN_DIR . 'includes/monetization/views/sorteios.php';
        self::enqueue_js( 'sorteios' );
    }

    // ════════════════════════════════════════════════════════════════
    // BRINDES
    // ════════════════════════════════════════════════════════════════
    public static function page_brindes() {
        include CV_PLUGIN_DIR . 'includes/monetization/views/brindes.php';
        self::enqueue_js( 'brindes' );
    }
}

CV_Monetization_Pages::init();
