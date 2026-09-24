<?php
// cancao-verdadeira/includes/admin/advanced/trait-cv-adv-permissoes.php
// Parte do CV_Advanced (trait CV_Adv_Permissoes): papéis customizados (Editor / Gerente / Master), capabilities
// das telas do painel e CV_Advanced::can().
// v2.37.0: saiu de class-cv-advanced.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Advanced::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Adv_Permissoes {

    // ════════════════════════════════════════════════════════════════
    // 4. NÍVEIS DE PERMISSÃO
    // ════════════════════════════════════════════════════════════════

    /**
     * Registra os três roles customizados do Canção Verdadeira.
     * Chamado uma vez na ativação do plugin.
     *
     * Editor    — pode criar e editar músicas, mas não excluir nem ver configs
     * Gerente   — tudo do Editor + excluir músicas, gerenciar playlists e usuários
     * Master    — tudo do Gerente + configurações e logs (sem ser admin do WP)
     */
    public static function register_roles() {
        // Capabilities base do WordPress que vamos usar
        $base_caps = array(
            'read'                   => true,
            'upload_files'           => true,
        );

        // ── Editor ────────────────────────────────────────────────
        add_role( 'cv_editor', 'CV Editor', array_merge( $base_caps, array(
            'cv_edit_musicas'        => true,
            'cv_create_musicas'      => true,
            'cv_read_musicas'        => true,
            // Capabilities nativas para o CPT musica
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
        ) ) );

        // ── Gerente ────────────────────────────────────────────────
        add_role( 'cv_gerente', 'CV Gerente', array_merge( $base_caps, array(
            'cv_edit_musicas'        => true,
            'cv_create_musicas'      => true,
            'cv_delete_musicas'      => true,
            'cv_read_musicas'        => true,
            'cv_manage_playlists'    => true,
            'cv_manage_users'        => true,
            'cv_view_ranking'        => true,
            'cv_view_subscribers'    => true,
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_published_posts' => true,
            'upload_files'           => true,
        ) ) );

        // ── Master ─────────────────────────────────────────────────
        add_role( 'cv_master', 'CV Master', array_merge( $base_caps, array(
            'cv_edit_musicas'        => true,
            'cv_create_musicas'      => true,
            'cv_delete_musicas'      => true,
            'cv_read_musicas'        => true,
            'cv_manage_playlists'    => true,
            'cv_manage_users'        => true,
            'cv_manage_settings'     => true,
            'cv_view_logs'           => true,
            'cv_view_ranking'        => true,
            'cv_view_subscribers'    => true,
            'cv_recalculate_ranking' => true,
            'edit_posts'             => true,
            'edit_published_posts'   => true,
            'publish_posts'          => true,
            'delete_posts'           => true,
            'delete_published_posts' => true,
            'upload_files'           => true,
            'manage_options'         => false, // não é admin WP
        ) ) );
    }

    /**
     * Remove os roles customizados (chamado na desativação do plugin).
     */
    public static function remove_roles() {
        remove_role( 'cv_editor' );
        remove_role( 'cv_gerente' );
        remove_role( 'cv_master' );
    }

    /**
     * Ajusta as capabilities exigidas nos submenus do painel
     * para que Gerentes e Masters também possam acessá-los.
     */
    public static function adjust_menu_caps() {
        global $submenu;
        if ( ! isset( $submenu['cancao-verdadeira'] ) ) { return; }

        foreach ( $submenu['cancao-verdadeira'] as &$item ) {
            // Gerente e Master podem acessar tudo exceto Configurações
            if ( isset( $item[1] ) && $item[1] === 'manage_options' ) {
                // Configurações só para admin e master
                if ( isset( $item[2] ) && $item[2] === 'cv-settings' ) {
                    $item[1] = current_user_can( 'manage_options' ) || current_user_can( 'cv_manage_settings' )
                               ? 'read' : 'manage_options';
                } else {
                    // Demais páginas: gerente e master podem acessar
                    if ( current_user_can( 'cv_gerente' ) || current_user_can( 'cv_master' ) ) {
                        $item[1] = 'read';
                    }
                }
            }
        }
    }

    /**
     * Verifica se o usuário atual pode executar uma ação do plugin.
     * Uso: CV_Advanced::can('edit_musicas')
     *
     * @param string $action  Ação sem prefixo 'cv_' (ex: 'edit_musicas')
     */
    public static function can( $action ) {
        return current_user_can( 'manage_options' )
            || current_user_can( 'cv_' . $action );
    }
}
