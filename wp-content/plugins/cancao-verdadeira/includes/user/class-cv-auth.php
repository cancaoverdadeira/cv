<?php
// cancao-verdadeira/includes/user/class-cv-auth.php
// Gerado em: 2026-06-21 20:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo de autenticação nativa: login, cadastro e logout sem depender
// do Ultimate Member. Shortcodes [cv_login_form] e [cv_register_form]
// funcionam em qualquer página do WordPress. Se o Ultimate Member
// estiver ativo, este módulo detecta e delega automaticamente para ele,
// evitando duplicidade. Envia boas-vindas via MailerLite ao cadastrar.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Auth {

    public static function init() {

        // ── Shortcodes ────────────────────────────────────────────
        add_shortcode( 'cv_login_form',    array( __CLASS__, 'shortcode_login' ) );
        add_shortcode( 'cv_register_form', array( __CLASS__, 'shortcode_register' ) );

        // ── Endpoints AJAX ────────────────────────────────────────
        add_action( 'wp_ajax_nopriv_cv_do_login',    array( __CLASS__, 'ajax_login' ) );
        add_action( 'wp_ajax_nopriv_cv_do_register', array( __CLASS__, 'ajax_register' ) );
        add_action( 'wp_ajax_cv_do_logout',          array( __CLASS__, 'ajax_logout' ) );

        // ── Hook: boas-vindas ao cadastrar (nativo WordPress) ────
        add_action( 'user_register', array( __CLASS__, 'on_user_register' ), 10, 1 );

        // ── Cria páginas de login e cadastro na ativação ─────────
        add_action( 'cv_activate_pages', array( __CLASS__, 'create_auth_pages' ) );

        // ── Redireciona wp-login.php para a página nativa ────────
        // Só ativa se a opção estiver marcada nas configurações
        if ( get_option( 'cv_custom_login_page', '0' ) === '1' ) {
            add_action( 'init', array( __CLASS__, 'redirect_wp_login' ) );
        }

        // ── /meu-perfil/ → "Minha conta" do Ultimate Member ───────
        add_action( 'template_redirect', array( __CLASS__, 'redirect_profile_to_um_account' ) );

        // ── Nonce de auth nos dados localizados do JS ─────────────
        add_filter( 'cv_public_js_data', array( __CLASS__, 'add_auth_nonces' ) );
    }

    // Com o UM ativo, a edição de dados fica só na página "Minha conta" dele;
    // a página nativa /meu-perfil/ vira apenas um atalho (links antigos seguem válidos).
    public static function redirect_profile_to_um_account() {
        if ( ! is_page( 'meu-perfil' ) || ! function_exists( 'um_get_core_page' ) ) {
            return;
        }
        $account = um_get_core_page( 'account' );
        if ( $account ) {
            wp_safe_redirect( $account, 301 );
            exit;
        }
    }

    // ── SHORTCODE: formulário de login ────────────────────────────

    public static function shortcode_login( $atts ) {

        // Se já está logado, mostra mensagem e link para o dashboard
        if ( is_user_logged_in() ) {
            $user = wp_get_current_user();
            $url  = home_url( '/minha-area/' );
            return '<div class="cv-auth-logged">'
                 . '<p>Olá, <strong>' . esc_html( $user->display_name ) . '</strong>! Você já está logado.</p>'
                 . '<a href="' . esc_url( $url ) . '" class="cv-btn cv-btn-primary">Ir para minha área</a>'
                 . '</div>';
        }

        // Se Ultimate Member está ativo, usa o shortcode dele
        // IDs reais dos formulários ficam em um_core_forms (não em um_login_id,
        // que nunca existiu — bug encontrado em 22/09/2026: com o UM ativo essa
        // delegação renderizava [ultimatemember form_id=""], vazio).
        if ( class_exists( 'UM' ) ) {
            $um_forms = get_option( 'um_core_forms', array() );
            $login_id = isset( $um_forms['login'] ) ? (int) $um_forms['login'] : 0;
            if ( $login_id ) {
                return do_shortcode( '[ultimatemember form_id="' . $login_id . '"]' );
            }
            // Sem formulário de login do UM configurado — cai no formulário nativo abaixo.
        }

        $redirect = isset( $atts['redirect'] ) ? esc_url( $atts['redirect'] ) : home_url( '/minha-area/' );
        $register_url = home_url( '/cadastro/' );
        $forgot_url   = wp_lostpassword_url();

        ob_start();
        ?>
        <div class="cv-auth-box" id="cv-login-box">
            <div class="cv-auth-header">
                <span class="cv-auth-icon">🎵</span>
                <h2 class="cv-auth-title">Entrar na Canção Verdadeira</h2>
                <p class="cv-auth-subtitle">Acesse favoritos, playlists e seu histórico</p>
            </div>

            <div class="cv-auth-msg" id="cv-login-msg" style="display:none"></div>

            <div class="cv-auth-form">
                <input type="hidden" id="cv-login-nonce" value="<?php echo esc_attr( wp_create_nonce( 'cv_auth_nonce' ) ); ?>" />
                <input type="hidden" id="cv-login-redirect" value="<?php echo esc_url( $redirect ); ?>" />

                <div class="cv-auth-field">
                    <label for="cv-login-email">E-mail</label>
                    <input type="email" id="cv-login-email" placeholder="seu@email.com" autocomplete="email" />
                </div>

                <div class="cv-auth-field">
                    <label for="cv-login-password">Senha</label>
                    <div class="cv-auth-password-wrap">
                        <input type="password" id="cv-login-password" placeholder="Sua senha" autocomplete="current-password" />
                        <button type="button" class="cv-auth-eye" data-target="cv-login-password" title="Mostrar/ocultar senha">👁</button>
                    </div>
                </div>

                <div class="cv-auth-remember">
                    <label>
                        <input type="checkbox" id="cv-login-remember" />
                        Lembrar de mim
                    </label>
                    <a href="<?php echo esc_url( $forgot_url ); ?>" class="cv-auth-link">Esqueci minha senha</a>
                </div>

                <button type="button" id="cv-login-btn" class="cv-btn cv-btn-primary cv-btn-full">
                    <span class="cv-btn-text">Entrar</span>
                    <span class="cv-btn-loading" style="display:none">⏳ Entrando...</span>
                </button>

                <div class="cv-auth-divider">ou</div>

                <p class="cv-auth-switch">
                    Não tem conta?
                    <a href="<?php echo esc_url( $register_url ); ?>" class="cv-auth-link">Cadastre-se grátis</a>
                </p>
            </div>
        </div>

        <style>
        .cv-auth-box { max-width:420px; margin:0 auto; background:#F8F0E4; border-radius:12px; padding:36px 32px; box-shadow:0 8px 32px rgba(123,58,34,0.12); }
        .cv-auth-header { text-align:center; margin-bottom:28px; }
        .cv-auth-icon { font-size:36px; display:block; margin-bottom:10px; }
        .cv-auth-title { color:#3B2418; font-family:'Oswald',sans-serif; font-size:22px; margin:0 0 6px; }
        .cv-auth-subtitle { color:#8A6A55; font-size:14px; margin:0; }
        .cv-auth-field { margin-bottom:18px; }
        .cv-auth-field label { display:block; color:#6B4C3B; font-size:13px; font-weight:600; margin-bottom:6px; }
        .cv-auth-field input[type="email"],
        .cv-auth-field input[type="text"],
        .cv-auth-field input[type="password"] { width:100%; padding:11px 14px; background:#FFFFFF; border:1px solid #EADBC6; border-radius:7px; color:#3B2418; font-size:15px; box-sizing:border-box; transition:border-color .2s; }
        .cv-auth-field input:focus { border-color:#C9A27E; outline:none; box-shadow:0 0 0 2px rgba(242,165,26,0.2); }
        .cv-auth-password-wrap { position:relative; }
        .cv-auth-password-wrap input { padding-right:44px; }
        .cv-auth-eye { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; font-size:16px; padding:4px; color:#8A6A55; }
        .cv-auth-remember { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; font-size:13px; color:#6B4C3B; }
        .cv-auth-remember label { display:flex; align-items:center; gap:6px; cursor:pointer; }
        .cv-auth-link { color:#7B3A22; text-decoration:none; }
        .cv-auth-link:hover { color:#7B3A22; text-decoration:underline; }
        .cv-btn { display:inline-block; padding:12px 24px; border-radius:7px; border:none; font-size:15px; font-weight:700; cursor:pointer; transition:all .2s; }
        .cv-btn-primary { background:#F2A51A; color:#3B2418; }
        .cv-btn-primary:hover { background:#F2A51A; }
        .cv-btn-full { width:100%; text-align:center; }
        .cv-auth-divider { text-align:center; color:#8A6A55; margin:18px 0; font-size:13px; position:relative; }
        .cv-auth-divider::before, .cv-auth-divider::after { content:''; position:absolute; top:50%; width:42%; height:1px; background:#F3E6D3; }
        .cv-auth-divider::before { left:0; } .cv-auth-divider::after { right:0; }
        .cv-auth-switch { text-align:center; color:#8A6A55; font-size:14px; margin:0; }
        .cv-auth-msg { padding:12px 16px; border-radius:7px; font-size:14px; margin-bottom:18px; }
        .cv-auth-msg.success { background:#EAF6EA; color:#388038; border:1px solid #2d6a2d; }
        .cv-auth-msg.error   { background:#F6EAEA; color:#D62C1A; border:1px solid #6a2d2d; }
        .cv-auth-terms { font-size:12px; color:#8A6A55; margin-top:14px; text-align:center; }
        .cv-auth-strength { height:4px; border-radius:2px; margin-top:6px; transition:all .3s; background:#F3E6D3; }
        .cv-auth-strength.weak   { background:#e74c3c; width:33%; }
        .cv-auth-strength.medium { background:#F2A51A; width:66%; }
        .cv-auth-strength.strong { background:#27ae60; width:100%; }
        </style>

        <script>
        (function($){
            // Mostrar/ocultar senha
            $(document).on('click', '.cv-auth-eye', function(){
                var $input = $('#' + $(this).data('target'));
                var type = $input.attr('type') === 'password' ? 'text' : 'password';
                $input.attr('type', type);
                $(this).text(type === 'password' ? '👁' : '🙈');
            });

            // Login via AJAX
            $('#cv-login-btn').on('click', function(){
                var email    = $('#cv-login-email').val().trim();
                var password = $('#cv-login-password').val();
                var remember = $('#cv-login-remember').is(':checked') ? '1' : '0';
                var nonce    = $('#cv-login-nonce').val();
                var redirect = $('#cv-login-redirect').val();
                var $btn     = $(this);
                var $msg     = $('#cv-login-msg');

                if (!email || !password) {
                    $msg.removeClass('success').addClass('error').text('Preencha e-mail e senha.').show();
                    return;
                }

                $btn.find('.cv-btn-text').hide();
                $btn.find('.cv-btn-loading').show();
                $btn.prop('disabled', true);

                $.post(cvPublic.ajaxUrl, {
                    action:   'cv_do_login',
                    nonce:    nonce,
                    email:    email,
                    password: password,
                    remember: remember
                }, function(res){
                    if (res.success) {
                        $msg.removeClass('error').addClass('success').text('✓ Login realizado! Redirecionando...').show();
                        setTimeout(function(){ window.location.href = res.data.redirect || redirect; }, 800);
                    } else {
                        $msg.removeClass('success').addClass('error').text(res.data.message || 'Erro ao fazer login.').show();
                        $btn.find('.cv-btn-text').show();
                        $btn.find('.cv-btn-loading').hide();
                        $btn.prop('disabled', false);
                    }
                }).fail(function(){
                    $msg.removeClass('success').addClass('error').text('Erro de conexão. Tente novamente.').show();
                    $btn.find('.cv-btn-text').show();
                    $btn.find('.cv-btn-loading').hide();
                    $btn.prop('disabled', false);
                });
            });

            // Permite login com Enter
            $('#cv-login-password').on('keypress', function(e){
                if (e.which === 13) { $('#cv-login-btn').trigger('click'); }
            });

        }(jQuery));
        </script>
        <?php
        return ob_get_clean();
    }

    // ── SHORTCODE: formulário de cadastro ─────────────────────────

    public static function shortcode_register( $atts ) {

        if ( is_user_logged_in() ) {
            $url = home_url( '/minha-area/' );
            return '<div class="cv-auth-logged"><p>Você já tem uma conta ativa.</p>'
                 . '<a href="' . esc_url( $url ) . '" class="cv-btn cv-btn-primary">Ir para minha área</a></div>';
        }

        if ( class_exists( 'UM' ) ) {
            $um_forms    = get_option( 'um_core_forms', array() );
            $register_id = isset( $um_forms['register'] ) ? (int) $um_forms['register'] : 0;
            if ( $register_id ) {
                return do_shortcode( '[ultimatemember form_id="' . $register_id . '"]' );
            }
            // Sem formulário de cadastro do UM configurado — cai no formulário nativo abaixo.
        }

        // Verifica se cadastro de usuários está habilitado no WordPress
        if ( ! get_option( 'users_can_register' ) ) {
            return '<div class="cv-auth-msg error" style="display:block">O cadastro está desabilitado no momento.</div>';
        }

        $login_url = home_url( '/login/' );

        ob_start();
        ?>
        <div class="cv-auth-box" id="cv-register-box">
            <div class="cv-auth-header">
                <span class="cv-auth-icon">🎶</span>
                <h2 class="cv-auth-title">Criar conta grátis</h2>
                <p class="cv-auth-subtitle">Favoritos, playlists e muito mais</p>
            </div>

            <div class="cv-auth-msg" id="cv-register-msg" style="display:none"></div>

            <div class="cv-auth-form">
                <input type="hidden" id="cv-register-nonce" value="<?php echo esc_attr( wp_create_nonce( 'cv_auth_nonce' ) ); ?>" />

                <div class="cv-auth-field">
                    <label for="cv-register-name">Seu nome</label>
                    <input type="text" id="cv-register-name" placeholder="Como quer ser chamado" autocomplete="name" />
                </div>

                <div class="cv-auth-field">
                    <label for="cv-register-email">E-mail</label>
                    <input type="email" id="cv-register-email" placeholder="seu@email.com" autocomplete="email" />
                </div>

                <div class="cv-auth-field">
                    <label for="cv-register-password">Senha <span style="font-weight:400;color:#8A6A55">(mín. 8 caracteres)</span></label>
                    <div class="cv-auth-password-wrap">
                        <input type="password" id="cv-register-password" placeholder="Crie uma senha segura" autocomplete="new-password" />
                        <button type="button" class="cv-auth-eye" data-target="cv-register-password" title="Mostrar senha">👁</button>
                    </div>
                    <div class="cv-auth-strength" id="cv-pw-strength"></div>
                </div>

                <div class="cv-auth-field">
                    <label for="cv-register-password2">Confirmar senha</label>
                    <div class="cv-auth-password-wrap">
                        <input type="password" id="cv-register-password2" placeholder="Repita a senha" autocomplete="new-password" />
                        <button type="button" class="cv-auth-eye" data-target="cv-register-password2" title="Mostrar senha">👁</button>
                    </div>
                </div>

                <button type="button" id="cv-register-btn" class="cv-btn cv-btn-primary cv-btn-full">
                    <span class="cv-btn-text">Criar conta</span>
                    <span class="cv-btn-loading" style="display:none">⏳ Criando conta...</span>
                </button>

                <p class="cv-auth-terms">
                    Ao criar uma conta você concorda com os
                    <a href="<?php echo esc_url( home_url( '/termos/' ) ); ?>" class="cv-auth-link" target="_blank">Termos de Uso</a>.
                </p>

                <div class="cv-auth-divider">ou</div>

                <p class="cv-auth-switch">
                    Já tem conta?
                    <a href="<?php echo esc_url( $login_url ); ?>" class="cv-auth-link">Fazer login</a>
                </p>
            </div>
        </div>

        <script>
        (function($){
            // Indicador de força da senha
            $('#cv-register-password').on('input', function(){
                var pw  = $(this).val();
                var $bar = $('#cv-pw-strength');
                if (!pw) { $bar.attr('class', 'cv-auth-strength'); return; }
                var score = 0;
                if (pw.length >= 8)  score++;
                if (/[A-Z]/.test(pw)) score++;
                if (/[0-9]/.test(pw)) score++;
                if (/[^A-Za-z0-9]/.test(pw)) score++;
                var cls = score <= 1 ? 'weak' : score <= 2 ? 'medium' : 'strong';
                $bar.attr('class', 'cv-auth-strength ' + cls);
            });

            // Cadastro via AJAX
            $('#cv-register-btn').on('click', function(){
                var name   = $('#cv-register-name').val().trim();
                var email  = $('#cv-register-email').val().trim();
                var pw     = $('#cv-register-password').val();
                var pw2    = $('#cv-register-password2').val();
                var nonce  = $('#cv-register-nonce').val();
                var $btn   = $(this);
                var $msg   = $('#cv-register-msg');

                // Validações no front-end
                if (!name)  { showMsg('error', 'Informe seu nome.'); return; }
                if (!email) { showMsg('error', 'Informe um e-mail válido.'); return; }
                if (pw.length < 8) { showMsg('error', 'A senha precisa ter pelo menos 8 caracteres.'); return; }
                if (pw !== pw2)    { showMsg('error', 'As senhas não coincidem.'); return; }

                $btn.find('.cv-btn-text').hide();
                $btn.find('.cv-btn-loading').show();
                $btn.prop('disabled', true);

                $.post(cvPublic.ajaxUrl, {
                    action: 'cv_do_register',
                    nonce:  nonce,
                    name:   name,
                    email:  email,
                    password: pw
                }, function(res){
                    if (res.success) {
                        showMsg('success', '✓ Conta criada! Entrando...');
                        setTimeout(function(){
                            window.location.href = res.data.redirect || '<?php echo esc_js( home_url( '/minha-area/' ) ); ?>';
                        }, 1200);
                    } else {
                        showMsg('error', res.data.message || 'Erro ao criar conta.');
                        $btn.find('.cv-btn-text').show();
                        $btn.find('.cv-btn-loading').hide();
                        $btn.prop('disabled', false);
                    }
                }).fail(function(){
                    showMsg('error', 'Erro de conexão. Tente novamente.');
                    $btn.find('.cv-btn-text').show();
                    $btn.find('.cv-btn-loading').hide();
                    $btn.prop('disabled', false);
                });

                function showMsg(type, msg) {
                    $msg.removeClass('success error').addClass(type).text(msg).show();
                }
            });

        }(jQuery));
        </script>
        <?php
        return ob_get_clean();
    }

    // ── AJAX: processar login ─────────────────────────────────────

    public static function ajax_login() {
        check_ajax_referer( 'cv_auth_nonce', 'nonce' );

        $email    = sanitize_email( $_POST['email']    ?? '' );
        $password = $_POST['password'] ?? '';
        $remember = ! empty( $_POST['remember'] ) && '1' === $_POST['remember'];

        if ( ! $email || ! $password ) {
            wp_send_json_error( array( 'message' => 'Preencha e-mail e senha.' ) );
        }

        // Aceita login por e-mail (WordPress usa username internamente)
        $user = get_user_by( 'email', $email );

        if ( ! $user ) {
            // Tenta como username também (para contas criadas antes)
            $user = get_user_by( 'login', $email );
        }

        if ( ! $user ) {
            wp_send_json_error( array( 'message' => 'E-mail não encontrado.' ) );
        }

        $credentials = array(
            'user_login'    => $user->user_login,
            'user_password' => $password,
            'remember'      => $remember,
        );

        $result = wp_signon( $credentials, is_ssl() );

        if ( is_wp_error( $result ) ) {
            // Mensagem genérica por segurança (não revela qual campo está errado)
            wp_send_json_error( array( 'message' => 'E-mail ou senha incorretos.' ) );
        }

        wp_send_json_success( array(
            'redirect' => home_url( '/minha-area/' ),
            'name'     => $result->display_name,
        ) );
    }

    // ── AJAX: processar cadastro ──────────────────────────────────

    public static function ajax_register() {
        check_ajax_referer( 'cv_auth_nonce', 'nonce' );

        if ( ! get_option( 'users_can_register' ) ) {
            wp_send_json_error( array( 'message' => 'Cadastro desabilitado.' ) );
        }

        $name     = sanitize_text_field( $_POST['name']     ?? '' );
        $email    = sanitize_email(      $_POST['email']    ?? '' );
        $password = $_POST['password'] ?? '';

        // Validações no servidor (nunca confiar só no front-end)
        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => 'Informe seu nome.' ) );
        }
        if ( ! is_email( $email ) ) {
            wp_send_json_error( array( 'message' => 'E-mail inválido.' ) );
        }
        if ( email_exists( $email ) ) {
            wp_send_json_error( array( 'message' => 'Este e-mail já está cadastrado.' ) );
        }
        if ( strlen( $password ) < 8 ) {
            wp_send_json_error( array( 'message' => 'A senha precisa ter pelo menos 8 caracteres.' ) );
        }

        // Gera username único a partir do e-mail
        $username = self::generate_username( $email );

        // Cria o usuário (WordPress armazena senha com bcrypt automaticamente)
        $user_id = wp_create_user( $username, $password, $email );

        if ( is_wp_error( $user_id ) ) {
            wp_send_json_error( array( 'message' => 'Erro ao criar conta. Tente novamente.' ) );
        }

        // Salva o nome
        wp_update_user( array(
            'ID'           => $user_id,
            'display_name' => $name,
            'first_name'   => $name,
        ) );

        // Faz login automático após o cadastro
        wp_set_current_user( $user_id );
        wp_set_auth_cookie( $user_id, false, is_ssl() );

        wp_send_json_success( array(
            'redirect' => home_url( '/minha-area/' ),
            'name'     => $name,
        ) );
    }

    // ── AJAX: logout ──────────────────────────────────────────────

    public static function ajax_logout() {
        check_ajax_referer( 'cv_auth_nonce', 'nonce' );

        if ( ! is_user_logged_in() ) {
            wp_send_json_success( array( 'redirect' => home_url( '/' ) ) );
        }

        wp_logout();

        wp_send_json_success( array( 'redirect' => home_url( '/' ) ) );
    }

    // ── Hook: ao criar usuário (nativo WordPress) ─────────────────

    public static function on_user_register( $user_id ) {
        // Não duplica se o UM já disparou o hook dele
        if ( class_exists( 'UM' ) ) {
            return;
        }

        $user     = get_userdata( $user_id );
        $email    = $user->user_email;
        $name     = $user->display_name;
        $api_key  = get_option( 'cv_mailerlite_api_key', '' );
        $group_id = get_option( 'cv_mailerlite_group_id', '' );

        // Envia boas-vindas via MailerLite (não bloqueia em caso de falha)
        if ( $api_key && $email ) {
            wp_remote_post(
                'https://connect.mailerlite.com/api/subscribers',
                array(
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => 'application/json',
                    ),
                    'body'    => wp_json_encode( array(
                        'email'  => $email,
                        'fields' => array( 'name' => $name ),
                        'groups' => $group_id ? array( $group_id ) : array(),
                    ) ),
                    'timeout'   => 8,
                    'blocking'  => false, // não trava o cadastro se MailerLite cair
                )
            );
        }
    }

    // ── Criação das páginas de login e cadastro ───────────────────

    public static function create_auth_pages() {
        $pages = array(
            array(
                'title'    => 'Login',
                'slug'     => 'login',
                'content'  => '[cv_login_form]',
                'template' => '',
            ),
            array(
                'title'    => 'Cadastro',
                'slug'     => 'cadastro',
                'content'  => '[cv_register_form]',
                'template' => '',
            ),
        );

        foreach ( $pages as $page ) {
            if ( ! get_page_by_path( $page['slug'] ) ) {
                wp_insert_post( array(
                    'post_title'   => $page['title'],
                    'post_name'    => $page['slug'],
                    'post_content' => $page['content'],
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                ) );
            }
        }
    }

    // ── Redireciona wp-login.php para /login/ ─────────────────────

    public static function redirect_wp_login() {
        $page_viewed = basename( $_SERVER['REQUEST_URI'] );

        if ( 'wp-login.php' === $page_viewed && ! isset( $_POST['log'] ) ) {
            $action = $_GET['action'] ?? '';

            if ( 'register' === $action ) {
                wp_redirect( home_url( '/cadastro/' ) );
                exit;
            }

            if ( '' === $action || 'login' === $action ) {
                wp_redirect( home_url( '/login/' ) );
                exit;
            }
        }
    }

    // ── Adiciona nonce de auth ao objeto JS ───────────────────────

    public static function add_auth_nonces( $data ) {
        $data['nonces']['auth']   = wp_create_nonce( 'cv_auth_nonce' );
        $data['loginUrl']         = home_url( '/login/' );
        $data['registerUrl']      = home_url( '/cadastro/' );
        $data['dashboardUrl']     = home_url( '/minha-area/' );
        return $data;
    }

    // ── Utilitários ───────────────────────────────────────────────

    private static function generate_username( $email ) {
        // Base: parte antes do @ no e-mail, sem caracteres especiais
        $base = sanitize_user( strstr( $email, '@', true ), true );
        $base = strtolower( preg_replace( '/[^a-z0-9]/', '', $base ) );
        if ( empty( $base ) ) { $base = 'usuario'; }

        $username = $base;
        $i = 1;

        // Garante unicidade
        while ( username_exists( $username ) ) {
            $username = $base . $i;
            $i++;
        }

        return $username;
    }
}

CV_Auth::init();
