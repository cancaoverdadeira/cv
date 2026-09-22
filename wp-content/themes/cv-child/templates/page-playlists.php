<?php
/*
 * Template Name: Minhas Playlists
 */
// cancao-verdadeira-child/templates/page-playlists.php
// Gerado em: 2026-06-22 02:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página de playlists do usuário: lista de playlists à esquerda,
// músicas da playlist selecionada à direita. Integrado ao player global.
// Redireciona para /login/ se não estiver logado.

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! is_user_logged_in() ) {
    wp_redirect( cv_login_url( get_permalink() ) );
    exit;
}

$user_id   = get_current_user_id();
$playlists = class_exists('CV_Playlists') ? CV_Playlists::get_user_playlists($user_id) : array();

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part('template-parts/topbar'); ?>

        <div style="padding:36px">

            <!-- Cabeçalho -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                        margin-bottom:28px;flex-wrap:wrap;gap:12px">
                <h1 style="font-family:var(--font-display);font-size:28px;font-weight:700;margin:0">
                    📋 Minhas <span style="color:var(--cv-gold)">Playlists</span>
                </h1>
                <button id="cv-create-playlist"
                        class="cv-btn cv-btn-primary">
                    + Nova Playlist
                </button>
            </div>

            <div id="cv-pl-msg" style="display:none;padding:10px 14px;border-radius:6px;
                                       margin-bottom:16px;font-size:14px"></div>

            <?php if (empty($playlists)) : ?>
            <!-- Estado vazio -->
            <div style="text-align:center;padding:60px 20px;background:var(--cv-bg-card);
                        border:1px dashed var(--cv-border);border-radius:var(--cv-radius)">
                <div style="font-size:48px;margin-bottom:16px">📋</div>
                <h2 style="font-family:var(--font-display);color:var(--cv-gold);margin-bottom:8px">
                    Nenhuma playlist criada
                </h2>
                <p style="color:var(--cv-text-muted);margin-bottom:24px">
                    Crie sua primeira playlist e adicione suas músicas favoritas
                </p>
                <button id="cv-create-playlist-empty"
                        class="cv-btn cv-btn-primary">
                    + Criar primeira playlist
                </button>
            </div>

            <?php else : ?>

            <!-- Layout grid: lista de playlists + músicas -->
            <div style="display:grid;grid-template-columns:280px 1fr;gap:24px;align-items:start">

                <!-- Lista de playlists -->
                <div>
                    <h2 style="font-size:13px;font-weight:700;text-transform:uppercase;
                               letter-spacing:.5px;color:var(--cv-text-dim);margin:0 0 12px">
                        Suas Playlists (<?php echo count($playlists); ?>)
                    </h2>

                    <div style="display:flex;flex-direction:column;gap:8px" id="cv-pl-list">
                        <?php foreach ($playlists as $pl) :
                            $cover_id = !empty($pl->items) ? $pl->items[0]->music_id : 0;
                            $cover    = $cover_id ? cv_cover_url($cover_id, 'cv-card') : '';
                        ?>
                        <div class="cv-pl-item"
                             data-id="<?php echo esc_attr($pl->id); ?>"
                             data-name="<?php echo esc_attr($pl->name); ?>"
                             style="display:flex;align-items:center;gap:12px;padding:12px;
                                    background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);
                                    border-radius:var(--cv-radius);cursor:pointer;transition:all .2s">

                            <!-- Capa -->
                            <div style="width:48px;height:48px;border-radius:var(--cv-radius-sm);
                                        background:<?php echo $cover ? "url('" . esc_url($cover) . "') center/cover" : 'var(--cv-bg-elevated)'; ?>;
                                        flex-shrink:0;display:flex;align-items:center;justify-content:center;
                                        font-size:20px">
                                <?php echo !$cover ? '📋' : ''; ?>
                            </div>

                            <div style="flex:1;min-width:0">
                                <div style="font-size:14px;font-weight:700;color:var(--cv-text);
                                            white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
                                    <?php echo esc_html($pl->name); ?>
                                </div>
                                <div style="font-size:12px;color:var(--cv-text-muted)">
                                    <?php echo $pl->count; ?> música<?php echo $pl->count !== 1 ? 's' : ''; ?>
                                    <?php echo $pl->is_public ? ' · 🌐 Pública' : ''; ?>
                                </div>
                            </div>

                            <!-- Ações -->
                            <div style="display:flex;gap:6px;flex-shrink:0">
                                <button class="cv-pl-play-all"
                                        data-id="<?php echo esc_attr($pl->id); ?>"
                                        title="Tocar tudo"
                                        onclick="event.stopPropagation()"
                                        style="background:none;border:none;color:var(--cv-gold);
                                               cursor:pointer;font-size:16px;padding:2px">▶</button>
                                <button class="cv-pl-delete"
                                        data-id="<?php echo esc_attr($pl->id); ?>"
                                        title="Excluir playlist"
                                        onclick="event.stopPropagation()"
                                        style="background:none;border:none;color:var(--cv-text-dim);
                                               cursor:pointer;font-size:14px;padding:2px">🗑</button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Músicas da playlist selecionada -->
                <div>
                    <div id="cv-pl-content"
                         style="background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);
                                border-radius:var(--cv-radius);padding:24px;min-height:300px">
                        <div style="text-align:center;padding:40px;color:var(--cv-text-dim)">
                            <div style="font-size:40px;margin-bottom:12px">👈</div>
                            <p>Selecione uma playlist para ver as músicas</p>
                        </div>
                    </div>
                </div>

            </div>

            <?php endif; ?>
        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>
    </main>
</div>

<style>
.cv-pl-item:hover { border-color: var(--cv-border) !important; background: var(--cv-bg-elevated) !important; }
.cv-pl-item.active { border-color: var(--cv-gold) !important; background: rgba(212,160,23,.06) !important; }
.cv-pl-track {
    display:flex; align-items:center; gap:12px; padding:10px 0;
    border-bottom:1px solid var(--cv-border-subtle); cursor:pointer;
}
.cv-pl-track:last-child { border-bottom:none; }
.cv-pl-track:hover { background:rgba(255,255,255,.02); }
@media(max-width:768px){
    div[style*="grid-template-columns:280px"] { grid-template-columns:1fr !important; }
}
</style>

<script>
jQuery(function($){
    var AJAX   = cvPublic.ajaxUrl;
    var nonces = cvPublic.nonces;

    // Criar playlist
    function criarPlaylist(){
        var name = prompt('Nome da nova playlist:');
        if (!name || !name.trim()) return;
        $.post(AJAX, { action:'cv_playlist_create', nonce:nonces.playlist, name:name.trim() }, function(res){
            if (res.success) {
                if(window.CV_Theme) CV_Theme.toast('✓ Playlist criada!', 'success');
                setTimeout(function(){ location.reload(); }, 800);
            } else {
                showMsg(res.data && res.data.message ? res.data.message : 'Erro.', false);
            }
        });
    }

    $('#cv-create-playlist, #cv-create-playlist-empty').on('click', criarPlaylist);

    // Selecionar playlist
    $(document).on('click', '.cv-pl-item', function(){
        $('.cv-pl-item').removeClass('active');
        $(this).addClass('active');
        var id   = $(this).data('id');
        var name = $(this).data('name');
        carregarMusicas(id, name);
    });

    // Seleciona a primeira por padrão
    $('.cv-pl-item:first').trigger('click');

    function carregarMusicas(plId, plName) {
        var $content = $('#cv-pl-content');
        $content.html('<div style="text-align:center;padding:40px;color:var(--cv-text-dim)">⏳ Carregando...</div>');

        $.post(AJAX, { action:'cv_playlist_list', nonce:nonces.playlist, playlist_id:plId }, function(res){
            if (!res.success || !res.data.items || !res.data.items.length) {
                $content.html(
                    '<div style="text-align:center;padding:40px">'
                    + '<div style="font-size:36px;margin-bottom:12px">🎵</div>'
                    + '<h3 style="color:var(--cv-gold);font-family:var(--font-display)">' + plName + '</h3>'
                    + '<p style="color:var(--cv-text-dim)">Nenhuma música nesta playlist.<br>'
                    + 'Adicione músicas clicando em "+" nas páginas de música.</p>'
                    + '</div>'
                );
                return;
            }

            var items = res.data.items;
            var html  = '<h3 style="font-family:var(--font-display);font-size:18px;font-weight:700;'
                      + 'margin:0 0 16px;color:var(--cv-text)">'
                      + '📋 ' + plName
                      + ' <span style="font-size:13px;font-weight:400;color:var(--cv-text-dim)">('
                      + items.length + ' músicas)</span></h3>';

            // Botão tocar tudo
            html += '<button class="cv-btn cv-btn-primary cv-btn-sm" id="cv-play-all-pl" '
                  + 'style="margin-bottom:20px">▶ Tocar tudo</button>';

            html += '<div id="cv-tracks-list">';
            items.forEach(function(item, i){
                html += '<div class="cv-pl-track" '
                      + 'data-music-id="' + item.music_id + '" '
                      + 'data-youtube-id="' + (item.youtube_id || '') + '" '
                      + 'data-title="' + item.post_title + '" '
                      + 'data-cover="' + (item.cover || '') + '">'
                      + '<span style="color:var(--cv-text-dim);font-size:13px;min-width:24px">' + (i+1) + '</span>'
                      + '<div style="width:40px;height:40px;border-radius:4px;flex-shrink:0;'
                      + 'background:url(\'' + (item.cover || '') + '\') center/cover,var(--cv-bg-elevated)"></div>'
                      + '<div style="flex:1;min-width:0">'
                      + '<div style="font-size:14px;font-weight:600;color:var(--cv-text);'
                      + 'white-space:nowrap;overflow:hidden;text-overflow:ellipsis">' + item.post_title + '</div>'
                      + '<div style="font-size:12px;color:var(--cv-text-muted)">' + (item.artista || '') + '</div>'
                      + '</div>'
                      + '<a href="' + item.url + '" style="color:var(--cv-text-dim);font-size:12px;'
                      + 'text-decoration:none;flex-shrink:0" onclick="event.stopPropagation()">↗</a>'
                      + '<button class="cv-remove-from-pl" data-playlist="' + plId + '" data-music="' + item.music_id + '" '
                      + 'onclick="event.stopPropagation()" '
                      + 'style="background:none;border:none;color:var(--cv-text-dim);cursor:pointer;'
                      + 'font-size:13px;padding:4px" title="Remover da playlist">✕</button>'
                      + '</div>';
            });
            html += '</div>';
            $content.html(html);

            // Tocar música ao clicar no track
            $content.on('click', '.cv-pl-track', function(e){
                if ($(e.target).is('button, a')) return;
                var d = $(this).data();
                if (d.youtubeId && window.CV_Player) {
                    CV_Player.playById({ musicId:d.musicId, youtubeId:d.youtubeId, title:d.title, cover:d.cover });
                }
            });

            // Tocar tudo
            $('#cv-play-all-pl').on('click', function(){
                if (!window.CV_Player) return;
                var queue = [];
                $content.find('.cv-pl-track').each(function(){
                    var d = $(this).data();
                    if (d.youtubeId) queue.push({ musicId:d.musicId, youtubeId:d.youtubeId, title:d.title, cover:d.cover });
                });
                if (queue.length) CV_Player.playQueue(queue);
            });

            // Remover música da playlist
            $content.on('click', '.cv-remove-from-pl', function(){
                var plId    = $(this).data('playlist');
                var musicId = $(this).data('music');
                var $track  = $(this).closest('.cv-pl-track');
                $.post(AJAX, { action:'cv_playlist_remove', nonce:nonces.playlist, playlist_id:plId, music_id:musicId }, function(res){
                    if (res.success) { $track.fadeOut(200, function(){ $(this).remove(); }); }
                });
            });
        });
    }

    // Excluir playlist
    $(document).on('click', '.cv-pl-delete', function(){
        var id = $(this).data('id');
        if (!confirm('Excluir esta playlist?')) return;
        $.post(AJAX, { action:'cv_playlist_delete', nonce:nonces.playlist, playlist_id:id }, function(res){
            if (res.success) {
                if(window.CV_Theme) CV_Theme.toast('Playlist excluída', 'info');
                setTimeout(function(){ location.reload(); }, 600);
            }
        });
    });

    // Tocar tudo via botão na lista lateral
    $(document).on('click', '.cv-pl-play-all', function(){
        var id = $(this).data('id');
        // Ativa a playlist e dispara tocar tudo
        $('[data-id="' + id + '"].cv-pl-item').trigger('click');
        setTimeout(function(){ $('#cv-play-all-pl').trigger('click'); }, 600);
    });

    function showMsg(msg, ok) {
        var $m = $('#cv-pl-msg');
        $m.css({ background: ok ? '#1a3a1a' : '#3a1a1a', color: ok ? '#5cb85c' : '#e74c3c',
                 border: '1px solid ' + (ok ? '#2d6a2d' : '#6a2d2d') })
          .text(msg).show();
    }
});
</script>

<?php get_footer(); ?>
