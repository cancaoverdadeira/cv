/* cancao-verdadeira-plugin/assets/js/admin.js
   Gerado em: 2025-06-01 00:00:00
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   JavaScript do painel administrativo: animação de KPIs, ações AJAX
   (recalcular ranking, limpar cache), tooltips e feedback visual.
*/

(function ($) {
    'use strict';

    // ── Animação dos KPI cards ────────────────────────────────────
    function animateKPI() {
        $('.cv-kpi-value').each(function () {
            var $el     = $(this);
            var target  = parseInt($el.data('target'), 10) || 0;
            var current = 0;
            var step    = Math.ceil(target / 60);
            if (step < 1) step = 1;

            var timer = setInterval(function () {
                current += step;
                if (current >= target) {
                    current = target;
                    clearInterval(timer);
                }
                $el.text(current.toLocaleString('pt-BR'));
            }, 16);
        });
    }

    // ── Recalcular ranking ────────────────────────────────────────
    $(document).on('click', '#cv-btn-recalculate', function () {
        var $btn = $(this);
        var $msg = $('#cv-action-message');

        $btn.prop('disabled', true).text('⏳ Calculando...');

        $.ajax({
            url:    cvAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'cv_recalculate_ranking',
                nonce:  cvAdmin.nonce,
            },
            success: function (res) {
                if (res.success) {
                    $msg.text('✅ ' + res.data.message + ' — ' + res.data.time).show();
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    $msg.css({ background: '#2e1a1a', borderColor: '#6a2d2d', color: '#ce7f7f' })
                        .text('❌ Erro: ' + (res.data && res.data.message)).show();
                }
            },
            error: function () {
                $msg.css({ background: '#2e1a1a', borderColor: '#6a2d2d', color: '#ce7f7f' })
                    .text('❌ Erro de conexão.').show();
            },
            complete: function () {
                $btn.prop('disabled', false).text('🔄 Recalcular Ranking');
            },
        });
    });

    // ── Limpar cache ──────────────────────────────────────────────
    $(document).on('click', '#cv-btn-clear-cache', function () {
        var $btn = $(this);
        var $msg = $('#cv-action-message');

        $btn.prop('disabled', true).text('⏳ Limpando...');

        $.ajax({
            url:    cvAdmin.ajaxUrl,
            method: 'POST',
            data: {
                action: 'cv_clear_cache',
                nonce:  cvAdmin.nonce,
            },
            success: function (res) {
                if (res.success) {
                    $msg.text('✅ ' + res.data.message).show();
                    setTimeout(function () { $msg.fadeOut(); }, 3000);
                }
            },
            complete: function () {
                $btn.prop('disabled', false).text('🗑 Limpar Cache');
            },
        });
    });

    // ── Init ──────────────────────────────────────────────────────
    $(function () {
        animateKPI();
    });

}(jQuery));
