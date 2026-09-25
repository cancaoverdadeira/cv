<?php
// cancao-verdadeira/includes/apoio/views/documentos.php
// Aba "📄 Propostas e contrato" da tela PIX e Parcerias (v2.49.0): taxa de
// divulgação, o que ela inclui, prazo, quem assina, foro e os dois textos
// ("Nossas propostas" e o contrato-modelo), com os {marcadores} que o site
// troca pelos dados do parceiro. Botões para ver um exemplo em Word e para
// voltar ao texto-modelo. Incluído por CV_Apoio::render().
// JS: assets/js/admin-apoio.js. Regras: CV_Parceria_Docs.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$c = CV_Parceria_Docs::config();
$exemplo = function ( $doc ) {
    return add_query_arg( array( 'action' => 'cv_parceria_doc', 'doc' => $doc, 'id' => 0 ), admin_url( 'admin-ajax.php' ) );
};
?>
<div class="cv-section" style="background:#FFF3D6;border:1px solid #F2A51A">
    <strong>⚖️ Importante:</strong> o contrato-modelo foi escrito para a Canção Verdadeira com base na Lei de Direitos Autorais (Lei nº 9.610/1998) e na LGPD, deixando claro que o objeto é <strong>só a divulgação</strong>, mediante taxa, e que <strong>a música continua do artista</strong>. Não substitui a orientação de um advogado: vale pedir uma revisão antes de usar com o primeiro parceiro.
</div>

<div class="cv-section">
    <h2 class="cv-section-title">Condições da divulgação</h2>
    <div class="cv-est-grid-2">
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-doc-taxa">Taxa de divulgação (R$)</label>
            <input type="text" id="cv-doc-taxa" class="cv-input" value="<?php echo esc_attr( $c['taxa'] ); ?>" placeholder="ex.: 150,00" style="max-width:180px" />
            <?php if ( '' === $c['taxa'] ) : ?><p style="font-size:12px;color:#C0392B;margin:4px 0 0">Sem valor: o contrato mostra "______ (a definir)".</p><?php endif; ?>
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-doc-prazo">Prazo da divulgação (dias)</label>
            <input type="number" id="cv-doc-prazo" class="cv-input" min="1" value="<?php echo (int) $c['prazo']; ?>" style="max-width:120px" />
        </div>
        <div class="cv-form-group" style="grid-column:1/-1">
            <label class="cv-form-label" for="cv-doc-inclui">O que a divulgação inclui (entra no contrato e nas propostas)</label>
            <textarea id="cv-doc-inclui" class="cv-input" rows="2"><?php echo esc_textarea( $c['taxa_inclui'] ); ?></textarea>
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-doc-contratada">Quem assina pela Canção Verdadeira</label>
            <input type="text" id="cv-doc-contratada" class="cv-input" value="<?php echo esc_attr( $c['contratada'] ); ?>" />
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-doc-foro">Foro (cidade/UF)</label>
            <input type="text" id="cv-doc-foro" class="cv-input" value="<?php echo esc_attr( $c['foro'] ); ?>" />
        </div>
    </div>
</div>

<div class="cv-est-grid-2">
    <div class="cv-section">
        <h2 class="cv-section-title">📄 Nossas propostas</h2>
        <textarea id="cv-doc-propostas" class="cv-input" rows="22" style="font-family:monospace;font-size:13px"><?php echo esc_textarea( $c['propostas'] ); ?></textarea>
        <p style="margin:8px 0 0"><a class="cv-btn cv-btn-outline cv-est-mini" href="<?php echo esc_url( $exemplo( 'propostas' ) ); ?>">⬇️ Ver exemplo em Word (salve antes)</a></p>
    </div>
    <div class="cv-section">
        <h2 class="cv-section-title">📝 Modelo de contrato</h2>
        <textarea id="cv-doc-contrato" class="cv-input" rows="22" style="font-family:monospace;font-size:13px"><?php echo esc_textarea( $c['contrato'] ); ?></textarea>
        <p style="margin:8px 0 0"><a class="cv-btn cv-btn-outline cv-est-mini" href="<?php echo esc_url( $exemplo( 'contrato' ) ); ?>">⬇️ Ver exemplo em Word (salve antes)</a></p>
    </div>
</div>

<div class="cv-section">
    <div style="display:flex;gap:10px;flex-wrap:wrap">
        <button id="cv-doc-salvar" class="cv-btn cv-btn-primary">💾 Salvar propostas e contrato</button>
        <button id="cv-doc-restaurar" class="cv-btn cv-btn-outline">↩ Voltar aos textos-modelo</button>
    </div>
    <h3 style="margin:20px 0 8px;color:#7B3A22;font-size:15px">Como escrever os textos</h3>
    <ul style="margin:0 0 12px 18px;font-size:13px;color:#6B4C3B;list-style:disc">
        <li><code># Título</code> → título centralizado · <code>## Subtítulo</code> → subtítulo (ex.: cada cláusula)</li>
        <li><code>- item</code> → item de lista · <code>&gt; texto</code> → nota pequena (rodapé)</li>
        <li>Cada linha vira um parágrafo. Linhas em branco são ignoradas.</li>
    </ul>
    <table class="cv-table" style="max-width:720px">
        <thead><tr><th>Marcador</th><th>Vira</th></tr></thead>
        <tbody>
        <?php foreach ( CV_Parceria_Docs::marcadores() as $m => $d ) : ?>
        <tr><td><code><?php echo esc_html( $m ); ?></code></td><td style="font-size:13px"><?php echo esc_html( $d ); ?></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
