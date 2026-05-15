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

  // Auto-pick from URL query (?balicek=mini)
  const urlPkg = new URLSearchParams(location.search).get('balicek');
  if (urlPkg) {
    const card = document.querySelector(`[data-pick-package="${urlPkg}"]`);
    if (card) card.click();
  }

  // ---------- Step 2: calendar ----------
  const MONTH_NAMES = ['Január','Február','Marec','Apríl','Máj','Jún','Júl','August','September','Október','November','December'];

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

    for (let i = 0; i < cells; i++) {
      const dayNum = i - startOffset + 1;
      const inMonth = dayNum >= 1 && dayNum <= last.getDate();
      const cellDate = new Date(year, month, dayNum);
      const iso = `${year}-${String(month + 1).padStart(2, '0')}-${String(cellDate.getDate()).padStart(2, '0')}`;
      const cell = document.createElement('button');
      cell.type = 'button';
      cell.className = 'day';
      cell.setAttribute('role', 'gridcell');
      if (!inMonth) {
        cell.classList.add('day--off-month');
        cell.disabled = true;
        cell.textContent = '';
        calendarGrid.appendChild(cell);
        continue;
      }

      const info = days?.[iso];
      const dayLabel = document.createElement('span');
      dayLabel.textContent = dayNum;
      cell.appendChild(dayLabel);

      if (cellDate < today) {
        cell.classList.add('day--past');
        cell.disabled = true;
      } else if (!info || info.status === 'unavailable') {
        const reason = info?.reason;
        if (reason === 'closed_day' || reason === 'blocked_full_day') {
          cell.classList.add('day--closed');
        } else if (reason === 'before_lead' || reason === 'after_horizon') {
          cell.classList.add('day--past');
        } else {
          cell.classList.add('day--full');
        }
        cell.disabled = true;
        cell.title = reasonLabel(reason);
      } else {
        cell.classList.add('day--available');
        cell.dataset.date = iso;
        cell.title = 'Voľné — kliknite pre výber času';
        cell.addEventListener('click', () => selectDay(cell, iso));
      }

      if (iso === today.toISOString().slice(0, 10)) {
        cell.classList.add('is-today');
      }

      calendarGrid.appendChild(cell);
    }
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

  async function selectDay(cell, iso) {
    calendarGrid.querySelectorAll('.day').forEach(c => c.classList.remove('is-selected'));
    cell.classList.add('is-selected');
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
  }

  // Calendar navigation
  document.querySelector('[data-cal-nav="prev"]').addEventListener('click', () => {
    currentMonth.setMonth(currentMonth.getMonth() - 1);
    loadMonth();
  });
  document.querySelector('[data-cal-nav="next"]').addEventListener('click', () => {
    currentMonth.setMonth(currentMonth.getMonth() + 1);
    loadMonth();
  });

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
}
