<?php
// cancao-verdadeira/includes/apoio/views/parcerias.php
// Aba "🤝 Parcerias" da tela PIX e Parcerias: propostas enviadas pelo
// "Seja nosso parceiro" do rodapé (divulgar músicas, gravar uma música do
// catálogo ou outra), com a situação (nova, em conversa, aceita, recusada),
// atalhos para responder por e-mail/WhatsApp e excluir (pedido da pessoa/LGPD).
// v2.49.0: mostra nome artístico, cidade e música, e baixa as propostas e o
// contrato (Word) de cada parceiro.
// Incluído por CV_Apoio::render(). JS: assets/js/admin-apoio.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

global $wpdb;
$lista  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_parcerias ORDER BY FIELD(status,'nova','conversa','aceita','recusada'), id DESC LIMIT 300" );
$tipos  = CV_Apoio::tipos_parceria();
$status = CV_Apoio::status_parceria();
?>
<div class="cv-section">
    <table class="cv-table">
        <thead><tr><th>Data</th><th>Pessoa</th><th>Proposta</th><th>Mensagem</th><th>Situação</th><th style="text-align:center">Ações</th></tr></thead>
        <tbody>
        <?php if ( empty( $lista ) ) : ?>
        <tr><td colspan="6" style="text-align:center;color:#8A6A55;padding:24px">Nenhuma proposta ainda. Elas chegam pelo botão "🤝 Seja nosso parceiro" do rodapé.</td></tr>
        <?php endif; ?>
        <?php foreach ( $lista as $p ) :
            $zap = preg_replace( '/\D/', '', $p->telefone ); ?>
        <tr>
            <td style="white-space:nowrap"><?php echo esc_html( mysql2date( 'd/m/Y H:i', $p->criado_em ) ); ?></td>
            <td>
                <strong><?php echo esc_html( $p->nome ); ?></strong>
                <?php if ( ! empty( $p->nome_artistico ) ) : ?><br><span style="font-size:12px">🎤 <?php echo esc_html( $p->nome_artistico ); ?></span><?php endif; ?>
                <?php if ( ! empty( $p->cidade_uf ) ) : ?><br><span style="font-size:12px;color:#8A6A55">📍 <?php echo esc_html( $p->cidade_uf ); ?></span><?php endif; ?><br>
                <a href="mailto:<?php echo esc_attr( $p->email ); ?>" style="font-size:12px"><?php echo esc_html( $p->email ); ?></a>
                <?php if ( $zap ) : ?><br><a href="<?php echo esc_url( 'https://wa.me/' . ( 0 === strpos( $zap, '55' ) ? $zap : '55' . $zap ) ); ?>" target="_blank" rel="noopener" style="font-size:12px">WhatsApp <?php echo esc_html( $p->telefone ); ?></a><?php endif; ?>
            </td>
            <td style="font-size:13px"><?php echo esc_html( $tipos[ $p->tipo ] ?? $p->tipo ); ?>
                <?php if ( ! empty( $p->musica ) ) : ?><br>🎵 <strong><?php echo esc_html( $p->musica ); ?></strong><?php endif; ?>
                <?php if ( $p->link ) : ?><br><a href="<?php echo esc_url( $p->link ); ?>" target="_blank" rel="noopener nofollow">🔗 abrir link</a><?php endif; ?>
            </td>
            <td style="font-size:13px;color:#6B4C3B;max-width:360px;white-space:pre-line"><?php echo esc_html( $p->mensagem ); ?></td>
            <td>
                <select class="cv-input cv-parc-status" data-id="<?php echo (int) $p->id; ?>" style="min-width:140px">
                    <?php foreach ( $status as $k => $l ) : ?>
                    <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $p->status, $k ); ?>><?php echo esc_html( $l ); ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td style="text-align:center;white-space:nowrap">
                <a class="cv-btn cv-btn-outline cv-est-mini" href="<?php echo esc_url( CV_Parceria_Docs::url_baixar( $p, 'propostas' ) ); ?>" title="Baixar as propostas (Word)">📄</a>
                <?php if ( 'divulgar' === $p->tipo ) : ?>
                <a class="cv-btn cv-btn-outline cv-est-mini" href="<?php echo esc_url( CV_Parceria_Docs::url_baixar( $p, 'contrato' ) ); ?>" title="Baixar o contrato preenchido (Word)">📝</a>
                <?php endif; ?>
                <button class="cv-btn cv-est-mini cv-parc-excluir" data-id="<?php echo (int) $p->id; ?>" title="Excluir" style="background:rgba(192,57,43,.12);color:#C0392B;border:1px solid rgba(192,57,43,.3)">🗑</button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
