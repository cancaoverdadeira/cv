<?php
// cancao-verdadeira/includes/admin/class-cv-publicacao-rapida.php
// Gerado em: 2026-06-27 11:00:00
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Publicação Acelerada de Músicas (v2.16.0)
// Funções : Fila de rascunhos importados do YouTube com formulário inline.
//           Preenche letra, MP3, descrição e sentimentos sem sair do
//           painel — publica com 1 clique.
// v2.26.0 : removidos os campos Gênero e Subcategoria (site todo sertanejo).
// v2.33.0 : CSS e JS em assets/css|js/admin-publicacao-rapida.*
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
        // Prioridade 20: o CSS da tela sai depois do admin.css (CV_Admin, prioridade 10).
        add_action( 'admin_enqueue_scripts',        array( __CLASS__, 'enqueue_assets' ), 20 );
    }

    // ── CSS e JS da tela (v2.33.0: saíram do <style>/<script> em linha) ──

    public static function enqueue_assets() {
        if ( 'cv-publicacao-rapida' !== sanitize_key( $_GET['page'] ?? '' ) ) {
            return;
        }
        wp_enqueue_style(
            'cv-publicacao-rapida',
            CV_PLUGIN_URL . 'assets/css/admin-publicacao-rapida.css',
            array(),
            CV_VERSION
        );
        // Só registra aqui: render_page() enfileira junto com os dados (window.cvPr).
        wp_register_script(
            'cv-publicacao-rapida',
            CV_PLUGIN_URL . 'assets/js/admin-publicacao-rapida.js',
            array(),
            CV_VERSION,
            true
        );
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
            'youtube_url'  => get_post_meta( $id, CV_Fields::YOUTUBE_URL, true ),
            'audio_url'    => get_post_meta( $id, CV_Fields::AUDIO_URL, true ),
            'artista'      => get_post_meta( $id, CV_Fields::ARTISTA, true ),
            'compositor'   => get_post_meta( $id, CV_Fields::COMPOSITOR, true ),
            'album'        => get_post_meta( $id, CV_Fields::ALBUM, true ),
            'ano'          => get_post_meta( $id, CV_Fields::ANO, true ),
            'descricao'    => get_post_meta( $id, CV_Fields::DESCRICAO, true ),
            'seo_title'    => get_post_meta( $id, '_cv_seo_title', true ),
            'ativo'        => get_post_meta( $id, CV_Fields::ATIVO, true ) ?: '1',
            'destaque'     => get_post_meta( $id, CV_Fields::DESTAQUE, true ),
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
                'artista'   => get_post_meta($rid,CV_Fields::ARTISTA,true) ?: '',
                'capa'      => get_the_post_thumbnail_url($rid,'thumbnail') ?: CV_PLUGIN_URL.'assets/img/default-cover.svg',
                'yt'        => get_post_meta($rid,CV_Fields::YOUTUBE_URL,true) ?: '',
                'tem_letra' => ! empty(trim($p->post_content)),
            );
        }
        ?>
        <div class="wrap" id="cv-pr-page">
        <?php echo CV_Admin::btn_voltar(); ?>

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

        <?php
        // JS da tela: assets/js/admin-publicacao-rapida.js (vai no rodapé).
        wp_enqueue_script( 'cv-publicacao-rapida' );
        wp_add_inline_script( 'cv-publicacao-rapida', 'window.cvPr = ' . wp_json_encode( array(
            'nonce'     => $nonce,
            'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
            'rascunhos' => $rascunhos_data,
            'total'     => (int) $total,
        ) ) . ';', 'before' );
    }
}

CV_Publicacao_Rapida::init();
