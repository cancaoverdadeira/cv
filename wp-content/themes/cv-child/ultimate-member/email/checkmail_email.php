<?php
// cancao-verdadeira-child/ultimate-member/email/checkmail_email.php
// Criado em: 24/09/2026 (tema v15.11.0) — Canção Verdadeira
// E-mail de ativação da conta (enviado logo após o cadastro).
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Que bom ter você aqui!', '<p>Olá!</p><p>Obrigado por se cadastrar. Para ativar sua conta, é só clicar no botão abaixo.</p><p style="font-size:15px;color:#6B4C3B">Se o botão não funcionar, copie e cole este endereço no navegador:<br>{account_activation_link}</p>', '{account_activation_link}', 'Ativar minha conta' );
