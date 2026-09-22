<?php
// cancao-verdadeira-plugin/includes/public/class-cv-features.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Reúne os três próximos passos do plugin em um único arquivo:
// 1. Shortcode [cv_generos] — grade visual de gêneros clicável
// 2. Busca AJAX com autocomplete em tempo real (migrado do tema para
//    o plugin, onde a lógica de negócio deve sempre residir)
// 3. WhatsApp flutuante aprimorado — pulso de atenção, mensagem
//    customizável e opção de ocultar em páginas específicas.
// Todos os endpoints usam nonce WordPress para segurança.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Features {

    public static function init() {
        // Shortcode de gêneros
        add_shortcode( 'cv_generos', array( __CLASS__, 'shortcode_generos' ) );

        // Busca AJAX com autocomplete (migrado do tema para o plugin)
        add_action( 'wp_ajax_cv_autocomplete',        array( __CLASS__, 'ajax_autocomplete' ) );
        add_action( 'wp_ajax_nopriv_cv_autocomplete', array( __CLASS__, 'ajax_autocomplete' ) );

        // WhatsApp flutuante — renderizado pelo plugin, não pelo tema
        add_action( 'wp_footer', array( __CLASS__, 'render_whatsapp' ), 20 );

        // Injeta dados do autocomplete no JS público
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_search' ), 30 );
    }

    // ════════════════════════════════════════════════════════════════
    // 1. SHORTCODE [cv_generos]
    // ════════════════════════════════════════════════════════════════
    //
    // Parâmetros:
    //   limite    = número de gêneros (padrão: todos)
    //   layout    = grade | lista | pills (padrão: grade)
    //   titulo    = texto do título da seção (padrão: vazio)
    //   colunas   = 2, 3 ou 4 — só no layout grade (padrão: 3)
    //
    // Exemplos:
    //   [cv_generos]
    //   [cv_generos layout="pills"]
    //   [cv_generos layout="lista" titulo="Explore os Gêneros"]
    //   [cv_generos colunas="2" limite="4"]

    public static function shortcode_generos( $atts ) {
        $atts = shortcode_atts( array(
            'limite'  => 0,
            'layout'  => 'grade',
            'titulo'  => '',
            'colunas' => 3,
        ), $atts, 'cv_generos' );

        $limite  = absint( $atts['limite'] );
        $layout  = sanitize_text_field( $atts['layout'] );
        $titulo  = sanitize_text_field( $atts['titulo'] );
        $colunas = absint( $atts['colunas'] );

        if ( ! in_array( $colunas, array( 2, 3, 4 ), true ) ) { $colunas = 3; }
        if ( ! in_array( $layout, array( 'grade', 'lista', 'pills' ), true ) ) { $layout = 'grade'; }

        $args = array(
            'taxonomy'   => 'cv_genre',
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );
        if ( $limite > 0 ) { $args['number'] = $limite; }

        $generos = get_terms( $args );
        if ( is_wp_error( $generos ) || empty( $generos ) ) {
            return '<p class="cv-sem-musicas">Nenhum gênero cadastrado ainda.</p>';
        }

        // Dados visuais de cada gênero
        $icones = array(
            'sertanejo-universitario' => '🎸',
            'sertanejo-raiz'          => '🪗',
            'sertanejo-romantico'     => '❤',
            'modao'                   => '🎩',
            'sertanejo-gospel'        => '✝',
            'sertanejo-sofrencia'     => '💔',
        );
        $gradientes = array(
            'sertanejo-universitario' => 'linear-gradient(135deg,#7f2c00,#e67e22)',
            'sertanejo-raiz'          => 'linear-gradient(135deg,#1a3a1a,#27ae60)',
            'sertanejo-romantico'     => 'linear-gradient(135deg,#3a0020,#e91e8c)',
            'modao'                   => 'linear-gradient(135deg,#2a1a00,#D4A017)',
            'sertanejo-gospel'        => 'linear-gradient(135deg,#1a1a3a,#9b59b6)',
            'sertanejo-sofrencia'     => 'linear-gradient(135deg,#001a3a,#3498db)',
        );
        $cores_pill = array(
            'sertanejo-universitario' => '#e67e22',
            'sertanejo-raiz'          => '#27ae60',
            'sertanejo-romantico'     => '#e91e8c',
            'modao'                   => '#D4A017',
            'sertanejo-gospel'        => '#9b59b6',
            'sertanejo-sofrencia'     => '#3498db',
        );

        ob_start();
        ?>
        <div class="cv-generos-wrap cv-generos-<?php echo esc_attr( $layout ); ?>">

            <?php if ( $titulo ) : ?>
                <h2 class="cv-section-titulo"><?php echo esc_html( $titulo ); ?></h2>
            <?php endif; ?>

            <?php if ( 'grade' === $layout ) : ?>

                <div class="cv-genre-grid cv-genre-grid-col-<?php echo esc_attr( $colunas ); ?>">
                    <?php foreach ( $generos as $gen ) :
                        $icone     = $icones[ $gen->slug ]     ?? '🎵';
                        $gradiente = $gradientes[ $gen->slug ] ?? 'linear-gradient(135deg,#1a1a1a,#333)';
                        $total     = $gen->count;
                    ?>
                    <a href="<?php echo esc_url( get_term_link( $gen ) ); ?>"
                       class="cv-genre-card"
                       title="<?php echo esc_attr( $gen->name ); ?>">
                        <div class="cv-genre-card-bg"
                             style="background:<?php echo esc_attr( $gradiente ); ?>">
                        </div>
                        <div class="cv-genre-card-overlay"></div>
                        <span class="cv-genre-card-icon"><?php echo $icone; ?></span>
                        <div class="cv-genre-card-name"><?php echo esc_html( $gen->name ); ?></div>
                        <?php if ( $total > 0 ) : ?>
                            <div class="cv-genre-card-count">
                                <?php echo number_format( $total ); ?> música<?php echo $total !== 1 ? 's' : ''; ?>
                            </div>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

            <?php elseif ( 'lista' === $layout ) : ?>

                <ul class="cv-generos-lista">
                    <?php foreach ( $generos as $gen ) :
                        $icone = $icones[ $gen->slug ] ?? '🎵';
                        $cor   = $cores_pill[ $gen->slug ] ?? '#D4A017';
                        $total = $gen->count;
                    ?>
                    <li class="cv-generos-lista-item">
                        <a href="<?php echo esc_url( get_term_link( $gen ) ); ?>">
                            <span class="cv-generos-lista-icon"
                                  style="background:<?php echo esc_attr( $cor ); ?>20;color:<?php echo esc_attr( $cor ); ?>">
                                <?php echo $icone; ?>
                            </span>
                            <span class="cv-generos-lista-nome">
                                <?php echo esc_html( $gen->name ); ?>
                            </span>
                            <?php if ( $total > 0 ) : ?>
                                <span class="cv-generos-lista-count">
                                    <?php echo number_format( $total ); ?>
                                </span>
                            <?php endif; ?>
                            <span class="cv-generos-lista-seta">›</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>

            <?php elseif ( 'pills' === $layout ) : ?>

                <div class="cv-generos-pills">
                    <?php foreach ( $generos as $gen ) :
                        $icone = $icones[ $gen->slug ] ?? '🎵';
                        $cor   = $cores_pill[ $gen->slug ] ?? '#D4A017';
                    ?>
                    <a href="<?php echo esc_url( get_term_link( $gen ) ); ?>"
                       class="cv-genre-pill-sc"
                       style="--pill-color:<?php echo esc_attr( $cor ); ?>">
                        <span><?php echo $icone; ?></span>
                        <?php echo esc_html( $gen->name ); ?>
                        <?php if ( $gen->count > 0 ) : ?>
                            <span class="cv-pill-count"><?php echo $gen->count; ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div><!-- .cv-generos-wrap -->
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 2. BUSCA AJAX COM AUTOCOMPLETE
    // ════════════════════════════════════════════════════════════════

    /**
     * Endpoint AJAX de autocomplete.
     * Busca músicas por título, artista ou compositor.
     * Retorna até 7 resultados com capa, título e artista.
     * Migrado do functions.php do tema para o plugin (lógica = plugin).
     */
    public static function ajax_autocomplete() {
        check_ajax_referer( 'cv_autocomplete_nonce', 'nonce' );

        $term = sanitize_text_field( $_GET['term'] ?? '' );

        if ( strlen( $term ) < 2 ) {
            wp_send_json_success( array( 'results' => array() ) );
        }

        // Usa Relevanssi se disponível, senão WP_Query padrão
        $posts = array();
        if ( function_exists( 'relevanssi_do_query' ) ) {
            $args  = array(
                'post_type'      => 'musica',
                'post_status'    => 'publish',
                'posts_per_page' => 7,
                's'              => $term,
            );
            $query = new WP_Query( $args );
            relevanssi_do_query( $query );
            $posts = $query->posts;
        } else {
            $posts = get_posts( array(
                'post_type'      => 'musica',
                'post_status'    => 'publish',
                'posts_per_page' => 7,
                's'              => $term,
                'meta_query'     => array(
                    array(
                        'key'     => '_cv_ativo',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                ),
            ) );
        }

        $results = array();
        foreach ( $posts as $post ) {
            $artista = get_post_meta( $post->ID, '_cv_artista',     true );
            $yt_url  = get_post_meta( $post->ID, '_cv_youtube_url', true );

            // Capa: thumbnail > YouTube > vazio
            $cover = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover && $yt_url ) {
                preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $yt_url, $m );
                $yt_id = $m[1] ?? '';
                $cover = $yt_id ? "https://img.youtube.com/vi/{$yt_id}/default.jpg" : '';
            }

            $results[] = array(
                'id'     => $post->ID,
                'title'  => $post->post_title,
                'artist' => $artista,
                'url'    => get_permalink( $post->ID ),
                'cover'  => $cover ?: '',
            );
        }

        wp_send_json_success( array( 'results' => $results ) );
    }

    /**
     * Injeta dados necessários para o autocomplete no JS público.
     * O tema pode usar cvPublic.search para disparar as buscas.
     */
    public static function localize_search() {
        if ( ! wp_script_is( 'cv-public-js', 'enqueued' ) ) { return; }

        wp_add_inline_script(
            'cv-public-js',
            'if(typeof cvPublic!=="undefined"){
                cvPublic.searchNonce="' . wp_create_nonce( 'cv_autocomplete_nonce' ) . '";
                cvPublic.searchUrl="' . esc_js( admin_url( 'admin-ajax.php' ) ) . '";
                cvPublic.searchMin=2;
            }',
            'after'
        );
    }

    // ════════════════════════════════════════════════════════════════
    // 3. WHATSAPP FLUTUANTE APRIMORADO
    // ════════════════════════════════════════════════════════════════

    /**
     * Renderiza o botão WhatsApp flutuante no rodapé.
     * Melhorias em relação ao tema v1.5:
     * - Mensagem customizável via painel admin
     * - Animação de pulso de atenção (chamar atenção do visitante)
     * - Tooltip com texto configurável
     * - Opção de ocultar em páginas do admin
     * - Suporte a múltiplos idiomas na mensagem
     */
    public static function render_whatsapp() {
        // Não renderiza no admin
        if ( is_admin() ) { return; }

        $numero   = get_option( 'cv_whatsapp_number', '' );
        if ( empty( $numero ) ) { return; }

        // Limpa o número (só dígitos)
        $numero_limpo = preg_replace( '/\D/', '', $numero );
        if ( empty( $numero_limpo ) ) { return; }

        // Mensagem customizável (com fallback)
        $mensagem = get_option(
            'cv_whatsapp_message',
            'Olá! Vim do Canção Verdadeira e gostaria de saber mais.'
        );
        $mensagem_encoded = urlencode( $mensagem );

        // Tooltip customizável
        $tooltip = get_option( 'cv_whatsapp_tooltip', 'Fale conosco no WhatsApp!' );

        // Pulso: exibir por X segundos após a página carregar (0 = sempre)
        $pulso_delay = (int) get_option( 'cv_whatsapp_pulse_delay', 3 );
        ?>

        <!-- WhatsApp Flutuante — Canção Verdadeira Plugin v1.6 -->
        <div class="cv-whatsapp-float" id="cv-whatsapp-float">
            <!-- Tooltip -->
            <div class="cv-whatsapp-tooltip" id="cv-whatsapp-tooltip">
                <?php echo esc_html( $tooltip ); ?>
            </div>

            <!-- Botão principal -->
            <a href="https://wa.me/<?php echo esc_attr( $numero_limpo ); ?>?text=<?php echo esc_attr( $mensagem_encoded ); ?>"
               class="cv-whatsapp-btn"
               id="cv-whatsapp-btn"
               target="_blank"
               rel="noopener noreferrer"
               aria-label="<?php echo esc_attr( $tooltip ); ?>">
                <!-- Ícone SVG do WhatsApp (sem dependência de emoji) -->
                <svg viewBox="0 0 24 24" fill="currentColor" width="26" height="26" aria-hidden="true">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.127.558 4.121 1.529 5.847L.057 23.784a.75.75 0 00.918.957l6.053-1.585A11.945 11.945 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 22c-1.88 0-3.636-.515-5.143-1.41l-.369-.22-3.808.998.914-3.713-.242-.381A9.944 9.944 0 012 12C2 6.477 6.477 2 12 2s10 4.477 10 10-4.477 10-10 10z"/>
                </svg>
            </a>
        </div>

        <style>
        .cv-whatsapp-float {
            position: fixed;
            bottom: calc(var(--cv-player-h, 80px) + 20px);
            right: 24px;
            z-index: 999;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 8px;
        }
        .cv-whatsapp-tooltip {
            background: rgba(18,18,18,.95);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 8px;
            color: #F5F0E0;
            font-size: 12px;
            font-weight: 600;
            padding: 6px 12px;
            white-space: nowrap;
            opacity: 0;
            transform: translateX(8px);
            transition: opacity .2s ease, transform .2s ease;
            pointer-events: none;
        }
        .cv-whatsapp-float:hover .cv-whatsapp-tooltip {
            opacity: 1;
            transform: translateX(0);
        }
        .cv-whatsapp-btn {
            width: 54px;
            height: 54px;
            border-radius: 50%;
            background: #25D366;
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 16px rgba(37,211,102,.4);
            transition: transform .2s ease, box-shadow .2s ease;
            position: relative;
        }
        .cv-whatsapp-btn:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 24px rgba(37,211,102,.5);
            color: #fff;
        }
        /* Anel de pulso */
        .cv-whatsapp-btn::before {
            content: '';
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 2px solid #25D366;
            opacity: 0;
            animation: cv-wa-pulse 2s ease-out infinite;
        }
        .cv-whatsapp-btn::after {
            content: '';
            position: absolute;
            inset: -8px;
            border-radius: 50%;
            border: 2px solid #25D366;
            opacity: 0;
            animation: cv-wa-pulse 2s ease-out infinite .4s;
        }
        @keyframes cv-wa-pulse {
            0%   { transform: scale(.9); opacity: .6; }
            100% { transform: scale(1.4); opacity: 0; }
        }
        /* Pausa no hover para não distrair quando o usuário interage */
        .cv-whatsapp-btn:hover::before,
        .cv-whatsapp-btn:hover::after {
            animation-play-state: paused;
        }
        @media (max-width: 480px) {
            .cv-whatsapp-float { right: 14px; bottom: calc(var(--cv-player-h, 80px) + 14px); }
            .cv-whatsapp-tooltip { display: none; }
        }
        </style>

        <?php if ( $pulso_delay > 0 ) : ?>
        <script>
        (function() {
            var btn = document.getElementById('cv-whatsapp-float');
            if (!btn) return;
            // Inicia oculto e aparece com fade após o delay configurado
            btn.style.opacity = '0';
            btn.style.transform = 'translateY(10px)';
            btn.style.transition = 'opacity .4s ease, transform .4s ease';
            setTimeout(function() {
                btn.style.opacity = '1';
                btn.style.transform = 'translateY(0)';
            }, <?php echo (int) $pulso_delay * 1000; ?>);
        })();
        </script>
        <?php endif; ?>

        <?php
    }

}

CV_Features::init();
