<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-youtube-import.php
// Página "Importador YouTube": importação de vídeos do YouTube como rascunhos.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Youtube_Import {

    public static function render() {
        wp_enqueue_media();

        $yt_key = get_option( 'cv_youtube_api_key', '' );
        if ( isset( $_POST['cv_save_yt_key'] ) && check_admin_referer( 'cv_yt_key_save' ) ) {
            $yt_key = sanitize_text_field( $_POST['cv_youtube_api_key'] );
            update_option( 'cv_youtube_api_key', $yt_key );
            $yt_saved = true;
        } else {
            $yt_saved = false;
        }
        $api_ok = ! empty( $yt_key );
        ?>
        <div class="wrap" id="cv-yt-exec">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        #cv-yt-exec {
            --gold:#B8700C; --bg:#FFFFFF; --card:#F8F0E4; --bord:#F3E6D3;
            --text:#3B2418; --muted:#C9A27E; --green:#1DB954; --red:#e74c3c; --yt:#FF0000;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:48px;
        }
        #cv-yt-exec * { box-sizing:border-box; }
        .cv-yt-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-yt-title { font-size:24px; font-weight:700; color:#3B2418; margin:0; }
        .cv-yt-title span { color:var(--yt); }
        .cv-yt-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .cv-yt-pill.ok  { background:rgba(29,185,84,.1); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-yt-pill.off { background:rgba(255,0,0,.08); border:1px solid rgba(255,0,0,.25); color:#DB0000; }
        /* Grid layout */
        .cv-yt-layout { display:grid; grid-template-columns:320px 1fr; gap:20px; align-items:start; }
        @media (max-width:1000px) { .cv-yt-layout { grid-template-columns:1fr; } }
        /* Cards laterais */
        .cv-yt-side { display:flex; flex-direction:column; gap:16px; }
        .cv-yt-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-yt-card-hdr { padding:14px 18px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:10px; }
        .cv-yt-card-hdr-icon { font-size:18px; }
        .cv-yt-card-hdr-title { font-size:14px; font-weight:700; color:#3B2418; }
        .cv-yt-card-body { padding:18px; }
        /* API form */
        .cv-yt-api-row { display:flex; gap:8px; }
        .cv-yt-input { flex:1; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-yt-input:focus { border-color:var(--gold); }
        .cv-yt-btn { padding:9px 16px; border-radius:8px; font-size:13px; font-weight:700; cursor:pointer; border:none; font-family:inherit; transition:opacity .2s; }
        .cv-yt-btn:hover { opacity:.85; }
        .cv-yt-btn-save { background:var(--gold); color:#3B2418; }
        .cv-yt-btn-primary { background:var(--yt); color:#3B2418; width:100%; justify-content:center; display:flex; align-items:center; gap:8px; padding:11px; }
        .cv-yt-hint { font-size:11px; color:var(--muted); margin-top:8px; line-height:1.5; }
        /* Busca */
        .cv-yt-search-row { display:flex; gap:8px; margin-bottom:14px; }
        .cv-yt-channel-input { flex:1; background:rgba(123,58,34,0.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:10px 14px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-yt-channel-input:focus { border-color:var(--yt); }
        /* Stats bar */
        .cv-yt-statsbar { display:flex; gap:16px; padding:12px 0; border-bottom:1px solid var(--bord); margin-bottom:16px; flex-wrap:wrap; }
        .cv-yt-stat { text-align:center; }
        .cv-yt-stat-num { font-size:20px; font-weight:800; color:var(--gold); }
        .cv-yt-stat-label { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        /* Progress */
        .cv-yt-progress-wrap { margin:14px 0; display:none; }
        .cv-yt-progress-bar { background:rgba(123,58,34,0.07); border-radius:6px; height:8px; overflow:hidden; margin-bottom:8px; }
        .cv-yt-progress-fill { height:100%; background:linear-gradient(90deg,var(--yt),#ff6b6b); width:0%; transition:width .3s; border-radius:6px; }
        .cv-yt-progress-txt { font-size:12px; color:var(--muted); }
        /* Result cards */
        .cv-yt-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(220px,1fr)); gap:12px; }
        .cv-yt-video-card {
            background:rgba(123,58,34,0.03); border:1px solid var(--bord);
            border-radius:10px; overflow:hidden; cursor:pointer;
            transition:border-color .2s, transform .15s;
            position:relative;
        }
        .cv-yt-video-card:hover { border-color:rgba(201,162,126,0.8); transform:translateY(-2px); }
        .cv-yt-video-card.selected { border-color:var(--gold); background:rgba(242,165,26,0.08); }
        .cv-yt-video-card.imported { border-color:rgba(29,185,84,.4); opacity:.6; }
        .cv-yt-thumb { width:100%; aspect-ratio:16/9; object-fit:cover; display:block; }
        .cv-yt-video-info { padding:10px; }
        .cv-yt-video-title { font-size:12px; font-weight:600; color:var(--text); line-height:1.4; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; overflow:hidden; }
        .cv-yt-video-date { font-size:10px; color:var(--muted); margin-top:4px; }
        .cv-yt-check { position:absolute; top:6px; right:6px; width:22px; height:22px; border-radius:50%; background:var(--gold); color:#3B2418; font-size:12px; font-weight:700; display:none; align-items:center; justify-content:center; }
        .cv-yt-video-card.selected .cv-yt-check { display:flex; }
        .cv-yt-imported-badge { position:absolute; top:6px; left:6px; background:rgba(29,185,84,.9); color:#3B2418; font-size:9px; font-weight:700; padding:2px 6px; border-radius:10px; }
        /* Status */
        .cv-yt-status-msg { font-size:13px; color:var(--gold); padding:10px 0; display:none; }
        .cv-yt-done-msg { display:none; }
        /* Toolbar */
        .cv-yt-toolbar { display:flex; align-items:center; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
        .cv-yt-btn-outline { background:rgba(123,58,34,0.06); border:1px solid var(--bord); color:var(--text); padding:7px 14px; }
        .cv-yt-btn-import { background:var(--yt); color:#3B2418; padding:9px 20px; }
        .cv-yt-selected-count { font-size:12px; color:var(--muted); }
        .cv-yt-loading { text-align:center; padding:40px; color:var(--muted); display:none; }
        .cv-yt-spinner { display:inline-block; width:24px; height:24px; border:3px solid rgba(123,58,34,0.16); border-top-color:var(--yt); border-radius:50%; animation:cv-spin .7s linear infinite; }
        @keyframes cv-spin { to { transform:rotate(360deg); } }
        </style>

        <div class="cv-yt-topbar">
            <h1 class="cv-yt-title">▶ Importar do <span>YouTube</span></h1>
            <span class="cv-yt-pill <?php echo $api_ok ? 'ok' : 'off'; ?>">
                <?php echo $api_ok ? '✅ API conectada' : '❌ API não configurada'; ?>
            </span>
        </div>
        <?php echo CV_Admin::btn_voltar(); ?>

        <div class="cv-yt-layout">

            <!-- Sidebar -->
            <div class="cv-yt-side">

                <!-- API Key -->
                <div class="cv-yt-card">
                    <div class="cv-yt-card-hdr">
                        <span class="cv-yt-card-hdr-icon">🔑</span>
                        <div class="cv-yt-card-hdr-title">Google API Key</div>
                    </div>
                    <div class="cv-yt-card-body">
                        <?php if ($yt_saved): ?>
                        <div style="background:rgba(29,185,84,.08);border:1px solid rgba(29,185,84,.25);border-radius:8px;padding:8px 12px;font-size:12px;color:var(--green);margin-bottom:12px">✅ API Key salva!</div>
                        <?php endif; ?>
                        <form method="post">
                            <?php wp_nonce_field( 'cv_yt_key_save' ); ?>
                            <div class="cv-yt-api-row">
                                <input type="password" name="cv_youtube_api_key"
                                       value="<?php echo esc_attr($yt_key); ?>"
                                       class="cv-yt-input" placeholder="AIza..." />
                                <input type="hidden" name="cv_save_yt_key" value="1" />
                                <button type="submit" class="cv-yt-btn cv-yt-btn-save">💾</button>
                            </div>
                        </form>
                        <div class="cv-yt-hint">
                            <strong style="color:var(--text)">Como obter:</strong><br>
                            1. console.cloud.google.com<br>
                            2. Criar projeto → Ativar "YouTube Data API v3"<br>
                            3. Credenciais → Criar chave de API<br>
                            <span style="color:#8A6A55">Cota gratuita: 10.000 req/dia</span>
                        </div>
                    </div>
                </div>

                <?php if ($api_ok): ?>
                <!-- Busca por canal -->
                <div class="cv-yt-card">
                    <div class="cv-yt-card-hdr">
                        <span class="cv-yt-card-hdr-icon">📡</span>
                        <div class="cv-yt-card-hdr-title">Buscar Canal</div>
                    </div>
                    <div class="cv-yt-card-body">
                        <input type="text" id="cv-yt-channel" class="cv-yt-channel-input"
                               value="cancaoverdadeira" placeholder="Handle do canal (sem @)" />
                        <div style="margin-top:10px">
                            <button id="cv-yt-fetch" class="cv-yt-btn cv-yt-btn-primary">
                                <span>▶</span> Buscar Vídeos
                            </button>
                        </div>
                        <div class="cv-yt-hint">Digite o handle do canal (o que aparece após @) e clique em Buscar.</div>
                    </div>
                </div>
                <?php else: ?>
                <div class="cv-yt-card">
                    <div class="cv-yt-card-body" style="text-align:center;padding:30px">
                        <div style="font-size:36px;margin-bottom:12px">🔑</div>
                        <div style="font-size:13px;color:var(--muted)">Configure a API Key ao lado para começar a importar vídeos do YouTube.</div>
                    </div>
                </div>
                <?php endif; ?>

            </div><!-- side -->

            <!-- Área principal de resultados -->
            <div class="cv-yt-card" style="min-height:300px">
                <div class="cv-yt-card-hdr">
                    <span class="cv-yt-card-hdr-icon">🎬</span>
                    <div class="cv-yt-card-hdr-title">Vídeos do Canal</div>
                </div>
                <div class="cv-yt-card-body">

                    <div id="cv-yt-status" class="cv-yt-status-msg"></div>

                    <!-- Loading -->
                    <div class="cv-yt-loading" id="cv-yt-loading">
                        <div class="cv-yt-spinner"></div>
                        <div style="margin-top:12px;font-size:13px">Conectando ao YouTube...</div>
                    </div>

                    <!-- Stats + toolbar (ocultos até buscar) -->
                    <div id="cv-yt-results" style="display:none">
                        <div class="cv-yt-statsbar">
                            <div class="cv-yt-stat">
                                <div class="cv-yt-stat-num" id="cv-yt-total-count">0</div>
                                <div class="cv-yt-stat-label">Encontrados</div>
                            </div>
                            <div class="cv-yt-stat">
                                <div class="cv-yt-stat-num" id="cv-yt-new-count" style="color:var(--green)">0</div>
                                <div class="cv-yt-stat-label">Novos</div>
                            </div>
                            <div class="cv-yt-stat">
                                <div class="cv-yt-stat-num" id="cv-yt-sel-count" style="color:var(--yt)">0</div>
                                <div class="cv-yt-stat-label">Selecionados</div>
                            </div>
                        </div>

                        <div class="cv-yt-toolbar">
                            <button id="cv-yt-select-all" class="cv-yt-btn cv-yt-btn-outline">☑ Selecionar Todos</button>
                            <button id="cv-yt-select-none" class="cv-yt-btn cv-yt-btn-outline">☐ Limpar Seleção</button>
                            <button id="cv-yt-import-selected" class="cv-yt-btn cv-yt-btn-import">⬇ Importar Selecionados</button>
                        </div>

                        <!-- Progress bar -->
                        <div class="cv-yt-progress-wrap" id="cv-yt-progress">
                            <div class="cv-yt-progress-bar">
                                <div class="cv-yt-progress-fill" id="cv-yt-bar"></div>
                            </div>
                            <div class="cv-yt-progress-txt" id="cv-yt-progress-text"></div>
                        </div>

                        <!-- Resultado final -->
                        <div class="cv-yt-done-msg" id="cv-yt-done"></div>

                        <!-- Grid de vídeos -->
                        <div class="cv-yt-grid" id="cv-yt-list"></div>
                    </div>

                    <!-- Estado vazio inicial -->
                    <div id="cv-yt-empty" style="text-align:center;padding:60px 20px;color:var(--muted)">
                        <div style="font-size:48px;margin-bottom:14px">▶</div>
                        <div style="font-size:15px;color:#8A6A55;margin-bottom:6px">Nenhuma busca realizada</div>
                        <div style="font-size:12px">Insira o handle do canal e clique em Buscar Vídeos.</div>
                    </div>

                </div>
            </div>

        </div><!-- layout -->
        </div><!-- wrap -->

        <script>
        jQuery(function($){
            var videos  = [];
            var apiKey  = '<?php echo esc_js( $yt_key ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url("admin-ajax.php") ); ?>';
            var nonce   = '<?php echo esc_js( wp_create_nonce("cv_admin_nonce") ); ?>';

            function updateSelCount() {
                var n = $('.cv-yt-video-card.selected:not(.imported)').length;
                $('#cv-yt-sel-count').text(n);
            }

            // Toggle seleção
            $(document).on('click', '.cv-yt-video-card:not(.imported)', function(){
                $(this).toggleClass('selected');
                updateSelCount();
            });

            $('#cv-yt-select-all').on('click', function(){
                $('.cv-yt-video-card:not(.imported)').addClass('selected');
                updateSelCount();
            });
            $('#cv-yt-select-none').on('click', function(){
                $('.cv-yt-video-card').removeClass('selected');
                updateSelCount();
            });

            // Buscar vídeos
            $('#cv-yt-fetch').on('click', function(){
                var channel = $('#cv-yt-channel').val().trim();
                if (!channel) return;
                videos = [];
                $('#cv-yt-results,#cv-yt-done,#cv-yt-empty').hide();
                $('#cv-yt-loading').show();
                $('#cv-yt-list').empty();
                $('#cv-yt-status').hide();
                $(this).prop('disabled', true).html('<span class="cv-yt-spinner"></span>');
                fetchChannelId(channel);
            });

            function fetchChannelId(handle) {
                $.getJSON(
                    'https://www.googleapis.com/youtube/v3/channels',
                    { part:'id,snippet', forHandle: handle, key: apiKey },
                    function(data){
                        if (!data.items || !data.items.length) {
                            showStatus('Canal não encontrado. Verifique o handle.', true);
                            resetBtn(); return;
                        }
                        fetchPlaylistId(data.items[0].id);
                    }
                ).fail(function(){ showStatus('Erro ao conectar ao YouTube. Verifique a API Key.', true); resetBtn(); });
            }

            function fetchPlaylistId(channelId) {
                $.getJSON(
                    'https://www.googleapis.com/youtube/v3/channels',
                    { part:'contentDetails', id: channelId, key: apiKey },
                    function(data){
                        var pid = data.items[0].contentDetails.relatedPlaylists.uploads;
                        fetchVideos(pid, null);
                    }
                );
            }

            function fetchVideos(playlistId, pageToken) {
                var params = { part:'snippet', playlistId: playlistId, maxResults: 50, key: apiKey };
                if (pageToken) { params.pageToken = pageToken; }
                $.getJSON('https://www.googleapis.com/youtube/v3/playlistItems', params, function(data){
                    data.items.forEach(function(item){
                        var s = item.snippet;
                        if (s.title === 'Private video' || s.title === 'Deleted video') return;
                        videos.push({
                            videoId : s.resourceId.videoId,
                            title   : s.title,
                            thumb   : (s.thumbnails.medium || s.thumbnails.default || {url:''}).url,
                            date    : s.publishedAt ? s.publishedAt.substr(0,10) : ''
                        });
                    });
                    if (data.nextPageToken && videos.length < 500) {
                        fetchVideos(playlistId, data.nextPageToken);
                    } else {
                        renderVideos();
                    }
                }).fail(function(){ showStatus('Erro ao buscar vídeos.', true); resetBtn(); });
            }

            function renderVideos() {
                $('#cv-yt-loading').hide();
                $('#cv-yt-empty').hide();
                var html = '';
                videos.forEach(function(v){
                    var ytUrl = 'https://www.youtube.com/watch?v=' + v.videoId;
                    html += '<div class="cv-yt-video-card" data-id="' + escHtml(v.videoId) + '">'
                          + '<div class="cv-yt-check">✓</div>'
                          + '<img src="' + escHtml(v.thumb) + '" class="cv-yt-thumb" loading="lazy" alt="">'
                          + '<div class="cv-yt-video-info">'
                          + '<div class="cv-yt-video-title">' + escHtml(v.title) + '</div>'
                          + '<div class="cv-yt-video-date">📅 ' + escHtml(v.date) + ' &nbsp; <a href="' + escHtml(ytUrl) + '" target="_blank" style="color:var(--yt);font-size:10px" onclick="event.stopPropagation()">▶ Ver</a></div>'
                          + '</div></div>';
                });
                $('#cv-yt-list').html(html);
                $('#cv-yt-total-count').text(videos.length);
                $('#cv-yt-new-count').text(videos.length);
                $('#cv-yt-sel-count').text(0);
                $('#cv-yt-results').show();
                resetBtn();
            }

            // Importar selecionados
            $('#cv-yt-import-selected').on('click', function(){
                var items = [];
                $('.cv-yt-video-card.selected:not(.imported)').each(function(){
                    var id = $(this).data('id');
                    var v  = videos.find(function(x){ return x.videoId === id; });
                    if (v) items.push(v);
                });
                if (!items.length) { alert('Selecione ao menos um vídeo.'); return; }
                $(this).prop('disabled', true);
                var done = 0, imported = 0, skipped = 0, falhas = 0, erros = [], total = items.length;
                $('#cv-yt-progress').show();
                $('#cv-yt-done').hide();

                function next() {
                    if (done >= total) {
                        $('#cv-yt-bar').css('width','100%');
                        $('#cv-yt-progress-text').text('Concluído!');
                        var corErros = falhas ? 'var(--red)' : 'var(--green)';
                        var bgErros  = falhas ? 'rgba(231,76,60,.08)' : 'rgba(29,185,84,.08)';
                        var bordErros = falhas ? 'rgba(231,76,60,.3)' : 'rgba(29,185,84,.3)';
                        var html = '<div style="background:' + bgErros + ';border:1px solid ' + bordErros + ';border-radius:10px;padding:14px 18px;font-size:13px;font-weight:600;color:' + corErros + ';margin-top:12px">'
                            + (falhas ? '⚠️ ' : '✅ ') + 'Importados: <strong>' + imported + '</strong> &nbsp;|&nbsp; Já existiam: <strong>' + skipped + '</strong>'
                            + (falhas ? ' &nbsp;|&nbsp; Falharam: <strong>' + falhas + '</strong>' : '') + '<br>';
                        if (falhas) {
                            html += '<div style="margin-top:8px;font-weight:400;font-size:12px;color:#DC1100;max-height:140px;overflow:auto">'
                                  + erros.map(escHtml).join('<br>') + '</div>';
                        }
                        html += '<a href="<?php echo esc_js( admin_url("admin.php?page=cv-publicacao-rapida") ); ?>" style="color:var(--gold);margin-top:8px;display:inline-block;font-size:12px">⚡ Ir para Publicação Acelerada →</a>'
                              + '</div>';
                        $('#cv-yt-done').html(html).show();
                        $('#cv-yt-import-selected').prop('disabled', false);
                        return;
                    }
                    var item = items[done];
                    var pct  = Math.round((done / total) * 100);
                    $('#cv-yt-bar').css('width', pct + '%');
                    $('#cv-yt-progress-text').text('Importando ' + (done+1) + ' de ' + total + ': ' + item.title);
                    var payload = {
                        url   : 'https://www.youtube.com/watch?v=' + item.videoId,
                        title : item.title,
                        thumb : item.thumb,
                        id    : item.videoId
                    };
                    $.ajax({
                        url: ajaxUrl, method: 'POST',
                        data: { action:'cv_import_youtube_video', nonce:nonce, video: JSON.stringify(payload) },
                        success: function(res){
                            if (res && res.success) {
                                if (res.data.skipped) {
                                    skipped++;
                                } else {
                                    imported++;
                                }
                                $('[data-id="' + item.videoId + '"]').addClass('imported').removeClass('selected');
                            } else {
                                falhas++;
                                var msg = (res && res.data && res.data.message) ? res.data.message : 'resposta inesperada do servidor';
                                erros.push(item.title + ': ' + msg);
                            }
                        },
                        error: function(jqXHR){
                            falhas++;
                            var txt = jqXHR.responseText || ('HTTP ' + jqXHR.status);
                            if (String(txt).trim() === '-1') {
                                txt = 'sessão expirada ou sem permissão — recarregue a página (F5) e tente de novo';
                            }
                            erros.push(item.title + ': ' + String(txt).substring(0, 200));
                        },
                        complete: function(){ done++; setTimeout(next, 300); }
                    });
                }
                next();
            });

            function showStatus(msg, err) {
                $('#cv-yt-status').text(msg).css('color', err ? 'var(--red)' : 'var(--gold)').show();
                $('#cv-yt-loading').hide();
                $('#cv-yt-empty').show();
            }
            function resetBtn() {
                $('#cv-yt-fetch').prop('disabled', false).html('<span>▶</span> Buscar Vídeos');
            }
            function escHtml(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
        });
        </script>
        <?php
    }
}
