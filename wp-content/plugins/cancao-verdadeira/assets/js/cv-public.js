/* cancao-verdadeira-plugin/assets/js/cv-public.js
   Gerado em: 2026-06-13 00:00:00
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   Script público do plugin. Gerencia via AJAX: contagem de plays (com
   timer de 30 segundos), toggle de favoritos, avaliação por estrelas,
   adição a playlists e inscrição na newsletter. Todos os dados são
   recebidos do PHP via cvPublic (wp_localize_script em class-cv-public.php).
   Compatível com PHP 7.4+. Não depende de ES6 — usa jQuery (disponível
   via WordPress). O player YouTube é gerenciado pelo tema filho.
*/

(function ($) {
    'use strict';

    // ── Variáveis globais do módulo ───────────────────────────────
    var CV = {
        playTimer:      null,   // timer dos 30 segundos de play válido
        playRegistered: false,  // evita registrar o mesmo play duas vezes
        currentMusicId: cvPublic.currentMusicId || 0,
    };

    // ════════════════════════════════════════════════════════════════
    // 1. SISTEMA DE PLAYS
    // ════════════════════════════════════════════════════════════════

    /**
     * Inicia o timer de play válido para uma música.
     * Deve ser chamado pelo player do tema ao iniciar reprodução.
     * Exemplo de uso no tema:
     *   CV_Public.startPlayTimer(musicId);
     *
     * @param {number} musicId  ID do post WordPress da música
     */
    function startPlayTimer(musicId) {
        // Cancela timer anterior se existir (troca de música)
        stopPlayTimer();

        CV.playRegistered  = false;
        CV.currentMusicId  = musicId;

        var seconds = parseInt( cvPublic.playSeconds, 10 ) || 30;

        CV.playTimer = setTimeout( function () {
            registerPlay( musicId );
        }, seconds * 1000 );
    }

    /**
     * Para o timer sem registrar o play.
     * Deve ser chamado quando o usuário pausa antes dos 30 segundos.
     */
    function stopPlayTimer() {
        if ( CV.playTimer ) {
            clearTimeout( CV.playTimer );
            CV.playTimer = null;
        }
    }

    /**
     * Registra o play via AJAX no servidor.
     */
    function registerPlay(musicId) {
        if ( CV.playRegistered ) { return; }
        CV.playRegistered = true;

        $.ajax({
            url:    cvPublic.ajaxUrl,
            method: 'POST',
            data: {
                action:   'cv_register_play',
                nonce:    cvPublic.nonces.play,
                music_id: musicId,
            },
            success: function (res) {
                if ( res.success ) {
                    // Atualiza o contador de plays visível na página
                    $('.cv-play-count[data-music-id="' + musicId + '"]')
                        .text( res.data.plays );
                }
            },
        });
    }

    // ════════════════════════════════════════════════════════════════
    // 2. SISTEMA DE FAVORITOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Delega o clique no botão de favorito.
     * HTML esperado: <button class="cv-btn-favorite" data-music-id="123">❤</button>
     */
    $(document).on( 'click', '.cv-btn-favorite', function (e) {
        e.preventDefault();

        if ( ! cvPublic.isLoggedIn ) {
            showToast( cvPublic.i18n.loginRequired, 'warning' );
            return;
        }

        var $btn     = $(this);
        var musicId  = parseInt( $btn.data('music-id'), 10 );

        if ( ! musicId ) { return; }

        $btn.prop( 'disabled', true );

        $.ajax({
            url:    cvPublic.ajaxUrl,
            method: 'POST',
            data: {
                action:   'cv_toggle_favorite',
                nonce:    cvPublic.nonces.favorite,
                music_id: musicId,
            },
            success: function (res) {
                if ( res.success ) {
                    var isFav = res.data.favorited;

                    // Atualiza visual do botão
                    $btn.toggleClass( 'cv-favorited', isFav );
                    $btn.attr( 'aria-pressed', isFav ? 'true' : 'false' );

                    // Atualiza contador de favoritos na página
                    $('.cv-fav-count[data-music-id="' + musicId + '"]')
                        .text( res.data.total );

                    showToast(
                        isFav
                            ? cvPublic.i18n.favoriteAdded
                            : cvPublic.i18n.favoriteRemoved,
                        isFav ? 'success' : 'info'
                    );
                } else {
                    showToast( res.data.message || cvPublic.i18n.error, 'error' );
                }
            },
            error: function () {
                showToast( cvPublic.i18n.error, 'error' );
            },
            complete: function () {
                $btn.prop( 'disabled', false );
            },
        });
    });

    // ════════════════════════════════════════════════════════════════
    // 3. SISTEMA DE AVALIAÇÃO POR ESTRELAS
    // ════════════════════════════════════════════════════════════════

    /**
     * HTML esperado para o componente de estrelas:
     * <div class="cv-stars" data-music-id="123">
     *   <span class="cv-star" data-value="1">★</span>
     *   <span class="cv-star" data-value="2">★</span>
     *   ...
     *   <span class="cv-star" data-value="5">★</span>
     * </div>
     */

    // Hover: ilumina estrelas ao passar o mouse
    $(document).on( 'mouseenter', '.cv-stars .cv-star', function () {
        var value   = parseInt( $(this).data('value'), 10 );
        var $parent = $(this).closest('.cv-stars');

        $parent.find('.cv-star').each( function () {
            $(this).toggleClass( 'cv-star-hover', parseInt( $(this).data('value'), 10 ) <= value );
        });
    });

    // Mouse sai: remove hover
    $(document).on( 'mouseleave', '.cv-stars', function () {
        $(this).find('.cv-star').removeClass('cv-star-hover');
    });

    // Clique: envia avaliação
    $(document).on( 'click', '.cv-stars .cv-star', function () {
        if ( ! cvPublic.isLoggedIn ) {
            showToast( cvPublic.i18n.loginRequired, 'warning' );
            return;
        }

        var $star    = $(this);
        var rating   = parseInt( $star.data('value'), 10 );
        var $parent  = $star.closest('.cv-stars');
        var musicId  = parseInt( $parent.data('music-id'), 10 );

        if ( ! musicId || rating < 1 || rating > 5 ) { return; }

        $parent.addClass('cv-stars-loading');

        $.ajax({
            url:    cvPublic.ajaxUrl,
            method: 'POST',
            data: {
                action:   'cv_rate_music',
                nonce:    cvPublic.nonces.rating,
                music_id: musicId,
                rating:   rating,
            },
            success: function (res) {
                if ( res.success ) {
                    // Marca estrelas selecionadas
                    $parent.find('.cv-star').each( function () {
                        $(this).toggleClass(
                            'cv-star-selected',
                            parseInt( $(this).data('value'), 10 ) <= rating
                        );
                    });

                    // Atualiza média exibida
                    $parent.siblings('.cv-avg-rating').text( res.data.avg );

                    showToast( cvPublic.i18n.ratingSuccess, 'success' );
                } else {
                    showToast( res.data.message || cvPublic.i18n.error, 'error' );
                }
            },
            error: function () {
                showToast( cvPublic.i18n.error, 'error' );
            },
            complete: function () {
                $parent.removeClass('cv-stars-loading');
            },
        });
    });

    // ════════════════════════════════════════════════════════════════
    // 4. SISTEMA DE PLAYLISTS (Modal de adição rápida)
    // ════════════════════════════════════════════════════════════════

    /**
     * Botão para abrir o modal de playlists.
     * HTML: <button class="cv-btn-add-playlist" data-music-id="123">+ Playlist</button>
     */
    $(document).on( 'click', '.cv-btn-add-playlist', function (e) {
        e.preventDefault();

        if ( ! cvPublic.isLoggedIn ) {
            showToast( cvPublic.i18n.loginRequired, 'warning' );
            return;
        }

        var musicId = parseInt( $(this).data('music-id'), 10 );
        if ( ! musicId ) { return; }

        openPlaylistModal( musicId );
    });

    /**
     * Abre o modal de playlists para uma música.
     */
    function openPlaylistModal(musicId) {
        var $modal = $( '#cv-playlist-modal' );

        // Cria o modal se não existir
        if ( ! $modal.length ) {
            $modal = $( buildPlaylistModalHtml() );
            $('body').append( $modal );
            bindPlaylistModalEvents();
        }

        $modal.data( 'music-id', musicId );
        $modal.find('.cv-modal-body').html('<p class="cv-loading">Carregando playlists...</p>');
        $modal.fadeIn(200);
        $('body').addClass('cv-modal-open');

        // Busca playlists do usuário
        $.ajax({
            url:    cvPublic.ajaxUrl,
            method: 'POST',
            data: {
                action: 'cv_get_playlists_for_modal',
                nonce:  cvPublic.nonces.playlist,
            },
            success: function (res) {
                if ( res.success ) {
                    renderPlaylistList( $modal, res.data.playlists, musicId );
                } else {
                    $modal.find('.cv-modal-body').html('<p>' + cvPublic.i18n.error + '</p>');
                }
            },
            error: function () {
                $modal.find('.cv-modal-body').html('<p>' + cvPublic.i18n.error + '</p>');
            },
        });
    }

    function buildPlaylistModalHtml() {
        return '<div id="cv-playlist-modal" class="cv-modal" role="dialog" aria-modal="true" aria-label="Adicionar à Playlist">'
            + '<div class="cv-modal-overlay"></div>'
            + '<div class="cv-modal-box">'
            + '<div class="cv-modal-header">'
            + '<h3>Adicionar à Playlist</h3>'
            + '<button class="cv-modal-close" aria-label="Fechar">✕</button>'
            + '</div>'
            + '<div class="cv-modal-body"></div>'
            + '</div>'
            + '</div>';
    }

    function renderPlaylistList($modal, playlists, musicId) {
        var html = '';

        if ( playlists && playlists.length ) {
            html += '<ul class="cv-playlist-list">';
            $.each( playlists, function (i, pl) {
                html += '<li>'
                    + '<button class="cv-playlist-pick" data-playlist-id="' + pl.id + '">'
                    + '<span class="cv-pl-name">' + $('<div>').text(pl.name).html() + '</span>'
                    + '<span class="cv-pl-count">' + pl.count + ' música' + ( pl.count !== 1 ? 's' : '' ) + '</span>'
                    + '</button>'
                    + '</li>';
            });
            html += '</ul>';
        } else {
            html += '<p class="cv-pl-empty">Você ainda não tem playlists.</p>';
        }

        html += '<div class="cv-pl-new">'
            + '<input type="text" id="cv-pl-new-name" placeholder="Nova playlist..." maxlength="100" />'
            + '<button id="cv-pl-create-btn">Criar e adicionar</button>'
            + '</div>';

        $modal.find('.cv-modal-body').html(html);

        // Clique em playlist existente
        $modal.find('.cv-playlist-pick').on( 'click', function () {
            var plId = parseInt( $(this).data('playlist-id'), 10 );
            quickAddToPlaylist( musicId, plId, '', $modal );
        });

        // Criar nova playlist e adicionar
        $modal.find('#cv-pl-create-btn').on( 'click', function () {
            var name = $.trim( $modal.find('#cv-pl-new-name').val() );
            if ( ! name ) {
                $modal.find('#cv-pl-new-name').focus();
                return;
            }
            quickAddToPlaylist( musicId, 0, name, $modal );
        });
    }

    function quickAddToPlaylist(musicId, playlistId, newName, $modal) {
        $.ajax({
            url:    cvPublic.ajaxUrl,
            method: 'POST',
            data: {
                action:      'cv_quick_add_to_playlist',
                nonce:       cvPublic.nonces.playlist,
                music_id:    musicId,
                playlist_id: playlistId,
                new_name:    newName,
            },
            success: function (res) {
                if ( res.success ) {
                    closeModal( $modal );
                    showToast( res.data.message || cvPublic.i18n.playlistAdded, 'success' );
                } else {
                    showToast( res.data.message || cvPublic.i18n.error, 'error' );
                }
            },
            error: function () {
                showToast( cvPublic.i18n.error, 'error' );
            },
        });
    }

    function bindPlaylistModalEvents() {
        $(document).on( 'click', '#cv-playlist-modal .cv-modal-close', function () {
            closeModal( $( '#cv-playlist-modal' ) );
        });

        $(document).on( 'click', '#cv-playlist-modal .cv-modal-overlay', function () {
            closeModal( $( '#cv-playlist-modal' ) );
        });

        $(document).on( 'keydown', function (e) {
            if ( e.key === 'Escape' ) {
                closeModal( $( '#cv-playlist-modal' ) );
            }
        });
    }

    function closeModal($modal) {
        $modal.fadeOut(150);
        $('body').removeClass('cv-modal-open');
    }

    // ════════════════════════════════════════════════════════════════
    // 5. NEWSLETTER
    // ════════════════════════════════════════════════════════════════

    /**
     * Formulário de newsletter.
     * HTML: <form class="cv-newsletter-form">
     *         <input type="email" name="cv_email" required />
     *         <input type="text"  name="cv_name" />
     *         <select name="cv_genre">...</select>
     *         <button type="submit">Assinar</button>
     *       </form>
     */
    $(document).on( 'submit', '.cv-newsletter-form', function (e) {
        e.preventDefault();

        var $form  = $(this);
        var $btn   = $form.find('[type="submit"]');
        var $msg   = $form.find('.cv-newsletter-msg');

        var email  = $.trim( $form.find('[name="cv_email"]').val() );
        var name   = $.trim( $form.find('[name="cv_name"]').val() );
        var genre  = $form.find('[name="cv_genre"]').val() || '';

        if ( ! email ) { return; }

        $btn.prop('disabled', true);

        $.ajax({
            url:    cvPublic.ajaxUrl,
            method: 'POST',
            data: {
                action: 'cv_subscribe',
                nonce:  cvPublic.nonces.newsletter,
                email:  email,
                name:   name,
                genre:  genre,
            },
            success: function (res) {
                if ( res.success ) {
                    $form.find('input, select').val('');
                    if ( $msg.length ) {
                        $msg.text( res.data.message ).addClass('cv-msg-success').show();
                    } else {
                        showToast( res.data.message, 'success' );
                    }
                } else {
                    var errMsg = res.data.message || cvPublic.i18n.newsletterError;
                    if ( $msg.length ) {
                        $msg.text( errMsg ).addClass('cv-msg-error').show();
                    } else {
                        showToast( errMsg, 'error' );
                    }
                }
            },
            error: function () {
                showToast( cvPublic.i18n.newsletterError, 'error' );
            },
            complete: function () {
                $btn.prop('disabled', false);
            },
        });
    });

    // ════════════════════════════════════════════════════════════════
    // 6. TOAST DE FEEDBACK
    // ════════════════════════════════════════════════════════════════

    /**
     * Exibe uma mensagem flutuante de feedback.
     * @param {string} message  Texto da mensagem
     * @param {string} type     'success' | 'error' | 'warning' | 'info'
     */
    function showToast(message, type) {
        var $container = $( '#cv-toast-container' );

        if ( ! $container.length ) {
            $container = $( '<div id="cv-toast-container" aria-live="polite"></div>' );
            $('body').append( $container );
        }

        var $toast = $( '<div class="cv-toast cv-toast-' + ( type || 'info' ) + '">' + message + '</div>' );
        $container.append( $toast );

        setTimeout( function () { $toast.addClass('cv-toast-visible'); }, 10 );

        setTimeout( function () {
            $toast.removeClass('cv-toast-visible');
            setTimeout( function () { $toast.remove(); }, 300 );
        }, 3500 );
    }

    // ════════════════════════════════════════════════════════════════
    // 7. API PÚBLICA — acessível pelo tema via window.CV_Public
    // ════════════════════════════════════════════════════════════════

    /**
     * Expõe funções que o tema filho precisará chamar,
     * especialmente para controlar o player YouTube.
     *
     * Uso no tema (quando o player iniciar uma música):
     *   CV_Public.startPlayTimer(musicId);
     *
     * Uso no tema (quando o usuário pausar):
     *   CV_Public.stopPlayTimer();
     */
    window.CV_Public = {
        startPlayTimer: startPlayTimer,
        stopPlayTimer:  stopPlayTimer,
        showToast:      showToast,
    };

    // ── Init ──────────────────────────────────────────────────────
    $(function () {
        // Nada a inicializar por padrão.
        // As funções são ativadas por eventos de clique e pelo tema.
    });

}(jQuery));

// ═══════════════════════════════════════════════════════════════════
// 8. SISTEMA DE NOTIFICAÇÕES (adicionado v2.4)
// Carrega o sino de notificações e o painel dropdown.
// Funciona tanto no header do tema atual quanto no tema filho Astra.
// ═══════════════════════════════════════════════════════════════════

(function($) {
    'use strict';

    if ( ! cvPublic.isLoggedIn ) { return; }

    var NOTIF_NONCE = cvPublic.nonces.notification;
    var AJAX        = cvPublic.ajaxUrl;
    var pollTimer   = null;

    // ── Carrega notificações via AJAX ────────────────────────────
    function loadNotifications() {
        $.post( AJAX, {
            action: 'cv_get_notifications',
            nonce:  NOTIF_NONCE
        }, function(res) {
            if ( ! res.success ) { return; }

            var notifs  = res.data.notifications || [];
            var unread  = res.data.unread || 0;

            // Atualiza badges (funciona em qualquer layout de tema)
            $('[data-cv-notif-badge]').each(function() {
                if ( unread > 0 ) {
                    $(this).text(unread).show();
                } else {
                    $(this).hide();
                }
            });
            // IDs específicos do tema atual (retrocompatibilidade)
            $('#cv-sidebar-badge, #cv-topbar-badge').each(function() {
                if ( unread > 0 ) { $(this).text(unread).show(); }
                else { $(this).hide(); }
            });

            // Preenche o painel de notificações
            var $list = $('[data-cv-notif-list], #cv-notif-list');
            if ( $list.length ) {
                if ( notifs.length === 0 ) {
                    $list.html('<p style="color:#555;font-size:13px;text-align:center;padding:24px">Nenhuma notificação</p>');
                } else {
                    var html = '';
                    notifs.forEach(function(n) {
                        var readClass = n.read ? 'cv-notif-read' : 'cv-notif-unread';
                        html += '<div class="cv-notif-item ' + readClass + '" data-notif-id="' + n.id + '">';
                        html += '<span class="cv-notif-icon">' + (n.icon || '🎵') + '</span>';
                        html += '<div class="cv-notif-body">';
                        if (n.url) {
                            html += '<a href="' + n.url + '" class="cv-notif-msg">' + n.message + '</a>';
                        } else {
                            html += '<span class="cv-notif-msg">' + n.message + '</span>';
                        }
                        html += '<span class="cv-notif-time">' + (n.time_human || '') + '</span>';
                        html += '</div></div>';
                    });
                    $list.html(html);
                }
            }
        });
    }

    // ── Marcar notificação como lida ao clicar ───────────────────
    $(document).on('click', '.cv-notif-item[data-notif-id]', function() {
        var id = $(this).data('notif-id');
        $(this).removeClass('cv-notif-unread').addClass('cv-notif-read');
        $.post( AJAX, { action: 'cv_mark_notif_read', nonce: NOTIF_NONCE, notif_id: id });
    });

    // ── Marcar todas como lidas ──────────────────────────────────
    $(document).on('click', '#cv-notif-mark-all, [data-cv-mark-all-notif]', function() {
        $('[data-cv-notif-badge], #cv-sidebar-badge, #cv-topbar-badge').hide();
        $('[data-cv-notif-list] .cv-notif-item, #cv-notif-list .cv-notif-item')
            .removeClass('cv-notif-unread').addClass('cv-notif-read');
        $.post( AJAX, { action: 'cv_mark_notif_read', nonce: NOTIF_NONCE, notif_id: 'all' });
    });

    // ── Toggle do painel (sino) ──────────────────────────────────
    $(document).on('click', '[data-cv-bell], #cv-sidebar-bell, #cv-topbar-bell', function(e) {
        e.stopPropagation();
        var $panel = $('[data-cv-notif-panel], #cv-notif-panel');
        $panel.toggle();
        if ( $panel.is(':visible') ) { loadNotifications(); }
    });

    // ── Fecha painel ao clicar fora ──────────────────────────────
    $(document).on('click', function(e) {
        if ( ! $(e.target).closest('[data-cv-notif-panel], #cv-notif-panel, [data-cv-bell], #cv-sidebar-bell').length ) {
            $('[data-cv-notif-panel], #cv-notif-panel').hide();
        }
    });

    $(document).on('click', '#cv-notif-close, [data-cv-notif-close]', function() {
        $('[data-cv-notif-panel], #cv-notif-panel').hide();
    });

    // ── Polling a cada 60 segundos ───────────────────────────────
    function startPolling() {
        loadNotifications(); // carrega imediatamente
        pollTimer = setInterval( loadNotifications, 60000 );
    }

    // ── Inicia quando o DOM estiver pronto ───────────────────────
    $(function() {
        startPolling();
    });

    // Expõe para o tema filho usar
    window.CV_Public.loadNotifications = loadNotifications;

}(jQuery));
