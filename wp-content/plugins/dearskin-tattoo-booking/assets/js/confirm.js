jQuery(function($){
  function postConfirm(extra) {
    const fd = $('#dstb-confirm-form').serialize() + (extra || '');
    $('#dstb-msg').text('Bitte warten …');
    $.post(DSTB_Confirm.ajax, fd + '&action=dstb_confirm_choice_v2', function(res){
      $('#dstb-msg').text(res && res.data ? res.data.msg : 'Fehler.');
      if(res && res.success){
        $('#dstb-confirm-form').fadeOut(200);
      }
    }).fail(function(){
      $('#dstb-msg').text('Netzwerkfehler.');
    });
  }

  $('#dstb-confirm-form').on('submit', function(e){
    e.preventDefault();
    const $terms = $('#dstb-terms');
    if($terms.length && !$terms.is(':checked')){
      $('#dstb-msg').text('Bitte bestätige die AGB und Bedingungen.');
      return;
    }
    postConfirm('');
  });

  $('#dstb-decline').on('click', function(){
    if(!confirm('Wirklich alle Termine ablehnen?')) return;
    postConfirm('&decline=1');
  });
});
