<?php
// cancao-verdadeira/includes/apoio/class-cv-envio-admin.php
// Criado em: 25/09/2026 (plugin v2.50.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Painel dos envios de música dos parceiros (PIX e Parcerias → aba
// "🎤 Envios de música"): baixar o contrato assinado e o comprovante,
// ver o resultado da conferência da assinatura (com link do validador do
// ITI), aprovar/recusar o contrato, confirmar o PIX, recusar o envio com
// motivo e, com tudo certo, GERAR A MÚSICA EM RASCUNHO (marcada como de
// parceiro). O parceiro recebe e-mail a cada decisão. HTML da aba em
// includes/apoio/views/envios.php.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Envio_Admin {

    public static function init() {
        add_action( 'wp_ajax_cv_envio_admin',   array( __CLASS__, 'ajax_acao' ) );
        add_action( 'wp_ajax_cv_envio_arquivo', array( __CLASS__, 'baixar_arquivo' ) );
    }

    private static function so_admin() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
    }

    private static function salvar( $id, $dados ) {
        global $wpdb;
        $dados['atualizado_em'] = current_time( 'mysql' );
        $wpdb->update( $wpdb->prefix . 'cv_envios', $dados, array( 'id' => $id ) );
    }

    /** Pode gerar a música? Devolve '' ou o que falta. */
    public static function falta( $e ) {
        if ( 'aprovado' !== $e->contrato_status )    { return 'aprovar o contrato'; }
        if ( 'confirmado' !== $e->pagamento_status ) { return 'confirmar o PIX'; }
        if ( 'enviado' !== $e->status )              { return 'o parceiro concluir a etapa 4'; }
        return '';
    }

    public static function ajax_acao() {
        self::so_admin();
        $e = CV_Envio::envio( absint( $_POST['id'] ?? 0 ) );
        if ( ! $e ) { wp_send_json_error( array( 'message' => 'Envio não encontrado.' ) ); }
        $motivo = sanitize_textarea_field( wp_unslash( $_POST['motivo'] ?? '' ) );

        switch ( sanitize_key( $_POST['acao'] ?? '' ) ) {
            case 'aprovar_contrato':
                if ( '' === $e->contrato_arquivo ) { wp_send_json_error( array( 'message' => 'Não há contrato enviado.' ) ); }
                self::salvar( $e->id, array( 'contrato_status' => 'aprovado' ) );
                $msg = 'Contrato aprovado.';
                break;

            case 'recusar_contrato':
                if ( '' === $motivo ) { wp_send_json_error( array( 'message' => 'Escreva o motivo, para o parceiro saber o que corrigir.' ) ); }
                // Volta a "em andamento" para o parceiro reenviar o contrato (as outras etapas ficam salvas)
                self::salvar( $e->id, array( 'contrato_status' => 'recusado', 'obs_admin' => $motivo, 'status' => 'rascunho' ) );
                self::avisar( $e, 'O contrato precisa ser enviado de novo', 'O contrato assinado que você enviou não pôde ser aceito: ' . $motivo . "\n\nEntre na sua Minha Área (aba \"Enviar música\") e envie o contrato de novo." );
                $msg = 'Contrato recusado; o parceiro foi avisado.';
                break;

            case 'confirmar_pix':
                self::salvar( $e->id, array( 'pagamento_status' => 'confirmado' ) );
                self::avisar( $e, 'PIX confirmado', 'Recebemos o PIX da taxa de divulgação. Obrigado!' );
                $msg = 'PIX confirmado; o parceiro foi avisado.';
                break;

            case 'recusar':
                if ( '' === $motivo ) { wp_send_json_error( array( 'message' => 'Escreva o motivo da recusa.' ) ); }
                self::salvar( $e->id, array( 'status' => 'recusado', 'obs_admin' => $motivo ) );
                self::avisar( $e, 'Sobre a sua música', 'Infelizmente não pudemos aprovar o envio da música "' . $e->titulo . '": ' . $motivo . "\n\nSe tiver dúvidas, é só responder este e-mail." );
                $msg = 'Envio recusado; o parceiro foi avisado.';
                break;

            case 'criar_rascunho':
                $falta = self::falta( $e );
                if ( $falta ) { wp_send_json_error( array( 'message' => 'Antes de gerar a música, falta ' . $falta . '.' ) ); }
                $r = self::criar_musica( $e );
                if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
                self::avisar( $e, 'Sua música foi aprovada! 🎉', 'Tudo certo com o contrato e o PIX. Estamos preparando a página da música "' . $e->titulo . '" no site. Avisaremos quando ela estiver no ar.' );
                wp_send_json_success( array( 'message' => 'Música criada em rascunho. Abrindo para revisão…', 'editar' => get_edit_post_link( $r, 'raw' ) ) );
                break;

            default:
                wp_send_json_error( array( 'message' => 'Ação desconhecida.' ) );
        }
        wp_send_json_success( array( 'message' => $msg ) );
    }

    /** Cria a música em RASCUNHO com os dados do envio. Devolve o ID. */
    public static function criar_musica( $e ) {
        $vid = CV_Fields::youtube_id( $e->youtube_url );
        if ( ! $vid ) { return new WP_Error( 'video', 'O link do YouTube do envio é inválido.' ); }
        if ( CV_Youtube_Import::find_existing( $vid ) ) { return new WP_Error( 'duplicada', 'Este vídeo já está cadastrado no site.' ); }
        $id = CV_Youtube_Import::criar_musica( $e->youtube_url, $e->titulo, $vid );
        if ( is_wp_error( $id ) ) { return $id; }

        wp_update_post( array( 'ID' => $id, 'post_content' => wpautop( esc_html( $e->letra ) ) ) );
        update_post_meta( $id, CV_Fields::ARTISTA,    $e->artista );
        update_post_meta( $id, CV_Fields::COMPOSITOR, $e->compositor );
        update_post_meta( $id, CV_Fields::DESCRICAO,  $e->descricao );
        update_post_meta( $id, '_cv_origem', 'parceiro' );
        update_post_meta( $id, '_cv_envio_id', (int) $e->id );
        if ( $e->tags ) { wp_set_post_terms( $id, array_map( 'trim', explode( ',', $e->tags ) ), 'post_tag', false ); }
        if ( method_exists( 'CV_Fields', 'sync_excerpt' ) ) { CV_Fields::sync_excerpt( $id ); }

        self::salvar( $e->id, array( 'status' => 'aprovado', 'musica_id' => $id ) );
        return $id;
    }

    private static function avisar( $e, $assunto, $texto ) {
        $u = get_userdata( $e->user_id );
        if ( ! $u ) { return; }
        wp_mail(
            $u->user_email,
            $assunto . ' — Canção Verdadeira',
            "Olá, " . $u->display_name . "!\n\n" . $texto . "\n\nAcompanhe em: " . add_query_arg( 'aba', 'enviar', home_url( '/minha-area/' ) ) . "\n\n— Canção Verdadeira"
        );
    }

    /** Baixa o contrato assinado ou o comprovante (só admin). */
    public static function baixar_arquivo() {
        if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['nonce'] ?? '' ) ), 'cv_admin_nonce' ) ) {
            wp_die( 'Sem permissão.', 'Canção Verdadeira', array( 'response' => 403 ) );
        }
        $e = CV_Envio::envio( absint( $_GET['id'] ?? 0 ) );
        $campo = 'comprovante' === sanitize_key( $_GET['tipo'] ?? '' ) ? 'comprovante_arquivo' : 'contrato_arquivo';
        $arq = $e ? CV_Envio::caminho( $e->$campo ) : '';
        if ( ! $arq || ! is_file( $arq ) ) { wp_die( 'Arquivo não encontrado.', 'Canção Verdadeira', array( 'response' => 404 ) ); }
        $ext  = strtolower( pathinfo( $arq, PATHINFO_EXTENSION ) );
        $mime = array( 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'png' => 'image/png' );
        nocache_headers();
        header( 'Content-Type: ' . ( $mime[ $ext ] ?? 'application/octet-stream' ) );
        header( 'Content-Disposition: attachment; filename="' . ( 'contrato_arquivo' === $campo ? 'contrato-assinado' : 'comprovante-pix' ) . '-envio-' . (int) $e->id . '.' . $ext . '"' );
        header( 'Content-Length: ' . filesize( $arq ) );
        header( 'X-Content-Type-Options: nosniff' );
        readfile( $arq );
        exit;
    }

    public static function url_arquivo( $e, $tipo ) {
        return add_query_arg( array( 'action' => 'cv_envio_arquivo', 'id' => (int) $e->id, 'tipo' => $tipo, 'nonce' => wp_create_nonce( 'cv_admin_nonce' ) ), admin_url( 'admin-ajax.php' ) );
    }
}

CV_Envio_Admin::init();
