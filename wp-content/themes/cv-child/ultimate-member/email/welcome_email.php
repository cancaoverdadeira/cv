<?php
// cancao-verdadeira-child/ultimate-member/email/welcome_email.php
// Criado em: 24/09/2026 (tema v15.11.0) — Canção Verdadeira
// Boas-vindas (conta ativa).
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Sua conta está ativa!', '<p>Olá!</p><p>Seja bem-vindo(a) ao {site_name}. Agora você pode favoritar músicas, criar playlists e acompanhar as novidades.</p>', '{action_url}', 'Entrar no site' );
