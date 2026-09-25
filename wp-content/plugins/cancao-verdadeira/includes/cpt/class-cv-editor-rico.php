<?php
// cancao-verdadeira/includes/cpt/class-cv-editor-rico.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Editor rico "estilo Word" (v2.45.0, 25/09/2026)
// Funções : 1) Posts do blog voltam ao editor clássico (sai o editor de blocos),
//           com a barra completa: estilo do parágrafo, tamanho da letra, cores,
//           alinhamentos, listas, citação, linha, tabela de símbolos, colar
//           como texto e tela cheia. 2) A letra da música usa a mesma barra
//           (CV_Metaboxes::render_letra lê self::barra_1() e self::barra_2()).
// Por quê : tudo vem do TinyMCE que já está dentro do WordPress — não precisa
//           instalar o Advanced Editor Tools (só ele traria "tabela").
// Nota    : posts já escritos em blocos continuam iguais no site; no editor
//           clássico os blocos viram HTML comum.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Editor_Rico {

    public static function init() {
        add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'sem_blocos' ), 100, 2 );
        add_filter( 'mce_buttons',                    array( __CLASS__, 'botoes_1' ), 100, 2 );
        add_filter( 'mce_buttons_2',                  array( __CLASS__, 'botoes_2' ), 100, 2 );
        add_filter( 'tiny_mce_before_init',           array( __CLASS__, 'config' ), 100, 2 );
    }

    // ── Barras de ferramentas (mesmas no blog e na letra) ───────────
    public static function barra_1() {
        return array( 'formatselect', 'fontsizeselect', 'bold', 'italic', 'underline', 'strikethrough',
            'forecolor', 'backcolor', 'alignleft', 'aligncenter', 'alignright', 'alignjustify',
            'link', 'unlink', 'fullscreen' );
    }

    public static function barra_2() {
        return array( 'bullist', 'numlist', 'outdent', 'indent', 'blockquote', 'hr', 'charmap',
            'pastetext', 'removeformat', 'undo', 'redo' );
    }

    // ── Blog: editor clássico no lugar do editor de blocos ──────────
    public static function sem_blocos( $usar, $post_type ) {
        return ( 'post' === $post_type ) ? false : $usar;
    }

    // ── Aplica as barras só no editor principal dos posts do blog ───
    // (a letra da música monta a própria barra em render_letra)
    public static function botoes_1( $botoes, $editor_id ) {
        return self::eh_editor_do_blog( $editor_id ) ? self::barra_1() : $botoes;
    }

    public static function botoes_2( $botoes, $editor_id ) {
        return self::eh_editor_do_blog( $editor_id ) ? self::barra_2() : $botoes;
    }

    private static function eh_editor_do_blog( $editor_id ) {
        if ( 'content' !== $editor_id || ! is_admin() ) { return false; }
        $tela = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
        return $tela && 'post' === $tela->base && 'post' === $tela->post_type;
    }

    // ── Opções em português: tamanhos e estilos de parágrafo ────────
    public static function config( $init, $editor_id ) {
        if ( ! self::eh_editor_do_blog( $editor_id ) && 'cv_letra' !== $editor_id ) { return $init; }
        $init['fontsize_formats'] = '12px 14px 16px 18px 20px 24px 28px 32px';
        $init['block_formats']    = 'Parágrafo=p;Título 2=h2;Título 3=h3;Título 4=h4;Citação=blockquote';
        // Barra 2 sempre aberta (sem precisar clicar no "mostrar mais").
        $init['wordpress_adv_hidden'] = false;
        return $init;
    }
}

CV_Editor_Rico::init();
