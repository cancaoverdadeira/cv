/*
 * cancao-verdadeira/assets/js/admin-sorteios.js
 * Tela "Sorteios" do painel: botões do formulário, Media Library e
 * chamadas AJAX. nonce e ajaxUrl vêm de window.cvMon, impressos por
 * CV_Monetization_Pages::enqueue_js().
 * v2.36.0: saiu do <script> que ficava dentro de page_sorteios().
 */
jQuery(function($){
    var nonce = (window.cvMon || {}).nonce || '';
    var ajax  = (window.cvMon || {}).ajaxUrl || '';

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
    $('#cv-sort-novo-btn').on('click',function(){$('#cv-sort-id').val(0);$('#cv-sort-form input,#cv-sort-form textarea').val('');$('#cv-sort-form').slideDown(180);});
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
