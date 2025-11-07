(function($){

  /** ---------- Hilfsfunktionen ---------- */
  function getAjaxUrl(){
    // Primär DSTB_Ajax (Frontend-Lokalisierung)
    if (window.DSTB_Ajax && typeof DSTB_Ajax.url === 'string' && DSTB_Ajax.url.length)
      return DSTB_Ajax.url;
    // Fallbacks
    if (window.wp && wp.ajax && wp.ajax.settings && wp.ajax.settings.url)
      return wp.ajax.settings.url;
    return '/wp-admin/admin-ajax.php';
  }

  function getNonce(){
    // Nur noch die aktuelle Lokalisierung verwenden
    if (window.DSTB_Ajax && typeof DSTB_Ajax.nonce === 'string')
      return DSTB_Ajax.nonce;
    return '';
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
    $sel.empty();
    s.forEach(t=>$sel.append(`<option value="${t}">${t}</option>`));
  }

  /** ---------- Server: freie Ranges für Artist/Datum ---------- */
  async function fetchFreeForDate(artist, dateStr) {
    const d = new Date(dateStr);
    const year = d.getFullYear();
    const month = d.getMonth() + 1;
    const day = d.getDate();
    const url = `${getAjaxUrl()}?action=dstb_calendar_data&artist=${encodeURIComponent(artist)}&year=${year}&month=${month}&nonce=${encodeURIComponent(getNonce())}`;

    try {
      const r = await fetch(url, { credentials: 'same-origin' });
      if (!r.ok) return [];
      const j = await r.json();
      if (!j || !j.free || !j.booked) return [];

      // Arrays der aktuellen Tagesnummer
      const dkey = String(day);
      const free = j.free[dkey] || [];
      const booked = j.booked[dkey] || [];

      // 🔍 Entferne gebuchte Teilbereiche aus den freien Ranges
      const clean = [];
      free.forEach(fr => {
        let [fStart, fEnd] = fr.map(hmToDate);
        booked.forEach(br => {
          const [bStart, bEnd] = br.map(hmToDate);
          // Freie Bereiche anpassen, falls sie sich überschneiden
          if (bEnd <= fStart || bStart >= fEnd) return; // kein Überlapp
          if (bStart > fStart) clean.push([formatHM(fStart), formatHM(bStart)]);
          fStart = bEnd < fEnd ? bEnd : fEnd;
        });
        if (fStart < fEnd) clean.push([formatHM(fStart), formatHM(fEnd)]);
      });

      return clean;
    } catch (e) {
      console.error('Fehler beim Laden freier Slots:', e);
      return [];
    }

    function formatHM(date) {
      return date.toTimeString().slice(0, 5);
    }
  }



  /** ---------- Dropdown einer Zeile befüllen ---------- */
  function populateRowFromRanges($row, freeRanges){
    const $start = $row.find('select[data-kind="start"]');
    const prevStart = $start.val();
    $start.empty();

    if(!freeRanges || !freeRanges.length){
      $start.append('<option value="">Keine freien Zeiten</option>');
      return;
    }

    const starts=[];
    freeRanges.forEach(r=>{
      const arr = rangeSteps(r[0], r[1]);
      for(let i=0;i<arr.length-1;i++){
        starts.push({time:arr[i]});
      }
    });

    if(!starts.length){
      $start.append('<option value="">Keine freien Zeiten</option>');
      return;
    }

    starts.forEach(obj=>{
      const o = document.createElement('option');
      o.value = obj.time;
      o.textContent = obj.time;
      $start.append(o);
    });

    if (prevStart && starts.find(s=>s.time===prevStart)) {
      $start.val(prevStart);
    }
  }

  /** ---------- Zeiten pro Zeile aktualisieren ---------- */
  async function updateRowTimesForDate($row){
    const artist = $('#dstb-artist').val();
    const hasIndividualCalendar =
    artist &&
    artist.trim() !== '' &&
    artist !== 'Kein bestimmter Artist' &&
    artist !== 'Artist of Residence';
    const dateStr = $row.find('input[type="date"]').val();
    const $start = $row.find('select[data-kind="start"]');

    const token = Date.now().toString();
    $row.data('loadingToken', token);
    $start.prop('disabled', true);

    if (!dateStr || !hasIndividualCalendar) {
      fillGeneric($start);
      $start.prop('disabled', false);
      return;
    }

    const freeRanges = await fetchFreeForDate(artist, dateStr);
    if ($row.data('loadingToken') !== token) return;

    if (freeRanges.length){
      populateRowFromRanges($row, freeRanges);
    } else {
      $start.empty().append('<option value="">Keine freien Zeiten</option>');
    }

    $start.prop('disabled', false);
  }

  /** ---------- UI: Zeilen anlegen/entfernen ---------- */
  function createSlotRow(i){
    return $(`
      <div class="dstb-slot" data-i="${i}">
        <label>Tag
          <input type="date" name="slots[${i}][date]" required>
        </label>
        <label>Startzeit
          <select name="slots[${i}][start]" data-kind="start" required></select>
        </label>
        <button type="button" class="dstb-remove" aria-label="Zeitfenster entfernen">×</button>
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

  $(document).on('click','#dstb-add-slot',async function(){
    const i = $('#dstb-slots .dstb-slot').length;
    if(i>=3) return;
    const $row = createSlotRow(i);
    $('#dstb-slots').append($row);
    refreshSlotAddBtn();
    fillGeneric($row.find('select[data-kind="start"]'));
  });

  $(document).on('click','.dstb-slot .dstb-remove',function(){
    $(this).closest('.dstb-slot').remove();
    renumberRows();
    refreshSlotAddBtn();
  });

  $(document).on('change', '#dstb-slots .dstb-slot input[type="date"]', async function(){
    await updateRowTimesForDate($(this).closest('.dstb-slot'));
  });

  $(document).on('change', '#dstb-artist', async function(){
    const $rows = $('#dstb-slots .dstb-slot');
    for (const el of $rows) {
      await updateRowTimesForDate($(el));
    }
  });

  $(function(){ $('#dstb-add-slot').trigger('click'); });

  /** ---------- Upload-Vorschau ---------- */
  $('#dstb-images').on('change',function(){
    const max = (window.DSTB_Ajax?.maxUploads) || 10;
    const files = Array.from(this.files).slice(0,max);
    const $p = $('#dstb-previews').empty();
    files.forEach(f=>{
      const url=URL.createObjectURL(f);
      $p.append(`<img src="${url}" alt=""/>`);
    });
  });

  /** ---------- Absenden ---------- */
  $('#dstb-form').on('submit',function(e){
    e.preventDefault();
    const fd = new FormData(this);
    const firstname = $('input[name="firstname"]').val() || '';
    const lastname  = $('input[name="lastname"]').val()  || '';

    fd.append('name', `${firstname.trim()} ${lastname.trim()}`.trim());
    fd.append('action','dstb_submit');
    fd.append('nonce', getNonce());

    const $msg = $('#dstb-msg').text('Sende ...');

    fetch(getAjaxUrl(), {
      method:'POST',
      body: fd,
      credentials:'same-origin'
    })
    .then(async r=>{
      let j = null;
      try { j = await r.json(); } catch(e) {
        console.error('Server gab kein JSON zurück', e);
      }
      return j || { success:false, data:{ msg:'Unerwartete Server-Antwort.' } };
    })
    .then(j=>{
      $msg.text(j.success ? j.data.msg : (j.data?.msg || 'Fehler beim Senden'));
      if(j.success){
        $('#dstb-form')[0].reset();
        $('#dstb-previews').empty();
        $('#dstb-slots').empty();
        $('#dstb-add-slot').prop('disabled', false).trigger('click');
      }
    })
    .catch(e=>{
      console.error('Fetch-Fehler', e);
      $msg.text('Netzwerkfehler');
    });
  });

})(jQuery);
