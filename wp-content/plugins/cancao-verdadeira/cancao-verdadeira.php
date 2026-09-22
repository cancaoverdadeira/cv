<?php
// cancao-verdadeira/cancao-verdadeira.php
// Gerado em: 2026-06-29 10:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// v2.23.0 — Painel de Banco de Dados: visão executiva das tabelas cv_*,
// contagem real de registros, tamanho em disco, engine, índices e status.
// Painel de Inteligência Editorial: oportunidades por gap (sem letra, sem
// capa, sem YouTube, etc.), score de completude, checklist visual e links
// diretos de edição. Dois novos botões na Central de Ações (Desempenho).
// Sugestões implementadas a partir da avaliação do Diretor de TI — Semana 28.

/**
 * Plugin Name: Cancao Verdadeira
 * Plugin URI:  https://cancaoverdadeira.com.br
 * Description: Plataforma de letras musicais sertanejas - player, ranking dinâmico, trending ao vivo, recomendação automática, conquistas e shortcodes para Elementor.
 * Version:     2.24.3
 * Author:      Cancao Verdadeira
 * Text Domain: cancao-verdadeira
 * Requires at least: 6.0
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CV_VERSION',        '2.24.3' );
define( 'CV_DB_VERSION',     '8' );       // v2.15.0: tabelas cv_sentimentos + cv_musica_sentimentos + cv_calibracao_log
define( 'CV_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CV_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'CV_PLUGIN_FILE',    __FILE__ );
define( 'CV_PLAY_SECONDS',   30 );

// ── Carrega todos os módulos do plugin ───────────────────────────
$cv_includes = array(
    // CPT, taxonomias e metaboxes
    'includes/cpt/class-cv-cpt.php',
    'includes/cpt/class-cv-taxonomies.php',
    'includes/cpt/class-cv-metaboxes.php',
    // Ranking e trending
    'includes/ranking/class-cv-ranking.php',
    'includes/realtime/class-cv-trending.php',
    // AJAX — interações do usuário
    'includes/ajax/class-cv-plays.php',
    'includes/ajax/class-cv-favorites.php',
    'includes/ajax/class-cv-playlists.php',
    'includes/ajax/class-cv-playlist-modal.php',
    'includes/ajax/class-cv-ratings.php',
    'includes/ajax/class-cv-newsletter.php',
    'includes/ajax/class-cv-lyric-comments.php',
    'includes/ajax/class-cv-youtube-import.php',
    // Área do usuário
    'includes/user/class-cv-auth.php',          // autenticação nativa (login/cadastro)
    'includes/user/class-cv-user-area.php',
    'includes/user/class-cv-profile-edit.php', // edição de perfil, senha, excluir conta
    'includes/user/class-cv-notifications.php',
    'includes/user/class-cv-public-profile.php',
    'includes/user/class-cv-um-integration.php',
    'includes/user/class-cv-achievements.php',
    // Segurança complementar
    'includes/security/class-cv-security.php',
    // SEO e compatibilidade
    'includes/seo/class-cv-schema.php',
    'includes/compat/class-cv-litespeed.php',
    // Painel administrativo
    'includes/admin/class-cv-admin.php',
    'includes/admin/class-cv-cover-preview.php',
    'includes/admin/class-cv-admin-exports.php',
    'includes/admin/class-cv-admin-charts.php',
    'includes/admin/class-cv-admin-seo.php',
    // Páginas do painel administrativo — uma classe por página (v2.24.4)
    'includes/admin/pages/class-cv-page-dashboard.php',
    'includes/admin/pages/class-cv-page-ranking.php',
    'includes/admin/pages/class-cv-page-subscribers.php',
    'includes/admin/pages/class-cv-page-appearance.php',
    'includes/admin/pages/class-cv-page-settings.php',
    'includes/admin/pages/class-cv-page-playlists.php',
    'includes/admin/pages/class-cv-page-users.php',
    'includes/admin/pages/class-cv-page-logs.php',
    'includes/admin/pages/class-cv-page-roles.php',
    'includes/admin/pages/class-cv-page-youtube-import.php',
    'includes/admin/class-cv-admin-menu.php',
    'includes/admin/class-cv-advanced.php',
    // Front-end público
    'includes/public/class-cv-public.php',
    'includes/public/class-cv-shortcodes.php',
    'includes/public/class-cv-search.php',
    'includes/public/class-cv-features.php',
    'includes/public/class-cv-mvp.php',
    'includes/public/class-cv-extras.php',
    // Social e e-mails
    'includes/admin/class-cv-social.php',
    'includes/admin/class-cv-email.php',
    // Monetização
    'includes/monetization/class-cv-monetization.php',
    'includes/monetization/class-cv-monetization-pages.php',
    // Sentimentos (v2.15.0)
    'includes/sentimentos/class-cv-sentimentos.php',
    // Painel Executivo de Sentimentos (v2.16.0)
    'includes/admin/class-cv-admin-sentimentos.php',
    // Status do Sistema (v2.22.0)
    'includes/admin/class-cv-system-status.php',
    // Banco de Dados — visão executiva (v2.23.0)
    'includes/admin/class-cv-banco-dados.php',
    // Inteligência Editorial — oportunidades (v2.23.0)
    'includes/admin/class-cv-editorial.php',
    // Segurança Avançada — painel executivo (v2.24.0)
    'includes/admin/class-cv-seguranca.php',
    // Publicação Acelerada de Músicas (v2.16.0)
    'includes/admin/class-cv-publicacao-rapida.php',
    // Calibração de Métricas — exclusivo admin (v2.15.0)
    'includes/admin/class-cv-calibracao.php',
);

foreach ( $cv_includes as $file ) {
    $path = CV_PLUGIN_DIR . $file;
    if ( file_exists( $path ) ) {
        require_once $path;
    }
}

// ── Handlers dos cron jobs ───────────────────────────────────────
add_action( 'cv_hourly_ranking', function() {
    if ( class_exists('CV_Ranking') ) {
        CV_Ranking::recalculate();
        update_option( 'cv_last_ranking_update', current_time('mysql') );
    }
} );

add_action( 'cv_daily_cleanup', function() {
    global $wpdb;
    // Plays are the historical source of truth; do not delete them here.
    // Delete only expired CV transients through the WordPress cache API.
    $prefix = '_transient_timeout_cv_';
    $expired = $wpdb->get_col( $wpdb->prepare(
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s AND CAST(option_value AS UNSIGNED) < %d",
        $wpdb->esc_like( $prefix ) . '%', time()
    ) );
    foreach ( $expired as $option ) {
        delete_transient( substr( $option, strlen( '_transient_timeout_' ) ) );
    }
    update_option( 'cv_last_cleanup', current_time( 'mysql' ), false );
} );

add_action( 'cv_weekly_trending', function() {
    if ( class_exists('CV_Trending') ) {
        delete_transient( 'cv_trending_15m' );
        CV_Trending::get_trending();
    }
} );

// ── Shortcode de redes sociais (registrado sempre, não só na ativação)
add_action( 'init', function() {
    if ( class_exists( 'CV_Social' ) ) {
        add_shortcode( 'cv_social_links', array( 'CV_Social', 'shortcode' ) );
    }
} );

// ── Hooks de ativação e desativação ──────────────────────────────
register_activation_hook( __FILE__, 'cv_activate' );
register_deactivation_hook( __FILE__, 'cv_deactivate' );

function cv_activate() {
    cv_create_tables();
    cv_seed_genres();
    // v2.15.0: semeia sentimentos padrão
    if ( class_exists('CV_Sentimentos') ) {
        CV_Sentimentos::seed();
    }
    // Registra shortcode de redes sociais
    if ( class_exists( 'CV_Social' ) ) {
        add_shortcode( 'cv_social_links', array( 'CV_Social', 'shortcode' ) );
    }
    cv_create_pages();
    // Cria páginas de login e cadastro nativos
    if ( class_exists( 'CV_Auth' ) ) {
        CV_Auth::create_auth_pages();
    }
    do_action( 'cv_activate_roles' );
    // Cria .htaccess de proteção na pasta do plugin
    if ( class_exists( 'CV_Security' ) ) {
        CV_Security::create_htaccess();
    }
    // Agendar cron jobs do plugin
    cv_schedule_crons();
    flush_rewrite_rules();
}

function cv_schedule_crons() {
    wp_clear_scheduled_hook( 'cv_cron_ranking' );
    if ( ! wp_next_scheduled( 'cv_hourly_ranking' ) ) {
        wp_schedule_event( time(), 'hourly', 'cv_hourly_ranking' );
    }
    if ( ! wp_next_scheduled( 'cv_daily_cleanup' ) ) {
        wp_schedule_event( time(), 'daily', 'cv_daily_cleanup' );
    }
    if ( ! wp_next_scheduled( 'cv_weekly_trending' ) ) {
        wp_schedule_event( time(), 'weekly', 'cv_weekly_trending' );
    }
}

// Garantir crons ativos mesmo após updates (roda uma vez por dia via option)
add_action( 'init', function() {
    $last_check = (int) get_option( 'cv_cron_check', 0 );
    if ( time() - $last_check > 86400 ) {
        cv_schedule_crons();
        update_option( 'cv_cron_check', time() );
    }
} );

function cv_deactivate() {
    wp_clear_scheduled_hook( 'cv_cron_ranking' );
    wp_clear_scheduled_hook( 'cv_cron_sorteios' );
    wp_clear_scheduled_hook( 'cv_hourly_ranking' );
    wp_clear_scheduled_hook( 'cv_daily_cleanup' );
    wp_clear_scheduled_hook( 'cv_weekly_trending' );
    if ( class_exists( 'CV_Advanced' ) ) {
        CV_Advanced::remove_roles();
    }
    flush_rewrite_rules();
}

// ── Migração automática ao atualizar o plugin ────────────────────
// Roda na inicialização se a versão do banco mudou, sem precisar
// desativar e reativar o plugin.
add_action( 'plugins_loaded', 'cv_maybe_upgrade_db', 5 );
function cv_maybe_upgrade_db() {
    if ( get_option( 'cv_db_version' ) !== CV_DB_VERSION ) {
        cv_create_tables();
        update_option( 'cv_db_version', CV_DB_VERSION );
    }
}

// ── Criação das tabelas customizadas ─────────────────────────────
// CORRIGIDO BUG B2: todas as tabelas, incluindo as de monetização,
// são definidas em um único array $sql e passadas de uma só vez para
// dbDelta(). Na versão anterior as tabelas de monetização eram
// adicionadas ao array APÓS a chamada do dbDelta(), portanto nunca
// eram criadas.
function cv_create_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $sql     = array();

    // ── Tabelas principais ────────────────────────────────────────

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_plays_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        music_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED DEFAULT 0,
        ip_address VARCHAR(45) NOT NULL,
        played_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY music_id (music_id),
        KEY played_at (played_at)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_favorites (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        music_id BIGINT UNSIGNED NOT NULL,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_music (user_id, music_id)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_ratings (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        music_id BIGINT UNSIGNED NOT NULL,
        rating TINYINT UNSIGNED NOT NULL DEFAULT 0,
        rated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY user_music (user_id, music_id)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_playlists (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        name VARCHAR(255) NOT NULL,
        description TEXT,
        is_public TINYINT(1) DEFAULT 0,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_playlist_items (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        playlist_id BIGINT UNSIGNED NOT NULL,
        music_id BIGINT UNSIGNED NOT NULL,
        sort_order INT UNSIGNED DEFAULT 0,
        added_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY playlist_id (playlist_id)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_ranking_cache (
        music_id BIGINT UNSIGNED PRIMARY KEY,
        score DECIMAL(12,4) DEFAULT 0,
        plays_total BIGINT UNSIGNED DEFAULT 0,
        plays_24h BIGINT UNSIGNED DEFAULT 0,
        plays_7d BIGINT UNSIGNED DEFAULT 0,
        plays_30d BIGINT UNSIGNED DEFAULT 0,
        favorites BIGINT UNSIGNED DEFAULT 0,
        avg_rating DECIMAL(3,2) DEFAULT 0,
        position INT UNSIGNED DEFAULT 0,
        position_prev INT UNSIGNED DEFAULT 0,
        last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
        KEY position (position)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_subscribers (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        email VARCHAR(191) NOT NULL,
        name VARCHAR(255),
        genre VARCHAR(100),
        subscribed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY email (email)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_lyric_comments (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        music_id BIGINT UNSIGNED NOT NULL,
        user_id BIGINT UNSIGNED NOT NULL,
        excerpt VARCHAR(200) NOT NULL,
        comment TEXT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY music_id (music_id),
        KEY user_id (user_id)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_action_logs (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
        action VARCHAR(100) NOT NULL,
        object_type VARCHAR(50) DEFAULT '',
        object_id BIGINT UNSIGNED DEFAULT 0,
        description TEXT,
        ip_address VARCHAR(45) DEFAULT '',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY user_id (user_id),
        KEY action (action),
        KEY created_at (created_at)
    ) $charset;";

    // ── Tabelas de monetização ────────────────────────────────────
    // CORRIGIDO BUG B2: agora dentro do mesmo array, executadas pelo
    // mesmo dbDelta() abaixo.

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_banners (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        imagem_url TEXT NOT NULL,
        url_destino TEXT NOT NULL,
        texto_alt VARCHAR(255) DEFAULT '',
        posicao VARCHAR(50) NOT NULL DEFAULT 'meio_pagina',
        data_inicio DATE DEFAULT NULL,
        data_fim DATE DEFAULT NULL,
        cliques BIGINT UNSIGNED DEFAULT 0,
        ativo TINYINT(1) DEFAULT 1,
        KEY posicao (posicao),
        KEY ativo (ativo)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_produtos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255) NOT NULL,
        descricao TEXT,
        categoria VARCHAR(50) DEFAULT 'fisico',
        preco DECIMAL(10,2) DEFAULT 0,
        preco_antigo DECIMAL(10,2) DEFAULT 0,
        imagem_url TEXT,
        url_compra TEXT NOT NULL,
        texto_botao VARCHAR(100) DEFAULT 'Comprar agora',
        badge VARCHAR(100) DEFAULT '',
        ordem INT UNSIGNED DEFAULT 0,
        ativo TINYINT(1) DEFAULT 1,
        KEY categoria (categoria),
        KEY ativo (ativo)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_sorteios (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT,
        premio VARCHAR(255) NOT NULL,
        imagem_url TEXT,
        data_sorteio DATETIME NOT NULL,
        status VARCHAR(30) DEFAULT 'agendado',
        vencedor_email VARCHAR(191) DEFAULT '',
        vencedor_nome VARCHAR(255) DEFAULT '',
        realizado_em DATETIME DEFAULT NULL,
        total_participantes INT UNSIGNED DEFAULT 0,
        KEY status (status),
        KEY data_sorteio (data_sorteio)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_brindes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        titulo VARCHAR(255) NOT NULL,
        descricao TEXT,
        imagem_url TEXT,
        quantidade INT UNSIGNED DEFAULT 1,
        status VARCHAR(30) DEFAULT 'disponivel'
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_brindes_entregas (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        brinde_id BIGINT UNSIGNED NOT NULL,
        email VARCHAR(191) NOT NULL,
        nome VARCHAR(255) DEFAULT '',
        mensagem_admin TEXT,
        enviado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        enviado_por BIGINT UNSIGNED DEFAULT 0,
        KEY brinde_id (brinde_id),
        KEY email (email)
    ) $charset;";

    // ── v2.15.0: Sentimentos (N:N com músicas) ───────────────────
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_sentimentos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(100) NOT NULL,
        slug VARCHAR(100) NOT NULL,
        descricao TEXT,
        icone VARCHAR(10) DEFAULT '🎵',
        cor VARCHAR(7) DEFAULT '#1DB954',
        ordem INT UNSIGNED DEFAULT 0,
        UNIQUE KEY slug (slug)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_musica_sentimentos (
        musica_id BIGINT UNSIGNED NOT NULL,
        sentimento_id BIGINT UNSIGNED NOT NULL,
        PRIMARY KEY (musica_id, sentimento_id),
        KEY sentimento_id (sentimento_id)
    ) $charset;";

    // ── v2.15.0: Log da Calibração de Métricas ───────────────────
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_calibracao_log (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        admin_id BIGINT UNSIGNED NOT NULL,
        usado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        ip VARCHAR(45) NOT NULL DEFAULT '',
        musicas_afetadas INT UNSIGNED DEFAULT 0,
        detalhes LONGTEXT,
        KEY admin_id (admin_id),
        KEY usado_em (usado_em)
    ) $charset;";

    // ── Executa dbDelta() em todas as tabelas de uma vez ─────────
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    foreach ( $sql as $query ) {
        dbDelta( $query );
    }

    // ── Migração segura: colunas adicionadas em versões posteriores ──
    // ALTER TABLE com IF NOT EXISTS é suportado no MySQL 5.6+ e MariaDB 10.0+
    // Usa try/catch implícito via $wpdb->suppress_errors para compatibilidade
    $wpdb->hide_errors();

    $wpdb->query( "ALTER TABLE {$wpdb->prefix}cv_ranking_cache
        ADD COLUMN IF NOT EXISTS plays_24h BIGINT UNSIGNED DEFAULT 0,
        ADD COLUMN IF NOT EXISTS plays_30d BIGINT UNSIGNED DEFAULT 0,
        ADD COLUMN IF NOT EXISTS position_prev INT UNSIGNED DEFAULT 0" );

    $wpdb->show_errors();

    // Grava versão do banco instalada
    update_option( 'cv_db_version', CV_DB_VERSION );
}

// ── Seed de gêneros musicais ─────────────────────────────────────
// Idempotente: verifica existência antes de inserir.
// Corrige nomes com acentuação se o slug já existir com nome errado.
function cv_seed_genres() {
    $genres = array(
        'sertanejo-universitario' => 'Sertanejo Universitário',
        'sertanejo-raiz'          => 'Sertanejo Raiz',
        'sertanejo-romantico'     => 'Sertanejo Romântico',
        'modao'                   => 'Modão',
        'sertanejo-gospel'        => 'Sertanejo Gospel',
        'sertanejo-sofrencia'     => 'Sertanejo Sofrência',
    );

    foreach ( $genres as $slug => $name ) {
        $existing = get_term_by( 'slug', $slug, 'cv_genre' );
        if ( ! $existing ) {
            wp_insert_term( $name, 'cv_genre', array( 'slug' => $slug ) );
        } elseif ( $existing->name !== $name ) {
            // Corrige nome se estava errado (ex: sem acento de versão anterior)
            wp_update_term( $existing->term_id, 'cv_genre', array( 'name' => $name ) );
        }
    }
}

// ── Criação de páginas na ativação ───────────────────────────────
function cv_create_pages() {
    $pages = array(
        array( 'title' => 'Buscar Musicas',  'slug' => 'buscar-musicas',  'content' => '', 'template' => 'templates/page-search.php' ),
        array( 'title' => 'Minhas Playlists','slug' => 'minhas-playlists','content' => '', 'template' => 'templates/page-playlists.php' ),
        array( 'title' => 'Minha Area',      'slug' => 'minha-area',      'content' => '', 'template' => 'templates/page-user-dashboard.php' ),
        array( 'title' => 'Ranking',         'slug' => 'ranking',         'content' => '', 'template' => 'templates/page-ranking.php' ),
        array( 'title' => 'Contato',         'slug' => 'contato',         'content' => '', 'template' => 'templates/page-contact.php' ),
        array( 'title' => 'Artista',         'slug' => 'artista',         'content' => '', 'template' => 'templates/page-artist.php' ),
        array( 'title' => 'Login',           'slug' => 'login',           'content' => '[cv_login_form]',    'template' => '' ),
        array( 'title' => 'Cadastro',        'slug' => 'cadastro',        'content' => '[cv_register_form]', 'template' => '' ),
        array( 'title' => 'Meu Perfil',      'slug' => 'meu-perfil',      'content' => '[cv_profile_form]',  'template' => '' ),
        array( 'title' => 'Notificacoes',    'slug' => 'notificacoes',    'content' => '',                   'template' => 'templates/page-notifications.php' ),
    );

    foreach ( $pages as $page ) {
        $exists = get_page_by_path( $page['slug'] );
        if ( ! $exists ) {
            // Cria a página se não existe
            $id = wp_insert_post( array(
                'post_title'   => $page['title'],
                'post_name'    => $page['slug'],
                'post_content' => $page['content'],
                'post_status'  => 'publish',
                'post_type'    => 'page',
            ) );
            if ( $id && ! is_wp_error( $id ) && $page['template'] ) {
                update_post_meta( $id, '_wp_page_template', $page['template'] );
            }
        } else {
            // Preserve existing content, publication state and editorial template.
        }
    }
}

// Recria paginas via AJAX - para quem ja tinha o plugin instalado
// Disponivel em: Cancao Verdadeira > Dashboard > botao "Recriar Paginas"
add_action( 'wp_ajax_cv_recreate_pages', 'cv_ajax_recreate_pages' );
// Salva grupos de email
add_action( 'wp_ajax_cv_email_save_groups', 'cv_ajax_email_save_groups' );
function cv_ajax_email_save_groups() {
    check_ajax_referer( 'cv_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array('message'=>'Sem permissao.') ); }
    foreach ( CV_Email::TIPOS as $key => $cfg ) {
        $val = sanitize_text_field( $_POST[ $cfg['option'] ] ?? '' );
        update_option( $cfg['option'], $val );
    }
    wp_send_json_success( array('message'=>'Grupos salvos!') );
}

// Toggle de automacao de email
add_action( 'wp_ajax_cv_email_toggle', 'cv_ajax_email_toggle' );
function cv_ajax_email_toggle() {
    check_ajax_referer( 'cv_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error(); }
    $option = sanitize_key( $_POST['option'] ?? '' );
    $valor  = absint( $_POST['valor'] ?? 0 );
    $allowed = array('cv_email_ativo_nova_musica','cv_email_ativo_favoritos');
    if ( ! in_array($option, $allowed, true) ) { wp_send_json_error(); }
    update_option( $option, $valor );
    wp_send_json_success( array('message'=>$valor ? 'Ativado!' : 'Desativado!') );
}

function cv_ajax_recreate_pages() {
    check_ajax_referer( 'cv_admin_nonce', 'nonce' );
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'message' => 'Sem permissao.' ) );
    }
    cv_create_pages();
    flush_rewrite_rules();
    wp_send_json_success( array( 'message' => 'Paginas criadas/verificadas com sucesso!' ) );
}

// ── WP-Cron: agendamento do recálculo de ranking ─────────────────
// Legacy entry point retained; only the canonical schedule is maintained.
function cv_schedule_cron() {
    cv_schedule_crons();
}
add_action( 'cv_hourly_ranking', array( 'CV_Advanced', 'recalculate_periods' ), 20 );

// ── WP-Cron: sorteios automáticos ────────────────────────────────
add_action( 'wp', 'cv_schedule_sorteios_cron' );
function cv_schedule_sorteios_cron() {
    if ( ! wp_next_scheduled( 'cv_cron_sorteios' ) ) {
        wp_schedule_event( time(), 'daily', 'cv_cron_sorteios' );
    }
}
add_action( 'cv_cron_sorteios', array( 'CV_Monetization', 'processar_sorteios' ) );
add_action( 'cv_cron_favoritos_digest', array( 'CV_Email', 'trigger_favoritos_digest' ) );

// ── Roles customizados: garante criação mesmo em updates ─────────
add_action( 'init', 'cv_maybe_register_roles', 1 );
function cv_maybe_register_roles() {
    if ( ! get_role( 'cv_editor' ) || ! get_role( 'cv_gerente' ) || ! get_role( 'cv_master' ) ) {
        if ( class_exists( 'CV_Advanced' ) ) {
            CV_Advanced::register_roles();
        }
    }
}

// ── Limpa cache de recomendações ao registrar play ou favoritar ──
add_action( 'cv_play_registered', function() {
    $user_id = get_current_user_id();
    if ( $user_id && class_exists( 'CV_Advanced' ) ) {
        CV_Advanced::clear_user_recommendations( $user_id );
    }
}, 30 );

add_action( 'cv_favorite_changed', function() {
    $user_id = get_current_user_id();
    if ( $user_id && class_exists( 'CV_Advanced' ) ) {
        CV_Advanced::clear_user_recommendations( $user_id );
    }
}, 30 );

// ── REST API: rotas públicas ──────────────────────────────────────
add_action( 'rest_api_init', 'cv_register_rest_routes' );

function cv_register_rest_routes() {
    $routes = array(
        array( 'cv/v1', '/ranking/top',    'cv_rest_ranking_top' ),
        array( 'cv/v1', '/ranking/recent', 'cv_rest_ranking_recent' ),
        array( 'cv/v1', '/ranking/best',   'cv_rest_ranking_best' ),
        array( 'cv/v1', '/musicas',        'cv_rest_musicas' ),
        array( 'cv/v1', '/status',         'cv_rest_status' ),
    );

    foreach ( $routes as $r ) {
        register_rest_route( $r[0], $r[1], array(
            'methods'             => 'GET',
            'callback'            => $r[2],
            'permission_callback' => '__return_true',
        ) );
    }
}

function cv_rest_ranking_top()    { return CV_Ranking::get_top( 10 ); }
function cv_rest_ranking_recent() { return CV_Ranking::get_recent( 10 ); }
function cv_rest_ranking_best()   { return CV_Ranking::get_best( 10 ); }

function cv_rest_musicas( $request ) {
    $genre = sanitize_text_field( $request->get_param( 'genre' ) );
    $limit = absint( $request->get_param( 'limit' ) ) ?: 12;

    $args = array(
        'post_type'      => 'musica',
        'post_status'    => 'publish',
        'posts_per_page' => min( $limit, 50 ), // máximo 50 por request
    );

    if ( $genre ) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'cv_genre',
                'field'    => 'slug',
                'terms'    => sanitize_key( $genre ),
            ),
        );
    }

    $query  = new WP_Query( $args );
    $result = array();

    foreach ( $query->posts as $post ) {
        $result[] = array(
            'id'    => $post->ID,
            'title' => $post->post_title,
            'slug'  => $post->post_name,
            'url'   => get_permalink( $post->ID ),
            'cover' => get_the_post_thumbnail_url( $post->ID, 'cv-cover' )
                       ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
            'plays' => (int) get_post_meta( $post->ID, '_cv_plays_total', true ),
        );
    }

    return $result;
}

function cv_rest_status() {
    return array(
        'status'     => 'ok',
        'version'    => CV_VERSION,
        'db_version' => CV_DB_VERSION,
        'time'       => current_time( 'mysql' ),
    );
}
