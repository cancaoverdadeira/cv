/* cancao-verdadeira-child/assets/js/cv-theme.js
   Gerado em: 2026-06-22 00:30:00
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   UI principal do tema filho: sidebar mobile, parallax do hero,
   autocomplete de busca, toasts de feedback e helpers globais.
   O parallax usa requestAnimationFrame para suavidade máxima e
   IntersectionObserver para parar quando o hero sai da tela (performance). */

(function($) {
    'use strict';

    // ═══════════════════════════════════════════════════════════════
    // 1. PARALLAX DO HERO
    // Efeito de profundidade no banner da home page.
    // Funciona em desktop E mobile (ao contrário do CSS fixed).
    // Velocidade: 0.35 = fundo move 35% da velocidade do scroll.
    // ═══════════════════════════════════════════════════════════════

    var ParallaxHero = {
        $hero   : null,
        $bg     : null,
        ticking : false,
        visible : true,
        speed   : 0.4,   // 40% — mais perceptível

        init: function() {
            var self = this;
            self.$hero = $('.cv-hero');
            self.$bg   = $('.cv-hero-bg');

            if ( ! self.$hero.length || ! self.$bg.length ) { return; }

            // Respeita preferência de acessibilidade
            if ( window.matchMedia('(prefers-reduced-motion: reduce)').matches ) { return; }

            // Só roda enquanto o hero está visível na tela
            if ( window.IntersectionObserver ) {
                new IntersectionObserver(function(entries) {
                    self.visible = entries[0].isIntersecting;
                    if ( self.visible ) { self.update(); }
                }, { rootMargin: '100px 0px' }).observe( self.$hero[0] );
            }

            // Dispara imediatamente
            self.update();

            // Scroll com rAF — passive:true não trava o dedo no mobile
            window.addEventListener('scroll', function() {
                if ( ! self.ticking ) {
                    window.requestAnimationFrame(function() {
                        if ( self.visible ) { self.update(); }
                        self.ticking = false;
                    });
                    self.ticking = true;
                }
            }, { passive: true });

            window.addEventListener('resize', function() {
                requestAnimationFrame(function() { self.update(); });
            }, { passive: true });
        },

        update: function() {
            if ( ! this.$bg || ! this.$bg.length ) { return; }

            var rect    = this.$hero[0].getBoundingClientRect();
            var windowH = window.innerHeight;

            // Hero fora da viewport — não processa
            if ( rect.bottom < 0 || rect.top > windowH ) { return; }

            // Posição relativa do centro do hero em relação ao centro da tela
            // 0 = hero centrado na tela, positivo = hero abaixo, negativo = acima
            var heroCenter   = rect.top + rect.height / 2;
            var screenCenter = windowH / 2;
            var offset       = heroCenter - screenCenter;

            // O fundo se move na direção oposta ao offset, mais devagar
            var translateY = offset * this.speed * -1;

            // Limita: máximo 20% da altura do hero
            var max = rect.height * 0.20;
            translateY = Math.max( -max, Math.min( max, translateY ) );

            this.$bg[0].style.transform = 'translateY(' + translateY.toFixed(2) + 'px)';
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 2. SIDEBAR MOBILE
    // Abre/fecha com hamburger, fecha ao clicar fora ou pressionar Esc.
    // ═══════════════════════════════════════════════════════════════

    var Sidebar = {
        init: function() {
            var $sidebar    = $('#cv-sidebar');
            var $overlay    = $('#cv-overlay');
            var $hamburger  = $('#cv-hamburger');

            if ( ! $sidebar.length ) { return; }

            function open() {
                $sidebar.addClass('open');
                $overlay.addClass('active');
                $hamburger.attr('aria-expanded', 'true');
                $('body').css('overflow', 'hidden'); // evita scroll por baixo
            }

            function close() {
                $sidebar.removeClass('open');
                $overlay.removeClass('active');
                $hamburger.attr('aria-expanded', 'false');
                $('body').css('overflow', '');
            }

            $hamburger.on('click', function() {
                $sidebar.hasClass('open') ? close() : open();
            });

            $overlay.on('click', close);

            $(document).on('keydown', function(e) {
                if ( e.key === 'Escape' && $sidebar.hasClass('open') ) { close(); }
            });

            // Toggle busca na sidebar
            $('.cv-search-trigger').on('click', function(e) {
                e.preventDefault();
                var $wrap = $('#cv-sidebar-search-wrap');
                $wrap.slideToggle(180, function() {
                    if ( $wrap.is(':visible') ) { $wrap.find('input').focus(); }
                });
            });

            // Toggle busca na topbar mobile
            $('#cv-topbar-search-toggle').on('click', function() {
                var $panel = $('#cv-topbar-search-panel');
                $panel.slideToggle(180, function() {
                    if ( $panel.is(':visible') ) { $('#cv-topbar-search-input').focus(); }
                });
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 3. AUTOCOMPLETE DE BUSCA
    // Busca em tempo real conforme o usuário digita (debounce 280ms).
    // ═══════════════════════════════════════════════════════════════

    var Search = {
        timer: null,

        init: function() {
            var self = this;

            $(document).on('input', '.cv-search-input', function() {
                var $input = $(this);
                var term   = $input.val().trim();
                var $list  = $input.closest('.cv-search-wrap').find('.cv-autocomplete-list');

                clearTimeout(self.timer);

                if ( term.length < 2 ) {
                    $list.hide().empty();
                    return;
                }

                self.timer = setTimeout(function() {
                    self.fetch(term, $list);
                }, 280);
            });

            // Fecha ao clicar fora
            $(document).on('click', function(e) {
                if ( ! $(e.target).closest('.cv-search-wrap').length ) {
                    $('.cv-autocomplete-list').hide();
                }
            });

            // Navega com teclado
            $(document).on('keydown', '.cv-search-input', function(e) {
                var $list  = $(this).closest('.cv-search-wrap').find('.cv-autocomplete-list');
                var $items = $list.find('.cv-autocomplete-item');
                var $active = $items.filter('.cv-ac-active');

                if ( e.key === 'ArrowDown' ) {
                    e.preventDefault();
                    if ( ! $active.length ) {
                        $items.first().addClass('cv-ac-active');
                    } else {
                        $active.removeClass('cv-ac-active').next().addClass('cv-ac-active');
                    }
                } else if ( e.key === 'ArrowUp' ) {
                    e.preventDefault();
                    $active.removeClass('cv-ac-active').prev().addClass('cv-ac-active');
                } else if ( e.key === 'Enter' ) {
                    e.preventDefault();
                    if ( $active.length ) {
                        window.location.href = $active.attr('href');
                    } else {
                        // Sem sugestão escolhida: abre a página de resultados.
                        var term = $.trim( $(this).val() );
                        if ( term.length >= 2 && window.cvPublic && cvPublic.searchPage ) {
                            window.location.href = cvPublic.searchPage + '?q=' + encodeURIComponent(term);
                        }
                    }
                } else if ( e.key === 'Escape' ) {
                    $list.hide();
                }
            });
        },

        fetch: function(term, $list) {
            if ( ! window.cvPublic ) { return; }

            $.get(cvPublic.ajaxUrl, {
                action : 'cv_autocomplete',
                term   : term,
                nonce  : cvPublic.nonces.autocomplete
            }, function(res) {
                if ( ! res.success || ! res.data.results.length ) {
                    $list.hide().empty();
                    return;
                }

                var esc = function(s) { return $('<div>').text(s || '').html(); };
                var html = '';
                res.data.results.forEach(function(r) {
                    html += '<a href="' + esc(r.url) + '" class="cv-autocomplete-item">'
                          + '<img class="cv-autocomplete-cover" src="' + esc(r.cover) + '" alt="" loading="lazy">'
                          + '<div>'
                          + '<div class="cv-autocomplete-title">' + esc(r.title) + '</div>'
                          + '<div class="cv-autocomplete-artist">' + esc(r.artist) + '</div>'
                          + '</div>'
                          + '</a>';
                });
                // Link para a página com todos os resultados
                if ( res.data.all_url ) {
                    html += '<a href="' + esc(res.data.all_url) + '" class="cv-autocomplete-item cv-autocomplete-all">'
                          + '🔍 Ver todos os resultados' + ( res.data.total > res.data.results.length ? ' (' + res.data.total + ')' : '' )
                          + '</a>';
                }

                $list.html(html).show();
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 4. TOASTS — feedback visual global
    // Uso: CV_Theme.toast('Mensagem', 'success') ou 'error', 'info', 'warning'
    // ═══════════════════════════════════════════════════════════════

    var Toast = {
        show: function(msg, type, duration) {
            type     = type     || 'info';
            duration = duration || 3000;

            var $container = $('#cv-toast-container');
            if ( ! $container.length ) { return; }

            var $toast = $('<div class="cv-toast cv-toast-' + type + '">' + msg + '</div>');
            $container.append($toast);

            // Anima entrada
            setTimeout(function() { $toast.addClass('cv-toast-visible'); }, 10);

            // Remove após duração
            setTimeout(function() {
                $toast.removeClass('cv-toast-visible');
                setTimeout(function() { $toast.remove(); }, 350);
            }, duration);
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 5. LINK ATIVO NA SIDEBAR
    // Destaca o item do menu que corresponde à URL atual.
    // ═══════════════════════════════════════════════════════════════

    var ActiveNav = {
        init: function() {
            var current = window.location.href;

            $('.cv-nav-link').each(function() {
                var href = $(this).attr('href');
                if ( ! href || href === '#' ) { return; }

                // Match exato ou sub-página
                if ( current === href || (href !== window.location.origin + '/' && current.indexOf(href) === 0) ) {
                    $(this).addClass('active');
                }
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 6. FAVORITOS — atualiza UI quando o plugin faz o toggle
    // ═══════════════════════════════════════════════════════════════

    var Favorites = {
        init: function() {
            $(document).on('click', '.cv-btn-favorite', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if ( ! window.cvPublic || ! cvPublic.isLoggedIn ) {
                    Toast.show(
                        '<a href="' + (cvPublic && cvPublic.loginUrl ? cvPublic.loginUrl : '/login/') + '" style="color:var(--cv-gold)">Faça login</a> para favoritar músicas.',
                        'info',
                        4000
                    );
                    return;
                }

                var $btn     = $(this);
                var musicId  = $btn.data('music-id');
                var $count   = $('.cv-fav-count[data-music-id="' + musicId + '"]');

                $btn.prop('disabled', true);

                $.post(cvPublic.ajaxUrl, {
                    action   : 'cv_toggle_favorite',
                    nonce    : cvPublic.nonces.favorite,
                    music_id : musicId
                }, function(res) {
                    if ( res.success ) {
                        var isFav = res.data.action === 'added';
                        $('[data-music-id="' + musicId + '"].cv-btn-favorite')
                            .toggleClass('cv-favorited', isFav)
                            .attr('aria-pressed', isFav ? 'true' : 'false');
                        $count.text(res.data.favorites_label !== undefined ? res.data.favorites_label : res.data.favorites);
                        Toast.show(
                            isFav ? '❤ Adicionado aos favoritos' : 'Removido dos favoritos',
                            isFav ? 'success' : 'info'
                        );
                    } else if ( res.data && res.data.require_login ) {
                        Toast.show('<a href="' + cvPublic.loginUrl + '" style="color:var(--cv-gold)">Faça login</a> para favoritar.', 'info', 4000);
                    }
                    $btn.prop('disabled', false);
                }).fail(function() {
                    Toast.show('Erro de conexão.', 'error');
                    $btn.prop('disabled', false);
                });
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // 7. CARD — play ao clicar na capa (comunica com o player)
    // ═══════════════════════════════════════════════════════════════

    var Cards = {
        init: function() {
            $(document).on('click', '.cv-card-play-overlay, .cv-card-play-icon', function(e) {
                e.preventDefault();
                e.stopPropagation();

                var $card    = $(this).closest('.cv-card');
                var musicId  = $card.data('music-id');
                var ytId     = $card.data('youtube-id');
                var title    = $card.data('title');
                var cover    = $card.data('cover');
                var artista  = $card.data('artista');

                if ( ! ytId ) {
                    // Sem YouTube — vai para a página da música
                    var $link = $card.find('.cv-card-capa-link');
                    if ( $link.length ) { window.location.href = $link.attr('href'); }
                    return;
                }

                // Comunica com o player global
                if ( window.CV_Player ) {
                    CV_Player.playById({
                        musicId  : musicId,
                        youtubeId: ytId,
                        title    : title || '',
                        cover    : cover || '',
                        artista  : artista || ''
                    });
                }
            });
        }
    };

    // ═══════════════════════════════════════════════════════════════
    // INICIALIZAÇÃO
    // ═══════════════════════════════════════════════════════════════

    $(function() {
        ParallaxHero.init();
        Sidebar.init();
        Search.init();
        ActiveNav.init();
        Favorites.init();
        Cards.init();
    });

    // API pública — outros scripts podem usar
    window.CV_Theme = {
        toast    : Toast.show.bind(Toast),
        parallax : ParallaxHero,
        sidebar  : Sidebar,
    };

}(jQuery));
