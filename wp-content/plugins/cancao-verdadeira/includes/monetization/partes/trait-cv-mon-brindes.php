<?php
// cancao-verdadeira/includes/monetization/partes/trait-cv-mon-brindes.php
// Parte do CV_Monetization (trait CV_Mon_Brindes): brindes para membros: cadastro e envio de brinde + e-mail.
// v2.38.0: saiu de class-cv-monetization.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Monetization::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Mon_Brindes {

    // ════════════════════════════════════════════════════════════════
    // 4. BRINDES PARA MEMBROS
    // ════════════════════════════════════════════════════════════════

    public static function ajax_save_brinde() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );

        $data = array(
            'titulo'      => sanitize_text_field( $_POST['titulo']      ?? '' ),
            'descricao'   => sanitize_textarea_field( $_POST['descricao'] ?? '' ),
            'imagem_url'  => esc_url_raw( $_POST['imagem_url']           ?? '' ),
            'quantidade'  => absint( $_POST['quantidade'] ?? 1 ),
            'status'      => 'disponivel',
        );
        $fmt = array( '%s','%s','%s','%d','%s' );

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'cv_brindes', $data, array( 'id' => $id ), $fmt, array( '%d' ) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'cv_brindes', $data, $fmt );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $id ) );
    }

    /**
     * Envia um brinde para um assinante específico.
     * Admin escolhe o brinde e o destinatário pelo e-mail.
     */
    public static function ajax_enviar_brinde() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;

        $brinde_id = absint( $_POST['brinde_id']      ?? 0 );
        $email     = sanitize_email( $_POST['email']  ?? '' );
        $mensagem  = sanitize_textarea_field( $_POST['mensagem'] ?? '' );

        if ( ! $brinde_id || ! $email ) {
            wp_send_json_error( array( 'message' => 'Dados incompletos.' ) );
        }

        $brinde = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_brindes WHERE id = %d AND status = 'disponivel'",
            $brinde_id
        ) );

        if ( ! $brinde ) {
            wp_send_json_error( array( 'message' => 'Brinde não encontrado ou indisponível.' ) );
        }

        // Busca nome do destinatário (assinante ou usuário WP)
        $nome = '';
        $sub  = $wpdb->get_row( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}cv_subscribers WHERE email = %s",
            $email
        ) );
        if ( $sub ) { $nome = $sub->name; }
        if ( ! $nome ) {
            $wp_user = get_user_by( 'email', $email );
            if ( $wp_user ) { $nome = $wp_user->display_name; }
        }
        $nome = $nome ?: 'Amigo(a) sertanejo(a)';

        // Registra entrega
        $wpdb->insert(
            $wpdb->prefix . 'cv_brindes_entregas',
            array(
                'brinde_id'      => $brinde_id,
                'email'          => $email,
                'nome'           => $nome,
                'mensagem_admin' => $mensagem,
                'enviado_em'     => current_time( 'mysql' ),
                'enviado_por'    => get_current_user_id(),
            ),
            array( '%d','%s','%s','%s','%s','%d' )
        );

        // Atualiza quantidade disponível
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}cv_brindes
             SET quantidade = GREATEST(0, quantidade - 1),
                 status = CASE WHEN quantidade <= 1 THEN 'esgotado' ELSE 'disponivel' END
             WHERE id = %d",
            $brinde_id
        ) );

        // Envia e-mail ao destinatário
        $site_name = get_bloginfo( 'name' );
        $assunto   = '🎁 Você recebeu um brinde do ' . $site_name . '!';
        $corpo     = "Olá, {$nome}!\n\n"
                   . "Temos uma surpresa para você: 🎁 {$brinde->titulo}\n\n"
                   . ( $brinde->descricao ? $brinde->descricao . "\n\n" : '' )
                   . ( $mensagem ? "Mensagem da equipe:\n{$mensagem}\n\n" : '' )
                   . "Entre em contato respondendo este e-mail para combinar a entrega.\n\n"
                   . "Com carinho,\nEquipe " . $site_name . "\n" . home_url();

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        $enviado = wp_mail( $email, $assunto, $corpo, $headers );

        if ( class_exists( 'CV_Advanced' ) ) {
            CV_Advanced::log(
                'brinde_enviado',
                'Brinde "' . $brinde->titulo . '" enviado para ' . $email,
                'brinde',
                $brinde_id
            );
        }

        wp_send_json_success( array(
            'message' => $enviado
                ? 'Brinde enviado com sucesso para ' . $email
                : 'Entrega registrada, mas o e-mail não pôde ser enviado. Verifique as configurações de e-mail.',
        ) );
    }
}
