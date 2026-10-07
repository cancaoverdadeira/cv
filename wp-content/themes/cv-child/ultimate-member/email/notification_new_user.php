<?php
// cancao-verdadeira-child/ultimate-member/email/notification_new_user.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Aviso ao administrador: alguém criou uma conta.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Nova conta criada', '<p><strong>{display_name}</strong> acabou de criar uma conta no {site_name}.</p><p>Dados do cadastro:</p><div style="background:#FBF6EE;border:1px solid #EADBC6;border-radius:8px;padding:12px 16px;font-size:16px;">{submitted_registration}</div>', '{user_profile_link}', 'Ver o perfil' );
