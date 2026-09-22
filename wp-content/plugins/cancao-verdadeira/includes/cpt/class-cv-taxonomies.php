<?php
// cancao-verdadeira-plugin/includes/cpt/class-cv-taxonomies.php
// Gerado em: 2025-06-01 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Registra as taxonomias cv_genre (gênero musical) e cv_subcategory
// (subcategoria/estilo) associadas ao CPT musica.
// Os termos iniciais são semeados na ativação do plugin (cv_seed_genres).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Taxonomies {

    public static function init() {
        add_action( 'init', array( __CLASS__, 'register' ) );
    }

    public static function register() {

        // ── Gênero ─────────────────────────────────────────────────────────
        register_taxonomy( 'cv_genre', 'musica', array(
            'labels'            => array(
                'name'          => 'Gêneros',
                'singular_name' => 'Gênero',
                'search_items'  => 'Buscar Gêneros',
                'all_items'     => 'Todos os Gêneros',
                'edit_item'     => 'Editar Gênero',
                'update_item'   => 'Atualizar Gênero',
                'add_new_item'  => 'Adicionar Novo Gênero',
                'new_item_name' => 'Novo Gênero',
                'menu_name'     => 'Gêneros',
            ),
            'hierarchical'      => true,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'genero', 'with_front' => false ),
            'show_in_rest'      => false,
        ) );

        // ── Subcategoria ───────────────────────────────────────────────────
        register_taxonomy( 'cv_subcategory', 'musica', array(
            'labels'            => array(
                'name'          => 'Subcategorias',
                'singular_name' => 'Subcategoria',
                'search_items'  => 'Buscar Subcategorias',
                'all_items'     => 'Todas as Subcategorias',
                'edit_item'     => 'Editar Subcategoria',
                'update_item'   => 'Atualizar Subcategoria',
                'add_new_item'  => 'Adicionar Subcategoria',
                'new_item_name' => 'Nova Subcategoria',
                'menu_name'     => 'Subcategorias',
            ),
            'hierarchical'      => false,
            'show_ui'           => true,
            'show_in_menu'      => false,
            'show_admin_column' => true,
            'query_var'         => true,
            'rewrite'           => array( 'slug' => 'estilo', 'with_front' => false ),
            'show_in_rest'      => false,
        ) );
    }
}

CV_Taxonomies::init();
