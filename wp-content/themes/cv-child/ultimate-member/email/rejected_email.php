<?php
// cancao-verdadeira-child/ultimate-member/email/rejected_email.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Cadastro não aprovado.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Seu cadastro não foi aprovado', '<p>Olá!</p><p>Infelizmente não pudemos aprovar seu cadastro no {site_name} desta vez.</p><p>Se quiser que a gente olhe de novo, escreva para <a href="mailto:{admin_email}" style="color:#7B3A22;">{admin_email}</a>.</p>' );
