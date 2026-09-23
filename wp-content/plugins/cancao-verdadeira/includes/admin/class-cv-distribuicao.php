<?php
// cancao-verdadeira/includes/admin/class-cv-distribuicao.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Distribuição para plataformas digitais (v2.29.0)
// Motivo  : DistroKid, ONErpm, Tratore etc. não têm API aberta para artista
//           independente. O envio final é feito no site da distribuidora; este
//           módulo prepara tudo e acompanha: checklist dos dados obrigatórios,
//           status (não iniciada → preparando → enviada → publicada), ficha
//           pronta para copiar no formulário da distribuidora e os links das
//           plataformas, que aparecem na página da música ("Ouça também em").
// Onde    : metabox "🚀 Distribuição" na música + página admin cv-distribuicao
//           (botão na Central de Ações do Dashboard).
// Gerado  : 2026-09-23

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Distribuicao {

    const PREFIX = '_cv_dist_';

    public static function init() {
        add_action( 'add_meta_boxes',     array( __CLASS__, 'add_metabox' ) );
        add_action( 'save_post_musica',   array( __CLASS__, 'save' ) );
        add_action( 'admin_menu',         array( __CLASS__, 'register_page' ) );
        add_action( 'admin_post_cv_dist_ficha', array( __CLASS__, 'download_ficha' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'media' ) );
    }

    // ── Listas fixas ────────────────────────────────────────────────

    public static function status_list() {
        return array(
            'nao_iniciada' => array( '⚪', 'Não iniciada' ),
            'preparando'   => array( '🟡', 'Preparando' ),
            'enviada'      => array( '🔵', 'Enviada — aguardando' ),
            'publicada'    => array( '🟢', 'Publicada' ),
            'recusada'     => array( '🔴', 'Recusada — corrigir' ),
        );
    }

    public static function distribuidoras() {
        return array(
            ''         => '— escolher —',
            'onerpm'   => 'ONErpm (grátis, fica com 15% do áudio)',
            'distrokid'=> 'DistroKid (US$ 24,99/ano, 100% para você)',
            'tratore'  => 'Tratore (R$ 50 por lançamento)',
            'cdbaby'   => 'CD Baby',
            'tunecore' => 'TuneCore',
            'ditto'    => 'Ditto',
            'outra'    => 'Outra',
        );
    }

    public static function plataformas() {
        return array(
            'spotify' => 'Spotify',
            'deezer'  => 'Deezer',
            'apple'   => 'Apple Music',
            'youtube' => 'YouTube Music',
            'amazon'  => 'Amazon Music',
        );
    }

    // ── Dados ───────────────────────────────────────────────────────

    public static function get( $post_id ) {
        $d = array(
            'status'        => get_post_meta( $post_id, self::PREFIX . 'status', true ) ?: 'nao_iniciada',
            'distribuidora' => get_post_meta( $post_id, self::PREFIX . 'distribuidora', true ),
            'data_envio'    => get_post_meta( $post_id, self::PREFIX . 'data_envio', true ),
            'lancamento'    => get_post_meta( $post_id, self::PREFIX . 'lancamento', true ),
            'interprete'    => get_post_meta( $post_id, self::PREFIX . 'interprete', true ),
            'compositores'  => get_post_meta( $post_id, self::PREFIX . 'compositores', true ),
            'isrc'          => get_post_meta( $post_id, self::PREFIX . 'isrc', true ),
            'upc'           => get_post_meta( $post_id, self::PREFIX . 'upc', true ),
            'wav'           => get_post_meta( $post_id, self::PREFIX . 'wav', true ),
            'capa'          => get_post_meta( $post_id, self::PREFIX . 'capa', true ),
            'explicita'     => get_post_meta( $post_id, self::PREFIX . 'explicita', true ),
            'direitos'      => get_post_meta( $post_id, self::PREFIX . 'direitos', true ),
            'links'         => (array) get_post_meta( $post_id, self::PREFIX . 'links', true ),
        );
        // Padrões vindos da ficha da música
        if ( '' === $d['interprete'] )   { $d['interprete']   = get_post_meta( $post_id, '_cv_artista', true ) ?: 'Canção Verdadeira'; }
        if ( '' === $d['compositores'] ) {
            $c = get_post_meta( $post_id, '_cv_compositor', true );
            $d['compositores'] = $c ? $c . ' — 100%' : '';
        }
        return $d;
    }

    // Checklist de envio: cada item = array( ok, rótulo, dica ).
    public static function checklist( $post_id ) {
        $d    = self::get( $post_id );
        $capa = self::capa_info( $d['capa'] );
        $dias = $d['lancamento'] ? (int) floor( ( strtotime( $d['lancamento'] ) - current_time( 'timestamp' ) ) / DAY_IN_SECONDS ) : null;

        return array(
            'titulo'      => array( '' !== trim( get_the_title( $post_id ) ), 'Título da música', '' ),
            'interprete'  => array( '' !== trim( $d['interprete'] ), 'Intérprete (nome do artista nas plataformas)', 'Usa o Artista da música se ficar vazio.' ),
            'compositores'=> array( '' !== trim( $d['compositores'] ), 'Compositores e divisão (%)', 'Ex.: Eduardo Marques — 100%. Precisa bater com o cadastro no ECAD/associação.' ),
            'letra'       => array( class_exists( 'CV_Fields' ) && CV_Fields::has_letra( $post_id ), 'Letra completa', 'As plataformas mostram a letra (Spotify via Musixmatch).' ),
            'wav'         => array( (bool) preg_match( '/\.wav(\?|$)/i', (string) $d['wav'] ), 'Áudio em WAV (16 bits, 44,1 kHz)', 'MP3 é recusado pela maioria das distribuidoras.' ),
            'capa'        => array( $capa['ok'], 'Capa quadrada de 3000×3000 px (JPG ou PNG)', $capa['msg'] ),
            'lancamento'  => array( null !== $dias && $dias >= 14, 'Lançamento com 14+ dias de antecedência', null === $dias ? 'Defina a data de lançamento.' : ( $dias < 14 ? "Faltam só {$dias} dias — pouco tempo para entrar em playlists editoriais." : '' ) ),
            'direitos'    => array( '1' === $d['direitos'], 'Direitos da gravação e autorização dos compositores', 'Confirme que você é dono da gravação (fonograma) ou tem autorização.' ),
        );
    }

    public static function progresso( $post_id ) {
        $c  = self::checklist( $post_id );
        $ok = count( array_filter( $c, function( $i ) { return $i[0]; } ) );
        return array( $ok, count( $c ) );
    }

    private static function capa_info( $url ) {
        if ( ! $url ) { return array( 'ok' => false, 'msg' => 'Escolha a capa na Biblioteca de Mídia.' ); }
        $id = attachment_url_to_postid( $url );
        $m  = $id ? wp_get_attachment_metadata( $id ) : null;
        if ( ! $m || empty( $m['width'] ) ) { return array( 'ok' => false, 'msg' => 'Envie a capa pela Biblioteca de Mídia para o sistema conferir o tamanho.' ); }
        $w = (int) $m['width']; $h = (int) $m['height'];
        if ( $w !== $h )  { return array( 'ok' => false, 'msg' => "A capa tem {$w}×{$h} px e precisa ser quadrada." ); }
        if ( $w < 3000 )  { return array( 'ok' => false, 'msg' => "A capa tem {$w}×{$h} px; o mínimo é 3000×3000." ); }
        return array( 'ok' => true, 'msg' => "{$w}×{$h} px" );
    }

    // ── Metabox na música ───────────────────────────────────────────

    public static function media( $hook ) {
        $screen = get_current_screen();
        if ( $screen && 'musica' === $screen->post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            wp_enqueue_media();
        }
    }

    public static function add_metabox() {
        add_meta_box( 'cv_distribuicao', '🚀 Distribuição (Spotify, Deezer…)', array( __CLASS__, 'render_metabox' ), 'musica', 'normal', 'low' );
    }

    public static function render_metabox( $post ) {
        wp_nonce_field( 'cv_dist_save', 'cv_dist_nonce' );
        $d = self::get( $post->ID );
        list( $ok, $total ) = self::progresso( $post->ID );
        $f = function( $k ) { return 'cv_dist_' . $k; };
        ?>
        <div class="cv-dist-box">
            <p style="margin-top:0">
                <strong>Checklist: <?php echo (int) $ok; ?>/<?php echo (int) $total; ?></strong> —
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-distribuicao' ) ); ?>">ver todas as músicas</a>
                <?php if ( $post->ID && 'auto-draft' !== $post->post_status ) : ?>
                · <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cv_dist_ficha&id=' . $post->ID ), 'cv_dist_ficha_' . $post->ID ) ); ?>">📦 baixar ficha para envio</a>
                <?php endif; ?>
            </p>
            <ul class="cv-dist-check">
                <?php foreach ( self::checklist( $post->ID ) as $item ) : ?>
                <li><?php echo $item[0] ? '✅' : '⬜'; ?> <?php echo esc_html( $item[1] ); ?>
                    <?php if ( $item[2] && ! $item[0] ) : ?><small> — <?php echo esc_html( $item[2] ); ?></small><?php endif; ?></li>
                <?php endforeach; ?>
            </ul>

            <div class="cv-dist-grid">
                <label>Status
                    <select name="<?php echo $f( 'status' ); ?>">
                        <?php foreach ( self::status_list() as $k => $s ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $d['status'], $k ); ?>><?php echo esc_html( $s[0] . ' ' . $s[1] ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label>Distribuidora
                    <select name="<?php echo $f( 'distribuidora' ); ?>">
                        <?php foreach ( self::distribuidoras() as $k => $n ) : ?>
                        <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $d['distribuidora'], $k ); ?>><?php echo esc_html( $n ); ?></option>
                        <?php endforeach; ?>
                    </select></label>
                <label>Data de envio <input type="date" name="<?php echo $f( 'data_envio' ); ?>" value="<?php echo esc_attr( $d['data_envio'] ); ?>"></label>
                <label>Data de lançamento <input type="date" name="<?php echo $f( 'lancamento' ); ?>" value="<?php echo esc_attr( $d['lancamento'] ); ?>"></label>
                <label>Intérprete <input type="text" name="<?php echo $f( 'interprete' ); ?>" value="<?php echo esc_attr( $d['interprete'] ); ?>"></label>
                <label>Compositores e divisão <input type="text" name="<?php echo $f( 'compositores' ); ?>" value="<?php echo esc_attr( $d['compositores'] ); ?>" placeholder="Eduardo Marques — 100%"></label>
                <label class="cv-dist-full">Áudio WAV (URL)
                    <span class="cv-dist-media"><input type="url" id="cv_dist_wav" name="<?php echo $f( 'wav' ); ?>" value="<?php echo esc_attr( $d['wav'] ); ?>">
                    <button type="button" class="button cv-dist-pick" data-target="cv_dist_wav" data-type="audio">📁 Biblioteca</button></span></label>
                <label class="cv-dist-full">Capa 3000×3000 (URL)
                    <span class="cv-dist-media"><input type="url" id="cv_dist_capa" name="<?php echo $f( 'capa' ); ?>" value="<?php echo esc_attr( $d['capa'] ); ?>">
                    <button type="button" class="button cv-dist-pick" data-target="cv_dist_capa" data-type="image">📁 Biblioteca</button></span></label>
                <label>ISRC (a distribuidora gera, se vazio) <input type="text" name="<?php echo $f( 'isrc' ); ?>" value="<?php echo esc_attr( $d['isrc'] ); ?>" placeholder="BR-XXX-26-00001"></label>
                <label>UPC (código do lançamento) <input type="text" name="<?php echo $f( 'upc' ); ?>" value="<?php echo esc_attr( $d['upc'] ); ?>"></label>
                <label class="cv-dist-inline"><input type="checkbox" name="<?php echo $f( 'explicita' ); ?>" value="1" <?php checked( $d['explicita'], '1' ); ?>> Letra com conteúdo explícito</label>
                <label class="cv-dist-inline"><input type="checkbox" name="<?php echo $f( 'direitos' ); ?>" value="1" <?php checked( $d['direitos'], '1' ); ?>> Tenho os direitos da gravação e autorização dos compositores</label>
            </div>

            <p style="margin:14px 0 6px"><strong>Links nas plataformas</strong> (depois de publicada; aparecem na página da música)</p>
            <div class="cv-dist-grid">
                <?php foreach ( self::plataformas() as $k => $n ) : ?>
                <label><?php echo esc_html( $n ); ?> <input type="url" name="cv_dist_links[<?php echo esc_attr( $k ); ?>]" value="<?php echo esc_attr( $d['links'][ $k ] ?? '' ); ?>" placeholder="https://"></label>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
        .cv-dist-check { margin:0 0 14px; columns:2; column-gap:24px; }
        .cv-dist-check li { break-inside:avoid; margin-bottom:6px; }
        .cv-dist-check small { color:#8A6A55; }
        .cv-dist-grid { display:grid; grid-template-columns:1fr 1fr; gap:10px 16px; }
        .cv-dist-grid label { display:flex; flex-direction:column; gap:4px; font-weight:600; font-size:12px; }
        .cv-dist-grid input[type=text], .cv-dist-grid input[type=url], .cv-dist-grid input[type=date], .cv-dist-grid select { width:100%; }
        .cv-dist-full { grid-column:1/-1; }
        .cv-dist-inline { flex-direction:row !important; align-items:center; font-weight:400 !important; grid-column:1/-1; }
        .cv-dist-media { display:flex; gap:6px; } .cv-dist-media input { flex:1; }
        @media (max-width:782px){ .cv-dist-grid{grid-template-columns:1fr;} .cv-dist-check{columns:1;} }
        </style>
        <script>
        jQuery(function($){
            $(document).on('click', '.cv-dist-pick', function(e){
                e.preventDefault();
                if (!window.wp || !wp.media) { return; }
                var alvo = $('#' + $(this).data('target'));
                var frame = wp.media({ title: 'Escolher arquivo', button: { text: 'Usar este arquivo' }, multiple: false, library: { type: $(this).data('type') } });
                frame.on('select', function(){ alvo.val( frame.state().get('selection').first().toJSON().url ); });
                frame.open();
            });
        });
        </script>
        <?php
    }

    public static function save( $post_id ) {
        if ( ! isset( $_POST['cv_dist_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cv_dist_nonce'] ) ), 'cv_dist_save' ) ) { return; }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
        if ( wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }

        $status = sanitize_key( $_POST['cv_dist_status'] ?? 'nao_iniciada' );
        if ( ! array_key_exists( $status, self::status_list() ) ) { $status = 'nao_iniciada'; }
        $dist = sanitize_key( $_POST['cv_dist_distribuidora'] ?? '' );
        if ( ! array_key_exists( $dist, self::distribuidoras() ) ) { $dist = ''; }

        $campos = array(
            'status'        => $status,
            'distribuidora' => $dist,
            'data_envio'    => self::data( $_POST['cv_dist_data_envio'] ?? '' ),
            'lancamento'    => self::data( $_POST['cv_dist_lancamento'] ?? '' ),
            'interprete'    => sanitize_text_field( wp_unslash( $_POST['cv_dist_interprete'] ?? '' ) ),
            'compositores'  => sanitize_text_field( wp_unslash( $_POST['cv_dist_compositores'] ?? '' ) ),
            'isrc'          => strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) wp_unslash( $_POST['cv_dist_isrc'] ?? '' ) ) ),
            'upc'           => preg_replace( '/\D/', '', (string) wp_unslash( $_POST['cv_dist_upc'] ?? '' ) ),
            'wav'           => esc_url_raw( wp_unslash( $_POST['cv_dist_wav'] ?? '' ) ),
            'capa'          => esc_url_raw( wp_unslash( $_POST['cv_dist_capa'] ?? '' ) ),
            'explicita'     => isset( $_POST['cv_dist_explicita'] ) ? '1' : '0',
            'direitos'      => isset( $_POST['cv_dist_direitos'] ) ? '1' : '0',
        );
        foreach ( $campos as $k => $v ) { update_post_meta( $post_id, self::PREFIX . $k, $v ); }

        $links = array();
        foreach ( self::plataformas() as $k => $n ) {
            $u = esc_url_raw( wp_unslash( $_POST['cv_dist_links'][ $k ] ?? '' ) );
            if ( $u ) { $links[ $k ] = $u; }
        }
        update_post_meta( $post_id, self::PREFIX . 'links', $links );
    }

    private static function data( $v ) {
        $v = sanitize_text_field( wp_unslash( $v ) );
        return preg_match( '/^\d{4}-\d{2}-\d{2}$/', $v ) ? $v : '';
    }

    // ── Ficha para envio (texto para copiar no formulário) ──────────

    public static function download_ficha() {
        $id = absint( $_GET['id'] ?? 0 );
        check_admin_referer( 'cv_dist_ficha_' . $id );
        if ( ! $id || 'musica' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) { wp_die( 'Música inválida.' ); }

        $d     = self::get( $id );
        $dists = self::distribuidoras();
        $l     = array();
        $l[]   = 'FICHA PARA ENVIO À DISTRIBUIDORA — Canção Verdadeira';
        $l[]   = 'Gerada em ' . wp_date( 'd/m/Y H:i' );
        $l[]   = str_repeat( '=', 60 );
        $l[]   = 'Título ...............: ' . get_the_title( $id );
        $l[]   = 'Intérprete ...........: ' . $d['interprete'];
        $l[]   = 'Compositores (%) .....: ' . $d['compositores'];
        $l[]   = 'Gênero ...............: Sertanejo';
        $l[]   = 'Idioma ...............: Português (Brasil)';
        $l[]   = 'Conteúdo explícito ...: ' . ( '1' === $d['explicita'] ? 'Sim' : 'Não' );
        $l[]   = 'Data de lançamento ...: ' . ( $d['lancamento'] ? wp_date( 'd/m/Y', strtotime( $d['lancamento'] ) ) : '(definir)' );
        $l[]   = 'Distribuidora ........: ' . ( $d['distribuidora'] ? $dists[ $d['distribuidora'] ] : '(escolher)' );
        $l[]   = 'ISRC .................: ' . ( $d['isrc'] ?: '(a distribuidora gera)' );
        $l[]   = 'UPC ..................: ' . ( $d['upc'] ?: '(a distribuidora gera)' );
        $l[]   = 'Álbum ................: ' . ( get_post_meta( $id, '_cv_album', true ) ?: 'Single' );
        $l[]   = 'Ano ..................: ' . ( get_post_meta( $id, '_cv_ano', true ) ?: wp_date( 'Y' ) );
        $l[]   = '';
        $l[]   = 'ARQUIVOS';
        $l[]   = 'Áudio WAV ............: ' . ( $d['wav'] ?: '(faltando)' );
        $l[]   = 'Capa 3000x3000 .......: ' . ( $d['capa'] ?: '(faltando)' );
        $l[]   = '';
        $l[]   = 'CHECKLIST';
        foreach ( self::checklist( $id ) as $item ) {
            $l[] = ( $item[0] ? '[x] ' : '[ ] ' ) . $item[1] . ( $item[2] && ! $item[0] ? ' — ' . $item[2] : '' );
        }
        $l[]   = '';
        $l[]   = 'LETRA';
        $l[]   = str_repeat( '-', 60 );
        $l[]   = class_exists( 'CV_Fields' ) ? CV_Fields::letra( $id ) : wp_strip_all_tags( get_post_field( 'post_content', $id ) );

        $nome = 'ficha-distribuicao-' . sanitize_title( get_the_title( $id ) ) . '.txt';
        nocache_headers();
        header( 'Content-Type: text/plain; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="' . $nome . '"' );
        echo "\xEF\xBB\xBF" . implode( "\r\n", $l ); // BOM: acentos certos no Bloco de Notas
        exit;
    }

    // ── Página "Distribuição" ───────────────────────────────────────

    public static function register_page() {
        add_submenu_page( null, 'Distribuição', 'Distribuição', 'manage_options', 'cv-distribuicao', array( __CLASS__, 'render_page' ) );
    }

    public static function render_page() {
        $filtro = sanitize_key( $_GET['status'] ?? '' );
        $musicas = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => array( 'publish', 'draft', 'future', 'pending' ),
            'posts_per_page' => 300,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        $status = self::status_list();
        $contagem = array_fill_keys( array_keys( $status ), 0 );
        $linhas = array();
        foreach ( $musicas as $m ) {
            $d = self::get( $m->ID );
            $contagem[ $d['status'] ] = ( $contagem[ $d['status'] ] ?? 0 ) + 1;
            if ( $filtro && $filtro !== $d['status'] ) { continue; }
            $linhas[] = array( $m, $d, self::progresso( $m->ID ) );
        }
        $dists = self::distribuidoras();
        ?>
        <div class="wrap cv-admin-wrap" style="max-width:1150px">
            <?php echo class_exists( 'CV_Admin' ) ? CV_Admin::btn_voltar() : ''; ?>
            <h1 style="margin-bottom:4px">🚀 Distribuição para plataformas</h1>
            <p style="color:#8A6A55;margin-top:0;max-width:820px">
                Prepare cada música para Spotify, Deezer, Apple Music, YouTube Music e Amazon.
                O envio final é feito no site da distribuidora (nenhuma tem integração aberta para artistas):
                complete o checklist na tela da música, baixe a <strong>ficha</strong> e copie os dados no formulário dela.
                Depois volte e marque o status e os links.
            </p>

            <p class="subsubsub" style="float:none;margin:10px 0 14px">
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-distribuicao' ) ); ?>" <?php echo $filtro ? '' : 'class="current"'; ?>>Todas (<?php echo count( $musicas ); ?>)</a>
                <?php foreach ( $status as $k => $s ) : ?>
                 | <a href="<?php echo esc_url( add_query_arg( 'status', $k, admin_url( 'admin.php?page=cv-distribuicao' ) ) ); ?>" <?php echo $filtro === $k ? 'class="current"' : ''; ?>><?php echo esc_html( $s[0] . ' ' . $s[1] ); ?> (<?php echo (int) $contagem[ $k ]; ?>)</a>
                <?php endforeach; ?>
            </p>

            <table class="widefat striped">
                <thead><tr>
                    <th>Música</th><th>Status</th><th>Distribuidora</th><th>Lançamento</th><th>Checklist</th><th>Plataformas</th><th>Ações</th>
                </tr></thead>
                <tbody>
                <?php if ( ! $linhas ) : ?>
                    <tr><td colspan="7" style="padding:24px;text-align:center;color:#8A6A55">Nenhuma música neste filtro.</td></tr>
                <?php endif; ?>
                <?php foreach ( $linhas as $row ) :
                    list( $m, $d, $p ) = $row;
                    $pct = $p[1] ? round( $p[0] / $p[1] * 100 ) : 0;
                    $cor = $pct >= 100 ? '#2F7B2F' : ( $pct >= 60 ? '#B8700C' : '#C0392B' );
                ?>
                    <tr>
                        <td><strong><?php echo esc_html( $m->post_title ); ?></strong><?php echo 'publish' !== $m->post_status ? ' <small style="color:#8A6A55">(rascunho no site)</small>' : ''; ?></td>
                        <td><?php echo esc_html( $status[ $d['status'] ][0] . ' ' . $status[ $d['status'] ][1] ); ?></td>
                        <td><?php echo esc_html( $d['distribuidora'] ? strtok( $dists[ $d['distribuidora'] ], ' (' ) : '—' ); ?></td>
                        <td><?php echo $d['lancamento'] ? esc_html( wp_date( 'd/m/Y', strtotime( $d['lancamento'] ) ) ) : '—'; ?></td>
                        <td>
                            <span style="display:inline-block;width:70px;height:6px;background:#F3E6D3;border-radius:3px;vertical-align:middle">
                                <span style="display:block;height:6px;width:<?php echo (int) $pct; ?>%;background:<?php echo esc_attr( $cor ); ?>;border-radius:3px"></span></span>
                            <small><?php echo (int) $p[0]; ?>/<?php echo (int) $p[1]; ?></small>
                        </td>
                        <td><?php echo $d['links'] ? esc_html( implode( ', ', array_intersect_key( self::plataformas(), $d['links'] ) ) ) : '—'; ?></td>
                        <td style="white-space:nowrap">
                            <a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $m->ID ) . '#cv_distribuicao' ); ?>">✏️ Preparar</a>
                            <a class="button button-small" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=cv_dist_ficha&id=' . $m->ID ), 'cv_dist_ficha_' . $m->ID ) ); ?>">📦 Ficha</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top:22px;padding:16px 18px;background:#FFFFFF;border:1px solid #EADBC6;border-radius:10px;max-width:820px">
                <strong style="color:#7B3A22">Passo a passo de um lançamento</strong>
                <ol style="margin:8px 0 0 18px;color:#6B4C3B">
                    <li>Na música: complete o checklist (WAV, capa 3000×3000, compositores com %, data de lançamento com 14+ dias).</li>
                    <li>Marque o status <em>Preparando</em> e baixe a <strong>📦 Ficha</strong>.</li>
                    <li>No site da distribuidora: crie o lançamento e copie os dados da ficha; envie o WAV e a capa.</li>
                    <li>Marque <em>Enviada</em> com a data de envio.</li>
                    <li>Quando sair nas plataformas: marque <em>Publicada</em> e cole os links — eles aparecem na página da música.</li>
                    <li>Direito autoral de execução: confira se a música está registrada na sua associação (ABRAMUS, UBC…) com o mesmo ISRC.</li>
                </ol>
            </div>
        </div>
        <?php
    }

    // ── Site: "Ouça também em" na página da música ──────────────────

    public static function links_html( $post_id ) {
        $d = self::get( $post_id );
        if ( 'publicada' !== $d['status'] || empty( $d['links'] ) ) { return ''; }
        $out = '';
        foreach ( self::plataformas() as $k => $nome ) {
            if ( empty( $d['links'][ $k ] ) ) { continue; }
            $out .= '<a class="cv-dist-link cv-dist-' . esc_attr( $k ) . '" href="' . esc_url( $d['links'][ $k ] ) . '" target="_blank" rel="noopener">' . esc_html( $nome ) . '</a>';
        }
        return $out ? '<div class="cv-dist-links"><span class="cv-dist-links-titulo">🎧 Ouça também em</span>' . $out . '</div>' : '';
    }
}

CV_Distribuicao::init();
