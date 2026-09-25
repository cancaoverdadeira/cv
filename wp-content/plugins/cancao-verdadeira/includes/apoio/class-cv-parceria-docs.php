<?php
// cancao-verdadeira/includes/apoio/class-cv-parceria-docs.php
// Criado em: 25/09/2026 (plugin v2.49.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Documentos do "Seja nosso parceiro": depois de enviar o formulário, o
// parceiro vê dois botões — "📄 Nossas propostas" e "📝 Modelo de contrato" —
// com os dados dele já preenchidos, na tela e para baixar em Word (.docx).
// Os textos, a taxa de divulgação, o prazo e o foro são editados no painel
// (PIX e Parcerias → aba "📄 Propostas e contrato"; opção cv_parceria_docs).
// Contrato-modelo: objeto ÚNICO e EXCLUSIVO de divulgação, mediante taxa; a
// música continua do artista (sem cessão — Lei 9.610/1998). Texto próprio,
// escrito para a Canção Verdadeira; recomenda-se revisão por advogado.
// Download: link com chave secreta (token) válido por 7 dias; o admin baixa
// qualquer um pelo painel.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Parceria_Docs {

    const OPCAO       = 'cv_parceria_docs';
    const VALIDADE    = 7; // dias em que o link de download do parceiro funciona

    public static function init() {
        add_action( 'wp_ajax_cv_parceria_doc_ver',          array( __CLASS__, 'ajax_ver' ) );
        add_action( 'wp_ajax_nopriv_cv_parceria_doc_ver',   array( __CLASS__, 'ajax_ver' ) );
        add_action( 'wp_ajax_cv_parceria_doc',              array( __CLASS__, 'baixar' ) );
        add_action( 'wp_ajax_nopriv_cv_parceria_doc',       array( __CLASS__, 'baixar' ) );
        add_action( 'wp_ajax_cv_apoio_docs_salvar',         array( __CLASS__, 'ajax_salvar' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // CONFIGURAÇÃO (painel)
    // ════════════════════════════════════════════════════════════════

    public static function marcadores() {
        return array(
            '{nome}'           => 'Nome completo do parceiro',
            '{nome_artistico}' => 'Nome artístico (ou o nome, se ficar em branco)',
            '{cidade_uf}'      => 'Cidade / UF do parceiro',
            '{email}'          => 'E-mail do parceiro',
            '{telefone}'       => 'WhatsApp do parceiro',
            '{musica}'         => 'Título da música',
            '{link}'           => 'Link enviado pelo parceiro',
            '{taxa}'           => 'Valor da taxa (ex.: 150,00)',
            '{taxa_inclui}'    => 'O que a divulgação inclui',
            '{prazo}'          => 'Prazo da divulgação, em dias',
            '{contratada}'     => 'Quem assina pela Canção Verdadeira',
            '{foro}'           => 'Cidade do foro',
            '{pix}'            => 'Chave PIX cadastrada',
            '{site}'           => 'Endereço do site',
            '{email_contato}'  => 'E-mail de contato da Canção Verdadeira',
            '{data}'           => 'Data de hoje, por extenso',
        );
    }

    public static function config() {
        $c = get_option( self::OPCAO, array() );
        return wp_parse_args( is_array( $c ) ? $c : array(), array(
            'taxa'        => '',
            'taxa_inclui' => 'página própria da música no site (letra, vídeo e player), destaque na seção "Chegando Agora" da página inicial por 7 dias, um post no blog da Canção Verdadeira e uma publicação nas redes sociais oficiais',
            'prazo'       => 90,
            'contratada'  => 'CANÇÃO VERDADEIRA, representada por Eduardo Marques',
            'foro'        => 'Belo Horizonte/MG',
            'propostas'   => self::propostas_padrao(),
            'contrato'    => self::contrato_padrao(),
        ) );
    }

    public static function ajax_salvar() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
        $p    = wp_unslash( $_POST );
        $taxa = trim( sanitize_text_field( $p['taxa'] ?? '' ) );
        // Aceita 150 · 150,00 · 1.500,00 · 150.00
        if ( '' !== $taxa && ! preg_match( '/^(\d{1,3}(\.\d{3})*|\d+)(,\d{1,2})?$|^\d+\.\d{1,2}$/', $taxa ) ) {
            wp_send_json_error( array( 'message' => 'Taxa inválida. Use só números, ex.: 150,00' ) );
        }
        $padrao = isset( $p['restaurar'] ) && '1' === $p['restaurar'];
        update_option( self::OPCAO, array(
            'taxa'        => $taxa,
            'taxa_inclui' => sanitize_textarea_field( $p['taxa_inclui'] ?? '' ),
            'prazo'       => max( 1, absint( $p['prazo'] ?? 90 ) ),
            'contratada'  => sanitize_text_field( $p['contratada'] ?? '' ),
            'foro'        => sanitize_text_field( $p['foro'] ?? '' ),
            'propostas'   => $padrao ? self::propostas_padrao() : sanitize_textarea_field( $p['propostas'] ?? '' ),
            'contrato'    => $padrao ? self::contrato_padrao() : sanitize_textarea_field( $p['contrato'] ?? '' ),
        ), false );
        wp_send_json_success( array( 'message' => $padrao ? 'Textos-modelo restaurados.' : 'Propostas e contrato salvos.' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // MONTAGEM DOS DOCUMENTOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Troca os {marcadores} pelos dados e transforma o texto em blocos:
     * "# " título · "## " subtítulo · "- " item de lista · "> " nota
     * pequena · outras linhas = parágrafo (cada linha é um parágrafo).
     */
    public static function montar( $tipo, $p ) {
        $c     = self::config();
        $texto = 'contrato' === $tipo ? $c['contrato'] : $c['propostas'];
        $taxa  = '' !== $c['taxa'] ? $c['taxa'] : '______ (a definir)';
        $pix   = class_exists( 'CV_Pix' ) && CV_Pix::ativo() ? CV_Pix::chave_para_exibir() : '______________';
        $vazio = '______________________';
        $troca = array(
            '{nome}'           => $p->nome ? $p->nome : $vazio,
            '{nome_artistico}' => ! empty( $p->nome_artistico ) ? $p->nome_artistico : ( $p->nome ? $p->nome : $vazio ),
            '{cidade_uf}'      => ! empty( $p->cidade_uf ) ? $p->cidade_uf : $vazio,
            '{email}'          => $p->email ? $p->email : $vazio,
            '{telefone}'       => ! empty( $p->telefone ) ? $p->telefone : $vazio,
            '{musica}'         => ! empty( $p->musica ) ? $p->musica : $vazio,
            '{link}'           => ! empty( $p->link ) ? $p->link : '—',
            '{taxa}'           => $taxa,
            '{taxa_inclui}'    => $c['taxa_inclui'],
            '{prazo}'          => (string) (int) $c['prazo'],
            '{contratada}'     => $c['contratada'] ? $c['contratada'] : $vazio,
            '{foro}'           => $c['foro'] ? $c['foro'] : $vazio,
            '{pix}'            => $pix,
            '{site}'           => home_url( '/' ),
            '{email_contato}'  => class_exists( 'CV_Apoio' ) ? CV_Apoio::email() : get_option( 'admin_email' ),
            '{data}'           => date_i18n( 'j \d\e F \d\e Y' ),
        );
        $texto  = strtr( $texto, $troca );
        $blocos = array();
        foreach ( preg_split( '/\r\n|\r|\n/', $texto ) as $linha ) {
            $linha = rtrim( $linha );
            if ( '' === trim( $linha ) ) { continue; }
            if ( 0 === strpos( $linha, '## ' ) )    { $blocos[] = array( 'tipo' => 'subtitulo', 'texto' => substr( $linha, 3 ) ); }
            elseif ( 0 === strpos( $linha, '# ' ) ) { $blocos[] = array( 'tipo' => 'titulo',    'texto' => substr( $linha, 2 ) ); }
            elseif ( 0 === strpos( $linha, '- ' ) ) { $blocos[] = array( 'tipo' => 'item',      'texto' => substr( $linha, 2 ) ); }
            elseif ( 0 === strpos( $linha, '> ' ) ) { $blocos[] = array( 'tipo' => 'nota',      'texto' => substr( $linha, 2 ) ); }
            else                                    { $blocos[] = array( 'tipo' => 'paragrafo', 'texto' => $linha ); }
        }
        return $blocos;
    }

    /** Dados fictícios, para o botão "ver exemplo" do painel. */
    public static function exemplo() {
        return (object) array(
            'id' => 0, 'nome' => 'Maria da Silva', 'nome_artistico' => 'Maria do Sertão', 'cidade_uf' => 'Divinópolis/MG',
            'email' => 'maria@exemplo.com', 'telefone' => '(37) 99999-0000', 'musica' => 'Estrada de Terra', 'link' => 'https://youtube.com/…',
        );
    }

    // ════════════════════════════════════════════════════════════════
    // AJAX — ver na tela e baixar
    // ════════════════════════════════════════════════════════════════

    /** Busca a proposta e confere a chave (ou o admin). Devolve o registro ou null. */
    private static function registro_autorizado( $id, $token ) {
        global $wpdb;
        $p = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}cv_parcerias WHERE id = %d", $id ) );
        if ( ! $p ) { return null; }
        if ( current_user_can( 'manage_options' ) ) { return $p; }
        $valido = strtotime( $p->criado_em ) >= current_time( 'timestamp' ) - self::VALIDADE * DAY_IN_SECONDS;
        return ( $valido && '' !== $p->token && is_string( $token ) && hash_equals( $p->token, $token ) ) ? $p : null;
    }

    private static function tipo_pedido() {
        return 'contrato' === sanitize_key( $_REQUEST['doc'] ?? '' ) ? 'contrato' : 'propostas';
    }

    public static function ajax_ver() {
        $tipo = self::tipo_pedido();
        $id   = absint( $_POST['id'] ?? 0 );
        $p    = 0 === $id && current_user_can( 'manage_options' ) ? self::exemplo() : self::registro_autorizado( $id, wp_unslash( $_POST['t'] ?? '' ) );
        if ( ! $p ) { wp_send_json_error( array( 'message' => 'Link expirado. Envie o formulário de novo ou fale com a gente.' ) ); }
        wp_send_json_success( array(
            'html'  => CV_Docx::html( self::montar( $tipo, $p ) ),
            'baixar'=> self::url_baixar( $p, $tipo ),
        ) );
    }

    public static function url_baixar( $p, $tipo ) {
        return add_query_arg( array(
            'action' => 'cv_parceria_doc',
            'doc'    => $tipo,
            'id'     => (int) $p->id,
            't'      => isset( $p->token ) ? $p->token : '',
        ), admin_url( 'admin-ajax.php' ) );
    }

    public static function baixar() {
        $tipo = self::tipo_pedido();
        $id   = absint( $_GET['id'] ?? 0 );
        $p    = 0 === $id && current_user_can( 'manage_options' ) ? self::exemplo() : self::registro_autorizado( $id, wp_unslash( $_GET['t'] ?? '' ) );
        if ( ! $p ) { wp_die( 'Link expirado ou inválido. Envie o formulário "Seja nosso parceiro" de novo ou fale com a gente.', 'Canção Verdadeira', array( 'response' => 403 ) ); }
        $base = 'contrato' === $tipo ? 'contrato-divulgacao' : 'propostas-cancao-verdadeira';
        $quem = sanitize_title( ! empty( $p->nome_artistico ) ? $p->nome_artistico : $p->nome );
        CV_Docx::enviar( $base . ( $quem ? '-' . $quem : '' ) . '.docx', self::montar( $tipo, $p ) );
    }

    // ════════════════════════════════════════════════════════════════
    // TEXTOS-MODELO (escritos para a Canção Verdadeira)
    // ════════════════════════════════════════════════════════════════

    public static function propostas_padrao() {
        return <<<TXT
# Nossas propostas de parceria
Olá, {nome_artistico}! Obrigado pelo interesse em caminhar junto com a Canção Verdadeira. Aqui está, de forma simples, como podemos trabalhar juntos.
## 1. Divulgação da sua música
Levamos a sua música ao nosso público: pessoas que amam o sertanejo de raiz e as letras que contam histórias verdadeiras.
- O que está incluído: {taxa_inclui}.
- Prazo da divulgação: {prazo} dias.
- Investimento: R$ {taxa}, pagos uma única vez por PIX.
## 2. Gravação de músicas da Canção Verdadeira
Se você canta e gostou de alguma música do nosso catálogo, podemos conversar sobre a gravação. Cada caso é combinado à parte, com autorização por escrito.
## 3. Como funciona
- Você nos envia a música (link ou arquivo), a letra e uma foto ou capa.
- Conferimos se a música combina com a linha do site (respeito, sentimento, sem conteúdo ofensivo).
- Assinamos o contrato de divulgação e você faz o PIX.
- A divulgação começa em até 5 dias úteis depois da confirmação do pagamento.
## 4. Importante
Sua música continua sendo sua. A Canção Verdadeira apenas divulga: não compra, não fica com a música e não recebe nada dos direitos autorais.
Dúvidas? Escreva para {email_contato}.
> Proposta válida por 30 dias a partir de {data}. Valores e condições podem mudar sem aviso para novas propostas.
TXT;
    }

    public static function contrato_padrao() {
        return <<<TXT
# CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE DIVULGAÇÃO MUSICAL
Pelo presente instrumento particular, as partes abaixo identificadas:
CONTRATANTE: {nome}, conhecido(a) artisticamente como "{nome_artistico}", residente em {cidade_uf}, e-mail {email}, telefone {telefone}, CPF nº ______________________.
CONTRATADA: {contratada}, responsável pelo site Canção Verdadeira ({site}), CPF/CNPJ nº ______________________.
têm entre si justo e acordado o que segue.
## Cláusula 1ª — Do objeto
1.1. O objeto deste contrato é, ÚNICA E EXCLUSIVAMENTE, a prestação de serviços de divulgação da obra musical "{musica}" (a "OBRA"), apresentada pelo CONTRATANTE, no site e nos canais oficiais da CONTRATADA.
1.2. A divulgação compreende: {taxa_inclui}.
1.3. Não fazem parte deste contrato gravação, produção, edição, distribuição em plataformas de streaming, agenciamento, empresariamento ou qualquer outro serviço não descrito no item 1.2.
## Cláusula 2ª — Dos direitos sobre a obra
2.1. A OBRA continua pertencendo integralmente ao CONTRATANTE e aos demais autores e titulares, se houver. A divulgação NÃO transfere à CONTRATADA a propriedade da OBRA, nem direitos autorais (patrimoniais ou morais), nem direitos sobre gravações, interpretações ou fonogramas.
2.2. Este contrato não é cessão de direitos. Em conformidade com a Lei nº 9.610/1998 (Lei de Direitos Autorais), o CONTRATANTE apenas AUTORIZA a CONTRATADA, de forma não exclusiva, pelo prazo deste contrato e somente para a finalidade de divulgação, a exibir o título, a letra, a capa e o vídeo ou link oficial fornecidos por ele.
2.3. A CONTRATADA indicará sempre o nome do(s) autor(es) e do intérprete, e não alterará a letra, a melodia ou a gravação.
2.4. Todos os rendimentos da OBRA (streaming, execução pública, vendas, shows e outros) pertencem ao CONTRATANTE e aos demais titulares. A CONTRATADA não recebe participação de nenhum tipo.
2.5. O CONTRATANTE declara ser autor ou titular da OBRA, ou estar autorizado pelos titulares a permitir a divulgação, e responde por qualquer reclamação de terceiros sobre a autoria ou os direitos da OBRA.
## Cláusula 3ª — Da taxa e do pagamento
3.1. Pelo serviço de divulgação, o CONTRATANTE pagará à CONTRATADA a taxa única de R$ {taxa}, por PIX (chave {pix}), em até 5 (cinco) dias após a assinatura deste contrato.
3.2. A divulgação começa em até 5 (cinco) dias úteis após a confirmação do pagamento.
3.3. A taxa remunera somente o serviço de divulgação. Ela não representa compra, cessão, licença onerosa ou qualquer participação na OBRA.
3.4. A CONTRATADA não garante número de visualizações, execuções, seguidores, posição em ranking ou resultado comercial, pois esses números dependem do público.
## Cláusula 4ª — Do prazo
4.1. A divulgação terá duração de {prazo} dias, contados do seu início, e poderá ser renovada por acordo entre as partes, inclusive por e-mail.
4.2. Encerrado o prazo sem renovação, a CONTRATADA retirará a OBRA do site e dos destaques em até 7 (sete) dias. Publicações já feitas em redes sociais podem permanecer, salvo pedido escrito do CONTRATANTE para removê-las.
## Cláusula 5ª — Das obrigações
5.1. A CONTRATADA se compromete a realizar a divulgação descrita no item 1.2, respeitar a integridade da OBRA e, se solicitado, informar ao final os números básicos de acesso à página da OBRA.
5.2. O CONTRATANTE se compromete a fornecer a letra, a capa e o link oficial da OBRA livres de direitos de terceiros, e a pagar a taxa no prazo combinado.
5.3. A CONTRATADA poderá recusar ou retirar conteúdo ofensivo, ilegal ou contrário à linha editorial do site, devolvendo a taxa proporcional ao período não cumprido.
## Cláusula 6ª — Da rescisão
6.1. Qualquer das partes pode encerrar este contrato com aviso por escrito (e-mail vale) de 7 (sete) dias.
6.2. Se o encerramento ocorrer antes do início da divulgação, a taxa será devolvida integralmente. Depois do início, não haverá devolução, salvo se o encerramento for causado por descumprimento da CONTRATADA, caso em que será devolvido o valor proporcional ao período não cumprido.
6.3. O descumprimento de qualquer cláusula permite o encerramento imediato pela parte prejudicada.
## Cláusula 7ª — Dos dados pessoais
7.1. Os dados pessoais do CONTRATANTE serão usados somente para a execução deste contrato e para a comunicação entre as partes, conforme a Lei nº 13.709/2018 (LGPD). O CONTRATANTE pode pedir a exclusão dos seus dados pelo e-mail {email_contato}, respeitado o prazo legal de guarda deste contrato.
## Cláusula 8ª — Disposições gerais
8.1. Este contrato não cria vínculo de emprego, sociedade ou representação entre as partes.
8.2. As comunicações entre as partes podem ser feitas pelos e-mails indicados neste contrato.
8.3. Qualquer mudança neste contrato só vale se for feita por escrito e aceita pelas duas partes.
## Cláusula 9ª — Do foro
9.1. Fica eleito o foro da comarca de {foro} para resolver questões deste contrato, com renúncia a qualquer outro.
E, por estarem de acordo, as partes assinam este contrato em 2 (duas) vias de igual teor.
{foro}, {data}.
______________________________________
CONTRATANTE: {nome}
______________________________________
CONTRATADA: {contratada}
Testemunhas: 1. ______________________   2. ______________________
> Documento-modelo gerado pelo site Canção Verdadeira para conferência. Só tem validade depois de assinado pelas duas partes. Recomenda-se a leitura com atenção e, se possível, a revisão por um advogado.
TXT;
    }
}

CV_Parceria_Docs::init();
