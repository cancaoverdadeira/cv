<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-ratings.php
// Gerado em: 2025-06-02 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Sistema de avaliacao por estrelas (1 a 5): salvar, consultar media
// e retornar avaliacao do usuario logado. Uma avaliacao por usuario
// por musica — atualiza se ja existir. Requer login para avaliar.
// Expos metodos estaticos para uso nos templates do tema.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Ratings {

    public static function init() {
        add_action( 'wp_ajax_cv_rate_music', array( __CLASS__, 'rate' ) );
        add_action( 'wp_ajax_cv_get_rating', array( __CLASS__, 'get_user_rating' ) );
    }

    public static function rate() {
        check_ajax_referer( 'cv_rating_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário para avaliar.', 'require_login' => true ) );
        }

        $music_id = absint( $_POST['music_id'] ?? 0 );
        $rating   = absint( $_POST['rating']   ?? 0 );
        $user_id  = get_current_user_id();

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'Música inválida.' ) );
        }

        if ( $rating < 1 || $rating > 5 ) {
            wp_send_json_error( array( 'message' => 'Avaliação inválida (1-5).' ) );
        }

        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_ratings WHERE user_id = %d AND music_id = %d",
            $user_id, $music_id
        ) );

        if ( $exists ) {
            $wpdb->update(
                $wpdb->prefix . 'cv_ratings',
                array( 'rating' => $rating, 'rated_at' => current_time( 'mysql' ) ),
                array( 'user_id' => $user_id, 'music_id' => $music_id ),
                array( '%d', '%s' ),
                array( '%d', '%d' )
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'cv_ratings',
                array(
                    'user_id'  => $user_id,
                    'music_id' => $music_id,
                    'rating'   => $rating,
                    'rated_at' => current_time( 'mysql' ),
                ),
                array( '%d', '%d', '%d', '%s' )
            );
        }

        // Atualiza media no post_meta para acesso rapido
        $avg = self::get_average( $music_id );
        update_post_meta( $music_id, CV_Fields::AVG_RATING, $avg );

        // v2.30.0: evento para as conquistas (antes da resposta).
        do_action( 'cv_music_rated', $music_id, $user_id, $rating );

        wp_send_json_success( array(
            'avg'        => round( $avg, 1 ),
            'user_rating'=> $rating,
            'stars_html' => self::stars_html( $avg ),
        ) );
    }

    public static function get_user_rating() {
        check_ajax_referer( 'cv_rating_nonce', 'nonce' );

        $music_id = absint( $_POST['music_id'] ?? 0 );
        $user_id  = get_current_user_id();

        if ( ! $user_id ) {
            wp_send_json_success( array( 'rating' => 0 ) );
        }

        global $wpdb;
        $rating = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT rating FROM {$wpdb->prefix}cv_ratings WHERE user_id = %d AND music_id = %d",
            $user_id, $music_id
        ) );

        wp_send_json_success( array( 'rating' => $rating ) );
    }

    /**
     * Retorna a media de avaliacoes de uma musica.
     */
    public static function get_average( $music_id ) {
        global $wpdb;
        $avg = $wpdb->get_var( $wpdb->prepare(
            "SELECT AVG(rating) FROM {$wpdb->prefix}cv_ratings WHERE music_id = %d",
            $music_id
        ) );
        return $avg ? round( (float) $avg, 2 ) : 0;
    }

    /**
     * Retorna a avaliacao do usuario atual para uma musica.
     */
    public static function get_user_rating_value( $music_id, $user_id = 0 ) {
        if ( ! $user_id ) { $user_id = get_current_user_id(); }
        if ( ! $user_id ) { return 0; }

        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT rating FROM {$wpdb->prefix}cv_ratings WHERE user_id = %d AND music_id = %d",
            $user_id, $music_id
        ) );
    }

    /**
     * Retorna o total de avaliacoes de uma musica.
     */
    public static function get_count( $music_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_ratings WHERE music_id = %d",
            $music_id
        ) );
    }

    /**
     * Gera HTML das estrelas para exibicao (nao interativo).
     */
    public static function stars_html( $avg ) {
        $html = '';
        for ( $i = 1; $i <= 5; $i++ ) {
            if ( $avg >= $i ) {
                $html .= '<span class="cv-star cv-star-full">★</span>';
            } elseif ( $avg >= $i - 0.5 ) {
                $html .= '<span class="cv-star cv-star-half">★</span>';
            } else {
                $html .= '<span class="cv-star cv-star-empty">☆</span>';
            }
        }
        return $html;
    }
}

CV_Ratings::init();
