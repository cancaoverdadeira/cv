<?php
// cancao-verdadeira/includes/cpt/class-cv-metaboxes.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Metaboxes do CPT musica (v2.15.0)
// Funções : Editor rico para letra, campos simplificados, SEO automático,
//           calendário de estreia, padrão dark completo
// Remove  : Campos SEO manuais (título/desc/tags) — sistema cuida do SEO
// Mantém  : YouTube URL, MP3, compositor, artista, álbum, ano, ativo, destaque
// Autor   : Canção Verdadeira | Gerado: 2026-06-26
// v2.26.0 : campo "Gênero Musical" removido (o site é todo sertanejo)
// v2.25.0 : sync_excerpt usa CV_Fields::sync_excerpt (compartilhado com o blog)
// v2.45.0 : letra com a barra "estilo Word" (CV_Editor_Rico) e caixa "🔎 SEO e
//           Tags" de volta: palavra-chave principal e título no Google (gravados
//           nos campos do Rank Math), tags e prévia do resultado no Google.
// v2.53.0 : caixa "📖 Por trás da canção" (CV_Fields::HISTORIA): a história da
//           música contada pelo compositor, mostrada abaixo da letra no site.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Metaboxes {

    public static function init() {
        add_action( 'add_meta_boxes',    array( __CLASS__, 'add' ) );
        add_action( 'save_post_musica',  array( __CLASS__, 'save' ) );
        add_action( 'save_post_musica',  array( __CLASS__, 'sync_excerpt' ), 20 );
        add_action( 'admin_head',        array( __CLASS__, 'dark_css' ) );
        // Remove metaboxes nativos desnecessários na tela de música
        add_action( 'admin_menu',        array( __CLASS__, 'remove_default_metaboxes' ) );
    }

    public static function remove_default_metaboxes() {
        remove_meta_box( 'tagsdiv-post_tag', 'musica', 'side' );
        remove_meta_box( 'slugdiv',          'musica', 'normal' );
        remove_meta_box( 'authordiv',        'musica', 'normal' );
        remove_meta_box( 'commentstatusdiv', 'musica', 'normal' );
        remove_meta_box( 'commentsdiv',      'musica', 'normal' );
        remove_meta_box( 'trackbacksdiv',    'musica', 'normal' );
        remove_meta_box( 'postcustom',       'musica', 'normal' );
    }

    public static function add() {
        add_meta_box( 'cv_music_player',   '🎬 Player & Áudio',     array( __CLASS__, 'render_player' ),   'musica', 'normal', 'high' );
        add_meta_box( 'cv_music_info',     '🎤 Informações',         array( __CLASS__, 'render_info' ),     'musica', 'normal', 'high' );
        add_meta_box( 'cv_music_letra',    '📝 Letra da Música',     array( __CLASS__, 'render_letra' ),    'musica', 'normal', 'high' );
        add_meta_box( 'cv_music_historia', '📖 Por trás da canção',  array( __CLASS__, 'render_historia' ), 'musica', 'normal', 'high' );
        add_meta_box( 'cv_music_seo',      '🔎 SEO e Tags',          array( __CLASS__, 'render_seo' ),      'musica', 'normal', 'high' );
        add_meta_box( 'cv_music_config',   '⚙️ Configurações',       array( __CLASS__, 'render_config' ),   'musica', 'side',   'high' );
        add_meta_box( 'cv_music_estreia',  '📅 Estreia',             array( __CLASS__, 'render_estreia' ),  'musica', 'side',   'high' );
        add_meta_box( 'cv_music_stats',    '📊 Estatísticas',        array( __CLASS__, 'render_stats' ),    'musica', 'side',   'low' );
    }

    // ── CSS dark para a tela de música ───────────────────────────
    public static function dark_css() {
        global $post_type;
        if ( $post_type !== 'musica' ) { return; }
        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        // Só aplica na tela de edição de UMA música (post.php / post-new.php).
        // Sem essa checagem, o CSS "agressivo" abaixo também pegava a LISTAGEM
        // (edit.php?post_type=musica, screen->base === 'edit'), quebrando o
        // visual da tabela de músicas no admin (linhas somem/ficam ilegíveis).
        if ( ! $screen || 'post' !== $screen->base ) { return; }
        echo '<style>
        /* ── Dark mode completo para tela de música ── */
        /* Reset agressivo — elimina cores do Astra, WP nativo e outros plugins */
        body.post-type-musica,
        body.post-type-musica #wpcontent,
        body.post-type-musica #wpbody,
        body.post-type-musica #wpbody-content { background: #FBF6EE !important; color: #3B2418 !important; }
        body.post-type-musica #poststuff { background: #FBF6EE !important; padding-top: 10px !important; }
        body.post-type-musica #post-body-content,
        body.post-type-musica .postbox-container { background: transparent !important; }
        /* Postboxes */
        body.post-type-musica .postbox { background: #F8F0E4 !important; border: 1px solid #EADBC6 !important; border-radius: 8px !important; margin-bottom: 16px !important; box-shadow: none !important; }
        body.post-type-musica .postbox .postbox-header { background: #F8F0E4 !important; border-bottom: 1px solid #EADBC6 !important; border-radius: 8px 8px 0 0 !important; }
        body.post-type-musica .postbox .postbox-header h2,
        body.post-type-musica .postbox .hndle { color: #3B2418 !important; font-size: 13px !important; }
        body.post-type-musica .postbox .inside { padding: 16px !important; background: transparent !important; }
        body.post-type-musica .postbox .handlediv button { color: #6B4C3B !important; }
        /* Título do post */
        body.post-type-musica #titlediv { margin-bottom: 8px !important; }
        body.post-type-musica #titlediv #title { background: #F8F0E4 !important; border: 1px solid #EADBC6 !important; color: #3B2418 !important; font-size: 20px !important; border-radius: 6px !important; }
        body.post-type-musica #titlediv #title::placeholder { color: #8A6A55 !important; }
        body.post-type-musica #titlediv label { color: #6B4C3B !important; }
        body.post-type-musica #titlediv #title-prompt-text { color: #8A6A55 !important; }
        /* Área de publicação */
        body.post-type-musica #submitdiv { background: #F8F0E4 !important; border-color: #EADBC6 !important; }
        body.post-type-musica #submitdiv .submitbox { background: #F8F0E4 !important; }
        body.post-type-musica #minor-publishing { background: #F8F0E4 !important; border-color: #EADBC6 !important; }
        body.post-type-musica #minor-publishing-actions,
        body.post-type-musica #misc-publishing-actions { color: #6B4C3B !important; }
        body.post-type-musica #minor-publishing label,
        body.post-type-musica #misc-publishing-actions label { color: #6B4C3B !important; }
        body.post-type-musica #major-publishing-actions { background: #FFFFFF !important; border-top: 1px solid #EADBC6 !important; }
        body.post-type-musica .button-primary { background: #1DB954 !important; border-color: #1DB954 !important; color: #3B2418 !important; }
        body.post-type-musica .button-primary:hover { background: #17a349 !important; border-color: #17a349 !important; }
        /* Imagem destacada */
        body.post-type-musica #postimagediv { background: #F8F0E4 !important; border-color: #EADBC6 !important; }
        body.post-type-musica #postimagediv .inside { background: transparent !important; color: #6B4C3B !important; }
        /* Editor TinyMCE */
        body.post-type-musica #wp-cv_letra-wrap { border-color: #EADBC6 !important; border-radius: 6px !important; }
        body.post-type-musica .mce-toolbar .mce-btn { background: #F8F0E4 !important; border-color: #EADBC6 !important; }
        body.post-type-musica .mce-toolbar .mce-btn button { color: #3B2418 !important; }
        body.post-type-musica #wp-cv_letra-editor-container { background: #FFFFFF !important; border-color: #EADBC6 !important; }
        /* Ocultar metaboxes de terceiros que escaparam */
        body.post-type-musica #rank_math_metabox,
        body.post-type-musica #wpseo_meta,
        body.post-type-musica #astra_settings_meta_box,
        body.post-type-musica #astra_meta_box,
        body.post-type-musica #elementor,
        body.post-type-musica #acf-field-group { display: none !important; }
        /* Astra color override */
        body.post-type-musica .astra-color-picker-wrap,
        body.post-type-musica .ast-page-header-meta-wrap,
        body.post-type-musica [class*="ast-"] { display: none !important; }
        /* Corrige cor roxa do WP nativo */
        body.post-type-musica #screen-options-link-wrap a,
        body.post-type-musica #contextual-help-link { color: #6B4C3B !important; }
        body.post-type-musica a.page-title-action { background: #1DB954 !important; border-color: #1DB954 !important; color: #3B2418 !important; border-radius: 4px !important; }
        #titlediv #title { background: #F8F0E4 !important; border-color: #EADBC6 !important; color: #3B2418 !important; font-size: 20px !important; }
        #titlediv #title::placeholder { color: #8A6A55 !important; }
        #titlediv label { color: #6B4C3B !important; }
        /* Campos do formulário */
        .cv-dark-field label { display:block; font-weight:600; margin-bottom:5px; color:#6B4C3B; font-size:12px; text-transform:uppercase; letter-spacing:.5px; }
        .cv-dark-field input[type=text],
        .cv-dark-field input[type=url],
        .cv-dark-field input[type=number],
        .cv-dark-field input[type=date],
        .cv-dark-field input[type=time],
        .cv-dark-field textarea,
        .cv-dark-field select { width:100%; padding:9px 12px; background:#FFFFFF; border:1px solid #EADBC6; border-radius:6px; color:#3B2418; font-size:14px; box-sizing:border-box; transition:border-color .2s; }
        .cv-dark-field input:focus,
        .cv-dark-field textarea:focus,
        .cv-dark-field select:focus { border-color:#1DB954; outline:none; box-shadow:0 0 0 2px rgba(29,185,84,.15); }
        .cv-dark-field select option { background:#F8F0E4; }
        .cv-dark-grid-2 { display:grid; grid-template-columns:1fr 1fr; gap:14px; }
        .cv-dark-grid-3 { display:grid; grid-template-columns:1fr 1fr 1fr; gap:14px; }
        .cv-section-label { font-size:11px; font-weight:700; color:#137B38; text-transform:uppercase; letter-spacing:1px; margin:16px 0 10px; padding-bottom:4px; border-bottom:1px solid #1DB95433; }
        .cv-toggle-wrap { display:flex; align-items:center; gap:10px; padding:8px 0; }
        .cv-toggle-wrap label { color:#3B2418; font-size:13px; margin:0; cursor:pointer; }
        .cv-toggle-wrap input[type=checkbox] { width:16px; height:16px; accent-color:#1DB954; cursor:pointer; }
        .cv-hint { font-size:11px; color:#8A6A55; margin-top:4px; }
        .cv-yt-preview { margin-top:10px; display:none; }
        .cv-yt-preview img { width:100%; max-width:280px; border-radius:6px; border:1px solid #EADBC6; }
        .cv-yt-error { color:#D62C1A; font-size:12px; margin-top:4px; display:none; }
        .cv-badge-green { display:inline-block; background:#1DB95422; border:1px solid #1DB954; color:#137B38; border-radius:4px; padding:2px 8px; font-size:11px; }
        /* Stats table */
        .cv-stats-table { width:100%; border-collapse:collapse; }
        .cv-stats-table td { padding:7px 0; border-bottom:1px solid #EADBC6; font-size:13px; color:#6B4C3B; }
        .cv-stats-table td:last-child { font-weight:700; text-align:right; color:#137B38; }
        /* Estreia */
        .cv-estreia-badge { background:#1DB95422; border:1px solid #1DB954; border-radius:6px; padding:8px 12px; margin-top:10px; font-size:12px; color:#137B38; display:none; }
        /* Editor rico */
        #wp-cv_letra-wrap { border-color: #EADBC6 !important; }
        #cv_letra { background: #FFFFFF !important; color: #3B2418 !important; border-color: #EADBC6 !important; }
        </style>';
    }

    // ── Player & Áudio ───────────────────────────────────────────
    public static function render_player( $post ) {
        wp_nonce_field( 'cv_save_music_data', 'cv_music_nonce' );
        $youtube_url = get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );
        $audio_url   = get_post_meta( $post->ID, CV_Fields::AUDIO_URL,   true );
        ?>
        <div class="cv-dark-field" style="margin-bottom:14px">
            <label for="cv_youtube_url">URL do YouTube <span style="color:#D62C1A">*</span></label>
            <input type="url" id="cv_youtube_url" name="cv_youtube_url"
                   value="<?php echo esc_attr($youtube_url); ?>"
                   placeholder="https://www.youtube.com/watch?v=..." />
            <div class="cv-yt-error" id="cv-yt-error">⚠ URL inválida — use youtube.com/watch?v= ou youtu.be/</div>
            <div class="cv-yt-preview" id="cv-yt-preview">
                <img id="cv-yt-thumb" src="" alt="Preview" />
            </div>
        </div>

        <div class="cv-dark-field">
            <label for="cv_audio_url">Arquivo de Áudio (MP3) <span class="cv-badge-green">opcional</span></label>
            <div style="display:flex;gap:8px;align-items:center">
                <input type="url" id="cv_audio_url" name="cv_audio_url"
                       value="<?php echo esc_attr($audio_url); ?>"
                       placeholder="URL do MP3 ou clique em Enviar Áudio"
                       style="flex:1" />
                <button type="button" id="cv_audio_upload_btn" class="button" style="flex-shrink:0;background:#1DB954;border-color:#1DB954;color:#3B2418">
                    🎵 Áudio
                </button>
                <button type="button" id="cv_audio_clear_btn" class="button" style="flex-shrink:0" title="Remover">✕</button>
            </div>
            <div id="cv_audio_preview" style="margin-top:8px;display:none">
                <audio controls style="width:100%;height:36px">
                    <source id="cv_audio_source" src="" type="audio/mpeg">
                </audio>
            </div>
            <p class="cv-hint">MP3 ativa o player estilo Spotify. Sem MP3, exibe apenas o YouTube.</p>
        </div>

        <script>
        jQuery(function($){
            // Upload de áudio
            var frame;
            $('#cv_audio_upload_btn').on('click', function(e){
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({ title:'Selecionar Áudio', button:{text:'Usar este áudio'}, library:{type:'audio'}, multiple:false });
                frame.on('select', function(){
                    var att = frame.state().get('selection').first().toJSON();
                    var url = att.url || '';
                    $('#cv_audio_url').val(url);
                    if (url) { $('#cv_audio_source').attr('src',url); $('#cv_audio_preview audio')[0].load(); $('#cv_audio_preview').show(); }
                });
                frame.open();
            });
            $('#cv_audio_clear_btn').on('click', function(){ $('#cv_audio_url').val(''); $('#cv_audio_preview').hide(); });
            if ($('#cv_audio_url').val()) { $('#cv_audio_source').attr('src',$('#cv_audio_url').val()); $('#cv_audio_preview').show(); }

            // Preview YouTube
            function ytId(url){ var m=url.match(/(?:[?&]v=|\/embed\/|youtu\.be\/|\/shorts\/|\/live\/)([A-Za-z0-9_-]{11})/); return m?m[1]:null; } // mesma regra de CV_Fields::YOUTUBE_REGEX
            function checkYt(){
                var val=$('#cv_youtube_url').val().trim();
                if(!val){$('#cv-yt-error,#cv-yt-preview').hide();return;}
                var id=ytId(val);
                if(id){$('#cv-yt-error').hide();$('#cv-yt-thumb').attr('src','https://img.youtube.com/vi/'+id+'/mqdefault.jpg');$('#cv-yt-preview').show();}
                else{$('#cv-yt-preview').hide();$('#cv-yt-error').show();}
            }
            $('#cv_youtube_url').on('input blur',checkYt);
            if($('#cv_youtube_url').val()){checkYt();}
        });
        </script>
        <?php
    }

    // ── Informações ───────────────────────────────────────────────
    public static function render_info( $post ) {
        $compositor = get_post_meta( $post->ID, CV_Fields::COMPOSITOR, true );
        $artista    = get_post_meta( $post->ID, CV_Fields::ARTISTA,    true );
        $album      = get_post_meta( $post->ID, CV_Fields::ALBUM,      true );
        $ano        = get_post_meta( $post->ID, CV_Fields::ANO,        true );
        $descricao  = get_post_meta( $post->ID, CV_Fields::DESCRICAO,  true );
        if ( empty($descricao) && ! empty($post->post_excerpt) ) { $descricao = $post->post_excerpt; }
        ?>
        <div class="cv-dark-grid-2" style="margin-bottom:14px">
            <div class="cv-dark-field">
                <label for="cv_compositor">Compositor <span style="color:#D62C1A">*</span></label>
                <input type="text" id="cv_compositor" name="cv_compositor"
                       value="<?php echo esc_attr($compositor); ?>"
                       placeholder="Nome do compositor" />
            </div>
            <div class="cv-dark-field">
                <label for="cv_artista">Artista / Dupla</label>
                <input type="text" id="cv_artista" name="cv_artista"
                       value="<?php echo esc_attr($artista); ?>"
                       placeholder="Nome do artista" />
            </div>
            <div class="cv-dark-field">
                <label for="cv_album">Álbum</label>
                <input type="text" id="cv_album" name="cv_album"
                       value="<?php echo esc_attr($album); ?>"
                       placeholder="Nome do álbum (opcional)" />
            </div>
            <div class="cv-dark-field">
                <label for="cv_ano">Ano</label>
                <input type="number" id="cv_ano" name="cv_ano"
                       value="<?php echo esc_attr($ano); ?>"
                       placeholder="<?php echo date('Y'); ?>" min="1900" max="2099" />
            </div>
        </div>

        <div class="cv-dark-field">
            <label for="cv_descricao">Descrição <span class="cv-badge-green">SEO automático</span></label>
            <textarea id="cv_descricao" name="cv_descricao" rows="3"
                      maxlength="300"
                      placeholder="Descreva a música em 1-2 frases. O sistema usa este texto para SEO automaticamente."
            ><?php echo esc_textarea($descricao); ?></textarea>
            <div class="cv-hint">
                <span id="cv-desc-len"><?php echo mb_strlen($descricao); ?></span>/300 caracteres
                — recomendado: 120–160 para melhor resultado no Google
            </div>
        </div>

        <script>
        jQuery(function($){
            var $ta=$('#cv_descricao'), $cnt=$('#cv-desc-len');
            $ta.on('input',function(){ $cnt.text($(this).val().length); });
        });
        </script>
        <?php
    }

    // ── Letra da Música (editor rico) ─────────────────────────────
    public static function render_letra( $post ) {
        $letra = $post->post_content;
        wp_editor( $letra, 'cv_letra', array(
            'textarea_name' => 'content',
            'media_buttons' => false,
            'teeny'         => false,
            'textarea_rows' => 20,
            'tinymce'       => array(
                'toolbar1'   => implode( ',', CV_Editor_Rico::barra_1() ),
                'toolbar2'   => implode( ',', CV_Editor_Rico::barra_2() ),
                'body_class' => 'cv-letra-editor',
            ),
            'quicktags'     => array( 'buttons' => 'strong,em,ul,ol,li,link' ),
        ) );
        echo '<p class="cv-hint" style="margin-top:8px">A letra é o conteúdo principal da página da música e é indexada pelo Google.</p>';
    }

    // ── Por trás da canção (v2.53.0) ──────────────────────────────
    public static function render_historia( $post ) {
        $historia = get_post_meta( $post->ID, CV_Fields::HISTORIA, true );
        ?>
        <p class="cv-hint" style="margin:0 0 8px">Conte com suas palavras como a música nasceu: onde, quando, para quem, o que sentiu. Aparece no site logo abaixo da letra. Deixe em branco para não mostrar.</p>
        <div class="cv-dark-field">
            <textarea id="cv_historia" name="cv_historia" rows="7" style="font-size:15px;line-height:1.6"
                      placeholder="Ex.: Escrevi esta canção numa madrugada em 1998, pensando na minha mãe..."><?php echo esc_textarea( $historia ); ?></textarea>
            <div class="cv-hint"><span id="cv-historia-len"><?php echo mb_strlen( (string) $historia ); ?></span> caracteres — o ideal é de 3 a 6 parágrafos curtos. Parágrafos são separados por uma linha em branco.</div>
        </div>
        <script>
        jQuery(function($){ $('#cv_historia').on('input', function(){ $('#cv-historia-len').text(this.value.length); }); });
        </script>
        <?php
    }

    // ── SEO e Tags (v2.45.0) ──────────────────────────────────────
    // Palavra-chave e título vão para os campos do Rank Math (rank_math_*),
    // que já monta título, descrição, Open Graph e sitemap. A descrição
    // continua no campo "Descrição" da caixa Informações.
    public static function render_seo( $post ) {
        $palavra = get_post_meta( $post->ID, 'rank_math_focus_keyword', true );
        $titulo  = get_post_meta( $post->ID, 'rank_math_title', true );
        $tags    = wp_get_post_terms( $post->ID, 'post_tag', array( 'fields' => 'names' ) );
        $tags    = is_wp_error( $tags ) ? '' : implode( ', ', $tags );
        $padrao  = ( $post->post_title ? $post->post_title : 'Título da música' ) . ' - ' . get_bloginfo( 'name' );
        $link    = get_permalink( $post->ID );
        ?>
        <div class="cv-dark-grid-2" style="margin-bottom:14px">
            <div class="cv-dark-field">
                <label for="cv_seo_palavra">Palavra-chave principal</label>
                <input type="text" id="cv_seo_palavra" name="cv_seo_palavra"
                       value="<?php echo esc_attr( $palavra ); ?>"
                       placeholder="Ex.: <?php echo esc_attr( $post->post_title ? mb_strtolower( $post->post_title ) . ' letra' : 'nome da música letra' ); ?>" />
                <div class="cv-hint">O que a pessoa digitaria no Google para achar esta música. O Rank Math mede a página por ela.</div>
            </div>
            <div class="cv-dark-field">
                <label for="cv_seo_titulo">Título no Google <span style="font-weight:400;text-transform:none">(opcional)</span></label>
                <input type="text" id="cv_seo_titulo" name="cv_seo_titulo" maxlength="70"
                       value="<?php echo esc_attr( $titulo ); ?>"
                       placeholder="<?php echo esc_attr( $padrao ); ?>" />
                <div class="cv-hint">Em branco, usa o automático: <em><?php echo esc_html( $padrao ); ?></em></div>
            </div>
        </div>
        <div class="cv-dark-field" style="margin-bottom:14px">
            <label for="cv_seo_tags">Tags</label>
            <input type="text" id="cv_seo_tags" name="cv_seo_tags"
                   value="<?php echo esc_attr( $tags ); ?>"
                   placeholder="Ex.: sertanejo romântico, amor maduro, recomeço" />
            <div class="cv-hint">Separe por vírgula. Ajudam a organizar as músicas e a busca do site.</div>
        </div>

        <div class="cv-section-label">Prévia no Google</div>
        <div id="cv-seo-previa" style="background:#FFFFFF;border:1px solid #EADBC6;border-radius:8px;padding:14px 16px;max-width:600px;font-family:Arial,sans-serif">
            <div style="font-size:12px;color:#4D5156"><?php echo esc_html( $link ); ?></div>
            <div id="cv-seo-previa-titulo" style="font-size:18px;color:#1A0DAB;margin:4px 0"></div>
            <div id="cv-seo-previa-desc" style="font-size:13px;color:#4D5156;line-height:1.5"></div>
        </div>
        <script>
        jQuery(function($){
            var padrao = <?php echo wp_json_encode( ' - ' . get_bloginfo( 'name' ) ); ?>;
            function atualizar(){
                var t = $.trim($('#cv_seo_titulo').val()) || ($.trim($('#title').val()) || 'Título da música') + padrao;
                var d = $.trim($('#cv_descricao').val()) || 'Escreva a Descrição na caixa Informações — é ela que aparece aqui.';
                $('#cv-seo-previa-titulo').text(t.length > 60 ? t.substr(0, 60) + '…' : t);
                $('#cv-seo-previa-desc').text(d.length > 160 ? d.substr(0, 160) + '…' : d);
            }
            $('#cv_seo_titulo, #title, #cv_descricao').on('input', atualizar);
            atualizar();
        });
        </script>
        <?php
    }

    // ── Configurações laterais ────────────────────────────────────
    public static function render_config( $post ) {
        $ativo_meta = get_post_meta( $post->ID, CV_Fields::ATIVO,    true );
        $ativo      = ( '' === $ativo_meta ) ? '1' : $ativo_meta;
        $destaque   = get_post_meta( $post->ID, CV_Fields::DESTAQUE, true );
        ?>
        <div class="cv-toggle-wrap">
            <input type="checkbox" id="cv_ativo" name="cv_ativo" value="1" <?php checked($ativo,'1'); ?> />
            <label for="cv_ativo">Música ativa (visível no site)</label>
        </div>
        <div class="cv-toggle-wrap">
            <input type="checkbox" id="cv_destaque" name="cv_destaque" value="1" <?php checked($destaque,'1'); ?> />
            <label for="cv_destaque">⭐ Destaque na Home</label>
        </div>
        <div class="cv-field" style="margin-top:8px">
            <label for="cv_selecao_ordem" style="font-size:12px">Ordem na "Seleção da Canção Verdadeira"</label>
            <input type="number" min="1" id="cv_selecao_ordem" name="cv_selecao_ordem" style="width:80px"
                   value="<?php echo esc_attr( get_post_meta( $post->ID, CV_Fields::SELECAO_ORDEM, true ) ); ?>" />
            <p class="cv-hint" style="margin:4px 0 0">Vale para músicas em destaque enquanto o ranking real não tem audiência suficiente. 1 aparece primeiro.</p>
        </div>
        <?php
    }

    // ── Calendário de estreia ─────────────────────────────────────
    public static function render_estreia( $post ) {
        $estreia_date = get_post_meta( $post->ID, CV_Fields::ESTREIA_DATE, true );
        $estreia_time = get_post_meta( $post->ID, CV_Fields::ESTREIA_TIME, true );
        if ( ! $estreia_time ) { $estreia_time = '00:00'; }

        // Se post já tem data futura agendada, mostra ela
        $post_date = $post->post_date;
        $status    = $post->post_status;
        ?>
        <p class="cv-hint" style="margin-bottom:10px">
            Defina a data para publicar a música automaticamente. Deixe em branco para publicar agora.
        </p>
        <div class="cv-dark-field" style="margin-bottom:12px">
            <label for="cv_estreia_date">📅 Data de Estreia</label>
            <input type="date" id="cv_estreia_date" name="cv_estreia_date"
                   value="<?php echo esc_attr($estreia_date); ?>"
                   min="<?php echo date('Y-m-d'); ?>" />
        </div>
        <div class="cv-dark-field" style="margin-bottom:12px">
            <label for="cv_estreia_time">🕐 Horário</label>
            <input type="time" id="cv_estreia_time" name="cv_estreia_time"
                   value="<?php echo esc_attr($estreia_time); ?>" />
        </div>
        <div class="cv-estreia-badge" id="cv-estreia-badge"></div>
        <?php if ( $status === 'future' ) : ?>
        <div style="background:#1DB95422;border:1px solid #1DB954;border-radius:6px;padding:8px 12px;margin-top:10px;font-size:12px;color:#137B38">
            ✅ Agendada para: <?php echo esc_html( date_i18n('d/m/Y \à\s H:i', strtotime($post_date)) ); ?>
        </div>
        <?php endif; ?>

        <script>
        jQuery(function($){
            var dias = ['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'];
            function updateBadge(){
                var d = $('#cv_estreia_date').val();
                var t = $('#cv_estreia_time').val() || '00:00';
                if(!d){$('#cv-estreia-badge').hide();return;}
                var dt = new Date(d + 'T' + t);
                var str = dias[dt.getDay()] + ', ' + dt.toLocaleDateString('pt-BR') + ' às ' + t;
                $('#cv-estreia-badge').text('📅 Estreia: ' + str).show();
            }
            $('#cv_estreia_date, #cv_estreia_time').on('change', updateBadge);
            updateBadge();
        });
        </script>
        <?php
    }

    // ── Estatísticas ──────────────────────────────────────────────
    public static function render_stats( $post ) {
        $plays_total = (int)   get_post_meta( $post->ID, CV_Fields::PLAYS_TOTAL, true );
        $avg_rating  = (float) get_post_meta( $post->ID, CV_Fields::AVG_RATING,  true );
        $favorites   = self::count_favorites( $post->ID );
        $score       = self::get_score( $post->ID );
        $position    = self::get_position( $post->ID );
        ?>
        <table class="cv-stats-table">
            <tr><td>▶ Plays totais</td><td><?php echo number_format($plays_total); ?></td></tr>
            <tr><td>❤ Favoritos</td><td><?php echo number_format($favorites); ?></td></tr>
            <tr><td>⭐ Avaliação média</td><td><?php echo $avg_rating ? number_format($avg_rating,1).'/5' : '—'; ?></td></tr>
            <tr><td>🏆 Ranking</td><td><?php echo $position ? '#'.$position : '—'; ?></td></tr>
            <tr><td>📊 Score</td><td><?php echo number_format($score,2); ?></td></tr>
        </table>
        <?php
    }

    // ── Helpers ───────────────────────────────────────────────────
    private static function count_favorites( $music_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE music_id = %d", $music_id
        ) );
    }

    private static function get_score( $music_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT score FROM {$wpdb->prefix}cv_ranking_cache WHERE music_id = %d", $music_id
        ) );
        return $row ? (float)$row->score : 0;
    }

    private static function get_position( $music_id ) {
        global $wpdb;
        $row = $wpdb->get_row( $wpdb->prepare(
            "SELECT position FROM {$wpdb->prefix}cv_ranking_cache WHERE music_id = %d", $music_id
        ) );
        return $row ? (int)$row->position : 0;
    }

    private static function is_valid_youtube_url( $url ) {
        if ( empty($url) ) { return true; }
        return (bool) preg_match(
            '/(?:youtube\.com\/(?:watch\?v=|embed\/|shorts\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})/',
            $url
        );
    }

    // ── Save ──────────────────────────────────────────────────────
    public static function save( $post_id ) {
        if ( ! isset($_POST['cv_music_nonce']) ) { return; }
        if ( ! wp_verify_nonce( sanitize_text_field(wp_unslash($_POST['cv_music_nonce'])), 'cv_save_music_data' ) ) { return; }
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) { return; }
        if ( ! current_user_can('edit_post', $post_id) ) { return; }

        // YouTube
        $youtube_url = isset($_POST['cv_youtube_url']) ? esc_url_raw(trim($_POST['cv_youtube_url'])) : '';
        if ( $youtube_url && ! self::is_valid_youtube_url($youtube_url) ) {
            set_transient('cv_metabox_error_'.$post_id, 'URL do YouTube inválida. O campo foi salvo vazio.', 60);
            $youtube_url = '';
        }
        update_post_meta( $post_id, CV_Fields::YOUTUBE_URL, $youtube_url );

        // Áudio
        $audio_url = isset($_POST['cv_audio_url']) ? esc_url_raw(trim($_POST['cv_audio_url'])) : '';
        update_post_meta( $post_id, CV_Fields::AUDIO_URL, $audio_url );

        // Campos texto
        $fields = array(
            '_cv_compositor' => array('field'=>'cv_compositor','type'=>'text'),
            '_cv_artista'    => array('field'=>'cv_artista',   'type'=>'text'),
            '_cv_album'      => array('field'=>'cv_album',     'type'=>'text'),
            '_cv_ano'        => array('field'=>'cv_ano',       'type'=>'int'),
            '_cv_descricao'  => array('field'=>'cv_descricao', 'type'=>'textarea'),
        );
        foreach ( $fields as $meta_key => $cfg ) {
            if ( ! isset($_POST[$cfg['field']]) ) { continue; }
            switch ($cfg['type']) {
                case 'int':      $val = absint($_POST[$cfg['field']]); break;
                case 'textarea': $val = sanitize_textarea_field(wp_unslash($_POST[$cfg['field']])); break;
                default:         $val = sanitize_text_field(wp_unslash($_POST[$cfg['field']]));
            }
            update_post_meta($post_id, $meta_key, $val);
        }

        // Checkboxes
        update_post_meta($post_id, CV_Fields::ATIVO,    isset($_POST['cv_ativo'])    ? '1' : '0');
        update_post_meta($post_id, CV_Fields::DESTAQUE, isset($_POST['cv_destaque']) ? '1' : '0');
        $ordem = isset($_POST['cv_selecao_ordem']) ? absint($_POST['cv_selecao_ordem']) : 0;
        if ( $ordem ) { update_post_meta($post_id, CV_Fields::SELECAO_ORDEM, $ordem); }
        else          { delete_post_meta($post_id, CV_Fields::SELECAO_ORDEM); }

        // Calendário de estreia
        $estreia_date = isset($_POST['cv_estreia_date']) ? sanitize_text_field($_POST['cv_estreia_date']) : '';
        $estreia_time = isset($_POST['cv_estreia_time']) ? sanitize_text_field($_POST['cv_estreia_time']) : '00:00';
        update_post_meta($post_id, CV_Fields::ESTREIA_DATE, $estreia_date);
        update_post_meta($post_id, CV_Fields::ESTREIA_TIME, $estreia_time);

        // Agendar publicação se data futura
        if ( $estreia_date && strtotime($estreia_date.' '.$estreia_time) > time() ) {
            $scheduled_gmt  = get_gmt_from_date($estreia_date.' '.$estreia_time.':00');
            $scheduled_local = $estreia_date.' '.$estreia_time.':00';
            remove_action('save_post_musica', array(__CLASS__,'save'));
            wp_update_post(array(
                'ID'            => $post_id,
                'post_status'   => 'future',
                'post_date'     => $scheduled_local,
                'post_date_gmt' => $scheduled_gmt,
            ));
            add_action('save_post_musica', array(__CLASS__,'save'));
        }

        // Por trás da canção (v2.53.0)
        if ( isset( $_POST['cv_historia'] ) ) {
            $historia = sanitize_textarea_field( wp_unslash( $_POST['cv_historia'] ) );
            if ( '' === trim( $historia ) ) { delete_post_meta( $post_id, CV_Fields::HISTORIA ); }
            else                            { update_post_meta( $post_id, CV_Fields::HISTORIA, $historia ); }
        }

        // SEO e Tags (v2.45.0) — campos do Rank Math + tags nativas
        if ( isset( $_POST['cv_seo_palavra'] ) ) {
            $palavra = sanitize_text_field( wp_unslash( $_POST['cv_seo_palavra'] ) );
            if ( '' === $palavra ) { delete_post_meta( $post_id, 'rank_math_focus_keyword' ); }
            else                   { update_post_meta( $post_id, 'rank_math_focus_keyword', $palavra ); }
        }
        if ( isset( $_POST['cv_seo_titulo'] ) ) {
            $titulo_seo = sanitize_text_field( wp_unslash( $_POST['cv_seo_titulo'] ) );
            if ( '' === $titulo_seo ) { delete_post_meta( $post_id, 'rank_math_title' ); }
            else                      { update_post_meta( $post_id, 'rank_math_title', $titulo_seo ); }
        }
        if ( isset( $_POST['cv_seo_tags'] ) ) {
            $tags = array_filter( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_POST['cv_seo_tags'] ) ) ) ) );
            wp_set_post_terms( $post_id, array_values( $tags ), 'post_tag', false );
        }

        // SEO automático — gera title e description a partir dos dados
        $titulo    = get_the_title($post_id);
        $descricao = get_post_meta($post_id, CV_Fields::DESCRICAO, true);
        $artista   = get_post_meta($post_id, CV_Fields::ARTISTA,   true);

        $seo_title = $titulo . ( $artista ? ' — ' . $artista : '' ) . ' | Canção Verdadeira';
        $seo_desc  = $descricao ?: ( $titulo . ( $artista ? ' interpretada por ' . $artista : '' ) . '. Letra completa no Canção Verdadeira.' );

        update_post_meta($post_id, '_cv_seo_title',       $seo_title);
        update_post_meta($post_id, '_cv_seo_description', mb_substr($seo_desc, 0, 160));

        // Admin notice
        add_action('admin_notices', function() use ($post_id) {
            $error = get_transient('cv_metabox_error_'.$post_id);
            if ($error) {
                delete_transient('cv_metabox_error_'.$post_id);
                echo '<div class="notice notice-warning is-dismissible"><p><strong>Canção Verdadeira:</strong> ' . esc_html($error) . '</p></div>';
            }
        });
    }

    public static function sync_excerpt( $post_id ) {
        if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE ) { return; }
        if ( ! current_user_can('edit_post',$post_id) ) { return; }
        CV_Fields::sync_excerpt( $post_id ); // mesma regra do blog (CV_Blog)
    }
}

CV_Metaboxes::init();
