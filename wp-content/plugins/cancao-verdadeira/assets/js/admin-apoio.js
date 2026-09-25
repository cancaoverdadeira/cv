/*
 * cancao-verdadeira/assets/js/admin-apoio.js
 * Tela "💠 PIX e Parcerias" do painel (v2.48.0): salvar a conta PIX e o
 * e-mail dos avisos, mudar a situação e excluir propostas de parceria.
 * nonce e ajaxUrl vêm de window.cvApoioAdmin (CV_Apoio::enqueue_admin()).
 */
jQuery(function ($) {
    var cfg = window.cvApoioAdmin || {};

    function msg(texto, ok) {
        $('#cv-apoio-msg').text(texto).css({
            background: ok ? '#E3F4E8' : '#FBE9E7', border: '1px solid ' + (ok ? '#1C7C44' : '#C0392B'),
            color: ok ? '#1C7C44' : '#C0392B', padding: '10px 14px', borderRadius: '8px', marginBottom: '14px'
        }).show();
        $('html,body').animate({ scrollTop: 0 }, 200);
    }
    function enviar(dados, $btn, recarregar) {
        var rotulo = $btn ? $btn.text() : '';
        if ($btn) { $btn.prop('disabled', true).text('Aguarde...'); }
        dados.nonce = cfg.nonce;
        $.post(cfg.ajaxUrl, dados, function (r) {
            var ok = !!(r && r.success);
            msg((ok ? '✅ ' : '❌ ') + ((r && r.data && r.data.message) || (ok ? 'Feito.' : 'Não foi possível concluir.')), ok);
            if (ok && recarregar) { setTimeout(function () { location.reload(); }, 900); }
            else if ($btn) { $btn.prop('disabled', false).text(rotulo); }
        }).fail(function () { msg('❌ Falha de conexão.', false); if ($btn) { $btn.prop('disabled', false).text(rotulo); } });
    }

    var exemplos = { email: 'ex.: cancaoverdadeira@gmail.com', telefone: 'ex.: (31) 99999-9999', aleatoria: 'ex.: 123e4567-e89b-12d3-a456-426614174000' };
    $('#cv-pix-tipo').on('change', function () { $('#cv-pix-chave').attr('placeholder', exemplos[this.value] || ''); }).trigger('change');

    $('#cv-pix-salvar').on('click', function () {
        enviar({
            action: 'cv_apoio_pix_salvar',
            tipo: $('#cv-pix-tipo').val(),
            chave: $('#cv-pix-chave').val(),
            nome: $('#cv-pix-nome').val(),
            cidade: $('#cv-pix-cidade').val(),
            ativo: $('#cv-pix-ativo').is(':checked') ? 1 : 0
        }, $(this), true);
    });
    $('#cv-apoio-email-salvar').on('click', function () {
        enviar({ action: 'cv_apoio_email', email: $('#cv-apoio-email').val() }, $(this), false);
    });
    $(document).on('change', '.cv-parc-status', function () {
        enviar({ action: 'cv_apoio_parceria_status', id: $(this).data('id'), status: this.value }, null, false);
    });
    $(document).on('click', '.cv-parc-excluir', function () {
        if (!confirm('Excluir esta proposta de vez? (Use quando a pessoa pedir para apagar os dados.)')) { return; }
        enviar({ action: 'cv_apoio_parceria_excluir', id: $(this).data('id') }, $(this), true);
    });
});
