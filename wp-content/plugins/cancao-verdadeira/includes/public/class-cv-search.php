<?php
// cancao-verdadeira/includes/public/class-cv-search.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Busca do site (v2.27.0) — ponto único usado pela página de busca,
//           pela 404, pelo autocomplete e pela REST /cv/v1/busca.
// Motor   : Relevanssi (especificação): título peso 10, letra peso 3 e ficha
//           técnica (compositor, artista, álbum) peso 2. Trecho da letra com o
//           termo em destaque. Sem Relevanssi, cai numa busca LIKE equivalente.
// Setup   : na 1ª carga ajusta as opções do Relevanssi (cv_busca_version);
//           o índice é reconstruído em Relevanssi → Indexação (ou via script).
// Também  : /?s=termo (busca nativa) redireciona para /buscar-musicas/?q=termo.
// Gerado  : 2026-09-23 (substitui o antigo CV_Search, que não era usado)

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Search {

    const SETUP_VERSION = '2';
    const PAGE_SLUG     = 'buscar-musicas';

    // Palavras ignoradas: só artigos, preposições e conjunções. A lista pt_BR
    // que vem com o Relevanssi descartava "caminho", "verdade", "verdadeiro",
    // "tempo", "novo"… — palavras que importam em letras de música.
    const STOPWORDS = 'a,o,as,os,um,uma,uns,umas,de,do,da,dos,das,no,na,nos,nas,em,ao,aos,à,às,pelo,pela,pelos,pelas,por,para,com,e,ou,que,se,é';

    // Campos da ficha técnica indexados (peso 2).
    const FICHA_FIELDS = array( '_cv_compositor', '_cv_artista', '_cv_album' );

    public static function init() {
        add_action( 'admin_init',          array( __CLASS__, 'maybe_setup_relevanssi' ) );
        add_filter( 'relevanssi_match',    array( __CLASS__, 'ficha_weight' ), 10, 2 );
        add_filter( 'relevanssi_post_ok',  array( __CLASS__, 'hide_inactive' ), 20, 2 ); // depois do padrão do Relevanssi (10), que sobrescreve
        add_action( 'template_redirect',   array( __CLASS__, 'redirect_native_search' ), 2 );
        add_filter( 'posts_search',        array( __CLASS__, 'fallback_sql' ), 20, 2 );
        add_filter( 'rank_math/frontend/robots', array( __CLASS__, 'noindex_results' ) );
    }

    public static function relevanssi_on() {
        return function_exists( 'relevanssi_do_query' );
    }

    public static function url( $termo = '' ) {
        $base = home_url( '/' . self::PAGE_SLUG . '/' );
        return '' === $termo ? $base : add_query_arg( 'q', rawurlencode( $termo ), $base );
    }

    // ── Configuração do Relevanssi (uma vez) ────────────────────────

    public static function maybe_setup_relevanssi() {
        if ( ! self::relevanssi_on() ) { return; }
        if ( get_option( 'cv_busca_version' ) === self::SETUP_VERSION ) { return; }

        update_option( 'relevanssi_index_post_types', array( 'musica', 'post' ) );
        update_option( 'relevanssi_index_fields',     implode( ',', self::FICHA_FIELDS ) );
        update_option( 'relevanssi_title_boost',      '10' ); // título: peso 10
        update_option( 'relevanssi_content_boost',    '3' );  // letra (post_content): peso 3
        update_option( 'relevanssi_excerpts',         'on' ); // trecho da letra nos resultados
        update_option( 'relevanssi_excerpt_length',   '24' );
        update_option( 'relevanssi_excerpt_type',     'words' );
        update_option( 'relevanssi_highlight',        'mark' );
        update_option( 'relevanssi_implicit_operator','AND' ); // todas as palavras…
        update_option( 'relevanssi_disable_or_fallback', 'off' ); // …e, sem resultado, qualquer uma
        update_option( 'relevanssi_fuzzy',            'always' ); // "saud" acha "saudade"
        $stop = get_option( 'relevanssi_stopwords', array() );
        $stop = is_array( $stop ) ? $stop : array();
        $stop[ get_locale() ] = self::STOPWORDS;
        update_option( 'relevanssi_stopwords', $stop );
        update_option( 'cv_busca_version', self::SETUP_VERSION );
        update_option( 'cv_busca_reindex_pendente', '1' );
    }

    // Ficha técnica com peso 2: o Relevanssi gratuito soma campo
    // personalizado com peso 1; aqui somamos mais 1× o mesmo valor.
    public static function ficha_weight( $match, $idf = 1 ) {
        if ( ! empty( $match->customfield ) ) {
            $match->weight += (float) $match->customfield * max( 1, (float) $idf );
        }
        return $match;
    }

    // Música marcada como inativa (_cv_ativo = 0) não aparece na busca.
    public static function hide_inactive( $ok, $post_id ) {
        if ( $ok && 'musica' === get_post_type( $post_id ) && '0' === get_post_meta( $post_id, CV_Fields::ATIVO, true ) ) {
            return false;
        }
        return $ok;
    }

    // ── Consulta única ──────────────────────────────────────────────
    // $args: termo, post_type (musica|post), por_pagina, pagina, ordem
    // (relevancia|recente|plays|titulo), sentimento (slug).
    public static function query( $args ) {
        $a = wp_parse_args( $args, array(
            'termo'      => '',
            'post_type'  => 'musica',
            'por_pagina' => 20,
            'pagina'     => 1,
            'ordem'      => 'relevancia',
            'sentimento' => '',
        ) );

        $q = array(
            'post_type'           => $a['post_type'],
            'post_status'         => 'publish',
            'posts_per_page'      => (int) $a['por_pagina'],
            'paged'               => max( 1, (int) $a['pagina'] ),
            's'                   => $a['termo'],
            'ignore_sticky_posts' => true,
        );

        // Música inativa (_cv_ativo = 0) fica fora; sem o campo, conta como ativa.
        if ( 'musica' === $a['post_type'] ) {
            $q['meta_query'] = array(
                'relation' => 'OR',
                array( 'key' => CV_Fields::ATIVO, 'compare' => 'NOT EXISTS' ),
                array( 'key' => CV_Fields::ATIVO, 'value' => '0', 'compare' => '!=' ),
            );
        }

        if ( 'musica' === $a['post_type'] && $a['sentimento'] && class_exists( 'CV_Sentimentos' ) ) {
            $sent = CV_Sentimentos::get_by_slug( $a['sentimento'] );
            $ids  = $sent ? wp_list_pluck( CV_Sentimentos::get_musicas_by_sentimento( $sent->id, 500 ), 'ID' ) : array();
            $q['post__in'] = $ids ? array_map( 'intval', $ids ) : array( 0 );
        }

        switch ( $a['ordem'] ) {
            case 'recente': $q['orderby'] = 'post_date'; $q['order'] = 'DESC'; break;
            case 'titulo':  $q['orderby'] = 'title';     $q['order'] = 'ASC';  break;
            case 'plays':   $q['orderby'] = 'meta_value_num'; $q['meta_key'] = '_cv_plays_total'; $q['order'] = 'DESC'; break;
            default:        $q['orderby'] = 'relevance';
        }

        // Sem termo (só filtro de sentimento): lista simples, sem motor de busca.
        if ( '' === $a['termo'] ) {
            unset( $q['s'] );
            if ( 'relevance' === $q['orderby'] ) { $q['orderby'] = 'post_date'; $q['order'] = 'DESC'; }
            return new WP_Query( $q );
        }

        if ( self::relevanssi_on() ) {
            $query = new WP_Query();
            $query->parse_query( $q );
            relevanssi_do_query( $query );
            return $query;
        }

        $q['cv_busca_fallback'] = true;
        if ( 'relevance' === $q['orderby'] ) { unset( $q['orderby'] ); }
        return new WP_Query( $q );
    }

    // Busca LIKE usada só se o Relevanssi estiver desligado: título, letra,
    // resumo, compositor, artista e álbum.
    public static function fallback_sql( $search, $query ) {
        if ( ! $query->get( 'cv_busca_fallback' ) || ! $query->get( 's' ) ) { return $search; }
        global $wpdb;
        $term   = '%' . $wpdb->esc_like( $query->get( 's' ) ) . '%';
        $fields = "'" . implode( "','", array_map( 'esc_sql', self::FICHA_FIELDS ) ) . "'";
        return $wpdb->prepare(
            " AND ({$wpdb->posts}.post_title LIKE %s OR {$wpdb->posts}.post_content LIKE %s OR {$wpdb->posts}.post_excerpt LIKE %s
              OR EXISTS (SELECT 1 FROM {$wpdb->postmeta} cvm WHERE cvm.post_id = {$wpdb->posts}.ID
                  AND cvm.meta_key IN ({$fields}) AND cvm.meta_value LIKE %s))",
            $term, $term, $term, $term
        );
    }

    // Trecho da letra com o termo em destaque (HTML seguro).
    // Com Relevanssi, usa o trecho que ele já montou (post_excerpt).
    public static function trecho( $post, $termo ) {
        if ( self::relevanssi_on() && ! empty( $post->relevance_score ) && '' !== trim( (string) $post->post_excerpt ) ) {
            return wp_kses( $post->post_excerpt, array( 'mark' => array(), 'strong' => array(), 'span' => array( 'class' => array() ) ) );
        }
        $texto = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( $post->post_content ) ) );
        if ( '' === $texto ) { return ''; }
        $pos = '' !== $termo ? mb_stripos( $texto, $termo ) : false;
        $ini = false === $pos ? 0 : max( 0, $pos - 60 );
        $cut = mb_substr( $texto, $ini, 170 );
        $out = ( $ini > 0 ? '…' : '' ) . esc_html( $cut ) . ( mb_strlen( $texto ) > $ini + 170 ? '…' : '' );
        if ( '' !== $termo ) {
            $out = preg_replace( '/(' . preg_quote( esc_html( $termo ), '/' ) . ')/iu', '<mark>$1</mark>', $out );
        }
        return $out;
    }

    // ── Redirecionamentos e SEO ─────────────────────────────────────

    // Qualquer busca nativa (/?s=) vai para a página de busca do site.
    public static function redirect_native_search() {
        if ( is_admin() || ! is_search() || is_feed() ) { return; }
        wp_safe_redirect( self::url( (string) get_search_query( false ) ), 302 );
        exit;
    }

    // Páginas de resultado não devem ser indexadas pelo Google.
    public static function noindex_results( $robots ) {
        if ( is_page( self::PAGE_SLUG ) && ( isset( $_GET['q'] ) || isset( $_GET['sentimento'] ) || isset( $_GET['ordem'] ) || isset( $_GET['pg'] ) ) ) {
            $robots['index'] = 'noindex';
        }
        return $robots;
    }
}

CV_Search::init();
