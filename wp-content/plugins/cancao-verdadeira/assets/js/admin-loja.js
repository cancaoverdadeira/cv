/*
 * cancao-verdadeira/assets/js/admin-loja.js
 * Tela "Loja" do painel: botões do formulário, Media Library e
 * chamadas AJAX. nonce e ajaxUrl vêm de window.cvMon, impressos por
 * CV_Monetization_Pages::enqueue_js().
 * v2.36.0: saiu do <script> que ficava dentro de page_loja().
 */
jQuery(function($){
    var nonce = (window.cvMon || {}).nonce || '';
    var ajax  = (window.cvMon || {}).ajaxUrl || '';

    function msg(t,ok){var $m=$('#cv-prod-msg');$m.text(t).css({background:ok?'#EBF4EB':'#F4EBEB',border:'1px solid '+(ok?'#2d6a2d':'#6a2d2d'),color:ok?'#7fce7f':'#ce7f7f'}).show();setTimeout(function(){$m.fadeOut();},3000);}
    $('#cv-prod-novo-btn').on('click',function(){$('#cv-prod-id').val(0);$('#cv-prod-form input,#cv-prod-form textarea,#cv-prod-form select').val('');$('#cv-p-ativo').val('1');$('#cv-p-ordem').val('0');$('#cv-p-imagem-preview').hide();$('#cv-prod-form').slideDown(180);});
    $('#cv-prod-cancelar').on('click',function(){$('#cv-prod-form').slideUp(180);});
    $(document).on('click','.cv-media-pick-prod',function(){var frame=wp.media({title:'Selecionar imagem',button:{text:'Usar'},multiple:false,library:{type:'image'}});frame.on('select',function(){var a=frame.state().get('selection').first().toJSON();$('#cv-p-imagem').val(a.url);$('#cv-p-imagem-preview').attr('src',a.url).show();});frame.open();});
    $('#cv-p-imagem').on('change',function(){var u=$.trim($(this).val());$('#cv-p-imagem-preview').attr('src',u)[u?'show':'hide']();});
    $(document).on('click','.cv-prod-editar',function(){var d=$(this).data('prod');$('#cv-prod-id').val(d.id);$('#cv-p-nome').val(d.nome);$('#cv-p-categoria').val(d.categoria);$('#cv-p-desc').val(d.descricao);$('#cv-p-preco').val(parseFloat(d.preco).toFixed(2).replace('.',','));$('#cv-p-preco-antigo').val(d.preco_antigo>0?parseFloat(d.preco_antigo).toFixed(2).replace('.',','):'');$('#cv-p-url').val(d.url_compra);$('#cv-p-imagem').val(d.imagem_url);if(d.imagem_url){$('#cv-p-imagem-preview').attr('src',d.imagem_url).show();}$('#cv-p-botao').val(d.texto_botao);$('#cv-p-badge').val(d.badge);$('#cv-p-ordem').val(d.ordem);$('#cv-p-ativo').val(d.ativo);$('#cv-prod-form').slideDown(180);$('html,body').animate({scrollTop:$('#cv-prod-form').offset().top-40},300);});
    $('#cv-prod-salvar').on('click',function(){var $b=$(this).prop('disabled',true).text('Salvando...');$.post(ajax,{action:'cv_save_produto',nonce:nonce,id:$('#cv-prod-id').val(),nome:$('#cv-p-nome').val(),categoria:$('#cv-p-categoria').val(),descricao:$('#cv-p-desc').val(),preco:$('#cv-p-preco').val(),preco_antigo:$('#cv-p-preco-antigo').val(),imagem_url:$('#cv-p-imagem').val(),url_compra:$('#cv-p-url').val(),texto_botao:$('#cv-p-botao').val(),badge:$('#cv-p-badge').val(),ordem:$('#cv-p-ordem').val(),ativo:$('#cv-p-ativo').val()},function(r){if(r.success){msg('✅ Produto salvo!',true);setTimeout(function(){location.reload();},1200);}else{msg('❌ Erro.',false);$b.prop('disabled',false).text('💾 Salvar Produto');}});});
    $(document).on('click','.cv-prod-excluir',function(){var id=$(this).data('id');if(!confirm('Excluir este produto?'))return;$.post(ajax,{action:'cv_delete_produto',nonce:nonce,id:id},function(r){if(r.success){$('#cv-prod-row-'+id).fadeOut(300,function(){$(this).remove();});msg('✅ Excluído.',true);}});});
});
