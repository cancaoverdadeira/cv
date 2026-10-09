<?php
// cancao-verdadeira/includes/user/class-cv-musica-favorita.php
// Criado em: 08/10/2026 (plugin v2.65.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// "🎵 Minha música favorita" no perfil do ouvinte (aba Sobre do Ultimate Member).
// A dona do perfil escolhe primeiro o SENTIMENTO (Sofrência, Romance…) e depois
// a MÚSICA daquele sentimento; o cartão da música aparece na hora, com
// "▶ Tocar" (player do rodapé), ❤ favoritar e ⭐ estrelas, sem sair do perfil.
// "💾 Guardar como minha favorita" grava o ID no meta cv_musica_favorita.
// Visitantes veem só o cartão. JS: assets/js/cv-musica-favorita.js;
// CSS: assets/css/cv-musica-favorita.css. Estrelas usam o cv_rate_music.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Musica_Favorita {

    const META = 'cv_musica_favorita';

    public static function init() {
        add_action( 'plugins_loaded',            array( __CLASS__, 'ganchos_um' ) );
        add_action( 'wp_ajax_cv_fav_cartao',     array( __CLASS__, 'ajax_cartao' ) );
        add_action( 'wp_ajax_cv_fav_salvar',     array( __CLASS__, 'ajax_salvar' ) );
    }

    public static function ganchos_um() {
        if ( ! class_exists( 'UM' ) ) { return; }
        // Depois dos campos da aba "Sobre" (o UM usa a prioridade 10)
        add_action( 'um_profile_content_main', array( __CLASS__, 'mostrar' ), 20, 2 );
    }

    /** ID da música favorita, só se ainda estiver publicada. */
    public static function da_pessoa( $user_id ) {
        $id = (int) get_user_meta( $user_id, self::META, true );
        if ( $id && 'musica' === get_post_type( $id ) && 'publish' === get_post_status( $id ) ) {
            return $id;
        }
        return 0;
    }

    /**
     * Sentimentos que têm pelo menos uma música publicada, cada um com a
     * lista de músicas (id + título). No fim, "🎵 Todas as músicas".
     */
    public static function opcoes() {
        $grupos = array();
        if ( class_exists( 'CV_Sentimentos' ) ) {
            foreach ( (array) CV_Sentimentos::get_all() as $s ) {
                $musicas = array();
                foreach ( (array) CV_Sentimentos::get_musicas_by_sentimento( $s->id, 500 ) as $m ) {
                    $musicas[] = array( 'id' => (int) $m->ID, 'titulo' => $m->post_title );
                }
                if ( $musicas ) {
                    $grupos[] = array( 'id' => (string) $s->id, 'nome' => trim( $s->icone . ' ' . $s->nome ), 'musicas' => $musicas );
                }
            }
        }
        $todas = array();
        $ids   = get_posts( array(
            'post_type' => 'musica', 'post_status' => 'publish', 'numberposts' => 500,
            'orderby' => 'title', 'order' => 'ASC', 'fields' => 'ids', 'no_found_rows' => true,
        ) );
        foreach ( $ids as $id ) {
            $todas[] = array( 'id' => (int) $id, 'titulo' => get_the_title( $id ) );
        }
        if ( $todas ) {
            $grupos[] = array( 'id' => 'todas', 'nome' => '🎵 Todas as músicas', 'musicas' => $todas );
        }
        return $grupos;
    }

    /** Cartão da música (template do tema) + estrelas (só para a dona do perfil). */
    public static function cartao_html( $music_id, $com_estrelas = true ) {
        $html  = '<div class="cv-um-grid cv-fav-cartao">' . cv_music_card( $music_id ) . '</div>';
        if ( $com_estrelas && is_user_logged_in() ) {
            $minha = class_exists( 'CV_Ratings' ) ? (int) CV_Ratings::get_user_rating_value( $music_id, get_current_user_id() ) : 0;
            $html .= '<div class="cv-fav-estrelas" role="group" aria-label="Dê a sua nota para esta música" data-music-id="' . (int) $music_id . '">';
            $html .= '<span class="cv-fav-estrelas-rotulo">Sua nota:</span>';
            for ( $i = 1; $i <= 5; $i++ ) {
                $html .= '<button type="button" class="cv-fav-estrela' . ( $i <= $minha ? ' cv-on' : '' ) . '" data-nota="' . $i . '"'
                       . ' aria-pressed="' . ( $i === $minha ? 'true' : 'false' ) . '"'
                       . ' aria-label="' . esc_attr( $i . ( 1 === $i ? ' estrela' : ' estrelas' ) ) . '">★</button>';
            }
            $html .= '<span class="cv-fav-estrelas-msg" aria-live="polite"></span></div>';
        }
        return $html;
    }

    /** Bloco no fim da aba "Sobre" (só no modo de ver, não no de editar). */
    public static function mostrar( $args, $form_id = 0 ) {
        if ( function_exists( 'UM' ) && ! empty( UM()->fields()->editing ) ) { return; }
        $dono_id = (int) um_profile_id();
        if ( ! $dono_id ) { return; }
        $e_dono  = ( get_current_user_id() === $dono_id );
        $musica  = self::da_pessoa( $dono_id );

        if ( ! $e_dono && ! $musica ) { return; } // visitante: nada a mostrar

        wp_enqueue_style( 'cv-musica-favorita', CV_PLUGIN_URL . 'assets/css/cv-musica-favorita.css', array(), CV_VERSION );

        echo '<section class="cv-fav" aria-labelledby="cv-fav-titulo">';
        echo '<h3 id="cv-fav-titulo" class="cv-fav-titulo">🎵 ' . ( $e_dono ? 'Minha música favorita' : 'Música favorita' ) . '</h3>';

        if ( $e_dono ) {
            $grupos = self::opcoes();
            if ( ! $grupos ) {
                echo '<p class="cv-fav-dica">As músicas do site estão chegando. Volte em breve para escolher a sua favorita!</p></section>';
                return;
            }
            // Sentimento já marcado: o primeiro grupo que contém a favorita
            $grupo_atual = '';
            foreach ( $grupos as $g ) {
                foreach ( $g['musicas'] as $m ) {
                    if ( $m['id'] === $musica ) { $grupo_atual = $g['id']; break 2; }
                }
            }
            wp_enqueue_script( 'cv-musica-favorita', CV_PLUGIN_URL . 'assets/js/cv-musica-favorita.js', array( 'jquery' ), CV_VERSION, true );
            wp_localize_script( 'cv-musica-favorita', 'cvFav', array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'cv_fav_nonce' ),
                'nota'    => wp_create_nonce( 'cv_rating_nonce' ),
                'grupos'  => $grupos,
                'musica'  => $musica,
                'grupo'   => $grupo_atual,
            ) );
            ?>
            <p class="cv-fav-dica">Escolha um sentimento e depois a música. Você pode ouvir antes de guardar.</p>
            <div class="cv-fav-escolha">
                <label class="cv-fav-campo">
                    <span>1. Qual sentimento?</span>
                    <select id="cv-fav-sentimento"><option value="">Escolha um sentimento</option></select>
                </label>
                <label class="cv-fav-campo">
                    <span>2. Qual música?</span>
                    <select id="cv-fav-musica" disabled><option value="">⬅ Escolha o sentimento</option></select>
                </label>
            </div>
            <?php
        }

        echo '<div id="cv-fav-previa" aria-live="polite">' . ( $musica ? self::cartao_html( $musica, $e_dono ) : '' ) . '</div>';

        if ( $e_dono ) {
            echo '<div class="cv-fav-acoes">'
               . '<button type="button" id="cv-fav-guardar" class="cv-btn cv-btn-primary" hidden>💾 Guardar como minha favorita</button>'
               . '<span id="cv-fav-msg" class="cv-fav-msg" role="status"></span></div>';
        }
        echo '</section>';
    }

    /** AJAX: cartão de uma música para a prévia. */
    public static function ajax_cartao() {
        check_ajax_referer( 'cv_fav_nonce', 'nonce' );
        $id = absint( isset( $_POST['music_id'] ) ? $_POST['music_id'] : 0 );
        if ( ! $id || 'musica' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
            wp_send_json_error( array( 'message' => 'Música não encontrada.' ) );
        }
        wp_send_json_success( array( 'html' => self::cartao_html( $id ) ) );
    }

    /** AJAX: guarda (ou tira, com 0) a música favorita da pessoa logada. */
    public static function ajax_salvar() {
        check_ajax_referer( 'cv_fav_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) { wp_send_json_error( array( 'message' => 'Entre na sua conta.' ) ); }
        $id = absint( isset( $_POST['music_id'] ) ? $_POST['music_id'] : 0 );
        if ( $id && ( 'musica' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) ) {
            wp_send_json_error( array( 'message' => 'Música não encontrada.' ) );
        }
        if ( $id ) {
            update_user_meta( get_current_user_id(), self::META, $id );
        } else {
            delete_user_meta( get_current_user_id(), self::META );
        }
        wp_send_json_success( array( 'titulo' => $id ? get_the_title( $id ) : '' ) );
    }
}

/**
 * Cartão de música (template-parts/card-musica.php do tema) como texto.
 * v2.65.0: as abas Histórico e Favoritas do perfil chamavam esta função,
 * que não existia — a aba quebrava quando havia alguma música na lista.
 */
if ( ! function_exists( 'cv_music_card' ) ) {
    function cv_music_card( $music_id ) {
        $music_id = (int) $music_id;
        if ( ! locate_template( 'template-parts/card-musica.php' ) ) {
            return '<p><a href="' . esc_url( get_permalink( $music_id ) ) . '">' . esc_html( get_the_title( $music_id ) ) . '</a></p>';
        }
        ob_start();
        get_template_part( 'template-parts/card-musica', null, array( 'music_id' => $music_id, 'show_rank' => false ) );
        return ob_get_clean();
    }
}

CV_Musica_Favorita::init();
