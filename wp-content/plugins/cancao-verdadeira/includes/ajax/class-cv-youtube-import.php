<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-youtube-import.php
// Gerado em: 2025-06-03 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Importador de videos do YouTube via AJAX: recebe dados de um video
// e cria o CPT musica preenchido com titulo, URL YouTube e capa.
// A importacao e feita video a video pelo JS do admin (batch controller).
// Videos ja cadastrados (mesma URL) sao ignorados silenciosamente.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Youtube_Import {

    public static function init() {
        add_action( 'wp_ajax_cv_import_youtube_video', array( __CLASS__, 'import_video' ) );
    }

    public static function import_video() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissao.' ) );
        }

        $raw = sanitize_text_field( $_POST['video'] ?? '' );
        if ( ! $raw ) {
            wp_send_json_error( array( 'message' => 'Dados invalidos.' ) );
        }

        $video = json_decode( stripslashes( $raw ), true );
        if ( ! $video || empty( $video['url'] ) || empty( $video['title'] ) ) {
            wp_send_json_error( array( 'message' => 'Dados invalidos.' ) );
        }

        $url   = esc_url_raw( $video['url'] );
        $title = sanitize_text_field( $video['title'] );
        $thumb = esc_url_raw( $video['thumb'] ?? '' );
        $vid   = sanitize_text_field( $video['id']   ?? '' );

        // Ignora se ja existir musica com esta URL
        $existing = get_posts( array(
            'post_type'      => 'musica',
            'posts_per_page' => 1,
            'meta_query'     => array(
                array( 'key' => CV_Fields::YOUTUBE_URL, 'value' => $url, 'compare' => '=' ),
            ),
        ) );

        if ( ! empty( $existing ) ) {
            wp_send_json_success( array( 'skipped' => true, 'title' => $title ) );
        }

        // Cria o post
        $post_id = wp_insert_post( array(
            'post_title'   => $title,
            'post_type'    => 'musica',
            'post_status'  => 'draft', // rascunho ate ter letra e compositor
            'post_content' => '',
        ) );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        }

        // Preenche metaboxes automaticamente
        update_post_meta( $post_id, CV_Fields::YOUTUBE_URL, $url );
        update_post_meta( $post_id, CV_Fields::ATIVO,       '0' ); // inativo ate ter letra
        update_post_meta( $post_id, CV_Fields::DESTAQUE,    '0' );

        // Importa a thumbnail do YouTube como featured image
        if ( $thumb ) {
            self::set_thumbnail_from_url( $post_id, $thumb, $vid );
        }

        wp_send_json_success( array(
            'skipped'  => false,
            'post_id'  => $post_id,
            'title'    => $title,
            'edit_url' => admin_url( 'post.php?post=' . $post_id . '&action=edit' ),
        ) );
    }

    /**
     * Faz download da thumbnail do YouTube e define como imagem destacada.
     */
    private static function set_thumbnail_from_url( $post_id, $thumb_url, $video_id ) {
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Tenta maxresdefault primeiro, depois hqdefault
        $urls = array(
            "https://img.youtube.com/vi/{$video_id}/maxresdefault.jpg",
            "https://img.youtube.com/vi/{$video_id}/hqdefault.jpg",
            $thumb_url,
        );

        foreach ( $urls as $url ) {
            $tmp = download_url( $url );
            if ( is_wp_error( $tmp ) ) { continue; }

            $file = array(
                'name'     => 'yt-' . $video_id . '.jpg',
                'type'     => 'image/jpeg',
                'tmp_name' => $tmp,
                'error'    => 0,
                'size'     => filesize( $tmp ),
            );

            $attach_id = media_handle_sideload( $file, $post_id );

            if ( ! is_wp_error( $attach_id ) ) {
                set_post_thumbnail( $post_id, $attach_id );
                return;
            }
        }
    }
}

CV_Youtube_Import::init();
