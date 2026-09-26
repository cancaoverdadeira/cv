<?php
// cancao-verdadeira/includes/estoque/class-cv-estoque-ligacao.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : ligação de BRINDES e SORTEIOS com o Estoque (v2.60.0, item 4.3)
// Como    : cada brinde/sorteio pode apontar para uma peça do estoque
//           (variação: Caneca, Camiseta M...). A ligação fica nas opções
//           cv_brinde_estoque e cv_sorteio_estoque ({id => variacao_id}),
//           sem mudar a estrutura do banco. Ao entregar o brinde ou realizar
//           o sorteio, baixar() tira 1 peça com CV_Estoque::movimentar(),
//           motivo "brinde"/"sorteio", e o movimento aparece no histórico.
// Uso     : campo( 'brinde', $id ) no formulário; ligar() ao salvar;
//           baixar() na entrega/sorteio; rotulo() na lista.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Estoque_Ligacao {

    private static function opcao( $tipo ) {
        return 'sorteio' === $tipo ? 'cv_sorteio_estoque' : 'cv_brinde_estoque';
    }

    private static function mapa( $tipo ) {
        $m = get_option( self::opcao( $tipo ), array() );
        return is_array( $m ) ? $m : array();
    }

    /** variacao_id ligada ao brinde/sorteio (0 = nenhuma). */
    public static function variacao_de( $tipo, $id ) {
        $m = self::mapa( $tipo );
        return isset( $m[ (int) $id ] ) ? (int) $m[ (int) $id ] : 0;
    }

    /** Grava (ou desfaz, com 0) a ligação. Só aceita peças que existem. */
    public static function ligar( $tipo, $id, $variacao_id ) {
        $id = (int) $id; $variacao_id = (int) $variacao_id;
        if ( ! $id ) { return; }
        $m = self::mapa( $tipo );
        if ( $variacao_id && class_exists( 'CV_Estoque' ) && CV_Estoque::variacao( $variacao_id ) ) {
            $m[ $id ] = $variacao_id;
        } else {
            unset( $m[ $id ] );
        }
        update_option( self::opcao( $tipo ), $m, false );
    }

    /**
     * Tira 1 peça do estoque. Devolve null se não houver ligação, o novo
     * saldo se deu certo, ou WP_Error (ex.: sem peça no estoque).
     */
    public static function baixar( $tipo, $id, $obs ) {
        $v = self::variacao_de( $tipo, $id );
        if ( ! $v || ! class_exists( 'CV_Estoque' ) ) { return null; }
        return CV_Estoque::movimentar( $v, -1, 'saida', 'sorteio' === $tipo ? 'sorteio' : 'brinde', $obs );
    }

    /** Nome da peça ligada (para a lista do painel) ou ''. */
    public static function rotulo( $tipo, $id ) {
        $v = self::variacao_de( $tipo, $id );
        if ( ! $v || ! class_exists( 'CV_Estoque' ) ) { return ''; }
        $obj = CV_Estoque::variacao( $v );
        return $obj ? CV_Estoque::rotulo( $obj ) : '';
    }

    /** Campo "📦 Tirar do estoque" dos formulários de brinde e sorteio. */
    public static function campo( $html_id ) {
        if ( ! class_exists( 'CV_Estoque' ) ) { return ''; }
        $vars = CV_Estoque::variacoes( true );
        ob_start();
        ?>
        <div class="cv-form-group" style="grid-column:1/-1">
            <label class="cv-form-label" for="<?php echo esc_attr( $html_id ); ?>">📦 Tirar do estoque <span style="font-weight:400">(opcional)</span></label>
            <select id="<?php echo esc_attr( $html_id ); ?>" class="cv-input" style="max-width:420px">
                <option value="0">Não tirar do estoque</option>
                <?php foreach ( $vars as $v ) : ?>
                <option value="<?php echo (int) $v->id; ?>"><?php echo esc_html( CV_Estoque::rotulo( $v ) ); ?><?php echo ! empty( $v->controla_estoque ) ? ' — ' . (int) $v->estoque_atual . ' no estoque' : ''; ?></option>
                <?php endforeach; ?>
            </select>
            <div style="font-size:12px;color:#8A6A55;margin-top:4px">
                <?php echo $vars ? 'Ao entregar (ou sortear), sai 1 peça do estoque sozinha, com o motivo registrado no histórico.' : 'Cadastre as peças em Estoque e Pedidos → Itens para poder escolher aqui.'; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}
