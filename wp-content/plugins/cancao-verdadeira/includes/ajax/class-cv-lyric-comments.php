<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-lyric-comments.php
// Gerado em: 2025-06-02 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Comentarios por trecho da letra: usuario seleciona um verso e comenta.
// Armazena em tabela cv_lyric_comments com referencia ao post e ao trecho.
// Endpoints: salvar, listar por musica, excluir (proprio usuario ou admin).
// Criada via dbDelta na ativacao do plugin (v1.3).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Lyric_Comments {

    public static function init() {
        add_action( 'wp_ajax_cv_save_lyric_comment',   array( __CLASS__, 'save' ) );
        add_action( 'wp_ajax_cv_get_lyric_comments',   array( __CLASS__, 'get_comments' ) );
        add_action( 'wp_ajax_nopriv_cv_get_lyric_comments', array( __CLASS__, 'get_comments' ) );
        add_action( 'wp_ajax_cv_delete_lyric_comment', array( __CLASS__, 'delete_comment' ) );
    }

    public static function save() {
        check_ajax_referer( 'cv_lyric_comment_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário para comentar.', 'require_login' => true ) );
        }

        $music_id = absint( $_POST['music_id'] ?? 0 );
        $excerpt  = sanitize_text_field( $_POST['excerpt']  ?? '' );
        $comment  = sanitize_textarea_field( $_POST['comment'] ?? '' );
        $user_id  = get_current_user_id();

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'Música inválida.' ) );
        }
        if ( empty( $excerpt ) || empty( $comment ) ) {
            wp_send_json_error( array( 'message' => 'Selecione um trecho e escreva um comentário.' ) );
        }
        if ( mb_strlen( $comment ) > 500 ) {
            wp_send_json_error( array( 'message' => 'Comentário muito longo (máx. 500 caracteres).' ) );
        }

        global $wpdb;

        // Rate limit: max 5 comentarios por musica por usuario
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_lyric_comments WHERE music_id = %d AND user_id = %d",
            $music_id, $user_id
        ) );
        if ( $count >= 5 ) {
            wp_send_json_error( array( 'message' => 'Limite de comentários por música atingido.' ) );
        }

        $result = $wpdb->insert(
            $wpdb->prefix . 'cv_lyric_comments',
            array(
                'music_id'   => $music_id,
                'user_id'    => $user_id,
                'excerpt'    => mb_substr( $excerpt, 0, 200 ),
                'comment'    => $comment,
                'created_at' => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s', '%s' )
        );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => 'Erro ao salvar. Tente novamente.' ) );
        }

        // v2.30.0: evento para as conquistas (antes da resposta).
        do_action( 'cv_lyric_commented', $music_id, $user_id, (int) $wpdb->insert_id );

        $user   = get_userdata( $user_id );
        $avatar = get_avatar_url( $user_id, array( 'size' => 40 ) );

        wp_send_json_success( array(
            'id'         => $wpdb->insert_id,
            'excerpt'    => mb_substr( $excerpt, 0, 200 ),
            'comment'    => $comment,
            'user_name'  => $user->display_name,
            'avatar'     => $avatar,
            'time_human' => 'agora mesmo',
        ) );
    }

    public static function get_comments() {
        check_ajax_referer( 'cv_lyric_comment_nonce', 'nonce' );

        $music_id = absint( $_GET['music_id'] ?? $_POST['music_id'] ?? 0 );
        if ( ! $music_id ) { wp_send_json_error(); }

        global $wpdb;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT lc.*, u.display_name, u.user_login
             FROM {$wpdb->prefix}cv_lyric_comments lc
             INNER JOIN {$wpdb->users} u ON u.ID = lc.user_id
             WHERE lc.music_id = %d
             ORDER BY lc.created_at DESC
             LIMIT 50",
            $music_id
        ) );

        $current_user = get_current_user_id();
        $is_admin     = current_user_can( 'manage_options' );

        $result = array();
        foreach ( $rows as $row ) {
            $result[] = array(
                'id'         => $row->id,
                'excerpt'    => $row->excerpt,
                'comment'    => $row->comment,
                'user_name'  => $row->display_name,
                'user_login' => $row->user_login,
                'avatar'     => get_avatar_url( $row->user_id, array( 'size' => 40 ) ),
                'time_human' => self::time_ago( strtotime( $row->created_at ) ),
                'can_delete' => ( (int) $row->user_id === $current_user || $is_admin ),
            );
        }

        wp_send_json_success( array( 'comments' => $result ) );
    }

    public static function delete_comment() {
        check_ajax_referer( 'cv_lyric_comment_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error(); }

        $id      = absint( $_POST['id'] ?? 0 );
        $user_id = get_current_user_id();

        global $wpdb;

        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT user_id FROM {$wpdb->prefix}cv_lyric_comments WHERE id = %d",
            $id
        ) );

        if ( ! $row ) { wp_send_json_error( array( 'message' => 'Comentário não encontrado.' ) ); }

        if ( (int) $row->user_id !== $user_id && ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        $wpdb->delete( $wpdb->prefix . 'cv_lyric_comments', array( 'id' => $id ), array( '%d' ) );
        wp_send_json_success();
    }

    private static function time_ago( $timestamp ) {
        $diff = current_time( 'timestamp' ) - $timestamp;
        if ( $diff < 60 )     { return 'agora mesmo'; }
        if ( $diff < 3600 )   { return intval( $diff / 60 ) . ' min atrás'; }
        if ( $diff < 86400 )  { return intval( $diff / 3600 ) . 'h atrás'; }
        if ( $diff < 604800 ) { return intval( $diff / 86400 ) . 'd atrás'; }
        return date( 'd/m/Y', $timestamp );
    }
}

CV_Lyric_Comments::init();
