<?php
/*
 * Template Name: Como assinar pelo gov.br
 */
// cancao-verdadeira-child/templates/page-ajuda-gov.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página "Como assinar pelo gov.br" (/assinar-gov-br/), criada em 26/09/2026
// (tema v15.34.0, item 4.7 da lista): passo a passo simples, em letras
// grandes, para o artista assinar o contrato de divulgação no assinador
// oficial (https://assinador.iti.br, grátis, conta gov.br prata ou ouro) e
// enviar o PDF assinado na Minha Área → 🎤 Enviar música. Fontes conferidas
// em 26/09: gov.br/pt-br/servicos/assinatura-eletronica. Links para cá: página
// Para artistas e o 1º passo do envio (plugin, CV_Envio_Area).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$assinador = 'https://assinador.iti.br';
$niveis    = 'https://www.gov.br/pt-br/servicos/assinatura-eletronica'; // página oficial conferida em 26/09 (explica os níveis)
$whats     = 'https://wa.me/' . preg_replace( '/\D/', '', apply_filters( 'cv_whatsapp_artistas', '5531983091360' ) ) . '?text=' . rawurlencode( 'Olá, Canção Verdadeira! Preciso de ajuda para assinar o contrato pelo gov.br.' );
$artistas  = class_exists( 'CV_Apoio' ) ? CV_Apoio::url_artistas() : home_url( '/artista/' );
$envio     = add_query_arg( 'aba', 'enviar', home_url( '/minha-area/' ) );

$passos = array(
    array( 'titulo' => 'Abra o assinador do gov.br', 'texto' => 'No celular ou no computador, entre no site oficial <strong>assinador.iti.br</strong>. É de graça.', 'botao' => array( $assinador, '🔗 Abrir o assinador' ) ),
    array( 'titulo' => 'Entre com a sua conta gov.br', 'texto' => 'Use o seu <strong>CPF</strong> e a <strong>senha do gov.br</strong> (a mesma do INSS, da Receita e da carteira digital).', 'botao' => null ),
    array( 'titulo' => 'Escolha o contrato', 'texto' => 'Toque em <strong>"Escolher arquivo"</strong> e selecione o contrato em PDF que você baixou da Canção Verdadeira.', 'botao' => null ),
    array( 'titulo' => 'Marque onde vai a assinatura', 'texto' => 'Toque no lugar do documento onde a assinatura deve aparecer (em geral, no fim, perto do seu nome). Depois toque em <strong>"Assinar digitalmente"</strong>.', 'botao' => null ),
    array( 'titulo' => 'Confirme com o código', 'texto' => 'Escolha <strong>"usar gov.br"</strong> e digite o <strong>código</strong> que chega no seu celular.', 'botao' => null ),
    array( 'titulo' => 'Baixe e envie para nós', 'texto' => 'Baixe o <strong>PDF assinado</strong> e envie na sua Minha Área, na aba <strong>"🎤 Enviar música"</strong>.', 'botao' => array( $envio, '🎤 Ir para o envio' ) ),
);

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => '✍️ Passo a passo',
            'titulo'    => 'Como assinar pelo',
            'destaque'  => 'gov.br',
            'subtitulo' => 'É de graça, pelo celular ou computador, e tem a mesma validade da assinatura no papel.',
        ) );
        ?>

        <section class="cv-section" aria-labelledby="cv-gov-antes">
            <div class="cv-gov-antes">
                <h2 id="cv-gov-antes">✅ Antes de começar, tenha à mão</h2>
                <ul>
                    <li>📱 <strong>O seu celular</strong>: o código de confirmação chega nele.</li>
                    <li>🔑 <strong>A sua conta gov.br nível prata ou ouro</strong> (a bronze não serve; veja abaixo como subir).</li>
                    <li>📄 <strong>O contrato em PDF</strong>, baixado da Canção Verdadeira depois de enviar a proposta na página <a href="<?php echo esc_url( $artistas ); ?>">Para artistas</a>.</li>
                </ul>
            </div>
        </section>

        <section class="cv-section" aria-labelledby="cv-gov-passos">
            <h2 id="cv-gov-passos" class="cv-section-title">✍️ Os <span>6 passos</span></h2>
            <ol class="cv-gov-passos">
                <?php foreach ( $passos as $i => $p ) : ?>
                <li>
                    <span class="cv-gov-num" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
                    <div>
                        <h3><?php echo esc_html( $p['titulo'] ); ?></h3>
                        <p><?php echo wp_kses( $p['texto'], array( 'strong' => array() ) ); ?></p>
                        <?php if ( $p['botao'] ) : ?>
                        <a class="cv-btn cv-btn-primary" href="<?php echo esc_url( $p['botao'][0] ); ?>"<?php echo 0 === strpos( $p['botao'][0], 'http' ) && false === strpos( $p['botao'][0], home_url() ) ? ' target="_blank" rel="noopener"' : ''; ?>><?php echo esc_html( $p['botao'][1] ); ?></a>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
        </section>

        <section class="cv-section" aria-labelledby="cv-gov-aviso">
            <div class="cv-gov-aviso">
                <h2 id="cv-gov-aviso">⚠️ Importante</h2>
                <p><strong>Não abra nem salve de novo o PDF depois de assinado.</strong> Qualquer mudança no arquivo faz a assinatura deixar de valer, e o site não aceita o envio. Se isso acontecer, é só assinar outra vez.</p>
            </div>
        </section>

        <section class="cv-section cv-artista-faq" aria-labelledby="cv-gov-duvidas">
            <h2 id="cv-gov-duvidas" class="cv-section-title">❓ Dúvidas <span>frequentes</span></h2>
            <details>
                <summary>Minha conta é bronze. Como passo para prata?</summary>
                <p>No <strong>aplicativo gov.br</strong> do celular, procure a opção de <strong>aumentar o nível da conta</strong> e faça o <strong>reconhecimento facial</strong>. Também dá para validar pelo site do seu banco, se ele for credenciado. <a href="<?php echo esc_url( $niveis ); ?>" target="_blank" rel="noopener">Veja a explicação oficial no site do gov.br</a>.</p>
            </details>
            <details>
                <summary>Não tenho conta gov.br. E agora?</summary>
                <p>Crie de graça no site ou no aplicativo gov.br, com o seu CPF. Depois suba o nível para prata, como explicado acima.</p>
            </details>
            <details>
                <summary>O código não chegou no celular.</summary>
                <p>Espere um minuto e peça o código de novo. Confira se o número do celular cadastrado no gov.br é o que está com você.</p>
            </details>
            <details>
                <summary>A assinatura tem validade de verdade?</summary>
                <p>Sim. A assinatura eletrônica do gov.br tem validade legal, regulamentada pelo Decreto nº 10.543/2020, e dispensa cartório.</p>
            </details>
        </section>

        <section class="cv-section" aria-labelledby="cv-gov-ajuda">
            <div class="cv-artista-whats">
                <div>
                    <h2 id="cv-gov-ajuda">💬 Ficou difícil? A gente ajuda</h2>
                    <p>Chame no WhatsApp dos artistas: <strong>(31) 98309-1360</strong>. Explicamos com calma, passo a passo.</p>
                </div>
                <a class="cv-btn cv-btn-whats cv-btn-lg" href="<?php echo esc_url( $whats ); ?>" target="_blank" rel="noopener">
                    <?php echo class_exists( 'CV_Icones' ) ? CV_Icones::svg( 'whatsapp', 22, '#FFFFFF' ) : ''; // phpcs:ignore ?> Chamar no WhatsApp
                </a>
            </div>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_footer(); ?>
