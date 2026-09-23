<?php
// cancao-verdadeira/includes/public/class-cv-shortcodes.php
// Gerado em: 2026-06-21 22:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Registra os shortcodes públicos do plugin para uso no Elementor Pro
// e em qualquer página ou post do WordPress.
// Shortcodes disponíveis:
//   [cv_grid_musicas] — grade de músicas (ordem, destaque, colunas)
//   [cv_ranking]      — tabela/lista do ranking dinâmico
//   [cv_newsletter]   — formulário de inscrição na newsletter
//   [cv_musica_card]  — card avulso de uma música pelo ID
// v2.26.0: removidos [cv_generos] e os parâmetros de gênero (site todo sertanejo).
// v2.1: corrigido is_favorited -> is_favorite (bug que causava erro fatal).
// Todos os parâmetros têm valores padrão e são opcionais.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Shortcodes {

    public static function init() {
        add_shortcode( 'cv_grid_musicas', array( __CLASS__, 'grid_musicas' ) );
        add_shortcode( 'cv_ranking',      array( __CLASS__, 'ranking' ) );
        add_shortcode( 'cv_newsletter',   array( __CLASS__, 'newsletter' ) );
        add_shortcode( 'cv_musica_card',  array( __CLASS__, 'musica_card' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // 1. SHORTCODE: [cv_grid_musicas]
    // ════════════════════════════════════════════════════════════════
    //
    // Parâmetros:
    //   limite    = número de músicas (padrão: 12)
    //   ordem     = novas | tocadas | ranking (padrão: novas)
    //   colunas   = 2, 3 ou 4 (padrão: 3)
    //   destaque  = sim | nao — mostrar só músicas em destaque (padrão: nao)
    //   titulo    = texto do título da seção (padrão: vazio)
    //
    // Exemplos:
    //   [cv_grid_musicas]
    //   [cv_grid_musicas limite="6" ordem="tocadas"]
    //   [cv_grid_musicas titulo="Mais Tocadas" ordem="tocadas" limite="10"]
    //   [cv_grid_musicas destaque="sim" colunas="2"]

    public static function grid_musicas( $atts ) {
        $atts = shortcode_atts( array(
            'limite'   => 12,
            'ordem'    => 'novas',
            'colunas'  => 3,
            'destaque' => 'nao',
            'titulo'   => '',
        ), $atts, 'cv_grid_musicas' );

        $limite  = absint( $atts['limite'] );
        $colunas = absint( $atts['colunas'] );
        $ordem   = sanitize_text_field( $atts['ordem'] );
        $destaque = ( 'sim' === $atts['destaque'] );
        $titulo  = sanitize_text_field( $atts['titulo'] );

        // Limites de segurança
        if ( $limite < 1 || $limite > 100 ) { $limite = 12; }
        if ( ! in_array( $colunas, array( 2, 3, 4 ), true ) ) { $colunas = 3; }

        // Monta os argumentos da query
        $args = array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => $limite,
            'meta_query'     => array(
                array(
                    'key'     => '_cv_ativo',
                    'value'   => '1',
                    'compare' => '=',
                ),
            ),
        );

        // Filtro de destaque
        if ( $destaque ) {
            $args['meta_query'][] = array(
                'key'     => '_cv_destaque',
                'value'   => '1',
                'compare' => '=',
            );
        }

        // Ordenação
        switch ( $ordem ) {
            case 'tocadas':
                $args['meta_key'] = '_cv_plays_total';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;

            case 'ranking':
                $args['meta_key'] = '_cv_score';
                $args['orderby']  = 'meta_value_num';
                $args['order']    = 'DESC';
                break;

            default: // novas
                $args['orderby'] = 'date';
                $args['order']   = 'DESC';
                break;
        }

        $query = new WP_Query( $args );

        if ( ! $query->have_posts() ) {
            return '<p class="cv-sem-musicas">Nenhuma música encontrada.</p>';
        }

        $user_id = get_current_user_id();

        ob_start();
        ?>
        <div class="cv-grid-wrap">

            <?php if ( $titulo ) : ?>
                <h2 class="cv-section-titulo"><?php echo esc_html( $titulo ); ?></h2>
            <?php endif; ?>

            <div class="cv-grid cv-grid-col-<?php echo esc_attr( $colunas ); ?>">

                <?php while ( $query->have_posts() ) : $query->the_post(); ?>
                    <?php
                    $music_id    = get_the_ID();
                    $youtube_url = get_post_meta( $music_id, '_cv_youtube_url', true );
                    $compositor  = get_post_meta( $music_id, '_cv_compositor',  true );
                    $artista     = get_post_meta( $music_id, '_cv_artista',     true );
                    $plays       = (int) get_post_meta( $music_id, '_cv_plays_total', true );
                    $favoritos   = (int) get_post_meta( $music_id, '_cv_favorites',   true );
                    $avg         = (float) get_post_meta( $music_id, '_cv_avg_rating', true );
                    $is_fav      = $user_id ? CV_Favorites::is_favorite( $user_id, $music_id ) : false;
                    $posicao     = CV_Ranking::get_position( $music_id );

                    // Capa: tenta thumbnail, depois YouTube, depois padrão
                    $cover = get_the_post_thumbnail_url( $music_id, 'cv-cover' );
                    if ( ! $cover && $youtube_url ) {
                        preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $m );
                        $yt_id = isset( $m[1] ) ? $m[1] : '';
                        $cover = $yt_id ? 'https://img.youtube.com/vi/' . $yt_id . '/mqdefault.jpg' : '';
                    }
                    if ( ! $cover ) {
                        $cover = CV_PLUGIN_URL . 'assets/img/default-cover.svg';
                    }
                    ?>

                    <div class="cv-card" data-music-id="<?php echo esc_attr( $music_id ); ?>">

                        <!-- Capa clicável -->
                        <a href="<?php the_permalink(); ?>" class="cv-card-capa-link">
                            <div class="cv-card-capa" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                                <?php if ( $posicao && $posicao <= 10 ) : ?>
                                    <span class="cv-card-posicao">#<?php echo esc_html( $posicao ); ?></span>
                                <?php endif; ?>
                                <div class="cv-card-play-overlay">
                                    <span class="cv-card-play-icon">▶</span>
                                </div>
                            </div>
                        </a>

                        <!-- Informações -->
                        <div class="cv-card-info">

                            <a href="<?php the_permalink(); ?>" class="cv-card-titulo">
                                <?php the_title(); ?>
                            </a>

                            <?php if ( $artista || $compositor ) : ?>
                                <p class="cv-card-artista">
                                    <?php echo esc_html( $artista ?: $compositor ); ?>
                                </p>
                            <?php endif; ?>


                            <!-- Estatísticas e ações -->
                            <div class="cv-card-acoes">

                                <?php if ( CV_Launch::plays_visible( $plays ) ) : ?>
                                <span class="cv-card-stat">
                                    ▶ <span class="cv-play-count" data-music-id="<?php echo esc_attr( $music_id ); ?>"><?php echo number_format( $plays ); ?></span>
                                </span>
                                <?php else : echo CV_Launch::badge(); endif; ?>

                                <button
                                    class="cv-btn-favorite <?php echo $is_fav ? 'cv-favorited' : ''; ?>"
                                    data-music-id="<?php echo esc_attr( $music_id ); ?>"
                                    aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
                                    aria-label="<?php echo $is_fav ? 'Remover dos favoritos' : 'Adicionar aos favoritos'; ?>"
                                    title="Favoritar">
                                    ❤ <span class="cv-fav-count" data-music-id="<?php echo esc_attr( $music_id ); ?>"><?php echo CV_Launch::fav_label( $favoritos ); ?></span>
                                </button>

                                <?php if ( $avg > 0 && CV_Launch::ratings_visible( CV_Ratings::get_count( $music_id ) ) ) : ?>
                                    <span class="cv-card-stat cv-card-avg" title="Avaliação média">
                                        ★ <?php echo number_format( $avg, 1 ); ?>
                                    </span>
                                <?php endif; ?>

                                <button
                                    class="cv-btn-add-playlist"
                                    data-music-id="<?php echo esc_attr( $music_id ); ?>"
                                    title="Adicionar à playlist">
                                    ＋
                                </button>

                            </div><!-- .cv-card-acoes -->

                        </div><!-- .cv-card-info -->

                    </div><!-- .cv-card -->

                <?php endwhile; wp_reset_postdata(); ?>

            </div><!-- .cv-grid -->

        </div><!-- .cv-grid-wrap -->
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 2. SHORTCODE: [cv_ranking]
    // ════════════════════════════════════════════════════════════════
    //
    // Parâmetros:
    //   limite  = número de músicas (padrão: 10)
    //   tipo    = top | recentes | melhores (padrão: top)
    //   titulo  = texto do título da seção (padrão: "🏆 Ranking")
    //   layout  = lista | cards (padrão: lista)
    //
    // Exemplos:
    //   [cv_ranking]
    //   [cv_ranking limite="5" tipo="top" titulo="Top 5 da Semana"]
    //   [cv_ranking layout="cards"]

    public static function ranking( $atts ) {
        $atts = shortcode_atts( array(
            'limite' => 10,
            'tipo'   => 'top',
            'titulo' => '🏆 Ranking',
            'layout' => 'lista',
        ), $atts, 'cv_ranking' );

        $limite = absint( $atts['limite'] );
        $tipo   = sanitize_text_field( $atts['tipo'] );
        $titulo = sanitize_text_field( $atts['titulo'] );
        $layout = sanitize_text_field( $atts['layout'] );

        if ( $limite < 1 || $limite > 50 ) { $limite = 10; }

        // Busca os dados de ranking
        switch ( $tipo ) {
            case 'recentes':
                $musicas = CV_Ranking::get_recent( $limite );
                break;
            case 'melhores':
                $musicas = CV_Ranking::get_best( $limite );
                break;
            default: // top
                $musicas = CV_Ranking::get_top( $limite );
                break;
        }

        // Modo lançamento: sem audiência real suficiente, "top"/"melhores"
        // viram a seleção editorial, com o título deixando isso explícito.
        $modo_selecao = 'recentes' !== $tipo && ! CV_Launch::ranking_ready();
        if ( $modo_selecao ) {
            $musicas = CV_Launch::selection( $limite );
            $titulo  = '⭐ Seleção da Canção Verdadeira';
        }

        if ( empty( $musicas ) ) {
            return '<p class="cv-sem-musicas">Nenhuma música no ranking ainda. Cadastre músicas no painel.</p>';
        }

        ob_start();
        ?>
        <div class="cv-ranking-wrap cv-ranking-<?php echo esc_attr( $layout ); ?>">

            <?php if ( $titulo ) : ?>
                <h2 class="cv-section-titulo"><?php echo esc_html( $titulo ); ?></h2>
            <?php endif; ?>

            <?php if ( 'cards' === $layout ) : ?>

                <!-- Layout em cards (igual ao grid) -->
                <div class="cv-grid cv-grid-col-3">
                    <?php foreach ( $musicas as $i => $row ) :
                        $music_id = (int) $row->music_id;
                        $cover    = $row->cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg';
                        $posicao  = $i + 1;
                        $plays    = isset( $row->plays_total ) ? (int) $row->plays_total : 0;
                        $avg      = isset( $row->avg_rating )  ? (float) $row->avg_rating : 0;
                    ?>
                    <div class="cv-card" data-music-id="<?php echo esc_attr( $music_id ); ?>">
                        <a href="<?php echo esc_url( $row->url ); ?>" class="cv-card-capa-link">
                            <div class="cv-card-capa" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                                <span class="cv-card-posicao">#<?php echo esc_html( $posicao ); ?></span>
                                <div class="cv-card-play-overlay"><span class="cv-card-play-icon">▶</span></div>
                            </div>
                        </a>
                        <div class="cv-card-info">
                            <a href="<?php echo esc_url( $row->url ); ?>" class="cv-card-titulo">
                                <?php echo esc_html( $row->post_title ); ?>
                            </a>
                            <?php if ( ! empty( $row->artista ) ) : ?>
                                <p class="cv-card-artista"><?php echo esc_html( $row->artista ); ?></p>
                            <?php endif; ?>
                            <div class="cv-card-acoes">
                                <?php echo CV_Launch::plays_visible( $plays ) ? '<span class="cv-card-stat">▶ ' . number_format( $plays ) . '</span>' : CV_Launch::badge(); ?>
                                <?php if ( $avg > 0 && CV_Launch::ratings_visible( CV_Ratings::get_count( $music_id ) ) ) : ?>
                                    <span class="cv-card-stat">★ <?php echo number_format( $avg, 1 ); ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php else : ?>

                <!-- Layout em lista (padrão) -->
                <ol class="cv-ranking-lista">
                    <?php foreach ( $musicas as $i => $row ) :
                        $music_id = (int) $row->music_id;
                        $cover    = $row->cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg';
                        $posicao  = $i + 1;
                        $plays    = isset( $row->plays_total ) ? (int) $row->plays_total : 0;
                        $favs     = isset( $row->favorites )   ? (int) $row->favorites   : 0;
                        $avg      = isset( $row->avg_rating )  ? (float) $row->avg_rating : 0;
                        $score    = isset( $row->score )       ? (float) $row->score      : 0;

                        // Medalha para top 3
                        $medalha = '';
                        if ( 1 === $posicao ) { $medalha = '🥇'; }
                        elseif ( 2 === $posicao ) { $medalha = '🥈'; }
                        elseif ( 3 === $posicao ) { $medalha = '🥉'; }
                    ?>
                    <li class="cv-ranking-item <?php echo $posicao <= 3 ? 'cv-ranking-top3' : ''; ?>"
                        data-music-id="<?php echo esc_attr( $music_id ); ?>">

                        <span class="cv-ranking-pos">
                            <?php echo $medalha ?: '#' . esc_html( $posicao ); ?>
                        </span>

                        <div class="cv-ranking-capa"
                             style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                        </div>

                        <div class="cv-ranking-dados">
                            <a href="<?php echo esc_url( $row->url ); ?>" class="cv-ranking-titulo">
                                <?php echo esc_html( $row->post_title ); ?>
                            </a>
                            <?php if ( ! empty( $row->artista ) || ! empty( $row->compositor ) ) : ?>
                                <span class="cv-ranking-artista">
                                    <?php echo esc_html( $row->artista ?: $row->compositor ); ?>
                                </span>
                            <?php endif; ?>
                        </div>

                        <div class="cv-ranking-stats">
                            <?php echo CV_Launch::plays_visible( $plays ) ? '<span title="Plays">▶ ' . number_format( $plays ) . '</span>' : CV_Launch::badge(); ?>
                            <?php if ( CV_Launch::favs_visible( $favs ) ) : ?>
                                <span title="Favoritos">❤ <?php echo number_format( $favs ); ?></span>
                            <?php endif; ?>
                            <?php if ( $avg > 0 && CV_Launch::ratings_visible( CV_Ratings::get_count( $music_id ) ) ) : ?>
                                <span title="Avaliação">★ <?php echo number_format( $avg, 1 ); ?></span>
                            <?php endif; ?>
                            <?php if ( $score > 0 ) : ?>
                                <span class="cv-ranking-score" title="Score">
                                    <?php echo number_format( $score, 1 ); ?> pts
                                </span>
                            <?php endif; ?>
                        </div>

                    </li>
                    <?php endforeach; ?>
                </ol>

            <?php endif; ?>

        </div><!-- .cv-ranking-wrap -->
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 3. SHORTCODE: [cv_newsletter]
    // ════════════════════════════════════════════════════════════════
    //
    // Parâmetros:
    //   titulo       = título do formulário (padrão: "Receba novidades")
    //   subtitulo    = texto de apoio (padrão: mensagem padrão)
    //   mostrar_nome = sim | nao (padrão: sim)
    //   botao        = texto do botão (padrão: "Assinar Grátis 🎵")
    //
    // Exemplos:
    //   [cv_newsletter]
    //   [cv_newsletter titulo="Fique por dentro!" botao="Quero receber"]
    //   [cv_newsletter mostrar_nome="nao"]

    public static function newsletter( $atts ) {
        $atts = shortcode_atts( array(
            'titulo'         => 'Receba as novidades',
            'subtitulo'      => 'Cadastre seu e-mail e saiba primeiro quando novas músicas forem publicadas.',
            'mostrar_nome'   => 'sim',
            'botao'          => 'Assinar Grátis 🎵',
        ), $atts, 'cv_newsletter' );

        $titulo         = sanitize_text_field( $atts['titulo'] );
        $subtitulo      = sanitize_text_field( $atts['subtitulo'] );
        $mostrar_nome   = ( 'sim' === $atts['mostrar_nome'] );
        $botao          = sanitize_text_field( $atts['botao'] );

        ob_start();
        ?>
        <div class="cv-newsletter-wrap">

            <?php if ( $titulo ) : ?>
                <h3 class="cv-newsletter-titulo"><?php echo esc_html( $titulo ); ?></h3>
            <?php endif; ?>

            <?php if ( $subtitulo ) : ?>
                <p class="cv-newsletter-subtitulo"><?php echo esc_html( $subtitulo ); ?></p>
            <?php endif; ?>

            <form class="cv-newsletter-form" novalidate>

                <?php if ( $mostrar_nome ) : ?>
                    <input
                        type="text"
                        name="cv_name"
                        placeholder="Seu nome (opcional)"
                        autocomplete="name"
                    />
                <?php endif; ?>

                <input
                    type="email"
                    name="cv_email"
                    placeholder="Seu melhor e-mail *"
                    required
                    autocomplete="email"
                />

                <button type="submit"><?php echo esc_html( $botao ); ?></button>

                <!-- Mensagem de retorno (preenchida pelo JS) -->
                <div class="cv-newsletter-msg" style="display:none;"></div>

            </form>

        </div><!-- .cv-newsletter-wrap -->
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 4. SHORTCODE: [cv_musica_card id="123"]
    // ════════════════════════════════════════════════════════════════
    // Exibe o card de uma música específica pelo ID.
    // Útil para inserir uma música em destaque em qualquer página do Elementor.
    //
    // Parâmetros:
    //   id = ID do post da música (obrigatório)
    //
    // Exemplo: [cv_musica_card id="42"]

    public static function musica_card( $atts ) {
        $atts     = shortcode_atts( array( 'id' => 0 ), $atts, 'cv_musica_card' );
        $music_id = absint( $atts['id'] );

        if ( ! $music_id || 'musica' !== get_post_type( $music_id ) ) {
            return '<p class="cv-sem-musicas">ID de música inválido.</p>';
        }

        $youtube_url = get_post_meta( $music_id, '_cv_youtube_url', true );
        $compositor  = get_post_meta( $music_id, '_cv_compositor',  true );
        $artista     = get_post_meta( $music_id, '_cv_artista',     true );
        $plays       = (int) get_post_meta( $music_id, '_cv_plays_total', true );
        $favoritos   = (int) get_post_meta( $music_id, '_cv_favorites',   true );
        $avg         = (float) get_post_meta( $music_id, '_cv_avg_rating', true );
        $user_id     = get_current_user_id();
        $is_fav      = $user_id ? CV_Favorites::is_favorite( $user_id, $music_id ) : false;

        $cover = get_the_post_thumbnail_url( $music_id, 'cv-cover' );
        if ( ! $cover && $youtube_url ) {
            preg_match( '/(?:v=|\/embed\/|\.be\/)([a-zA-Z0-9_-]{11})/', $youtube_url, $m );
            if ( ! empty( $m[1] ) ) {
                $cover = 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg';
            }
        }
        $cover = $cover ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg';

        ob_start();
        ?>
        <div class="cv-card" data-music-id="<?php echo esc_attr( $music_id ); ?>" style="max-width:280px">
            <a href="<?php echo esc_url( get_permalink( $music_id ) ); ?>" class="cv-card-capa-link">
                <div class="cv-card-capa" style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                    <div class="cv-card-play-overlay"><span class="cv-card-play-icon">▶</span></div>
                </div>
            </a>
            <div class="cv-card-info">
                <a href="<?php echo esc_url( get_permalink( $music_id ) ); ?>" class="cv-card-titulo">
                    <?php echo esc_html( get_the_title( $music_id ) ); ?>
                </a>
                <?php if ( $artista || $compositor ) : ?>
                    <p class="cv-card-artista"><?php echo esc_html( $artista ?: $compositor ); ?></p>
                <?php endif; ?>
                <div class="cv-card-acoes">
                    <?php echo CV_Launch::plays_visible( $plays ) ? '<span class="cv-card-stat">▶ ' . number_format( $plays ) . '</span>' : CV_Launch::badge(); ?>
                    <button class="cv-btn-favorite <?php echo $is_fav ? 'cv-favorited' : ''; ?>"
                            data-music-id="<?php echo esc_attr( $music_id ); ?>"
                            title="Favoritar">
                        ❤ <span class="cv-fav-count" data-music-id="<?php echo esc_attr( $music_id ); ?>"><?php echo CV_Launch::fav_label( $favoritos ); ?></span>
                    </button>
                    <?php if ( $avg > 0 && CV_Launch::ratings_visible( CV_Ratings::get_count( $music_id ) ) ) : ?>
                        <span class="cv-card-stat">★ <?php echo number_format( $avg, 1 ); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

}

CV_Shortcodes::init();
