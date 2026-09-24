/*
 * cancao-verdadeira/assets/js/admin-musicas.js
 * Criado em: 24/09/2026 (plugin v2.42.0)
 * Tela "🗂 Gerenciar músicas" (CV_Page_Musicas): filtro por título e status,
 * botão "👁 Dados" (mostra todos os dados cadastrados da música), "🗑" (exclui
 * de vez, com confirmação) e "♻ Reimportar" (exclui e importa de novo do
 * YouTube). Usa cvAdmin.ajaxUrl e cvAdmin.nonce (CV_Admin::enqueue_assets).
 */
jQuery(function($){
    'use strict';
    var AJAX  = window.cvAdmin ? cvAdmin.ajaxUrl : '';
    var NONCE = window.cvAdmin ? cvAdmin.nonce : '';

    function esc(t){ return $('<div>').text(t == null ? '' : String(t)).html(); }

    function msg(texto, ok){
        $('#cv-mus-msg').removeClass('success error').addClass(ok ? 'success' : 'error')
            .css({ background: ok ? '#EAF6EA' : '#F6EAEA', color: ok ? '#1f6b35' : '#9b2c2c', padding: '10px 14px', borderRadius: '6px', marginBottom: '14px' })
            .text(texto).show();
        $('html, body').animate({ scrollTop: 0 }, 200);
    }

    // Filtro por título e status
    function filtrar(){
        var q  = $.trim($('#cv-mus-busca').val()).toLowerCase();
        var st = $('#cv-mus-status').val();
        $('.cv-mus-linha').each(function(){
            var $l = $(this);
            var ok = (!q || String($l.data('titulo')).indexOf(q) !== -1) && (!st || $l.data('status') === st);
            $l.toggle(ok);
            if (!ok) { $('.cv-mus-detalhe[data-id="' + $l.data('id') + '"]').hide(); }
        });
    }
    $('#cv-mus-busca').on('input', filtrar);
    $('#cv-mus-status').on('change', filtrar);

    // 👁 Dados: todos os campos e contagens da música
    $(document).on('click', '.cv-mus-ver', function(){
        var id = $(this).closest('tr').data('id');
        var $d = $('.cv-mus-detalhe[data-id="' + id + '"]');
        if ($d.is(':visible')) { $d.hide(); return; }
        $d.show().find('td').html('⏳ Carregando...');
        $.post(AJAX, { action: 'cv_musicas_dados', nonce: NONCE, musica_id: id }, function(r){
            if (!r || !r.success) { $d.find('td').text((r && r.data && r.data.message) || 'Erro ao carregar.'); return; }
            var d = r.data, c = d.contagens || {};
            var h = '<div style="background:#FBF6EE;border:1px solid #EADBC6;border-radius:8px;padding:14px">'
                + '<p style="margin:0 0 8px"><strong>' + esc(d.titulo) + '</strong> — ' + esc(d.status)
                + ' · <a href="' + esc(d.link) + '" target="_blank" rel="noopener">abrir no site ↗</a></p>'
                + '<p style="margin:0 0 8px">📝 Letra: ' + esc(d.letra)
                + ' · 🎭 Sentimentos: ' + (d.sentimentos.length ? esc(d.sentimentos.join(', ')) : '—') + '</p>'
                + '<p style="margin:0 0 10px">▶ ' + (c.plays || 0) + ' plays · ❤ ' + (c.favoritos || 0) + ' favoritos · ⭐ '
                + (c.notas || 0) + ' avaliações · 📋 ' + (c.playlists || 0) + ' playlists · 💬 ' + (c.comentarios || 0) + ' comentários</p>'
                + '<table class="cv-table" style="font-size:12px"><thead><tr><th>Campo</th><th>Valor</th></tr></thead><tbody>';
            (d.campos || []).forEach(function(f){
                h += '<tr><td><code>' + esc(f.campo) + '</code></td><td style="word-break:break-all">' + (f.valor === '' ? '<span style="color:#C9A27E">(vazio)</span>' : esc(f.valor)) + '</td></tr>';
            });
            h += '</tbody></table></div>';
            $d.find('td').html(h);
        });
    });

    function tituloDaLinha($btn){
        return $.trim($btn.closest('tr').find('td:eq(1) strong').text());
    }

    // 🗑 Excluir de vez
    $(document).on('click', '.cv-mus-excluir', function(){
        var $b = $(this), $l = $b.closest('tr'), id = $l.data('id');
        if (!confirm('Excluir DE VEZ "' + tituloDaLinha($b) + '"?\n\nSomem também plays, favoritos, notas, playlists, comentários, sentimentos e a capa.\nNão dá para desfazer.')) { return; }
        $b.prop('disabled', true).text('...');
        $.post(AJAX, { action: 'cv_musicas_excluir', nonce: NONCE, musica_id: id }, function(r){
            if (r && r.success) {
                msg('✅ ' + r.data.message, true);
                $('.cv-mus-detalhe[data-id="' + id + '"]').remove();
                $l.fadeOut(250, function(){ $(this).remove(); });
            } else {
                msg('❌ ' + ((r && r.data && r.data.message) || 'Erro ao excluir.'), false);
                $b.prop('disabled', false).text('🗑');
            }
        }).fail(function(){ msg('❌ Erro de conexão.', false); $b.prop('disabled', false).text('🗑'); });
    });

    // ♻ Excluir e reimportar do YouTube
    $(document).on('click', '.cv-mus-reimportar', function(){
        var $b = $(this), id = $b.closest('tr').data('id');
        if (!confirm('Reimportar "' + tituloDaLinha($b) + '" do YouTube?\n\nA versão atual será EXCLUÍDA com todos os dados (letra, campos, plays, favoritos...) e uma música nova será criada em rascunho, com o título e a capa atuais do vídeo.\nNão dá para desfazer.')) { return; }
        $b.prop('disabled', true).text('⏳ Reimportando...');
        $.post(AJAX, { action: 'cv_musicas_reimportar', nonce: NONCE, musica_id: id }, function(r){
            if (r && r.success) {
                msg('✅ ' + r.data.message + ' Recarregando a lista...', true);
                setTimeout(function(){ location.reload(); }, 1500);
            } else {
                msg('❌ ' + ((r && r.data && r.data.message) || 'Erro ao reimportar.'), false);
                $b.prop('disabled', false).text('♻ Reimportar');
            }
        }).fail(function(){ msg('❌ Erro de conexão.', false); $b.prop('disabled', false).text('♻ Reimportar'); });
    });
});
