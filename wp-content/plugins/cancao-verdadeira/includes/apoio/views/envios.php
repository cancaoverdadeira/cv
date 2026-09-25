<?php
// cancao-verdadeira/includes/apoio/views/envios.php
// Aba "🎤 Envios de música" da tela PIX e Parcerias (v2.50.0): um bloco por
// envio com as 4 etapas — contrato (assinatura conferida, baixar, validar no
// ITI, aprovar/recusar), PIX (comprovante, confirmar), YouTube e dados — e o
// botão "Gerar música em rascunho". Abaixo, o editor do prompt de ajuda.
// Incluído por CV_Apoio::render(). Regras: CV_Envio_Admin. JS: admin-apoio.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$envios = $wpdb->get_results(
    "SELECT e.*, u.display_name, u.user_email FROM {$wpdb->prefix}cv_envios e
       LEFT JOIN {$wpdb->users} u ON u.ID = e.user_id
      ORDER BY FIELD(e.status,'enviado','rascunho','aprovado','recusado'), e.id DESC LIMIT 100"
);
$st_envio = array( 'rascunho' => '✏️ Em andamento', 'enviado' => '📨 Enviado — revisar', 'aprovado' => '✅ Aprovado', 'recusado' => '✖ Recusado' );
$st_contr = array( 'pendente' => '⏳ a conferir', 'aprovado' => '✅ aprovado', 'recusado' => '✖ recusado' );
$st_pix   = array( 'aguardando' => '— aguardando', 'informado' => '⏳ informado', 'confirmado' => '✅ confirmado' );
?>
<div class="cv-section">
    <p style="margin-top:0;font-size:13px;color:#6B4C3B">
        O parceiro faz o envio na <strong>Minha Área → 🎤 Enviar música</strong>: 1) contrato em PDF com <strong>assinatura digital</strong> (obrigatória),
        2) PIX da taxa (identificação <code>CVDIV</code> + número), 3) link do YouTube, 4) título, descrição, letra e tags.
        Você aprova o contrato, confirma o PIX e gera a música em <strong>rascunho</strong> para revisar e publicar.
    </p>
    <p style="font-size:12px;margin:0 0 12px;color:<?php echo CV_Envio::pasta_fora_do_site() ? '#1C7C44' : '#B8700C'; ?>">
        <?php echo CV_Envio::pasta_fora_do_site()
            ? '🔒 Contratos e comprovantes guardados fora da pasta pública do site (ninguém acessa pela internet).'
            : '⚠️ A hospedagem não permitiu guardar fora da pasta pública: os arquivos estão em uploads/cv-privado, com bloqueio e nomes aleatórios.'; ?>
    </p>
    <?php if ( empty( $envios ) ) : ?>
    <p style="color:#8A6A55">Nenhum envio ainda.</p>
    <?php endif; ?>

    <?php foreach ( $envios as $e ) :
        $info  = $e->contrato_info ? json_decode( $e->contrato_info, true ) : array();
        $falta = CV_Envio_Admin::falta( $e );
        $vid   = $e->youtube_url ? CV_Fields::youtube_id( $e->youtube_url ) : '';
    ?>
    <div class="cv-envio-adm" style="border:1px solid #EADBC6;border-radius:10px;padding:14px 16px;margin-bottom:14px;background:#FFFFFF">
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;margin-bottom:10px">
            <strong style="font-size:16px">#<?php echo (int) $e->id; ?> · <?php echo esc_html( $e->titulo ? $e->titulo : '(sem título)' ); ?></strong>
            <span class="cv-est-status cv-est-status-<?php echo 'aprovado' === $e->status ? 'confirmado' : ( 'recusado' === $e->status ? 'cancelado' : 'pendente' ); ?>"><?php echo esc_html( $st_envio[ $e->status ] ?? $e->status ); ?></span>
            <span style="font-size:13px;color:#6B4C3B">👤 <?php echo esc_html( $e->display_name ? $e->display_name : '(usuário excluído)' ); ?> · <a href="mailto:<?php echo esc_attr( $e->user_email ); ?>"><?php echo esc_html( $e->user_email ); ?></a><?php echo $e->artista ? ' · 🎤 ' . esc_html( $e->artista ) : ''; ?></span>
            <span style="font-size:12px;color:#8A6A55;margin-left:auto"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $e->criado_em ) ); ?></span>
        </div>

        <table class="cv-table" style="margin:0">
            <tbody>
            <tr>
                <td style="width:130px"><strong>1. Contrato</strong></td>
                <td>
                    <?php if ( $e->contrato_arquivo ) : ?>
                    <?php echo ! empty( $info['assinado'] ) ? '✅ Assinatura digital' : '⚠️ Sem assinatura'; ?>
                    <?php if ( ! empty( $info['assinante'] ) ) : ?>de <strong><?php echo esc_html( $info['assinante'] ); ?></strong><?php endif; ?>
                    <?php if ( ! empty( $info['emissor'] ) ) : ?><br><span style="font-size:12px;color:#6B4C3B">Certificado: <?php echo esc_html( $info['emissor'] ); ?><?php echo ! empty( $info['govbr'] ) ? ' (gov.br)' : ( ! empty( $info['icp'] ) ? ' (ICP-Brasil)' : '' ); ?></span><?php endif; ?>
                    <br><span style="font-size:12px;color:#6B4C3B">Integridade: <?php echo true === $info['integridade'] ? '✅ não foi alterado depois de assinado' : ( false === $info['integridade'] ? '✖ alterado' : '⚠️ não conferida' ); ?></span>
                    <br><a href="<?php echo esc_url( CV_Envio_Admin::url_arquivo( $e, 'contrato' ) ); ?>">⬇️ Baixar PDF</a> · <a href="<?php echo esc_url( CV_Assinatura_PDF::VALIDADOR_ITI ); ?>" target="_blank" rel="noopener">✔ Validar no ITI (oficial)</a>
                    <?php else : ?>— ainda não enviado<?php endif; ?>
                </td>
                <td style="width:260px;text-align:right;white-space:nowrap">
                    <?php echo esc_html( $st_contr[ $e->contrato_status ] ?? '' ); ?>
                    <?php if ( $e->contrato_arquivo && 'aprovado' !== $e->contrato_status && 'recusado' !== $e->status ) : ?>
                    <br><button class="cv-btn cv-btn-primary cv-est-mini cv-envio-acao" data-id="<?php echo (int) $e->id; ?>" data-acao="aprovar_contrato">✅ Aprovar</button>
                    <button class="cv-btn cv-btn-outline cv-est-mini cv-envio-acao" data-id="<?php echo (int) $e->id; ?>" data-acao="recusar_contrato" data-motivo="1">✖ Recusar</button>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>2. PIX</strong></td>
                <td>R$ <?php echo esc_html( number_format( (float) $e->valor, 2, ',', '.' ) ); ?> · identificação <code><?php echo esc_html( CV_Envio::txid( $e->id ) ); ?></code>
                    <?php if ( $e->comprovante_arquivo ) : ?> · <a href="<?php echo esc_url( CV_Envio_Admin::url_arquivo( $e, 'comprovante' ) ); ?>">⬇️ Comprovante</a><?php endif; ?>
                </td>
                <td style="text-align:right;white-space:nowrap">
                    <?php echo esc_html( $st_pix[ $e->pagamento_status ] ?? '' ); ?>
                    <?php if ( 'confirmado' !== $e->pagamento_status && (int) $e->etapa >= 2 && 'recusado' !== $e->status ) : ?>
                    <br><button class="cv-btn cv-btn-primary cv-est-mini cv-envio-acao" data-id="<?php echo (int) $e->id; ?>" data-acao="confirmar_pix" data-confirma="Confirma que o PIX CVDIV<?php echo (int) $e->id; ?> caiu na conta?">✅ PIX caiu na conta</button>
                    <?php endif; ?>
                </td>
            </tr>
            <tr>
                <td><strong>3. YouTube</strong></td>
                <td colspan="2"><?php echo $vid ? '<a href="' . esc_url( $e->youtube_url ) . '" target="_blank" rel="noopener">' . esc_html( $e->youtube_titulo ? $e->youtube_titulo : $e->youtube_url ) . '</a>' : '—'; ?></td>
            </tr>
            <tr>
                <td><strong>4. Dados</strong></td>
                <td colspan="2" style="font-size:13px">
                    <?php if ( (int) $e->etapa >= 5 || $e->letra ) : ?>
                    <strong><?php echo esc_html( $e->titulo ); ?></strong> — <?php echo esc_html( $e->artista ); ?><?php echo $e->compositor ? ' · composição: ' . esc_html( $e->compositor ) : ''; ?>
                    <br><em><?php echo esc_html( $e->descricao ); ?></em>
                    <?php if ( $e->tags ) : ?><br>🏷 <?php echo esc_html( $e->tags ); ?><?php endif; ?>
                    <details style="margin-top:6px"><summary style="cursor:pointer">Ver a letra</summary><div style="white-space:pre-line;margin-top:6px;max-height:260px;overflow:auto"><?php echo esc_html( $e->letra ); ?></div></details>
                    <?php else : ?>—<?php endif; ?>
                </td>
            </tr>
            </tbody>
        </table>

        <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-top:10px">
            <?php if ( 'aprovado' === $e->status && $e->musica_id ) : ?>
            <a class="cv-btn cv-btn-primary cv-est-mini" href="<?php echo esc_url( get_edit_post_link( $e->musica_id ) ); ?>">✏️ Abrir a música (rascunho)</a>
            <?php elseif ( 'recusado' !== $e->status ) : ?>
            <button class="cv-btn cv-btn-primary cv-est-mini cv-envio-acao" data-id="<?php echo (int) $e->id; ?>" data-acao="criar_rascunho" <?php disabled( '' !== $falta ); ?> title="<?php echo esc_attr( $falta ? 'Falta ' . $falta : '' ); ?>">🎵 Gerar música em rascunho</button>
            <?php if ( $falta ) : ?><span style="font-size:12px;color:#8A6A55">Falta <?php echo esc_html( $falta ); ?>.</span><?php endif; ?>
            <button class="cv-btn cv-btn-outline cv-est-mini cv-envio-acao" data-id="<?php echo (int) $e->id; ?>" data-acao="recusar" data-motivo="1" style="margin-left:auto">✖ Recusar envio</button>
            <?php endif; ?>
            <?php if ( $e->obs_admin ) : ?><span style="font-size:12px;color:#C0392B;width:100%">Motivo informado: <?php echo esc_html( $e->obs_admin ); ?></span><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="cv-section">
    <h2 class="cv-section-title">💡 Prompt de ajuda (etapa 4)</h2>
    <p style="font-size:13px;color:#6B4C3B;margin-top:0">O parceiro baixa este texto em Word, já com o material da música, e cola numa inteligência artificial para receber título, descrição e tags para o YouTube.
    Marcadores: <code>{titulo}</code> <code>{artista}</code> <code>{compositor}</code> <code>{descricao}</code> <code>{tags}</code> <code>{youtube}</code> e, numa linha sozinha, <code>{letra}</code>.</p>
    <textarea id="cv-envio-prompt" class="cv-input" rows="18" style="font-family:monospace;font-size:13px"><?php echo esc_textarea( CV_Envio_Prompt::texto() ); ?></textarea>
    <div style="display:flex;gap:10px;margin-top:10px;flex-wrap:wrap">
        <button id="cv-envio-prompt-salvar" class="cv-btn cv-btn-primary">💾 Salvar prompt</button>
        <button id="cv-envio-prompt-restaurar" class="cv-btn cv-btn-outline">↩ Voltar ao prompt-modelo</button>
    </div>
</div>
