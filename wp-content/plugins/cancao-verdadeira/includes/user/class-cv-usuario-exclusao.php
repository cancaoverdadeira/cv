<?php
// cancao-verdadeira/includes/user/class-cv-usuario-exclusao.php
// Criado em: 25/09/2026 (plugin v2.46.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// O que faz: quando um usuário é excluído, cuida dos dados dele nas tabelas
// próprias do plugin (o WordPress só cuida de posts, páginas e metas).
// 1) Com "Atribuir todo o conteúdo a…" (tela Usuários → Excluir): playlists,
//    favoritos, notas, plays e comentários de trecho PASSAM para o usuário
//    escolhido. Favorito/nota que ele já tinha na mesma música fica o dele.
// 2) Sem atribuição (ex.: a pessoa apaga a própria conta): apaga playlists,
//    favoritos, notas e comentários; os plays ficam anônimos (user_id = 0)
//    para o ranking e os totais não mudarem.
// Os registros de cv_action_logs NÃO mudam: são auditoria (quem fez o quê).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Usuario_Exclusao {

    public static function init() {
        // Roda antes de o WordPress apagar o usuário (o ID ainda existe).
        add_action( 'delete_user', array( __CLASS__, 'ao_excluir' ), 10, 2 );
    }

    /**
     * Devolve um resumo (tabela => linhas afetadas), útil para testes.
     */
    public static function ao_excluir( $user_id, $reatribuir = null ) {
        $user_id    = absint( $user_id );
        $reatribuir = absint( $reatribuir );
        if ( ! $user_id ) { return array(); }
        if ( $reatribuir === $user_id ) { $reatribuir = 0; }

        return $reatribuir ? self::transferir( $user_id, $reatribuir ) : self::limpar( $user_id );
    }

    // ── 1. Transfere tudo para outro usuário ────────────────────────
    public static function transferir( $de, $para ) { // v2.52.0: pública (usada também por CV_Transferir_Autoria)
        global $wpdb;
        $resumo = array();

        // Tabelas sem regra de "um por música": só troca o dono.
        foreach ( array( 'cv_playlists', 'cv_plays_log', 'cv_lyric_comments' ) as $tabela ) {
            $nome = $wpdb->prefix . $tabela;
            if ( ! self::tabela_existe( $nome ) ) { continue; }
            $n = $wpdb->update( $nome, array( 'user_id' => $para ), array( 'user_id' => $de ), array( '%d' ), array( '%d' ) );
            if ( $n ) { $resumo[ $tabela ] = (int) $n; }
        }

        // Favoritos e notas: só um por pessoa e música (chave user_music).
        // UPDATE IGNORE passa o que não repete; o que sobrar é duplicata e sai.
        foreach ( array( 'cv_favorites', 'cv_ratings' ) as $tabela ) {
            $nome = $wpdb->prefix . $tabela;
            if ( ! self::tabela_existe( $nome ) ) { continue; }
            $n = $wpdb->query( $wpdb->prepare( "UPDATE IGNORE {$nome} SET user_id = %d WHERE user_id = %d", $para, $de ) );
            $wpdb->delete( $nome, array( 'user_id' => $de ), array( '%d' ) );
            if ( $n ) { $resumo[ $tabela ] = (int) $n; }
        }
        return $resumo;
    }

    // ── 2. Sem herdeiro: apaga o que é pessoal ─────────────────────
    private static function limpar( $user_id ) {
        global $wpdb;
        $resumo = array();

        $playlists = $wpdb->prefix . 'cv_playlists';
        $itens     = $wpdb->prefix . 'cv_playlist_items';
        if ( self::tabela_existe( $playlists ) ) {
            $ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$playlists} WHERE user_id = %d", $user_id ) );
            if ( $ids && self::tabela_existe( $itens ) ) {
                $lista = implode( ',', array_map( 'absint', $ids ) );
                $wpdb->query( "DELETE FROM {$itens} WHERE playlist_id IN ({$lista})" );
            }
            $n = $wpdb->delete( $playlists, array( 'user_id' => $user_id ), array( '%d' ) );
            if ( $n ) { $resumo['cv_playlists'] = (int) $n; }
        }

        foreach ( array( 'cv_favorites', 'cv_ratings', 'cv_lyric_comments' ) as $tabela ) {
            $nome = $wpdb->prefix . $tabela;
            if ( ! self::tabela_existe( $nome ) ) { continue; }
            $n = $wpdb->delete( $nome, array( 'user_id' => $user_id ), array( '%d' ) );
            if ( $n ) { $resumo[ $tabela ] = (int) $n; }
        }

        $plays = $wpdb->prefix . 'cv_plays_log';
        if ( self::tabela_existe( $plays ) ) {
            $n = $wpdb->update( $plays, array( 'user_id' => 0 ), array( 'user_id' => $user_id ), array( '%d' ), array( '%d' ) );
            if ( $n ) { $resumo['cv_plays_log'] = (int) $n; }
        }
        return $resumo;
    }

    private static function tabela_existe( $nome ) {
        global $wpdb;
        return $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $nome ) ) === $nome;
    }
}

CV_Usuario_Exclusao::init();
