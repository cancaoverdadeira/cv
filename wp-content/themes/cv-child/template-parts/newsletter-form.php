<?php
// cancao-verdadeira-child/template-parts/newsletter-form.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Formulário da newsletter (v15.61.0): nome + e-mail + botão + aviso com o
// link da Política de Privacidade. Usado no rodapé (footer-content.php) e na
// janela "📩 Receba novidades" do menu lateral (newsletter-janela.php).
// O nome vai para o MailerLite e permite o "Olá, Maria!" nos e-mails.
// Quem está logado já vem com nome e e-mail preenchidos.
// O envio é feito pelo script único no fim de footer-content.php (.cv-nl-box).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$cv_nl_user  = wp_get_current_user();
$cv_nl_nome  = $cv_nl_user->exists() ? $cv_nl_user->first_name : '';
$cv_nl_email = $cv_nl_user->exists() ? $cv_nl_user->user_email : '';
$cv_nl_priv  = function_exists( 'get_privacy_policy_url' ) ? get_privacy_policy_url() : '';
?>
<div class="cv-nl-box">
    <div class="cv-newsletter-form cv-nl-campos">
        <input type="text" class="cv-input cv-nl-nome" placeholder="Seu nome"
               autocomplete="given-name" aria-label="Seu nome"
               value="<?php echo esc_attr( $cv_nl_nome ); ?>" />
        <input type="email" class="cv-input cv-nl-email" placeholder="Seu melhor e-mail"
               autocomplete="email" aria-label="Seu e-mail"
               value="<?php echo esc_attr( $cv_nl_email ); ?>" />
        <button type="button" class="cv-btn cv-btn-primary cv-nl-enviar">Inscrever-se</button>
    </div>
    <div class="cv-nl-msg" role="status" style="display:none"></div>
    <p class="cv-nl-aviso">
        Sem spam. Cancele quando quiser, pelo link no fim de cada e-mail.
        <?php if ( $cv_nl_priv ) : ?>
        <a href="<?php echo esc_url( $cv_nl_priv ); ?>">Política de Privacidade</a>
        <?php endif; ?>
    </p>
</div>
