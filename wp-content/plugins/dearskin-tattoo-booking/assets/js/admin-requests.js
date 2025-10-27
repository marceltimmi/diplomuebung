jQuery(function($){

  /* ========= Helper ========= */
  function ajaxUrl(){ return (window.DSTB_Ajax && DSTB_Ajax.url) || window.ajaxurl || '/wp-admin/admin-ajax.php'; }
  function nonce(){ return (window.DSTB_Ajax && DSTB_Ajax.nonce) || ''; }

  /* ========= Mehr-Zeilen-Eingabe (neue Vorschläge) ========= */
  $(document).on('click', '.add-sug', function(){
    const form = $(this).closest('form');
    const grid = form.find('.dstb-sug-grid');
    grid.append(`
      <input type="date" name="date[]" required>
      <input type="time" name="start[]" step="1800" required>
      <input type="time" name="end[]" step="1800" required>
      <input type="number" name="price[]" placeholder="Preis €" min="0" step="10">
      <input type="text" name="note[]" placeholder="Notiz (optional)" class="dstb-col-2">
    `);
  });

  /* ========= Entwürfe speichern / an Kunden senden ========= */
  $(document).on('click', '.dstb-sug-form .dstb-save, .dstb-sug-form .dstb-send', function(){
    const btn  = $(this);
    const form = btn.closest('form');
    const msg  = form.find('.dstb-sug-msg').css('color','').text('Bitte warten …');

    const payload = form.serializeArray();
    payload.push({name:'action', value:'dstb_add_suggestion'});
    payload.push({name:'nonce', value: nonce()});
    payload.push({name:'request_id', value: form.data('req')});
    payload.push({name:'save_action', value: btn.data('action')}); // 'draft' | 'send'

    $.post(ajaxUrl(), payload)
      .done(function(res){
        if(res && res.success){
          msg.text((res.data && res.data.msg) ? '✅ ' + res.data.msg : '✅ Gespeichert.');
          setTimeout(()=> location.reload(), 800);
        }else{
          const m = (res && res.data && res.data.msg) ? res.data.msg : 'Fehler.';
          msg.css('color','#ffb3b3').text('❌ ' + m);
        }
      })
      .fail(function(){
        msg.css('color','#ffb3b3').text('❌ Netzwerkfehler.');
      });
  });

  /* ========= Modal (Bearbeiten) ========= */
  const $modal = $('#dstb-modal');
  const $modalForm = $('#dstb-modal-form');
  const $modalMsg = $modal.find('.dstb-modal-msg');

  function openModal(prefill){
    $modalForm.find('input[name="id"]').val(prefill.id || '');
    $modalForm.find('input[name="date"]').val(prefill.date || '');
    $modalForm.find('input[name="start"]').val(prefill.start || '');
    $modalForm.find('input[name="end"]').val(prefill.end || '');
    $modalForm.find('input[name="price"]').val(prefill.price || 0);
    $modalForm.find('input[name="note"]').val(prefill.note || '');
    $modalMsg.text('');
    $modal.fadeIn(120);
  }
  function closeModal(){
    $modal.fadeOut(120);
    $modalForm[0].reset();
    $modalMsg.text('');
  }

  // Öffnen (Werte aus Tabellenzellen ziehen)
  $(document).off('click.dstb','.dstb-edit-sug').on('click.dstb', '.dstb-edit-sug', function(){
    const $row = $(this).closest('tr');
    // Spalten: 0=Datum, 1=Start, 2=Ende, 3=Preis, 4=Notiz, 5=Status, 6=Aktionen
    const prefill = {
      id:    $row.data('sid'),
      date:  $row.find('td').eq(0).text().trim(),
      start: $row.find('td').eq(1).text().trim(),
      end:   $row.find('td').eq(2).text().trim(),
      price: ($row.find('td').eq(3).text().replace('€','').trim() || '0'),
      note:  $row.find('td').eq(4).text().trim()
    };
    openModal(prefill);
  });

  // Schließen
  $(document).on('click', '.dstb-modal__close, .dstb-modal-cancel', function(){ closeModal(); });
  $modal.on('click', function(e){ if(e.target === this) closeModal(); });

  // Speichern im Modal
  $(document).off('click.dstb','.dstb-modal-save').on('click.dstb', '.dstb-modal-save', function(){
    const payload = $modalForm.serializeArray();
    payload.push({name:'action', value:'dstb_update_suggestion'});
    payload.push({name:'nonce', value: nonce()});
    $modalMsg.css('color','').text('Speichere …');

    $.post(ajaxUrl(), payload)
      .done(function(res){
        if(res && res.success){
          $modalMsg.css('color','#b2f5b2').text(res.data && res.data.msg ? res.data.msg : 'Gespeichert.');
          setTimeout(()=> location.reload(), 650);
        }else{
          const m = (res && res.data && res.data.msg) ? res.data.msg : 'Fehler.';
          $modalMsg.css('color','#ffb3b3').text('❌ ' + m);
        }
      })
      .fail(function(){
        $modalMsg.css('color','#ffb3b3').text('❌ Netzwerkfehler.');
      });
  });

  /* ========= Löschen (nur draft) ========= */
  $(document).off('click.dstb','.dstb-del-sug').on('click.dstb', '.dstb-del-sug', function(){
    if(!confirm('Diesen Vorschlag wirklich löschen?')) return;
    const sid = $(this).data('sid');
    const $row = $(this).closest('tr');

    $.post(ajaxUrl(), {
      action: 'dstb_delete_suggestion',
      nonce: nonce(),
      id: sid
    }).done(function(res){
      if(res && res.success){
        $row.fadeOut(200, ()=> $row.remove());
        alert(res.data && res.data.msg ? res.data.msg : 'Gelöscht.');
      }else{
        alert((res && res.data && res.data.msg) ? res.data.msg : 'Löschen nicht möglich.');
      }
    }).fail(function(){
      alert('Netzwerkfehler.');
    });
  });

});
