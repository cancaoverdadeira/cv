<?php
// cancao-verdadeira-child/template-parts/musica/oferecer.php
// Projeto: Canção Verdadeira — parte da página individual da música.
// "💌 Ofereça esta música" (v15.22.0, 26/09/2026 — prioridade 0, item 2.2):
// cartão na lateral + janela para escrever Para / Recado / De e escolher o
// estilo. O navegador desenha um cartão (imagem 1080×1080) e a pessoa envia
// pelo WhatsApp (no celular a imagem vai junto) ou baixa. O link enviado abre
// a música com a dedicatória no topo (template-parts/musica/dedicatoria.php).
// Nada é gravado no banco: o recado viaja no próprio link.
// JS: assets/js/cv-oferecer.js · CSS: cv-ajustes.css (seção 14).
// v15.30.0: âncora #oferecer (atalho do "✨ Novidades" da home).

if ( ! defined( 'ABSPATH' ) ) { exit; }

$titulo = isset( $args['titulo'] ) ? $args['titulo'] : get_the_title();
$cover  = isset( $args['cover'] ) ? $args['cover'] : '';
$autor  = ! empty( $args['artista'] ) ? $args['artista'] : ( ! empty( $args['compositor'] ) ? $args['compositor'] : '' );
?>
<div class="cv-aside-card cv-oferecer-card" id="oferecer">
    <h3 class="cv-aside-title">💌 Ofereça esta música</h3>
    <p class="cv-oferecer-intro">Mande para alguém especial, com um recado seu. Criamos um cartão bonito para enviar pelo WhatsApp.</p>
    <button type="button" class="cv-btn cv-btn-primary cv-oferecer-abrir">💌 Oferecer para alguém</button>
</div>

<dialog id="cv-janela-oferecer" class="cv-janela" aria-labelledby="cv-oferecer-titulo"
        data-titulo="<?php echo esc_attr( $titulo ); ?>"
        data-autor="<?php echo esc_attr( $autor ); ?>"
        data-capa="<?php echo esc_attr( $cover ); ?>"
        data-url="<?php echo esc_attr( get_permalink() ); ?>"
        data-site="<?php echo esc_attr( wp_parse_url( home_url(), PHP_URL_HOST ) ); ?>">
    <button type="button" class="cv-janela-fechar" aria-label="Fechar">✕</button>
    <h2 id="cv-oferecer-titulo">💌 Ofereça "<?php echo esc_html( $titulo ); ?>"</h2>

    <form class="cv-janela-form cv-oferecer-form" novalidate>
        <div class="cv-janela-grade">
            <label>Para quem? *<input type="text" name="para" maxlength="40" required placeholder="Ex.: Maria, minha mãe, meu amor"></label>
            <label>De quem? *<input type="text" name="de" maxlength="40" required placeholder="Seu nome"></label>
        </div>
        <label class="cv-janela-cheio">Seu recado <small>(até 200 letras)</small>
            <textarea name="msg" rows="3" maxlength="200" placeholder="Escreva algo do coração..."></textarea>
        </label>
        <div class="cv-oferecer-sugestoes" aria-label="Sugestões de recado">
            <?php foreach ( array( 'Pensei em você quando ouvi esta música.', 'Com todo o meu carinho.', 'Saudade de você!', 'Para lembrar do nosso amor.' ) as $sug ) : ?>
            <button type="button" class="cv-oferecer-sugestao"><?php echo esc_html( $sug ); ?></button>
            <?php endforeach; ?>
        </div>
        <fieldset class="cv-janela-tipos cv-oferecer-estilos">
            <legend>Estilo do cartão</legend>
            <label class="cv-janela-opcao"><input type="radio" name="estilo" value="amanhecer" checked> 🌅 Amanhecer</label>
            <label class="cv-janela-opcao"><input type="radio" name="estilo" value="cafe"> ☕ Café com viola</label>
            <label class="cv-janela-opcao"><input type="radio" name="estilo" value="rosa"> 🌹 Romântico</label>
        </fieldset>
        <div class="cv-janela-msg" role="status" aria-live="polite"></div>
        <button type="submit" class="cv-btn cv-btn-primary cv-janela-enviar">🎨 Criar o cartão</button>
    </form>

    <div class="cv-oferecer-pronto" hidden>
        <img class="cv-oferecer-previa" alt="Prévia do cartão" width="1080" height="1080">
        <div class="cv-oferecer-acoes">
            <button type="button" class="cv-btn cv-btn-primary cv-oferecer-whats" style="background:#25D366;color:#FFFFFF">📲 Enviar pelo WhatsApp</button>
            <button type="button" class="cv-btn cv-btn-secondary cv-oferecer-baixar">⬇️ Baixar o cartão</button>
            <button type="button" class="cv-btn cv-btn-secondary cv-oferecer-copiar">🔗 Copiar o link</button>
            <button type="button" class="cv-link-botao cv-oferecer-refazer">✏️ Mudar o recado</button>
        </div>
        <p class="cv-oferecer-dica">No celular, a imagem do cartão vai junto no WhatsApp. No computador, o cartão é baixado e o WhatsApp abre com o recado e o link da música.</p>
    </div>
</dialog>
