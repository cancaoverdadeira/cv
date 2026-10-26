<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-playlists.php
// Gerado em: 2025-06-01 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gerencia playlists dos usuários: criar, renomear, excluir,
// adicionar/remover músicas e reordenar por drag-and-drop.
// Todas as ações requerem usuário logado e nonce AJAX válido.
// v2.52.0: cv_playlist_visibilidade — o dono deixa a playlist 🔒 privada ou
// 🌐 pública (pública aparece no perfil público e qualquer pessoa pode tocar).
// v2.67.0 (14/10/2026): página "Playlists da Comunidade" (/playlists-da-comunidade/,
// modelo templates/page-playlists-comunidade.php do tema) com as públicas de todos
// (get_public_playlists). A página é criada uma vez só (opção cv_comunidade_pagina);
// se for para a lixeira, não é recriada. Mexeu numa playlist → limpa o cache dela.
// v2.67.1: cada playlist pública traz também os nomes das 3 primeiras músicas (musicas).
// v2.71.1 (24/10/2026): o mesmo usuário não cria nem renomeia para um nome que já usa
// ("Família" e "familia" contam como iguais: sem acento e sem maiúscula). Antes a Elen
// ficou com duas "Família" e, no modal, colocava as músicas sempre na mesma.
// v2.72.0 (24/10/2026): o Administrador (manage_options) exclui, renomeia e mexe nas
// músicas da playlist de qualquer usuário (antes só nas dele: "Erro ao excluir").

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Playlists {

    public static function init() {
        add_action( 'wp_ajax_cv_playlist_create',       array( __CLASS__, 'create' ) );
        add_action( 'wp_ajax_cv_playlist_delete',       array( __CLASS__, 'delete_playlist' ) );
        add_action( 'wp_ajax_cv_playlist_rename',       array( __CLASS__, 'rename' ) );
        add_action( 'wp_ajax_cv_playlist_add_music',    array( __CLASS__, 'add_music' ) );
        add_action( 'wp_ajax_cv_playlist_remove_music', array( __CLASS__, 'remove_music' ) );
        add_action( 'wp_ajax_cv_playlist_reorder',      array( __CLASS__, 'reorder' ) );
        add_action( 'wp_ajax_cv_playlist_list',         array( __CLASS__, 'list_playlists' ) );
        add_action( 'wp_ajax_cv_playlist_visibilidade', array( __CLASS__, 'visibilidade' ) );
        add_action( 'wp_loaded',                        array( __CLASS__, 'criar_pagina_comunidade' ) );
    }

    const SLUG_COMUNIDADE     = 'playlists-da-comunidade';
    const TEMPLATE_COMUNIDADE = 'templates/page-playlists-comunidade.php';
    const VERSAO_COMUNIDADE   = '1';

    /** Cria a página "Playlists da Comunidade" uma única vez (não recria se foi apagada). */
    public static function criar_pagina_comunidade() {
        if ( get_option( 'cv_comunidade_pagina' ) === self::VERSAO_COMUNIDADE ) { return; }
        $existe = get_posts( array(
            'post_type'   => 'page',
            'name'        => self::SLUG_COMUNIDADE,
            'post_status' => array( 'publish', 'draft', 'private', 'trash', 'pending', 'future' ),
            'numberposts' => 1,
            'fields'      => 'ids',
        ) );
        if ( empty( $existe ) ) {
            $id = wp_insert_post( array(
                'post_type'    => 'page',
                'post_title'   => 'Playlists da Comunidade',
                'post_name'    => self::SLUG_COMUNIDADE,
                'post_status'  => 'publish',
                'post_content' => '',
            ) );
            if ( $id && ! is_wp_error( $id ) ) { update_post_meta( $id, '_wp_page_template', self::TEMPLATE_COMUNIDADE ); }
        }
        update_option( 'cv_comunidade_pagina', self::VERSAO_COMUNIDADE, false );
    }

    public static function url_comunidade() {
        $p = get_page_by_path( self::SLUG_COMUNIDADE );
        return $p ? get_permalink( $p->ID ) : home_url( '/' . self::SLUG_COMUNIDADE . '/' );
    }

    /** Uma playlist mudou: a página da comunidade sai do cache do WP Rocket. */
    private static function limpar_cache_comunidade() {
        if ( ! class_exists( 'CV_Litespeed' ) ) { return; }
        $p = get_page_by_path( self::SLUG_COMUNIDADE );
        if ( $p ) { CV_Litespeed::limpar_post( $p->ID ); }
    }

    /**
     * Playlists públicas de todos, mais novas primeiro. Só entram as que têm
     * pelo menos 1 música publicada e cujo dono ainda existe.
     * Cada uma traz: id, name, user_id, count, autor, perfil, capa e musicas
     * (nomes das 3 primeiras músicas publicadas, na ordem da playlist).
     */
    public static function get_public_playlists( $por_pagina = 24, $pagina = 1 ) {
        global $wpdb;
        $por_pagina = max( 1, (int) $por_pagina );
        $offset     = max( 0, ( (int) $pagina - 1 ) * $por_pagina );

        $lista = $wpdb->get_results( $wpdb->prepare(
            "SELECT pl.id, pl.name, pl.user_id, COUNT(pi.music_id) AS count
             FROM {$wpdb->prefix}cv_playlists pl
             INNER JOIN {$wpdb->users} u ON u.ID = pl.user_id
             INNER JOIN {$wpdb->prefix}cv_playlist_items pi ON pi.playlist_id = pl.id
             INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id AND p.post_status = 'publish'
             WHERE pl.is_public = 1
             GROUP BY pl.id, pl.name, pl.user_id, pl.created_at
             ORDER BY pl.created_at DESC, pl.id DESC
             LIMIT %d OFFSET %d",
            $por_pagina, $offset
        ) );

        foreach ( $lista as $pl ) {
            $pl->count  = (int) $pl->count;
            $dono       = get_userdata( $pl->user_id );
            $pl->autor  = $dono ? $dono->display_name : '';
            $pl->perfil = function_exists( 'um_user_profile_url' ) ? um_user_profile_url( $pl->user_id ) : '';
            $pl->capa   = self::capa( $pl->id );
            $pl->musicas = $wpdb->get_col( $wpdb->prepare(
                "SELECT p.post_title FROM {$wpdb->prefix}cv_playlist_items pi
                 INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id AND p.post_status = 'publish'
                 WHERE pi.playlist_id = %d ORDER BY pi.sort_order ASC LIMIT 3",
                $pl->id
            ) );
        }
        return $lista;
    }

    /** Total de playlists que aparecem na comunidade (para a paginação). */
    public static function count_public_playlists() {
        global $wpdb;
        return (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT pl.id)
             FROM {$wpdb->prefix}cv_playlists pl
             INNER JOIN {$wpdb->users} u ON u.ID = pl.user_id
             INNER JOIN {$wpdb->prefix}cv_playlist_items pi ON pi.playlist_id = pl.id
             INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id AND p.post_status = 'publish'
             WHERE pl.is_public = 1"
        );
    }

    /** Capa da playlist: a imagem da 1ª música publicada (ou a do YouTube). */
    private static function capa( $playlist_id ) {
        global $wpdb;
        $music_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT pi.music_id FROM {$wpdb->prefix}cv_playlist_items pi
             INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id AND p.post_status = 'publish'
             WHERE pi.playlist_id = %d ORDER BY pi.sort_order ASC LIMIT 1",
            $playlist_id
        ) );
        if ( ! $music_id ) { return ''; }
        $capa = get_the_post_thumbnail_url( $music_id, 'cv-cover' );
        if ( ! $capa && class_exists( 'CV_Fields' ) ) {
            $yt = CV_Fields::youtube_id( (string) get_post_meta( $music_id, CV_Fields::YOUTUBE_URL, true ) );
            if ( $yt ) { $capa = "https://img.youtube.com/vi/{$yt}/mqdefault.jpg"; }
        }
        return $capa ? $capa : '';
    }

    /** Normaliza o nome para comparar: sem acento, minúsculo e com espaços simples. */
    public static function nome_comparavel( $nome ) {
        $nome = remove_accents( trim( preg_replace( '/\s+/u', ' ', (string) $nome ) ) );
        return function_exists( 'mb_strtolower' ) ? mb_strtolower( $nome, 'UTF-8' ) : strtolower( $nome );
    }

    /** v2.71.1: id da playlist do usuário com esse nome (ignorando $ignorar_id), ou 0. */
    public static function playlist_com_nome( $user_id, $nome, $ignorar_id = 0 ) {
        global $wpdb;
        $alvo = self::nome_comparavel( $nome );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT id, name FROM {$wpdb->prefix}cv_playlists WHERE user_id = %d",
            $user_id
        ) );
        foreach ( $rows as $r ) {
            if ( (int) $r->id !== (int) $ignorar_id && self::nome_comparavel( $r->name ) === $alvo ) {
                return (int) $r->id;
            }
        }
        return 0;
    }

    private static function msg_nome_repetido( $nome ) {
        return 'Você já tem uma playlist chamada "' . $nome . '". Use essa ou escolha outro nome.';
    }

    private static function require_login() {
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.', 'require_login' => true ) );
        }
    }

    public static function create() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $name        = sanitize_text_field( $_POST['name']        ?? '' );
        $description = sanitize_textarea_field( $_POST['description'] ?? '' );
        $is_public   = absint( $_POST['is_public']   ?? 0 );
        $is_featured = absint( $_POST['is_featured'] ?? 0 );
        $cover_url   = esc_url_raw( $_POST['cover_url'] ?? '' );
        $user_id     = get_current_user_id();

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Nome da playlist é obrigatório.' ) );
        }
        if ( self::playlist_com_nome( $user_id, $name ) ) {
            wp_send_json_error( array( 'message' => self::msg_nome_repetido( $name ) ) );
        }

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'cv_playlists',
            array(
                'user_id'     => $user_id,
                'name'        => $name,
                'description' => $description,
                'is_public'   => $is_public,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s' )
        );

        $new_id = $wpdb->insert_id;

        // Salva capa e destaque como meta da playlist (em wp_options com chave única)
        if ( $cover_url ) {
            update_option( 'cv_playlist_cover_' . $new_id, $cover_url );
        }
        if ( $is_featured ) {
            // Guarda lista de playlists em destaque
            $featured = get_option( 'cv_featured_playlists', array() );
            if ( ! is_array( $featured ) ) { $featured = array(); }
            $featured[] = $new_id;
            update_option( 'cv_featured_playlists', array_unique( $featured ) );
        }

        // Dispara hook para o sistema de logs registrar a criação
        do_action( 'cv_playlist_created', $new_id, $name );

        wp_send_json_success( array(
            'id'   => $new_id,
            'name' => $name,
        ) );
    }

    public static function delete_playlist() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $wpdb->delete( $wpdb->prefix . 'cv_playlist_items', array( 'playlist_id' => $playlist_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cv_playlists',      array( 'id' => $playlist_id ),          array( '%d' ) );
        self::limpar_cache_comunidade();

        wp_send_json_success();
    }

    public static function rename() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $name        = sanitize_text_field( $_POST['name'] ?? '' );
        $user_id     = get_current_user_id();
        $dono        = self::dono( $playlist_id );

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        if ( '' === $name ) {
            wp_send_json_error( array( 'message' => 'Nome da playlist é obrigatório.' ) );
        }
        if ( self::playlist_com_nome( $dono, $name, $playlist_id ) ) {
            wp_send_json_error( array( 'message' => self::msg_nome_repetido( $name ) ) );
        }

        global $wpdb;

        $wpdb->update(
            $wpdb->prefix . 'cv_playlists',
            array( 'name' => $name ),
            array( 'id'   => $playlist_id ),
            array( '%s' ),
            array( '%d' )
        );
        self::limpar_cache_comunidade();

        wp_send_json_success( array( 'name' => $name ) );
    }

    public static function add_music() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $music_id    = absint( $_POST['music_id']    ?? 0 );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        if ( 'musica' !== get_post_type( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'Música inválida.' ) );
        }

        global $wpdb;

        // Verifica se já está na playlist
        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_playlist_items WHERE playlist_id = %d AND music_id = %d",
            $playlist_id, $music_id
        ) );

        if ( $exists ) {
            wp_send_json_error( array( 'message' => 'Música já está nesta playlist.' ) );
        }

        $max_order = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT MAX(sort_order) FROM {$wpdb->prefix}cv_playlist_items WHERE playlist_id = %d",
            $playlist_id
        ) );

        $wpdb->insert(
            $wpdb->prefix . 'cv_playlist_items',
            array(
                'playlist_id' => $playlist_id,
                'music_id'    => $music_id,
                'sort_order'  => $max_order + 1,
                'added_at'    => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%d', '%s' )
        );
        self::limpar_cache_comunidade();

        wp_send_json_success();
    }

    public static function remove_music() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $music_id    = absint( $_POST['music_id']    ?? 0 );
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $wpdb->delete(
            $wpdb->prefix . 'cv_playlist_items',
            array( 'playlist_id' => $playlist_id, 'music_id' => $music_id ),
            array( '%d', '%d' )
        );
        self::limpar_cache_comunidade();

        wp_send_json_success();
    }

    public static function reorder() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $order       = $_POST['order'] ?? array();
        $user_id     = get_current_user_id();

        if ( ! self::owns_playlist( $playlist_id, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        foreach ( (array) $order as $index => $music_id ) {
            $music_id = absint( $music_id );
            if ( ! $music_id ) { continue; }

            $wpdb->update(
                $wpdb->prefix . 'cv_playlist_items',
                array( 'sort_order' => $index ),
                array( 'playlist_id' => $playlist_id, 'music_id' => $music_id ),
                array( '%d' ),
                array( '%d', '%d' )
            );
        }

        wp_send_json_success();
    }

    public static function list_playlists() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();

        $user_id = get_current_user_id();

        wp_send_json_success( self::get_user_playlists( $user_id ) );
    }

    /**
     * Retorna playlists de um usuário com músicas.
     */
    public static function get_user_playlists( $user_id ) {
        global $wpdb;

        $playlists = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_playlists WHERE user_id = %d ORDER BY created_at DESC",
            $user_id
        ) );

        foreach ( $playlists as $playlist ) {
            $items = $wpdb->get_results( $wpdb->prepare(
                "SELECT pi.music_id, p.post_title
                 FROM {$wpdb->prefix}cv_playlist_items pi
                 INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id
                 WHERE pi.playlist_id = %d AND p.post_status = 'publish'
                 ORDER BY pi.sort_order ASC",
                $playlist->id
            ) );

            $playlist->items = $items;
            $playlist->count = count( $items );
        }

        return $playlists;
    }

    /** Liga/desliga "pública" (só o dono). Devolve o novo estado. */
    public static function visibilidade() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );
        self::require_login();
        $id      = absint( $_POST['playlist_id'] ?? 0 );
        $publica = absint( $_POST['publica'] ?? 0 ) ? 1 : 0;
        if ( ! $id || ! self::owns_playlist( $id, get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => 'Playlist não encontrada.' ) );
        }
        global $wpdb;
        $wpdb->update( $wpdb->prefix . 'cv_playlists', array( 'is_public' => $publica ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
        self::limpar_cache_comunidade();
        wp_send_json_success( array(
            'publica' => $publica,
            'message' => $publica
                ? '🌐 Playlist pública: aparece no seu perfil e em Playlists da Comunidade.'
                : '🔒 Playlist privada: só você vê e ouve.',
        ) );
    }

    /** Id do dono da playlist (0 se não existe). */
    private static function dono( $playlist_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}cv_playlists WHERE id = %d",
            $playlist_id
        ) );
    }

    /** Dono da playlist ou, desde a v2.72.0, o Administrador (qualquer playlist existente). */
    private static function owns_playlist( $playlist_id, $user_id ) {
        global $wpdb;

        if ( current_user_can( 'manage_options' ) ) {
            return (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}cv_playlists WHERE id = %d",
                $playlist_id
            ) );
        }

        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_playlists WHERE id = %d AND user_id = %d",
            $playlist_id,
            $user_id
        ) );
    }
}

CV_Playlists::init();
