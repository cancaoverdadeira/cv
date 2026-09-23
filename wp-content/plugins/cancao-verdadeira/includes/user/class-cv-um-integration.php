<?php
// cancao-verdadeira-plugin/includes/user/class-cv-um-integration.php
// Gerado em: 2025-06-02 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Integracao com Ultimate Member: adiciona abas customizadas no perfil
// do usuario (Historico, Favoritas, Playlists, Configuracoes).
// v2.26.0: saiu o "Genero Favorito" das configuracoes (site todo sertanejo).
// So carrega se o plugin Ultimate Member estiver ativo.
// Compativel com UM 2.x. Nao quebra o site se UM estiver desativo.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_UM_Integration {

    public static function init() {
        if ( ! class_exists( 'UM' ) ) { return; }

        add_filter( 'um_profile_tabs',         array( __CLASS__, 'add_tabs' ), 1000 );
        add_action( 'um_profile_content_cv_history_default',    array( __CLASS__, 'tab_history' ) );
        add_action( 'um_profile_content_cv_favorites_default',  array( __CLASS__, 'tab_favorites' ) );
        add_action( 'um_profile_content_cv_playlists_default',  array( __CLASS__, 'tab_playlists' ) );
        add_action( 'um_profile_content_cv_settings_default',   array( __CLASS__, 'tab_settings' ) );
    }

    // default_privacy: 0 = qualquer pessoa, 3 = só o dono do perfil.
    // Cada aba também precisa estar ligada em UM → Configurações → Aparência →
    // Menu do perfil (opção profile_tab_{id}), senão o UM não a exibe.
    public static function add_tabs( $tabs ) {
        $tabs['cv_history'] = array(
            'name'   => 'Histórico',
            'icon'   => 'um-faicon-music',
            'custom' => true,
            'default_privacy' => 3,
        );
        $tabs['cv_favorites'] = array(
            'name'   => 'Favoritas',
            'icon'   => 'um-faicon-heart',
            'custom' => true,
            'default_privacy' => 3,
        );
        $tabs['cv_playlists'] = array(
            'name'   => 'Playlists',
            'icon'   => 'um-faicon-list',
            'custom' => true,
            'default_privacy' => 0,
        );
        $tabs['cv_settings'] = array(
            'name'   => 'Preferências',
            'icon'   => 'um-faicon-cog',
            'custom' => true,
            'default_privacy' => 3,
        );
        return $tabs;
    }

    public static function tab_history() {
        $user_id = um_profile_id();
        $history = get_user_meta( $user_id, '_cv_play_history', true );
        if ( ! is_array( $history ) || empty( $history ) ) {
            echo '<p style="color:#8A6A55;padding:20px 0">Nenhuma música ouvida ainda.</p>';
            return;
        }
        echo '<div class="cv-um-grid">';
        foreach ( array_slice( $history, 0, 16 ) as $item ) {
            $id   = $item['id'] ?? 0;
            $post = get_post( $id );
            if ( ! $post || 'publish' !== $post->post_status ) { continue; }
            echo cv_music_card( $id );
        }
        echo '</div>';
    }

    public static function tab_favorites() {
        $user_id   = um_profile_id();
        $favorites = class_exists( 'CV_Favorites' )
            ? CV_Favorites::get_user_favorites( $user_id, 16 )
            : array();

        if ( empty( $favorites ) ) {
            echo '<p style="color:#8A6A55;padding:20px 0">Nenhuma música favorita ainda.</p>';
            return;
        }
        echo '<div class="cv-um-grid">';
        foreach ( $favorites as $post ) {
            echo cv_music_card( $post->ID );
        }
        echo '</div>';
    }

    public static function tab_playlists() {
        $user_id   = um_profile_id();
        $is_owner  = ( get_current_user_id() === $user_id );
        $playlists = class_exists( 'CV_Playlists' )
            ? CV_Playlists::get_user_playlists( $user_id )
            : array();

        if ( empty( $playlists ) ) {
            echo '<p style="color:#8A6A55;padding:20px 0">Nenhuma playlist criada ainda.</p>';
            return;
        }

        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(180px,1fr));gap:14px">';
        foreach ( $playlists as $pl ) {
            if ( ! $is_owner && ! $pl->is_public ) { continue; }
            echo '<div style="background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);border-radius:10px;padding:14px;text-align:center">';
            echo '<div style="font-size:32px;margin-bottom:8px">📋</div>';
            echo '<div style="font-size:13px;font-weight:700;margin-bottom:4px">' . esc_html( $pl->name ) . '</div>';
            echo '<div style="font-size:11px;color:#8A6A55">' . (int) $pl->count . ' músicas</div>';
            echo '</div>';
        }
        echo '</div>';
    }

    public static function tab_settings() {
        if ( get_current_user_id() !== um_profile_id() ) {
            echo '<p style="color:#8A6A55">Estas configurações são privadas.</p>';
            return;
        }

        $user_id       = get_current_user_id();
        $notif_enabled = get_user_meta( $user_id, '_cv_notif_enabled', true );
        $notif_enabled = $notif_enabled === '' ? '1' : $notif_enabled;
        ?>
        <div style="max-width:400px;padding:8px 0">
            <h3 style="font-size:15px;margin-bottom:16px;color:var(--cv-text)">Notificações</h3>
            <form id="cv-um-settings-form">
                <div style="margin-bottom:18px">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer">
                        <input type="checkbox" id="cv-um-notif" value="1" <?php checked( $notif_enabled, '1' ); ?> />
                        <span style="font-size:13px;color:var(--cv-text)">Receber notificações de novas músicas</span>
                    </label>
                </div>
                <button type="button" id="cv-um-save-settings" class="cv-button cv-button-primary" style="padding:10px 24px;font-size:13px">
                    Salvar Preferências
                </button>
                <span id="cv-um-save-msg" style="font-size:12px;color:#2F7B2F;margin-left:12px;display:none">✅ Salvo!</span>
            </form>
        </div>
        <script>
        jQuery(function($){
            $('#cv-um-save-settings').on('click', function(){
                var notif = $('#cv-um-notif').is(':checked') ? '1' : '0';
                $.post(cvTheme.ajaxUrl, {
                    action: 'cv_save_um_settings',
                    nonce:  cvTheme.playNonce,
                    notif:  notif,
                }, function(res){
                    if (res.success) {
                        $('#cv-um-save-msg').show();
                        setTimeout(function(){ $('#cv-um-save-msg').fadeOut(); }, 3000);
                    }
                });
            });
        });
        </script>
        <?php
    }

}

// AJAX: salvar preferencias UM
add_action( 'wp_ajax_cv_save_um_settings', 'cv_save_um_settings_handler' );
function cv_save_um_settings_handler() {
    check_ajax_referer( 'cv_play_nonce', 'nonce' );
    if ( ! is_user_logged_in() ) { wp_send_json_error(); }

    $user_id = get_current_user_id();
    $notif   = sanitize_text_field( $_POST['notif']  ?? '1' );

    update_user_meta( $user_id, '_cv_notif_enabled',   $notif );

    wp_send_json_success();
}

// Em plugins_loaded: este plugin carrega antes do ultimate-member (ordem
// alfabética), então class_exists('UM') ainda seria false aqui no include.
add_action( 'plugins_loaded', array( 'CV_UM_Integration', 'init' ) );
