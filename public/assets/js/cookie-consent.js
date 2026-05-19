// Cookie consent — single source of truth for the whole site.
// Loaded by cookie-banner.php so it runs on every page that shows the banner
// (full layout + the reservation page's minimal layout).
//
// Storage: localStorage 'kuko_cookie_consent' = JSON
//   { v:2, recaptcha:bool, analytics:bool, marketing:bool }
// "Necessary" cookies are always on and are not stored.
// Legacy values: 'accepted' -> all true, 'denied' -> all false.
// A decision is "made" only once a JSON/legacy value exists; until then the
// banner is shown. Detailed choices live in a separate modal (#cookie-modal),
// not crammed into the banner. Every change dispatches document
// `kuko:consent` (detail.value = the consent object) so consumers (e.g. the
// reservation form's reCAPTCHA gate) can react.
(() => {
  const KEY = 'kuko_cookie_consent';
  const CATS = ['recaptcha', 'analytics', 'marketing'];

  const allTrue = () => ({ recaptcha: true, analytics: true, marketing: true });
  const allFalse = () => ({ recaptcha: false, analytics: false, marketing: false });

  function readConsent() {
    let raw = null;
    try { raw = localStorage.getItem(KEY); } catch (e) { return null; }
    if (raw == null || raw === '') return null;
    if (raw === 'accepted') return allTrue();
    if (raw === 'denied') return allFalse();
    try {
      const o = JSON.parse(raw);
      if (o && typeof o === 'object') {
        return { recaptcha: !!o.recaptcha, analytics: !!o.analytics, marketing: !!o.marketing };
      }
    } catch (e) { /* corrupt — treat as no decision */ }
    return null;
  }

  const banner = document.getElementById('cookie-banner');
  const modal = document.getElementById('cookie-modal');
  const reopen = document.getElementById('cookie-reopen');
  let lastFocus = null;

  function showBanner() { if (banner) banner.hidden = false; }
  function hideBanner() { if (banner) banner.hidden = true; }

  function openModal() {
    if (!modal) return;
    prefillToggles(readConsent());
    lastFocus = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    (modal.querySelector('[data-cookie-cat], [data-cookie-action], button') || modal).focus();
  }
  function closeModal() {
    if (!modal || modal.hidden) return;
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus instanceof HTMLElement) lastFocus.focus();
  }

  function prefillToggles(consent) {
    if (!modal) return;
    const c = consent || allFalse();
    CATS.forEach(cat => {
      const el = modal.querySelector(`[data-cookie-cat="${cat}"]`);
      if (el) el.checked = !!c[cat];
    });
  }

  function readToggles() {
    const out = allFalse();
    if (modal) {
      CATS.forEach(cat => {
        const el = modal.querySelector(`[data-cookie-cat="${cat}"]`);
        if (el) out[cat] = !!el.checked;
      });
    }
    return out;
  }

  function save(consent) {
    try {
      localStorage.setItem(KEY, JSON.stringify({ v: 2, ...consent }));
    } catch (e) { /* storage disabled — consent just won't persist */ }
    document.dispatchEvent(new CustomEvent('kuko:consent', { detail: { value: consent } }));
    closeModal();
    hideBanner();
  }

  // Delegated handler: works for the banner buttons, the modal buttons AND
  // the reservation page's inline cookie-gate ("Súhlasím s cookies" => accept).
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('[data-cookie-action]');
    if (btn) {
      const action = btn.dataset.cookieAction;
      if (action === 'accept') save(allTrue());
      else if (action === 'deny') save(allFalse());
      else if (action === 'save') save(readToggles());
      else if (action === 'settings') openModal();
      else if (action === 'close') closeModal();
      return;
    }
    // Click on the modal backdrop (outside the dialog) closes it.
    if (modal && !modal.hidden && e.target === modal) closeModal();
  });

  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && modal && !modal.hidden) closeModal();
  });

  // Footer "Cookie nastavenia": open the settings modal directly.
  reopen?.addEventListener('click', openModal);

  // First visit (no decision yet) → show the banner.
  if (banner && readConsent() === null) showBanner();
})();
