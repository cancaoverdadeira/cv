/*
 * cancao-verdadeira/assets/js/admin-brindes.js
 * Tela "Brindes" do painel: botões do formulário, Media Library e
 * chamadas AJAX. nonce e ajaxUrl vêm de window.cvMon, impressos por
 * CV_Monetization_Pages::enqueue_js().
 * v2.36.0: saiu do <script> que ficava dentro de page_brindes().
 * v2.60.0: envia também a peça do estoque (#cv-br-estoque).
 */
jQuery(function($){
    var nonce = (window.cvMon || {}).nonce || '';
    var ajax  = (window.cvMon || {}).ajaxUrl || '';
    function msg(t,ok){var $m=$('#cv-brinde-msg');$m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},4000);}
    $('#cv-brinde-novo-btn').on('click',function(){$('#cv-br-id').val(0);$('#cv-brinde-form input,#cv-brinde-form textarea').val('');$('#cv-br-qtd').val('1');$('#cv-brinde-form').slideDown(180);});
    $('#cv-brinde-cancelar').on('click',function(){$('#cv-brinde-form').slideUp(180);});
    $(document).on('click','.cv-media-pick-br',function(){var frame=wp.media({title:'Selecionar imagem',button:{text:'Usar'},multiple:false});frame.on('select',function(){$('#cv-br-imagem').val(frame.state().get('selection').first().toJSON().url);});frame.open();});
    $('#cv-brinde-salvar').on('click',function(){var $b=$(this).prop('disabled',true).text('Salvando...');$.post(ajax,{action:'cv_save_brinde',nonce:nonce,id:$('#cv-br-id').val(),titulo:$('#cv-br-titulo').val(),descricao:$('#cv-br-desc').val(),imagem_url:$('#cv-br-imagem').val(),quantidade:$('#cv-br-qtd').val(),estoque:$('#cv-br-estoque').val()||0},function(r){if(r.success){msg('✅ Brinde salvo!',true);setTimeout(function(){location.reload();},1200);}else{msg('❌ Erro.',false);$b.prop('disabled',false).text('💾 Salvar Brinde');}});});
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
