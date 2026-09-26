<?php
// cv-migracao/includes/class-cvm-endereco.php
// Projeto : Canção Verdadeira — plugin provisório da migração
// Função  : depois de restaurar no site no ar um backup feito no computador,
//           as opções "home" e "siteurl" ainda dizem http://cv.local e o
//           WordPress mandaria o visitante para lá (o site pareceria quebrado).
//           No 1º acesso pelo endereço de verdade, esta classe grava o
//           endereço certo e recarrega a página. Proteções: só age se o
//           endereço pedido estiver em CVM_DESTINOS, se home/siteurl ainda
//           tiverem "cv.local" e se NÃO estiver no computador. Uma vez só.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CVM_Endereco {

    public static function init() {
        add_action( 'plugins_loaded', array( __CLASS__, 'corrigir_se_preciso' ), 0 );
    }

    /** Host pedido pelo navegador, em minúsculas e sem porta. */
    public static function host_pedido() {
        $h = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) $_SERVER['HTTP_HOST'] ) : '';
        return preg_replace( '/:\d+$/', '', $h );
    }

    public static function host_e_destino( $host ) {
        return '' !== $host && in_array( $host, array_map( 'trim', explode( ',', CVM_DESTINOS ) ), true );
    }

    /** O navegador chegou por https? (Hostnet pode usar proxy na frente) */
    public static function pedido_https() {
        if ( is_ssl() ) { return true; }
        $proto = isset( $_SERVER['HTTP_X_FORWARDED_PROTO'] ) ? strtolower( (string) $_SERVER['HTTP_X_FORWARDED_PROTO'] ) : '';
        return 'https' === $proto;
    }

    /**
     * Decide sem mexer em nada (usada também nos testes).
     * Devolve o endereço novo (ex.: https://cancaoverdadeira.com.br) ou ''.
     */
    public static function endereco_novo( $ambiente, $host, $https, $home, $siteurl ) {
        if ( in_array( $ambiente, array( 'local', 'development' ), true ) ) { return ''; }
        if ( ! self::host_e_destino( $host ) ) { return ''; }
        $antigo = ( false !== strpos( (string) $home, CVM_ORIGEM_HOST ) ) || ( false !== strpos( (string) $siteurl, CVM_ORIGEM_HOST ) );
        if ( ! $antigo ) { return ''; }
        return ( $https ? 'https://' : 'http://' ) . $host;
    }

    public static function corrigir_se_preciso() {
        if ( defined( 'WP_CLI' ) && WP_CLI ) { return; }
        if ( defined( 'WP_HOME' ) || defined( 'WP_SITEURL' ) ) { return; } // o wp-config manda no endereço
        $novo = self::endereco_novo(
            wp_get_environment_type(),
            self::host_pedido(),
            self::pedido_https(),
            get_option( 'home' ),
            get_option( 'siteurl' )
        );
        if ( '' === $novo ) { return; }

        update_option( 'cvm_endereco_antigo', array( 'home' => get_option( 'home' ), 'siteurl' => get_option( 'siteurl' ), 'quando' => current_time( 'mysql' ) ), false );
        update_option( 'home', $novo );
        update_option( 'siteurl', $novo );
        update_option( 'cvm_endereco_corrigido', $novo, false );

        // Recarrega a mesma página já com o endereço certo (cookies e links novos)
        $caminho = isset( $_SERVER['REQUEST_URI'] ) ? (string) $_SERVER['REQUEST_URI'] : '/';
        if ( '' === $caminho || '/' !== $caminho[0] ) { $caminho = '/'; }
        nocache_headers();
        header( 'Location: ' . $novo . $caminho, true, 302 );
        exit;
    }
}
