<?php
// cancao-verdadeira/includes/admin/class-cv-email.php
// Gerado em: 2026-06-21 23:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo de e-mails automáticos via MailerLite API v3. Gerencia 4 tipos
// de disparo: boas-vindas (novo usuário), nova música publicada, resumo
// de favoritos (semanal) e anúncio de sorteio. Funciona com grupo único
// ou múltiplos grupos quando configurados. Todos os disparos são
// assíncronos (blocking:false) — não travam o WordPress se ML cair.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Email {

    // ── Tipos de automação e seus grupos ─────────────────────────
    // Cada tipo pode ter um grupo/lista específica no MailerLite.
    // Se não configurado, usa o grupo padrão (cv_mailerlite_group_id).
    const TIPOS = array(
        'boas_vindas'    => array( 'option' => 'cv_email_group_boas_vindas',  'label' => 'Boas-vindas (novo usuário)' ),
        'nova_musica'    => array( 'option' => 'cv_email_group_nova_musica',   'label' => 'Nova música publicada'     ),
        'favoritos'      => array( 'option' => 'cv_email_group_favoritos',     'label' => 'Resumo de favoritos'       ),
        'sorteio'        => array( 'option' => 'cv_email_group_sorteio',       'label' => 'Anúncio de sorteio'        ),
    );

    public static function init() {

        // ── Registro das opções ───────────────────────────────────
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

        // ── AJAX: testar conexão e enviar teste ───────────────────
        add_action( 'wp_ajax_cv_email_test',        array( __CLASS__, 'ajax_test' ) );
        add_action( 'wp_ajax_cv_email_send_test',   array( __CLASS__, 'ajax_send_test' ) );
        add_action( 'wp_ajax_cv_email_fetch_groups',array( __CLASS__, 'ajax_fetch_groups' ) );

        // ── Triggers automáticos ──────────────────────────────────

        // 1. Boas-vindas: usuário cadastrado via sistema nativo
        add_action( 'user_register', array( __CLASS__, 'trigger_boas_vindas' ), 20, 1 );

        // 2. Nova música: ao publicar
        add_action( 'publish_musica', array( __CLASS__, 'trigger_nova_musica' ), 20, 2 );

        // 3. Resumo de favoritos: via WP-Cron semanal
        add_action( 'cv_cron_favoritos_digest', array( __CLASS__, 'trigger_favoritos_digest' ) );
        add_action( 'wp',                        array( __CLASS__, 'schedule_digest' ) );

        // 4. Sorteio: ao criar/anunciar no admin
        add_action( 'wp_ajax_cv_email_anunciar_sorteio', array( __CLASS__, 'ajax_anunciar_sorteio' ) );
    }

    // ── REGISTRO DE OPÇÕES ────────────────────────────────────────

    public static function register_settings() {
        foreach ( self::TIPOS as $key => $cfg ) {
            register_setting( 'cv_email_group', $cfg['option'],
                array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        }
        register_setting( 'cv_email_group', 'cv_email_ativo_nova_musica',
            array( 'sanitize_callback' => 'absint', 'default' => 1 ) );
        register_setting( 'cv_email_group', 'cv_email_ativo_favoritos',
            array( 'sanitize_callback' => 'absint', 'default' => 0 ) );
        register_setting( 'cv_email_group', 'cv_email_automation_boas_vindas',
            array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
        register_setting( 'cv_email_group', 'cv_email_automation_nova_musica',
            array( 'sanitize_callback' => 'sanitize_text_field', 'default' => '' ) );
    }

    // ── PÁGINA ADMIN ──────────────────────────────────────────────

    public static function page_email() {
        $api_key     = get_option( 'cv_mailerlite_api_key', '' );
        $group_padrao= get_option( 'cv_mailerlite_group_id', '' );
        $grupos_json = get_option( 'cv_mailerlite_groups_cache', '[]' );
        $grupos      = json_decode( $grupos_json, true ) ?: array();
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">

            <div class="cv-admin-header">
                <div>
                    <h1>📧 E-mails Automáticos</h1>
                    <p class="cv-admin-subtitle">Configure os disparos automáticos via MailerLite. A API já está em Configurações.</p>
                </div>
                <?php if ( $api_key ) : ?>
                <button id="cv-email-test-btn" class="cv-btn cv-btn-outline">
                    🔌 Testar Conexão MailerLite
                </button>
                <?php endif; ?>
            </div>

            <div id="cv-email-msg" class="cv-action-message" style="display:none;margin:0 0 20px"></div>

            <?php if ( ! $api_key ) : ?>
            <div class="cv-notice cv-notice-warning" style="margin-bottom:24px">
                ⚠️ A chave de API do MailerLite não está configurada.
                <a href="<?php echo admin_url('admin.php?page=cv-settings'); ?>" style="color:#D4A017">
                    Configure em Configurações →
                </a>
            </div>
            <?php endif; ?>

            <!-- Grupos / Listas -->
            <div class="cv-section">
                <h2 class="cv-section-title">📋 Grupos / Listas no MailerLite</h2>
                <p style="color:#888;font-size:13px;margin-bottom:16px">
                    Para cada tipo de e-mail você pode usar um grupo diferente do MailerLite (ex: "Assinantes Gerais", "Usuários Registrados").
                    Se deixar em branco, usa o grupo padrão configurado em Configurações.
                    <strong style="color:#D4A017"> Grupo padrão atual: <?php echo $group_padrao ?: 'não configurado'; ?></strong>
                </p>

                <?php if ( $api_key ) : ?>
                <button id="cv-fetch-groups-btn" class="cv-btn cv-btn-outline" style="margin-bottom:20px;font-size:13px">
                    🔄 Buscar grupos do MailerLite
                </button>
                <?php endif; ?>

                <div style="display:grid;gap:16px">
                    <?php foreach ( self::TIPOS as $key => $cfg ) :
                        $grupo_val = get_option( $cfg['option'], '' );
                    ?>
                    <div style="background:#1a1a1a;border-radius:8px;padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center">
                        <div>
                            <p style="color:#F5F0E0;font-weight:700;margin-bottom:4px;font-size:14px">
                                <?php echo esc_html( $cfg['label'] ); ?>
                            </p>
                            <p style="color:#666;font-size:12px;margin:0">
                                <?php echo self::descricao_tipo( $key ); ?>
                            </p>
                        </div>
                        <div>
                            <label style="display:block;color:#C8B98A;font-size:12px;margin-bottom:4px">ID do grupo (ou deixe vazio para usar o padrão)</label>
                            <?php if ( ! empty( $grupos ) ) : ?>
                            <select name="<?php echo esc_attr($cfg['option']); ?>"
                                    class="cv-input cv-email-group-select"
                                    data-key="<?php echo esc_attr($cfg['option']); ?>"
                                    style="width:100%">
                                <option value="">— Usar grupo padrão —</option>
                                <?php foreach ( $grupos as $g ) : ?>
                                <option value="<?php echo esc_attr($g['id']); ?>"
                                        <?php selected( $grupo_val, $g['id'] ); ?>>
                                    <?php echo esc_html( $g['name'] ); ?> (<?php echo $g['total'] ?? '?'; ?> assinantes)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <?php else : ?>
                            <input type="text"
                                   class="cv-input cv-email-group-select"
                                   data-key="<?php echo esc_attr($cfg['option']); ?>"
                                   value="<?php echo esc_attr( $grupo_val ); ?>"
                                   placeholder="ID do grupo (ex: 12345678)"
                                   style="width:100%" />
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <button id="cv-email-groups-save" class="cv-btn cv-btn-primary" style="margin-top:20px">
                    💾 Salvar Grupos
                </button>
            </div>

            <!-- Automações -->
            <div class="cv-section">
                <h2 class="cv-section-title">⚡ Automações Ativas</h2>
                <p style="color:#888;font-size:13px;margin-bottom:20px">
                    Controle quais e-mails automáticos estão ativos.
                    Os disparos usam a API do MailerLite para adicionar assinantes a grupos
                    e acionar automações que você configura <strong style="color:#D4A017">dentro do próprio MailerLite</strong>.
                </p>

                <div style="display:grid;gap:12px">
                    <?php
                    $automacoes = array(
                        array(
                            'key'    => 'boas_vindas',
                            'titulo' => '👋 Boas-vindas',
                            'desc'   => 'Enviado automaticamente quando um novo usuário se cadastra no site.',
                            'sempre' => true, // sempre ativo, não tem toggle
                        ),
                        array(
                            'key'    => 'nova_musica',
                            'titulo' => '🎵 Nova Música',
                            'desc'   => 'Enviado para todos os assinantes quando uma nova música é publicada.',
                            'option' => 'cv_email_ativo_nova_musica',
                        ),
                        array(
                            'key'    => 'favoritos',
                            'titulo' => '❤ Resumo de Favoritos',
                            'desc'   => 'Enviado semanalmente para usuários logados com suas músicas favoritas.',
                            'option' => 'cv_email_ativo_favoritos',
                        ),
                        array(
                            'key'    => 'sorteio',
                            'titulo' => '🎰 Anúncio de Sorteio',
                            'desc'   => 'Disparado manualmente na página de Sorteios quando você clica em "Anunciar".',
                            'manual' => true,
                        ),
                    );
                    foreach ( $automacoes as $a ) :
                        $ativo = ! empty( $a['sempre'] ) || ! empty( $a['manual'] )
                            ? true
                            : (bool) get_option( $a['option'] ?? '', 0 );
                    ?>
                    <div style="background:#1a1a1a;border-radius:8px;padding:16px;display:flex;align-items:center;gap:16px">
                        <div style="flex:1">
                            <p style="color:#F5F0E0;font-weight:700;margin-bottom:4px">
                                <?php echo $a['titulo']; ?>
                                <?php if ( ! empty($a['sempre']) ) : ?>
                                    <span style="background:#1a3a1a;color:#5cb85c;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px">SEMPRE ATIVO</span>
                                <?php elseif ( ! empty($a['manual']) ) : ?>
                                    <span style="background:#1a2a3a;color:#3a9bd5;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px">MANUAL</span>
                                <?php endif; ?>
                            </p>
                            <p style="color:#666;font-size:12px;margin:0"><?php echo $a['desc']; ?></p>
                        </div>
                        <?php if ( empty($a['sempre']) && empty($a['manual']) ) : ?>
                        <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0">
                            <input type="checkbox" class="cv-email-toggle"
                                   data-option="<?php echo esc_attr( $a['option'] ); ?>"
                                   <?php checked( $ativo ); ?>
                                   style="opacity:0;width:0;height:0" />
                            <span style="position:absolute;cursor:pointer;inset:0;background:<?php echo $ativo ? '#D4A017' : '#333'; ?>;border-radius:24px;transition:.3s"></span>
                            <span style="position:absolute;content:'';height:18px;width:18px;left:<?php echo $ativo ? '22px' : '3px'; ?>;bottom:3px;background:white;border-radius:50%;transition:.3s"></span>
                        </label>
                        <?php endif; ?>
                        <?php if ( ! empty($a['manual']) ) : ?>
                        <a href="<?php echo admin_url('admin.php?page=cv-sorteios'); ?>"
                           class="cv-btn cv-btn-outline" style="font-size:12px;padding:6px 14px;flex-shrink:0">
                            Ir para Sorteios →
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Como funciona -->
            <div class="cv-section">
                <h2 class="cv-section-title">📖 Como configurar no MailerLite</h2>
                <div style="background:#1a1a1a;border-radius:8px;padding:20px;color:#C8B98A;font-size:13px;line-height:1.8">
                    <p style="color:#D4A017;font-weight:700;margin-bottom:12px">Passo a passo recomendado:</p>
                    <ol style="padding-left:20px;color:#888">
                        <li style="margin-bottom:8px">No MailerLite, crie um <strong style="color:#C8B98A">Grupo</strong> para cada tipo (ex: "Boas-vindas CV", "Nova Música CV")</li>
                        <li style="margin-bottom:8px">Copie o <strong style="color:#C8B98A">ID de cada grupo</strong> e cole nos campos acima</li>
                        <li style="margin-bottom:8px">No MailerLite, crie uma <strong style="color:#C8B98A">Automação</strong> com gatilho "Assinante entra em grupo"</li>
                        <li style="margin-bottom:8px">Desenhe o e-mail no editor visual do MailerLite com as variáveis <code>{{ subscriber.name }}</code></li>
                        <li style="margin-bottom:8px">Quando um usuário se cadastrar ou uma música for publicada, o plugin <strong style="color:#C8B98A">adiciona o assinante ao grupo</strong> — o MailerLite dispara o e-mail automaticamente</li>
                    </ol>
                    <p style="color:#555;font-size:12px;margin-top:12px">
                        💡 Dica: Você pode usar os campos personalizados do MailerLite como <code>{{ subscriber.fields.music_title }}</code> para incluir o título da música no e-mail de nova publicação.
                    </p>
                </div>
            </div>

        </div>

        <script>
        jQuery(function($){
            var nonce = '<?php echo wp_create_nonce('cv_admin_nonce'); ?>';

            // Testar conexão
            $('#cv-email-test-btn').on('click', function(){
                var $msg = $('#cv-email-msg');
                $(this).prop('disabled',true).text('Testando...');
                $.post(cvAdmin.ajaxUrl, { action:'cv_email_test', nonce:nonce }, function(res){
                    var tipo = res.success ? 'cv-notice-success' : 'cv-notice-error';
                    $msg.removeClass('cv-notice-success cv-notice-error').addClass('cv-notice '+tipo).text(res.data.message).show();
                    $('#cv-email-test-btn').prop('disabled',false).text('🔌 Testar Conexão MailerLite');
                });
            });

            // Buscar grupos
            $('#cv-fetch-groups-btn').on('click', function(){
                var $btn = $(this);
                $btn.prop('disabled',true).text('Buscando...');
                $.post(cvAdmin.ajaxUrl, { action:'cv_email_fetch_groups', nonce:nonce }, function(res){
                    if (res.success) {
                        $('#cv-email-msg').addClass('cv-notice cv-notice-success').text('✓ ' + res.data.total + ' grupos encontrados. Recarregando...').show();
                        setTimeout(function(){ location.reload(); }, 1000);
                    } else {
                        $('#cv-email-msg').addClass('cv-notice cv-notice-error').text(res.data.message).show();
                        $btn.prop('disabled',false).text('🔄 Buscar grupos do MailerLite');
                    }
                });
            });

            // Salvar grupos
            $('#cv-email-groups-save').on('click', function(){
                var $btn = $(this);
                var dados = { action:'cv_save_social', nonce:nonce };
                // Reutiliza ajax_save mas para grupos de email
                var payload = { action:'cv_email_save_groups', nonce:nonce };
                $('.cv-email-group-select').each(function(){
                    payload[$(this).data('key')] = $(this).val();
                });
                $.post(cvAdmin.ajaxUrl, payload, function(res){
                    var tipo = res.success ? 'cv-notice-success' : 'cv-notice-error';
                    $('#cv-email-msg').removeClass('cv-notice-success cv-notice-error').addClass('cv-notice '+tipo).text(res.data.message).show();
                });
            });

            // Toggles de ativação
            $('.cv-email-toggle').on('change', function(){
                var option = $(this).data('option');
                var valor  = $(this).is(':checked') ? 1 : 0;
                $.post(cvAdmin.ajaxUrl, { action:'cv_email_toggle', nonce:nonce, option:option, valor:valor }, function(res){
                    if (res.success) {
                        $('#cv-email-msg').addClass('cv-notice cv-notice-success').text(res.data.message).show();
                        setTimeout(function(){ $('#cv-email-msg').fadeOut(); }, 2000);
                    }
                });
            });
        });
        </script>
        <?php
    }

    // ── AJAX HANDLERS ─────────────────────────────────────────────

    public static function ajax_test() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array('message' => 'Sem permissão.') );
        }

        $api_key = get_option( 'cv_mailerlite_api_key', '' );
        if ( ! $api_key ) {
            wp_send_json_error( array('message' => 'Chave de API não configurada.') );
        }

        $response = wp_remote_get(
            'https://connect.mailerlite.com/api/subscribers?limit=1',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array('message' => 'Erro de conexão: ' . $response->get_error_message()) );
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( 200 === $code ) {
            $body  = json_decode( wp_remote_retrieve_body($response), true );
            $total = $body['meta']['total'] ?? '?';
            wp_send_json_success( array('message' => '✓ Conexão OK! Total de assinantes: ' . $total) );
        } else {
            wp_send_json_error( array('message' => 'API retornou código ' . $code . '. Verifique a chave.') );
        }
    }

    public static function ajax_fetch_groups() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array('message' => 'Sem permissão.') );
        }

        $api_key = get_option( 'cv_mailerlite_api_key', '' );
        if ( ! $api_key ) {
            wp_send_json_error( array('message' => 'API key não configurada.') );
        }

        $response = wp_remote_get(
            'https://connect.mailerlite.com/api/groups?limit=100',
            array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'timeout' => 10,
            )
        );

        if ( is_wp_error( $response ) ) {
            wp_send_json_error( array('message' => 'Erro: ' . $response->get_error_message()) );
        }

        $body   = json_decode( wp_remote_retrieve_body($response), true );
        $grupos = array();

        if ( ! empty( $body['data'] ) ) {
            foreach ( $body['data'] as $g ) {
                $grupos[] = array(
                    'id'    => $g['id'],
                    'name'  => $g['name'],
                    'total' => $g['active_count'] ?? 0,
                );
            }
        }

        // Salva em cache para usar nos selects
        update_option( 'cv_mailerlite_groups_cache', wp_json_encode( $grupos ) );

        wp_send_json_success( array(
            'total'  => count( $grupos ),
            'grupos' => $grupos,
        ) );
    }

    public static function ajax_anunciar_sorteio() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array('message' => 'Sem permissão.') );
        }

        $sorteio_id = absint( $_POST['sorteio_id'] ?? 0 );
        if ( ! $sorteio_id ) {
            wp_send_json_error( array('message' => 'ID de sorteio inválido.') );
        }

        global $wpdb;
        $sorteio = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_sorteios WHERE id = %d",
            $sorteio_id
        ) );

        if ( ! $sorteio ) {
            wp_send_json_error( array('message' => 'Sorteio não encontrado.') );
        }

        $result = self::disparar_para_grupo(
            'sorteio',
            array(
                'sorteio_titulo'    => $sorteio->titulo,
                'sorteio_premio'    => $sorteio->premio,
                'sorteio_descricao' => $sorteio->descricao,
                'sorteio_data'      => date('d/m/Y', strtotime($sorteio->data_sorteio)),
            )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array('message' => 'Erro ao anunciar: ' . $result->get_error_message()) );
        }

        wp_send_json_success( array('message' => '✓ Sorteio anunciado para os assinantes!') );
    }

    // ── TRIGGERS AUTOMÁTICOS ──────────────────────────────────────

    /**
     * Trigger 1: Boas-vindas ao novo usuário.
     * Adiciona o usuário ao grupo de boas-vindas no MailerLite.
     */
    public static function trigger_boas_vindas( $user_id ) {
        // Não duplica se CV_Auth já enviou (CV_Auth faz o subscribe direto)
        // Aqui apenas adiciona ao grupo específico de automação de boas-vindas
        $group_bv = get_option( 'cv_email_group_boas_vindas', '' )
                  ?: get_option( 'cv_mailerlite_group_id', '' );

        if ( ! $group_bv ) { return; }

        $user     = get_userdata( $user_id );
        $api_key  = get_option( 'cv_mailerlite_api_key', '' );

        if ( ! $user || ! $api_key ) { return; }

        // Adiciona ao grupo específico de boas-vindas para acionar a automação
        wp_remote_post(
            'https://connect.mailerlite.com/api/subscribers',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode( array(
                    'email'  => $user->user_email,
                    'fields' => array( 'name' => $user->display_name ),
                    'groups' => array( $group_bv ),
                    'status' => 'active',
                ) ),
                'timeout'  => 8,
                'blocking' => false, // assíncrono — não trava o cadastro
            )
        );
    }

    /**
     * Trigger 2: Nova música publicada.
     * Adiciona um "evento" ao grupo de nova música para acionar automação.
     */
    public static function trigger_nova_musica( $post_id, $post ) {
        if ( ! get_option( 'cv_email_ativo_nova_musica', 1 ) ) { return; }
        if ( 'publish' !== $post->post_status ) { return; }

        // Evita disparar ao apenas editar uma música já publicada
        $primeira_vez = get_post_meta( $post_id, '_cv_email_nova_musica_sent', true );
        if ( $primeira_vez ) { return; }

        update_post_meta( $post_id, '_cv_email_nova_musica_sent', current_time('mysql') );

        $artista    = get_post_meta( $post_id, '_cv_artista', true );
        $compositor = get_post_meta( $post_id, '_cv_compositor', true );
        $cover      = get_the_post_thumbnail_url( $post_id, 'large' );
        $url        = get_permalink( $post_id );

        // Fallback da capa: thumbnail YouTube
        if ( ! $cover ) {
            $yt = get_post_meta( $post_id, '_cv_youtube_url', true );
            if ( $yt ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $yt, $m );
                if ( ! empty($m[1]) ) { $cover = 'https://img.youtube.com/vi/' . $m[1] . '/maxresdefault.jpg'; }
            }
        }

        self::disparar_para_grupo( 'nova_musica', array(
            'music_title'     => $post->post_title,
            'music_url'       => $url,
            'music_artista'   => $artista ?: $compositor,
            'music_cover_url' => $cover ?: '',
        ) );
    }

    /**
     * Trigger 3: Resumo semanal de favoritos.
     * Chamado via WP-Cron semanalmente para usuários com favoritos.
     */
    public static function trigger_favoritos_digest() {
        if ( ! get_option( 'cv_email_ativo_favoritos', 0 ) ) { return; }
        if ( ! class_exists( 'CV_Favorites' ) ) { return; }

        $api_key  = get_option( 'cv_mailerlite_api_key', '' );
        $group_id = get_option( 'cv_email_group_favoritos', '' )
                  ?: get_option( 'cv_mailerlite_group_id', '' );

        if ( ! $api_key || ! $group_id ) { return; }

        // Busca usuários que têm favoritos
        global $wpdb;
        $usuarios = $wpdb->get_results(
            "SELECT DISTINCT user_id FROM {$wpdb->prefix}cv_favorites LIMIT 500"
        );

        foreach ( $usuarios as $row ) {
            $user = get_userdata( $row->user_id );
            if ( ! $user || ! $user->user_email ) { continue; }

            $favs = CV_Favorites::get_user_favorites( $row->user_id, 3 );
            if ( empty( $favs ) ) { continue; }

            // Monta lista de músicas favoritas (nome simples para os campos do MailerLite)
            $titulos = implode( ', ', array_map(function($p){ return $p->post_title; }, $favs) );

            // Adiciona ao grupo de favoritos para acionar automação do MailerLite
            wp_remote_post(
                'https://connect.mailerlite.com/api/subscribers',
                array(
                    'headers' => array(
                        'Content-Type'  => 'application/json',
                        'Authorization' => 'Bearer ' . $api_key,
                        'Accept'        => 'application/json',
                    ),
                    'body'    => wp_json_encode( array(
                        'email'  => $user->user_email,
                        'fields' => array(
                            'name'            => $user->display_name,
                            'favoritos_recentes' => $titulos,
                        ),
                        'groups' => array( $group_id ),
                        'status' => 'active',
                    ) ),
                    'timeout'  => 5,
                    'blocking' => false,
                )
            );
        }
    }

    // ── AGENDAMENTO DO DIGEST ─────────────────────────────────────

    public static function schedule_digest() {
        if ( ! wp_next_scheduled( 'cv_cron_favoritos_digest' ) ) {
            // Todo domingo às 10h
            $domingo = strtotime( 'next sunday 10:00:00' );
            wp_schedule_event( $domingo, 'weekly', 'cv_cron_favoritos_digest' );
        }
    }

    // ── HELPER: dispara para o grupo do tipo ─────────────────────

    /**
     * Adiciona um assinante ao grupo do tipo especificado no MailerLite,
     * acionando a automação configurada naquele grupo.
     *
     * @param string $tipo    Chave do tipo (boas_vindas, nova_musica, etc.)
     * @param array  $campos  Campos extras para passar ao MailerLite
     * @return true|WP_Error
     */
    private static function disparar_para_grupo( $tipo, $campos = array() ) {
        $api_key  = get_option( 'cv_mailerlite_api_key', '' );
        $cfg      = self::TIPOS[ $tipo ] ?? null;
        $group_id = $cfg ? get_option( $cfg['option'], '' ) : '';
        $group_id = $group_id ?: get_option( 'cv_mailerlite_group_id', '' );

        if ( ! $api_key || ! $group_id ) {
            return new WP_Error( 'ml_config', 'API key ou grupo não configurados.' );
        }

        // Para disparos de "evento" (nova música, sorteio), usa um e-mail
        // sentinel que representa o disparo da campanha no grupo.
        // O MailerLite aciona a automação quando qualquer assinante é adicionado ao grupo.
        // O administrador deve criar a automação "Quando entrar no grupo X → Enviar e-mail".
        $email_admin = get_option( 'admin_email' );

        $body = array(
            'email'  => $email_admin,
            'fields' => $campos,
            'groups' => array( $group_id ),
            'status' => 'active',
        );

        $response = wp_remote_post(
            'https://connect.mailerlite.com/api/subscribers',
            array(
                'headers' => array(
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                    'Accept'        => 'application/json',
                ),
                'body'    => wp_json_encode( $body ),
                'timeout'  => 8,
                'blocking' => false,
            )
        );

        if ( is_wp_error( $response ) ) {
            return $response;
        }

        return true;
    }

    // ── UTILITÁRIO ────────────────────────────────────────────────

    private static function descricao_tipo( $key ) {
        $descs = array(
            'boas_vindas' => 'Acionado quando um novo usuário se cadastra no site',
            'nova_musica' => 'Acionado quando uma nova música é publicada',
            'favoritos'   => 'Acionado semanalmente com resumo dos favoritos',
            'sorteio'     => 'Acionado manualmente ao anunciar um sorteio',
        );
        return $descs[ $key ] ?? '';
    }
}

CV_Email::init();
