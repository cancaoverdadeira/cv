<?php
// cancao-verdadeira-child/ultimate-member/email/changedpw_email.php
// Criado em: 24/09/2026 (tema v15.11.0) — Canção Verdadeira
// Aviso de senha alterada.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Sua senha foi alterada', '<p>Olá!</p><p>A senha da sua conta acabou de ser trocada.</p><p>Se não foi você, fale com a gente agora mesmo pelo e-mail abaixo.</p>', '{user_account_link}', 'Ver minha conta' );
