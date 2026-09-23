<?php
// cancao-verdadeira/includes/user/class-cv-profile-edit.php
// Gerado em: 2026-06-21 20:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Edição de perfil do usuário logado: nome, e-mail, senha, foto e gênero
// favorito. Todos os endpoints usam nonce para segurança. A exclusão de
// conta exige confirmação por senha (requisito LGPD). Shortcode
// [cv_profile_form] renderiza o formulário no dashboard do usuário.
// Funciona com ou sem o Ultimate Member instalado.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Profile_Edit {

    public static function init() {

        // ── Shortcode ─────────────────────────────────────────────
        add_shortcode( 'cv_profile_form', array( __CLASS__, 'shortcode_profile' ) );

        // ── Endpoints AJAX ────────────────────────────────────────
        add_action( 'wp_ajax_cv_update_profile',       array( __CLASS__, 'update_profile' ) );
        add_action( 'wp_ajax_cv_change_password',      array( __CLASS__, 'change_password' ) );
        add_action( 'wp_ajax_cv_save_genre_preference',array( __CLASS__, 'save_genre' ) );
        add_action( 'wp_ajax_cv_delete_account',       array( __CLASS__, 'delete_account' ) );
    }

    // ── SHORTCODE: formulário de perfil ──────────────────────────

    public static function shortcode_profile() {
        if ( ! is_user_logged_in() ) {
            return '<p>Você precisa estar logado para editar seu perfil. '
                 . '<a href="' . esc_url( home_url( '/login/' ) ) . '">Fazer login</a></p>';
        }

        $user   = wp_get_current_user();
        $genre  = get_user_meta( $user->ID, '_cv_favorite_genre', true );
        $genres = get_terms( array( 'taxonomy' => 'cv_genre', 'hide_empty' => false, 'orderby' => 'name' ) );

        ob_start();
        ?>
        <div class="cv-profile-box">

            <!-- Avatar -->
            <div class="cv-profile-avatar-section">
                <img src="<?php echo esc_url( get_avatar_url( $user->ID, array( 'size' => 100 ) ) ); ?>"
                     alt="Avatar" class="cv-profile-avatar" id="cv-avatar-img" />
                <p class="cv-profile-avatar-hint">Sua foto vem do <a href="https://gravatar.com" target="_blank" rel="noopener" style="color:#7B3A22">Gravatar</a> (vinculado ao e-mail cadastrado)</p>
            </div>

            <div class="cv-profile-msg" id="cv-profile-msg" style="display:none"></div>

            <!-- Dados pessoais -->
            <div class="cv-profile-section">
                <h3 class="cv-profile-section-title">👤 Dados pessoais</h3>
                <input type="hidden" id="cv-profile-nonce" value="<?php echo esc_attr( wp_create_nonce( 'cv_profile_nonce' ) ); ?>" />

                <div class="cv-profile-field">
                    <label for="cv-profile-name">Nome</label>
                    <input type="text" id="cv-profile-name" value="<?php echo esc_attr( $user->display_name ); ?>" />
                </div>
                <div class="cv-profile-field">
                    <label for="cv-profile-email">E-mail</label>
                    <input type="email" id="cv-profile-email" value="<?php echo esc_attr( $user->user_email ); ?>" />
                </div>
                <button type="button" id="cv-profile-save-btn" class="cv-btn cv-btn-primary">
                    <span class="cv-btn-text">Salvar dados</span>
                    <span class="cv-btn-loading" style="display:none">⏳ Salvando...</span>
                </button>
            </div>

            <!-- Gênero favorito -->
            <?php if ( ! empty( $genres ) && ! is_wp_error( $genres ) ) : ?>
            <div class="cv-profile-section">
                <h3 class="cv-profile-section-title">🎵 Gênero favorito</h3>
                <p class="cv-profile-hint">Usado para personalizar recomendações e notificações</p>
                <div class="cv-profile-field">
                    <select id="cv-profile-genre">
                        <option value="">Selecione um gênero...</option>
                        <?php foreach ( $genres as $g ) : ?>
                            <option value="<?php echo esc_attr( $g->slug ); ?>" <?php selected( $genre, $g->slug ); ?>>
                                <?php echo esc_html( $g->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="cv-genre-save-btn" class="cv-btn cv-btn-secondary">
                    <span class="cv-btn-text">Salvar gênero</span>
                    <span class="cv-btn-loading" style="display:none">⏳ Salvando...</span>
                </button>
            </div>
            <?php endif; ?>

            <!-- Trocar senha -->
            <div class="cv-profile-section">
                <h3 class="cv-profile-section-title">🔒 Alterar senha</h3>
                <div class="cv-profile-msg" id="cv-pw-msg" style="display:none"></div>
                <div class="cv-profile-field">
                    <label for="cv-pw-current">Senha atual</label>
                    <input type="password" id="cv-pw-current" placeholder="Digite sua senha atual" />
                </div>
                <div class="cv-profile-field">
                    <label for="cv-pw-new">Nova senha <span style="color:#8A6A55;font-weight:400">(mín. 8 caracteres)</span></label>
                    <input type="password" id="cv-pw-new" placeholder="Nova senha" />
                </div>
                <div class="cv-profile-field">
                    <label for="cv-pw-confirm">Confirmar nova senha</label>
                    <input type="password" id="cv-pw-confirm" placeholder="Repita a nova senha" />
                </div>
                <button type="button" id="cv-pw-save-btn" class="cv-btn cv-btn-secondary">
                    <span class="cv-btn-text">Alterar senha</span>
                    <span class="cv-btn-loading" style="display:none">⏳ Alterando...</span>
                </button>
            </div>

            <!-- Excluir conta -->
            <div class="cv-profile-section cv-profile-danger">
                <h3 class="cv-profile-section-title" style="color:#D62C1A">⚠️ Excluir conta</h3>
                <p class="cv-profile-hint">Esta ação é permanente. Todos os seus dados serão removidos.</p>
                <div class="cv-profile-msg" id="cv-delete-msg" style="display:none"></div>
                <div class="cv-profile-field" id="cv-delete-confirm-area" style="display:none">
                    <label for="cv-delete-password">Confirme sua senha para excluir</label>
                    <input type="password" id="cv-delete-password" placeholder="Digite sua senha" />
                    <button type="button" id="cv-delete-confirm-btn" class="cv-btn cv-btn-danger" style="margin-top:10px">
                        <span class="cv-btn-text">Confirmar exclusão</span>
                        <span class="cv-btn-loading" style="display:none">⏳ Excluindo...</span>
                    </button>
                </div>
                <button type="button" id="cv-delete-account-btn" class="cv-btn cv-btn-outline-danger">
                    Quero excluir minha conta
                </button>
            </div>

        </div>

        <style>
        .cv-profile-box { max-width:560px; }
        .cv-profile-avatar-section { text-align:center; margin-bottom:28px; }
        .cv-profile-avatar { width:100px; height:100px; border-radius:50%; border:3px solid #C9A27E; object-fit:cover; }
        .cv-profile-avatar-hint { color:#8A6A55; font-size:12px; margin-top:8px; }
        .cv-profile-section { background:#F8F0E4; border-radius:10px; padding:24px; margin-bottom:20px; }
        .cv-profile-section-title { color:#3B2418; font-family:'Oswald',sans-serif; font-size:16px; margin:0 0 16px; }
        .cv-profile-hint { color:#8A6A55; font-size:13px; margin:-10px 0 14px; }
        .cv-profile-field { margin-bottom:14px; }
        .cv-profile-field label { display:block; color:#6B4C3B; font-size:13px; font-weight:600; margin-bottom:5px; }
        .cv-profile-field input[type="text"],
        .cv-profile-field input[type="email"],
        .cv-profile-field input[type="password"],
        .cv-profile-field select { width:100%; padding:10px 12px; background:#FFFFFF; border:1px solid #EADBC6; border-radius:6px; color:#3B2418; font-size:14px; box-sizing:border-box; }
        .cv-profile-field input:focus,
        .cv-profile-field select:focus { border-color:#C9A27E; outline:none; }
        .cv-profile-msg { padding:10px 14px; border-radius:6px; font-size:13px; margin-bottom:14px; }
        .cv-profile-msg.success { background:#EAF6EA; color:#388038; border:1px solid #2d6a2d; }
        .cv-profile-msg.error   { background:#F6EAEA; color:#D62C1A; border:1px solid #6a2d2d; }
        .cv-profile-danger { border:1px solid #DCB2B2; }
        .cv-btn { display:inline-block; padding:10px 20px; border-radius:6px; border:none; font-size:14px; font-weight:700; cursor:pointer; transition:all .2s; }
        .cv-btn-primary   { background:#F2A51A; color:#3B2418; }
        .cv-btn-primary:hover { background:#F2A51A; }
        .cv-btn-secondary { background:#F3E6D3; color:#3B2418; }
        .cv-btn-secondary:hover { background:#F3E6D3; }
        .cv-btn-danger    { background:#e74c3c; color:#3B2418; width:100%; text-align:center; }
        .cv-btn-danger:hover { background:#c0392b; }
        .cv-btn-outline-danger { background:transparent; color:#D62C1A; border:1px solid #e74c3c; }
        .cv-btn-outline-danger:hover { background:#F6EAEA; }
        </style>

        <script>
        (function($){
            var ajaxUrl = cvPublic.ajaxUrl;

            function showMsg($el, type, msg) {
                $el.removeClass('success error').addClass(type).text(msg).show();
            }
            function btnLoading($btn, loading) {
                $btn.find('.cv-btn-text').toggle(!loading);
                $btn.find('.cv-btn-loading').toggle(loading);
                $btn.prop('disabled', loading);
            }

            // Salvar dados pessoais
            $('#cv-profile-save-btn').on('click', function(){
                var $btn = $(this);
                var name  = $('#cv-profile-name').val().trim();
                var email = $('#cv-profile-email').val().trim();
                var $msg  = $('#cv-profile-msg');

                if (!name || !email) { showMsg($msg, 'error', 'Preencha nome e e-mail.'); return; }

                btnLoading($btn, true);
                $.post(ajaxUrl, {
                    action: 'cv_update_profile',
                    nonce:  $('#cv-profile-nonce').val(),
                    name:   name,
                    email:  email
                }, function(res){
                    showMsg($msg, res.success ? 'success' : 'error', res.data.message);
                    btnLoading($btn, false);
                }).fail(function(){ showMsg($msg, 'error', 'Erro de conexão.'); btnLoading($btn, false); });
            });

            // Salvar gênero favorito
            $('#cv-genre-save-btn').on('click', function(){
                var $btn  = $(this);
                var genre = $('#cv-profile-genre').val();
                var $msg  = $('#cv-profile-msg');
                btnLoading($btn, true);
                $.post(ajaxUrl, {
                    action: 'cv_save_genre_preference',
                    nonce:  $('#cv-profile-nonce').val(),
                    genre:  genre
                }, function(res){
                    showMsg($msg, res.success ? 'success' : 'error', res.data.message);
                    btnLoading($btn, false);
                }).fail(function(){ showMsg($msg, 'error', 'Erro de conexão.'); btnLoading($btn, false); });
            });

            // Alterar senha
            $('#cv-pw-save-btn').on('click', function(){
                var $btn    = $(this);
                var current = $('#cv-pw-current').val();
                var newpw   = $('#cv-pw-new').val();
                var confirm = $('#cv-pw-confirm').val();
                var $msg    = $('#cv-pw-msg');

                if (!current) { showMsg($msg, 'error', 'Informe sua senha atual.'); return; }
                if (newpw.length < 8) { showMsg($msg, 'error', 'A nova senha precisa ter pelo menos 8 caracteres.'); return; }
                if (newpw !== confirm) { showMsg($msg, 'error', 'As senhas não coincidem.'); return; }

                btnLoading($btn, true);
                $.post(ajaxUrl, {
                    action:       'cv_change_password',
                    nonce:        $('#cv-profile-nonce').val(),
                    current_pass: current,
                    new_pass:     newpw
                }, function(res){
                    showMsg($msg, res.success ? 'success' : 'error', res.data.message);
                    if (res.success) { $('#cv-pw-current, #cv-pw-new, #cv-pw-confirm').val(''); }
                    btnLoading($btn, false);
                }).fail(function(){ showMsg($msg, 'error', 'Erro de conexão.'); btnLoading($btn, false); });
            });

            // Mostrar campo de confirmação para excluir conta
            $('#cv-delete-account-btn').on('click', function(){
                $(this).hide();
                $('#cv-delete-confirm-area').slideDown(200);
            });

            // Confirmar exclusão
            $('#cv-delete-confirm-btn').on('click', function(){
                var $btn = $(this);
                var pw   = $('#cv-delete-password').val();
                var $msg = $('#cv-delete-msg');

                if (!pw) { showMsg($msg, 'error', 'Digite sua senha para confirmar.'); return; }

                if (!confirm('Tem certeza? Esta ação não pode ser desfeita.')) { return; }

                btnLoading($btn, true);
                $.post(ajaxUrl, {
                    action:   'cv_delete_account',
                    nonce:    $('#cv-profile-nonce').val(),
                    password: pw
                }, function(res){
                    if (res.success) {
                        showMsg($msg, 'success', 'Conta excluída. Redirecionando...');
                        setTimeout(function(){ window.location.href = '/'; }, 1500);
                    } else {
                        showMsg($msg, 'error', res.data.message || 'Erro ao excluir.');
                        btnLoading($btn, false);
                    }
                }).fail(function(){ showMsg($msg, 'error', 'Erro de conexão.'); btnLoading($btn, false); });
            });

        }(jQuery));
        </script>
        <?php
        return ob_get_clean();
    }

    // ── AJAX: atualizar nome e e-mail ─────────────────────────────

    public static function update_profile() {
        check_ajax_referer( 'cv_profile_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }

        $user_id = get_current_user_id();
        $name    = sanitize_text_field( $_POST['name']  ?? '' );
        $email   = sanitize_email(      $_POST['email'] ?? '' );

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'O nome não pode ficar vazio.' ) );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'E-mail inválido.' ) );
        }

        // Verifica se o e-mail já pertence a outro usuário
        $existing = get_user_by( 'email', $email );
        if ( $existing && $existing->ID !== $user_id ) {
            wp_send_json_error( array( 'message' => 'Este e-mail já está sendo usado por outra conta.' ) );
        }

        $result = wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $name,
            'first_name'   => $name,
            'user_email'   => $email,
        ) );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => 'Erro ao salvar. Tente novamente.' ) );
        }

        wp_send_json_success( array( 'message' => '✓ Dados atualizados com sucesso!' ) );
    }

    // ── AJAX: trocar senha ────────────────────────────────────────

    public static function change_password() {
        check_ajax_referer( 'cv_profile_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }

        $user_id      = get_current_user_id();
        $current_pass = $_POST['current_pass'] ?? '';
        $new_pass     = $_POST['new_pass']     ?? '';

        if ( empty( $current_pass ) || empty( $new_pass ) ) {
            wp_send_json_error( array( 'message' => 'Preencha todos os campos.' ) );
        }
        if ( strlen( $new_pass ) < 8 ) {
            wp_send_json_error( array( 'message' => 'A nova senha precisa ter pelo menos 8 caracteres.' ) );
        }

        // Valida a senha atual antes de trocar
        $user = get_userdata( $user_id );
        if ( ! wp_check_password( $current_pass, $user->user_pass, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Senha atual incorreta.' ) );
        }

        // Atualiza a senha (WordPress usa bcrypt automaticamente)
        wp_set_password( $new_pass, $user_id );

        // Mantém o usuário logado após trocar a senha
        wp_set_auth_cookie( $user_id, true, is_ssl() );

        wp_send_json_success( array( 'message' => '✓ Senha alterada com sucesso!' ) );
    }

    // ── AJAX: salvar gênero favorito ──────────────────────────────

    public static function save_genre() {
        check_ajax_referer( 'cv_profile_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }

        $genre   = sanitize_key( $_POST['genre'] ?? '' );
        $user_id = get_current_user_id();

        if ( $genre ) {
            // Valida se o gênero existe
            $term = get_term_by( 'slug', $genre, 'cv_genre' );
            if ( ! $term ) {
                wp_send_json_error( array( 'message' => 'Gênero inválido.' ) );
            }
            update_user_meta( $user_id, '_cv_favorite_genre', $genre );
        } else {
            delete_user_meta( $user_id, '_cv_favorite_genre' );
        }

        wp_send_json_success( array( 'message' => '✓ Preferência salva!' ) );
    }

    // ── AJAX: excluir conta ───────────────────────────────────────

    public static function delete_account() {
        check_ajax_referer( 'cv_profile_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Login necessário.' ) );
        }

        $user_id  = get_current_user_id();
        $password = $_POST['password'] ?? '';

        if ( empty( $password ) ) {
            wp_send_json_error( array( 'message' => 'Confirme sua senha para excluir a conta.' ) );
        }

        // Valida a senha antes de qualquer exclusão
        $user = get_userdata( $user_id );
        if ( ! wp_check_password( $password, $user->user_pass, $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Senha incorreta.' ) );
        }

        // Remove dados do usuário das tabelas customizadas
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'cv_favorites',      array( 'user_id' => $user_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cv_ratings',        array( 'user_id' => $user_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cv_lyric_comments', array( 'user_id' => $user_id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'cv_plays_log',      array( 'user_id' => $user_id ), array( '%d' ) );

        // Remove playlists e seus itens
        $playlist_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}cv_playlists WHERE user_id = %d",
            $user_id
        ) );
        foreach ( $playlist_ids as $pid ) {
            $wpdb->delete( $wpdb->prefix . 'cv_playlist_items', array( 'playlist_id' => $pid ), array( '%d' ) );
        }
        $wpdb->delete( $wpdb->prefix . 'cv_playlists', array( 'user_id' => $user_id ), array( '%d' ) );

        // Faz logout antes de excluir
        wp_logout();

        // Exclui o usuário do WordPress
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user( $user_id );

        wp_send_json_success( array( 'message' => 'Conta excluída com sucesso.' ) );
    }
}

CV_Profile_Edit::init();
