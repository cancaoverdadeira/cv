<?php
// cancao-verdadeira/includes/apoio/class-cv-docx.php
// Criado em: 25/09/2026 (plugin v2.49.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gera arquivos do Word (.docx) simples, sem biblioteca externa: um .docx é
// um ZIP com alguns XML (padrão Office Open XML). Aceita blocos do tipo
// titulo, subtitulo, paragrafo, item (lista) e nota (texto pequeno).
// Usado pelas "Nossas propostas" e pelo "Modelo de contrato" do parceiro
// (CV_Parceria_Docs). Precisa da extensão ZipArchive do PHP; sem ela,
// entrega um .doc em HTML, que o Word também abre.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Docx {

    /**
     * Envia o arquivo para o navegador e encerra.
     * $blocos = array( array( 'tipo' => 'titulo'|'subtitulo'|'paragrafo'|'item'|'nota', 'texto' => '...' ), ... )
     */
    public static function enviar( $nome_arquivo, $blocos ) {
        $nome_arquivo = sanitize_file_name( $nome_arquivo );
        nocache_headers();
        if ( ! class_exists( 'ZipArchive' ) ) {
            header( 'Content-Type: application/msword; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="' . preg_replace( '/\.docx$/', '.doc', $nome_arquivo ) . '"' );
            echo '<html><head><meta charset="utf-8"></head><body>' . self::html( $blocos ) . '</body></html>'; // phpcs:ignore — já escapado em html()
            exit;
        }
        $arquivo = self::gerar( $blocos );
        if ( ! $arquivo ) { wp_die( 'Não foi possível gerar o documento.' ); }
        header( 'Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document' );
        header( 'Content-Disposition: attachment; filename="' . $nome_arquivo . '"' );
        header( 'Content-Length: ' . filesize( $arquivo ) );
        readfile( $arquivo );
        @unlink( $arquivo );
        exit;
    }

    /** Monta o .docx num arquivo temporário e devolve o caminho (ou ''). */
    public static function gerar( $blocos ) {
        if ( ! function_exists( 'wp_tempnam' ) ) { require_once ABSPATH . 'wp-admin/includes/file.php'; }
        $caminho = wp_tempnam( 'cv-doc' );
        $zip = new ZipArchive();
        if ( true !== $zip->open( $caminho, ZipArchive::OVERWRITE ) ) { return ''; }

        $zip->addFromString( '[Content_Types].xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/>'
            . '<Override PartName="/word/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.styles+xml"/>'
            . '</Types>' );
        $zip->addFromString( '_rels/.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/>'
            . '</Relationships>' );
        $zip->addFromString( 'word/_rels/document.xml.rels',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>' );
        // Letra padrão: Calibri 11, com espaço entre parágrafos
        $zip->addFromString( 'word/styles.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:styles xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main">'
            . '<w:docDefaults><w:rPrDefault><w:rPr><w:rFonts w:ascii="Calibri" w:hAnsi="Calibri" w:cs="Calibri"/><w:sz w:val="22"/><w:lang w:val="pt-BR"/></w:rPr></w:rPrDefault>'
            . '<w:pPrDefault><w:pPr><w:spacing w:after="120" w:line="276" w:lineRule="auto"/></w:pPr></w:pPrDefault></w:docDefaults>'
            . '</w:styles>' );

        $corpo = '';
        foreach ( $blocos as $b ) { $corpo .= self::paragrafo( $b ); }
        $zip->addFromString( 'word/document.xml',
            '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>'
            . $corpo
            . '<w:sectPr><w:pgSz w:w="11906" w:h="16838"/><w:pgMar w:top="1417" w:right="1417" w:bottom="1417" w:left="1417" w:header="708" w:footer="708" w:gutter="0"/></w:sectPr>'
            . '</w:body></w:document>' );
        $zip->close();
        return $caminho;
    }

    private static function x( $t ) {
        return htmlspecialchars( (string) $t, ENT_QUOTES | ENT_XML1, 'UTF-8' );
    }

    /** Um bloco vira um parágrafo do Word (A4, margens de 2,5 cm). */
    private static function paragrafo( $b ) {
        $tipo  = isset( $b['tipo'] ) ? $b['tipo'] : 'paragrafo';
        $texto = isset( $b['texto'] ) ? $b['texto'] : '';
        $ppr = ''; $rpr = '';
        switch ( $tipo ) {
            case 'titulo':
                $ppr = '<w:jc w:val="center"/><w:spacing w:before="120" w:after="240"/>';
                $rpr = '<w:b/><w:sz w:val="30"/><w:color w:val="7B3A22"/>';
                break;
            case 'subtitulo':
                $ppr = '<w:keepNext/><w:spacing w:before="240" w:after="80"/>';
                $rpr = '<w:b/><w:sz w:val="24"/><w:color w:val="7B3A22"/>';
                break;
            case 'item':
                $ppr   = '<w:ind w:left="567" w:hanging="283"/><w:jc w:val="both"/>';
                $texto = '•  ' . $texto;
                break;
            case 'nota':
                $ppr = '<w:jc w:val="both"/><w:spacing w:before="240"/>';
                $rpr = '<w:i/><w:sz w:val="18"/><w:color w:val="6B4C3B"/>';
                break;
            default:
                $ppr = '<w:jc w:val="both"/>';
        }
        // Quebras de linha dentro do bloco viram <w:br/>
        $runs = array();
        foreach ( explode( "\n", $texto ) as $linha ) {
            $runs[] = '<w:t xml:space="preserve">' . self::x( $linha ) . '</w:t>';
        }
        return '<w:p><w:pPr>' . $ppr . '</w:pPr><w:r>' . ( $rpr ? '<w:rPr>' . $rpr . '</w:rPr>' : '' )
            . implode( '<w:br/>', $runs ) . '</w:r></w:p>';
    }

    /** Os mesmos blocos em HTML (tela do site e reserva sem ZipArchive). */
    public static function html( $blocos ) {
        $h = '';
        $lista = false;
        foreach ( $blocos as $b ) {
            $tipo  = isset( $b['tipo'] ) ? $b['tipo'] : 'paragrafo';
            $texto = nl2br( esc_html( isset( $b['texto'] ) ? $b['texto'] : '' ) );
            if ( 'item' === $tipo && ! $lista ) { $h .= '<ul>'; $lista = true; }
            if ( 'item' !== $tipo && $lista )   { $h .= '</ul>'; $lista = false; }
            switch ( $tipo ) {
                case 'titulo':    $h .= '<h3 class="cv-doc-titulo">' . $texto . '</h3>'; break;
                case 'subtitulo': $h .= '<h4 class="cv-doc-subtitulo">' . $texto . '</h4>'; break;
                case 'item':      $h .= '<li>' . $texto . '</li>'; break;
                case 'nota':      $h .= '<p class="cv-doc-nota">' . $texto . '</p>'; break;
                default:          $h .= '<p>' . $texto . '</p>';
            }
        }
        if ( $lista ) { $h .= '</ul>'; }
        return $h;
    }
}
