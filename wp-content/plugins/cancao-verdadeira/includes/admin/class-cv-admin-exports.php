<?php
// cancao-verdadeira/includes/admin/class-cv-admin-exports.php
// 2026-06-27 11:00
// Módulo de Exportações do Plugin Canção Verdadeira — v1.1
// Gera downloads CSV e TXT diretamente via PHP headers (sem salvar no servidor).
// Exporta: músicas, plays por período, ranking atual, assinantes newsletter.
// CORRIGIDO v1.1: removido register_submenu() deste arquivo — o menu é
// registrado exclusivamente em class-cv-admin-menu.php para evitar duplicatas.
// Compatível com PHP 7.2+ — sem arrow functions nem typed returns.
// v2.26.0: removidos o filtro e as colunas Gênero/Subcategoria (site todo
// sertanejo); coluna "Plays Total" passou a ler _cv_plays_total (lia _cv_plays,
// que não existe, e saía sempre 0).
// v2.62.0: exportações de plays (coluna ip_address) e de ranking (variação calculada de position_prev) voltaram a funcionar.

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CV_Admin_Exports {

    private static $instance = null;

    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Submenu registrado APENAS em class-cv-admin-menu.php
        add_action( 'admin_init',            array( $this, 'handle_export_request' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    // ─────────────────────────────────────────────
    // ASSETS
    // ─────────────────────────────────────────────

    public function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'cv-exports' ) === false ) {
            return;
        }
        $css = '
        body.toplevel_page_cancao-verdadeira #wpcontent,
        body #wpcontent { background: #FBF6EE !important; }

        .cv-exports-wrap {
            max-width: 980px;
            margin: 28px auto;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
        }
        .cv-exp-hero {
            background: linear-gradient(135deg, #FFFFFF 0%, #F8F4E7 100%);
            border: 1px solid #E0D7AE;
            border-radius: 14px;
            padding: 28px 32px;
            margin-bottom: 28px;
            display: flex;
            align-items: center;
            gap: 20px;
        }
        .cv-exp-hero-icon {
            font-size: 48px;
            line-height: 1;
            filter: drop-shadow(0 0 12px rgba(242,165,26,0.52));
        }
        .cv-exp-hero h1 {
            color: #7B3A22;
            font-size: 22px;
            margin: 0 0 4px 0;
            font-weight: 700;
            letter-spacing: -0.3px;
        }
        .cv-exp-hero p {
            color: #8A6A55;
            font-size: 13px;
            margin: 0;
        }
        .cv-exp-stats {
            display: flex;
            gap: 12px;
            margin-left: auto;
        }
        .cv-exp-stat {
            background: rgba(242,165,26,0.1);
            border: 1px solid rgba(201,162,126,0.4);
            border-radius: 10px;
            padding: 12px 18px;
            text-align: center;
            min-width: 80px;
        }
        .cv-exp-stat strong {
            display: block;
            font-size: 22px;
            font-weight: 800;
            color: #7B3A22;
            line-height: 1.1;
        }
        .cv-exp-stat span {
            font-size: 10px;
            color: #8A6A55;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Grid de cards */
        .cv-exp-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        @media (max-width: 800px) { .cv-exp-grid { grid-template-columns: 1fr; } }

        .cv-export-card {
            background: #FFFFFF;
            border: 1px solid #EADBC6;
            border-radius: 12px;
            padding: 24px;
            transition: border-color 0.2s, box-shadow 0.2s;
            position: relative;
            overflow: hidden;
        }
        .cv-export-card::before {
            content: "";
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 3px;
            border-radius: 12px 12px 0 0;
        }
        .cv-export-card.card-musicas::before  { background: linear-gradient(90deg, #F2A51A, #F2A51A); }
        .cv-export-card.card-plays::before    { background: linear-gradient(90deg, #1db954, #17a845); }
        .cv-export-card.card-ranking::before  { background: linear-gradient(90deg, #F2A51A, #c07010); }
        .cv-export-card.card-assin::before    { background: linear-gradient(90deg, #4a90d9, #2070b0); }

        .cv-export-card:hover {
            border-color: #EADBC6;
            box-shadow: 0 4px 24px rgba(123,58,34,0.12);
        }
        .cv-export-card h2 {
            font-size: 15px;
            font-weight: 700;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: #3B2418;
        }
        .cv-export-card h2 .icon { font-size: 20px; }
        .cv-export-card p.cv-desc {
            color: #8A6A55;
            font-size: 12px;
            margin: 0 0 20px 0;
            line-height: 1.5;
        }
        .cv-export-form {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            align-items: flex-end;
        }
        .cv-export-field {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        .cv-export-field label {
            color: #8A6A55;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }
        .cv-export-field select,
        .cv-export-field input[type="date"] {
            background: #FBF6EE;
            border: 1px solid #EADBC6;
            color: #3B2418;
            border-radius: 6px;
            padding: 7px 10px;
            font-size: 12px;
            min-width: 130px;
            outline: none;
            transition: border-color 0.2s;
        }
        .cv-export-field select:focus,
        .cv-export-field input[type="date"]:focus {
            border-color: #C9A27E;
        }
        .cv-btn-export {
            font-weight: 700;
            border: none;
            border-radius: 6px;
            padding: 8px 18px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.2s;
            white-space: nowrap;
            letter-spacing: 0.2px;
        }
        .card-musicas  .cv-btn-export { background: #F2A51A; color: #3B2418; }
        .card-musicas  .cv-btn-export:hover { background: #F2A51A; }
        .card-plays    .cv-btn-export { background: #1db954; color: #3B2418; }
        .card-plays    .cv-btn-export:hover { background: #22d460; }
        .card-ranking  .cv-btn-export { background: #F2A51A; color: #3B2418; }
        .card-ranking  .cv-btn-export:hover { background: #F2A51A; }
        .card-assin    .cv-btn-export { background: #4a90d9; color: #3B2418; }
        .card-assin    .cv-btn-export:hover { background: #5aa0e9; }

        .cv-export-badge {
            background: rgba(242,165,26,0.16);
            color: #7B3A22;
            font-size: 10px;
            border-radius: 20px;
            padding: 2px 8px;
            font-weight: 600;
        }
        .cv-exp-columns {
            font-size: 11px;
            color: #8A6A55;
            margin-top: 12px;
            padding-top: 12px;
            border-top: 1px solid #EADBC6;
            line-height: 1.6;
        }
        .cv-exp-columns strong { color: #8A6A55; }
        ';
        wp_add_inline_style( 'wp-admin', $css );
    }

    // ─────────────────────────────────────────────
    // PÁGINA PRINCIPAL
    // ─────────────────────────────────────────────

    public function render_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Acesso negado.' );
        }

        global $wpdb;
        $total_musicas   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type='musica' AND post_status='publish'" );
        $table_news      = $wpdb->prefix . 'cv_newsletter';
        $total_assin     = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_news}'" ) === $table_news ) {
            $total_assin = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_news}" );
        }
        $table_plays     = $wpdb->prefix . 'cv_plays_log';
        $total_plays     = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_plays}'" ) === $table_plays ) {
            $total_plays = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_plays}" );
        }
        $table_ranking   = $wpdb->prefix . 'cv_ranking_cache';
        $total_ranking   = 0;
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_ranking}'" ) === $table_ranking ) {
            $total_ranking = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_ranking}" );
        }
        ?>
        <div class="wrap cv-exports-wrap">
        <?php echo CV_Admin::btn_voltar(); ?>
        

            <!-- Hero -->
            <div class="cv-exp-hero">
                <div class="cv-exp-hero-icon">📤</div>
                <div>
                    <h1>Central de Exportações</h1>
                    <p>Baixe os dados da plataforma em CSV ou TXT para análise, relatórios ou importação externa.</p>
                </div>
                <div class="cv-exp-stats">
                    <div class="cv-exp-stat">
                        <strong><?php echo number_format( $total_musicas ); ?></strong>
                        <span>Músicas</span>
                    </div>
                    <div class="cv-exp-stat">
                        <strong><?php echo number_format( $total_plays ); ?></strong>
                        <span>Plays</span>
                    </div>
                    <div class="cv-exp-stat">
                        <strong><?php echo number_format( $total_assin ); ?></strong>
                        <span>Assinantes</span>
                    </div>
                    <div class="cv-exp-stat">
                        <strong><?php echo number_format( $total_ranking ); ?></strong>
                        <span>No Ranking</span>
                    </div>
                </div>
            </div>

            <!-- Grid de cards -->
            <div class="cv-exp-grid">
                <?php $this->render_card_musicas( $total_musicas ); ?>
                <?php $this->render_card_plays(); ?>
                <?php $this->render_card_ranking( $total_ranking ); ?>
                <?php $this->render_card_assinantes( $total_assin ); ?>
            </div>

        </div>
        <?php
    }

    // ─────────────────────────────────────────────
    // CARDS
    // ─────────────────────────────────────────────

    private function render_card_musicas( $total ) {
        ?>
        <div class="cv-export-card card-musicas">
            <h2>
                <span class="icon">🎵</span>
                Catálogo de Músicas
                <span class="cv-export-badge"><?php echo number_format( $total ); ?> músicas</span>
            </h2>
            <p class="cv-desc">Título, artista, compositor, álbum, ano, plays, favoritos, avaliação e status de cada música.</p>
            <form method="post" class="cv-export-form">
                <?php wp_nonce_field( 'cv_export_nonce', 'cv_nonce' ); ?>
                <input type="hidden" name="cv_export_type" value="musicas">
                <div class="cv-export-field">
                    <label>Status</label>
                    <select name="cv_status">
                        <option value="">Todos</option>
                        <option value="1">Somente Ativas</option>
                        <option value="0">Somente Inativas</option>
                    </select>
                </div>
                <button type="submit" class="cv-btn-export">⬇ Baixar CSV</button>
            </form>
            <div class="cv-exp-columns">
                <strong>Colunas:</strong> ID · Título · Artista · Compositor · Álbum · Ano · Plays · Favoritos · Avaliação · Status · YouTube · MP3 · Data
            </div>
        </div>
        <?php
    }

    private function render_card_plays() {
        $default_start = date( 'Y-m-d', strtotime( '-30 days' ) );
        $default_end   = date( 'Y-m-d' );
        ?>
        <div class="cv-export-card card-plays">
            <h2>
                <span class="icon">📈</span>
                Plays por Período
            </h2>
            <p class="cv-desc">Cada reprodução válida registrada: música, usuário (e-mail se logado), IP e data/hora. Ideal para análise de engajamento.</p>
            <form method="post" class="cv-export-form">
                <?php wp_nonce_field( 'cv_export_nonce', 'cv_nonce' ); ?>
                <input type="hidden" name="cv_export_type" value="plays">
                <div class="cv-export-field">
                    <label>Data início</label>
                    <input type="date" name="cv_date_start" value="<?php echo esc_attr( $default_start ); ?>">
                </div>
                <div class="cv-export-field">
                    <label>Data fim</label>
                    <input type="date" name="cv_date_end" value="<?php echo esc_attr( $default_end ); ?>">
                </div>
                <button type="submit" class="cv-btn-export">⬇ Baixar CSV</button>
            </form>
            <div class="cv-exp-columns">
                <strong>Colunas:</strong> ID Música · Título · Usuário · IP · Data/Hora
            </div>
        </div>
        <?php
    }

    private function render_card_ranking( $total ) {
        ?>
        <div class="cv-export-card card-ranking">
            <h2>
                <span class="icon">🏆</span>
                Ranking Atual
                <span class="cv-export-badge"><?php echo number_format( $total ); ?> posições</span>
            </h2>
            <p class="cv-desc">Snapshot do ranking com posição, score calculado, plays totais, plays dos últimos 7 dias, favoritos e avaliação.</p>
            <form method="post" class="cv-export-form">
                <?php wp_nonce_field( 'cv_export_nonce', 'cv_nonce' ); ?>
                <input type="hidden" name="cv_export_type" value="ranking">
                <div class="cv-export-field">
                    <label>Quantidade</label>
                    <select name="cv_ranking_limit">
                        <option value="10">Top 10</option>
                        <option value="25">Top 25</option>
                        <option value="50" selected>Top 50</option>
                        <option value="100">Top 100</option>
                        <option value="0">Ranking completo</option>
                    </select>
                </div>
                <button type="submit" class="cv-btn-export">⬇ Baixar CSV</button>
            </form>
            <div class="cv-exp-columns">
                <strong>Colunas:</strong> Posição · Título · Score · Plays Total · Plays 7d · Favoritos · Avaliação · Variação ↑↓
            </div>
        </div>
        <?php
    }

    private function render_card_assinantes( $total ) {
        ?>
        <div class="cv-export-card card-assin">
            <h2>
                <span class="icon">📧</span>
                Assinantes Newsletter
                <span class="cv-export-badge"><?php echo number_format( $total ); ?> assinantes</span>
            </h2>
            <p class="cv-desc">CSV com nome e data de cadastro, ou TXT só com e-mails (um por linha) — pronto para importação direta no MailerLite.</p>
            <form method="post" class="cv-export-form">
                <?php wp_nonce_field( 'cv_export_nonce', 'cv_nonce' ); ?>
                <input type="hidden" name="cv_export_type" value="assinantes">
                <div class="cv-export-field">
                    <label>Formato</label>
                    <select name="cv_format">
                        <option value="csv">CSV — com nome e data</option>
                        <option value="txt">TXT — só e-mails (MailerLite)</option>
                    </select>
                </div>
                <div class="cv-export-field">
                    <label>Cadastrados após (opcional)</label>
                    <input type="date" name="cv_date_start" value="">
                </div>
                <button type="submit" class="cv-btn-export">⬇ Baixar Arquivo</button>
            </form>
            <div class="cv-exp-columns">
                <strong>CSV:</strong> E-mail · Nome · Data de cadastro &nbsp;|&nbsp; <strong>TXT:</strong> um e-mail por linha
            </div>
        </div>
        <?php
    }

    // ─────────────────────────────────────────────
    // ROTEADOR
    // ─────────────────────────────────────────────

    public function handle_export_request() {
        if ( ! isset( $_POST['cv_export_type'] ) ) {
            return;
        }
        if ( ! isset( $_POST['cv_nonce'] ) || ! wp_verify_nonce( $_POST['cv_nonce'], 'cv_export_nonce' ) ) {
            wp_die( 'Nonce inválido. Recarregue a página e tente novamente.' );
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Permissão negada.' );
        }
        $type = sanitize_text_field( $_POST['cv_export_type'] );
        switch ( $type ) {
            case 'musicas':    $this->export_musicas();    break;
            case 'plays':      $this->export_plays();      break;
            case 'ranking':    $this->export_ranking();    break;
            case 'assinantes': $this->export_assinantes(); break;
        }
    }

    // ─────────────────────────────────────────────
    // EXPORTAÇÃO: MÚSICAS
    // ─────────────────────────────────────────────

    private function export_musicas() {
        $status_filter = isset( $_POST['cv_status'] ) ? sanitize_text_field( $_POST['cv_status'] ) : '';
        $args = array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );
        if ( $status_filter !== '' ) {
            $args['meta_query'] = array( array(
                'key'     => CV_Fields::ATIVO,
                'value'   => $status_filter,
                'compare' => '=',
            ) );
        }
        $musicas  = get_posts( $args );
        $filename = 'cancaoverdadeira-musicas-' . date( 'Y-m-d' ) . '.csv';
        $this->set_csv_headers( $filename );
        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
        fputcsv( $out, array(
            'ID','Título','Artista','Compositor',
            'Álbum','Ano','Plays Total','Favoritos','Avaliação Média',
            'Status','Tem YouTube','Tem MP3','Data Publicação'
        ), ';' );
        foreach ( $musicas as $m ) {
            $ativo       = get_post_meta( $m->ID, CV_Fields::ATIVO,       true );
            $youtube     = get_post_meta( $m->ID, CV_Fields::YOUTUBE_URL, true );
            $mp3         = get_post_meta( $m->ID, CV_Fields::AUDIO_URL,   true );
            fputcsv( $out, array(
                $m->ID,
                $m->post_title,
                get_post_meta( $m->ID, CV_Fields::ARTISTA,    true ),
                get_post_meta( $m->ID, CV_Fields::COMPOSITOR, true ),
                get_post_meta( $m->ID, CV_Fields::ALBUM, true ),
                get_post_meta( $m->ID, CV_Fields::ANO,   true ),
                intval( get_post_meta( $m->ID, CV_Fields::PLAYS_TOTAL, true ) ),
                intval( get_post_meta( $m->ID, CV_Fields::FAVORITES,  true ) ),
                round( floatval( get_post_meta( $m->ID, CV_Fields::AVG_RATING, true ) ), 2 ),
                ( $ativo == '1' || $ativo === '' ) ? 'Ativa' : 'Inativa',
                ! empty( $youtube ) ? 'Sim' : 'Não',
                ! empty( $mp3 )     ? 'Sim' : 'Não',
                get_the_date( 'd/m/Y', $m->ID ),
            ), ';' );
        }
        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────
    // EXPORTAÇÃO: PLAYS
    // ─────────────────────────────────────────────

    private function export_plays() {
        global $wpdb;
        $table = $wpdb->prefix . 'cv_plays_log';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            wp_die( 'Tabela cv_plays_log não encontrada. Desative e reative o plugin.' );
        }
        $date_start = isset( $_POST['cv_date_start'] ) ? sanitize_text_field( $_POST['cv_date_start'] ) : date( 'Y-m-d', strtotime( '-30 days' ) );
        $date_end   = isset( $_POST['cv_date_end'] )   ? sanitize_text_field( $_POST['cv_date_end'] )   : date( 'Y-m-d' );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_start ) ) $date_start = date( 'Y-m-d', strtotime( '-30 days' ) );
        if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date_end ) )   $date_end   = date( 'Y-m-d' );
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.music_id, po.post_title AS titulo, p.user_id, p.ip_address AS user_ip, p.played_at
             FROM {$table} p
             LEFT JOIN {$wpdb->posts} po ON po.ID = p.music_id
             WHERE p.played_at >= %s AND p.played_at < %s
             ORDER BY p.played_at DESC",
            $date_start . ' 00:00:00',
            date( 'Y-m-d', strtotime( $date_end . ' +1 day' ) ) . ' 00:00:00'
        ) );
        $filename = 'cancaoverdadeira-plays-' . $date_start . '-a-' . $date_end . '.csv';
        $this->set_csv_headers( $filename );
        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
        fputcsv( $out, array( 'ID Música', 'Título', 'Usuário', 'IP', 'Data/Hora' ), ';' );
        foreach ( $rows as $row ) {
            $user_info  = $row->user_id ? get_userdata( $row->user_id ) : null;
            $user_label = $user_info ? $user_info->user_email : 'Visitante';
            fputcsv( $out, array(
                $row->music_id, $row->titulo, $user_label, $row->user_ip, $row->played_at,
            ), ';' );
        }
        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────
    // EXPORTAÇÃO: RANKING
    // ─────────────────────────────────────────────

    private function export_ranking() {
        global $wpdb;
        $table = $wpdb->prefix . 'cv_ranking_cache';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            wp_die( 'Tabela cv_ranking_cache não encontrada. Desative e reative o plugin.' );
        }
        $limit     = isset( $_POST['cv_ranking_limit'] ) ? intval( $_POST['cv_ranking_limit'] ) : 50;
        $limit_sql = $limit > 0 ? 'LIMIT ' . intval( $limit ) : '';
        $rows = $wpdb->get_results(
            "SELECT r.position, r.music_id, po.post_title AS titulo,
                    r.score, r.plays_total, r.plays_7d, r.favorites, r.avg_rating,
                    CASE WHEN r.position_prev > 0 THEN CAST(r.position_prev AS SIGNED) - CAST(r.position AS SIGNED) ELSE 0 END AS position_change
             FROM {$table} r
             LEFT JOIN {$wpdb->posts} po ON po.ID = r.music_id
             ORDER BY r.position ASC {$limit_sql}"
        );
        $suffix   = $limit > 0 ? 'top' . $limit : 'completo';
        $filename = 'cancaoverdadeira-ranking-' . $suffix . '-' . date( 'Y-m-d' ) . '.csv';
        $this->set_csv_headers( $filename );
        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
        fputcsv( $out, array(
            'Posição','Título','Score','Plays Total','Plays 7 dias','Favoritos','Avaliação Média','Variação'
        ), ';' );
        foreach ( $rows as $row ) {
            $v = intval( $row->position_change );
            if ( $v > 0 )     $var = '+' . $v . ' ↑';
            elseif ( $v < 0 ) $var = $v . ' ↓';
            else               $var = '—';
            fputcsv( $out, array(
                '#' . $row->position,
                $row->titulo,
                round( floatval( $row->score ), 4 ),
                intval( $row->plays_total ),
                intval( $row->plays_7d ),
                intval( $row->favorites ),
                round( floatval( $row->avg_rating ), 2 ),
                $var,
            ), ';' );
        }
        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────
    // EXPORTAÇÃO: ASSINANTES
    // ─────────────────────────────────────────────

    private function export_assinantes() {
        global $wpdb;
        $table  = $wpdb->prefix . 'cv_newsletter';
        $format = isset( $_POST['cv_format'] ) ? sanitize_text_field( $_POST['cv_format'] ) : 'csv';
        $since  = isset( $_POST['cv_date_start'] ) ? sanitize_text_field( $_POST['cv_date_start'] ) : '';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
            wp_die( 'Tabela cv_newsletter não encontrada. Desative e reative o plugin.' );
        }
        if ( ! empty( $since ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since ) ) {
            $rows = $wpdb->get_results( $wpdb->prepare(
                "SELECT email, name, subscribed_at FROM {$table} WHERE subscribed_at >= %s ORDER BY subscribed_at DESC",
                $since . ' 00:00:00'
            ) );
        } else {
            $rows = $wpdb->get_results( "SELECT email, name, subscribed_at FROM {$table} ORDER BY subscribed_at DESC" );
        }
        $date_suffix = date( 'Y-m-d' );
        if ( $format === 'txt' ) {
            header( 'Content-Type: text/plain; charset=utf-8' );
            header( 'Content-Disposition: attachment; filename="cancaoverdadeira-emails-' . $date_suffix . '.txt"' );
            header( 'Cache-Control: no-cache, no-store, must-revalidate' );
            header( 'Pragma: no-cache' );
            header( 'Expires: 0' );
            foreach ( $rows as $row ) {
                echo sanitize_email( $row->email ) . "\n";
            }
            exit;
        }
        $this->set_csv_headers( 'cancaoverdadeira-assinantes-' . $date_suffix . '.csv' );
        $out = fopen( 'php://output', 'w' );
        fprintf( $out, chr(0xEF).chr(0xBB).chr(0xBF) );
        fputcsv( $out, array( 'E-mail', 'Nome', 'Data de Cadastro' ), ';' );
        foreach ( $rows as $row ) {
            fputcsv( $out, array(
                sanitize_email( $row->email ),
                isset( $row->name ) ? sanitize_text_field( $row->name ) : '',
                isset( $row->subscribed_at ) ? date( 'd/m/Y H:i', strtotime( $row->subscribed_at ) ) : '',
            ), ';' );
        }
        fclose( $out );
        exit;
    }

    // ─────────────────────────────────────────────
    // UTILITÁRIO
    // ─────────────────────────────────────────────

    private function set_csv_headers( $filename ) {
        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Cache-Control: no-cache, no-store, must-revalidate' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );
    }
}

CV_Admin_Exports::get_instance();
