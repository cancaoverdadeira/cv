<?php
/*
 * Template Name: Para artistas
 */
// cancao-verdadeira-child/templates/page-artist.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página "Para artistas" (/artista/, página ID 13), criada em 26/09/2026
// (tema v15.26.0) no lugar da página vazia. Mostra: banner; as propostas de
// parceria com o MESMO texto do painel (CV_Parceria_Docs::montar, PIX e
// Parcerias → Propostas e contrato); o passo a passo para enviar a música,
// com o botão certo para cada pessoa (visitante, logado, parceiro com a aba
// "Enviar música"); o WhatsApp dos artistas com mensagem pronta; e perguntas
// frequentes. A janela "Seja nosso parceiro" é a do rodapé (CV_Apoio).
// v15.34.0: links para "Como assinar pelo gov.br" (/assinar-gov-br/).

if ( ! defined( 'ABSPATH' ) ) { exit; }

// WhatsApp exclusivo para artistas (pedido do Eduardo em 26/09/2026)
$whats_numero = apply_filters( 'cv_whatsapp_artistas', '5531983091360' );
$whats_visivel = '(31) 98309-1360';
$whats_link = 'https://wa.me/' . preg_replace( '/\D/', '', $whats_numero ) . '?text=' . rawurlencode( 'Olá, Canção Verdadeira! Sou artista e quero saber sobre a parceria. 🎵' );

// Propostas do painel, com um "artista" genérico no lugar dos dados da pessoa
$blocos = array();
$taxa_ok = false;
if ( class_exists( 'CV_Parceria_Docs' ) ) {
    $taxa_ok = '' !== CV_Parceria_Docs::config()['taxa'];
    $blocos  = CV_Parceria_Docs::montar( 'propostas', (object) array(
        'nome' => 'artista', 'nome_artistico' => 'artista', 'cidade_uf' => '', 'email' => '',
        'telefone' => '', 'musica' => '', 'link' => '',
    ) );
}

// Situação de quem está vendo: visitante, com conta, ou parceiro com a aba de envio
$logado   = is_user_logged_in();
$parceiro = $logado && class_exists( 'CV_Envio' ) && CV_Envio::pode_ver( get_current_user_id() );
$url_envio    = add_query_arg( 'aba', 'enviar', home_url( '/minha-area/' ) );
$url_cadastro = home_url( '/cadastro/' );
$url_login    = home_url( '/login/' );

get_header();
?>

<div class="cv-app" id="cv-app">

    <?php get_template_part( 'template-parts/sidebar' ); ?>

    <main class="cv-main" id="cv-main" role="main">

        <?php get_template_part( 'template-parts/topbar' ); ?>

        <?php
        ob_start(); ?>
            <div class="cv-artista-acoes">
                <?php if ( $parceiro ) : ?>
                <a class="cv-btn cv-btn-primary cv-btn-lg" href="<?php echo esc_url( $url_envio ); ?>">🎤 Enviar minha música agora</a>
                <?php else : ?>
                <button type="button" class="cv-btn cv-btn-primary cv-btn-lg cv-apoio-abrir" data-janela="cv-janela-parceiro">🤝 Quero ser parceiro</button>
                <?php endif; ?>
                <a class="cv-btn cv-btn-whats cv-btn-lg" href="<?php echo esc_url( $whats_link ); ?>" target="_blank" rel="noopener">
                    <?php echo class_exists( 'CV_Icones' ) ? CV_Icones::svg( 'whatsapp', 20, '#FFFFFF' ) : ''; // phpcs:ignore ?> Conversar no WhatsApp
                </a>
            </div>
        <?php $acoes = ob_get_clean();
        get_template_part( 'template-parts/banner-pagina', null, array(
            'tag'       => '🎤 Para artistas',
            'titulo'    => 'Sua música merece ser',
            'destaque'  => 'ouvida',
            'subtitulo' => 'Cantor, dupla ou compositor: divulgue suas canções para quem ama o sertanejo de verdade.',
            'acoes'     => $acoes,
        ) );
        ?>

        <?php if ( $blocos ) : ?>
        <section class="cv-section cv-artista-propostas" aria-labelledby="cv-artista-propostas-titulo">
            <h2 id="cv-artista-propostas-titulo" class="cv-section-title">🤝 Como podemos <span>caminhar juntos</span></h2>
            <div class="cv-artista-texto">
                <?php
                $lista_aberta = false;
                foreach ( $blocos as $b ) {
                    if ( 'titulo' === $b['tipo'] || 'nota' === $b['tipo'] ) { continue; } // o título é o da página; a validade é do documento
                    $texto = $b['texto'];
                    if ( ! $taxa_ok && false !== strpos( $texto, '______' ) ) {
                        $texto = 'Investimento: fale com a gente pelo WhatsApp para saber o valor.';
                    }
                    if ( 'item' === $b['tipo'] && ! $lista_aberta ) { echo '<ul>'; $lista_aberta = true; }
                    if ( 'item' !== $b['tipo'] && $lista_aberta ) { echo '</ul>'; $lista_aberta = false; }
                    if ( 'subtitulo' === $b['tipo'] )     { echo '<h3>' . esc_html( $texto ) . '</h3>'; }
                    elseif ( 'item' === $b['tipo'] )      { echo '<li>' . esc_html( $texto ) . '</li>'; }
                    else                                  { echo '<p>' . esc_html( $texto ) . '</p>'; }
                }
                if ( $lista_aberta ) { echo '</ul>'; }
                ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="cv-section cv-artista-passos" aria-labelledby="cv-artista-passos-titulo">
            <h2 id="cv-artista-passos-titulo" class="cv-section-title">🎤 Como enviar <span>sua música</span></h2>
            <ol class="cv-artista-lista-passos">
                <li>
                    <span class="cv-artista-num">1</span>
                    <h3>Mande a proposta</h3>
                    <p>Conte quem você é e qual música quer divulgar. É rápido e sem compromisso.</p>
                    <?php if ( ! $parceiro ) : ?>
                    <button type="button" class="cv-btn cv-btn-primary cv-apoio-abrir" data-janela="cv-janela-parceiro">🤝 Mandar proposta</button>
                    <?php else : ?>
                    <span class="cv-artista-feito">✅ Feito</span>
                    <?php endif; ?>
                </li>
                <li>
                    <span class="cv-artista-num">2</span>
                    <h3>Entre na sua conta</h3>
                    <p>Use o <strong>mesmo e-mail</strong> da proposta. Não tem conta? Crie uma de graça.</p>
                    <?php if ( ! $logado ) : ?>
                    <div class="cv-artista-botoes">
                        <a class="cv-btn cv-btn-secondary" href="<?php echo esc_url( $url_cadastro ); ?>">Criar conta</a>
                        <a class="cv-btn cv-btn-ghost" href="<?php echo esc_url( $url_login ); ?>">Entrar</a>
                    </div>
                    <?php else : ?>
                    <span class="cv-artista-feito">✅ Você já está na sua conta</span>
                    <?php endif; ?>
                </li>
                <li>
                    <span class="cv-artista-num">3</span>
                    <h3>Envie pela Minha Área</h3>
                    <p>Na aba <strong>"🎤 Enviar música"</strong>: o contrato assinado pelo gov.br (<a href="<?php echo esc_url( home_url( '/assinar-gov-br/' ) ); ?>">veja como assinar</a>), o PIX da divulgação, o link do YouTube e os dados da música.</p>
                    <?php if ( $parceiro ) : ?>
                    <a class="cv-btn cv-btn-primary" href="<?php echo esc_url( $url_envio ); ?>">🎤 Enviar minha música</a>
                    <?php else : ?>
                    <span class="cv-artista-nota">A aba aparece depois dos passos 1 e 2.</span>
                    <?php endif; ?>
                </li>
                <li>
                    <span class="cv-artista-num">4</span>
                    <h3>Sua música no ar</h3>
                    <p>Conferimos tudo e sua música ganha página própria no site, com letra, vídeo e player.</p>
                </li>
            </ol>
        </section>

        <section class="cv-section" aria-labelledby="cv-artista-whats-titulo">
            <div class="cv-artista-whats">
                <div>
                    <h2 id="cv-artista-whats-titulo">💬 Prefere conversar?</h2>
                    <p>Fale direto com a Canção Verdadeira pelo WhatsApp dos artistas: <strong><?php echo esc_html( $whats_visivel ); ?></strong>. Tiramos dúvidas sobre a parceria, o contrato e o envio.</p>
                </div>
                <a class="cv-btn cv-btn-whats cv-btn-lg" href="<?php echo esc_url( $whats_link ); ?>" target="_blank" rel="noopener">
                    <?php echo class_exists( 'CV_Icones' ) ? CV_Icones::svg( 'whatsapp', 22, '#FFFFFF' ) : ''; // phpcs:ignore ?> Chamar no WhatsApp
                </a>
            </div>
        </section>

        <section class="cv-section cv-artista-faq" aria-labelledby="cv-artista-faq-titulo">
            <h2 id="cv-artista-faq-titulo" class="cv-section-title">❓ Perguntas <span>frequentes</span></h2>
            <details>
                <summary>A minha música continua sendo minha?</summary>
                <p>Sim. A Canção Verdadeira só divulga: não compra a música, não fica com ela e não recebe nada dos direitos autorais.</p>
            </details>
            <details>
                <summary>Quanto custa?</summary>
                <p>A divulgação tem uma taxa única, paga por PIX. <?php echo $taxa_ok ? 'O valor aparece nas propostas acima.' : 'Fale com a gente pelo WhatsApp para saber o valor.'; ?></p>
            </details>
            <details>
                <summary>O que é a assinatura digital do contrato?</summary>
                <p>É uma assinatura feita pelo celular ou computador, de graça, no aplicativo ou site do <strong>gov.br</strong> (conta prata ou ouro). Ela dá validade ao contrato sem precisar imprimir nem ir ao cartório. <a href="<?php echo esc_url( home_url( '/assinar-gov-br/' ) ); ?>">Veja o passo a passo</a>. Se tiver dificuldade, chame no WhatsApp que a gente ajuda.</p>
            </details>
            <details>
                <summary>Que tipo de música vocês divulgam?</summary>
                <p>Sertanejo com letra respeitosa e sentimento verdadeiro: romântico, raiz, saudade, fé e festa. Conferimos cada música antes de publicar.</p>
            </details>
        </section>

        <?php get_template_part( 'template-parts/footer-content' ); ?>
        <?php get_template_part( 'template-parts/player' ); ?>

    </main>

</div>

<?php get_footer(); ?>
