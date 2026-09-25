/*
 * cancao-verdadeira-child/assets/js/cv-oferecer.js
 * "💌 Ofereça esta música" (v15.22.0): abre a janela, monta o link com a
 * dedicatória (?para=&de=&msg=), DESENHA O CARTÃO (canvas 1080×1080: capa,
 * título, Para, recado, De e o endereço do site) e envia pelo WhatsApp —
 * no celular com a imagem (Web Share), no computador baixando o cartão e
 * abrindo o WhatsApp com o texto. Também "▶ Ouvir agora" da faixa de
 * dedicatória. Carregado só na página da música (functions.php).
 */
jQuery(function ($) {
    var dlg = document.getElementById('cv-janela-oferecer');

    // Faixa de dedicatória: "Ouvir agora" usa o botão principal da música
    $(document).on('click', '.cv-dedicatoria-ouvir', function () {
        var $p = $('#cv-play-btn');
        if ($p.length) { $p.trigger('click'); }
    });

    if (!dlg) { return; }
    var $d = $(dlg), $form = $d.find('.cv-oferecer-form'), $pronto = $d.find('.cv-oferecer-pronto');
    var dados = { titulo: $d.data('titulo'), autor: $d.data('autor'), capa: $d.data('capa'), url: $d.data('url'), site: $d.data('site') };
    var cartao = null, link = '', texto = '';

    var ESTILOS = {
        // A moldura é sempre clara: os textos são escuros em todos os estilos
        amanhecer: { fundo: ['#FFE3A3', '#F2A51A', '#B8541C'], destaque: '#7B3A22', claro: '#FFF8EC' },
        cafe:      { fundo: ['#5E2A17', '#3B2418', '#1E120B'], destaque: '#5E2A17', claro: '#F3E6D3' },
        rosa:      { fundo: ['#FFE4EC', '#F4A7B9', '#B5475F'], destaque: '#8E2440', claro: '#FFF5F8' }
    };

    function abrir() {
        $form.prop('hidden', false); $pronto.prop('hidden', true);
        $form.find('.cv-janela-msg').removeClass('is-ok is-erro').text('');
        if (typeof dlg.showModal === 'function') { dlg.showModal(); } else { dlg.setAttribute('open', ''); }
        $form.find('[name="para"]').trigger('focus');
    }
    $(document).on('click', '.cv-oferecer-abrir', abrir);
    $d.on('click', '.cv-oferecer-sugestao', function () { $form.find('[name="msg"]').val($(this).text()).trigger('focus'); });
    $d.on('click', '.cv-oferecer-refazer', function () { $pronto.prop('hidden', true); $form.prop('hidden', false); });

    // ── Desenho do cartão ────────────────────────────────────────
    function quebrar(ctx, txt, largura) {
        var palavras = String(txt).split(/\s+/), linhas = [], linha = '';
        palavras.forEach(function (p) {
            var teste = linha ? linha + ' ' + p : p;
            if (ctx.measureText(teste).width > largura && linha) { linhas.push(linha); linha = p; } else { linha = teste; }
        });
        if (linha) { linhas.push(linha); }
        return linhas;
    }
    function carregarImagem(src) {
        return new Promise(function (ok) {
            if (!src) { ok(null); return; }
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () { ok(img); };
            img.onerror = function () { ok(null); };
            img.src = src;
        });
    }
    function desenhar(v, img) {
        var W = 1080, c = document.createElement('canvas'), ctx = c.getContext('2d'), e = ESTILOS[v.estilo] || ESTILOS.amanhecer;
        c.width = W; c.height = W;
        var g = ctx.createLinearGradient(0, 0, W, W);
        g.addColorStop(0, e.fundo[0]); g.addColorStop(0.55, e.fundo[1]); g.addColorStop(1, e.fundo[2]);
        ctx.fillStyle = g; ctx.fillRect(0, 0, W, W);

        // Moldura clara
        ctx.fillStyle = e.claro; ctx.globalAlpha = 0.92;
        ctx.beginPath(); if (ctx.roundRect) { ctx.roundRect(60, 60, W - 120, W - 120, 40); } else { ctx.rect(60, 60, W - 120, W - 120); }
        ctx.fill(); ctx.globalAlpha = 1;

        var y = 120;
        ctx.textAlign = 'center';
        ctx.fillStyle = e.destaque;
        ctx.font = '600 38px "EB Garamond", Georgia, serif';
        ctx.fillText('💌 Uma música para você', W / 2, y + 20);
        y += 70;

        // Capa
        if (img) {
            var tam = 300, x = (W - tam) / 2;
            ctx.save();
            ctx.beginPath(); if (ctx.roundRect) { ctx.roundRect(x, y, tam, tam, 24); } else { ctx.rect(x, y, tam, tam); }
            ctx.clip();
            var r = Math.max(tam / img.width, tam / img.height), iw = img.width * r, ih = img.height * r;
            ctx.drawImage(img, x + (tam - iw) / 2, y + (tam - ih) / 2, iw, ih);
            ctx.restore();
            y += tam + 60;
        } else { y += 40; }

        ctx.fillStyle = e.destaque;
        ctx.font = '700 58px Oswald, "Arial Narrow", sans-serif';
        quebrar(ctx, '“' + dados.titulo + '”', W - 220).slice(0, 2).forEach(function (l) { ctx.fillText(l, W / 2, y); y += 66; });
        if (dados.autor) {
            ctx.font = 'italic 32px "EB Garamond", Georgia, serif';
            ctx.fillStyle = '#6B4C3B';
            ctx.fillText(dados.autor, W / 2, y); y += 50;
        }
        y += 16;
        ctx.fillStyle = '#3B2418';
        ctx.font = '600 44px "EB Garamond", Georgia, serif';
        ctx.fillText('Para ' + v.para, W / 2, y); y += 58;
        if (v.msg) {
            ctx.font = 'italic 36px "EB Garamond", Georgia, serif';
            quebrar(ctx, v.msg, W - 260).slice(0, 4).forEach(function (l) { ctx.fillText(l, W / 2, y); y += 46; });
            y += 6;
        }
        ctx.font = '600 36px "EB Garamond", Georgia, serif';
        ctx.fillStyle = e.destaque;
        ctx.fillText('Com carinho, ' + v.de, W / 2, Math.min(y + 10, W - 150));

        // Rodapé
        ctx.font = '700 30px Oswald, "Arial Narrow", sans-serif';
        ctx.fillStyle = '#7B3A22';
        ctx.fillText('♪ Canção Verdadeira · ' + dados.site, W / 2, W - 95);
        return c;
    }

    // ── Criar ────────────────────────────────────────────────────
    $form.on('submit', function (ev) {
        ev.preventDefault();
        var v = {
            para: $.trim($form.find('[name="para"]').val()),
            de: $.trim($form.find('[name="de"]').val()),
            msg: $.trim($form.find('[name="msg"]').val()),
            estilo: $form.find('[name="estilo"]:checked').val()
        };
        var $m = $form.find('.cv-janela-msg').removeClass('is-ok is-erro');
        if (!v.para || !v.de) { $m.addClass('is-erro').text('Preencha para quem é e de quem é.'); return; }
        var sep = dados.url.indexOf('?') === -1 ? '?' : '&';
        link = dados.url + sep + $.param({ para: v.para, de: v.de, msg: v.msg });
        texto = '💌 ' + v.para + ', ofereço esta música para você: "' + dados.titulo + '".'
              + (v.msg ? '\n' + v.msg : '') + '\n— ' + v.de + '\n\n🎵 Ouça aqui: ' + link;

        var $b = $form.find('.cv-janela-enviar').prop('disabled', true).text('Criando...');
        var fontes = (document.fonts && document.fonts.ready) ? document.fonts.ready : Promise.resolve();
        fontes.then(function () { return carregarImagem(dados.capa); }).then(function (img) {
            var c = desenhar(v, img);
            try {
                $d.find('.cv-oferecer-previa').attr('src', c.toDataURL('image/jpeg', 0.9));
                cartao = c;
            } catch (e) {
                // Capa de outro site sem permissão: desenha sem a capa
                c = desenhar(v, null); cartao = c;
                $d.find('.cv-oferecer-previa').attr('src', c.toDataURL('image/jpeg', 0.9));
            }
            $form.prop('hidden', true); $pronto.prop('hidden', false);
            dlg.scrollTop = 0;
        }).then(function () { $b.prop('disabled', false).text('🎨 Criar o cartão'); });
    });

    // ── Enviar, baixar, copiar ───────────────────────────────────
    function arquivo(cb) {
        if (!cartao) { cb(null); return; }
        cartao.toBlob(function (b) {
            cb(b ? new File([b], 'cartao-cancao-verdadeira.jpg', { type: 'image/jpeg' }) : null);
        }, 'image/jpeg', 0.9);
    }
    function baixar() {
        var a = document.createElement('a');
        a.href = $d.find('.cv-oferecer-previa').attr('src');
        a.download = 'cartao-cancao-verdadeira.jpg';
        document.body.appendChild(a); a.click(); a.remove();
    }
    $d.on('click', '.cv-oferecer-baixar', baixar);
    $d.on('click', '.cv-oferecer-whats', function () {
        arquivo(function (f) {
            if (f && navigator.canShare && navigator.canShare({ files: [f] })) {
                navigator.share({ files: [f], text: texto }).catch(function () {});
                return;
            }
            baixar();
            window.open('https://wa.me/?text=' + encodeURIComponent(texto), '_blank', 'noopener');
        });
    });
    $d.on('click', '.cv-oferecer-copiar', function () {
        var $b = $(this);
        var feito = function () { var t = $b.text(); $b.text('✅ Link copiado!'); setTimeout(function () { $b.text(t); }, 2500); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(link).then(feito, function () { prompt('Copie o link:', link); }); }
        else { prompt('Copie o link:', link); }
    });
});
