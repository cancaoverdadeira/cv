<?php
// cancao-verdadeira/includes/security/class-cv-security.php
// Gerado em: 2026-06-21 20:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo de segurança complementar do plugin. Protege contra tentativas
// excessivas de login (bloqueia IP após 5 erros em 10 minutos), adiciona
// headers HTTP de segurança nas páginas públicas, gera .htaccess na pasta
// do plugin para impedir acesso direto aos arquivos PHP, e registra
// tentativas bloqueadas na tabela cv_action_logs para auditoria.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Security {

    // Máximo de tentativas de login antes de bloquear
    const MAX_ATTEMPTS = 5;

    // Tempo de bloqueio em segundos (10 minutos)
    const LOCKOUT_TIME = 600;

    public static function init() {

        // ── Proteção de login ─────────────────────────────────────
        add_action( 'wp_login_failed',    array( __CLASS__, 'on_login_failed' ) );
        add_filter( 'authenticate',       array( __CLASS__, 'block_locked_ip' ), 30, 3 );
        add_action( 'wp_login',           array( __CLASS__, 'on_login_success' ), 10, 2 );

        // ── Headers HTTP de segurança ─────────────────────────────
        add_action( 'send_headers', array( __CLASS__, 'send_security_headers' ) );

        // ── Cria .htaccess na ativação ────────────────────────────
        add_action( 'cv_activate_security', array( __CLASS__, 'create_htaccess' ) );

        // ── Remove informações desnecessárias do WordPress ────────
        add_action( 'init', array( __CLASS__, 'harden_wordpress' ) );
    }

    // ── PROTEÇÃO DE LOGIN ─────────────────────────────────────────

    /**
     * Registra tentativa de login com falha.
     * Armazena contador por IP via transient do WordPress.
     */
    public static function on_login_failed( $username ) {
        $ip  = self::get_ip();
        $key = 'cv_login_fail_' . md5( $ip );

        $attempts = (int) get_transient( $key );
        $attempts++;

        // Renova o transient a cada falha (janela deslizante de 10 min)
        set_transient( $key, $attempts, self::LOCKOUT_TIME );

        // Registra no log após a 3ª tentativa
        if ( $attempts >= 3 ) {
            self::log_action(
                0,
                'login_failed',
                'auth',
                0,
                'Login falhou ' . $attempts . 'x para usuário "' . sanitize_text_field( $username ) . '" — IP: ' . $ip,
                $ip
            );
        }
    }

    /**
     * Bloqueia IPs que passaram do limite de tentativas.
     * Roda antes de verificar a senha — se bloqueado, nem tenta.
     */
    public static function block_locked_ip( $user, $username, $password ) {
        if ( empty( $username ) && empty( $password ) ) {
            return $user;
        }

        $ip       = self::get_ip();
        $key      = 'cv_login_fail_' . md5( $ip );
        $attempts = (int) get_transient( $key );

        if ( $attempts >= self::MAX_ATTEMPTS ) {
            $minutos = round( self::LOCKOUT_TIME / 60 );

            self::log_action(
                0,
                'login_blocked',
                'auth',
                0,
                'IP bloqueado após ' . $attempts . ' tentativas. Usuário: "' . sanitize_text_field( $username ) . '"',
                $ip
            );

            return new WP_Error(
                'cv_too_many_attempts',
                sprintf(
                    'Muitas tentativas de login. Tente novamente em %d minutos.',
                    $minutos
                )
            );
        }

        return $user;
    }

    /**
     * Limpa o contador de falhas ao fazer login com sucesso.
     */
    public static function on_login_success( $user_login, $user ) {
        $ip  = self::get_ip();
        $key = 'cv_login_fail_' . md5( $ip );
        delete_transient( $key );
    }

    // ── HEADERS HTTP DE SEGURANÇA ─────────────────────────────────

    /**
     * Adiciona headers de segurança HTTP em todas as páginas.
     * Estes headers protegem contra ataques comuns como clickjacking,
     * MIME sniffing e exposição de referrer.
     */
    public static function send_security_headers() {

        // Impede que o site seja carregado em iframes de outros domínios (anti-clickjacking)
        if ( ! headers_sent() ) {
            header( 'X-Frame-Options: SAMEORIGIN' );

            // Impede que o navegador "adivinhe" o tipo de arquivo (anti-MIME sniffing)
            header( 'X-Content-Type-Options: nosniff' );

            // Controla quais informações são enviadas no Referer
            header( 'Referrer-Policy: strict-origin-when-cross-origin' );

            // Força HTTPS por 1 ano se o site já usa SSL
            if ( is_ssl() ) {
                header( 'Strict-Transport-Security: max-age=31536000; includeSubDomains' );
            }

            // Desativa detecção de XSS nos navegadores mais antigos
            header( 'X-XSS-Protection: 1; mode=block' );

            // Remove o header que revela a versão do WordPress
            header_remove( 'X-Powered-By' );
        }
    }

    // ── PROTEÇÃO DO DIRETÓRIO DO PLUGIN ──────────────────────────

    /**
     * Cria arquivo .htaccess dentro da pasta do plugin.
     * Impede que qualquer pessoa acesse os arquivos .php diretamente
     * pelo navegador — eles só devem ser executados pelo WordPress.
     */
    public static function create_htaccess() {
        $htaccess_path = CV_PLUGIN_DIR . '.htaccess';

        // Só cria se não existir
        if ( file_exists( $htaccess_path ) ) {
            return;
        }

        $content  = "# Canção Verdadeira — Bloqueia acesso direto aos arquivos PHP do plugin\n";
        $content .= "# Gerado automaticamente em " . date( 'd/m/Y H:i' ) . "\n\n";
        $content .= "<Files *.php>\n";
        $content .= "    Order allow,deny\n";
        $content .= "    Deny from all\n";
        $content .= "</Files>\n";

        // Exceção: permite o arquivo principal do plugin (necessário para WordPress)
        $content .= "\n<Files cancao-verdadeira.php>\n";
        $content .= "    Order allow,deny\n";
        $content .= "    Allow from all\n";
        $content .= "</Files>\n";

        file_put_contents( $htaccess_path, $content );
    }

    // ── ENDURECIMENTO DO WORDPRESS ────────────────────────────────

    /**
     * Remove informações que revelam detalhes técnicos do WordPress.
     * Dificulta a vida de quem tenta encontrar vulnerabilidades.
     */
    public static function harden_wordpress() {

        // Remove versão do WordPress do HTML e dos feeds
        remove_action( 'wp_head', 'wp_generator' );

        // Remove links desnecessários do <head>
        remove_action( 'wp_head', 'wlwmanifest_link' );
        remove_action( 'wp_head', 'rsd_link' );

        // Remove versão dos scripts e estilos do WordPress
        // (dificulta identificar a versão exata instalada)
        add_filter( 'style_loader_src',  array( __CLASS__, 'remove_version_from_url' ), 9999 );
        add_filter( 'script_loader_src', array( __CLASS__, 'remove_version_from_url' ), 9999 );
    }

    public static function remove_version_from_url( $src ) {
        // v2.27.1: mantém ?ver= nos arquivos do próprio site (plugin e tema
        // filho) — é o que obriga o navegador a baixar a versão nova depois
        // de cada atualização. Só esconde a versão do WordPress e de terceiros.
        if ( false !== strpos( $src, '/cancao-verdadeira/' ) || false !== strpos( $src, '/cv-child/' ) ) {
            return $src;
        }
        if ( strpos( $src, 'ver=' ) ) {
            $src = remove_query_arg( 'ver', $src );
        }
        return $src;
    }

    // ── LOG DE AÇÕES ──────────────────────────────────────────────

    /**
     * Registra uma ação na tabela cv_action_logs.
     * Usado para auditoria no painel administrativo.
     */
    public static function log_action( $user_id, $action, $object_type = '', $object_id = 0, $description = '', $ip = '' ) {
        global $wpdb;

        if ( empty( $ip ) ) {
            $ip = self::get_ip();
        }

        $wpdb->insert(
            $wpdb->prefix . 'cv_action_logs',
            array(
                'user_id'     => absint( $user_id ),
                'action'      => sanitize_text_field( $action ),
                'object_type' => sanitize_text_field( $object_type ),
                'object_id'   => absint( $object_id ),
                'description' => sanitize_textarea_field( $description ),
                'ip_address'  => sanitize_text_field( $ip ),
                'created_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%d', '%s', '%s', '%s' )
        );
    }

    // ── UTILITÁRIOS ───────────────────────────────────────────────

    /**
     * Retorna o IP real do visitante com suporte a Cloudflare e proxies.
     * Valida o IP antes de retornar para evitar falsificação.
     */
    private static function get_ip() {
        $keys = array(
            'HTTP_CF_CONNECTING_IP', // Cloudflare (mais confiável quando está na frente)
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR',
        );

        foreach ( $keys as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                // HTTP_X_FORWARDED_FOR pode ter múltiplos IPs separados por vírgula
                // O primeiro é o IP original do cliente
                $ip = trim( explode( ',', $_SERVER[ $key ] )[0] );

                if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
                    return $ip;
                }
            }
        }

        // Fallback para REMOTE_ADDR (pode ser IP privado em ambientes locais)
        return sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0' );
    }
}

CV_Security::init();
