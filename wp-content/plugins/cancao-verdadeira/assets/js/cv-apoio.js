/*
 * cancao-verdadeira/assets/js/cv-apoio.js
 * "Apoie" do rodapé (v2.48.0): abre e fecha as janelas "Seja nosso parceiro"
 * e "Seja nosso colaborador", envia a proposta de parceria e gera o PIX da
 * doação no valor escolhido (o código vem do servidor; o QR é desenhado por
 * cv-pix.js). ajaxUrl e nonce: window.cvApoio (parceria) e window.cvPix (PIX).
 */
jQuery(function ($) {
    var cfg = window.cvApoio || {};
    var pix = window.cvPix || {};

    // ── Janelas ──────────────────────────────────────────────────
    $(document).on('click', '.cv-apoio-abrir', function () {
        var d = document.getElementById($(this).data('janela'));
        if (!d) { return; }
        if (typeof d.showModal === 'function') { d.showModal(); } else { d.setAttribute('open', ''); }
        $(d).find('input:visible, button:visible').not('.cv-janela-fechar').first().trigger('focus');
    });
    function fechar(d) { if (typeof d.close === 'function') { d.close(); } else { d.removeAttribute('open'); } }
    $(document).on('click', '.cv-janela-fechar', function () { fechar($(this).closest('dialog')[0]); });
    // Clique fora da janela (no fundo escuro) fecha
    $(document).on('click', 'dialog.cv-janela', function (e) { if (e.target === this) { fechar(this); } });

    // ── Parceria ─────────────────────────────────────────────────
    $('#cv-parceria-form').on('submit', function (e) {
        e.preventDefault();
        var $f = $(this), $msg = $f.find('.cv-janela-msg'), $b = $f.find('.cv-janela-enviar');
        var dados = $f.serializeArray();
        dados.push({ name: 'action', value: 'cv_parceria_enviar' }, { name: 'nonce', value: cfg.nonce });
        $b.prop('disabled', true).text('Enviando...');
        $msg.removeClass('is-ok is-erro').text('');
        $.post(cfg.ajaxUrl, $.param(dados), function (r) {
            var ok = r && r.success;
            $msg.addClass(ok ? 'is-ok' : 'is-erro').text((r && r.data && r.data.message) || 'Não foi possível enviar.');
            if (ok) { $f[0].reset(); $f.find('input[name="tipo"]').first().prop('checked', true); }
        }).fail(function () {
            $msg.addClass('is-erro').text('Falha de conexão. Tente de novo.');
        }).always(function () { $b.prop('disabled', false).text('Enviar proposta'); });
    });

    // ── Doação por PIX ───────────────────────────────────────────
    function gerar(valor) {
        var $r = $('#cv-doacao-resultado').html('<p class="cv-janela-msg">Gerando o PIX…</p>');
        $.post(pix.ajaxUrl, { action: 'cv_pix_gerar', nonce: pix.nonce, valor: valor }, function (r) {
            if (r && r.success) {
                $r.html(r.data.html);
                if (window.CVPix) { window.CVPix.desenhar($r[0]); }
            } else {
                $r.html($('<p class="cv-janela-msg is-erro">').text((r && r.data && r.data.message) || 'Não foi possível gerar o PIX.'));
            }
        }).fail(function () { $r.html('<p class="cv-janela-msg is-erro">Falha de conexão. Tente de novo.</p>'); });
    }
    $(document).on('click', '.cv-doacao-valor', function () {
        $('.cv-doacao-valor').removeClass('is-ativo');
        $(this).addClass('is-ativo');
        $('#cv-doacao-valor').val('');
        gerar($(this).data('valor'));
    });
    $('#cv-doacao-gerar').on('click', function () {
        var v = $.trim($('#cv-doacao-valor').val());
        if (!v) { $('#cv-doacao-valor').trigger('focus'); return; }
        $('.cv-doacao-valor').removeClass('is-ativo');
        gerar(v.replace(/\./g, '').replace(',', '.'));
    });
    $('#cv-doacao-valor').on('keydown', function (e) { if (e.key === 'Enter') { e.preventDefault(); $('#cv-doacao-gerar').trigger('click'); } });
    $('#cv-doacao-sem-valor').on('click', function () { $('.cv-doacao-valor').removeClass('is-ativo'); gerar(0); });
});
