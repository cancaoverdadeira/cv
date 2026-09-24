/*
 * cancao-verdadeira/assets/js/admin-publicacao-rapida.js
 * Tela "Publicação Acelerada": carrega o rascunho no formulário, salva,
 * publica, pula e exclui via AJAX, sem recarregar a página.
 * Os dados vêm de window.cvPr = { nonce, ajaxUrl, rascunhos, total },
 * impressos por CV_Publicacao_Rapida::render_page().
 * v2.33.0: saiu do <script> que ficava dentro de render_page().
 */
(function() {
    var CFG       = window.cvPr || {};
    var NONCE     = CFG.nonce || '';
    var AJAX      = CFG.ajaxUrl || '';
    var RASCUNHOS = CFG.rascunhos || [];
    var TOTAL     = CFG.total || 0;
    var publicadas = 0;
    var musicaAtual = null;

    // ── Utilitários ───────────────────────────────────────

    function toast(msg, erro) {
        var el = document.getElementById('cv-pr-toast');
        el.textContent = msg;
        el.className = 'cv-pr-toast' + (erro ? ' erro' : '');
        el.classList.add('show');
        setTimeout(function(){ el.classList.remove('show'); }, 3000);
    }

    function post(action, extra, cb) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', NONCE);
        for (var k in extra) { fd.append(k, extra[k]); }
        fetch(AJAX, {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(cb)
            .catch(function(){ toast('Erro de conexão.', true); });
    }

    function postForm(action, cb) {
        if (!musicaAtual) return;
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', NONCE);
        fd.append('musica_id', musicaAtual);
        fd.append('titulo',    document.getElementById('cv-pr-titulo').value);
        fd.append('conteudo',  document.getElementById('cv-pr-letra').value);
        fd.append('artista',   document.getElementById('cv-pr-artista').value);
        fd.append('compositor',document.getElementById('cv-pr-compositor').value);
        fd.append('album',     document.getElementById('cv-pr-album').value);
        fd.append('ano',       document.getElementById('cv-pr-ano').value);
        fd.append('_cv_youtube_url', document.getElementById('cv-pr-yt-url').value);
        fd.append('_cv_audio_url',   document.getElementById('cv-pr-audio-url').value);
        fd.append('_cv_descricao',   document.getElementById('cv-pr-descricao').value);
        fd.append('_cv_ativo',    document.getElementById('cv-pr-ativo').checked ? '1' : '0');
        fd.append('_cv_destaque', document.getElementById('cv-pr-destaque').checked ? '1' : '0');
        // Sentimentos
        document.querySelectorAll('#cv-pr-sents-checks input:checked').forEach(function(el){
            fd.append('sentimentos[]', el.value);
        });
        fetch(AJAX, {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(cb)
            .catch(function(){ toast('Erro de conexão.', true); });
    }

    // ── Carregar música ───────────────────────────────────
    window.cvPrCarregar = function(id) {
        musicaAtual = id;
        // Marcar ativo na lista
        document.querySelectorAll('.cv-pr-item').forEach(function(el){ el.classList.remove('ativo'); });
        var li = document.getElementById('cv-pr-li-' + id);
        if (li) li.classList.add('ativo');

        // Mostrar loading
        document.getElementById('cv-pr-placeholder').style.display = 'none';
        document.getElementById('cv-pr-form-real').style.display = 'block';
        document.getElementById('cv-pr-titulo').value = '⏳ Carregando...';

        post('cv_pr_load_musica', {musica_id: id}, function(res) {
            if (!res.success) { toast('Erro ao carregar música.', true); return; }
            var d = res.data;

            // Cabeçalho
            document.getElementById('cv-pr-hdr-capa').src = d.capa_url || '';
            document.getElementById('cv-pr-titulo').value = d.titulo || '';
            document.getElementById('cv-pr-hdr-artista').textContent = d.artista || '';

            var ytWrap = document.getElementById('cv-pr-yt-wrap');
            var ytLink = document.getElementById('cv-pr-link-ext');
            if (d.youtube_url) {
                ytWrap.innerHTML = '<a href="' + d.youtube_url + '" target="_blank" class="cv-pr-yt-link">▶ Ver no YouTube</a>';
                ytLink.href = d.youtube_url;
                ytLink.style.display = 'inline-flex';
            } else {
                ytWrap.innerHTML = '';
                ytLink.style.display = 'none';
            }

            // Campos
            document.getElementById('cv-pr-artista').value    = d.artista || '';
            document.getElementById('cv-pr-compositor').value = d.compositor || '';
            document.getElementById('cv-pr-album').value      = d.album || '';
            document.getElementById('cv-pr-ano').value        = d.ano || '';
            document.getElementById('cv-pr-yt-url').value     = d.youtube_url || '';
            document.getElementById('cv-pr-audio-url').value  = d.audio_url || '';
            document.getElementById('cv-pr-descricao').value  = d.descricao || '';
            document.getElementById('cv-pr-letra').value      = d.conteudo || '';

            // Checkbox ativo/destaque
            document.getElementById('cv-pr-ativo').checked    = d.ativo !== '0';
            document.getElementById('cv-pr-destaque').checked = d.destaque === '1';
            cvPrSyncCheck(document.getElementById('cv-pr-lbl-ativo'));
            cvPrSyncCheck(document.getElementById('cv-pr-lbl-destaque'));

            // Sentimentos
            var sentEl = document.getElementById('cv-pr-sents-checks');
            if (sentEl) {
                sentEl.querySelectorAll('input').forEach(function(el){
                    el.checked = d.sentimentos.indexOf(parseInt(el.value)) !== -1;
                    cvPrToggleSent(el.closest('label'));
                });
            }
        });
    };

    // ── Toggle visual dos checks ──────────────────────────
    window.cvPrToggleCheck = function(label) {
        cvPrSyncCheck(label);
    };
    function cvPrSyncCheck(label) {
        if (!label) return;
        var cb = label.querySelector('input');
        if (!cb) return;
        if (cb.checked) label.classList.add('marcado');
        else label.classList.remove('marcado');
    }
    window.cvPrToggleSent = function(label) {
        if (!label) return;
        var cb  = label.querySelector('input');
        var cor = label.dataset.cor || '#B8700C';
        if (cb && cb.checked) {
            label.style.background    = cor + '33';
            label.style.borderColor   = cor;
            label.style.color         = cor;
        } else {
            label.style.background    = cor + '18';
            label.style.borderColor   = cor + '44';
            label.style.color         = cor;
        }
    };

    // ── Ações ─────────────────────────────────────────────
    window.cvPrSalvar = function() {
        postForm('cv_pr_salvar', function(res) {
            if (res.success) toast('💾 Rascunho salvo!');
            else toast('Erro ao salvar.', true);
        });
    };

    window.cvPrPublicar = function() {
        var btn = document.querySelector('.cv-pr-btn-publicar');
        btn.innerHTML = '<span class="cv-pr-spinner"></span> Publicando...';
        btn.disabled = true;

        postForm('cv_pr_publicar', function(res) {
            btn.innerHTML = '🚀 Publicar música';
            btn.disabled = false;
            if (res.success) {
                toast('✅ ' + document.getElementById('cv-pr-titulo').value + ' publicada!');
                // Marcar como publicada na lista
                var li = document.getElementById('cv-pr-li-' + musicaAtual);
                if (li) {
                    li.classList.add('publicada');
                    li.classList.remove('ativo');
                    var chk = document.getElementById('cv-pr-chk-' + musicaAtual);
                    if (chk) chk.textContent = '✅';
                }
                publicadas++;
                document.getElementById('cv-pr-count-pub').textContent = publicadas;
                var pct = Math.round(publicadas / TOTAL * 100);
                document.getElementById('cv-pr-prog').style.width = pct + '%';
                // Ir para próxima automaticamente
                cvPrProxima();
            } else {
                toast('Erro ao publicar.', true);
            }
        });
    };

    window.cvPrPular = function() {
        toast('⏭ Pulado — próxima música');
        cvPrProxima();
    };

    window.cvPrExcluir = function() {
        if (!musicaAtual) return;
        var titulo = document.getElementById('cv-pr-titulo').value || 'esta música';
        if (!confirm('⚠️ Excluir permanentemente "' + titulo + '"?\n\nEsta ação não pode ser desfeita.')) return;

        var btn = document.querySelector('.cv-pr-btn-excluir');
        btn.textContent = '⏳ Excluindo...';
        btn.disabled = true;

        post('cv_pr_excluir', {musica_id: musicaAtual}, function(res) {
            btn.textContent = '🗑 Excluir';
            btn.disabled = false;
            if (res.success) {
                toast('🗑 "' + (res.data.titulo || titulo) + '" excluída.', false);
                // Remover da lista
                var li = document.getElementById('cv-pr-li-' + musicaAtual);
                if (li) li.remove();
                musicaAtual = null;
                // Ir para próxima
                cvPrProxima();
            } else {
                toast('Erro ao excluir.', true);
            }
        });
    };

    function cvPrProxima() {
        // Encontrar próxima da lista que não está publicada
        var items = document.querySelectorAll('.cv-pr-item:not(.publicada):not(.ativo)');
        if (items.length > 0) {
            var nextId = items[0].id.replace('cv-pr-li-', '');
            cvPrCarregar(parseInt(nextId));
            items[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        } else {
            // Todas processadas
            document.getElementById('cv-pr-form-real').style.display = 'none';
            document.getElementById('cv-pr-placeholder').style.display = 'block';
            document.querySelector('.cv-pr-placeholder-emoji').textContent = '🎉';
            document.querySelector('.cv-pr-placeholder-txt').textContent = 'Fila processada! Todas as músicas foram revisadas.';
        }
    }

    // Carregar a primeira automaticamente se houver rascunhos
    if (RASCUNHOS.length > 0) {
        cvPrCarregar(RASCUNHOS[0].id);
    }
})();
