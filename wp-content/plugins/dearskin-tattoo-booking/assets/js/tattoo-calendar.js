(function ($) {

  // Globale Variablen – merken den aktiven Zustand
  window.DSTB = window.DSTB || {};
  window.DSTB.currentArtist = null;
  window.DSTB.currentDate = null;
  window.DSTB.currentFreeRanges = [];

  function el(tag, cls, html) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html !== undefined) e.innerHTML = html;
    return e;
  }

  function hmToDate(hm){ const [h,m]=hm.split(':').map(Number); return new Date(2000,0,1,h,m||0); }
  function steps(from,to,step=30){
    const res=[]; let s=hmToDate(from), e=hmToDate(to);
    if(e<=s) e.setDate(e.getDate()+1);
    for(let t=new Date(s); t<e; t.setMinutes(t.getMinutes()+step)){
      res.push(t.toTimeString().slice(0,5));
    }
    return res;
  }

  /** ---- Dropdown-Befüllung (global exportiert) ---- */
  function populateSlotRow($row, freeRanges){
    const $start = $row.find('select[data-kind="start"]');
    const $end   = $row.find('select[data-kind="end"]');
    $start.empty(); $end.empty();

    if(!freeRanges || !freeRanges.length){
      $start.append(`<option value="">Keine freien Zeiten</option>`);
      $end.append(`<option value="">–</option>`);
      return;
    }

    const starts=[];
    freeRanges.forEach(range=>{
      const s=steps(range[0],range[1]);
      for(let i=0;i<s.length-1;i++){ starts.push({time:s[i], range}); }
    });

    starts.forEach(s=>{
      const o=document.createElement('option');
      o.value=s.time; o.textContent=s.time;
      o.dataset.rangeFrom=s.range[0];
      o.dataset.rangeTo=s.range[1];
      $start.append(o);
    });

    // initial Endzeiten passend zum ersten Start
    if(starts.length){
      const sel=starts[0];
      const es=steps(sel.range[0], sel.range[1]).filter(t=>t>sel.time);
      es.forEach(t=> $end.append(`<option value="${t}">${t}</option>`));
    }

    // bei Änderung Startzeit → Endzeiten neu aufbauen
    $start.on('change', function(){
      const opt=this.selectedOptions[0]; if(!opt) return;
      const range=[opt.dataset.rangeFrom,opt.dataset.rangeTo];
      const es=steps(range[0],range[1]).filter(t=>t>this.value);
      $end.empty(); es.forEach(t=> $end.append(`<option value="${t}">${t}</option>`));
    });
  }
  window.populateSlotRow = populateSlotRow;

  /** ---- Kalender ---- */
  function renderTattooCalendar(container, artist) {
    const now = new Date();
    let state = { month: now.getMonth(), year: now.getFullYear() };
    window.DSTB.currentArtist = artist;

    const card = el("div", "dstb-cal-card");
    const header = el("div", "dstb-cal-head");
    const title = el("h3", "", "");
    const nav = el("div", "dstb-cal-nav");
    const prev = el("button", "dstb-cal-btn", "«");
    const next = el("button", "dstb-cal-btn", "»");
    nav.append(prev, next);
    header.append(title, nav);
    card.append(header);

    const grid = el("div", "dstb-cal-grid");
    card.append(grid);

    const slotPanel = el("div", "dstb-slot-panel");
    slotPanel.setAttribute("data-dstb-slots", "");
    card.append(slotPanel);

    container.innerHTML = "";
    container.append(card);

    async function monthData(year, month){
      const r = await fetch(`${DSTB.ajax_url}?action=dstb_calendar_data&artist=${encodeURIComponent(artist)}&year=${year}&month=${month+1}`);
      if(!r.ok) return {booked:{},free:{}}; 
      return r.json();
    }

    async function freeForDate(dateStr){
      const r = await fetch(`${DSTB.ajax_url}?action=dstb_free_slots&artist=${encodeURIComponent(artist)}&date=${dateStr}`);
      if(!r.ok) return []; 
      const j = await r.json(); 
      return j.free||[];
    }

    async function render() {
      title.textContent = new Date(state.year, state.month).toLocaleString("de-DE", { month: "long", year: "numeric" });
      grid.innerHTML = "";

      const first = new Date(state.year, state.month, 1);
      const startOffset = (first.getDay() + 6) % 7;

      const data = await monthData(state.year, state.month);
      const booked = data.booked || {};
      const free   = data.free   || {};
      const daysInMonth = new Date(state.year, state.month + 1, 0).getDate();

      for (let i=0;i<startOffset;i++) grid.append(el("div", "dstb-day empty", ""));

      for (let d=1; d<=daysInMonth; d++){
        const dayEl = el("div", "dstb-day", d);
        const key = String(d);

        if (booked[key]) dayEl.classList.add("booked");
        else if (free[key]) dayEl.classList.add("free");
        else dayEl.classList.add("neutral");

        dayEl.addEventListener("click", async ()=>{
          slotPanel.innerHTML = "";
          slotPanel.style.display = "block";
          const dateStr = `${state.year}-${String(state.month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
          slotPanel.append(el("h4","",`📅 ${d}.${state.month+1}.${state.year}`));

          const list = el("div","dstb-slot-list");
            const bookedSlots = (booked[key] || []);
            bookedSlots.forEach(b => {
                const from = b[0];
                const to = b[1];
                let label = `Besetzt: ${from} – ${to}`;

            // 👉 Wenn ganztägig gesperrt, als "Betriebsurlaub" anzeigen
            if ((from === "00:00" && to === "23:59") || (from === "00:00" && to === "24:00")) {
                label = "🏖️ Betriebsurlaub";
            }

  list.append(el("div", "dstb-slot booked", label));
});


          const freeRanges = await freeForDate(dateStr);
          window.DSTB.currentDate = dateStr;
          window.DSTB.currentFreeRanges = freeRanges;

          if (freeRanges.length){
            freeRanges.forEach(fr => list.append(el("div","dstb-slot free", `Frei: ${fr[0]} – ${fr[1]}`)));
          } else {
            list.append(el("div","dstb-slot neutral","Keine freien Slots verfügbar"));
          }
          slotPanel.append(list);

          // Kalender-Slots direkt befüllen
          const $rows = $('#dstb-slots .dstb-slot');
          $rows.each(function(){
            $(this).find('input[type="date"]').val(dateStr);
            populateSlotRow($(this), freeRanges);
          });
        });

        grid.append(dayEl);
      }
    }

    prev.addEventListener("click", () => { if (--state.month<0){ state.month=11; state.year--; } render(); });
    next.addEventListener("click", () => { if (++state.month>11){ state.month=0; state.year++; } render(); });

    render().then(async ()=>{
      // 👇 Fix: beim Laden sofort freie Zeiten für HEUTE laden
      const todayStr = new Date().toISOString().split('T')[0];
      const freeRanges = await freeForDate(todayStr);
      window.DSTB.currentDate = todayStr;
      window.DSTB.currentFreeRanges = freeRanges;
      // Slots automatisch initialisieren
      $('#dstb-slots .dstb-slot').each(function(){
        $(this).find('input[type="date"]').val(todayStr);
        populateSlotRow($(this), freeRanges);
      });
    });
  }

  /** ---- Umschalten Artist ---- */
  $(document).on("change", "#dstb-artist", function () {
    const artist = $(this).val();
    const isFixed = artist === "Silvia" || artist === "Sahrabie";
    $("#dstb-calendar-box").toggle(isFixed);
    $("#dstb-slot-box").show();
    if (isFixed) {
      const cont = document.getElementById("dstb-calendar");
      renderTattooCalendar(cont, artist);
    }
  }).trigger("change");

})(jQuery);
