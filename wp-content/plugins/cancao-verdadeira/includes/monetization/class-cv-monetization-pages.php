<?php
// cancao-verdadeira/includes/monetization/class-cv-monetization-pages.php
// Gerado em: 2026-06-14 00:00:00
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// Páginas administrativas do módulo de monetização: Banners de Parceiros,
// Loja (produtos), Sorteios e Brindes. Cada página tem formulário de
// cadastro, listagem com ações e integração com a Media Library do WP.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Monetization_Pages {

    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ), 15 );
    }

    public static function register_menus() {
        add_submenu_page(
            null, 'Banners de Parceiros', '📣 Banners',
            'manage_options', 'cv-banners', array( __CLASS__, 'page_banners' )
        );
        add_submenu_page(
            null, 'Loja', '🛒 Loja',
            'manage_options', 'cv-loja', array( __CLASS__, 'page_loja' )
        );
        add_submenu_page(
            null, 'Sorteios', '🎰 Sorteios',
            'manage_options', 'cv-sorteios', array( __CLASS__, 'page_sorteios' )
        );
        add_submenu_page(
            null, 'Brindes', '🎁 Brindes',
            'manage_options', 'cv-brindes', array( __CLASS__, 'page_brindes' )
        );
    }

    // ════════════════════════════════════════════════════════════════
    // BANNERS
    // ════════════════════════════════════════════════════════════════
    public static function page_banners() {
        wp_enqueue_media();
        global $wpdb;
        $banners = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}cv_banners ORDER BY id DESC LIMIT 100"
        );
        $posicoes = CV_Monetization::posicoes(); // v2.28.0: sem posição no topo

        // Configuração do bloco "depois da letra" (parágrafo + aviso + banner)
        if ( isset( $_POST['cv_pub_salvar'] ) && check_admin_referer( 'cv_pub_config', 'cv_pub_nonce' ) && current_user_can( 'manage_options' ) ) {
            update_option( 'cv_publicidade', array(
                'apos_letra_ativo' => isset( $_POST['cv_pub_ativo'] ) ? 1 : 0,
                'aviso'            => sanitize_text_field( wp_unslash( $_POST['cv_pub_aviso'] ?? '' ) ) ?: 'Leia após a publicidade',
                'paragrafo'        => sanitize_textarea_field( wp_unslash( $_POST['cv_pub_paragrafo'] ?? '' ) ),
            ) );
            echo '<div class="notice notice-success is-dismissible"><p>Configuração da publicidade salva.</p></div>';
        }
        $pub = CV_Monetization::config();
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>📣 Banners de Parceiros</h1>
                <p class="cv-admin-subtitle"><?php echo count( $banners ); ?> banner(s) cadastrado(s)</p>
                <button id="cv-banner-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">
                    + Novo Banner
                </button>
            </div>

            <div id="cv-banner-msg" class="cv-action-message" style="display:none"></div>

            <!-- Regras e bloco "depois da letra" -->
            <form method="post" class="cv-section" style="max-width:900px">
                <?php wp_nonce_field( 'cv_pub_config', 'cv_pub_nonce' ); ?>
                <h2 class="cv-section-title">🎵 Publicidade na página da música</h2>
                <p style="font-size:13px;color:#8A6A55;margin:0 0 12px">
                    Regras do site: <strong>nunca no topo</strong> e <strong>sem banner rotativo</strong> — cada posição mostra
                    um único banner (o ativo mais recente). Na página da música a ordem é:
                    <em>letra → parágrafo curto → aviso → banner → resto da página</em>.
                    Sem banner ativo na posição "depois da letra", nada aparece.
                </p>
                <label style="display:flex;gap:8px;align-items:center;margin-bottom:12px">
                    <input type="checkbox" name="cv_pub_ativo" value="1" <?php checked( ! empty( $pub['apos_letra_ativo'] ) ); ?> />
                    Mostrar publicidade depois da letra
                </label>
                <div style="display:grid;grid-template-columns:1fr 2fr;gap:16px">
                    <div class="cv-form-group">
                        <label class="cv-form-label">Aviso antes do banner</label>
                        <input type="text" name="cv_pub_aviso" class="cv-input" value="<?php echo esc_attr( $pub['aviso'] ); ?>" />
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Parágrafo curto (usado quando a música não tem Descrição)</label>
                        <textarea name="cv_pub_paragrafo" class="cv-input" rows="2"><?php echo esc_textarea( $pub['paragrafo'] ); ?></textarea>
                    </div>
                </div>
                <button type="submit" name="cv_pub_salvar" value="1" class="cv-btn cv-btn-primary">💾 Salvar configuração</button>
            </form>

            <!-- Formulário -->
            <div id="cv-banner-form" class="cv-section" style="display:none">
                <h2 class="cv-section-title">Cadastrar / Editar Banner</h2>
                <input type="hidden" id="cv-banner-id" value="0" />
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

                    <div class="cv-form-group">
                        <label class="cv-form-label">Título do banner *</label>
                        <input type="text" id="cv-b-titulo" class="cv-input" placeholder="Ex: Parceiro Sertanejo FM" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Posição *</label>
                        <select id="cv-b-posicao" class="cv-input">
                            <?php foreach ( $posicoes as $val => $label ) : ?>
                            <option value="<?php echo esc_attr( $val ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">URL da imagem *</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" id="cv-b-imagem" class="cv-input" placeholder="https://..." style="flex:1" />
                            <button type="button" class="cv-btn cv-btn-outline cv-media-pick" data-target="cv-b-imagem">📁 Biblioteca</button>
                        </div>
                        <img id="cv-b-imagem-preview" src="" alt="" style="display:none;margin-top:8px;max-height:80px;border-radius:6px" />
                    </div>

                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">URL de destino (ao clicar) *</label>
                        <input type="text" id="cv-b-url" class="cv-input" placeholder="https://parceiro.com.br" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Texto alternativo (acessibilidade)</label>
                        <input type="text" id="cv-b-alt" class="cv-input" placeholder="Descrição da imagem para leitores de tela" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Ativo</label>
                        <select id="cv-b-ativo" class="cv-input">
                            <option value="1">✅ Sim — exibir no site</option>
                            <option value="0">❌ Não — ocultar</option>
                        </select>
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Data início (opcional)</label>
                        <input type="date" id="cv-b-inicio" class="cv-input" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Data fim (opcional)</label>
                        <input type="date" id="cv-b-fim" class="cv-input" />
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px">
                    <button id="cv-banner-salvar" class="cv-btn cv-btn-primary">💾 Salvar Banner</button>
                    <button id="cv-banner-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
                </div>

                <div class="cv-section" style="margin-top:20px;padding:14px;background:#FFFFFF;border-radius:8px;font-size:13px;color:#8A6A55">
                    <strong style="color:#7B3A22">Como usar os banners no site:</strong><br>
                    <strong>Depois da letra</strong> — automático na página de cada música (não precisa de código)<br>
                    <code style="color:#6B4C3B">[cv_banner posicao="meio_pagina" leia_mais="sim"]</code> — Meio de página interna com botão fechar<br>
                    <code style="color:#6B4C3B">[cv_banner posicao="rodape_pagina"]</code> — Rodapé de página interna
                </div>
            </div>

            <!-- Listagem -->
            <div class="cv-section">
                <?php if ( empty( $banners ) ) : ?>
                <p class="cv-empty">Nenhum banner cadastrado ainda. Clique em "+ Novo Banner" para começar.</p>
                <?php else : ?>
                <table class="cv-table">
                    <thead>
                        <tr>
                            <th style="width:80px">Imagem</th>
                            <th>Título</th>
                            <th>Posição</th>
                            <th style="text-align:center">Cliques</th>
                            <th style="text-align:center">Status</th>
                            <th>Validade</th>
                            <th style="text-align:center">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $banners as $b ) : ?>
                    <tr id="cv-banner-row-<?php echo esc_attr( $b->id ); ?>">
                        <td>
                            <img src="<?php echo esc_url( $b->imagem_url ); ?>"
                                 style="width:72px;height:40px;object-fit:cover;border-radius:4px;background:#F8F0E4"
                                 alt="<?php echo esc_attr( $b->titulo ); ?>" />
                        </td>
                        <td>
                            <strong style="color:var(--cv-text)"><?php echo esc_html( $b->titulo ); ?></strong><br>
                            <small style="color:#8A6A55;font-size:11px"><?php echo esc_url( $b->url_destino ); ?></small>
                        </td>
                        <td style="font-size:12px;color:#8A6A55"><?php echo esc_html( $posicoes[ $b->posicao ] ?? $b->posicao ); ?></td>
                        <td style="text-align:center;font-weight:700;color:#7B3A22"><?php echo number_format( $b->cliques ); ?></td>
                        <td style="text-align:center">
                            <?php if ( $b->ativo ) : ?>
                                <span style="color:#1C7C44;font-size:18px" title="Ativo">●</span>
                            <?php else : ?>
                                <span style="color:#8A6A55;font-size:18px" title="Inativo">○</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;color:#8A6A55">
                            <?php
                            if ( $b->data_inicio || $b->data_fim ) {
                                echo esc_html( $b->data_inicio ? date( 'd/m/Y', strtotime( $b->data_inicio ) ) : '—' );
                                echo ' → ';
                                echo esc_html( $b->data_fim ? date( 'd/m/Y', strtotime( $b->data_fim ) ) : '∞' );
                            } else {
                                echo 'Sem prazo';
                            }
                            ?>
                        </td>
                        <td style="text-align:center">
                            <div style="display:flex;gap:6px;justify-content:center">
                                <button class="cv-btn cv-btn-outline cv-banner-editar"
                                        data-banner='<?php echo esc_attr( json_encode( $b ) ); ?>'
                                        style="padding:4px 10px;font-size:11px">✏ Editar</button>
                                <button class="cv-btn cv-banner-excluir"
                                        data-id="<?php echo esc_attr( $b->id ); ?>"
                                        style="padding:4px 10px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3)">🗑</button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>

        <script>
        jQuery(function($){
            var nonce = '<?php echo esc_js( wp_create_nonce( 'cv_admin_nonce' ) ); ?>';
            var ajax  = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

            function msg(t, ok){ var $m=$('#cv-banner-msg'); $m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show(); setTimeout(function(){$m.fadeOut();},3000); }

            $('#cv-banner-novo-btn').on('click',function(){ $('#cv-banner-id').val(0); $('#cv-banner-form input,#cv-banner-form select').val(''); $('#cv-b-ativo').val('1'); $('#cv-b-posicao').val('apos_letra'); $('#cv-b-imagem-preview').hide(); $('#cv-banner-form').slideToggle(180); });
            $('#cv-banner-cancelar').on('click',function(){ $('#cv-banner-form').slideUp(180); });

            // Media Library
            $(document).on('click','.cv-media-pick',function(){
                var target = $(this).data('target');
                var frame = wp.media({ title:'Selecionar imagem', button:{text:'Usar imagem'}, multiple:false, library:{type:'image'} });
                frame.on('select',function(){ var a=frame.state().get('selection').first().toJSON(); $('#'+target).val(a.url); if(target==='cv-b-imagem'){ $('#cv-b-imagem-preview').attr('src',a.url).show(); } });
                frame.open();
            });
            $('#cv-b-imagem').on('change',function(){ var u=$.trim($(this).val()); $('#cv-b-imagem-preview').attr('src',u)[u?'show':'hide'](); });

            // Editar
            $(document).on('click','.cv-banner-editar',function(){
                var d = $(this).data('banner');
                $('#cv-banner-id').val(d.id);
                $('#cv-b-titulo').val(d.titulo);
                $('#cv-b-posicao').val(d.posicao);
                $('#cv-b-imagem').val(d.imagem_url);
                $('#cv-b-imagem-preview').attr('src',d.imagem_url).show();
                $('#cv-b-url').val(d.url_destino);
                $('#cv-b-alt').val(d.texto_alt);
                $('#cv-b-ativo').val(d.ativo);
                $('#cv-b-inicio').val(d.data_inicio||'');
                $('#cv-b-fim').val(d.data_fim||'');
                $('#cv-banner-form').slideDown(180);
                $('html,body').animate({scrollTop:$('#cv-banner-form').offset().top-40},300);
            });

            // Salvar
            $('#cv-banner-salvar').on('click',function(){
                var $btn=$(this).prop('disabled',true).text('Salvando...');
                $.post(ajax,{
                    action:'cv_save_banner', nonce:nonce,
                    id:$('#cv-banner-id').val(), titulo:$('#cv-b-titulo').val(),
                    posicao:$('#cv-b-posicao').val(), imagem_url:$('#cv-b-imagem').val(),
                    url_destino:$('#cv-b-url').val(), texto_alt:$('#cv-b-alt').val(),
                    ativo:$('#cv-b-ativo').val(), data_inicio:$('#cv-b-inicio').val(),
                    data_fim:$('#cv-b-fim').val()
                },function(r){
                    if(r.success){ msg('✅ Banner salvo!',true); setTimeout(function(){location.reload();},1200); }
                    else{ msg('❌ Erro ao salvar.',false); $btn.prop('disabled',false).text('💾 Salvar Banner'); }
                });
            });

            // Excluir
            $(document).on('click','.cv-banner-excluir',function(){
                var id=$(this).data('id');
                if(!confirm('Excluir este banner?')) return;
                $.post(ajax,{action:'cv_delete_banner',nonce:nonce,id:id},function(r){
                    if(r.success){ $('#cv-banner-row-'+id).fadeOut(300,function(){$(this).remove();}); msg('✅ Excluído.',true); }
                });
            });
        });
        </script>
        <?php
    }

    // ════════════════════════════════════════════════════════════════
    // LOJA
    // ════════════════════════════════════════════════════════════════
    public static function page_loja() {
        wp_enqueue_media();
        global $wpdb;
        $produtos  = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_produtos ORDER BY ordem ASC, id DESC" );
        $categorias = array( 'ebook' => '📖 E-book', 'fisico' => '🎁 Físico (caneca, camiseta, pendrive)', 'digital' => '💿 Digital (download)' );
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>🛒 Loja</h1>
                <p class="cv-admin-subtitle"><?php echo count( $produtos ); ?> produto(s) cadastrado(s)</p>
                <button id="cv-prod-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">+ Novo Produto</button>
            </div>
            <div id="cv-prod-msg" class="cv-action-message" style="display:none"></div>

            <div id="cv-prod-form" class="cv-section" style="display:none">
                <h2 class="cv-section-title">Cadastrar / Editar Produto</h2>
                <input type="hidden" id="cv-prod-id" value="0" />
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:900px">

                    <div class="cv-form-group">
                        <label class="cv-form-label">Nome do produto *</label>
                        <input type="text" id="cv-p-nome" class="cv-input" placeholder="Ex: E-book 100 Letras Sertanejas" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Categoria *</label>
                        <select id="cv-p-categoria" class="cv-input">
                            <?php foreach ( $categorias as $v => $l ) : ?>
                            <option value="<?php echo esc_attr( $v ); ?>"><?php echo esc_html( $l ); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Descrição</label>
                        <textarea id="cv-p-desc" class="cv-input" rows="2" placeholder="Breve descrição do produto (até 200 caracteres)"></textarea>
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Preço (R$) *</label>
                        <input type="text" id="cv-p-preco" class="cv-input" placeholder="29,90" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Preço antigo (riscado) — opcional</label>
                        <input type="text" id="cv-p-preco-antigo" class="cv-input" placeholder="49,90" />
                    </div>

                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">URL de compra / checkout *</label>
                        <input type="text" id="cv-p-url" class="cv-input" placeholder="https://loja.exemplo.com/produto" />
                    </div>

                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Imagem do produto</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" id="cv-p-imagem" class="cv-input" placeholder="URL ou use a biblioteca" style="flex:1" />
                            <button type="button" class="cv-btn cv-btn-outline cv-media-pick-prod" data-target="cv-p-imagem">📁 Biblioteca</button>
                        </div>
                        <img id="cv-p-imagem-preview" src="" alt="" style="display:none;margin-top:8px;max-height:80px;border-radius:6px" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Texto do botão</label>
                        <input type="text" id="cv-p-botao" class="cv-input" placeholder="Comprar agora" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Badge (opcional)</label>
                        <input type="text" id="cv-p-badge" class="cv-input" placeholder="Ex: Mais vendido | Novo | Promoção" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Ordem de exibição</label>
                        <input type="number" id="cv-p-ordem" class="cv-input" value="0" min="0" style="width:100px" />
                    </div>

                    <div class="cv-form-group">
                        <label class="cv-form-label">Ativo</label>
                        <select id="cv-p-ativo" class="cv-input">
                            <option value="1">✅ Sim</option>
                            <option value="0">❌ Não</option>
                        </select>
                    </div>
                </div>

                <div style="display:flex;gap:10px;margin-top:16px">
                    <button id="cv-prod-salvar" class="cv-btn cv-btn-primary">💾 Salvar Produto</button>
                    <button id="cv-prod-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
                </div>

                <div class="cv-section" style="margin-top:20px;padding:14px;background:#FFFFFF;border-radius:8px;font-size:13px;color:#8A6A55">
                    <strong style="color:#7B3A22">Shortcode da loja:</strong><br>
                    <code style="color:#6B4C3B">[cv_loja]</code> — exibe todos os produtos<br>
                    <code style="color:#6B4C3B">[cv_loja categoria="ebook" titulo="Nossos E-books"]</code><br>
                    <code style="color:#6B4C3B">[cv_loja categoria="fisico" colunas="2"]</code>
                </div>
            </div>

            <div class="cv-section">
                <?php if ( empty( $produtos ) ) : ?>
                <p class="cv-empty" style="font-size:13px;color:#6B4C3B;padding:12px 0">Nenhum produto cadastrado ainda. Use o formulário acima para adicionar o primeiro.</p>
                <?php else : ?>
                <table class="cv-table">
                    <thead><tr><th style="width:70px">Img</th><th>Nome</th><th>Categoria</th><th>Preço</th><th style="text-align:center">Ativo</th><th style="text-align:center">Ações</th></tr></thead>
                    <tbody>
                    <?php foreach ( $produtos as $p ) : ?>
                    <tr id="cv-prod-row-<?php echo esc_attr( $p->id ); ?>">
                        <td>
                            <?php if ( $p->imagem_url ) : ?>
                            <img src="<?php echo esc_url( $p->imagem_url ); ?>" style="width:56px;height:56px;object-fit:cover;border-radius:4px" alt="" />
                            <?php else : ?>
                            <div style="width:56px;height:56px;background:#FFFFFF;border-radius:4px;display:flex;align-items:center;justify-content:center;font-size:22px">🎁</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong style="color:var(--cv-text)"><?php echo esc_html( $p->nome ); ?></strong>
                            <?php if ( $p->badge ) : ?>
                            <span style="background:rgba(242,165,26,0.2);color:#7B3A22;font-size:10px;padding:1px 7px;border-radius:20px;margin-left:6px"><?php echo esc_html( $p->badge ); ?></span>
                            <?php endif; ?>
                        </td>
                        <td style="color:#8A6A55;font-size:13px"><?php echo esc_html( $categorias[ $p->categoria ] ?? $p->categoria ); ?></td>
                        <td style="color:#7B3A22;font-weight:700">R$ <?php echo number_format( (float) $p->preco, 2, ',', '.' ); ?></td>
                        <td style="text-align:center"><?php echo $p->ativo ? '<span style="color:#1C7C44">●</span>' : '<span style="color:#8A6A55">○</span>'; ?></td>
                        <td style="text-align:center">
                            <div style="display:flex;gap:6px;justify-content:center">
                                <button class="cv-btn cv-btn-outline cv-prod-editar" data-prod='<?php echo esc_attr( json_encode( $p ) ); ?>' style="padding:4px 10px;font-size:11px">✏</button>
                                <button class="cv-btn cv-prod-excluir" data-id="<?php echo esc_attr( $p->id ); ?>" style="padding:4px 10px;font-size:11px;background:rgba(192,57,43,.15);color:#D62C1A;border:1px solid rgba(192,57,43,.3)">🗑</button>
                            </div>
                        </td>
                        <td style="text-align:right">
                            <?php if ( 'agendado' === $s->status ) : ?>
                            <button class="cv-btn cv-btn-sm cv-sort-anunciar-btn"
                                    data-id="<?php echo esc_attr($s->id); ?>"
                                    style="background:#F2A51A;color:#3B2418;font-size:11px;padding:5px 12px;border:none;border-radius:4px;cursor:pointer;font-weight:700">
                                📧 Anunciar
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js(wp_create_nonce('cv_admin_nonce'));?>';
            var ajax='<?php echo esc_js(admin_url('admin-ajax.php'));?>';

            // Botao Anunciar Sorteio
            $(document).on('click', '.cv-sort-anunciar-btn', function(){
                var id  = $(this).data('id');
                var $btn = $(this);
                if (!confirm('Enviar e-mail de anuncio deste sorteio para todos os assinantes?')) { return; }
                $btn.prop('disabled',true).text('Enviando...');
                $.post(ajax, { action:'cv_email_anunciar_sorteio', nonce:nonce, sorteio_id:id }, function(res){
                    if (res.success) {
                        msg(res.data.message, true);
                        $btn.text('✓ Anunciado');
                    } else {
                        msg(res.data.message || 'Erro ao anunciar.', false);
                        $btn.prop('disabled',false).text('📧 Anunciar');
                    }
                }).fail(function(){ msg('Erro de conexao.', false); $btn.prop('disabled',false).text('📧 Anunciar'); });
            });
            function msg(t,ok){var $m=$('#cv-prod-msg');$m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},3000);}
            $('#cv-prod-novo-btn').on('click',function(){$('#cv-prod-id').val(0);$('#cv-prod-form input,#cv-prod-form textarea,#cv-prod-form select').val('');$('#cv-p-ativo').val('1');$('#cv-p-ordem').val('0');$('#cv-p-imagem-preview').hide();$('#cv-prod-form').slideToggle(180);});
            $('#cv-prod-cancelar').on('click',function(){$('#cv-prod-form').slideUp(180);});
            $(document).on('click','.cv-media-pick-prod',function(){var frame=wp.media({title:'Selecionar imagem',button:{text:'Usar'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#cv-p-imagem').val(a.url);$('#cv-p-imagem-preview').attr('src',a.url).show();});frame.open();});
            $('#cv-p-imagem').on('change',function(){var u=$.trim($(this).val());$('#cv-p-imagem-preview').attr('src',u)[u?'show':'hide']();});
            $(document).on('click','.cv-prod-editar',function(){var d=$(this).data('prod');$('#cv-prod-id').val(d.id);$('#cv-p-nome').val(d.nome);$('#cv-p-categoria').val(d.categoria);$('#cv-p-desc').val(d.descricao);$('#cv-p-preco').val(parseFloat(d.preco).toFixed(2).replace('.',','));$('#cv-p-preco-antigo').val(d.preco_antigo>0?parseFloat(d.preco_antigo).toFixed(2).replace('.',','):'');$('#cv-p-url').val(d.url_compra);$('#cv-p-imagem').val(d.imagem_url);if(d.imagem_url){$('#cv-p-imagem-preview').attr('src',d.imagem_url).show();}$('#cv-p-botao').val(d.texto_botao);$('#cv-p-badge').val(d.badge);$('#cv-p-ordem').val(d.ordem);$('#cv-p-ativo').val(d.ativo);$('#cv-prod-form').slideDown(180);$('html,body').animate({scrollTop:$('#cv-prod-form').offset().top-40},300);});
            $('#cv-prod-salvar').on('click',function(){var $b=$(this).prop('disabled',true).text('Salvando...');$.post(ajax,{action:'cv_save_produto',nonce:nonce,id:$('#cv-prod-id').val(),nome:$('#cv-p-nome').val(),categoria:$('#cv-p-categoria').val(),descricao:$('#cv-p-desc').val(),preco:$('#cv-p-preco').val(),preco_antigo:$('#cv-p-preco-antigo').val(),imagem_url:$('#cv-p-imagem').val(),url_compra:$('#cv-p-url').val(),texto_botao:$('#cv-p-botao').val(),badge:$('#cv-p-badge').val(),ordem:$('#cv-p-ordem').val(),ativo:$('#cv-p-ativo').val()},function(r){if(r.success){msg('✅ Produto salvo!',true);setTimeout(function(){location.reload();},1200);}else{msg('❌ Erro.',false);$b.prop('disabled',false).text('💾 Salvar Produto');}});});
            $(document).on('click','.cv-prod-excluir',function(){var id=$(this).data('id');if(!confirm('Excluir este produto?'))return;$.post(ajax,{action:'cv_delete_produto',nonce:nonce,id:id},function(r){if(r.success){$('#cv-prod-row-'+id).fadeOut(300,function(){$(this).remove();});msg('✅ Excluído.',true);}});});
        });
        </script>
        <?php
    }

    // ════════════════════════════════════════════════════════════════
    // SORTEIOS
    // ════════════════════════════════════════════════════════════════
    public static function page_sorteios() {
        wp_enqueue_media();
        global $wpdb;
        $sorteios = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_sorteios ORDER BY data_sorteio DESC LIMIT 50" );
        $total_subs = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}cv_subscribers" );
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>🎰 Sorteios</h1>
                <p class="cv-admin-subtitle">
                    <?php echo count( $sorteios ); ?> sorteio(s) •
                    <strong style="color:#7B3A22"><?php echo $total_subs; ?></strong> assinantes elegíveis
                </p>
                <button id="cv-sort-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">+ Novo Sorteio</button>
            </div>
            <div id="cv-sort-msg" class="cv-action-message" style="display:none"></div>

            <div id="cv-sort-form" class="cv-section" style="display:none">
                <h2 class="cv-section-title">Agendar Sorteio</h2>
                <input type="hidden" id="cv-sort-id" value="0" />
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:760px">
                    <div class="cv-form-group">
                        <label class="cv-form-label">Título do sorteio *</label>
                        <input type="text" id="cv-s-titulo" class="cv-input" placeholder="Ex: Sorteio Canção Verdadeira — Junho" />
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Data e hora do sorteio *</label>
                        <input type="datetime-local" id="cv-s-data" class="cv-input" />
                    </div>
                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Prêmio *</label>
                        <input type="text" id="cv-s-premio" class="cv-input" placeholder="Ex: Kit Canção Verdadeira (camiseta + caneca + e-book)" />
                    </div>
                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Descrição / Regulamento</label>
                        <textarea id="cv-s-desc" class="cv-input" rows="3" placeholder="Detalhes do sorteio, regras de participação..."></textarea>
                    </div>
                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Imagem do prêmio (opcional)</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" id="cv-s-imagem" class="cv-input" placeholder="URL da imagem" style="flex:1" />
                            <button type="button" class="cv-btn cv-btn-outline cv-media-pick-sort" data-target="cv-s-imagem">📁 Biblioteca</button>
                        </div>
                    </div>
                </div>
                <p style="font-size:13px;color:#8A6A55;margin-top:12px">
                    ℹ O sorteio será realizado automaticamente na data/hora definida. O vencedor será escolhido aleatoriamente entre os <strong style="color:#7B3A22"><?php echo $total_subs; ?></strong> assinantes e receberá um e-mail automático.
                </p>
                <div style="display:flex;gap:10px;margin-top:16px">
                    <button id="cv-sort-salvar" class="cv-btn cv-btn-primary">🎰 Agendar Sorteio</button>
                    <button id="cv-sort-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
                </div>
            </div>

            <div class="cv-section">
                <?php if ( empty( $sorteios ) ) : ?>
                <p class="cv-empty" style="font-size:13px;color:#6B4C3B;padding:12px 0">Nenhum sorteio cadastrado ainda. Use o formulário acima para criar o primeiro.</p>
                <?php else :
                $status_labels = array(
                    'agendado'          => array( '⏳ Agendado',   '#B8700C' ),
                    'realizado'         => array( '✅ Realizado',  '#27ae60' ),
                    'sem_participantes' => array( '⚠ Sem participantes', '#e74c3c' ),
                );
                ?>
                <table class="cv-table">
                    <thead>
                        <tr><th>Título</th><th>Prêmio</th><th>Data</th><th style="text-align:center">Status</th><th>Vencedor</th><th style="text-align:center">Participantes</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ( $sorteios as $s ) :
                        $sl = $status_labels[ $s->status ] ?? array( $s->status, '#C9A27E' );
                    ?>
                    <tr>
                        <td><strong style="color:var(--cv-text)"><?php echo esc_html( $s->titulo ); ?></strong></td>
                        <td style="color:#6B4C3B;font-size:13px"><?php echo esc_html( $s->premio ); ?></td>
                        <td style="font-size:12px;color:#8A6A55"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $s->data_sorteio ) ) ); ?></td>
                        <td style="text-align:center">
                            <span style="color:<?php echo esc_attr( $sl[1] ); ?>;font-size:13px;font-weight:700"><?php echo esc_html( $sl[0] ); ?></span>
                        </td>
                        <td style="font-size:13px">
                            <?php if ( $s->vencedor_email ) : ?>
                                <strong style="color:#7B3A22"><?php echo esc_html( $s->vencedor_nome ); ?></strong><br>
                                <small style="color:#8A6A55"><?php echo esc_html( $s->vencedor_email ); ?></small>
                            <?php else : ?>
                                <span style="color:#8A6A55">—</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:center;color:#8A6A55;font-size:13px"><?php echo $s->total_participantes ? number_format( $s->total_participantes ) : '—'; ?></td>
                        <td style="text-align:right">
                            <?php if ( 'agendado' === $s->status ) : ?>
                            <button class="cv-btn cv-btn-sm cv-sort-anunciar-btn"
                                    data-id="<?php echo esc_attr($s->id); ?>"
                                    style="background:#F2A51A;color:#3B2418;font-size:11px;padding:5px 12px;border:none;border-radius:4px;cursor:pointer;font-weight:700">
                                📧 Anunciar
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js(wp_create_nonce('cv_admin_nonce'));?>';
            var ajax='<?php echo esc_js(admin_url('admin-ajax.php'));?>';

            // Botao Anunciar Sorteio
            $(document).on('click', '.cv-sort-anunciar-btn', function(){
                var id  = $(this).data('id');
                var $btn = $(this);
                if (!confirm('Enviar e-mail de anuncio deste sorteio para todos os assinantes?')) { return; }
                $btn.prop('disabled',true).text('Enviando...');
                $.post(ajax, { action:'cv_email_anunciar_sorteio', nonce:nonce, sorteio_id:id }, function(res){
                    if (res.success) {
                        msg(res.data.message, true);
                        $btn.text('✓ Anunciado');
                    } else {
                        msg(res.data.message || 'Erro ao anunciar.', false);
                        $btn.prop('disabled',false).text('📧 Anunciar');
                    }
                }).fail(function(){ msg('Erro de conexao.', false); $btn.prop('disabled',false).text('📧 Anunciar'); });
            });
            function msg(t,ok){var $m=$('#cv-sort-msg');$m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},3500);}
            $('#cv-sort-novo-btn').on('click',function(){$('#cv-sort-id').val(0);$('#cv-sort-form input,#cv-sort-form textarea').val('');$('#cv-sort-form').slideToggle(180);});
            $('#cv-sort-cancelar').on('click',function(){$('#cv-sort-form').slideUp(180);});
            $(document).on('click','.cv-media-pick-sort',function(){var frame=wp.media({title:'Selecionar imagem',button:{text:'Usar'},multiple:false});frame.on('select',function(){$('#cv-s-imagem').val(frame.state().get('selection').first().toJSON().url);});frame.open();});
            $('#cv-sort-salvar').on('click',function(){
                var $b=$(this).prop('disabled',true).text('Agendando...');
                $.post(ajax,{action:'cv_save_sorteio',nonce:nonce,id:$('#cv-sort-id').val(),titulo:$('#cv-s-titulo').val(),descricao:$('#cv-s-desc').val(),premio:$('#cv-s-premio').val(),imagem_url:$('#cv-s-imagem').val(),data_sorteio:$('#cv-s-data').val().replace('T',' ')},function(r){
                    if(r.success){msg('✅ Sorteio agendado!',true);setTimeout(function(){location.reload();},1200);}
                    else{msg('❌ Erro.',false);$b.prop('disabled',false).text('🎰 Agendar Sorteio');}
                });
            });
        });
        </script>
        <?php
    }

    // ════════════════════════════════════════════════════════════════
    // BRINDES
    // ════════════════════════════════════════════════════════════════
    public static function page_brindes() {
        wp_enqueue_media();
        global $wpdb;
        $brindes   = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}cv_brindes ORDER BY id DESC" );
        $entregas  = $wpdb->get_results( "SELECT e.*, b.titulo AS brinde_titulo FROM {$wpdb->prefix}cv_brindes_entregas e LEFT JOIN {$wpdb->prefix}cv_brindes b ON b.id = e.brinde_id ORDER BY e.enviado_em DESC LIMIT 50" );
        $assinantes= $wpdb->get_results( "SELECT email, name FROM {$wpdb->prefix}cv_subscribers ORDER BY name ASC LIMIT 500" );
        ?>
        <div id="cv-admin-page" class="cv-admin-wrap">
            <div class="cv-admin-header">
                <h1>🎁 Brindes</h1>
                <p class="cv-admin-subtitle"><?php echo count( $brindes ); ?> brinde(s) • <?php echo count( $entregas ); ?> entrega(s) realizada(s)</p>
                <button id="cv-brinde-novo-btn" class="cv-btn cv-btn-primary" style="margin-left:auto">+ Novo Brinde</button>
            </div>
            <div id="cv-brinde-msg" class="cv-action-message" style="display:none"></div>

            <!-- Cadastrar brinde -->
            <div id="cv-brinde-form" class="cv-section" style="display:none">
                <h2 class="cv-section-title">Cadastrar Brinde</h2>
                <input type="hidden" id="cv-br-id" value="0" />
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;max-width:760px">
                    <div class="cv-form-group">
                        <label class="cv-form-label">Nome do brinde *</label>
                        <input type="text" id="cv-br-titulo" class="cv-input" placeholder="Ex: Caneca Canção Verdadeira" />
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Quantidade disponível</label>
                        <input type="number" id="cv-br-qtd" class="cv-input" value="1" min="1" style="width:120px" />
                    </div>
                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Descrição</label>
                        <textarea id="cv-br-desc" class="cv-input" rows="2" placeholder="Detalhes do brinde..."></textarea>
                    </div>
                    <div class="cv-form-group" style="grid-column:1/-1">
                        <label class="cv-form-label">Imagem</label>
                        <div style="display:flex;gap:8px">
                            <input type="text" id="cv-br-imagem" class="cv-input" placeholder="URL da imagem" style="flex:1" />
                            <button type="button" class="cv-btn cv-btn-outline cv-media-pick-br" data-target="cv-br-imagem">📁 Biblioteca</button>
                        </div>
                    </div>
                </div>
                <div style="display:flex;gap:10px;margin-top:16px">
                    <button id="cv-brinde-salvar" class="cv-btn cv-btn-primary">💾 Salvar Brinde</button>
                    <button id="cv-brinde-cancelar" class="cv-btn cv-btn-outline">Cancelar</button>
                </div>
            </div>

            <!-- Enviar brinde -->
            <div class="cv-section">
                <h2 class="cv-section-title">📤 Enviar Brinde para um Membro</h2>
                <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;max-width:900px">
                    <div class="cv-form-group">
                        <label class="cv-form-label">Brinde *</label>
                        <select id="cv-env-brinde" class="cv-input">
                            <option value="">— Selecione —</option>
                            <?php foreach ( $brindes as $b ) :
                                if ( $b->status === 'esgotado' ) { continue; }
                            ?>
                            <option value="<?php echo esc_attr( $b->id ); ?>">
                                <?php echo esc_html( $b->titulo . ' (' . $b->quantidade . ' disp.)' ); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">E-mail do destinatário *</label>
                        <input type="text" id="cv-env-email" class="cv-input" list="cv-subs-list" placeholder="e-mail do assinante" />
                        <datalist id="cv-subs-list">
                            <?php foreach ( $assinantes as $s ) : ?>
                            <option value="<?php echo esc_attr( $s->email ); ?>"><?php echo esc_attr( $s->name ?: $s->email ); ?></option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>
                    <div class="cv-form-group">
                        <label class="cv-form-label">Mensagem pessoal (opcional)</label>
                        <input type="text" id="cv-env-msg" class="cv-input" placeholder="Parabéns pela participação!" />
                    </div>
                </div>
                <button id="cv-env-brinde-btn" class="cv-btn cv-btn-primary">📤 Enviar Brinde + E-mail</button>
            </div>

            <!-- Brindes cadastrados -->
            <div class="cv-section">
                <h2 class="cv-section-title">Brindes cadastrados</h2>
                <?php if ( empty( $brindes ) ) : ?>
                <p class="cv-empty">Nenhum brinde cadastrado ainda.</p>
                <?php else : ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
                    <?php foreach ( $brindes as $b ) : ?>
                    <div style="background:#FFFFFF;border:1px solid <?php echo $b->status==='esgotado' ? '#F3E6D3' : '#F8F0E4'; ?>;border-radius:10px;padding:16px;opacity:<?php echo $b->status==='esgotado' ? '0.5' : '1'; ?>">
                        <?php if ( $b->imagem_url ) : ?>
                        <img src="<?php echo esc_url( $b->imagem_url ); ?>" style="width:100%;height:100px;object-fit:cover;border-radius:6px;margin-bottom:10px" alt="" />
                        <?php endif; ?>
                        <div style="font-weight:700;color:var(--cv-text);margin-bottom:4px"><?php echo esc_html( $b->titulo ); ?></div>
                        <div style="font-size:12px;color:#8A6A55;margin-bottom:8px"><?php echo esc_html( $b->descricao ?: '—' ); ?></div>
                        <div style="display:flex;justify-content:space-between;align-items:center">
                            <span style="font-size:13px;color:<?php echo $b->quantidade > 0 ? '#27ae60' : '#e74c3c'; ?>;font-weight:700">
                                <?php echo $b->status === 'esgotado' ? '⚠ Esgotado' : $b->quantidade . ' disponível(eis)'; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Histórico de entregas -->
            <?php if ( ! empty( $entregas ) ) : ?>
            <div class="cv-section">
                <h2 class="cv-section-title">Histórico de Entregas</h2>
                <table class="cv-table">
                    <thead><tr><th>Data</th><th>Brinde</th><th>Destinatário</th><th>E-mail</th></tr></thead>
                    <tbody>
                    <?php foreach ( $entregas as $e ) : ?>
                    <tr>
                        <td style="font-size:12px;color:#8A6A55"><?php echo esc_html( date( 'd/m/Y H:i', strtotime( $e->enviado_em ) ) ); ?></td>
                        <td style="color:#6B4C3B"><?php echo esc_html( $e->brinde_titulo ); ?></td>
                        <td><?php echo esc_html( $e->nome ?: '—' ); ?></td>
                        <td style="color:#8A6A55;font-size:13px"><?php echo esc_html( $e->email ); ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <script>
        jQuery(function($){
            var nonce='<?php echo esc_js(wp_create_nonce('cv_admin_nonce'));?>';
            var ajax='<?php echo esc_js(admin_url('admin-ajax.php'));?>';
            function msg(t,ok){var $m=$('#cv-brinde-msg');$m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},4000);}
            $('#cv-brinde-novo-btn').on('click',function(){$('#cv-br-id').val(0);$('#cv-brinde-form input,#cv-brinde-form textarea').val('');$('#cv-br-qtd').val('1');$('#cv-brinde-form').slideToggle(180);});
            $('#cv-brinde-cancelar').on('click',function(){$('#cv-brinde-form').slideUp(180);});
            $(document).on('click','.cv-media-pick-br',function(){var frame=wp.media({title:'Selecionar imagem',button:{text:'Usar'},multiple:false});frame.on('select',function(){$('#cv-br-imagem').val(frame.state().get('selection').first().toJSON().url);});frame.open();});
            $('#cv-brinde-salvar').on('click',function(){var $b=$(this).prop('disabled',true).text('Salvando...');$.post(ajax,{action:'cv_save_brinde',nonce:nonce,id:$('#cv-br-id').val(),titulo:$('#cv-br-titulo').val(),descricao:$('#cv-br-desc').val(),imagem_url:$('#cv-br-imagem').val(),quantidade:$('#cv-br-qtd').val()},function(r){if(r.success){msg('✅ Brinde salvo!',true);setTimeout(function(){location.reload();},1200);}else{msg('❌ Erro.',false);$b.prop('disabled',false).text('💾 Salvar Brinde');}});});
            $('#cv-env-brinde-btn').on('click',function(){
                var bid=$('#cv-env-brinde').val(), email=$('#cv-env-email').val(), msgtxt=$('#cv-env-msg').val();
                if(!bid||!email){msg('❌ Selecione o brinde e informe o e-mail.',false);return;}
                var $b=$(this).prop('disabled',true).text('Enviando...');
                $.post(ajax,{action:'cv_enviar_brinde',nonce:nonce,brinde_id:bid,email:email,mensagem:msgtxt},function(r){
                    if(r.success){msg('✅ '+r.data.message,true);$('#cv-env-brinde').val('');$('#cv-env-email').val('');$('#cv-env-msg').val('');}
                    else{msg('❌ '+(r.data&&r.data.message?r.data.message:'Erro.'),false);}
                    $b.prop('disabled',false).text('📤 Enviar Brinde + E-mail');
                });
            });
        });
        </script>
        <?php
    }
}

CV_Monetization_Pages::init();
