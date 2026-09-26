/*
 * cancao-verdadeira/assets/js/cv-apoio.js
 * "Apoie" do rodapé (v2.48.0): abre e fecha as janelas "Seja nosso parceiro"
 * e "Apoie a Canção Verdadeira" (antes "Seja nosso colaborador"), envia a proposta de parceria e gera o PIX da
 * doação no valor escolhido (o código vem do servidor; o QR é desenhado por
 * cv-pix.js). ajaxUrl e nonce: window.cvApoio (parceria) e window.cvPix (PIX).
 * v2.49.0: depois do envio, o formulário dá lugar aos botões "Nossas
 * propostas" e "Modelo de contrato" (texto na tela + "Baixar em Word").
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
            if (ok && r.data.id) {
                mostrarDocs(r.data);
                $f[0].reset();
                $f.find('input[name="tipo"]').first().prop('checked', true);
            } else if (ok) { $f[0].reset(); }
        }).fail(function () {
            $msg.addClass('is-erro').text('Falha de conexão. Tente de novo.');
        }).always(function () { $b.prop('disabled', false).text('Enviar proposta'); });
    });

    // ── Documentos do parceiro (propostas e contrato) ────────────
    var docs = { id: 0, t: '' };
    function mostrarDocs(d) {
        docs = { id: d.id, t: d.t };
        $('#cv-parceria-form').prop('hidden', true);
        $('#cv-parceria-ok').text(d.message);
        $('#cv-parceria-btn-contrato').prop('hidden', !d.contrato);
        $('.cv-parceria-sem-contrato').prop('hidden', !!d.contrato);
        $('#cv-parceria-doc-area').prop('hidden', true);
        $('#cv-parceria-docs').prop('hidden', false);
        $('#cv-janela-parceiro')[0].scrollTop = 0;
    }
    $(document).on('click', '.cv-parceria-doc', function () {
        var $b = $(this), doc = $b.data('doc');
        $('.cv-parceria-doc').removeClass('is-ativo');
        $b.addClass('is-ativo');
        var $area = $('#cv-parceria-doc-area').prop('hidden', false);
        $area.find('.cv-parceria-doc-texto').html('<p>Carregando…</p>');
        $.post(cfg.ajaxUrl, { action: 'cv_parceria_doc_ver', doc: doc, id: docs.id, t: docs.t }, function (r) {
            if (r && r.success) {
                $area.find('.cv-parceria-doc-texto').html(r.data.html).scrollTop(0);
                $area.find('.cv-parceria-baixar').attr('href', r.data.baixar);
            } else {
                $area.find('.cv-parceria-doc-texto').html($('<p class="cv-janela-msg is-erro">').text((r && r.data && r.data.message) || 'Não foi possível abrir o documento.'));
            }
        });
    });
    // Ao fechar a janela depois do envio, volta o formulário para uma nova proposta
    $('#cv-janela-parceiro').on('close', function () {
        if (!$('#cv-parceria-docs').prop('hidden')) {
            $('#cv-parceria-docs').prop('hidden', true);
            $('#cv-parceria-form').prop('hidden', false).find('.cv-janela-msg').removeClass('is-ok is-erro').text('');
        }
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
