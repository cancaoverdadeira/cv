<?php
// cancao-verdadeira/includes/admin/class-cv-admin-menu.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Menu Administrativo (v2.15.0)
// Funções : Registra menu principal e submenus. Inclui Calibração de Métricas
//           (exclusivo admin ID 3) e Sentimentos. Gênero agora tem página
//           própria dark em vez de apontar para o nativo do WordPress.
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
        add_submenu_page( null, 'Publicação Rápida',  'Publicação Rápida',
            'manage_options', 'cv-publicacao-rapida',  array( 'CV_Publicacao_Rapida', 'render_page' ) );
        add_submenu_page( null, 'Gêneros',            'Gêneros',
            'manage_options', 'cv-generos',            array( __CLASS__, 'page_generos' ) );
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
        add_submenu_page( null, 'Gerenciar Sentimentos', 'Gerenciar Sentimentos',
            'manage_options', 'cv-sentimentos-crud',   array( 'CV_Sentimentos', 'render_admin_page' ) );
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
        add_submenu_page( null, 'Sorteios',           'Sorteios',
            'manage_options', 'cv-sorteios',           array( 'CV_Monetization_Pages', 'page_sorteios' ) );
        add_submenu_page( null, 'Banners',            'Banners',
            'manage_options', 'cv-banners',            array( 'CV_Page_Appearance', 'render' ) );

        // Calibração (gerava plays/avaliações fictícias) foi desativada em
        // 23/09/2026 e substituída pelo modo lançamento (CV_Launch), que só
        // esconde contadores baixos e mostra uma seleção editorial rotulada.
    }

    // ── Página de Gêneros dark (substitui edit-tags.php nativo) ──
    public static function page_generos() {
        global $wpdb;

        // Handle ações
        if ( isset($_POST['cv_genero_action']) && check_admin_referer('cv_genero_nonce','cv_gnonce') ) {
            $action = sanitize_text_field($_POST['cv_genero_action']);
            $nome   = sanitize_text_field(wp_unslash($_POST['cv_genero_nome'] ?? ''));
            $desc   = sanitize_textarea_field(wp_unslash($_POST['cv_genero_desc'] ?? ''));
            $id     = absint($_POST['cv_genero_id'] ?? 0);

            if ( $nome ) {
                if ( $action === 'add' ) {
                    wp_insert_term( $nome, 'cv_genre', array('description'=>$desc) );
                } elseif ( $action === 'edit' && $id ) {
                    wp_update_term( $id, 'cv_genre', array('name'=>$nome,'description'=>$desc) );
                }
            }
            if ( $action === 'delete' && $id ) {
                wp_delete_term( $id, 'cv_genre' );
            }
        }

        $generos = get_terms( array('taxonomy'=>'cv_genre','hide_empty'=>false,'orderby'=>'name','order'=>'ASC') );
        $editing = null;
        if ( isset($_GET['action'],$_GET['tag_id']) && $_GET['action']==='edit' ) {
            $editing = get_term( absint($_GET['tag_id']), 'cv_genre' );
        }

        echo '<div class="wrap" style="background:#FFFFFF;min-height:100vh;padding:20px">';
        echo '<h1 style="color:#3B2418;margin-bottom:4px">🎸 Gêneros Musicais</h1>';
        echo '<p style="color:#6B4C3B;margin-bottom:24px">' . count((array)$generos) . ' gênero(s) cadastrado(s)</p>';

        if ( isset($_GET['msg']) ) {
            echo '<div class="notice notice-success is-dismissible"><p>Operação realizada com sucesso.</p></div>';
        }

        echo '<div style="display:grid;grid-template-columns:1fr 360px;gap:24px">';

        // Tabela
        echo '<div>';
        echo '<table class="widefat" style="background:#F8F0E4;color:#3B2418;border:1px solid #EADBC6;border-radius:8px;overflow:hidden">';
        echo '<thead style="background:#F8F0E4"><tr>';
        echo '<th style="color:#6B4C3B;padding:12px">Gênero</th>';
        echo '<th style="color:#6B4C3B;padding:12px">Descrição</th>';
        echo '<th style="color:#6B4C3B;padding:12px;text-align:center">Músicas</th>';
        echo '<th style="color:#6B4C3B;padding:12px">Ações</th>';
        echo '</tr></thead><tbody>';

        if ( ! empty($generos) && ! is_wp_error($generos) ) {
            foreach ( $generos as $g ) {
                $count    = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->term_relationships} tr
                     INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
                     WHERE tt.term_id = %d AND tt.taxonomy = 'cv_genre'", $g->term_id
                ));
                $edit_url = esc_url(add_query_arg(array('page'=>'cv-generos','action'=>'edit','tag_id'=>$g->term_id), admin_url('admin.php')));
                echo '<tr style="border-bottom:1px solid #EADBC6">';
                echo '<td style="padding:12px;font-weight:600">' . esc_html($g->name) . '</td>';
                echo '<td style="padding:12px;color:#6B4C3B;font-size:12px">' . esc_html(mb_substr($g->description,0,60)) . '</td>';
                echo '<td style="padding:12px;text-align:center"><span style="background:#1DB95422;border:1px solid #1DB954;color:#137B38;border-radius:12px;padding:2px 10px;font-size:12px">' . $count . '</span></td>';
                echo '<td style="padding:12px">';
                echo '<a href="' . $edit_url . '" style="color:#137B38;text-decoration:none;margin-right:12px">✏️ Editar</a>';
                echo '<form method="post" style="display:inline" onsubmit="return confirm(\'Remover este gênero?\')">';
                wp_nonce_field('cv_genero_nonce','cv_gnonce');
                echo '<input type="hidden" name="cv_genero_action" value="delete">';
                echo '<input type="hidden" name="cv_genero_id" value="' . (int)$g->term_id . '">';
                echo '<button type="submit" style="background:none;border:none;color:#D62C1A;cursor:pointer;font-size:13px">🗑️ Remover</button>';
                echo '</form>';
                echo '</td></tr>';
            }
        } else {
            echo '<tr><td colspan="4" style="padding:40px;text-align:center;color:#6B4C3B">Nenhum gênero cadastrado ainda.</td></tr>';
        }
        echo '</tbody></table></div>';

        // Formulário
        $is_edit  = $editing && ! is_wp_error($editing);
        $f_nome   = $is_edit ? esc_attr($editing->name)        : '';
        $f_desc   = $is_edit ? esc_textarea($editing->description) : '';
        $f_id     = $is_edit ? (int)$editing->term_id          : 0;
        $f_action = $is_edit ? 'edit'                          : 'add';
        $f_title  = $is_edit ? '✏️ Editar Gênero'              : '➕ Novo Gênero';
        $f_btn    = $is_edit ? '💾 Salvar'                     : '➕ Adicionar';
        $cancel   = esc_url(admin_url('admin.php?page=cv-generos'));

        echo '<div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:8px;padding:20px">';
        echo '<h3 style="color:#3B2418;margin-top:0">' . $f_title . '</h3>';
        echo '<form method="post">';
        wp_nonce_field('cv_genero_nonce','cv_gnonce');
        echo '<input type="hidden" name="cv_genero_action" value="' . $f_action . '">';
        if ($f_id) { echo '<input type="hidden" name="cv_genero_id" value="' . $f_id . '">'; }

        echo '<div style="margin-bottom:14px">';
        echo '<label style="display:block;color:#6B4C3B;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Nome *</label>';
        echo '<input type="text" name="cv_genero_nome" value="' . $f_nome . '" required style="width:100%;background:#FFFFFF;border:1px solid #EADBC6;border-radius:6px;color:#3B2418;padding:9px 12px;box-sizing:border-box;font-size:14px">';
        echo '</div>';

        echo '<div style="margin-bottom:14px">';
        echo '<label style="display:block;color:#6B4C3B;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:5px">Descrição</label>';
        echo '<textarea name="cv_genero_desc" rows="3" style="width:100%;background:#FFFFFF;border:1px solid #EADBC6;border-radius:6px;color:#3B2418;padding:9px 12px;box-sizing:border-box;font-size:14px;resize:vertical">' . $f_desc . '</textarea>';
        echo '</div>';

        echo '<button type="submit" style="background:#1DB954;border:none;color:#3B2418;padding:9px 20px;border-radius:6px;font-size:14px;cursor:pointer;font-weight:600">' . $f_btn . '</button>';
        if ($is_edit) { echo ' <a href="' . $cancel . '" style="margin-left:10px;color:#6B4C3B;text-decoration:none">Cancelar</a>'; }
        echo '</form></div>';
        echo '</div>'; // grid
        echo '</div>'; // wrap
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
