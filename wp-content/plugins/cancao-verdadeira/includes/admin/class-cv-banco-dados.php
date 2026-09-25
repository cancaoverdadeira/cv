<?php
// includes/admin/class-cv-banco-dados.php
// Gerado em: 2026-06-29 10:00:00
// Módulo: Painel de Banco de Dados — Canção Verdadeira v2.23.0
// Exibe visão executiva de todas as tabelas cv_* do banco de dados WordPress.
// Mostra: nome, número de registros, tamanho em disco, índices, status de saúde.
// Inspirado pela sugestão do Diretor de TI José Amado — painel de maturidade técnica.
// Acesso: apenas administradores. Nenhuma escrita/alteração no banco.
// Compatível com PHP 7.2+. Usa $wpdb direto (sem arrow functions, sem tipos).
// v2.41.0: a lista agora bate com as tabelas reais. Saíram 4 que nunca existiram
// (cv_user_notifications, cv_achievements, cv_login_attempts, cv_sorteio_entries):
// notificações e conquistas ficam em user_meta, tentativas de login em transients
// e os participantes do sorteio são os assinantes. Entraram ratings, comentários,
// produtos, brindes e entregas, que existiam mas não apareciam.

if ( ! defined( 'ABSPATH' ) ) exit;

class CV_Banco_Dados {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_page' ) );
        add_action( 'wp_ajax_cv_banco_refresh', array( __CLASS__, 'ajax_refresh' ) );
    }

    public static function register_page() {
        add_submenu_page(
            null,
            'Banco de Dados',
            'Banco de Dados',
            'manage_options',
            'cv-banco-dados',
            array( __CLASS__, 'render' )
        );
    }

    // ── Coleta dados das tabelas ──────────────────────────────────
    private static function get_table_data() {
        global $wpdb;

        $prefix = $wpdb->prefix;

        // Tabelas do plugin (cv_*)
        $cv_tables = array(
            $prefix . 'cv_plays_log'          => array( 'label' => 'Plays Log',           'icon' => '▶️',  'desc' => 'Registro de cada play válido (30s+)' ),
            $prefix . 'cv_ranking_cache'      => array( 'label' => 'Ranking Cache',        'icon' => '🏆', 'desc' => 'Score calculado de cada música' ),
            $prefix . 'cv_favorites'          => array( 'label' => 'Favoritos',            'icon' => '❤️',  'desc' => 'Músicas favoritadas por usuário' ),
            $prefix . 'cv_playlists'          => array( 'label' => 'Playlists',            'icon' => '📋', 'desc' => 'Playlists criadas por usuários' ),
            $prefix . 'cv_playlist_items'     => array( 'label' => 'Itens de Playlist',    'icon' => '🎵', 'desc' => 'Músicas dentro das playlists' ),
            $prefix . 'cv_subscribers'        => array( 'label' => 'Assinantes Newsletter','icon' => '📨', 'desc' => 'E-mails cadastrados para newsletter' ),
            $prefix . 'cv_ratings'            => array( 'label' => 'Avaliações',           'icon' => '⭐', 'desc' => 'Notas (estrelas) dadas às músicas' ),
            $prefix . 'cv_lyric_comments'     => array( 'label' => 'Comentários de trecho','icon' => '💬', 'desc' => 'Comentários em trechos da letra' ),
            $prefix . 'cv_sentimentos'        => array( 'label' => 'Sentimentos',          'icon' => '🎭', 'desc' => 'Categorias de sentimento musical' ),
            $prefix . 'cv_musica_sentimentos' => array( 'label' => 'Música × Sentimento',  'icon' => '🔗', 'desc' => 'Relação N:N música-sentimento' ),
            $prefix . 'cv_calibracao_log'     => array( 'label' => 'Log de Calibração',    'icon' => '⚖️',  'desc' => 'Desativada em 23/09/2026 (fica vazia)' ),
            $prefix . 'cv_action_logs'        => array( 'label' => 'Logs de Ação',         'icon' => '📝', 'desc' => 'Ações administrativas e sistema' ),
            $prefix . 'cv_banners'            => array( 'label' => 'Banners',              'icon' => '🖼️',  'desc' => 'Banners de monetização' ),
            $prefix . 'cv_sorteios'           => array( 'label' => 'Sorteios',             'icon' => '🎁', 'desc' => 'Campanhas de sorteio' ),
            $prefix . 'cv_produtos'           => array( 'label' => 'Loja (produtos)',      'icon' => '🛒', 'desc' => 'Produtos da loja' ),
            $prefix . 'cv_brindes'            => array( 'label' => 'Brindes',              'icon' => '🎀', 'desc' => 'Brindes cadastrados' ),
            $prefix . 'cv_brindes_entregas'   => array( 'label' => 'Entregas de brindes',  'icon' => '📦', 'desc' => 'Brindes enviados a assinantes' ),
            $prefix . 'cv_estoque_itens'      => array( 'label' => 'Estoque (itens)',      'icon' => '📦', 'desc' => 'E-book, Caneca, Camiseta (v2.47.0)' ),
            $prefix . 'cv_estoque_variacoes'  => array( 'label' => 'Estoque (tamanhos)',   'icon' => '👕', 'desc' => 'Saldo por item e tamanho' ),
            $prefix . 'cv_estoque_mov'        => array( 'label' => 'Movimentos de estoque','icon' => '🔁', 'desc' => 'Entradas, saídas, ajustes e pedidos' ),
            $prefix . 'cv_pedidos'            => array( 'label' => 'Pedidos',              'icon' => '🛍️', 'desc' => 'Pedidos feitos na Minha Área' ),
            $prefix . 'cv_parcerias'          => array( 'label' => 'Parcerias',            'icon' => '🤝', 'desc' => 'Propostas do "Seja nosso parceiro" (v2.48.0)' ),
        );

        // Busca informações de tamanho e status via INFORMATION_SCHEMA
        $db_name = DB_NAME;
        $size_query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT TABLE_NAME, TABLE_ROWS, DATA_LENGTH, INDEX_LENGTH,
                        (DATA_LENGTH + INDEX_LENGTH) AS TOTAL_SIZE,
                        CREATE_TIME, UPDATE_TIME, ENGINE
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = %s
                   AND TABLE_NAME LIKE %s",
                $db_name,
                $prefix . 'cv_%'
            ),
            ARRAY_A
        );

        $size_map = array();
        if ( $size_query ) {
            foreach ( $size_query as $row ) {
                $size_map[ $row['TABLE_NAME'] ] = $row;
            }
        }

        // Busca índices
        $index_query = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT TABLE_NAME, COUNT(*) as idx_count
                 FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = %s
                   AND TABLE_NAME LIKE %s
                 GROUP BY TABLE_NAME",
                $db_name,
                $prefix . 'cv_%'
            ),
            ARRAY_A
        );
        $index_map = array();
        if ( $index_query ) {
            foreach ( $index_query as $row ) {
                $index_map[ $row['TABLE_NAME'] ] = (int) $row['idx_count'];
            }
        }

        // Monta resultado
        $result = array();
        foreach ( $cv_tables as $table => $meta ) {
            $exists = isset( $size_map[ $table ] );
            $rows   = $exists ? (int) $size_map[ $table ]['TABLE_ROWS'] : 0;
            $size   = $exists ? (int) $size_map[ $table ]['TOTAL_SIZE'] : 0;
            $data   = $exists ? (int) $size_map[ $table ]['DATA_LENGTH'] : 0;
            $index  = $exists ? (int) $size_map[ $table ]['INDEX_LENGTH'] : 0;
            $engine = $exists ? $size_map[ $table ]['ENGINE'] : '—';
            $upd    = $exists ? $size_map[ $table ]['UPDATE_TIME'] : null;
            $idx_c  = isset( $index_map[ $table ] ) ? $index_map[ $table ] : 0;

            // Contagem real (TABLE_ROWS é estimada pelo InnoDB; fazemos count real só para tabelas pequenas)
            if ( $exists && $rows < 50000 ) {
                $real = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
                $rows = (int) $real;
            }

            $result[] = array(
                'table'   => $table,
                'label'   => $meta['label'],
                'icon'    => $meta['icon'],
                'desc'    => $meta['desc'],
                'exists'  => $exists,
                'rows'    => $rows,
                'size'    => $size,
                'data'    => $data,
                'index'   => $index,
                'engine'  => $engine,
                'updated' => $upd,
                'indexes' => $idx_c,
            );
        }

        return $result;
    }

    // ── Totalizadores do banco WordPress completo ─────────────────
    private static function get_wp_totals() {
        global $wpdb;
        $db_name = DB_NAME;
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT COUNT(*) as total_tables,
                        SUM(DATA_LENGTH + INDEX_LENGTH) as total_size,
                        SUM(TABLE_ROWS) as total_rows
                 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = %s",
                $db_name
            ),
            ARRAY_A
        );
        return $row ? $row : array( 'total_tables' => 0, 'total_size' => 0, 'total_rows' => 0 );
    }

    // ── AJAX refresh ─────────────────────────────────────────────
    public static function ajax_refresh() {
        check_ajax_referer( 'cv_banco_refresh', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) wp_die();
        $tables = self::get_table_data();
        wp_send_json_success( array( 'tables' => $tables, 'ts' => current_time('H:i:s') ) );
    }

    // ── Helpers de formatação ─────────────────────────────────────
    private static function format_bytes( $bytes ) {
        if ( $bytes <= 0 ) return '0 B';
        if ( $bytes < 1024 ) return $bytes . ' B';
        if ( $bytes < 1048576 ) return round( $bytes / 1024, 1 ) . ' KB';
        return round( $bytes / 1048576, 2 ) . ' MB';
    }

    private static function health_color( $rows, $exists ) {
        if ( ! $exists ) return '#e74c3c'; // vermelho — não existe
        if ( $rows === 0 ) return '#e67e22'; // laranja — existe mas vazia
        return '#1DB954'; // verde — OK
    }

    // ── Render ───────────────────────────────────────────────────
    public static function render() {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Acesso negado.' );

        $tables   = self::get_table_data();
        $wp_total = self::get_wp_totals();

        $cv_size    = array_sum( array_column( $tables, 'size' ) );
        $cv_rows    = array_sum( array_column( $tables, 'rows' ) );
        $cv_count   = count( array_filter( $tables, function( $t ) { return $t['exists']; } ) );
        $cv_missing = count( array_filter( $tables, function( $t ) { return ! $t['exists']; } ) );

        $nonce = wp_create_nonce( 'cv_banco_refresh' );
        ?>
        <div class="wrap" style="background:#FFFFFF;min-height:100vh;padding:24px;box-sizing:border-box;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif;">

        <!-- HEADER -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;">
            <div>
                <h1 style="color:#3B2418;font-size:22px;margin:0 0 4px 0;font-weight:700;">🗄️ Banco de Dados</h1>
                <p style="color:#8A6A55;font-size:13px;margin:0;">Estrutura e saúde das tabelas Canção Verdadeira · <?php echo esc_html( DB_NAME ); ?></p>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <span id="cv-banco-ts" style="color:#8A6A55;font-size:12px;">Atualizado agora</span>
                <button id="cv-banco-refresh" style="background:#D4A01722;border:1px solid #C9A27E;color:#7B3A22;padding:8px 16px;border-radius:8px;cursor:pointer;font-size:13px;font-weight:600;">
                    🔄 Atualizar
                </button>
            </div>
        </div>

        <!-- KPI CARDS -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:28px;">

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;padding:20px;">
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Tabelas CV Ativas</div>
                <div style="font-size:32px;font-weight:800;color:#7B3A22;"><?php echo $cv_count; ?></div>
                <div style="font-size:12px;color:#D62C1A;margin-top:4px;"><?php echo $cv_missing; ?> ausentes</div>
            </div>

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;padding:20px;">
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Registros CV</div>
                <div style="font-size:32px;font-weight:800;color:#137B38;"><?php echo number_format( $cv_rows, 0, ',', '.' ); ?></div>
                <div style="font-size:12px;color:#8A6A55;margin-top:4px;">total nas tabelas plugin</div>
            </div>

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;padding:20px;">
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Tamanho CV</div>
                <div style="font-size:32px;font-weight:800;color:#2871BE;"><?php echo self::format_bytes( $cv_size ); ?></div>
                <div style="font-size:12px;color:#8A6A55;margin-top:4px;">dados + índices</div>
            </div>

            <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;padding:20px;">
                <div style="font-size:11px;color:#8A6A55;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Total BD WordPress</div>
                <div style="font-size:32px;font-weight:800;color:#9752B3;"><?php echo self::format_bytes( (int) $wp_total['total_size'] ); ?></div>
                <div style="font-size:12px;color:#8A6A55;margin-top:4px;"><?php echo (int) $wp_total['total_tables']; ?> tabelas no banco</div>
            </div>

        </div>

        <!-- TABELA PRINCIPAL -->
        <div style="background:#F8F0E4;border:1px solid #EADBC6;border-radius:12px;overflow:hidden;">
            <div style="padding:18px 20px;border-bottom:1px solid #EADBC6;display:flex;align-items:center;justify-content:space-between;">
                <div style="color:#7B3A22;font-size:14px;font-weight:700;">📊 Tabelas do Plugin</div>
                <div style="font-size:12px;color:#8A6A55;">Contagem real de registros · Engine · Índices · Tamanho</div>
            </div>

            <div style="overflow-x:auto;">
            <table id="cv-banco-table" style="width:100%;border-collapse:collapse;">
                <thead>
                <tr style="background:#FFFFFF;">
                    <th style="padding:12px 16px;text-align:left;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Tabela</th>
                    <th style="padding:12px 16px;text-align:left;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Descrição</th>
                    <th style="padding:12px 16px;text-align:center;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Status</th>
                    <th style="padding:12px 16px;text-align:right;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Registros</th>
                    <th style="padding:12px 16px;text-align:center;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Engine</th>
                    <th style="padding:12px 16px;text-align:center;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Índices</th>
                    <th style="padding:12px 16px;text-align:right;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Dados</th>
                    <th style="padding:12px 16px;text-align:right;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Índice</th>
                    <th style="padding:12px 16px;text-align:right;color:#8A6A55;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:600;">Total</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ( $tables as $i => $t ) :
                    $bg      = $i % 2 === 0 ? '#F8F0E4' : '#F8F0E4';
                    $hc      = self::health_color( $t['rows'], $t['exists'] );
                    $status  = ! $t['exists'] ? '❌ Ausente' : ( $t['rows'] === 0 ? '⚠️ Vazia' : '✅ OK' );
                    $short   = str_replace( $GLOBALS['wpdb']->prefix . 'cv_', 'cv_', $t['table'] );
                ?>
                <tr style="background:<?php echo $bg; ?>;border-bottom:1px solid #EADBC6;transition:background .15s;" onmouseover="this.style.background='#F3E6D3'" onmouseout="this.style.background='<?php echo $bg; ?>'">
                    <td style="padding:13px 16px;">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <span style="font-size:18px;"><?php echo $t['icon']; ?></span>
                            <div>
                                <div style="color:#3B2418;font-size:13px;font-weight:600;"><?php echo esc_html( $t['label'] ); ?></div>
                                <div style="color:#8A6A55;font-size:11px;font-family:monospace;"><?php echo esc_html( $short ); ?></div>
                            </div>
                        </div>
                    </td>
                    <td style="padding:13px 16px;color:#8A6A55;font-size:12px;"><?php echo esc_html( $t['desc'] ); ?></td>
                    <td style="padding:13px 16px;text-align:center;">
                        <span style="background:<?php echo $hc; ?>22;color:<?php echo $hc; ?>;border:1px solid <?php echo $hc; ?>44;border-radius:20px;padding:3px 10px;font-size:11px;font-weight:700;">
                            <?php echo $status; ?>
                        </span>
                    </td>
                    <td style="padding:13px 16px;text-align:right;color:<?php echo $t['rows'] > 0 ? '#B8700C' : '#F3E6D3'; ?>;font-weight:700;font-size:14px;">
                        <?php echo $t['exists'] ? number_format( $t['rows'], 0, ',', '.' ) : '—'; ?>
                    </td>
                    <td style="padding:13px 16px;text-align:center;color:#8A6A55;font-size:12px;font-family:monospace;">
                        <?php echo esc_html( $t['engine'] ); ?>
                    </td>
                    <td style="padding:13px 16px;text-align:center;">
                        <?php if ( $t['indexes'] > 0 ) : ?>
                        <span style="background:#4a90d922;color:#2871BE;border:1px solid #4a90d944;border-radius:12px;padding:2px 8px;font-size:11px;font-weight:700;">
                            <?php echo $t['indexes']; ?>
                        </span>
                        <?php else : ?>
                        <span style="color:#8A6A55;">—</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:13px 16px;text-align:right;color:#8A6A55;font-size:12px;"><?php echo $t['exists'] ? self::format_bytes( $t['data'] ) : '—'; ?></td>
                    <td style="padding:13px 16px;text-align:right;color:#8A6A55;font-size:12px;"><?php echo $t['exists'] ? self::format_bytes( $t['index'] ) : '—'; ?></td>
                    <td style="padding:13px 16px;text-align:right;color:<?php echo $t['size'] > 0 ? '#9b59b6' : '#F3E6D3'; ?>;font-size:12px;font-weight:600;"><?php echo $t['exists'] ? self::format_bytes( $t['size'] ) : '—'; ?></td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>

        <!-- RECOMENDAÇÕES -->
        <?php $missing = array_filter( $tables, function( $t ) { return ! $t['exists']; } ); ?>
        <?php if ( ! empty( $missing ) ) : ?>
        <div style="background:#e74c3c11;border:1px solid #e74c3c44;border-radius:12px;padding:20px;margin-top:20px;">
            <div style="color:#D62C1A;font-size:14px;font-weight:700;margin-bottom:12px;">⚠️ Tabelas Ausentes — Ação Recomendada</div>
            <p style="color:#6B4C3B;font-size:13px;margin:0 0 12px 0;">As tabelas abaixo não foram criadas ainda. Desative e reative o plugin para executar o script de criação (dbDelta).</p>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php foreach ( $missing as $m ) : ?>
                <span style="background:#e74c3c22;color:#D62C1A;border:1px solid #e74c3c44;border-radius:6px;padding:4px 10px;font-size:12px;font-family:monospace;">
                    <?php echo esc_html( $m['icon'] . ' ' . $m['label'] ); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- LEGENDA -->
        <div style="display:flex;gap:20px;margin-top:16px;flex-wrap:wrap;">
            <div style="color:#8A6A55;font-size:12px;display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:50%;background:#1DB954;display:inline-block;"></span> Tabela OK com dados</div>
            <div style="color:#8A6A55;font-size:12px;display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:50%;background:#e67e22;display:inline-block;"></span> Tabela existe mas está vazia</div>
            <div style="color:#8A6A55;font-size:12px;display:flex;align-items:center;gap:6px;"><span style="width:10px;height:10px;border-radius:50%;background:#e74c3c;display:inline-block;"></span> Tabela não criada no banco</div>
        </div>

        </div><!-- .wrap -->

        <script>
        (function(){
            document.getElementById('cv-banco-refresh').addEventListener('click', function(){
                var btn = this;
                btn.disabled = true;
                btn.textContent = '⏳ Atualizando...';

                var fd = new FormData();
                fd.append('action', 'cv_banco_refresh');
                fd.append('nonce', '<?php echo $nonce; ?>');

                fetch(ajaxurl, { method:'POST', body: fd })
                    .then(function(r){ return r.json(); })
                    .then(function(data){
                        if (data.success) {
                            document.getElementById('cv-banco-ts').textContent = 'Atualizado às ' + data.data.ts;
                        }
                        btn.disabled = false;
                        btn.textContent = '🔄 Atualizar';
                    })
                    .catch(function(){
                        btn.disabled = false;
                        btn.textContent = '🔄 Atualizar';
                    });
            });
        })();
        </script>
        <?php
    }
}

CV_Banco_Dados::init();
