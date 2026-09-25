/*
 * cancao-verdadeira/assets/js/cv-pedidos.js
 * Aba "🛍️ Meus Pedidos" da Minha Área (v2.47.0): botão "Incluir pedido"
 * e botão "Excluir" de cada pedido pendente. Depois de cada ação, a lista
 * e o select (estoque atualizado) voltam prontos do servidor.
 * v2.48.0: botão "💠 Pagar com PIX" abre/fecha o QR do pedido (cv-pix.js
 * desenha), e o PIX do pedido recém-criado já abre sozinho.
 * ajaxUrl e nonce vêm de window.cvPedidos (CV_Estoque_Area::enqueue()).
 */
jQuery(function ($) {
    var cfg = window.cvPedidos || {};
    if (!$('.cv-ped').length) { return; }

    function aviso(texto, ok) {
        $('#cv-ped-msg').removeClass('is-ok is-erro').addClass(ok ? 'is-ok' : 'is-erro').text(texto).show();
    }

    // A quantidade máxima acompanha o estoque do item escolhido
    $('#cv-ped-item').on('change', function () {
        var max = parseInt($(this).find('option:selected').data('max'), 10) || 10;
        var $q = $('#cv-ped-qtd').attr('max', max);
        if (parseInt($q.val(), 10) > max) { $q.val(max); }
    });

    $('#cv-ped-incluir').on('click', function () {
        var $b = $(this);
        var item = $('#cv-ped-item').val();
        if (!item) { aviso('Escolha um item na lista.', false); $('#cv-ped-item').focus(); return; }
        $b.prop('disabled', true).text('Enviando...');
        $.post(cfg.ajaxUrl, {
            action: 'cv_pedido_criar',
            nonce: cfg.nonce,
            variacao_id: item,
            quantidade: $('#cv-ped-qtd').val(),
            obs: $('#cv-ped-obs').val()
        }, function (r) {
            if (r && r.success) {
                aviso('✅ ' + r.data.message, true);
                $('#cv-ped-lista').html(r.data.html);
                $('#cv-ped-item').html(r.data.opcoes);
                abrirPix(r.data.pedido_id);
                $('#cv-ped-qtd').val(1);
                $('#cv-ped-obs').val('');
            } else {
                aviso('⚠️ ' + ((r && r.data && r.data.message) || 'Não foi possível enviar o pedido.'), false);
            }
        }).fail(function () {
            aviso('⚠️ Falha de conexão. Tente de novo.', false);
        }).always(function () {
            $b.prop('disabled', false).text('➕ Incluir pedido');
        });
    });

    // ── PIX do pedido ────────────────────────────────────────────
    function abrirPix(id) {
        var $caixa = $('#cv-ped-pix-' + id);
        if (!$caixa.length) { return; }
        $caixa.prop('hidden', false);
        $('.cv-ped-pagar[data-id="' + id + '"]').attr('aria-expanded', 'true').text('Fechar PIX');
        if (window.CVPix) { window.CVPix.desenhar($caixa[0]); }
        $('html,body').animate({ scrollTop: $caixa.closest('.cv-ped-linha').offset().top - 90 }, 300);
    }
    $(document).on('click', '.cv-ped-pagar', function () {
        var id = $(this).data('id');
        if ($(this).attr('aria-expanded') === 'true') {
            $('#cv-ped-pix-' + id).prop('hidden', true);
            $(this).attr('aria-expanded', 'false').text('💠 Pagar com PIX');
        } else { abrirPix(id); }
    });

    $(document).on('click', '.cv-ped-excluir', function () {
        var $b = $(this);
        if (!confirm('Excluir o pedido #' + $b.data('id') + '?')) { return; }
        $b.prop('disabled', true);
        $.post(cfg.ajaxUrl, { action: 'cv_pedido_cancelar', nonce: cfg.nonce, pedido_id: $b.data('id') }, function (r) {
            if (r && r.success) {
                aviso('Pedido excluído.', true);
                $('#cv-ped-lista').html(r.data.html);
            } else {
                aviso('⚠️ ' + ((r && r.data && r.data.message) || 'Não foi possível excluir.'), false);
                $b.prop('disabled', false);
            }
        }).fail(function () { aviso('⚠️ Falha de conexão.', false); $b.prop('disabled', false); });
    });
});
