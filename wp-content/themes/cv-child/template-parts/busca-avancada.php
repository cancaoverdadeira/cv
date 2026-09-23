<?php
// cancao-verdadeira-child/template-parts/busca-avancada.php
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Formulário de busca avançada, usado pela página /buscar-musicas/ e pela 404.
// Campos: termo (título, trecho da letra, compositor ou artista, com
// autocomplete), sentimento (Saudade, Romance…) e ordenação.
// Envia por GET para /buscar-musicas/ (?q=, ?sentimento=, ?ordem=), onde a
// busca unificada do plugin (CV_Search → Relevanssi) monta os resultados.
// Uso: get_template_part( 'template-parts/busca-avancada', null, array( 'termo' => '', 'sentimento' => '', 'ordem' => '', 'autofocus' => false ) );
// Gerado em: 2026-09-23 (tema v15.5.0)

if ( ! defined( 'ABSPATH' ) ) { exit; }

$termo      = isset( $args['termo'] )      ? (string) $args['termo']      : '';
$sentimento = isset( $args['sentimento'] ) ? (string) $args['sentimento'] : '';
$ordem      = isset( $args['ordem'] ) && $args['ordem'] ? (string) $args['ordem'] : 'relevancia';
$autofocus  = ! empty( $args['autofocus'] );
$acao       = class_exists( 'CV_Search' ) ? CV_Search::url() : home_url( '/buscar-musicas/' );
$sentimentos = class_exists( 'CV_Sentimentos' ) ? CV_Sentimentos::get_all() : array();

$ordens = array(
    'relevancia' => 'Mais relevantes',
    'plays'      => 'Mais tocadas',
    'recente'    => 'Mais recentes',
    'titulo'     => 'A → Z',
);
?>
<form class="cv-busca-avancada" method="get" action="<?php echo esc_url( $acao ); ?>" role="search">
    <div class="cv-search-wrap cv-busca-campo">
        <label for="cv-busca-q" class="screen-reader-text">Buscar músicas</label>
        <input type="search"
               id="cv-busca-q"
               name="q"
               class="cv-input cv-search-input"
               value="<?php echo esc_attr( $termo ); ?>"
               placeholder="Título, trecho da letra, compositor ou artista…"
               autocomplete="off"
               <?php echo $autofocus ? 'autofocus' : ''; ?> />
        <button type="submit" class="cv-busca-lupa" aria-label="Buscar">🔍</button>
        <div class="cv-autocomplete-list"></div>
    </div>

    <div class="cv-busca-filtros">
        <?php if ( $sentimentos ) : ?>
        <label class="cv-busca-filtro">
            <span>Sentimento</span>
            <select name="sentimento">
                <option value="">Todos</option>
                <?php foreach ( $sentimentos as $s ) : ?>
                <option value="<?php echo esc_attr( $s->slug ); ?>" <?php selected( $sentimento, $s->slug ); ?>>
                    <?php echo esc_html( trim( $s->icone . ' ' . $s->nome ) ); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php endif; ?>

        <label class="cv-busca-filtro">
            <span>Ordenar</span>
            <select name="ordem">
                <?php foreach ( $ordens as $valor => $rotulo ) : ?>
                <option value="<?php echo esc_attr( $valor ); ?>" <?php selected( $ordem, $valor ); ?>><?php echo esc_html( $rotulo ); ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <button type="submit" class="cv-btn cv-btn-primary">Buscar</button>
    </div>
</form>
