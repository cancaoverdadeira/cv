<?php
// cancao-verdadeira-child/template-parts/sidebar.php
// Gerado em: 2026-06-21 23:45:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Sidebar fixa à esquerda: logo, ações rápidas, navegação principal,
// e área do usuário (logado/deslogado).
// Incluída em todos os templates via get_template_part('template-parts/sidebar').
// Dados do usuário são buscados diretamente aqui (sem AJAX).
// v15.4.0: removida a lista de gêneros (o site é todo sertanejo).
// v15.5.1: removido o script duplicado do botão "Buscar" (campo não abria).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$is_logged = is_user_logged_in();
$user      = $is_logged ? wp_get_current_user() : null;
$avatar    = $user ? get_avatar_url( $user->ID, array('size' => 64) ) : '';

// Notificações não lidas (somente se logado)
$notif_unread = 0;
if ( $is_logged ) {
    $notifs       = get_user_meta( $user->ID, '_cv_notifications', true );
    $notifs       = is_array($notifs) ? $notifs : array();
    $notif_unread = count( array_filter($notifs, function($n){ return empty($n['read']); }) );
}
?>

<aside class="cv-sidebar" id="cv-sidebar" role="navigation" aria-label="Menu Principal">

    <!-- Logo -->
    <div class="cv-sidebar-logo">
        <a href="<?php echo esc_url( home_url('/') ); ?>" aria-label="Canção Verdadeira — Início">
            <img src="<?php echo esc_url( cv_logo_url() ); ?>"
                 alt="<?php bloginfo('name'); ?>"
                 loading="eager" />
        </a>
    </div>

    <!-- Ações rápidas -->
    <div class="cv-sidebar-actions">
        <a href="<?php echo esc_url( home_url('/musicas/') ); ?>"
           class="cv-btn-sidebar cv-btn-sidebar-primary">
            <span>🎵</span>
            <span>Ver Músicas</span>
        </a>
        <a href="<?php echo esc_url( home_url('/buscar-musicas/') ); ?>"
           class="cv-btn-sidebar cv-btn-sidebar-secondary cv-search-trigger">
            <span>🔍</span>
            <span>Buscar</span>
        </a>
    </div>

    <!-- Campo de busca rápida (expansível) -->
    <div class="cv-sidebar-search" style="padding:0 12px 10px;display:none" id="cv-sidebar-search-wrap">
        <div class="cv-search-wrap" style="position:relative">
            <input type="text"
                   class="cv-input cv-search-input"
                   placeholder="Buscar músicas, artistas..."
                   autocomplete="off"
                   style="font-size:13px;padding:8px 36px 8px 12px" />
            <span class="cv-search-icon" style="right:10px">🔍</span>
            <div class="cv-autocomplete-list" style="position:absolute;top:100%;left:0;right:0;z-index:50"></div>
        </div>
    </div>

    <!-- Navegação principal -->
    <nav class="cv-nav-section" aria-label="Menu principal">
        <div class="cv-nav-label">Menu</div>

        <a href="<?php echo esc_url( home_url('/') ); ?>"
           class="cv-nav-link <?php echo is_front_page() ? 'active' : ''; ?>">
            <span class="nav-icon">🏠</span> Início
        </a>

        <a href="<?php echo esc_url( home_url('/musicas/') ); ?>"
           class="cv-nav-link <?php echo is_post_type_archive('musica') ? 'active' : ''; ?>">
            <span class="nav-icon">🎵</span> Todas as Músicas
        </a>

        <a href="<?php echo esc_url( home_url('/ranking/') ); ?>"
           class="cv-nav-link <?php echo is_page('ranking') ? 'active' : ''; ?>">
            <span class="nav-icon">🏆</span> Ranking
        </a>

        <a href="<?php echo esc_url( home_url('/buscar-musicas/') ); ?>"
           class="cv-nav-link <?php echo is_page('buscar-musicas') ? 'active' : ''; ?>">
            <span class="nav-icon">🔍</span> Buscar
        </a>

        <?php if ( $is_logged ) : ?>
        <a href="<?php echo esc_url( home_url('/minhas-playlists/') ); ?>"
           class="cv-nav-link <?php echo is_page('minhas-playlists') ? 'active' : ''; ?>">
            <span class="nav-icon">📋</span> Minhas Playlists
        </a>
        <a href="<?php echo esc_url( home_url('/notificacoes/') ); ?>"
           class="cv-nav-link <?php echo is_page('notificacoes') ? 'active' : ''; ?>"
           style="position:relative">
            <span class="nav-icon">🔔</span> Notificações
            <?php if ( $notif_unread > 0 ) : ?>
            <span style="background:var(--cv-error);color:#3B2418;font-size:10px;font-weight:700;
                         padding:1px 6px;border-radius:50px;margin-left:auto">
                <?php echo $notif_unread; ?>
            </span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
    </nav>

    <!-- Área do usuário -->
    <div class="cv-sidebar-user">
        <?php if ( $is_logged ) : ?>

            <!-- Avatar e nome -->
            <div class="cv-sidebar-user-info">
                <div class="cv-avatar-sm">
                    <img src="<?php echo esc_url($avatar); ?>"
                         alt="<?php echo esc_attr($user->display_name); ?>" />
                </div>
                <span class="cv-user-name"><?php echo esc_html($user->display_name); ?></span>
            </div>

            <!-- Sino de notificações -->
            <button class="cv-btn-sidebar cv-btn-sidebar-secondary"
                    id="cv-sidebar-bell"
                    data-cv-bell
                    style="position:relative;justify-content:space-between;margin-bottom:6px"
                    aria-label="Notificações">
                <span><span style="margin-right:8px">🔔</span> Notificações</span>
                <span class="cv-notif-badge"
                      data-cv-notif-badge
                      id="cv-sidebar-badge"
                      style="<?php echo $notif_unread > 0 ? '' : 'display:none'; ?>">
                    <?php echo $notif_unread ?: ''; ?>
                </span>
            </button>

            <!-- Links do usuário -->
            <a href="<?php echo esc_url( cv_dashboard_url() ); ?>"
               class="cv-btn-sidebar cv-btn-sidebar-secondary" style="margin-bottom:4px">
                <span>⚙</span> Minha Área
            </a>

            <a href="<?php echo esc_url( cv_profile_url() ); ?>"
               class="cv-btn-sidebar cv-btn-sidebar-secondary" style="margin-bottom:4px">
                <span>👤</span> Meu Perfil
            </a>

            <a href="<?php echo esc_url( wp_logout_url( home_url('/') ) ); ?>"
               class="cv-btn-sidebar cv-btn-sidebar-secondary"
               style="color:var(--cv-text-dim);margin-top:4px">
                <span>↩</span> Sair
            </a>

        <?php else : ?>

            <!-- Deslogado -->
            <p style="font-size:12px;color:var(--cv-text-dim);margin:0 0 10px;text-align:center">
                Entre para favoritar músicas e criar playlists
            </p>
            <a href="<?php echo esc_url( cv_login_url( get_permalink() ) ); ?>"
               class="cv-btn-sidebar cv-btn-sidebar-primary">
                <span>🔐</span> Entrar
            </a>
            <a href="<?php echo esc_url( cv_register_url() ); ?>"
               class="cv-btn-sidebar cv-btn-sidebar-secondary" style="margin-top:6px">
                <span>✨</span> Criar Conta Grátis
            </a>

        <?php endif; ?>
    </div>

</aside>

<!-- Overlay para fechar sidebar no mobile -->
<div class="cv-overlay" id="cv-overlay" aria-hidden="true"></div>

<!-- Painel de notificações (dropdown) -->
<?php if ( $is_logged ) : ?>
<div class="cv-notif-panel" id="cv-notif-panel" data-cv-notif-panel role="dialog" aria-label="Notificações">
    <div class="cv-notif-header">
        <span>🔔 Notificações</span>
        <div style="display:flex;gap:10px">
            <button data-cv-mark-all-notif
                    style="background:none;border:none;color:var(--cv-text-dim);font-size:11px;cursor:pointer">
                Marcar todas lidas
            </button>
            <button data-cv-notif-close
                    style="background:none;border:none;color:var(--cv-text-dim);font-size:18px;cursor:pointer;line-height:1">
                ✕
            </button>
        </div>
    </div>
    <div class="cv-notif-list" id="cv-notif-list" data-cv-notif-list>
        <p style="color:var(--cv-text-dim);font-size:13px;text-align:center;padding:24px">
            Carregando...
        </p>
    </div>
</div>
<?php endif; ?>


<?php // O botão "Buscar" abre/fecha o campo via assets/js/cv-theme.js. Havia uma
// segunda cópia deste script aqui: os dois alternavam juntos e o campo abria e
// fechava no mesmo clique (corrigido na v15.5.1). ?>
