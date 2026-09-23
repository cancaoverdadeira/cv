<?php
/*
 * Template Name: Minha Área
 */
// cancao-verdadeira-child/templates/page-user-dashboard.php
// Gerado em: 2026-06-22 02:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Dashboard do usuário logado: cabeçalho com avatar e stats, abas de
// Favoritas, Histórico, Playlists e Conquistas. Redireciona para /login/
// se não estiver logado. Todos os dados vêm de AJAX do plugin.

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! is_user_logged_in() ) {
    wp_redirect( cv_login_url( get_permalink() ) );
    exit;
}

$user_id  = get_current_user_id();
$user     = wp_get_current_user();
$data     = class_exists('CV_User_Area')    ? CV_User_Area::get_user_data( $user_id )            : array();
$conquistas = class_exists('CV_Achievements') ? CV_Achievements::get_user_achievements( $user_id ) : array();

$earned = count( array_filter( $conquistas, function($a){ return $a['earned']; } ) );
$total  = count( $conquistas );

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part('template-parts/topbar'); ?>

        <div style="max-width:1100px;margin:0 auto;padding:0 0 40px">

            <!-- Cabeçalho do perfil -->
            <div style="background:linear-gradient(180deg,#FFFFFF 0%,var(--cv-bg) 100%);
                        padding:40px 36px 32px">
                <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap">

                    <!-- Avatar -->
                    <div style="width:84px;height:84px;border-radius:50%;overflow:hidden;
                                border:3px solid var(--cv-gold);flex-shrink:0">
                        <img src="<?php echo esc_url($data['avatar'] ?? ''); ?>"
                             alt="<?php echo esc_attr($user->display_name); ?>"
                             style="width:100%;height:100%;object-fit:cover" />
                    </div>

                    <!-- Nome e info -->
                    <div>
                        <h1 style="font-family:var(--font-display);font-size:26px;
                                   font-weight:700;margin:0 0 4px">
                            Olá, <?php echo esc_html($user->display_name); ?> 👋
                        </h1>
                        <p style="color:var(--cv-text-muted);font-size:13px;margin:0">
                            <?php echo esc_html($user->user_email); ?> · Membro desde
                            <?php echo date('M/Y', strtotime($user->user_registered)); ?>
                        </p>
                    </div>

                    <!-- Stats -->
                    <div style="margin-left:auto;display:flex;gap:16px;flex-wrap:wrap">
                        <?php
                        $stats = array(
                            array('▶', $data['plays_total'] ?? 0,   'Plays'),
                            array('❤', $data['favorites']  ?? 0,    'Favoritos'),
                            array('📋',$data['playlists']  ?? 0,    'Playlists'),
                            array('🏅', $earned . '/' . $total,      'Conquistas'),
                        );
                        foreach ($stats as $s) : ?>
                        <div style="text-align:center;background:var(--cv-bg-card);
                                    border:1px solid var(--cv-border-subtle);
                                    border-radius:var(--cv-radius);padding:14px 18px;min-width:80px">
                            <div style="font-size:18px;margin-bottom:4px"><?php echo $s[0]; ?></div>
                            <div style="font-family:var(--font-display);font-size:22px;
                                        font-weight:700;color:var(--cv-gold)">
                                <?php echo $s[1]; ?>
                            </div>
                            <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:.5px">
                                <?php echo $s[2]; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Editar perfil -->
                    <a href="<?php echo esc_url(cv_profile_url()); ?>"
                       class="cv-btn cv-btn-secondary cv-btn-sm">
                        ✏ Editar Perfil
                    </a>
                </div>
            </div>

            <!-- Conquistas (mini) -->
            <?php if (!empty($conquistas)) :
                $earned_list = array_filter($conquistas, function($a){ return $a['earned']; });
            ?>
            <div style="padding:0 36px;margin-bottom:8px">
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <span style="font-size:12px;color:var(--cv-text-dim);text-transform:uppercase;
                                 letter-spacing:.5px;margin-right:4px">Conquistas:</span>
                    <?php foreach (array_slice($earned_list, 0, 6) as $a) : ?>
                    <span title="<?php echo esc_attr($a['name'] . ': ' . $a['desc']); ?>"
                          style="font-size:20px;cursor:help"><?php echo $a['icon']; ?></span>
                    <?php endforeach; ?>
                    <?php if (count($earned_list) > 6) : ?>
                    <span style="font-size:12px;color:var(--cv-text-dim)">+<?php echo count($earned_list) - 6; ?> mais</span>
                    <?php endif; ?>
                    <a href="#cv-tab-conquistas"
                       onclick="document.querySelector('[data-tab=conquistas]').click();return false;"
                       style="font-size:12px;color:var(--cv-gold);margin-left:auto">Ver todas →</a>
                </div>
            </div>
            <?php endif; ?>

            <!-- Abas -->
            <div style="padding:0 36px">
                <div class="cv-dash-tabs" role="tablist">
                    <?php
                    $tabs = array(
                        array('id' => 'favoritas',  'icon' => '❤',  'label' => 'Favoritas'),
                        array('id' => 'historico',  'icon' => '▶',  'label' => 'Histórico'),
                        array('id' => 'playlists',  'icon' => '📋', 'label' => 'Playlists'),
                        array('id' => 'conquistas', 'icon' => '🏅', 'label' => 'Conquistas'),
                    );
                    foreach ($tabs as $i => $tab) : ?>
                    <button class="cv-dash-tab <?php echo $i === 0 ? 'active' : ''; ?>"
                            data-tab="<?php echo esc_attr($tab['id']); ?>"
                            role="tab"
                            aria-selected="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                            aria-controls="cv-tab-<?php echo esc_attr($tab['id']); ?>">
                        <?php echo $tab['icon']; ?> <?php echo esc_html($tab['label']); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Conteúdo das abas -->
            <div style="padding:24px 36px 0">

                <!-- ABA: Favoritas -->
                <div id="cv-tab-favoritas" class="cv-dash-panel active" role="tabpanel">
                    <div id="cv-favoritas-grid" class="cv-grid">
                        <div class="cv-loading">⏳ Carregando favoritas...</div>
                    </div>
                </div>

                <!-- ABA: Histórico -->
                <div id="cv-tab-historico" class="cv-dash-panel" role="tabpanel" style="display:none">
                    <div id="cv-historico-lista">
                        <div class="cv-loading">⏳ Carregando histórico...</div>
                    </div>
                </div>

                <!-- ABA: Playlists -->
                <div id="cv-tab-playlists" class="cv-dash-panel" role="tabpanel" style="display:none">
                    <div style="display:flex;justify-content:flex-end;margin-bottom:16px">
                        <button id="cv-new-playlist" class="cv-btn cv-btn-primary cv-btn-sm">
                            + Nova Playlist
                        </button>
                    </div>
                    <div id="cv-playlists-grid"
                         style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:14px">
                        <div class="cv-loading">⏳ Carregando playlists...</div>
                    </div>
                </div>

                <!-- ABA: Conquistas -->
                <div id="cv-tab-conquistas" class="cv-dash-panel" role="tabpanel" style="display:none">
                    <?php if (!empty($conquistas)) : ?>
                    <div class="cv-badges-grid">
                        <?php foreach ($conquistas as $a) : ?>
                        <div class="cv-badge <?php echo $a['earned'] ? 'earned' : 'locked'; ?>"
                             style="<?php echo $a['earned'] ? '--badge-color:' . esc_attr($a['color'] ?? '#B8700C') . ';' : ''; ?>"
                             title="<?php echo esc_attr($a['desc']); ?>">
                            <div class="cv-badge-icon"><?php echo esc_html($a['icon']); ?></div>
                            <div class="cv-badge-name"><?php echo esc_html($a['name']); ?></div>
                            <div class="cv-badge-desc">
                                <?php echo $a['earned'] ? '<span style="color:var(--cv-gold)">✓ Conquistado</span>' : esc_html($a['desc']); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else : ?>
                    <div class="cv-empty">🏅 Nenhuma conquista ainda. Continue ouvindo e favoritando músicas!</div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>
    </main>
</div>

<style>
.cv-dash-tabs { display:flex; gap:4px; border-bottom:1px solid var(--cv-border-subtle); margin-bottom:0; flex-wrap:wrap; }
.cv-dash-tab {
    background:none; border:none; color:var(--cv-text-muted);
    font-size:14px; font-weight:600; padding:12px 20px; cursor:pointer;
    border-bottom:2px solid transparent; transition:all var(--cv-transition); white-space:nowrap;
}
.cv-dash-tab:hover { color:var(--cv-text); }
.cv-dash-tab.active { color:var(--cv-gold); border-bottom-color:var(--cv-gold); }
.cv-dash-panel { padding-top:24px; }
@media(max-width:768px){
    .cv-dash-tabs { padding:0; }
    .cv-dash-tab { font-size:13px; padding:10px 14px; }
}
</style>

<script>
jQuery(function($){
    var AJAX   = cvPublic.ajaxUrl;
    var nonces = cvPublic.nonces;
    var loaded = {};

    // ── Abas ─────────────────────────────────────────────────────
    $('.cv-dash-tab').on('click', function(){
        var tab = $(this).data('tab');
        $('.cv-dash-tab').removeClass('active').attr('aria-selected','false');
        $(this).addClass('active').attr('aria-selected','true');
        $('.cv-dash-panel').hide();
        $('#cv-tab-' + tab).show();
        if (!loaded[tab]) { loadTab(tab); }
    });

    function loadTab(tab) {
        loaded[tab] = true;
        if      (tab === 'favoritas') loadFavoritas();
        else if (tab === 'historico') loadHistorico();
        else if (tab === 'playlists') loadPlaylists();
    }

    // ── Favoritas ─────────────────────────────────────────────────
    function loadFavoritas() {
        $.post(AJAX, { action:'cv_get_user_favorites', nonce:nonces.favorite }, function(res){
            var $g = $('#cv-favoritas-grid').empty();
            if (!res.success || !res.data.favorites.length) {
                $g.html('<div class="cv-empty">♡ Nenhuma música favoritada ainda.<br><a href="' + (cvPublic.siteUrl || '/') + 'musicas/" style="color:var(--cv-gold)">Explorar músicas →</a></div>');
                return;
            }
            res.data.favorites.forEach(function(m){ $g.append(buildCard(m)); });
        });
    }

    // ── Histórico ─────────────────────────────────────────────────
    function loadHistorico() {
        $.post(AJAX, { action:'cv_get_user_history', nonce:nonces.play }, function(res){
            var $l = $('#cv-historico-lista').empty();
            if (!res.success || !res.data.history.length) {
                $l.html('<div class="cv-empty">▶ Você ainda não ouviu nenhuma música aqui.</div>');
                return;
            }
            var html = '<div style="display:flex;flex-direction:column;gap:8px">';
            res.data.history.forEach(function(m, i){
                html += '<div style="display:flex;align-items:center;gap:14px;padding:10px 14px;'
                      + 'background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);'
                      + 'border-radius:var(--cv-radius);transition:all .2s" '
                      + 'onmouseover="this.style.borderColor=\'var(--cv-border)\'" '
                      + 'onmouseout="this.style.borderColor=\'var(--cv-border-subtle)\'">'
                      + '<span style="font-family:var(--font-display);font-size:16px;font-weight:700;'
                      + 'color:var(--cv-text-dim);width:28px;text-align:center;flex-shrink:0">' + (i+1) + '</span>'
                      + '<div style="width:44px;height:44px;border-radius:var(--cv-radius-sm);'
                      + 'background:url(\'' + m.cover + '\') center/cover;flex-shrink:0"></div>'
                      + '<div style="flex:1;min-width:0">'
                      + '<a href="' + m.url + '" style="display:block;font-size:14px;font-weight:700;'
                      + 'color:var(--cv-text);text-decoration:none;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">'
                      + m.title + '</a>'
                      + '<div style="font-size:12px;color:var(--cv-text-muted)">' + (m.artista || '') + '</div>'
                      + '</div></div>';
            });
            html += '</div>';
            $l.html(html);
        });
    }

    // ── Playlists ─────────────────────────────────────────────────
    function loadPlaylists() {
        $.post(AJAX, { action:'cv_get_user_playlists', nonce:nonces.playlist }, function(res){
            var $g = $('#cv-playlists-grid').empty();
            if (!res.success || !res.data.playlists.length) {
                $g.html('<div class="cv-empty">📋 Nenhuma playlist ainda. Crie a primeira!</div>');
                return;
            }
            res.data.playlists.forEach(function(pl){
                $g.append(
                    '<div style="background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);'
                    + 'border-radius:var(--cv-radius);padding:16px;text-align:center">'
                    + '<div style="font-size:36px;margin-bottom:10px">📋</div>'
                    + '<div style="font-weight:700;font-size:14px;margin-bottom:4px;color:var(--cv-text)">' + pl.name + '</div>'
                    + '<div style="font-size:12px;color:#8A6A55">' + (pl.total_musicas || pl.count || 0) + ' músicas</div>'
                    + (pl.is_public ? '<div style="font-size:10px;color:var(--cv-gold);margin-top:4px">🌐 Pública</div>' : '')
                    + '<div style="display:flex;gap:8px;justify-content:center;margin-top:12px">'
                    + '<button class="cv-btn-del-pl cv-btn cv-btn-secondary cv-btn-sm" data-id="' + pl.id + '">🗑 Excluir</button>'
                    + '</div></div>'
                );
            });
        });
    }

    // Excluir playlist
    $(document).on('click', '.cv-btn-del-pl', function(){
        if (!confirm('Excluir esta playlist?')) return;
        var id = $(this).data('id');
        var $card = $(this).closest('div[style]');
        $.post(AJAX, { action:'cv_playlist_delete', nonce:nonces.playlist, playlist_id:id }, function(res){
            if (res.success) { $card.fadeOut(300, function(){ $(this).remove(); }); }
        });
    });

    // Nova playlist
    $('#cv-new-playlist').on('click', function(){
        var name = prompt('Nome da nova playlist:');
        if (!name || !name.trim()) return;
        $.post(AJAX, { action:'cv_playlist_create', nonce:nonces.playlist, name:name.trim() }, function(res){
            if (res.success) {
                loaded['playlists'] = false;
                loadPlaylists();
                if(window.CV_Theme) CV_Theme.toast('✓ Playlist criada!', 'success');
            }
        });
    });

    // Helpers
    function buildCard(m) {
        return '<div class="cv-card" data-music-id="' + m.id + '" '
            + 'data-youtube-id="' + (m.youtube_id || '') + '" '
            + 'data-title="' + m.title + '" data-cover="' + m.cover + '">'
            + '<a href="' + m.url + '" class="cv-card-capa-link">'
            + '<div class="cv-card-capa" style="background-image:url(\'' + m.cover + '\')">'
            + '<div class="cv-card-play-overlay"><span class="cv-card-play-icon">▶</span></div>'
            + '</div></a>'
            + '<div class="cv-card-info">'
            + '<a href="' + m.url + '" class="cv-card-titulo">' + m.title + '</a>'
            + '<p class="cv-card-artista">' + (m.artista || '') + '</p>'
            + '<div class="cv-card-acoes">'
            + '<span class="cv-card-stat">▶ ' + (m.plays || 0) + '</span>'
            + '<button class="cv-btn-favorite cv-favorited" data-music-id="' + m.id + '">❤</button>'
            + '</div></div></div>';
    }

    // Carrega a primeira aba ao iniciar
    loadTab('favoritas');
});
</script>

<?php get_footer(); ?>
