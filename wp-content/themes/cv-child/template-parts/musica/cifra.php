<?php
// cancao-verdadeira-child/template-parts/musica/cifra.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// "🎸 Cifra simples" (v15.23.0): só a sequência de acordes de cada parte,
// para quem toca violão. Lê o campo _cv_cifra do plugin, uma parte por linha
// ("Refrão: C G D"); a linha "Tom: G" vira o selo do tom no cabeçalho.
// Cada parte ganha um tom da paleta, do mais claro (em cima) ao mais escuro
// (embaixo): classes .cv-cifra-tom-0 a .cv-cifra-tom-5 em cv-ajustes.css.
// Chamado por template-parts/musica/letra.php com $args['cifra'].
// Sem cifra cadastrada, não mostra nada.

if ( ! defined( 'ABSPATH' ) ) { exit; }

$texto = isset( $args['cifra'] ) ? trim( (string) $args['cifra'] ) : '';
if ( '' === $texto ) { return; }

// Monta as partes: "Nome: acordes". Linha sem ":" vira uma parte sem nome.
$tom    = '';
$partes = array();
foreach ( preg_split( '/\r\n|\r|\n/', $texto ) as $linha ) {
    $linha = trim( $linha );
    if ( '' === $linha ) { continue; }
    $nome    = '';
    $acordes = $linha;
    if ( false !== strpos( $linha, ':' ) ) {
        list( $nome, $acordes ) = array_map( 'trim', explode( ':', $linha, 2 ) );
    }
    // Acordes separados por espaço, traço, vírgula ou barra vertical (G/B continua um acorde só)
    $lista = array_values( array_filter( preg_split( '/\s*[–—,|]\s*|\s+-\s+|\s+/u', $acordes ), 'strlen' ) );
    if ( 0 === strcasecmp( $nome, 'tom' ) ) {
        $tom = $lista ? $lista[0] : '';
        continue;
    }
    if ( $lista ) {
        $partes[] = array( 'nome' => $nome, 'acordes' => $lista );
    }
}
if ( ! $partes ) { return; }

// Espalha os 6 tons da paleta pelas partes: a primeira é sempre a mais clara
// e a última a mais escura, tenha a música 2 ou 8 partes.
$total = count( $partes );
?>
                        <!-- Cifra simples (v15.23.0) -->
                        <section class="cv-cifra" aria-label="Cifra simples">
                            <div class="cv-cifra-topo">
                                <h2>🎸 Cifra simples</h2>
                                <?php if ( '' !== $tom ) : ?>
                                <span class="cv-cifra-tom">Tom: <strong><?php echo esc_html( $tom ); ?></strong></span>
                                <?php endif; ?>
                            </div>
                            <p class="cv-cifra-dica">Só os acordes de cada parte, na ordem em que aparecem.</p>
                            <ol class="cv-cifra-partes">
                                <?php foreach ( $partes as $i => $parte ) :
                                    $nivel = ( $total > 1 ) ? (int) round( $i * 5 / ( $total - 1 ) ) : 0;
                                ?>
                                <li class="cv-cifra-parte cv-cifra-tom-<?php echo (int) $nivel; ?>">
                                    <?php if ( '' !== $parte['nome'] ) : ?>
                                    <span class="cv-cifra-nome"><?php echo esc_html( $parte['nome'] ); ?></span>
                                    <?php endif; ?>
                                    <span class="cv-cifra-acordes">
                                        <?php foreach ( $parte['acordes'] as $acorde ) : ?>
                                        <span class="cv-cifra-acorde"><?php echo esc_html( $acorde ); ?></span>
                                        <?php endforeach; ?>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                            </ol>
                        </section>
