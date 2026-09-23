<?php
/*
 * Template Name: Notificações
 */
// cancao-verdadeira-child/templates/page-notifications.php
// Gerado em: 2026-06-22 03:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página completa de notificações do usuário: lista de todas as
// notificações com ações de marcar como lida, marcar todas e excluir.
// Redireciona para /login/ se não estiver logado.

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! is_user_logged_in() ) {
    wp_redirect( cv_login_url( get_permalink() ) );
    exit;
}

$user_id      = get_current_user_id();
$notifications= get_user_meta( $user_id, '_cv_notifications', true );
$notifications= is_array($notifications) ? $notifications : array();
$unread_count = count( array_filter($notifications, function($n){ return empty($n['read']); }) );

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part('template-parts/topbar'); ?>

        <div style="max-width:760px;margin:0 auto;padding:40px 36px">

            <!-- Cabeçalho -->
            <div style="display:flex;align-items:center;justify-content:space-between;
                        margin-bottom:28px;flex-wrap:wrap;gap:12px">
                <div>
                    <h1 style="font-family:var(--font-display);font-size:28px;
                               font-weight:700;margin:0 0 4px">
                        🔔 Notificações
                    </h1>
                    <?php if ($unread_count > 0) : ?>
                    <span style="font-size:13px;color:var(--cv-text-muted)">
                        <?php echo $unread_count; ?> não lida<?php echo $unread_count !== 1 ? 's' : ''; ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($notifications)) : ?>
                <button id="cv-mark-all-read"
                        class="cv-btn cv-btn-secondary cv-btn-sm">
                    ✓ Marcar todas como lidas
                </button>
                <?php endif; ?>
            </div>

            <!-- Lista de notificações -->
            <div id="cv-notif-full-list">

                <?php if (empty($notifications)) : ?>
                <div style="text-align:center;padding:60px 20px;
                            background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);
                            border-radius:var(--cv-radius)">
                    <div style="font-size:48px;margin-bottom:16px">🔔</div>
                    <h2 style="font-family:var(--font-display);color:var(--cv-gold);margin-bottom:8px">
                        Tudo tranquilo por aqui
                    </h2>
                    <p style="color:var(--cv-text-muted)">
                        Você ainda não tem notificações.<br>
                        Favorite músicas e elas entrarão no ranking para você acompanhar.
                    </p>
                </div>

                <?php else : ?>

                <div style="display:flex;flex-direction:column;gap:8px">
                    <?php foreach (array_reverse($notifications) as $i => $n) :
                        $is_read  = ! empty($n['read']);
                        $notif_id = $n['id'] ?? $i;
                        $icon     = $n['icon']    ?? '🎵';
                        $msg      = $n['message'] ?? '';
                        $url      = $n['url']     ?? '';
                        $time     = isset($n['time']) ? human_time_diff($n['time'], current_time('timestamp')) . ' atrás' : '';
                    ?>
                    <div class="cv-notif-full-item <?php echo $is_read ? '' : 'cv-notif-unread'; ?>"
                         data-id="<?php echo esc_attr($notif_id); ?>"
                         style="display:flex;align-items:flex-start;gap:14px;padding:16px;
                                background:<?php echo $is_read ? 'var(--cv-bg-card)' : 'rgba(242,165,26,0.05)'; ?>;
                                border:1px solid <?php echo $is_read ? 'var(--cv-border-subtle)' : 'rgba(242,165,26,0.26)'; ?>;
                                border-radius:var(--cv-radius);transition:all .2s">

                        <!-- Ícone -->
                        <div style="width:44px;height:44px;border-radius:50%;
                                    background:rgba(123,58,34,0.07);
                                    display:flex;align-items:center;justify-content:center;
                                    font-size:20px;flex-shrink:0">
                            <?php echo esc_html($icon); ?>
                        </div>

                        <!-- Conteúdo -->
                        <div style="flex:1;min-width:0">
                            <div style="font-size:14px;color:var(--cv-text);line-height:1.5;margin-bottom:6px">
                                <?php if ($url) : ?>
                                <a href="<?php echo esc_url($url); ?>"
                                   style="color:var(--cv-text);text-decoration:none">
                                    <?php echo esc_html($msg); ?>
                                </a>
                                <?php else : ?>
                                <?php echo esc_html($msg); ?>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--cv-text-dim)">
                                <?php echo esc_html($time); ?>
                                <?php if (!$is_read) : ?>
                                <span style="display:inline-block;width:6px;height:6px;
                                             border-radius:50%;background:var(--cv-accent);
                                             margin-left:8px;vertical-align:middle"></span>
                                <span style="color:var(--cv-gold);font-weight:700">Nova</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Ações -->
                        <div style="display:flex;gap:8px;flex-shrink:0">
                            <?php if ($url) : ?>
                            <a href="<?php echo esc_url($url); ?>"
                               class="cv-btn cv-btn-secondary cv-btn-sm"
                               style="font-size:11px;padding:4px 10px">
                                Ver →
                            </a>
                            <?php endif; ?>
                            <?php if (!$is_read) : ?>
                            <button class="cv-mark-read cv-btn cv-btn-ghost cv-btn-sm"
                                    data-id="<?php echo esc_attr($notif_id); ?>"
                                    style="font-size:11px;padding:4px 10px">
                                ✓ Lida
                            </button>
                            <?php endif; ?>
                        </div>

                    </div>
                    <?php endforeach; ?>
                </div>

                <?php endif; ?>
            </div>
        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>
    </main>
</div>

<script>
jQuery(function($){
    var AJAX  = cvPublic.ajaxUrl;
    var nonce = cvPublic.nonces.notification;

    // Marcar uma como lida
    $(document).on('click', '.cv-mark-read', function(){
        var id   = $(this).data('id');
        var $row = $(this).closest('.cv-notif-full-item');
        $.post(AJAX, { action:'cv_mark_notif_read', nonce:nonce, notif_id:id }, function(res){
            if (res.success) {
                $row.css({
                    background    : 'var(--cv-bg-card)',
                    borderColor   : 'var(--cv-border-subtle)'
                });
                $row.find('.cv-mark-read').remove();
                $row.find('span[style*="gold"]').parent().html('');
                // Atualiza badge do sino
                $('[data-cv-notif-badge]').each(function(){
                    var n = parseInt($(this).text()) - 1;
                    if (n <= 0) { $(this).hide(); } else { $(this).text(n); }
                });
            }
        });
    });

    // Marcar todas como lidas
    $('#cv-mark-all-read').on('click', function(){
        $.post(AJAX, { action:'cv_mark_notif_read', nonce:nonce, notif_id:'all' }, function(res){
            if (res.success) {
                $('.cv-notif-full-item').css({ background:'var(--cv-bg-card)', borderColor:'var(--cv-border-subtle)' });
                $('.cv-mark-read').remove();
                $('[data-cv-notif-badge], #cv-sidebar-badge, #cv-topbar-badge').hide();
                $('#cv-mark-all-read').prop('disabled', true).text('✓ Todas lidas');
            }
        });
    });
});
</script>

<?php get_footer(); ?>
