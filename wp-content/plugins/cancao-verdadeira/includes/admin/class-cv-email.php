<?php
// cancao-verdadeira/includes/admin/class-cv-email.php
// Gerado em: 2026-06-21 23:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo de e-mails automáticos via MailerLite API v3. Gerencia 4 tipos
// de disparo: boas-vindas (novo usuário), nova música publicada, resumo
// de favoritos (semanal) e anúncio de sorteio. Funciona com grupo único
// ou múltiplos grupos quando configurados. Todos os disparos são
// assíncronos (blocking:false) — não travam o WordPress se ML cair.
// v2.44.0: "nova música" e "sorteio" viraram CAMPANHAS do MailerLite (antes o
// e-mail do admin entrava no grupo para acionar a automação, o que só funcionava
// na 1ª vez). Modo rascunho (padrão; obrigatório no site local) ou envio na hora.

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
        // v2.44.0: a campanha é criada em segundo plano (não atrasa o "Publicar")
        add_action( 'cv_email_campanha_nova_musica', array( __CLASS__, 'enviar_nova_musica' ) );
        add_action( 'wp_ajax_cv_email_save_campanha', array( __CLASS__, 'ajax_save_campanha' ) );

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
                <a href="<?php echo admin_url('admin.php?page=cv-settings'); ?>" style="color:#7B3A22">
                    Configure em Configurações →
                </a>
            </div>
            <?php endif; ?>

            <!-- Grupos / Listas -->
            <div class="cv-section">
                <h2 class="cv-section-title">📋 Grupos / Listas no MailerLite</h2>
                <p style="color:#8A6A55;font-size:13px;margin-bottom:16px">
                    Para cada tipo de e-mail você pode usar um grupo diferente do MailerLite (ex: "Assinantes Gerais", "Usuários Registrados").
                    Se deixar em branco, usa o grupo padrão configurado em Configurações.
                    <strong style="color:#7B3A22"> Grupo padrão atual: <?php echo $group_padrao ?: 'não configurado'; ?></strong>
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
                    <div style="background:#FFFFFF;border-radius:8px;padding:16px;display:grid;grid-template-columns:1fr 1fr;gap:16px;align-items:center">
                        <div>
                            <p style="color:#3B2418;font-weight:700;margin-bottom:4px;font-size:14px">
                                <?php echo esc_html( $cfg['label'] ); ?>
                            </p>
                            <p style="color:#8A6A55;font-size:12px;margin:0">
                                <?php echo self::descricao_tipo( $key ); ?>
                            </p>
                        </div>
                        <div>
                            <label style="display:block;color:#6B4C3B;font-size:12px;margin-bottom:4px">ID do grupo (ou deixe vazio para usar o padrão)</label>
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

            <!-- v2.44.0: como saem os avisos de nova música e sorteio -->
            <?php
            $modo_salvo = get_option( 'cv_email_campanha_modo', 'rascunho' );
            $modo_real  = self::campanha_modo();
            $ultimo     = get_option( 'cv_email_ultimo_disparo', array() );
            ?>
            <div class="cv-section">
                <h2 class="cv-section-title">✉️ Avisos de nova música e de sorteio</h2>
                <p style="color:#8A6A55;font-size:13px;margin-bottom:14px">
                    Quando uma música é publicada (ou um sorteio é anunciado), o site cria uma <strong style="color:#7B3A22">campanha no MailerLite</strong>
                    para o grupo escolhido acima, com capa, texto e botão para o site.
                </p>
                <?php if ( 'rascunho' === $modo_real && 'enviar' === $modo_salvo ) : ?>
                <div class="cv-notice cv-notice-warning" style="margin-bottom:14px">
                    🏠 Este é o site <strong>local</strong>: aqui a campanha é sempre criada como <strong>rascunho</strong>, para nunca enviar aos assinantes reais sem querer.
                </div>
                <?php endif; ?>
                <div style="display:grid;gap:10px;max-width:640px">
                    <label style="display:flex;gap:10px;align-items:flex-start;color:#3B2418">
                        <input type="radio" name="cv-campanha-modo" value="rascunho" <?php checked( $modo_salvo, 'rascunho' ); ?> style="margin-top:4px">
                        <span><strong>Criar como rascunho</strong> (recomendado) — você revisa no MailerLite e clica em Enviar.</span>
                    </label>
                    <label style="display:flex;gap:10px;align-items:flex-start;color:#3B2418">
                        <input type="radio" name="cv-campanha-modo" value="enviar" <?php checked( $modo_salvo, 'enviar' ); ?> style="margin-top:4px">
                        <span><strong>Enviar na hora</strong> — a campanha sai assim que a música é publicada.</span>
                    </label>
                    <label style="display:block;color:#6B4C3B;font-size:12px;margin-top:6px">Remetente (precisa estar <strong>verificado</strong> no MailerLite)</label>
                    <input type="email" id="cv-campanha-remetente" class="cv-input" value="<?php echo esc_attr( get_option( 'cv_email_remetente', '' ) ); ?>" placeholder="<?php echo esc_attr( self::remetente() ); ?>">
                </div>
                <button id="cv-campanha-save" class="cv-btn cv-btn-primary" style="margin-top:14px">💾 Salvar</button>
                <?php if ( ! empty( $ultimo['quando'] ) ) : ?>
                <p style="margin-top:14px;font-size:13px;color:<?php echo ! empty( $ultimo['ok'] ) ? '#1f6b35' : '#9b2c2c'; ?>">
                    Último disparo (<?php echo esc_html( date_i18n( 'd/m/Y H:i', strtotime( $ultimo['quando'] ) ) . ' — ' . $ultimo['tipo'] ); ?>):
                    <?php echo esc_html( $ultimo['msg'] ); ?>
                </p>
                <?php endif; ?>
            </div>

            <!-- Automações -->
            <div class="cv-section">
                <h2 class="cv-section-title">⚡ Automações Ativas</h2>
                <p style="color:#8A6A55;font-size:13px;margin-bottom:20px">
                    Controle quais e-mails automáticos estão ativos. Boas-vindas usa uma
                    <strong style="color:#7B3A22">automação do MailerLite</strong> (a pessoa entra no grupo e recebe);
                    nova música e sorteio viram <strong style="color:#7B3A22">campanhas</strong> (veja o quadro acima).
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
                    <div style="background:#FFFFFF;border-radius:8px;padding:16px;display:flex;align-items:center;gap:16px">
                        <div style="flex:1">
                            <p style="color:#3B2418;font-weight:700;margin-bottom:4px">
                                <?php echo $a['titulo']; ?>
                                <?php if ( ! empty($a['sempre']) ) : ?>
                                    <span style="background:#EAF6EA;color:#388038;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px">SEMPRE ATIVO</span>
                                <?php elseif ( ! empty($a['manual']) ) : ?>
                                    <span style="background:#F8F0E4;color:#2374A5;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;margin-left:8px">MANUAL</span>
                                <?php endif; ?>
                            </p>
                            <p style="color:#8A6A55;font-size:12px;margin:0"><?php echo $a['desc']; ?></p>
                        </div>
                        <?php if ( empty($a['sempre']) && empty($a['manual']) ) : ?>
                        <label style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0">
                            <input type="checkbox" class="cv-email-toggle"
                                   data-option="<?php echo esc_attr( $a['option'] ); ?>"
                                   <?php checked( $ativo ); ?>
                                   style="opacity:0;width:0;height:0" />
                            <span style="position:absolute;cursor:pointer;inset:0;background:<?php echo $ativo ? '#B8700C' : '#F3E6D3'; ?>;border-radius:24px;transition:.3s"></span>
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
                <div style="background:#FFFFFF;border-radius:8px;padding:20px;color:#6B4C3B;font-size:13px;line-height:1.8">
                    <p style="color:#7B3A22;font-weight:700;margin-bottom:12px">Passo a passo recomendado:</p>
                    <ol style="padding-left:20px;color:#8A6A55">
                        <li style="margin-bottom:8px">No MailerLite, crie um <strong style="color:#6B4C3B">Grupo</strong> para cada tipo (ex: "Boas-vindas CV", "Nova Música CV")</li>
                        <li style="margin-bottom:8px">Copie o <strong style="color:#6B4C3B">ID de cada grupo</strong> e cole nos campos acima</li>
                        <li style="margin-bottom:8px"><strong style="color:#6B4C3B">Boas-vindas:</strong> no MailerLite, crie uma Automação com gatilho "Assinante entra em grupo" (o grupo de boas-vindas) e desenhe o e-mail</li>
                        <li style="margin-bottom:8px"><strong style="color:#6B4C3B">Nova música e sorteio:</strong> não precisa de automação. O site cria a campanha para o grupo escolhido (todos os assinantes desse grupo recebem)</li>
                        <li style="margin-bottom:8px">Verifique no MailerLite o e-mail do <strong style="color:#6B4C3B">remetente</strong> (Configurações da conta → Domínios/Remetentes)</li>
                        <li style="margin-bottom:8px">Teste primeiro com um <strong style="color:#6B4C3B">grupo de teste</strong> que tenha só o seu e-mail</li>
                    </ol>
                    <p style="color:#8A6A55;font-size:12px;margin-top:12px">
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

            // v2.44.0: modo das campanhas e remetente
            $('#cv-campanha-save').on('click', function(){
                $.post(cvAdmin.ajaxUrl, {
                    action: 'cv_email_save_campanha', nonce: nonce,
                    modo: $('input[name="cv-campanha-modo"]:checked').val(),
                    remetente: $('#cv-campanha-remetente').val()
                }, function(res){
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

        $corpo = '<p style="font-size:22px;font-weight:bold;color:#7B3A22;margin:0 0 10px">' . esc_html( $sorteio->titulo ) . '</p>'
               . '<p>🎁 Prêmio: <strong>' . esc_html( $sorteio->premio ) . '</strong></p>'
               . ( $sorteio->descricao ? '<p>' . nl2br( esc_html( $sorteio->descricao ) ) . '</p>' : '' )
               . '<p>📅 Sorteio em <strong>' . esc_html( date_i18n( 'd/m/Y', strtotime( $sorteio->data_sorteio ) ) ) . '</strong>. Todos os assinantes participam!</p>';

        $result = self::criar_campanha(
            'sorteio',
            'Sorteio: ' . $sorteio->titulo,
            '🎁 Sorteio: ' . $sorteio->titulo,
            self::html_email( 'Tem sorteio chegando!', $corpo, home_url( '/' ), 'Visitar o site' )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array('message' => 'Erro ao anunciar: ' . $result->get_error_message()) );
        }

        wp_send_json_success( array( 'message' => '✓ ' . $result['mensagem'] ) );
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
     * v2.44.0: agenda a criação da campanha (enviar_nova_musica) para daqui a 10 s.
     */
    public static function trigger_nova_musica( $post_id, $post ) {
        if ( ! get_option( 'cv_email_ativo_nova_musica', 1 ) ) { return; }
        if ( 'publish' !== $post->post_status ) { return; }

        // Evita disparar ao apenas editar uma música já publicada
        $primeira_vez = get_post_meta( $post_id, '_cv_email_nova_musica_sent', true );
        if ( $primeira_vez ) { return; }

        update_post_meta( $post_id, '_cv_email_nova_musica_sent', current_time('mysql') );

        // Sem grupo configurado não há para quem avisar (nada é enviado).
        if ( ! self::grupo_do_tipo( 'nova_musica' ) || ! get_option( 'cv_mailerlite_api_key', '' ) ) { return; }

        wp_schedule_single_event( time() + 10, 'cv_email_campanha_nova_musica', array( (int) $post_id ) );
    }

    /**
     * v2.44.0 (WP-Cron): cria a campanha "nova música" no MailerLite para o grupo.
     */
    public static function enviar_nova_musica( $post_id ) {
        $post = get_post( (int) $post_id );
        if ( ! $post || 'publish' !== $post->post_status ) { return; }

        $artista = get_post_meta( $post->ID, CV_Fields::ARTISTA, true ) ?: get_post_meta( $post->ID, CV_Fields::COMPOSITOR, true );
        $capa    = CV_Fields::cover_url( $post->ID, 'large', 'maxresdefault' );
        $url     = get_permalink( $post->ID );
        $titulo  = wp_strip_all_tags( html_entity_decode( get_the_title( $post->ID ), ENT_QUOTES, 'UTF-8' ) );

        $corpo = '<p>Acabou de chegar uma música nova no ' . esc_html( get_bloginfo( 'name' ) ) . ':</p>'
               . '<p style="font-size:22px;font-weight:bold;color:#7B3A22;margin:10px 0">' . esc_html( $titulo ) . '</p>'
               . ( $artista ? '<p style="color:#6B4C3B">' . esc_html( $artista ) . '</p>' : '' )
               . '<p>Ouça, leia a letra completa e, se gostar, compartilhe com quem você ama. 💛</p>';

        self::criar_campanha(
            'nova_musica',
            'Nova música: ' . $titulo,
            '🎵 Nova música: ' . $titulo,
            self::html_email( 'Música nova no ar!', $corpo, $url, 'Ouvir e ler a letra', $capa )
        );
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

    // ── CAMPANHAS (v2.44.0) ───────────────────────────────────────

    // Grupo do tipo; se vazio, o grupo padrão.
    private static function grupo_do_tipo( $tipo ) {
        $cfg = self::TIPOS[ $tipo ] ?? null;
        $g   = $cfg ? get_option( $cfg['option'], '' ) : '';
        return $g ?: get_option( 'cv_mailerlite_group_id', '' );
    }

    // 'rascunho' ou 'enviar'. No site local (LocalWP) é SEMPRE rascunho:
    // a chave do MailerLite é a real e os assinantes também.
    public static function campanha_modo() {
        if ( in_array( wp_get_environment_type(), array( 'local', 'development' ), true ) ) { return 'rascunho'; }
        return 'enviar' === get_option( 'cv_email_campanha_modo', 'rascunho' ) ? 'enviar' : 'rascunho';
    }

    // Remetente: precisa ser um e-mail VERIFICADO no MailerLite.
    public static function remetente() {
        $r = get_option( 'cv_email_remetente', '' );
        if ( ! $r && function_exists( 'UM' ) ) { $r = UM()->options()->get( 'mail_from_addr' ); }
        return is_email( $r ) ? $r : get_option( 'admin_email' );
    }

    private static function ml_post( $caminho, $corpo, $api_key ) {
        return wp_remote_post( 'https://connect.mailerlite.com/api/' . $caminho, array(
            'headers' => array(
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
                'Accept'        => 'application/json',
            ),
            'body'    => wp_json_encode( $corpo ),
            'timeout' => 20,
        ) );
    }

    /**
     * Cria uma campanha no MailerLite para o grupo do tipo. Modo "rascunho":
     * fica em Campanhas → Rascunhos para revisar e enviar. Modo "enviar": envia
     * na hora. Se o plano do MailerLite não aceitar o HTML pela API, cria o
     * rascunho sem conteúdo (monte o e-mail no editor do MailerLite).
     * @return array|WP_Error  array( 'id', 'modo', 'mensagem' )
     */
    public static function criar_campanha( $tipo, $nome, $assunto, $html ) {
        $api_key = get_option( 'cv_mailerlite_api_key', '' );
        $grupo   = self::grupo_do_tipo( $tipo );
        if ( ! $api_key || ! $grupo ) {
            return self::registrar( $tipo, new WP_Error( 'ml_config', 'Chave do MailerLite ou grupo não configurados.' ) );
        }

        $modo  = self::campanha_modo();
        $email = array(
            'subject'   => $assunto,
            'from_name' => get_bloginfo( 'name' ),
            'from'      => self::remetente(),
            'content'   => $html,
        );
        $corpo = array(
            'name'   => $nome . ' — ' . date_i18n( 'd/m/Y H:i' ),
            'type'   => 'regular',
            'emails' => array( $email ),
            'groups' => array( (string) $grupo ),
        );

        $r      = self::ml_post( 'campaigns', $corpo, $api_key );
        $sem_html = false;
        $codigo = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
        if ( $codigo >= 400 && false !== stripos( wp_remote_retrieve_body( $r ), 'content' ) ) {
            unset( $corpo['emails'][0]['content'] ); // plano sem HTML pela API
            $r        = self::ml_post( 'campaigns', $corpo, $api_key );
            $codigo   = is_wp_error( $r ) ? 0 : (int) wp_remote_retrieve_response_code( $r );
            $sem_html = true;
        }
        if ( is_wp_error( $r ) ) { return self::registrar( $tipo, $r ); }
        $d = json_decode( wp_remote_retrieve_body( $r ), true );
        if ( $codigo < 200 || $codigo >= 300 || empty( $d['data']['id'] ) ) {
            $erro = isset( $d['message'] ) ? $d['message'] : 'resposta ' . $codigo;
            return self::registrar( $tipo, new WP_Error( 'ml_campanha', 'MailerLite recusou a campanha: ' . $erro ) );
        }
        $id = $d['data']['id'];

        if ( 'enviar' === $modo && ! $sem_html ) {
            $s = self::ml_post( 'campaigns/' . rawurlencode( $id ) . '/schedule', array( 'delivery' => 'instant' ), $api_key );
            $c = is_wp_error( $s ) ? 0 : (int) wp_remote_retrieve_response_code( $s );
            if ( $c < 200 || $c >= 300 ) {
                return self::registrar( $tipo, array( 'id' => $id, 'modo' => 'rascunho',
                    'mensagem' => 'Campanha criada, mas o envio automático falhou. Ela está nos rascunhos do MailerLite: revise e envie por lá.' ) );
            }
            return self::registrar( $tipo, array( 'id' => $id, 'modo' => 'enviar', 'mensagem' => 'Campanha enviada para o grupo no MailerLite.' ) );
        }

        $msg = $sem_html
            ? 'Rascunho criado no MailerLite SEM o texto (o seu plano não aceita HTML pela API): abra em Campanhas → Rascunhos, monte o e-mail e envie.'
            : 'Rascunho criado no MailerLite: abra em Campanhas → Rascunhos, revise e clique em Enviar.';
        return self::registrar( $tipo, array( 'id' => $id, 'modo' => 'rascunho', 'mensagem' => $msg ) );
    }

    // Guarda o último resultado (aparece na tela de e-mails) e devolve o próprio resultado.
    private static function registrar( $tipo, $resultado ) {
        $ok  = ! is_wp_error( $resultado );
        $msg = $ok ? $resultado['mensagem'] : $resultado->get_error_message();
        update_option( 'cv_email_ultimo_disparo', array(
            'quando' => current_time( 'mysql' ),
            'tipo'   => $tipo,
            'ok'     => $ok,
            'msg'    => $msg,
        ), false );
        if ( class_exists( 'CV_Advanced' ) && method_exists( 'CV_Advanced', 'log' ) ) {
            CV_Advanced::log( 'email_campanha', ( $ok ? '' : 'ERRO: ' ) . $tipo . ' — ' . $msg, 'email', 0 );
        }
        return $resultado;
    }

    // HTML simples do e-mail (letra grande, cores da marca).
    public static function html_email( $titulo, $corpo, $botao_url, $botao_txt, $imagem = '' ) {
        return '<div style="background:#FBF6EE;padding:30px 12px;font-family:Georgia,serif">'
             . '<div style="max-width:560px;margin:0 auto;background:#FFFFFF;border-radius:10px;overflow:hidden;border:1px solid #EADBC6">'
             . '<div style="background:#7B3A22;color:#FBF6EE;text-align:center;padding:22px;font-size:26px;font-weight:bold">' . esc_html( get_bloginfo( 'name' ) ) . '</div>'
             . ( $imagem ? '<img src="' . esc_url( $imagem ) . '" alt="" style="display:block;width:100%;height:auto">' : '' )
             . '<div style="padding:26px 30px;color:#3B2418;font-size:18px;line-height:1.6">'
             . '<p style="font-size:22px;font-weight:bold;margin:0 0 14px;color:#7B3A22">' . esc_html( $titulo ) . '</p>' . $corpo . '</div>'
             . '<div style="text-align:center;padding:0 30px 30px"><a href="' . esc_url( $botao_url ) . '" style="display:inline-block;background:#F2A51A;color:#3B2418;font-size:18px;font-weight:bold;padding:14px 32px;border-radius:8px;text-decoration:none">' . esc_html( $botao_txt ) . '</a></div>'
             . '<div style="background:#F3E6D3;padding:16px 30px;color:#6B4C3B;font-size:14px;text-align:center">'
             . 'Você recebe este e-mail porque se inscreveu no ' . esc_html( get_bloginfo( 'name' ) ) . '. '
             . '<a href="{$unsubscribe}" style="color:#7B3A22">Cancelar inscrição</a></div>'
             . '</div></div>';
    }

    public static function ajax_save_campanha() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
        $modo = 'enviar' === ( $_POST['modo'] ?? '' ) ? 'enviar' : 'rascunho';
        $rem  = sanitize_email( wp_unslash( $_POST['remetente'] ?? '' ) );
        if ( $rem && ! is_email( $rem ) ) { wp_send_json_error( array( 'message' => 'E-mail do remetente inválido.' ) ); }
        update_option( 'cv_email_campanha_modo', $modo );
        update_option( 'cv_email_remetente', $rem );
        wp_send_json_success( array( 'message' => '✓ Salvo.' ) );
    }

    // ── UTILITÁRIO ────────────────────────────────────────────────

    private static function descricao_tipo( $key ) {
        $descs = array(
            'boas_vindas' => 'Acionado quando um novo usuário se cadastra no site',
            'nova_musica' => 'Campanha criada quando uma música é publicada (grupo = quem recebe)',
            'favoritos'   => 'Acionado semanalmente com resumo dos favoritos',
            'sorteio'     => 'Campanha criada ao clicar em "Anunciar" num sorteio (grupo = quem recebe)',
        );
        return $descs[ $key ] ?? '';
    }
}

CV_Email::init();
