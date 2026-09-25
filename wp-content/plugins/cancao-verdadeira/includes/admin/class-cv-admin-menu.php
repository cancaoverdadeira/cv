<?php
// cancao-verdadeira/includes/admin/class-cv-admin-menu.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Menu Administrativo (v2.15.0)
// Funções : Registra menu principal e submenus (Sentimentos, SEO, etc.).
// 24/09/2026: arquivo da antiga Calibração de Métricas apagado (substituída
//           pelo modo lançamento, CV_Launch).
// v2.26.0 : removida a página "Gêneros" (cv-generos) — o site é todo sertanejo.
// Segurança: Operadores nunca veem o menu do WP — apenas este painel.
// Autor   : Canção Verdadeira | Gerado: 2026-06-26

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Admin_Menu {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register' ) );
        add_action( 'admin_menu',      array( __CLASS__, 'remove_third_party_metaboxes' ), 99 );
        add_action( 'add_meta_boxes',  array( __CLASS__, 'remove_third_party_metaboxes' ), 99 );
        add_action( 'admin_head', array( __CLASS__, 'hide_wp_menus_for_operators' ) );
    }

    public static function register() {
        add_menu_page(
            'Cancao Verdadeira', 'Cancao Verdadeira', 'manage_options',
            'cancao-verdadeira', array( 'CV_Page_Dashboard', 'render' ),
            'dashicons-format-audio', 3
        );

        // Menu enxuto — apenas os essenciais de navegação diária
        // Todas as funções secundárias estão no Dashboard → Central de Ações
        add_submenu_page( 'cancao-verdadeira', 'Dashboard',       'Dashboard',
            'manage_options', 'cancao-verdadeira',      array( 'CV_Page_Dashboard', 'render' ) );
        add_submenu_page( 'cancao-verdadeira', 'Gerenciar músicas', '🗂 Músicas',
            'manage_options', 'cv-musicas',             array( 'CV_Page_Musicas', 'render' ) );
        add_submenu_page( 'cancao-verdadeira', 'Ranking',         '🏆 Ranking',
            'manage_options', 'cv-ranking',             array( 'CV_Page_Ranking', 'render' ) );
        add_submenu_page( 'cancao-verdadeira', 'Email Marketing', '📨 Email Marketing',
            'manage_options', 'cv-subscribers',         array( 'CV_Page_Subscribers', 'render' ) );
        add_submenu_page( 'cancao-verdadeira', 'Logs',            '📋 Logs',
            'manage_options', 'cv-logs',                array( 'CV_Page_Logs', 'render' ) );
        // Nova Música e Músicas removidos do menu — acessíveis via Central de Ações no Dashboard
        add_submenu_page( null, 'Nova Música', 'Nova Música',
            'manage_options', 'post-new.php?post_type=musica' );
        add_submenu_page( null, 'Músicas', 'Músicas',
            'manage_options', 'edit.php?post_type=musica' );

        // Páginas acessadas via Central de Ações (não aparecem no menu lateral)
        add_submenu_page( null, 'Analytics',          'Analytics',
            'manage_options', 'cv-analytics',          array( 'CV_Admin_Charts', 'page_analytics' ) );
        add_submenu_page( null, 'Exportações',        'Exportações',
            'manage_options', 'cv-exports',            array( CV_Admin_Exports::get_instance(), 'render_page' ) );
        add_submenu_page( null, 'SEO',                'SEO',
            'manage_options', 'cv-seo',                array( 'CV_Admin_SEO', 'page_seo' ) );
        add_submenu_page( null, 'Importar YouTube',   'Importar YouTube',
            'manage_options', 'cv-youtube-import',     array( 'CV_Page_Youtube_Import', 'render' ) );
        add_submenu_page( null, 'Sentimentos',        'Sentimentos',
            'manage_options', 'cv-sentimentos',        array( 'CV_Admin_Sentimentos', 'render_page' ) );
        add_submenu_page( null, 'Status do Sistema',     'Status do Sistema',
            'manage_options', 'cv-system-status',      array( 'CV_System_Status', 'render_page' ) );
        add_submenu_page( null, 'Banco de Dados',        'Banco de Dados',
            'manage_options', 'cv-banco-dados',        array( 'CV_Banco_Dados',   'render' ) );
        add_submenu_page( null, 'Inteligência Editorial','Inteligência Editorial',
            'manage_options', 'cv-editorial',          array( 'CV_Editorial',     'render' ) );
        add_submenu_page( null, 'Segurança Avançada',    'Segurança Avançada',
            'manage_options', 'cv-seguranca',          array( 'CV_Seguranca',     'render' ) );
        // v2.41.0: "Gerenciar Sentimentos" virou a aba Gerenciar da tela Sentimentos;
        // o endereço antigo é redirecionado em CV_Admin::registrar_antigos().
        add_submenu_page( null, 'Playlists',          'Playlists',
            'manage_options', 'cv-playlists',          array( 'CV_Page_Playlists', 'render' ) );
        add_submenu_page( null, 'Usuários',           'Usuários',
            'manage_options', 'cv-users',              array( 'CV_Page_Users', 'render' ) );
        add_submenu_page( null, 'Aparência',          'Aparência',
            'manage_options', 'cv-appearance',         array( 'CV_Page_Appearance', 'render' ) );
        add_submenu_page( null, 'Configurações',      'Configurações',
            'manage_options', 'cv-settings',           array( 'CV_Page_Settings', 'render' ) );
        add_submenu_page( null, 'Redes Sociais',      'Redes Sociais',
            'manage_options', 'cv-social',             array( 'CV_Social', 'page_social' ) );
        add_submenu_page( null, 'E-mails',            'E-mails',
            'manage_options', 'cv-email',              array( 'CV_Email', 'page_email' ) );
        add_submenu_page( null, 'Permissões',         'Permissões',
            'manage_options', 'cv-roles',              array( 'CV_Page_Roles', 'render' ) );
        add_submenu_page( null, 'Loja',               'Loja',
            'manage_options', 'cv-loja',               array( 'CV_Monetization_Pages', 'page_loja' ) );
        add_submenu_page( null, 'Estoque e Pedidos',  'Estoque e Pedidos',
            'manage_options', 'cv-estoque',            array( 'CV_Estoque_Admin', 'render' ) );
        add_submenu_page( null, 'PIX e Parcerias',    'PIX e Parcerias',
            'manage_options', 'cv-apoio',              array( 'CV_Apoio', 'render' ) );
        add_submenu_page( null, 'Sorteios',           'Sorteios',
            'manage_options', 'cv-sorteios',           array( 'CV_Monetization_Pages', 'page_sorteios' ) );
        add_submenu_page( null, 'Banners',            'Banners',
            'manage_options', 'cv-banners',            array( 'CV_Page_Appearance', 'render' ) );

        // Calibração (gerava plays/avaliações fictícias) foi desativada em
        // 23/09/2026 e substituída pelo modo lançamento (CV_Launch), que só
        // esconde contadores baixos e mostra uma seleção editorial rotulada.
    }

    // ── Remove metaboxes de plugins terceiros na tela de música ──
    public static function remove_third_party_metaboxes() {
        // ── WordPress nativo — campos desnecessários ──────────────
        // SEO e permalink são gerenciados pelo plugin CV
        remove_meta_box( 'slugdiv',              'musica', 'normal' );   // Permalink editável
        remove_meta_box( 'postexcerpt',          'musica', 'normal' );   // Resumo
        remove_meta_box( 'trackbacksdiv',        'musica', 'normal' );   // Trackbacks
        remove_meta_box( 'postcustom',           'musica', 'normal' );   // Campos personalizados nativos
        remove_meta_box( 'commentsdiv',          'musica', 'normal' );   // Comentários
        remove_meta_box( 'commentstatusdiv',     'musica', 'side' );     // Status de comentários
        remove_meta_box( 'authordiv',            'musica', 'normal' );   // Autor do post
        remove_meta_box( 'revisionsdiv',         'musica', 'normal' );   // Revisões
        remove_meta_box( 'pageparentdiv',        'musica', 'side' );     // Página pai

        // ── Rank Math SEO — todas as variantes de ID ─────────────
        foreach ( array( 'normal', 'side', 'advanced' ) as $ctx ) {
            remove_meta_box( 'rank_math_metabox',       'musica', $ctx );
            remove_meta_box( 'rank-math-metabox',       'musica', $ctx );
            remove_meta_box( 'rank_math_primary_term',  'musica', $ctx );
            remove_meta_box( 'rank_math_rich_snippet',  'musica', $ctx );
        }

        // ── Yoast SEO ─────────────────────────────────────────────
        remove_meta_box( 'wpseo_meta',           'musica', 'normal' );
        remove_meta_box( 'wpseo_meta',           'musica', 'side' );
        remove_meta_box( 'wpseo_meta',           'musica', 'advanced' );

        // ── Astra — todas as variantes conhecidas ─────────────────
        foreach ( array( 'normal', 'side', 'advanced' ) as $ctx ) {
            remove_meta_box( 'astra_settings_meta_box',   'musica', $ctx );
            remove_meta_box( 'ast-page-title-visibility', 'musica', $ctx );
            remove_meta_box( 'astra_meta_box',            'musica', $ctx );
            remove_meta_box( 'ast_custom_layouts',        'musica', $ctx );
            remove_meta_box( 'astra-custom-css',          'musica', $ctx );
        }

        // ── Elementor ─────────────────────────────────────────────
        remove_meta_box( 'elementor',            'musica', 'side' );
        remove_meta_box( 'elementor',            'musica', 'normal' );
        remove_meta_box( 'elementor_page_settings', 'musica', 'side' );

        // ── ACF (Advanced Custom Fields) ─────────────────────────
        remove_meta_box( 'acf-field-group',      'musica', 'normal' );
        remove_meta_box( 'acf-field-group',      'musica', 'side' );

        // ── Wordfence ─────────────────────────────────────────────
        remove_meta_box( 'wordfence-ls-2fa',     'musica', 'side' );

        // ── WP Rocket ─────────────────────────────────────────────
        remove_meta_box( 'wpr_cache_options',    'musica', 'side' );
        remove_meta_box( 'wpr_cache_options',    'musica', 'normal' );

        // ── Outros plugins comuns ─────────────────────────────────
        remove_meta_box( 'wpml_languages',       'musica', 'side' );     // WPML
        remove_meta_box( 'icl_div',              'musica', 'normal' );   // WPML
        remove_meta_box( 'sharing_meta',         'musica', 'advanced' ); // Jetpack
        remove_meta_box( 'monsterinsights-metabox', 'musica', 'normal' );
    }

    // ── Oculta menus do WP para operadores (não admins) ──────────
    public static function hide_wp_menus_for_operators() {
        if ( current_user_can('manage_options') ) { return; }
        echo '<style>
        #adminmenu li:not(.menu-top):not([id*="cancao"]) { display:none !important; }
        #adminmenu .wp-submenu li:not([class*="cv"]) { display:none !important; }
        #wp-toolbar { display:none !important; }
        #wpadminbar { display:none !important; }
        #adminmenu .update-plugins { display:none !important; }
        </style>';
    }
}

CV_Admin_Menu::init();
