<?php
/*
 * Template Name: Contato
 */
// cancao-verdadeira-child/templates/page-contact.php
// Gerado em: 2026-06-22 15:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página de contato com formulário nativo WordPress (wp_mail) e
// links para redes sociais configuradas no plugin CV_Social.
// Sem dependência do Gravity Forms — funciona com o WordPress puro.

if ( ! defined( 'ABSPATH' ) ) { exit; }

// Processa envio do formulário
$msg_enviada = false;
$msg_erro    = '';

if ( isset($_POST['cv_contact_submit']) ) {
    if ( ! wp_verify_nonce($_POST['cv_contact_nonce'] ?? '', 'cv_contact_form') ) {
        $msg_erro = 'Erro de segurança. Tente novamente.';
    } else {
        $nome    = sanitize_text_field( $_POST['cv_nome']    ?? '' );
        $email   = sanitize_email(      $_POST['cv_email']   ?? '' );
        $assunto = sanitize_text_field( $_POST['cv_assunto'] ?? '' );
        $mensagem= sanitize_textarea_field( $_POST['cv_mensagem'] ?? '' );

        if ( ! $nome || ! is_email($email) || ! $mensagem ) {
            $msg_erro = 'Por favor, preencha todos os campos obrigatórios.';
        } else {
            $admin_email = get_option('admin_email');
            $corpo = "Nome: $nome\nE-mail: $email\nAssunto: $assunto\n\nMensagem:\n$mensagem";
            $enviou = wp_mail(
                $admin_email,
                '[Canção Verdadeira] ' . ($assunto ?: 'Mensagem de contato'),
                $corpo,
                array("Reply-To: $nome <$email>")
            );
            if ($enviou) {
                $msg_enviada = true;
            } else {
                $msg_erro = 'Erro ao enviar. Tente novamente ou entre em contato pelo WhatsApp.';
            }
        }
    }
}

$redes = class_exists('CV_Social') ? CV_Social::get_all() : array();
$whatsapp = $redes['whatsapp']['url'] ?? '';

get_header();
?>

<div class="cv-app" id="cv-app">
    <?php get_template_part('template-parts/sidebar'); ?>

    <main class="cv-main" id="cv-main" role="main">
        <?php get_template_part('template-parts/topbar'); ?>

        <div style="max-width:720px;margin:0 auto;padding:48px 36px 60px">

            <h1 style="font-family:var(--font-display);font-size:32px;font-weight:700;
                       margin:0 0 8px">
                📬 Fale <span style="color:var(--cv-gold)">Conosco</span>
            </h1>
            <p style="color:var(--cv-text-muted);margin:0 0 36px;font-size:15px">
                Tem uma música autoral, uma sugestão ou quer saber mais? Entre em contato!
            </p>

            <!-- Links rápidos -->
            <?php if (!empty($redes)) : ?>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:36px">
                <?php foreach ($redes as $key => $r) : ?>
                <a href="<?php echo esc_url($r['url']); ?>"
                   target="_blank" rel="noopener"
                   style="display:inline-flex;align-items:center;gap:8px;padding:10px 18px;
                          background:rgba(255,255,255,.05);border:1px solid var(--cv-border);
                          border-radius:var(--cv-radius-full);color:var(--cv-text);
                          font-size:13px;font-weight:600;text-decoration:none;transition:all .2s"
                   onmouseover="this.style.background='<?php echo esc_attr($r['color']); ?>';this.style.borderColor='transparent'"
                   onmouseout="this.style.background='rgba(255,255,255,.05)';this.style.borderColor='var(--cv-border)'">
                    <?php echo $r['icon']; ?> <?php echo esc_html($r['label']); ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Feedback de envio -->
            <?php if ($msg_enviada) : ?>
            <div style="background:#1a3a1a;border:1px solid #2d6a2d;border-radius:var(--cv-radius);
                        padding:20px 24px;margin-bottom:28px;color:#5cb85c;font-size:15px">
                ✓ Mensagem enviada com sucesso! Responderemos em breve.
            </div>
            <?php elseif ($msg_erro) : ?>
            <div style="background:#3a1a1a;border:1px solid #6a2d2d;border-radius:var(--cv-radius);
                        padding:20px 24px;margin-bottom:28px;color:#e74c3c;font-size:15px">
                ⚠ <?php echo esc_html($msg_erro); ?>
            </div>
            <?php endif; ?>

            <!-- Formulário -->
            <?php if (!$msg_enviada) : ?>
            <form method="POST"
                  style="background:var(--cv-bg-card);border:1px solid var(--cv-border-subtle);
                         border-radius:var(--cv-radius);padding:32px">
                <?php wp_nonce_field('cv_contact_form', 'cv_contact_nonce'); ?>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;
                                      color:var(--cv-text-muted);margin-bottom:6px">
                            Seu nome <span style="color:var(--cv-error)">*</span>
                        </label>
                        <input type="text" name="cv_nome"
                               class="cv-input"
                               value="<?php echo esc_attr($_POST['cv_nome'] ?? ''); ?>"
                               placeholder="Como quer ser chamado"
                               required />
                    </div>
                    <div>
                        <label style="display:block;font-size:13px;font-weight:600;
                                      color:var(--cv-text-muted);margin-bottom:6px">
                            E-mail <span style="color:var(--cv-error)">*</span>
                        </label>
                        <input type="email" name="cv_email"
                               class="cv-input"
                               value="<?php echo esc_attr($_POST['cv_email'] ?? ''); ?>"
                               placeholder="seu@email.com"
                               required />
                    </div>
                </div>

                <div style="margin-bottom:20px">
                    <label style="display:block;font-size:13px;font-weight:600;
                                  color:var(--cv-text-muted);margin-bottom:6px">
                        Assunto
                    </label>
                    <input type="text" name="cv_assunto"
                           class="cv-input"
                           value="<?php echo esc_attr($_POST['cv_assunto'] ?? ''); ?>"
                           placeholder="Ex: Quero cadastrar minha música, Sugestão, Parceria..." />
                </div>

                <div style="margin-bottom:28px">
                    <label style="display:block;font-size:13px;font-weight:600;
                                  color:var(--cv-text-muted);margin-bottom:6px">
                        Mensagem <span style="color:var(--cv-error)">*</span>
                    </label>
                    <textarea name="cv_mensagem"
                              class="cv-input"
                              rows="5"
                              placeholder="Escreva sua mensagem aqui..."
                              required><?php echo esc_textarea($_POST['cv_mensagem'] ?? ''); ?></textarea>
                </div>

                <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px">
                    <p style="font-size:12px;color:var(--cv-text-dim);margin:0">
                        Campos marcados com <span style="color:var(--cv-error)">*</span> são obrigatórios
                    </p>
                    <button type="submit"
                            name="cv_contact_submit"
                            value="1"
                            class="cv-btn cv-btn-primary cv-btn-lg">
                        📬 Enviar mensagem
                    </button>
                </div>
            </form>
            <?php endif; ?>

            <?php if ($whatsapp) : ?>
            <div style="text-align:center;margin-top:28px;padding:20px;
                        background:rgba(37,211,102,.06);border:1px solid rgba(37,211,102,.2);
                        border-radius:var(--cv-radius)">
                <p style="color:var(--cv-text-muted);margin:0 0 12px;font-size:14px">
                    Prefere resposta mais rápida?
                </p>
                <a href="<?php echo esc_url($whatsapp); ?>"
                   target="_blank" rel="noopener"
                   class="cv-btn cv-btn-primary"
                   style="background:#25D366;border:none">
                    💬 Falar pelo WhatsApp
                </a>
            </div>
            <?php endif; ?>

        </div>

        <?php get_template_part('template-parts/footer-content'); ?>
        <?php get_template_part('template-parts/player'); ?>
    </main>
</div>

<?php get_footer(); ?>
