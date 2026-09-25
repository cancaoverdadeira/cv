<?php
// cancao-verdadeira/includes/estoque/class-cv-estoque-area.php
// Criado em: 25/09/2026 (plugin v2.47.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Aba "🛍️ Meus Pedidos" da Minha Área: o cliente escolhe o item no select
// (E-book, Caneca, Camiseta por tamanho; esgotados aparecem desativados),
// a quantidade e uma observação, e usa os botões ao lado para Incluir o
// pedido ou Excluir (desistir de) um pedido ainda pendente.
// O tema chama CV_Estoque_Area::render_aba() dentro da aba. CSS e JS:
// assets/css/cv-pedidos.css e assets/js/cv-pedidos.js (só na Minha Área).
// v2.48.0: pagamento SOMENTE por PIX — cada pedido pendente tem o botão
// "💠 Pagar com PIX" (QR + copia e cola no valor do pedido, identificação
// CVPED<número>), e o PIX do pedido novo já abre sozinho.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Estoque_Area {

    const TEMPLATE = 'templates/page-user-dashboard.php';

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 30 );
    }

    public static function enqueue() {
        if ( ! is_user_logged_in() || ! is_page_template( self::TEMPLATE ) ) { return; }
        wp_enqueue_style( 'cv-pedidos', CV_PLUGIN_URL . 'assets/css/cv-pedidos.css', array(), CV_VERSION );
        wp_enqueue_script( 'cv-pedidos', CV_PLUGIN_URL . 'assets/js/cv-pedidos.js', array( 'jquery' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-pedidos', 'window.cvPedidos = ' . wp_json_encode( array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cv_pedido_nonce' ),
        ) ) . ';', 'before' );
    }

    /** <option>s do select: itens ativos; esgotados ficam desativados. */
    public static function opcoes_select() {
        $tipos = CV_Estoque::tipos();
        $html  = '<option value="">Escolha um item…</option>';
        foreach ( CV_Estoque::variacoes( true ) as $v ) {
            $icone  = mb_substr( $tipos[ $v->tipo ] ?? '', 0, 1 );
            $texto  = trim( $icone . ' ' . CV_Estoque::rotulo( $v ) ) . ' — R$ ' . number_format( (float) $v->preco, 2, ',', '.' );
            $saldo  = (int) $v->estoque_atual;
            $esgot  = $v->controla_estoque && $saldo < 1;
            if ( $esgot ) {
                $texto .= ' (esgotado)';
            } elseif ( $v->controla_estoque && $saldo <= 5 ) {
                $texto .= ' (últimas ' . $saldo . ')';
            }
            $max   = $v->controla_estoque ? min( $saldo, CV_Estoque::MAX_QTD_PEDIDO ) : CV_Estoque::MAX_QTD_PEDIDO;
            $html .= sprintf(
                '<option value="%d" data-max="%d"%s>%s</option>',
                (int) $v->id, (int) $max, $esgot ? ' disabled' : '', esc_html( $texto )
            );
        }
        return $html;
    }

    /** Identificação do pedido no PIX (aparece no extrato do banco). */
    public static function txid( $pedido_id ) {
        return 'CVPED' . (int) $pedido_id;
    }

    /** Lista dos pedidos do usuário, com "Pagar com PIX" e "Excluir" nos pendentes. */
    public static function lista_pedidos( $user_id ) {
        $pedidos = CV_Estoque::pedidos_do_usuario( $user_id );
        if ( empty( $pedidos ) ) {
            return '<div class="cv-empty">🛍️ Você ainda não fez pedidos. Escolha um item acima e clique em "Incluir pedido".</div>';
        }
        $status = CV_Estoque::status_pedido();
        $tipos  = CV_Estoque::tipos();
        ob_start();
        ?>
        <ul class="cv-ped-lista">
            <?php foreach ( $pedidos as $p ) : ?>
            <li class="cv-ped-linha cv-ped-<?php echo esc_attr( $p->status ); ?>">
                <div class="cv-ped-info">
                    <strong><?php echo esc_html( trim( mb_substr( $tipos[ $p->tipo ] ?? '', 0, 1 ) . ' ' . $p->nome . ( '' !== $p->variacao ? ' — ' . $p->variacao : '' ) ) ); ?></strong>
                    <span class="cv-ped-meta">
                        Pedido #<?php echo (int) $p->id; ?> · <?php echo esc_html( mysql2date( 'd/m/Y', $p->criado_em ) ); ?>
                        · <?php echo (int) $p->quantidade; ?> un. · R$ <?php echo esc_html( number_format( $p->quantidade * (float) $p->preco_unit, 2, ',', '.' ) ); ?>
                    </span>
                    <?php if ( $p->obs ) : ?><span class="cv-ped-obs"><?php echo esc_html( $p->obs ); ?></span><?php endif; ?>
                </div>
                <span class="cv-ped-status"><?php echo esc_html( $status[ $p->status ] ?? $p->status ); ?></span>
                <?php if ( 'pendente' === $p->status ) : ?>
                <span class="cv-ped-acoes">
                    <button type="button" class="cv-btn cv-btn-primary cv-btn-sm cv-ped-pagar" data-id="<?php echo (int) $p->id; ?>" aria-expanded="false">💠 Pagar com PIX</button>
                    <button type="button" class="cv-btn cv-btn-secondary cv-btn-sm cv-ped-excluir" data-id="<?php echo (int) $p->id; ?>">🗑 Excluir</button>
                </span>
                <div class="cv-ped-pix" id="cv-ped-pix-<?php echo (int) $p->id; ?>" hidden>
                    <?php echo CV_Pix::bloco( $p->quantidade * (float) $p->preco_unit, self::txid( $p->id ), 'Pedido ' . (int) $p->id, 'Pagamento do pedido #' . (int) $p->id ); ?>
                    <p class="cv-ped-pix-nota">Depois de pagar, é só aguardar: conferimos o PIX e confirmamos o pedido por e-mail.</p>
                </div>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php
        return ob_get_clean();
    }

    /** Conteúdo completo da aba (formulário + lista). */
    public static function render_aba( $user_id ) {
        ob_start();
        ?>
        <div class="cv-ped">
            <p class="cv-ped-intro">
                Leve a Canção Verdadeira com você! Escolha o item, a quantidade e clique em <strong>Incluir pedido</strong>.
                O pagamento é <strong>somente por PIX</strong> (o QR Code aparece logo depois do pedido), e combinamos a entrega com você.
            </p>
            <div class="cv-ped-form">
                <div class="cv-ped-campos">
                    <label class="cv-ped-campo cv-ped-campo-item">
                        <span>Item</span>
                        <select id="cv-ped-item" class="cv-input"><?php echo self::opcoes_select(); ?></select>
                    </label>
                    <label class="cv-ped-campo cv-ped-campo-qtd">
                        <span>Quantidade</span>
                        <input type="number" id="cv-ped-qtd" class="cv-input" min="1" max="<?php echo (int) CV_Estoque::MAX_QTD_PEDIDO; ?>" value="1" />
                    </label>
                    <label class="cv-ped-campo cv-ped-campo-obs">
                        <span>Observação (opcional)</span>
                        <input type="text" id="cv-ped-obs" class="cv-input" maxlength="200" placeholder="Ex.: melhor horário para contato, cidade" />
                    </label>
                </div>
                <div class="cv-ped-botoes">
                    <button type="button" id="cv-ped-incluir" class="cv-btn cv-btn-primary">➕ Incluir pedido</button>
                </div>
            </div>
            <div id="cv-ped-msg" class="cv-ped-msg" role="status" aria-live="polite" style="display:none"></div>
            <h3 class="cv-ped-titulo">Meus pedidos</h3>
            <div id="cv-ped-lista"><?php echo self::lista_pedidos( $user_id ); ?></div>
        </div>
        <?php
        return ob_get_clean();
    }
}

CV_Estoque_Area::init();
