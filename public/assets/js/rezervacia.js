// Multi-step reservation: Step 1 package, Step 2 calendar + slot, Step 3 contact
const root = document.getElementById('rezervacia');
if (root) {
  const form         = document.getElementById('rezervacia-form');
  const steps        = Array.from(root.querySelectorAll('[data-step]'));
  const stepIndicators = Array.from(root.querySelectorAll('[data-step-indicator]'));
  const pkgInput     = document.getElementById('f-package');
  const dateInput    = document.getElementById('f-date');
  const timeInput    = document.getElementById('f-time');
  const calendarGrid = document.getElementById('calendar-grid');
  const calendarTitle = document.getElementById('calendar-title');
  const slotSection  = document.getElementById('slot-section');
  const slotGrid     = document.getElementById('slot-grid');
  const to3Btn       = document.getElementById('to-step-3');
  const errorBox     = document.getElementById('form-error');
  const submitBtn    = document.getElementById('submit-btn');
  const cookieGate   = document.getElementById('cookie-gate');
  const selPkgName   = document.getElementById('selected-package-name');
  const selPkgDur    = document.getElementById('selected-package-duration');
  const sumPkg       = document.getElementById('summary-package');
  const sumDate      = document.getElementById('summary-date');
  const sumTime      = document.getElementById('summary-time');
  const successLink  = document.getElementById('success-link');

  const siteKey = document.querySelector('meta[name="recaptcha-site-key"]')?.content ?? '';
  let recaptchaPromise = null;
  let currentMonth = new Date();
  currentMonth.setDate(1);
  let selectedDuration = 0; // minutes — duration of the picked package

  // "09:00" + 120 min → "11:00"
  function addMinutes(hm, minutes) {
    const [h, m] = hm.split(':').map(Number);
    const total = h * 60 + m + minutes;
    return String(Math.floor(total / 60)).padStart(2, '0') + ':' + String(total % 60).padStart(2, '0');
  }

  // ---------- Step navigation ----------
  function goStep(step) {
    steps.forEach(s => s.classList.toggle('is-active', s.dataset.step === String(step)));
    stepIndicators.forEach(li => {
      const n = li.dataset.stepIndicator;
      li.classList.toggle('is-active', n === String(step));
      li.classList.toggle('is-done', Number(n) < Number(step) || step === 'success');
    });
    location.hash = step === 'success' ? '#hotovo' : '#krok-' + step;
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  document.querySelectorAll('[data-go-step]').forEach(btn => {
    btn.addEventListener('click', () => goStep(btn.dataset.goStep));
  });

  // ---------- Step 1: package pick ----------
  document.querySelectorAll('[data-pick-package]').forEach(card => {
    card.addEventListener('click', () => {
      const code = card.dataset.pickPackage;
      const dur  = card.dataset.duration;
      pkgInput.value = code;
      selectedDuration = parseInt(dur, 10) || 0;
      selPkgName.textContent = card.querySelector('h2').textContent;
      selPkgDur.textContent  = dur;
      sumPkg.textContent     = card.querySelector('h2').textContent;
      goStep(2);
      loadMonth();
    });
  });

  // ---------- Step 2: calendar ----------
  const MONTH_NAMES = ['Január','Február','Marec','Apríl','Máj','Jún','Júl','August','September','Október','November','December'];
  const DAY_NAMES = ['Nedeľa','Pondelok','Utorok','Streda','Štvrtok','Piatok','Sobota'];

  // Visually-hidden live region for screen-reader announcements
  const calendarAnnouncer = document.createElement('div');
  calendarAnnouncer.setAttribute('aria-live', 'polite');
  calendarAnnouncer.setAttribute('aria-atomic', 'true');
  calendarAnnouncer.className = 'sr-only';
  calendarAnnouncer.id = 'calendar-announcer';
  document.body.appendChild(calendarAnnouncer);

  function monthKey(d) {
    return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0');
  }

  async function loadMonth() {
    const pkg = pkgInput.value;
    if (!pkg) return;
    const mKey = monthKey(currentMonth);
    calendarTitle.textContent = MONTH_NAMES[currentMonth.getMonth()] + ' ' + currentMonth.getFullYear();
    calendarGrid.innerHTML = '<p class="calendar__hint">Načítavam dostupné dni…</p>';
    slotSection.hidden = true;
    timeInput.value = '';
    to3Btn.disabled = true;

    let data;
    try {
      const res = await fetch(`/api/month-availability?month=${mKey}&package=${pkg}`);
      data = await res.json();
      if (!res.ok) throw new Error(data.error || 'load_failed');
    } catch (e) {
      calendarGrid.innerHTML = '<p class="calendar__hint">Načítanie zlyhalo. Skúste znova.</p>';
      return;
    }

    renderGrid(data.days);
  }

  function renderGrid(days) {
    calendarGrid.innerHTML = '';
    calendarGrid.setAttribute('role', 'grid');
    calendarGrid.setAttribute('aria-label', 'Kalendár dostupných termínov');

    const year = currentMonth.getFullYear();
    const month = currentMonth.getMonth();
    const first = new Date(year, month, 1);
    const last = new Date(year, month + 1, 0);

    // Calendar starts on Monday — JS getDay returns 0=Sun…6=Sat
    const startOffset = (first.getDay() + 6) % 7;
    const totalCells = startOffset + last.getDate();
    const rows = Math.ceil(totalCells / 7);
    const cells = rows * 7;

    const today = new Date(); today.setHours(0, 0, 0, 0);
    const todayIso = today.toISOString().slice(0, 10);

    // Track the first available (or today's) cell for roving tabindex seed
    let firstFocusable = null;

    for (let i = 0; i < cells; i++) {
      const dayNum = i - startOffset + 1;
      const inMonth = dayNum >= 1 && dayNum <= last.getDate();
      const cellDate = new Date(year, month, dayNum);
      const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(cellDate.getDate()).padStart(2, '0')}`;
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'day';
      cell.setAttribute('role', 'gridcell');
      cell.setAttribute('tabindex', '-1');
      if (!inMonth) {
        // Spacer: keyboard-focusable for grid traversal, but inert for activation.
        // No aria-hidden — a focusable aria-hidden element is an ARIA violation;
        // give it a descriptive label instead so AT announces it as off-month.
        cell.classList.add('day--off-month');
        cell.setAttribute('aria-disabled', 'true');
        cell.setAttribute('aria-label', 'Mimo aktuálneho mesiaca');
        calendarGrid.appendChild(cell);
        continue;
      }

      const info = days?.[iso];
      const dayName = DAY_NAMES[cellDate.getDay()];
      const monthName = MONTH_NAMES[month];
      const dayLabel = document.createElement('span');
      dayLabel.textContent = dayNum;
      cell.appendChild(dayLabel);

      if (cellDate < today) {
        cell.classList.add('day--past');
        cell.setAttribute('aria-disabled', 'true');
        cell.setAttribute('aria-label', `${dayName} ${dayNum}. ${monthName}, v minulosti`);
      } else if (!info || info.status === 'unavailable') {
        const reason = info?.reason;
        if (reason === 'closed_day' || reason === 'blocked_full_day') {
          cell.classList.add('day--closed');
        } else if (reason === 'before_lead' || reason === 'after_horizon') {
          cell.classList.add('day--past');
        } else {
          cell.classList.add('day--full');
        }
        cell.title = reasonLabel(reason);
        cell.setAttribute('aria-disabled', 'true');
        cell.setAttribute('aria-label', `${dayName} ${dayNum}. ${monthName}, plne obsadené`);
      } else {
        const freeCount = info.free_count ?? info.slots ?? 0;
        cell.classList.add('day--available');
        cell.dataset.date = iso;
        cell.dataset.free = String(freeCount);
        cell.title = 'Voľné — kliknite pre výber času';
        cell.setAttribute('aria-selected', 'false');
        cell.setAttribute('aria-label', `${dayName} ${dayNum}. ${monthName}, dostupné, ${freeCount} voľných termínov`);
        cell.setAttribute('aria-disabled', 'false');
        cell.addEventListener('click', () => selectDay(cell, iso));
        if (firstFocusable === null) firstFocusable = cell;
      }

      if (iso === todayIso) {
        cell.classList.add('is-today');
        if (firstFocusable === null) firstFocusable = cell;
      }

      calendarGrid.appendChild(cell);
    }

    // FIX A: a month with no available days and no "today" cell would otherwise
    // get NO tabindex=0, leaving the grid completely un-Tab-able. Fall back to
    // the very first gridcell so the grid always has exactly one tab stop.
    if (!firstFocusable) {
      firstFocusable = calendarGrid.querySelector('[role="gridcell"]');
    }

    // Set initial roving tabindex on the first focusable cell
    if (firstFocusable) firstFocusable.setAttribute('tabindex', '0');
  }

  function reasonLabel(reason) {
    return ({
      closed_day:       'Zatvorené',
      blocked_full_day: 'Blokovaný deň',
      before_lead:      'Príliš skoro',
      after_horizon:    'Príliš ďaleko',
      full:             'Plne obsadené',
    })[reason] ?? 'Nedostupné';
  }

  // Move roving tabindex + DOM focus to a cell (used by keyboard nav)
  function focusCell(cell) {
    if (!cell) return;
    calendarGrid.querySelectorAll('[role="gridcell"]').forEach(c => c.setAttribute('tabindex', '-1'));
    cell.setAttribute('tabindex', '0');
    cell.focus();
  }

  async function selectDay(cell, iso) {
    calendarGrid.querySelectorAll('.day').forEach(c => {
      c.classList.remove('is-selected');
      if (c.hasAttribute('aria-selected')) c.setAttribute('aria-selected', 'false');
    });
    cell.classList.add('is-selected');
    cell.setAttribute('aria-selected', 'true');
    // Selected cell becomes the roving tabindex anchor
    calendarGrid.querySelectorAll('[role="gridcell"]').forEach(c => c.setAttribute('tabindex', '-1'));
    cell.setAttribute('tabindex', '0');

    // Announce selection to screen readers
    const d = new Date(iso + 'T00:00:00');
    // Free-slot count is stored on the cell at render time (FIX C);
    // falls back to '' so the announcement still works if absent.
    const freeCount = cell.dataset.free ?? '';
    calendarAnnouncer.textContent =
      `Vybraný ${d.getDate()}. ${MONTH_NAMES[d.getMonth()]}. ${freeCount} voľných termínov nižšie.`;

    dateInput.value = iso;
    sumDate.textContent = iso;
    timeInput.value = '';
    sumTime.textContent = '—';
    to3Btn.disabled = true;
    slotSection.hidden = false;
    slotGrid.innerHTML = '<p style="color:#7A7A7A">Načítavam časy…</p>';

    try {
      const res = await fetch(`/api/availability?date=${iso}&package=${pkgInput.value}`);
      const data = await res.json();
      renderSlots(data.slots ?? []);
    } catch {
      slotGrid.innerHTML = '<p style="color:#c0392b">Načítanie zlyhalo.</p>';
    }
  }

  function renderSlots(slots) {
    slotGrid.innerHTML = '';
    if (!slots.length) {
      slotGrid.innerHTML = '<p style="color:#c0392b">V tento deň už nie sú voľné časy.</p>';
      return;
    }

    // Help text — explains that you pick a START time and the package spans N hours
    const durH = Math.floor(selectedDuration / 60);
    const durM = selectedDuration % 60;
    const durLabel = durM === 0 ? `${durH} h` : `${durH} h ${durM} min`;
    const hint = document.createElement('p');
    hint.className = 'slot-help';
    hint.innerHTML = `Vyberte <strong>začiatok</strong> oslavy. Každý termín pokrýva celý balíček (<strong>${durLabel}</strong>).`;
    slotGrid.appendChild(hint);

    const list = document.createElement('div');
    list.className = 'slot-list';
    slots.forEach(t => {
      const end = addMinutes(t, selectedDuration);
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'slot';
      b.setAttribute('role', 'radio');
      b.setAttribute('aria-checked', 'false');
      b.dataset.start = t;
      b.innerHTML = `<span class="slot__range">${t} – ${end}</span><span class="slot__dur">${durLabel}</span>`;
      b.setAttribute('aria-label', `Oslava ${t} až ${end}, ${durLabel}`);
      b.addEventListener('click', () => {
        list.querySelectorAll('.slot').forEach(s => {
          s.classList.remove('is-selected');
          s.setAttribute('aria-checked', 'false');
        });
        b.classList.add('is-selected');
        b.setAttribute('aria-checked', 'true');
        timeInput.value = t;
        sumTime.textContent = `${t} – ${end}`;
        to3Btn.disabled = false;
      });
      list.appendChild(b);
    });
    slotGrid.appendChild(list);

    // QoL: default the time to 14:00 when the user has not yet chosen a slot
    // for this day. selectDay() resets timeInput.value before re-rendering, so
    // this only pre-selects on a fresh day; a manual pick keeps timeInput.value
    // set and we leave it alone. Reuse the exact click path so the selected
    // state / hidden input / summary / button-enable all stay consistent.
    if (!timeInput.value) {
      const preferred = Array.from(list.querySelectorAll('.slot'))
        .find(s => s.dataset.start === '14:00');
      if (preferred) preferred.click();
    }
  }

  // Calendar navigation
  function gotoPrevMonth() {
    currentMonth.setMonth(currentMonth.getMonth() - 1);
    loadMonth();
  }
  function gotoNextMonth() {
    currentMonth.setMonth(currentMonth.getMonth() + 1);
    loadMonth();
  }
  document.querySelector('[data-cal-nav="prev"]').addEventListener('click', gotoPrevMonth);
  document.querySelector('[data-cal-nav="next"]').addEventListener('click', gotoNextMonth);
  // FIX D: bind grid keydown exactly once for the page lifetime (not per
  // renderGrid). handleGridKeydown resolves the live cells dynamically via
  // gridCells() at event time, so re-rendered grids are handled correctly.
  calendarGrid.addEventListener('keydown', handleGridKeydown);

  // ---------- Grid keyboard navigation ----------
  // ALL cells in DOM order, including off-month spacers. Index maps to grid
  // geometry: column = index % 7, row = floor(index / 7). Off-month spacer
  // cells are valid navigation targets so the focus ring can traverse the
  // whole 7-wide grid; only their selection (Enter/Space) is a no-op.
  function gridCells() {
    return Array.from(calendarGrid.querySelectorAll('[role="gridcell"]'));
  }

  function handleGridKeydown(e) {
    const cells = gridCells();
    if (!cells.length) return;

    // Anchor on the currently focused cell, or the roving-tabindex cell.
    let current = cells.indexOf(document.activeElement);
    if (current === -1) {
      current = cells.findIndex(c => c.getAttribute('tabindex') === '0');
      if (current === -1) current = 0;
    }

    // INTENTIONAL: ARIA APG *date-picker* pattern (not generic grid) — arrows do
    // continuous ±1 day nav across week boundaries; Home/End = week start/end.
    // Do not "fix" this to per-cell grid semantics; it matches the APG spec.
    let target = current;
    switch (e.key) {
      case 'ArrowLeft':  target = current - 1; break;
      case 'ArrowRight': target = current + 1; break;
      case 'ArrowUp':    target = current - 7; break;
      case 'ArrowDown':  target = current + 7; break;
      case 'Home':       target = current - (current % 7); break;
      case 'End':        target = current - (current % 7) + 6; break;
      case 'PageUp':     e.preventDefault(); gotoPrevMonth(); return;
      case 'PageDown':   e.preventDefault(); gotoNextMonth(); return;
      case 'Enter':
      case ' ':
      case 'Spacebar': {
        const c = cells[current];
        if (c && c.classList.contains('day--available') && c.dataset.date) {
          e.preventDefault();
          selectDay(c, c.dataset.date);
        }
        return;
      }
      default:
        return;
    }

    e.preventDefault();
    if (target < 0 || target >= cells.length) return; // edge of grid — stay put
    focusCell(cells[target]);
  }

  // ---------- Cookie consent + reCAPTCHA ----------
  function consentAccepted() {
    return localStorage.getItem('kuko_cookie_consent') === 'accepted';
  }

  function loadRecaptcha() {
    if (recaptchaPromise) return recaptchaPromise;
    if (!siteKey) return Promise.resolve(null);
    recaptchaPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
      s.async = true; s.defer = true;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('reCAPTCHA load failed'));
      document.head.appendChild(s);
    });
    return recaptchaPromise;
  }

  function updateCookieGate() {
    if (!siteKey || !cookieGate) return;
    if (!consentAccepted()) {
      cookieGate.hidden = false;
      submitBtn.disabled = true;
    } else {
      cookieGate.hidden = true;
      submitBtn.disabled = false;
      loadRecaptcha().catch(() => {});
    }
  }
  document.addEventListener('kuko:consent', updateCookieGate);
  updateCookieGate();

  // Cookie banner accept buttons (inline + banner)
  document.addEventListener('click', e => {
    const btn = e.target.closest('[data-cookie-action]');
    if (!btn) return;
    const decision = btn.dataset.cookieAction === 'accept' ? 'accepted' : 'denied';
    localStorage.setItem('kuko_cookie_consent', decision);
    document.dispatchEvent(new CustomEvent('kuko:consent', { detail: { value: decision } }));
    const banner = document.getElementById('cookie-banner');
    if (banner) banner.hidden = true;
  });

  // Banner appears on first visit
  const banner = document.getElementById('cookie-banner');
  if (banner && !localStorage.getItem('kuko_cookie_consent')) banner.hidden = false;

  // ---------- Draft persistence (QoL) ----------
  // Persist only the personal text fields to sessionStorage so an accidental
  // reload doesn't lose typed contact info. Date/time/package are intentionally
  // NOT persisted (avoids stale-availability bugs). Wrapped in try/catch —
  // sessionStorage can throw in private mode / when storage is disabled.
  const DRAFT_KEY = 'kuko_resv_draft';
  const draftFields = ['kids_count', 'name', 'phone', 'email', 'note'];

  function draftEl(name) {
    return form.querySelector(`[name="${name}"]`);
  }

  function saveDraft() {
    try {
      const data = {};
      draftFields.forEach(n => {
        const el = draftEl(n);
        if (el) data[n] = el.value;
      });
      sessionStorage.setItem(DRAFT_KEY, JSON.stringify(data));
    } catch (e) { /* storage unavailable — ignore */ }
  }

  function restoreDraft() {
    try {
      const raw = sessionStorage.getItem(DRAFT_KEY);
      if (!raw) return;
      const data = JSON.parse(raw);
      draftFields.forEach(n => {
        const el = draftEl(n);
        // Only restore into empty fields so we never clobber a fresh entry.
        if (el && !el.value && data[n] != null && data[n] !== '') {
          el.value = data[n];
        }
      });
    } catch (e) { /* corrupt / unavailable — ignore */ }
  }

  function clearDraft() {
    try { sessionStorage.removeItem(DRAFT_KEY); } catch (e) { /* ignore */ }
  }

  draftFields.forEach(n => {
    const el = draftEl(n);
    if (!el) return;
    el.addEventListener('input', saveDraft);
    el.addEventListener('change', saveDraft);
  });
  restoreDraft();

  // ---------- Step 3 submit ----------
  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.hidden = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Odosielam…';

    try {
      if (!pkgInput.value || !dateInput.value || !timeInput.value) {
        throw new Error('Chýba balíček, dátum alebo čas. Vráťte sa späť.');
      }

      let recaptchaToken = '';
      if (siteKey) {
        if (!consentAccepted()) throw new Error('Pre odoslanie potvrďte cookies.');
        await loadRecaptcha();
        if (!window.grecaptcha) throw new Error('reCAPTCHA sa nepodarilo načítať.');
        recaptchaToken = await new Promise((resolve, reject) => {
          window.grecaptcha.ready(() => {
            window.grecaptcha.execute(siteKey, { action: 'reservation' }).then(resolve).catch(reject);
          });
        });
      }

      const fd = new FormData(form);
      const payload = Object.fromEntries(fd);
      payload.kids_count = Number(payload.kids_count);
      payload.recaptcha_token = recaptchaToken;

      const res = await fetch('/api/reservation', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(payload),
      });
      const json = await res.json().catch(() => ({}));

      if (!res.ok) {
        if (json.error === 'slot_taken') {
          throw new Error('Tento termín bol medzitým zarezervovaný. Vyberte iný voľný čas.');
        }
        if (json.error === 'validation' && json.fields) {
          throw new Error('Skontrolujte: ' + Object.values(json.fields).join(' '));
        }
        if (json.error === 'rate_limited') throw new Error('Príliš veľa pokusov. Skúste neskôr.');
        if (json.error === 'csrf_invalid') throw new Error('Bezpečnostný token vypršal. Obnovte stránku.');
        if (json.error === 'spam_blocked') throw new Error('Detekcia spamu zablokovala odoslanie.');
        throw new Error('Odoslanie zlyhalo. Skúste prosím znova alebo nás kontaktujte.');
      }

      clearDraft();
      goStep('success');
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = false;
      submitBtn.textContent = 'Odoslať rezerváciu';
    }
  });

  // ---------- Hash routing for back button ----------
  if (location.hash.startsWith('#krok-')) {
    const step = location.hash.slice(6);
    if (['1', '2', '3'].includes(step)) goStep(step);
  }
  window.addEventListener('popstate', () => {
    if (location.hash.startsWith('#krok-')) {
      goStep(location.hash.slice(6));
    } else {
      goStep(1);
    }
  });

  // ---------- Auto-pick package from URL query (?balicek=mini) ----------
  // Runs last so all consts/functions (MONTH_NAMES, loadMonth, …) are initialized.
  const urlPkg = new URLSearchParams(location.search).get('balicek');
  if (urlPkg) {
    const card = document.querySelector(`[data-pick-package="${urlPkg}"]`);
    if (card) card.click();
  }
}
