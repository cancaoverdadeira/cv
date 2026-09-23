<?php
// cancao-verdadeira/includes/monetization/class-cv-monetization.php
// Gerado em: 2026-06-14 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Sistema de monetização em 4 módulos:
// 1. Banners de parceiros — imagem+link+texto+data+tracking de cliques,
//    posicionamento configurável (home topo, meio de página, rodapé),
//    tipo "Anúncio Interno" exclusivo para home com filtro no cadastro.
// 2. Loja simples — ebook, pendrive, caneca, camiseta com link externo,
//    shortcode [cv_loja] para uso no Elementor.
// 3. Sorteios automáticos por data agendada via WP-Cron, participantes
//    são assinantes cadastrados, vencedor recebe e-mail automático.
// 4. Brindes para membros da comunidade — admin cadastra e escolhe
//    destinatário, sistema envia e-mail de notificação.
// v2.28.0 (regras do usuário para publicidade):
//   - nunca no topo: a posição "home_topo" foi removida;
//   - sem banner rotativo: cada posição mostra UM banner fixo (o ativo mais
//     recente), nada de sorteio a cada visita;
//   - nova posição "apos_letra" na página da música: letra → parágrafo curto
//     → aviso "Leia após a publicidade" → banner → resto da página;
//   - clique: o destino vem do banner cadastrado (antes vinha da URL, o que
//     permitia usar o site para redirecionar para qualquer endereço).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Monetization {

    public static function init() {
        // Shortcodes
        add_shortcode( 'cv_banner',  array( __CLASS__, 'shortcode_banner' ) );
        add_shortcode( 'cv_loja',    array( __CLASS__, 'shortcode_loja' ) );

        // AJAX — tracking de cliques em banners
        add_action( 'wp_ajax_cv_banner_click',        array( __CLASS__, 'ajax_banner_click' ) );
        add_action( 'wp_ajax_nopriv_cv_banner_click', array( __CLASS__, 'ajax_banner_click' ) );

        // AJAX — admin: salvar banner, produto, sorteio, brinde
        add_action( 'wp_ajax_cv_save_banner',   array( __CLASS__, 'ajax_save_banner' ) );
        add_action( 'wp_ajax_cv_delete_banner', array( __CLASS__, 'ajax_delete_banner' ) );
        add_action( 'wp_ajax_cv_save_produto',  array( __CLASS__, 'ajax_save_produto' ) );
        add_action( 'wp_ajax_cv_delete_produto',array( __CLASS__, 'ajax_delete_produto' ) );
        add_action( 'wp_ajax_cv_save_sorteio',  array( __CLASS__, 'ajax_save_sorteio' ) );
        add_action( 'wp_ajax_cv_save_brinde',   array( __CLASS__, 'ajax_save_brinde' ) );
        add_action( 'wp_ajax_cv_enviar_brinde', array( __CLASS__, 'ajax_enviar_brinde' ) );

        // Cron de sorteios automáticos
        add_action( 'cv_cron_sorteios', array( __CLASS__, 'processar_sorteios' ) );
    }

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
        $paragrafo = trim( (string) get_post_meta( $music_id, '_cv_descricao', true ) );
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

    // ════════════════════════════════════════════════════════════════
    // 2. LOJA SIMPLES
    // ════════════════════════════════════════════════════════════════

    /**
     * Shortcode [cv_loja]
     *
     * Parâmetros:
     *   categoria = todos | ebook | fisico | digital (padrão: todos)
     *   colunas   = 2, 3 ou 4 (padrão: 3)
     *   titulo    = título da seção (padrão: "Nossa Loja")
     *   limite    = número de produtos (padrão: 12)
     *
     * Exemplos:
     *   [cv_loja]
     *   [cv_loja categoria="ebook" titulo="E-books Sertanejos"]
     *   [cv_loja categoria="fisico" colunas="2" limite="4"]
     */
    public static function shortcode_loja( $atts ) {
        $atts = shortcode_atts( array(
            'categoria' => 'todos',
            'colunas'   => 3,
            'titulo'    => 'Nossa Loja',
            'limite'    => 12,
        ), $atts, 'cv_loja' );

        $categoria = sanitize_text_field( $atts['categoria'] );
        $colunas   = in_array( (int) $atts['colunas'], array( 2, 3, 4 ), true ) ? (int) $atts['colunas'] : 3;
        $titulo    = sanitize_text_field( $atts['titulo'] );
        $limite    = min( absint( $atts['limite'] ), 50 );

        global $wpdb;

        $where = $categoria !== 'todos'
            ? $wpdb->prepare( "AND categoria = %s", $categoria )
            : '';

        $produtos = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_produtos
             WHERE ativo = 1 $where
             ORDER BY ordem ASC, id DESC
             LIMIT %d",
            $limite
        ) );

        if ( empty( $produtos ) ) {
            return '<p class="cv-sem-musicas">Nenhum produto disponível no momento.</p>';
        }

        // Ícones por categoria
        $icones = array(
            'ebook'   => '📖',
            'fisico'  => '🎁',
            'digital' => '💿',
        );

        ob_start();
        ?>
        <div class="cv-loja-wrap">
            <?php if ( $titulo ) : ?>
                <h2 class="cv-section-titulo">🛒 <?php echo esc_html( $titulo ); ?></h2>
            <?php endif; ?>

            <div class="cv-loja-grid cv-grid-col-<?php echo esc_attr( $colunas ); ?>">
                <?php foreach ( $produtos as $p ) :
                    $icone = $icones[ $p->categoria ] ?? '🎵';
                    $preco_fmt = 'R$ ' . number_format( (float) $p->preco, 2, ',', '.' );
                    $preco_antigo = $p->preco_antigo > 0
                        ? 'R$ ' . number_format( (float) $p->preco_antigo, 2, ',', '.' )
                        : '';
                ?>
                <div class="cv-produto-card">

                    <!-- Imagem / Capa -->
                    <div class="cv-produto-capa">
                        <?php if ( $p->imagem_url ) : ?>
                            <img src="<?php echo esc_url( $p->imagem_url ); ?>"
                                 alt="<?php echo esc_attr( $p->nome ); ?>"
                                 loading="lazy" />
                        <?php else : ?>
                            <div class="cv-produto-capa-placeholder">
                                <span><?php echo $icone; ?></span>
                            </div>
                        <?php endif; ?>

                        <?php if ( $p->badge ) : ?>
                            <span class="cv-produto-badge"><?php echo esc_html( $p->badge ); ?></span>
                        <?php endif; ?>

                        <span class="cv-produto-categoria-tag">
                            <?php echo $icone; ?> <?php echo esc_html( ucfirst( $p->categoria ) ); ?>
                        </span>
                    </div>

                    <!-- Informações -->
                    <div class="cv-produto-info">
                        <h3 class="cv-produto-nome"><?php echo esc_html( $p->nome ); ?></h3>

                        <?php if ( $p->descricao ) : ?>
                            <p class="cv-produto-desc"><?php echo esc_html( mb_substr( $p->descricao, 0, 100 ) ); ?></p>
                        <?php endif; ?>

                        <div class="cv-produto-preco-wrap">
                            <?php if ( $preco_antigo ) : ?>
                                <span class="cv-produto-preco-antigo"><?php echo esc_html( $preco_antigo ); ?></span>
                            <?php endif; ?>
                            <span class="cv-produto-preco"><?php echo esc_html( $preco_fmt ); ?></span>
                        </div>

                        <a href="<?php echo esc_url( $p->url_compra ); ?>"
                           class="cv-btn-comprar"
                           target="_blank"
                           rel="sponsored nofollow noopener noreferrer">
                            🛒 <?php echo esc_html( $p->texto_botao ?: 'Comprar agora' ); ?>
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    // AJAX admin — salvar produto
    public static function ajax_save_produto() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );

        $data = array(
            'nome'        => sanitize_text_field( $_POST['nome']         ?? '' ),
            'descricao'   => sanitize_textarea_field( $_POST['descricao'] ?? '' ),
            'categoria'   => sanitize_text_field( $_POST['categoria']    ?? 'fisico' ),
            'preco'       => (float) str_replace( ',', '.', $_POST['preco'] ?? '0' ),
            'preco_antigo'=> (float) str_replace( ',', '.', $_POST['preco_antigo'] ?? '0' ),
            'imagem_url'  => esc_url_raw( $_POST['imagem_url']           ?? '' ),
            'url_compra'  => esc_url_raw( $_POST['url_compra']           ?? '' ),
            'texto_botao' => sanitize_text_field( $_POST['texto_botao']  ?? 'Comprar agora' ),
            'badge'       => sanitize_text_field( $_POST['badge']        ?? '' ),
            'ordem'       => absint( $_POST['ordem'] ?? 0 ),
            'ativo'       => absint( $_POST['ativo'] ?? 1 ),
        );
        $fmt = array( '%s','%s','%s','%f','%f','%s','%s','%s','%s','%d','%d' );

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'cv_produtos', $data, array( 'id' => $id ), $fmt, array( '%d' ) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'cv_produtos', $data, $fmt );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $id ) );
    }

    public static function ajax_delete_produto() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cv_produtos', array( 'id' => absint( $_POST['id'] ?? 0 ) ), array( '%d' ) );
        wp_send_json_success();
    }

    // ════════════════════════════════════════════════════════════════
    // 3. SORTEIOS AUTOMÁTICOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Processa sorteios com data_sorteio <= agora e status = agendado.
     * Chamado via WP-Cron diariamente.
     */
    public static function processar_sorteios() {
        global $wpdb;

        $agora = current_time( 'mysql' );

        $sorteios = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_sorteios
             WHERE status = 'agendado' AND data_sorteio <= %s",
            $agora
        ) );

        if ( empty( $sorteios ) ) { return; }

        foreach ( $sorteios as $sorteio ) {

            // Busca participantes elegíveis (assinantes ativos)
            $participantes = $wpdb->get_results(
                "SELECT * FROM {$wpdb->prefix}cv_subscribers ORDER BY RAND() LIMIT 1000"
            );

            if ( empty( $participantes ) ) {
                $wpdb->update(
                    $wpdb->prefix . 'cv_sorteios',
                    array( 'status' => 'sem_participantes' ),
                    array( 'id' => $sorteio->id ),
                    array( '%s' ), array( '%d' )
                );
                continue;
            }

            // Sorteia um vencedor aleatório
            $vencedor = $participantes[ array_rand( $participantes ) ];

            // Salva o resultado
            $wpdb->update(
                $wpdb->prefix . 'cv_sorteios',
                array(
                    'status'           => 'realizado',
                    'vencedor_email'   => $vencedor->email,
                    'vencedor_nome'    => $vencedor->name ?: 'Assinante',
                    'realizado_em'     => $agora,
                    'total_participantes' => count( $participantes ),
                ),
                array( 'id' => $sorteio->id ),
                array( '%s','%s','%s','%s','%d' ),
                array( '%d' )
            );

            // Envia e-mail ao vencedor
            self::enviar_email_vencedor( $sorteio, $vencedor );

            // Log
            if ( class_exists( 'CV_Advanced' ) ) {
                CV_Advanced::log(
                    'sorteio_realizado',
                    'Sorteio "' . $sorteio->titulo . '" realizado. Vencedor: ' . $vencedor->email,
                    'sorteio',
                    $sorteio->id
                );
            }
        }
    }

    /**
     * Envia e-mail ao vencedor do sorteio.
     */
    private static function enviar_email_vencedor( $sorteio, $vencedor ) {
        $site_name = get_bloginfo( 'name' );
        $nome      = $vencedor->name ?: 'Amigo(a) sertanejo(a)';
        $premio    = esc_html( $sorteio->premio );
        $descricao = esc_html( $sorteio->descricao );

        $assunto = '🎉 Parabéns! Você ganhou o sorteio do ' . $site_name;

        $corpo = "Olá, {$nome}!\n\n"
               . "Você foi sorteado(a) e GANHOU:\n\n"
               . "🏆 {$premio}\n\n"
               . ( $descricao ? "{$descricao}\n\n" : '' )
               . "Entre em contato conosco respondendo este e-mail para retirar seu prêmio.\n\n"
               . "Com carinho,\n"
               . "Equipe " . $site_name . "\n"
               . home_url();

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        wp_mail( $vencedor->email, $assunto, $corpo, $headers );

        // Também notifica o admin
        $admin_corpo = "Sorteio realizado: {$sorteio->titulo}\n"
                     . "Vencedor: {$nome} ({$vencedor->email})\n"
                     . "Prêmio: {$premio}\n"
                     . "Total de participantes: " . ( $sorteio->total_participantes ?? '?' );

        wp_mail( get_option( 'admin_email' ), '[CV] Sorteio Realizado — ' . $sorteio->titulo, $admin_corpo, $headers );
    }

    public static function ajax_save_sorteio() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );

        $data = array(
            'titulo'       => sanitize_text_field( $_POST['titulo']       ?? '' ),
            'descricao'    => sanitize_textarea_field( $_POST['descricao'] ?? '' ),
            'premio'       => sanitize_text_field( $_POST['premio']       ?? '' ),
            'imagem_url'   => esc_url_raw( $_POST['imagem_url']           ?? '' ),
            'data_sorteio' => sanitize_text_field( $_POST['data_sorteio'] ?? '' ),
            'status'       => 'agendado',
        );
        $fmt = array( '%s','%s','%s','%s','%s','%s' );

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'cv_sorteios', $data, array( 'id' => $id ), $fmt, array( '%d' ) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'cv_sorteios', $data, $fmt );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $id ) );
    }

    // ════════════════════════════════════════════════════════════════
    // 4. BRINDES PARA MEMBROS
    // ════════════════════════════════════════════════════════════════

    public static function ajax_save_brinde() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;
        $id = absint( $_POST['id'] ?? 0 );

        $data = array(
            'titulo'      => sanitize_text_field( $_POST['titulo']      ?? '' ),
            'descricao'   => sanitize_textarea_field( $_POST['descricao'] ?? '' ),
            'imagem_url'  => esc_url_raw( $_POST['imagem_url']           ?? '' ),
            'quantidade'  => absint( $_POST['quantidade'] ?? 1 ),
            'status'      => 'disponivel',
        );
        $fmt = array( '%s','%s','%s','%d','%s' );

        if ( $id ) {
            $wpdb->update( $wpdb->prefix . 'cv_brindes', $data, array( 'id' => $id ), $fmt, array( '%d' ) );
        } else {
            $wpdb->insert( $wpdb->prefix . 'cv_brindes', $data, $fmt );
            $id = $wpdb->insert_id;
        }

        wp_send_json_success( array( 'id' => $id ) );
    }

    /**
     * Envia um brinde para um assinante específico.
     * Admin escolhe o brinde e o destinatário pelo e-mail.
     */
    public static function ajax_enviar_brinde() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }

        global $wpdb;

        $brinde_id = absint( $_POST['brinde_id']      ?? 0 );
        $email     = sanitize_email( $_POST['email']  ?? '' );
        $mensagem  = sanitize_textarea_field( $_POST['mensagem'] ?? '' );

        if ( ! $brinde_id || ! $email ) {
            wp_send_json_error( array( 'message' => 'Dados incompletos.' ) );
        }

        $brinde = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_brindes WHERE id = %d AND status = 'disponivel'",
            $brinde_id
        ) );

        if ( ! $brinde ) {
            wp_send_json_error( array( 'message' => 'Brinde não encontrado ou indisponível.' ) );
        }

        // Busca nome do destinatário (assinante ou usuário WP)
        $nome = '';
        $sub  = $wpdb->get_row( $wpdb->prepare(
            "SELECT name FROM {$wpdb->prefix}cv_subscribers WHERE email = %s",
            $email
        ) );
        if ( $sub ) { $nome = $sub->name; }
        if ( ! $nome ) {
            $wp_user = get_user_by( 'email', $email );
            if ( $wp_user ) { $nome = $wp_user->display_name; }
        }
        $nome = $nome ?: 'Amigo(a) sertanejo(a)';

        // Registra entrega
        $wpdb->insert(
            $wpdb->prefix . 'cv_brindes_entregas',
            array(
                'brinde_id'      => $brinde_id,
                'email'          => $email,
                'nome'           => $nome,
                'mensagem_admin' => $mensagem,
                'enviado_em'     => current_time( 'mysql' ),
                'enviado_por'    => get_current_user_id(),
            ),
            array( '%d','%s','%s','%s','%s','%d' )
        );

        // Atualiza quantidade disponível
        $wpdb->query( $wpdb->prepare(
            "UPDATE {$wpdb->prefix}cv_brindes
             SET quantidade = GREATEST(0, quantidade - 1),
                 status = CASE WHEN quantidade <= 1 THEN 'esgotado' ELSE 'disponivel' END
             WHERE id = %d",
            $brinde_id
        ) );

        // Envia e-mail ao destinatário
        $site_name = get_bloginfo( 'name' );
        $assunto   = '🎁 Você recebeu um brinde do ' . $site_name . '!';
        $corpo     = "Olá, {$nome}!\n\n"
                   . "Temos uma surpresa para você: 🎁 {$brinde->titulo}\n\n"
                   . ( $brinde->descricao ? $brinde->descricao . "\n\n" : '' )
                   . ( $mensagem ? "Mensagem da equipe:\n{$mensagem}\n\n" : '' )
                   . "Entre em contato respondendo este e-mail para combinar a entrega.\n\n"
                   . "Com carinho,\nEquipe " . $site_name . "\n" . home_url();

        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option( 'admin_email' ) . '>',
        );

        $enviado = wp_mail( $email, $assunto, $corpo, $headers );

        if ( class_exists( 'CV_Advanced' ) ) {
            CV_Advanced::log(
                'brinde_enviado',
                'Brinde "' . $brinde->titulo . '" enviado para ' . $email,
                'brinde',
                $brinde_id
            );
        }

        wp_send_json_success( array(
            'message' => $enviado
                ? 'Brinde enviado com sucesso para ' . $email
                : 'Entrega registrada, mas o e-mail não pôde ser enviado. Verifique as configurações de e-mail.',
        ) );
    }
}

CV_Monetization::init();
