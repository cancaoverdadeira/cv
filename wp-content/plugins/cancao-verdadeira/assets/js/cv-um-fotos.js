/* ═══════════════════════════════════════════════════════════════
   cancao-verdadeira/assets/js/cv-um-fotos.js
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   v2.66.0 (08/10/2026): janela "Trocar a foto do perfil / de capa" do
   Ultimate Member. O um-modal.js não trata erro no "Aplicar": a janela
   ficava em "Processando..." para sempre. Aqui:
   1) Erro do servidor → mensagem em português + "Fechar" e "Fechar e
      recarregar"; o botão "Aplicar" volta a funcionar.
   2) Mais de 40 s sem resposta → mesma caixa, com aviso de demora.
   3) v2.66.2 (09/10/2026): manda junto com o "Aplicar" um resumo da página
      (sessão, usuário, hora, modo do formulário) para o log dizer por que
      a chave de segurança deu 403 no ar.
   4) v2.66.3: se o pedido sair sem a chave (um-modal.js velho no cache do
      Cloudflare), a chave é lida da própria página (data-resize-nonce).
   Carregado só no perfil (class-cv-um-fotos.php). Estilo: cv-um-fotos.css.
   ═══════════════════════════════════════════════════════════════ */
(function ($) {
    'use strict';

    var LIMITE_MS = 40000;
    var relogio   = null;
    var textoBotao = 'Aplicar';

    var TRADUCOES = {
        'Invalid file ownership': 'O site não reconheceu a imagem enviada. Feche, recarregue a página e envie de novo.',
        'Too many requests': 'Muitas tentativas seguidas. Espere 1 minuto e tente de novo.',
        'Invalid coordinates': 'O recorte da imagem não foi entendido. Tente marcar a área de novo.',
        'Please login to edit this user': 'Sua sessão terminou. Entre de novo na sua conta.'
    };

    function caixa() {
        return $('.um-modal:visible .um-modal-body').first();
    }

    function liberarBotao() {
        $('.um-modal:visible .um-finish-upload.image').removeClass('disabled').html(textoBotao);
    }

    function mostrarAviso(titulo, detalhe) {
        var $corpo = caixa();
        if (!$corpo.length) { return; }
        $corpo.find('.cv-foto-aviso').remove();
        var $aviso = $('<div class="cv-foto-aviso" role="alert"></div>');
        $aviso.append($('<p class="cv-foto-aviso-titulo"></p>').text(titulo));
        if (detalhe) { $aviso.append($('<p class="cv-foto-aviso-detalhe"></p>').text(detalhe)); }
        var $botoes = $('<div class="cv-foto-aviso-botoes"></div>');
        $botoes.append($('<button type="button" class="cv-foto-fechar">Fechar</button>'));
        $botoes.append($('<button type="button" class="cv-foto-recarregar">🔄 Fechar e recarregar a página</button>'));
        $aviso.append($botoes);
        $corpo.append($aviso);
        liberarBotao();
        if (typeof window.um_modal_responsive === 'function') { window.um_modal_responsive(); }
    }

    function pararRelogio() {
        if (relogio) { clearTimeout(relogio); relogio = null; }
    }

    // Guarda o texto do botão antes de o UM trocar por "Processando..."
    $(document).on('mousedown touchstart keydown', '.um-finish-upload.image:not(.disabled)', function () {
        var t = $.trim($(this).text());
        if (t && t !== $(this).attr('data-processing')) { textoBotao = t; }
    });

    // Diagnóstico (v2.66.2): dados da página vão junto com o pedido do "Aplicar".
    $.ajaxPrefilter(function (opcoes) {
        if (typeof opcoes.data !== 'string' || opcoes.data.indexOf('action=um_resize_image') === -1) { return; }
        var d = window.cvUmFotos || {};
        var m = /(?:^|&)key=([^&]*)/.exec(opcoes.data);
        var chave = m ? decodeURIComponent(m[1]) : '';
        var $campo = $('div.um-field-image').filter(function () { return $(this).attr('data-key') === chave; });
        var tela = $campo.length + ' campo(s), modo do campo "' + ($campo.first().attr('data-mode') || '') +
            '", modo do formulário "' + ($campo.first().closest('.um-form').attr('data-mode') || '') +
            '", ' + $('.um-form').length + ' formulário(s)';
        // Sem chave no pedido: usa a que o servidor pôs na página.
        if (/(?:^|&)_wpnonce=(?:&|$)/.test(opcoes.data) || !/(?:^|&)_wpnonce=/.test(opcoes.data)) {
            var n = $campo.first().attr('data-resize-nonce') || '';
            if (n) {
                opcoes.data = opcoes.data.replace(/(^|&)_wpnonce=(?=&|$)/, '$1') + '&_wpnonce=' + encodeURIComponent(n);
                tela += ', chave lida da página';
            }
        }
        opcoes.data += '&cv_diag_s=' + encodeURIComponent(d.s || '') +
            '&cv_diag_u=' + encodeURIComponent(d.u || '') +
            '&cv_diag_t=' + encodeURIComponent(d.t || '') +
            '&cv_diag_f=' + encodeURIComponent(tela);
    });

    $(document).on('click', '.um-finish-upload.image', function () {
        caixa().find('.cv-foto-aviso').remove();
        pararRelogio();
        relogio = setTimeout(function () {
            if ($('.um-modal:visible .um-finish-upload.image.disabled').length) {
                mostrarAviso('Está demorando mais do que o normal.',
                    'A imagem pode já ter sido salva. Feche e recarregue a página para conferir.');
            }
        }, LIMITE_MS);
    });

    // Resposta do "Aplicar" (um_resize_image)
    $(document).ajaxComplete(function (evento, xhr, opcoes) {
        if (typeof opcoes.data !== 'string' || opcoes.data.indexOf('action=um_resize_image') === -1) { return; }
        pararRelogio();
        var r = xhr.responseJSON;
        if (xhr.status === 200 && r && r.success) { return; } // deu certo: o UM fecha a janela

        var detalhe = '';
        if (r && typeof r.data === 'string') {
            detalhe = TRADUCOES[r.data] || ('Detalhe: ' + r.data);
        } else if (xhr.status && xhr.status !== 200) {
            detalhe = 'O servidor respondeu com o código ' + xhr.status + '. Tente uma imagem menor ou tente mais tarde.';
        } else {
            detalhe = 'Tente uma imagem menor ou tente mais tarde.';
        }
        mostrarAviso('Não foi possível salvar a imagem.', detalhe);
    });

    $(document).on('click', '.cv-foto-fechar', function () {
        pararRelogio();
        if (typeof window.um_remove_modal === 'function') { window.um_remove_modal(); }
    });

    $(document).on('click', '.cv-foto-recarregar', function () {
        window.location.reload();
    });
})(jQuery);
