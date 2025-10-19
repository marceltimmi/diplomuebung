(function($){

  function fillWithAllTimeSteps($select){
    const steps = (window.DSTB?.timeSteps)||[];
    $select.empty();
    steps.forEach(t=> $select.append(`<option value="${t}">${t}</option>`));
  }

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

  function refreshSlotAddBtn(){
    const count = $('#dstb-slots .dstb-slot').length;
    $('#dstb-add-slot').prop('disabled', count >= 3);
  }

  $(document).on('click','#dstb-add-slot',function(){
    const i = $('#dstb-slots .dstb-slot').length;
    if(i>=3) return;

    const $row = createSlotRow(i);
    $('#dstb-slots').append($row);
    refreshSlotAddBtn();

    const artist = $('#dstb-artist').val();
    const isFixed = (artist === 'Silvia' || artist === 'Sahrabie');
    const $start = $row.find('select[data-kind="start"]');
    const $end   = $row.find('select[data-kind="end"]');

    if(isFixed && window.DSTB?.currentFreeRanges?.length){
      const dateStr = window.DSTB?.currentDate || new Date().toISOString().split('T')[0];
      $row.find('input[type="date"]').val(dateStr);
      window.populateSlotRow($row, window.DSTB.currentFreeRanges);
    } else {
      fillWithAllTimeSteps($start);
      fillWithAllTimeSteps($end);
    }
  });

  $(document).on('click','.dstb-slot .dstb-remove',function(){
    $(this).closest('.dstb-slot').remove();
    $('#dstb-slots .dstb-slot').each(function(idx){
      $(this).attr('data-i', idx);
      $(this).find('input,select').each(function(){
        this.name = this.name.replace(/slots\[[0-9]+\]/, `slots[${idx}]`);
      });
    });
    refreshSlotAddBtn();
  });

  $(function(){ $('#dstb-add-slot').trigger('click'); });

  $('#dstb-images').on('change',function(){
    const max=(window.DSTB?.maxUploads)||10;
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
    fd.append('nonce',(window.DSTB?.nonce)||'');

    const $msg=$('#dstb-msg').text('Sende ...');
    fetch((window.DSTB?.ajax_url)||'',{method:'POST',body:fd})
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
