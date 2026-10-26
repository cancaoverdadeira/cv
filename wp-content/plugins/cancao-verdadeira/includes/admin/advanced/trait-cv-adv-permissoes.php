<?php
// cancao-verdadeira/includes/admin/advanced/trait-cv-adv-permissoes.php
// Parte do CV_Advanced (trait CV_Adv_Permissoes): papéis customizados (Editor / Gerente / Master), capabilities
// das telas do painel e CV_Advanced::can().
// v2.37.0: saiu de class-cv-advanced.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Advanced::metodo().
// v2.71.0 (23/10/2026): Gerente e Master viram ajudantes do dia a dia — editam TODAS as músicas e
//   posts, importam do YouTube e aprovam cadastros (edit_users do Ultimate Member). Blindagem:
//   só mexem em contas de ouvintes/Editor (nunca em Administrador, Master ou Gerente), não veem
//   essas contas na lista e não trocam papéis. sync_roles() atualiza os papéis já existentes.

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
     *             (v2.71.0: + editar tudo, importar do YouTube, aprovar cadastros)
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
        ), self::caps_ajudante() ) );

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
        ), self::caps_ajudante() ) );
    }

    /** Versão das permissões; mudar aqui faz sync_roles() rodar de novo nos sites já instalados. */
    public static function roles_version() {
        return '4';
    }

    /**
     * v2.71.0: permissões extras de Gerente e Master para o trabalho do dia a dia.
     * Sem promote_users/create_users/delete_users: não trocam papéis nem criam/apagam contas.
     */
    public static function caps_ajudante() {
        return array(
            'cv_import_youtube'      => true,  // tela Importar do YouTube
            'edit_others_posts'      => true,  // músicas e posts de outras pessoas
            'delete_others_posts'    => true,
            'edit_private_posts'     => true,
            'read_private_posts'     => true,
            'delete_private_posts'   => true,
            'manage_categories'      => true,  // categorias e tags do blog
            'moderate_comments'      => true,  // depoimentos dos ouvintes
            'list_users'             => true,  // ver a lista de usuários
            'edit_users'             => true,  // exigido pelo Ultimate Member para aprovar cadastros
        );
    }

    /**
     * v2.71.0: add_role() não mexe em papel que já existe. Esta função acrescenta
     * as permissões novas aos papéis Gerente e Master já criados (uma vez por versão).
     */
    public static function sync_roles() {
        if ( get_option( 'cv_roles_version' ) === self::roles_version() ) { return; }
        if ( ! get_role( 'cv_editor' ) || ! get_role( 'cv_gerente' ) || ! get_role( 'cv_master' ) ) {
            self::register_roles();
        }
        foreach ( array( 'cv_gerente', 'cv_master' ) as $nome ) {
            $role = get_role( $nome );
            if ( ! $role ) { continue; }
            foreach ( self::caps_ajudante() as $cap => $sim ) {
                $role->add_cap( $cap, $sim );
            }
            // O Ultimate Member tem regras próprias por papel (um_role_<papel>_meta): sem elas
            // ele esconde a lista de usuários e barra a aprovação. Só ouvintes e CV Editor.
            $um = get_option( "um_role_{$nome}_meta", array() );
            $um = array_merge( array(
                '_um_after_login'  => 'redirect_admin',
                '_um_after_logout' => 'redirect_home',
                '_um_default_homepage' => 1,
            ), is_array( $um ) ? $um : array(), array(
                '_um_can_access_wpadmin'        => 1,
                '_um_can_view_all'              => 1,
                '_um_can_edit_profile'          => 1,
                '_um_can_edit_everyone'         => 1,
                '_um_can_edit_roles'            => array( 'subscriber', 'cv_editor' ),
                '_um_can_delete_everyone'       => 0,
                '_um_can_access_private_profile'=> 1,
                '_um_status'                    => 'approved',
            ) );
            update_option( "um_role_{$nome}_meta", $um );
        }
        // O UM guarda uma cópia dos dados de cada usuário (um_cache_userdata_<ID>); sem limpar,
        // as contas que já existem continuam com as regras antigas.
        foreach ( get_users( array( 'role__in' => array( 'cv_gerente', 'cv_master' ), 'fields' => 'ID' ) ) as $uid ) {
            delete_option( 'um_cache_userdata_' . (int) $uid );
        }
        update_option( 'cv_roles_version', self::roles_version() );
    }

    /**
     * v2.71.0: contas que só um Administrador pode editar, apagar ou aprovar.
     */
    public static function conta_protegida( $user_id ) {
        $user = get_userdata( (int) $user_id );
        if ( ! $user ) { return false; }
        if ( user_can( $user, 'manage_options' ) ) { return true; }
        return (bool) array_intersect( array( 'administrator', 'cv_master', 'cv_gerente' ), (array) $user->roles );
    }

    /**
     * v2.71.0 (filtro map_meta_cap): quem não é Administrador não edita, apaga nem
     * promove conta protegida (a não ser a própria, para trocar a própria senha).
     */
    public static function blindar_contas( $caps, $cap, $user_id, $args ) {
        if ( ! in_array( $cap, array( 'edit_user', 'delete_user', 'promote_user', 'remove_user' ), true ) ) {
            return $caps;
        }
        $alvo = isset( $args[0] ) ? (int) $args[0] : 0;
        if ( ! $alvo || ( 'edit_user' === $cap && $alvo === (int) $user_id ) ) {
            return $caps;
        }
        if ( user_can( $user_id, 'manage_options' ) ) {
            return $caps;
        }
        if ( self::conta_protegida( $alvo ) ) {
            $caps[] = 'do_not_allow';
        }
        return $caps;
    }

    /**
     * v2.71.0: na lista Usuários, quem não é Administrador não vê as contas protegidas.
     */
    public static function esconder_contas_protegidas( $args ) {
        if ( current_user_can( 'manage_options' ) ) { return $args; }
        $args['role__not_in'] = array_merge(
            isset( $args['role__not_in'] ) ? (array) $args['role__not_in'] : array(),
            array( 'administrator', 'cv_master', 'cv_gerente' )
        );
        return $args;
    }

    /**
     * v2.71.0: as ações em massa do Ultimate Member (aprovar, desativar...) não conferem
     * conta por conta. Tira as contas protegidas da seleção antes de o WordPress agir.
     */
    public static function filtrar_acao_em_massa() {
        if ( current_user_can( 'manage_options' ) || empty( $_REQUEST['users'] ) ) { return; }
        $ids = array_filter( array_map( 'absint', (array) $_REQUEST['users'] ), function( $id ) {
            return ! CV_Advanced::conta_protegida( $id );
        } );
        $_REQUEST['users'] = array_values( $ids );
        if ( isset( $_GET['users'] ) )  { $_GET['users']  = $_REQUEST['users']; }
        if ( isset( $_POST['users'] ) ) { $_POST['users'] = $_REQUEST['users']; }
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
