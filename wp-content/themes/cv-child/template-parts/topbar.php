<?php
// cancao-verdadeira-child/template-parts/topbar.php
// Gerado em: 2026-06-21 23:45:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Barra superior mobile (display:none no desktop, display:flex no mobile).
// Contém: botão hamburger, logo centralizada, busca e avatar/login.
// Incluída em todos os templates antes do conteúdo principal.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$is_logged    = is_user_logged_in();
$user         = $is_logged ? wp_get_current_user() : null;
$avatar       = $user ? get_avatar_url( $user->ID, array('size' => 48) ) : '';
$notif_unread = 0;

if ( $is_logged ) {
    $notifs       = get_user_meta( $user->ID, '_cv_notifications', true );
    $notifs       = is_array($notifs) ? $notifs : array();
    $notif_unread = count( array_filter($notifs, function($n){ return empty($n['read']); }) );
}
?>

<header class="cv-topbar" id="cv-topbar" role="banner">

    <!-- Hamburger -->
    <button class="cv-hamburger" id="cv-hamburger"
            aria-controls="cv-sidebar"
            aria-expanded="false"
            aria-label="Abrir menu">
        ☰
    </button>

    <!-- Logo centralizada -->
    <a href="<?php echo esc_url( home_url('/') ); ?>"
       class="cv-topbar-logo"
       aria-label="Canção Verdadeira — Início"
       style="position:absolute;left:50%;transform:translateX(-50%)">
        <img src="<?php echo esc_url( cv_logo_url() ); ?>"
             alt="<?php bloginfo('name'); ?>"
             loading="eager" />
    </a>

    <!-- Ações à direita -->
    <div class="cv-topbar-right">

        <!-- Link Início mobile -->
        <a href="<?php echo esc_url( home_url('/') ); ?>"
           aria-label="Início"
           style="background:none;color:var(--cv-text-muted);font-size:18px;
                  padding:6px;text-decoration:none;display:flex;align-items:center"
           title="Voltar ao início">
            🏠
        </a>

        <!-- Busca mobile -->
        <button class="cv-topbar-btn"
                id="cv-topbar-search-toggle"
                aria-label="Buscar"
                style="background:none;border:none;color:var(--cv-text-muted);font-size:18px;cursor:pointer;padding:6px">
            🔍
        </button>

        <?php if ( $is_logged ) : ?>

        <!-- Sino de notificações -->
        <button class="cv-topbar-btn"
                id="cv-topbar-bell"
                data-cv-bell
                aria-label="Notificações"
                style="background:none;border:none;color:var(--cv-text-muted);font-size:18px;cursor:pointer;padding:6px;position:relative">
            🔔
            <span class="cv-notif-badge"
                  data-cv-notif-badge
                  id="cv-topbar-badge"
                  style="position:absolute;top:2px;right:2px;<?php echo $notif_unread > 0 ? '' : 'display:none'; ?>">
                <?php echo $notif_unread ?: ''; ?>
            </span>
        </button>

        <!-- Avatar / dashboard -->
        <a href="<?php echo esc_url( cv_dashboard_url() ); ?>"
           class="cv-topbar-avatar"
           aria-label="Minha Área"
           style="display:block;width:32px;height:32px;border-radius:50%;overflow:hidden;border:2px solid var(--cv-gold-dim)">
            <img src="<?php echo esc_url($avatar); ?>"
                 alt="<?php echo esc_attr($user->display_name); ?>"
                 style="width:100%;height:100%;object-fit:cover" />
        </a>

        <?php else : ?>

        <!-- Botão entrar -->
        <a href="<?php echo esc_url( cv_login_url( get_permalink() ) ); ?>"
           style="background:var(--cv-gold);color:#1a1a1a;font-size:12px;font-weight:700;padding:6px 14px;border-radius:50px;text-decoration:none;white-space:nowrap">
            Entrar
        </a>

        <?php endif; ?>
    </div>

</header>

<!-- Busca expandida mobile (aparece abaixo da topbar) -->
<div id="cv-topbar-search-panel"
     style="display:none;position:fixed;top:var(--cv-topbar-h);left:0;right:0;background:rgba(17,17,17,.98);
            backdrop-filter:blur(12px);padding:12px 16px;z-index:299;border-bottom:1px solid var(--cv-border-subtle)">
    <div class="cv-search-wrap" style="position:relative">
        <input type="text"
               class="cv-input cv-search-input"
               id="cv-topbar-search-input"
               placeholder="Buscar músicas, artistas, letras..."
               autocomplete="off"
               style="font-size:14px;padding:10px 36px 10px 14px" />
        <span class="cv-search-icon">🔍</span>
        <div class="cv-autocomplete-list"
             style="position:absolute;top:100%;left:0;right:0;z-index:50;margin-top:4px"></div>
    </div>
</div>

<script>
jQuery(function($){
    var $searchPanel = $('#cv-topbar-search-panel');
    var $hamburger   = $('#cv-hamburger');
    var $sidebar     = $('#cv-sidebar');
    var $overlay     = $('#cv-overlay');

    // Toggle hamburger
    $hamburger.on('click', function(){
        var isOpen = $sidebar.hasClass('open');
        $sidebar.toggleClass('open');
        $overlay.toggleClass('active');
        $hamburger.attr('aria-expanded', !isOpen);
    });

    // Toggle busca mobile
    $('#cv-topbar-search-toggle').on('click', function(){
        $searchPanel.slideToggle(180, function(){
            if ($searchPanel.is(':visible')) {
                $('#cv-topbar-search-input').focus();
            }
        });
    });

    // Fechar busca ao pressionar Esc
    $(document).on('keydown', function(e){
        if (e.key === 'Escape') {
            $searchPanel.slideUp(180);
            $sidebar.removeClass('open');
            $overlay.removeClass('active');
            $hamburger.attr('aria-expanded', 'false');
        }
    });
});
</script>
