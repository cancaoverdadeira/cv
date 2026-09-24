<?php
// cancao-verdadeira/includes/admin/advanced/trait-cv-adv-exportacao.php
// Parte do CV_Advanced (trait CV_Adv_Exportacao): exportação de usuários em CSV (AJAX cv_export_users_csv, só admin).
// v2.37.0: saiu de class-cv-advanced.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Advanced::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Adv_Exportacao {

    // ════════════════════════════════════════════════════════════════
    // 6. EXPORTAÇÃO DE USUÁRIOS CSV
    // ════════════════════════════════════════════════════════════════

    /**
     * AJAX: gera e faz download de um CSV com todos os usuários.
     * Inclui: nome, e-mail, papel, data de cadastro, favoritos, playlists, plays.
     */
    public static function ajax_export_users_csv() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );

        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'cv_master' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        global $wpdb;

        $users = get_users( array(
            'number'  => -1,
            'orderby' => 'registered',
            'order'   => 'ASC',
        ) );

        // Contadores em lote para performance
        $user_ids = wp_list_pluck( $users, 'ID' );
        $fav_map  = array();
        $pl_map   = array();

        if ( ! empty( $user_ids ) ) {
            $ids_in = implode( ',', array_map( 'intval', $user_ids ) );

            $favs = $wpdb->get_results(
                "SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_favorites
                 WHERE user_id IN ($ids_in) GROUP BY user_id"
            );
            foreach ( $favs as $r ) { $fav_map[ $r->user_id ] = (int) $r->total; }

            $pls = $wpdb->get_results(
                "SELECT user_id, COUNT(*) AS total FROM {$wpdb->prefix}cv_playlists
                 WHERE user_id IN ($ids_in) GROUP BY user_id"
            );
            foreach ( $pls as $r ) { $pl_map[ $r->user_id ] = (int) $r->total; }
        }

        // Monta CSV em memória
        $lines   = array();
        $lines[] = implode( ';', array(
            'ID', 'Nome', 'Login', 'E-mail', 'Papel',
            'Cadastro', 'Favoritos', 'Playlists', 'Plays (historico)',
        ) );

        foreach ( $users as $user ) {
            $history = get_user_meta( $user->ID, '_cv_play_history', true );
            $plays   = is_array( $history ) ? count( $history ) : 0;

            $lines[] = implode( ';', array(
                $user->ID,
                '"' . str_replace( '"', '""', $user->display_name ) . '"',
                $user->user_login,
                $user->user_email,
                implode( '/', $user->roles ),
                date( 'd/m/Y', strtotime( $user->user_registered ) ),
                $fav_map[ $user->ID ] ?? 0,
                $pl_map[ $user->ID ]  ?? 0,
                $plays,
            ) );
        }

        $csv      = "\xEF\xBB\xBF" . implode( "\n", $lines );
        $filename = 'cancao-verdadeira-usuarios-' . date( 'Y-m-d' ) . '.csv';

        // Download direto — evita limite de memória com base64+JSON
        nocache_headers();
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Content-Length: ' . strlen( $csv ) );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
        echo $csv;
        exit;
    }
}
