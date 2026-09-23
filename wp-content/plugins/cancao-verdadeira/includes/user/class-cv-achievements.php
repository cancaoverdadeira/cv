<?php
// cancao-verdadeira-plugin/includes/user/class-cv-achievements.php
// Gerado em: 2025-06-03 00:00:00
// Projeto: Cancao Verdadeira - Plataforma de letras musicais sertanejas
// Sistema de conquistas (badges) para usuarios ativos: concedidas
// automaticamente ao atingir marcos de plays, favoritos, playlists
// e avaliacoes. Armazena em user_meta _cv_achievements.
// Exibe badge visual no perfil publico e no dashboard do usuario.
// v2.26.0: "Puro Sertanejo" agora vale para quem ouviu 20 musicas diferentes
// (antes exigia ouvir todos os generos, que foram removidos do site).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Achievements {

    // Definicao de todas as conquistas disponiveis
    const BADGES = array(
        'first_play'    => array( 'icon' => '🎵', 'name' => 'Primeira Escuta',  'desc' => 'Ouviu a primeira musica',          'color' => '#3498db' ),
        'plays_10'      => array( 'icon' => '🎧', 'name' => 'Ouvinte',           'desc' => '10 musicas ouvidas',               'color' => '#27ae60' ),
        'plays_50'      => array( 'icon' => '🎼', 'name' => 'Melomano',          'desc' => '50 musicas ouvidas',               'color' => '#8e44ad' ),
        'plays_100'     => array( 'icon' => '🏅', 'name' => 'Fanatico',          'desc' => '100 musicas ouvidas',              'color' => '#B8700C' ),
        'plays_500'     => array( 'icon' => '👑', 'name' => 'Lenda do Sertao',  'desc' => '500 musicas ouvidas',              'color' => '#e74c3c' ),
        'first_fav'     => array( 'icon' => '❤',  'name' => 'Coracao Aberto',   'desc' => 'Favoritou a primeira musica',      'color' => '#e91e63' ),
        'favs_10'       => array( 'icon' => '💝', 'name' => 'Colecionador',      'desc' => '10 musicas favoritadas',           'color' => '#c0392b' ),
        'favs_50'       => array( 'icon' => '💖', 'name' => 'Apaixonado',        'desc' => '50 musicas favoritadas',           'color' => '#e74c3c' ),
        'first_playlist'=> array( 'icon' => '📋', 'name' => 'Criador',          'desc' => 'Criou a primeira playlist',        'color' => '#2980b9' ),
        'playlists_5'   => array( 'icon' => '🎛', 'name' => 'DJ do Sertao',     'desc' => '5 playlists criadas',              'color' => '#1abc9c' ),
        'first_rating'  => array( 'icon' => '⭐', 'name' => 'Critico Musical',  'desc' => 'Deu a primeira avaliacao',         'color' => '#B8700C' ),
        'first_comment' => array( 'icon' => '💬', 'name' => 'Comentarista',     'desc' => 'Comentou em um trecho de letra',   'color' => '#16a085' ),
        'raiz'          => array( 'icon' => '🪗', 'name' => 'Puro Sertanejo',   'desc' => 'Ouviu 20 musicas diferentes',      'color' => '#8B4513' ),
    );

    public static function init() {
        // Verifica conquistas apos cada play
        add_action( 'wp_ajax_cv_register_play', array( __CLASS__, 'check_after_play' ), 20 );
        // Verifica apos favoritar
        add_action( 'wp_ajax_cv_toggle_favorite', array( __CLASS__, 'check_after_favorite' ), 20 );
        // Verifica apos avaliar
        add_action( 'wp_ajax_cv_rate_music', array( __CLASS__, 'check_after_rating' ), 20 );
        // Verifica apos comentar
        add_action( 'wp_ajax_cv_save_lyric_comment', array( __CLASS__, 'check_after_comment' ), 20 );

        // Endpoint para buscar conquistas do usuario
        add_action( 'wp_ajax_cv_get_achievements', array( __CLASS__, 'get_achievements' ) );
        add_action( 'wp_ajax_nopriv_cv_get_achievements', array( __CLASS__, 'get_achievements' ) );
    }

    /**
     * Verifica e concede conquistas baseadas em plays.
     */
    public static function check_after_play() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) { return; }

        $history   = get_user_meta( $user_id, '_cv_play_history', true );
        $play_count= is_array( $history ) ? count( $history ) : 0;

        self::maybe_grant( $user_id, 'first_play', $play_count >= 1 );
        self::maybe_grant( $user_id, 'plays_10',   $play_count >= 10 );
        self::maybe_grant( $user_id, 'plays_50',   $play_count >= 50 );
        self::maybe_grant( $user_id, 'plays_100',  $play_count >= 100 );
        self::maybe_grant( $user_id, 'plays_500',  $play_count >= 500 );

        // Badge "Puro Sertanejo": ouviu 20 musicas diferentes
        self::check_variety( $user_id );
    }

    public static function check_after_favorite() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) { return; }

        global $wpdb;
        $fav_count = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_favorites WHERE user_id = %d",
            $user_id
        ) );

        self::maybe_grant( $user_id, 'first_fav', $fav_count >= 1 );
        self::maybe_grant( $user_id, 'favs_10',   $fav_count >= 10 );
        self::maybe_grant( $user_id, 'favs_50',   $fav_count >= 50 );
    }

    public static function check_after_rating() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) { return; }
        self::maybe_grant( $user_id, 'first_rating', true );
    }

    public static function check_after_comment() {
        $user_id = get_current_user_id();
        if ( ! $user_id ) { return; }
        self::maybe_grant( $user_id, 'first_comment', true );
    }

    private static function check_variety( $user_id ) {
        $history = get_user_meta( $user_id, '_cv_play_history', true );
        if ( ! is_array( $history ) ) { return; }
        $ids = array();
        foreach ( $history as $item ) {
            if ( ! empty( $item['id'] ) ) { $ids[ (int) $item['id'] ] = true; }
        }
        self::maybe_grant( $user_id, 'raiz', count( $ids ) >= 20 );
    }

    /**
     * Concede a conquista se ainda nao foi concedida.
     * Retorna true se foi concedida agora (nova), false se ja tinha.
     */
    public static function maybe_grant( $user_id, $badge_key, $condition ) {
        if ( ! $condition ) { return false; }

        $achievements = get_user_meta( $user_id, '_cv_achievements', true );
        if ( ! is_array( $achievements ) ) { $achievements = array(); }

        if ( isset( $achievements[ $badge_key ] ) ) { return false; }

        $achievements[ $badge_key ] = array(
            'granted_at' => current_time( 'timestamp' ),
        );

        update_user_meta( $user_id, '_cv_achievements', $achievements );

        // Envia notificacao da conquista
        if ( class_exists( 'CV_Notifications' ) && isset( self::BADGES[ $badge_key ] ) ) {
            $badge = self::BADGES[ $badge_key ];
            CV_Notifications::add(
                $user_id,
                'achievement',
                'Conquista desbloqueada: ' . $badge['name'] . '!',
                '',
                $badge['icon']
            );
        }

        return true;
    }

    /**
     * Retorna todas as conquistas de um usuario com status (conquistado/nao).
     */
    public static function get_user_achievements( $user_id ) {
        $earned = get_user_meta( $user_id, '_cv_achievements', true );
        if ( ! is_array( $earned ) ) { $earned = array(); }

        $result = array();
        foreach ( self::BADGES as $key => $badge ) {
            $result[] = array(
                'key'        => $key,
                'icon'       => $badge['icon'],
                'name'       => $badge['name'],
                'desc'       => $badge['desc'],
                'color'      => $badge['color'],
                'earned'     => isset( $earned[ $key ] ),
                'earned_at'  => isset( $earned[ $key ] ) ? $earned[ $key ]['granted_at'] : null,
            );
        }

        return $result;
    }

    // AJAX endpoint publico
    public static function get_achievements() {
        $user_id = absint( $_GET['user_id'] ?? get_current_user_id() );
        if ( ! $user_id ) { wp_send_json_success( array( 'achievements' => array() ) ); }

        wp_send_json_success( array(
            'achievements' => self::get_user_achievements( $user_id ),
        ) );
    }
}

CV_Achievements::init();
