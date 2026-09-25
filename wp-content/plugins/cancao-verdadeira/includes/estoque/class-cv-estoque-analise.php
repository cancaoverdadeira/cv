<?php
// cancao-verdadeira/includes/estoque/class-cv-estoque-analise.php
// Criado em: 25/09/2026 (plugin v2.47.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Números do Painel da tela Estoque e Pedidos (só leitura, sem HTML):
// indicadores do topo, itens mais retirados, ranking dos itens (pedidos,
// unidades, faturamento, conversão, custo médio e margem), tamanhos mais
// pedidos, saídas por mês e motivo, e previsão de quantos dias o estoque dura.
// Período: últimos 30, 90 ou 365 dias, ou tudo (0).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Estoque_Analise {

    private static function desde( $dias ) {
        return $dias ? gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) - $dias * DAY_IN_SECONDS ) : '1970-01-01 00:00:00';
    }

    public static function indicadores( $dias ) {
        global $wpdb;
        $p = $wpdb->prefix;
        $estoque = $wpdb->get_row(
            "SELECT COALESCE(SUM(v.estoque_atual),0) pecas, COALESCE(SUM(v.estoque_atual * i.preco),0) valor,
                    SUM(v.estoque_atual <= i.estoque_minimo) alertas
               FROM {$p}cv_estoque_variacoes v JOIN {$p}cv_estoque_itens i ON i.id = v.item_id
              WHERE i.ativo = 1 AND v.ativo = 1 AND i.controla_estoque = 1"
        );
        $vendas = $wpdb->get_row( $wpdb->prepare(
            "SELECT COALESCE(SUM(quantidade * preco_unit),0) faturamento, COALESCE(SUM(quantidade),0) unidades, COUNT(*) pedidos
               FROM {$p}cv_pedidos WHERE status IN ('confirmado','entregue') AND criado_em >= %s",
            self::desde( $dias )
        ) );
        return array(
            'itens'       => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}cv_estoque_itens WHERE ativo = 1" ),
            'pecas'       => (int) $estoque->pecas,
            'valor'       => (float) $estoque->valor,
            'alertas'     => (int) $estoque->alertas,
            'pendentes'   => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$p}cv_pedidos WHERE status = 'pendente'" ),
            'faturamento' => (float) $vendas->faturamento,
            'unidades'    => (int) $vendas->unidades,
            'vendas'      => (int) $vendas->pedidos,
        );
    }

    /** Peças que saíram por item no período (pedidos + saídas manuais − estornos). */
    public static function mais_retirados( $dias ) {
        global $wpdb;
        $p = $wpdb->prefix;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT i.nome, -SUM(m.quantidade) retiradas
               FROM {$p}cv_estoque_mov m JOIN {$p}cv_estoque_itens i ON i.id = m.item_id
              WHERE m.tipo IN ('saida','pedido','estorno') AND m.criado_em >= %s
              GROUP BY m.item_id HAVING retiradas > 0
              ORDER BY retiradas DESC LIMIT 10",
            self::desde( $dias )
        ) );
    }

    /** Ranking dos itens: quem vende mais e dá mais retorno. */
    public static function ranking( $dias ) {
        global $wpdb;
        $p = $wpdb->prefix;
        $linhas = $wpdb->get_results( $wpdb->prepare(
            "SELECT i.id, i.nome, i.tipo, i.preco,
                    COUNT(pe.id) pedidos,
                    SUM(pe.status IN ('confirmado','entregue')) vendidos,
                    COALESCE(SUM(CASE WHEN pe.status IN ('confirmado','entregue') THEN pe.quantidade END),0) unidades,
                    COALESCE(SUM(CASE WHEN pe.status IN ('confirmado','entregue') THEN pe.quantidade * pe.preco_unit END),0) faturamento
               FROM {$p}cv_estoque_itens i
          LEFT JOIN {$p}cv_pedidos pe ON pe.item_id = i.id AND pe.criado_em >= %s
              GROUP BY i.id
              ORDER BY faturamento DESC, unidades DESC, pedidos DESC",
            self::desde( $dias )
        ) );
        // Custo médio das compras (entradas com custo informado), de todo o histórico
        $custos = array();
        foreach ( $wpdb->get_results(
            "SELECT item_id, SUM(quantidade * custo_unit) / SUM(quantidade) custo
               FROM {$p}cv_estoque_mov WHERE tipo = 'entrada' AND custo_unit > 0 GROUP BY item_id"
        ) as $c ) { $custos[ $c->item_id ] = (float) $c->custo; }

        foreach ( $linhas as $l ) {
            $l->conversao = $l->pedidos ? round( 100 * $l->vendidos / $l->pedidos ) : null;
            $l->custo     = isset( $custos[ $l->id ] ) ? $custos[ $l->id ] : null;
            $l->margem    = null !== $l->custo && $l->preco > 0 ? round( 100 * ( $l->preco - $l->custo ) / $l->preco ) : null;
        }
        return $linhas;
    }

    /** Tamanhos de camiseta mais pedidos (pedidos não cancelados). */
    public static function tamanhos( $dias ) {
        global $wpdb;
        $p = $wpdb->prefix;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT v.variacao tamanho, SUM(pe.quantidade) unidades
               FROM {$p}cv_pedidos pe JOIN {$p}cv_estoque_variacoes v ON v.id = pe.variacao_id
              WHERE v.variacao <> '' AND pe.status <> 'cancelado' AND pe.criado_em >= %s
              GROUP BY v.variacao
              ORDER BY FIELD(v.variacao, 'P', 'M', 'G', 'GG')",
            self::desde( $dias )
        ) );
    }

    /** Saídas dos últimos 6 meses, por motivo (para o gráfico empilhado). */
    public static function saidas_por_mes() {
        global $wpdb;
        $p = $wpdb->prefix;
        // Base no dia 1º do mês atual: "-5 months" a partir do dia 31 pularia meses.
        $base   = strtotime( gmdate( 'Y-m-01', current_time( 'timestamp' ) ) );
        $inicio = gmdate( 'Y-m-01 00:00:00', strtotime( '-5 months', $base ) );
        $linhas = $wpdb->get_results( $wpdb->prepare(
            "SELECT DATE_FORMAT(criado_em, '%%Y-%%m') mes, motivo, -SUM(quantidade) qtd
               FROM {$p}cv_estoque_mov
              WHERE tipo IN ('saida','pedido','estorno') AND criado_em >= %s
              GROUP BY mes, motivo",
            $inicio
        ) );
        $meses = array();
        for ( $i = 5; $i >= 0; $i-- ) {
            $meses[] = gmdate( 'Y-m', strtotime( "-{$i} months", $base ) );
        }
        $rotulos = array_merge( array( 'pedido' => 'Pedidos do site' ), CV_Estoque::motivos_saida() );
        $series  = array();
        foreach ( $rotulos as $motivo => $rotulo ) {
            $dados = array_fill_keys( $meses, 0 );
            foreach ( $linhas as $l ) {
                if ( $l->motivo === $motivo && isset( $dados[ $l->mes ] ) ) { $dados[ $l->mes ] += (int) $l->qtd; }
            }
            if ( array_sum( $dados ) > 0 ) { $series[] = array( 'rotulo' => $rotulo, 'dados' => array_values( $dados ) ); }
        }
        $nomes = array();
        foreach ( $meses as $m ) { $nomes[] = date_i18n( 'M/y', strtotime( $m . '-01' ) ); }
        return array( 'meses' => $nomes, 'series' => $series );
    }

    /** Saldo, média de saída por dia (30 dias) e previsão de dias até acabar. */
    public static function previsao() {
        global $wpdb;
        $p = $wpdb->prefix;
        $saidas = array();
        foreach ( $wpdb->get_results( $wpdb->prepare(
            "SELECT variacao_id, -SUM(quantidade) qtd FROM {$p}cv_estoque_mov
              WHERE tipo IN ('saida','pedido','estorno') AND criado_em >= %s GROUP BY variacao_id",
            self::desde( 30 )
        ) ) as $s ) { $saidas[ $s->variacao_id ] = max( 0, (int) $s->qtd ); }

        $lista = array();
        foreach ( CV_Estoque::variacoes( true ) as $v ) {
            if ( ! $v->controla_estoque ) { continue; }
            $por_dia = isset( $saidas[ $v->id ] ) ? $saidas[ $v->id ] / 30 : 0;
            $lista[] = (object) array(
                'rotulo'  => CV_Estoque::rotulo( $v ),
                'saldo'   => (int) $v->estoque_atual,
                'minimo'  => (int) $v->estoque_minimo,
                'por_dia' => $por_dia,
                'dias'    => $por_dia > 0 ? (int) floor( $v->estoque_atual / $por_dia ) : null,
            );
        }
        return $lista;
    }
}
