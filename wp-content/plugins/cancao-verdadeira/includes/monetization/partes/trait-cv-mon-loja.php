<?php
// cancao-verdadeira/includes/monetization/partes/trait-cv-mon-loja.php
// Parte do CV_Monetization (trait CV_Mon_Loja): loja simples: shortcode [cv_loja] e AJAX de salvar/excluir produto.
// v2.38.0: saiu de class-cv-monetization.php, sem mudança de lógica.
// Os métodos continuam sendo chamados como CV_Monetization::metodo().

if ( ! defined( 'ABSPATH' ) ) { exit; }

trait CV_Mon_Loja {

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
}
