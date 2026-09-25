<?php
// cancao-verdadeira-child/template-parts/musica/dedicatoria.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// Faixa "💌 <De> ofereceu esta música para <Para>" no topo da música, quando
// o link veio de um "Ofereça esta música" (?para=&de=&msg=). v15.22.0.
// Tudo é limpo e cortado (nomes até 40 letras, recado até 200).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$para = isset( $_GET['para'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['para'] ) ), 0, 40 ) : '';
$de   = isset( $_GET['de'] ) ? mb_substr( sanitize_text_field( wp_unslash( $_GET['de'] ) ), 0, 40 ) : '';
$msg  = isset( $_GET['msg'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_GET['msg'] ) ), 0, 200 ) : '';
if ( '' === $para || '' === $de ) { return; }
?>
<section class="cv-dedicatoria" aria-label="Dedicatória">
    <div class="cv-dedicatoria-icone" aria-hidden="true">💌</div>
    <div class="cv-dedicatoria-texto">
        <p class="cv-dedicatoria-para"><?php echo esc_html( $para ); ?>, esta música é para você!</p>
        <?php if ( '' !== $msg ) : ?>
        <blockquote><?php echo nl2br( esc_html( $msg ) ); ?></blockquote>
        <?php endif; ?>
        <p class="cv-dedicatoria-de">Com carinho, <strong><?php echo esc_html( $de ); ?></strong></p>
    </div>
    <button type="button" class="cv-btn cv-btn-primary cv-dedicatoria-ouvir">▶ Ouvir agora</button>
</section>
