(function ($) {

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

  async function monthData(artist, year, month){
    const r = await fetch(`${DSTB_Ajax.url}?action=dstb_calendar_data&artist=${encodeURIComponent(artist)}&year=${year}&month=${month+1}&nonce=${DSTB_Ajax.nonce}`);
    if(!r.ok) return {booked:{},free:{}}; 
    return r.json();
  }

  async function freeForDate(artist, dateStr){
    const r = await fetch(`${DSTB_Ajax.url}?action=dstb_free_slots&artist=${encodeURIComponent(artist)}&date=${dateStr}&nonce=${DSTB_Ajax.nonce}`);
    if(!r.ok) return []; 
    const j = await r.json(); 
    return j.free||[];
  }

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

    async function render() {
      title.textContent = new Date(state.year, state.month).toLocaleString("de-DE", { month: "long", year: "numeric" });
      grid.innerHTML = "";

      const first = new Date(state.year, state.month, 1);
      const startOffset = (first.getDay() + 6) % 7;

      const data = await monthData(artist, state.year, state.month);
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
          // Nur Informations-Popup
          slotPanel.innerHTML = "";
          slotPanel.style.display = "block";

          const dateStr = `${state.year}-${String(state.month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;

          // 💡 Überschrift (Datum in schöner Form)
          const weekdayNames = ["Mo","Di","Mi","Do","Fr","Sa","So"];
          const dateObj = new Date(state.year, state.month, d);
          const weekday = weekdayNames[(dateObj.getDay()+6)%7];
          const heading = el("div","dstb-slot-header",`
            <h4>📅 ${weekday}, ${d}.${String(state.month+1).padStart(2,'0')}.${state.year}</h4>
            <p class="dstb-slot-sub">Freie Zeitfenster an diesem Tag</p>
          `);
          slotPanel.append(heading);

          // 🗓️ Liste mit Slots
          const list = el("div","dstb-slot-list");


         

          const bookedSlots = (booked[key]||[]);
          bookedSlots.forEach(b => {
            const from = b[0], to = b[1];
            let label = `Besetzt: ${from} – ${to}`;
            if ((from === "00:00" && (to === "23:59" || to === "24:00"))) {
              label = "🏖️ Betriebsurlaub";
            }
            list.append(el("div","dstb-slot booked", label));
          });

          const freeRanges = await freeForDate(artist, dateStr);
          window.DSTB.currentDate = dateStr;
          window.DSTB.currentFreeRanges = freeRanges;

          if (freeRanges.length){
            freeRanges.forEach(fr => list.append(el("div","dstb-slot free", `Frei: ${fr[0]} – ${fr[1]}`)));
          } else {
            list.append(el("div","dstb-slot neutral","Keine freien Slots verfügbar"));
          }

          slotPanel.append(list);
        });

        grid.append(dayEl);
      }
    }

    prev.addEventListener("click", () => { if (--state.month<0){ state.month=11; state.year--; } render(); });
    next.addEventListener("click", () => { if (++state.month>11){ state.month=0; state.year++; } render(); });

    render();
  }

  // Artist-Wechsel → Kalender (Anzeige)
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
