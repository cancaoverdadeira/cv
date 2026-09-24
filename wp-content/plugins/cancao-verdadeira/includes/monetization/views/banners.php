<?php
// cancao-verdadeira/includes/monetization/views/banners.php
// Tela "Banners de Parceiros" do painel: cadastro de banners (Media Library), editar/excluir e a configuração
// do bloco "depois da letra" (opção cv_publicidade).
// Incluído por CV_Monetization_Pages::page_banners() (v2.36.0: saiu de dentro do método).
// O JavaScript da tela fica em assets/js/admin-banners.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

wp_enqueue_media();
global $wpdb;
$banners = $wpdb->get_results(
    "SELECT * FROM {$wpdb->prefix}cv_banners ORDER BY id DESC LIMIT 100"
);
$posicoes = CV_Monetization::posicoes(); // v2.28.0: sem posição no topo

// Configuração do bloco "depois da letra" (parágrafo + aviso + banner)
if ( isset( $_POST['cv_pub_salvar'] ) && check_admin_referer( 'cv_pub_config', 'cv_pub_nonce' ) && current_user_can( 'manage_options' ) ) {
    update_option( 'cv_publicidade', array(
        'apos_letra_ativo' => isset( $_POST['cv_pub_ativo'] ) ? 1 : 0,
        'aviso'            => sanitize_text_field( wp_unslash( $_POST['cv_pub_aviso'] ?? '' ) ) ?: 'Leia após a publicidade',
        'paragrafo'        => sanitize_textarea_field( wp_unslash( $_POST['cv_pub_paragrafo'] ?? '' ) ),
    ) );
    echo '<div class="notice notice-success is-dismissible"><p>Configuração da publicidade salva.</p></div>';
}
$pub = CV_Monetization::config();
?>
<div id="cv-admin-page" class="cv-admin-wrap">
    <div class="cv-admin-header">
        <h1>📣 Banners de Parceiros</h1>
        <p class="cv-admin-subtitle"><?php echo count( $banners ); ?> banner(s) cadastrado(s)</p>
        <button id="cv-banner-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">
            + Novo Banner
        </button>
    </div>

    <div id="cv-banner-msg" class="cv-action-message" style="display:none"></div>

    <!-- Regras e bloco "depois da letra" -->
    <form method="post" class="cv-section" style="max-width:900px">
        <?php wp_nonce_field( 'cv_pub_config', 'cv_pub_nonce' ); ?>
        <h2 class="cv-section-title">🎵 Publicidade na página da música</h2>
        <p style="font-size:13px;color:#8A6A55;margin:0 0 12px">
            Regras do site: <strong>nunca no topo</strong> e <strong>sem banner rotativo</strong> — cada posição mostra
            um único banner (o ativo mais recente). Na página da música a ordem é:
            <em>letra → parágrafo curto → aviso → banner → resto da página</em>.
            Sem banner ativo na posição "depois da letra", nada aparece.
        </p>
        <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
            <input type="checkbox" name="cv_pub_ativo" value="1" <?php checked( ! empty( $pub['apos_letra_ativo'] ) ); ?> />
            Mostrar publicidade depois da letra
        </label>
        <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px">
            <div class="cv-form-group">
                <label class="cv-form-label">Aviso antes do banner</label>
                <input type="text" name="cv_pub_aviso" class="cv-input" value="<?php echo esc_attr( $pub['aviso'] ); ?>" />
            </div>
            <div class="cv-form-group">
                <label class="cv-form-label">Parágrafo curto (usado quando a música não tem Descrição)</label>
                <textarea name="cv_pub_paragrafo" class="cv-input" rows="2"><?php echo esc_textarea( $pub['paragrafo'] ); ?></textarea>
            </div>
        </div>
        <button type="submit" name="cv_pub_salvar" value="1" class="cv-btn cv-btn-primary">💾 Salvar configuração</button>
    </form>

    <!-- Formulário -->
    <div id="cv-banner-form" class="cv-section" style="display:none">
        <h2 class="cv-section-title">Cadastrar / Editar Banner</h2>
        <input type="hidden" id="cv-banner-id" value="0" />
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

            <div class="cv-form-group">
                <label class="cv-form-label">Título do banner *</label>
                <input type="text" id="cv-b-titulo" class="cv-input" placeholder="Ex: Parceiro Sertanejo FM" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Posição *</label>
                <select id="cv-b-posicao" class="cv-input">
                    <?php foreach ( $posicoes as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">URL da imagem *</label>
                <div style="display:flex;gap:8px">
                    <input type="text" id="cv-b-imagem" class="cv-input" placeholder="https://..." style="flex:1" />
                    <button type="button" class="cv-btn cv-btn-outline cv-media-pick" data-target="cv-b-imagem">📁 Biblioteca</button>
                </div>
                <img id="cv-b-imagem-preview" src="" alt="" style="display:none;margin-top:8px;max-height:80px;border-radius:6px" />
            </div>

            <div class="cv-form-group" style="grid-column:1/-1">
                <label class="cv-form-label">URL de destino (ao clicar) *</label>
                <input type="text" id="cv-b-url" class="cv-input" placeholder="https://parceiro.com.br" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Texto alternativo (acessibilidade)</label>
                <input type="text" id="cv-b-alt" class="cv-input" placeholder="Descrição da imagem para leitores de tela" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Ativo</label>
                <select id="cv-b-ativo" class="cv-input">
                    <option value="1">✅ Sim — exibir no site</option>
                    <option value="0">❌ Não — ocultar</option>
                </select>
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Data início (opcional)</label>
                <input type="date" id="cv-b-inicio" class="cv-input" />
            </div>

            <div class="cv-form-group">
                <label class="cv-form-label">Data fim (opcional)</label>
                <input type="date" id="cv-b-fim" class="cv-input" />
            </div>
        </div>

        <div style="display:flex;gap:10px;margin-top:16px">
            <button id="cv-banner-salvar" class="cv-btn cv-btn-primary">💾 Salvar Banner</button>
            <button id="cv-banner-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
        </div>

        <div class="cv-section" style="margin-top:20px;padding:14px;background:#FFFFFF;border-radius:8px;font-size:13px;color:#8A6A55">
            <strong style="color:#7B3A22">Como usar os banners no site:</strong><br>
            <strong>Depois da letra</strong> — automático na página de cada música (não precisa de código)<br>
            <code style="color:#6B4C3B">[cv_banner posicao="meio_pagina" leia_mais="sim"]</code> — Meio de página interna com botão fechar<br>
            <code style="color:#6B4C3B">[cv_banner posicao="rodape_pagina"]</code> — Rodapé de página interna
        </div>
    </div>

    <!-- Listagem -->
    <div class="cv-section">
        <?php if ( empty( $banners ) ) : ?>
        <p class="cv-empty">Nenhum banner cadastrado ainda. Clique em "+ Novo Banner" para começar.</p>
        <?php else : ?>
        <table class="cv-table">
            <thead>
                <tr>
                    <th style="width:80px">Imagem</th>
                    <th>Título</th>
                    <th>Posição</th>
                    <th style="text-align:center">Cliques</th>
                    <th style="text-align:center">Status</th>
                    <th>Validade</th>
                    <th style="text-align:center">Ações</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ( $banners as $b ) : ?>
            <tr id="cv-banner-row-<?php echo esc_attr( $b->id ); ?>">
                <td>
                    <img src="<?php echo esc_url( $b->imagem_url ); ?>"
                         style="width:72px;height:40px;object-fit:cover;border-radius:4px;background:#F8F0E4"
                         alt="<?php echo esc_attr( $b->titulo ); ?>" />
                </td>
                <td>
                    <strong style="color:var(--cv-text)"><?php echo esc_html( $b->titulo ); ?></strong><br>
                    <small style="color:#8A6A55;font-size:11px"><?php echo esc_url( $b->url_destino ); ?></small>
                </td>
                <td style="font-size:12px;color:#8A6A55"><?php echo esc_html( $posicoes[ $b->posicao ] ?? $b->posicao ); ?></td>
                <td style="text-align:center;font-weight:700;color:#7B3A22"><?php echo number_format( $b->cliques ); ?></td>
                <td style="text-align:center">
                    <?php if ( $b->ativo ) : ?>
                        <span style="color:#1C7C44;font-size:18px" title="Ativo">●</span>
                    <?php else : ?>
                        <span style="color:#8A6A55;font-size:18px" title="Inativo">○</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:12px;color:#8A6A55">
                    <?php
                    if ( $b->data_inicio || $b->data_fim ) {
                        echo esc_html( $b->data_inicio ? date( 'd/m/Y', strtotime( $b->data_inicio ) ) : '—' );
                        echo ' → ';
                        echo esc_html( $b->data_fim ? date( 'd/m/Y', strtotime( $b->data_fim ) ) : '∞' );
                    } else {
                        echo 'Sem prazo';
                    }
                    ?>
                </td>
                <td style="text-align:center">
                    <div style="display:flex;gap:6px;justify-content:center">
                        <button class="cv-btn cv-btn-outline cv-banner-editar"
                                data-banner='<?php echo esc_attr( json_encode( $b ) ); ?>'
                                style="padding:4px 10px;font-size:11px">✏ Editar</button>
                        <button class="cv-btn cv-banner-excluir"
                                data-id="<?php echo esc_attr( $b->id ); ?>"
                                style="padding:4px 10px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3)">🗑</button>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>
