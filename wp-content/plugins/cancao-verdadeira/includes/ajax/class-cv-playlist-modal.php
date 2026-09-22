<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-playlist-modal.php
// Gerado em: 2025-06-02 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Endpoint AJAX que retorna as playlists do usuario para popular o
// modal "Adicionar a Playlist" nos cards e na pagina individual.
// Tambem processa a adicao direta via modal sem recarregar a pagina.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Playlist_Modal {

    public static function init() {
        add_action( 'wp_ajax_cv_get_playlists_for_modal', array( __CLASS__, 'get_for_modal' ) );
        add_action( 'wp_ajax_cv_quick_add_to_playlist',   array( __CLASS__, 'quick_add' ) );
    }

    /**
     * Retorna playlists do usuario para popular o modal.
     */
    public static function get_for_modal() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.', 'require_login' => true ) );
        }

        $user_id   = get_current_user_id();
        $playlists = CV_Playlists::get_user_playlists( $user_id );

        $result = array();
        foreach ( $playlists as $pl ) {
            $result[] = array(
                'id'    => $pl->id,
                'name'  => $pl->name,
                'count' => $pl->count,
            );
        }

        wp_send_json_success( array( 'playlists' => $result ) );
    }

    /**
     * Adiciona musica a uma playlist existente ou nova (criada no modal).
     */
    public static function quick_add() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.', 'require_login' => true ) );
        }

        $music_id    = absint( $_POST['music_id']    ?? 0 );
        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        $new_name    = sanitize_text_field( $_POST['new_name'] ?? '' );
        $user_id     = get_current_user_id();

        if ( ! $music_id ) {
            wp_send_json_error( array( 'message' => 'Música inválida.' ) );
        }

        global $wpdb;

        // Cria nova playlist se solicitado
        if ( $new_name && ! $playlist_id ) {
            $wpdb->insert(
                $wpdb->prefix . 'cv_playlists',
                array(
                    'user_id'    => $user_id,
                    'name'       => $new_name,
                    'created_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%s', '%s' )
            );
            $playlist_id = $wpdb->insert_id;
        }

        if ( ! $playlist_id ) {
            wp_send_json_error( array( 'message' => 'Selecione uma playlist.' ) );
        }

        // Verifica posse da playlist
        $owns = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_playlists WHERE id = %d AND user_id = %d",
            $playlist_id, $user_id
        ) );

        if ( ! $owns ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        // Verifica duplicata
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

        $pl_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}cv_playlists WHERE id = %d",
            $playlist_id
        ) );

        wp_send_json_success( array(
            'message'       => 'Adicionado a "' . esc_html( $pl_name ) . '"!',
            'playlist_name' => $pl_name,
        ) );
    }
}

CV_Playlist_Modal::init();
