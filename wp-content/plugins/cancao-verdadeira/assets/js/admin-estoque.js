/*
 * cancao-verdadeira/assets/js/admin-estoque.js
 * Tela "📦 Estoque e Pedidos" do painel (v2.47.0): formulário de itens
 * (com a biblioteca de mídia), lançamentos de estoque, mudança de situação
 * dos pedidos, e-mail do alerta e gráficos do Painel (Chart.js, dados no
 * JSON #cv-est-dados). nonce e ajaxUrl vêm de window.cvEst, impressos por
 * CV_Estoque_Admin::enqueue().
 */
jQuery(function ($) {
    var cfg   = window.cvEst || {};
    var nonce = cfg.nonce || '';
    var ajax  = cfg.ajaxUrl || '';

    function msg(texto, ok) {
        var $m = $('#cv-est-msg');
        $m.text(texto).css({
            background: ok ? '#E3F4E8' : '#FBE9E7',
            border: '1px solid ' + (ok ? '#1C7C44' : '#C0392B'),
            color: ok ? '#1C7C44' : '#C0392B',
            padding: '10px 14px', borderRadius: '8px', marginBottom: '14px'
        }).show();
        $('html,body').animate({ scrollTop: 0 }, 200);
        if (ok) { setTimeout(function () { $m.fadeOut(); }, 4000); }
    }

    function enviar(dados, $btn, recarregar) {
        var rotulo = $btn ? $btn.text() : '';
        if ($btn) { $btn.prop('disabled', true).text('Aguarde...'); }
        dados.nonce = nonce;
        $.post(ajax, dados, function (r) {
            var texto = (r && r.data && r.data.message) || (r && r.success ? 'Feito.' : 'Não foi possível concluir.');
            msg((r && r.success ? '✅ ' : '❌ ') + texto, !!(r && r.success));
            if (r && r.success && recarregar) { setTimeout(function () { location.reload(); }, 900); }
            else if ($btn) { $btn.prop('disabled', false).text(rotulo); }
        }).fail(function () {
            msg('❌ Falha de conexão. Tente de novo.', false);
            if ($btn) { $btn.prop('disabled', false).text(rotulo); }
        });
    }

    // ── Aba Itens ────────────────────────────────────────────────
    function ajustarTipo() {
        var tipo  = $('#cv-ei-tipo').val();
        var novo  = $('#cv-ei-id').val() === '0';
        $('.cv-ei-so-fisico').toggle(tipo !== 'ebook');
        $('.cv-ei-so-ebook').toggle(tipo === 'ebook');
        $('#cv-ei-iniciais').toggle(novo && tipo !== 'ebook');
        $('#cv-ei-ini-unico').css('display', tipo === 'camiseta' ? 'none' : 'flex');
        $('#cv-ei-ini-tamanhos').css('display', tipo === 'camiseta' ? 'flex' : 'none');
    }
    function limparForm() {
        $('#cv-ei-id').val('0');
        $('#cv-ei-nome, #cv-ei-preco, #cv-ei-desc, #cv-ei-imagem').val('');
        $('#cv-ei-tipo').val('caneca').prop('disabled', false);
        $('#cv-ei-minimo').val('10');
        $('#cv-ei-ordem').val('0');
        $('#cv-ei-ativo').val('1');
        $('.cv-ei-ini').val('0');
        $('#cv-est-form-titulo').text('Novo item');
        ajustarTipo();
    }
    $('#cv-ei-tipo').on('change', ajustarTipo);
    if ($('#cv-ei-tipo').length) { limparForm(); }

    $('#cv-est-novo').on('click', function () { limparForm(); $('#cv-est-form').slideDown(180); });
    $('#cv-ei-cancelar').on('click', function () { $('#cv-est-form').slideUp(180); });

    $('#cv-ei-biblioteca').on('click', function () {
        var frame = wp.media({ title: 'Imagem do item', button: { text: 'Usar' }, multiple: false, library: { type: 'image' } });
        frame.on('select', function () { $('#cv-ei-imagem').val(frame.state().get('selection').first().toJSON().url); });
        frame.open();
    });

    $(document).on('click', '.cv-ei-editar', function () {
        var d = $(this).data('item');
        $('#cv-ei-id').val(d.id);
        $('#cv-ei-nome').val(d.nome);
        $('#cv-ei-tipo').val(d.tipo).prop('disabled', true); // o tipo não muda depois de criado
        $('#cv-ei-preco').val(parseFloat(d.preco).toFixed(2).replace('.', ','));
        $('#cv-ei-minimo').val(d.estoque_minimo);
        $('#cv-ei-desc').val(d.descricao);
        $('#cv-ei-imagem').val(d.imagem_url);
        $('#cv-ei-ordem').val(d.ordem);
        $('#cv-ei-ativo').val(d.ativo);
        $('#cv-est-form-titulo').text('Editar: ' + d.nome);
        ajustarTipo();
        $('#cv-est-form').slideDown(180);
        $('html,body').animate({ scrollTop: $('#cv-est-form').offset().top - 40 }, 300);
    });

    $('#cv-ei-salvar').on('click', function () {
        var dados = {
            action: 'cv_est_salvar_item',
            id: $('#cv-ei-id').val(),
            nome: $('#cv-ei-nome').val(),
            tipo: $('#cv-ei-tipo').val(),
            preco: $('#cv-ei-preco').val(),
            estoque_minimo: $('#cv-ei-minimo').val(),
            descricao: $('#cv-ei-desc').val(),
            imagem_url: $('#cv-ei-imagem').val(),
            ordem: $('#cv-ei-ordem').val(),
            ativo: $('#cv-ei-ativo').val()
        };
        if (dados.id === '0') {
            $('.cv-ei-ini').each(function () { dados[this.name] = this.value; });
        }
        enviar(dados, $(this), true);
    });

    $(document).on('click', '.cv-ei-excluir', function () {
        if (!confirm('Excluir o item "' + $(this).data('nome') + '" e todo o histórico de estoque dele?\n\nItens com pedidos não podem ser excluídos — nesse caso, desative.')) { return; }
        enviar({ action: 'cv_est_excluir_item', id: $(this).data('id') }, $(this), true);
    });

    // ── Aba Estoque ──────────────────────────────────────────────
    function ajustarOperacao() {
        var op = $('input[name="cv-em-op"]:checked').val();
        $('.cv-em-so-entrada').toggle(op === 'entrada');
        $('.cv-em-so-saida').toggle(op === 'saida');
        $('#cv-em-qtd-rotulo').text({ entrada: 'Quantidade comprada', saida: 'Quantidade que saiu', ajuste: 'Quantas peças você contou' }[op]);
    }
    $('input[name="cv-em-op"]').on('change', ajustarOperacao);
    if ($('#cv-em-lancar').length) { ajustarOperacao(); }

    $('#cv-em-lancar').on('click', function () {
        var op = $('input[name="cv-em-op"]:checked').val();
        var qtd = $('#cv-em-qtd').val();
        if (qtd === '' || (op !== 'ajuste' && parseInt(qtd, 10) <= 0)) { msg('❌ Informe a quantidade.', false); return; }
        if (op === 'ajuste' && !confirm('O saldo de "' + $('#cv-em-variacao option:selected').text() + '" vai passar a ser ' + qtd + '. Confirma?')) { return; }
        enviar({
            action: 'cv_est_movimento',
            variacao_id: $('#cv-em-variacao').val(),
            operacao: op,
            quantidade: qtd,
            custo: $('#cv-em-custo').val(),
            motivo: $('#cv-em-motivo').val(),
            obs: $('#cv-em-obs').val()
        }, $(this), true);
    });

    // ── Aba Pedidos ──────────────────────────────────────────────
    $(document).on('click', '.cv-ep-status', function () {
        var st = $(this).data('status');
        var avisos = {
            confirmado: 'Confirmar o pedido #' + $(this).data('id') + '? As peças saem do estoque e o cliente recebe um e-mail.',
            entregue: 'Marcar o pedido #' + $(this).data('id') + ' como entregue?',
            cancelado: 'Cancelar o pedido #' + $(this).data('id') + '? Se já estava confirmado, as peças voltam ao estoque.'
        };
        if (!confirm(avisos[st] || 'Confirma?')) { return; }
        enviar({ action: 'cv_est_status_pedido', id: $(this).data('id'), status: st }, $(this), true);
    });

    // ── Painel: e-mail do alerta ─────────────────────────────────
    $('#cv-est-email-salvar').on('click', function () {
        enviar({ action: 'cv_est_email', email: $('#cv-est-email').val() }, $(this), false);
    });

    // ── Painel: gráficos ─────────────────────────────────────────
    var $dados = $('#cv-est-dados');
    if (!$dados.length || typeof Chart === 'undefined') { return; }
    var d;
    try { d = JSON.parse($dados.text()); } catch (e) { return; }
    var cores = ['#F2A51A', '#7B3A22', '#1C7C44', '#2871BE', '#C0392B', '#8A6A55'];
    Chart.defaults.font.family = 'inherit';
    Chart.defaults.color = '#6B4C3B';

    if (document.getElementById('cv-est-graf-retirados')) {
        new Chart(document.getElementById('cv-est-graf-retirados'), {
            type: 'bar',
            data: { labels: d.retirados.nomes, datasets: [{ label: 'Peças retiradas', data: d.retirados.qtd, backgroundColor: '#F2A51A', borderRadius: 6 }] },
            options: { indexAxis: 'y', maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }
    if (document.getElementById('cv-est-graf-meses')) {
        new Chart(document.getElementById('cv-est-graf-meses'), {
            type: 'bar',
            data: {
                labels: d.meses.meses,
                datasets: d.meses.series.map(function (s, i) { return { label: s.rotulo, data: s.dados, backgroundColor: cores[i % cores.length], borderRadius: 4 }; })
            },
            options: { maintainAspectRatio: false, scales: { x: { stacked: true }, y: { stacked: true, beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }
    if (document.getElementById('cv-est-graf-tamanhos')) {
        new Chart(document.getElementById('cv-est-graf-tamanhos'), {
            type: 'bar',
            data: { labels: d.tamanhos.nomes, datasets: [{ label: 'Unidades pedidas', data: d.tamanhos.qtd, backgroundColor: '#7B3A22', borderRadius: 6 }] },
            options: { maintainAspectRatio: false, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true, ticks: { precision: 0 } } } }
        });
    }
});
