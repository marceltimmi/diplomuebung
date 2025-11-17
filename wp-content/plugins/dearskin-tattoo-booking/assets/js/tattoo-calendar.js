(function ($) {

  // ===== Globals =====
  window.DSTB = window.DSTB || {};
  window.DSTB.currentArtist = null;
  window.DSTB.currentDate = null;
  window.DSTB.currentFreeRanges = [];

  // ===== Helpers (eindeutige Namen, keine Kollisionen) =====
  function el(tag, cls, html) {
    const e = document.createElement(tag);
    if (cls) e.className = cls;
    if (html !== undefined) e.innerHTML = html;
    return e;
  }
  function hmToDateCal(hm){ const [h,m]=String(hm).split(':').map(Number); return new Date(2000,0,1,h||0,m||0,0,0); }
  function dateToHMCal(d){ return d.toTimeString().slice(0,5); }

  // Schneidet eine Liste freier Ranges an einer Liste gebuchter Ranges zurecht
  function subtractBookedFromFree(freeRanges, bookedRanges){
    if (!Array.isArray(freeRanges) || !freeRanges.length) return [];
    if (!Array.isArray(bookedRanges) || !bookedRanges.length) return freeRanges.slice();

    const cleaned = [];
    freeRanges.forEach(fr => {
      let fStart = hmToDateCal(fr[0]);
      const fEnd = hmToDateCal(fr[1]);
      let cursor = new Date(fStart);

      // sortierte Kopie der Buchungen
      const bookedSorted = bookedRanges
        .map(b => [hmToDateCal(b[0]), hmToDateCal(b[1])])
        .sort((a,b) => a[0] - b[0]);

      bookedSorted.forEach(([bStart, bEnd]) => {
        // kein overlap
        if (bEnd <= cursor || bStart >= fEnd) return;
        // Lücke vor der Buchung?
        if (bStart > cursor) {
          cleaned.push([ dateToHMCal(cursor), dateToHMCal(bStart) ]);
        }
        // Cursor hinter die Buchung schieben
        if (bEnd > cursor) cursor = new Date(bEnd);
        if (cursor > fEnd) cursor = new Date(fEnd);
      });

      // Rest nach letzter Buchung
      if (cursor < fEnd) {
        cleaned.push([ dateToHMCal(cursor), dateToHMCal(fEnd) ]);
      }
    });

    // Filter: nur sinnvolle Intervalle behalten
    return cleaned.filter(pair => pair && pair[0] && pair[1] && pair[0] < pair[1]);
  }

  // ===== AJAX =====
  async function monthData(artist, year, month){
    const r = await fetch(`${DSTB_Ajax.url}?action=dstb_calendar_data&artist=${encodeURIComponent(artist)}&year=${year}&month=${month+1}&nonce=${DSTB_Ajax.nonce}`);
    if(!r.ok) return {booked:{},free:{}}; 
    return r.json();
  }

  async function freeForDate(artist, dateStr){
    const r = await fetch(`${DSTB_Ajax.url}?action=dstb_free_slots&artist=${encodeURIComponent(artist)}&date=${encodeURIComponent(dateStr)}&nonce=${DSTB_Ajax.nonce}`);
    if(!r.ok) return []; 
    const j = await r.json(); 
    return j.free||[];
  }

  // ===== Calendar =====
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
      const monthBookedMap = data.booked || {};
      const monthFreeMap   = data.free   || {};
      const daysInMonth = new Date(state.year, state.month + 1, 0).getDate();

      for (let i=0;i<startOffset;i++) grid.append(el("div", "dstb-day empty", ""));

      for (let d=1; d<=daysInMonth; d++){
        const dayEl = el("div", "dstb-day", d);
        const key = String(d);

        const hasBooked = Array.isArray(monthBookedMap[key]) && monthBookedMap[key].length > 0;
        const hasFree   = Array.isArray(monthFreeMap[key])   && monthFreeMap[key].length > 0;

        if (hasBooked && hasFree) {
          dayEl.classList.add("mixed");
          const split = document.createElement('div');
          split.className = "dstb-day-split";
          dayEl.appendChild(split);
        } else if (hasBooked) {
          dayEl.classList.add("booked");
        } else if (hasFree) {
          dayEl.classList.add("free");
        } else {
          dayEl.classList.add("neutral");
        }

        dayEl.addEventListener("click", async ()=>{

          slotPanel.innerHTML = "";
          slotPanel.style.display = "block";

          const dateStr = `${state.year}-${String(state.month+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;

          // Heading
          const weekdayNames = ["Mo","Di","Mi","Do","Fr","Sa","So"];
          const dateObj = new Date(state.year, state.month, d);
          const weekday = weekdayNames[(dateObj.getDay()+6)%7];
          const heading = el("div","dstb-slot-header",`
            <h4>📅 ${weekday}, ${d}.${String(state.month+1).padStart(2,'0')}.${state.year}</h4>
            <p class="dstb-slot-sub">Freie Zeitfenster an diesem Tag</p>
          `);
          slotPanel.append(heading);

          const list = el("div","dstb-slot-list");

          // Gebuchte Slots aus Monatsdaten (nicht neu laden)
          const bookedDaySlots = (monthBookedMap[key] || []).slice(); // [[HH:MM,HH:MM], ...]
          bookedDaySlots.forEach(b => {
            const from = b[0], to = b[1];
            let label = `Besetzt: ${from} – ${to}`;
            if ((from === "00:00" && (to === "23:59" || to === "24:00"))) {
              label = "🏖️ Betriebsurlaub";
            }
            list.append(el("div","dstb-slot booked", label));
          });

          // Freie Ranges (vom Server; diese enthalten i.d.R. schon die Pufferlogik)
          let freeRangesDay = await freeForDate(artist, dateStr);

          // Sicherheitsnetz: falls der Server freie Ranges NICHT um Buchungen gekürzt hat,
          // wird hier clientseitig nochmal gekürzt:
          if (bookedDaySlots.length && freeRangesDay.length) {
            freeRangesDay = subtractBookedFromFree(freeRangesDay, bookedDaySlots);
          }

          window.DSTB.currentDate = dateStr;
          window.DSTB.currentFreeRanges = freeRangesDay;

          if (freeRangesDay.length){
            freeRangesDay.forEach(fr => list.append(el("div","dstb-slot free", `Frei: ${fr[0]} – ${fr[1]}`)));
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
    const artist = ($(this).val() || "").trim();

    const normalize = (v) => (v === undefined || v === null) ? '' : String(v).trim().toLowerCase();

    // Diese Artists sollen KEINEN Kalender haben:
    const noCalendarArtists = (window.DSTB_Ajax && Array.isArray(window.DSTB_Ajax.noCalendarArtists))
      ? window.DSTB_Ajax.noCalendarArtists
      : ["Kein bestimmter Artist", "Artist of Residence", "Kein bevorzugter Artist", ""];

    const calendarArtists = (window.DSTB_Ajax && Array.isArray(window.DSTB_Ajax.calendarArtists))
      ? window.DSTB_Ajax.calendarArtists
      : [];

    const normalizedNoCalendar = new Set(noCalendarArtists.map(normalize));
    const normalizedCalendar   = new Set(calendarArtists.map(normalize));
    const normalizedArtist     = normalize(artist);

    // Standard: Kalender anzeigen, außer explizit als kalendarfrei markiert oder leerer Auswahl
    let showCalendar = normalizedArtist !== '' && !normalizedNoCalendar.has(normalizedArtist);

    // Falls eine explizite Liste an Kalender-Artists vorhanden ist, damit zusätzlich einschränken
    if (showCalendar && normalizedCalendar.size) {
      showCalendar = normalizedCalendar.has(normalizedArtist);
    }

    $("#dstb-calendar-box").toggle(showCalendar);
    $("#dstb-slot-box").show();

    if (showCalendar) {
      const cont = document.getElementById("dstb-calendar");
      renderTattooCalendar(cont, artist);
    }
  }).trigger("change");

})(jQuery);
