jQuery(function($){

  /* ========= Mehr-Zeilen-Eingabe ========= */
  $(document).on('click', '.add-sug', function(){
    const form = $(this).closest('form');
    const grid = form.find('.dstb-sug-grid');
    const row = `
      <input type="date" name="date[]" required>
      <input type="time" name="start[]" step="1800" required>
      <input type="time" name="end[]" step="1800" required>
      <input type="number" name="price[]" placeholder="Preis €" min="0" step="10">
      <input type="text" name="note[]" placeholder="Notiz (optional)" class="dstb-col-2">
    `;
    grid.append(row);
  });

  /* ========= Entwürfe speichern / an Kunden senden ========= */
  $(document).on('click', '.dstb-sug-form .dstb-save, .dstb-sug-form .dstb-send', function(){
    const btn  = $(this);
    const form = btn.closest('form');
    const msg  = form.find('.dstb-sug-msg').text('Bitte warten …');

    const payload = form.serializeArray();
    payload.push({name:'action', value:'dstb_add_suggestion'});
    payload.push({name:'nonce', value:(window.DSTB_Ajax && DSTB_Ajax.nonce) || ''});
    payload.push({name:'request_id', value:form.data('req')});
    payload.push({name:'save_action', value:btn.data('action')}); // 'draft' oder 'send'

    $.post((window.DSTB_Ajax && DSTB_Ajax.url) || ajaxurl, payload)
      .done(function(res){
        if (res && res.success){
          msg.text('✅ '+ (res.data && res.data.msg ? res.data.msg : 'Gespeichert.'));
          setTimeout(()=>{ location.reload(); }, 800);
        } else {
          const err = res && res.data && res.data.msg ? res.data.msg : 'Fehler.';
          msg.css('color','#ffb3b3').text('❌ '+ err);
        }
      })
      .fail(function(){
        msg.css('color','#ffb3b3').text('❌ Netzwerkfehler.');
      });
  });

  /* ========= Popup: Bearbeiten ========= */
  const $modal = $('#dstb-modal');
  const $modalForm = $('#dstb-modal-form');

  function openModal(data){
    $modalForm.find('input[name="id"]').val(data.id || '');
    $modalForm.find('input[name="date"]').val(data.date || '');
    $modalForm.find('input[name="start"]').val(data.start || '');
    $modalForm.find('input[name="end"]').val(data.end || '');
    $modalForm.find('input[name="price"]').val(data.price || 0);
    $modalForm.find('input[name="note"]').val(data.note || '');
    $modal.fadeIn(120);
  }
  function closeModal(){
    $modal.fadeOut(120);
    $modalForm[0].reset();
    $modal.find('.dstb-modal-msg').text('');
  }

  $(document).on('click', '.dstb-edit-sug', function(){
    const btn = $(this);
    if (btn.is(':disabled') || btn.hasClass('disabled')) return;
    const row = btn.closest('.dstb-sug-row');
    const data = {
      id:    row.data('sid'),
      date:  row.data('date'),
      start: row.data('start'),
      end:   row.data('end'),
      price: row.data('price'),
      note:  row.data('note')
    };
    openModal(data);
  });

  /* ========= Vorschlag löschen ========= */
  $(document).on('click', '.dstb-delete-sug', function(){
    const btn = $(this);
    const sid = btn.data('sid');
    if (!sid) return;
    if (!window.confirm('Diesen Vorschlag wirklich löschen?')) return;

    const cell = btn.closest('td');
    let msg = cell.find('.dstb-delete-msg');
    if (!msg.length) {
      msg = $('<span class="dstb-delete-msg" style="margin-left:6px;"></span>').appendTo(cell);
    }
    msg.css('color', '').text('Lösche …');
    btn.prop('disabled', true);

    $.post((window.DSTB_Ajax && DSTB_Ajax.url) || ajaxurl, {
      action: 'dstb_delete_suggestion',
      nonce: (window.DSTB_Ajax && DSTB_Ajax.nonce) || '',
      id: sid
    }).done(function(res){
      if (res && res.success) {
        msg.text('✅ Vorschlag gelöscht.');
        setTimeout(function(){
          btn.closest('tr').fadeOut(150, function(){ $(this).remove(); });
        }, 200);
      } else {
        const err = res && res.data && res.data.msg ? res.data.msg : 'Fehler.';
        msg.css('color', '#ffb3b3').text('❌ '+ err);
        btn.prop('disabled', false);
      }
    }).fail(function(){
      msg.css('color', '#ffb3b3').text('❌ Netzwerkfehler.');
      btn.prop('disabled', false);
    });
  });

  $(document).on('click', '.dstb-modal__close, .dstb-modal-cancel', function(){
    closeModal();
  });

  $(document).on('click', '.dstb-modal-save', function(){
    const msg = $modal.find('.dstb-modal-msg').text('Speichere …');
    const payload = $modalForm.serializeArray();
    payload.push({name:'action', value:'dstb_update_suggestion'});
    payload.push({name:'nonce', value:(window.DSTB_Ajax && DSTB_Ajax.nonce) || ''});

    $.post((window.DSTB_Ajax && DSTB_Ajax.url) || ajaxurl, payload)
      .done(function(res){
        if (res && res.success){
          msg.text('✅ Gespeichert.');
          setTimeout(()=>{ location.reload(); }, 600);
        } else {
          msg.css('color','#ffb3b3').text('❌ '+(res && res.data && res.data.msg ? res.data.msg : 'Fehler.'));
        }
      })
      .fail(function(){
        msg.css('color','#ffb3b3').text('❌ Netzwerkfehler.');
      });
  });

  // Klick außerhalb des Dialogs schließt das Modal
  $modal.on('click', function(e){
    if (e.target === this) closeModal();
  });
});
