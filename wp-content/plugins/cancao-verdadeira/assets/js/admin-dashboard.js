/*
 * cancao-verdadeira/assets/js/admin-dashboard.js
 * Dashboard do plugin: anima os KPIs, desenha o gráfico de plays dos
 * últimos 30 dias (Chart.js) e liga os botões da Central de Ações
 * (recalcular ranking, limpar cache) via AJAX.
 * v2.41.0: saiu o botão "Recriar Páginas" (rotina de alto risco).
 * Os dados do gráfico vêm de window.cvDash = { labels, values },
 * impressos por CV_Page_Dashboard::render().
 * v2.35.0: saiu do <script> que ficava dentro de render().
 */
(function(){
    var CFG = window.cvDash || {};

    // Animação dos KPI values
    document.querySelectorAll('.cv-kpi2-value[data-target]').forEach(function(el){
        var target = parseInt(el.dataset.target, 10) || 0;
        var step   = Math.ceil(target / 40);
        var cur    = 0;
        var timer  = setInterval(function(){
            cur = Math.min(cur + step, target);
            el.textContent = cur.toLocaleString('pt-BR');
            if (cur >= target) clearInterval(timer);
        }, 30);
    });

    // Gráfico de plays
    var ctx = document.getElementById('cv-plays-chart');
    if (ctx) {
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: CFG.labels || [],
                datasets: [{
                    label: 'Plays',
                    data:  CFG.values || [],
                    borderColor: '#B8700C',
                    backgroundColor: 'rgba(242,165,26,0.1)',
                    borderWidth: 2,
                    pointRadius: 2,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#B8700C',
                    fill: true,
                    tension: 0.4,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                aspectRatio: 2.5,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#FFFFFF',
                        borderColor: '#F3E6D3',
                        borderWidth: 1,
                        titleColor: '#B8700C',
                        bodyColor: '#C9A27E',
                        callbacks: {
                            label: function(c){ return ' ' + c.parsed.y + ' plays'; }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(123,58,34,0.04)' },
                        ticks: { color: '#F3E6D3', font: { size: 10 }, maxTicksLimit: 10 }
                    },
                    y: {
                        grid: { color: 'rgba(123,58,34,0.04)' },
                        ticks: { color: '#F3E6D3', font: { size: 10 }, precision: 0 },
                        beginAtZero: true
                    }
                }
            }
        });
    }
})();

// Ações rápidas
jQuery(function($){
    var nonce  = (typeof cvAdmin !== 'undefined') ? cvAdmin.nonce  : '';
    var ajaxUrl= (typeof cvAdmin !== 'undefined') ? cvAdmin.ajaxUrl : ajaxurl;

    function showMsg(txt, ok) {
        $('#cv-action-message')
            .removeClass('cv-notice-success cv-notice-error')
            .addClass('cv-notice ' + (ok ? 'cv-notice-success' : 'cv-notice-error'))
            .text(txt).show();
        setTimeout(function(){ $('#cv-action-message').fadeOut(); }, 4000);
    }

    $('#cv-btn-recalculate').on('click', function(){
        var $b = $(this).prop('disabled',true).text('Recalculando...');
        $.post(ajaxUrl,{ action:'cv_recalculate_ranking', nonce:nonce }, function(r){
            showMsg(r.success ? '✅ Ranking recalculado!' : '❌ Erro.', r.success);
            $b.prop('disabled',false).text('🔄 Recalcular Ranking');
        });
    });
    $('#cv-btn-clear-cache').on('click', function(){
        var $b = $(this).prop('disabled',true).text('Limpando...');
        $.post(ajaxUrl,{ action:'cv_clear_cache', nonce:nonce }, function(r){
            showMsg(r.success ? '✅ Cache limpo!' : '❌ Erro.', r.success);
            $b.prop('disabled',false).text('🗑 Limpar Cache');
        });
    });
});
