<?php
// cancao-verdadeira/includes/monetization/partes/trait-cv-mon-banners.php
// Parte do CV_Monetization (trait CV_Mon_Banners): banners de parceiros: busca do banner fixo por posição,
// render, [cv_banner], clique rastreado, bloco "depois da letra" e AJAX do painel.
// v2.38.0: saiu de class-cv-monetization.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Monetization::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Mon_Banners {

    // ════════════════════════════════════════════════════════════════
    // 1. BANNERS DE PARCEIROS
    // ════════════════════════════════════════════════════════════════

    /**
     * Retorna o banner ativo de uma posição (um só, sem rotação).
     * Posições: apos_letra | meio_pagina | rodape_pagina
     *
     * @param string $posicao
     * @param bool   $apenas_um  mantido por compatibilidade; sempre 1 banner
     */
    public static function get_banners( $posicao = 'meio_pagina', $apenas_um = true ) {
        global $wpdb;

        if ( ! array_key_exists( $posicao, self::posicoes() ) ) { return array(); }

        $hoje  = current_time( 'Y-m-d' );
        $limit = 'LIMIT 1';

        $banners = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_banners
             WHERE ativo = 1
               AND posicao = %s
               AND ( data_inicio IS NULL OR data_inicio <= %s )
               AND ( data_fim   IS NULL OR data_fim   >= %s )
             ORDER BY id DESC
             $limit",
            $posicao, $hoje, $hoje
        ) );

        return $banners ?: array();
    }

    /**
     * Renderiza HTML de um ou mais banners.
     *
     * @param string $posicao
     * @param bool   $apenas_um
     */
    public static function render_banners( $posicao = 'meio_pagina', $apenas_um = false ) {
        $banners = self::get_banners( $posicao, $apenas_um );
        if ( empty( $banners ) ) { return ''; }

        ob_start();
        ?>
        <div class="cv-banners-wrap cv-banners-<?php echo esc_attr( $posicao ); ?>">
            <?php foreach ( $banners as $b ) : ?>
            <div class="cv-banner-item">
                <a href="<?php echo esc_url( add_query_arg( array( 'action' => 'cv_banner_click', 'id' => (int) $b->id ), admin_url( 'admin-ajax.php' ) ) ); ?>"
                   class="cv-banner-link"
                   target="_blank"
                   rel="sponsored nofollow noopener noreferrer"
                   data-banner-id="<?php echo esc_attr( $b->id ); ?>"
                   aria-label="<?php echo esc_attr( $b->texto_alt ?: $b->titulo ); ?>">
                    <img src="<?php echo esc_url( $b->imagem_url ); ?>"
                         alt="<?php echo esc_attr( $b->texto_alt ?: $b->titulo ); ?>"
                         class="cv-banner-img"
                         loading="lazy" />
                    <?php if ( $b->titulo ) : ?>
                        <span class="cv-banner-titulo"><?php echo esc_html( $b->titulo ); ?></span>
                    <?php endif; ?>
                </a>
                <span class="cv-banner-tag">Publicidade</span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode [cv_banner]
     *
     * Parâmetros:
     *   posicao  = apos_letra | meio_pagina | rodape_pagina (padrão: meio_pagina)
     *   apenas_um = sim | nao (padrão: nao)
     *   leia_mais = sim | nao — exibe botão "Leia mais" abaixo (padrão: nao)
     *
     * Exemplos:
     *   [cv_banner posicao="meio_pagina" leia_mais="sim"]
     *   [cv_banner posicao="rodape_pagina" apenas_um="sim"]
     */
    public static function shortcode_banner( $atts ) {
        $atts = shortcode_atts( array(
            'posicao'   => 'meio_pagina',
            'apenas_um' => 'nao',
            'leia_mais' => 'nao',
        ), $atts, 'cv_banner' );

        $posicao   = sanitize_text_field( $atts['posicao'] );
        $apenas_um = ( 'sim' === $atts['apenas_um'] );
        $leia_mais = ( 'sim' === $atts['leia_mais'] );

        $html = self::render_banners( $posicao, $apenas_um );

        if ( $html && $leia_mais ) {
            $html .= '<div class="cv-banner-leia-mais">'
                   . '<button class="cv-btn-leia-mais" onclick="this.closest(\'.cv-banner-leia-mais\').previousSibling.style.display=\'none\';this.parentNode.style.display=\'none\'">'
                   . '✕ Fechar anúncio'
                   . '</button>'
                   . '</div>';
        }

        return $html ?: '';
    }

    /**
     * AJAX: registra clique e redireciona para URL destino.
     */
    public static function ajax_banner_click() {
        global $wpdb;

        $id   = absint( $_GET['id'] ?? 0 );
        // O destino vem do banner cadastrado — nunca da URL (evita redirecionamento aberto).
        $dest = $id ? $wpdb->get_var( $wpdb->prepare(
            "SELECT url_destino FROM {$wpdb->prefix}cv_banners WHERE id = %d AND ativo = 1",
            $id
        ) ) : '';

        if ( $dest ) {
            $wpdb->query( $wpdb->prepare(
                "UPDATE {$wpdb->prefix}cv_banners SET cliques = cliques + 1 WHERE id = %d",
                $id
            ) );
            wp_redirect( esc_url_raw( $dest ), 302 );
            exit;
        }

        wp_safe_redirect( home_url( '/' ) );
        exit;
    }

    // Posições permitidas (nenhuma no topo da página).
    public static function posicoes() {
        return array(
            'apos_letra'    => '🎵 Página da música — depois da letra (principal)',
            'meio_pagina'   => '📄 Páginas internas — meio do conteúdo ([cv_banner])',
            'rodape_pagina' => '📄 Páginas internas — fim do conteúdo ([cv_banner posicao="rodape_pagina"])',
        );
    }

    // Configuração do bloco "depois da letra" (opção cv_publicidade).
    public static function config() {
        return wp_parse_args( (array) get_option( 'cv_publicidade', array() ), array(
            'apos_letra_ativo' => 1,
            'aviso'            => 'Leia após a publicidade',
            'paragrafo'        => 'Gostou desta letra? Compartilhe com quem vai se emocionar com ela e deixe sua avaliação.',
        ) );
    }

    /**
     * Bloco da página da música, logo depois da letra:
     * parágrafo curto → aviso "Leia após a publicidade" → banner.
     * Sem banner ativo (ou desligado), não mostra nada.
     */
    public static function bloco_apos_letra( $music_id ) {
        $cfg = self::config();
        if ( empty( $cfg['apos_letra_ativo'] ) ) { return ''; }
        $banner = self::render_banners( 'apos_letra' );
        if ( '' === $banner ) { return ''; }

        // Parágrafo: a descrição da música, se houver; senão o texto padrão.
        $paragrafo = trim( (string) get_post_meta( $music_id, CV_Fields::DESCRICAO, true ) );
        if ( '' === $paragrafo ) { $paragrafo = $cfg['paragrafo']; }

        return '<aside class="cv-pub-apos-letra" aria-label="Publicidade">'
             . ( $paragrafo ? '<p class="cv-pub-paragrafo">' . esc_html( $paragrafo ) . '</p>' : '' )
             . '<div class="cv-pub-aviso">' . esc_html( $cfg['aviso'] ) . '</div>'
             . $banner
             . '</aside>';
    }

    // AJAX admin — salvar banner
    public static function ajax_save_banner() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;

        $id         = absint( $_POST['id'] ?? 0 );
        $data = array(
            'titulo'      => sanitize_text_field( $_POST['titulo']      ?? '' ),
            'imagem_url'  => esc_url_raw( $_POST['imagem_url']          ?? '' ),
            'url_destino' => esc_url_raw( $_POST['url_destino']         ?? '' ),
            'texto_alt'   => sanitize_text_field( $_POST['texto_alt']   ?? '' ),
            'posicao'     => array_key_exists( $_POST['posicao'] ?? '', self::posicoes() ) ? sanitize_key( $_POST['posicao'] ) : 'apos_letra',
            'data_inicio' => sanitize_text_field( $_POST['data_inicio'] ?? '' ) ?: null,
            'data_fim'    => sanitize_text_field( $_POST['data_fim']    ?? '' ) ?: null,
            'ativo'       => absint( $_POST['ativo'] ?? 1 ),
        );
        $fmt = array( '%s','%s','%s','%s','%s','%s','%s','%d' );

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'cv_banners', $data, array( 'id' => $id ), $fmt, array( '%d' ) );
        } else {
            $data['cliques'] = 0;
            $fmt[]           = '%d';
            $wpdb->insert( $wpdb->prefix . 'cv_banners', $data, $fmt );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $id ) );
    }

    public static function ajax_delete_banner() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cv_banners', array( 'id' => absint( $_POST['id'] ?? 0 ) ), array( '%d' ) );
        wp_send_json_success();
    }
}
