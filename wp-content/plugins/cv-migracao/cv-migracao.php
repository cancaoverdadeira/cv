<?php
/**
 * Plugin Name: CV Migração (provisório)
 * Description: Ajuda a levar o site Canção Verdadeira do computador (cv.local) para o ar (cancaoverdadeira.com.br): confere tudo antes do backup, corrige o endereço depois da restauração e troca "cv.local" pelo endereço de verdade no banco, sempre com prévia. Apague depois da subida.
 * Version:     1.0.0
 * Author:      Canção Verdadeira
 * Requires PHP: 7.2
 * Text Domain: cv-migracao
 */
// cv-migracao/cv-migracao.php
// Projeto : Canção Verdadeira — plugin PROVISÓRIO da migração (26/09/2026)
// Função  : junta as peças: CVM_Endereco (corrige home/siteurl no 1º acesso
//           ao site no ar), CVM_Troca (troca cv.local pelo endereço novo em
//           todas as tabelas, com prévia e em lotes), CVM_Verificacoes (lista
//           do que conferir antes e depois) e CVM_Painel (Ferramentas → 🚚
//           Migração CV). Nada aqui apaga dados. No computador (ambiente
//           "local") a troca só roda em modo PRÉVIA.
// Depois : quando o site no ar estiver conferido, desative e apague o plugin.

if ( ! defined( 'ABSPATH' ) ) { exit; }

define( 'CVM_VERSAO',       '1.0.0' );
define( 'CVM_DIR',          plugin_dir_path( __FILE__ ) );
define( 'CVM_URL',          plugin_dir_url( __FILE__ ) );
// De onde o site vem (o computador do Eduardo, LocalWP no Ubuntu)
define( 'CVM_ORIGEM_HOST',  'cv.local' );
define( 'CVM_ORIGEM_PASTA', '/home/casa/Local Sites/cv/app/public' );
// Únicos endereços aceitos como destino (proteção: nunca troca para outro)
define( 'CVM_DESTINOS',     'cancaoverdadeira.com.br,www.cancaoverdadeira.com.br' );

require_once CVM_DIR . 'includes/class-cvm-endereco.php';
require_once CVM_DIR . 'includes/class-cvm-troca.php';
require_once CVM_DIR . 'includes/class-cvm-verificacoes.php';
require_once CVM_DIR . 'includes/class-cvm-painel.php';

/** true no computador (LocalWP define WP_ENVIRONMENT_TYPE = local). */
function cvm_no_computador() {
    return in_array( wp_get_environment_type(), array( 'local', 'development' ), true );
}

CVM_Endereco::init();
CVM_Painel::init();
