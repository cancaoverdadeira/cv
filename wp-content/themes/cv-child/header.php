<?php
// cancao-verdadeira-child/header.php
// Gerado em: 2026-06-22 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Header mínimo: abre o documento HTML, carrega wp_head() e o toast container.
// A sidebar e topbar são incluídas diretamente nos templates de página
// via get_template_part() — não ficam aqui para permitir controle total do layout.
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <?php wp_head(); ?>
</head>
<body <?php body_class('cv-dark'); ?>>
<?php wp_body_open(); ?>
<!-- Container de toasts (feedback visual) -->
<div id="cv-toast-container" role="status" aria-live="polite"></div>
