<?php
// cancao-verdadeira/includes/monetization/class-cv-monetization.php
// Gerado em: 2026-06-14 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Sistema de monetização em 4 módulos:
// 1. Banners de parceiros — imagem+link+texto+data+tracking de cliques,
//    posicionamento configurável (home topo, meio de página, rodapé),
//    tipo "Anúncio Interno" exclusivo para home com filtro no cadastro.
// 2. Loja simples — ebook, pendrive, caneca, camiseta com link externo,
//    shortcode [cv_loja] para uso no Elementor.
// 3. Sorteios automáticos por data agendada via WP-Cron, participantes
//    são assinantes cadastrados, vencedor recebe e-mail automático.
// 4. Brindes para membros da comunidade — admin cadastra e escolhe
//    destinatário, sistema envia e-mail de notificação.
// v2.28.0 (regras do usuário para publicidade):
//   - nunca no topo: a posição "home_topo" foi removida;
//   - sem banner rotativo: cada posição mostra UM banner fixo (o ativo mais
//     recente), nada de sorteio a cada visita;
//   - nova posição "apos_letra" na página da música: letra → parágrafo curto
//     → aviso "Leia após a publicidade" → banner → resto da página;
//   - clique: o destino vem do banner cadastrado (antes vinha da URL, o que
//     permitia usar o site para redirecionar para qualquer endereço).
// v2.38.0: cada módulo foi para um trait em includes/monetization/partes/.

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/partes/trait-cv-mon-banners.php';
require_once __DIR__ . '/partes/trait-cv-mon-loja.php';
require_once __DIR__ . '/partes/trait-cv-mon-sorteios.php';
require_once __DIR__ . '/partes/trait-cv-mon-brindes.php';

class CV_Monetization {

    // Cada módulo vive num arquivo próprio em includes/monetization/partes/ (v2.38.0).
    use CV_Mon_Banners;
    use CV_Mon_Loja;
    use CV_Mon_Sorteios;
    use CV_Mon_Brindes;

    public static function init() {
        // Shortcodes
        add_shortcode( 'cv_banner',  array( __CLASS__, 'shortcode_banner' ) );
        add_shortcode( 'cv_loja',    array( __CLASS__, 'shortcode_loja' ) );

        // AJAX — tracking de cliques em banners
        add_action( 'wp_ajax_cv_banner_click',        array( __CLASS__, 'ajax_banner_click' ) );
        add_action( 'wp_ajax_nopriv_cv_banner_click', array( __CLASS__, 'ajax_banner_click' ) );

        // AJAX — admin: salvar banner, produto, sorteio, brinde
        add_action( 'wp_ajax_cv_save_banner',   array( __CLASS__, 'ajax_save_banner' ) );
        add_action( 'wp_ajax_cv_delete_banner', array( __CLASS__, 'ajax_delete_banner' ) );
        add_action( 'wp_ajax_cv_save_produto',  array( __CLASS__, 'ajax_save_produto' ) );
        add_action( 'wp_ajax_cv_delete_produto',array( __CLASS__, 'ajax_delete_produto' ) );
        add_action( 'wp_ajax_cv_save_sorteio',  array( __CLASS__, 'ajax_save_sorteio' ) );
        add_action( 'wp_ajax_cv_save_brinde',   array( __CLASS__, 'ajax_save_brinde' ) );
        add_action( 'wp_ajax_cv_enviar_brinde', array( __CLASS__, 'ajax_enviar_brinde' ) );

        // Cron de sorteios automáticos
        add_action( 'cv_cron_sorteios', array( __CLASS__, 'processar_sorteios' ) );
    }
}

CV_Monetization::init();
