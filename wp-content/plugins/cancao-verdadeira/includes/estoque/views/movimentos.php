<?php
// cancao-verdadeira/includes/estoque/views/movimentos.php
// Aba "🔁 Estoque" da tela Estoque e Pedidos: lançar entrada (compra, com
// custo opcional), saída (venda direta, brinde, sorteio, perda) ou contagem
// (define o saldo exato), e ver o histórico dos últimos 100 movimentos.
// Incluído por CV_Estoque_Admin::render(). JS: assets/js/admin-estoque.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$variacoes = array_filter( CV_Estoque::variacoes(), function ( $v ) { return (int) $v->controla_estoque === 1; } );
$movs = $wpdb->get_results(
    "SELECT m.*, i.nome, v.variacao, u.display_name
       FROM {$wpdb->prefix}cv_estoque_mov m
       JOIN {$wpdb->prefix}cv_estoque_itens i ON i.id = m.item_id
       JOIN {$wpdb->prefix}cv_estoque_variacoes v ON v.id = m.variacao_id
  LEFT JOIN {$wpdb->users} u ON u.ID = m.user_id
      ORDER BY m.id DESC
      LIMIT 100"
);
$motivos = CV_Estoque::motivos_saida();
$rotulos_tipo = array(
    'entrada' => '⬆ Entrada',
    'saida'   => '⬇ Saída',
    'ajuste'  => '🔢 Contagem',
    'pedido'  => '🛍️ Pedido',
    'estorno' => '↩ Estorno',
);
?>
<div class="cv-est-grid-2">
    <div class="cv-section">
        <h2 class="cv-section-title">Lançar no estoque</h2>
        <?php if ( empty( $variacoes ) ) : ?>
        <p style="color:#6B4C3B">Cadastre primeiro uma Caneca ou uma Camiseta na aba <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-estoque&aba=itens' ) ); ?>">🏷️ Itens</a>.</p>
        <?php else : ?>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-em-variacao">Item</label>
            <select id="cv-em-variacao" class="cv-input">
                <?php foreach ( $variacoes as $v ) : ?>
                <option value="<?php echo esc_attr( $v->id ); ?>"><?php echo esc_html( CV_Estoque::rotulo( $v ) . ' (atual: ' . (int) $v->estoque_atual . ')' ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cv-form-group">
            <span class="cv-form-label">O que aconteceu?</span>
            <div style="display:flex;gap:16px;flex-wrap:wrap">
                <label><input type="radio" name="cv-em-op" value="entrada" checked> ⬆ Chegou mercadoria (compra)</label>
                <label><input type="radio" name="cv-em-op" value="saida"> ⬇ Saiu do estoque</label>
                <label><input type="radio" name="cv-em-op" value="ajuste"> 🔢 Contei o estoque</label>
            </div>
        </div>
        <div class="cv-est-grid-2">
            <div class="cv-form-group">
                <label class="cv-form-label" for="cv-em-qtd" id="cv-em-qtd-rotulo">Quantidade comprada</label>
                <input type="number" id="cv-em-qtd" class="cv-input" min="0" value="" placeholder="0" />
            </div>
            <div class="cv-form-group cv-em-so-entrada">
                <label class="cv-form-label" for="cv-em-custo">Custo por peça (R$, opcional)</label>
                <input type="text" id="cv-em-custo" class="cv-input" placeholder="18,50" />
            </div>
            <div class="cv-form-group cv-em-so-saida" style="display:none">
                <label class="cv-form-label" for="cv-em-motivo">Motivo</label>
                <select id="cv-em-motivo" class="cv-input">
                    <?php foreach ( $motivos as $k => $l ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $l ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-em-obs">Observação (opcional)</label>
            <input type="text" id="cv-em-obs" class="cv-input" maxlength="255" placeholder="Ex.: fornecedor, nota fiscal, ganhador do sorteio" />
        </div>
        <button id="cv-em-lancar" class="cv-btn cv-btn-primary">💾 Lançar</button>
        <?php endif; ?>
    </div>

    <div class="cv-section">
        <h2 class="cv-section-title">Estoque agora</h2>
        <table class="cv-table">
            <thead><tr><th>Item</th><th style="text-align:center">Saldo</th><th style="text-align:center">Mínimo</th></tr></thead>
            <tbody>
            <?php foreach ( $variacoes as $v ) : $baixo = (int) $v->estoque_atual <= (int) $v->estoque_minimo; ?>
            <tr>
                <td><?php echo esc_html( CV_Estoque::rotulo( $v ) ); ?><?php echo $v->item_ativo ? '' : ' <em style="color:#8A6A55">(inativo)</em>'; ?></td>
                <td style="text-align:center" class="<?php echo $baixo ? 'cv-est-saldo-baixo' : 'cv-est-saldo-ok'; ?>"><?php echo (int) $v->estoque_atual; ?><?php echo $baixo ? ' ⚠️' : ''; ?></td>
                <td style="text-align:center"><?php echo (int) $v->estoque_minimo; ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if ( empty( $variacoes ) ) : ?><tr><td colspan="3" style="color:#8A6A55">—</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="cv-section">
    <h2 class="cv-section-title">Histórico (últimos 100)</h2>
    <table class="cv-table">
        <thead><tr><th>Data</th><th>Item</th><th>Tipo</th><th style="text-align:right">Qtd</th><th style="text-align:right">Saldo depois</th><th>Motivo / observação</th><th>Quem</th></tr></thead>
        <tbody>
        <?php if ( empty( $movs ) ) : ?>
        <tr><td colspan="7" style="text-align:center;color:#8A6A55;padding:20px">Nenhum movimento ainda.</td></tr>
        <?php endif; ?>
        <?php foreach ( $movs as $m ) :
            $motivo = isset( $motivos[ $m->motivo ] ) ? $motivos[ $m->motivo ] : ( 'compra' === $m->motivo ? 'Compra' : '' ); ?>
        <tr>
            <td style="white-space:nowrap"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $m->criado_em ) ); ?></td>
            <td><?php echo esc_html( $m->nome . ( '' !== $m->variacao ? ' — ' . $m->variacao : '' ) ); ?></td>
            <td><?php echo esc_html( $rotulos_tipo[ $m->tipo ] ?? $m->tipo ); ?></td>
            <td style="text-align:right;font-weight:700;color:<?php echo $m->quantidade < 0 ? '#C0392B' : '#1C7C44'; ?>"><?php echo $m->quantidade > 0 ? '+' : ''; ?><?php echo (int) $m->quantidade; ?></td>
            <td style="text-align:right"><?php echo (int) $m->saldo_apos; ?></td>
            <td style="font-size:13px;color:#6B4C3B">
                <?php echo esc_html( implode( ' · ', array_filter( array(
                    $motivo,
                    $m->custo_unit > 0 ? 'R$ ' . number_format( (float) $m->custo_unit, 2, ',', '.' ) . '/peça' : '',
                    $m->obs,
                ) ) ) ); ?>
            </td>
            <td style="font-size:13px"><?php echo esc_html( $m->display_name ? $m->display_name : '—' ); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
