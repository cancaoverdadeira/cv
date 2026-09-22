<?php
// cancao-verdadeira/includes/sentimentos/class-cv-sentimentos.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Sentimentos (v2.15.0) — relacionamento N:N músicas × sentimentos
// Funções : CRUD de sentimentos, metabox no CPT, página admin, endpoints AJAX
//           filtro público por sentimento, badges nos cards, slug rewrite
// Regras  : sentimentos são OPCIONAIS no cadastro de música
// Autor   : Canção Verdadeira | Gerado: 2026-06-26
// Depende : tabelas cv_sentimentos + cv_musica_sentimentos (DB_VERSION 8)

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Sentimentos {

    const DEFAULTS = array(
        array( 'nome' => 'Sofrência',  'slug' => 'sofrencia',  'icone' => '💔', 'cor' => '#e74c3c', 'ordem' => 1 ),
        array( 'nome' => 'Festa',      'slug' => 'festa',      'icone' => '🎉', 'cor' => '#f39c12', 'ordem' => 2 ),
        array( 'nome' => 'Romance',    'slug' => 'romance',    'icone' => '❤️',  'cor' => '#e91e63', 'ordem' => 3 ),
        array( 'nome' => 'Saudade',    'slug' => 'saudade',    'icone' => '🌧️',  'cor' => '#3498db', 'ordem' => 4 ),
        array( 'nome' => 'Motivação',  'slug' => 'motivacao',  'icone' => '💪', 'cor' => '#27ae60', 'ordem' => 5 ),
        array( 'nome' => 'Churrasco',  'slug' => 'churrasco',  'icone' => '🔥', 'cor' => '#e67e22', 'ordem' => 6 ),
        array( 'nome' => 'Religioso',  'slug' => 'religioso',  'icone' => '🙏', 'cor' => '#9b59b6', 'ordem' => 7 ),
        array( 'nome' => 'Dança',      'slug' => 'danca',      'icone' => '💃', 'cor' => '#1DB954', 'ordem' => 8 ),
    );

    public static function init() {
        add_action( 'add_meta_boxes',                           array( __CLASS__, 'register_metabox' ) );
        add_action( 'save_post_musica',                         array( __CLASS__, 'save_metabox' ) );
        add_action( 'admin_menu',                               array( __CLASS__, 'admin_menu' ) );
        add_action( 'admin_post_cv_save_sentimento',            array( __CLASS__, 'handle_save' ) );
        add_action( 'admin_post_cv_delete_sentimento',          array( __CLASS__, 'handle_delete' ) );
        add_action( 'wp_ajax_cv_musicas_por_sentimento',        array( __CLASS__, 'ajax_musicas' ) );
        add_action( 'wp_ajax_nopriv_cv_musicas_por_sentimento', array( __CLASS__, 'ajax_musicas' ) );
        add_action( 'init',              array( __CLASS__, 'register_rewrite' ) );
        add_filter( 'query_vars',        array( __CLASS__, 'query_vars' ) );
        add_action( 'template_redirect', array( __CLASS__, 'template_redirect' ) );
        add_shortcode( 'cv_sentimentos_filtro', array( __CLASS__, 'shortcode_filtro' ) );
    }

    public static function seed() {
        global $wpdb;
        $table = $wpdb->prefix . 'cv_sentimentos';
        $count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" );
        if ( $count > 0 ) { return; }
        foreach ( self::DEFAULTS as $s ) {
            $wpdb->insert( $table, array(
                'nome'  => $s['nome'],
                'slug'  => $s['slug'],
                'icone' => $s['icone'],
                'cor'   => $s['cor'],
                'ordem' => $s['ordem'],
            ), array( '%s','%s','%s','%s','%d' ) );
        }
    }

    // ── CRUD ─────────────────────────────────────────────────────
    public static function get_all() {
        global $wpdb;
        return $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_sentimentos ORDER BY ordem ASC, nome ASC" );
    }

    public static function get_by_id( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cv_sentimentos WHERE id = %d", absint($id) ) );
    }

    public static function get_by_slug( $slug ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cv_sentimentos WHERE slug = %s", sanitize_title($slug) ) );
    }

    public static function get_for_musica( $musica_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT s.* FROM {$wpdb->prefix}cv_sentimentos s
             INNER JOIN {$wpdb->prefix}cv_musica_sentimentos ms ON ms.sentimento_id = s.id
             WHERE ms.musica_id = %d ORDER BY s.ordem ASC",
            absint($musica_id)
        ) );
    }

    public static function set_for_musica( $musica_id, array $sentimento_ids ) {
        global $wpdb;
        $mid = absint($musica_id);
        $rel = $wpdb->prefix . 'cv_musica_sentimentos';
        $wpdb->delete( $rel, array( 'musica_id' => $mid ), array( '%d' ) );
        foreach ( $sentimento_ids as $sid ) {
            $sid = absint($sid);
            if ( $sid > 0 ) {
                $wpdb->replace( $rel, array( 'musica_id' => $mid, 'sentimento_id' => $sid ), array( '%d','%d' ) );
            }
        }
    }

    public static function get_musicas_by_sentimento( $sentimento_id, $limit = 20, $offset = 0 ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, p.post_title, p.post_name
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->prefix}cv_musica_sentimentos ms ON ms.musica_id = p.ID
             WHERE ms.sentimento_id = %d AND p.post_status = 'publish' AND p.post_type = 'musica'
             ORDER BY p.post_title ASC LIMIT %d OFFSET %d",
            absint($sentimento_id), absint($limit), absint($offset)
        ) );
    }

    // ── Metabox ──────────────────────────────────────────────────
    public static function register_metabox() {
        add_meta_box( 'cv_sentimentos_metabox', '🎭 Sentimentos', array( __CLASS__, 'render_metabox' ), 'musica', 'side', 'default' );
    }

    public static function render_metabox( $post ) {
        $todos     = self::get_all();
        $atuais    = self::get_for_musica( $post->ID );
        $atuais_id = array();
        foreach ( $atuais as $s ) { $atuais_id[] = (int) $s->id; }
        wp_nonce_field( 'cv_sentimentos_metabox', 'cv_sentimentos_nonce' );
        echo '<p style="color:#aaa;font-size:11px;margin-top:0">Opcional — selecione um ou mais sentimentos</p>';
        echo '<div style="display:flex;flex-direction:column;gap:6px">';
        foreach ( $todos as $s ) {
            $checked = in_array( (int)$s->id, $atuais_id, true ) ? 'checked' : '';
            $sid     = (int) $s->id;
            $icone   = esc_html( $s->icone );
            $nome    = esc_html( $s->nome );
            echo '<label style="display:flex;align-items:center;gap:8px;cursor:pointer">';
            echo '<input type="checkbox" name="cv_sentimentos[]" value="' . $sid . '" ' . $checked . '>';
            echo '<span style="font-size:16px">' . $icone . '</span>';
            echo '<span style="color:#e0e0e0">' . $nome . '</span>';
            echo '</label>';
        }
        echo '</div>';
    }

    public static function save_metabox( $post_id ) {
        if ( ! isset( $_POST['cv_sentimentos_nonce'] ) ) { return; }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_sentimentos_nonce'] ) ), 'cv_sentimentos_metabox' ) ) { return; }
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
        $ids = isset( $_POST['cv_sentimentos'] ) ? array_map( 'absint', (array) $_POST['cv_sentimentos'] ) : array();
        self::set_for_musica( $post_id, $ids );
    }

    // ── Admin ─────────────────────────────────────────────────────
    public static function admin_menu() {
        add_submenu_page( 'cv-dashboard', 'Sentimentos', '🎭 Sentimentos', 'manage_options', 'cv-sentimentos', array( __CLASS__, 'render_admin_page' ) );
    }

    public static function render_admin_page() {
        global $wpdb;
        $todos   = self::get_all();
        $editing = null;
        if ( isset( $_GET['action'], $_GET['id'] ) && $_GET['action'] === 'edit' ) {
            $editing = self::get_by_id( absint( $_GET['id'] ) );
        }

        echo '<div class="wrap cv-admin-sentimentos">';
        echo '<h1 class="wp-heading-inline">🎭 Sentimentos</h1>';
        echo '<hr class="wp-header-end">';

        if ( isset( $_GET['msg'] ) ) {
            $msg_txt = ( $_GET['msg'] === 'saved' ) ? 'Sentimento salvo com sucesso.' : 'Sentimento removido.';
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $msg_txt ) . '</p></div>';
        }

        echo '<div style="display:grid;grid-template-columns:1fr 380px;gap:24px;margin-top:20px">';

        // Lista
        echo '<div>';
        echo '<table class="widefat striped">';
        echo '<thead><tr><th>Ícone</th><th>Nome</th><th>Slug</th><th>Cor</th><th>Ordem</th><th>Ações</th></tr></thead>';
        echo '<tbody>';
        foreach ( $todos as $s ) {
            $total      = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_musica_sentimentos WHERE sentimento_id = %d", $s->id ) );
            $musicas_txt = $total . ' música' . ( $total !== 1 ? 's' : '' );
            $cor_safe   = esc_attr( $s->cor );
            $edit_url   = esc_url( admin_url( 'admin.php?page=cv-sentimentos&action=edit&id=' . (int)$s->id ) );
            $delete_url = esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cv_delete_sentimento&id=' . (int)$s->id ), 'cv_delete_sentimento_' . (int)$s->id ) );
            echo '<tr>';
            echo '<td style="font-size:20px;text-align:center">' . esc_html( $s->icone ) . '</td>';
            echo '<td><strong>' . esc_html( $s->nome ) . '</strong><br><small style="color:#aaa">' . esc_html( $musicas_txt ) . '</small></td>';
            echo '<td><code>' . esc_html( $s->slug ) . '</code></td>';
            echo '<td><span style="display:inline-block;width:24px;height:24px;border-radius:50%;background:' . $cor_safe . '"></span></td>';
            echo '<td>' . (int)$s->ordem . '</td>';
            echo '<td>';
            echo '<a href="' . $edit_url . '">Editar</a>';
            echo ' &nbsp;|&nbsp; ';
            echo '<a href="' . $delete_url . '" onclick="return confirm(\'Remover este sentimento?\')" style="color:#cc0000">Remover</a>';
            echo '</td>';
            echo '</tr>';
        }
        if ( empty( $todos ) ) {
            echo '<tr><td colspan="6" style="text-align:center;color:#aaa;padding:20px">Nenhum sentimento cadastrado ainda.</td></tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        // Formulário
        $form_action = esc_url( admin_url('admin-post.php') );
        $cancel_url  = esc_url( admin_url('admin.php?page=cv-sentimentos') );
        $ed_id       = $editing ? (int) $editing->id : 0;
        $ed_nome     = $editing ? esc_attr( $editing->nome )     : '';
        $ed_icone    = $editing ? esc_attr( $editing->icone )    : '🎵';
        $ed_cor      = $editing ? esc_attr( $editing->cor )      : '#1DB954';
        $ed_ordem    = $editing ? (int) $editing->ordem          : 0;
        $ed_desc     = $editing ? esc_textarea( $editing->descricao ) : '';
        $form_title  = $editing ? '✏️ Editar Sentimento' : '➕ Novo Sentimento';
        $btn_txt     = $editing ? '💾 Salvar Alterações'  : '➕ Adicionar Sentimento';

        echo '<div>';
        echo '<div style="background:#1e1e2e;padding:20px;border-radius:8px;border:1px solid #333">';
        echo '<h3 style="margin-top:0;color:#e0e0e0">' . $form_title . '</h3>';
        echo '<form method="post" action="' . $form_action . '">';
        wp_nonce_field( 'cv_save_sentimento', 'cv_sent_nonce' );
        echo '<input type="hidden" name="action" value="cv_save_sentimento">';
        if ( $editing ) {
            echo '<input type="hidden" name="sentimento_id" value="' . $ed_id . '">';
        }
        echo '<table class="form-table" style="color:#e0e0e0">';
        echo '<tr><th>Nome *</th><td><input type="text" name="nome" class="regular-text" required value="' . $ed_nome . '"></td></tr>';
        echo '<tr><th>Ícone (emoji)</th><td><input type="text" name="icone" maxlength="10" style="width:60px;font-size:20px;text-align:center" value="' . $ed_icone . '"></td></tr>';
        echo '<tr><th>Cor</th><td><input type="color" name="cor" value="' . $ed_cor . '"></td></tr>';
        echo '<tr><th>Ordem</th><td><input type="number" name="ordem" min="0" style="width:70px" value="' . $ed_ordem . '"></td></tr>';
        echo '<tr><th>Descrição</th><td><textarea name="descricao" rows="3" class="regular-text">' . $ed_desc . '</textarea></td></tr>';
        echo '</table>';
        echo '<p><button type="submit" class="button button-primary">' . $btn_txt . '</button>';
        if ( $editing ) {
            echo ' <a href="' . $cancel_url . '" class="button">Cancelar</a>';
        }
        echo '</p>';
        echo '</form>';
        echo '</div>';
        echo '</div>';
        echo '</div>'; // grid
        echo '</div>'; // wrap
    }

    public static function handle_save() {
        if ( ! current_user_can('manage_options') ) { wp_die('Sem permissão.'); }
        check_admin_referer( 'cv_save_sentimento', 'cv_sent_nonce' );
        global $wpdb;
        $table = $wpdb->prefix . 'cv_sentimentos';
        $nome  = sanitize_text_field( wp_unslash( $_POST['nome'] ?? '' ) );
        if ( empty($nome) ) { wp_redirect( admin_url('admin.php?page=cv-sentimentos&msg=error') ); exit; }
        $data = array(
            'nome'      => $nome,
            'slug'      => sanitize_title( $nome ),
            'icone'     => sanitize_text_field( wp_unslash( isset($_POST['icone']) ? $_POST['icone'] : '🎵' ) ),
            'cor'       => sanitize_hex_color( isset($_POST['cor']) ? $_POST['cor'] : '#1DB954' ) ?: '#1DB954',
            'ordem'     => absint( isset($_POST['ordem']) ? $_POST['ordem'] : 0 ),
            'descricao' => sanitize_textarea_field( wp_unslash( isset($_POST['descricao']) ? $_POST['descricao'] : '' ) ),
        );
        $fmt = array( '%s','%s','%s','%s','%d','%s' );
        $id  = absint( isset($_POST['sentimento_id']) ? $_POST['sentimento_id'] : 0 );
        if ( $id > 0 ) {
            $wpdb->update( $table, $data, array( 'id' => $id ), $fmt, array('%d') );
        } else {
            $wpdb->insert( $table, $data, $fmt );
        }
        wp_redirect( admin_url('admin.php?page=cv-sentimentos&msg=saved') );
        exit;
    }

    public static function handle_delete() {
        if ( ! current_user_can('manage_options') ) { wp_die('Sem permissão.'); }
        $id = absint( isset($_GET['id']) ? $_GET['id'] : 0 );
        check_admin_referer( 'cv_delete_sentimento_' . $id );
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cv_sentimentos',        array( 'id' => $id ), array('%d') );
        $wpdb->delete( $wpdb->prefix . 'cv_musica_sentimentos', array( 'sentimento_id' => $id ), array('%d') );
        wp_redirect( admin_url('admin.php?page=cv-sentimentos&msg=deleted') );
        exit;
    }

    // ── AJAX público ─────────────────────────────────────────────
    public static function ajax_musicas() {
        $slug = sanitize_title( isset($_POST['slug']) ? $_POST['slug'] : '' );
        $page = max( 1, absint( isset($_POST['page']) ? $_POST['page'] : 1 ) );
        $per  = 20;
        $sent = self::get_by_slug( $slug );
        if ( ! $sent ) { wp_send_json_error('Sentimento não encontrado.'); }
        $musicas = self::get_musicas_by_sentimento( $sent->id, $per, ($page-1)*$per );
        global $wpdb;
        $total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_musica_sentimentos WHERE sentimento_id = %d", $sent->id ) );
        $items = array();
        foreach ( $musicas as $m ) {
            $items[] = array(
                'id'    => $m->ID,
                'title' => $m->post_title,
                'url'   => get_permalink( $m->ID ),
                'cover' => get_the_post_thumbnail_url( $m->ID, 'medium' ) ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            );
        }
        wp_send_json_success( array( 'sentimento' => $sent, 'musicas' => $items, 'total' => $total, 'page' => $page ) );
    }

    // ── Rewrite ───────────────────────────────────────────────────
    public static function register_rewrite() {
        add_rewrite_rule( '^sentimento/([^/]+)/?$', 'index.php?cv_sentimento_slug=$matches[1]', 'top' );
    }

    public static function query_vars( $vars ) {
        $vars[] = 'cv_sentimento_slug';
        return $vars;
    }

    public static function template_redirect() {
        $slug = get_query_var('cv_sentimento_slug');
        if ( ! $slug ) { return; }
        $sent = self::get_by_slug( $slug );
        if ( ! $sent ) { global $wp_query; $wp_query->set_404(); status_header(404); return; }
        self::render_sentimento_page( $sent );
        exit;
    }

    private static function render_sentimento_page( $sent ) {
        $musicas = self::get_musicas_by_sentimento( $sent->id, 40 );
        get_header();
        echo '<div class="cv-sentimento-page" style="max-width:1200px;margin:40px auto;padding:0 20px">';
        echo '<div class="cv-sent-header" style="display:flex;align-items:center;gap:16px;margin-bottom:32px">';
        echo '<span style="font-size:56px">' . esc_html($sent->icone) . '</span>';
        echo '<div>';
        echo '<h1 style="margin:0;color:#e0e0e0">' . esc_html($sent->nome) . '</h1>';
        echo '<p style="margin:4px 0 0;color:#aaa">' . count($musicas) . ' músicas neste sentimento</p>';
        echo '</div></div>';
        if ( ! empty($musicas) ) {
            echo '<div class="cv-grid-musicas" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:16px">';
            foreach ( $musicas as $m ) {
                $cover   = get_the_post_thumbnail_url( $m->ID, 'medium' ) ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg';
                $url     = esc_url( get_permalink($m->ID) );
                $title   = esc_html( $m->post_title );
                $img_url = esc_url( $cover );
                echo '<a href="' . $url . '" class="cv-card-mini" style="display:block;text-decoration:none;background:#1a1a2e;border-radius:8px;overflow:hidden">';
                echo '<img src="' . $img_url . '" alt="' . $title . '" style="width:100%;aspect-ratio:1;object-fit:cover">';
                echo '<div style="padding:10px"><p style="margin:0;color:#e0e0e0;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' . $title . '</p></div>';
                echo '</a>';
            }
            echo '</div>';
        } else {
            echo '<p style="color:#aaa">Nenhuma música cadastrada com este sentimento ainda.</p>';
        }
        echo '</div>';
        get_footer();
    }

    // ── Helper público: badges ────────────────────────────────────
    public static function render_badges( $musica_id, $echo = true ) {
        $sentimentos = self::get_for_musica( $musica_id );
        if ( empty($sentimentos) ) { return ''; }
        $html = '<div class="cv-sentimento-badges" style="display:flex;flex-wrap:wrap;gap:4px;margin-top:6px">';
        foreach ( $sentimentos as $s ) {
            $url  = esc_url( home_url('/sentimento/' . $s->slug . '/') );
            $cor  = esc_attr( $s->cor );
            $html .= '<a href="' . $url . '" class="cv-sent-badge" title="' . esc_attr($s->nome) . '" ';
            $html .= 'style="display:inline-flex;align-items:center;gap:4px;padding:2px 8px;border-radius:20px;font-size:11px;text-decoration:none;background:' . $cor . '22;border:1px solid ' . $cor . ';color:#e0e0e0">';
            $html .= esc_html($s->icone) . ' ' . esc_html($s->nome);
            $html .= '</a>';
        }
        $html .= '</div>';
        if ($echo) { echo $html; }
        return $html;
    }

    // ── Shortcode [cv_sentimentos_filtro] ─────────────────────────
    public static function shortcode_filtro( $atts ) {
        $todos = self::get_all();
        if ( empty($todos) ) { return ''; }
        $html = '<div class="cv-sentimentos-filtro" style="display:flex;flex-wrap:wrap;gap:8px;margin:16px 0">';
        foreach ( $todos as $s ) {
            $url = esc_url( home_url('/sentimento/' . $s->slug . '/') );
            $cor = esc_attr( $s->cor );
            $html .= '<a href="' . $url . '" class="cv-sent-btn" ';
            $html .= 'style="display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:24px;text-decoration:none;background:' . $cor . '22;border:1px solid ' . $cor . ';color:#e0e0e0;font-size:13px">';
            $html .= esc_html($s->icone) . ' ' . esc_html($s->nome);
            $html .= '</a>';
        }
        $html .= '</div>';
        return $html;
    }
}

CV_Sentimentos::init();
