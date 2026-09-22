<?php
// cancao-verdadeira-plugin/uninstall.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Executado pelo WordPress ao EXCLUIR o plugin (não ao desativar).
// Remove todas as tabelas customizadas, opções salvas e roles criados.
// ATENÇÃO: esta operação é irreversível — todos os dados serão perdidos.

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) { exit; }

// Preserve content and engagement data unless erasure was explicitly enabled.
if ( ! defined( 'CV_DELETE_DATA_ON_UNINSTALL' ) || CV_DELETE_DATA_ON_UNINSTALL !== true ) {
    return;
}

global $wpdb;

// ── 1. Remove tabelas customizadas ───────────────────────────────
$tables = array(
    $wpdb->prefix . 'cv_plays_log',
    $wpdb->prefix . 'cv_favorites',
    $wpdb->prefix . 'cv_ratings',
    $wpdb->prefix . 'cv_playlists',
    $wpdb->prefix . 'cv_playlist_items',
    $wpdb->prefix . 'cv_ranking_cache',
    $wpdb->prefix . 'cv_subscribers',
    $wpdb->prefix . 'cv_lyric_comments',
    $wpdb->prefix . 'cv_action_logs',
    $wpdb->prefix . 'cv_banners',
    $wpdb->prefix . 'cv_produtos',
    $wpdb->prefix . 'cv_sorteios',
    $wpdb->prefix . 'cv_brindes',
    $wpdb->prefix . 'cv_brindes_entregas',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS `{$table}`" );
}

// ── 2. Remove opções salvas ───────────────────────────────────────
$options = array(
    'cv_mailerlite_api_key',
    'cv_mailerlite_group_id',
    'cv_whatsapp_number',
    'cv_whatsapp_message',
    'cv_whatsapp_tooltip',
    'cv_whatsapp_pulse_delay',
    'cv_play_seconds',
    'cv_logo_url',
    'cv_banner_url',
    'cv_ranking_last_update',
    'cv_featured_playlists',
    'cv_db_version',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Remove opções de capa de playlists
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE 'cv_playlist_cover_%'"
);

// ── 3. Remove transients ──────────────────────────────────────────
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_cv_%'
        OR option_name LIKE '_transient_timeout_cv_%'"
);

// ── 4. Remove roles customizados ─────────────────────────────────
remove_role( 'cv_editor' );
remove_role( 'cv_gerente' );
remove_role( 'cv_master' );

// ── 5. Remove agendamentos de cron ───────────────────────────────
wp_clear_scheduled_hook( 'cv_cron_ranking' );

// ── 6. Remove user_meta do plugin ────────────────────────────────
// (apenas se houver poucos usuários — evita timeout em sites grandes)
$user_count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );
if ( $user_count <= 10000 ) {
    $meta_keys = array(
        '_cv_play_history',
        '_cv_notifications',
        '_cv_achievements',
        '_cv_favorite_genre',
    );
    foreach ( $meta_keys as $key ) {
        $wpdb->delete( $wpdb->usermeta, array( 'meta_key' => $key ), array( '%s' ) );
    }
}

// ── 7. Flush rewrite rules ────────────────────────────────────────
flush_rewrite_rules();
