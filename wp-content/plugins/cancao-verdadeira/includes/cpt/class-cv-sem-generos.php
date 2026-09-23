<?php
// cancao-verdadeira/includes/cpt/class-cv-sem-generos.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Remoção dos gêneros (v2.26.0). Substitui class-cv-taxonomies.php.
// Motivo  : o site é todo sertanejo; classificar por gênero era informação
//           desnecessária. As taxonomias cv_genre (/genero/) e cv_subcategory
//           (/estilo/) deixaram de ser registradas.
// Faz     : redireciona (301) endereços antigos /genero/... e /estilo/... e o
//           filtro ?genero= para o catálogo /musicas/, sem perder SEO.
// Dados   : os termos antigos continuam no banco, sem uso (ver docs).
// Gerado  : 2026-09-23

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Sem_Generos {

    public static function init() {
        add_action( 'template_redirect', array( __CLASS__, 'redirect' ), 1 );
    }

    public static function redirect() {
        $path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
        $home = rtrim( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
        $rel  = ltrim( substr( $path, strlen( $home ) ), '/' );

        $old_path  = (bool) preg_match( '#^(genero|estilo)(/|$)#', $rel );
        $old_param = isset( $_GET['genero'] ) || isset( $_GET['cv_genre'] ) || isset( $_GET['cv_subcategory'] );

        if ( $old_path || ( $old_param && ( is_post_type_archive( 'musica' ) || 0 === strpos( $rel, 'musicas' ) ) ) ) {
            wp_safe_redirect( home_url( '/musicas/' ), 301 );
            exit;
        }
    }
}

CV_Sem_Generos::init();
