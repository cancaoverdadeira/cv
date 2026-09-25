<?php
// cancao-verdadeira/includes/apoio/class-cv-assinatura-pdf.php
// Criado em: 25/09/2026 (plugin v2.50.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Confere a ASSINATURA DIGITAL de um PDF (padrão PAdES: gov.br, certificado
// ICP-Brasil e outros). O que o site consegue saber sozinho:
//   1) se o PDF tem assinatura digital (campo /ByteRange + bloco PKCS#7/CMS);
//   2) quem assinou (nome do certificado; o CPF que vem junto é escondido)
//      e quem emitiu o certificado (ex.: gov.br, ICP-Brasil);
//   3) se a assinatura cobre o documento inteiro e se o arquivo NÃO foi
//      alterado depois de assinado (integridade): confere o resumo (hash)
//      do documento com o "messageDigest" gravado na assinatura e confere a
//      assinatura com a chave pública do certificado (openssl_verify).
//      Feito à mão porque openssl_cms_verify do PHP falha com assinatura
//      separada do conteúdo (caso do PDF) — conferido em 25/09/2026.
// O que NÃO confere: a validade jurídica da cadeia de certificados — isso é
// feito no validador oficial do ITI (link no painel). Funciona em PHP 7.2+.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Assinatura_PDF {

    const VALIDADOR_ITI = 'https://validar.iti.gov.br/';
    const TAMANHO_MAX   = 10485760; // 10 MB

    /**
     * Devolve um resumo:
     * pdf, assinado, qtd, assinante, emissor, govbr, icp, valido_ate,
     * cobre_tudo, integridade (true | false | null = não foi possível conferir), mensagem
     */
    public static function verificar( $caminho ) {
        $r = array(
            'pdf' => false, 'assinado' => false, 'qtd' => 0, 'assinante' => '', 'emissor' => '',
            'govbr' => false, 'icp' => false, 'valido_ate' => '', 'cobre_tudo' => false,
            'integridade' => null, 'mensagem' => '',
        );
        if ( ! is_readable( $caminho ) || filesize( $caminho ) > self::TAMANHO_MAX ) {
            $r['mensagem'] = 'Arquivo não encontrado ou maior que 10 MB.';
            return $r;
        }
        $dados = file_get_contents( $caminho );
        if ( '%PDF-' !== substr( $dados, 0, 5 ) ) {
            $r['mensagem'] = 'O arquivo não é um PDF.';
            return $r;
        }
        $r['pdf'] = true;

        // Cada assinatura tem um /ByteRange [ início tam1 início2 tam2 ]; o
        // intervalo entre as duas partes é o "<...>" com a assinatura em hexa.
        if ( ! preg_match_all( '/\/ByteRange\s*\[\s*(\d+)\s+(\d+)\s+(\d+)\s+(\d+)\s*\]/', $dados, $m, PREG_SET_ORDER ) ) {
            $r['mensagem'] = 'O PDF não tem assinatura digital.';
            return $r;
        }
        $r['qtd'] = count( $m );

        // A assinatura que chega mais longe no arquivo é a mais recente.
        usort( $m, function ( $a, $b ) { return ( $b[3] + $b[4] ) - ( $a[3] + $a[4] ); } );
        list( , $a0, $t1, $a2, $t2 ) = array_map( 'intval', $m[0] );
        $total = strlen( $dados );
        if ( 0 !== $a0 || $a0 + $t1 >= $a2 || $a2 + $t2 > $total ) {
            $r['mensagem'] = 'A assinatura do PDF está corrompida.';
            return $r;
        }
        $depois = substr( $dados, $a2 + $t2 );
        $r['cobre_tudo'] = ( '' === trim( $depois ) );
        // Assinadores oficiais (padrão PAdES-LTV) podem acrescentar, depois da
        // assinatura, só os dados de validação (/DSS) — isso é legítimo.
        if ( ! $r['cobre_tudo'] && false !== strpos( $depois, '/DSS' ) && false === strpos( $depois, '/Type /Page' ) && false === strpos( $depois, '/Type/Page' ) ) {
            $r['cobre_tudo'] = true;
        }

        $hex = substr( $dados, $a0 + $t1 + 1, $a2 - ( $a0 + $t1 ) - 2 ); // sem "<" e ">"
        $der = self::der_da_assinatura( $hex );
        if ( '' === $der ) {
            $r['mensagem'] = 'Não foi possível ler a assinatura do PDF.';
            return $r;
        }
        $r['assinado'] = true;

        // Certificado de quem assinou
        $certs = self::certificados( $der );
        $cert  = self::certificado_do_assinante( $certs );
        if ( $cert ) {
            $sub = isset( $cert['subject']['CN'] ) ? (array) $cert['subject']['CN'] : array( '' );
            $emi = isset( $cert['issuer']['CN'] ) ? (array) $cert['issuer']['CN'] : array( '' );
            $r['assinante']  = self::sem_cpf( end( $sub ) );
            $r['emissor']    = (string) end( $emi );
            $org             = isset( $cert['issuer']['O'] ) ? implode( ' ', (array) $cert['issuer']['O'] ) : '';
            $r['govbr']      = false !== stripos( $r['emissor'], 'Governo Federal' ) || false !== stripos( $r['emissor'], 'gov.br' );
            $r['icp']        = false !== stripos( $org, 'ICP-Brasil' );
            $r['valido_ate'] = isset( $cert['validTo_time_t'] ) ? gmdate( 'Y-m-d', $cert['validTo_time_t'] ) : '';
        }

        // Integridade: o conteúdo assinado ainda confere com a assinatura?
        $r['integridade'] = self::conferir_integridade( substr( $dados, $a0, $t1 ) . substr( $dados, $a2, $t2 ), $der, $certs );

        if ( false === $r['integridade'] ) {
            $r['mensagem'] = 'O PDF foi ALTERADO depois de assinado. Assine de novo e envie o arquivo sem mudanças.';
        } elseif ( ! $r['cobre_tudo'] ) {
            $r['mensagem'] = 'O PDF recebeu mudanças depois da assinatura. Envie o arquivo exatamente como saiu do assinador.';
        } else {
            $r['mensagem'] = 'Assinatura digital encontrada' . ( $r['assinante'] ? ': ' . $r['assinante'] : '' ) . '.';
        }
        return $r;
    }

    /** Aceita para seguir: assinado, cobre o documento e não foi alterado. */
    public static function aceito( $r ) {
        return ! empty( $r['assinado'] ) && ! empty( $r['cobre_tudo'] ) && false !== $r['integridade'];
    }

    /** Converte o hexa (com zeros de sobra no fim) no bloco DER exato. */
    private static function der_da_assinatura( $hex ) {
        $hex = preg_replace( '/[^0-9A-Fa-f]/', '', $hex );
        if ( strlen( $hex ) < 8 || strlen( $hex ) % 2 ) { return ''; }
        $bin = hex2bin( $hex );
        if ( "\x30" !== $bin[0] ) { return ''; }
        $b1 = ord( $bin[1] );
        if ( $b1 < 0x80 ) {
            $total = 2 + $b1;
        } elseif ( 0x80 === $b1 ) {
            // Tamanho indefinido: tira só os pares "00" de sobra do fim
            return hex2bin( preg_replace( '/(00)+$/', '', $hex ) . '0000' );
        } else {
            $n = $b1 & 0x7F;
            $tam = 0;
            for ( $i = 0; $i < $n; $i++ ) { $tam = ( $tam << 8 ) | ord( $bin[ 2 + $i ] ); }
            $total = 2 + $n + $tam;
        }
        return $total <= strlen( $bin ) ? substr( $bin, 0, $total ) : '';
    }

    private static function pem( $der ) {
        return "-----BEGIN PKCS7-----\n" . chunk_split( base64_encode( $der ), 64, "\n" ) . "-----END PKCS7-----\n";
    }

    /** Certificados (PEM) que vêm dentro da assinatura. */
    private static function certificados( $der ) {
        $certs = array();
        if ( ! function_exists( 'openssl_pkcs7_read' ) ) { return $certs; }
        return @openssl_pkcs7_read( self::pem( $der ), $certs ) ? $certs : array();
    }

    /** O certificado "final" (não é autoridade certificadora), já lido. */
    private static function certificado_do_assinante( $certs ) {
        $primeiro = null;
        foreach ( $certs as $pem ) {
            $c = openssl_x509_parse( $pem );
            if ( ! $c ) { continue; }
            if ( null === $primeiro ) { $primeiro = $c; }
            $bc = isset( $c['extensions']['basicConstraints'] ) ? $c['extensions']['basicConstraints'] : '';
            if ( false === stripos( $bc, 'CA:TRUE' ) ) { return $c; }
        }
        return $primeiro;
    }

    // ── Integridade (CMS SignedData lido à mão, só o necessário) ────────

    /** Um elemento DER: array( tag, início do valor, tamanho, início, tamanho total ) ou null. */
    private static function der_elemento( $bin, $pos ) {
        if ( $pos + 2 > strlen( $bin ) ) { return null; }
        $tag = ord( $bin[ $pos ] );
        $b   = ord( $bin[ $pos + 1 ] );
        $cab = 2;
        if ( $b < 0x80 ) {
            $tam = $b;
        } else {
            $n = $b & 0x7F;
            if ( 0 === $n || $n > 4 ) { return null; }
            $tam = 0;
            for ( $i = 0; $i < $n; $i++ ) { $tam = ( $tam << 8 ) | ord( $bin[ $pos + 2 + $i ] ); }
            $cab += $n;
        }
        if ( $pos + $cab + $tam > strlen( $bin ) ) { return null; }
        return array( $tag, $pos + $cab, $tam, $pos, $cab + $tam );
    }

    private static function der_filhos( $bin, $el ) {
        $filhos = array();
        $pos = $el[1];
        $fim = $el[1] + $el[2];
        while ( $pos < $fim ) {
            $f = self::der_elemento( $bin, $pos );
            if ( ! $f ) { break; }
            $filhos[] = $f;
            $pos = $f[3] + $f[4];
        }
        return $filhos;
    }

    private static function algoritmo( $oid_hex ) {
        $mapa = array(
            '608648016503040201' => array( 'sha256', OPENSSL_ALGO_SHA256 ),
            '608648016503040202' => array( 'sha384', OPENSSL_ALGO_SHA384 ),
            '608648016503040203' => array( 'sha512', OPENSSL_ALGO_SHA512 ),
            '2b0e03021a'         => array( 'sha1',   OPENSSL_ALGO_SHA1 ),
        );
        return isset( $mapa[ $oid_hex ] ) ? $mapa[ $oid_hex ] : null;
    }

    /** true = confere · false = alterado · null = não foi possível conferir. */
    private static function conferir_integridade( $conteudo, $der, $certs ) {
        if ( empty( $certs ) || ! function_exists( 'openssl_verify' ) ) { return null; }
        // ContentInfo → [0] → SignedData → último SET = signerInfos → 1º SignerInfo
        $raiz = self::der_elemento( $der, 0 );
        $f    = $raiz ? self::der_filhos( $der, $raiz ) : array();
        if ( count( $f ) < 2 || 0xA0 !== $f[1][0] ) { return null; }
        $sd  = self::der_filhos( $der, $f[1] );
        $sd  = $sd ? self::der_filhos( $der, $sd[0] ) : array();
        $set = null;
        foreach ( $sd as $e ) { if ( 0x31 === $e[0] ) { $set = $e; } }
        $si  = $set ? self::der_filhos( $der, $set ) : array();
        if ( empty( $si ) ) { return null; }
        $partes = self::der_filhos( $der, $si[0] );
        if ( count( $partes ) < 5 ) { return null; }

        // digestAlgorithm é o 3º campo; depois vêm [0] signedAttrs (opcional), algoritmo e assinatura
        $alg_oid = self::der_filhos( $der, $partes[2] );
        $alg     = $alg_oid ? self::algoritmo( bin2hex( substr( $der, $alg_oid[0][1], $alg_oid[0][2] ) ) ) : null;
        if ( ! $alg ) { return null; }
        $attrs = null;
        $assin = null;
        for ( $i = 3; $i < count( $partes ); $i++ ) {
            if ( 0xA0 === $partes[ $i ][0] ) { $attrs = $partes[ $i ]; }
            if ( 0x04 === $partes[ $i ][0] ) { $assin = substr( $der, $partes[ $i ][1], $partes[ $i ][2] ); break; }
        }
        if ( null === $assin ) { return null; }
        $resumo = hash( $alg[0], $conteudo, true );

        if ( $attrs ) {
            // 1) O resumo do documento tem de ser igual ao "messageDigest" assinado
            $md = null;
            foreach ( self::der_filhos( $der, $attrs ) as $at ) {
                $c = self::der_filhos( $der, $at );
                if ( count( $c ) >= 2 && '2a864886f70d010904' === bin2hex( substr( $der, $c[0][1], $c[0][2] ) ) ) {
                    $v  = self::der_filhos( $der, $c[1] );
                    $md = $v ? substr( $der, $v[0][1], $v[0][2] ) : null;
                }
            }
            if ( null === $md ) { return null; }
            if ( ! hash_equals( $md, $resumo ) ) { return false; }
            // 2) A assinatura vale sobre os atributos (com a etiqueta SET no lugar de [0])
            $dados = "\x31" . substr( $der, $attrs[3] + 1, $attrs[4] - 1 );
        } else {
            $dados = $conteudo;
        }
        foreach ( $certs as $pem ) {
            $chave = openssl_pkey_get_public( $pem );
            if ( $chave && 1 === openssl_verify( $dados, $assin, $chave, $alg[1] ) ) { return true; }
        }
        return false;
    }

    /** "MARIA DA SILVA:12345678900" → "MARIA DA SILVA" (não expor CPF). */
    private static function sem_cpf( $nome ) {
        return trim( preg_replace( '/[:\s]*\d{11}\s*$/', '', (string) $nome ) );
    }
}
