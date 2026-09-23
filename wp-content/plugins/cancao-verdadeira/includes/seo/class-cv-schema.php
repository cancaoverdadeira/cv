<?php
// cancao-verdadeira/includes/seo/class-cv-schema.php
// Gerado em: 2026-06-21 22:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// v2.4: Schema.org completo para páginas de música. Gera MusicComposition
// com VideoObject (indexa o YouTube no Google), BreadcrumbList (navegação)
// e Open Graph com og:video para preview rico em redes sociais.
// Ativa apenas quando Rank Math não está configurado para o CPT musica.
// v2.25.0: com o Rank Math ativo, o breadcrumb fica só com ele (antes saía
// duplicado) e o Open Graph dele passa a usar a capa do YouTube como imagem
// de reserva e o tipo music.song nas páginas de música.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Schema {

    public static function init() {
        add_action( 'wp_head', array( __CLASS__, 'output' ),    5 );
        add_action( 'wp_head', array( __CLASS__, 'output_og' ), 5 );
        // Complementos para o Open Graph do Rank Math (música).
        add_action( 'rank_math/opengraph/facebook/add_additional_images', array( __CLASS__, 'rm_youtube_image' ) );
        add_action( 'rank_math/opengraph/twitter/add_additional_images',  array( __CLASS__, 'rm_youtube_image' ) );
        add_filter( 'rank_math/opengraph/type', array( __CLASS__, 'rm_og_type' ) );
    }

    // Sem imagem destacada, usa a capa do vídeo do YouTube.
    public static function rm_youtube_image( $image ) {
        if ( ! is_singular( 'musica' ) || $image->has_images() ) { return; }
        $yt_id = self::yt_id( get_post_meta( get_queried_object_id(), '_cv_youtube_url', true ) );
        if ( $yt_id ) {
            $image->add_image_by_url( 'https://img.youtube.com/vi/' . $yt_id . '/maxresdefault.jpg' );
        }
    }

    public static function rm_og_type( $type ) {
        return is_singular( 'musica' ) ? 'music.song' : $type;
    }

    // ── Schema.org JSON-LD ────────────────────────────────────────

    public static function output() {
        if ( ! is_singular( 'musica' ) ) { return; }

        // Se Rank Math estiver ativo E com schema configurado para o CPT, não duplica
        if ( class_exists( 'RankMath' ) && get_option( 'rank_math_schema_musica' ) ) { return; }
        if ( class_exists( 'WPSEO_Frontend' ) ) { return; }

        global $post;

        $title      = get_the_title( $post );
        $url        = get_permalink( $post );
        $compositor = get_post_meta( $post->ID, '_cv_compositor',  true );
        $artista    = get_post_meta( $post->ID, '_cv_artista',     true );
        $album      = get_post_meta( $post->ID, '_cv_album',       true );
        $ano        = get_post_meta( $post->ID, '_cv_ano',         true );
        $youtube    = get_post_meta( $post->ID, '_cv_youtube_url', true );
        $cover      = get_the_post_thumbnail_url( $post->ID, 'large' );
        $excerpt    = get_the_excerpt( $post );
        $genres     = wp_get_post_terms( $post->ID, 'cv_genre', array( 'fields' => 'names' ) );
        $genre_name = ( ! is_wp_error( $genres ) && $genres ) ? $genres[0] : 'Sertanejo';
        $yt_id      = self::yt_id( $youtube );

        // Fallback da capa: thumbnail do YouTube
        if ( ! $cover && $yt_id ) {
            $cover = 'https://img.youtube.com/vi/' . $yt_id . '/maxresdefault.jpg';
        }

        // ── 1. MusicComposition ───────────────────────────────────
        $schema = array(
            '@context'    => 'https://schema.org',
            '@type'       => 'MusicComposition',
            'name'        => $title,
            'url'         => $url,
            'genre'       => $genre_name,
            'description' => $excerpt ?: 'Letra de ' . $title . ' no Canção Verdadeira.',
        );

        if ( $compositor ) {
            $schema['composer'] = array( '@type' => 'Person', 'name' => $compositor );
        }
        if ( $artista ) {
            $schema['lyricist'] = array( '@type' => 'Person', 'name' => $artista );
        }
        if ( $ano ) {
            $schema['datePublished'] = $ano . '-01-01';
        }
        if ( $album ) {
            $schema['inAlbum'] = array( '@type' => 'MusicAlbum', 'name' => $album );
        }
        if ( $cover ) {
            $schema['image'] = $cover;
        }

        // Adds the lyrics text if post content exists
        $letra = get_the_content( null, false, $post );
        if ( $letra ) {
            $schema['lyrics'] = array(
                '@type'    => 'CreativeWork',
                'text'     => wp_strip_all_tags( $letra ),
                'inLanguage' => 'pt-BR',
            );
        }

        // ── 2. VideoObject (YouTube) — ajuda o Google indexar o vídeo ──
        $video_schema = null;
        if ( $youtube && $yt_id ) {
            $video_schema = array(
                '@context'     => 'https://schema.org',
                '@type'        => 'VideoObject',
                'name'         => $title . ' — Canção Verdadeira',
                'description'  => $excerpt ?: 'Ouça e confira a letra de ' . $title . '.',
                'thumbnailUrl' => 'https://img.youtube.com/vi/' . $yt_id . '/maxresdefault.jpg',
                'embedUrl'     => 'https://www.youtube.com/embed/' . $yt_id,
                'url'          => $url,
                'uploadDate'   => get_the_date( 'c', $post ),
                'publisher'    => array(
                    '@type' => 'Organization',
                    'name'  => get_bloginfo( 'name' ),
                    'url'   => home_url(),
                ),
            );
            // Vincula o video à composição
            $schema['recordedAs'] = array(
                '@type'    => 'MusicRecording',
                'name'     => $title,
                'url'      => $youtube,
                'encoding' => $video_schema,
            );
        }

        // ── 3. BreadcrumbList ────────────────────────────────────
        $breadcrumb = array(
            '@context' => 'https://schema.org',
            '@type'    => 'BreadcrumbList',
            'itemListElement' => array(
                array( '@type' => 'ListItem', 'position' => 1, 'name' => 'Início', 'item' => home_url() ),
                array( '@type' => 'ListItem', 'position' => 2, 'name' => 'Músicas', 'item' => home_url( '/musicas/' ) ),
            ),
        );

        // Adiciona gênero no breadcrumb se existir
        if ( ! is_wp_error( $genres ) && ! empty( $genres ) ) {
            $genre_terms = wp_get_post_terms( $post->ID, 'cv_genre' );
            if ( $genre_terms && ! is_wp_error( $genre_terms ) ) {
                $breadcrumb['itemListElement'][] = array(
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $genres[0],
                    'item'     => get_term_link( $genre_terms[0] ),
                );
                $breadcrumb['itemListElement'][] = array(
                    '@type'    => 'ListItem',
                    'position' => 4,
                    'name'     => $title,
                    'item'     => $url,
                );
            } else {
                $breadcrumb['itemListElement'][] = array(
                    '@type'    => 'ListItem',
                    'position' => 3,
                    'name'     => $title,
                    'item'     => $url,
                );
            }
        } else {
            $breadcrumb['itemListElement'][] = array(
                '@type'    => 'ListItem',
                'position' => 3,
                'name'     => $title,
                'item'     => $url,
            );
        }

        // ── Output dos JSONs ──────────────────────────────────────
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";

        if ( $video_schema ) {
            echo '<script type="application/ld+json">' . wp_json_encode( $video_schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        }

        // Rank Math já publica um BreadcrumbList no grafo dele: não duplicar.
        if ( ! class_exists( 'RankMath' ) ) {
            echo '<script type="application/ld+json">' . wp_json_encode( $breadcrumb, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>' . "\n";
        }
    }

    // ── Open Graph + og:video ─────────────────────────────────────

    public static function output_og() {
        if ( ! is_singular( 'musica' ) ) { return; }
        if ( class_exists( 'RankMath' ) || class_exists( 'WPSEO_Frontend' ) ) { return; }

        global $post;

        $title    = get_the_title( $post );
        $url      = get_permalink( $post );
        $excerpt  = get_the_excerpt( $post ) ?: 'Letra de ' . $title . ' no Canção Verdadeira.';
        $artista  = get_post_meta( $post->ID, '_cv_artista', true );
        $youtube  = get_post_meta( $post->ID, '_cv_youtube_url', true );
        $yt_id    = self::yt_id( $youtube );
        $cover    = get_the_post_thumbnail_url( $post->ID, 'large' );

        if ( ! $cover && $yt_id ) {
            $cover = 'https://img.youtube.com/vi/' . $yt_id . '/maxresdefault.jpg';
        }

        $description = 'Letra de ' . $title . ( $artista ? ' — ' . $artista : '' ) . '. Ouça e confira a letra completa no Canção Verdadeira.';
        if ( strlen( $description ) > 160 ) { $description = substr( $description, 0, 157 ) . '...'; }

        ?>
<meta property="og:site_name"   content="<?php echo esc_attr( get_bloginfo('name') ); ?>" />
<meta property="og:type"        content="music.song" />
<meta property="og:title"       content="<?php echo esc_attr( $title ); ?>" />
<meta property="og:description" content="<?php echo esc_attr( $description ); ?>" />
<meta property="og:url"         content="<?php echo esc_url( $url ); ?>" />
<?php if ( $cover ) : ?>
<meta property="og:image"        content="<?php echo esc_url( $cover ); ?>" />
<meta property="og:image:width"  content="1280" />
<meta property="og:image:height" content="720" />
<?php endif; ?>
<?php if ( $yt_id ) : ?>
<meta property="og:video"              content="https://www.youtube.com/embed/<?php echo esc_attr( $yt_id ); ?>" />
<meta property="og:video:type"         content="text/html" />
<meta property="og:video:width"        content="1280" />
<meta property="og:video:height"       content="720" />
<meta property="og:video:secure_url"   content="https://www.youtube.com/embed/<?php echo esc_attr( $yt_id ); ?>" />
<?php endif; ?>
<meta name="twitter:card"        content="<?php echo $yt_id ? 'player' : 'summary_large_image'; ?>" />
<meta name="twitter:title"       content="<?php echo esc_attr( $title ); ?>" />
<meta name="twitter:description" content="<?php echo esc_attr( $description ); ?>" />
<?php if ( $cover ) : ?>
<meta name="twitter:image"       content="<?php echo esc_url( $cover ); ?>" />
<?php endif; ?>
<?php if ( $yt_id ) : ?>
<meta name="twitter:player"      content="https://www.youtube.com/embed/<?php echo esc_attr( $yt_id ); ?>" />
<meta name="twitter:player:width"  content="1280" />
<meta name="twitter:player:height" content="720" />
<?php endif; ?>
        <?php
    }

    // ── Utilitário ────────────────────────────────────────────────

    private static function yt_id( $url ) {
        if ( empty( $url ) ) { return ''; }
        preg_match( '/(?:v=|\/embed\/|\.be\/|\/shorts\/)([a-zA-Z0-9_-]{11})/', $url, $m );
        return $m[1] ?? '';
    }
}

CV_Schema::init();
