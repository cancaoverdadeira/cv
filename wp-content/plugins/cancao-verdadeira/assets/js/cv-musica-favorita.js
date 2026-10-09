/* ═══════════════════════════════════════════════════════════════
   cancao-verdadeira/assets/js/cv-musica-favorita.js
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   v2.65.0 (08/10/2026): "🎵 Minha música favorita" no perfil do ouvinte.
   1) Lista de sentimentos → lista de músicas daquele sentimento.
   2) Ao escolher a música, o cartão aparece (▶ Tocar e ❤ já funcionam pelo
      cv-player.js / cv-theme.js do tema) com as estrelas da nota.
   3) "💾 Guardar como minha favorita" grava pelo AJAX cv_fav_salvar.
   Dados vindos do PHP em window.cvFav (class-cv-musica-favorita.php).
   ═══════════════════════════════════════════════════════════════ */
(function ($) {
    'use strict';
    if (!window.cvFav) { return; }

    var $sent    = $('#cv-fav-sentimento');
    var $mus     = $('#cv-fav-musica');
    var $previa  = $('#cv-fav-previa');
    var $guardar = $('#cv-fav-guardar');
    var $msg     = $('#cv-fav-msg');
    var guardada = parseInt(cvFav.musica, 10) || 0;
    var pedido   = null;

    function grupoPorId(id) {
        for (var i = 0; i < cvFav.grupos.length; i++) {
            if (String(cvFav.grupos[i].id) === String(id)) { return cvFav.grupos[i]; }
        }
        return null;
    }

    function preencherMusicas(grupoId, marcada) {
        var g = grupoPorId(grupoId);
        $mus.empty();
        if (!g) {
            $mus.append($('<option>', { value: '', text: '⬅ Escolha o sentimento' })).prop('disabled', true);
            return;
        }
        $mus.append($('<option>', { value: '', text: 'Escolha a música (' + g.musicas.length + ')' }));
        $.each(g.musicas, function (_, m) {
            $mus.append($('<option>', { value: m.id, text: m.titulo, selected: m.id === marcada }));
        });
        $mus.prop('disabled', false);
    }

    function atualizarBotao() {
        var escolhida = parseInt($mus.val(), 10) || 0;
        $guardar.prop('hidden', !escolhida || escolhida === guardada);
    }

    function mostrarCartao(id) {
        if (pedido) { pedido.abort(); }
        if (!id) { return; }
        $previa.attr('aria-busy', 'true').addClass('cv-fav-carregando');
        pedido = $.post(cvFav.ajaxUrl, { action: 'cv_fav_cartao', nonce: cvFav.nonce, music_id: id }, function (res) {
            if (res && res.success) { $previa.html(res.data.html); }
        }).always(function () {
            $previa.attr('aria-busy', 'false').removeClass('cv-fav-carregando');
        });
    }

    // Monta a lista de sentimentos
    $.each(cvFav.grupos, function (_, g) {
        $sent.append($('<option>', { value: g.id, text: g.nome, selected: String(g.id) === String(cvFav.grupo) }));
    });
    if (cvFav.grupo) { preencherMusicas(cvFav.grupo, guardada); }

    $sent.on('change', function () {
        preencherMusicas(this.value, 0);
        $msg.text('');
        atualizarBotao();
    });

    $mus.on('change', function () {
        var id = parseInt(this.value, 10) || 0;
        $msg.text('');
        atualizarBotao();
        if (id) { mostrarCartao(id); }
    });

    $guardar.on('click', function () {
        var id = parseInt($mus.val(), 10) || 0;
        if (!id) { return; }
        $guardar.prop('disabled', true);
        $.post(cvFav.ajaxUrl, { action: 'cv_fav_salvar', nonce: cvFav.nonce, music_id: id }, function (res) {
            if (res && res.success) {
                guardada = id;
                $msg.removeClass('cv-erro').text('✅ "' + res.data.titulo + '" agora é a sua música favorita!');
                atualizarBotao();
            } else {
                $msg.addClass('cv-erro').text((res && res.data && res.data.message) || 'Não deu certo. Tente de novo.');
            }
        }).fail(function () {
            $msg.addClass('cv-erro').text('Sem conexão. Tente de novo.');
        }).always(function () {
            $guardar.prop('disabled', false);
        });
    });

    // Estrelas: a nota da pessoa para a música mostrada
    $previa.on('click', '.cv-fav-estrela', function () {
        var $b     = $(this);
        var $caixa = $b.closest('.cv-fav-estrelas');
        var nota   = parseInt($b.data('nota'), 10);
        var $info  = $caixa.find('.cv-fav-estrelas-msg');
        $caixa.find('.cv-fav-estrela').prop('disabled', true);
        $.post(cvFav.ajaxUrl, { action: 'cv_rate_music', nonce: cvFav.nota, music_id: $caixa.data('music-id'), rating: nota }, function (res) {
            if (res && res.success) {
                $caixa.find('.cv-fav-estrela').each(function () {
                    var n = parseInt($(this).data('nota'), 10);
                    $(this).toggleClass('cv-on', n <= nota).attr('aria-pressed', n === nota ? 'true' : 'false');
                });
                $info.text('Obrigado! Nota ' + nota + ' guardada.');
            } else {
                $info.text((res && res.data && res.data.message) || 'Não deu certo.');
            }
        }).fail(function () {
            $info.text('Sem conexão.');
        }).always(function () {
            $caixa.find('.cv-fav-estrela').prop('disabled', false);
        });
    });
})(jQuery);
