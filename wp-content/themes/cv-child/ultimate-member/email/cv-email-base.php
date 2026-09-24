<?php
// cancao-verdadeira-child/ultimate-member/email/cv-email-base.php
// Criado em: 24/09/2026 (tema v15.11.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Base visual dos e-mails do Ultimate Member em português: cabeçalho marrom
// com o nome do site, texto grande (público de 55 a 65 anos), botão laranja
// da marca e rodapé com contato. Usada pelos modelos desta pasta, que
// substituem os modelos em inglês do plugin (ultimate-member/templates/email).
// Os campos entre chaves ({site_name}, {admin_email}...) são trocados pelo
// Ultimate Member na hora do envio.

if ( ! defined( 'ABSPATH' ) ) { exit; }

if ( ! function_exists( 'cv_um_email' ) ) {
    /**
     * @param string $titulo    Frase principal (texto puro).
     * @param string $texto     Parágrafo(s) em HTML simples.
     * @param string $botao_url Link do botão ('' = sem botão). Pode ser um campo {..}.
     * @param string $botao_txt Texto do botão.
     */
    function cv_um_email( $titulo, $texto, $botao_url = '', $botao_txt = '' ) {
        ?>
<div style="background:#FBF6EE;padding:30px 12px;font-family:Georgia,'Times New Roman',serif;">
  <div style="max-width:560px;margin:0 auto;background:#FFFFFF;border-radius:10px;overflow:hidden;border:1px solid #EADBC6;">
    <div style="background:#7B3A22;color:#FBF6EE;text-align:center;padding:22px 20px;font-size:26px;font-weight:bold;">{site_name}</div>
    <div style="padding:30px 30px 10px 30px;color:#3B2418;font-size:18px;line-height:1.6;">
      <p style="font-size:22px;font-weight:bold;margin:0 0 16px 0;color:#7B3A22;"><?php echo esc_html( $titulo ); ?></p>
      <?php echo wp_kses_post( $texto ); ?>
    </div>
    <?php if ( $botao_url ) : ?>
    <div style="text-align:center;padding:20px 30px 30px 30px;">
      <a href="<?php echo $botao_url; // campo {..} trocado pelo Ultimate Member ?>" style="display:inline-block;background:#F2A51A;color:#3B2418;font-size:18px;font-weight:bold;padding:14px 32px;border-radius:8px;text-decoration:none;"><?php echo esc_html( $botao_txt ); ?></a>
    </div>
    <?php endif; ?>
    <div style="background:#F3E6D3;padding:18px 30px;color:#6B4C3B;font-size:15px;line-height:1.5;text-align:center;">
      Precisa de ajuda? Escreva para <a href="mailto:{admin_email}" style="color:#7B3A22;">{admin_email}</a><br>
      Com carinho, equipe <a href="{site_url}" style="color:#7B3A22;">{site_name}</a>
    </div>
  </div>
</div>
        <?php
    }
}
