<?php
// cancao-verdadeira-plugin/includes/ajax/class-cv-newsletter.php
// Gerado em: 2025-06-01 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gerencia a inscrição na newsletter via MailerLite API v3.
// Fallback local: salva na tabela cv_subscribers se a API falhar.
// Suporte a grupos por gênero musical configurado no painel admin.
// Rate limiting básico por IP para evitar spam.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Newsletter {

    public static function init() {
        add_action( 'wp_ajax_cv_subscribe',        array( __CLASS__, 'subscribe' ) );
        add_action( 'wp_ajax_nopriv_cv_subscribe', array( __CLASS__, 'subscribe' ) );
    }

    public static function subscribe() {
        check_ajax_referer( 'cv_newsletter_nonce', 'nonce' );

        $email = sanitize_email( $_POST['email'] ?? '' );
        $name  = sanitize_text_field( $_POST['name']  ?? '' );
        $genre = sanitize_text_field( $_POST['genre'] ?? '' );

        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'E-mail inválido.' ) );
        }

        // Rate limiting simples por IP
        if ( self::is_rate_limited() ) {
            wp_send_json_error( array( 'message' => 'Muitas tentativas. Tente novamente em instantes.' ) );
        }

        // Tenta MailerLite
        $api_key  = get_option( 'cv_mailerlite_api_key', '' );
        $group_id = self::get_group_id( $genre );

        if ( $api_key ) {
            $result = self::mailerlite_subscribe( $email, $name, $api_key, $group_id );

            if ( is_wp_error( $result ) ) {
                // Fallback local
                self::save_local( $email, $name, $genre );
            }
        } else {
            // Sem API key: apenas fallback local
            self::save_local( $email, $name, $genre );
        }

        wp_send_json_success( array(
            'message' => 'Inscrição realizada com sucesso! Bem-vindo(a) ao Canção Verdadeira. 🎵',
        ) );
    }

    private static function mailerlite_subscribe( $email, $name, $api_key, $group_id ) {
        $body = array(
            'email' => $email,
        );

        if ( $name ) {
            $body['fields'] = array( 'name' => $name );
        }

        if ( $group_id ) {
            $body['groups'] = array( $group_id );
        }

        $response = wp_remote_post(
            'https://connect.mailerlite.com/api/subscribers',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code( $response );

        if ( $code < 200 || $code >= 300 ) {
            return new WP_Error( 'mailerlite_error', 'MailerLite API error: ' . $code );
        }

        return true;
    }

    private static function save_local( $email, $name, $genre ) {
        global $wpdb;

        $exists = $wpdb->get_var( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_subscribers WHERE email = %s",
            $email
        ) );

        if ( ! $exists ) {
            $wpdb->insert(
                $wpdb->prefix . 'cv_subscribers',
                array(
                    'email'         => $email,
                    'name'          => $name,
                    'genre'         => $genre,
                    'subscribed_at' => current_time( 'mysql' ),
                ),
                array( '%s', '%s', '%s', '%s' )
            );
        }
    }

    private static function get_group_id( $genre ) {
        $groups = get_option( 'cv_mailerlite_groups', array() );
        return isset( $groups[ $genre ] ) ? $groups[ $genre ] : get_option( 'cv_mailerlite_group_id', '' );
    }

    private static function is_rate_limited() {
        $ip  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $key = 'cv_nl_rl_' . md5( $ip );

        $count = (int) get_transient( $key );

        if ( $count >= 3 ) {
            return true;
        }

        set_transient( $key, $count + 1, 5 * MINUTE_IN_SECONDS );

        return false;
    }
}

CV_Newsletter::init();
