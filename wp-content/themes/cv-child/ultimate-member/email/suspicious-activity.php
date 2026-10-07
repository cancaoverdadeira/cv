<?php
// cancao-verdadeira-child/ultimate-member/email/suspicious-activity.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Aviso ao administrador: atividade suspeita em contas.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Atividade suspeita', '<p>O Ultimate Member encontrou atividade suspeita nestas contas:</p><p>{banned_profile_links}</p><p>Por segurança, essas contas foram bloqueadas ou desativadas e desconectadas do site.</p>' );
