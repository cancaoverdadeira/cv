/*
 * cancao-verdadeira/assets/js/cv-pix.js
 * Blocos "Pagar com PIX" (v2.48.0): desenha o QR Code a partir do código
 * copia e cola que o servidor gerou (atributo data-pix, CV_Pix::bloco) e
 * cuida do botão "Copiar". A biblioteca do QR (qrcode-generator 1.4.4, MIT)
 * só é carregada na primeira vez que um PIX aparece na tela.
 * Uso por outros scripts: window.CVPix.desenhar(elementoOuSeletor).
 */
(function ($) {
    'use strict';
    var cfg = window.cvPix || {};
    var fila = null;

    function carregarLib(cb) {
        if (window.qrcode) { cb(); return; }
        if (fila) { fila.push(cb); return; }
        fila = [cb];
        var s = document.createElement('script');
        s.src = cfg.qrLib;
        s.async = true;
        s.onload = function () { var f = fila; fila = null; f.forEach(function (fn) { fn(); }); };
        s.onerror = function () { fila = null; $('.cv-pix-qr-carregando').text('Não foi possível gerar o QR Code. Use o Copia e Cola abaixo.'); };
        document.head.appendChild(s);
    }

    function desenhar(alvo) {
        $(alvo).find('.cv-pix[data-pix]').addBack('.cv-pix[data-pix]').each(function () {
            var $b = $(this);
            if ($b.data('desenhado') || !$b.is(':visible')) { return; }
            $b.data('desenhado', 1);
            carregarLib(function () {
                try {
                    var qr = window.qrcode(0, 'M');
                    qr.addData($b.attr('data-pix'));
                    qr.make();
                    $b.find('.cv-pix-qr').html(qr.createImgTag(6, 12));
                    $b.find('.cv-pix-qr img').attr('alt', 'QR Code do PIX');
                } catch (e) {
                    $b.find('.cv-pix-qr').text('Use o PIX Copia e Cola ao lado.');
                }
            });
        });
    }

    function copiar(texto, $btn) {
        var feito = function () {
            var original = $btn.data('rotulo') || $btn.text();
            $btn.data('rotulo', original).text('✅ Copiado!');
            setTimeout(function () { $btn.text(original); }, 2500);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(texto).then(feito, function () { copiarAntigo(texto); feito(); });
        } else { copiarAntigo(texto); feito(); }
    }
    function copiarAntigo(texto) {
        var $t = $('<textarea readonly>').val(texto).css({ position: 'fixed', top: '-1000px' }).appendTo('body');
        $t[0].select();
        try { document.execCommand('copy'); } catch (e) {}
        $t.remove();
    }

    $(document).on('click', '.cv-pix-copiar', function () {
        var $inp = $(this).closest('.cv-pix').find('.cv-pix-codigo');
        $inp[0].select();
        copiar($inp.val(), $(this));
    });

    window.CVPix = { desenhar: desenhar };
    $(function () { desenhar(document.body); });
})(jQuery);
