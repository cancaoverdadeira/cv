/*
 * cancao-verdadeira-child/assets/js/cv-depoimentos.js
 * Projeto: Canção Verdadeira — "💬 Depoimentos dos ouvintes" (v15.25.0).
 * Conta as letras do depoimento e envia o formulário
 * (template-parts/musica/depoimentos.php) por AJAX para o plugin
 * (CV_Depoimentos::ajax_enviar, ação "cv_depoimento_enviar").
 * Dados: cvDepo = { ajaxUrl, nonce } (functions.php).
 * Depois de enviar, o formulário some e fica só o agradecimento: o
 * depoimento aparece na página quando for aprovado no painel.
 */
jQuery(function($){
    var $form = $('.cv-depo-form');
    if (!$form.length || !window.cvDepo) { return; }

    var $texto = $form.find('textarea[name="texto"]');
    $texto.on('input', function(){ $form.find('.cv-depo-conta b').text(this.value.length); });

    // Ao abrir, leva o cursor para o texto
    $('.cv-depo-form-caixa').on('toggle', function(){ if (this.open) { $texto.trigger('focus'); } });

    $form.on('submit', function(e){
        e.preventDefault();
        var $msg = $form.find('.cv-depo-msg').removeClass('is-erro is-ok').text('');
        var $btn = $form.find('button[type="submit"]');
        var dados = $form.serializeArray();
        dados.push({ name: 'action', value: 'cv_depoimento_enviar' });
        dados.push({ name: 'nonce',  value: cvDepo.nonce });

        $btn.prop('disabled', true).text('Enviando…');
        $.post(cvDepo.ajaxUrl, $.param(dados))
            .done(function(res){
                if (res && res.success) {
                    $form.find('.cv-depo-campo, .cv-depo-linha, .cv-depo-aceite, .cv-depo-aviso, button[type="submit"]').hide();
                    $msg.addClass('is-ok').text(res.data.message);
                } else {
                    $msg.addClass('is-erro').text(res && res.data && res.data.message ? res.data.message : 'Não foi possível enviar. Tente de novo.');
                }
            })
            .fail(function(xhr){
                var txt = (xhr && xhr.responseText === '-1')
                    ? 'A página ficou aberta muito tempo. Recarregue e tente de novo.'
                    : 'Sem conexão agora. Tente de novo em instantes.';
                $msg.addClass('is-erro').text(txt);
            })
            .always(function(){ $btn.prop('disabled', false).text('💌 Enviar depoimento'); });
    });
});
