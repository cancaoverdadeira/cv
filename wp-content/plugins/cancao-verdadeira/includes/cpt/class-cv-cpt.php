<?php
// cancao-verdadeira-plugin/includes/cpt/class-cv-cpt.php
// Gerado em: 2025-06-01 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Registra o Custom Post Type "musica" com suporte a thumbnail,
// editor de letra via TinyMCE, campos customizados (ACF), SEO e
// controle de visibilidade. Todas as músicas são gerenciadas pelo
// painel administrativo do plugin, sem uso do Gutenberg.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_CPT {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'init', array( __CLASS__, 'disable_gutenberg' ) );
        add_filter( 'use_block_editor_for_post_type', array( __CLASS__, 'force_classic_editor' ), 10, 2 );
    }

    public static function register() {
        $labels = array(
            'name'               => 'Músicas',
            'singular_name'      => 'Música',
            'add_new'            => 'Nova Música',
            'add_new_item'       => 'Cadastrar Nova Música',
            'edit_item'          => 'Editar Música',
            'new_item'           => 'Nova Música',
            'view_item'          => 'Ver Música',
            'search_items'       => 'Buscar Músicas',
            'not_found'          => 'Nenhuma música encontrada',
            'not_found_in_trash' => 'Nenhuma música na lixeira',
            'menu_name'          => 'Músicas',
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => false, // aparece no menu próprio do plugin
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'musica', 'with_front' => false ),
            'capability_type'    => 'post',
            'has_archive'        => 'musicas',
            'hierarchical'       => false,
            'menu_position'      => null,
            // Sem 'editor': a letra (post_content) é editada só pelo metabox
            // "Letra da Música" (CV_Metaboxes::render_letra). Com os dois, havia
            // dois campos name="content" na tela e só o último era salvo.
            'supports'           => array( 'title', 'thumbnail', 'custom-fields', 'comments' ),
            'show_in_rest'       => false, // usamos REST própria
            'taxonomies'         => array( 'post_tag' ), // v2.26.0: sem gêneros
        );

        register_post_type( 'musica', $args );
    }

    public static function force_classic_editor( $use_block_editor, $post_type ) {
        if ( 'musica' === $post_type ) {
            return false;
        }
        return $use_block_editor;
    }

    public static function disable_gutenberg() {
        // Garante TinyMCE no CPT musica mesmo com plugins de blocos ativos
        add_filter( 'gutenberg_can_edit_post_type', array( __CLASS__, 'force_classic_editor' ), 10, 2 );
    }
}

CV_CPT::init();
