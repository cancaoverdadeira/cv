<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-appearance.php
// Página "Aparência": banners e configurações visuais do site.
// Extraído de class-cv-admin-pages.php em 2026-09-12 (refatoração:
// cada página do admin passou a viver em seu próprio arquivo/classe).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Appearance {

    public static function render() {
        wp_enqueue_media();

        if ( isset( $_POST['cv_save_appearance'] ) && check_admin_referer( 'cv_appearance_save' ) ) {
            if ( isset( $_POST['cv_logo_url'] ) ) {
                update_option( 'cv_logo_url', esc_url_raw( $_POST['cv_logo_url'] ) );
            }
            if ( isset( $_POST['cv_banner_url'] ) ) {
                update_option( 'cv_banner_url', esc_url_raw( $_POST['cv_banner_url'] ) );
            }
            $saved = true;
        } else {
            $saved = false;
        }

        $logo_url   = get_option( 'cv_logo_url',   '' );
        $banner_url = get_option( 'cv_banner_url', '' );
        $has_logo   = ! empty( $logo_url );
        $has_banner = ! empty( $banner_url );
        ?>
        <div class="wrap" id="cv-appearance-exec">
        <style>
        body.wp-admin { background:#0f0f1a !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#0f0f1a !important; }
        #cv-appearance-exec {
            --gold:#D4A017; --bg:#0f0f1a; --card:#1a1a2e; --bord:#2a2a4a;
            --text:#e0e0e0; --muted:#888; --green:#1DB954;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif;
            padding-bottom:48px;
        }
        #cv-appearance-exec * { box-sizing:border-box; }
        .cv-ap-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:24px; flex-wrap:wrap; gap:12px; }
        .cv-ap-title { font-size:24px; font-weight:700; color:#fff; margin:0; }
        .cv-ap-title span { color:var(--gold); }
        .cv-ap-grid { display:grid; grid-template-columns:1fr 1fr; gap:22px; }
        @media (max-width:900px) { .cv-ap-grid { grid-template-columns:1fr; } }
        .cv-ap-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; }
        .cv-ap-card-header { padding:16px 20px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:12px; }
        .cv-ap-card-icon { font-size:22px; }
        .cv-ap-card-title { font-size:15px; font-weight:700; color:#fff; }
        .cv-ap-card-sub { font-size:11px; color:var(--muted); margin-top:2px; }
        .cv-ap-card-body { padding:20px; }
        /* Preview logo */
        .cv-ap-logo-preview {
            background:linear-gradient(135deg,#0d0d0d,#1a1a1a);
            border:1px solid var(--bord); border-radius:10px;
            min-height:100px; display:flex; align-items:center; justify-content:center;
            margin-bottom:16px; padding:20px; position:relative; overflow:hidden;
        }
        .cv-ap-logo-preview::before {
            content:'Prévia do cabeçalho';
            position:absolute; top:8px; left:12px;
            font-size:9px; color:#333; text-transform:uppercase; letter-spacing:.5px;
        }
        .cv-ap-logo-preview img { max-width:240px; max-height:70px; object-fit:contain; }
        .cv-ap-logo-placeholder { color:#333; font-size:12px; text-align:center; }
        /* Preview banner */
        .cv-ap-banner-preview {
            border:1px solid var(--bord); border-radius:10px;
            height:140px; overflow:hidden; position:relative; margin-bottom:16px;
            background:#0d0d0d;
        }
        .cv-ap-banner-preview::before {
            content:'Prévia do banner home';
            position:absolute; top:8px; left:12px; z-index:2;
            font-size:9px; color:rgba(255,255,255,.4); text-transform:uppercase; letter-spacing:.5px;
        }
        .cv-ap-banner-img {
            width:100%; height:100%; object-fit:cover;
            transition:transform .4s ease; transform:scale(1.05);
        }
        .cv-ap-banner-img:hover { transform:scale(1); }
        .cv-ap-banner-overlay {
            position:absolute; inset:0; z-index:1;
            background:linear-gradient(to bottom, transparent 40%, rgba(0,0,0,.7) 100%);
            display:flex; align-items:flex-end; padding:12px 14px;
        }
        .cv-ap-banner-label { font-size:11px; color:rgba(255,255,255,.6); }
        .cv-ap-banner-placeholder {
            display:flex; align-items:center; justify-content:center;
            height:100%; color:#333; font-size:12px; flex-direction:column; gap:8px;
        }
        /* Input area */
        .cv-ap-field { margin-bottom:14px; }
        .cv-ap-field label { display:block; font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:6px; }
        .cv-ap-input-row { display:flex; gap:8px; }
        .cv-ap-input { flex:1; background:rgba(255,255,255,.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:9px 12px; font-size:13px; outline:none; transition:border-color .2s; font-family:inherit; }
        .cv-ap-input:focus { border-color:var(--gold); }
        .cv-ap-btn-select { background:rgba(212,160,23,.12); border:1px solid rgba(212,160,23,.3); color:var(--gold); padding:9px 14px; border-radius:8px; cursor:pointer; font-size:12px; font-weight:600; white-space:nowrap; transition:background .2s; font-family:inherit; }
        .cv-ap-btn-select:hover { background:rgba(212,160,23,.22); }
        .cv-ap-hint { font-size:11px; color:var(--muted); margin-top:4px; display:flex; align-items:center; gap:8px; }
        .cv-ap-badge { font-size:10px; padding:2px 7px; border-radius:10px; font-weight:600; }
        .cv-ap-badge.ok  { background:rgba(29,185,84,.12); color:var(--green); border:1px solid rgba(29,185,84,.3); }
        .cv-ap-badge.off { background:rgba(136,136,136,.1); color:var(--muted); border:1px solid var(--bord); }
        /* Status geral */
        .cv-ap-status-row { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:20px; }
        .cv-ap-pill { display:inline-flex; align-items:center; gap:6px; padding:6px 14px; border-radius:20px; font-size:12px; font-weight:600; }
        .cv-ap-pill.ok  { background:rgba(29,185,84,.1); border:1px solid rgba(29,185,84,.3); color:var(--green); }
        .cv-ap-pill.off { background:rgba(136,136,136,.08); border:1px solid var(--bord); color:var(--muted); }
        /* Save bar */
        .cv-ap-save-bar { margin-top:22px; background:var(--card); border:1px solid var(--bord); border-radius:14px; padding:16px 20px; display:flex; align-items:center; gap:12px; }
        .cv-ap-btn-save { background:var(--gold); color:#000; border:none; padding:11px 28px; border-radius:8px; font-weight:700; font-size:14px; cursor:pointer; transition:opacity .2s; font-family:inherit; }
        .cv-ap-btn-save:hover { opacity:.85; }
        .cv-ap-saved { color:var(--green); font-size:13px; <?php echo $saved ? '' : 'display:none'; ?> }
        /* Dica de dimensão */
        .cv-ap-dim { display:inline-flex; align-items:center; gap:4px; font-size:10px; color:#555; background:rgba(255,255,255,.04); border:1px solid var(--bord); border-radius:6px; padding:3px 8px; }
        </style>

        <div class="cv-ap-topbar">
            <h1 class="cv-ap-title">🎨 <span>Aparência</span></h1>
            <div class="cv-ap-status-row">
                <span class="cv-ap-pill <?php echo $has_logo ? 'ok' : 'off'; ?>">
                    <?php echo $has_logo ? '✅ Logo definido' : '⚪ Sem logo'; ?>
                </span>
                <span class="cv-ap-pill <?php echo $has_banner ? 'ok' : 'off'; ?>">
                    <?php echo $has_banner ? '✅ Banner definido' : '⚪ Sem banner'; ?>
                </span>
            </div>
        </div>

        <?php echo CV_Admin::btn_voltar(); ?>

        <form method="post" id="cv-ap-form">
            <?php wp_nonce_field( 'cv_appearance_save' ); ?>
            <input type="hidden" name="cv_save_appearance" value="1" />

            <div class="cv-ap-grid">

                <!-- Logo -->
                <div class="cv-ap-card">
                    <div class="cv-ap-card-header">
                        <span class="cv-ap-card-icon">🖼️</span>
                        <div>
                            <div class="cv-ap-card-title">Logo do Site</div>
                            <div class="cv-ap-card-sub">Exibido no cabeçalho e sidebar</div>
                        </div>
                    </div>
                    <div class="cv-ap-card-body">
                        <!-- Preview -->
                        <div class="cv-ap-logo-preview" id="cv-logo-preview-box">
                            <?php if ( $has_logo ) : ?>
                            <img src="<?php echo esc_url($logo_url); ?>" alt="Logo" id="cv-logo-preview-img" />
                            <?php else : ?>
                            <div class="cv-ap-logo-placeholder" id="cv-logo-preview-img">
                                <span style="font-size:32px">🎵</span>
                                <span>Nenhum logo definido</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Input -->
                        <div class="cv-ap-field">
                            <label>URL da imagem</label>
                            <div class="cv-ap-input-row">
                                <input type="text" id="cv_logo_url" name="cv_logo_url"
                                       value="<?php echo esc_url($logo_url); ?>"
                                       class="cv-ap-input" placeholder="https://..." />
                                <button type="button" class="cv-ap-btn-select cv-media-btn" data-target="cv_logo_url" data-preview="cv-logo-preview">
                                    📁 Selecionar
                                </button>
                            </div>
                            <div class="cv-ap-hint">
                                <span class="cv-ap-dim">📐 Recomendado: 300×120px</span>
                                <span class="cv-ap-dim">🎨 PNG transparente</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banner -->
                <div class="cv-ap-card">
                    <div class="cv-ap-card-header">
                        <span class="cv-ap-card-icon">🖼️</span>
                        <div>
                            <div class="cv-ap-card-title">Banner da Home</div>
                            <div class="cv-ap-card-sub">Fundo do hero com efeito parallax</div>
                        </div>
                    </div>
                    <div class="cv-ap-card-body">
                        <!-- Preview -->
                        <div class="cv-ap-banner-preview">
                            <?php if ( $has_banner ) : ?>
                            <img src="<?php echo esc_url($banner_url); ?>" alt="Banner"
                                 class="cv-ap-banner-img" id="cv-banner-preview-img" />
                            <div class="cv-ap-banner-overlay">
                                <span class="cv-ap-banner-label">✨ Canção Verdadeira — banner ativo</span>
                            </div>
                            <?php else : ?>
                            <div class="cv-ap-banner-placeholder" id="cv-banner-placeholder">
                                <span style="font-size:28px">🌅</span>
                                <span>Nenhum banner definido</span>
                                <span style="font-size:10px;color:#444">Aparecerá um gradiente padrão no site</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        <!-- Input -->
                        <div class="cv-ap-field">
                            <label>URL da imagem</label>
                            <div class="cv-ap-input-row">
                                <input type="text" id="cv_banner_url" name="cv_banner_url"
                                       value="<?php echo esc_url($banner_url); ?>"
                                       class="cv-ap-input" placeholder="https://..." />
                                <button type="button" class="cv-ap-btn-select cv-media-btn" data-target="cv_banner_url" data-preview="cv-banner-preview">
                                    📁 Selecionar
                                </button>
                            </div>
                            <div class="cv-ap-hint">
                                <span class="cv-ap-dim">📐 Recomendado: 1920×600px</span>
                                <span class="cv-ap-dim">📸 JPG ou WebP</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div><!-- grid -->

            <!-- Save bar -->
            <div class="cv-ap-save-bar">
                <button type="submit" class="cv-ap-btn-save">💾 Salvar Aparência</button>
                <span class="cv-ap-saved">✅ Aparência salva com sucesso!</span>
                <span style="font-size:12px;color:var(--muted);margin-left:auto">
                    As alterações aparecem no site imediatamente após salvar.
                </span>
            </div>
        </form>
        </div><!-- wrap -->

        <script>
        jQuery(function($){
            // Media uploader com preview em tempo real
            $('body').on('click', '.cv-media-btn', function(){
                var targetId  = $(this).data('target');
                var previewKey = $(this).data('preview');
                var frame = wp.media({
                    title: 'Selecionar Imagem',
                    button: { text: 'Usar esta imagem' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    var url = attachment.url;
                    $('#' + targetId).val(url);
                    // Preview logo
                    if (previewKey === 'cv-logo-preview') {
                        var box = $('#cv-logo-preview-box');
                        box.find('.cv-ap-logo-placeholder').remove();
                        var img = box.find('img');
                        if (img.length) { img.attr('src', url); }
                        else { box.html('<img src="' + url + '" id="cv-logo-preview-img" style="max-width:240px;max-height:70px;object-fit:contain" />'); }
                    }
                    // Preview banner
                    if (previewKey === 'cv-banner-preview') {
                        var bimg = $('#cv-banner-preview-img');
                        var bph  = $('#cv-banner-placeholder');
                        if (bimg.length) { bimg.attr('src', url); }
                        else {
                            bph.replaceWith('<img src="' + url + '" id="cv-banner-preview-img" class="cv-ap-banner-img" />');
                        }
                    }
                });
                frame.open();
            });
        });
        </script>
        <?php
    }
}
