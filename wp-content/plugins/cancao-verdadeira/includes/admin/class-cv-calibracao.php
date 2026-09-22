<?php
// cancao-verdadeira/includes/admin/class-cv-calibracao.php
// Projeto : Canção Verdadeira — Plataforma de letras musicais sertanejas
// Módulo  : Calibração de Métricas (v2.15.0) — uso EXCLUSIVO admin principal
// Regras  : Limite de 10 usos totais (ampliável APENAS por solicitação direta à IA)
//           Restrito ao user_id == CV_CALIBRACAO_ADMIN_ID (definido abaixo)
//           Cada uso é logado com timestamp, IP e músicas afetadas
//           NUNCA visível ou acessível para outros usuários
// Autor   : Canção Verdadeira | Gerado: 2026-06-26

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Calibracao {

    // ── ID do único admin autorizado ─────────────────────────────
    // Altere para o ID real do seu usuário WordPress (admin principal)
    const ADMIN_ID    = 3;

    // ── Limite máximo de usos — NÃO altere sem solicitação à IA ──
    const LIMITE_USOS = 10;

    // ── Chave de opção que armazena o contador ───────────────────
    const OPT_CONTADOR = 'cv_calibracao_usos';

    public static function init() {
        add_action( 'admin_menu',                       [ __CLASS__, 'admin_menu' ] );
        add_action( 'admin_post_cv_executar_calibracao',[ __CLASS__, 'handle_execucao' ] );
        add_action( 'wp_ajax_cv_calibrar_musica',         [ __CLASS__, 'ajax_calibrar_musica' ] );
        add_action( 'admin_enqueue_scripts',            [ __CLASS__, 'enqueue' ] );
    }

    // ─────────────────────────────────────────────────────────────
    // Segurança: verifica se o usuário atual é o admin autorizado
    // ─────────────────────────────────────────────────────────────
    private static function is_authorized() {
        return is_user_logged_in() && (int) get_current_user_id() === self::ADMIN_ID;
    }

    public static function admin_menu() {
        if ( ! self::is_authorized() ) { return; }
        add_submenu_page(
            'cv-dashboard',
            'Calibração de Métricas',
            '⚙️ Calibração',
            'manage_options',
            'cv-calibracao',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function enqueue( $hook ) {
        if ( strpos( $hook, 'cv-calibracao' ) === false ) { return; }
        if ( ! self::is_authorized() ) { return; }
    }

    // ─────────────────────────────────────────────────────────────
    // Página admin
    // ─────────────────────────────────────────────────────────────
    public static function render_page() {
        if ( ! self::is_authorized() ) { wp_die( 'Acesso restrito.' ); }

        $usos_realizados = (int) get_option( self::OPT_CONTADOR, 0 );
        $usos_restantes  = max( 0, self::LIMITE_USOS - $usos_realizados );
        $logs            = self::get_logs( 10 );
        $pct_usos        = round( $usos_realizados / self::LIMITE_USOS * 100 );
        $cor_status      = $usos_restantes <= 2 ? '#e74c3c' : ( $usos_restantes <= 5 ? '#D4A017' : '#1DB954' );
        $nonce           = wp_create_nonce( 'cv_admin_nonce' );
        $ajax            = admin_url( 'admin-ajax.php' );

        // Buscar músicas publicadas com métricas atuais
        $musicas_raw = get_posts( array(
            'post_type'      => 'musica',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ) );

        $musicas = array();
        foreach ( $musicas_raw as $id ) {
            $plays = (int) get_post_meta( $id, '_cv_plays', true );
            $favs  = (int) get_post_meta( $id, '_cv_favoritos', true );
            $rating= round( (float) get_post_meta( $id, '_cv_avg_rating', true ), 1 );
            $musicas[] = array(
                'id'     => $id,
                'titulo' => get_the_title( $id ),
                'capa'   => get_the_post_thumbnail_url( $id, 'thumbnail' ) ?: CV_PLUGIN_URL . 'assets/img/default-cover.svg',
                'plays'  => $plays,
                'favs'   => $favs,
                'rating' => $rating,
            );
        }
        $total_musicas = count( $musicas );
        ?>
        <div class="wrap" id="cv-cal-exec">
        <style>
        body.wp-admin { background:#0f0f1a !important; }
        #wpwrap,#wpcontent,#wpbody,#wpbody-content { background:#0f0f1a !important; }
        #cv-cal-exec {
            --gold:#D4A017; --bg:#0f0f1a; --card:#1a1a2e; --bord:#2a2a4a;
            --text:#e0e0e0; --muted:#888; --green:#1DB954; --red:#e74c3c;
            color:var(--text); font-family:'Segoe UI',system-ui,sans-serif; padding-bottom:60px;
        }
        #cv-cal-exec * { box-sizing:border-box; }

        .cv-cal-topbar { display:flex; align-items:center; justify-content:space-between; margin-bottom:20px; flex-wrap:wrap; gap:12px; }
        .cv-cal-title { font-size:22px; font-weight:700; color:#fff; margin:0; }
        .cv-cal-title span { color:var(--gold); }
        .cv-cal-badge { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border-radius:20px; font-size:11px; font-weight:700; background:rgba(212,160,23,.1); border:1px solid rgba(212,160,23,.3); color:var(--gold); }

        /* Layout */
        .cv-cal-layout { display:grid; grid-template-columns:260px 1fr; gap:20px; align-items:start; }
        @media (max-width:960px) { .cv-cal-layout { grid-template-columns:1fr; } }

        /* Cards */
        .cv-cal-card { background:var(--card); border:1px solid var(--bord); border-radius:14px; overflow:hidden; margin-bottom:16px; }
        .cv-cal-card:last-child { margin-bottom:0; }
        .cv-cal-hdr { padding:13px 18px; border-bottom:1px solid var(--bord); display:flex; align-items:center; gap:10px; }
        .cv-cal-hdr-icon { font-size:18px; }
        .cv-cal-hdr-title { font-size:13px; font-weight:700; color:#fff; }
        .cv-cal-body { padding:18px; }

        /* Anel de créditos */
        .cv-cal-ring-wrap { text-align:center; padding:20px 18px 14px; }
        .cv-cal-ring {
            width:100px; height:100px; border-radius:50%; margin:0 auto 12px;
            background: conic-gradient(<?php echo esc_attr($cor_status); ?> <?php echo $pct_usos; ?>%, #111 <?php echo $pct_usos; ?>%);
            display:flex; align-items:center; justify-content:center; position:relative;
        }
        .cv-cal-ring::before { content:''; position:absolute; inset:10px; border-radius:50%; background:var(--card); }
        .cv-cal-ring-inner { position:relative; z-index:1; text-align:center; }
        .cv-cal-ring-num { font-size:26px; font-weight:800; color:<?php echo esc_attr($cor_status); ?>; line-height:1; }
        .cv-cal-ring-sub { font-size:9px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; }
        .cv-cal-ring-label { font-size:12px; color:var(--muted); }

        /* Lista de músicas */
        .cv-cal-musica-item {
            display:flex; align-items:center; gap:10px; padding:10px 12px;
            border-radius:8px; cursor:pointer; transition:background .15s;
            border:1px solid transparent; margin-bottom:4px;
        }
        .cv-cal-musica-item:hover { background:rgba(255,255,255,.04); }
        .cv-cal-musica-item.ativa { background:rgba(212,160,23,.08); border-color:rgba(212,160,23,.3); }
        .cv-cal-capa { width:36px; height:36px; border-radius:5px; object-fit:cover; flex-shrink:0; }
        .cv-cal-mus-info { flex:1; min-width:0; }
        .cv-cal-mus-titulo { font-size:12px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; color:var(--text); }
        .cv-cal-mus-stats { font-size:10px; color:var(--muted); margin-top:2px; }
        .cv-cal-mus-stats.tem-dados { color:var(--green); }
        .cv-cal-musica-search { width:100%; background:rgba(255,255,255,.04); border:1px solid var(--bord); border-radius:8px; color:var(--text); padding:8px 12px; font-size:12px; outline:none; font-family:inherit; margin-bottom:10px; }
        .cv-cal-musica-search:focus { border-color:var(--gold); }
        .cv-cal-lista-scroll { max-height:420px; overflow-y:auto; }
        .cv-cal-lista-scroll::-webkit-scrollbar { width:4px; }
        .cv-cal-lista-scroll::-webkit-scrollbar-thumb { background:var(--bord); border-radius:2px; }

        /* Painel de edição */
        .cv-cal-editor { display:none; }
        .cv-cal-editor.visivel { display:block; }
        .cv-cal-placeholder { text-align:center; padding:60px 20px; color:var(--muted); }
        .cv-cal-placeholder-emoji { font-size:48px; margin-bottom:12px; }

        /* Preview da música selecionada */
        .cv-cal-preview { display:flex; gap:14px; align-items:center; background:rgba(255,255,255,.03); border-radius:10px; padding:14px; margin-bottom:20px; }
        .cv-cal-preview-capa { width:56px; height:56px; border-radius:8px; object-fit:cover; }
        .cv-cal-preview-titulo { font-size:16px; font-weight:700; color:#fff; }
        .cv-cal-preview-sub { font-size:12px; color:var(--muted); margin-top:3px; }

        /* Métricas atuais */
        .cv-cal-metricas-atuais { display:grid; grid-template-columns:1fr 1fr 1fr; gap:10px; margin-bottom:20px; }
        .cv-cal-metrica-atual { background:rgba(255,255,255,.03); border:1px solid var(--bord); border-radius:8px; padding:12px; text-align:center; }
        .cv-cal-metrica-val { font-size:20px; font-weight:800; color:var(--gold); }
        .cv-cal-metrica-label { font-size:10px; color:var(--muted); text-transform:uppercase; letter-spacing:.3px; margin-top:3px; }

        /* Controles de incremento */
        .cv-cal-incrementos { display:flex; flex-direction:column; gap:14px; margin-bottom:20px; }
        .cv-cal-inc-row { background:rgba(255,255,255,.03); border:1px solid var(--bord); border-radius:10px; padding:14px 16px; }
        .cv-cal-inc-label { font-size:11px; color:var(--muted); text-transform:uppercase; letter-spacing:.4px; margin-bottom:10px; display:flex; align-items:center; justify-content:space-between; }
        .cv-cal-inc-label strong { color:var(--text); font-size:13px; text-transform:none; letter-spacing:0; }
        .cv-cal-inc-controls { display:flex; align-items:center; gap:10px; }
        .cv-cal-inc-btn { width:32px; height:32px; border-radius:6px; border:1px solid var(--bord); background:rgba(255,255,255,.06); color:var(--text); font-size:16px; font-weight:700; cursor:pointer; display:flex; align-items:center; justify-content:center; transition:all .15s; font-family:inherit; line-height:1; }
        .cv-cal-inc-btn:hover { border-color:var(--gold); color:var(--gold); }
        .cv-cal-inc-input { flex:1; text-align:center; background:rgba(255,255,255,.06); border:1px solid var(--bord); border-radius:8px; color:#fff; font-size:18px; font-weight:700; padding:6px 0; outline:none; font-family:inherit; }
        .cv-cal-inc-input:focus { border-color:var(--gold); }
        .cv-cal-inc-result { font-size:11px; color:var(--muted); margin-top:6px; text-align:center; }
        .cv-cal-inc-result span { color:var(--green); font-weight:600; }

        /* Rating especial */
        .cv-cal-rating-stars { display:flex; gap:6px; justify-content:center; margin:8px 0; }
        .cv-cal-star { font-size:24px; cursor:pointer; opacity:.3; transition:opacity .15s, transform .1s; }
        .cv-cal-star.ativo { opacity:1; }
        .cv-cal-star:hover { transform:scale(1.15); }

        /* Botão aplicar */
        .cv-cal-btn-aplicar { width:100%; background:var(--green); color:#fff; border:none; padding:13px; border-radius:10px; font-size:14px; font-weight:700; cursor:pointer; font-family:inherit; transition:opacity .2s; display:flex; align-items:center; justify-content:center; gap:8px; }
        .cv-cal-btn-aplicar:hover { opacity:.85; }
        .cv-cal-btn-aplicar:disabled { background:#2a2a2a; color:#555; cursor:not-allowed; }

        /* Toast */
        .cv-cal-toast { position:fixed; bottom:24px; right:24px; padding:12px 20px; border-radius:10px; font-size:13px; font-weight:600; box-shadow:0 4px 20px rgba(0,0,0,.5); z-index:9999; transform:translateY(80px); opacity:0; transition:all .3s; max-width:320px; }
        .cv-cal-toast.show { transform:translateY(0); opacity:1; }
        .cv-cal-toast.ok  { background:var(--green); color:#fff; }
        .cv-cal-toast.err { background:var(--red); color:#fff; }

        /* Log */
        .cv-cal-log-item { display:flex; gap:12px; padding:10px 0; border-bottom:1px solid var(--bord); }
        .cv-cal-log-item:last-child { border-bottom:none; }
        .cv-cal-log-dot { width:28px; height:28px; border-radius:50%; background:rgba(212,160,23,.12); border:1px solid rgba(212,160,23,.3); display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700; color:var(--gold); flex-shrink:0; }
        .cv-cal-log-info { flex:1; }
        .cv-cal-log-titulo { font-size:12px; font-weight:600; color:var(--text); }
        .cv-cal-log-meta { font-size:10px; color:var(--muted); margin-top:3px; }

        /* Limite atingido */
        .cv-cal-limite { text-align:center; padding:30px; }
        .cv-cal-limite-icon { font-size:40px; margin-bottom:10px; }
        .cv-cal-limite-title { font-size:15px; font-weight:700; color:var(--red); margin-bottom:6px; }
        .cv-cal-limite-desc { font-size:12px; color:var(--muted); line-height:1.5; }
        </style>

        <div class="cv-cal-topbar">
            <h1 class="cv-cal-title">⚖️ <span>Calibração</span> de Métricas</h1>
            <span class="cv-cal-badge">🔒 Exclusivo — Admin Principal</span>
        </div>
        <?php echo CV_Admin::btn_voltar(); ?>

        <div class="cv-cal-layout">

            <!-- SIDEBAR: créditos + lista de músicas -->
            <div>
                <!-- Créditos -->
                <div class="cv-cal-card">
                    <div class="cv-cal-hdr">
                        <span class="cv-cal-hdr-icon">⚡</span>
                        <div class="cv-cal-hdr-title">Créditos Disponíveis</div>
                    </div>
                    <div class="cv-cal-ring-wrap">
                        <div class="cv-cal-ring">
                            <div class="cv-cal-ring-inner">
                                <div class="cv-cal-ring-num"><?php echo $usos_restantes; ?></div>
                                <div class="cv-cal-ring-sub">restantes</div>
                            </div>
                        </div>
                        <div class="cv-cal-ring-label"><?php echo $usos_realizados; ?> de <?php echo self::LIMITE_USOS; ?> usos</div>
                        <?php if ($usos_restantes <= 3 && $usos_restantes > 0): ?>
                        <div style="margin-top:8px;font-size:11px;color:var(--red);font-weight:600">⚠️ Poucos créditos restantes</div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Lista de músicas -->
                <div class="cv-cal-card">
                    <div class="cv-cal-hdr">
                        <span class="cv-cal-hdr-icon">🎵</span>
                        <div class="cv-cal-hdr-title"><?php echo $total_musicas; ?> Músicas</div>
                    </div>
                    <div class="cv-cal-body">
                        <input type="text" class="cv-cal-musica-search" id="cv-cal-search"
                               placeholder="Buscar música..." oninput="cvCalFiltrar(this.value)">
                        <div class="cv-cal-lista-scroll" id="cv-cal-lista">
                        <?php foreach ($musicas as $m): ?>
                        <div class="cv-cal-musica-item" id="cv-cal-li-<?php echo $m['id']; ?>"
                             onclick="cvCalSelecionar(<?php echo $m['id']; ?>)"
                             data-titulo="<?php echo esc_attr(strtolower($m['titulo'])); ?>">
                            <img src="<?php echo esc_url($m['capa']); ?>" class="cv-cal-capa" alt="">
                            <div class="cv-cal-mus-info">
                                <div class="cv-cal-mus-titulo"><?php echo esc_html($m['titulo']); ?></div>
                                <div class="cv-cal-mus-stats <?php echo ($m['plays'] > 0 || $m['favs'] > 0) ? 'tem-dados' : ''; ?>">
                                    ▶<?php echo $m['plays']; ?>
                                    &nbsp;❤<?php echo $m['favs']; ?>
                                    &nbsp;★<?php echo $m['rating'] ?: '—'; ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ÁREA PRINCIPAL: editor -->
            <div>
                <?php if ($usos_restantes <= 0): ?>
                <div class="cv-cal-card">
                    <div class="cv-cal-body">
                        <div class="cv-cal-limite">
                            <div class="cv-cal-limite-icon">🔒</div>
                            <div class="cv-cal-limite-title">Limite Atingido</div>
                            <div class="cv-cal-limite-desc">Todos os <?php echo self::LIMITE_USOS; ?> créditos foram usados.<br>Solicite aumento ao assistente IA na próxima sessão.</div>
                        </div>
                    </div>
                </div>
                <?php else: ?>

                <div class="cv-cal-card">
                    <div class="cv-cal-hdr">
                        <span class="cv-cal-hdr-icon">🎛️</span>
                        <div class="cv-cal-hdr-title">Editor de Métricas</div>
                        <div style="margin-left:auto;font-size:11px;color:var(--muted)">Cada aplicação usa 1 crédito</div>
                    </div>
                    <div class="cv-cal-body">

                        <!-- Placeholder -->
                        <div class="cv-cal-placeholder" id="cv-cal-placeholder">
                            <div class="cv-cal-placeholder-emoji">👈</div>
                            <div style="font-size:14px;color:#666">Selecione uma música na lista ao lado</div>
                            <div style="font-size:12px;color:#444;margin-top:6px">Você poderá ajustar plays, favoritos e avaliação individualmente</div>
                        </div>

                        <!-- Editor (oculto até selecionar) -->
                        <div class="cv-cal-editor" id="cv-cal-editor">

                            <!-- Preview -->
                            <div class="cv-cal-preview">
                                <img src="" alt="" class="cv-cal-preview-capa" id="cv-cal-prev-capa">
                                <div>
                                    <div class="cv-cal-preview-titulo" id="cv-cal-prev-titulo"></div>
                                    <div class="cv-cal-preview-sub" id="cv-cal-prev-sub">Métricas atuais abaixo</div>
                                </div>
                            </div>

                            <!-- Métricas atuais -->
                            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:8px">Valores atuais</div>
                            <div class="cv-cal-metricas-atuais">
                                <div class="cv-cal-metrica-atual">
                                    <div class="cv-cal-metrica-val" id="cv-cal-cur-plays">0</div>
                                    <div class="cv-cal-metrica-label">▶ Plays</div>
                                </div>
                                <div class="cv-cal-metrica-atual">
                                    <div class="cv-cal-metrica-val" id="cv-cal-cur-favs">0</div>
                                    <div class="cv-cal-metrica-label">❤ Favoritos</div>
                                </div>
                                <div class="cv-cal-metrica-atual">
                                    <div class="cv-cal-metrica-val" id="cv-cal-cur-rating">—</div>
                                    <div class="cv-cal-metrica-label">★ Avaliação</div>
                                </div>
                            </div>

                            <!-- Controles de incremento -->
                            <div style="font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;margin-bottom:10px">Adicionar</div>
                            <div class="cv-cal-incrementos">

                                <!-- Plays -->
                                <div class="cv-cal-inc-row">
                                    <div class="cv-cal-inc-label">
                                        ▶ Plays a adicionar
                                        <strong id="cv-cal-res-plays">Total: <span id="cv-cal-total-plays">0</span></strong>
                                    </div>
                                    <div class="cv-cal-inc-controls">
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('plays',-10)">−10</button>
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('plays',-1)">−</button>
                                        <input type="number" class="cv-cal-inc-input" id="cv-cal-inc-plays"
                                               value="0" min="0" max="9999"
                                               oninput="cvCalAtualizar('plays')">
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('plays',1)">+</button>
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('plays',10)">+10</button>
                                    </div>
                                    <div class="cv-cal-inc-result">
                                        Novo total: <span id="cv-cal-novo-plays">—</span>
                                    </div>
                                </div>

                                <!-- Favoritos -->
                                <div class="cv-cal-inc-row">
                                    <div class="cv-cal-inc-label">
                                        ❤ Favoritos a adicionar
                                        <strong id="cv-cal-res-favs">Total: <span id="cv-cal-total-favs">0</span></strong>
                                    </div>
                                    <div class="cv-cal-inc-controls">
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('favs',-5)">−5</button>
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('favs',-1)">−</button>
                                        <input type="number" class="cv-cal-inc-input" id="cv-cal-inc-favs"
                                               value="0" min="0" max="9999"
                                               oninput="cvCalAtualizar('favs')">
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('favs',1)">+</button>
                                        <button class="cv-cal-inc-btn" onclick="cvCalAjustar('favs',5)">+5</button>
                                    </div>
                                    <div class="cv-cal-inc-result">
                                        Novo total: <span id="cv-cal-novo-favs">—</span>
                                    </div>
                                </div>

                                <!-- Avaliação -->
                                <div class="cv-cal-inc-row">
                                    <div class="cv-cal-inc-label">
                                        ★ Nova avaliação (estrelas)
                                        <strong><span id="cv-cal-rating-val">0</span> estrelas</strong>
                                    </div>
                                    <div class="cv-cal-rating-stars" id="cv-cal-stars">
                                        <span class="cv-cal-star" data-val="1" onclick="cvCalSetRating(1)">★</span>
                                        <span class="cv-cal-star" data-val="2" onclick="cvCalSetRating(2)">★</span>
                                        <span class="cv-cal-star" data-val="3" onclick="cvCalSetRating(3)">★</span>
                                        <span class="cv-cal-star" data-val="4" onclick="cvCalSetRating(4)">★</span>
                                        <span class="cv-cal-star" data-val="5" onclick="cvCalSetRating(5)">★</span>
                                    </div>
                                    <div style="font-size:11px;color:var(--muted);text-align:center">
                                        0 = manter avaliação atual &nbsp;|&nbsp; 1–5 = definir nova média
                                    </div>
                                </div>

                            </div>

                            <!-- Botão aplicar -->
                            <button class="cv-cal-btn-aplicar" id="cv-cal-btn-aplicar" onclick="cvCalAplicar()">
                                ✅ Aplicar Métricas — 1 crédito
                            </button>
                            <div style="font-size:11px;color:var(--muted);text-align:center;margin-top:8px">
                                Os valores são somados aos existentes. Ação irreversível.
                            </div>

                        </div><!-- editor -->
                    </div>
                </div>

                <?php endif; ?>

                <!-- Log de usos -->
                <div class="cv-cal-card" style="margin-top:16px">
                    <div class="cv-cal-hdr">
                        <span class="cv-cal-hdr-icon">📋</span>
                        <div class="cv-cal-hdr-title">Histórico de Calibrações</div>
                    </div>
                    <div class="cv-cal-body">
                        <?php if (empty($logs)): ?>
                        <div style="text-align:center;padding:24px;color:var(--muted);font-size:13px">
                            Nenhuma calibração realizada ainda.
                        </div>
                        <?php else: ?>
                        <?php foreach ($logs as $i => $log):
                            $det = json_decode($log->detalhes, true);
                            if (!is_array($det)) { $det = array(); }
                            $num = $usos_realizados - $i;
                        ?>
                        <div class="cv-cal-log-item">
                            <div class="cv-cal-log-dot"><?php echo $num; ?></div>
                            <div class="cv-cal-log-info">
                                <div class="cv-cal-log-titulo">
                                    <?php if (isset($det['musica_titulo'])): ?>
                                    🎵 <?php echo esc_html($det['musica_titulo']); ?>
                                    <?php elseif (isset($det['selecao'])): ?>
                                    Lote: <?php echo esc_html($det['selecao']); ?>
                                    <?php else: ?>
                                    <?php echo (int)$log->musicas_afetadas; ?> música(s) afetada(s)
                                    <?php endif; ?>
                                </div>
                                <div class="cv-cal-log-meta">
                                    📅 <?php echo esc_html(date_i18n('d/m/Y H:i', strtotime($log->usado_em))); ?>
                                    <?php if (isset($det['plays_add']) && $det['plays_add']): ?>
                                    &nbsp;▶+<?php echo (int)$det['plays_add']; ?>
                                    <?php endif; ?>
                                    <?php if (isset($det['favs_add']) && $det['favs_add']): ?>
                                    &nbsp;❤+<?php echo (int)$det['favs_add']; ?>
                                    <?php endif; ?>
                                    <?php if (isset($det['rating']) && $det['rating']): ?>
                                    &nbsp;★<?php echo esc_html($det['rating']); ?>
                                    <?php endif; ?>
                                    &nbsp;🌐 <?php echo esc_html($log->ip); ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

            </div><!-- área principal -->
        </div><!-- layout -->
        </div><!-- wrap -->

        <div class="cv-cal-toast" id="cv-cal-toast"></div>

        <script>
        (function(){
            var NONCE   = '<?php echo esc_js($nonce); ?>';
            var AJAX    = '<?php echo esc_js($ajax); ?>';
            var musicas = <?php echo wp_json_encode($musicas); ?>;
            var atual   = null; // música selecionada
            var ratingVal = 0;

            // ── Filtro de busca ───────────────────────────────────
            window.cvCalFiltrar = function(q) {
                q = q.toLowerCase();
                document.querySelectorAll('.cv-cal-musica-item').forEach(function(el){
                    el.style.display = (el.dataset.titulo.indexOf(q) !== -1) ? '' : 'none';
                });
            };

            // ── Selecionar música ─────────────────────────────────
            window.cvCalSelecionar = function(id) {
                var m = musicas.find(function(x){ return x.id === id; });
                if (!m) return;
                atual = m;
                ratingVal = 0;

                // Marcar ativa
                document.querySelectorAll('.cv-cal-musica-item').forEach(function(el){
                    el.classList.remove('ativa');
                });
                var li = document.getElementById('cv-cal-li-' + id);
                if (li) { li.classList.add('ativa'); li.scrollIntoView({block:'nearest'}); }

                // Mostrar editor
                document.getElementById('cv-cal-placeholder').style.display = 'none';
                document.getElementById('cv-cal-editor').classList.add('visivel');

                // Preencher preview
                document.getElementById('cv-cal-prev-capa').src  = m.capa;
                document.getElementById('cv-cal-prev-titulo').textContent = m.titulo;

                // Métricas atuais
                document.getElementById('cv-cal-cur-plays').textContent  = m.plays;
                document.getElementById('cv-cal-cur-favs').textContent   = m.favs;
                document.getElementById('cv-cal-cur-rating').textContent = m.rating || '—';

                // Resetar incrementos
                document.getElementById('cv-cal-inc-plays').value = '0';
                document.getElementById('cv-cal-inc-favs').value  = '0';
                cvCalAtualizar('plays');
                cvCalAtualizar('favs');
                cvCalSetRating(0);
            };

            // ── Ajuste rápido ─────────────────────────────────────
            window.cvCalAjustar = function(campo, delta) {
                var el  = document.getElementById('cv-cal-inc-' + campo);
                var val = parseInt(el.value, 10) || 0;
                el.value = Math.max(0, val + delta);
                cvCalAtualizar(campo);
            };

            window.cvCalAtualizar = function(campo) {
                if (!atual) return;
                var inc   = parseInt(document.getElementById('cv-cal-inc-' + campo).value, 10) || 0;
                var atual_val = campo === 'plays' ? atual.plays : atual.favs;
                var novo  = atual_val + inc;
                document.getElementById('cv-cal-novo-' + campo).textContent = novo;
                document.getElementById('cv-cal-total-' + campo).textContent = inc;
            };

            // ── Rating por estrelas ───────────────────────────────
            window.cvCalSetRating = function(val) {
                ratingVal = val;
                document.getElementById('cv-cal-rating-val').textContent = val;
                document.querySelectorAll('.cv-cal-star').forEach(function(s){
                    s.classList.toggle('ativo', parseInt(s.dataset.val, 10) <= val);
                });
            };
            // Hover nas estrelas
            document.querySelectorAll('.cv-cal-star').forEach(function(s){
                s.addEventListener('mouseover', function(){
                    var v = parseInt(s.dataset.val, 10);
                    document.querySelectorAll('.cv-cal-star').forEach(function(x){
                        x.style.opacity = parseInt(x.dataset.val,10) <= v ? '1' : '.3';
                    });
                });
                s.addEventListener('mouseout', function(){
                    document.querySelectorAll('.cv-cal-star').forEach(function(x){
                        x.style.opacity = parseInt(x.dataset.val,10) <= ratingVal ? '1' : '.3';
                    });
                });
            });

            // ── Aplicar métricas via AJAX ─────────────────────────
            window.cvCalAplicar = function() {
                if (!atual) return;
                var plays = parseInt(document.getElementById('cv-cal-inc-plays').value,10) || 0;
                var favs  = parseInt(document.getElementById('cv-cal-inc-favs').value,10) || 0;
                var rating = ratingVal;

                if (plays === 0 && favs === 0 && rating === 0) {
                    toast('Defina ao menos um valor para aplicar.', true); return;
                }

                var btn = document.getElementById('cv-cal-btn-aplicar');
                btn.disabled = true;
                btn.textContent = '⏳ Aplicando...';

                var fd = new FormData();
                fd.append('action', 'cv_calibrar_musica');
                fd.append('nonce', NONCE);
                fd.append('musica_id', atual.id);
                fd.append('plays_add', plays);
                fd.append('favs_add', favs);
                fd.append('rating', rating);

                fetch(AJAX, {method:'POST', body:fd})
                    .then(function(r){ return r.json(); })
                    .then(function(res){
                        btn.disabled = false;
                        btn.textContent = '✅ Aplicar Métricas — 1 crédito';
                        if (res.success) {
                            // Atualizar dados locais
                            atual.plays  += plays;
                            atual.favs   += favs;
                            if (rating) { atual.rating = rating; }
                            // Atualizar cards de métricas
                            document.getElementById('cv-cal-cur-plays').textContent  = atual.plays;
                            document.getElementById('cv-cal-cur-favs').textContent   = atual.favs;
                            document.getElementById('cv-cal-cur-rating').textContent = atual.rating || '—';
                            // Atualizar item da lista
                            var sub = document.querySelector('#cv-cal-li-' + atual.id + ' .cv-cal-mus-stats');
                            if (sub) {
                                sub.textContent = '▶' + atual.plays + '  ❤' + atual.favs + '  ★' + (atual.rating || '—');
                                sub.className = 'cv-cal-mus-stats tem-dados';
                            }
                            // Resetar controles
                            document.getElementById('cv-cal-inc-plays').value = '0';
                            document.getElementById('cv-cal-inc-favs').value  = '0';
                            cvCalAtualizar('plays'); cvCalAtualizar('favs');
                            cvCalSetRating(0);
                            // Atualizar créditos
                            var ringNum = document.querySelector('.cv-cal-ring-num');
                            if (ringNum) {
                                var restantes = parseInt(ringNum.textContent, 10) - 1;
                                ringNum.textContent = Math.max(0, restantes);
                                if (restantes <= 0) { btn.disabled = true; btn.textContent = '🔒 Sem créditos'; }
                            }
                            toast('✅ Métricas aplicadas em "' + atual.titulo + '"!', false);
                        } else {
                            toast(res.data || 'Erro ao aplicar.', true);
                        }
                    })
                    .catch(function(){ btn.disabled=false; btn.textContent='✅ Aplicar Métricas — 1 crédito'; toast('Erro de conexão.', true); });
            };

            function toast(msg, err) {
                var el = document.getElementById('cv-cal-toast');
                el.textContent = msg;
                el.className = 'cv-cal-toast ' + (err ? 'err' : 'ok');
                el.classList.add('show');
                setTimeout(function(){ el.classList.remove('show'); }, 3500);
            }
        })();
        </script>
        <?php
    }



    // ─────────────────────────────────────────────────────────────
    // Execução da calibração
    // ─────────────────────────────────────────────────────────────
    // ─── AJAX: calibrar música individual ────────────────────────
    public static function ajax_calibrar_musica() {
        check_ajax_referer( 'cv_admin_nonce', 'nonce' );
        if ( ! self::is_authorized() ) { wp_send_json_error( 'Acesso restrito.' ); }

        $usos = (int) get_option( self::OPT_CONTADOR, 0 );
        if ( $usos >= self::LIMITE_USOS ) {
            wp_send_json_error( 'Limite de usos atingido.' );
        }

        $musica_id = absint( isset($_POST['musica_id']) ? $_POST['musica_id'] : 0 );
        $plays_add = absint( isset($_POST['plays_add']) ? $_POST['plays_add'] : 0 );
        $favs_add  = absint( isset($_POST['favs_add'])  ? $_POST['favs_add']  : 0 );
        $rating    = (float) ( isset($_POST['rating']) ? $_POST['rating'] : 0 );
        $rating    = min( 5.0, max( 0.0, $rating ) );

        if ( ! $musica_id || get_post_type($musica_id) !== 'musica' ) {
            wp_send_json_error( 'Música não encontrada.' );
        }

        global $wpdb;
        $agora = time();

        // ── Adicionar plays ───────────────────────────────────────
        if ( $plays_add > 0 ) {
            $plays_table = $wpdb->prefix . 'cv_plays_log';
            // Distribuir os plays nos últimos 30 dias com IPs variados
            for ( $i = 0; $i < $plays_add; $i++ ) {
                $offset   = mt_rand( 0, 30 * 86400 );
                $ts       = date( 'Y-m-d H:i:s', $agora - $offset );
                $fake_ip  = mt_rand(100,199).'.'.mt_rand(0,255).'.'.mt_rand(0,255).'.'.mt_rand(1,254);
                $wpdb->insert( $plays_table, array(
                    'music_id'   => $musica_id,
                    'ip_address' => $fake_ip,
                    'played_at'  => $ts,
                    'user_id'    => 0,
                ), array('%d','%s','%s','%d') );
            }
            // Atualizar meta _cv_plays (total acumulado)
            $plays_atual = (int) get_post_meta( $musica_id, '_cv_plays', true );
            update_post_meta( $musica_id, '_cv_plays', $plays_atual + $plays_add );
        }

        // ── Adicionar favoritos ───────────────────────────────────
        if ( $favs_add > 0 ) {
            $favs_atual = (int) get_post_meta( $musica_id, '_cv_favoritos', true );
            update_post_meta( $musica_id, '_cv_favoritos', $favs_atual + $favs_add );
        }

        // ── Definir avaliação ─────────────────────────────────────
        if ( $rating > 0 ) {
            update_post_meta( $musica_id, '_cv_avg_rating', round($rating, 1) );
            // Inserir algumas avaliações fictícias para a média ser consistente
            $ratings_table = $wpdb->prefix . 'cv_ratings';
            $qtd_votos = max( 3, intval($plays_add / 10) );
            for ( $j = 0; $j < $qtd_votos; $j++ ) {
                $variacao  = mt_rand(-5,5) / 10;
                $nota      = (int) round( min(5, max(1, $rating + $variacao)) );
                $offset    = mt_rand(0, 30 * 86400);
                $ts        = date('Y-m-d H:i:s', $agora - $offset);
                $fake_ip   = mt_rand(100,199).'.'.mt_rand(0,255).'.'.mt_rand(0,255).'.'.mt_rand(1,254);
                $existe    = $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM $ratings_table WHERE music_id=%d AND ip_address=%s",
                    $musica_id, $fake_ip
                ) );
                if ( ! $existe ) {
                    $wpdb->insert( $ratings_table, array(
                        'music_id'   => $musica_id,
                        'rating'     => $nota,
                        'ip_address' => $fake_ip,
                        'user_id'    => 0,
                        'rated_at'   => $ts,
                    ), array('%d','%d','%s','%d','%s') );
                }
            }
        }

        // ── Registrar uso ─────────────────────────────────────────
        $titulo = get_the_title( $musica_id );
        $ip     = sanitize_text_field( isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '' );
        $wpdb->insert(
            $wpdb->prefix . 'cv_calibracao_log',
            array(
                'admin_id'         => get_current_user_id(),
                'ip'               => $ip,
                'musicas_afetadas' => 1,
                'detalhes'         => wp_json_encode( array(
                    'musica_id'    => $musica_id,
                    'musica_titulo'=> $titulo,
                    'plays_add'    => $plays_add,
                    'favs_add'     => $favs_add,
                    'rating'       => $rating > 0 ? $rating : null,
                ) ),
            ),
            array('%d','%s','%d','%s')
        );
        update_option( self::OPT_CONTADOR, $usos + 1 );

        // ── Disparar recálculo do ranking ─────────────────────────
        if ( class_exists('CV_Ranking') ) {
            CV_Ranking::recalculate();
        }

        wp_send_json_success( array(
            'msg'          => 'Métricas aplicadas.',
            'plays_novo'   => (int) get_post_meta($musica_id,'_cv_plays',true),
            'favs_novo'    => (int) get_post_meta($musica_id,'_cv_favoritos',true),
            'rating_novo'  => round((float) get_post_meta($musica_id,'_cv_avg_rating',true),1),
            'creditos'     => max(0, self::LIMITE_USOS - ($usos + 1)),
        ) );
    }

    public static function handle_execucao() {
        if ( ! self::is_authorized() ) { wp_die('Acesso restrito.'); }
        check_admin_referer('cv_executar_calibracao','cv_cal_nonce');
        if ( empty($_POST['confirmar']) ) { wp_redirect( admin_url('admin.php?page=cv-calibracao&msg=sem_confirmacao') ); exit; }

        $usos = (int) get_option( self::OPT_CONTADOR, 0 );
        if ( $usos >= self::LIMITE_USOS ) { wp_redirect( admin_url('admin.php?page=cv-calibracao&msg=limite') ); exit; }

        $selecao    = sanitize_text_field($_POST['selecao_musicas'] ?? 'todas');
        $intensidade= sanitize_text_field($_POST['intensidade'] ?? 'moderada');
        $periodo    = absint($_POST['periodo_dias'] ?? 30);

        $musicas    = self::get_musicas_para_calibrar($selecao);
        if ( empty($musicas) ) { wp_redirect( admin_url('admin.php?page=cv-calibracao&msg=sem_musicas') ); exit; }

        $total_afetadas = self::executar( $musicas, $intensidade, $periodo );

        // Log
        global $wpdb;
        $ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] ?? '' );
        $wpdb->insert(
            $wpdb->prefix . 'cv_calibracao_log',
            [
                'admin_id'        => get_current_user_id(),
                'ip'              => $ip,
                'musicas_afetadas'=> $total_afetadas,
                'detalhes'        => wp_json_encode(['selecao'=>$selecao,'intensidade'=>$intensidade,'periodo'=>$periodo]),
            ],
            [ '%d','%s','%d','%s' ]
        );

        update_option( self::OPT_CONTADOR, $usos + 1 );

        // Força recalculo do ranking
        if ( class_exists('CV_Ranking') ) {
            CV_Ranking::update_cache();
        }

        wp_redirect( admin_url('admin.php?page=cv-calibracao&msg=ok&afetadas='.$total_afetadas) );
        exit;
    }

    // ─────────────────────────────────────────────────────────────
    // Motor de calibração: insere plays e ratings simulados
    // ─────────────────────────────────────────────────────────────
    private static function executar( array $musicas, $intensidade, $periodo_dias ) {
        global $wpdb;

        $faixas = [
            'leve'    => ['plays_min'=>10,'plays_max'=>50,'rat_min'=>3.5,'rat_max'=>4.5],
            'moderada'=> ['plays_min'=>50,'plays_max'=>200,'rat_min'=>3.8,'rat_max'=>4.8],
            'forte'   => ['plays_min'=>200,'plays_max'=>500,'rat_min'=>4.0,'rat_max'=>5.0],
        ];
        $f = $faixas[$intensidade] ?? $faixas['moderada'];

        $plays_table   = $wpdb->prefix . 'cv_plays_log';
        $ratings_table = $wpdb->prefix . 'cv_ratings';
        $agora         = time();
        $afetadas      = 0;

        foreach ( $musicas as $musica_id ) {
            $musica_id = absint($musica_id);

            // Quantidade de plays: aleatória dentro da faixa
            $qtd_plays = mt_rand($f['plays_min'], $f['plays_max']);

            for ($i = 0; $i < $qtd_plays; $i++) {
                // Distribui no período simulado com variação temporal natural
                $offset  = mt_rand(0, $periodo_dias * 86400);
                $ts      = date('Y-m-d H:i:s', $agora - $offset);
                // IP fictício variado para não parecer robô
                $fake_ip = mt_rand(100,199).'.'.mt_rand(0,255).'.'.mt_rand(0,255).'.'.mt_rand(1,254);

                $wpdb->insert( $plays_table, [
                    'music_id'   => $musica_id,
                    'ip_address' => $fake_ip,
                    'played_at'  => $ts,
                    'user_id'    => 0,
                ], ['%d','%s','%s','%d'] );
            }

            // Avaliação: distribui notas com variação natural (não todas 5)
            $nota_media = $f['rat_min'] + (mt_rand(0,100)/100) * ($f['rat_max'] - $f['rat_min']);
            $qtd_rat    = max(3, intval($qtd_plays / 15));
            for ($j = 0; $j < $qtd_rat; $j++) {
                // Varia ±0.5 em torno da média
                $nota    = round( min(5, max(1, $nota_media + (mt_rand(-50,50)/100))), 1 );
                $nota_int= (int) round($nota);
                $offset  = mt_rand(0, $periodo_dias * 86400);
                $ts      = date('Y-m-d H:i:s', $agora - $offset);
                $fake_ip = mt_rand(100,199).'.'.mt_rand(0,255).'.'.mt_rand(0,255).'.'.mt_rand(1,254);

                // Evita duplicata de IP (cada IP vota uma vez)
                $existe = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $ratings_table WHERE music_id=%d AND ip_address=%s",
                    $musica_id, $fake_ip
                ));
                if (!$existe) {
                    $wpdb->insert($ratings_table, [
                        'music_id'   => $musica_id,
                        'rating'     => $nota_int,
                        'ip_address' => $fake_ip,
                        'user_id'    => 0,
                        'rated_at'   => $ts,
                    ], ['%d','%d','%s','%d','%s']);
                }
            }
            $afetadas++;
        }
        return $afetadas;
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────
    private static function get_musicas_publicadas() {
        return get_posts(['post_type'=>'musica','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']);
    }

    private static function get_musicas_para_calibrar($selecao) {
        global $wpdb;
        switch ($selecao) {
            case 'recentes':
                return get_posts(['post_type'=>'musica','post_status'=>'publish','numberposts'=>20,'orderby'=>'date','order'=>'DESC','fields'=>'ids']);
            case 'sem_plays':
                $ids_com_plays = $wpdb->get_col(
                    "SELECT DISTINCT music_id FROM {$wpdb->prefix}cv_plays_log"
                );
                $args = ['post_type'=>'musica','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids'];
                if ($ids_com_plays) {
                    $args['post__not_in'] = array_map('absint',$ids_com_plays);
                }
                return get_posts($args);
            default: // todas
                return get_posts(['post_type'=>'musica','post_status'=>'publish','numberposts'=>-1,'fields'=>'ids']);
        }
    }

    private static function get_logs($limit=10) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}cv_calibracao_log ORDER BY usado_em DESC LIMIT %d",
            $limit
        ));
    }
}

CV_Calibracao::init();
