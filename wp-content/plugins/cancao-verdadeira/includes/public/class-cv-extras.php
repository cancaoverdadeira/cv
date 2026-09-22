<?php
// cancao-verdadeira-plugin/includes/public/class-cv-extras.php
// Gerado em: 2026-06-13 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Sugestões de melhoria implementadas: shortcode [cv_recomendacoes] que
// usa CV_Advanced::get_recommendations() para exibir músicas personalizadas;
// shortcode [cv_ranking_periodo] com indicadores de tendência ↑ ↓ = para
// rankings diário, semanal e mensal; gráfico de plays dos últimos 14 dias
// injetado no dashboard admin via hook. Todos os dados já existem no banco —
// este arquivo apenas os expõe de forma acessível ao tema e ao admin.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Extras {

    public static function init() {
        add_shortcode( 'cv_recomendacoes',   array( __CLASS__, 'shortcode_recomendacoes' ) );
        add_shortcode( 'cv_ranking_periodo', array( __CLASS__, 'shortcode_ranking_periodo' ) );

        // Injeta gráfico de plays no dashboard admin
        add_action( 'cv_dashboard_after_kpis', array( __CLASS__, 'render_plays_chart' ) );
    }

    // ════════════════════════════════════════════════════════════════
    // 1. [cv_recomendacoes]
    // ════════════════════════════════════════════════════════════════
    //
    // Exibe músicas recomendadas com base no histórico e gênero favorito
    // do usuário logado. Para visitantes não logados, exibe o Top Geral.
    //
    // Parâmetros:
    //   limite  = quantidade de músicas (padrão: 6, máx: 12)
    //   titulo  = título da seção (padrão: "Recomendado para você")
    //   colunas = 2, 3 ou 4 (padrão: 3)
    //   fallback_titulo = título quando não logado (padrão: "Mais Populares")
    //
    // Exemplos:
    //   [cv_recomendacoes]
    //   [cv_recomendacoes limite="4" colunas="2"]
    //   [cv_recomendacoes titulo="Você pode gostar" fallback_titulo="Em Alta"]

    public static function shortcode_recomendacoes( $atts ) {
        $atts = shortcode_atts( array(
            'limite'          => 6,
            'titulo'          => 'Recomendado para você',
            'colunas'         => 3,
            'fallback_titulo' => 'Mais Populares',
        ), $atts, 'cv_recomendacoes' );

        $limite  = min( absint( $atts['limite'] ), 12 );
        $colunas = in_array( (int) $atts['colunas'], array( 2, 3, 4 ), true ) ? (int) $atts['colunas'] : 3;
        $titulo  = sanitize_text_field(
            is_user_logged_in() ? $atts['titulo'] : $atts['fallback_titulo']
        );

        if ( ! class_exists( 'CV_Advanced' ) ) {
            return '<p class="cv-sem-musicas">Módulo de recomendações não disponível.</p>';
        }

        $musicas = CV_Advanced::get_recommendations( get_current_user_id(), $limite );

        if ( empty( $musicas ) ) {
            return '<p class="cv-sem-musicas">Nenhuma recomendação disponível no momento.</p>';
        }

        ob_start();
        ?>
        <div class="cv-recomendacoes-wrap">

            <?php if ( $titulo ) : ?>
                <h2 class="cv-section-titulo">
                    <?php echo is_user_logged_in() ? '🎯 ' : '🔥 '; ?>
                    <?php echo esc_html( $titulo ); ?>
                </h2>
            <?php endif; ?>

            <div class="cv-grid cv-grid-col-<?php echo esc_attr( $colunas ); ?>">
                <?php foreach ( $musicas as $m ) :
                    // Suporte tanto a array (quando vem de get_recommendations)
                    // quanto a objeto (quando vem de get_top do CV_Ranking)
                    $id      = is_array( $m ) ? (int) $m['id']        : (int) $m->music_id;
                    $title   = is_array( $m ) ? $m['title']           : $m->post_title;
                    $url     = is_array( $m ) ? $m['url']             : $m->url;
                    $cover   = is_array( $m ) ? $m['cover']           : $m->cover;
                    $artista = is_array( $m ) ? $m['artista']         : ( $m->artista ?? '' );
                    $genero  = is_array( $m ) ? ( $m['genero'] ?? '' ): '';
                    $plays   = is_array( $m ) ? (int) ( $m['plays'] ?? 0 ) : (int) ( $m->plays_total ?? 0 );
                    $user_id = get_current_user_id();
                    $is_fav  = $user_id && class_exists( 'CV_Favorites' )
                               ? CV_Favorites::is_favorited( $id, $user_id ) : false;
                ?>
                <div class="cv-card" data-music-id="<?php echo esc_attr( $id ); ?>">
                    <a href="<?php echo esc_url( $url ); ?>" class="cv-card-capa-link">
                        <div class="cv-card-capa"
                             style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                            <div class="cv-card-play-overlay">
                                <span class="cv-card-play-icon">▶</span>
                            </div>
                        </div>
                    </a>
                    <div class="cv-card-info">
                        <a href="<?php echo esc_url( $url ); ?>" class="cv-card-titulo">
                            <?php echo esc_html( $title ); ?>
                        </a>
                        <?php if ( $artista ) : ?>
                            <p class="cv-card-artista"><?php echo esc_html( $artista ); ?></p>
                        <?php endif; ?>
                        <?php if ( $genero ) : ?>
                            <span class="cv-card-genero"><?php echo esc_html( $genero ); ?></span>
                        <?php endif; ?>
                        <div class="cv-card-acoes">
                            <span class="cv-card-stat">
                                ▶ <span class="cv-play-count"
                                        data-music-id="<?php echo esc_attr( $id ); ?>">
                                    <?php echo number_format( $plays ); ?>
                                </span>
                            </span>
                            <button
                                class="cv-btn-favorite <?php echo $is_fav ? 'cv-favorited' : ''; ?>"
                                data-music-id="<?php echo esc_attr( $id ); ?>"
                                aria-pressed="<?php echo $is_fav ? 'true' : 'false'; ?>"
                                title="Favoritar">❤</button>
                            <button class="cv-btn-add-playlist"
                                    data-music-id="<?php echo esc_attr( $id ); ?>"
                                    title="Adicionar à playlist">＋</button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </div>
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 2. [cv_ranking_periodo]
    // ════════════════════════════════════════════════════════════════
    //
    // Exibe ranking por período (diário, semanal ou mensal) com
    // indicadores visuais de tendência ↑↑ ↑ = ↓ ↓↓ ●
    //
    // Parâmetros:
    //   periodo = diario | semanal | mensal (padrão: semanal)
    //   limite  = número de músicas (padrão: 10, máx: 50)
    //   genero  = slug do gênero (padrão: todos)
    //   titulo  = título da seção (padrão: automático por período)
    //   layout  = lista | cards (padrão: lista)
    //
    // Exemplos:
    //   [cv_ranking_periodo]
    //   [cv_ranking_periodo periodo="diario" titulo="🔥 Mais Tocadas Hoje"]
    //   [cv_ranking_periodo periodo="mensal" limite="5" layout="cards"]
    //   [cv_ranking_periodo genero="sertanejo-universitario" periodo="semanal"]

    public static function shortcode_ranking_periodo( $atts ) {
        $atts = shortcode_atts( array(
            'periodo' => 'semanal',
            'limite'  => 10,
            'genero'  => '',
            'titulo'  => '',
            'layout'  => 'lista',
        ), $atts, 'cv_ranking_periodo' );

        $periodo = sanitize_text_field( $atts['periodo'] );
        $limite  = min( absint( $atts['limite'] ), 50 );
        $genero  = sanitize_text_field( $atts['genero'] );
        $layout  = in_array( $atts['layout'], array( 'lista', 'cards' ), true ) ? $atts['layout'] : 'lista';

        // Mapeia alias em português para os valores internos
        $mapa_periodo = array(
            'diario'   => 'daily',
            'semanal'  => 'weekly',
            'mensal'   => 'monthly',
            'daily'    => 'daily',
            'weekly'   => 'weekly',
            'monthly'  => 'monthly',
        );
        $periodo_interno = $mapa_periodo[ $periodo ] ?? 'weekly';

        // Título automático por período
        $titulos_padrao = array(
            'daily'   => '🔥 Mais Tocadas Hoje',
            'weekly'  => '📈 Top da Semana',
            'monthly' => '🏆 Melhores do Mês',
        );
        $titulo = sanitize_text_field( $atts['titulo'] )
                  ?: $titulos_padrao[ $periodo_interno ];

        if ( ! class_exists( 'CV_Advanced' ) ) {
            return '<p class="cv-sem-musicas">Módulo de ranking por período não disponível.</p>';
        }

        $musicas = CV_Advanced::get_ranking_period( $periodo_interno, $limite, $genero );

        if ( empty( $musicas ) ) {
            return '<p class="cv-sem-musicas">Nenhuma música no ranking deste período ainda.</p>';
        }

        ob_start();
        ?>
        <div class="cv-ranking-wrap cv-ranking-periodo cv-ranking-<?php echo esc_attr( $layout ); ?>">

            <h2 class="cv-section-titulo"><?php echo esc_html( $titulo ); ?></h2>

            <?php if ( 'cards' === $layout ) : ?>

                <div class="cv-grid cv-grid-col-3">
                    <?php foreach ( $musicas as $i => $m ) :
                        $id     = is_array( $m ) ? (int) $m['music_id']    : (int) $m->music_id;
                        $title  = is_array( $m ) ? $m['post_title']        : $m->post_title;
                        $url    = is_array( $m ) ? $m['url']               : $m->url;
                        $cover  = is_array( $m ) ? $m['cover']             : $m->cover;
                        $artista= is_array( $m ) ? ( $m['artista'] ?? '' ) : ( $m->artista ?? '' );
                        $plays  = is_array( $m ) ? (int) ( $m['plays_period'] ?? 0 ) : (int) ( $m->plays_period ?? 0 );
                        $trend  = is_array( $m ) ? ( $m['trend'] ?? '●' )  : ( $m->trend ?? '●' );
                        $tclass = is_array( $m ) ? ( $m['trend_class'] ?? '' ) : ( $m->trend_class ?? '' );
                        $tlabel = is_array( $m ) ? ( $m['trend_label'] ?? '' ) : ( $m->trend_label ?? '' );
                    ?>
                    <div class="cv-card" data-music-id="<?php echo esc_attr( $id ); ?>">
                        <a href="<?php echo esc_url( $url ); ?>" class="cv-card-capa-link">
                            <div class="cv-card-capa"
                                 style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                                <span class="cv-card-posicao">#<?php echo $i + 1; ?></span>
                                <span class="cv-trend-badge <?php echo esc_attr( $tclass ); ?>"
                                      title="<?php echo esc_attr( $tlabel ); ?>">
                                    <?php echo esc_html( $trend ); ?>
                                </span>
                                <div class="cv-card-play-overlay">
                                    <span class="cv-card-play-icon">▶</span>
                                </div>
                            </div>
                        </a>
                        <div class="cv-card-info">
                            <a href="<?php echo esc_url( $url ); ?>" class="cv-card-titulo">
                                <?php echo esc_html( $title ); ?>
                            </a>
                            <?php if ( $artista ) : ?>
                                <p class="cv-card-artista"><?php echo esc_html( $artista ); ?></p>
                            <?php endif; ?>
                            <div class="cv-card-acoes">
                                <span class="cv-card-stat">▶ <?php echo number_format( $plays ); ?> no período</span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

            <?php else : ?>

                <ol class="cv-ranking-lista">
                    <?php foreach ( $musicas as $i => $m ) :
                        $id      = is_array( $m ) ? (int) $m['music_id']       : (int) $m->music_id;
                        $title   = is_array( $m ) ? $m['post_title']           : $m->post_title;
                        $url     = is_array( $m ) ? $m['url']                  : $m->url;
                        $cover   = is_array( $m ) ? $m['cover']                : $m->cover;
                        $artista = is_array( $m ) ? ( $m['artista'] ?? '' )    : ( $m->artista ?? '' );
                        $plays   = is_array( $m ) ? (int) ( $m['plays_period'] ?? 0 ) : (int) ( $m->plays_period ?? 0 );
                        $trend   = is_array( $m ) ? ( $m['trend'] ?? '●' )    : ( $m->trend ?? '●' );
                        $tclass  = is_array( $m ) ? ( $m['trend_class'] ?? '' ) : ( $m->trend_class ?? '' );
                        $tlabel  = is_array( $m ) ? ( $m['trend_label'] ?? '' ) : ( $m->trend_label ?? '' );
                        $pos     = $i + 1;

                        $medalha = $pos === 1 ? '🥇' : ( $pos === 2 ? '🥈' : ( $pos === 3 ? '🥉' : '' ) );
                    ?>
                    <li class="cv-ranking-item <?php echo $pos <= 3 ? 'cv-ranking-top3' : ''; ?>"
                        data-music-id="<?php echo esc_attr( $id ); ?>">

                        <span class="cv-ranking-pos">
                            <?php echo $medalha ?: '#' . esc_html( $pos ); ?>
                        </span>

                        <!-- Indicador de tendência -->
                        <span class="cv-trend-indicator <?php echo esc_attr( $tclass ); ?>"
                              title="<?php echo esc_attr( $tlabel ); ?>">
                            <?php echo esc_html( $trend ); ?>
                        </span>

                        <div class="cv-ranking-capa"
                             style="background-image:url('<?php echo esc_url( $cover ); ?>')">
                        </div>

                        <div class="cv-ranking-dados">
                            <a href="<?php echo esc_url( $url ); ?>" class="cv-ranking-titulo">
                                <?php echo esc_html( $title ); ?>
                            </a>
                            <?php if ( $artista ) : ?>
                                <span class="cv-ranking-artista"><?php echo esc_html( $artista ); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="cv-ranking-stats">
                            <span title="Plays no período">▶ <?php echo number_format( $plays ); ?></span>
                        </div>

                    </li>
                    <?php endforeach; ?>
                </ol>

            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    // ════════════════════════════════════════════════════════════════
    // 3. GRÁFICO DE PLAYS NO DASHBOARD ADMIN
    // ════════════════════════════════════════════════════════════════

    /**
     * Renderiza o gráfico de plays dos últimos 14 dias no dashboard admin.
     * Chamado via hook cv_dashboard_after_kpis.
     * O hook deve ser adicionado no template do dashboard com do_action().
     */
    public static function render_plays_chart() {
        global $wpdb;

        $rows = $wpdb->get_results(
            "SELECT DATE(played_at) AS dia, COUNT(*) AS total
             FROM {$wpdb->prefix}cv_plays_log
             WHERE played_at >= DATE_SUB(NOW(), INTERVAL 14 DAY)
             GROUP BY dia
             ORDER BY dia ASC"
        );

        // Gera array completo dos 14 dias (incluindo dias sem plays)
        $labels = array();
        $data   = array();
        $map    = array();
        foreach ( $rows as $r ) {
            $map[ $r->dia ] = (int) $r->total;
        }

        for ( $i = 13; $i >= 0; $i-- ) {
            $date     = date( 'Y-m-d', strtotime( "-{$i} days" ) );
            $label    = date( 'd/m', strtotime( $date ) );
            $labels[] = $label;
            $data[]   = $map[ $date ] ?? 0;
        }

        $total_periodo = array_sum( $data );
        $pico          = max( $data ) ?: 1;
        ?>
        <div class="cv-section" style="margin-top:0">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;flex-wrap:wrap;gap:8px">
                <h2 style="font-size:16px;font-weight:700;color:#F5F0E0;margin:0">
                    📊 Plays — últimos 14 dias
                </h2>
                <span style="font-size:13px;color:#888">
                    Total: <strong style="color:#D4A017"><?php echo number_format( $total_periodo ); ?></strong> plays
                </span>
            </div>

            <!-- Gráfico de barras em SVG puro — sem dependências -->
            <div style="width:100%;overflow-x:auto">
                <svg viewBox="0 0 <?php echo 14 * 48; ?> 160"
                     style="width:100%;min-width:480px;display:block"
                     role="img" aria-label="Gráfico de plays dos últimos 14 dias">
                    <?php foreach ( $data as $i => $val ) :
                        $x       = $i * 48 + 4;
                        $barH    = $pico > 0 ? (int) round( ( $val / $pico ) * 100 ) : 0;
                        $barY    = 110 - $barH;
                        $isToday = ( $i === 13 );
                        $color   = $isToday ? '#FFD700' : '#D4A017';
                        $opacity = $val > 0 ? '1' : '0.3';
                        $label   = $labels[ $i ];
                    ?>
                        <!-- Barra -->
                        <rect x="<?php echo $x + 4; ?>"
                              y="<?php echo $barH > 0 ? $barY : 109; ?>"
                              width="32"
                              height="<?php echo max( $barH, 1 ); ?>"
                              rx="3"
                              fill="<?php echo $color; ?>"
                              opacity="<?php echo $opacity; ?>">
                            <title><?php echo esc_html( $label ); ?>: <?php echo number_format( $val ); ?> play<?php echo $val !== 1 ? 's' : ''; ?></title>
                        </rect>

                        <!-- Valor acima da barra (só se > 0) -->
                        <?php if ( $val > 0 ) : ?>
                        <text x="<?php echo $x + 20; ?>"
                              y="<?php echo max( $barY - 4, 12 ); ?>"
                              text-anchor="middle"
                              font-size="9"
                              fill="#888">
                            <?php echo $val >= 1000 ? round( $val / 1000, 1 ) . 'k' : $val; ?>
                        </text>
                        <?php endif; ?>

                        <!-- Label de data -->
                        <text x="<?php echo $x + 20; ?>"
                              y="130"
                              text-anchor="middle"
                              font-size="9"
                              fill="<?php echo $isToday ? '#D4A017' : '#555'; ?>"
                              font-weight="<?php echo $isToday ? '700' : '400'; ?>">
                            <?php echo esc_html( $label ); ?>
                        </text>

                    <?php endforeach; ?>

                    <!-- Linha de base -->
                    <line x1="0" y1="111" x2="<?php echo 14 * 48; ?>" y2="111"
                          stroke="#2a2a2a" stroke-width="1"/>
                </svg>
            </div>

            <!-- Legenda -->
            <div style="display:flex;gap:16px;margin-top:8px;font-size:11px;color:#555">
                <span>
                    <span style="display:inline-block;width:10px;height:10px;background:#FFD700;border-radius:2px;margin-right:4px;vertical-align:middle"></span>
                    Hoje
                </span>
                <span>
                    <span style="display:inline-block;width:10px;height:10px;background:#D4A017;border-radius:2px;margin-right:4px;vertical-align:middle"></span>
                    Dias anteriores
                </span>
                <span style="margin-left:auto">
                    Pico: <strong style="color:#D4A017"><?php echo number_format( $pico ); ?></strong> plays
                </span>
            </div>
        </div>
        <?php
    }

}

CV_Extras::init();
