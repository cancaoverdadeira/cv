<?php
// cancao-verdadeira/includes/seo/class-cv-seo-privadas.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// v2.69.0 (17/10/2026): páginas de conta e de sistema (login, cadastro, sair, minha conta,
// notificações, perfis…) e a "Página exemplo" do WordPress saem da busca do Google:
// ficam fora do mapa do site do Rank Math e recebem "noindex". Para o visitante nada muda.
// Achado ao enviar o sitemap no Google Search Console (preparação do lançamento dos 10 mil).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_SEO_Privadas {

    const SLUGS = array(
        'pagina-exemplo', 'login', 'cadastro', 'sair', 'recuperar-senha',
        'minha-conta', 'minha-area', 'meu-perfil', 'membro', 'membros',
        'notificacoes', 'minhas-playlists', 'assinar-gov-br',
    );

    public static function init() {
        add_filter( 'rank_math/sitemap/entry',   array( __CLASS__, 'fora_do_sitemap' ), 10, 3 );
        add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'noindex' ) );
    }

    private static function e_privada( $post ) {
        return $post && 'page' === $post->post_type && in_array( $post->post_name, self::SLUGS, true );
    }

    public static function fora_do_sitemap( $url, $tipo, $objeto ) {
        if ( 'post' === $tipo && self::e_privada( $objeto ) ) { return false; }
        return $url;
    }

    public static function noindex( $robots ) {
        if ( is_page() && self::e_privada( get_queried_object() ) ) {
            $robots['index']  = 'noindex';
            $robots['follow'] = 'follow';
        }
        return $robots;
    }
}

CV_SEO_Privadas::init();
