<?php
// cancao-verdadeira/includes/seo/class-cv-ads-txt.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Arquivo /ads.txt, v2.73.9 (30/10/2026).
// O Google AdSense pede o ads.txt na raiz do site, dizendo quem pode vender
// anúncios nele. Sem FTP, o arquivo não existe no servidor: o plugin responde
// ao endereço /ads.txt com o texto (sem criar arquivo nenhum).
// A conta é a do e-mail do AdSense de 15/10 (pub-3556110817945784); para
// mudar, trocar a constante PUB ou usar o filtro cv_ads_txt_linhas.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Ads_Txt {

    const PUB = 'pub-3556110817945784';

    public static function init() {
        add_action( 'init', array( __CLASS__, 'responder' ), 0 );
    }

    /** Se o endereço pedido for /ads.txt, entrega o texto e para por aqui. */
    public static function responder() {
        $uri  = isset( $_SERVER['REQUEST_URI'] ) ? $_SERVER['REQUEST_URI'] : '';
        $path = (string) wp_parse_url( $uri, PHP_URL_PATH );
        if ( '/ads.txt' !== $path ) { return; }

        $linhas = apply_filters( 'cv_ads_txt_linhas', array(
            'google.com, ' . self::PUB . ', DIRECT, f08c47fec0942fa0',
        ) );

        status_header( 200 );
        header( 'Content-Type: text/plain; charset=utf-8' );
        header( 'Cache-Control: public, max-age=86400' );
        echo implode( "\n", $linhas ) . "\n";
        exit;
    }
}

CV_Ads_Txt::init();
