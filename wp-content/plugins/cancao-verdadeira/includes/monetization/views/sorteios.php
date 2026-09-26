<?php
// cancao-verdadeira/includes/monetization/views/sorteios.php
// Tela "Sorteios" do painel: agendar sorteios entre os assinantes e anunciar por e-mail.
// Incluído por CV_Monetization_Pages::page_sorteios() (v2.36.0: saiu de dentro do método).
// O JavaScript da tela fica em assets/js/admin-sorteios.js.
// v2.60.0: campo "📦 Tirar do estoque" (CV_Estoque_Ligacao) e a peça ligada na lista.

if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_media();
global $wpdb;
$sorteios = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_sorteios ORDER BY data_sorteio DESC LIMIT 50" );
$total_subs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_subscribers" );
?>
<div id="cv-admin-page" class="cv-admin-wrap">
    <div class="cv-admin-header">
        <h1>🎰 Sorteios</h1>
        <p class="cv-admin-subtitle">
            <?php echo count( $sorteios ); ?> sorteio(s) •
            <strong style="color:#7B3A22"><?php echo $total_subs; ?></strong> assinantes elegíveis
        </p>
        <button id="cv-sort-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">+ Novo Sorteio</button>
    </div>
    <div id="cv-sort-msg" class="cv-action-message" style="display:none"></div>

    <?php // v2.41.0: sem sorteios, o formulário já vem aberto ?>
    <div id="cv-sort-form" class="cv-section" style="<?php echo empty( $sorteios ) ? '' : 'display:none'; ?>">
        <h2 class="cv-section-title">Agendar Sorteio</h2>
        <input type="hidden" id="cv-sort-id" value="0" />
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:760px">
            <div class="cv-form-group">
                <label class="cv-form-label">Título do sorteio *</label>
                <input type="text" id="cv-s-titulo" class="cv-input" placeholder="Ex: Sorteio Canção Verdadeira — Junho" />
            </div>
            <div class="cv-form-group">
                <label class="cv-form-label">Data e hora do sorteio *</label>
                <input type="datetime-local" id="cv-s-data" class="cv-input" />
            </div>
            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Prêmio *</label>
                <input type="text" id="cv-s-premio" class="cv-input" placeholder="Ex: Kit Canção Verdadeira (camiseta + caneca + e-book)" />
            </div>
            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Descrição / Regulamento</label>
                <textarea id="cv-s-desc" class="cv-input" rows="3" placeholder="Detalhes do sorteio, regras de participação..."></textarea>
            </div>
            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">Imagem do prêmio (opcional)</label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="cv-s-imagem" class="cv-input" placeholder="URL da imagem" style="flex:1" />
                    <button type="button" class="cv-btn cv-btn-outline cv-media-pick-sort" data-target="cv-s-imagem">📁 Biblioteca</button>
                </div>
            </div>
            <?php echo class_exists( 'CV_Estoque_Ligacao' ) ? CV_Estoque_Ligacao::campo( 'cv-s-estoque' ) : ''; // v2.60.0 ?>
        </div>
        <p style="font-size:13px;color:#8A6A55;margin-top:12px">
            ℹ O sorteio será realizado automaticamente na data/hora definida. O vencedor será escolhido aleatoriamente entre os <strong style="color:#7B3A22"><?php echo $total_subs; ?></strong> assinantes e receberá um e-mail automático.
        </p>
        <div style="display:flex;gap:10px;margin-top:16px">
            <button id="cv-sort-salvar" class="cv-btn cv-btn-primary">🎰 Agendar Sorteio</button>
            <button id="cv-sort-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
        </div>
    </div>

    <div class="cv-section">
        <?php
        $status_labels = array(
            'agendado'          => array( '⏳ Agendado',   '#B8700C' ),
            'realizado'         => array( '✅ Realizado',  '#27ae60' ),
            'sem_participantes' => array( '⚠ Sem participantes', '#e74c3c' ),
        );
        ?>
        <table class="cv-table">
            <thead>
                <tr><th>Título</th><th>Prêmio</th><th>Data</th><th style="text-align:center">Status</th><th>Vencedor</th><th style="text-align:center">Participantes</th></tr>
            </thead>
            <tbody>
            <?php if ( empty( $sorteios ) ) : ?>
            <tr><td colspan="6" style="text-align:center;padding:24px;color:#6B4C3B">Nenhum sorteio cadastrado ainda. Preencha o formulário acima para criar o primeiro.</td></tr>
            <?php endif; ?>
            <?php foreach ( $sorteios as $s ) :
                $sl = $status_labels[ $s->status ] ?? array( $s->status, '#C9A27E' );
            ?>
            <tr>
                <td><strong style="color:var(--cv-text)"><?php echo esc_html( $s->titulo ); ?></strong></td>
                <td style="color:#6B4C3B;font-size:13px"><?php echo esc_html( $s->premio ); ?>
                    <?php $cv_peca = class_exists( 'CV_Estoque_Ligacao' ) ? CV_Estoque_Ligacao::rotulo( 'sorteio', $s->id ) : ''; if ( $cv_peca ) : // v2.60.0 ?><br><span style="font-size:12px">📦 tira do estoque: <?php echo esc_html( $cv_peca ); ?></span><?php endif; ?></td>
                <td style="font-size:12px;color:#8A6A55"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $s->data_sorteio ) ) ); ?></td>
                <td style="text-align:center">
                    <span style="color:<?php echo esc_attr( $sl[1] ); ?>;font-size:13px;font-weight:700"><?php echo esc_html( $sl[0] ); ?></span>
                </td>
                <td style="font-size:13px">
                    <?php if ( $s->vencedor_email ) : ?>
                        <strong style="color:#7B3A22"><?php echo esc_html( $s->vencedor_nome ); ?></strong><br>
                        <small style="color:#8A6A55"><?php echo esc_html( $s->vencedor_email ); ?></small>
                    <?php else : ?>
                        <span style="color:#8A6A55">—</span>
                    <?php endif; ?>
                </td>
                <td style="text-align:center;color:#8A6A55;font-size:13px"><?php echo $s->total_participantes ? number_format( $s->total_participantes ) : '—'; ?></td>
                <td style="text-align:right">
                    <?php if ( 'agendado' === $s->status ) : ?>
                    <button class="cv-btn cv-btn-sm cv-sort-anunciar-btn"
                            data-id="<?php echo esc_attr($s->id); ?>"
                            style="background:#F2A51A;color:#3B2418;font-size:11px;padding:5px 12px;border:none;border-radius:4px;cursor:pointer;font-weight:700">
                        📧 Anunciar
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
