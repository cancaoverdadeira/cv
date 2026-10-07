/* cancao-verdadeira-child/assets/js/cv-senha.js
   Gerado em: 2026-10-04
   Projeto: Canção Verdadeira — Plataforma de letras musicais sertanejas
   v15.37.0: botão "👁️ Mostrar / 🙈 Esconder" em TODO campo de senha do site
   (Entrar, Cadastro, Recuperar senha, Minha conta e formulários do plugin).
   Pedido do Eduardo para o público 55+: ver o que está digitando evita erro
   de senha. O botão tem texto (não só ícone), é grande para o dedo e avisa o
   leitor de tela (aria-pressed). Antes de enviar o formulário a senha volta a
   ficar escondida, para o navegador oferecer "salvar senha" normalmente.
   Sem jQuery e sem arrow functions (roda em navegadores antigos). */

(function () {
    'use strict';

    var TXT_MOSTRAR = 'Mostrar';
    var TXT_ESCONDER = 'Esconder';

    function atualizar(botao, visivel) {
        botao.setAttribute('aria-pressed', visivel ? 'true' : 'false');
        botao.setAttribute('aria-label', visivel ? 'Esconder a senha' : 'Mostrar a senha');
        botao.querySelector('.cv-senha-icone').textContent = visivel ? '🙈' : '👁️';
        botao.querySelector('.cv-senha-txt').textContent = visivel ? TXT_ESCONDER : TXT_MOSTRAR;
    }

    function enfeitar(input) {
        if (input.getAttribute('data-cv-senha')) { return; }
        // O Ultimate Member tem um olhinho próprio (opção desligada); se alguém ligar, não duplica.
        if (input.closest && input.closest('.um-field-area-password')) { return; }
        input.setAttribute('data-cv-senha', '1');

        var caixa = document.createElement('span');
        caixa.className = 'cv-senha-caixa';
        input.parentNode.insertBefore(caixa, input);
        caixa.appendChild(input);

        var botao = document.createElement('button');
        botao.type = 'button';
        botao.className = 'cv-senha-ver';
        botao.innerHTML = '<span class="cv-senha-icone" aria-hidden="true"></span><span class="cv-senha-txt"></span>';
        if (input.id) { botao.setAttribute('aria-controls', input.id); }
        atualizar(botao, false);
        caixa.appendChild(botao);

        botao.addEventListener('click', function () {
            var visivel = input.type === 'password';
            input.type = visivel ? 'text' : 'password';
            atualizar(botao, visivel);
            input.focus();
        });

        if (input.form) {
            input.form.addEventListener('submit', function () {
                input.type = 'password';
                atualizar(botao, false);
            });
        }
    }

    function procurar(raiz) {
        var lista = (raiz || document).querySelectorAll('input[type="password"]');
        for (var i = 0; i < lista.length; i++) { enfeitar(lista[i]); }
    }

    function iniciar() {
        procurar(document);
        // Formulários que aparecem depois (janelas, abas da Minha conta).
        if (window.MutationObserver) {
            var espera = null;
            new MutationObserver(function () {
                clearTimeout(espera);
                espera = setTimeout(function () { procurar(document); }, 150);
            }).observe(document.body, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciar);
    } else {
        iniciar();
    }
})();
