/*
 * cancao-verdadeira/assets/js/admin-banners.js
 * Tela "Banners de Parceiros" do painel: botões do formulário, Media Library e
 * chamadas AJAX. nonce e ajaxUrl vêm de window.cvMon, impressos por
 * CV_Monetization_Pages::enqueue_js().
 * v2.36.0: saiu do <script> que ficava dentro de page_banners().
 */
jQuery(function($){
    var nonce = (window.cvMon || {}).nonce || '';
    var ajax  = (window.cvMon || {}).ajaxUrl || '';

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
