<?php
// cancao-verdadeira/includes/estoque/views/itens.php
// Aba "🏷️ Itens" da tela Estoque e Pedidos: cadastro de E-book, Caneca e
// Camiseta (nome, preço, imagem, estoque mínimo do alerta, ativo) e lista
// com o saldo de cada tamanho. Na criação dá para lançar o estoque inicial.
// Incluído por CV_Estoque_Admin::render(). JS: assets/js/admin-estoque.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$itens = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_estoque_itens ORDER BY ordem ASC, id ASC" );
$saldos = array();
foreach ( CV_Estoque::variacoes() as $v ) { $saldos[ $v->item_id ][] = $v; }
$tipos = CV_Estoque::tipos();
?>
<div style="display:flex;justify-content:flex-end;margin-bottom:12px">
    <button id="cv-est-novo" class="cv-btn cv-btn-primary">+ Novo item</button>
</div>

<div id="cv-est-form" class="cv-section" style="<?php echo empty( $itens ) ? '' : 'display:none'; ?>">
    <h2 class="cv-section-title" id="cv-est-form-titulo">Novo item</h2>
    <input type="hidden" id="cv-ei-id" value="0" />
    <div class="cv-est-grid-2" style="max-width:900px">
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-ei-nome">Nome *</label>
            <input type="text" id="cv-ei-nome" class="cv-input" placeholder="Ex.: Caneca Canção Verdadeira" />
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-ei-tipo">Tipo *</label>
            <select id="cv-ei-tipo" class="cv-input">
                <?php foreach ( $tipos as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-ei-preco">Preço (R$)</label>
            <input type="text" id="cv-ei-preco" class="cv-input" placeholder="39,90" />
        </div>
        <div class="cv-form-group cv-ei-so-fisico">
            <label class="cv-form-label" for="cv-ei-minimo">Estoque mínimo (alerta por e-mail)</label>
            <input type="number" id="cv-ei-minimo" class="cv-input" value="10" min="0" style="width:120px" />
        </div>
        <div class="cv-form-group" style="grid-column:1/-1">
            <label class="cv-form-label" for="cv-ei-desc">Descrição</label>
            <textarea id="cv-ei-desc" class="cv-input" rows="2" placeholder="Aparece para o cliente na Minha Área"></textarea>
        </div>
        <div class="cv-form-group" style="grid-column:1/-1">
            <label class="cv-form-label" for="cv-ei-imagem">Imagem</label>
            <div style="display:flex;gap:8px">
                <input type="text" id="cv-ei-imagem" class="cv-input" placeholder="URL ou use a biblioteca" style="flex:1" />
                <button type="button" class="cv-btn cv-btn-outline" id="cv-ei-biblioteca">📁 Biblioteca</button>
            </div>
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-ei-ordem">Ordem na lista</label>
            <input type="number" id="cv-ei-ordem" class="cv-input" value="0" min="0" style="width:120px" />
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-ei-ativo">Ativo (aparece na Minha Área)</label>
            <select id="cv-ei-ativo" class="cv-input">
                <option value="1">✅ Sim</option>
                <option value="0">❌ Não</option>
            </select>
        </div>
    </div>

    <div id="cv-ei-iniciais" class="cv-ei-so-fisico" style="margin-top:6px">
        <div class="cv-form-label" style="margin-bottom:8px">Estoque inicial (peças que você já tem)</div>
        <div id="cv-ei-ini-unico" style="display:flex;gap:10px;align-items:center">
            <input type="number" name="ini_unico" class="cv-input cv-ei-ini" min="0" value="0" style="width:120px" /> peças
        </div>
        <div id="cv-ei-ini-tamanhos" style="display:none;gap:14px;flex-wrap:wrap">
            <?php foreach ( CV_Estoque::TAMANHOS as $t ) : ?>
            <label style="display:flex;gap:6px;align-items:center;font-weight:600">
                <?php echo esc_html( $t ); ?>
                <input type="number" name="ini_<?php echo esc_attr( $t ); ?>" class="cv-input cv-ei-ini" min="0" value="0" style="width:90px" />
            </label>
            <?php endforeach; ?>
        </div>
        <p style="font-size:12px;color:#8A6A55;margin:6px 0 0">Depois de criado, o estoque muda só pela aba "🔁 Estoque" (tudo fica registrado).</p>
    </div>
    <p class="cv-ei-so-ebook" style="display:none;font-size:13px;color:#8A6A55">📖 E-book é digital: não tem estoque nem alerta. Os pedidos entram normalmente.</p>

    <div style="display:flex;gap:10px;margin-top:16px">
        <button id="cv-ei-salvar" class="cv-btn cv-btn-primary">💾 Salvar item</button>
        <button id="cv-ei-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
    </div>
</div>

<div class="cv-section">
    <h2 class="cv-section-title">Itens cadastrados</h2>
    <table class="cv-table">
        <thead><tr><th style="width:64px">Img</th><th>Nome</th><th>Tipo</th><th>Preço</th><th>Estoque</th><th style="text-align:center">Mínimo</th><th style="text-align:center">Ativo</th><th style="text-align:center">Ações</th></tr></thead>
        <tbody>
        <?php if ( empty( $itens ) ) : ?>
        <tr><td colspan="8" style="text-align:center;padding:24px;color:#6B4C3B">Nenhum item ainda. Preencha o formulário acima (sugestão: comece pela Caneca, pela Camiseta e pelo E-book).</td></tr>
        <?php endif; ?>
        <?php foreach ( $itens as $it ) : ?>
        <tr>
            <td>
                <?php if ( $it->imagem_url ) : ?>
                <img src="<?php echo esc_url( $it->imagem_url ); ?>" alt="" style="width:52px;height:52px;object-fit:cover;border-radius:6px" />
                <?php else : ?>
                <div style="width:52px;height:52px;border-radius:6px;background:#F8F0E4;display:flex;align-items:center;justify-content:center;font-size:22px"><?php echo esc_html( mb_substr( $tipos[ $it->tipo ] ?? '📦', 0, 1 ) ); ?></div>
                <?php endif; ?>
            </td>
            <td><strong><?php echo esc_html( $it->nome ); ?></strong></td>
            <td><?php echo esc_html( $tipos[ $it->tipo ] ?? $it->tipo ); ?></td>
            <td>R$ <?php echo esc_html( number_format( (float) $it->preco, 2, ',', '.' ) ); ?></td>
            <td>
                <?php if ( ! $it->controla_estoque ) : ?>
                <span style="color:#8A6A55">— digital</span>
                <?php else : foreach ( $saldos[ $it->id ] ?? array() as $v ) :
                    $baixo = (int) $v->estoque_atual <= (int) $it->estoque_minimo; ?>
                    <span style="margin-right:10px;white-space:nowrap">
                        <?php echo '' !== $v->variacao ? esc_html( $v->variacao ) . ': ' : ''; ?>
                        <span class="<?php echo $baixo ? 'cv-est-saldo-baixo' : 'cv-est-saldo-ok'; ?>"><?php echo (int) $v->estoque_atual; ?></span>
                    </span>
                <?php endforeach; endif; ?>
            </td>
            <td style="text-align:center"><?php echo $it->controla_estoque ? (int) $it->estoque_minimo : '—'; ?></td>
            <td style="text-align:center"><?php echo $it->ativo ? '<span style="color:#1C7C44">●</span>' : '<span style="color:#8A6A55">○</span>'; ?></td>
            <td style="text-align:center;white-space:nowrap">
                <button class="cv-btn cv-btn-outline cv-est-mini cv-ei-editar" data-item='<?php echo esc_attr( wp_json_encode( $it ) ); ?>' title="Editar">✏</button>
                <button class="cv-btn cv-est-mini cv-ei-excluir" data-id="<?php echo esc_attr( $it->id ); ?>" data-nome="<?php echo esc_attr( $it->nome ); ?>" title="Excluir"
                        style="background:rgba(192,57,43,.12);color:#C0392B;border:1px solid rgba(192,57,43,.3)">🗑</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
