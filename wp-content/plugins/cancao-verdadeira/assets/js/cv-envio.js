/*
 * cancao-verdadeira/assets/js/cv-envio.js
 * Aba "🎤 Enviar música" da Minha Área (v2.50.0): envia cada etapa (contrato
 * em PDF, "Já fiz o PIX" com comprovante opcional, link do YouTube e dados
 * da música) e troca o cartão pelo HTML novo que o servidor devolve.
 * "Baixar prompt de ajuda" salva o rascunho antes, para o Word levar a letra.
 * ajaxUrl e nonce: window.cvEnvio (CV_Envio_Area::enqueue()).
 */
jQuery(function ($) {
    var cfg = window.cvEnvio || {};
    var $aba = $('#cv-envio-aba');
    if (!$aba.length) { return; }

    // O QR do PIX só é desenhado com a aba visível: desenha ao abrir a aba
    function desenharPix() { if (window.CVPix && $aba.is(':visible')) { window.CVPix.desenhar($aba[0]); } }
    $(document).on('click', '.cv-dash-tab[data-tab="enviar"]', function () { setTimeout(desenharPix, 50); });
    setTimeout(desenharPix, 400); // aberta direto por ?aba=enviar

    function aviso($onde, texto, ok) {
        $onde.removeClass('is-ok is-erro').addClass(ok ? 'is-ok' : 'is-erro').text(texto).prop('hidden', false);
    }
    function trocar($card, html) {
        var $novo = $(html);
        $card.replaceWith($novo);
        if (window.CVPix) { window.CVPix.desenhar($novo[0]); }
        return $novo;
    }
    function postar(dados, $btn, feito) {
        var rotulo = $btn ? $btn.text() : '';
        if ($btn) { $btn.prop('disabled', true).text('Enviando...'); }
        dados.append('nonce', cfg.nonce);
        $.ajax({ url: cfg.ajaxUrl, type: 'POST', data: dados, processData: false, contentType: false })
            .done(function (r) { feito(r || {}); })
            .fail(function () { feito({ success: false, data: { message: 'Falha de conexão (ou arquivo grande demais). Tente de novo.' } }); })
            .always(function () { if ($btn && $btn.closest('body').length) { $btn.prop('disabled', false).text(rotulo); } });
    }

    // Etapas (cada <form data-acao> de um cartão)
    $aba.on('submit', '.cv-envio-form', function (e) {
        e.preventDefault();
        var $f = $(this), $card = $f.closest('.cv-envio-card');
        var dados = new FormData(this);
        dados.append('action', 'cv_envio_' + $f.data('acao'));
        dados.append('envio_id', $card.data('id'));
        postar(dados, $f.find('button[type="submit"]'), function (r) {
            var msg = (r.data && r.data.message) || 'Não foi possível concluir.';
            if (r.success && r.data.html) {
                var $novo = trocar($card, r.data.html);
                aviso($novo.find('.cv-envio-msg').first(), msg, true);
                $('html,body').animate({ scrollTop: $novo.offset().top - 90 }, 300);
            } else {
                aviso($card.find('.cv-envio-msg').first(), msg, false);
            }
        });
    });

    // Salvar rascunho da etapa 4 (e, se pedido, baixar o prompt depois)
    function salvarRascunho($card, depois) {
        var $f = $card.find('.cv-envio-dados');
        var dados = new FormData($f[0]);
        dados.append('action', 'cv_envio_dados');
        dados.append('envio_id', $card.data('id'));
        dados.append('so_salvar', '1');
        postar(dados, $card.find('.cv-envio-rascunho'), function (r) {
            if (r.success && r.data.html) {
                var $novo = trocar($card, r.data.html);
                aviso($novo.find('.cv-envio-msg').first(), r.data.message, true);
                if (depois) { depois($novo); }
            } else {
                aviso($card.find('.cv-envio-msg').first(), (r.data && r.data.message) || 'Não foi possível salvar.', false);
            }
        });
    }
    $aba.on('click', '.cv-envio-rascunho', function () { salvarRascunho($(this).closest('.cv-envio-card')); });
    $aba.on('click', '.cv-envio-prompt', function (e) {
        e.preventDefault();
        var url = $(this).data('url');
        salvarRascunho($(this).closest('.cv-envio-card'), function () { window.location.href = url; });
    });

    // Novo envio e excluir
    $aba.on('click', '.cv-envio-novo', function () {
        var dados = new FormData();
        dados.append('action', 'cv_envio_novo');
        postar(dados, $(this), function (r) {
            if (r.success) {
                $aba.html(r.data.html);
                var $c = $aba.find('.cv-envio-card').first();
                if ($c.length) { $('html,body').animate({ scrollTop: $c.offset().top - 90 }, 300); }
            } else { aviso($('#cv-envio-msg-geral'), r.data.message, false); }
        });
    });
    $aba.on('click', '.cv-envio-excluir', function () {
        if (!confirm('Excluir este envio? Os arquivos enviados também serão apagados.')) { return; }
        var dados = new FormData();
        dados.append('action', 'cv_envio_excluir');
        dados.append('envio_id', $(this).closest('.cv-envio-card').data('id'));
        postar(dados, $(this), function (r) {
            if (r.success) { $aba.html(r.data.html); aviso($('#cv-envio-msg-geral'), r.data.message, true); }
            else { aviso($('#cv-envio-msg-geral'), r.data.message, false); }
        });
    });
});
