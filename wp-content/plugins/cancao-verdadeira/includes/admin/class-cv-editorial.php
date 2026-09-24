<?php
// includes/admin/class-cv-editorial.php
// Gerado em: 2026-06-29 10:00:00
// Módulo: Painel de Inteligência Editorial — Canção Verdadeira v2.23.0
// Exibe painel "Oportunidades": músicas com dados incompletos agrupadas por gap.
// Inspirado pela sugestão do Diretor de TI José Amado: "o administrador resolve tudo rapidamente".
// Campos auditados: letra, capa, artista, compositor, YouTube URL, SEO, sentimento.
// v2.26.0: removido o item "Sem Gênero" (o site é todo sertanejo).
// Acesso: apenas administradores. Visual executivo dark mode.
// Compatível com PHP 7.2+.

if ( ! defined( 'ABSPATH' ) ) exit;

class CV_Editorial {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'wp_ajax_cv_editorial_data', array( __CLASS__, 'ajax_data' ) );
    }

    public static function register_page() {
        add_submenu_page(
            null,
            'Inteligência Editorial',
            'Inteligência Editorial',
            'manage_options',
            'cv-editorial',
            array( __CLASS__, 'render' )
        );
    }

    // ── Coleta gaps por tipo ──────────────────────────────────────
    private static function get_gaps() {
        global $wpdb;

        $gaps = array();

        // Helper: busca músicas publicadas com meta ausente/vazia
        $base_args = array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        );

        // 1. Sem letra
        // A letra fica em post_content (não há meta de letra), então filtra em PHP.
        $ids = array_values( array_filter( get_posts( $base_args ), function( $id ) {
            return ! CV_Fields::has_letra( $id );
        } ) );
        $gaps['sem_letra'] = array(
            'label'   => 'Sem Letra',
            'icon'    => '📝',
            'color'   => '#e74c3c',
            'ids'     => $ids,
            'tip'     => 'A letra é o principal ativo de SEO — sem ela a música não ranqueia.',
            'priority'=> 1,
        );

        // 2. Sem capa (imagem destacada). v2.31.0: o filtro antigo usava o meta
        // _cv_capa_id, que nunca é gravado — agora olha direto a imagem destacada.
        $ids2 = get_posts( $base_args );
        $no_thumb = array();
        foreach ( $ids2 as $id ) {
            if ( ! has_post_thumbnail( $id ) ) $no_thumb[] = $id;
        }
        $gaps['sem_capa'] = array(
            'label'   => 'Sem Capa',
            'icon'    => '🖼️',
            'color'   => '#e67e22',
            'ids'     => $no_thumb,
            'tip'     => 'Capa melhora CTR nas redes sociais e aparência nos cards.',
            'priority'=> 2,
        );

        // 3. Sem URL do YouTube
        $args = $base_args;
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => CV_Fields::YOUTUBE_URL, 'compare' => 'NOT EXISTS' ),
            array( 'key' => CV_Fields::YOUTUBE_URL, 'value' => '', 'compare' => '=' ),
        );
        $ids = get_posts( $args );
        $gaps['sem_youtube'] = array(
            'label'   => 'Sem YouTube',
            'icon'    => '▶️',
            'color'   => '#c0392b',
            'ids'     => $ids,
            'tip'     => 'Sem link do YouTube o player não funciona — visitante vai embora sem ouvir.',
            'priority'=> 1,
        );

        // 4. Sem artista
        $args = $base_args;
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => CV_Fields::ARTISTA, 'compare' => 'NOT EXISTS' ),
            array( 'key' => CV_Fields::ARTISTA, 'value' => '', 'compare' => '=' ),
        );
        $ids = get_posts( $args );
        $gaps['sem_artista'] = array(
            'label'   => 'Sem Artista',
            'icon'    => '🎤',
            'color'   => '#9b59b6',
            'ids'     => $ids,
            'tip'     => 'Artista é usado no Schema.org e no SEO — impacta indexação.',
            'priority'=> 2,
        );

        // 5. Sem compositor
        $args = $base_args;
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => CV_Fields::COMPOSITOR, 'compare' => 'NOT EXISTS' ),
            array( 'key' => CV_Fields::COMPOSITOR, 'value' => '', 'compare' => '=' ),
        );
        $ids = get_posts( $args );
        $gaps['sem_compositor'] = array(
            'label'   => 'Sem Compositor',
            'icon'    => '✍️',
            'color'   => '#8e44ad',
            'ids'     => $ids,
            'tip'     => 'Diferencial do Canção Verdadeira — valorizar o compositor é o posicionamento do site.',
            'priority'=> 2,
        );

        // 6. Sem sentimento. v2.31.0: os sentimentos ficam na tabela
        // cv_musica_sentimentos (não num meta _cv_sentimentos, que nunca existiu);
        // antes TODAS as músicas contavam como "sem sentimento".
        $com_sent = $wpdb->get_col( "SELECT DISTINCT musica_id FROM {$wpdb->prefix}cv_musica_sentimentos" );
        $args     = $base_args;
        if ( $com_sent ) { $args['post__not_in'] = array_map( 'intval', $com_sent ); }
        $ids = get_posts( $args );
        $gaps['sem_sentimento'] = array(
            'label'   => 'Sem Sentimento',
            'icon'    => '🎭',
            'color'   => '#16a085',
            'ids'     => $ids,
            'tip'     => 'Sentimentos permitem filtros emocionais — recurso diferenciador da plataforma.',
            'priority'=> 3,
        );

        // 8. Sem descrição SEO (excerpt / _cv_descricao)
        $args = $base_args;
        $args['meta_query'] = array(
            'relation' => 'OR',
            array( 'key' => CV_Fields::DESCRICAO, 'compare' => 'NOT EXISTS' ),
            array( 'key' => CV_Fields::DESCRICAO, 'value' => '', 'compare' => '=' ),
        );
        $ids_meta = get_posts( $args );
        $ids_no_excerpt = array();
        foreach ( $ids_meta as $id ) {
            $p = get_post( $id );
            if ( empty( $p->post_excerpt ) ) $ids_no_excerpt[] = $id;
        }
        $gaps['sem_seo'] = array(
            'label'   => 'Sem Descrição SEO',
            'icon'    => '📡',
            'color'   => '#27ae60',
            'ids'     => $ids_no_excerpt,
            'tip'     => 'Descrição é usada no meta description — direto no CTR do Google.',
            'priority'=> 2,
        );

        return $gaps;
    }

    // ── Totais gerais ─────────────────────────────────────────────
    private static function get_totals() {
        $total = wp_count_posts( 'musica' );
        return array(
            'published' => (int) $total->publish,
            'draft'     => (int) $total->draft,
            'private'   => isset( $total->private ) ? (int) $total->private : 0,
        );
    }

    // ── AJAX ─────────────────────────────────────────────────────
    public static function ajax_data() {
        check_ajax_referer( 'cv_editorial_data', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $gaps   = self::get_gaps();
        $totals = self::get_totals();
        // Simplifica para JSON
        $out = array();
        foreach ( $gaps as $key => $g ) {
            $out[$key] = array(
                'label'    => $g['label'],
                'icon'     => $g['icon'],
                'color'    => $g['color'],
                'count'    => count( $g['ids'] ),
                'tip'      => $g['tip'],
                'priority' => $g['priority'],
            );
        }
        wp_send_json_success( array( 'gaps' => $out, 'totals' => $totals ) );
    }

    // ── Render ───────────────────────────────────────────────────
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado.' );

        $gaps   = self::get_gaps();
        $totals = self::get_totals();
        $nonce  = wp_create_nonce( 'cv_editorial_data' );

        // Score de completude: média ponderada
        $total_pub = $totals['published'];
        $score = 100;
        if ( $total_pub > 0 ) {
            $weights = array( 'sem_letra' => 30, 'sem_youtube' => 25, 'sem_capa' => 15, 'sem_artista' => 10, 'sem_compositor' => 10, 'sem_seo' => 5, 'sem_sentimento' => 2 );
            $deduct = 0;
            foreach ( $weights as $key => $w ) {
                if ( isset( $gaps[$key] ) ) {
                    $pct = count( $gaps[$key]['ids'] ) / $total_pub;
                    $deduct += $pct * $w;
                }
            }
            $score = max( 0, round( 100 - $deduct ) );
        }
        $score_color = $score >= 85 ? '#1DB954' : ( $score >= 60 ? '#e67e22' : '#e74c3c' );
        ?>
        <div class="wrap" style="background:#FFFFFF;min-height:100vh;padding:24px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">

        <!-- HEADER -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
            <div>
                <h1 style="color:#3B2418;font-size:22px;margin:0 0 4px 0;font-weight:700;">🧠 Inteligência Editorial</h1>
                <p style="color:#8A6A55;font-size:13px;margin:0;">Oportunidades de melhoria no catálogo · <?php echo $total_pub; ?> músicas publicadas</p>
            </div>
            <div style="display:flex;align-items:center;gap:16px;">
                <!-- Score geral -->
                <div style="text-align:center;">
                    <div style="font-size:36px;font-weight:800;color:<?php echo $score_color; ?>;"><?php echo $score; ?>%</div>
                    <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;">Completude</div>
                </div>
                <button id="cv-ed-refresh" style="background:#D4A01722;border:1px solid #C9A27E;color:#7B3A22;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">
                    🔄 Atualizar
                </button>
            </div>
        </div>

        <!-- KPI RESUMO -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px;margin-bottom:28px;">
            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#7B3A22;"><?php echo $total_pub; ?></div>
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-top:4px;">Publicadas</div>
            </div>
            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#AD5C14;"><?php echo $totals['draft']; ?></div>
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-top:4px;">Rascunhos</div>
            </div>
            <?php
            $total_issues = 0;
            foreach ( $gaps as $g ) $total_issues += count( $g['ids'] );
            $musicas_com_gap = array();
            foreach ( $gaps as $g ) $musicas_com_gap = array_merge( $musicas_com_gap, $g['ids'] );
            $musicas_com_gap = count( array_unique( $musicas_com_gap ) );
            ?>
            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#D62C1A;"><?php echo $musicas_com_gap; ?></div>
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-top:4px;">Têm algum gap</div>
            </div>
            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:10px;padding:16px;text-align:center;">
                <div style="font-size:28px;font-weight:800;color:#137B38;"><?php echo max(0, $total_pub - $musicas_com_gap); ?></div>
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-top:4px;">100% Completas</div>
            </div>
        </div>

        <!-- BARRA DE SCORE VISUAL -->
        <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;padding:20px;margin-bottom:24px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                <div style="color:#7B3A22;font-size:13px;font-weight:700;">📊 Score de Completude do Catálogo</div>
                <div style="color:<?php echo $score_color; ?>;font-size:15px;font-weight:800;"><?php echo $score; ?>%</div>
            </div>
            <div style="background:#FBF6EE;border-radius:20px;height:12px;overflow:clip;">
                <div style="height:100%;width:<?php echo $score; ?>%;background:<?php echo $score_color; ?>;border-radius:20px;transition:width 1s;"></div>
            </div>
            <div style="display:flex;justify-content:space-between;margin-top:6px;">
                <span style="font-size:11px;color:#8A6A55;">0%</span>
                <span style="font-size:11px;color:#8A6A55;">Alvo: 85%+</span>
                <span style="font-size:11px;color:#8A6A55;">100%</span>
            </div>
        </div>

        <!-- CARDS DE OPORTUNIDADE -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:16px;margin-bottom:24px;">
        <?php foreach ( $gaps as $key => $g ) :
            $count   = count( $g['ids'] );
            $pct     = $total_pub > 0 ? round( $count / $total_pub * 100 ) : 0;
            $urgency = $g['priority'] === 1 ? '🔴 Alta' : ( $g['priority'] === 2 ? '🟡 Média' : '🟢 Baixa' );
            $urg_c   = $g['priority'] === 1 ? '#e74c3c' : ( $g['priority'] === 2 ? '#e67e22' : '#1DB954' );
            if ( $count === 0 ) continue;
            ?>
        <div style="background:#F8F0E4;border:1px solid #EADBC6;border-left:4px solid <?php echo $g['color']; ?>;border-radius:12px;padding:20px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;">
                <div style="display:flex;align-items:center;gap:8px;">
                    <span style="font-size:22px;"><?php echo $g['icon']; ?></span>
                    <div style="color:#3B2418;font-size:14px;font-weight:700;"><?php echo esc_html( $g['label'] ); ?></div>
                </div>
                <span style="background:<?php echo $urg_c; ?>22;color:<?php echo $urg_c; ?>;border:1px solid <?php echo $urg_c; ?>44;border-radius:20px;padding:2px 8px;font-size:10px;font-weight:700;">
                    <?php echo $urgency; ?>
                </span>
            </div>

            <div style="display:flex;align-items:baseline;gap:8px;margin-bottom:8px;">
                <span style="font-size:38px;font-weight:800;color:<?php echo $g['color']; ?>;"><?php echo $count; ?></span>
                <span style="font-size:14px;color:#8A6A55;">músicas (<?php echo $pct; ?>%)</span>
            </div>

            <div style="background:#FBF6EE;border-radius:10px;height:6px;overflow:clip;margin-bottom:10px;">
                <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $g['color']; ?>;border-radius:10px;"></div>
            </div>

            <p style="color:#8A6A55;font-size:12px;margin:0 0 12px 0;line-height:1.5;"><?php echo esc_html( $g['tip'] ); ?></p>

            <?php if ( ! empty( $g['ids'] ) ) :
                $sample = array_slice( $g['ids'], 0, 3 );
                ?>
            <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px;">
                <?php foreach ( $sample as $sid ) :
                    $title = get_the_title( $sid );
                    $edit  = get_edit_post_link( $sid );
                    ?>
                <div style="display:flex;align-items:center;justify-content:space-between;background:#FFFFFF;border-radius:6px;padding:7px 10px;">
                    <span style="color:#6B4C3B;font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:200px;"><?php echo esc_html( $title ); ?></span>
                    <a href="<?php echo esc_url( $edit ); ?>" style="color:#7B3A22;font-size:11px;font-weight:700;text-decoration:none;white-space:nowrap;margin-left:8px;">Editar →</a>
                </div>
                <?php endforeach; ?>
                <?php if ( count( $g['ids'] ) > 3 ) : ?>
                <div style="color:#8A6A55;font-size:11px;text-align:center;padding:4px 0;">
                    + <?php echo count( $g['ids'] ) - 3; ?> outras músicas
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=musica' ) ); ?>"
               style="display:block;text-align:center;background:<?php echo $g['color']; ?>22;border:1px solid <?php echo $g['color']; ?>44;color:<?php echo $g['color']; ?>;border-radius:8px;padding:8px;font-size:12px;font-weight:700;text-decoration:none;">
                Ver todas as músicas →
            </a>
        </div>
        <?php endforeach; ?>

        <!-- Card "Tudo OK" se não há pendências -->
        <?php $all_clear = array_filter( $gaps, function( $g ) { return count( $g['ids'] ) > 0; } );
        if ( empty( $all_clear ) ) : ?>
        <div style="background:#1DB95411;border:1px solid #1DB95444;border-radius:12px;padding:40px;text-align:center;grid-column:1/-1;">
            <div style="font-size:48px;margin-bottom:12px;">🎉</div>
            <div style="color:#137B38;font-size:18px;font-weight:700;margin-bottom:8px;">Catálogo 100% Completo!</div>
            <div style="color:#8A6A55;font-size:13px;">Todas as músicas publicadas têm letra, capa, YouTube, artista e compositor preenchidos.</div>
        </div>
        <?php endif; ?>
        </div>

        <!-- CHECKLIST EDITORIAL -->
        <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="color:#7B3A22;font-size:14px;font-weight:700;margin-bottom:16px;">✅ Checklist Editorial — Campos Obrigatórios por Música</div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:10px;">
                <?php
                $checklist = array(
                    '📝 Letra completa'              => 'sem_letra',
                    '🖼️ Capa / thumbnail'            => 'sem_capa',
                    '▶️ URL do YouTube'              => 'sem_youtube',
                    '🎤 Nome do artista'             => 'sem_artista',
                    '✍️ Nome do compositor'          => 'sem_compositor',
                    '📡 Descrição SEO'               => 'sem_seo',
                    '🎭 Sentimento / emoção'         => 'sem_sentimento',
                );
                foreach ( $checklist as $label => $key ) :
                    $cnt  = isset( $gaps[$key] ) ? count( $gaps[$key]['ids'] ) : 0;
                    $ok   = $cnt === 0;
                    $col  = $ok ? '#1DB954' : '#e74c3c';
                    $icon = $ok ? '✅' : '❌';
                    ?>
                <div style="display:flex;align-items:center;gap:8px;background:#FFFFFF;border-radius:8px;padding:10px 12px;">
                    <span style="font-size:16px;"><?php echo $icon; ?></span>
                    <div>
                        <div style="color:#6B4C3B;font-size:12px;font-weight:600;"><?php echo esc_html( $label ); ?></div>
                        <div style="font-size:11px;color:<?php echo $col; ?>;"><?php echo $ok ? 'Todas completas' : $cnt . ' pendentes'; ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        </div><!-- .wrap -->

        <script>
        (function(){
            document.getElementById('cv-ed-refresh').addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.textContent = '⏳ Analisando...';
                setTimeout(function(){ window.location.reload(); }, 300);
            });
        })();
        </script>
        <?php
    }
}

CV_Editorial::init();
