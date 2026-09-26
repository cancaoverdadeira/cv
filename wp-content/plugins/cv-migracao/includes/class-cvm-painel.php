<?php
// cv-migracao/includes/class-cvm-painel.php
// Projeto : Canção Verdadeira — plugin provisório da migração
// Função  : tela Ferramentas → "🚚 Migração CV".
//           · Lista de conferência (CVM_Verificacoes): "Antes do backup" no
//             computador, "Depois da restauração" no site no ar.
//           · Botões seguros (admin-post + nonce): desativar o Query Monitor,
//             refazer os links, limpar os caches, criar a pasta privada.
//           · "Troca do endereço" (CVM_Troca) por AJAX em lotes: 1) Prévia
//             (só conta e mostra exemplos) 2) Aplicar (só no site no ar,
//             depois da prévia e com a caixa "fiz o backup" marcada).
//           · Relatório em texto para copiar e colar para o Claude.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CVM_Painel {

    const PAGINA = 'cv-migracao';

    public static function init() {
        add_action( 'admin_menu',                  array( __CLASS__, 'menu' ) );
        add_action( 'admin_post_cvm_acao',         array( __CLASS__, 'acao' ) );
        add_action( 'wp_ajax_cvm_lote',            array( __CLASS__, 'ajax_lote' ) );
        add_action( 'admin_enqueue_scripts',       array( __CLASS__, 'scripts' ) );
        add_filter( 'plugin_action_links_cv-migracao/cv-migracao.php', array( __CLASS__, 'link_plugins' ) );
    }

    public static function menu() {
        add_management_page( 'Migração CV', '🚚 Migração CV', 'manage_options', self::PAGINA, array( __CLASS__, 'render' ) );
    }

    public static function link_plugins( $links ) {
        array_unshift( $links, '<a href="' . esc_url( admin_url( 'tools.php?page=' . self::PAGINA ) ) . '">Abrir</a>' );
        return $links;
    }

    /** Destino da troca: no site no ar é o endereço atual; no computador, só para a prévia. */
    private static function destino() {
        if ( cvm_no_computador() ) {
            return array( 'url' => 'https://cancaoverdadeira.com.br', 'pasta' => '/PASTA-DO-SITE-NO-AR', 'pode_aplicar' => false );
        }
        $home = untrailingslashit( home_url() );
        $ok = CVM_Endereco::host_e_destino( (string) wp_parse_url( $home, PHP_URL_HOST ) );
        return array( 'url' => $home, 'pasta' => untrailingslashit( ABSPATH ), 'pode_aplicar' => $ok );
    }

    public static function scripts( $hook ) {
        if ( 'tools_page_' . self::PAGINA !== $hook ) { return; }
        wp_enqueue_script( 'cvm', CVM_URL . 'assets/cvm.js', array( 'jquery' ), CVM_VERSAO, true );
        wp_localize_script( 'cvm', 'cvm', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cvm_lote' ),
            'tabelas' => array_keys( CVM_Troca::tabelas() ),
        ) );
    }

    // ── Botões seguros ────────────────────────────────────────────
    public static function acao() {
        if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Sem permissão.' ); }
        $acao = sanitize_key( $_GET['faz'] ?? '' );
        check_admin_referer( 'cvm_' . $acao );
        $msg = '';
        if ( 'desativar_qm' === $acao ) {
            deactivate_plugins( 'query-monitor/query-monitor.php' );
            $msg = 'Query Monitor desativado.';
        } elseif ( 'refazer_links' === $acao ) {
            flush_rewrite_rules( true );
            $msg = 'Links permanentes gravados de novo.';
        } elseif ( 'limpar_cache' === $acao ) {
            wp_cache_flush();
            if ( function_exists( 'rocket_clean_domain' ) ) { rocket_clean_domain(); }
            if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) { \Elementor\Plugin::$instance->files_manager->clear_cache(); }
            $msg = 'Caches limpos (WordPress, WP Rocket e estilos do Elementor).';
        } elseif ( 'criar_privado' === $acao ) {
            $dir = dirname( untrailingslashit( ABSPATH ) ) . '/cv-privado';
            if ( wp_mkdir_p( $dir ) ) {
                @file_put_contents( $dir . '/index.php', "<?php\n// Silêncio é ouro.\n" ); // phpcs:ignore
                @file_put_contents( $dir . '/.htaccess', "Require all denied\nDeny from all\n" ); // phpcs:ignore
                $msg = 'Pasta privada criada fora do site.';
            } else {
                $msg = 'Não foi possível criar a pasta (a hospedagem não deixou). O plugin usará uploads/cv-privado.';
            }
        }
        update_option( 'cvm_log', array_slice( array_merge( array( current_time( 'mysql' ) . ' — ' . $msg ), (array) get_option( 'cvm_log', array() ) ), 0, 30 ), false );
        wp_safe_redirect( admin_url( 'tools.php?page=' . self::PAGINA . '&feito=' . rawurlencode( $msg ) ) );
        exit;
    }

    // ── Troca do endereço (AJAX, em lotes) ────────────────────────
    public static function ajax_lote() {
        check_ajax_referer( 'cvm_lote', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
        $d = self::destino();
        $aplicar = ! empty( $_POST['aplicar'] );
        if ( $aplicar && ! $d['pode_aplicar'] ) {
            wp_send_json_error( array( 'message' => cvm_no_computador() ? 'No computador a troca só roda em PRÉVIA.' : 'O endereço do site ainda não é o do site no ar.' ) );
        }
        if ( $aplicar && empty( $_POST['backup'] ) ) { wp_send_json_error( array( 'message' => 'Marque a caixa confirmando o backup.' ) ); }
        $tabela = sanitize_text_field( wp_unslash( $_POST['tabela'] ?? '' ) );
        $cursor = sanitize_text_field( wp_unslash( $_POST['cursor'] ?? '' ) );
        @set_time_limit( 120 ); // phpcs:ignore
        $r = CVM_Troca::lote( $tabela, $cursor, $aplicar, CVM_Troca::pares( $d['url'], $d['pasta'] ) );
        if ( $aplicar && $r['fim'] ) {
            update_option( 'cvm_log', array_slice( array_merge( array( current_time( 'mysql' ) . " — troca aplicada em {$tabela}: {$r['linhas']} linha(s)" ), (array) get_option( 'cvm_log', array() ) ), 0, 60 ), false );
        }
        wp_send_json_success( $r );
    }

    private static function botao( $item ) {
        if ( ! empty( $item['acao'] ) ) {
            $u = wp_nonce_url( admin_url( 'admin-post.php?action=cvm_acao&faz=' . $item['acao'] ), 'cvm_' . $item['acao'] );
            return '<a class="button button-primary" href="' . esc_url( $u ) . '">' . esc_html( $item['rotulo'] ) . '</a>';
        }
        if ( ! empty( $item['link'] ) ) {
            return '<a class="button" href="' . esc_url( $item['link'] ) . '">' . esc_html( $item['rotulo'] ) . '</a>';
        }
        return '';
    }

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $local = cvm_no_computador();
        $lista = $local ? CVM_Verificacoes::antes() : CVM_Verificacoes::depois();
        $d     = self::destino();
        $icone = array( 'ok' => '✅', 'aviso' => '⚠️', 'erro' => '⛔', 'info' => 'ℹ️' );
        $relatorio = 'CV Migração ' . CVM_VERSAO . ' — ' . ( $local ? 'COMPUTADOR (antes do backup)' : 'SITE NO AR (depois da restauração)' ) . ' — ' . home_url() . "\n";
        foreach ( $lista as $i ) { $relatorio .= $icone[ $i['estado'] ] . ' ' . $i['titulo'] . ': ' . wp_strip_all_tags( $i['detalhe'] ) . "\n"; }
        $feito = isset( $_GET['feito'] ) ? sanitize_text_field( wp_unslash( $_GET['feito'] ) ) : '';
        ?>
        <div class="wrap" style="max-width:1100px">
            <h1>🚚 Migração CV <span style="font-size:14px;color:#8A6A55">(plugin provisório <?php echo esc_html( CVM_VERSAO ); ?>)</span></h1>
            <p style="font-size:15px;padding:12px 16px;border-radius:8px;background:<?php echo $local ? '#F3E6D3' : '#E3F4EA'; ?>">
                <?php if ( $local ) : ?>
                    <strong>💻 Você está no COMPUTADOR.</strong> Resolva os ⚠️ e ⛔ abaixo <strong>antes</strong> de fazer o backup no UpdraftPlus. Nada aqui muda o site no ar.
                <?php else : ?>
                    <strong>🌐 Você está no SITE NO AR.</strong> Siga a lista de cima para baixo. A troca do endereço tem prévia antes de mudar qualquer coisa.
                <?php endif; ?>
            </p>
            <?php if ( $feito ) : ?><div class="notice notice-success"><p><?php echo esc_html( $feito ); ?></p></div><?php endif; ?>

            <h2><?php echo $local ? 'Lista de conferência: antes do backup' : 'Lista de conferência: depois da restauração'; ?></h2>
            <table class="widefat striped" style="font-size:14px">
                <tbody>
                <?php $grupo = ''; foreach ( $lista as $i ) : ?>
                    <?php if ( $i['grupo'] !== $grupo ) : $grupo = $i['grupo']; ?>
                    <tr><th colspan="3" style="background:#FBF6EE;font-size:15px"><?php echo esc_html( $grupo ); ?></th></tr>
                    <?php endif; ?>
                    <tr>
                        <td style="width:34px;font-size:20px;text-align:center"><?php echo esc_html( $icone[ $i['estado'] ] ); ?></td>
                        <td><strong><?php echo esc_html( $i['titulo'] ); ?></strong><br><span style="color:#5a4636"><?php echo esc_html( $i['detalhe'] ); ?></span></td>
                        <td style="width:220px;text-align:right"><?php echo self::botao( $i ); // phpcs:ignore ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <h2 style="margin-top:28px">🔁 Troca do endereço no banco</h2>
            <p style="font-size:14px">Troca <code>http://cv.local</code> por <code><?php echo esc_html( $d['url'] ); ?></code> e a pasta do computador pela pasta do site, em todas as tabelas, sem estragar os dados dos plugins.
                <?php if ( $local ) : ?><br><em>No computador só funciona a PRÉVIA (para você ver quanta coisa será trocada lá).</em><?php endif; ?></p>
            <p>
                <button type="button" class="button button-secondary" id="cvm-previa">1️⃣ Ver a prévia (não muda nada)</button>
                <?php if ( $d['pode_aplicar'] ) : ?>
                <label style="margin:0 12px"><input type="checkbox" id="cvm-backup"> Fiz o backup do site no ar hoje</label>
                <button type="button" class="button button-primary" id="cvm-aplicar" disabled>2️⃣ Aplicar a troca</button>
                <?php endif; ?>
            </p>
            <div id="cvm-progresso" style="display:none;font-size:14px;padding:12px 16px;background:#fff;border:1px solid #EADBC6;border-radius:8px"></div>

            <h2 style="margin-top:28px">📋 Relatório para o Claude</h2>
            <p>Se algo der errado ou para conferir, copie este texto e cole na conversa.</p>
            <textarea readonly rows="10" style="width:100%;font-family:monospace;font-size:12px" onclick="this.select()"><?php echo esc_textarea( $relatorio . "\nÚltimas ações:\n" . implode( "\n", (array) get_option( 'cvm_log', array() ) ) ); ?></textarea>
        </div>
        <?php
    }
}
