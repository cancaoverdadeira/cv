<?php
// cancao-verdadeira/includes/monetization/partes/trait-cv-mon-sorteios.php
// Parte do CV_Monetization (trait CV_Mon_Sorteios): sorteios automáticos: cron que sorteia entre os assinantes,
// e-mail ao vencedor e AJAX de salvar sorteio.
// v2.38.0: saiu de class-cv-monetization.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Monetization::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Mon_Sorteios {

    // ════════════════════════════════════════════════════════════════
    // 3. SORTEIOS AUTOMÁTICOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Processa sorteios com data_sorteio <= agora e status = agendado.
     * Chamado via WP-Cron diariamente.
     */
    public static function processar_sorteios() {
        global $wpdb;

        $agora = current_time( 'mysql' );

        $sorteios = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_sorteios
             WHERE status = 'agendado' AND data_sorteio <= %s",
            $agora
        ) );

        if ( empty( $sorteios ) ) { return; }

        foreach ( $sorteios as $sorteio ) {

            // Busca participantes elegíveis (assinantes ativos)
            $participantes = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}cv_subscribers ORDER BY RAND() LIMIT 1000"
            );

            if ( empty( $participantes ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'cv_sorteios',
                    array( 'status' => 'sem_participantes' ),
                    array( 'id' => $sorteio->id ),
                    array( '%s' ), array( '%d' )
                );
                continue;
            }

            // Sorteia um vencedor aleatório
            $vencedor = $participantes[ array_rand( $participantes ) ];

            // Salva o resultado
            $wpdb->update(
                $wpdb->prefix . 'cv_sorteios',
                array(
                    'status'           => 'realizado',
                    'vencedor_email'   => $vencedor->email,
                    'vencedor_nome'    => $vencedor->name ?: 'Assinante',
                    'realizado_em'     => $agora,
                    'total_participantes' => count( $participantes ),
                ),
                array( 'id' => $sorteio->id ),
                array( '%s','%s','%s','%s','%d' ),
                array( '%d' )
            );

            // Envia e-mail ao vencedor
            self::enviar_email_vencedor( $sorteio, $vencedor );

            // Log
            if ( class_exists( 'CV_Advanced' ) ) {
                CV_Advanced::log(
                    'sorteio_realizado',
                    'Sorteio "' . $sorteio->titulo . '" realizado. Vencedor: ' . $vencedor->email,
                    'sorteio',
                    $sorteio->id
                );
            }
        }
    }

    /**
     * Envia e-mail ao vencedor do sorteio.
     */
    private static function enviar_email_vencedor( $sorteio, $vencedor ) {
        $site_name = get_bloginfo( 'name' );
        $nome      = $vencedor->name ?: 'Amigo(a) sertanejo(a)';
        $premio    = esc_html( $sorteio->premio );
        $descricao = esc_html( $sorteio->descricao );

        $assunto = '🎉 Parabéns! Você ganhou o sorteio do ' . $site_name;

        $corpo = "Olá, {$nome}!\n\n"
               . "Você foi sorteado(a) e GANHOU:\n\n"
               . "🏆 {$premio}\n\n"
               . ( $descricao ? "{$descricao}\n\n" : '' )
               . "Entre em contato conosco respondendo este e-mail para retirar seu prêmio.\n\n"
               . "Com carinho,\n"
               . "Equipe " . $site_name . "\n"
               . home_url();

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        wp_mail( $vencedor->email, $assunto, $corpo, $headers );

        // Também notifica o admin
        $admin_corpo = "Sorteio realizado: {$sorteio->titulo}\n"
                     . "Vencedor: {$nome} ({$vencedor->email})\n"
                     . "Prêmio: {$premio}\n"
                     . "Total de participantes: " . ( $sorteio->total_participantes ?? '?' );

        wp_mail( get_option( 'admin_email' ), '[CV] Sorteio Realizado — ' . $sorteio->titulo, $admin_corpo, $headers );
    }

    public static function ajax_save_sorteio() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );

        $data = array(
            'titulo'       => sanitize_text_field( $_POST['titulo']       ?? '' ),
            'descricao'    => sanitize_textarea_field( $_POST['descricao'] ?? '' ),
            'premio'       => sanitize_text_field( $_POST['premio']       ?? '' ),
            'imagem_url'   => esc_url_raw( $_POST['imagem_url']           ?? '' ),
            'data_sorteio' => sanitize_text_field( $_POST['data_sorteio'] ?? '' ),
            'status'       => 'agendado',
        );
        $fmt = array( '%s','%s','%s','%s','%s','%s' );

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'cv_sorteios', $data, array( 'id' => $id ), $fmt, array( '%d' ) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'cv_sorteios', $data, $fmt );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $id ) );
    }
}
