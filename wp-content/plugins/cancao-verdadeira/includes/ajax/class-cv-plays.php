<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-plays.php
// Gerado em: 2025-06-01 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Registra plays válidos via AJAX após 30 segundos de reprodução.
// Antifraude: ignora plays duplicados do mesmo IP em < 60 segundos.
// Salva na tabela cv_plays_log e atualiza o meta _cv_plays_total.
// Todos os endpoints usam nonce WordPress para segurança.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Plays {

    public static function init() {
        add_action( 'wp_ajax_cv_register_play',        array( __CLASS__, 'register_play' ) );
        add_action( 'wp_ajax_nopriv_cv_register_play', array( __CLASS__, 'register_play' ) );
    }

    public static function register_play() {
        check_ajax_referer( 'cv_play_nonce', 'nonce' );

        $music_id = absint( $_POST['music_id'] ?? 0 );

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) ) {
            wp_send_json_error( array( 'message' => 'ID inválido' ) );
        }

        $ip      = self::get_ip();
        $user_id = get_current_user_id();

        // Antifraude: mesmo IP nos últimos 60 segundos
        if ( self::is_duplicate( $music_id, $ip ) ) {
            wp_send_json_success( array( 'message' => 'Duplicate ignored' ) );
        }

        global $wpdb;

        $wpdb->insert(
            $wpdb->prefix . 'cv_plays_log',
            array(
                'music_id'   => $music_id,
                'user_id'    => $user_id,
                'ip_address' => $ip,
                'played_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s' )
        );

        // Atualiza contador em post_meta (acesso rápido nos templates)
        $total = (int) get_post_meta( $music_id, CV_Fields::PLAYS_TOTAL, true );
        update_post_meta( $music_id, CV_Fields::PLAYS_TOTAL, $total + 1 );

        // Salva histórico no usuário logado
        if ( $user_id ) {
            self::save_user_history( $user_id, $music_id );
        }

        // v2.30.0: avisa conquistas e cache de recomendações ANTES de responder
        // (wp_send_json encerra a requisição; ganchos depois dele nunca rodavam).
        do_action( 'cv_play_registered', $music_id, $user_id );

        wp_send_json_success( array(
            'plays' => $total + 1,
        ) );
    }

    private static function is_duplicate( $music_id, $ip ) {
        global $wpdb;

        $since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 );

        $count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
             WHERE music_id = %d AND ip_address = %s AND played_at >= %s",
            $music_id,
            $ip,
            $since
        ) );

        return $count > 0;
    }

    private static function save_user_history( $user_id, $music_id ) {
        $history = get_user_meta( $user_id, '_cv_play_history', true );
        if ( ! is_array( $history ) ) {
            $history = array();
        }

        // Evita duplicatas no histórico — move para o início
        $history = array_filter( $history, function( $item ) use ( $music_id ) {
            return (int) $item['id'] !== $music_id;
        } );

        array_unshift( $history, array(
            'id'      => $music_id,
            'played'  => current_time( 'timestamp' ),
        ) );

        // Mantém apenas os últimos 50
        $history = array_slice( $history, 0, 50 );

        update_user_meta( $user_id, '_cv_play_history', $history );
    }

    private static function get_ip() {
        $keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = explode( ',', $_SERVER[ $key ] )[0];
                $ip = trim( $ip );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}

CV_Plays::init();
