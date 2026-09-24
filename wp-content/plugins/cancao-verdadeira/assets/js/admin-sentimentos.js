/*
 * cancao-verdadeira/assets/js/admin-sentimentos.js
 * Painel Executivo de Sentimentos: anima os KPIs e barras, desenha os
 * gráficos (Chart.js) e abre o painel de detalhe com as músicas via AJAX.
 * Os dados vêm de window.cvSent = { nonce, ajaxUrl, stats },
 * impressos por CV_Admin_Sentimentos::render_page().
 * v2.34.0: saiu do <script> que ficava dentro de render_page().
 */
(function() {
    var CFG    = window.cvSent || {};
    var NONCE  = CFG.nonce || '';
    var AJAX   = CFG.ajaxUrl || '';
    var statsData = CFG.stats || [];

    // ── Animação KPIs ─────────────────────────────────────
    document.querySelectorAll('.cv-kpi-num[data-target]').forEach(function(el) {
        var target = parseInt(el.dataset.target, 10);
        if (isNaN(target)) return;
        var start = 0, duration = 1200, startTime = null;
        function step(ts) {
            if (!startTime) startTime = ts;
            var progress = Math.min((ts - startTime) / duration, 1);
            var ease = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(ease * target).toLocaleString('pt-BR');
            if (progress < 1) requestAnimationFrame(step);
        }
        requestAnimationFrame(step);
    });

    // ── Animação barras ───────────────────────────────────
    setTimeout(function() {
        document.querySelectorAll('[data-w]').forEach(function(el) {
            el.style.width = el.dataset.w;
        });
    }, 300);

    // ── Chart.js — inicializar quando disponível ──────────
    function initCharts() {
        if (typeof Chart === 'undefined' || statsData.length === 0) return;

        var labels = statsData.map(function(s){ return s.icone + ' ' + s.nome; });
        var counts = statsData.map(function(s){ return s.count; });
        var plays  = statsData.map(function(s){ return s.plays; });
        var cores  = statsData.map(function(s){ return s.cor; });
        var coresBorder = cores.map(function(c){ return c; });

        // Pizza — distribuição
        var ctxP = document.getElementById('cv-chart-pizza');
        if (ctxP) {
            new Chart(ctxP, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: counts,
                        backgroundColor: cores.map(function(c){ return c + '99'; }),
                        borderColor: coresBorder,
                        borderWidth: 2,
                        hoverOffset: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: { color: '#C9A27E', boxWidth: 14, font: { size: 11 } }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(ctx) {
                                    var v = ctx.parsed;
                                    var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                                    var pct = total > 0 ? Math.round(v/total*100) : 0;
                                    return ' ' + v + ' músicas (' + pct + '%)';
                                }
                            }
                        }
                    },
                    cutout: '60%'
                }
            });
        }

        // Barras horizontais — plays
        var ctxB = document.getElementById('cv-chart-plays');
        if (ctxB) {
            new Chart(ctxB, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Plays totais',
                        data: plays,
                        backgroundColor: cores.map(function(c){ return c + 'bb'; }),
                        borderColor: cores,
                        borderWidth: 1,
                        borderRadius: 6
                    }]
                },
                options: {
                    indexAxis: 'y',
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(ctx){
                                    return ' ' + ctx.parsed.x.toLocaleString('pt-BR') + ' plays';
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { color: 'rgba(123,58,34,0.06)' },
                            ticks: { color: '#C9A27E', font: { size: 11 } }
                        },
                        y: {
                            grid: { display: false },
                            ticks: { color: '#3B2418', font: { size: 11 } }
                        }
                    }
                }
            });
        }
    }

    // Esperar Chart.js carregar
    if (typeof Chart !== 'undefined') {
        initCharts();
    } else {
        var checkChart = setInterval(function(){
            if (typeof Chart !== 'undefined') {
                clearInterval(checkChart);
                initCharts();
            }
        }, 200);
    }

    // ── Painel de detalhe ─────────────────────────────────
    window.cvSentAbrirDetalhe = function(card) {
        var id    = card.dataset.id;
        var nome  = card.dataset.nome;
        var icone = card.dataset.icone;
        var cor   = card.dataset.cor;

        // Marcar ativo
        document.querySelectorAll('.cv-sent-card').forEach(function(c){ c.classList.remove('active'); });
        card.classList.add('active');

        var panel = document.getElementById('cv-sent-detail-panel');
        document.getElementById('cv-det-emoji').textContent = icone;
        document.getElementById('cv-det-nome').textContent  = nome;
        document.getElementById('cv-det-sub').textContent   = 'Carregando músicas...';
        document.getElementById('cv-det-musicas').innerHTML = '<div class="cv-sent-loading">⏳ Carregando músicas...</div>';
        panel.classList.add('visible');
        panel.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        panel.style.borderColor = cor;

        // AJAX
        var fd = new FormData();
        fd.append('action', 'cv_admin_sent_musicas');
        fd.append('nonce', NONCE);
        fd.append('sentimento_id', id);

        fetch(AJAX, { method: 'POST', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(res) {
                if (!res.success || !res.data.length) {
                    document.getElementById('cv-det-sub').textContent = '0 músicas';
                    document.getElementById('cv-det-musicas').innerHTML =
                        '<div class="cv-sent-empty">Nenhuma música publicada com este sentimento ainda.</div>';
                    return;
                }
                var musicas = res.data;
                document.getElementById('cv-det-sub').textContent = musicas.length + ' música' + (musicas.length !== 1 ? 's' : '');
                var html = '';
                musicas.forEach(function(m) {
                    html += '<a href="' + m.url + '" target="_blank" class="cv-musica-item">';
                    html += '<img src="' + m.capa + '" class="cv-musica-capa" alt="">';
                    html += '<div style="min-width:0">';
                    html += '<div class="cv-musica-titulo">' + m.titulo + '</div>';
                    html += '<div class="cv-musica-stats">▶ ' + m.plays.toLocaleString('pt-BR') + '  ❤ ' + m.favs + '  ★ ' + m.rating + '</div>';
                    html += '</div></a>';
                });
                document.getElementById('cv-det-musicas').innerHTML = html;
            })
            .catch(function() {
                document.getElementById('cv-det-musicas').innerHTML =
                    '<div class="cv-sent-empty">Erro ao carregar músicas.</div>';
            });
    };

    window.cvSentFecharDetalhe = function() {
        document.getElementById('cv-sent-detail-panel').classList.remove('visible');
        document.querySelectorAll('.cv-sent-card').forEach(function(c){ c.classList.remove('active'); });
    };
})();
