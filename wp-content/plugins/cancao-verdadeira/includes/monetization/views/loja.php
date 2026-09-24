<?php
// cancao-verdadeira/includes/monetization/views/loja.php
// Tela "Loja" do painel: cadastro de produtos afiliados (nome, preço, imagem, link), editar e excluir.
// Incluído por CV_Monetization_Pages::page_loja() (v2.36.0: saiu de dentro do método).
// O JavaScript da tela fica em assets/js/admin-loja.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_media();
global $wpdb;
$produtos  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_produtos ORDER BY ordem ASC, id DESC" );
$categorias = array( 'ebook' => '📖 E-book', 'fisico' => '🎁 Físico (caneca, camiseta, pendrive)', 'digital' => '💿 Digital (download)' );
?>
<div id="cv-admin-page" class="cv-admin-wrap">
    <div class="cv-admin-header">
        <h1>🛒 Loja</h1>
        <p class="cv-admin-subtitle"><?php echo count( $produtos ); ?> produto(s) cadastrado(s)</p>
        <button id="cv-prod-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">+ Novo Produto</button>
    </div>
    <div id="cv-prod-msg" class="cv-action-message" style="display:none"></div>

    <div id="cv-prod-form" class="cv-section" style="display:none">
        <h2 class="cv-section-title">Cadastrar / Editar Produto</h2>
        <input type="hidden" id="cv-prod-id" value="0" />
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

            <div class="cv-form-group">
                <label class="cv-form-label">Nome do produto *</label>
                <input type="text" id="cv-p-nome" class="cv-input" placeholder="Ex: E-book 100 Letras Sertanejas" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Categoria *</label>
                <select id="cv-p-categoria" class="cv-input">
                    <?php foreach ( $categorias as $v => $l ) : ?>
                    <option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Descrição</label>
                <textarea id="cv-p-desc" class="cv-input" rows="2" placeholder="Breve descrição do produto (até 200 caracteres)"></textarea>
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Preço (R$) *</label>
                <input type="text" id="cv-p-preco" class="cv-input" placeholder="29,90" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Preço antigo (riscado) — opcional</label>
                <input type="text" id="cv-p-preco-antigo" class="cv-input" placeholder="49,90" />
            </div>

            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">URL de compra / checkout *</label>
                <input type="text" id="cv-p-url" class="cv-input" placeholder="https://loja.exemplo.com/produto" />
            </div>

            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Imagem do produto</label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="cv-p-imagem" class="cv-input" placeholder="URL ou use a biblioteca" style="flex:1" />
                    <button type="button" class="cv-btn cv-btn-outline cv-media-pick-prod" data-target="cv-p-imagem">📁 Biblioteca</button>
                </div>
                <img id="cv-p-imagem-preview" src="" alt="" style="display:none;margin-top:8px;max-height:80px;border-radius:6px" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Texto do botão</label>
                <input type="text" id="cv-p-botao" class="cv-input" placeholder="Comprar agora" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Badge (opcional)</label>
                <input type="text" id="cv-p-badge" class="cv-input" placeholder="Ex: Mais vendido | Novo | Promoção" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Ordem de exibição</label>
                <input type="number" id="cv-p-ordem" class="cv-input" value="0" min="0" style="width:100px" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Ativo</label>
                <select id="cv-p-ativo" class="cv-input">
                    <option value="1">✅ Sim</option>
                    <option value="0">❌ Não</option>
                </select>
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button id="cv-prod-salvar" class="cv-btn cv-btn-primary">💾 Salvar Produto</button>
            <button id="cv-prod-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
        </div>

        <div class="cv-section" style="margin-top:20px;padding:14px;background:#FFFFFF;border-radius:8px;font-size:13px;color:#8A6A55">
            <strong style="color:#7B3A22">Shortcode da loja:</strong><br>
            <code style="color:#6B4C3B">[cv_loja]</code> — exibe todos os produtos<br>
            <code style="color:#6B4C3B">[cv_loja categoria="ebook" titulo="Nossos E-books"]</code><br>
            <code style="color:#6B4C3B">[cv_loja categoria="fisico" colunas="2"]</code>
        </div>
    </div>

    <div class="cv-section">
        <?php if ( empty( $produtos ) ) : ?>
        <p class="cv-empty" style="font-size:13px;color:#6B4C3B;padding:12px 0">Nenhum produto cadastrado ainda. Use o formulário acima para adicionar o primeiro.</p>
        <?php else : ?>
        <table class="cv-table">
            <thead><tr><th style="width:70px">Img</th><th>Nome</th><th>Categoria</th><th>Preço</th><th style="text-align:center">Ativo</th><th style="text-align:center">Ações</th></tr></thead>
            <tbody>
            <?php foreach ( $produtos as $p ) : ?>
            <tr id="cv-prod-row-<?php echo esc_attr( $p->id ); ?>">
                <td>
                    <?php if ( $p->imagem_url ) : ?>
                    <img src="<?php echo esc_url( $p->imagem_url ); ?>" style="width:56px;height:56px;object-fit:cover;border-radius:4px" alt="" />
                    <?php else : ?>
                    <div style="width:56px;height:56px;background:#FFFFFF;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:22px">🎁</div>
                    <?php endif; ?>
                </td>
                <td>
                    <strong style="color:var(--cv-text)"><?php echo esc_html( $p->nome ); ?></strong>
                    <?php if ( $p->badge ) : ?>
                    <span style="background:rgba(242,165,26,0.2);color:#7B3A22;font-size:10px;padding:1px 7px;border-radius:20px;margin-left:6px"><?php echo esc_html( $p->badge ); ?></span>
                    <?php endif; ?>
                </td>
                <td style="color:#8A6A55;font-size:13px"><?php echo esc_html( $categorias[ $p->categoria ] ?? $p->categoria ); ?></td>
                <td style="color:#7B3A22;font-weight:700">R$ <?php echo number_format( (float) $p->preco, 2, ',', '.' ); ?></td>
                <td style="text-align:center"><?php echo $p->ativo ? '<span style="color:#1C7C44">●</span>' : '<span style="color:#8A6A55">○</span>'; ?></td>
                <td style="text-align:center">
                    <div style="display:flex;gap:6px;justify-content:center">
                        <button class="cv-btn cv-btn-outline cv-prod-editar" data-prod='<?php echo esc_attr( json_encode( $p ) ); ?>' style="padding:4px 10px;font-size:11px">✏</button>
                        <button class="cv-btn cv-prod-excluir" data-id="<?php echo esc_attr( $p->id ); ?>" style="padding:4px 10px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3)">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
