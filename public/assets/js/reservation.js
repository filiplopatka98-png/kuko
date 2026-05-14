// Reservation modal with cookie gate + reCAPTCHA v3 + dynamic slot picker
const modal = document.getElementById('reservation-modal');
if (modal) {
  const form        = document.getElementById('reservation-form');
  const errorBox    = document.getElementById('modal-error');
  const successBox  = document.getElementById('modal-success');
  const fields      = document.getElementById('modal-fields');
  const actions     = document.getElementById('modal-actions');
  const submitBtn   = document.getElementById('modal-submit');
  const cookieGate  = document.getElementById('modal-cookie-gate');
  const slotPicker  = document.getElementById('slot-picker');
  const hiddenTime  = document.getElementById('wished_time');
  const dateInput   = form.querySelector('[name="wished_date"]');
  const pkgSelect   = form.querySelector('[name="package"]');

  const siteKey = document.querySelector('meta[name="recaptcha-site-key"]')?.content ?? '';
  let recaptchaPromise = null;
  let availabilityAbort = null;

  function consentAccepted() {
    return localStorage.getItem('kuko_cookie_consent') === 'accepted';
  }

  function loadRecaptcha() {
    if (recaptchaPromise) return recaptchaPromise;
    if (!siteKey) return Promise.resolve(null);
    recaptchaPromise = new Promise((resolve, reject) => {
      const s = document.createElement('script');
      s.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(siteKey)}`;
      s.async = true;
      s.defer = true;
      s.onload = () => resolve();
      s.onerror = () => reject(new Error('reCAPTCHA load failed'));
      document.head.appendChild(s);
    });
    return recaptchaPromise;
  }

  function updateCookieGate() {
    if (!siteKey) {
      cookieGate.hidden = true;
      submitBtn.disabled = !hiddenTime.value;
      return;
    }
    if (!consentAccepted()) {
      cookieGate.hidden = false;
      submitBtn.disabled = true;
    } else {
      cookieGate.hidden = true;
      submitBtn.disabled = !hiddenTime.value;
      loadRecaptcha().catch(() => {});
    }
  }

  function setSlotHint(text, isError = false) {
    slotPicker.innerHTML = '';
    const p = document.createElement('p');
    p.className = isError ? 'slot-picker__error' : 'slot-picker__hint';
    p.textContent = text;
    slotPicker.appendChild(p);
  }

  function renderSlots(slots) {
    slotPicker.innerHTML = '';
    if (!slots.length) {
      setSlotHint('V tento deň nie sú voľné termíny pre tento balíček.', true);
      return;
    }
    slots.forEach(time => {
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'slot-chip';
      btn.textContent = time;
      btn.setAttribute('role', 'radio');
      btn.setAttribute('aria-checked', 'false');
      btn.addEventListener('click', () => {
        slotPicker.querySelectorAll('.slot-chip').forEach(c => c.setAttribute('aria-checked', 'false'));
        btn.setAttribute('aria-checked', 'true');
        hiddenTime.value = time;
        updateCookieGate(); // re-evaluate submit enabled state
      });
      slotPicker.appendChild(btn);
    });
  }

  async function fetchAvailability() {
    const date = dateInput.value;
    const pkg  = pkgSelect.value;
    if (!date || !pkg) {
      setSlotHint('Vyberte dátum a balíček.');
      hiddenTime.value = '';
      updateCookieGate();
      return;
    }
    if (availabilityAbort) availabilityAbort.abort();
    availabilityAbort = new AbortController();
    setSlotHint('Načítavam dostupné termíny…');
    try {
      const res = await fetch(`/api/availability?date=${encodeURIComponent(date)}&package=${encodeURIComponent(pkg)}`, { signal: availabilityAbort.signal });
      const json = await res.json();
      if (!res.ok) throw new Error(json.error || 'load_failed');
      if (json.reason && json.slots.length === 0) {
        const msg = {
          closed_day:        'V tento deň je herňa zatvorená.',
          before_lead:       'Tento dátum je príliš skoro — minimálny predstih nie je dodržaný.',
          after_horizon:     'Tento dátum je príliš ďaleko v budúcnosti.',
          blocked_full_day:  'V tento deň je herňa rezervovaná alebo zablokovaná.',
          full:              'V tento deň nie sú voľné termíny pre tento balíček.',
          unknown_package:   'Neznámy balíček.',
          bad_date:          'Neplatný dátum.'
        }[json.reason] || 'V tento deň nie sú voľné termíny.';
        setSlotHint(msg, true);
        hiddenTime.value = '';
      } else {
        renderSlots(json.slots);
        hiddenTime.value = '';
      }
    } catch (err) {
      if (err.name === 'AbortError') return;
      setSlotHint('Načítanie zlyhalo. Skúste znova alebo zavolajte +421 915 319 934.', true);
    } finally {
      updateCookieGate();
    }
  }

  dateInput.addEventListener('change', fetchAvailability);
  pkgSelect.addEventListener('change', fetchAvailability);

  function resetUI() {
    if (errorBox)   errorBox.hidden = true;
    if (successBox) successBox.hidden = true;
    if (fields)     fields.hidden = false;
    if (actions)    actions.hidden = false;
    submitBtn.textContent = 'Odoslať rezerváciu';
  }

  function openModal(pkg) {
    resetUI();
    form.reset();
    hiddenTime.value = '';
    if (pkg && pkgSelect) pkgSelect.value = pkg;
    setSlotHint('Vyberte dátum.');
    updateCookieGate();
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
    // If a date is already prefilled (rare), fetch availability
    if (dateInput.value) fetchAvailability();
  }
  function closeModal() {
    if (typeof modal.close === 'function') modal.close();
    else modal.removeAttribute('open');
  }

  document.querySelectorAll('[data-open-reservation]').forEach(btn => {
    btn.addEventListener('click', () => openModal(btn.dataset.package || ''));
  });
  modal.querySelectorAll('[data-close-modal]').forEach(btn => {
    btn.addEventListener('click', closeModal);
  });
  modal.addEventListener('click', e => {
    if (e.target === modal) closeModal();
  });

  document.addEventListener('kuko:consent', updateCookieGate);

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    errorBox.hidden = true;
    submitBtn.disabled = true;
    submitBtn.textContent = 'Odosielam…';

    try {
      if (!hiddenTime.value) throw new Error('Vyberte časový slot.');

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
          // re-fetch available slots so the user can pick a different one
          fetchAvailability();
          throw new Error('Tento termín bol medzitým zarezervovaný. Vyberte iný voľný čas.');
        }
        if (json.error === 'validation' && json.fields) {
          throw new Error('Skontrolujte: ' + Object.values(json.fields).join(' '));
        }
        if (json.error === 'rate_limited') {
          throw new Error('Príliš veľa pokusov. Skúste neskôr.');
        }
        if (json.error === 'csrf_invalid') {
          throw new Error('Bezpečnostný token vypršal. Obnovte prosím stránku.');
        }
        if (json.error === 'spam_blocked') {
          throw new Error('Detekcia spamu zablokovala odoslanie. Skúste znova alebo zavolajte.');
        }
        throw new Error('Odoslanie zlyhalo. Skúste prosím znova alebo nás kontaktujte.');
      }

      fields.hidden = true;
      actions.hidden = true;
      successBox.hidden = false;
    } catch (err) {
      errorBox.textContent = err.message;
      errorBox.hidden = false;
    } finally {
      submitBtn.disabled = !hiddenTime.value;
      submitBtn.textContent = 'Odoslať rezerváciu';
    }
  });
}
