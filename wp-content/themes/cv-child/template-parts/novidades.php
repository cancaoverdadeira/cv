<?php
// cancao-verdadeira-child/template-parts/novidades.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// "✨ Novidades para você" (v15.30.0, 26/09/2026), logo abaixo do banner da
// página inicial: atalhos grandes para as funções pensadas para o público
// 55+, que ficam na página de cada música (Letra maior, Cantar junto, Cifra,
// Por trás da canção, Ofereça esta música e Depoimentos). Cada atalho leva a
// uma música de exemplo, já na parte certa (#letra, #cantar-junto, #cifra,
// #historia, #oferecer, #depoimentos). Cifra e história só entram se alguma
// música publicada tiver esses campos. "✕ Fechar" esconde por 30 dias
// (assets/js/cv-novidades.js). Sem nenhuma música publicada, não aparece.

if ( ! defined( 'ABSPATH' ) ) { exit; }

/** Música publicada mais recente que tenha o campo preenchido (ou só a mais recente). */
$cv_exemplo = function ( $meta = '' ) {
    $q = array(
        'post_type'      => 'musica',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    );
    if ( $meta ) {
        $q['meta_query'] = array( array( 'key' => $meta, 'value' => '', 'compare' => '!=' ) );
    }
    $ids = get_posts( $q );
    return $ids ? (int) $ids[0] : 0;
};

$base = $cv_exemplo();
if ( ! $base ) { return; }
$com_cifra    = $cv_exemplo( '_cv_cifra' );
$com_historia = $cv_exemplo( '_cv_historia' );

$itens = array(
    array( 'icone' => '🔠', 'titulo' => 'Letra maior',          'texto' => 'Toque em A+ e a letra cresce. O site lembra o tamanho que você escolheu.', 'id' => $base, 'ancora' => 'letra' ),
    array( 'icone' => '🎤', 'titulo' => 'Cantar junto',         'texto' => 'A letra na tela inteira, estrofe por estrofe, para cantar acompanhando.', 'id' => $base, 'ancora' => 'cantar-junto' ),
    array( 'icone' => '🎸', 'titulo' => 'Cifra simples',        'texto' => 'Os acordes de cada parte, para quem toca violão.', 'id' => $com_cifra, 'ancora' => 'cifra' ),
    array( 'icone' => '📖', 'titulo' => 'Por trás da canção',   'texto' => 'A história de como cada música nasceu, contada pelo compositor.', 'id' => $com_historia, 'ancora' => 'historia' ),
    array( 'icone' => '💌', 'titulo' => 'Ofereça esta música',  'texto' => 'Mande para alguém especial, com um cartão bonito pelo WhatsApp.', 'id' => $base, 'ancora' => 'oferecer' ),
    array( 'icone' => '💬', 'titulo' => 'Conte o que sentiu',   'texto' => 'Deixe seu depoimento: essa música lembrou alguém?', 'id' => $base, 'ancora' => 'depoimentos' ),
);
?>
        <!-- ══════════════════════════════════════════════════════
             ✨ NOVIDADES (v15.30.0)
        ══════════════════════════════════════════════════════ -->
        <section class="cv-section cv-novidades" id="cv-novidades" aria-labelledby="cv-novidades-titulo">
            <div class="cv-novidades-caixa">
                <div class="cv-novidades-topo">
                    <h2 id="cv-novidades-titulo" class="cv-section-title">✨ Novidades <span>para você</span></h2>
                    <button type="button" class="cv-novidades-fechar" aria-label="Fechar as novidades">✕ Fechar</button>
                </div>
                <p class="cv-novidades-intro">Preparamos estas funções para deixar a música ainda mais perto de você. Toque em uma para ver como funciona:</p>
                <ul class="cv-novidades-lista">
                    <?php foreach ( $itens as $it ) : if ( ! $it['id'] ) { continue; } ?>
                    <li>
                        <a class="cv-novidade" href="<?php echo esc_url( get_permalink( $it['id'] ) . '#' . $it['ancora'] ); ?>">
                            <span class="cv-novidade-icone" aria-hidden="true"><?php echo esc_html( $it['icone'] ); ?></span>
                            <span class="cv-novidade-titulo"><?php echo esc_html( $it['titulo'] ); ?></span>
                            <span class="cv-novidade-texto"><?php echo esc_html( $it['texto'] ); ?></span>
                            <span class="cv-novidade-ver">Ver na música →</span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </section>
