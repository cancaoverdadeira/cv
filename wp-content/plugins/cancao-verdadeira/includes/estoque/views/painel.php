<?php
// cancao-verdadeira/includes/estoque/views/painel.php
// Aba "📊 Painel" da tela Estoque e Pedidos: indicadores do topo, estoque com
// previsão de duração, gráficos (mais retirados, saídas por mês, tamanhos),
// ranking dos itens e o e-mail que recebe os alertas de estoque baixo.
// Os números vêm de CV_Estoque_Analise; os gráficos são desenhados pelo
// assets/js/admin-estoque.js a partir do JSON #cv-est-dados (Chart.js).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$periodos = array( 30 => '30 dias', 90 => '90 dias', 365 => '12 meses', 0 => 'Tudo' );
$dias = isset( $_GET['periodo'] ) ? absint( $_GET['periodo'] ) : 30;
if ( ! isset( $periodos[ $dias ] ) ) { $dias = 30; }

$k         = CV_Estoque_Analise::indicadores( $dias );
$retirados = CV_Estoque_Analise::mais_retirados( $dias );
$ranking   = CV_Estoque_Analise::ranking( $dias );
$tamanhos  = CV_Estoque_Analise::tamanhos( $dias );
$por_mes   = CV_Estoque_Analise::saidas_por_mes();
$previsao  = CV_Estoque_Analise::previsao();
$tipos     = CV_Estoque::tipos();
$reais     = function ( $v ) { return 'R$ ' . number_format( (float) $v, 2, ',', '.' ); };
$base      = admin_url( 'admin.php?page=cv-estoque&aba=painel&periodo=' );
?>
<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:14px">
    <span style="font-size:13px;color:#8A6A55">Período:</span>
    <?php foreach ( $periodos as $d => $rotulo ) : ?>
    <a href="<?php echo esc_url( $base . $d ); ?>" class="cv-btn <?php echo $dias === $d ? 'cv-btn-primary' : 'cv-btn-outline'; ?> cv-est-mini"><?php echo esc_html( $rotulo ); ?></a>
    <?php endforeach; ?>
</div>

<div class="cv-kpi-grid">
    <div class="cv-kpi-card"><div class="cv-kpi-icon">🏷️</div><div class="cv-kpi-value" data-target="<?php echo (int) $k['itens']; ?>"><?php echo (int) $k['itens']; ?></div><div class="cv-kpi-label">Itens ativos</div></div>
    <div class="cv-kpi-card"><div class="cv-kpi-icon">📦</div><div class="cv-kpi-value" data-target="<?php echo (int) $k['pecas']; ?>"><?php echo (int) $k['pecas']; ?></div><div class="cv-kpi-label">Peças em estoque</div></div>
    <div class="cv-kpi-card"><div class="cv-kpi-icon">💰</div><div class="cv-est-kpi-reais"><?php echo esc_html( $reais( $k['valor'] ) ); ?></div><div class="cv-kpi-label">Valor em estoque (preço de venda)</div></div>
    <div class="cv-kpi-card<?php echo $k['pendentes'] ? ' cv-kpi-highlight' : ''; ?>"><div class="cv-kpi-icon">⏳</div><div class="cv-kpi-value" data-target="<?php echo (int) $k['pendentes']; ?>"><?php echo (int) $k['pendentes']; ?></div><div class="cv-kpi-label"><a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-estoque&aba=pedidos' ) ); ?>">Pedidos pendentes</a></div></div>
    <div class="cv-kpi-card"><div class="cv-kpi-icon">🧾</div><div class="cv-est-kpi-reais"><?php echo esc_html( $reais( $k['faturamento'] ) ); ?></div><div class="cv-kpi-label">Vendido no período (<?php echo (int) $k['unidades']; ?> un.)</div></div>
    <div class="cv-kpi-card<?php echo $k['alertas'] ? ' cv-kpi-highlight' : ''; ?>"><div class="cv-kpi-icon">⚠️</div><div class="cv-kpi-value" data-target="<?php echo (int) $k['alertas']; ?>"><?php echo (int) $k['alertas']; ?></div><div class="cv-kpi-label">No estoque mínimo</div></div>
</div>

<div class="cv-est-grid-2">
    <div class="cv-section">
        <h2 class="cv-section-title">🔝 Mais retirados (<?php echo esc_html( $periodos[ $dias ] ); ?>)</h2>
        <?php if ( empty( $retirados ) ) : ?>
        <p style="color:#8A6A55">Ainda não houve saídas neste período.</p>
        <?php else : ?>
        <div style="height:260px"><canvas id="cv-est-graf-retirados"></canvas></div>
        <?php endif; ?>
    </div>
    <div class="cv-section">
        <h2 class="cv-section-title">📅 Saídas por mês (últimos 6 meses)</h2>
        <?php if ( empty( $por_mes['series'] ) ) : ?>
        <p style="color:#8A6A55">Ainda não houve saídas.</p>
        <?php else : ?>
        <div style="height:260px"><canvas id="cv-est-graf-meses"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<div class="cv-section">
    <h2 class="cv-section-title">🏆 Ranking dos itens (<?php echo esc_html( $periodos[ $dias ] ); ?>)</h2>
    <table class="cv-table">
        <thead><tr><th>#</th><th>Item</th><th style="text-align:center">Pedidos</th><th style="text-align:center">Unidades vendidas</th><th style="text-align:right">Faturamento</th><th style="text-align:center" title="Pedidos confirmados ÷ pedidos feitos">Conversão</th><th style="text-align:right" title="Média das compras com custo informado">Custo médio</th><th style="text-align:center" title="(preço − custo) ÷ preço">Margem</th></tr></thead>
        <tbody>
        <?php if ( empty( $ranking ) ) : ?><tr><td colspan="8" style="color:#8A6A55;text-align:center">Nenhum item cadastrado.</td></tr><?php endif; ?>
        <?php foreach ( $ranking as $i => $r ) : ?>
        <tr>
            <td><?php echo $i < 3 && $r->faturamento > 0 ? array( '🥇', '🥈', '🥉' )[ $i ] : ( $i + 1 ); ?></td>
            <td><?php echo esc_html( mb_substr( $tipos[ $r->tipo ] ?? '', 0, 1 ) . ' ' . $r->nome ); ?></td>
            <td style="text-align:center"><?php echo (int) $r->pedidos; ?></td>
            <td style="text-align:center"><?php echo (int) $r->unidades; ?></td>
            <td style="text-align:right;font-weight:700"><?php echo esc_html( $reais( $r->faturamento ) ); ?></td>
            <td style="text-align:center"><?php echo null === $r->conversao ? '—' : (int) $r->conversao . '%'; ?></td>
            <td style="text-align:right"><?php echo null === $r->custo ? '—' : esc_html( $reais( $r->custo ) ); ?></td>
            <td style="text-align:center"><?php echo null === $r->margem ? '—' : (int) $r->margem . '%'; ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="font-size:12px;color:#8A6A55;margin:8px 0 0">Custo médio e margem aparecem quando você informa o "custo por peça" nas entradas da aba 🔁 Estoque.</p>
</div>

<div class="cv-est-grid-2">
    <div class="cv-section">
        <h2 class="cv-section-title">⏱ Quanto tempo o estoque dura</h2>
        <table class="cv-table">
            <thead><tr><th>Item</th><th style="text-align:center">Saldo</th><th style="text-align:center">Saída/dia (30d)</th><th style="text-align:center">Dura</th></tr></thead>
            <tbody>
            <?php if ( empty( $previsao ) ) : ?><tr><td colspan="4" style="color:#8A6A55">—</td></tr><?php endif; ?>
            <?php foreach ( $previsao as $p ) : $baixo = $p->saldo <= $p->minimo; ?>
            <tr>
                <td><?php echo esc_html( $p->rotulo ); ?></td>
                <td style="text-align:center" class="<?php echo $baixo ? 'cv-est-saldo-baixo' : 'cv-est-saldo-ok'; ?>"><?php echo (int) $p->saldo; ?><?php echo $baixo ? ' ⚠️' : ''; ?></td>
                <td style="text-align:center"><?php echo esc_html( number_format( $p->por_dia, 1, ',', '.' ) ); ?></td>
                <td style="text-align:center"><?php
                if ( $p->saldo < 1 )        { echo '<span class="cv-est-saldo-baixo">esgotado</span>'; }
                elseif ( null === $p->dias ) { echo '<span style="color:#8A6A55">sem saídas</span>'; }
                else                         { echo '~' . (int) $p->dias . ' dias'; }
                ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="cv-section">
        <h2 class="cv-section-title">👕 Tamanhos mais pedidos</h2>
        <?php if ( empty( $tamanhos ) ) : ?>
        <p style="color:#8A6A55">Ainda não há pedidos de camiseta neste período.</p>
        <?php else : ?>
        <div style="height:220px"><canvas id="cv-est-graf-tamanhos"></canvas></div>
        <?php endif; ?>
    </div>
</div>

<div class="cv-section">
    <h2 class="cv-section-title">✉️ Alerta de estoque baixo</h2>
    <p style="font-size:13px;color:#6B4C3B;margin-top:0">Quando um item (ou um tamanho) chega ao estoque mínimo, um e-mail vai para este endereço — uma vez só, até o estoque voltar a subir. Os avisos de novo pedido também vão para ele.</p>
    <div style="display:flex;gap:8px;max-width:520px">
        <input type="email" id="cv-est-email" class="cv-input" value="<?php echo esc_attr( CV_Estoque::email_alerta() ); ?>" style="flex:1" />
        <button id="cv-est-email-salvar" class="cv-btn cv-btn-primary">Salvar</button>
    </div>
</div>

<script type="application/json" id="cv-est-dados"><?php echo wp_json_encode( array(
    'retirados' => array(
        'nomes' => wp_list_pluck( $retirados, 'nome' ),
        'qtd'   => array_map( 'intval', wp_list_pluck( $retirados, 'retiradas' ) ),
    ),
    'meses'    => $por_mes,
    'tamanhos' => array(
        'nomes' => wp_list_pluck( $tamanhos, 'tamanho' ),
        'qtd'   => array_map( 'intval', wp_list_pluck( $tamanhos, 'unidades' ) ),
    ),
) ); ?></script>
