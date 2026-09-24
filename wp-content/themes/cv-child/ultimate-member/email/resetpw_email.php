<?php
// cancao-verdadeira-child/ultimate-member/email/resetpw_email.php
// Criado em: 24/09/2026 (tema v15.11.0) — Canção Verdadeira
// Link para redefinir a senha.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Vamos criar uma nova senha', '<p>Olá!</p><p>Recebemos um pedido para trocar a senha da sua conta. Se foi você, clique no botão abaixo.</p><p>Se você não pediu, pode ignorar este e-mail: sua senha continua a mesma.</p><p style="font-size:15px;color:#6B4C3B">Se o botão não funcionar, copie e cole este endereço no navegador:<br>{password_reset_link}</p>', '{password_reset_link}', 'Criar nova senha' );
