// Reservation modal with cookie gate + reCAPTCHA v3 submit
const modal = document.getElementById('reservation-modal');
if (modal) {
  const form        = document.getElementById('reservation-form');
  const errorBox    = document.getElementById('modal-error');
  const successBox  = document.getElementById('modal-success');
  const fields      = document.getElementById('modal-fields');
  const actions     = document.getElementById('modal-actions');
  const submitBtn   = document.getElementById('modal-submit');
  const cookieGate  = document.getElementById('modal-cookie-gate');

  const siteKey = document.querySelector('meta[name="recaptcha-site-key"]')?.content ?? '';
  let recaptchaPromise = null;

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
      // No reCAPTCHA configured (dev) — let user submit without consent gate.
      cookieGate.hidden = true;
      submitBtn.disabled = false;
      return;
    }
    if (!consentAccepted()) {
      cookieGate.hidden = false;
      submitBtn.disabled = true;
    } else {
      cookieGate.hidden = true;
      submitBtn.disabled = false;
      loadRecaptcha().catch(() => {});
    }
  }

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
    if (pkg && form.querySelector('[name="package"]')) {
      form.querySelector('[name="package"]').value = pkg;
    }
    updateCookieGate();
    if (typeof modal.showModal === 'function') modal.showModal();
    else modal.setAttribute('open', '');
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
      submitBtn.disabled = false;
      submitBtn.textContent = 'Odoslať rezerváciu';
    }
  });
}
