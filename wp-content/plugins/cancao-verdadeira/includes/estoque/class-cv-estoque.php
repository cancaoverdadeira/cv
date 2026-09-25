<?php
// cancao-verdadeira/includes/estoque/class-cv-estoque.php
// Criado em: 25/09/2026 (plugin v2.47.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Núcleo do módulo "Estoque e Pedidos": regras de negócio, sem HTML.
// Itens: E-book (sem controle de estoque), Caneca (estoque único) e Camiseta
// (estoque por tamanho P, M, G, GG). Cada tamanho é uma "variação".
// Movimentos: entrada (compra), saída (brinde, sorteio, perda, venda direta),
// ajuste (contagem) e pedido/estorno. Todo movimento fica registrado.
// Pedidos (Minha Área): entram "pendente" e só baixam o estoque quando o admin
// CONFIRMA. Pagamento só por PIX (CV_Pix, v2.48.0); a entrega é combinada.
// Alerta: quando uma variação chega ao mínimo (padrão 10 peças), um e-mail
// vai para cv_estoque_email (padrão cancaoverdadeira@gmail.com), uma vez só;
// o aviso "rearma" quando o estoque volta a ficar acima do mínimo.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Estoque {

    const TAMANHOS          = array( 'P', 'M', 'G', 'GG' );
    const MAX_QTD_PEDIDO    = 10; // por pedido
    const MAX_PENDENTES     = 10; // pedidos pendentes por pessoa (evita abuso)
    const EMAIL_PADRAO      = 'cancaoverdadeira@gmail.com';

    public static function tipos() {
        return array(
            'ebook'    => '📖 E-book',
            'caneca'   => '☕ Caneca',
            'camiseta' => '👕 Camiseta',
        );
    }

    public static function status_pedido() {
        return array(
            'pendente'   => '⏳ Pendente',
            'confirmado' => '✅ Confirmado',
            'entregue'   => '📦 Entregue',
            'cancelado'  => '✖ Cancelado',
        );
    }

    public static function motivos_saida() {
        return array(
            'venda_direta' => 'Venda direta (fora do site)',
            'brinde'       => 'Brinde',
            'sorteio'      => 'Sorteio',
            'perda'        => 'Perda / defeito',
            'outro'        => 'Outro',
        );
    }

    public static function email_alerta() {
        $email = get_option( 'cv_estoque_email', self::EMAIL_PADRAO );
        return is_email( $email ) ? $email : self::EMAIL_PADRAO;
    }

    private static function t( $nome ) {
        global $wpdb;
        return $wpdb->prefix . 'cv_' . $nome;
    }

    // ════════════════════════════════════════════════════════════════
    // LEITURA
    // ════════════════════════════════════════════════════════════════

    public static function item( $item_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'estoque_itens' ) . ' WHERE id = %d', $item_id ) );
    }

    /** Variação com os dados do item (nome, tipo, preço, controle, mínimo). */
    public static function variacao( $variacao_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            'SELECT v.*, i.nome, i.tipo, i.preco, i.controla_estoque, i.estoque_minimo, i.ativo AS item_ativo
               FROM ' . self::t( 'estoque_variacoes' ) . ' v
               JOIN ' . self::t( 'estoque_itens' ) . ' i ON i.id = v.item_id
              WHERE v.id = %d',
            $variacao_id
        ) );
    }

    /** Todas as variações (com dados do item), na ordem da tela. */
    public static function variacoes( $so_ativas = false ) {
        global $wpdb;
        $where = $so_ativas ? 'WHERE i.ativo = 1 AND v.ativo = 1' : '';
        return $wpdb->get_results(
            'SELECT v.*, i.nome, i.tipo, i.preco, i.controla_estoque, i.estoque_minimo, i.ativo AS item_ativo, i.imagem_url, i.descricao
               FROM ' . self::t( 'estoque_variacoes' ) . ' v
               JOIN ' . self::t( 'estoque_itens' ) . " i ON i.id = v.item_id
              {$where}
              ORDER BY i.ordem ASC, i.id ASC, FIELD(v.variacao, '', 'P', 'M', 'G', 'GG')"
        );
    }

    public static function rotulo( $v ) {
        return $v->nome . ( '' !== $v->variacao ? ' — ' . $v->variacao : '' );
    }

    // ════════════════════════════════════════════════════════════════
    // ITENS
    // ════════════════════════════════════════════════════════════════

    /**
     * Cria ou atualiza um item. Camiseta ganha os 4 tamanhos; os outros, uma
     * variação única (''). $iniciais = array( variacao => quantidade ) só na criação.
     * Devolve o ID do item ou WP_Error.
     */
    public static function salvar_item( $dados, $iniciais = array() ) {
        global $wpdb;
        $tipos = self::tipos();
        $tipo  = isset( $dados['tipo'] ) && isset( $tipos[ $dados['tipo'] ] ) ? $dados['tipo'] : '';
        $nome  = isset( $dados['nome'] ) ? trim( sanitize_text_field( $dados['nome'] ) ) : '';
        if ( '' === $nome ) { return new WP_Error( 'nome', 'Informe o nome do item.' ); }
        if ( '' === $tipo ) { return new WP_Error( 'tipo', 'Escolha o tipo: E-book, Caneca ou Camiseta.' ); }

        $linha = array(
            'nome'             => $nome,
            'tipo'             => $tipo,
            'descricao'        => sanitize_textarea_field( isset( $dados['descricao'] ) ? $dados['descricao'] : '' ),
            'preco'            => max( 0, (float) str_replace( ',', '.', isset( $dados['preco'] ) ? $dados['preco'] : '0' ) ),
            'imagem_url'       => esc_url_raw( isset( $dados['imagem_url'] ) ? $dados['imagem_url'] : '' ),
            'controla_estoque' => 'ebook' === $tipo ? 0 : 1,
            'estoque_minimo'   => isset( $dados['estoque_minimo'] ) && '' !== $dados['estoque_minimo'] ? absint( $dados['estoque_minimo'] ) : 10,
            'ativo'            => empty( $dados['ativo'] ) ? 0 : 1,
            'ordem'            => absint( isset( $dados['ordem'] ) ? $dados['ordem'] : 0 ),
        );
        $fmt = array( '%s', '%s', '%s', '%f', '%s', '%d', '%d', '%d', '%d' );

        $id = absint( isset( $dados['id'] ) ? $dados['id'] : 0 );
        if ( $id ) {
            $atual = self::item( $id );
            if ( ! $atual ) { return new WP_Error( 'item', 'Item não encontrado.' ); }
            if ( $atual->tipo !== $tipo ) { return new WP_Error( 'tipo', 'O tipo não pode mudar depois de criado. Crie um item novo.' ); }
            $wpdb->update( self::t( 'estoque_itens' ), $linha, array( 'id' => $id ), $fmt, array( '%d' ) );
            // O mínimo pode ter mudado: reavalia o alerta de cada tamanho.
            foreach ( self::ids_variacoes( $id ) as $vid ) { self::verificar_alerta( $vid ); }
            return $id;
        }

        $wpdb->insert( self::t( 'estoque_itens' ), $linha, $fmt );
        $id = (int) $wpdb->insert_id;
        if ( ! $id ) { return new WP_Error( 'banco', 'Não foi possível gravar o item.' ); }

        $vars = 'camiseta' === $tipo ? self::TAMANHOS : array( '' );
        foreach ( $vars as $var ) {
            $wpdb->insert( self::t( 'estoque_variacoes' ), array( 'item_id' => $id, 'variacao' => $var ), array( '%d', '%s' ) );
            $vid = (int) $wpdb->insert_id;
            $qtd = isset( $iniciais[ $var ] ) ? absint( $iniciais[ $var ] ) : 0;
            if ( $vid && $qtd && $linha['controla_estoque'] ) {
                self::movimentar( $vid, $qtd, 'entrada', 'compra', 'Estoque inicial' );
            }
        }
        return $id;
    }

    private static function ids_variacoes( $item_id ) {
        global $wpdb;
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            'SELECT id FROM ' . self::t( 'estoque_variacoes' ) . ' WHERE item_id = %d', $item_id
        ) ) );
    }

    /** Só exclui item sem pedidos (os pedidos são histórico de clientes). */
    public static function excluir_item( $item_id ) {
        global $wpdb;
        $item_id = absint( $item_id );
        $pedidos = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::t( 'pedidos' ) . ' WHERE item_id = %d', $item_id ) );
        if ( $pedidos ) {
            return new WP_Error( 'pedidos', 'Este item tem ' . $pedidos . ' pedido(s) e não pode ser excluído. Use "Ativo: Não" para tirá-lo da Minha Área.' );
        }
        $wpdb->delete( self::t( 'estoque_mov' ), array( 'item_id' => $item_id ), array( '%d' ) );
        $wpdb->delete( self::t( 'estoque_variacoes' ), array( 'item_id' => $item_id ), array( '%d' ) );
        $wpdb->delete( self::t( 'estoque_itens' ), array( 'id' => $item_id ), array( '%d' ) );
        return true;
    }

    // ════════════════════════════════════════════════════════════════
    // MOVIMENTOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Soma $delta (positivo entra, negativo sai) no estoque da variação, de
     * forma atômica: nunca deixa o saldo negativo. Registra o movimento e
     * confere o alerta. Devolve o novo saldo ou WP_Error.
     */
    public static function movimentar( $variacao_id, $delta, $tipo, $motivo = '', $obs = '', $custo = 0, $pedido_id = 0 ) {
        global $wpdb;
        $v = self::variacao( $variacao_id );
        if ( ! $v ) { return new WP_Error( 'variacao', 'Item não encontrado.' ); }
        $delta = (int) $delta;
        if ( 0 === $delta ) { return new WP_Error( 'qtd', 'Informe uma quantidade maior que zero.' ); }

        if ( $v->controla_estoque ) {
            $ok = $wpdb->query( $wpdb->prepare(
                'UPDATE ' . self::t( 'estoque_variacoes' ) . ' SET estoque_atual = estoque_atual + %d WHERE id = %d AND estoque_atual + %d >= 0',
                $delta, $variacao_id, $delta
            ) );
            if ( ! $ok ) {
                return new WP_Error( 'saldo', sprintf( 'Estoque insuficiente de "%s": há %d peça(s).', self::rotulo( $v ), (int) $v->estoque_atual ) );
            }
            $saldo = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT estoque_atual FROM ' . self::t( 'estoque_variacoes' ) . ' WHERE id = %d', $variacao_id ) );
        } else {
            $saldo = 0; // E-book: registra o movimento só para as análises
        }

        $wpdb->insert( self::t( 'estoque_mov' ), array(
            'item_id'     => (int) $v->item_id,
            'variacao_id' => (int) $variacao_id,
            'tipo'        => sanitize_key( $tipo ),
            'quantidade'  => $delta,
            'saldo_apos'  => $saldo,
            'custo_unit'  => max( 0, (float) $custo ),
            'motivo'      => sanitize_key( $motivo ),
            'pedido_id'   => absint( $pedido_id ),
            'obs'         => mb_substr( sanitize_text_field( $obs ), 0, 255 ),
            'user_id'     => get_current_user_id(),
            'criado_em'   => current_time( 'mysql' ),
        ), array( '%d', '%d', '%s', '%d', '%d', '%f', '%s', '%d', '%s', '%d', '%s' ) );

        if ( $v->controla_estoque ) { self::verificar_alerta( $variacao_id ); }
        return $saldo;
    }

    /** Contagem física: define o saldo exato (registra a diferença como "ajuste"). */
    public static function ajustar( $variacao_id, $novo_saldo, $obs = '' ) {
        $v = self::variacao( $variacao_id );
        if ( ! $v ) { return new WP_Error( 'variacao', 'Item não encontrado.' ); }
        if ( ! $v->controla_estoque ) { return new WP_Error( 'ebook', 'E-book não tem estoque para ajustar.' ); }
        $delta = absint( $novo_saldo ) - (int) $v->estoque_atual;
        if ( 0 === $delta ) { return (int) $v->estoque_atual; }
        return self::movimentar( $variacao_id, $delta, 'ajuste', 'contagem', $obs ? $obs : 'Contagem de estoque' );
    }

    // ════════════════════════════════════════════════════════════════
    // ALERTA DE ESTOQUE BAIXO
    // ════════════════════════════════════════════════════════════════

    public static function verificar_alerta( $variacao_id ) {
        global $wpdb;
        $v = self::variacao( $variacao_id );
        if ( ! $v || ! $v->controla_estoque ) { return false; }
        $baixo = (int) $v->estoque_atual <= (int) $v->estoque_minimo;

        if ( $baixo && ! $v->alerta_enviado && $v->item_ativo && $v->ativo ) {
            $assunto = sprintf( '⚠️ Estoque baixo: %s (%d restante%s)', self::rotulo( $v ), (int) $v->estoque_atual, 1 === (int) $v->estoque_atual ? '' : 's' );
            $corpo   = sprintf(
                "Olá!\n\nO item \"%s\" chegou a %d peça(s) no estoque (o mínimo definido é %d).\n\nHora de repor.\n\nVer o estoque: %s\n\n— Canção Verdadeira (aviso automático)",
                self::rotulo( $v ),
                (int) $v->estoque_atual,
                (int) $v->estoque_minimo,
                admin_url( 'admin.php?page=cv-estoque' )
            );
            wp_mail( self::email_alerta(), $assunto, $corpo );
            $wpdb->update( self::t( 'estoque_variacoes' ), array( 'alerta_enviado' => 1 ), array( 'id' => $variacao_id ), array( '%d' ), array( '%d' ) );
            return true;
        }
        if ( ! $baixo && $v->alerta_enviado ) {
            // Voltou a ficar acima do mínimo: o próximo aviso pode sair de novo.
            $wpdb->update( self::t( 'estoque_variacoes' ), array( 'alerta_enviado' => 0 ), array( 'id' => $variacao_id ), array( '%d' ), array( '%d' ) );
        }
        return false;
    }

    // ════════════════════════════════════════════════════════════════
    // PEDIDOS
    // ════════════════════════════════════════════════════════════════

    public static function criar_pedido( $user_id, $variacao_id, $qtd, $obs = '' ) {
        global $wpdb;
        $user_id = absint( $user_id );
        $qtd     = absint( $qtd );
        $v       = self::variacao( $variacao_id );
        if ( ! $user_id ) { return new WP_Error( 'login', 'Entre na sua conta para fazer o pedido.' ); }
        if ( ! $v || ! $v->item_ativo || ! $v->ativo ) { return new WP_Error( 'item', 'Este item não está disponível.' ); }
        if ( $qtd < 1 || $qtd > self::MAX_QTD_PEDIDO ) {
            return new WP_Error( 'qtd', 'Escolha de 1 a ' . self::MAX_QTD_PEDIDO . ' unidades.' );
        }
        if ( $v->controla_estoque && (int) $v->estoque_atual < $qtd ) {
            return new WP_Error( 'saldo', (int) $v->estoque_atual
                ? sprintf( 'Temos só %d unidade(s) de "%s" no momento.', (int) $v->estoque_atual, self::rotulo( $v ) )
                : sprintf( '"%s" está esgotado no momento.', self::rotulo( $v ) ) );
        }
        $pendentes = (int) $wpdb->get_var( $wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::t( 'pedidos' ) . " WHERE user_id = %d AND status = 'pendente'", $user_id
        ) );
        if ( $pendentes >= self::MAX_PENDENTES ) {
            return new WP_Error( 'limite', 'Você já tem ' . $pendentes . ' pedidos aguardando. Aguarde nosso contato para fazer novos pedidos.' );
        }

        $wpdb->insert( self::t( 'pedidos' ), array(
            'user_id'     => $user_id,
            'item_id'     => (int) $v->item_id,
            'variacao_id' => (int) $variacao_id,
            'quantidade'  => $qtd,
            'preco_unit'  => (float) $v->preco,
            'status'      => 'pendente',
            'obs'         => mb_substr( sanitize_text_field( $obs ), 0, 255 ),
            'criado_em'   => current_time( 'mysql' ),
        ), array( '%d', '%d', '%d', '%d', '%f', '%s', '%s', '%s' ) );
        $pedido_id = (int) $wpdb->insert_id;
        if ( ! $pedido_id ) { return new WP_Error( 'banco', 'Não foi possível gravar o pedido. Tente de novo.' ); }

        $u = get_userdata( $user_id );
        wp_mail(
            self::email_alerta(),
            sprintf( '🛍️ Novo pedido #%d: %s × %d', $pedido_id, self::rotulo( $v ), $qtd ),
            sprintf(
                "Novo pedido pela Minha Área.\n\nPedido: #%d\nItem: %s\nQuantidade: %d\nValor: R$ %s\nCliente: %s <%s>\nObservação: %s\n\nCombine o pagamento e a entrega e depois confirme o pedido:\n%s",
                $pedido_id,
                self::rotulo( $v ),
                $qtd,
                number_format( $qtd * (float) $v->preco, 2, ',', '.' ),
                $u ? $u->display_name : '#' . $user_id,
                $u ? $u->user_email : '',
                $obs ? $obs : '—',
                admin_url( 'admin.php?page=cv-estoque&aba=pedidos' )
            )
        );
        // v2.48.0: o cliente recebe os dados do PIX do pedido (só PIX por enquanto)
        if ( $u && class_exists( 'CV_Pix' ) && CV_Pix::ativo() ) {
            $total = $qtd * (float) $v->preco;
            wp_mail(
                $u->user_email,
                sprintf( 'Pedido #%d recebido — pague com PIX', $pedido_id ),
                sprintf(
                    "Olá, %s!\n\nRecebemos seu pedido #%d: %s × %d.\nValor: R$ %s\n\nPague com PIX Copia e Cola (copie o código abaixo e cole no app do seu banco, na opção PIX Copia e Cola):\n\n%s\n\nOu use a chave PIX: %s\n\nO QR Code também está na Minha Área: %s\n\nAssim que o pagamento cair, confirmamos o pedido por e-mail.\n\n— Canção Verdadeira",
                    $u->display_name, $pedido_id, self::rotulo( $v ), $qtd,
                    number_format( $total, 2, ',', '.' ),
                    CV_Pix::payload( $total, CV_Estoque_Area::txid( $pedido_id ), 'Pedido ' . $pedido_id ),
                    CV_Pix::chave_para_exibir(),
                    add_query_arg( 'aba', 'pedidos', home_url( '/minha-area/' ) )
                )
            );
        }
        return $pedido_id;
    }

    public static function pedido( $pedido_id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::t( 'pedidos' ) . ' WHERE id = %d', $pedido_id ) );
    }

    public static function pedidos_do_usuario( $user_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            'SELECT p.*, i.nome, i.tipo, v.variacao
               FROM ' . self::t( 'pedidos' ) . ' p
               JOIN ' . self::t( 'estoque_itens' ) . ' i ON i.id = p.item_id
               JOIN ' . self::t( 'estoque_variacoes' ) . ' v ON v.id = p.variacao_id
              WHERE p.user_id = %d
              ORDER BY p.id DESC
              LIMIT 50',
            $user_id
        ) );
    }

    /** O cliente só pode desistir de pedido ainda pendente. */
    public static function cancelar_pelo_cliente( $user_id, $pedido_id ) {
        $p = self::pedido( $pedido_id );
        if ( ! $p || (int) $p->user_id !== (int) $user_id ) { return new WP_Error( 'pedido', 'Pedido não encontrado.' ); }
        if ( 'pendente' !== $p->status ) { return new WP_Error( 'status', 'Este pedido já foi confirmado. Fale com a gente para cancelar.' ); }
        return self::gravar_status( $p, 'cancelado', 'Cancelado pelo cliente' );
    }

    /**
     * Mudança de status feita pelo admin. Regras de estoque:
     * pendente → confirmado: baixa o estoque (falha se não houver peças);
     * confirmado/entregue → cancelado: devolve as peças (estorno).
     */
    public static function mudar_status( $pedido_id, $novo ) {
        $p = self::pedido( $pedido_id );
        if ( ! $p ) { return new WP_Error( 'pedido', 'Pedido não encontrado.' ); }
        $validos = array(
            'pendente'   => array( 'confirmado', 'cancelado' ),
            'confirmado' => array( 'entregue', 'cancelado' ),
            'entregue'   => array( 'cancelado' ),
            'cancelado'  => array(),
        );
        if ( ! isset( $validos[ $p->status ] ) || ! in_array( $novo, $validos[ $p->status ], true ) ) {
            return new WP_Error( 'status', 'Mudança de situação não permitida.' );
        }

        if ( 'confirmado' === $novo ) {
            $r = self::movimentar( $p->variacao_id, -1 * (int) $p->quantidade, 'pedido', 'pedido', 'Pedido #' . $p->id, 0, $p->id );
            if ( is_wp_error( $r ) ) { return $r; }
        }
        if ( 'cancelado' === $novo && in_array( $p->status, array( 'confirmado', 'entregue' ), true ) ) {
            $r = self::movimentar( $p->variacao_id, (int) $p->quantidade, 'estorno', 'pedido', 'Cancelamento do pedido #' . $p->id, 0, $p->id );
            if ( is_wp_error( $r ) ) { return $r; }
        }
        return self::gravar_status( $p, $novo );
    }

    private static function gravar_status( $p, $novo, $nota = '' ) {
        global $wpdb;
        $dados = array( 'status' => $novo, 'atualizado_em' => current_time( 'mysql' ) );
        $fmt   = array( '%s', '%s' );
        if ( $nota ) {
            $dados['obs'] = mb_substr( trim( $p->obs . ' | ' . $nota, ' |' ), 0, 255 );
            $fmt[]        = '%s';
        }
        $wpdb->update( self::t( 'pedidos' ), $dados, array( 'id' => $p->id ), $fmt, array( '%d' ) );

        // Aviso ao cliente quando o admin muda a situação
        if ( ! $nota ) {
            $u = get_userdata( $p->user_id );
            $v = self::variacao( $p->variacao_id );
            $textos = array(
                'confirmado' => 'foi CONFIRMADO. Em breve combinamos a entrega com você.',
                'entregue'   => 'foi marcado como ENTREGUE. Obrigado pelo carinho com a Canção Verdadeira!',
                'cancelado'  => 'foi CANCELADO. Se tiver dúvidas, é só responder este e-mail.',
            );
            if ( $u && $v && isset( $textos[ $novo ] ) ) {
                wp_mail(
                    $u->user_email,
                    sprintf( 'Seu pedido #%d — Canção Verdadeira', $p->id ),
                    sprintf( "Olá, %s!\n\nSeu pedido #%d (%s × %d) %s\n\nAcompanhe em: %s\n\n— Canção Verdadeira",
                        $u->display_name, $p->id, self::rotulo( $v ), (int) $p->quantidade, $textos[ $novo ], add_query_arg( 'aba', 'pedidos', home_url( '/minha-area/' ) ) )
                );
            }
        }
        return true;
    }
}
