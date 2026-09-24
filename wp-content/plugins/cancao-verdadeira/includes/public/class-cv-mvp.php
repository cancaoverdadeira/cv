<?php
// cancao-verdadeira-plugin/includes/public/class-cv-mvp.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Completa o MVP do plugin com os itens restantes:
// 1. Botões de compartilhamento social (WhatsApp, Facebook, Twitter/X, Telegram)
//    como shortcode [cv_share] e helper PHP cv_share_buttons()
// 2. REST API completa: /musicas/{id}, /busca
//    (v2.26.0: saíram /generos, o filtro genre e a fila por gênero)
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
                'color' => '#FBF6EE',
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
                'color' => '#F3E6D3',
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

            <span class="cv-share-copy-msg" style="display:none;font-size:12px;color:#1C7C44;margin-left:8px">
                ✓ Link copiado!
            </span>
        </div>


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

        $ativo = get_post_meta( $id, CV_Fields::ATIVO, true );
        if ( '1' !== $ativo ) {
            return new WP_Error( 'inactive', 'Música inativa.', array( 'status' => 403 ) );
        }

        $youtube_url = get_post_meta( $id, CV_Fields::YOUTUBE_URL, true );
        $tags        = wp_get_post_terms( $id, 'post_tag',       array( 'fields' => 'names' ) );
        $ranking     = class_exists( 'CV_Ranking' ) ? CV_Ranking::get_position( $id ) : 0;

        // YouTube ID
        $yt_id = '';
        if ( $youtube_url ) {
            $yt_id = CV_Fields::youtube_id( $youtube_url );
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
            'descricao'   => get_post_meta( $id, CV_Fields::DESCRICAO,  true ),
            'compositor'  => get_post_meta( $id, CV_Fields::COMPOSITOR, true ),
            'artista'     => get_post_meta( $id, CV_Fields::ARTISTA,    true ),
            'album'       => get_post_meta( $id, CV_Fields::ALBUM,      true ),
            'ano'         => get_post_meta( $id, CV_Fields::ANO,        true ),
            'youtube_url' => $youtube_url,
            'youtube_id'  => $yt_id,
            'cover'       => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            'tags'        => ! is_wp_error( $tags ) ? $tags : array(),
            'destaque'    => get_post_meta( $id, CV_Fields::DESTAQUE,     true ) === '1',
            'plays_total' => (int) get_post_meta( $id, CV_Fields::PLAYS_TOTAL, true ),
            'plays_7d'    => (int) get_post_meta( $id, '_cv_plays_7d',   true ),
            'favorites'   => (int) get_post_meta( $id, CV_Fields::FAVORITES,  true ),
            'avg_rating'  => (float) get_post_meta( $id, CV_Fields::AVG_RATING, true ),
            'score'       => (float) get_post_meta( $id, CV_Fields::SCORE,     true ),
            'ranking_pos' => (int) $ranking,
            'published'   => $post->post_date,
        );
    }

    /**
     * GET /cv/v1/busca?q=termo&limit=20&page=1
     * Busca músicas por texto, com paginação.
     */
    public static function rest_busca( $request ) {
        $termo = sanitize_text_field( $request->get_param( 'q' )     ?? '' );
        $limit = min( absint( $request->get_param( 'limit' ) ?? 20 ), 50 );
        $page  = max( 1, absint( $request->get_param( 'page' ) ?? 1 ) );

        if ( strlen( $termo ) < 2 ) {
            return new WP_Error( 'invalid_query', 'Informe ao menos 2 caracteres.', array( 'status' => 400 ) );
        }

        // v2.27.0: mesma busca do site (Relevanssi com pesos), via CV_Search.
        $query = CV_Search::query( array( 'termo' => $termo, 'por_pagina' => $limit, 'pagina' => $page ) );

        $result = array();
        foreach ( $query->posts as $post ) {
            $yt_url = get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );
            $cover  = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover && $yt_url ) {
                $yt_id = CV_Fields::youtube_id( $yt_url );
                $cover = $yt_id ? "https://img.youtube.com/vi/{$yt_id}/mqdefault.jpg" : '';
            }

            $result[] = array(
                'id'         => $post->ID,
                'title'      => $post->post_title,
                'slug'       => $post->post_name,
                'url'        => get_permalink( $post->ID ),
                'artista'    => get_post_meta( $post->ID, CV_Fields::ARTISTA, true ),
                'compositor' => get_post_meta( $post->ID, CV_Fields::COMPOSITOR, true ),
                'cover'      => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'plays'      => (int) get_post_meta( $post->ID, CV_Fields::PLAYS_TOTAL, true ),
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

        $total = (int) get_post_meta( $music_id, CV_Fields::PLAYS_TOTAL, true );
        update_post_meta( $music_id, CV_Fields::PLAYS_TOTAL, $total + 1 );

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

        // v2.40.0: playlist privada só abre para o dono (antes qualquer
        // visitante com o nonce público lia as músicas de qualquer playlist).
        $dona = $wpdb->get_row( $wpdb->prepare(
            "SELECT user_id, is_public FROM {$wpdb->prefix}cv_playlists WHERE id = %d",
            $playlist_id
        ) );
        if ( ! $dona || ( ! (int) $dona->is_public && (int) $dona->user_id !== get_current_user_id() ) ) {
            wp_send_json_error( array( 'message' => 'Playlist não encontrada.' ) );
        }

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
     * Formata uma música para o formato esperado pelo player global do tema.
     * Retorna null se a música não tiver nem YouTube nem MP3 (não pode ser tocada).
     * v2.40.0: inclui audioUrl (MP3) e aceita música só com MP3.
     */
    private static function format_track( $music_id ) {
        $yt_id = CV_Fields::youtube_id( (string) get_post_meta( $music_id, CV_Fields::YOUTUBE_URL, true ) );
        $audio = esc_url_raw( (string) get_post_meta( $music_id, CV_Fields::AUDIO_URL, true ) );
        if ( ! $yt_id && ! $audio ) { return null; }

        $cover = get_the_post_thumbnail_url( $music_id, 'cv-cover' );
        if ( ! $cover && $yt_id ) {
            $cover = "https://img.youtube.com/vi/{$yt_id}/mqdefault.jpg";
        }

        return array(
            'musicId'    => (int) $music_id,
            'youtubeId'  => $yt_id,
            'audioUrl'   => $audio,
            'title'      => get_the_title( $music_id ),
            'artist'     => get_post_meta( $music_id, CV_Fields::ARTISTA,    true )
                         ?: get_post_meta( $music_id, CV_Fields::COMPOSITOR, true ),
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
        $yt    = get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );
        $cover = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
        if ( ! $cover && $yt ) {
            $m = CV_Fields::youtube_match( $yt );
            if ( ! empty($m[1]) ) { $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg'; }
        }
        $result[] = array(
            'id'      => $post->ID,
            'title'   => $post->post_title,
            'url'     => get_permalink( $post->ID ),
            'cover'   => $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            'artista' => get_post_meta( $post->ID, CV_Fields::ARTISTA, true ),
            'plays'   => (int) get_post_meta( $post->ID, CV_Fields::PLAYS_TOTAL, true ),
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
