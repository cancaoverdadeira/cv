/*
 * cancao-verdadeira-child/assets/js/cv-novidades.js
 * Projeto: Canção Verdadeira — quadro "✨ Novidades para você" da home (v15.30.0).
 * O botão "✕ Fechar" esconde o quadro por 30 dias neste aparelho
 * (localStorage, com proteção se o navegador bloquear). Sem JavaScript, o
 * quadro só fica sempre visível. Carregado só na página inicial (functions.php).
 */
(function(){
    var CHAVE = 'cv_novidades_fechado_ate', DIAS = 30;
    var caixa = document.getElementById('cv-novidades');
    if (!caixa) { return; }
    try {
        var ate = parseInt(window.localStorage.getItem(CHAVE), 10) || 0;
        if (ate > Date.now()) { caixa.hidden = true; return; }
    } catch (e) {}
    var botao = caixa.querySelector('.cv-novidades-fechar');
    if (!botao) { return; }
    botao.addEventListener('click', function(){
        caixa.hidden = true;
        try { window.localStorage.setItem(CHAVE, String(Date.now() + DIAS * 864e5)); } catch (e) {}
    });
})();
