<?php
// cancao-verdadeira-child/ultimate-member/email/approved_email.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Conta aprovada pelo administrador.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Sua conta foi aprovada!', '<p>Olá!</p><p>Obrigado por se cadastrar no {site_name}. Sua conta já está liberada.</p><p>Seu e-mail de acesso: <strong>{email}</strong></p><p style="font-size:15px;color:#6B4C3B">Se ainda não criou sua senha, use este endereço:<br>{password_reset_link}</p>', '{login_url}', 'Entrar no site' );
