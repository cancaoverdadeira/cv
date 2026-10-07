<?php
// cancao-verdadeira-child/ultimate-member/email/pending_email.php
// Criado em: 07/10/2026 (tema v15.38.0) — Canção Verdadeira
// Cadastro recebido, esperando a análise do administrador.
// Substitui o modelo em inglês do Ultimate Member. Visual em cv-email-base.php.
// Os campos {..} são trocados pelo Ultimate Member no envio.
if ( ! defined( 'ABSPATH' ) ) { exit; }
include_once __DIR__ . '/cv-email-base.php';
cv_um_email( 'Recebemos seu cadastro!', '<p>Olá!</p><p>Obrigado por se cadastrar no {site_name}. Seu cadastro vai ser conferido pela nossa equipe.</p><p>Assim que for aprovado, você recebe outro e-mail avisando.</p>' );
