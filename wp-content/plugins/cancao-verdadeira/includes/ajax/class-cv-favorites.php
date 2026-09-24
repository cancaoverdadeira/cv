<?php
// cancao-verdadeira/includes/ajax/class-cv-favorites.php
// Gerado em: 2026-06-21 18:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gerencia o sistema de favoritos: adicionar, remover e verificar.
// v2.1 — CORRIGIDO BUG B3: métodos estáticos get_user_favorites() e
// is_favorite() estavam ausentes mas referenciados em class-cv-um-integration,
// class-cv-user-area e class-cv-public-profile, causando fatal error no
// dashboard. Ambos os métodos foram adicionados nesta versão.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Favorites {

    public static function init() {
        add_action( 'wp_ajax_cv_toggle_favorite', array( __CLASS__, 'toggle' ) );
        add_action( 'wp_ajax_cv_check_favorite',  array( __CLASS__, 'check' ) );
    }

    /**
     * Adiciona ou remove uma música dos favoritos do usuário logado.
     */
    public static function toggle() {
        check_ajax_referer( 'cv_favorite_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array(
                'message'       => 'Login necessário para favoritar.',
                'require_login' => true,
            ) );
        }

        $music_id = absint( $_POST['music_id'] ?? 0 );
        $user_id  = get_current_user_id();

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'Música inválida.' ) );
        }

        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_favorites WHERE user_id = %d AND music_id = %d",
            $user_id,
            $music_id
        ) );

        if ( $exists ) {
            $wpdb->delete(
                $wpdb->prefix . 'cv_favorites',
                array( 'user_id' => $user_id, 'music_id' => $music_id ),
                array( '%d', '%d' )
            );
            $action = 'removed';
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'cv_favorites',
                array(
                    'user_id'  => $user_id,
                    'music_id' => $music_id,
                    'added_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%s' )
            );
            $action = 'added';
        }

        // Atualiza contador no post_meta (acesso rápido nos templates e no ranking)
        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE music_id = %d",
            $music_id
        ) );
        update_post_meta( $music_id, CV_Fields::FAVORITES, $count );

        // v2.30.0: evento para conquistas e cache de recomendações (antes da resposta).
        do_action( 'cv_favorite_changed', $music_id, $user_id, $action );

        wp_send_json_success( array(
            'action'    => $action,
            'favorites' => $count,
            // Texto a exibir: vazio enquanto abaixo do mínimo do modo lançamento.
            'favorites_label' => class_exists( 'CV_Launch' ) ? CV_Launch::fav_label( $count ) : (string) $count,
        ) );
    }

    /**
     * Verifica se uma música está nos favoritos do usuário logado.
     */
    public static function check() {
        check_ajax_referer( 'cv_favorite_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_success( array( 'favorited' => false ) );
        }

        $music_id = absint( $_POST['music_id'] ?? 0 );
        $user_id  = get_current_user_id();

        wp_send_json_success( array(
            'favorited' => self::is_favorite( $user_id, $music_id ),
        ) );
    }

    /**
     * CORRIGIDO BUG B3: método estático ausente que causava fatal error.
     * Retorna array de WP_Post[] com as músicas favoritas do usuário.
     *
     * @param int $user_id  ID do usuário.
     * @param int $limit    Número máximo de resultados (padrão 20).
     * @return WP_Post[]
     */
    public static function get_user_favorites( $user_id, $limit = 20 ) {
        $user_id = absint( $user_id );
        $limit   = absint( $limit );

        if ( ! $user_id ) {
            return array();
        }

        global $wpdb;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT music_id
             FROM {$wpdb->prefix}cv_favorites
             WHERE user_id = %d
             ORDER BY added_at DESC
             LIMIT %d",
            $user_id,
            $limit
        ) );

        if ( empty( $ids ) ) {
            return array();
        }

        // Busca os posts em uma única query, preservando a ordem
        $posts = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'post__in'       => array_map( 'absint', $ids ),
            'orderby'        => 'post__in',
            'posts_per_page' => $limit,
        ) );

        return $posts;
    }

    /**
     * CORRIGIDO BUG B3: método estático ausente que causava fatal error.
     * Verifica se um usuário específico favoritou uma música.
     *
     * @param int $user_id   ID do usuário.
     * @param int $music_id  ID da música.
     * @return bool
     */
    public static function is_favorite( $user_id, $music_id ) {
        $user_id  = absint( $user_id );
        $music_id = absint( $music_id );

        if ( ! $user_id || ! $music_id ) {
            return false;
        }

        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_favorites WHERE user_id = %d AND music_id = %d LIMIT 1",
            $user_id,
            $music_id
        ) );

        return ! empty( $exists );
    }

    /**
     * Retorna IDs das músicas favoritas do usuário (leve, para recomendações).
     *
     * @param int $user_id
     * @param int $limit
     * @return int[]
     */
    public static function get_user_favorite_ids( $user_id, $limit = 50 ) {
        $user_id = absint( $user_id );
        if ( ! $user_id ) { return array(); }

        global $wpdb;

        $ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT music_id FROM {$wpdb->prefix}cv_favorites
             WHERE user_id = %d ORDER BY added_at DESC LIMIT %d",
            $user_id,
            absint( $limit )
        ) );

        return array_map( 'absint', $ids );
    }

    /**
     * Retorna o total de favoritos de um usuário.
     *
     * @param int $user_id
     * @return int
     */
    public static function get_user_favorites_count( $user_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE user_id = %d",
            absint( $user_id )
        ) );
    }
}

CV_Favorites::init();
