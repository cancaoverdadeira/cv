<?php
// cancao-verdadeira-plugin/includes/public/class-cv-mvp.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Completa o MVP do plugin com os itens restantes:
// 1. Botões de compartilhamento social (WhatsApp, Facebook, Twitter/X, Telegram)
//    como shortcode [cv_share] e helper PHP cv_share_buttons()
// 2. REST API completa: /musicas/{id}, /generos, /busca
// 3. Endpoint AJAX para carregar fila de playlist no player global
//    (permite que o tema reproduza uma playlist completa em sequência)

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_MVP {

    public static function init() {
        // Shortcode de compartilhamento
        add_shortcode( 'cv_share', array( __CLASS__, 'shortcode_share' ) );

        // REST API adicional
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );

        // AJAX: fila de playlist para o player
        add_action( 'wp_ajax_cv_get_playlist_queue',        array( __CLASS__, 'ajax_playlist_queue' ) );
        add_action( 'wp_ajax_nopriv_cv_get_playlist_queue', array( __CLASS__, 'ajax_playlist_queue' ) );

        // AJAX: músicas de um gênero para o player (fila por gênero)
        add_action( 'wp_ajax_cv_get_genre_queue',           array( __CLASS__, 'ajax_genre_queue' ) );
        add_action( 'wp_ajax_nopriv_cv_get_genre_queue',    array( __CLASS__, 'ajax_genre_queue' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // 1. COMPARTILHAMENTO SOCIAL
    // ════════════════════════════════════════════════════════════════

    /**
     * Shortcode [cv_share]
     *
     * Parâmetros:
     *   url     = URL a compartilhar (padrão: URL da página atual)
     *   titulo  = Texto da mensagem (padrão: título do post atual)
     *   redes   = quais redes exibir, separadas por vírgula
     *             valores: whatsapp, facebook, twitter, telegram, copiar
     *             padrão: whatsapp,facebook,twitter,telegram,copiar
     *   layout  = botoes | icones (padrão: botoes)
     *   label   = texto antes dos botões (padrão: "Compartilhar:")
     *
     * Exemplos:
     *   [cv_share]
     *   [cv_share redes="whatsapp,telegram" layout="icones"]
     *   [cv_share label="Conta para um amigo:"]
     */
    public static function shortcode_share( $atts ) {
        $atts = shortcode_atts( array(
            'url'    => '',
            'titulo' => '',
            'redes'  => 'whatsapp,facebook,twitter,telegram,copiar',
            'layout' => 'botoes',
            'label'  => 'Compartilhar:',
        ), $atts, 'cv_share' );

        $url    = $atts['url']    ? esc_url( $atts['url'] )    : get_permalink();
        $titulo = $atts['titulo'] ? $atts['titulo']            : get_the_title();
        $redes  = array_map( 'trim', explode( ',', $atts['redes'] ) );
        $layout = sanitize_text_field( $atts['layout'] );
        $label  = sanitize_text_field( $atts['label'] );

        return self::render_share_buttons( $url, $titulo, $redes, $layout, $label );
    }

    /**
     * Helper PHP para uso direto nos templates do tema.
     * Uso: echo CV_MVP::share_buttons();
     * ou:  echo CV_MVP::share_buttons( get_permalink(), get_the_title() );
     */
    public static function share_buttons(
        $url    = '',
        $titulo = '',
        $redes  = array( 'whatsapp', 'facebook', 'twitter', 'telegram', 'copiar' ),
        $layout = 'botoes',
        $label  = 'Compartilhar:'
    ) {
        $url    = $url    ?: get_permalink();
        $titulo = $titulo ?: get_the_title();
        return self::render_share_buttons( $url, $titulo, $redes, $layout, $label );
    }

    /**
     * Renderiza os botões de compartilhamento.
     */
    private static function render_share_buttons( $url, $titulo, $redes, $layout, $label ) {
        $url_enc    = rawurlencode( $url );
        $titulo_enc = rawurlencode( 'Ouça "' . $titulo . '" no Canção Verdadeira: ' );

        $links = array(
            'whatsapp' => array(
                'href'  => 'https://api.whatsapp.com/send?text=' . $titulo_enc . $url_enc,
                'label' => 'WhatsApp',
                'icon'  => '💬',
                'color' => '#25D366',
                'class' => 'cv-share-wa',
            ),
            'facebook' => array(
                'href'  => 'https://www.facebook.com/sharer/sharer.php?u=' . $url_enc,
                'label' => 'Facebook',
                'icon'  => 'f',
                'color' => '#1877F2',
                'class' => 'cv-share-fb',
            ),
            'twitter' => array(
                'href'  => 'https://twitter.com/intent/tweet?text=' . $titulo_enc . '&url=' . $url_enc,
                'label' => 'Twitter/X',
                'icon'  => '𝕏',
                'color' => '#000000',
                'class' => 'cv-share-tw',
            ),
            'telegram' => array(
                'href'  => 'https://t.me/share/url?url=' . $url_enc . '&text=' . $titulo_enc,
                'label' => 'Telegram',
                'icon'  => '✈',
                'color' => '#229ED9',
                'class' => 'cv-share-tg',
            ),
            'copiar' => array(
                'href'  => '#',
                'label' => 'Copiar link',
                'icon'  => '🔗',
                'color' => '#555555',
                'class' => 'cv-share-copy',
                'data'  => 'data-url="' . esc_attr( $url ) . '"',
            ),
        );

        ob_start();
        $is_icones = ( 'icones' === $layout );
        ?>
        <div class="cv-share-wrap cv-share-<?php echo esc_attr( $layout ); ?>">
            <?php if ( $label ) : ?>
                <span class="cv-share-label"><?php echo esc_html( $label ); ?></span>
            <?php endif; ?>

            <div class="cv-share-buttons">
                <?php foreach ( $redes as $rede ) :
                    if ( ! isset( $links[ $rede ] ) ) { continue; }
                    $r    = $links[ $rede ];
                    $data = $r['data'] ?? '';
                    $target = ( $rede !== 'copiar' ) ? 'target="_blank" rel="noopener noreferrer"' : '';
                ?>
                <a href="<?php echo esc_url( $r['href'] ); ?>"
                   class="cv-share-btn <?php echo esc_attr( $r['class'] ); ?>"
                   style="--share-color:<?php echo esc_attr( $r['color'] ); ?>"
                   <?php echo $target; ?>
                   <?php echo $data; ?>
                   title="<?php echo esc_attr( $r['label'] ); ?>"
                   aria-label="Compartilhar no <?php echo esc_attr( $r['label'] ); ?>">
                    <span class="cv-share-icon"><?php echo $r['icon']; ?></span>
                    <?php if ( ! $is_icones ) : ?>
                        <span class="cv-share-text"><?php echo esc_html( $r['label'] ); ?></span>
                    <?php endif; ?>
                </a>
                <?php endforeach; ?>
            </div>

            <span class="cv-share-copy-msg" style="display:none;font-size:12px;color:#27ae60;margin-left:8px">
                ✓ Link copiado!
            </span>
        </div>

        <style>
        .cv-share-wrap { display:flex; align-items:center; flex-wrap:wrap; gap:10px; }
        .cv-share-label { font-size:13px; color:var(--cv-text-muted,#888); font-weight:600; }
        .cv-share-buttons { display:flex; flex-wrap:wrap; gap:8px; }
        .cv-share-btn {
            display:     inline-flex;
            align-items: center;
            gap:         6px;
            border-radius: 8px;
            border:      1px solid color-mix(in srgb, var(--share-color) 40%, transparent);
            background:  color-mix(in srgb, var(--share-color) 10%, transparent);
            color:       var(--share-color);
            font-size:   13px;
            font-weight: 600;
            padding:     6px 14px;
            text-decoration: none;
            transition:  background .2s, transform .1s;
            cursor:      pointer;
        }
        .cv-share-btn:hover {
            background:  color-mix(in srgb, var(--share-color) 20%, transparent);
            color:       var(--share-color);
            transform:   translateY(-1px);
        }
        .cv-share-icones .cv-share-btn { padding:8px 10px; border-radius:50%; width:38px; height:38px; justify-content:center; }
        .cv-share-icon { font-size:16px; line-height:1; }
        </style>

        <script>
        document.querySelectorAll('.cv-share-copy').forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                var url = this.dataset.url || window.location.href;
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function(){
                        var msg = btn.closest('.cv-share-wrap').querySelector('.cv-share-copy-msg');
                        if (msg) { msg.style.display='inline'; setTimeout(function(){ msg.style.display='none'; }, 2500); }
                    });
                } else {
                    var ta = document.createElement('textarea');
                    ta.value = url;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand('copy');
                    document.body.removeChild(ta);
                }
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 2. REST API COMPLETA
    // ════════════════════════════════════════════════════════════════

    public static function register_rest_routes() {

        // /cv/v1/musicas/{id} — detalhe completo de uma música
        register_rest_route( 'cv/v1', '/musicas/(?P<id>\d+)', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_musica_single' ),
            'permission_callback' => '__return_true',
            'args'                => array(
                'id' => array(
                    'validate_callback' => function( $v ) { return is_numeric( $v ); },
                ),
            ),
        ) );

        // /cv/v1/generos — lista de gêneros com contagem
        register_rest_route( 'cv/v1', '/generos', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_generos' ),
            'permission_callback' => '__return_true',
        ) );

        // /cv/v1/busca — busca por texto
        register_rest_route( 'cv/v1', '/busca', array(
            'methods'             => 'GET',
            'callback'            => array( __CLASS__, 'rest_busca' ),
            'permission_callback' => '__return_true',
        ) );

        // /cv/v1/plays — registrar play via REST (alternativa ao AJAX)
        register_rest_route( 'cv/v1', '/plays', array(
            'methods'             => 'POST',
            'callback'            => array( __CLASS__, 'rest_register_play' ),
            'permission_callback' => '__return_true',
        ) );
    }

    /**
     * GET /cv/v1/musicas/{id}
     * Retorna todos os dados de uma música específica.
     */
    public static function rest_musica_single( $request ) {
        $id   = (int) $request['id'];
        $post = get_post( $id );

        if ( ! $post || 'musica' !== $post->post_type || 'publish' !== $post->post_status ) {
            return new WP_Error( 'not_found', 'Música não encontrada.', array( 'status' => 404 ) );
        }

        $ativo = get_post_meta( $id, '_cv_ativo', true );
        if ( '1' !== $ativo ) {
            return new WP_Error( 'inactive', 'Música inativa.', array( 'status' => 403 ) );
        }

        $youtube_url = get_post_meta( $id, '_cv_youtube_url', true );
        $generos     = wp_get_post_terms( $id, 'cv_genre',       array( 'fields' => 'all' ) );
        $subcats     = wp_get_post_terms( $id, 'cv_subcategory', array( 'fields' => 'names' ) );
        $tags        = wp_get_post_terms( $id, 'post_tag',       array( 'fields' => 'names' ) );
        $ranking     = class_exists( 'CV_Ranking' ) ? CV_Ranking::get_position( $id ) : 0;

        // YouTube ID
        $yt_id = '';
        if ( $youtube_url ) {
            preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $m );
            $yt_id = $m[1] ?? '';
        }

        $cover = get_the_post_thumbnail_url( $id, 'cv-cover' );
        if ( ! $cover && $yt_id ) {
            $cover = "https://img.youtube.com/vi/{$yt_id}/mqdefault.jpg";
        }

        return array(
            'id'          => $id,
            'title'       => $post->post_title,
            'slug'        => $post->post_name,
            'url'         => get_permalink( $id ),
            'letra'       => apply_filters( 'the_content', $post->post_content ),
            'descricao'   => get_post_meta( $id, '_cv_descricao',  true ),
            'compositor'  => get_post_meta( $id, '_cv_compositor', true ),
            'artista'     => get_post_meta( $id, '_cv_artista',    true ),
            'album'       => get_post_meta( $id, '_cv_album',      true ),
            'ano'         => get_post_meta( $id, '_cv_ano',        true ),
            'youtube_url' => $youtube_url,
            'youtube_id'  => $yt_id,
            'cover'       => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            'generos'     => ! is_wp_error( $generos ) ? array_map( function($t){ return array('id'=>$t->term_id,'name'=>$t->name,'slug'=>$t->slug); }, $generos ) : array(),
            'subcategorias' => ! is_wp_error( $subcats ) ? $subcats : array(),
            'tags'        => ! is_wp_error( $tags ) ? $tags : array(),
            'destaque'    => get_post_meta( $id, '_cv_destaque',     true ) === '1',
            'plays_total' => (int) get_post_meta( $id, '_cv_plays_total', true ),
            'plays_7d'    => (int) get_post_meta( $id, '_cv_plays_7d',   true ),
            'favorites'   => (int) get_post_meta( $id, '_cv_favorites',  true ),
            'avg_rating'  => (float) get_post_meta( $id, '_cv_avg_rating', true ),
            'score'       => (float) get_post_meta( $id, '_cv_score',     true ),
            'ranking_pos' => (int) $ranking,
            'published'   => $post->post_date,
        );
    }

    /**
     * GET /cv/v1/generos
     * Lista todos os gêneros com total de músicas e link.
     */
    public static function rest_generos( $request ) {
        $generos = get_terms( array(
            'taxonomy'   => 'cv_genre',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ) );

        if ( is_wp_error( $generos ) ) {
            return array();
        }

        $icons = array(
            'sertanejo-universitario' => '🎸',
            'sertanejo-raiz'          => '🪗',
            'sertanejo-romantico'     => '❤',
            'modao'                   => '🎩',
            'sertanejo-gospel'        => '✝',
            'sertanejo-sofrencia'     => '💔',
        );

        $result = array();
        foreach ( $generos as $gen ) {
            $result[] = array(
                'id'          => $gen->term_id,
                'name'        => $gen->name,
                'slug'        => $gen->slug,
                'url'         => get_term_link( $gen ),
                'count'       => (int) $gen->count,
                'description' => $gen->description,
                'icon'        => $icons[ $gen->slug ] ?? '🎵',
            );
        }

        return $result;
    }

    /**
     * GET /cv/v1/busca?q=termo&genre=slug&limit=20&page=1
     * Busca músicas por texto com filtro por gênero e paginação.
     */
    public static function rest_busca( $request ) {
        $termo = sanitize_text_field( $request->get_param( 'q' )     ?? '' );
        $genre = sanitize_text_field( $request->get_param( 'genre' ) ?? '' );
        $limit = min( absint( $request->get_param( 'limit' ) ?? 20 ), 50 );
        $page  = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );

        if ( strlen( $termo ) < 2 && ! $genre ) {
            return new WP_Error( 'invalid_query', 'Informe ao menos 2 caracteres ou um gênero.', array( 'status' => 400 ) );
        }

        $args = array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'paged'          => $page,
            'meta_query'     => array(
                array( 'key' => '_cv_ativo', 'value' => '1', 'compare' => '=' ),
            ),
        );

        if ( $termo ) {
            $args['s'] = $termo;
        }

        if ( $genre ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'cv_genre',
                    'field'    => 'slug',
                    'terms'    => $genre,
                ),
            );
        }

        // Usa Relevanssi se disponível
        $query = new WP_Query( $args );
        if ( $termo && function_exists( 'relevanssi_do_query' ) ) {
            relevanssi_do_query( $query );
        }

        $result = array();
        foreach ( $query->posts as $post ) {
            $yt_url = get_post_meta( $post->ID, '_cv_youtube_url', true );
            $cover  = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover && $yt_url ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $yt_url, $m );
                $yt_id = $m[1] ?? '';
                $cover = $yt_id ? "https://img.youtube.com/vi/{$yt_id}/mqdefault.jpg" : '';
            }

            $result[] = array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'slug'       => $post->post_name,
                'url'        => get_permalink( $post->ID ),
                'artista'    => get_post_meta( $post->ID, '_cv_artista', true ),
                'compositor' => get_post_meta( $post->ID, '_cv_compositor', true ),
                'cover'      => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'plays'      => (int) get_post_meta( $post->ID, '_cv_plays_total', true ),
            );
        }

        return array(
            'total'    => (int) $query->found_posts,
            'pages'    => (int) $query->max_num_pages,
            'page'     => $page,
            'results'  => $result,
        );
    }

    /**
     * POST /cv/v1/plays
     * Alternativa REST ao AJAX para registrar plays (útil para PWA).
     * Body JSON: { "music_id": 123 }
     */
    public static function rest_register_play( $request ) {
        $music_id = absint( $request->get_param( 'music_id' ) ?? 0 );

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) ) {
            return new WP_Error( 'invalid', 'ID inválido.', array( 'status' => 400 ) );
        }

        global $wpdb;

        $ip   = self::get_ip();
        $since = date( 'Y-m-d H:i:s', current_time( 'timestamp' ) - 60 );
        $dup  = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_plays_log
             WHERE music_id = %d AND ip_address = %s AND played_at >= %s",
            $music_id, $ip, $since
        ) );

        if ( $dup > 0 ) {
            return array( 'registered' => false, 'reason' => 'duplicate' );
        }

        $wpdb->insert(
            $wpdb->prefix . 'cv_plays_log',
            array(
                'music_id'   => $music_id,
                'user_id'    => get_current_user_id(),
                'ip_address' => $ip,
                'played_at'  => current_time( 'mysql' ),
            ),
            array( '%d', '%d', '%s', '%s' )
        );

        $total = (int) get_post_meta( $music_id, '_cv_plays_total', true );
        update_post_meta( $music_id, '_cv_plays_total', $total + 1 );

        return array( 'registered' => true, 'plays' => $total + 1 );
    }

    private static function get_ip() {
        foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' ) as $key ) {
            if ( ! empty( $_SERVER[ $key ] ) ) {
                $ip = trim( explode( ',', $_SERVER[ $key ] )[0] );
                if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) { return $ip; }
            }
        }
        return '0.0.0.0';
    }

    // ════════════════════════════════════════════════════════════════
    // 3. FILA DE PLAYLIST PARA O PLAYER GLOBAL
    // ════════════════════════════════════════════════════════════════

    /**
     * AJAX: retorna todas as músicas de uma playlist formatadas para o player.
     * O tema chama este endpoint quando o usuário clica em "Reproduzir Playlist".
     *
     * Uso no tema (JS):
     *   $.post(cvPublic.ajaxUrl, {
     *       action: 'cv_get_playlist_queue',
     *       nonce:  cvPublic.nonces.playlist,
     *       playlist_id: 5
     *   }, function(res) {
     *       if (res.success) cvPlayer.loadQueue(res.data.queue);
     *   });
     */
    public static function ajax_playlist_queue() {
        check_ajax_referer( 'cv_playlist_nonce', 'nonce' );

        $playlist_id = absint( $_POST['playlist_id'] ?? 0 );
        if ( ! $playlist_id ) {
            wp_send_json_error( array( 'message' => 'Playlist inválida.' ) );
        }

        global $wpdb;

        $items = $wpdb->get_results( $wpdb->prepare(
            "SELECT pi.music_id, pi.sort_order
             FROM {$wpdb->prefix}cv_playlist_items pi
             INNER JOIN {$wpdb->posts} p ON p.ID = pi.music_id
             WHERE pi.playlist_id = %d AND p.post_status = 'publish'
             ORDER BY pi.sort_order ASC",
            $playlist_id
        ) );

        if ( empty( $items ) ) {
            wp_send_json_error( array( 'message' => 'Playlist vazia.' ) );
        }

        $queue = array();
        foreach ( $items as $item ) {
            $track = self::format_track( $item->music_id );
            if ( $track ) { $queue[] = $track; }
        }

        $pl_name = $wpdb->get_var( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}cv_playlists WHERE id = %d",
            $playlist_id
        ) );

        wp_send_json_success( array(
            'playlist_id'   => $playlist_id,
            'playlist_name' => $pl_name,
            'total'         => count( $queue ),
            'queue'         => $queue,
        ) );
    }

    /**
     * AJAX: retorna músicas de um gênero formatadas para o player.
     * Permite que o player auto-avance dentro do mesmo gênero.
     *
     * Uso no tema (JS):
     *   $.post(cvPublic.ajaxUrl, {
     *       action: 'cv_get_genre_queue',
     *       nonce:  cvPublic.nonces.play,
     *       genre:  'sertanejo-universitario',
     *       limit:  20
     *   }, function(res) {
     *       if (res.success) cvPlayer.loadQueue(res.data.queue);
     *   });
     */
    public static function ajax_genre_queue() {
        check_ajax_referer( 'cv_play_nonce', 'nonce' );

        $genre = sanitize_text_field( $_POST['genre'] ?? '' );
        $limit = min( absint( $_POST['limit'] ?? 20 ), 50 );

        $args = array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => $limit,
            'orderby'        => 'meta_value_num',
            'meta_key'       => '_cv_score',
            'order'          => 'DESC',
            'meta_query'     => array(
                array( 'key' => '_cv_ativo', 'value' => '1', 'compare' => '=' ),
            ),
        );

        if ( $genre ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'cv_genre',
                    'field'    => 'slug',
                    'terms'    => $genre,
                ),
            );
        }

        $posts = get_posts( $args );
        $queue = array();
        foreach ( $posts as $post ) {
            $track = self::format_track( $post->ID );
            if ( $track ) { $queue[] = $track; }
        }

        wp_send_json_success( array(
            'genre' => $genre,
            'total' => count( $queue ),
            'queue' => $queue,
        ) );
    }

    /**
     * Formata uma música para o formato esperado pelo player global do tema.
     * Retorna null se a música não tiver URL do YouTube (não pode ser tocada).
     */
    private static function format_track( $music_id ) {
        $youtube_url = get_post_meta( $music_id, '_cv_youtube_url', true );
        if ( ! $youtube_url ) { return null; }

        preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $m );
        $yt_id = $m[1] ?? '';
        if ( ! $yt_id ) { return null; }

        $cover = get_the_post_thumbnail_url( $music_id, 'cv-cover' );
        if ( ! $cover ) {
            $cover = "https://img.youtube.com/vi/{$yt_id}/mqdefault.jpg";
        }

        return array(
            'musicId'    => (int) $music_id,
            'youtubeId'  => $yt_id,
            'title'      => get_the_title( $music_id ),
            'artist'     => get_post_meta( $music_id, '_cv_artista',    true )
                         ?: get_post_meta( $music_id, '_cv_compositor', true ),
            'cover'      => $cover,
            'url'        => get_permalink( $music_id ),
        );
    }
}

CV_MVP::init();

// ── Endpoints REST autenticados (adicionados v2.4) ────────────────
// Registrado via rest_api_init junto com os demais routes da classe.
// Adicionados ao método register_rest_routes() existente via action.
add_action( 'rest_api_init', function() {

    // GET /cv/v1/user/favorites — favoritos do usuário logado
    register_rest_route( 'cv/v1', '/user/favorites', array(
        'methods'             => 'GET',
        'callback'            => 'cv_rest_user_favorites',
        'permission_callback' => function() { return is_user_logged_in(); },
    ) );

    // GET /cv/v1/user/playlists — playlists do usuário logado
    register_rest_route( 'cv/v1', '/user/playlists', array(
        'methods'             => 'GET',
        'callback'            => 'cv_rest_user_playlists',
        'permission_callback' => function() { return is_user_logged_in(); },
    ) );

    // GET /cv/v1/user/stats — estatísticas do usuário logado
    register_rest_route( 'cv/v1', '/user/stats', array(
        'methods'             => 'GET',
        'callback'            => 'cv_rest_user_stats',
        'permission_callback' => function() { return is_user_logged_in(); },
    ) );

} );

function cv_rest_user_favorites( $request ) {
    $user_id = get_current_user_id();
    $limit   = absint( $request->get_param('limit') ) ?: 20;
    $posts   = class_exists('CV_Favorites') ? CV_Favorites::get_user_favorites( $user_id, $limit ) : array();
    $result  = array();
    foreach ( $posts as $post ) {
        $yt    = get_post_meta( $post->ID, '_cv_youtube_url', true );
        $cover = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
        if ( ! $cover && $yt ) {
            preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $yt, $m );
            if ( ! empty($m[1]) ) { $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg'; }
        }
        $result[] = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'url'     => get_permalink( $post->ID ),
            'cover'   => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            'artista' => get_post_meta( $post->ID, '_cv_artista', true ),
            'plays'   => (int) get_post_meta( $post->ID, '_cv_plays_total', true ),
        );
    }
    return rest_ensure_response( $result );
}

function cv_rest_user_playlists( $request ) {
    $user_id   = get_current_user_id();
    $playlists = class_exists('CV_Playlists') ? CV_Playlists::get_user_playlists( $user_id ) : array();
    $result    = array();
    foreach ( $playlists as $pl ) {
        $result[] = array(
            'id'    => (int) $pl->id,
            'name'  => $pl->name,
            'count' => (int) ( $pl->count ?? 0 ),
            'public'=> (bool) $pl->is_public,
        );
    }
    return rest_ensure_response( $result );
}

function cv_rest_user_stats( $request ) {
    $user_id = get_current_user_id();
    $data    = class_exists('CV_User_Area') ? CV_User_Area::get_user_data( $user_id ) : array();
    return rest_ensure_response( $data );
}
