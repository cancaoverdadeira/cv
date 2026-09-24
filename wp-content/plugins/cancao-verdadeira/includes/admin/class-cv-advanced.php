<?php
// cancao-verdadeira/includes/admin/class-cv-advanced.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Funcionalidades pós-MVP agrupadas em um único arquivo:
// 1. Ranking por período (diário, semanal, mensal) com cálculo e cache
// 2. Indicadores ↑ ↓ = de posição no ranking
// 3. Sistema de logs de ações administrativas
// 4. Níveis de permissão customizados (Editor / Gerente / Master)
// 5. Recomendação automática (melhores ranqueadas que o usuário não ouviu)
// 6. Exportação de usuários em CSV
// Compatível com PHP 7.2+. Toda lógica de negócio no plugin.
// v2.26.0: removidos o filtro por gênero do ranking e das recomendações.
// v2.37.0: cada assunto foi para um trait em includes/admin/advanced/.

if ( ! defined( 'ABSPATH' ) ) { exit; }

require_once __DIR__ . '/advanced/trait-cv-adv-ranking-periodo.php';
require_once __DIR__ . '/advanced/trait-cv-adv-logs.php';
require_once __DIR__ . '/advanced/trait-cv-adv-permissoes.php';
require_once __DIR__ . '/advanced/trait-cv-adv-recomendacoes.php';
require_once __DIR__ . '/advanced/trait-cv-adv-exportacao.php';

class CV_Advanced {

    // Cada assunto vive num arquivo próprio em includes/admin/advanced/ (v2.37.0).
    use CV_Adv_Ranking_Periodo;
    use CV_Adv_Logs;
    use CV_Adv_Permissoes;
    use CV_Adv_Recomendacoes;
    use CV_Adv_Exportacao;

    public static function init() {
        // Permissões: registra roles na ativação do plugin
        add_action( 'cv_activate_roles', array( __CLASS__, 'register_roles' ) );

        // Permissões: aplica capabilities nas páginas admin
        add_action( 'admin_menu', array( __CLASS__, 'adjust_menu_caps' ), 20 );

        // Logs: intercepta ações relevantes
        add_action( 'save_post_musica',          array( __CLASS__, 'log_music_save' ),    10, 2 );
        add_action( 'before_delete_post',        array( __CLASS__, 'log_music_delete' ),  10, 1 );
        add_action( 'wp_login',                  array( __CLASS__, 'log_login' ),         10, 2 );
        add_action( 'wp_logout',                 array( __CLASS__, 'log_logout' ) );
        add_action( 'cv_playlist_created',       array( __CLASS__, 'log_playlist' ),      10, 2 );

        // AJAX: ranking por período
        add_action( 'wp_ajax_cv_ranking_period',        array( __CLASS__, 'ajax_ranking_period' ) );
        add_action( 'wp_ajax_nopriv_cv_ranking_period', array( __CLASS__, 'ajax_ranking_period' ) );

        // AJAX: recomendação
        add_action( 'wp_ajax_cv_get_recommendations',        array( __CLASS__, 'ajax_recommendations' ) );
        add_action( 'wp_ajax_nopriv_cv_get_recommendations', array( __CLASS__, 'ajax_recommendations' ) );

        // AJAX: exportar CSV (só admin)
        add_action( 'wp_ajax_cv_export_users_csv', array( __CLASS__, 'ajax_export_users_csv' ) );

        // AJAX: ver logs
        add_action( 'wp_ajax_cv_get_logs', array( __CLASS__, 'ajax_get_logs' ) );

        // REST API: ranking por período
        add_action( 'rest_api_init', array( __CLASS__, 'register_rest_routes' ) );
    }
}

CV_Advanced::init();
