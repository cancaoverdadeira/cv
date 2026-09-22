<?php
// cv-limpeza/cv-limpeza.php
// Plugin auxiliar temporário — Canção Verdadeira
// Função: remove completamente a pasta do plugin cancao-verdadeira
// Uso: ativar, acessar a página, clicar em Limpar, desativar e deletar
// ATENÇÃO: usar apenas uma vez e remover após a limpeza
// Autor: Canção Verdadeira | Gerado: 2026-06-26

/**
 * Plugin Name: CV Limpeza (Temporário)
 * Description: Remove a pasta do plugin Cancao Verdadeira para reinstalação limpa. DELETE APÓS O USO.
 * Version: 1.0
 * Author: Cancao Verdadeira
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_menu', function() {
    add_menu_page( 'CV Limpeza', '🧹 CV Limpeza', 'manage_options', 'cv-limpeza', 'cv_limpeza_page', 'dashicons-trash', 2 );
});

function cv_limpeza_page() {
    $alvo = WP_PLUGIN_DIR . '/cancao-verdadeira';
    $msg  = '';

    if ( isset($_POST['cv_fazer_limpeza']) && check_admin_referer('cv_limpeza_action') ) {
        if ( file_exists($alvo) ) {
            cv_limpeza_remover_pasta($alvo);
            $msg = file_exists($alvo)
                ? '<div style="background:#f8d7da;border:1px solid #f5c6cb;padding:12px;border-radius:4px;color:#721c24">⚠️ Não foi possível remover todos os arquivos. Tente novamente ou remova manualmente os que restaram.</div>'
                : '<div style="background:#d4edda;border:1px solid #c3e6cb;padding:12px;border-radius:4px;color:#155724">✅ Pasta <strong>cancao-verdadeira</strong> removida com sucesso! Agora instale o novo plugin e delete este.</div>';
        } else {
            $msg = '<div style="background:#d4edda;border:1px solid #c3e6cb;padding:12px;border-radius:4px;color:#155724">✅ Pasta já não existe. Pode instalar o novo plugin!</div>';
        }
    }

    $existe  = file_exists($alvo);
    $arquivos = $existe ? cv_limpeza_contar($alvo) : 0;
    ?>
    <div class="wrap">
        <h1>🧹 CV Limpeza — Plugin Temporário</h1>
        <div style="background:#fff3cd;border:1px solid #ffc107;padding:12px;border-radius:4px;margin:16px 0;max-width:600px">
            <strong>⚠️ Atenção:</strong> Este plugin é de uso único. Após a limpeza, instale o plugin principal e <strong>delete este plugin</strong>.
        </div>
        <?= $msg ?>
        <div style="background:#f8f9fa;border:1px solid #dee2e6;padding:20px;border-radius:8px;margin-top:20px;max-width:600px">
            <h3 style="margin-top:0">Status da pasta</h3>
            <p><strong>Caminho:</strong> <code><?= esc_html($alvo) ?></code></p>
            <p><strong>Situação:</strong>
                <?php if ($existe) : ?>
                    <span style="color:#dc3545">⛔ Existe com <?= $arquivos ?> arquivo(s)/pasta(s)</span>
                <?php else : ?>
                    <span style="color:#28a745">✅ Não existe — pronto para instalar!</span>
                <?php endif; ?>
            </p>
            <?php if ($existe) : ?>
            <form method="post">
                <?php wp_nonce_field('cv_limpeza_action'); ?>
                <button type="submit" name="cv_fazer_limpeza" value="1"
                    style="background:#dc3545;color:#fff;border:none;padding:10px 24px;border-radius:4px;font-size:14px;cursor:pointer"
                    onclick="return confirm('Confirma a remoção completa da pasta cancao-verdadeira?')">
                    🗑️ Remover pasta cancao-verdadeira
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

function cv_limpeza_remover_pasta($pasta) {
    if ( ! is_dir($pasta) ) {
        @unlink($pasta);
        return;
    }
    $itens = array_diff( scandir($pasta), ['.','..'] );
    foreach ($itens as $item) {
        $caminho = $pasta . DIRECTORY_SEPARATOR . $item;
        if ( is_dir($caminho) ) {
            cv_limpeza_remover_pasta($caminho);
        } else {
            @chmod($caminho, 0777);
            @unlink($caminho);
        }
    }
    @chmod($pasta, 0777);
    @rmdir($pasta);
}

function cv_limpeza_contar($pasta) {
    $total = 0;
    $itens = array_diff( scandir($pasta), ['.','..'] );
    foreach ($itens as $item) {
        $total++;
        if ( is_dir($pasta . '/' . $item) ) {
            $total += cv_limpeza_contar($pasta . '/' . $item);
        }
    }
    return $total;
}
