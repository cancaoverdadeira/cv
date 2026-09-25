/*
 * cancao-verdadeira-child/assets/js/cv-musica.js
 * Projeto: Canção Verdadeira — JavaScript da página individual da música.
 * Registra o play após N segundos, botão Ouvir, Favoritar, estrelas,
 * copiar letra/link, comentários de trecho (selecionar, publicar, listar,
 * excluir). Carregado só em páginas de música (functions.php).
 * Dados: cvPublic (plugin) e cvMusica = { musicId, playMs } (tema).
 * v15.10.0: saiu do <script> em linha do single-musica.php (fase 6).
 * v15.21.0: A− / A+ muda o tamanho da letra (15 a 29 px) e guarda a escolha
 * no aparelho (localStorage, com proteção se o navegador bloquear).
 */
jQuery(function($){
    var AJAX   = cvPublic.ajaxUrl;
    var nonces = cvPublic.nonces;
    var musicId = parseInt(window.cvMusica && cvMusica.musicId, 10) || 0;

    // ── Registra play ao carregar a página ──────────────────────
    if (cvPublic.isLoggedIn || true) { // registra para todos
        setTimeout(function(){
            $.post(AJAX, {
                action:   'cv_register_play',
                nonce:    nonces.play,
                music_id: musicId
            });
        }, (window.cvMusica && cvMusica.playMs) || 30000); // após N segundos
    }

    // ── Botão Play ───────────────────────────────────────────────
    $('#cv-play-btn').on('click', function(){
        var d = $(this).data();
        // Se tem MP3 cadastrado: usa WaveSurfer (player do rodapé)
        if (d.audioUrl && window.CV_Player) {
            CV_Player.playById({
                musicId  : d.musicId,
                audioUrl : d.audioUrl,
                youtubeId: d.youtubeId || '',
                title    : d.title,
                cover    : d.cover,
                artista  : d.artista || ''
            });
        } else {
            // Sem MP3: rola a página até o embed do YouTube
            var $iframe = $('#cv-musica-iframe');
            if ($iframe.length) {
                $('html,body').animate({ scrollTop: $iframe.offset().top - 80 }, 400);
                if(window.CV_Theme) CV_Theme.toast('🎵 Toque o vídeo abaixo para ouvir', 'info', 3000);
            }
        }
    });

    // ── Favoritar ────────────────────────────────────────────────
    $('#cv-fav-btn').on('click', function(){
        if (!cvPublic.isLoggedIn) {
            window.location.href = cvPublic.loginUrl + '?redirect_to=' + encodeURIComponent(window.location.href);
            return;
        }
        var $btn = $(this);
        $btn.prop('disabled', true);
        $.post(AJAX, { action:'cv_toggle_favorite', nonce:nonces.favorite, music_id:musicId }, function(res){
            if (res.success) {
                var fav = res.data.action === 'added';
                $btn.toggleClass('cv-favorited', fav)
                    .attr('aria-pressed', fav ? 'true' : 'false')
                    .css({
                        background: fav ? 'rgba(231,76,60,.2)'    : '',
                        borderColor: fav ? '#e74c3c'              : '',
                        color:       fav ? '#e74c3c'              : ''
                    });
                $btn.find('span, .cv-btn-text').remove();
                $btn.text(fav ? '❤ Favoritado' : '♡ Favoritar');
                if (res.data.favorites_label) { $btn.append(' <span id="cv-fav-count">(' + res.data.favorites_label + ')</span>'); }
                if(window.CV_Theme) CV_Theme.toast(fav ? '❤ Adicionado aos favoritos' : 'Removido dos favoritos', fav ? 'success' : 'info');
            }
            $btn.prop('disabled', false);
        });
    });

    // ── Avaliação por estrelas ───────────────────────────────────
    var $stars = $('#cv-stars-widget');
    $stars.on('mouseenter', '.cv-star', function(){
        var val = $(this).data('value');
        $stars.find('.cv-star').each(function(){
            $(this).toggleClass('active', $(this).data('value') <= val);
        });
    }).on('mouseleave', function(){
        var cur = parseInt($stars.data('current')) || 0;
        $stars.find('.cv-star').each(function(){
            $(this).toggleClass('active', $(this).data('value') <= cur);
        });
    }).on('click', '.cv-star', function(){
        var val = $(this).data('value');
        $stars.data('current', val);
        $.post(AJAX, { action:'cv_rate_music', nonce:nonces.rating, music_id:musicId, rating:val }, function(res){
            if (res.success) {
                $('#cv-rating-msg').text('✓ Avaliação salva!').show();
                if(window.CV_Theme) CV_Theme.toast('★ Avaliação salva!', 'success');
            }
        });
    });
    // Teclado
    $stars.on('keydown', '.cv-star', function(e){
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); $(this).trigger('click'); }
    });

    // ── Copiar letra ─────────────────────────────────────────────
    // ── Tamanho da letra (A− / A+) ─────────────────────────────
    (function(){
        var MIN = 15, MAX = 29, PASSO = 2, CHAVE = 'cv_letra_tamanho';
        var $alvo = $('#cv-letra-conteudo');
        if (!$alvo.length) { return; }
        var tam = 17;
        try { tam = parseInt(window.localStorage.getItem(CHAVE), 10) || 17; } catch (e) {}
        function aplicar() {
            tam = Math.max(MIN, Math.min(MAX, tam));
            $alvo[0].style.setProperty('--cv-letra-tam', tam + 'px');
            $('.cv-historia')[0] && $('.cv-historia')[0].style.setProperty('--cv-letra-tam', tam + 'px');
            $('#cv-letra-menor').prop('disabled', tam <= MIN);
            $('#cv-letra-maior').prop('disabled', tam >= MAX);
            try { window.localStorage.setItem(CHAVE, String(tam)); } catch (e) {}
        }
        $('#cv-letra-menor').on('click', function(){ tam -= PASSO; aplicar(); });
        $('#cv-letra-maior').on('click', function(){ tam += PASSO; aplicar(); });
        aplicar();
    })();

    $('#cv-copy-letra-btn').on('click', function(){
        var txt = $('#cv-letra-conteudo').text().trim();
        navigator.clipboard.writeText(txt).then(function(){
            if(window.CV_Theme) CV_Theme.toast('📋 Letra copiada!', 'success');
        }).catch(function(){
            // fallback
            var $ta = $('<textarea style="position:fixed;left:-9999px">').val(txt).appendTo('body').select();
            document.execCommand('copy');
            $ta.remove();
            if(window.CV_Theme) CV_Theme.toast('📋 Letra copiada!', 'success');
        });
    });

    // ── Copiar link ──────────────────────────────────────────────
    $('#cv-copy-link-btn').on('click', function(){
        var url = $(this).data('url');
        navigator.clipboard.writeText(url).then(function(){
            if(window.CV_Theme) CV_Theme.toast('🔗 Link copiado!', 'success');
        });
    });

    // ── Seleção de trecho para comentar ──────────────────────────
    var $popup    = $('#cv-trecho-popup');
    var $modal    = $('#cv-trecho-modal');
    var $excerptD = $('#cv-trecho-selecionado');
    var selText   = '';

    $('#cv-letra-conteudo').on('mouseup touchend', function(){
        var sel = window.getSelection ? window.getSelection() : null;
        selText = sel ? sel.toString().trim() : '';

        if (selText.length > 5 && selText.length < 200 && cvPublic.isLoggedIn) {
            var range = sel.getRangeAt(0);
            var rect  = range.getBoundingClientRect();
            $popup.css({
                top : rect.top + window.scrollY - 40 + 'px',
                left: rect.left + 'px'
            }).show();
        } else {
            $popup.hide();
        }
    });

    $(document).on('click', function(e){
        if (!$(e.target).closest('#cv-trecho-popup').length) { $popup.hide(); }
    });

    $('#cv-comentar-trecho-btn').on('click', function(){
        if (!selText) return;
        $excerptD.text('"' + selText + '"');
        $('#cv-trecho-comment').val('');
        $('#cv-trecho-msg').hide();
        $popup.hide();
        $modal.css('display', 'flex');
    });

    function fecharModal() { $modal.css('display', 'none'); }
    $('#cv-trecho-modal-close, #cv-trecho-modal-close2').on('click', fecharModal);
    $modal.on('click', function(e){ if ($(e.target).is($modal)) fecharModal(); });

    $('#cv-trecho-submit').on('click', function(){
        var comment = $('#cv-trecho-comment').val().trim();
        if (!comment) {
            $('#cv-trecho-msg').css('color','var(--cv-error)').text('Escreva um comentário.').show();
            return;
        }
        var $btn = $(this).prop('disabled', true).text('Enviando...');
        $.post(AJAX, {
            action:   'cv_save_lyric_comment',
            nonce:    nonces.lyricComment,
            music_id: musicId,
            excerpt:  selText,
            comment:  comment
        }, function(res){
            if (res.success) {
                $('#cv-trecho-msg').css('color','var(--cv-success)').text('✓ Comentário publicado!').show();
                setTimeout(function(){ fecharModal(); carregarComentarios(); }, 1000);
            } else {
                $('#cv-trecho-msg').css('color','var(--cv-error)').text(res.data && res.data.message ? res.data.message : 'Erro.').show();
            }
            $btn.prop('disabled', false).text('Publicar');
        });
    });

    // ── Carrega comentários de trechos ───────────────────────────
    function carregarComentarios(){
        $.post(AJAX, {
            action:   'cv_get_lyric_comments',
            nonce:    nonces.lyricComment,
            music_id: musicId
        }, function(res){
            var $list = $('#cv-lyric-comments-list');
            if (!res.success || !res.data.comments.length) {
                $list.html('<p style="color:var(--cv-text-dim);font-size:13px">Nenhum comentário ainda. Selecione um trecho da letra para ser o primeiro!</p>');
                return;
            }
            var html = '';
            res.data.comments.forEach(function(c){
                html += '<div class="cv-lyric-comment">';
                html += '<div class="cv-lyric-comment-excerpt">"' + c.excerpt + '"</div>';
                html += '<div class="cv-lyric-comment-body">' + c.comment + '</div>';
                html += '<div class="cv-lyric-comment-meta">';
                html += '<img src="' + c.avatar + '" alt="' + c.user_name + '">';
                html += '<span><strong>' + c.user_name + '</strong></span>';
                html += '<span>· ' + c.time_human + '</span>';
                if (c.can_delete) {
                    html += ' <button class="cv-del-comment" data-id="' + c.id + '" '
                          + 'style="background:none;border:none;color:var(--cv-error);'
                          + 'font-size:11px;cursor:pointer;margin-left:auto">🗑 Excluir</button>';
                }
                html += '</div></div>';
            });
            $list.html(html);
        });
    }

    // Excluir comentário
    $(document).on('click', '.cv-del-comment', function(){
        if (!confirm('Excluir este comentário?')) return;
        var id = $(this).data('id');
        var $item = $(this).closest('.cv-lyric-comment');
        $.post(AJAX, { action:'cv_delete_lyric_comment', nonce:nonces.lyricComment, id:id }, function(res){
            if (res.success) { $item.fadeOut(300, function(){ $(this).remove(); }); }
        });
    });

    carregarComentarios();
});
