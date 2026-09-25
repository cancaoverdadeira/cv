<?php
// cancao-verdadeira/includes/cpt/class-cv-blog.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Blog (v2.25.0). Posts comuns na categoria "Blog" (slug blog).
// URLs    : /blog/ (lista), /blog/page/2/, /blog/feed/ e /blog/{post}/.
//           Endereços antigos (/category/blog/ e /{post}/) redirecionam (301).
// SEO     : o mesmo "SEO automático" das músicas: campo Descrição (300
//           caracteres) → resumo → meta description do Rank Math. Título,
//           Open Graph, schema BlogPosting e sitemap ficam com o Rank Math.
// Setup   : na 1ª carga cria a categoria, torna-a padrão e atualiza as regras
//           de URL (opção cv_blog_version).
// Gerado  : 2026-09-23
// v2.46.0 : tela do post organizada. Coluna direita: Publicar → Imagem
//           destacada → Categorias → Tags (a imagem tinha ido para o fim, depois
//           de 9 caixas). Saem caixas sem uso no blog: Formato, Atributos do
//           post, Opções do WP Rocket, Astra, Trackbacks e Campos personalizados.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Blog {

    const SLUG          = 'blog';
    const SETUP_VERSION = '1';

    public static function init() {
        add_action( 'init',              array( __CLASS__, 'rewrite_rules' ) );
        add_action( 'wp_loaded',         array( __CLASS__, 'maybe_setup' ) );
        add_filter( 'post_link',         array( __CLASS__, 'post_link' ), 10, 2 );
        add_filter( 'term_link',         array( __CLASS__, 'term_link' ), 10, 3 );
        add_action( 'template_redirect', array( __CLASS__, 'redirect_old_urls' ) );
        add_action( 'add_meta_boxes',    array( __CLASS__, 'add_metabox' ) );
        add_action( 'save_post_post',    array( __CLASS__, 'save' ) );
        add_action( 'add_meta_boxes',    array( __CLASS__, 'limpar_tela' ), 999, 1 );
        add_filter( 'get_user_option_meta-box-order_post', array( __CLASS__, 'ordem_caixas' ) );
    }

    // ── Tela do post: ordem das caixas e caixas sem uso (v2.46.0) ────

    public static function ordem_caixas( $ordem ) {
        $ordem = is_array( $ordem ) ? $ordem : array();
        $ordem['side']   = 'submitdiv,postimagediv,categorydiv,tagsdiv-post_tag,rank_math_metabox_content_ai,rank_math_metabox_link_suggestions';
        $ordem['normal'] = 'cv_blog_seo,rank_math_metabox,postexcerpt';
        return $ordem;
    }

    public static function limpar_tela( $post_type ) {
        if ( 'post' !== $post_type ) { return; }
        $caixas = array(
            'formatdiv'               => 'side',     // Formato (o tema não usa)
            'pageparentdiv'           => 'side',     // Atributos do post (modelo de página)
            'rocket_post_exclude'     => 'side',     // Opções do WP Rocket (técnico)
            'astra_settings_meta_box' => 'side',     // Configurações do Astra (o tema filho manda)
            'trackbacksdiv'           => 'normal',   // Trackbacks (ninguém usa mais)
            'postcustom'              => 'normal',   // Campos personalizados (técnico)
        );
        foreach ( $caixas as $id => $contexto ) {
            remove_meta_box( $id, 'post', $contexto );
        }
    }

    // ── Configuração inicial (roda uma vez) ─────────────────────────

    public static function maybe_setup() {
        if ( get_option( 'cv_blog_version' ) === self::SETUP_VERSION ) { return; }

        $term = get_term_by( 'slug', self::SLUG, 'category' );
        if ( ! $term ) {
            $res = wp_insert_term( 'Blog', 'category', array(
                'slug'        => self::SLUG,
                'description' => 'Histórias, bastidores das composições e novidades do sertanejo.',
            ) );
            if ( is_wp_error( $res ) ) { return; }
            $term_id = (int) $res['term_id'];
        } else {
            $term_id = (int) $term->term_id;
        }
        // Post novo sem categoria cai no Blog.
        update_option( 'default_category', $term_id );

        flush_rewrite_rules( false );
        update_option( 'cv_blog_version', self::SETUP_VERSION );
    }

    public static function term_id() {
        $term = get_term_by( 'slug', self::SLUG, 'category' );
        return $term ? (int) $term->term_id : 0;
    }

    public static function is_blog_post( $post ) {
        $post = get_post( $post );
        return $post && 'post' === $post->post_type && has_term( self::SLUG, 'category', $post );
    }

    // ── URLs ────────────────────────────────────────────────────────

    public static function rewrite_rules() {
        // A ordem importa: "page" e "feed" antes da regra do post.
        add_rewrite_rule( '^blog/?$',                             'index.php?category_name=blog', 'top' );
        add_rewrite_rule( '^blog/page/([0-9]+)/?$',               'index.php?category_name=blog&paged=$matches[1]', 'top' );
        add_rewrite_rule( '^blog/(?:feed/)?(feed|rss2|atom)/?$',  'index.php?category_name=blog&feed=$matches[1]', 'top' );
        add_rewrite_rule( '^blog/([^/]+)/?$',                     'index.php?name=$matches[1]', 'top' );
    }

    public static function post_link( $url, $post ) {
        // Rascunho sem slug continua com o link padrão (?p=ID).
        if ( empty( $post->post_name ) || ! in_array( $post->post_status, array( 'publish', 'future', 'private' ), true ) ) {
            return $url;
        }
        if ( ! self::is_blog_post( $post ) ) { return $url; }
        return home_url( user_trailingslashit( self::SLUG . '/' . $post->post_name ) );
    }

    public static function term_link( $url, $term, $taxonomy ) {
        if ( 'category' === $taxonomy && self::SLUG === $term->slug ) {
            return home_url( user_trailingslashit( self::SLUG ) );
        }
        return $url;
    }

    // Leva endereços antigos para o novo, sem perder SEO (301).
    public static function redirect_old_urls() {
        if ( is_feed() || is_preview() ) { return; }
        $path = (string) wp_parse_url( isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '', PHP_URL_PATH );
        $home = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
        $rel  = ltrim( substr( $path, strlen( rtrim( $home, '/' ) ) ), '/' );
        $in_blog_path = ( 0 === strpos( $rel, self::SLUG . '/' ) || self::SLUG === rtrim( $rel, '/' ) );

        if ( is_category( self::SLUG ) && ! $in_blog_path ) {
            $paged = max( 1, (int) get_query_var( 'paged' ) );
            $url   = get_term_link( self::SLUG, 'category' );
            if ( $paged > 1 ) { $url = trailingslashit( $url ) . user_trailingslashit( 'page/' . $paged ); }
            wp_safe_redirect( $url, 301 );
            exit;
        }
        if ( is_singular( 'post' ) && ! $in_blog_path && self::is_blog_post( get_queried_object_id() ) ) {
            wp_safe_redirect( get_permalink( get_queried_object_id() ), 301 );
            exit;
        }
    }

    // ── Metabox "SEO automático" (igual ao da música) ─────────────────

    public static function add_metabox() {
        add_meta_box( 'cv_blog_seo', '🔎 SEO automático', array( __CLASS__, 'render_metabox' ), 'post', 'normal', 'high' );
    }

    public static function render_metabox( $post ) {
        wp_nonce_field( 'cv_blog_seo_save', 'cv_blog_seo_nonce' );
        $descricao = (string) get_post_meta( $post->ID, CV_Fields::DESCRICAO, true );
        ?>
        <p style="margin-top:0">
            <label for="cv_blog_descricao"><strong>Descrição</strong></label><br>
            <textarea id="cv_blog_descricao" name="cv_blog_descricao" rows="3" maxlength="300"
                      style="width:100%"
                      placeholder="Resuma o post em 1 ou 2 frases. É o texto que aparece no Google e ao compartilhar."
            ><?php echo esc_textarea( $descricao ); ?></textarea>
        </p>
        <p class="description" style="margin:0">
            <span id="cv-blog-desc-len"><?php echo (int) mb_strlen( $descricao ); ?></span>/300 caracteres.
            O Google mostra cerca de 155. O sistema copia este texto para o Resumo do post.
            Deixe vazio para usar o começo do texto.
        </p>
        <script>
        (function(){
            var t=document.getElementById('cv_blog_descricao'), c=document.getElementById('cv-blog-desc-len');
            if(t&&c){ t.addEventListener('input',function(){ c.textContent=t.value.length; }); }
        })();
        </script>
        <?php
    }

    public static function save( $post_id ) {
        if ( ! isset( $_POST['cv_blog_seo_nonce'] ) ) { return; }
        if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_blog_seo_nonce'] ) ), 'cv_blog_seo_save' ) ) { return; }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( wp_is_post_revision( $post_id ) ) { return; }
        if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

        $descricao = isset( $_POST['cv_blog_descricao'] )
            ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['cv_blog_descricao'] ) ), 0, 300 )
            : '';
        update_post_meta( $post_id, CV_Fields::DESCRICAO, $descricao );
        CV_Fields::sync_excerpt( $post_id );
    }
}

CV_Blog::init();
