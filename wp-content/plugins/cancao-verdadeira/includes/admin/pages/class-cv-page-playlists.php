<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-playlists.php
// Página "Playlists": gestão de playlists e suas músicas.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Playlists {

    public static function render() {
        global $wpdb;

        // Busca todas as playlists (de todos os usuarios) para visao admin
        $playlists = $wpdb->get_results(
            "SELECT p.*, u.display_name AS user_name,
             (SELECT COUNT(*) FROM {$wpdb->prefix}cv_playlist_items pi WHERE pi.playlist_id = p.id) AS total_musicas
             FROM {$wpdb->prefix}cv_playlists p
             LEFT JOIN {$wpdb->users} u ON u.ID = p.user_id
             ORDER BY p.created_at DESC
             LIMIT 200"
        );

        // Busca musicas para o modal de adicao
        $musicas = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'meta_query'     => array(
                array( 'key' => '_cv_ativo', 'value' => '1', 'compare' => '=' ),
            ),
        ) );
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
        <style>
        body.wp-admin { background:#FBF6EE !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#FBF6EE !important; }
        /* Dark nos campos do formulário de playlists */
        #cv-admin-page .cv-input {
            background:rgba(123,58,34,0.04) !important;
            border:1px solid #EADBC6 !important;
            color:#3B2418 !important;
            border-radius:8px !important;
        }
        #cv-admin-page .cv-input:focus { border-color:#C9A27E !important; outline:none !important; }
        #cv-admin-page .cv-form-label { color:#6B4C3B !important; font-size:11px !important; text-transform:uppercase; letter-spacing:.4px; }
        #cv-admin-page .cv-section { background:#F8F0E4 !important; border:1px solid #EADBC6 !important; border-radius:14px; padding:22px 24px !important; margin-bottom:18px; }
        #cv-admin-page .cv-section-title { color:#7B3A22; border-color:#EADBC6 !important; }
        #cv-admin-page .cv-admin-header { background:#F8F0E4; border:1px solid #EADBC6; border-radius:12px; padding:18px 22px; margin-bottom:18px; }
        #cv-admin-page h1 { color:#3B2418 !important; }
        #cv-admin-page .cv-admin-subtitle { color:#8A6A55 !important; }
        #cv-admin-page .cv-btn-primary { background:#F2A51A !important; color:#3B2418 !important; font-weight:700 !important; border-radius:8px !important; }
        #cv-admin-page .cv-btn-outline { background:rgba(123,58,34,0.06) !important; border:1px solid #EADBC6 !important; color:#6B4C3B !important; border-radius:8px !important; }
        #cv-admin-page .cv-table { background:#F8F0E4 !important; }
        #cv-admin-page .cv-table th { background:#F8F0E4 !important; color:#8A6A55 !important; border-color:#EADBC6 !important; }
        #cv-admin-page .cv-table td { border-color:#EADBC6 !important; color:#3B2418 !important; }
        #cv-admin-page .cv-table tr:hover td { background:rgba(123,58,34,0.03) !important; }
        </style>
        <?php echo CV_Admin::btn_voltar(); ?>

            <div class="cv-admin-header">
                <div>
                    <h1>📋 Playlists</h1>
                    <p class="cv-admin-subtitle">
                        <?php echo count( $playlists ); ?> playlist<?php echo count( $playlists ) !== 1 ? 's' : ''; ?> cadastrada<?php echo count( $playlists ) !== 1 ? 's' : ''; ?>
                    </p>
                </div>
                <div style="margin-left:auto">
                    <button id="cv-pl-btn-nova" class="cv-btn cv-btn-primary" data-open="1">
                        ✕ Ocultar Formulário
                    </button>
                </div>
            </div>

            <div id="cv-pl-msg" class="cv-action-message" style="display:none"></div>

            <!-- Formulário de nova playlist -->
            <div id="cv-pl-form-nova" class="cv-section" style="display:block">
                <h2 class="cv-section-title" style="color:#7B3A22;font-size:16px;margin-bottom:18px">🎵 Nova Playlist Oficial</h2>
                <div style="display:flex;flex-direction:column;gap:14px;max-width:560px">
                    <div class="cv-form-group">
                        <label class="cv-form-label">Nome da playlist <span style="color:#D62C1A">*</span></label>
                        <input type="text" id="cv-pl-nome" class="cv-input"
                               placeholder="Ex: Top Sertanejo Universitário" maxlength="100" />
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Descrição</label>
                        <textarea id="cv-pl-desc" class="cv-input" rows="2"
                                  placeholder="Descrição opcional da playlist"></textarea>
                    </div>
                    <div class="cv-form-group" style="display:flex;align-items:center;gap:10px">
                        <input type="checkbox" id="cv-pl-publica" style="width:18px;height:18px;accent-color:#B8700C" />
                        <label for="cv-pl-publica" class="cv-form-label" style="margin:0">
                            Playlist pública (visível para todos os usuários)
                        </label>
                    </div>
                    <div class="cv-form-group" style="display:flex;align-items:center;gap:10px">
                        <input type="checkbox" id="cv-pl-destaque" style="width:18px;height:18px;accent-color:#B8700C" />
                        <label for="cv-pl-destaque" class="cv-form-label" style="margin:0">
                            ⭐ Fixar na home (aparece na seção de playlists em destaque)
                        </label>
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Capa da playlist (URL da imagem)</label>
                        <div style="display:flex;gap:8px;align-items:center">
                            <input type="text" id="cv-pl-capa-url" class="cv-input"
                                   placeholder="Cole a URL da imagem ou use o botão ao lado" style="flex:1" />
                            <button type="button" id="cv-pl-capa-media" class="cv-btn cv-btn-outline"
                                    style="padding:8px 12px;white-space:nowrap">
                                📁 Biblioteca
                            </button>
                        </div>
                        <div id="cv-pl-capa-preview" style="margin-top:8px;display:none">
                            <img id="cv-pl-capa-img" src="" alt="Preview da capa"
                                 style="width:80px;height:80px;object-fit:cover;border-radius:8px;border:1px solid #EADBC6" />
                        </div>
                    </div>
                    <div style="display:flex;gap:10px">
                        <button id="cv-pl-salvar" class="cv-btn cv-btn-primary">💾 Criar Playlist</button>
                        <button id="cv-pl-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
                    </div>
                </div>
            </div>

            <!-- Tabela de playlists -->
            <div class="cv-section">
                <?php if ( empty( $playlists ) ) : ?>
                    <div style="background:rgba(242,165,26,0.07);border:1px dashed rgba(201,162,126,0.5);border-radius:14px;padding:40px;text-align:center;margin-bottom:8px">
                        <div style="font-size:48px;margin-bottom:14px">🎵</div>
                        <div style="font-size:17px;color:#3B2418;font-weight:700;margin-bottom:8px">Nenhuma playlist criada ainda</div>
                        <div style="font-size:13px;color:#8A6A55;margin-bottom:20px;line-height:1.6">
                            Playlists editoriais são curadas por você e aparecem para todos os usuários do site.<br>
                            Ideal para destacar os maiores hits, lançamentos e temáticas especiais.
                        </div>
                        <button onclick="document.getElementById('cv-pl-nome').focus()"
                                style="background:#F2A51A;color:#3B2418;border:none;padding:10px 24px;border-radius:8px;font-weight:700;font-size:13px;cursor:pointer">
                            ↑ Preencha o formulário acima para criar a primeira
                        </button>
                    </div>
                <?php else : ?>
                <table class="cv-table" id="cv-pl-tabela">
                    <thead>
                        <tr>
                            <th style="width:36px">#</th>
                            <th>Nome</th>
                            <th>Criada por</th>
                            <th style="text-align:center">Músicas</th>
                            <th style="text-align:center">Pública</th>
                            <th>Criada em</th>
                            <th style="text-align:center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $playlists as $i => $pl ) : ?>
                        <tr id="cv-pl-row-<?php echo esc_attr( $pl->id ); ?>">
                            <td style="color:#8A6A55;font-size:12px"><?php echo $i + 1; ?></td>
                            <td>
                                <strong style="color:var(--cv-text)"><?php echo esc_html( $pl->name ); ?></strong>
                                <?php if ( $pl->description ) : ?>
                                    <br><span style="font-size:11px;color:#8A6A55"><?php echo esc_html( mb_substr( $pl->description, 0, 60 ) ); ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#8A6A55;font-size:13px"><?php echo esc_html( $pl->user_name ?: 'Admin' ); ?></td>
                            <td style="text-align:center">
                                <span class="cv-badge-count" id="cv-pl-count-<?php echo esc_attr( $pl->id ); ?>">
                                    <?php echo (int) $pl->total_musicas; ?>
                                </span>
                            </td>
                            <td style="text-align:center">
                                <?php if ( $pl->is_public ) : ?>
                                    <span style="color:#1C7C44;font-size:18px" title="Pública">●</span>
                                <?php else : ?>
                                    <span style="color:#8A6A55;font-size:18px" title="Privada">○</span>
                                <?php endif; ?>
                            </td>
                            <td style="color:#8A6A55;font-size:12px">
                                <?php echo esc_html( date( 'd/m/Y', strtotime( $pl->created_at ) ) ); ?>
                            </td>
                            <td style="text-align:center">
                                <div style="display:flex;gap:6px;justify-content:center">
                                    <button class="cv-btn cv-btn-outline cv-pl-ver-musicas"
                                            data-id="<?php echo esc_attr( $pl->id ); ?>"
                                            data-nome="<?php echo esc_attr( $pl->name ); ?>"
                                            style="padding:4px 10px;font-size:11px">
                                        🎵 Músicas
                                    </button>
                                    <button class="cv-btn cv-btn-outline cv-pl-renomear"
                                            data-id="<?php echo esc_attr( $pl->id ); ?>"
                                            data-nome="<?php echo esc_attr( $pl->name ); ?>"
                                            style="padding:4px 10px;font-size:11px">
                                        ✏ Renomear
                                    </button>
                                    <button class="cv-btn cv-pl-excluir"
                                            data-id="<?php echo esc_attr( $pl->id ); ?>"
                                            style="padding:4px 10px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3)">
                                        🗑
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

        </div><!-- /.cv-admin-wrap -->

        <!-- Modal: gerenciar músicas da playlist -->
        <div id="cv-pl-modal" style="display:none;position:fixed;inset:0;background:rgba(59,36,24,0.45);z-index:9999;align-items:center;justify-content:center">
            <div style="background:#FFFFFF;border:1px solid rgba(201,162,126,0.4);border-radius:16px;width:90%;max-width:680px;max-height:85vh;overflow:hidden;display:flex;flex-direction:column">

                <div style="display:flex;align-items:center;justify-content:space-between;padding:18px 24px;border-bottom:1px solid rgba(123,58,34,0.12)">
                    <div>
                        <h3 id="cv-pl-modal-titulo" style="font-size:17px;font-weight:700;color:#3B2418;margin:0"></h3>
                        <span id="cv-pl-modal-count" style="font-size:12px;color:#8A6A55"></span>
                    </div>
                    <button id="cv-pl-modal-fechar" style="background:none;border:none;color:#8A6A55;font-size:20px;cursor:pointer;padding:4px 8px">✕</button>
                </div>

                <div style="display:flex;gap:0;flex:1;overflow:hidden">

                    <!-- Músicas na playlist -->
                    <div style="flex:1;overflow-y:auto;padding:16px;border-right:1px solid rgba(123,58,34,0.12)">
                        <p style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Na playlist</p>
                        <div id="cv-pl-musicas-lista" style="display:flex;flex-direction:column;gap:6px">
                            <p style="color:#8A6A55;font-size:13px">Carregando...</p>
                        </div>
                    </div>

                    <!-- Adicionar músicas -->
                    <div style="flex:1;overflow-y:auto;padding:16px">
                        <p style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px">Adicionar música</p>
                        <input type="text" id="cv-pl-busca-musica" placeholder="Filtrar músicas..."
                               style="width:100%;background:#FBF6EE;border:1px solid #EADBC6;border-radius:8px;padding:8px 12px;color:#3B2418;font-size:13px;outline:none;margin-bottom:10px;box-sizing:border-box" />
                        <div id="cv-pl-musicas-disponiveis" style="display:flex;flex-direction:column;gap:4px;max-height:320px;overflow-y:auto">
                            <?php foreach ( $musicas as $musica ) : ?>
                            <div class="cv-pl-musica-item"
                                 data-id="<?php echo esc_attr( $musica->ID ); ?>"
                                 data-titulo="<?php echo esc_attr( strtolower( $musica->post_title ) ); ?>"
                                 style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;background:rgba(123,58,34,0.03);border-radius:6px;cursor:pointer;transition:background .15s">
                                <span style="font-size:13px;color:#6B4C3B"><?php echo esc_html( $musica->post_title ); ?></span>
                                <button class="cv-pl-add-musica cv-btn cv-btn-outline"
                                        data-id="<?php echo esc_attr( $musica->ID ); ?>"
                                        style="padding:3px 10px;font-size:11px;flex-shrink:0">
                                    +
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce   = '<?php echo esc_js( wp_create_nonce( 'cv_playlist_nonce' ) ); ?>';
            var ajaxUrl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';
            var plAtual = 0;

            function showMsg(msg, ok) {
                var $m = $('#cv-pl-msg');
                $m.text(msg)
                  .css({ background: ok ? '#EBF4EB' : '#F4EBEB',
                         border: '1px solid ' + (ok ? '#2d6a2d' : '#6a2d2d'),
                         color:  ok ? '#7fce7f' : '#ce7f7f' })
                  .show();
                setTimeout(function(){ $m.fadeOut(); }, 3500);
            }

            // ── Nova playlist ──────────────────────────────────────
            $('#cv-pl-btn-nova').on('click', function(){
                var isOpen = $(this).data('open') == 1;
                if (isOpen) {
                    $('#cv-pl-form-nova').slideUp(180);
                    $(this).text('+ Nova Playlist Oficial').data('open', 0);
                } else {
                    $('#cv-pl-form-nova').slideDown(180);
                    $(this).text('✕ Ocultar Formulário').data('open', 1);
                }
            });
            $('#cv-pl-cancelar').on('click', function(){
                $('#cv-pl-form-nova').slideUp(180);
                $('#cv-pl-btn-nova').text('+ Nova Playlist Oficial').data('open', 0);
                $('#cv-pl-nome').val('');
                $('#cv-pl-desc').val('');
                $('#cv-pl-publica').prop('checked', false);
            });

            $('#cv-pl-salvar').on('click', function(){
                var nome = $.trim($('#cv-pl-nome').val());
                if (!nome) { alert('Informe o nome da playlist.'); return; }
                var $btn = $(this).prop('disabled', true).text('Criando...');

                $.post(ajaxUrl, {
                    action:      'cv_playlist_create',
                    nonce:       nonce,
                    name:        nome,
                    description: $.trim($('#cv-pl-desc').val()),
                    is_public:   $('#cv-pl-publica').is(':checked') ? 1 : 0,
                    is_featured: $('#cv-pl-destaque').is(':checked') ? 1 : 0,
                    cover_url:   $.trim($('#cv-pl-capa-url').val()),
                }, function(res){
                    if (res.success) {
                        showMsg('✅ Playlist "' + nome + '" criada!', true);
                        setTimeout(function(){ location.reload(); }, 1200);
                    } else {
                        showMsg('Erro: ' + (res.data && res.data.message ? res.data.message : 'tente novamente.'), false);
                        $btn.prop('disabled', false).text('💾 Criar Playlist');
                    }
                });
            });

            // ── Renomear ───────────────────────────────────────────
            $(document).on('click', '.cv-pl-renomear', function(){
                var id   = $(this).data('id');
                var nome = $(this).data('nome');
                var novo = prompt('Novo nome da playlist:', nome);
                if (!novo || !novo.trim() || novo.trim() === nome) return;

                $.post(ajaxUrl, {
                    action:      'cv_playlist_rename',
                    nonce:       nonce,
                    playlist_id: id,
                    name:        $.trim(novo),
                }, function(res){
                    if (res.success) {
                        showMsg('✅ Renomeada com sucesso!', true);
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        showMsg('Erro ao renomear.', false);
                    }
                });
            });

            // ── Excluir ────────────────────────────────────────────
            $(document).on('click', '.cv-pl-excluir', function(){
                var id = $(this).data('id');
                if (!confirm('Excluir esta playlist e todas as suas músicas? Esta ação não pode ser desfeita.')) return;

                $.post(ajaxUrl, {
                    action:      'cv_playlist_delete',
                    nonce:       nonce,
                    playlist_id: id,
                }, function(res){
                    if (res.success) {
                        $('#cv-pl-row-' + id).fadeOut(300, function(){ $(this).remove(); });
                        showMsg('✅ Playlist excluída.', true);
                    } else {
                        showMsg('Erro ao excluir.', false);
                    }
                });
            });

            // ── Modal: ver e gerenciar músicas ─────────────────────
            $(document).on('click', '.cv-pl-ver-musicas', function(){
                plAtual = $(this).data('id');
                var nome = $(this).data('nome');
                $('#cv-pl-modal-titulo').text('📋 ' + nome);
                $('#cv-pl-modal').css('display', 'flex');
                carregarMusicasPlaylist(plAtual);
            });

            $('#cv-pl-modal-fechar').on('click', function(){
                $('#cv-pl-modal').hide();
                plAtual = 0;
            });

            // Fecha ao clicar fora
            $('#cv-pl-modal').on('click', function(e){
                if ($(e.target).is('#cv-pl-modal')) { $(this).hide(); plAtual = 0; }
            });

            function carregarMusicasPlaylist(plId) {
                $('#cv-pl-musicas-lista').html('<p style="color:#8A6A55;font-size:13px">Carregando...</p>');

                // Busca músicas via endpoint REST do plugin
                $.get('<?php echo esc_js( rest_url( 'cv/v1/ranking/top' ) ); ?>', function(){})
                 .always(function(){
                    // Fallback: busca direta no banco via AJAX admin
                    $.post(ajaxUrl, {
                        action:      'cv_admin_get_playlist_items',
                        nonce:       '<?php echo esc_js( wp_create_nonce( "cv_admin_nonce" ) ); ?>',
                        playlist_id: plId,
                    }, function(res){
                        if (res.success && res.data.items) {
                            renderMusicasPlaylist(res.data.items, plId);
                            $('#cv-pl-modal-count').text(res.data.items.length + ' música(s)');
                            $('#cv-pl-count-' + plId).text(res.data.items.length);
                        } else {
                            $('#cv-pl-musicas-lista').html('<p style="color:#8A6A55;font-size:13px">Nenhuma música ainda.</p>');
                        }
                    });
                 });
            }

            function renderMusicasPlaylist(items, plId) {
                if (!items.length) {
                    $('#cv-pl-musicas-lista').html('<p style="color:#8A6A55;font-size:13px;font-style:italic">Playlist vazia. Adicione músicas ao lado.</p>');
                    return;
                }
                var html = '';
                $.each(items, function(i, item){
                    html += '<div style="display:flex;align-items:center;gap:8px;padding:7px 10px;background:rgba(123,58,34,0.03);border-radius:6px">'
                          + '<span style="font-size:11px;color:#8A6A55;min-width:18px">' + (i+1) + '</span>'
                          + '<span style="flex:1;font-size:13px;color:#6B4C3B;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + escHtml(item.title) + '</span>'
                          + '<button class="cv-pl-remover-musica cv-btn" data-pl="' + plId + '" data-music="' + item.id + '"'
                          + ' style="padding:3px 8px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3);flex-shrink:0">✕</button>'
                          + '</div>';
                });
                $('#cv-pl-musicas-lista').html(html);
            }

            // Adicionar música
            $(document).on('click', '.cv-pl-add-musica', function(e){
                e.stopPropagation();
                if (!plAtual) return;
                var musicId = $(this).data('id');
                var $btn = $(this).prop('disabled', true).text('...');

                $.post(ajaxUrl, {
                    action:      'cv_playlist_add_music',
                    nonce:       nonce,
                    playlist_id: plAtual,
                    music_id:    musicId,
                }, function(res){
                    if (res.success) {
                        carregarMusicasPlaylist(plAtual);
                    } else {
                        alert(res.data && res.data.message ? res.data.message : 'Erro ao adicionar.');
                    }
                    $btn.prop('disabled', false).text('+');
                });
            });

            // Remover música
            $(document).on('click', '.cv-pl-remover-musica', function(){
                var plId    = $(this).data('pl');
                var musicId = $(this).data('music');
                var $btn    = $(this).prop('disabled', true).text('...');

                $.post(ajaxUrl, {
                    action:      'cv_playlist_remove_music',
                    nonce:       nonce,
                    playlist_id: plId,
                    music_id:    musicId,
                }, function(res){
                    if (res.success) {
                        carregarMusicasPlaylist(plId);
                    } else {
                        $btn.prop('disabled', false).text('✕');
                    }
                });
            });

            // Media Library para capa da playlist
            $('#cv-pl-capa-media').on('click', function(e){
                e.preventDefault();
                if (typeof wp === 'undefined' || !wp.media) {
                    alert('A biblioteca de mídia não está disponível. Cole a URL diretamente.');
                    return;
                }
                var frame = wp.media({
                    title:    'Escolher capa da playlist',
                    button:   { text: 'Usar esta imagem' },
                    multiple: false,
                    library:  { type: 'image' },
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#cv-pl-capa-url').val(attachment.url);
                    $('#cv-pl-capa-img').attr('src', attachment.url);
                    $('#cv-pl-capa-preview').show();
                });
                frame.open();
            });

            // Preview ao digitar URL manualmente
            $('#cv-pl-capa-url').on('change', function(){
                var url = $.trim($(this).val());
                if (url) {
                    $('#cv-pl-capa-img').attr('src', url);
                    $('#cv-pl-capa-preview').show();
                } else {
                    $('#cv-pl-capa-preview').hide();
                }
            });

            // Filtro de busca no modal
            $('#cv-pl-busca-musica').on('input', function(){
                var termo = $(this).val().toLowerCase();
                $('.cv-pl-musica-item').each(function(){
                    var titulo = $(this).data('titulo') || '';
                    $(this).toggle( titulo.indexOf(termo) !== -1 );
                });
            });

            function escHtml(str) {
                return $('<div>').text(str).html();
            }
        });
        </script>
        <?php
    }
}
