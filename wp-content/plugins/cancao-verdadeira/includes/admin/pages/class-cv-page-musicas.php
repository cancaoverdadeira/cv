<?php
// cancao-verdadeira/includes/admin/pages/class-cv-page-musicas.php
// Criado em: 24/09/2026 (plugin v2.42.0) — pedido do Eduardo no 1º teste.
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Tela "🗂 Gerenciar músicas" (admin.php?page=cv-musicas): todas as músicas
// numa tabela, com tudo o que já foi cadastrado para cada uma (campos, plays,
// favoritos, notas, playlists, comentários, sentimentos, capa, MP3, letra).
// Ações por música: ver os dados, editar, "Excluir de vez" (a limpeza completa
// é feita por CV_Exclusao) e "Excluir e reimportar do YouTube" (apaga tudo e
// cria o rascunho de novo com título e capa atuais do vídeo).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Page_Musicas {

    // Status mostrados na tela (auto-draft = rascunho automático do WordPress, fica de fora)
    private static $status = array(
        'publish' => 'Publicada',
        'future'  => 'Agendada',
        'draft'   => 'Rascunho',
        'pending' => 'Pendente',
        'private' => 'Privada',
        'trash'   => 'Lixeira',
    );

    public static function init() {
        add_action( 'wp_ajax_cv_musicas_dados',      array( __CLASS__, 'ajax_dados' ) );
        add_action( 'wp_ajax_cv_musicas_excluir',    array( __CLASS__, 'ajax_excluir' ) );
        add_action( 'wp_ajax_cv_musicas_reimportar', array( __CLASS__, 'ajax_reimportar' ) );
    }

    // ── Dados ────────────────────────────────────────────────────

    // Contagens nas tabelas próprias, de uma vez para todas as músicas.
    private static function contagens() {
        global $wpdb;
        $p = $wpdb->prefix;
        $consultas = array(
            'plays'       => "SELECT music_id AS id, COUNT(*) AS n FROM {$p}cv_plays_log GROUP BY music_id",
            'favoritos'   => "SELECT music_id AS id, COUNT(*) AS n FROM {$p}cv_favorites GROUP BY music_id",
            'notas'       => "SELECT music_id AS id, COUNT(*) AS n FROM {$p}cv_ratings GROUP BY music_id",
            'playlists'   => "SELECT music_id AS id, COUNT(*) AS n FROM {$p}cv_playlist_items GROUP BY music_id",
            'comentarios' => "SELECT music_id AS id, COUNT(*) AS n FROM {$p}cv_lyric_comments GROUP BY music_id",
            'sentimentos' => "SELECT musica_id AS id, COUNT(*) AS n FROM {$p}cv_musica_sentimentos GROUP BY musica_id",
        );
        $out = array();
        foreach ( $consultas as $chave => $sql ) {
            foreach ( (array) $wpdb->get_results( $sql ) as $r ) {
                $out[ (int) $r->id ][ $chave ] = (int) $r->n;
            }
        }
        return $out;
    }

    // ── Tela ─────────────────────────────────────────────────────

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }

        $musicas = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => array_keys( self::$status ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );
        $cont = self::contagens();

        $por_status = array();
        foreach ( $musicas as $m ) {
            $por_status[ $m->post_status ] = ( $por_status[ $m->post_status ] ?? 0 ) + 1;
        }

        wp_enqueue_script( 'cv-admin-musicas', CV_PLUGIN_URL . 'assets/js/admin-musicas.js', array( 'jquery' ), CV_VERSION, true );
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <?php echo CV_Admin::btn_voltar(); ?>
            <div class="cv-admin-header">
                <h1>🗂 Gerenciar músicas</h1>
                <p class="cv-admin-subtitle">
                    <?php echo count( $musicas ); ?> música(s)
                    <?php foreach ( $por_status as $st => $n ) : ?>
                        • <?php echo (int) $n . ' ' . esc_html( mb_strtolower( self::$status[ $st ] ) ); ?>
                    <?php endforeach; ?>
                </p>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-youtube-import' ) ); ?>" class="cv-btn cv-btn-primary" style="margin-left:auto">▶ Importar do YouTube</a>
            </div>
            <div id="cv-mus-msg" class="cv-action-message" style="display:none"></div>

            <div class="cv-section">
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
                    <input type="search" id="cv-mus-busca" class="cv-input" placeholder="🔎 Procurar pelo título..." style="max-width:320px">
                    <select id="cv-mus-status" class="cv-input" style="max-width:200px">
                        <option value="">Todos os status</option>
                        <?php foreach ( self::$status as $st => $rotulo ) : if ( empty( $por_status[ $st ] ) ) { continue; } ?>
                        <option value="<?php echo esc_attr( $st ); ?>"><?php echo esc_html( $rotulo ); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <table class="cv-table" id="cv-mus-tabela">
                    <thead>
                        <tr>
                            <th style="width:56px">Capa</th>
                            <th>Música</th>
                            <th>Status</th>
                            <th title="Letra / MP3 / YouTube">Letra · MP3 · YouTube</th>
                            <th style="text-align:center" title="Plays registrados">▶</th>
                            <th style="text-align:center" title="Favoritos">❤</th>
                            <th style="text-align:center" title="Avaliações">⭐</th>
                            <th style="text-align:center" title="Em playlists">📋</th>
                            <th style="text-align:right">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if ( empty( $musicas ) ) : ?>
                        <tr><td colspan="9" style="text-align:center;padding:24px;color:#6B4C3B">Nenhuma música cadastrada. Use "▶ Importar do YouTube" para começar.</td></tr>
                    <?php endif; ?>
                    <?php foreach ( $musicas as $m ) :
                        $id    = (int) $m->ID;
                        $yt    = CV_Fields::youtube_id( (string) get_post_meta( $id, CV_Fields::YOUTUBE_URL, true ) );
                        $mp3   = (string) get_post_meta( $id, CV_Fields::AUDIO_URL, true );
                        $capa  = CV_Fields::cover_url( $id );
                        $c     = $cont[ $id ] ?? array();
                        $ok    = '<span style="color:#1f8a4c;font-weight:700">✓</span>';
                        $nao   = '<span style="color:#C9A27E">—</span>';
                    ?>
                        <tr class="cv-mus-linha" data-id="<?php echo $id; ?>" data-status="<?php echo esc_attr( $m->post_status ); ?>" data-titulo="<?php echo esc_attr( mb_strtolower( $m->post_title ) ); ?>" data-yt="<?php echo esc_attr( $yt ); ?>">
                            <td><?php if ( $capa ) : ?><img src="<?php echo esc_url( $capa ); ?>" alt="" style="width:48px;height:48px;object-fit:cover;border-radius:6px"><?php endif; ?></td>
                            <td>
                                <strong><?php echo esc_html( $m->post_title ?: '(sem título)' ); ?></strong><br>
                                <small style="color:#8A6A55">ID <?php echo $id; ?> · <?php echo esc_html( get_the_date( 'd/m/Y', $m ) ); ?></small>
                            </td>
                            <td><?php echo esc_html( self::$status[ $m->post_status ] ?? $m->post_status ); ?></td>
                            <td><?php echo ( CV_Fields::has_letra( $id ) ? $ok : $nao ) . ' · ' . ( $mp3 ? $ok : $nao ) . ' · ' . ( $yt ? '<a href="https://www.youtube.com/watch?v=' . esc_attr( $yt ) . '" target="_blank" rel="noopener">' . esc_html( $yt ) . '</a>' : $nao ); ?></td>
                            <td style="text-align:center"><?php echo (int) ( $c['plays'] ?? 0 ); ?></td>
                            <td style="text-align:center"><?php echo (int) ( $c['favoritos'] ?? 0 ); ?></td>
                            <td style="text-align:center"><?php echo (int) ( $c['notas'] ?? 0 ); ?></td>
                            <td style="text-align:center"><?php echo (int) ( $c['playlists'] ?? 0 ); ?></td>
                            <td style="text-align:right;white-space:nowrap">
                                <button type="button" class="cv-btn cv-btn-secondary cv-mus-ver" title="Ver todos os dados">👁 Dados</button>
                                <a class="cv-btn cv-btn-secondary" href="<?php echo esc_url( get_edit_post_link( $id, 'raw' ) ); ?>" title="Editar">✏️</a>
                                <?php if ( $yt ) : ?>
                                <button type="button" class="cv-btn cv-btn-outline cv-mus-reimportar" title="Excluir tudo e importar de novo do YouTube">♻ Reimportar</button>
                                <?php endif; ?>
                                <button type="button" class="cv-btn cv-mus-excluir" style="background:#C0392B;color:#fff" title="Excluir de vez, com todos os dados">🗑</button>
                            </td>
                        </tr>
                        <tr class="cv-mus-detalhe" data-id="<?php echo $id; ?>" style="display:none"><td colspan="9"></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="font-size:12px;color:#8A6A55;margin-top:14px">
                    🗑 <strong>Excluir de vez</strong> apaga a música e tudo ligado a ela (plays, favoritos, notas, playlists, comentários, sentimentos e a capa). Não dá para desfazer.<br>
                    ♻ <strong>Reimportar</strong> faz o mesmo e depois cria a música de novo, em rascunho, com o título e a capa atuais do vídeo no YouTube.
                </p>
            </div>
        </div>
        <?php
    }

    // ── AJAX ─────────────────────────────────────────────────────

    private static function checar() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
        $id   = absint( $_POST['musica_id'] ?? 0 );
        $post = get_post( $id );
        if ( ! $post || 'musica' !== $post->post_type ) {
            wp_send_json_error( array( 'message' => 'Música não encontrada.' ) );
        }
        return $post;
    }

    // Todos os dados cadastrados de uma música (para o botão "👁 Dados").
    public static function ajax_dados() {
        $post = self::checar();
        $id   = (int) $post->ID;

        $campos = array();
        foreach ( get_post_meta( $id ) as $chave => $valores ) {
            if ( 0 === strpos( $chave, '_edit_' ) || 0 === strpos( $chave, '_wp_old' ) ) { continue; }
            $v = maybe_unserialize( $valores[0] );
            if ( is_array( $v ) || is_object( $v ) ) { $v = wp_json_encode( $v, JSON_UNESCAPED_UNICODE ); }
            $v = (string) $v;
            $campos[] = array( 'campo' => $chave, 'valor' => mb_strlen( $v ) > 200 ? mb_substr( $v, 0, 200 ) . '…' : $v );
        }

        $sent = array();
        if ( class_exists( 'CV_Sentimentos' ) ) {
            foreach ( (array) CV_Sentimentos::get_for_musica( $id ) as $s ) { $sent[] = $s->icone . ' ' . $s->nome; }
        }
        $cont = self::contagens();

        wp_send_json_success( array(
            'titulo'      => $post->post_title,
            'status'      => self::$status[ $post->post_status ] ?? $post->post_status,
            'link'        => get_permalink( $id ),
            'letra'       => CV_Fields::has_letra( $id ) ? mb_strlen( wp_strip_all_tags( $post->post_content ) ) . ' caracteres' : 'sem letra',
            'sentimentos' => $sent,
            'contagens'   => $cont[ $id ] ?? array(),
            'campos'      => $campos,
        ) );
    }

    public static function ajax_excluir() {
        $post   = self::checar();
        $titulo = $post->post_title;
        if ( ! wp_delete_post( $post->ID, true ) ) { // CV_Exclusao limpa as tabelas cv_* e a capa
            wp_send_json_error( array( 'message' => 'Não foi possível excluir.' ) );
        }
        wp_send_json_success( array( 'message' => '"' . $titulo . '" foi excluída com todos os dados.' ) );
    }

    public static function ajax_reimportar() {
        $post = self::checar();
        $url  = (string) get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );
        $vid  = CV_Fields::youtube_id( $url );
        if ( ! $vid ) {
            wp_send_json_error( array( 'message' => 'Esta música não tem link do YouTube.' ) );
        }

        // Título atual no YouTube; se o YouTube não responder, mantém o título antigo.
        $titulo = CV_Youtube_Import::titulo_do_youtube( $vid );
        if ( '' === $titulo ) { $titulo = $post->post_title; }

        if ( ! wp_delete_post( $post->ID, true ) ) {
            wp_send_json_error( array( 'message' => 'Não foi possível excluir a versão antiga.' ) );
        }

        $novo = CV_Youtube_Import::criar_musica( 'https://www.youtube.com/watch?v=' . $vid, $titulo, $vid );
        if ( is_wp_error( $novo ) ) {
            wp_send_json_error( array( 'message' => 'A versão antiga foi excluída, mas a nova não foi criada: ' . $novo->get_error_message() ) );
        }
        wp_send_json_success( array(
            'message'  => '"' . $titulo . '" foi reimportada do YouTube (rascunho novo).',
            'edit_url' => get_edit_post_link( $novo, 'raw' ),
        ) );
    }
}

CV_Page_Musicas::init();
