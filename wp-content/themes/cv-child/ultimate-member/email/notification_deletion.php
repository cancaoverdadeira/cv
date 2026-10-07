<?php
// cancao-verdadeira-child/ultimate-member/email/notification_deletion.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Aviso ao administrador: uma conta foi excluída.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Conta excluída', '<p>A conta de <strong>{display_name}</strong> foi excluída do {site_name}.</p>' );
