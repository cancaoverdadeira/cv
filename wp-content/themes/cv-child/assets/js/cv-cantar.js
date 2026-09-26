/*
 * cancao-verdadeira-child/assets/js/cv-cantar.js
 * Projeto: Canção Verdadeira — modo "🎤 Cantar junto" da página da música.
 * v15.24.0: abre a letra em tela cheia, dividida em estrofes (separadas por
 * linha em branco), com a estrofe atual destacada e as outras apagadas.
 * Avança pelos botões grandes, tocando numa estrofe, arrastando o dedo para
 * o lado ou pelas setas do teclado. Tem A− / A+ próprio (guardado no
 * aparelho), "▶ Tocar a música" (vídeo da página por postMessage, ou o MP3
 * pelo CV_Player) e pede para a tela do celular não apagar (Wake Lock).
 * Carregado só na página da música (functions.php); precisa do botão
 * #cv-cantar-btn e da letra em #cv-letra-conteudo (template-parts/musica/letra.php).
 * v15.30.0: abre sozinho quando o endereço termina em #cantar-junto; letra sem
 * separação de estrofes (um verso por parágrafo) é agrupada de 4 em 4 versos.
 */
jQuery(function($){
    var $botao = $('#cv-cantar-btn');
    var $fonte = $('#cv-letra-conteudo [itemprop="text"]');
    if (!$botao.length || !$fonte.length) { return; }

    var CHAVE = 'cv_cantar_tamanho', MIN = 24, MAX = 60, PASSO = 4;
    var $tela = null, estrofes = [], atual = 0, tam = 34, travaTela = null, tocando = false, iniciouMp3 = false;

    // ── Letra → estrofes ─────────────────────────────────────────
    // DOMParser só lê o texto (não executa nada do HTML da letra).
    function lerEstrofes() {
        var html = $fonte.html()
            .replace(/<br\s*\/?>/gi, '\n')
            .replace(/<\/p>\s*/gi, '\n\n');
        var texto = new DOMParser().parseFromString(html, 'text/html').body.textContent || '';
        texto = texto.replace(/\r/g, '').replace(/[ \t]+\n/g, '\n');
        var lista = texto.split(/\n\s*\n+/)
            .map(function(s){ return s.replace(/^\s+|\s+$/g, ''); })
            .filter(function(s){ return s && !/^letra( completa)?\b/i.test(s); });
        // v15.30.0: letra colada do Word/LibreOffice com UM verso por parágrafo e
        // nenhuma linha em branco (ex.: 76 "estrofes" de 1 linha). Sem como saber
        // onde cada estrofe termina, agrupa os versos de 4 em 4.
        var umaLinha = lista.filter(function(s){ return s.indexOf('\n') === -1; }).length;
        if (lista.length >= 12 && umaLinha / lista.length > 0.8) {
            var versos = lista.join('\n').split('\n'), grupos = [];
            for (var i = 0; i < versos.length; i += 4) { grupos.push(versos.slice(i, i + 4).join('\n')); }
            return grupos;
        }
        return lista;
    }

    // ── Monta a tela (uma vez só) ────────────────────────────────
    function montar() {
        var titulo = $.trim($('.cv-single-musica h1').first().text()) || document.title;
        $tela = $(
            '<div class="cv-cantar" role="dialog" aria-modal="true" aria-label="Cantar junto" hidden>' +
                '<div class="cv-cantar-topo">' +
                    '<div class="cv-cantar-titulo">🎤 <span></span></div>' +
                    '<div class="cv-cantar-contador" aria-live="polite"></div>' +
                    '<div class="cv-cantar-ferramentas">' +
                        '<button type="button" data-acao="menor" aria-label="Diminuir a letra">A−</button>' +
                        '<button type="button" data-acao="maior" aria-label="Aumentar a letra" class="cv-cantar-maior">A+</button>' +
                        '<button type="button" data-acao="sair" class="cv-cantar-sair">✕ Sair</button>' +
                    '</div>' +
                '</div>' +
                '<div class="cv-cantar-letra"></div>' +
                '<div class="cv-cantar-base">' +
                    '<button type="button" data-acao="anterior" class="cv-cantar-nav">◀ Anterior</button>' +
                    '<button type="button" data-acao="tocar" class="cv-cantar-tocar">▶ Tocar a música</button>' +
                    '<button type="button" data-acao="proxima" class="cv-cantar-nav cv-cantar-proxima">Próxima ▶</button>' +
                '</div>' +
                '<p class="cv-cantar-dica">Toque numa estrofe para ir até ela · no computador, use as setas do teclado</p>' +
            '</div>'
        );
        $tela.find('.cv-cantar-titulo span').text(titulo);
        var $letra = $tela.find('.cv-cantar-letra');
        $.each(estrofes, function(i, texto){
            $('<div class="cv-cantar-estrofe" tabindex="-1"></div>').attr('data-i', i).text(texto).appendTo($letra);
        });
        if (!temVideo() && !temMp3()) { $tela.find('[data-acao="tocar"]').remove(); }
        $('body').append($tela);

        $tela.on('click', '[data-acao]', function(){
            var acao = $(this).data('acao');
            if (acao === 'sair')     { fechar(); }
            if (acao === 'anterior') { ir(atual - 1); }
            if (acao === 'proxima')  { ir(atual + 1); }
            if (acao === 'menor')    { tam -= PASSO; aplicarTamanho(); }
            if (acao === 'maior')    { tam += PASSO; aplicarTamanho(); }
            if (acao === 'tocar')    { tocarPausar(); }
        });
        $tela.on('click', '.cv-cantar-estrofe', function(){ ir(parseInt($(this).attr('data-i'), 10)); });

        // Arrastar o dedo para o lado: próxima / anterior
        var x0 = null, y0 = null;
        $letra.on('touchstart', function(e){ var t = e.originalEvent.touches[0]; x0 = t.clientX; y0 = t.clientY; });
        $letra.on('touchend', function(e){
            if (x0 === null) { return; }
            var t = e.originalEvent.changedTouches[0], dx = t.clientX - x0, dy = t.clientY - y0;
            if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy) * 1.5) { ir(atual + (dx < 0 ? 1 : -1)); }
            x0 = y0 = null;
        });
    }

    // ── Estrofe atual ────────────────────────────────────────────
    function ir(i) {
        atual = Math.max(0, Math.min(estrofes.length - 1, i));
        var $todas = $tela.find('.cv-cantar-estrofe');
        $todas.removeClass('is-atual is-passada').attr('aria-current', null);
        $todas.slice(0, atual).addClass('is-passada');
        var el = $todas.eq(atual).addClass('is-atual').attr('aria-current', 'true')[0];
        var suave = !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        if (el && el.scrollIntoView) { el.scrollIntoView({ block: 'center', behavior: suave ? 'smooth' : 'auto' }); }
        $tela.find('.cv-cantar-contador').text('Estrofe ' + (atual + 1) + ' de ' + estrofes.length);
        $tela.find('[data-acao="anterior"]').prop('disabled', atual === 0);
        $tela.find('[data-acao="proxima"]').prop('disabled', atual === estrofes.length - 1);
    }

    function aplicarTamanho() {
        tam = Math.max(MIN, Math.min(MAX, tam));
        $tela[0].style.setProperty('--cantar-tam', tam + 'px');
        $tela.find('[data-acao="menor"]').prop('disabled', tam <= MIN);
        $tela.find('[data-acao="maior"]').prop('disabled', tam >= MAX);
        try { window.localStorage.setItem(CHAVE, String(tam)); } catch (e) {}
        ir(atual); // recentraliza a estrofe depois de mudar o tamanho
    }

    // ── Tocar / pausar a música da página ────────────────────────
    function temVideo() { return $('#cv-musica-iframe').length > 0; }
    function temMp3()   { return !!($('#cv-play-btn').data('audioUrl') && window.CV_Player); }
    function tocarPausar() {
        tocando = !tocando;
        if (temMp3()) {
            if (!iniciouMp3) { $('#cv-play-btn').trigger('click'); iniciouMp3 = true; }
            else if (window.CV_Player.togglePlay) { window.CV_Player.togglePlay(); }
        } else if (temVideo()) {
            var ifr = $('#cv-musica-iframe')[0];
            ifr.contentWindow.postMessage(JSON.stringify({ event: 'command', func: tocando ? 'playVideo' : 'pauseVideo', args: [] }), '*');
        }
        $tela.find('[data-acao="tocar"]').text(tocando ? '⏸ Pausar' : '▶ Tocar a música');
    }

    // ── Abrir / fechar ───────────────────────────────────────────
    function abrir() {
        estrofes = lerEstrofes();
        if (!estrofes.length) { return; }
        if (!$tela) { montar(); }
        var padrao = window.innerWidth < 600 ? 28 : 34; // celular começa um pouco menor
        tam = padrao;
        try { tam = parseInt(window.localStorage.getItem(CHAVE), 10) || padrao; } catch (e) {}
        $tela.prop('hidden', false);
        $('html').addClass('cv-cantar-aberto');
        var el = $tela[0];
        try {
            var fs = el.requestFullscreen || el.webkitRequestFullscreen;
            if (fs) { var p = fs.call(el); if (p && p.catch) { p.catch(function(){}); } }
        } catch (e) {}
        try {
            if (navigator.wakeLock && navigator.wakeLock.request) {
                navigator.wakeLock.request('screen').then(function(t){ travaTela = t; }).catch(function(){});
            }
        } catch (e) {}
        aplicarTamanho();
        ir(0);
        $tela.find('.cv-cantar-proxima').trigger('focus');
    }

    function fechar() {
        if (!$tela || $tela.prop('hidden')) { return; }
        $tela.prop('hidden', true);
        $('html').removeClass('cv-cantar-aberto');
        try {
            var sai = document.exitFullscreen || document.webkitExitFullscreen;
            if ((document.fullscreenElement || document.webkitFullscreenElement) && sai) {
                var p = sai.call(document); if (p && p.catch) { p.catch(function(){}); }
            }
        } catch (e) {}
        if (travaTela) { try { travaTela.release(); } catch (e) {} travaTela = null; }
        $botao.trigger('focus');
    }

    $botao.on('click', abrir);

    // v15.30.0: o atalho "🎤 Cantar junto" do "✨ Novidades" (home) chega com
    // #cantar-junto no endereço e já abre a tela (sem tela cheia do navegador,
    // que só abre com um toque da pessoa; o botão ✕ Sair fecha normalmente).
    if (window.location.hash === '#cantar-junto') { setTimeout(abrir, 300); }
    // v15.31.0: vindo da janela "✨ Novidades" estando já nesta música
    window.addEventListener('hashchange', function(){ if (window.location.hash === '#cantar-junto') { abrir(); } });

    // Teclado: setas, espaço, Home/End, Esc e o Tab preso dentro da tela
    $(document).on('keydown', function(e){
        if (!$tela || $tela.prop('hidden')) { return; }
        var k = e.key;
        if (k === 'Escape') { e.preventDefault(); fechar(); return; }
        if (k === 'ArrowRight' || k === 'ArrowDown' || k === 'PageDown' || (k === ' ' && !$(e.target).is('button'))) { e.preventDefault(); ir(atual + 1); return; }
        if (k === 'ArrowLeft' || k === 'ArrowUp' || k === 'PageUp') { e.preventDefault(); ir(atual - 1); return; }
        if (k === 'Home') { e.preventDefault(); ir(0); return; }
        if (k === 'End')  { e.preventDefault(); ir(estrofes.length - 1); return; }
        if (k === 'Tab') {
            var $f = $tela.find('button:not(:disabled)');
            if (!$f.length) { return; }
            var i = $f.index(document.activeElement);
            if (e.shiftKey && i <= 0)              { e.preventDefault(); $f.last().trigger('focus'); }
            else if (!e.shiftKey && i === $f.length - 1) { e.preventDefault(); $f.first().trigger('focus'); }
        }
    });

    // Se a tela do celular apagar e voltar, pede de novo para não apagar
    document.addEventListener('visibilitychange', function(){
        if (document.visibilityState === 'visible' && $tela && !$tela.prop('hidden') && navigator.wakeLock) {
            navigator.wakeLock.request('screen').then(function(t){ travaTela = t; }).catch(function(){});
        }
    });
});
