/* cancao-verdadeira-child/assets/js/cv-player.js
   Gerado em: 2026-07-28 21:00:00
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   v15.0.0 — MOTOR DE PLAYER REESCRITO. Substitui WaveSurfer.js (removido)
   por um motor duplo: YouTube IFrame API (padrão) + HTML5 Audio nativo
   para MP3 hospedado (_cv_audio_url). O visitante pode alternar entre as
   duas fontes pelo botão de modo no player. Mantém 100% da API pública
   window.CV_Player já usada pelos templates (front-page, single-musica,
   páginas de playlist) e adiciona window.cvPlayer (alias em minúsculo)
   e o método loadQueue(), contrato já esperado pelo plugin (ver
   includes/public/class-cv-mvp.php). Fila, shuffle, repeat, volume e
   registro de plays preservados do motor anterior.
   v15.14.0: na página inicial a fila do ranking é embaralhada a cada visita
   (antes começava sempre pela 1ª colocada). */

(function($) {
    'use strict';

    // ── Estado ───────────────────────────────────────────────────
    var QUEUE       = [];
    var IDX         = 0;
    var PLAYING     = false;
    var SHUFFLE     = false;
    var REPEAT      = false;
    var MODE        = sessionStorage.getItem('cv_player_mode') || 'youtube'; // 'youtube' | 'mp3'
    var REG_TIMER   = null;
    var AJAX        = (window.cvPublic && cvPublic.ajaxUrl)      || '';
    var REST        = (window.cvTheme  && cvTheme.restUrl)       || '/wp-json/cv/v1/';
    var NONCES      = (window.cvPublic && cvPublic.nonces)       || {};
    var DEF_SEC     = parseInt((window.cvPublic && cvPublic.playSeconds) || 30);
    var DEF_COV     = (window.cvPublic && cvPublic.defaultCover) || '';
    var IS_HOME     = !!(window.cvTheme && parseInt(cvTheme.isFrontPage));

    // Motor YouTube
    var YT_PLAYER    = null;
    var YT_READY     = false;
    var YT_PENDING   = null;   // id pendente enquanto o player não fica pronto
    var PROGRESS_TMR = null;

    // Motor MP3 (HTML5 Audio nativo)
    var AUDIO_EL = null;

    // ── Normaliza um registro vindo do REST/AJAX para o formato interno ──
    // Aceita tanto o formato antigo (audio_url, youtube_id, music_id) quanto
    // o novo, já usado pelo plugin em format_track() (youtubeId, musicId).
    function normalizeTrack(m) {
        return {
            mid       : parseInt(m.music_id || m.musicId || m.mid) || 0,
            youtubeId : m.youtube_id || m.youtubeId || '',
            audioUrl  : m.audio_url  || m.audioUrl  || '',
            title     : m.post_title || m.title || '',
            cover     : m.cover || DEF_COV,
            art       : m.artista || m.artist || m.art || ''
        };
    }

    // ── Motor YouTube ────────────────────────────────────────────
    function initYT() {
        if (YT_PLAYER) { return; }
        if (typeof YT === 'undefined' || !YT.Player) { return; }
        if (!document.getElementById('cv-yt-engine')) { return; }

        YT_PLAYER = new YT.Player('cv-yt-engine', {
            height: '1', width: '1',
            playerVars: { autoplay: 0, controls: 0, disablekb: 1, fs: 0, modestbranding: 1, playsinline: 1, rel: 0 },
            events: {
                onReady: function() {
                    YT_READY = true;
                    try { YT_PLAYER.setVolume(parseInt(sessionStorage.getItem('cv_volume') || '80')); } catch(e) {}
                    if (YT_PENDING) { var id = YT_PENDING; YT_PENDING = null; loadYtVideo(id); }
                },
                onStateChange: onYtStateChange,
                onError: onYtError
            }
        });
    }
    // Callback global exigido pela YouTube IFrame API — chamado assim que
    // o script https://www.youtube.com/iframe_api termina de carregar.
    window.onYouTubeIframeAPIReady = function() { initYT(); };

    function loadYtVideo(id) {
        if (!id) { next(); return; }
        if (!YT_PLAYER || !YT_READY) { YT_PENDING = id; initYT(); return; }
        try { YT_PLAYER.loadVideoById(id); }
        catch (e) {
            console.warn('[CV Player] Erro ao carregar vídeo YouTube:', e);
            var m = QUEUE[IDX];
            if (m && m.audioUrl) { fallbackAudio(m); } else { setTimeout(next, 800); }
        }
    }

    function onYtStateChange(e) {
        if (typeof YT === 'undefined') { return; }
        if (e.data === YT.PlayerState.PLAYING) {
            PLAYING = true; updateBtn(true); startProgressPoll(); scheduleReg();
        } else if (e.data === YT.PlayerState.PAUSED) {
            PLAYING = false; updateBtn(false); stopProgressPoll(); clearReg();
        } else if (e.data === YT.PlayerState.ENDED) {
            PLAYING = false; stopProgressPoll(); clearReg(); updateBtn(false);
            REPEAT ? go(IDX) : next();
        } else if (e.data === YT.PlayerState.BUFFERING) {
            $('#cv-player-duration').text('...');
        }
    }

    function onYtError(e) {
        console.warn('[CV Player] Erro YouTube (código ' + (e && e.data) + ')');
        var m = QUEUE[IDX];
        if (m && m.audioUrl) { fallbackAudio(m); } else { setTimeout(next, 1200); }
    }

    function startProgressPoll() {
        stopProgressPoll();
        PROGRESS_TMR = setInterval(function() {
            if (!YT_PLAYER || typeof YT_PLAYER.getCurrentTime !== 'function') { return; }
            var t = YT_PLAYER.getCurrentTime() || 0;
            var d = YT_PLAYER.getDuration()    || 0;
            $('#cv-player-current').text(fmt(t));
            if (d) { $('#cv-player-duration').text(fmt(d)); $('#cv-player-bar-fill').css('width', (t / d * 100) + '%'); }
        }, 500);
    }
    function stopProgressPoll() {
        if (PROGRESS_TMR) { clearInterval(PROGRESS_TMR); PROGRESS_TMR = null; }
    }

    // ── Motor MP3 (HTML5 Audio nativo) ──────────────────────────
    function fallbackAudio(m) {
        if (AUDIO_EL) { try { AUDIO_EL.pause(); } catch(e) {} AUDIO_EL = null; }
        if (YT_PLAYER && typeof YT_PLAYER.stopVideo === 'function') { try { YT_PLAYER.stopVideo(); } catch(e) {} }

        AUDIO_EL = new Audio(m.audioUrl);
        AUDIO_EL.volume = parseInt(sessionStorage.getItem('cv_volume') || '80') / 100;
        AUDIO_EL.onplay  = function() { PLAYING = true;  updateBtn(true);  scheduleReg(); };
        AUDIO_EL.onpause = function() { PLAYING = false; updateBtn(false); clearReg(); };
        AUDIO_EL.onended = function() { PLAYING = false; clearReg(); REPEAT ? go(IDX) : next(); };
        AUDIO_EL.ontimeupdate = function() {
            var t = AUDIO_EL.currentTime, d = AUDIO_EL.duration || 0;
            $('#cv-player-current').text(fmt(t));
            if (d) { $('#cv-player-bar-fill').css('width', (t / d * 100) + '%'); }
        };
        AUDIO_EL.onloadedmetadata = function() { $('#cv-player-duration').text(fmt(AUDIO_EL.duration)); };
        AUDIO_EL.play().catch(function(e) { console.warn('[CV Player] Erro no áudio MP3:', e); });
    }

    // ── Reprodução (decide qual motor usar por faixa) ────────────
    function go(idx) {
        if (!QUEUE.length) { return; }
        idx = ((idx % QUEUE.length) + QUEUE.length) % QUEUE.length;
        IDX = idx;
        var m = QUEUE[idx];
        if (!m) { next(); return; }

        updateUI(m);
        clearReg(); stopProgressPoll();
        updateBtn(false);
        $('#cv-player-duration').text('...');
        $('#cv-player-current').text('0:00');
        $('#cv-player-bar-fill').css('width', '0%');
        sessionStorage.setItem('cv_queue_idx', String(idx));

        // Para qualquer motor em execução antes de trocar de faixa
        if (AUDIO_EL) { try { AUDIO_EL.pause(); } catch(e) {} AUDIO_EL = null; }
        if (YT_PLAYER && typeof YT_PLAYER.stopVideo === 'function') { try { YT_PLAYER.stopVideo(); } catch(e) {} }

        var useYt = (MODE === 'youtube' && m.youtubeId) || (!m.audioUrl && m.youtubeId);
        if (useYt) {
            loadYtVideo(m.youtubeId);
        } else if (m.audioUrl) {
            fallbackAudio(m);
        } else if (m.youtubeId) {
            loadYtVideo(m.youtubeId); // única fonte disponível, mesmo fora do modo preferido
        } else {
            console.warn('[CV Player] Música sem fonte de áudio disponível:', m.title);
            setTimeout(next, 400);
        }
    }

    function next() {
        if (!QUEUE.length) { return; }
        go(SHUFFLE ? Math.floor(Math.random() * QUEUE.length) : IDX + 1);
    }

    function prev() {
        var t = currentTime();
        if (t > 3) { seekTo(0); return; }
        go(IDX - 1);
    }

    function currentTime() {
        if (YT_PLAYER && YT_READY && typeof YT_PLAYER.getCurrentTime === 'function') { return YT_PLAYER.getCurrentTime() || 0; }
        if (AUDIO_EL) { return AUDIO_EL.currentTime || 0; }
        return 0;
    }
    function seekTo(t) {
        if (YT_PLAYER && YT_READY && typeof YT_PLAYER.seekTo === 'function') { YT_PLAYER.seekTo(t, true); return; }
        if (AUDIO_EL) { AUDIO_EL.currentTime = t; }
    }

    // ── UI ────────────────────────────────────────────────────────
    function updateUI(m) {
        $('#cv-player-cover-img').attr('src', m.cover || DEF_COV);
        $('#cv-player-title').text(m.title || '—');
        $('#cv-player-artist').text(m.art  || '');
        renderQueue();
    }

    function updateBtn(playing) {
        $('#cv-btn-play').text(playing ? '⏸' : '▶');
    }

    function updateModeBtn() {
        $('#cv-btn-mode')
            .text(MODE === 'youtube' ? '🎬 YouTube' : '💿 MP3')
            .attr('title', MODE === 'youtube' ? 'Tocando via YouTube — clique para usar MP3' : 'Tocando via MP3 — clique para usar YouTube');
    }

    // ── Registro de play (antifraude — mesmo endpoint do plugin) ──
    function scheduleReg() {
        clearReg();
        var m = QUEUE[IDX];
        if (!m || !m.mid || !AJAX) { return; }
        REG_TIMER = setTimeout(function() {
            $.post(AJAX, { action: 'cv_register_play', nonce: NONCES.play || '', music_id: m.mid });
        }, DEF_SEC * 1000);
    }
    function clearReg() {
        if (REG_TIMER) { clearTimeout(REG_TIMER); REG_TIMER = null; }
    }

    // ── Fila ──────────────────────────────────────────────────────
    function buildQueue(items) {
        QUEUE = (items || []).map(normalizeTrack).filter(function(m) { return !!(m.youtubeId || m.audioUrl); });
        try { sessionStorage.setItem('cv_queue', JSON.stringify(QUEUE.slice(0, 50))); } catch(e) {}
        return QUEUE;
    }

    function renderQueue() {
        var $l = $('#cv-queue-list');
        if (!$l.length || !QUEUE.length) { return; }
        var html = '';
        QUEUE.forEach(function(m, i) {
            var a = (i === IDX);
            html += '<div onclick="window._cvPlay(' + i + ')"'
                  + ' style="display:flex;align-items:center;gap:10px;padding:10px 14px;cursor:pointer;'
                  + 'border-bottom:1px solid var(--cv-border-subtle);'
                  + (a ? 'background:rgba(242,165,26,0.1)' : '') + '">'
                  + '<span style="font-size:11px;color:var(--cv-text-dim);min-width:18px">' + (i + 1) + '</span>'
                  + '<div style="width:36px;height:36px;border-radius:4px;flex-shrink:0;'
                  + 'background:url(\'' + (m.cover || DEF_COV).replace(/'/g, "\\'") + '\') center/cover,'
                  + 'var(--cv-bg-elevated)"></div>'
                  + '<div style="flex:1;min-width:0">'
                  + '<div style="font-size:12px;font-weight:' + (a ? '700' : '400') + ';color:'
                  + (a ? 'var(--cv-gold)' : 'var(--cv-text)') + ';white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                  + (m.title || '—') + '</div>'
                  + '<div style="font-size:11px;color:var(--cv-text-muted)">' + (m.art || '') + '</div>'
                  + '</div>' + (a ? '<span style="color:var(--cv-gold)">▶</span>' : '') + '</div>';
        });
        $l.html(html);
    }

    window._cvPlay = function(idx) { go(idx); };

    // ── Carga pelo ranking (usada na home) ──────────────────────
    // Embaralha a lista (Fisher-Yates): cada visita começa numa música diferente
    function embaralhar(lista) {
        for (var i = lista.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var t = lista[i]; lista[i] = lista[j]; lista[j] = t;
        }
        return lista;
    }

    function loadByPriority() {
        var urls = [REST + 'ranking/top', REST + 'ranking/best', REST + 'ranking/recent'];
        var i = 0;
        function tryNext() {
            if (i >= urls.length) { return; }
            $.get(urls[i++], { limit: 50 }, function(data) {
                var valid = (data || []).map(normalizeTrack).filter(function(m) { return !!(m.youtubeId || m.audioUrl); });
                if (valid.length) {
                    QUEUE = embaralhar(valid);
                    try { sessionStorage.setItem('cv_queue', JSON.stringify(QUEUE.slice(0, 50))); } catch(e) {}
                    go(0);
                } else {
                    tryNext();
                }
            }).fail(tryNext);
        }
        tryNext();
    }

    // ── API Pública (compatível com o que já existe no tema) ──────
    var API = {
        playById: function(d) {
            if (!d) { return; }
            var m = normalizeTrack({
                musicId: d.musicId || d.mid, youtubeId: d.youtubeId || d.youtube_id,
                audioUrl: d.audioUrl || d.audio_url, title: d.title,
                cover: d.cover, artista: d.artista || d.art
            });
            if (!m.youtubeId && !m.audioUrl) { return; }
            var ei = -1;
            QUEUE.forEach(function(q, i) {
                if ((m.youtubeId && q.youtubeId === m.youtubeId) || (m.audioUrl && q.audioUrl === m.audioUrl)) { ei = i; }
            });
            if (ei >= 0) { go(ei); }
            else {
                QUEUE.unshift(m); IDX = 0;
                try { sessionStorage.setItem('cv_queue', JSON.stringify(QUEUE.slice(0, 50))); } catch(e) {}
                go(0);
            }
        },
        playQueue : function(items) { if (buildQueue(items).length) { go(0); } },
        loadQueue : function(items) { if (buildQueue(items).length) { go(0); } }, // alias — contrato usado pelo plugin
        togglePlay: function() {
            if (YT_PLAYER && YT_READY && MODE === 'youtube' && QUEUE[IDX] && QUEUE[IDX].youtubeId) {
                var st = YT_PLAYER.getPlayerState();
                (typeof YT !== 'undefined' && st === YT.PlayerState.PLAYING) ? YT_PLAYER.pauseVideo() : YT_PLAYER.playVideo();
            } else if (AUDIO_EL) {
                PLAYING ? AUDIO_EL.pause() : AUDIO_EL.play();
            }
        },
        isPlaying : function() { return PLAYING; },
        getQueue  : function() { return QUEUE; },
        getCurrent: function() { return QUEUE[IDX]; },
        setMode   : function(mode) {
            if (mode !== 'youtube' && mode !== 'mp3') { return; }
            MODE = mode;
            sessionStorage.setItem('cv_player_mode', mode);
            updateModeBtn();
            if (QUEUE.length) { go(IDX); }
        },
        getMode   : function() { return MODE; }
    };
    window.CV_Player = API;
    window.cvPlayer  = API; // alias em minúsculo — contrato documentado em class-cv-mvp.php

    // ── Inicialização e controles ─────────────────────────────────
    $(function() {
        updateModeBtn();

        // Garante que o motor YouTube inicializa assim que a API estiver pronta
        function tryInitYT() {
            if (typeof YT !== 'undefined' && YT.Player) { initYT(); }
            else { setTimeout(tryInitYT, 300); }
        }
        tryInitYT();

        if (IS_HOME) {
            loadByPriority();
        } else {
            try {
                var sq = JSON.parse(sessionStorage.getItem('cv_queue') || '[]');
                var si = parseInt(sessionStorage.getItem('cv_queue_idx') || '0');
                if (sq.length) {
                    QUEUE = sq;
                    IDX   = Math.min(si, sq.length - 1);
                    updateUI(QUEUE[IDX]);
                }
            } catch(e) {}
        }

        $(document).on('click', '#cv-btn-play', function() {
            if (!QUEUE.length && IS_HOME) { loadByPriority(); return; }
            if (!QUEUE.length) { return; }
            API.togglePlay.call(null);
            if (!YT_PLAYER && !AUDIO_EL) { go(IDX); }
        });

        $(document).on('click', '#cv-btn-next', function() { next(); });
        $(document).on('click', '#cv-btn-prev', function() { prev(); });

        $(document).on('click', '#cv-btn-mode', function() {
            API.setMode(MODE === 'youtube' ? 'mp3' : 'youtube');
        });

        $(document).on('click', '#cv-btn-shuffle', function() {
            SHUFFLE = !SHUFFLE;
            $(this).css('color', SHUFFLE ? 'var(--cv-gold)' : 'var(--cv-text-muted)');
        });

        $(document).on('click', '#cv-btn-repeat', function() {
            REPEAT = !REPEAT;
            $(this).css('color', REPEAT ? 'var(--cv-gold)' : 'var(--cv-text-muted)');
        });

        $(document).on('click', '#cv-btn-mute', function() {
            var cur = (YT_PLAYER && YT_READY && typeof YT_PLAYER.getVolume === 'function')
                ? YT_PLAYER.getVolume() : (AUDIO_EL ? AUDIO_EL.volume * 100 : 80);
            if (cur > 0) {
                sessionStorage.setItem('cv_volume_before_mute', String(cur));
                if (YT_PLAYER) { try { YT_PLAYER.setVolume(0); } catch(e) {} }
                if (AUDIO_EL) { AUDIO_EL.volume = 0; }
                $('#cv-volume-slider').val(0); $(this).text('🔇');
            } else {
                var restore = parseInt(sessionStorage.getItem('cv_volume_before_mute') || '80');
                if (YT_PLAYER) { try { YT_PLAYER.setVolume(restore); } catch(e) {} }
                if (AUDIO_EL) { AUDIO_EL.volume = restore / 100; }
                $('#cv-volume-slider').val(restore); $(this).text('🔊');
            }
        });

        $(document).on('input', '#cv-volume-slider', function() {
            var vPct = parseInt($(this).val());
            if (YT_PLAYER) { try { YT_PLAYER.setVolume(vPct); } catch(e) {} }
            if (AUDIO_EL) { AUDIO_EL.volume = vPct / 100; }
            sessionStorage.setItem('cv_volume', String(vPct));
            $('#cv-btn-mute').text(vPct === 0 ? '🔇' : vPct < 40 ? '🔉' : '🔊');
        });

        $(document).on('click', '#cv-btn-queue', function() { $('#cv-queue-panel').toggle(); renderQueue(); });
        $(document).on('click', '#cv-queue-close', function() { $('#cv-queue-panel').hide(); });

        $(document).on('click', '#cv-hero-play-btn', function() {
            $(this).fadeOut(400);
            if (QUEUE.length) { go(IDX); } else { loadByPriority(); }
        });

        // v15.20.0: o botão "▶ Tocar" do cartão usa o mesmo caminho da capa
        $(document).on('click', '.cv-card-play-overlay, .cv-card-play-icon, .cv-card-tocar', function(e) {
            e.preventDefault(); e.stopPropagation();
            var $c = $(this).closest('.cv-card'), d = $c.data();
            API.playById({ musicId: d.musicId, audioUrl: d.audioUrl || '', youtubeId: d.youtubeId || '',
                            title: d.title || '', cover: d.cover || '', artista: d.artista || '' });
        });

        $(document).on('click', '.cv-ranking-item[data-music-id]', function(e) {
            if ($(e.target).is('a')) { return; }
            var d = $(this).data();
            API.playById({ musicId: d.musicId, audioUrl: d.audioUrl || '', youtubeId: d.youtubeId || '',
                            title: d.title || '', cover: d.cover || '' });
        });
    });

    // Utilitários
    function fmt(s) {
        s = Math.floor(s || 0);
        return Math.floor(s / 60) + ':' + (s % 60 < 10 ? '0' : '') + s % 60;
    }

}(jQuery));
