/*
 * cv-migracao/assets/cvm.js
 * Projeto: Canção Verdadeira — plugin provisório da migração.
 * Roda a "Troca do endereço" tabela por tabela, em lotes, chamando o AJAX
 * "cvm_lote" (CVM_Painel::ajax_lote). Prévia: só conta e mostra exemplos.
 * Aplicar: só libera depois de uma prévia completa e com a caixa
 * "Fiz o backup" marcada; pede confirmação antes de começar.
 * Dados: cvm = { ajaxUrl, nonce, tabelas }.
 */
jQuery(function($){
    if (!window.cvm) { return; }
    var $p = $('#cvm-progresso'), fezPrevia = false, rodando = false;

    function esc(s) { return $('<div>').text(s).html(); }

    function rodar(aplicar) {
        if (rodando) { return; }
        rodando = true;
        $('#cvm-previa, #cvm-aplicar').prop('disabled', true);
        var i = 0, cursor = '', tot = { linhas: 0, trocas: 0, puladas: 0 }, exemplos = [], avisos = [], porTabela = [];
        $p.show().html('Começando…');

        function proximo() {
            if (i >= cvm.tabelas.length) { return terminar(); }
            var t = cvm.tabelas[i];
            $p.html((aplicar ? '✍️ Trocando' : '🔎 Conferindo') + ' <strong>' + esc(t) + '</strong> (' + (i + 1) + ' de ' + cvm.tabelas.length + ')…');
            $.post(cvm.ajaxUrl, { action: 'cvm_lote', nonce: cvm.nonce, tabela: t, cursor: cursor, aplicar: aplicar ? 1 : '', backup: $('#cvm-backup').is(':checked') ? 1 : '' })
                .done(function(res){
                    if (!res || !res.success) { return falhou(res && res.data ? res.data.message : 'erro desconhecido'); }
                    var r = res.data;
                    tot.linhas += r.linhas; tot.trocas += r.trocas; tot.puladas += r.puladas;
                    if (r.linhas || r.puladas) { porTabela.push(t + ': ' + r.linhas + ' linha(s)' + (r.puladas ? ', ' + r.puladas + ' pulada(s)' : '')); }
                    if (r.aviso) { avisos.push(t + ': ' + r.aviso); }
                    $.each(r.exemplos, function(_, e){ if (exemplos.length < 6) { exemplos.push(e); } });
                    if (r.fim) { i++; cursor = ''; } else { cursor = r.cursor; }
                    proximo();
                })
                .fail(function(xhr){ falhou('o servidor não respondeu (' + (xhr ? xhr.status : '?') + '). Pode tentar de novo: o que já foi trocado não é trocado duas vezes.'); });
        }

        function terminar() {
            rodando = false;
            var h = '<p style="font-size:15px"><strong>' + (aplicar ? '✅ Troca aplicada.' : '🔎 Prévia pronta (nada foi mudado).') + '</strong> ' +
                tot.linhas + ' linha(s) com ' + tot.trocas + ' troca(s).' + (tot.puladas ? ' ' + tot.puladas + ' valor(es) pulado(s) por segurança (dados internos de plugins, que eles mesmos refazem).' : '') + '</p>';
            if (porTabela.length) { h += '<details><summary>Por tabela</summary><pre style="white-space:pre-wrap">' + esc(porTabela.join('\n')) + '</pre></details>'; }
            if (exemplos.length) {
                h += '<details open><summary>Exemplos</summary>';
                $.each(exemplos, function(_, e){
                    h += '<p style="margin:8px 0"><code>' + esc(e.onde) + '</code><br>antes: <code>' + esc(e.antes) + '</code><br>depois: <code>' + esc(e.depois) + '</code></p>';
                });
                h += '</details>';
            }
            if (avisos.length) { h += '<details><summary>Tabelas não trocadas</summary><pre style="white-space:pre-wrap">' + esc(avisos.join('\n')) + '</pre></details>'; }
            if (aplicar) { h += '<p>Agora use, na lista acima, <strong>Refazer os links</strong> e depois <strong>Limpar os caches</strong>. Recarregue esta página para ver a lista atualizada.</p>'; }
            $p.html(h);
            if (!aplicar) { fezPrevia = true; }
            $('#cvm-previa').prop('disabled', false);
            atualizarAplicar();
        }

        function falhou(msg) {
            rodando = false;
            $p.append('<p style="color:#C0392B"><strong>Parou:</strong> ' + esc(msg) + '</p>');
            $('#cvm-previa').prop('disabled', false);
            atualizarAplicar();
        }
        proximo();
    }

    function atualizarAplicar() {
        $('#cvm-aplicar').prop('disabled', rodando || !fezPrevia || !$('#cvm-backup').is(':checked'));
    }

    $('#cvm-previa').on('click', function(){ rodar(false); });
    $('#cvm-backup').on('change', atualizarAplicar);
    $('#cvm-aplicar').on('click', function(){
        if (!window.confirm('Vai trocar o endereço em todo o banco do site no ar. Você fez o backup hoje? Continuar?')) { return; }
        rodar(true);
    });
});
