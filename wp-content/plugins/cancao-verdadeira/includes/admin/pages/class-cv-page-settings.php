<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-settings.php
// Página "Configurações": opções gerais do plugin.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Settings {

    public static function render() {
        // Salvar
        if ( isset( $_POST['cv_save_settings'] ) && check_admin_referer( 'cv_settings_save' ) ) {
            update_option( 'cv_mailerlite_api_key',   sanitize_text_field( isset($_POST['cv_mailerlite_api_key'])   ? $_POST['cv_mailerlite_api_key']   : '' ) );
            update_option( 'cv_mailerlite_group_id',  sanitize_text_field( isset($_POST['cv_mailerlite_group_id'])  ? $_POST['cv_mailerlite_group_id']  : '' ) );
            update_option( 'cv_whatsapp_number',      sanitize_text_field( isset($_POST['cv_whatsapp_number'])      ? $_POST['cv_whatsapp_number']      : '' ) );
            update_option( 'cv_whatsapp_message',     sanitize_textarea_field( isset($_POST['cv_whatsapp_message']) ? $_POST['cv_whatsapp_message']     : '' ) );
            update_option( 'cv_whatsapp_tooltip',     sanitize_text_field( isset($_POST['cv_whatsapp_tooltip'])     ? $_POST['cv_whatsapp_tooltip']     : '' ) );
            update_option( 'cv_whatsapp_pulse_delay', absint( isset($_POST['cv_whatsapp_pulse_delay']) ? $_POST['cv_whatsapp_pulse_delay'] : 3 ) );
            update_option( 'cv_play_seconds',         absint( isset($_POST['cv_play_seconds'])         ? $_POST['cv_play_seconds']         : 30 ) );
            $saved = true;
        } else {
            $saved = false;
        }

        $api_key      = get_option( 'cv_mailerlite_api_key',   '' );
        $group_id     = get_option( 'cv_mailerlite_group_id',  '' );
        $whatsapp     = get_option( 'cv_whatsapp_number',      '' );
        $wa_message   = get_option( 'cv_whatsapp_message',     'Olá! Vim do Canção Verdadeira e gostaria de saber mais.' );
        $wa_tooltip   = get_option( 'cv_whatsapp_tooltip',     'Fale conosco no WhatsApp!' );
        $wa_delay     = (int) get_option( 'cv_whatsapp_pulse_delay', 3 );
        $play_secs    = (int) get_option( 'cv_play_seconds', 30 );
        $api_ok       = ! empty($api_key);
        $wa_ok        = ! empty($whatsapp);
        ?>
        <div class="wrap" id="cv-settings-exec">
        <style>
        body.wp-admin { background:#0f0f1a !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#0f0f1a !important; }
        #cv-settings-exec {
            --gold:#D4A017; --bg:#0f0f1a; --card:#1a1a2e; --card2:#16213e;
            --bord:#2a2a4a; --text:#e0e0e0; --muted:#888; --green:#1DB954; --red:#e74c3c;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:48px;
        }
        #cv-settings-exec * { box-sizing:border-box; }
        .cv-set-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-set-title { font-size:24px; font-weight:700; color:#fff; margin:0; }
        .cv-set-title span { color:var(--gold); }
        .cv-set-status { display:flex; gap:10px; flex-wrap:wrap; }
        .cv-set-pill { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:12px; font-weight:600; }
        .cv-set-pill.ok  { background:rgba(29,185,84,.12); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-set-pill.off { background:rgba(231,76,60,.1); border:1px solid rgba(231,76,60,.3); color:var(--red); }
        .cv-set-grid { display:grid; grid-template-columns:1fr 1fr; gap:20px; }
        @media (max-width:900px) { .cv-set-grid { grid-template-columns:1fr; } }
        .cv-set-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-set-card-header { padding:16px 20px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:12px; }
        .cv-set-card-icon { font-size:22px; }
        .cv-set-card-title { font-size:15px; font-weight:700; color:#fff; }
        .cv-set-card-subtitle { font-size:11px; color:var(--muted); margin-top:2px; }
        .cv-set-card-body { padding:20px; display:flex; flex-direction:column; gap:16px; }
        .cv-set-field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; }
        .cv-set-input { width:100%; background:rgba(255,255,255,.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-set-input:focus { border-color:var(--gold); }
        .cv-set-input::placeholder { color:#444; }
        .cv-set-hint { font-size:11px; color:var(--muted); margin-top:4px; }
        .cv-set-status-dot { display:inline-block; width:8px; height:8px; border-radius:50%; margin-right:4px; }
        .cv-set-status-dot.ok { background:var(--green); }
        .cv-set-status-dot.off { background:var(--red); }
        /* Preview WhatsApp */
        .cv-wa-preview { background:#075e54; border-radius:12px; padding:14px 16px; margin-top:8px; }
        .cv-wa-preview-label { font-size:10px; color:rgba(255,255,255,.5); margin-bottom:6px; text-transform:uppercase; letter-spacing:.5px; }
        .cv-wa-bubble { background:#dcf8c6; color:#111; border-radius:8px 8px 0 8px; padding:8px 12px; font-size:12px; max-width:80%; margin-left:auto; word-break:break-word; }
        .cv-wa-bubble-time { font-size:10px; color:#666; text-align:right; margin-top:3px; }
        /* Slider visual */
        .cv-set-slider-wrap { display:flex; align-items:center; gap:12px; }
        .cv-set-slider { flex:1; accent-color:var(--gold); }
        .cv-set-slider-val { font-size:14px; font-weight:700; color:var(--gold); min-width:40px; text-align:center; }
        /* Save bar */
        .cv-set-save-bar { position:sticky; bottom:0; background:var(--card); border-top:1px solid var(--bord); padding:14px 20px; display:flex; align-items:center; gap:12px; border-radius:0 0 14px 14px; }
        .cv-set-btn-save { background:var(--gold); color:#000; border:none; padding:11px 28px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; transition:opacity .2s; }
        .cv-set-btn-save:hover { opacity:.85; }
        .cv-set-saved-msg { color:var(--green); font-size:13px; display:none; }
        </style>

        <?php if ($saved) : ?>
        <script>document.addEventListener('DOMContentLoaded',function(){var m=document.getElementById('cv-saved-msg');if(m){m.style.display='block';setTimeout(function(){m.style.display='none';},3000);}});</script>
        <?php endif; ?>

        <div class="cv-set-topbar">
            <h1 class="cv-set-title">⚙️ <span>Configurações</span></h1>
            <div class="cv-set-status">
                <span class="cv-set-pill <?php echo $api_ok ? 'ok' : 'off'; ?>">
                    <?php echo $api_ok ? '✅' : '❌'; ?> MailerLite <?php echo $api_ok ? 'conectado' : 'não configurado'; ?>
                </span>
                <span class="cv-set-pill <?php echo $wa_ok ? 'ok' : 'off'; ?>">
                    <?php echo $wa_ok ? '✅' : '❌'; ?> WhatsApp <?php echo $wa_ok ? 'ativo' : 'não configurado'; ?>
                </span>
            </div>
        </div>

        <?php echo CV_Admin::btn_voltar(); ?>

        <form method="post" id="cv-settings-form">
            <?php wp_nonce_field('cv_settings_save'); ?>
            <input type="hidden" name="cv_save_settings" value="1" />

            <div class="cv-set-grid">

                <!-- MailerLite -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">📧</span>
                        <div>
                            <div class="cv-set-card-title">MailerLite</div>
                            <div class="cv-set-card-subtitle">
                                <span class="cv-set-status-dot <?php echo $api_ok ? 'ok' : 'off'; ?>"></span>
                                <?php echo $api_ok ? 'API configurada' : 'Não configurado'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cv-set-card-body">
                        <div class="cv-set-field">
                            <label>API Key</label>
                            <input type="password" name="cv_mailerlite_api_key" class="cv-set-input"
                                   value="<?php echo esc_attr($api_key); ?>"
                                   placeholder="Sua chave de API do MailerLite" />
                            <div class="cv-set-hint">Encontre em: MailerLite → Integrações → API</div>
                        </div>
                        <div class="cv-set-field">
                            <label>Group ID (lista padrão)</label>
                            <input type="text" name="cv_mailerlite_group_id" class="cv-set-input"
                                   value="<?php echo esc_attr($group_id); ?>"
                                   placeholder="ID numérico do grupo" />
                            <div class="cv-set-hint">Novos assinantes serão adicionados neste grupo.</div>
                        </div>
                        <?php if ($api_ok) : ?>
                        <div style="background:rgba(29,185,84,.06);border:1px solid rgba(29,185,84,.2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--green)">
                            ✅ MailerLite ativo — e-mails automáticos habilitados
                        </div>
                        <?php else : ?>
                        <div style="background:rgba(231,76,60,.06);border:1px solid rgba(231,76,60,.2);border-radius:8px;padding:10px 14px;font-size:12px;color:var(--red)">
                            ⚠️ Configure a API Key para habilitar o e-mail marketing
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- WhatsApp -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">💬</span>
                        <div>
                            <div class="cv-set-card-title">WhatsApp Flutuante</div>
                            <div class="cv-set-card-subtitle">
                                <span class="cv-set-status-dot <?php echo $wa_ok ? 'ok' : 'off'; ?>"></span>
                                <?php echo $wa_ok ? 'Botão ativo no site' : 'Botão oculto'; ?>
                            </div>
                        </div>
                    </div>
                    <div class="cv-set-card-body">
                        <div class="cv-set-field">
                            <label>Número (com DDI)</label>
                            <input type="text" name="cv_whatsapp_number" class="cv-set-input"
                                   id="cv-wa-num"
                                   value="<?php echo esc_attr($whatsapp); ?>"
                                   placeholder="5531999999999" />
                            <div class="cv-set-hint">55 + DDD + número. Deixe vazio para ocultar o botão.</div>
                        </div>
                        <div class="cv-set-field">
                            <label>Mensagem pré-preenchida</label>
                            <textarea name="cv_whatsapp_message" rows="2" class="cv-set-input"
                                      id="cv-wa-msg"
                                      placeholder="Mensagem ao clicar no botão..."
                            ><?php echo esc_textarea($wa_message); ?></textarea>
                        </div>
                        <div class="cv-set-field">
                            <label>Preview da conversa</label>
                            <div class="cv-wa-preview">
                                <div class="cv-wa-preview-label">Como aparece para o visitante</div>
                                <div class="cv-wa-bubble" id="cv-wa-preview-bubble">
                                    <?php echo esc_html($wa_message ?: 'Mensagem aparecerá aqui...'); ?>
                                </div>
                                <div class="cv-wa-bubble-time">agora ✓✓</div>
                            </div>
                        </div>
                        <div class="cv-set-field">
                            <label>Delay de aparecimento — <span id="cv-wa-delay-val"><?php echo $wa_delay; ?></span>s</label>
                            <div class="cv-set-slider-wrap">
                                <input type="range" name="cv_whatsapp_pulse_delay" class="cv-set-slider"
                                       min="0" max="30" value="<?php echo $wa_delay; ?>"
                                       oninput="document.getElementById('cv-wa-delay-val').textContent=this.value" />
                                <span class="cv-set-slider-val"><?php echo $wa_delay; ?>s</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Player -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">▶️</span>
                        <div>
                            <div class="cv-set-card-title">Player de Áudio</div>
                            <div class="cv-set-card-subtitle">Regras de contagem de plays</div>
                        </div>
                    </div>
                    <div class="cv-set-card-body">
                        <div class="cv-set-field">
                            <label>Segundos mínimos para play válido — <span id="cv-ps-val"><?php echo $play_secs; ?></span>s</label>
                            <div class="cv-set-slider-wrap">
                                <input type="range" name="cv_play_seconds" class="cv-set-slider"
                                       min="5" max="120" value="<?php echo $play_secs; ?>"
                                       oninput="document.getElementById('cv-ps-val').textContent=this.value;document.getElementById('cv-ps-display').textContent=this.value" />
                                <span class="cv-set-slider-val" id="cv-ps-display"><?php echo $play_secs; ?>s</span>
                            </div>
                            <div class="cv-set-hint">Um play só é contabilizado após este tempo de reprodução contínua. Padrão: 30s</div>
                        </div>
                        <div style="background:rgba(212,160,23,.06);border:1px solid rgba(212,160,23,.2);border-radius:8px;padding:12px 14px">
                            <div style="font-size:12px;color:var(--gold);font-weight:600;margin-bottom:4px">ℹ️ Como funciona</div>
                            <div style="font-size:11px;color:var(--muted);line-height:1.5">
                                O sistema registra 1 play após <strong style="color:var(--text)"><?php echo $play_secs; ?> segundos</strong> de reprodução.
                                Plays do mesmo IP em menos de 60s são descartados (antifraude).
                                O ranking é recalculado automaticamente a cada hora.
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Informações do sistema -->
                <div class="cv-set-card">
                    <div class="cv-set-card-header">
                        <span class="cv-set-card-icon">🖥️</span>
                        <div>
                            <div class="cv-set-card-title">Status do Sistema</div>
                            <div class="cv-set-card-subtitle">Informações técnicas</div>
                        </div>
                    </div>
                    <div class="cv-set-card-body" style="gap:10px">
                        <?php
                        $infos = array(
                            array('label' => 'Plugin', 'value' => 'Canção Verdadeira v' . CV_VERSION, 'ok' => true),
                            array('label' => 'PHP', 'value' => phpversion(), 'ok' => version_compare(phpversion(),'7.2','>=')),
                            array('label' => 'WordPress', 'value' => get_bloginfo('version'), 'ok' => true),
                            array('label' => 'Charset', 'value' => get_bloginfo('charset'), 'ok' => true),
                            array('label' => 'URL do site', 'value' => get_site_url(), 'ok' => true),
                            array('label' => 'Debug ativo', 'value' => (defined('WP_DEBUG') && WP_DEBUG) ? 'Sim (desative em produção)' : 'Não', 'ok' => !(defined('WP_DEBUG') && WP_DEBUG)),
                        );
                        foreach ($infos as $info):
                        ?>
                        <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--bord)">
                            <span style="font-size:12px;color:var(--muted)"><?php echo esc_html($info['label']); ?></span>
                            <span style="font-size:12px;color:<?php echo $info['ok'] ? 'var(--text)' : 'var(--red)'; ?>;font-weight:600">
                                <?php echo $info['ok'] ? '✅ ' : '⚠️ '; ?><?php echo esc_html($info['value']); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div><!-- grid -->

            <!-- Save bar sticky -->
            <div class="cv-set-save-bar" style="margin-top:24px;border-radius:14px">
                <button type="submit" class="cv-set-btn-save">💾 Salvar Configurações</button>
                <span id="cv-saved-msg" class="cv-set-saved-msg">✅ Configurações salvas com sucesso!</span>
            </div>
        </form>

        <script>
        (function(){
            // Preview WhatsApp em tempo real
            var msg = document.getElementById('cv-wa-msg');
            var bubble = document.getElementById('cv-wa-preview-bubble');
            if (msg && bubble) {
                msg.addEventListener('input', function(){
                    bubble.textContent = this.value || 'Mensagem aparecerá aqui...';
                });
            }
            // Slider WhatsApp delay
            var slider = document.querySelector('input[name="cv_whatsapp_pulse_delay"]');
            var sliderVal = document.querySelector('.cv-set-slider-val');
            if (slider && sliderVal) {
                slider.addEventListener('input', function(){
                    sliderVal.textContent = this.value + 's';
                });
            }
        })();
        </script>
        </div>
        <?php
    }
}
