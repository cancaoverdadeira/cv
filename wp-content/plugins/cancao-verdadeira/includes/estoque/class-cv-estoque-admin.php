<?php
// cancao-verdadeira/includes/estoque/class-cv-estoque-admin.php
// Criado em: 25/09/2026 (plugin v2.47.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Tela "📦 Estoque e Pedidos" do painel (admin.php?page=cv-estoque), com 4 abas:
// Painel (indicadores e análises), Itens (incluir/editar/excluir), Estoque
// (entrada, saída, contagem e histórico) e Pedidos (confirmar, entregar,
// cancelar). O HTML de cada aba fica em includes/estoque/views/{aba}.php e o
// JavaScript em assets/js/admin-estoque.js. As regras ficam em CV_Estoque.
// Também responde às chamadas da aba "Meus Pedidos" da Minha Área.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Estoque_Admin {

    public static function abas() {
        return array(
            'painel'     => '📊 Painel',
            'itens'      => '🏷️ Itens',
            'movimentos' => '🔁 Estoque',
            'pedidos'    => '🛍️ Pedidos',
        );
    }

    public static function init() {
        // Painel (só administradores)
        foreach ( array( 'salvar_item', 'excluir_item', 'movimento', 'status_pedido', 'email' ) as $acao ) {
            add_action( 'wp_ajax_cv_est_' . $acao, array( __CLASS__, 'ajax_' . $acao ) );
        }
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
        // Minha Área (usuário logado)
        add_action( 'wp_ajax_cv_pedido_criar',    array( __CLASS__, 'ajax_pedido_criar' ) );
        add_action( 'wp_ajax_cv_pedido_cancelar', array( __CLASS__, 'ajax_pedido_cancelar' ) );
    }

    // CSS e JS só na tela do estoque
    public static function enqueue() {
        if ( 'cv-estoque' !== sanitize_key( $_GET['page'] ?? '' ) ) { return; }
        wp_enqueue_media();
        wp_enqueue_style( 'cv-admin-estoque', CV_PLUGIN_URL . 'assets/css/admin-estoque.css', array(), CV_VERSION );
        wp_enqueue_script( 'cv-admin-estoque', CV_PLUGIN_URL . 'assets/js/admin-estoque.js', array( 'jquery' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-admin-estoque', 'window.cvEst = ' . wp_json_encode( array(
            'nonce'   => wp_create_nonce( 'cv_admin_nonce' ),
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        ) ) . ';', 'before' );
    }

    // ════════════════════════════════════════════════════════════════
    // TELA
    // ════════════════════════════════════════════════════════════════

    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) { return; }
        $abas = self::abas();
        $aba  = sanitize_key( $_GET['aba'] ?? 'painel' );
        if ( ! isset( $abas[ $aba ] ) ) { $aba = 'painel'; }

        global $wpdb;
        $pendentes = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_pedidos WHERE status = 'pendente'" );
        $alertas   = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}cv_estoque_variacoes v
               JOIN {$wpdb->prefix}cv_estoque_itens i ON i.id = v.item_id
              WHERE i.ativo = 1 AND v.ativo = 1 AND i.controla_estoque = 1 AND v.estoque_atual <= i.estoque_minimo"
        );

        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>📦 Estoque e Pedidos</h1>
                <p class="cv-admin-subtitle">E-book, Caneca e Camiseta: estoque, pedidos da Minha Área e análises</p>
            </div>
            <div id="cv-est-msg" class="cv-action-message" style="display:none"></div>

            <nav class="cv-est-abas" aria-label="Abas do estoque">
                <?php foreach ( $abas as $id => $rotulo ) :
                    $badge = '';
                    if ( 'pedidos' === $id && $pendentes ) { $badge = '<span class="cv-est-badge">' . $pendentes . '</span>'; }
                    if ( 'painel' === $id && $alertas )    { $badge = '<span class="cv-est-badge cv-est-badge-alerta" title="Itens no estoque mínimo">' . $alertas . '</span>'; }
                ?>
                <a href="<?php echo esc_url( admin_url( 'admin.php?page=cv-estoque&aba=' . $id ) ); ?>"
                   class="cv-est-aba<?php echo $aba === $id ? ' is-ativa' : ''; ?>"><?php echo esc_html( $rotulo ); ?><?php echo $badge; ?></a>
                <?php endforeach; ?>
            </nav>

            <?php include CV_PLUGIN_DIR . 'includes/estoque/views/' . $aba . '.php'; ?>
        </div>
        <?php
    }

    // ════════════════════════════════════════════════════════════════
    // AJAX — PAINEL
    // ════════════════════════════════════════════════════════════════

    private static function so_admin() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Sem permissão.' ) );
        }
    }

    private static function responder( $r, $ok_msg ) {
        if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
        wp_send_json_success( array( 'message' => $ok_msg, 'resultado' => $r ) );
    }

    public static function ajax_salvar_item() {
        self::so_admin();
        $post     = wp_unslash( $_POST );
        $iniciais = array();
        foreach ( array_merge( array( '' ), CV_Estoque::TAMANHOS ) as $var ) {
            $campo = 'ini_' . ( '' === $var ? 'unico' : $var );
            if ( isset( $post[ $campo ] ) ) { $iniciais[ $var ] = absint( $post[ $campo ] ); }
        }
        $r = CV_Estoque::salvar_item( $post, $iniciais );
        self::responder( $r, 'Item salvo.' );
    }

    public static function ajax_excluir_item() {
        self::so_admin();
        self::responder( CV_Estoque::excluir_item( absint( $_POST['id'] ?? 0 ) ), 'Item excluído.' );
    }

    public static function ajax_movimento() {
        self::so_admin();
        $vid  = absint( $_POST['variacao_id'] ?? 0 );
        $op   = sanitize_key( $_POST['operacao'] ?? '' );
        $qtd  = absint( $_POST['quantidade'] ?? 0 );
        $obs  = sanitize_text_field( wp_unslash( $_POST['obs'] ?? '' ) );
        $custo = (float) str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['custo'] ?? '0' ) ) );

        switch ( $op ) {
            case 'entrada':
                if ( ! $qtd ) { wp_send_json_error( array( 'message' => 'Informe a quantidade comprada.' ) ); }
                $r = CV_Estoque::movimentar( $vid, $qtd, 'entrada', 'compra', $obs, $custo );
                break;
            case 'saida':
                if ( ! $qtd ) { wp_send_json_error( array( 'message' => 'Informe a quantidade que saiu.' ) ); }
                $motivos = CV_Estoque::motivos_saida();
                $motivo  = sanitize_key( $_POST['motivo'] ?? 'outro' );
                if ( ! isset( $motivos[ $motivo ] ) ) { $motivo = 'outro'; }
                $r = CV_Estoque::movimentar( $vid, -1 * $qtd, 'saida', $motivo, $obs );
                break;
            case 'ajuste':
                $r = CV_Estoque::ajustar( $vid, $qtd, $obs );
                break;
            default:
                $r = new WP_Error( 'op', 'Escolha: entrada, saída ou contagem.' );
        }
        self::responder( $r, 'Estoque atualizado.' );
    }

    public static function ajax_status_pedido() {
        self::so_admin();
        $status = sanitize_key( $_POST['status'] ?? '' );
        self::responder( CV_Estoque::mudar_status( absint( $_POST['id'] ?? 0 ), $status ), 'Pedido atualizado.' );
    }

    public static function ajax_email() {
        self::so_admin();
        $email = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
        if ( ! is_email( $email ) ) { wp_send_json_error( array( 'message' => 'E-mail inválido.' ) ); }
        update_option( 'cv_estoque_email', $email, false );
        wp_send_json_success( array( 'message' => 'E-mail de alerta salvo.' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // AJAX — MINHA ÁREA
    // ════════════════════════════════════════════════════════════════

    private static function so_logado() {
        check_ajax_referer( 'cv_pedido_nonce', 'nonce' );
        if ( ! is_user_logged_in() ) {
            wp_send_json_error( array( 'message' => 'Entre na sua conta para fazer pedidos.' ) );
        }
    }

    public static function ajax_pedido_criar() {
        self::so_logado();
        $r = CV_Estoque::criar_pedido(
            get_current_user_id(),
            absint( $_POST['variacao_id'] ?? 0 ),
            absint( $_POST['quantidade'] ?? 1 ),
            sanitize_text_field( wp_unslash( $_POST['obs'] ?? '' ) )
        );
        if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
        wp_send_json_success( array(
            'pedido_id' => $r,
            'message' => CV_Pix::ativo()
                ? 'Pedido #' . $r . ' recebido! Pague com o PIX abaixo e aguarde nossa confirmação por e-mail.'
                : 'Pedido #' . $r . ' recebido! Vamos entrar em contato para combinar o pagamento e a entrega.',
            'html'    => CV_Estoque_Area::lista_pedidos( get_current_user_id() ),
            'opcoes'  => CV_Estoque_Area::opcoes_select(),
        ) );
    }

    public static function ajax_pedido_cancelar() {
        self::so_logado();
        $r = CV_Estoque::cancelar_pelo_cliente( get_current_user_id(), absint( $_POST['pedido_id'] ?? 0 ) );
        if ( is_wp_error( $r ) ) { wp_send_json_error( array( 'message' => $r->get_error_message() ) ); }
        wp_send_json_success( array(
            'message' => 'Pedido cancelado.',
            'html'    => CV_Estoque_Area::lista_pedidos( get_current_user_id() ),
        ) );
    }
}

CV_Estoque_Admin::init();
