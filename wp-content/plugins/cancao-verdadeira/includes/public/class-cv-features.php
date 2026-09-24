<?php
// cancao-verdadeira-plugin/includes/public/class-cv-features.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Reúne dois recursos do plugin em um único arquivo:
// (v2.26.0: saiu o shortcode [cv_generos] — o site é todo sertanejo)
// 2. Busca AJAX com autocomplete em tempo real (migrado do tema para
//    o plugin, onde a lógica de negócio deve sempre residir)
// 3. WhatsApp flutuante aprimorado — pulso de atenção, mensagem
//    customizável e opção de ocultar em páginas específicas.
// Todos os endpoints usam nonce WordPress para segurança.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Features {

    public static function init() {
        // Busca AJAX com autocomplete (migrado do tema para o plugin)
        add_action( 'wp_ajax_cv_autocomplete',        array( __CLASS__, 'ajax_autocomplete' ) );
        add_action( 'wp_ajax_nopriv_cv_autocomplete', array( __CLASS__, 'ajax_autocomplete' ) );

        // WhatsApp flutuante — renderizado pelo plugin, não pelo tema
        add_action( 'wp_footer', array( __CLASS__, 'render_whatsapp' ), 20 );

        // Injeta dados do autocomplete no JS público
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'localize_search' ), 30 );
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

        // v2.27.0: mesma busca do site (Relevanssi com pesos), via CV_Search.
        $query = CV_Search::query( array( 'termo' => $term, 'por_pagina' => 7 ) );
        $posts = $query->posts;

        $results = array();
        foreach ( $posts as $post ) {
            $artista = get_post_meta( $post->ID, CV_Fields::ARTISTA,     true );
            $yt_url  = get_post_meta( $post->ID, CV_Fields::YOUTUBE_URL, true );

            // Capa: thumbnail > YouTube > vazio
            $cover = get_the_post_thumbnail_url( $post->ID, 'cv-cover' );
            if ( ! $cover && $yt_url ) {
                $yt_id = CV_Fields::youtube_id( $yt_url );
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

        wp_send_json_success( array(
            'results' => $results,
            'total'   => (int) $query->found_posts,
            'all_url' => CV_Search::url( $term ), // "ver todos os resultados"
        ) );
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
                cvPublic.searchPage="' . esc_js( CV_Search::url() ) . '";
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
