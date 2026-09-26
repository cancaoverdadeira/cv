<?php
// cv-migracao/includes/class-cvm-troca.php
// Projeto : Canção Verdadeira — plugin provisório da migração
// Função  : troca "cv.local" (e a pasta do computador) pelo endereço e pela
//           pasta do site no ar, em TODAS as tabelas do WordPress (prefixo).
// Cuidado : muitos plugins guardam dados "serializados" (s:10:"texto"), em
//           que o tamanho vem escrito junto; trocar o texto direto estragaria
//           o dado. Aqui cada valor é aberto (unserialize, só aceitando a
//           classe stdClass), trocado e guardado de novo (serialize). Objetos
//           de outras classes e dados corrompidos são PULADOS e contados.
//           Também troca as formas "http:\/\/cv.local" (JSON do Elementor) e
//           "http%3A%2F%2Fcv.local" (links codificados). A coluna guid dos
//           posts não é trocada (recomendação do WordPress).
// Uso     : lote( $tabela, $cursor, $aplicar ) — em lotes pela chave primária;
//           com $aplicar = false só conta (PRÉVIA) e devolve exemplos.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CVM_Troca {

    const LOTE = 300;

    private static $trocas = 0;
    private static $pulou  = false;

    /** Pares "de → para", do mais comprido ao mais curto. */
    public static function pares( $destino_url, $destino_pasta ) {
        $destino_url   = untrailingslashit( $destino_url );
        $host          = (string) wp_parse_url( $destino_url, PHP_URL_HOST );
        $esc           = str_replace( '/', '\/', $destino_url );
        $cod           = rawurlencode( $destino_url );
        $o             = CVM_ORIGEM_HOST;
        $pares = array(
            'https://' . $o         => $destino_url,
            'http://' . $o          => $destino_url,
            'https:\/\/' . $o       => $esc,
            'http:\/\/' . $o        => $esc,
            'https%3A%2F%2F' . $o   => $cod,
            'http%3A%2F%2F' . $o    => $cod,
            '\/\/' . $o             => '\/\/' . $host,
            '//' . $o               => '//' . $host,
        );
        $destino_pasta = untrailingslashit( (string) $destino_pasta );
        if ( '' !== $destino_pasta && $destino_pasta !== CVM_ORIGEM_PASTA ) {
            $pares[ str_replace( '/', '\/', CVM_ORIGEM_PASTA ) ] = str_replace( '/', '\/', $destino_pasta );
            $pares[ CVM_ORIGEM_PASTA ] = $destino_pasta;
        }
        uksort( $pares, array( __CLASS__, 'mais_comprido_primeiro' ) );
        return $pares;
    }

    public static function mais_comprido_primeiro( $a, $b ) {
        return strlen( $b ) - strlen( $a );
    }

    /** Tabelas do site com uma chave primária simples e as colunas de texto. */
    public static function tabelas() {
        global $wpdb;
        $lista = array();
        $nomes = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
        foreach ( $nomes as $t ) {
            $pk = array(); $textos = array();
            foreach ( $wpdb->get_results( "SHOW COLUMNS FROM `{$t}`" ) as $c ) {
                if ( 'PRI' === $c->Key ) { $pk[] = $c->Field; }
                if ( preg_match( '/char|text|blob|json/i', $c->Type ) ) { $textos[] = $c->Field; }
            }
            if ( $wpdb->posts === $t ) { $textos = array_values( array_diff( $textos, array( 'guid' ) ) ); }
            $lista[ $t ] = array(
                'pk'     => 1 === count( $pk ) ? $pk[0] : '',
                'textos' => array_values( array_diff( $textos, $pk ) ),
            );
        }
        return $lista;
    }

    /**
     * Processa um lote de uma tabela.
     * Devolve: linhas (com troca), trocas (quantas vezes), puladas, cursor, fim, exemplos.
     */
    public static function lote( $tabela, $cursor, $aplicar, $pares ) {
        global $wpdb;
        $tabs = self::tabelas();
        $res  = array( 'linhas' => 0, 'trocas' => 0, 'puladas' => 0, 'cursor' => $cursor, 'fim' => true, 'exemplos' => array(), 'aviso' => '' );
        if ( ! isset( $tabs[ $tabela ] ) ) { $res['aviso'] = 'tabela desconhecida'; return $res; }
        $pk = $tabs[ $tabela ]['pk']; $cols = $tabs[ $tabela ]['textos'];
        if ( ! $cols ) { return $res; }
        if ( '' === $pk ) { $res['aviso'] = 'sem chave primária simples (não trocada)'; return $res; }

        $marcas = array( '%' . $wpdb->esc_like( CVM_ORIGEM_HOST ) . '%', '%' . $wpdb->esc_like( 'Local Sites' ) . '%' );
        $onde = array(); $vals = array();
        foreach ( $cols as $c ) { foreach ( $marcas as $m ) { $onde[] = "`{$c}` LIKE %s"; $vals[] = $m; } }
        $vals[] = $cursor; $vals[] = self::LOTE;
        $sql = "SELECT `{$pk}`, `" . implode( '`, `', $cols ) . "` FROM `{$tabela}` WHERE (" . implode( ' OR ', $onde ) . ") AND `{$pk}` > %s ORDER BY `{$pk}` LIMIT %d";
        $linhas = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A );

        foreach ( $linhas as $l ) {
            $id = $l[ $pk ];
            $res['cursor'] = $id;
            if ( $wpdb->options === $tabela && isset( $l['option_name'] ) && 0 === strpos( $l['option_name'], 'cvm_' ) ) { continue; }
            $mudou = array();
            foreach ( $cols as $c ) {
                if ( null === $l[ $c ] || '' === $l[ $c ] ) { continue; }
                $novo = self::trocar_valor( $l[ $c ], $pares );
                if ( self::$pulou ) { $res['puladas']++; continue; }
                if ( self::$trocas > 0 && $novo !== $l[ $c ] ) {
                    $mudou[ $c ] = $novo;
                    $res['trocas'] += self::$trocas;
                    if ( count( $res['exemplos'] ) < 3 ) { $res['exemplos'][] = self::exemplo( $tabela, $id, $c, $l[ $c ], $novo, $pares ); }
                }
            }
            if ( $mudou ) {
                $res['linhas']++;
                if ( $aplicar ) { $wpdb->update( $tabela, $mudou, array( $pk => $id ) ); }
            }
        }
        $res['fim'] = count( $linhas ) < self::LOTE;
        return $res;
    }

    /** Troca num valor do banco, respeitando dados serializados. */
    public static function trocar_valor( $valor, $pares ) {
        self::$trocas = 0; self::$pulou = false; // cada valor começa do zero
        return self::valor( $valor, $pares );
    }

    private static function valor( $valor, $pares ) {
        if ( is_serialized( $valor ) ) {
            $dado = @unserialize( $valor, array( 'allowed_classes' => array( 'stdClass' ) ) ); // phpcs:ignore
            if ( false === $dado && 'b:0;' !== $valor ) { self::$pulou = true; return $valor; } // corrompido
            $dado = self::trocar_dado( $dado, $pares );
            if ( self::$pulou ) { return $valor; }
            return serialize( $dado ); // phpcs:ignore
        }
        return self::trocar_texto( $valor, $pares );
    }

    private static function trocar_dado( $dado, $pares ) {
        if ( is_string( $dado ) ) {
            return is_serialized( $dado ) ? self::valor( $dado, $pares ) : self::trocar_texto( $dado, $pares );
        }
        if ( is_array( $dado ) ) {
            foreach ( $dado as $k => $v ) { $dado[ $k ] = self::trocar_dado( $v, $pares ); if ( self::$pulou ) { return $dado; } }
            return $dado;
        }
        if ( is_object( $dado ) ) {
            if ( ! ( $dado instanceof stdClass ) ) { self::$pulou = true; return $dado; } // objeto de plugin: não mexe
            foreach ( get_object_vars( $dado ) as $k => $v ) { $dado->$k = self::trocar_dado( $v, $pares ); if ( self::$pulou ) { return $dado; } }
            return $dado;
        }
        return $dado; // número, true/false, null
    }

    private static function trocar_texto( $texto, $pares ) {
        $n = 0;
        $novo = str_replace( array_keys( $pares ), array_values( $pares ), $texto, $n );
        self::$trocas += $n;
        return $novo;
    }

    private static function exemplo( $tabela, $id, $coluna, $antes, $depois, $pares ) {
        $pos = false;
        foreach ( array_keys( $pares ) as $de ) { $p = strpos( $antes, $de ); if ( false !== $p && ( false === $pos || $p < $pos ) ) { $pos = $p; } }
        $ini = max( 0, (int) $pos - 40 );
        return array(
            'onde'   => $tabela . ' #' . $id . ' (' . $coluna . ')',
            'antes'  => wp_check_invalid_utf8( substr( $antes, $ini, 120 ), true ),
            'depois' => wp_check_invalid_utf8( substr( $depois, $ini, 140 ), true ),
        );
    }
}
