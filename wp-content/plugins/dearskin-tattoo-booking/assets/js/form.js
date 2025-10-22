(function($){

  /** ---------- Hilfsfunktionen ---------- */
  function getAjaxUrl(){
    // Nutzt das in PHP definierte Objekt DSTB_Ajax
    if (window.DSTB_Ajax && DSTB_Ajax.url) return DSTB_Ajax.url;
    return '/wp-admin/admin-ajax.php';
  }

  function getNonce(){
    return (window.DSTB_Ajax && DSTB_Ajax.nonce) ? DSTB_Ajax.nonce : '';
  }

  function steps30(){
    return [
      "08:00","08:30","09:00","09:30","10:00","10:30","11:00","11:30",
      "12:00","12:30","13:00","13:30","14:00","14:30","15:00","15:30",
      "16:00","16:30","17:00","17:30","18:00"
    ];
  }

  function hmToDate(hm){ const [h,m]=hm.split(':').map(Number); return new Date(2000,0,1,h,(m||0)); }
  function rangeSteps(from,to,step=30){
    const out=[]; let s=hmToDate(from), e=hmToDate(to);
    if(e<=s) e.setDate(e.getDate()+1);
    for(let t=new Date(s); t<e; t.setMinutes(t.getMinutes()+step)){
      out.push(t.toTimeString().slice(0,5));
    }
    return out;
  }

  function fillGeneric($sel){
    const s = steps30();
    $sel.empty(); s.forEach(t=>$sel.append(`<option value="${t}">${t}</option>`));
  }

  /** ---------- Server: freie Ranges für Artist/Datum ---------- */
  async function fetchFreeForDate(artist, dateStr){
    const url = `${getAjaxUrl()}?action=dstb_free_slots&artist=${encodeURIComponent(artist)}&date=${encodeURIComponent(dateStr)}&nonce=${getNonce()}`;
    try{
      const r = await fetch(url, {credentials:'same-origin'});
      if(!r.ok) return [];
      const j = await r.json();
      return Array.isArray(j.free) ? j.free : [];
    }catch(e){ return []; }
  }

  /** ---------- Dropdowns einer Zeile befüllen ---------- */
  function populateRowFromRanges($row, freeRanges){
    const $start = $row.find('select[data-kind="start"]');
    const $end   = $row.find('select[data-kind="end"]');
    const prevStart = $start.val();
    const prevEnd   = $end.val();
    $start.empty(); $end.empty();

    if(!freeRanges || !freeRanges.length){
      $start.append('<option value="">Keine freien Zeiten</option>');
      $end.append('<option value="">–</option>');
      return;
    }

    const starts=[];
    freeRanges.forEach(r=>{
      const arr = rangeSteps(r[0], r[1]);
      for(let i=0;i<arr.length-1;i++) starts.push({time:arr[i], range:r});
    });
    starts.forEach(obj=>{
      const o = document.createElement('option');
      o.value = obj.time; o.textContent = obj.time;
      o.dataset.rangeFrom = obj.range[0];
      o.dataset.rangeTo   = obj.range[1];
      $start.append(o);
    });

    let startVal = prevStart && starts.find(s=>s.time===prevStart) ? prevStart : (starts[0]?.time || '');
    if (startVal) {
      $start.val(startVal);
      const opt = $start[0].selectedOptions[0];
      const es = rangeSteps(opt.dataset.rangeFrom, opt.dataset.rangeTo).filter(t=>t>startVal);
      es.forEach(t=>$end.append(`<option value="${t}">${t}</option>`));
      if (prevEnd && es.includes(prevEnd)) $end.val(prevEnd);
    }

    $start.off('change.dstb').on('change.dstb', function(){
      const opt=this.selectedOptions[0]; if(!opt) return;
      const es = rangeSteps(opt.dataset.rangeFrom, opt.dataset.rangeTo).filter(t=>t>this.value);
      $end.empty(); es.forEach(t=>$end.append(`<option value="${t}">${t}</option>`));
    });
  }

  /** ---------- Zeiten pro Zeile aktualisieren ---------- */
  async function updateRowTimesForDate($row){
    const artist = $('#dstb-artist').val();
    const isFixed = (artist === 'Silvia' || artist === 'Sahrabie');
    const dateStr = $row.find('input[type="date"]').val();
    const $start = $row.find('select[data-kind="start"]');
    const $end   = $row.find('select[data-kind="end"]');
    const token = Date.now().toString();
    $row.data('loadingToken', token);

    $start.prop('disabled', true); 
    $end.prop('disabled', true);

    if (!dateStr || !isFixed) {
      fillGeneric($start); 
      fillGeneric($end);
      $start.prop('disabled', false); 
      $end.prop('disabled', false);
      return;
    }

    const freeRanges = await fetchFreeForDate(artist, dateStr);
    if ($row.data('loadingToken') !== token) return;

    if (freeRanges.length){
      populateRowFromRanges($row, freeRanges);
    } else {
      $start.empty().append('<option value="">Keine freien Zeiten</option>');
      $end.empty().append('<option value="">–</option>');
    }

    $start.prop('disabled', false);
    $end.prop('disabled', false);
  }

  /** ---------- UI: Zeilen anlegen/entfernen ---------- */
  function createSlotRow(i){
    return $(`
      <div class="dstb-slot" data-i="${i}">
        <label>Tag <input type="date" name="slots[${i}][date]" required></label>
        <label>Start <select name="slots[${i}][start]" data-kind="start"></select></label>
        <label>Ende <select name="slots[${i}][end]" data-kind="end"></select></label>
        <button type="button" class="dstb-remove">×</button>
      </div>
    `);
  }

  function renumberRows(){
    $('#dstb-slots .dstb-slot').each(function(idx){
      $(this).attr('data-i', idx);
      $(this).find('input,select').each(function(){
        this.name = this.name.replace(/slots\[[0-9]+\]/, `slots[${idx}]`);
      });
    });
  }

  function refreshSlotAddBtn(){
    const count = $('#dstb-slots .dstb-slot').length;
    $('#dstb-add-slot').prop('disabled', count >= 3);
  }

  // + neue Zeile
  $(document).on('click','#dstb-add-slot',async function(){
    const i = $('#dstb-slots .dstb-slot').length;
    if(i>=3) return;
    const $row = createSlotRow(i);
    $('#dstb-slots').append($row);
    refreshSlotAddBtn();
    fillGeneric($row.find('select[data-kind="start"]'));
    fillGeneric($row.find('select[data-kind="end"]'));
  });

  // - Zeile entfernen
  $(document).on('click','.dstb-slot .dstb-remove',function(){
    $(this).closest('.dstb-slot').remove();
    renumberRows();
    refreshSlotAddBtn();
  });

  // Datum geändert → freie Slots laden
  $(document).on('change', '#dstb-slots .dstb-slot input[type="date"]', async function(){
    await updateRowTimesForDate($(this).closest('.dstb-slot'));
  });

  // Artist gewechselt → alle Zeilen aktualisieren
  $(document).on('change', '#dstb-artist', async function(){
    const $rows = $('#dstb-slots .dstb-slot');
    for (const el of $rows) {
      await updateRowTimesForDate($(el));
    }
  });

  // Init: erste Zeile
  $(function(){ $('#dstb-add-slot').trigger('click'); });

  /** ---------- Upload-Vorschau & Absenden ---------- */
  $('#dstb-images').on('change',function(){
    const max=(window.DSTB_Ajax?.maxUploads)||10;
    const files=Array.from(this.files).slice(0,max);
    const $p=$('#dstb-previews').empty();
    files.forEach(f=>{
      const url=URL.createObjectURL(f);
      $p.append(`<img src="${url}" alt=""/>`);
    });
  });

  $('#dstb-form').on('submit',function(e){
    e.preventDefault();
    const fd=new FormData(this);
    const firstname=$('input[name="firstname"]').val()||'';
    const lastname=$('input[name="lastname"]').val()||'';
    fd.append('name',`${firstname.trim()} ${lastname.trim()}`.trim());
    fd.append('action','dstb_submit');
    fd.append('nonce',getNonce());

    const $msg=$('#dstb-msg').text('Sende ...');
    fetch(getAjaxUrl(),{method:'POST',body:fd,credentials:'same-origin'})
      .then(r=>r.json())
      .then(j=>{
        $msg.text(j.success ? j.data.msg : (j.data?.msg || 'Fehler beim Senden'));
        if(j.success){
          $('#dstb-form')[0].reset();
          $('#dstb-previews').empty();
          $('#dstb-slots').empty();
          $('#dstb-add-slot').prop('disabled',false).trigger('click');
        }
      })
      .catch(()=> $msg.text('Netzwerkfehler'));
  });

})(jQuery);
