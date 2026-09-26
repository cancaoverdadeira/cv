<?php
// cancao-verdadeira/includes/apoio/class-cv-envio-area.php
// Criado em: 25/09/2026 (plugin v2.50.0)
// Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
// HTML da aba "🎤 Enviar música" da Minha Área: um cartão por envio, com as
// 4 etapas (1 Contrato assinado · 2 PIX da taxa · 3 YouTube · 4 Dados da
// música). A etapa atual fica aberta; as concluídas mostram um resumo.
// A aba só aparece para quem já mandou proposta em "Seja nosso parceiro"
// (mesmo e-mail) ou já tem envios. Regras e AJAX: CV_Envio.
// CSS/JS: assets/css/cv-envio.css e assets/js/cv-envio.js.

if ( ! defined( 'ABSPATH' ) ) { exit; }

class CV_Envio_Area {

    const ASSINADOR_GOVBR = 'https://assinador.iti.br/';
    const AJUDA_GOVBR     = 'https://www.gov.br/pt-br/servicos/assinatura-eletronica';

    public static function init() {
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ), 32 );
    }

    public static function enqueue() {
        if ( ! is_user_logged_in() || ! is_page_template( 'templates/page-user-dashboard.php' ) ) { return; }
        wp_enqueue_style( 'cv-envio', CV_PLUGIN_URL . 'assets/css/cv-envio.css', array( 'cv-pix' ), CV_VERSION );
        wp_enqueue_script( 'cv-envio', CV_PLUGIN_URL . 'assets/js/cv-envio.js', array( 'jquery', 'cv-pix' ), CV_VERSION, true );
        wp_add_inline_script( 'cv-envio', 'window.cvEnvio = ' . wp_json_encode( array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'cv_envio_nonce' ),
        ) ) . ';', 'before' );
    }

    public static function url_baixar( $e, $doc ) {
        return add_query_arg( array( 'action' => 'cv_envio_baixar', 'doc' => $doc, 'envio_id' => (int) $e->id, 'nonce' => wp_create_nonce( 'cv_envio_nonce' ) ), admin_url( 'admin-ajax.php' ) );
    }

    /** Conteúdo da aba: explicação, botão "Enviar nova música" e os cartões. */
    public static function lista( $user_id ) {
        if ( ! CV_Envio::pode_ver( $user_id ) ) {
            return '<div class="cv-empty">🎤 Para enviar sua música, primeiro mande uma proposta na página <a href="' . esc_url( CV_Apoio::url_artistas() ) . '"><strong>Para artistas</strong></a>, usando o mesmo e-mail da sua conta.</div>';
        }
        $envios = CV_Envio::do_usuario( $user_id );
        ob_start();
        ?>
        <div class="cv-envio-topo">
            <p class="cv-envio-intro">Envie sua música para divulgação em <strong>4 passos</strong>: contrato assinado, PIX da taxa, link do YouTube e os dados da música. Você pode parar e continuar depois.</p>
            <button type="button" class="cv-btn cv-btn-primary cv-envio-novo">➕ Enviar nova música</button>
        </div>
        <div id="cv-envio-msg-geral" class="cv-envio-msg" role="status" aria-live="polite" hidden></div>
        <?php if ( empty( $envios ) ) : ?>
        <div class="cv-empty">Nenhum envio ainda. Clique em <strong>"Enviar nova música"</strong> para começar.</div>
        <?php endif; ?>
        <?php foreach ( $envios as $e ) { echo self::cartao( $e ); } ?>
        <?php
        return ob_get_clean();
    }

    /** Um cartão de envio (é o que o servidor devolve depois de cada passo). */
    public static function cartao( $e ) {
        if ( ! $e ) { return ''; }
        $etapa   = (int) $e->etapa;
        $rotulos = array( 1 => 'Contrato', 2 => 'PIX', 3 => 'YouTube', 4 => 'Dados' );
        $situacao = array(
            'rascunho' => array( 'Em andamento', 'andamento' ),
            'enviado'  => array( 'Aguardando aprovação', 'aguardando' ),
            'aprovado' => array( 'Aprovado', 'aprovado' ),
            'recusado' => array( 'Recusado', 'recusado' ),
        );
        $st = isset( $situacao[ $e->status ] ) ? $situacao[ $e->status ] : array( $e->status, 'andamento' );
        $aberto = in_array( $e->status, array( 'rascunho', 'enviado' ), true );
        ob_start();
        ?>
        <article class="cv-envio-card" id="cv-envio-<?php echo (int) $e->id; ?>" data-id="<?php echo (int) $e->id; ?>">
            <header class="cv-envio-cab">
                <h3>🎵 <?php echo esc_html( $e->titulo ? $e->titulo : 'Nova música' ); ?></h3>
                <span class="cv-envio-selo is-<?php echo esc_attr( $st[1] ); ?>"><?php echo esc_html( $st[0] ); ?></span>
                <span class="cv-envio-num">Envio #<?php echo (int) $e->id; ?></span>
            </header>

            <ol class="cv-envio-passos" aria-label="Etapas">
                <?php foreach ( $rotulos as $n => $r ) :
                    $cls = $etapa > $n ? 'is-feito' : ( $etapa === $n && $aberto ? 'is-atual' : '' ); ?>
                <li class="<?php echo esc_attr( $cls ); ?>"><span><?php echo $etapa > $n ? '✓' : (int) $n; ?></span> <?php echo esc_html( $r ); ?></li>
                <?php endforeach; ?>
            </ol>

            <div class="cv-envio-msg" role="status" aria-live="polite" hidden></div>

            <?php
            echo self::passo_contrato( $e, $aberto );
            if ( $etapa >= 2 ) { echo self::passo_pix( $e, $aberto ); }
            if ( $etapa >= 3 ) { echo self::passo_youtube( $e, $aberto ); }
            if ( $etapa >= 4 ) { echo self::passo_dados( $e, $aberto ); }
            echo self::rodape( $e );
            ?>
        </article>
        <?php
        return ob_get_clean();
    }

    // ── 1. Contrato ───────────────────────────────────────────────
    private static function passo_contrato( $e, $aberto ) {
        $info = $e->contrato_info ? json_decode( $e->contrato_info, true ) : array();
        $tem  = '' !== $e->contrato_arquivo;
        $sit  = array( 'pendente' => '⏳ aguardando conferência', 'aprovado' => '✅ aprovado', 'recusado' => '✖ recusado' );
        ob_start();
        ?>
        <section class="cv-envio-passo<?php echo $tem && 'recusado' !== $e->contrato_status ? ' is-resumo' : ''; ?>">
            <h4>1. Contrato assinado</h4>
            <?php if ( $tem ) : ?>
            <p class="cv-envio-ok">
                ✅ Assinatura digital de <strong><?php echo esc_html( ! empty( $info['assinante'] ) ? $info['assinante'] : 'assinante identificado' ); ?></strong>
                <?php if ( ! empty( $info['emissor'] ) ) : ?>(certificado: <?php echo esc_html( $info['emissor'] ); ?>)<?php endif; ?>
                — <?php echo esc_html( $sit[ $e->contrato_status ] ?? $e->contrato_status ); ?>.
            </p>
            <?php endif; ?>
            <?php if ( 'recusado' === $e->contrato_status && $e->obs_admin ) : ?>
            <p class="cv-envio-alerta">O contrato foi recusado: <?php echo esc_html( $e->obs_admin ); ?> Envie de novo.</p>
            <?php endif; ?>

            <?php if ( $aberto && ( ! $tem || 'recusado' === $e->contrato_status ) ) : ?>
            <ol class="cv-envio-como">
                <li><a href="<?php echo esc_url( self::url_baixar( $e, 'contrato' ) ); ?>">⬇️ <strong>Baixe o contrato</strong> (Word)</a>, já com os seus dados.</li>
                <li>Abra no Word (ou LibreOffice), confira e escolha <strong>Salvar como → PDF</strong>.</li>
                <li>Assine <strong>de graça</strong> no <a href="<?php echo esc_url( self::ASSINADOR_GOVBR ); ?>" target="_blank" rel="noopener">assinador do gov.br</a>: entre com sua conta gov.br (nível prata ou ouro), envie o PDF, posicione a assinatura e <strong>baixe o PDF assinado</strong>. <a href="<?php echo esc_url( self::AJUDA_GOVBR ); ?>" target="_blank" rel="noopener">Como funciona</a>.</li>
                <li>Envie aqui o <strong>PDF assinado</strong>, sem abrir nem salvar de novo (qualquer mudança invalida a assinatura).</li>
            </ol>
            <form class="cv-envio-form" data-acao="contrato">
                <label class="cv-envio-arquivo">PDF assinado (até 10 MB)
                    <input type="file" name="arquivo" accept=".pdf,application/pdf" required>
                </label>
                <button type="submit" class="cv-btn cv-btn-primary">📤 Enviar contrato assinado</button>
            </form>
            <?php elseif ( $aberto && $tem ) : ?>
            <p class="cv-envio-trocar"><a href="<?php echo esc_url( self::url_baixar( $e, 'contrato' ) ); ?>">Baixar o contrato de novo</a></p>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    // ── 2. PIX da taxa ────────────────────────────────────────────
    private static function passo_pix( $e, $aberto ) {
        $valor = (float) $e->valor > 0 ? (float) $e->valor : CV_Envio::valor_taxa();
        $sit   = array( 'aguardando' => '', 'informado' => '⏳ PIX informado — aguardando conferência', 'confirmado' => '✅ PIX confirmado' );
        ob_start();
        ?>
        <section class="cv-envio-passo<?php echo 'aguardando' !== $e->pagamento_status ? ' is-resumo' : ''; ?>">
            <h4>2. PIX da taxa de divulgação</h4>
            <?php if ( 'aguardando' !== $e->pagamento_status ) : ?>
            <p class="cv-envio-ok"><?php echo esc_html( $sit[ $e->pagamento_status ] ?? '' ); ?><?php echo $e->comprovante_arquivo ? ' (comprovante recebido)' : ''; ?>.</p>
            <?php elseif ( $aberto ) : ?>
            <?php if ( $valor <= 0 || ! CV_Pix::ativo() ) : ?>
            <p class="cv-envio-alerta">O pagamento por PIX ainda não está disponível para esta etapa. Aguarde nosso contato por e-mail antes de pagar.</p>
            <?php else : ?>
            <?php echo CV_Pix::bloco( $valor, CV_Envio::txid( $e->id ), 'Divulgacao envio ' . (int) $e->id, 'Taxa de divulgação — R$ ' . number_format( $valor, 2, ',', '.' ) ); ?>
            <form class="cv-envio-form" data-acao="pago">
                <label class="cv-envio-arquivo">Comprovante (opcional — imagem ou PDF, até 5 MB)
                    <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png">
                </label>
                <button type="submit" class="cv-btn cv-btn-primary">✅ Já fiz o PIX</button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    // ── 3. YouTube ────────────────────────────────────────────────
    private static function passo_youtube( $e, $aberto ) {
        $vid = $e->youtube_url ? CV_Fields::youtube_id( $e->youtube_url ) : '';
        ob_start();
        ?>
        <section class="cv-envio-passo<?php echo $vid ? ' is-resumo' : ''; ?>">
            <h4>3. Link do vídeo no YouTube</h4>
            <?php if ( $vid ) : ?>
            <div class="cv-envio-video">
                <img src="<?php echo esc_url( 'https://img.youtube.com/vi/' . $vid . '/mqdefault.jpg' ); ?>" alt="" width="160" height="90" loading="lazy">
                <p>✅ <strong><?php echo esc_html( $e->youtube_titulo ); ?></strong><br><a href="<?php echo esc_url( $e->youtube_url ); ?>" target="_blank" rel="noopener">Abrir no YouTube</a></p>
            </div>
            <?php endif; ?>
            <?php if ( $aberto && 'enviado' !== $e->status ) : ?>
            <form class="cv-envio-form cv-envio-linha" data-acao="youtube">
                <label>Endereço do vídeo
                    <input type="url" name="url" value="<?php echo esc_attr( $e->youtube_url ); ?>" placeholder="https://www.youtube.com/watch?v=..." required>
                </label>
                <button type="submit" class="cv-btn cv-btn-primary"><?php echo $vid ? 'Trocar vídeo' : '🔎 Conferir vídeo'; ?></button>
            </form>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    // ── 4. Dados da música ────────────────────────────────────────
    private static function passo_dados( $e, $aberto ) {
        $editavel = $aberto && 'enviado' !== $e->status;
        ob_start();
        ?>
        <section class="cv-envio-passo<?php echo $editavel ? '' : ' is-resumo'; ?>">
            <h4>4. Dados da música</h4>
            <?php if ( ! $editavel ) : ?>
            <p class="cv-envio-ok">✅ <strong><?php echo esc_html( $e->titulo ); ?></strong> — <?php echo esc_html( $e->artista ); ?><?php echo $e->compositor ? ' · composição: ' . esc_html( $e->compositor ) : ''; ?></p>
            <?php else : ?>
            <p class="cv-envio-dica">💡 Precisa de ajuda com o título, a descrição e as tags? <a href="#" class="cv-envio-prompt" data-url="<?php echo esc_url( self::url_baixar( $e, 'prompt' ) ); ?>"><strong>Baixe o prompt de ajuda (Word)</strong></a> — ele já leva a sua letra: é só colar numa inteligência artificial (ChatGPT, Claude, Gemini).</p>
            <form class="cv-envio-form cv-envio-dados" data-acao="dados">
                <div class="cv-envio-grade">
                    <label>Título da música *<input type="text" name="titulo" maxlength="191" value="<?php echo esc_attr( $e->titulo ); ?>" required></label>
                    <label>Artista / dupla *<input type="text" name="artista" maxlength="120" value="<?php echo esc_attr( $e->artista ); ?>" required></label>
                    <label>Compositor(es)<input type="text" name="compositor" maxlength="191" value="<?php echo esc_attr( $e->compositor ); ?>"></label>
                    <label>Tags (até 8, separadas por vírgula)<input type="text" name="tags" maxlength="255" value="<?php echo esc_attr( $e->tags ); ?>" placeholder="saudade, amor verdadeiro, sertanejo raiz"></label>
                </div>
                <label>Descrição * <small>(1 ou 2 frases sobre a música — até 300 caracteres)</small>
                    <textarea name="descricao" rows="3" maxlength="300" required><?php echo esc_textarea( $e->descricao ); ?></textarea>
                </label>
                <label>Letra completa *
                    <textarea name="letra" rows="12" required><?php echo esc_textarea( $e->letra ); ?></textarea>
                </label>
                <div class="cv-envio-botoes">
                    <button type="button" class="cv-btn cv-btn-secondary cv-envio-rascunho">💾 Salvar rascunho</button>
                    <button type="submit" class="cv-btn cv-btn-primary">📨 Enviar para aprovação</button>
                </div>
            </form>
            <?php endif; ?>
        </section>
        <?php
        return ob_get_clean();
    }

    // ── Rodapé do cartão: situação final e excluir ───────────────
    private static function rodape( $e ) {
        ob_start();
        ?>
        <footer class="cv-envio-rodape">
            <?php if ( 'enviado' === $e->status ) : ?>
            <p>⏳ Recebemos tudo! Estamos conferindo o contrato e o PIX. Você recebe um e-mail assim que a página da sua música estiver pronta.</p>
            <?php elseif ( 'aprovado' === $e->status ) : ?>
            <p>🎉 Aprovado! <?php
                $post = $e->musica_id ? get_post( $e->musica_id ) : null;
                echo ( $post && 'publish' === $post->post_status )
                    ? 'Sua música já está no ar: <a href="' . esc_url( get_permalink( $post ) ) . '">ver a página</a>.'
                    : 'A página da sua música está sendo preparada.';
            ?></p>
            <?php elseif ( 'recusado' === $e->status ) : ?>
            <p class="cv-envio-alerta">Este envio não foi aprovado<?php echo $e->obs_admin ? ': ' . esc_html( $e->obs_admin ) : '.'; ?></p>
            <?php elseif ( 'rascunho' === $e->status ) : ?>
            <button type="button" class="cv-link-botao cv-envio-excluir">🗑 Excluir este envio</button>
            <?php endif; ?>
        </footer>
        <?php
        return ob_get_clean();
    }
}

CV_Envio_Area::init();
