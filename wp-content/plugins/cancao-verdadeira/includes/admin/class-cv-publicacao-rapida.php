<?php
// cancao-verdadeira/includes/admin/class-cv-publicacao-rapida.php
// Gerado em: 2026-06-27 11:00:00
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Publicação Acelerada de Músicas (v2.16.0)
// Funções : Fila de rascunhos importados do YouTube com formulário inline.
//           Preenche letra, MP3, descrição e sentimentos sem sair do
//           painel — publica com 1 clique.
// v2.26.0 : removidos os campos Gênero e Subcategoria (site todo sertanejo).
//           Reduz tempo de cadastro de ~5min por música para ~90 segundos.
// Visual  : Dark mode Spotify-style, painel dividido (lista | formulário)
// Autor   : Canção Verdadeira | Gerado: 2026-06-27

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Publicacao_Rapida {

    public static function init() {
        add_action( 'wp_ajax_cv_pr_load_musica',    array( __CLASS__, 'ajax_load' ) );
        add_action( 'wp_ajax_cv_pr_salvar',         array( __CLASS__, 'ajax_salvar' ) );
        add_action( 'wp_ajax_cv_pr_publicar',       array( __CLASS__, 'ajax_publicar' ) );
        add_action( 'wp_ajax_cv_pr_pular',          array( __CLASS__, 'ajax_pular' ) );
        add_action( 'wp_ajax_cv_pr_excluir',        array( __CLASS__, 'ajax_excluir' ) );
    }

    // ── Helpers ───────────────────────────────────────────────────

    private static function get_rascunhos( $limit = 50 ) {
        return get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'draft',
            'posts_per_page' => $limit,
            'orderby'        => 'date',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );
    }

    // ── AJAX: carregar dados de uma música ────────────────────────

    public static function ajax_load() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }

        $id   = absint( $_POST['musica_id'] ?? 0 );
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'musica' ) {
            wp_send_json_error('Música não encontrada.');
        }

        $sentimentos = array();
        if ( class_exists('CV_Sentimentos') ) {
            $sents = CV_Sentimentos::get_for_musica( $id );
            foreach ( $sents as $s ) { $sentimentos[] = (int)$s->id; }
        }

        wp_send_json_success( array(
            'id'           => $id,
            'titulo'       => $post->post_title,
            'conteudo'     => $post->post_content,
            'youtube_url'  => get_post_meta( $id, '_cv_youtube_url', true ),
            'audio_url'    => get_post_meta( $id, '_cv_audio_url', true ),
            'artista'      => get_post_meta( $id, '_cv_artista', true ),
            'compositor'   => get_post_meta( $id, '_cv_compositor', true ),
            'album'        => get_post_meta( $id, '_cv_album', true ),
            'ano'          => get_post_meta( $id, '_cv_ano', true ),
            'descricao'    => get_post_meta( $id, '_cv_descricao', true ),
            'seo_title'    => get_post_meta( $id, '_cv_seo_title', true ),
            'ativo'        => get_post_meta( $id, '_cv_ativo', true ) ?: '1',
            'destaque'     => get_post_meta( $id, '_cv_destaque', true ),
            'capa_url'     => get_the_post_thumbnail_url( $id, 'medium' ) ?: '',
            'sentimentos'  => $sentimentos,
            'status'       => $post->post_status,
        ) );
    }

    // ── AJAX: salvar (rascunho) ───────────────────────────────────

    public static function ajax_salvar() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        self::_salvar_campos( absint($_POST['musica_id'] ?? 0), 'draft' );
        wp_send_json_success( array('msg' => 'Rascunho salvo.') );
    }

    // ── AJAX: publicar ────────────────────────────────────────────

    public static function ajax_publicar() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        $id = absint( $_POST['musica_id'] ?? 0 );
        self::_salvar_campos( $id, 'publish' );
        wp_send_json_success( array(
            'msg'  => 'Música publicada!',
            'url'  => get_permalink( $id ),
        ) );
    }

    // ── AJAX: pular (deixar no rascunho, ir para próxima) ─────────

    public static function ajax_pular() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        wp_send_json_success( array('msg' => 'Pulado.') );
    }

    // ── AJAX: excluir permanentemente ────────────────────────────

    public static function ajax_excluir() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can('manage_options') ) { wp_send_json_error(); }
        $id   = absint( $_POST['musica_id'] ?? 0 );
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'musica' ) {
            wp_send_json_error('Música não encontrada.');
        }
        $titulo = $post->post_title;
        wp_delete_post( $id, true ); // true = exclusão permanente (sem lixeira)
        wp_send_json_success( array(
            'msg'    => 'Música excluída permanentemente.',
            'titulo' => $titulo,
        ) );
    }

    // ── Salvar campos ─────────────────────────────────────────────

    private static function _salvar_campos( $id, $status ) {
        if ( ! $id ) { return; }
        $post = get_post( $id );
        if ( ! $post || $post->post_type !== 'musica' ) { return; }

        // Post data
        $update = array(
            'ID'           => $id,
            'post_status'  => $status,
            'post_content' => wp_kses_post( wp_unslash( $_POST['conteudo'] ?? '' ) ),
        );
        $titulo = sanitize_text_field( wp_unslash( $_POST['titulo'] ?? '' ) );
        if ( $titulo ) { $update['post_title'] = $titulo; }
        wp_update_post( $update );

        // Meta fields
        $metas = array(
            '_cv_artista'    => 'sanitize_text_field',
            '_cv_compositor' => 'sanitize_text_field',
            '_cv_album'      => 'sanitize_text_field',
            '_cv_ano'        => 'absint',
            '_cv_youtube_url'=> 'esc_url_raw',
            '_cv_audio_url'  => 'esc_url_raw',
            '_cv_descricao'  => 'sanitize_textarea_field',
            '_cv_seo_title'  => 'sanitize_text_field',
            '_cv_ativo'      => 'absint',
            '_cv_destaque'   => 'absint',
        );
        foreach ( $metas as $key => $fn ) {
            $raw = $_POST[ ltrim($key,'_') ] ?? $_POST[ $key ] ?? null;
            if ( $raw !== null ) {
                update_post_meta( $id, $key, $fn( wp_unslash( $raw ) ) );
            }
        }

        // Sentimentos
        if ( class_exists('CV_Sentimentos') ) {
            $sents = isset($_POST['sentimentos']) ? array_map('absint', (array)$_POST['sentimentos']) : array();
            CV_Sentimentos::set_for_musica( $id, $sents );
        }

        // Capa via URL externa (thumbnail se vier da importação)
        $capa_url = esc_url_raw( wp_unslash( $_POST['capa_url'] ?? '' ) );
        if ( $capa_url && ! has_post_thumbnail($id) ) {
            self::_definir_capa_por_url( $id, $capa_url );
        }
    }

    private static function _definir_capa_por_url( $post_id, $url ) {
        // Tenta usar miniatura do YouTube já na biblioteca
        if ( strpos($url, 'ytimg.com') !== false ) {
            // Busca attachment já existente com essa URL
            global $wpdb;
            $att = $wpdb->get_var( $wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key='_cv_yt_thumb' AND meta_value=%s LIMIT 1",
                $url
            ) );
            if ( $att ) {
                set_post_thumbnail( $post_id, (int)$att );
            }
        }
    }

    // ── Render da página ──────────────────────────────────────────

    public static function render_page() {
        $rascunhos    = self::get_rascunhos(100);
        $total        = count( $rascunhos );
        $sents_list   = class_exists('CV_Sentimentos') ? CV_Sentimentos::get_all() : array();
        $nonce        = wp_create_nonce('cv_admin_nonce');

        // Montar dados dos rascunhos para o JS
        $rascunhos_data = array();
        foreach ( $rascunhos as $rid ) {
            $p = get_post($rid);
            $rascunhos_data[] = array(
                'id'        => $rid,
                'titulo'    => $p->post_title ?: '(sem título)',
                'artista'   => get_post_meta($rid,'_cv_artista',true) ?: '',
                'capa'      => get_the_post_thumbnail_url($rid,'thumbnail') ?: CV_PLUGIN_URL.'assets/img/default-cover.svg',
                'yt'        => get_post_meta($rid,'_cv_youtube_url',true) ?: '',
                'tem_letra' => ! empty(trim($p->post_content)),
            );
        }
        ?>
        <div class="wrap" id="cv-pr-page">
        <?php echo CV_Admin::btn_voltar(); ?>
        

        <style>
        body.wp-admin { background: #FBF6EE !important; }
        #wpwrap, #wpcontent, #wpbody, #wpbody-content { background: #FBF6EE !important; }
        #cv-pr-page {
            --gold: #B8700C;
            --bg:   #FFFFFF;
            --card: #F8F0E4;
            --card2:#F8F0E4;
            --bord: #F3E6D3;
            --text: #3B2418;
            --muted:#C9A27E;
            --green:#1DB954;
            --red:  #e74c3c;
            background: var(--bg);
            color: var(--text);
            font-family: 'Segoe UI', system-ui, sans-serif;
            padding-bottom: 60px;
            min-height: 100vh;
        }
        #cv-pr-page * { box-sizing: border-box; }

        .cv-pr-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 12px;
        }
        .cv-pr-title { font-size: 22px; font-weight: 700; color: #3B2418; margin: 0; }
        .cv-pr-title span { color: var(--gold); }
        .cv-pr-progress-wrap { display: flex; align-items: center; gap: 12px; }
        .cv-pr-progress-bar {
            width: 200px; height: 6px;
            background: rgba(123,58,34,0.09);
            border-radius: 4px; overflow: hidden;
        }
        .cv-pr-progress-fill {
            height: 100%; background: var(--green);
            border-radius: 4px; transition: width .5s ease;
        }
        .cv-pr-progress-text { font-size: 13px; color: var(--muted); }

        .cv-pr-layout {
            display: grid;
            grid-template-columns: 300px 1fr;
            gap: 20px;
            align-items: start;
        }
        @media (max-width: 1100px) {
            .cv-pr-layout { grid-template-columns: 1fr; }
        }

        /* Lista de rascunhos */
        .cv-pr-lista-box {
            background: var(--card);
            border: 1px solid var(--bord);
            border-radius: 14px;
            overflow: hidden;
            position: sticky;
            top: 32px;
        }
        .cv-pr-lista-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--bord);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .cv-pr-lista-header h3 { margin: 0; font-size: 13px; color: #3B2418; }
        .cv-pr-lista-header small { color: var(--muted); font-size: 11px; }
        .cv-pr-lista-scroll { max-height: 70vh; overflow-y: auto; }
        .cv-pr-lista-scroll::-webkit-scrollbar { width: 4px; }
        .cv-pr-lista-scroll::-webkit-scrollbar-track { background: transparent; }
        .cv-pr-lista-scroll::-webkit-scrollbar-thumb { background: var(--bord); border-radius: 2px; }

        .cv-pr-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--bord);
            cursor: pointer;
            transition: background .15s;
        }
        .cv-pr-item:hover { background: rgba(123,58,34,0.04); }
        .cv-pr-item.ativo { background: rgba(242,165,26,0.1); border-left: 3px solid var(--gold); }
        .cv-pr-item.publicada { opacity: .4; pointer-events: none; }
        .cv-pr-item-capa {
            width: 38px; height: 38px;
            border-radius: 6px; object-fit: cover; flex-shrink: 0;
        }
        .cv-pr-item-info { flex: 1; min-width: 0; }
        .cv-pr-item-titulo {
            font-size: 12px; font-weight: 600; color: var(--text);
            white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
        }
        .cv-pr-item-sub { font-size: 10px; color: var(--muted); margin-top: 2px; }
        .cv-pr-item-check { font-size: 14px; flex-shrink: 0; }
        .cv-pr-badge-letra {
            font-size: 9px; padding: 2px 5px; border-radius: 4px;
            background: rgba(29,185,84,.15); color: var(--green);
            border: 1px solid rgba(29,185,84,.3);
        }
        .cv-pr-empty-lista {
            padding: 40px 20px; text-align: center; color: var(--muted); font-size: 13px;
        }

        /* Formulário */
        .cv-pr-form-box {
            background: var(--card);
            border: 1px solid var(--bord);
            border-radius: 14px;
            overflow: hidden;
        }
        .cv-pr-form-header {
            padding: 16px 20px;
            border-bottom: 1px solid var(--bord);
            display: flex;
            align-items: center;
            gap: 14px;
        }
        .cv-pr-form-capa {
            width: 56px; height: 56px;
            border-radius: 8px; object-fit: cover;
        }
        .cv-pr-form-titulo-wrap { flex: 1; }
        .cv-pr-form-titulo-input {
            width: 100%;
            background: transparent;
            border: none;
            border-bottom: 1px solid var(--bord);
            color: #3B2418;
            font-size: 18px;
            font-weight: 700;
            padding: 4px 0;
            outline: none;
        }
        .cv-pr-form-titulo-input:focus { border-bottom-color: var(--gold); }
        .cv-pr-form-subtitle { font-size: 11px; color: var(--muted); margin-top: 4px; }
        .cv-pr-yt-link {
            font-size: 11px; color: var(--gold);
            text-decoration: none; display: inline-flex; align-items: center; gap: 4px;
        }

        .cv-pr-form-body { padding: 20px; }
        .cv-pr-section { margin-bottom: 20px; }
        .cv-pr-section-title {
            font-size: 11px; color: var(--muted);
            text-transform: uppercase; letter-spacing: .6px;
            margin-bottom: 10px;
            display: flex; align-items: center; gap: 6px;
        }
        .cv-pr-section-title::after {
            content: ''; flex: 1; height: 1px; background: var(--bord);
        }

        /* Campos */
        .cv-pr-field { margin-bottom: 14px; }
        .cv-pr-label {
            display: block; font-size: 11px; color: var(--muted);
            text-transform: uppercase; letter-spacing: .4px; margin-bottom: 5px;
        }
        .cv-pr-input, .cv-pr-select, .cv-pr-textarea {
            width: 100%;
            background: rgba(123,58,34,0.04);
            border: 1px solid var(--bord);
            border-radius: 8px;
            color: var(--text);
            padding: 9px 12px;
            font-size: 13px;
            outline: none;
            transition: border-color .2s;
            font-family: inherit;
        }
        .cv-pr-input:focus, .cv-pr-select:focus, .cv-pr-textarea:focus {
            border-color: var(--gold);
            background: rgba(242,165,26,0.05);
        }
        .cv-pr-select option { background: #F8F0E4; }
        .cv-pr-textarea { min-height: 180px; resize: vertical; font-size: 12px; line-height: 1.7; }
        .cv-pr-textarea-letra { min-height: 280px; }

        /* Grid 2 colunas */
        .cv-pr-grid2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }

        /* Checkboxes de sentimento */
        .cv-pr-checks {
            display: flex; flex-wrap: wrap; gap: 8px;
        }
        .cv-pr-check-label {
            display: inline-flex; align-items: center; gap: 6px;
            background: rgba(123,58,34,0.04);
            border: 1px solid var(--bord);
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all .15s;
        }
        .cv-pr-check-label:hover { border-color: var(--gold); }
        .cv-pr-check-label input { display: none; }
        .cv-pr-check-label.marcado {
            background: rgba(242,165,26,0.2);
            border-color: var(--gold);
            color: var(--gold);
        }

        /* Sentimento com cor */
        .cv-pr-sent-label {
            display: inline-flex; align-items: center; gap: 5px;
            border-radius: 20px;
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all .15s;
        }
        .cv-pr-sent-label input { display: none; }

        /* Botões de ação */
        .cv-pr-form-footer {
            padding: 16px 20px;
            border-top: 1px solid var(--bord);
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .cv-pr-btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            border: none;
            transition: opacity .2s, transform .1s;
            display: inline-flex; align-items: center; gap: 6px;
        }
        .cv-pr-btn:hover { opacity: .88; }
        .cv-pr-btn:active { transform: scale(.97); }
        .cv-pr-btn-publicar { background: var(--green); color: #3B2418; flex: 1; justify-content: center; }
        .cv-pr-btn-salvar   { background: rgba(123,58,34,0.09); color: var(--text); }
        .cv-pr-btn-pular    { background: transparent; color: var(--muted); border: 1px solid var(--bord); }
        .cv-pr-btn-ext      { background: rgba(255,0,0,.12); color: #DB0000; border: 1px solid rgba(255,0,0,.2); text-decoration: none; }
        .cv-pr-btn-excluir  { background: rgba(231,76,60,.12); color: #D62C1A; border: 1px solid rgba(231,76,60,.3); }
        .cv-pr-btn-excluir:hover { background: rgba(231,76,60,.25); }

        .cv-pr-toast {
            position: fixed; bottom: 24px; right: 24px;
            background: var(--green); color: #3B2418;
            padding: 12px 20px; border-radius: 10px;
            font-size: 13px; font-weight: 600;
            box-shadow: 0 4px 20px rgba(123,58,34,0.12);
            z-index: 9999;
            transform: translateY(80px); opacity: 0;
            transition: all .3s ease;
            max-width: 320px;
        }
        .cv-pr-toast.show { transform: translateY(0); opacity: 1; }
        .cv-pr-toast.erro { background: var(--red); }

        .cv-pr-placeholder {
            padding: 60px 20px; text-align: center; color: var(--muted);
        }
        .cv-pr-placeholder-emoji { font-size: 48px; margin-bottom: 12px; }
        .cv-pr-placeholder-txt { font-size: 14px; }

        .cv-pr-spinner {
            display: inline-block; width: 16px; height: 16px;
            border: 2px solid rgba(123,58,34,0.32);
            border-top-color: #EADBC6;
            border-radius: 50%;
            animation: cv-spin .6s linear infinite;
        }
        @keyframes cv-spin { to { transform: rotate(360deg); } }
        </style>

        <!-- Topbar -->
        <div class="cv-pr-topbar">
            <h1 class="cv-pr-title">⚡ Publicação <span>Acelerada</span></h1>
            <div class="cv-pr-progress-wrap">
                <div style="font-size:13px;color:var(--cv-muted,#8A6A55)">
                    <span id="cv-pr-count-pub">0</span> publicadas /
                    <strong style="color:#3B2418"><?php echo $total; ?></strong> rascunhos
                </div>
                <div class="cv-pr-progress-bar">
                    <div class="cv-pr-progress-fill" id="cv-pr-prog" style="width:0%"></div>
                </div>
            </div>
        </div>

        <?php if ( $total === 0 ) : ?>
        <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:14px;padding:60px;text-align:center">
            <div style="font-size:48px;margin-bottom:16px">🎉</div>
            <div style="font-size:18px;color:#3B2418;margin-bottom:8px">Nenhum rascunho na fila!</div>
            <div style="font-size:13px;color:#8A6A55;margin-bottom:20px">Todas as músicas importadas já foram publicadas.</div>
            <a href="<?php echo esc_url(admin_url('admin.php?page=cv-youtube-import')); ?>"
               style="background:#F2A51A;color:#3B2418;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:700">
                ▶ Importar mais do YouTube
            </a>
        </div>
        <?php else : ?>

        <div class="cv-pr-layout">

            <!-- Lista de rascunhos -->
            <div class="cv-pr-lista-box">
                <div class="cv-pr-lista-header">
                    <h3>📋 Fila de Rascunhos</h3>
                    <small><?php echo $total; ?> músicas</small>
                </div>
                <div class="cv-pr-lista-scroll" id="cv-pr-lista">
                    <?php foreach ( $rascunhos_data as $r ) : ?>
                    <div class="cv-pr-item" id="cv-pr-li-<?php echo $r['id']; ?>"
                         onclick="cvPrCarregar(<?php echo $r['id']; ?>)">
                        <img src="<?php echo esc_url($r['capa']); ?>"
                             class="cv-pr-item-capa" alt="">
                        <div class="cv-pr-item-info">
                            <div class="cv-pr-item-titulo"><?php echo esc_html($r['titulo']); ?></div>
                            <div class="cv-pr-item-sub">
                                <?php echo $r['artista'] ? esc_html($r['artista']) : '<span style="color:#8A6A55">sem artista</span>'; ?>
                                <?php if ($r['tem_letra']) : ?>
                                &nbsp;<span class="cv-pr-badge-letra">letra ✓</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="cv-pr-item-check" id="cv-pr-chk-<?php echo $r['id']; ?>"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Formulário -->
            <div class="cv-pr-form-box" id="cv-pr-form-box">

                <!-- Placeholder inicial -->
                <div class="cv-pr-placeholder" id="cv-pr-placeholder">
                    <div class="cv-pr-placeholder-emoji">👈</div>
                    <div class="cv-pr-placeholder-txt">
                        Selecione uma música na lista ao lado para começar
                    </div>
                </div>

                <!-- Formulário real (oculto até selecionar) -->
                <div id="cv-pr-form-real" style="display:none">
                    <div class="cv-pr-form-header">
                        <img src="" alt="" class="cv-pr-form-capa" id="cv-pr-hdr-capa">
                        <div class="cv-pr-form-titulo-wrap">
                            <input type="text" class="cv-pr-form-titulo-input"
                                   id="cv-pr-titulo" placeholder="Título da música">
                            <div class="cv-pr-form-subtitle">
                                <span id="cv-pr-hdr-artista"></span>
                                <span id="cv-pr-yt-wrap" style="margin-left:10px"></span>
                            </div>
                        </div>
                    </div>

                    <div class="cv-pr-form-body">

                        <!-- Informações básicas -->
                        <div class="cv-pr-section">
                            <div class="cv-pr-section-title">📝 Informações</div>
                            <div class="cv-pr-grid2">
                                <div class="cv-pr-field">
                                    <label class="cv-pr-label">Artista</label>
                                    <input type="text" class="cv-pr-input" id="cv-pr-artista" placeholder="Nome do artista">
                                </div>
                                <div class="cv-pr-field">
                                    <label class="cv-pr-label">Compositor</label>
                                    <input type="text" class="cv-pr-input" id="cv-pr-compositor" placeholder="Nome do compositor">
                                </div>
                                <div class="cv-pr-field">
                                    <label class="cv-pr-label">Álbum</label>
                                    <input type="text" class="cv-pr-input" id="cv-pr-album" placeholder="Nome do álbum">
                                </div>
                                <div class="cv-pr-field">
                                    <label class="cv-pr-label">Ano</label>
                                    <input type="number" class="cv-pr-input" id="cv-pr-ano" placeholder="2024">
                                </div>
                            </div>
                            <div class="cv-pr-field">
                                <label class="cv-pr-label">URL MP3 (arquivo de áudio)</label>
                                <input type="url" class="cv-pr-input" id="cv-pr-audio-url" placeholder="https://...">
                            </div>
                            <div class="cv-pr-field">
                                <label class="cv-pr-label">URL YouTube</label>
                                <input type="url" class="cv-pr-input" id="cv-pr-yt-url" placeholder="https://youtube.com/watch?v=...">
                            </div>
                            <div class="cv-pr-field">
                                <label class="cv-pr-label">Descrição / Contexto da música</label>
                                <textarea class="cv-pr-textarea" id="cv-pr-descricao"
                                          placeholder="Breve descrição para SEO e contexto..."></textarea>
                            </div>
                        </div>

                        <!-- Letra -->
                        <div class="cv-pr-section">
                            <div class="cv-pr-section-title">🎵 Letra</div>
                            <div class="cv-pr-field">
                                <textarea class="cv-pr-textarea cv-pr-textarea-letra"
                                          id="cv-pr-letra"
                                          placeholder="Cole a letra completa aqui...&#10;&#10;[Verso 1]&#10;...&#10;&#10;[Refrão]&#10;..."></textarea>
                            </div>
                        </div>

                        <!-- Sentimentos -->
                        <?php if ( ! empty($sents_list) ) : ?>
                        <div class="cv-pr-section">
                            <div class="cv-pr-section-title">🎭 Sentimentos</div>
                            <div class="cv-pr-checks" id="cv-pr-sents-checks">
                                <?php foreach ( $sents_list as $s ) :
                                    $cor = esc_attr($s->cor);
                                ?>
                                <label class="cv-pr-sent-label"
                                       data-id="<?php echo (int)$s->id; ?>"
                                       data-cor="<?php echo $cor; ?>"
                                       style="background:<?php echo $cor; ?>18;border-color:<?php echo $cor; ?>44;color:<?php echo $cor; ?>">
                                    <input type="checkbox" value="<?php echo (int)$s->id; ?>"
                                           name="sentimentos[]" onchange="cvPrToggleSent(this.closest('label'))">
                                    <?php echo esc_html($s->icone . ' ' . $s->nome); ?>
                                </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Opções -->
                        <div class="cv-pr-section">
                            <div class="cv-pr-section-title">⚙️ Opções</div>
                            <div class="cv-pr-grid2">
                                <label class="cv-pr-check-label" id="cv-pr-lbl-ativo"
                                       style="border-radius:8px;padding:10px 14px">
                                    <input type="checkbox" id="cv-pr-ativo" value="1"
                                           onchange="cvPrToggleCheck(this.closest('label'))">
                                    ✅ Música ativa (visível no catálogo)
                                </label>
                                <label class="cv-pr-check-label" id="cv-pr-lbl-destaque"
                                       style="border-radius:8px;padding:10px 14px">
                                    <input type="checkbox" id="cv-pr-destaque" value="1"
                                           onchange="cvPrToggleCheck(this.closest('label'))">
                                    ⭐ Marcar como destaque
                                </label>
                            </div>
                        </div>

                    </div><!-- form-body -->

                    <div class="cv-pr-form-footer">
                        <button class="cv-pr-btn cv-pr-btn-excluir" onclick="cvPrExcluir()">🗑 Excluir</button>
                        <button class="cv-pr-btn cv-pr-btn-pular" onclick="cvPrPular()">⏭ Pular</button>
                        <button class="cv-pr-btn cv-pr-btn-salvar" onclick="cvPrSalvar()">💾 Salvar rascunho</button>
                        <a href="#" id="cv-pr-link-ext" target="_blank"
                           class="cv-pr-btn cv-pr-btn-ext" style="display:none">
                            ▶ Ver no YouTube
                        </a>
                        <button class="cv-pr-btn cv-pr-btn-publicar" onclick="cvPrPublicar()">
                            🚀 Publicar música
                        </button>
                    </div>
                </div><!-- form-real -->

            </div><!-- form-box -->
        </div><!-- layout -->

        <?php endif; ?>
        </div><!-- wrap -->

        <div class="cv-pr-toast" id="cv-pr-toast"></div>

        <script>
        (function() {
            var NONCE     = '<?php echo esc_js($nonce); ?>';
            var AJAX      = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
            var RASCUNHOS = <?php echo wp_json_encode($rascunhos_data); ?>;
            var TOTAL     = <?php echo (int)$total; ?>;
            var publicadas = 0;
            var musicaAtual = null;

            // ── Utilitários ───────────────────────────────────────

            function toast(msg, erro) {
                var el = document.getElementById('cv-pr-toast');
                el.textContent = msg;
                el.className = 'cv-pr-toast' + (erro ? ' erro' : '');
                el.classList.add('show');
                setTimeout(function(){ el.classList.remove('show'); }, 3000);
            }

            function post(action, extra, cb) {
                var fd = new FormData();
                fd.append('action', action);
                fd.append('nonce', NONCE);
                for (var k in extra) { fd.append(k, extra[k]); }
                fetch(AJAX, {method:'POST', body:fd})
                    .then(function(r){ return r.json(); })
                    .then(cb)
                    .catch(function(){ toast('Erro de conexão.', true); });
            }

            function postForm(action, cb) {
                if (!musicaAtual) return;
                var fd = new FormData();
                fd.append('action', action);
                fd.append('nonce', NONCE);
                fd.append('musica_id', musicaAtual);
                fd.append('titulo',    document.getElementById('cv-pr-titulo').value);
                fd.append('conteudo',  document.getElementById('cv-pr-letra').value);
                fd.append('artista',   document.getElementById('cv-pr-artista').value);
                fd.append('compositor',document.getElementById('cv-pr-compositor').value);
                fd.append('album',     document.getElementById('cv-pr-album').value);
                fd.append('ano',       document.getElementById('cv-pr-ano').value);
                fd.append('_cv_youtube_url', document.getElementById('cv-pr-yt-url').value);
                fd.append('_cv_audio_url',   document.getElementById('cv-pr-audio-url').value);
                fd.append('_cv_descricao',   document.getElementById('cv-pr-descricao').value);
                fd.append('_cv_ativo',    document.getElementById('cv-pr-ativo').checked ? '1' : '0');
                fd.append('_cv_destaque', document.getElementById('cv-pr-destaque').checked ? '1' : '0');
                // Sentimentos
                document.querySelectorAll('#cv-pr-sents-checks input:checked').forEach(function(el){
                    fd.append('sentimentos[]', el.value);
                });
                fetch(AJAX, {method:'POST', body:fd})
                    .then(function(r){ return r.json(); })
                    .then(cb)
                    .catch(function(){ toast('Erro de conexão.', true); });
            }

            // ── Carregar música ───────────────────────────────────
            window.cvPrCarregar = function(id) {
                musicaAtual = id;
                // Marcar ativo na lista
                document.querySelectorAll('.cv-pr-item').forEach(function(el){ el.classList.remove('ativo'); });
                var li = document.getElementById('cv-pr-li-' + id);
                if (li) li.classList.add('ativo');

                // Mostrar loading
                document.getElementById('cv-pr-placeholder').style.display = 'none';
                document.getElementById('cv-pr-form-real').style.display = 'block';
                document.getElementById('cv-pr-titulo').value = '⏳ Carregando...';

                post('cv_pr_load_musica', {musica_id: id}, function(res) {
                    if (!res.success) { toast('Erro ao carregar música.', true); return; }
                    var d = res.data;

                    // Cabeçalho
                    document.getElementById('cv-pr-hdr-capa').src = d.capa_url || '';
                    document.getElementById('cv-pr-titulo').value = d.titulo || '';
                    document.getElementById('cv-pr-hdr-artista').textContent = d.artista || '';

                    var ytWrap = document.getElementById('cv-pr-yt-wrap');
                    var ytLink = document.getElementById('cv-pr-link-ext');
                    if (d.youtube_url) {
                        ytWrap.innerHTML = '<a href="' + d.youtube_url + '" target="_blank" class="cv-pr-yt-link">▶ Ver no YouTube</a>';
                        ytLink.href = d.youtube_url;
                        ytLink.style.display = 'inline-flex';
                    } else {
                        ytWrap.innerHTML = '';
                        ytLink.style.display = 'none';
                    }

                    // Campos
                    document.getElementById('cv-pr-artista').value    = d.artista || '';
                    document.getElementById('cv-pr-compositor').value = d.compositor || '';
                    document.getElementById('cv-pr-album').value      = d.album || '';
                    document.getElementById('cv-pr-ano').value        = d.ano || '';
                    document.getElementById('cv-pr-yt-url').value     = d.youtube_url || '';
                    document.getElementById('cv-pr-audio-url').value  = d.audio_url || '';
                    document.getElementById('cv-pr-descricao').value  = d.descricao || '';
                    document.getElementById('cv-pr-letra').value      = d.conteudo || '';

                    // Checkbox ativo/destaque
                    document.getElementById('cv-pr-ativo').checked    = d.ativo !== '0';
                    document.getElementById('cv-pr-destaque').checked = d.destaque === '1';
                    cvPrSyncCheck(document.getElementById('cv-pr-lbl-ativo'));
                    cvPrSyncCheck(document.getElementById('cv-pr-lbl-destaque'));

                    // Sentimentos
                    var sentEl = document.getElementById('cv-pr-sents-checks');
                    if (sentEl) {
                        sentEl.querySelectorAll('input').forEach(function(el){
                            el.checked = d.sentimentos.indexOf(parseInt(el.value)) !== -1;
                            cvPrToggleSent(el.closest('label'));
                        });
                    }
                });
            };

            // ── Toggle visual dos checks ──────────────────────────
            window.cvPrToggleCheck = function(label) {
                cvPrSyncCheck(label);
            };
            function cvPrSyncCheck(label) {
                if (!label) return;
                var cb = label.querySelector('input');
                if (!cb) return;
                if (cb.checked) label.classList.add('marcado');
                else label.classList.remove('marcado');
            }
            window.cvPrToggleSent = function(label) {
                if (!label) return;
                var cb  = label.querySelector('input');
                var cor = label.dataset.cor || '#B8700C';
                if (cb && cb.checked) {
                    label.style.background    = cor + '33';
                    label.style.borderColor   = cor;
                    label.style.color         = cor;
                } else {
                    label.style.background    = cor + '18';
                    label.style.borderColor   = cor + '44';
                    label.style.color         = cor;
                }
            };

            // ── Ações ─────────────────────────────────────────────
            window.cvPrSalvar = function() {
                postForm('cv_pr_salvar', function(res) {
                    if (res.success) toast('💾 Rascunho salvo!');
                    else toast('Erro ao salvar.', true);
                });
            };

            window.cvPrPublicar = function() {
                var btn = document.querySelector('.cv-pr-btn-publicar');
                btn.innerHTML = '<span class="cv-pr-spinner"></span> Publicando...';
                btn.disabled = true;

                postForm('cv_pr_publicar', function(res) {
                    btn.innerHTML = '🚀 Publicar música';
                    btn.disabled = false;
                    if (res.success) {
                        toast('✅ ' + document.getElementById('cv-pr-titulo').value + ' publicada!');
                        // Marcar como publicada na lista
                        var li = document.getElementById('cv-pr-li-' + musicaAtual);
                        if (li) {
                            li.classList.add('publicada');
                            li.classList.remove('ativo');
                            var chk = document.getElementById('cv-pr-chk-' + musicaAtual);
                            if (chk) chk.textContent = '✅';
                        }
                        publicadas++;
                        document.getElementById('cv-pr-count-pub').textContent = publicadas;
                        var pct = Math.round(publicadas / TOTAL * 100);
                        document.getElementById('cv-pr-prog').style.width = pct + '%';
                        // Ir para próxima automaticamente
                        cvPrProxima();
                    } else {
                        toast('Erro ao publicar.', true);
                    }
                });
            };

            window.cvPrPular = function() {
                toast('⏭ Pulado — próxima música');
                cvPrProxima();
            };

            window.cvPrExcluir = function() {
                if (!musicaAtual) return;
                var titulo = document.getElementById('cv-pr-titulo').value || 'esta música';
                if (!confirm('⚠️ Excluir permanentemente "' + titulo + '"?\n\nEsta ação não pode ser desfeita.')) return;

                var btn = document.querySelector('.cv-pr-btn-excluir');
                btn.textContent = '⏳ Excluindo...';
                btn.disabled = true;

                post('cv_pr_excluir', {musica_id: musicaAtual}, function(res) {
                    btn.textContent = '🗑 Excluir';
                    btn.disabled = false;
                    if (res.success) {
                        toast('🗑 "' + (res.data.titulo || titulo) + '" excluída.', false);
                        // Remover da lista
                        var li = document.getElementById('cv-pr-li-' + musicaAtual);
                        if (li) li.remove();
                        musicaAtual = null;
                        // Ir para próxima
                        cvPrProxima();
                    } else {
                        toast('Erro ao excluir.', true);
                    }
                });
            };

            function cvPrProxima() {
                // Encontrar próxima da lista que não está publicada
                var items = document.querySelectorAll('.cv-pr-item:not(.publicada):not(.ativo)');
                if (items.length > 0) {
                    var nextId = items[0].id.replace('cv-pr-li-', '');
                    cvPrCarregar(parseInt(nextId));
                    items[0].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                } else {
                    // Todas processadas
                    document.getElementById('cv-pr-form-real').style.display = 'none';
                    document.getElementById('cv-pr-placeholder').style.display = 'block';
                    document.querySelector('.cv-pr-placeholder-emoji').textContent = '🎉';
                    document.querySelector('.cv-pr-placeholder-txt').textContent = 'Fila processada! Todas as músicas foram revisadas.';
                }
            }

            // Carregar a primeira automaticamente se houver rascunhos
            if (RASCUNHOS.length > 0) {
                cvPrCarregar(RASCUNHOS[0].id);
            }
        })();
        </script>
        <?php
    }
}

CV_Publicacao_Rapida::init();
