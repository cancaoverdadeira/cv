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
// v2.25.0 (23/09/2026) — Blog: categoria "Blog" com URLs /blog/ e o mesmo SEO
// automático das músicas (CV_Blog); ajustes no Open Graph e schema da música.
// v2.25.1 — CV_Ranking::get_most_favorited() para a seção "Mais Favoritadas".
// v2.26.0 — Gêneros removidos do site inteiro (o site é todo sertanejo): sem
// taxonomias cv_genre/cv_subcategory, sem campo, filtros, abas ou gráficos.
// v2.27.0 — Busca unificada no Relevanssi com os pesos da especificação
// (CV_Search): página de busca, 404, autocomplete e REST usam a mesma consulta.
// v2.27.1 — cv-public.js e cv-theme.js voltaram a carregar (reservas vazias
// registradas na ordem errada bloqueavam os dois); ?ver= mantido nos nossos arquivos.
// v2.28.0 — Publicidade: sem topo e sem rotação; bloco depois da letra; clique
// de banner sem redirecionamento aberto.
// v2.29.0 — Distribuição (CV_Distribuicao): checklist, status, ficha para a
// distribuidora e links "Ouça também em" na página da música.
// v2.30.0 — Refatoração fase 1–3: calibração e cv-limpeza apagados; eventos
// cv_play_registered/cv_favorite_changed/cv_music_rated/cv_lyric_commented
// disparados antes da resposta (conquistas e cache de recomendações funcionam).
// v2.31.0 — Refatoração fase 4: CV_Fields::youtube_id/youtube_match/cover_url
// (regra única, aceita /shorts/ e /live/); 167 nomes de meta viraram constantes;
// corrigidos metas inexistentes (_cv_plays, _cv_capa_url, _cv_sentimentos).
// v2.32.0 — Refatoração fase 5: estilos públicos em cv-public-componentes.css;
// Chart.js 1× no <head>, só nas 6 telas com gráfico.
// v2.33.0 — Refatoração fase 6: CSS e JS da Publicação Acelerada em arquivos próprios.
// v2.34.0 — Refatoração fase 6: CSS e JS do painel de Sentimentos em arquivos próprios.
// v2.35.0 — Refatoração fase 6: CSS e JS do Dashboard em arquivos próprios.
// v2.36.0 — Refatoração fase 6: telas de Banners, Loja, Sorteios e Brindes em views/ + JS próprio.
// v2.37.0 — Refatoração fase 6: CV_Advanced dividido em 5 traits (includes/admin/advanced/).
// v2.38.0 — Refatoração fase 6: CV_Monetization dividido em 4 traits (includes/monetization/partes/).
// v2.39.0 (24/09/2026) — Exclusão completa de música (CV_Exclusao): ao apagar de vez,
// limpa as tabelas cv_*, logs antigos, a capa importada e os caches. Importação do
// YouTube não duplica mais músicas em rascunho/lixeira (compara o ID do vídeo).
// v2.40.0 (24/09/2026) — Correções do 1º teste (fase 1, site público): playlist toca
// (fila com YouTube ou MP3, botão "▶ Tocar" na Minha Área e no perfil; playlist
// privada só para o dono); Ultimate Member em português (CV_UM_Traducao); acentos
// das conquistas; newsletter sempre com cópia local em cv_subscribers.
// v2.41.0 (24/09/2026) — Correções do 1º teste (fase 2, painel): "← Dashboard" em
// todas as telas; saíram a Publicação Acelerada e o botão "Recriar Páginas";
// Sentimentos numa tela só (abas Painel | Gerenciar, "Editar" volta a funcionar);
// Loja/Sorteios/Brindes com formulário aberto quando vazios; Banco de Dados com
// a lista real de tabelas.
// v2.42.0 (24/09/2026) — Tela "🗂 Gerenciar músicas" (CV_Page_Musicas): todos os
// dados de cada música, excluir uma a uma e reimportar do YouTube na mesma tela.
// v2.43.0 (24/09/2026) — Grupo "📰 Blog" no Dashboard (novo post, posts, rascunhos,
// ver no site) e "← Dashboard" também nas telas de post.
// v2.44.0 (24/09/2026) — Ícones oficiais das redes (CV_Icones, Simple Icons CC0) nos
// links e botões de compartilhar; avisos de nova música e sorteio viram CAMPANHAS do
// MailerLite (rascunho por padrão e sempre no site local; ou envio na hora).
// v2.45.0 (25/09/2026) — Correções do 2º teste: editor "estilo Word" nos posts do
// blog (editor clássico) e na letra (CV_Editor_Rico); caixa "🔎 SEO e Tags" de volta
// na música (palavra-chave e título do Rank Math, tags, prévia do Google); Gravatar
// ligado no Ultimate Member (a foto do perfil vem do e-mail, como a tela já dizia).
// v2.46.0 (25/09/2026) — Tela do post organizada (Imagem destacada logo abaixo de
// Publicar, caixas sem uso fora); exclusão de usuário passa playlists, favoritos,
// notas e plays para quem herda o conteúdo (CV_Usuario_Exclusao).
// v2.47.0 (25/09/2026) — Estoque e Pedidos (includes/estoque/): itens E-book, Caneca
// e Camiseta (estoque por tamanho), entradas/saídas/contagem com histórico, pedidos
// pela Minha Área (baixam o estoque só quando o admin confirma), alerta por e-mail
// no estoque mínimo (padrão 10) e Painel com indicadores e análises. Banco v9.
// v2.48.0 (25/09/2026) — PIX (includes/apoio/): conta PIX no painel (e-mail, telefone
// ou aleatória) e gerador do código copia e cola (BR Code + CRC16) com QR Code; pedidos
// só por PIX; rodapé com "Seja nosso parceiro" (propostas em cv_parcerias) e "Seja nosso
// colaborador" (doação por PIX). Tela "💠 PIX e Parcerias". Banco v10.
// v2.49.0 (25/09/2026) — Página "Loja" (/loja/) com a vitrine do estoque e "Quero este"
// indo para a Minha Área com o item escolhido. "Seja nosso parceiro": nome artístico,
// cidade e música; depois do envio, "Nossas propostas" e "Modelo de contrato" (só
// divulgação, mediante taxa; a música continua do artista) na tela e em Word
// (CV_Docx, CV_Parceria_Docs). Aba "📄 Propostas e contrato" no painel. Banco v11.
// v2.50.0 (25/09/2026) — Envio de música do parceiro, na Minha Área, em 4 etapas:
// contrato em PDF com ASSINATURA DIGITAL obrigatória (CV_Assinatura_PDF confere
// quem assinou e se não foi alterado), PIX da taxa (CVDIV<nº>), link do YouTube e
// dados da música (com prompt de ajuda em Word). O admin aprova e gera a música em
// rascunho (aba "🎤 Envios de música"). Arquivos em cv-privado/ fora da pasta pública. Banco v12.
// v2.51.0 (25/09/2026) — Caixa "Apoie" (Seja nosso parceiro / colaborador) também na
// Minha Área; prompt de ajuda com os subitens do "b)" iguais ao original em Word.
// v2.52.0 (26/09/2026) — 3º teste: "🔁 Transferir autoria" na tela Usuários
// (CV_Transferir_Autoria); playlist pública/privada (cv_playlist_visibilidade);
// CV_Ranking::get_most_played/get_best_rated (seções novas da home); "← Dashboard"
// também em Categorias, Tags, Usuários e Mídia; "🎤 Enviar minha música" na caixa Apoie.
// v2.53.0 (26/09/2026) — Prioridade 0 (público 55+): campo "📖 Por trás da canção"
// (CV_Fields::HISTORIA) no cadastro da música, mostrado abaixo da letra.
// v2.62.0 (26/09/2026) — a ativação não apaga mais as regras de endereço /musica/ (cv_refazer_regras, com
// auto-conserto); ALTER do cv_ranking_cache compatível com MySQL 8; exportações de plays e ranking corrigidas.

/**
 * Plugin Name: Cancao Verdadeira
 * Plugin URI:  https://cancaoverdadeira.com.br
 * Description: Plataforma de letras musicais sertanejas - player, ranking dinâmico, trending ao vivo, recomendação automática, conquistas e shortcodes para Elementor.
 * Version:     2.62.0
 * Author:      Cancao Verdadeira
 * Text Domain: cancao-verdadeira
 * Requires at least: 6.0
 * Requires PHP:      7.2
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CV_VERSION',        '2.62.0' );
define( 'CV_DB_VERSION',     '12' );      // v2.50.0: cv_envios (v2.49.0: colunas novas em cv_parcerias)
define( 'CV_PLUGIN_DIR',     plugin_dir_path( __FILE__ ) );
define( 'CV_PLUGIN_URL',     plugin_dir_url( __FILE__ ) );
define( 'CV_PLUGIN_FILE',    __FILE__ );
define( 'CV_PLAY_SECONDS',   30 );

// ── Carrega todos os módulos do plugin ───────────────────────────
$cv_includes = array(
    // CPT, taxonomias e metaboxes
    'includes/cpt/class-cv-fields.php',
    'includes/public/class-cv-icones.php',     // v2.44.0: ícones oficiais das redes (Simple Icons, CC0)
    'includes/public/class-cv-launch.php',     // modo lançamento (contadores mínimos e seleção da casa)        // nomes centrais dos campos da música
    'includes/cpt/class-cv-cpt.php',
    'includes/cpt/class-cv-sem-generos.php',   // v2.26.0: gêneros removidos; redireciona /genero/ e /estilo/
    'includes/cpt/class-cv-editor-rico.php',   // v2.45.0: editor "estilo Word" no blog e na letra
    'includes/cpt/class-cv-metaboxes.php',
    'includes/cpt/class-cv-exclusao.php',      // v2.39.0: exclusão completa da música (tabelas cv_*, capa, caches)
    'includes/cpt/class-cv-blog.php',          // v2.25.0: categoria Blog, URLs /blog/ e SEO automático dos posts
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
    'includes/user/class-cv-usuario-exclusao.php', // v2.46.0: dados cv_* passam para quem herda o conteúdo
    'includes/user/class-cv-transferir-autoria.php', // v2.52.0: "Transferir autoria" (tela Usuários)
    'includes/user/class-cv-um-traducao.php',    // v2.40.0: textos do Ultimate Member em português
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
    'includes/admin/pages/class-cv-page-musicas.php',   // v2.42.0: Gerenciar músicas (dados, excluir, reimportar)
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
    'includes/public/class-cv-depoimentos.php', // v2.55.0: depoimentos dos ouvintes (aprovados no painel)
    // Social e e-mails
    'includes/admin/class-cv-social.php',
    'includes/admin/class-cv-email.php',
    // Monetização
    'includes/monetization/class-cv-monetization.php',
    'includes/monetization/class-cv-monetization-pages.php',
    // v2.47.0: Estoque e Pedidos (E-book, Caneca, Camiseta; pedidos na Minha Área)
    // v2.48.0: PIX (gerador do código copia e cola) e "Apoie" do rodapé
    'includes/apoio/class-cv-pix.php',
    'includes/apoio/class-cv-apoio.php',
    'includes/apoio/class-cv-docx.php',          // v2.49.0: gera .docx (Word) sem biblioteca externa
    'includes/apoio/class-cv-parceria-docs.php', // v2.49.0: propostas e contrato do parceiro
    'includes/apoio/class-cv-assinatura-pdf.php',// v2.50.0: confere a assinatura digital do PDF
    'includes/apoio/class-cv-envio.php',         // v2.50.0: envio de música do parceiro (4 etapas)
    'includes/apoio/class-cv-envio-prompt.php',  // v2.50.0: prompt de ajuda do YouTube (Word)
    'includes/apoio/class-cv-envio-area.php',    // v2.50.0: aba "Enviar música" da Minha Área
    'includes/apoio/class-cv-envio-admin.php',   // v2.50.0: aprovação e geração da música em rascunho
    'includes/estoque/class-cv-estoque.php',
    'includes/estoque/class-cv-estoque-ligacao.php', // v2.60.0: brindes e sorteios tiram peça do estoque
    'includes/estoque/class-cv-estoque-analise.php',
    'includes/estoque/class-cv-estoque-area.php',
    'includes/estoque/class-cv-estoque-admin.php',
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
    'includes/admin/class-cv-distribuicao.php',  // v2.29.0: preparo e acompanhamento do envio às plataformas
    // Segurança Avançada — painel executivo (v2.24.0)
    'includes/admin/class-cv-seguranca.php',
    // Calibração de Métricas desativada em 23/09/2026 (gerava métricas
    // fictícias); substituída por includes/public/class-cv-launch.php.
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
    // v2.62.0: NÃO refazer as regras aqui. Na ativação o tipo "musica" ainda não
    // foi registrado, e o flush apagava as regras /musica/… (26/09: o endereço da
    // música virou "página da imagem" e o Rank Math redirecionou em círculo → 502).
    // Só marca; cv_refazer_regras() refaz no próximo carregamento.
    update_option( 'cv_refazer_regras', 1, false );
}

/**
 * v2.62.0: refaz as regras de endereço DEPOIS que o tipo "musica" foi
 * registrado (init, prioridade 999): quando a ativação pediu, ou quando as
 * regras das músicas sumiram (auto-conserto, no máximo 1 vez a cada 10 min).
 */
function cv_refazer_regras() {
    if ( ! post_type_exists( 'musica' ) || '' === (string) get_option( 'permalink_structure' ) ) { return; }
    $pedido = (bool) get_option( 'cv_refazer_regras' );
    if ( ! $pedido ) {
        $regras = get_option( 'rewrite_rules' );
        if ( ! is_array( $regras ) || empty( $regras ) ) { return; } // o WordPress refaz sozinho
        foreach ( $regras as $destino ) { if ( false !== strpos( $destino, 'musica=' ) ) { return; } }
        if ( get_transient( 'cv_regras_consertadas' ) ) { return; }
        set_transient( 'cv_regras_consertadas', 1, 10 * MINUTE_IN_SECONDS );
    }
    delete_option( 'cv_refazer_regras' );
    flush_rewrite_rules( false );
}
add_action( 'init', 'cv_refazer_regras', 999 );

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

    // v2.47.0 (CV_DB_VERSION 9): Estoque e Pedidos (includes/estoque/).
    // Itens (E-book, Caneca, Camiseta), variações (tamanhos da camiseta; ''
    // para os demais), movimentos de estoque e pedidos feitos na Minha Área.
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_estoque_itens (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(191) NOT NULL,
        tipo VARCHAR(20) NOT NULL DEFAULT 'caneca',
        descricao TEXT,
        preco DECIMAL(10,2) DEFAULT 0,
        imagem_url TEXT,
        controla_estoque TINYINT(1) DEFAULT 1,
        estoque_minimo INT UNSIGNED DEFAULT 10,
        ativo TINYINT(1) DEFAULT 1,
        ordem INT UNSIGNED DEFAULT 0,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY tipo (tipo),
        KEY ativo (ativo)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_estoque_variacoes (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        item_id BIGINT UNSIGNED NOT NULL,
        variacao VARCHAR(20) NOT NULL DEFAULT '',
        estoque_atual INT NOT NULL DEFAULT 0,
        alerta_enviado TINYINT(1) DEFAULT 0,
        ativo TINYINT(1) DEFAULT 1,
        UNIQUE KEY item_var (item_id, variacao)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_estoque_mov (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        item_id BIGINT UNSIGNED NOT NULL,
        variacao_id BIGINT UNSIGNED NOT NULL,
        tipo VARCHAR(20) NOT NULL,
        quantidade INT NOT NULL,
        saldo_apos INT NOT NULL DEFAULT 0,
        custo_unit DECIMAL(10,2) DEFAULT 0,
        motivo VARCHAR(30) DEFAULT '',
        pedido_id BIGINT UNSIGNED DEFAULT 0,
        obs VARCHAR(255) DEFAULT '',
        user_id BIGINT UNSIGNED DEFAULT 0,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY item_id (item_id),
        KEY variacao_id (variacao_id),
        KEY tipo (tipo),
        KEY criado_em (criado_em)
    ) $charset;";

    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_pedidos (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        item_id BIGINT UNSIGNED NOT NULL,
        variacao_id BIGINT UNSIGNED NOT NULL,
        quantidade INT UNSIGNED DEFAULT 1,
        preco_unit DECIMAL(10,2) DEFAULT 0,
        status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        obs VARCHAR(255) DEFAULT '',
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NULL,
        KEY user_id (user_id),
        KEY item_id (item_id),
        KEY status (status),
        KEY criado_em (criado_em)
    ) $charset;";

    // v2.48.0 (CV_DB_VERSION 10): propostas do "Seja nosso parceiro" (rodapé).
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_parcerias (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL,
        email VARCHAR(191) NOT NULL,
        telefone VARCHAR(30) DEFAULT '',
        nome_artistico VARCHAR(120) DEFAULT '',
        cidade_uf VARCHAR(80) DEFAULT '',
        musica VARCHAR(191) DEFAULT '',
        token VARCHAR(40) DEFAULT '',
        tipo VARCHAR(30) NOT NULL DEFAULT 'outro',
        link VARCHAR(255) DEFAULT '',
        mensagem TEXT,
        status VARCHAR(20) NOT NULL DEFAULT 'nova',
        consentimento_em DATETIME NULL,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY status (status),
        KEY criado_em (criado_em)
    ) $charset;";

    // v2.50.0 (CV_DB_VERSION 12): envio de música do parceiro (4 etapas na Minha Área).
    $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}cv_envios (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id BIGINT UNSIGNED NOT NULL,
        parceria_id BIGINT UNSIGNED DEFAULT 0,
        etapa TINYINT UNSIGNED NOT NULL DEFAULT 1,
        contrato_arquivo VARCHAR(255) DEFAULT '',
        contrato_info TEXT,
        contrato_status VARCHAR(20) NOT NULL DEFAULT 'pendente',
        pagamento_status VARCHAR(20) NOT NULL DEFAULT 'aguardando',
        comprovante_arquivo VARCHAR(255) DEFAULT '',
        valor DECIMAL(10,2) DEFAULT 0,
        youtube_url VARCHAR(255) DEFAULT '',
        youtube_titulo VARCHAR(191) DEFAULT '',
        titulo VARCHAR(191) DEFAULT '',
        artista VARCHAR(120) DEFAULT '',
        compositor VARCHAR(191) DEFAULT '',
        descricao TEXT,
        letra LONGTEXT,
        tags VARCHAR(255) DEFAULT '',
        status VARCHAR(20) NOT NULL DEFAULT 'rascunho',
        musica_id BIGINT UNSIGNED DEFAULT 0,
        obs_admin TEXT,
        criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME NULL,
        KEY user_id (user_id),
        KEY status (status)
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

    // v2.62.0: o MySQL 8 não aceita "ADD COLUMN IF NOT EXISTS"; confere uma a uma.
    $rk = $wpdb->prefix . 'cv_ranking_cache';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $rk ) ) === $rk ) {
        $cols_rk = $wpdb->get_col( "SHOW COLUMNS FROM {$rk}" );
        foreach ( array( 'plays_24h' => 'BIGINT UNSIGNED DEFAULT 0', 'plays_30d' => 'BIGINT UNSIGNED DEFAULT 0', 'position_prev' => 'INT UNSIGNED DEFAULT 0' ) as $col => $def ) {
            if ( ! in_array( $col, $cols_rk, true ) ) { $wpdb->query( "ALTER TABLE {$rk} ADD COLUMN {$col} {$def}" ); }
        }
    }

    // v2.49.0: colunas novas de cv_parcerias. MySQL 8 não aceita
    // "ADD COLUMN IF NOT EXISTS", então confere uma a uma antes de criar.
    $parc = $wpdb->prefix . 'cv_parcerias';
    if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $parc ) ) === $parc ) {
        $cols = $wpdb->get_col( "SHOW COLUMNS FROM {$parc}" );
        $novas = array(
            'nome_artistico' => "VARCHAR(120) DEFAULT '' AFTER telefone",
            'cidade_uf'      => "VARCHAR(80) DEFAULT '' AFTER nome_artistico",
            'musica'         => "VARCHAR(191) DEFAULT '' AFTER cidade_uf",
            'token'          => "VARCHAR(40) DEFAULT '' AFTER musica",
        );
        foreach ( $novas as $col => $def ) {
            if ( ! in_array( $col, $cols, true ) ) { $wpdb->query( "ALTER TABLE {$parc} ADD COLUMN {$col} {$def}" ); }
        }
    }

    $wpdb->show_errors();

    // Grava versão do banco instalada
    update_option( 'cv_db_version', CV_DB_VERSION );
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

// v2.41.0: o botão "Recriar Páginas" e o AJAX cv_recreate_pages foram removidos
// (rotina de alto risco, suspeita nos incidentes de 29/07 e da v2.9.2). A criação
// de páginas faltantes continua só na ativação do plugin (cv_activate).
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
    $limit = absint( $request->get_param( 'limit' ) ) ?: 12;

    $args = array(
        'post_type'      => 'musica',
        'post_status'    => 'publish',
        'posts_per_page' => min( $limit, 50 ), // máximo 50 por request
    );

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
            'plays' => (int) get_post_meta( $post->ID, CV_Fields::PLAYS_TOTAL, true ),
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
