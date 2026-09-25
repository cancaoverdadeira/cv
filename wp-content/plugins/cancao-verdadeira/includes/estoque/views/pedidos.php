<?php
// cancao-verdadeira/includes/estoque/views/pedidos.php
// Aba "🛍️ Pedidos" da tela Estoque e Pedidos: pedidos feitos na Minha Área,
// filtrados pela situação. Ações: Confirmar (baixa o estoque), Entregue e
// Cancelar (se já estava confirmado, as peças voltam ao estoque). O cliente
// recebe um e-mail a cada mudança. Pagamento: só PIX (v2.48.0, CVPED<nº>).
// Incluído por CV_Estoque_Admin::render(). JS: assets/js/admin-estoque.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$status_lista = CV_Estoque::status_pedido();
$filtro = sanitize_key( $_GET['status'] ?? 'pendente' );
if ( 'todos' !== $filtro && ! isset( $status_lista[ $filtro ] ) ) { $filtro = 'pendente'; }

$contagem = array( 'todos' => 0 );
foreach ( $wpdb->get_results( "SELECT status, COUNT(*) n FROM {$wpdb->prefix}cv_pedidos GROUP BY status" ) as $c ) {
    $contagem[ $c->status ] = (int) $c->n;
    $contagem['todos']     += (int) $c->n;
}

$where = 'todos' === $filtro ? '' : $wpdb->prepare( 'WHERE p.status = %s', $filtro );
$pedidos = $wpdb->get_results(
    "SELECT p.*, i.nome, v.variacao, v.estoque_atual, i.controla_estoque, u.display_name, u.user_email
       FROM {$wpdb->prefix}cv_pedidos p
       JOIN {$wpdb->prefix}cv_estoque_itens i ON i.id = p.item_id
       JOIN {$wpdb->prefix}cv_estoque_variacoes v ON v.id = p.variacao_id
  LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
      {$where}
      ORDER BY p.id DESC
      LIMIT 200"
);
$acoes = array(
    'pendente'   => array( 'confirmado' => '✅ Confirmar', 'cancelado' => '✖ Cancelar' ),
    'confirmado' => array( 'entregue' => '📦 Entregue', 'cancelado' => '✖ Cancelar' ),
    'entregue'   => array( 'cancelado' => '✖ Cancelar' ),
    'cancelado'  => array(),
);
$base = admin_url( 'admin.php?page=cv-estoque&aba=pedidos&status=' );
?>
<div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
    <?php foreach ( array_merge( $status_lista, array( 'todos' => '📋 Todos' ) ) as $k => $rotulo ) : ?>
    <a href="<?php echo esc_url( $base . $k ); ?>" class="cv-btn <?php echo $filtro === $k ? 'cv-btn-primary' : 'cv-btn-outline'; ?> cv-est-mini">
        <?php echo esc_html( $rotulo ); ?> (<?php echo (int) ( $contagem[ $k ] ?? 0 ); ?>)
    </a>
    <?php endforeach; ?>
</div>

<div class="cv-section">
    <p style="margin-top:0;font-size:13px;color:#8A6A55">
        Fluxo: o cliente pede na Minha Área e paga por <strong>PIX</strong> (identificação CVPED + número do pedido) → você confere o PIX no banco → <strong>Confirmar</strong> tira as peças do estoque → <strong>Entregue</strong> encerra. O cliente recebe e-mail a cada passo.
    </p>
    <table class="cv-table">
        <thead><tr><th>#</th><th>Data</th><th>Cliente</th><th>Item</th><th style="text-align:center">Qtd</th><th style="text-align:right">Valor</th><th>Observação</th><th>Situação</th><th style="text-align:center">Ações</th></tr></thead>
        <tbody>
        <?php if ( empty( $pedidos ) ) : ?>
        <tr><td colspan="9" style="text-align:center;color:#8A6A55;padding:24px">Nenhum pedido nesta situação.</td></tr>
        <?php endif; ?>
        <?php foreach ( $pedidos as $p ) :
            $falta = 'pendente' === $p->status && $p->controla_estoque && (int) $p->estoque_atual < (int) $p->quantidade; ?>
        <tr>
            <td><strong>#<?php echo (int) $p->id; ?></strong><br><span style="font-size:11px;color:#8A6A55" title="Identificação que aparece no extrato do PIX">PIX: <?php echo esc_html( CV_Estoque_Area::txid( $p->id ) ); ?></span></td>
            <td style="white-space:nowrap"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $p->criado_em ) ); ?></td>
            <td>
                <?php echo esc_html( $p->display_name ? $p->display_name : '(usuário excluído)' ); ?>
                <?php if ( $p->user_email ) : ?><br><a href="mailto:<?php echo esc_attr( $p->user_email ); ?>" style="font-size:12px"><?php echo esc_html( $p->user_email ); ?></a><?php endif; ?>
            </td>
            <td>
                <?php echo esc_html( $p->nome . ( '' !== $p->variacao ? ' — ' . $p->variacao : '' ) ); ?>
                <?php if ( $falta ) : ?><br><span class="cv-est-saldo-baixo" style="font-size:12px">⚠️ só <?php echo (int) $p->estoque_atual; ?> em estoque</span><?php endif; ?>
            </td>
            <td style="text-align:center"><?php echo (int) $p->quantidade; ?></td>
            <td style="text-align:right;white-space:nowrap">R$ <?php echo esc_html( number_format( $p->quantidade * (float) $p->preco_unit, 2, ',', '.' ) ); ?></td>
            <td style="font-size:13px;color:#6B4C3B;max-width:220px"><?php echo esc_html( $p->obs ); ?></td>
            <td><span class="cv-est-status cv-est-status-<?php echo esc_attr( $p->status ); ?>"><?php echo esc_html( $status_lista[ $p->status ] ?? $p->status ); ?></span></td>
            <td style="text-align:center;white-space:nowrap">
                <?php foreach ( $acoes[ $p->status ] ?? array() as $novo => $rotulo ) : ?>
                <button class="cv-btn <?php echo 'cancelado' === $novo ? 'cv-btn-outline' : 'cv-btn-primary'; ?> cv-est-mini cv-ep-status"
                        data-id="<?php echo (int) $p->id; ?>" data-status="<?php echo esc_attr( $novo ); ?>"
                        <?php echo ( 'confirmado' === $novo && $falta ) ? 'disabled title="Estoque insuficiente"' : ''; ?>><?php echo esc_html( $rotulo ); ?></button>
                <?php endforeach; ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
