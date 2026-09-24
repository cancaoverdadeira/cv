<?php
// cancao-verdadeira-child/ultimate-member/email/inactive_email.php
// Criado em: 24/09/2026 (tema v15.11.0) — Canção Verdadeira
// Aviso de conta desativada.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Sua conta foi desativada', '<p>Olá!</p><p>Sua conta no {site_name} foi desativada.</p><p>Se quiser reativá-la, escreva para o e-mail abaixo.</p>' );
