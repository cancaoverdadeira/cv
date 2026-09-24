<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-youtube-import.php
// Gerado em: 2025-06-03 00:00:00 — revisado em 24/09/2026 (plugin v2.39.0)
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Importador de videos do YouTube via AJAX: recebe dados de um video
// e cria o CPT musica preenchido com titulo, URL YouTube e capa.
// A importacao e feita video a video pelo JS do admin (batch controller).
// Videos ja cadastrados sao ignorados. v2.39.0: a checagem compara o ID do
// video (aceita watch?v=, youtu.be, shorts, embed, live) e olha TODOS os
// status (publicada, rascunho, pendente, agendada, privada e lixeira).
// Antes so via as publicadas e duplicava as musicas em rascunho.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Youtube_Import {

    // Status em que uma musica ja "existe" para fins de duplicata.
    // 'any' do WordPress deixa a lixeira de fora, por isso a lista explicita.
    private static $status_existentes = array( 'publish', 'draft', 'pending', 'future', 'private', 'trash' );

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

        // O ID do video vem da propria URL (fonte confiavel); o campo 'id'
        // enviado pelo JS so e usado se a URL nao tiver um ID reconhecivel.
        $vid = CV_Fields::youtube_id( $url );
        if ( '' === $vid ) {
            $vid = sanitize_text_field( $video['id'] ?? '' );
        }
        if ( ! preg_match( '/^[A-Za-z0-9_-]{11}$/', $vid ) ) {
            wp_send_json_error( array( 'message' => 'Link do YouTube sem ID de video valido.' ) );
        }

        // Ignora se ja existir musica com este video (qualquer status)
        $existente = self::find_existing( $vid );
        if ( $existente ) {
            wp_send_json_success( array(
                'skipped'  => true,
                'title'    => $title,
                'post_id'  => $existente->ID,
                'status'   => $existente->post_status,
                'message'  => 'trash' === $existente->post_status
                    ? 'Ja existe na lixeira: restaure ou exclua de vez antes de importar.'
                    : 'Ja cadastrada: "' . $existente->post_title . '".',
                'edit_url' => admin_url( 'post.php?post=' . $existente->ID . '&action=edit' ),
            ) );
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
     * Procura uma musica ja cadastrada com o mesmo video do YouTube, em
     * qualquer status. O LIKE pre-filtra no banco; a confirmacao final e
     * feita com CV_Fields::youtube_id() para nao aceitar IDs parecidos.
     *
     * @return WP_Post|null
     */
    public static function find_existing( $video_id ) {
        global $wpdb;
        if ( '' === $video_id ) { return null; }

        $status_sql = "'" . implode( "','", array_map( 'esc_sql', self::$status_existentes ) ) . "'";
        $linhas = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, pm.meta_value
               FROM {$wpdb->posts} p
               JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
              WHERE p.post_type = 'musica'
                AND p.post_status IN ({$status_sql})
                AND pm.meta_key = %s
                AND pm.meta_value LIKE %s
              ORDER BY p.ID ASC",
            CV_Fields::YOUTUBE_URL,
            '%' . $wpdb->esc_like( $video_id ) . '%'
        ) );

        foreach ( $linhas as $linha ) {
            if ( CV_Fields::youtube_id( $linha->meta_value ) === $video_id ) {
                return get_post( (int) $linha->ID );
            }
        }
        return null;
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
