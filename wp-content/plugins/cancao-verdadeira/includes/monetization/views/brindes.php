<?php
// cancao-verdadeira/includes/monetization/views/brindes.php
// Tela "Brindes" do painel: cadastro de brindes e envio de brinde + e-mail a um assinante.
// Incluído por CV_Monetization_Pages::page_brindes() (v2.36.0: saiu de dentro do método).
// O JavaScript da tela fica em assets/js/admin-brindes.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_media();
global $wpdb;
$brindes   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_brindes ORDER BY id DESC" );
$entregas  = $wpdb->get_results( "SELECT e.*, b.titulo AS brinde_titulo FROM {$wpdb->prefix}cv_brindes_entregas e LEFT JOIN {$wpdb->prefix}cv_brindes b ON b.id = e.brinde_id ORDER BY e.enviado_em DESC LIMIT 50" );
$assinantes= $wpdb->get_results( "SELECT email, name FROM {$wpdb->prefix}cv_subscribers ORDER BY name ASC LIMIT 500" );
?>
<div id="cv-admin-page" class="cv-admin-wrap">
    <div class="cv-admin-header">
        <h1>🎁 Brindes</h1>
        <p class="cv-admin-subtitle"><?php echo count( $brindes ); ?> brinde(s) • <?php echo count( $entregas ); ?> entrega(s) realizada(s)</p>
        <button id="cv-brinde-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">+ Novo Brinde</button>
    </div>
    <div id="cv-brinde-msg" class="cv-action-message" style="display:none"></div>

    <!-- Cadastrar brinde -->
    <div id="cv-brinde-form" class="cv-section" style="display:none">
        <h2 class="cv-section-title">Cadastrar Brinde</h2>
        <input type="hidden" id="cv-br-id" value="0" />
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:760px">
            <div class="cv-form-group">
                <label class="cv-form-label">Nome do brinde *</label>
                <input type="text" id="cv-br-titulo" class="cv-input" placeholder="Ex: Caneca Canção Verdadeira" />
            </div>
            <div class="cv-form-group">
                <label class="cv-form-label">Quantidade disponível</label>
                <input type="number" id="cv-br-qtd" class="cv-input" value="1" min="1" style="width:120px" />
            </div>
            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Descrição</label>
                <textarea id="cv-br-desc" class="cv-input" rows="2" placeholder="Detalhes do brinde..."></textarea>
            </div>
            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Imagem</label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="cv-br-imagem" class="cv-input" placeholder="URL da imagem" style="flex:1" />
                    <button type="button" class="cv-btn cv-btn-outline cv-media-pick-br" data-target="cv-br-imagem">📁 Biblioteca</button>
                </div>
            </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button id="cv-brinde-salvar" class="cv-btn cv-btn-primary">💾 Salvar Brinde</button>
            <button id="cv-brinde-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
        </div>
    </div>

    <!-- Enviar brinde -->
    <div class="cv-section">
        <h2 class="cv-section-title">📤 Enviar Brinde para um Membro</h2>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;max-width:900px">
            <div class="cv-form-group">
                <label class="cv-form-label">Brinde *</label>
                <select id="cv-env-brinde" class="cv-input">
                    <option value="">— Selecione —</option>
                    <?php foreach ( $brindes as $b ) :
                        if ( $b->status === 'esgotado' ) { continue; }
                    ?>
                    <option value="<?php echo esc_attr( $b->id ); ?>">
                        <?php echo esc_html( $b->titulo . ' (' . $b->quantidade . ' disp.)' ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="cv-form-group">
                <label class="cv-form-label">E-mail do destinatário *</label>
                <input type="text" id="cv-env-email" class="cv-input" list="cv-subs-list" placeholder="e-mail do assinante" />
                <datalist id="cv-subs-list">
                    <?php foreach ( $assinantes as $s ) : ?>
                    <option value="<?php echo esc_attr( $s->email ); ?>"><?php echo esc_attr( $s->name ?: $s->email ); ?></option>
                    <?php endforeach; ?>
                </datalist>
            </div>
            <div class="cv-form-group">
                <label class="cv-form-label">Mensagem pessoal (opcional)</label>
                <input type="text" id="cv-env-msg" class="cv-input" placeholder="Parabéns pela participação!" />
            </div>
        </div>
        <button id="cv-env-brinde-btn" class="cv-btn cv-btn-primary">📤 Enviar Brinde + E-mail</button>
    </div>

    <!-- Brindes cadastrados -->
    <div class="cv-section">
        <h2 class="cv-section-title">Brindes cadastrados</h2>
        <?php if ( empty( $brindes ) ) : ?>
        <p class="cv-empty">Nenhum brinde cadastrado ainda.</p>
        <?php else : ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
            <?php foreach ( $brindes as $b ) : ?>
            <div style="background:#FFFFFF;border:1px solid <?php echo $b->status==='esgotado' ? '#F3E6D3' : '#F8F0E4'; ?>;border-radius:10px;padding:16px;opacity:<?php echo $b->status==='esgotado' ? '0.5' : '1'; ?>">
                <?php if ( $b->imagem_url ) : ?>
                <img src="<?php echo esc_url( $b->imagem_url ); ?>" style="width:100%;height:100px;object-fit:cover;border-radius:6px;margin-bottom:10px" alt="" />
                <?php endif; ?>
                <div style="font-weight:700;color:var(--cv-text);margin-bottom:4px"><?php echo esc_html( $b->titulo ); ?></div>
                <div style="font-size:12px;color:#8A6A55;margin-bottom:8px"><?php echo esc_html( $b->descricao ?: '—' ); ?></div>
                <div style="display:flex;justify-content:space-between;align-items:center">
                    <span style="font-size:13px;color:<?php echo $b->quantidade > 0 ? '#27ae60' : '#e74c3c'; ?>;font-weight:700">
                        <?php echo $b->status === 'esgotado' ? '⚠ Esgotado' : $b->quantidade . ' disponível(eis)'; ?>
                    </span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

    <!-- Histórico de entregas -->
    <?php if ( ! empty( $entregas ) ) : ?>
    <div class="cv-section">
        <h2 class="cv-section-title">Histórico de Entregas</h2>
        <table class="cv-table">
            <thead><tr><th>Data</th><th>Brinde</th><th>Destinatário</th><th>E-mail</th></tr></thead>
            <tbody>
            <?php foreach ( $entregas as $e ) : ?>
            <tr>
                <td style="font-size:12px;color:#8A6A55"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $e->enviado_em ) ) ); ?></td>
                <td style="color:#6B4C3B"><?php echo esc_html( $e->brinde_titulo ); ?></td>
                <td><?php echo esc_html( $e->nome ?: '—' ); ?></td>
                <td style="color:#8A6A55;font-size:13px"><?php echo esc_html( $e->email ); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
