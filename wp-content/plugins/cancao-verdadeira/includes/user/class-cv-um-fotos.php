<?php
// cancao-verdadeira/includes/user/class-cv-um-fotos.php
// Criado em: 08/10/2026 (plugin v2.66.0) · Diagnóstico da chave: 09/10/2026 (v2.66.2)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Foto do perfil e capa (Ultimate Member): no site do ar, o "Aplicar" do recorte
// ficava em "Processando..." para sempre, porque o JS do UM (um-modal.js) não
// trata resposta de erro. Aqui:
// 1) Tela: assets/js/cv-um-fotos.js mostra o erro em português, devolve o botão
//    e oferece "Fechar"; se passar de 40 s sem resposta, oferece fechar também.
// 2) Servidor: guarda no Log do Painel CV (ação "foto_perfil_erro") a resposta
//    de erro ou o erro fatal do PHP no envio/recorte, para achar a causa.
// 3) v2.66.2: no ar o "Aplicar" dá 403 (-1 = chave de segurança "um-resize-image"
//    + modo + campo recusada). O log agora diz QUAL parte não confere (modo enviado,
//    sessão da página x sessão do pedido, idade da página, cookies de login) e, se a
//    chave confere com outro modo, o pedido é ajustado para esse modo e segue.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_UM_Fotos {

    private static $nivel_buffer = 0;
    private static $acao         = '';
    private static $anotado      = false;
    private static $diagnostico  = '';

    public static function init() {
        add_action( 'wp_enqueue_scripts',         array( __CLASS__, 'scripts' ), 30 );
        add_action( 'wp_ajax_um_resize_image',    array( __CLASS__, 'vigiar' ), 1 );
        add_action( 'wp_ajax_um_imageupload',     array( __CLASS__, 'vigiar' ), 1 );
    }

    /** Só nas páginas de perfil do Ultimate Member, para quem está logado. */
    public static function scripts() {
        if ( ! is_user_logged_in() || ! function_exists( 'um_is_core_page' ) || ! um_is_core_page( 'user' ) ) { return; }
        wp_enqueue_style( 'cv-um-fotos', CV_PLUGIN_URL . 'assets/css/cv-um-fotos.css', array(), CV_VERSION );
        wp_enqueue_script( 'cv-um-fotos', CV_PLUGIN_URL . 'assets/js/cv-um-fotos.js', array( 'jquery' ), CV_VERSION, true );
        // Dados da página para comparar com o pedido do "Aplicar" (sem a sessão em si: só um resumo).
        wp_localize_script( 'cv-um-fotos', 'cvUmFotos', array(
            's' => self::resumo_sessao(),
            'u' => get_current_user_id(),
            't' => time(),
        ) );
    }

    /** Antes do UM (prioridade 1): guarda a saída para ler no fim do pedido. */
    public static function vigiar() {
        self::$acao = current_action();
        if ( 'wp_ajax_um_resize_image' === self::$acao ) {
            self::conferir_chave();
        }
        ob_start();
        self::$nivel_buffer = ob_get_level();
        // Prioridade 0: antes do wp_ob_end_flush_all (prioridade 1), que esvazia a saída.
        add_action( 'shutdown', array( __CLASS__, 'ao_terminar' ), 0 );
        // Erro fatal: o tratamento do WordPress roda antes e encerra o PHP,
        // então anotamos já na mensagem de erro que ele monta.
        add_filter( 'wp_php_error_message', array( __CLASS__, 'ao_fatal' ), 10, 2 );
    }

    /**
     * Confere a chave do "Aplicar" do mesmo jeito que o UM (check_ajax_referer) e monta
     * o diagnóstico. Se a chave só confere com outro modo, ajusta o pedido para esse modo:
     * a própria chave prova que o servidor a criou para este usuário, campo e modo.
     */
    private static function conferir_chave() {
        $nonce = isset( $_REQUEST['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wpnonce'] ) ) : '';
        $chave = isset( $_REQUEST['key'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['key'] ) ) : '';
        $modo  = isset( $_POST['set_mode'] ) ? sanitize_text_field( wp_unslash( $_POST['set_mode'] ) ) : null;

        $confere = array();
        foreach ( array_unique( array( (string) $modo, 'profile', '', 'register', 'login' ) ) as $m ) {
            $r = wp_verify_nonce( $nonce, 'um-resize-image' . $m . $chave );
            if ( $r ) { $confere[ $m ] = $r; }
        }

        $partes   = array();
        $partes[] = 'Enviado: modo "' . ( null === $modo ? '(nenhum)' : $modo ) . '", campo "' . $chave . '", form '
            . ( isset( $_POST['set_id'] ) ? absint( $_POST['set_id'] ) : '?' ) . ', chave com ' . strlen( $nonce ) . ' letras';
        if ( $confere ) {
            $lista = array();
            foreach ( $confere as $m => $r ) { $lista[] = '"' . $m . '"' . ( 2 === $r ? ' (de ontem)' : '' ); }
            $partes[] = 'chave confere com o modo ' . implode( ', ', $lista );
        } else {
            $partes[] = 'chave NÃO confere com nenhum modo';
        }

        // Sessão, usuário e idade da página (enviados pelo cv-um-fotos.js).
        $sessao_pag = isset( $_POST['cv_diag_s'] ) ? sanitize_key( wp_unslash( $_POST['cv_diag_s'] ) ) : '';
        if ( '' === $sessao_pag ) {
            $partes[] = 'sem dados da página';
        } else {
            $partes[] = 'sessão ' . ( hash_equals( self::resumo_sessao(), $sessao_pag ) ? 'igual' : 'DIFERENTE' ) . ' à da página';
            $u_pag    = isset( $_POST['cv_diag_u'] ) ? absint( $_POST['cv_diag_u'] ) : 0;
            $partes[] = 'usuário da página ' . $u_pag . ', agora ' . get_current_user_id();
            $t_pag    = isset( $_POST['cv_diag_t'] ) ? absint( $_POST['cv_diag_t'] ) : 0;
            if ( $t_pag ) { $partes[] = 'página aberta há ' . self::tempo( time() - $t_pag ); }
            if ( isset( $_POST['cv_diag_f'] ) ) {
                $partes[] = 'tela: ' . sanitize_text_field( wp_unslash( $_POST['cv_diag_f'] ) );
            }
        }

        // Cookies de login repetidos atrapalham a sessão.
        $bruto = isset( $_SERVER['HTTP_COOKIE'] ) ? (string) $_SERVER['HTTP_COOKIE'] : '';
        $partes[] = 'cookies de login: ' . substr_count( $bruto, LOGGED_IN_COOKIE . '=' );

        if ( ! isset( $confere[ (string) $modo ] ) && $confere ) {
            $novo               = (string) key( $confere );
            $_POST['set_mode']  = $novo;
            $_REQUEST['set_mode'] = $novo;
            $partes[]           = 'AJUSTADO para o modo "' . $novo . '"';
            if ( class_exists( 'CV_Advanced' ) ) {
                CV_Advanced::log( 'foto_perfil_ajuste', 'Foto/capa: modo ajustado. ' . implode( '; ', $partes ), 'user', get_current_user_id() );
            }
        }

        self::$diagnostico = implode( '; ', $partes );
    }

    /** Resumo curto da sessão de login (não dá para refazer a sessão a partir dele). */
    private static function resumo_sessao() {
        $token = function_exists( 'wp_get_session_token' ) ? wp_get_session_token() : '';
        return '' === $token ? 'sem-sessao' : substr( wp_hash( $token ), 0, 10 );
    }

    private static function tempo( $segundos ) {
        $segundos = max( 0, (int) $segundos );
        if ( $segundos < 120 )  { return $segundos . ' s'; }
        if ( $segundos < 7200 ) { return round( $segundos / 60 ) . ' min'; }
        return round( $segundos / 3600, 1 ) . ' h';
    }

    public static function ao_fatal( $mensagem, $erro ) {
        self::anotar( '', is_array( $erro ) ? $erro : null );
        return $mensagem;
    }

    /** No fim do pedido: se a resposta não foi "success", anota no log. */
    public static function ao_terminar() {
        $saida = ( ob_get_level() >= self::$nivel_buffer ) ? (string) ob_get_contents() : '';
        self::anotar( $saida, error_get_last() );
    }

    private static function anotar( $saida, $fatal ) {
        if ( self::$anotado ) { return; }
        $e_fatal = $fatal && in_array( $fatal['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ), true );
        $json    = json_decode( $saida, true );
        $ok      = is_array( $json ) && ! empty( $json['success'] );
        if ( $ok && ! $e_fatal ) { return; }

        $chave = isset( $_REQUEST['key'] ) ? sanitize_key( wp_unslash( $_REQUEST['key'] ) ) : '';
        $tipo  = ( 'cover_photo' === $chave ) ? 'capa' : ( 'profile_photo' === $chave ? 'foto do perfil' : $chave );
        $etapa = ( 'wp_ajax_um_resize_image' === self::$acao ) ? 'Aplicar (recorte)' : 'Enviar arquivo';
        $texto = 'Erro na ' . $tipo . ' — etapa ' . $etapa . '. ';
        if ( $e_fatal ) {
            $texto .= 'Erro do PHP: ' . $fatal['message'] . ' em ' . wp_basename( $fatal['file'] ) . ':' . $fatal['line'] . '. ';
        }
        if ( is_array( $json ) && isset( $json['data'] ) ) {
            $texto .= 'Resposta: ' . ( is_string( $json['data'] ) ? $json['data'] : wp_json_encode( $json['data'] ) );
        } elseif ( '' !== trim( $saida ) ) {
            $texto .= 'Resposta: ' . wp_strip_all_tags( substr( $saida, 0, 400 ) );
        }
        $texto .= ' | PHP ' . PHP_VERSION . ', memória ' . ini_get( 'memory_limit' ) . ', editor de imagem: ' . self::editor_disponivel();
        if ( '' !== self::$diagnostico ) {
            $texto .= ' | ' . self::$diagnostico;
        }

        self::$anotado = true;
        if ( class_exists( 'CV_Advanced' ) ) {
            CV_Advanced::log( 'foto_perfil_erro', $texto, 'user', get_current_user_id() );
        }
    }

    private static function editor_disponivel() {
        $e = array();
        if ( extension_loaded( 'imagick' ) ) { $e[] = 'Imagick'; }
        if ( extension_loaded( 'gd' ) )      { $e[] = 'GD'; }
        return $e ? implode( '+', $e ) : 'NENHUM';
    }
}

CV_UM_Fotos::init();
