<?php
// cancao-verdadeira/includes/apoio/views/pix.php
// Aba "💠 Conta PIX" da tela PIX e Parcerias: cadastro da chave (e-mail,
// telefone ou aleatória), nome do recebedor e cidade (como o banco mostra),
// liga/desliga o PIX no site, e mostra um PIX de teste de R$ 1,00 (QR +
// copia e cola) para conferir no celular. Também guarda o e-mail dos avisos.
// Incluído por CV_Apoio::render(). JS: assets/js/admin-apoio.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$c = CV_Pix::config();
?>
<div class="cv-est-grid-2">
    <div class="cv-section">
        <h2 class="cv-section-title">Conta que recebe</h2>
        <p style="font-size:13px;color:#6B4C3B;margin-top:0">Por enquanto a loja aceita <strong>somente PIX</strong>. Use a mesma chave cadastrada no seu banco. Nenhuma senha ou dado bancário é guardado aqui, só a chave.</p>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-pix-tipo">Tipo de chave</label>
            <select id="cv-pix-tipo" class="cv-input">
                <?php foreach ( CV_Pix::tipos_chave() as $v => $l ) : ?>
                <option value="<?php echo esc_attr( $v ); ?>" <?php selected( $c['tipo'], $v ); ?>><?php echo esc_html( $l ); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="cv-form-group">
            <label class="cv-form-label" for="cv-pix-chave">Chave PIX</label>
            <input type="text" id="cv-pix-chave" class="cv-input" value="<?php echo esc_attr( 'telefone' === $c['tipo'] ? CV_Pix::chave_para_exibir() : $c['chave'] ); ?>" placeholder="ex.: cancaoverdadeira@gmail.com" />
        </div>
        <div class="cv-est-grid-2">
            <div class="cv-form-group">
                <label class="cv-form-label" for="cv-pix-nome">Nome do recebedor (até 25 letras)</label>
                <input type="text" id="cv-pix-nome" class="cv-input" maxlength="25" value="<?php echo esc_attr( $c['nome'] ); ?>" placeholder="EDUARDO MARQUES" />
            </div>
            <div class="cv-form-group">
                <label class="cv-form-label" for="cv-pix-cidade">Cidade (até 15 letras)</label>
                <input type="text" id="cv-pix-cidade" class="cv-input" maxlength="15" value="<?php echo esc_attr( $c['cidade'] ); ?>" placeholder="BELO HORIZONTE" />
            </div>
        </div>
        <label style="display:flex;gap:8px;align-items:center;margin:6px 0 14px;font-weight:600">
            <input type="checkbox" id="cv-pix-ativo" <?php checked( ! empty( $c['ativo'] ) ); ?> /> PIX ligado no site (pedidos e doações)
        </label>
        <p style="font-size:12px;color:#8A6A55;margin:0 0 12px">O nome e a cidade vão sem acentos e em maiúsculas, como exige o padrão do Banco Central.</p>
        <button id="cv-pix-salvar" class="cv-btn cv-btn-primary">💾 Salvar conta PIX</button>
    </div>

    <div class="cv-section">
        <h2 class="cv-section-title">Teste: PIX de R$ 1,00</h2>
        <?php if ( '' === $c['chave'] ) : ?>
        <p style="color:#8A6A55">Salve a conta ao lado para ver o QR Code de teste.</p>
        <?php else : ?>
        <p style="font-size:13px;color:#6B4C3B;margin-top:0">Pague este PIX de teste pelo celular e confira se o nome e o dinheiro chegam certinho na sua conta.<?php echo empty( $c['ativo'] ) ? ' <strong>(O PIX está desligado para os visitantes.)</strong>' : ''; ?></p>
        <?php echo CV_Pix::bloco( 1, 'CVTESTE', 'Teste Cancao Verdadeira', '', true ); ?>
        <?php endif; ?>
    </div>
</div>

<div class="cv-section">
    <h2 class="cv-section-title">✉️ E-mail dos avisos</h2>
    <p style="font-size:13px;color:#6B4C3B;margin-top:0">Recebe as propostas do "Seja nosso parceiro".</p>
    <div style="display:flex;gap:8px;max-width:520px">
        <input type="email" id="cv-apoio-email" class="cv-input" value="<?php echo esc_attr( CV_Apoio::email() ); ?>" style="flex:1" />
        <button id="cv-apoio-email-salvar" class="cv-btn cv-btn-primary">Salvar</button>
    </div>
</div>
