<?php
// cancao-verdadeira/includes/admin/class-cv-social.php
// Gerado em: 2026-06-21 23:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Gerencia as redes sociais do site: página administrativa para configurar
// URLs de Instagram, YouTube, Facebook, TikTok, Twitter/X e WhatsApp.
// Os links ficam disponíveis via get_option() para uso no tema filho.
// Também injeta os ícones sociais no rodapé via hook cv_social_links.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Social {

    // Definição de todas as redes suportadas
    const REDES = array(
        'instagram'  => array( 'label' => 'Instagram',   'icon' => '📸', 'placeholder' => 'https://instagram.com/seuperfil',      'color' => '#E1306C' ),
        'youtube'    => array( 'label' => 'YouTube',     'icon' => '▶',  'placeholder' => 'https://youtube.com/@seucanal',         'color' => '#FF0000' ),
        'facebook'   => array( 'label' => 'Facebook',    'icon' => '📘', 'placeholder' => 'https://facebook.com/suapagina',        'color' => '#1877F2' ),
        'tiktok'     => array( 'label' => 'TikTok',      'icon' => '🎵', 'placeholder' => 'https://tiktok.com/@seuperfil',         'color' => '#010101' ),
        'twitter'    => array( 'label' => 'Twitter / X', 'icon' => '✕',  'placeholder' => 'https://twitter.com/seuperfil',         'color' => '#000000' ),
        'telegram'   => array( 'label' => 'Telegram',    'icon' => '✈',  'placeholder' => 'https://t.me/seucanal',                 'color' => '#2CA5E0' ),
        'spotify'    => array( 'label' => 'Spotify',     'icon' => '🎧', 'placeholder' => 'https://open.spotify.com/artist/...',   'color' => '#1DB954' ),
        'whatsapp'   => array( 'label' => 'WhatsApp',    'icon' => '💬', 'placeholder' => 'https://wa.me/5511999999999',           'color' => '#25D366' ),
    );

    public static function init() {
        // Registra as opções no WordPress
        add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );

        // AJAX para salvar via painel
        add_action( 'wp_ajax_cv_save_social', array( __CLASS__, 'ajax_save' ) );

        // Hook para renderizar links sociais (usado pelo tema filho)
        // Uso: do_action('cv_social_links') ou CV_Social::render_links()
    }

    // ── Registro das opções ───────────────────────────────────────

    public static function register_settings() {
        foreach ( array_keys( self::REDES ) as $rede ) {
            register_setting(
                'cv_social_group',
                'cv_social_' . $rede,
                array( 'sanitize_callback' => 'esc_url_raw', 'default' => '' )
            );
        }
        register_setting( 'cv_social_group', 'cv_social_mostrar_rodape',
            array( 'sanitize_callback' => 'absint', 'default' => 1 ) );
    }

    // ── Página administrativa ─────────────────────────────────────

    public static function page_social() {
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">

            <div class="cv-admin-header">
                <div>
                    <h1>📱 Redes Sociais</h1>
                    <p class="cv-admin-subtitle">Configure os links das suas redes. Eles aparecem no rodapé do site e nos botões de compartilhamento.</p>
                </div>
            </div>

            <div id="cv-social-msg" class="cv-action-message" style="display:none;margin:0 0 20px"></div>

            <div class="cv-section">
                <h2 class="cv-section-title">🔗 Links das Redes Sociais</h2>
                <p style="color:#888;font-size:13px;margin-bottom:24px">
                    Preencha apenas as redes que você usa. Deixe em branco para não exibir.
                </p>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">
                    <?php foreach ( self::REDES as $key => $rede ) :
                        $valor = get_option( 'cv_social_' . $key, '' );
                    ?>
                    <div class="cv-form-group">
                        <label class="cv-form-label">
                            <span style="font-size:18px;margin-right:8px"><?php echo $rede['icon']; ?></span>
                            <strong><?php echo esc_html( $rede['label'] ); ?></strong>
                        </label>
                        <div style="position:relative">
                            <input type="url"
                                   class="cv-input cv-social-input"
                                   id="cv_social_<?php echo esc_attr( $key ); ?>"
                                   name="cv_social_<?php echo esc_attr( $key ); ?>"
                                   value="<?php echo esc_attr( $valor ); ?>"
                                   placeholder="<?php echo esc_attr( $rede['placeholder'] ); ?>"
                                   style="padding-right:40px" />
                            <?php if ( $valor ) : ?>
                            <a href="<?php echo esc_url( $valor ); ?>" target="_blank" rel="noopener"
                               style="position:absolute;right:10px;top:50%;transform:translateY(-50%);font-size:14px;color:<?php echo esc_attr($rede['color']); ?>;text-decoration:none"
                               title="Abrir <?php echo esc_attr($rede['label']); ?>">↗</a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div style="margin-top:24px;padding-top:20px;border-top:1px solid rgba(255,255,255,.08)">
                    <label style="display:flex;align-items:center;gap:10px;cursor:pointer;color:#C8B98A;font-size:14px">
                        <input type="checkbox" id="cv_social_mostrar_rodape"
                               <?php checked( get_option('cv_social_mostrar_rodape', 1), 1 ); ?> />
                        Exibir ícones sociais no rodapé do site
                    </label>
                </div>

                <div style="margin-top:28px">
                    <button id="cv-social-save-btn" class="cv-btn cv-btn-primary" style="padding:11px 32px;font-size:15px">
                        <span class="cv-btn-text">💾 Salvar Redes Sociais</span>
                        <span class="cv-btn-loading" style="display:none">⏳ Salvando...</span>
                    </button>
                </div>
            </div>

            <!-- Preview dos botões -->
            <div class="cv-section">
                <h2 class="cv-section-title">👁 Preview — Como aparecerá no site</h2>
                <p style="color:#888;font-size:13px;margin-bottom:20px">
                    Estes botões aparecerão no rodapé e podem ser adicionados em qualquer página com o shortcode <code style="background:#1a1a1a;padding:2px 8px;border-radius:4px">[cv_social_links]</code>
                </p>
                <div id="cv-social-preview" style="display:flex;flex-wrap:wrap;gap:10px">
                    <?php echo self::render_links( 'botoes' ); ?>
                </div>
            </div>

            <!-- Como usar -->
            <div class="cv-section">
                <h2 class="cv-section-title">📋 Como usar em páginas</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
                    <div style="background:#1a1a1a;border-radius:8px;padding:16px">
                        <p style="color:#D4A017;font-weight:700;margin-bottom:8px;font-size:13px">Shortcodes disponíveis:</p>
                        <code style="display:block;color:#C8B98A;font-size:13px;margin-bottom:6px">[cv_social_links]</code>
                        <p style="color:#666;font-size:12px;margin-bottom:12px">Exibe botões com ícone e nome de todas as redes</p>
                        <code style="display:block;color:#C8B98A;font-size:13px;margin-bottom:6px">[cv_social_links estilo="icones"]</code>
                        <p style="color:#666;font-size:12px;margin-bottom:12px">Exibe apenas ícones (compacto)</p>
                        <code style="display:block;color:#C8B98A;font-size:13px;margin-bottom:6px">[cv_social_links redes="instagram,youtube,tiktok"]</code>
                        <p style="color:#666;font-size:12px">Exibe só as redes especificadas</p>
                    </div>
                    <div style="background:#1a1a1a;border-radius:8px;padding:16px">
                        <p style="color:#D4A017;font-weight:700;margin-bottom:8px;font-size:13px">Para usar no tema filho (PHP):</p>
                        <code style="display:block;color:#C8B98A;font-size:13px;margin-bottom:6px">CV_Social::render_links();</code>
                        <p style="color:#666;font-size:12px;margin-bottom:12px">Renderiza os botões diretamente em qualquer template PHP</p>
                        <code style="display:block;color:#C8B98A;font-size:13px;margin-bottom:6px">CV_Social::get_url('instagram');</code>
                        <p style="color:#666;font-size:12px">Retorna a URL de uma rede específica</p>
                    </div>
                </div>
            </div>

        </div>

        <script>
        jQuery(function($){
            var nonce   = '<?php echo wp_create_nonce('cv_admin_nonce'); ?>';

            function coletarDados() {
                var dados = { mostrar_rodape: $('#cv_social_mostrar_rodape').is(':checked') ? 1 : 0 };
                $('.cv-social-input').each(function(){
                    dados[$(this).attr('name')] = $(this).val().trim();
                });
                return dados;
            }

            $('#cv-social-save-btn').on('click', function(){
                var $btn = $(this);
                var $msg = $('#cv-social-msg');
                $btn.find('.cv-btn-text').hide();
                $btn.find('.cv-btn-loading').show();
                $btn.prop('disabled', true);

                var dados = coletarDados();
                dados.action = 'cv_save_social';
                dados.nonce  = nonce;

                $.post(cvAdmin.ajaxUrl, dados, function(res){
                    var tipo = res.success ? 'cv-notice-success' : 'cv-notice-error';
                    $msg.removeClass('cv-notice-success cv-notice-error')
                        .addClass('cv-notice ' + tipo)
                        .text(res.data.message).show();
                    $btn.find('.cv-btn-text').show();
                    $btn.find('.cv-btn-loading').hide();
                    $btn.prop('disabled', false);

                    // Atualiza preview
                    if (res.success) {
                        setTimeout(function(){ location.reload(); }, 800);
                    }
                }).fail(function(){
                    $msg.addClass('cv-notice cv-notice-error').text('Erro de conexão.').show();
                    $btn.find('.cv-btn-text').show();
                    $btn.find('.cv-btn-loading').hide();
                    $btn.prop('disabled', false);
                });
            });
        });
        </script>
        <?php
    }

    // ── AJAX: salvar configurações sociais ────────────────────────

    public static function ajax_save() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }

        foreach ( array_keys( self::REDES ) as $rede ) {
            $valor = esc_url_raw( $_POST[ 'cv_social_' . $rede ] ?? '' );
            update_option( 'cv_social_' . $rede, $valor );
        }

        update_option( 'cv_social_mostrar_rodape', absint( $_POST['mostrar_rodape'] ?? 1 ) );

        wp_send_json_success( array( 'message' => '✓ Redes sociais salvas com sucesso!' ) );
    }

    // ── Renderização dos links (uso no tema e shortcode) ──────────

    /**
     * Renderiza os botões/ícones sociais.
     * Chamado pelo shortcode [cv_social_links] e pelo tema filho.
     *
     * @param string $estilo   'botoes' | 'icones'
     * @param array  $filtro   Lista de redes para exibir (vazio = todas)
     * @return string HTML
     */
    public static function render_links( $estilo = 'botoes', $filtro = array() ) {
        $html  = '';
        $redes = self::REDES;

        if ( ! empty( $filtro ) ) {
            $redes = array_intersect_key( $redes, array_flip( $filtro ) );
        }

        foreach ( $redes as $key => $rede ) {
            $url = get_option( 'cv_social_' . $key, '' );
            if ( ! $url ) { continue; }

            if ( 'icones' === $estilo ) {
                $html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"'
                       . ' title="' . esc_attr( $rede['label'] ) . '"'
                       . ' style="display:inline-flex;align-items:center;justify-content:center;'
                       . 'width:40px;height:40px;border-radius:50%;background:' . esc_attr( $rede['color'] ) . ';'
                       . 'color:#fff;font-size:18px;text-decoration:none;transition:opacity .2s"'
                       . ' onmouseover="this.style.opacity=.8" onmouseout="this.style.opacity=1">'
                       . $rede['icon'] . '</a>';
            } else {
                $html .= '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer"'
                       . ' style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;'
                       . 'border-radius:50px;background:' . esc_attr( $rede['color'] ) . ';'
                       . 'color:#fff;font-size:13px;font-weight:700;text-decoration:none;'
                       . 'transition:opacity .2s"'
                       . ' onmouseover="this.style.opacity=.8" onmouseout="this.style.opacity=1">'
                       . '<span>' . $rede['icon'] . '</span>'
                       . '<span>' . esc_html( $rede['label'] ) . '</span>'
                       . '</a>';
            }
        }

        return $html ?: '<p style="color:#666;font-size:13px">Nenhuma rede configurada ainda.</p>';
    }

    /**
     * Retorna a URL de uma rede específica.
     * Uso no tema: CV_Social::get_url('instagram')
     *
     * @param string $rede  Slug da rede (instagram, youtube, facebook...)
     * @return string URL ou string vazia
     */
    public static function get_url( $rede ) {
        return get_option( 'cv_social_' . sanitize_key( $rede ), '' );
    }

    /**
     * Retorna array com todas as redes configuradas.
     * Uso no tema filho para montar o rodapé.
     *
     * @return array  [ 'slug' => ['label','icon','color','url'] ]
     */
    public static function get_all() {
        $result = array();
        foreach ( self::REDES as $key => $rede ) {
            $url = get_option( 'cv_social_' . $key, '' );
            if ( $url ) {
                $result[ $key ] = array_merge( $rede, array( 'url' => $url ) );
            }
        }
        return $result;
    }

    // ── Shortcode [cv_social_links] ───────────────────────────────

    public static function shortcode( $atts ) {
        $atts = shortcode_atts( array(
            'estilo' => 'botoes',
            'redes'  => '',
        ), $atts, 'cv_social_links' );

        $filtro = array();
        if ( $atts['redes'] ) {
            $filtro = array_map( 'trim', explode( ',', $atts['redes'] ) );
        }

        return '<div class="cv-social-links cv-social-' . esc_attr( $atts['estilo'] ) . '" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center">'
             . self::render_links( $atts['estilo'], $filtro )
             . '</div>';
    }
}

CV_Social::init();
