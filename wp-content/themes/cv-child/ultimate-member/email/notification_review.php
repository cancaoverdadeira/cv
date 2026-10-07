<?php
// cancao-verdadeira-child/ultimate-member/email/notification_review.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Aviso ao administrador: cadastro esperando análise.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Cadastro esperando sua análise', '<p><strong>{display_name}</strong> se cadastrou no {site_name} e está esperando a sua aprovação.</p><p>Dados do cadastro:</p><div style="background:#FBF6EE;border:1px solid #EADBC6;border-radius:8px;padding:12px 16px;font-size:16px;">{submitted_registration}</div>', '{user_profile_link}', 'Analisar o cadastro' );
