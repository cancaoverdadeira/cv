<?php
// cancao-verdadeira/includes/cpt/class-cv-exclusao.php
// Criado em: 24/09/2026 (plugin v2.39.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Exclusão completa ("eliminação física") de uma música. Quando uma música é
// apagada DE VEZ (esvaziar lixeira, "Excluir permanentemente"), o WordPress só
// apaga o post, os metas e os termos.
// Este módulo apaga também tudo o que fica nas tabelas próprias (plays, favoritos,
// notas, playlists, ranking, comentários de trecho, sentimentos, logs antigos),
// a capa importada do YouTube e os caches de ranking/SEO/gráficos.
// Mandar para a LIXEIRA não apaga nada daqui (a lixeira pode ser desfeita).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Exclusao {

    // Tabela (sem prefixo) => coluna que guarda o ID da música.
    private static $tabelas = array(
        'cv_plays_log'          => 'music_id',
        'cv_favorites'          => 'music_id',
        'cv_ratings'            => 'music_id',
        'cv_playlist_items'     => 'music_id',
        'cv_ranking_cache'      => 'music_id',
        'cv_lyric_comments'     => 'music_id',
        'cv_musica_sentimentos' => 'musica_id',
    );

    public static function init() {
        // Prioridade 20: roda DEPOIS do log "music_deleted" (CV_Advanced, prioridade 10),
        // enquanto o post e a imagem destacada ainda existem no banco.
        add_action( 'before_delete_post', array( __CLASS__, 'limpar_musica' ), 20, 1 );
    }

    /**
     * Apaga todos os dados ligados à música. Devolve um resumo (tabela => linhas
     * apagadas), útil para testes e para o log.
     */
    public static function limpar_musica( $post_id ) {
        $post_id = absint( $post_id );
        if ( ! $post_id || 'musica' !== get_post_type( $post_id ) ) { return array(); }

        global $wpdb;
        $resumo = array();

        // 1. Tabelas próprias do plugin
        foreach ( self::$tabelas as $tabela => $coluna ) {
            $nome = $wpdb->prefix . $tabela;
            if ( ! self::tabela_existe( $nome ) ) { continue; }
            $n = $wpdb->delete( $nome, array( $coluna => $post_id ), array( '%d' ) );
            if ( $n ) { $resumo[ $tabela ] = (int) $n; }
        }

        // 2. Logs antigos da música. Fica só o registro "music_deleted",
        //    como prova de quem apagou e quando (auditoria).
        $logs = $wpdb->prefix . 'cv_action_logs';
        if ( self::tabela_existe( $logs ) ) {
            $n = $wpdb->query( $wpdb->prepare(
                "DELETE FROM {$logs} WHERE object_type = 'musica' AND object_id = %d AND action <> 'music_deleted'",
                $post_id
            ) );
            if ( $n ) { $resumo['cv_action_logs'] = (int) $n; }
        }

        // 3. Imagens: a capa e os anexos enviados para esta música
        //    (ex.: yt-<id>.jpg da importação), se nenhum outro post usar.
        $anexos = get_posts( array(
            'post_type'      => 'attachment',
            'post_parent'    => $post_id,
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ) );
        $capa = (int) get_post_thumbnail_id( $post_id );
        if ( $capa ) { $anexos[] = $capa; }
        $apagados = 0;
        foreach ( array_unique( array_map( 'intval', $anexos ) ) as $anexo_id ) {
            if ( self::imagem_em_uso( $anexo_id, $post_id ) ) { continue; }
            if ( wp_delete_attachment( $anexo_id, true ) ) { $apagados++; }
        }
        if ( $apagados ) { $resumo['anexos'] = $apagados; }

        // 4. Caches que podem ainda mostrar a música
        if ( class_exists( 'CV_Ranking' ) )       { CV_Ranking::flush_cache(); }
        if ( class_exists( 'CV_Admin_SEO' ) )     { CV_Admin_SEO::clear_cache(); }
        if ( class_exists( 'CV_Admin_Charts' ) )  { CV_Admin_Charts::clear_cache(); }
        delete_transient( 'cv_launch_ranking_ready' );

        do_action( 'cv_musica_eliminada', $post_id, $resumo );
        return $resumo;
    }

    // A imagem é capa de outro post, ou pertence a outro post?
    private static function imagem_em_uso( $anexo_id, $post_id ) {
        global $wpdb;
        $pai = (int) wp_get_post_parent_id( $anexo_id );
        if ( $pai && $pai !== $post_id ) { return true; }
        $outros = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->postmeta}
             WHERE meta_key = '_thumbnail_id' AND meta_value = %s AND post_id <> %d",
            (string) $anexo_id, $post_id
        ) );
        return $outros > 0;
    }

    private static function tabela_existe( $nome ) {
        static $cache = array();
        if ( ! isset( $cache[ $nome ] ) ) {
            global $wpdb;
            $cache[ $nome ] = ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nome ) ) === $nome );
        }
        return $cache[ $nome ];
    }
}

CV_Exclusao::init();
