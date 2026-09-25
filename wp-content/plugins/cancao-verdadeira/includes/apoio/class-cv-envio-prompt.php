<?php
// cancao-verdadeira/includes/apoio/class-cv-envio-prompt.php
// Criado em: 25/09/2026 (plugin v2.50.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// "Prompt de ajuda" da etapa 4 do envio de música: um arquivo Word para o
// parceiro colar numa IA (ChatGPT, Claude, Gemini) e receber título,
// descrição e tags otimizados para o YouTube. Texto-base do Eduardo
// ("Prompt youtube.odt/.docx", 25/09/2026; subitens do "b)" como no Word), com o material da música já inserido
// no lugar de "[Digite aqui a letra de sua música]". Editável no painel
// (PIX e Parcerias → 🎤 Envios de música; opção cv_envio_prompt).

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Envio_Prompt {

    const OPCAO = 'cv_envio_prompt';

    public static function init() {
        add_action( 'wp_ajax_cv_envio_prompt_salvar', array( __CLASS__, 'ajax_salvar' ) );
    }

    public static function texto() {
        $t = get_option( self::OPCAO, '' );
        return '' !== trim( (string) $t ) ? $t : self::padrao();
    }

    public static function ajax_salvar() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) { wp_send_json_error( array( 'message' => 'Sem permissão.' ) ); }
        if ( ! empty( $_POST['restaurar'] ) ) {
            delete_option( self::OPCAO );
            wp_send_json_success( array( 'message' => 'Prompt-modelo restaurado.' ) );
        }
        update_option( self::OPCAO, sanitize_textarea_field( wp_unslash( $_POST['texto'] ?? '' ) ), false );
        wp_send_json_success( array( 'message' => 'Prompt salvo.' ) );
    }

    /** Blocos para o CV_Docx, com o material da música no lugar dos marcadores. */
    public static function blocos( $e ) {
        $vazio = '(preencha)';
        $texto = strtr( self::texto(), array(
            '{titulo}'     => $e->titulo ? $e->titulo : $vazio,
            '{artista}'    => $e->artista ? $e->artista : $vazio,
            '{compositor}' => $e->compositor ? $e->compositor : $vazio,
            '{descricao}'  => $e->descricao ? $e->descricao : $vazio,
            '{tags}'       => $e->tags ? $e->tags : $vazio,
            '{youtube}'    => $e->youtube_url ? $e->youtube_url : $vazio,
        ) );
        $letra  = trim( (string) $e->letra );
        $blocos = array();
        foreach ( preg_split( '/\r\n|\r|\n/', $texto ) as $linha ) {
            $linha = rtrim( $linha );
            if ( '' === trim( $linha ) ) { continue; }
            if ( '{letra}' === trim( $linha ) ) {
                // A letra vai inteira, com as quebras de linha (estrofes separadas)
                $blocos[] = array( 'tipo' => 'paragrafo', 'texto' => '' !== $letra ? $letra : '[Cole aqui a letra completa da sua música]' );
                continue;
            }
            if ( 0 === strpos( $linha, '## ' ) )    { $blocos[] = array( 'tipo' => 'subtitulo', 'texto' => substr( $linha, 3 ) ); }
            elseif ( 0 === strpos( $linha, '# ' ) ) { $blocos[] = array( 'tipo' => 'titulo',    'texto' => substr( $linha, 2 ) ); }
            elseif ( 0 === strpos( $linha, '- ' ) ) { $blocos[] = array( 'tipo' => 'item',      'texto' => substr( $linha, 2 ) ); }
            elseif ( 0 === strpos( $linha, '> ' ) ) { $blocos[] = array( 'tipo' => 'nota',      'texto' => substr( $linha, 2 ) ); }
            else                                    { $blocos[] = array( 'tipo' => 'paragrafo', 'texto' => $linha ); }
        }
        return $blocos;
    }

    public static function padrao() {
        return <<<TXT
# YouTube: Descrição e SEO de Músicas Autorais
> Como usar: copie todo este texto e cole numa inteligência artificial (ChatGPT, Claude, Gemini). Ela devolve títulos, a descrição pronta e as tags. Depois, confira e cole no seu vídeo e no site da Canção Verdadeira.
## Papel
Atue como estrategista de divulgação no YouTube de músicas autorais, com foco em SEO.
## Material da música
Título: {titulo}
Artista / dupla: {artista}
Compositor(es): {compositor}
Apresentação (texto do autor): {descricao}
Palavras-chave e sentimentos: {tags}
Vídeo: {youtube}
Letra completa:
{letra}
## Regra essencial
- NUNCA citar, comparar ou sugerir cantores famosos nem músicas conhecidas — nem no texto, nem em títulos, tags ou hashtags.
- Usar SEMPRE o conteúdo original do autor na íntegra: apresentação da música, letra, palavras-chave/sentimentos e ficha técnica.
- Respeitar o limite do campo descrição do YouTube (~5.000 caracteres). Se o material exceder, priorizar nesta ordem: linha de abertura com SEO, texto de apresentação, letra completa, CTA; condensar o restante.
## Fluxo de trabalho
- 1. Receber o material da música (título, texto de apresentação, letra, palavras-chave, compositor).
- 2. Identificar tema central, emoções e público-alvo (ex.: saudade, coração partido, recomeço, amor verdadeiro).
- 3. Produzir, nesta ordem:
- a) Até 3 opções de título (máx. ~70 caracteres) com a palavra-chave principal: nome da música + estilo + tema. Ex.: "Título | Descrição curta | Letra".
- b) Descrição final pronta para copiar, dentro do limite de caracteres, na estrutura:
- · Linha 1: título do vídeo + palavras-chave principais (prioridade de ranqueamento no YouTube e Google).
- · Parágrafo emocional de apresentação, preservando o texto original do autor.
- · Seção "LETRA COMPLETA" com a letra integral em blocos (captura buscas por "letra de...").
- · Bloco de palavras e sentimentos da canção, separados por •.
- · CTA de engajamento: like, compartilhar, inscrever-se e ativar sininho.
- · Ficha técnica: Composição e Canal.
- c) Lista de tags/palavras-chave (nome da música, estilo, emoções, variações de busca).
- 4. Conferir a contagem de caracteres da descrição e ajustar até caber no limite.
## Regras de SEO aplicadas sempre
- Mesma palavra-chave principal repetida no título, na 1ª linha da descrição e nas tags.
- Hashtags sem espaços internos (correto: #FizPraVoce; errado: #Fiz pra você).
- Sugerir capítulos do vídeo quando útil (0:00 Abertura, verso 1, refrão...).
- Incluir variações de busca do público brasileiro coerentes com o gênero (sofrência, modão, música para chorar, música de saudade, sertanejo novo...).
## Formato da resposta (sempre em português)
- 1. Título sugerido (até 3 opções).
- 2. Descrição otimizada em bloco de código, pronta para copiar e colar.
- 3. Tags e palavras-chave.
- 4. Oferecer próximos passos opcionais (miniatura/thumbnail, roteiro de clipe).
TXT;
    }
}

CV_Envio_Prompt::init();
