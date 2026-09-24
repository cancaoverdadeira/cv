<?php
// cancao-verdadeira/includes/admin/advanced/trait-cv-adv-logs.php
// Parte do CV_Advanced (trait CV_Adv_Logs): log de ações administrativas (salvar/excluir música, login,
// logout, playlist) e AJAX cv_get_logs.
// v2.37.0: saiu de class-cv-advanced.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Advanced::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Adv_Logs {

    // ════════════════════════════════════════════════════════════════
    // 3. SISTEMA DE LOGS
    // ════════════════════════════════════════════════════════════════

    /**
     * Registra uma ação no log.
     *
     * @param string $action      Código da ação (ex: 'music_created')
     * @param string $description Descrição legível
     * @param string $obj_type    Tipo do objeto ('musica', 'playlist', 'user')
     * @param int    $obj_id      ID do objeto (0 se não aplicável)
     */
    public static function log( $action, $description, $obj_type = '', $obj_id = 0 ) {
        global $wpdb;

        $ip = '0.0.0.0';
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $candidate = trim( explode( ',', $_SERVER[ $key ] )[0] );
                if ( filter_var( $candidate, FILTER_VALIDATE_IP ) ) {
                    $ip = $candidate;
                    break;
                }
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'cv_action_logs',
            array(
                'user_id'     => get_current_user_id(),
                'action'      => sanitize_key( $action ),
                'object_type' => sanitize_text_field( $obj_type ),
                'object_id'   => absint( $obj_id ),
                'description' => sanitize_textarea_field( $description ),
                'ip_address'  => $ip,
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
        );

        // Limpa logs com mais de 90 dias automaticamente
        $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}cv_action_logs WHERE created_at < %s",
            date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - ( 90 * DAY_IN_SECONDS ) )
        ) );
    }

    // Hooks de log
    public static function log_music_save( $post_id, $post ) {
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( 'musica' !== $post->post_type ) { return; }
        $action = ( get_post_status( $post_id ) === 'publish' ) ? 'music_published' : 'music_saved';
        self::log( $action, 'Música "' . $post->post_title . '" salva/publicada.', 'musica', $post_id );
    }

    public static function log_music_delete( $post_id ) {
        if ( 'musica' !== get_post_type( $post_id ) ) { return; }
        self::log( 'music_deleted', 'Música "' . get_the_title( $post_id ) . '" excluída.', 'musica', $post_id );
    }

    public static function log_login( $user_login, $user ) {
        if ( user_can( $user, 'manage_options' ) || user_can( $user, 'cv_gerente' ) ) {
            self::log( 'admin_login', 'Login de administrador: ' . $user_login, 'user', $user->ID );
        }
    }

    public static function log_logout() {
        $user = wp_get_current_user();
        if ( $user && $user->ID ) {
            self::log( 'admin_logout', 'Logout: ' . $user->user_login, 'user', $user->ID );
        }
    }

    public static function log_playlist( $playlist_id, $name ) {
        self::log( 'playlist_created', 'Playlist criada: "' . $name . '"', 'playlist', $playlist_id );
    }

    /**
     * Retorna os logs recentes para a página admin.
     */
    public static function get_logs( $limit = 50, $action = '', $user_id = 0 ) {
        global $wpdb;

        $where = array( '1=1' );
        $args  = array();

        if ( $action ) {
            $where[] = 'l.action = %s';
            $args[]  = $action;
        }
        if ( $user_id ) {
            $where[] = 'l.user_id = %d';
            $args[]  = $user_id;
        }

        $args[] = $limit;

        $sql = "SELECT l.*, u.display_name AS user_name, u.user_email
                FROM {$wpdb->prefix}cv_action_logs l
                LEFT JOIN {$wpdb->users} u ON u.ID = l.user_id
                WHERE " . implode( ' AND ', $where ) . "
                ORDER BY l.created_at DESC
                LIMIT %d";

        return $wpdb->get_results( $wpdb->prepare( $sql, $args ) );
    }

    public static function ajax_get_logs() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'cv_master' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        $logs = self::get_logs(
            absint( $_POST['limit'] ?? 50 ),
            sanitize_text_field( $_POST['action_filter'] ?? '' ),
            absint( $_POST['user_id'] ?? 0 )
        );
        wp_send_json_success( array( 'logs' => $logs ) );
    }
}
