/*
 * cancao-verdadeira/assets/js/admin-transferir.js
 * Caixa "🔁 Transferir autoria" da tela Usuários (v2.52.0): confirma e
 * chama o AJAX cv_transferir_autoria. nonce e ajaxUrl em window.cvTransf.
 */
jQuery(function ($) {
    var cfg = window.cvTransf || {};
    $('#cv-transf-btn').on('click', function () {
        var $b = $(this), de = $('#cv-transf-de'), para = $('#cv-transf-para');
        if (de.val() === para.val()) { alert('Escolha usuários diferentes.'); return; }
        if (!confirm('Passar TUDO de "' + $.trim(de.find(':selected').text().split('—')[0]) + '" para "' + $.trim(para.find(':selected').text()) + '"?\n\nNinguém será excluído.')) { return; }
        $b.prop('disabled', true).text('Transferindo...');
        $.post(cfg.ajaxUrl, { action: 'cv_transferir_autoria', nonce: cfg.nonce, de: de.val(), para: para.val() }, function (r) {
            var ok = !!(r && r.success);
            $('#cv-transf-msg').text((ok ? '✅ ' : '❌ ') + ((r && r.data && r.data.message) || 'Não foi possível transferir.'))
                .css({ background: ok ? '#E3F4E8' : '#FBE9E7', color: ok ? '#1C7C44' : '#C0392B', border: '1px solid ' + (ok ? '#1C7C44' : '#C0392B') }).show();
            $b.prop('disabled', false).text('🔁 Transferir tudo');
        }).fail(function () { $('#cv-transf-msg').text('❌ Falha de conexão.').show(); $b.prop('disabled', false).text('🔁 Transferir tudo'); });
    });
});
