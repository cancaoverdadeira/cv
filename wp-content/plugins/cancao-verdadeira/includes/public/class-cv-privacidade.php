<?php
// cancao-verdadeira/includes/public/class-cv-privacidade.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Página "Política de Privacidade" (LGPD), v2.73.8 (27/10/2026).
// Uma vez só (opção cv_privacidade_pagina): usa a página de privacidade do
// WordPress (opção wp_page_for_privacy_policy; a de fábrica é um RASCUNHO em
// inglês/genérico) ou a de endereço /politica-de-privacidade/. Só escreve o
// texto se a página ainda for rascunho ou não existir; uma página já publicada
// (talvez editada pelo Eduardo ou pelo advogado) nunca é sobrescrita.
// Depois o texto se edita normalmente em Páginas → Política de Privacidade.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Privacidade {

    const SLUG   = 'politica-de-privacidade';
    const VERSAO = '1';

    public static function init() {
        add_action( 'wp_loaded', array( __CLASS__, 'criar_pagina' ) );
    }

    /** Cria (ou completa o rascunho de fábrica) e publica a página, uma única vez. */
    public static function criar_pagina() {
        if ( get_option( 'cv_privacidade_pagina' ) === self::VERSAO ) { return; }

        $id   = (int) get_option( 'wp_page_for_privacy_policy' );
        $post = $id ? get_post( $id ) : null;
        if ( ! $post || 'page' !== $post->post_type || 'trash' === $post->post_status ) {
            $post = get_page_by_path( self::SLUG );
        }

        $dados = array(
            'post_type'    => 'page',
            'post_title'   => 'Política de Privacidade',
            'post_name'    => self::SLUG,
            'post_status'  => 'publish',
            'post_content' => self::texto(),
        );

        if ( $post && 'publish' === $post->post_status ) {
            $id = $post->ID; // já publicada: não mexe no texto
        } elseif ( $post ) {
            $dados['ID'] = $post->ID;
            $id = wp_update_post( $dados, true );
        } else {
            $id = wp_insert_post( $dados, true );
        }

        if ( $id && ! is_wp_error( $id ) ) {
            update_option( 'wp_page_for_privacy_policy', (int) $id );
        }
        update_option( 'cv_privacidade_pagina', self::VERSAO, false );
    }

    /** Texto da política, em linguagem simples (rascunho para o advogado revisar). */
    public static function texto() {
        $email = 'contato@cancaoverdadeira.com.br';
        $data  = '27 de outubro de 2026';
        return <<<HTML
<p><em>Última atualização: {$data}.</em></p>

<p>Esta página explica, em palavras simples, quais informações o site <strong>Canção Verdadeira</strong> guarda sobre você, para que usa, com quem compartilha e como você pode pedir para ver, corrigir ou apagar seus dados. Ela segue a Lei Geral de Proteção de Dados (LGPD, Lei nº 13.709/2018).</p>

<h2>1. Quem cuida dos seus dados</h2>
<p>O site Canção Verdadeira (cancaoverdadeira.com.br), de músicas autorais sertanejas, é o responsável pelos dados descritos aqui. Para qualquer assunto sobre privacidade, escreva para <a href="mailto:{$email}">{$email}</a>.</p>

<h2>2. Que dados guardamos e para quê</h2>
<ul>
<li><strong>Newsletter:</strong> seu nome (opcional) e seu e-mail, para avisar sobre músicas novas e notícias do canal.</li>
<li><strong>Cadastro (Minha Área):</strong> nome, sobrenome, e-mail e senha (guardada de forma embaralhada, nem nós conseguimos ler). Se você quiser, também cidade, estado, como conheceu o site e foto. Servem para a sua conta funcionar: favoritos, playlists, avaliações e conquistas.</li>
<li><strong>Depoimentos:</strong> nome, cidade e o texto que você escrever. Só aparecem no site depois de aprovados.</li>
<li><strong>Parcerias e envio de música:</strong> os dados do formulário e os documentos que você enviar, para analisar a sua proposta.</li>
<li><strong>Apoio por PIX:</strong> o pagamento acontece no aplicativo do seu banco. Não recebemos dados de cartão nem senha de banco.</li>
<li><strong>Dados técnicos:</strong> endereço de internet (IP), tipo de aparelho e navegador, para contar as músicas tocadas sem fraude, gerar estatísticas de visitas e proteger o site contra ataques.</li>
</ul>

<h2>3. Por que podemos usar esses dados</h2>
<ul>
<li>Newsletter: porque você <strong>autorizou</strong> ao se inscrever (consentimento).</li>
<li>Cadastro, favoritos e playlists: porque são necessários para prestar o serviço que você pediu.</li>
<li>Segurança e estatísticas: pelo interesse legítimo de manter o site seguro e funcionando bem, e para cumprir a lei (o Marco Civil da Internet pede que os registros de acesso sejam guardados por 6 meses).</li>
</ul>

<h2>4. Com quem compartilhamos</h2>
<p><strong>Não vendemos seus dados.</strong> Usamos alguns serviços para o site funcionar, e eles recebem só o necessário:</p>
<ul>
<li><strong>Hostnet</strong> (Brasil): hospedagem do site.</li>
<li><strong>Cloudflare</strong>: proteção contra ataques e velocidade.</li>
<li><strong>MailerLite</strong>: envio da newsletter (nome e e-mail).</li>
<li><strong>Google / YouTube</strong>: os vídeos das músicas e as estatísticas de visitas.</li>
<li><strong>WhatsApp</strong>: só quando você clica no botão do WhatsApp.</li>
</ul>
<p>Alguns desses serviços guardam dados fora do Brasil. Escolhemos empresas que seguem regras de proteção de dados reconhecidas.</p>

<h2>5. Cookies e dados no seu aparelho</h2>
<p>Cookies são pequenos arquivos que o navegador guarda. Usamos cookies para manter você conectado, e o seu aparelho guarda preferências como o tamanho da letra. O YouTube e as estatísticas do Google também usam cookies. Você pode apagar os cookies nas configurações do seu navegador a qualquer momento.</p>

<h2>6. Por quanto tempo guardamos</h2>
<ul>
<li>Newsletter: até você cancelar a inscrição.</li>
<li>Conta: enquanto ela existir. Se você pedir para apagar, os dados pessoais são apagados.</li>
<li>Registros de acesso e segurança: 6 meses, como pede a lei.</li>
</ul>

<h2>7. Seus direitos</h2>
<p>Você pode, a qualquer momento:</p>
<ul>
<li>saber quais dados temos sobre você;</li>
<li>corrigir dados errados (também direto na Minha Área);</li>
<li>pedir para apagar seus dados ou sua conta;</li>
<li><strong>cancelar a newsletter</strong>: basta clicar em <em>"Cancelar inscrição"</em> no fim de qualquer e-mail;</li>
<li>retirar uma autorização que você deu.</li>
</ul>
<p>Para isso, escreva para <a href="mailto:{$email}">{$email}</a>. Respondemos em até 15 dias. Se não ficar satisfeito, você pode procurar a Autoridade Nacional de Proteção de Dados (ANPD).</p>

<h2>8. Menores de idade</h2>
<p>O site é feito para adultos. Menores de 18 anos devem usar o cadastro e a newsletter com a autorização dos pais ou responsáveis.</p>

<h2>9. Segurança</h2>
<p>O site usa conexão protegida (o cadeado no navegador), senhas embaralhadas, firewall e cópias de segurança. Nenhum sistema é 100% invulnerável, mas cuidamos dos seus dados com seriedade e avisaremos você se algo grave acontecer.</p>

<h2>10. Mudanças nesta política</h2>
<p>Se esta política mudar, a data no começo da página será atualizada. Mudanças importantes serão avisadas no site ou por e-mail.</p>
HTML;
    }
}

CV_Privacidade::init();
