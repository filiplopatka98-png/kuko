// KUKO detský svet — main client script
const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

// ===== Hamburger toggle =====
const navToggle = $('.nav__toggle');
const navMenu = $('#primary-nav');
if (navToggle && navMenu) {
  navToggle.addEventListener('click', () => {
    const expanded = navToggle.getAttribute('aria-expanded') === 'true';
    navToggle.setAttribute('aria-expanded', String(!expanded));
    navMenu.classList.toggle('is-open');
  });
  navMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    navToggle.setAttribute('aria-expanded', 'false');
    navMenu.classList.remove('is-open');
  }));
}

// ===== Sticky nav: collapse the logo row once scrolled =====
const navEl = $('.nav');
const topbarEl = $('.topbar');
if (navEl) {
  const syncStuck = () => {
    const threshold = (topbarEl?.offsetHeight ?? 0) + 4;
    navEl.classList.toggle('is-stuck', window.scrollY > threshold);
  };
  syncStuck();
  window.addEventListener('scroll', syncStuck, { passive: true });
}

// ===== Scroll reveal =====
const revealEls = $$('[data-reveal]');
if (revealEls.length && 'IntersectionObserver' in window) {
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('is-visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1 });
  revealEls.forEach(el => io.observe(el));
} else {
  revealEls.forEach(el => el.classList.add('is-visible'));
}

// ===== Smooth scroll with sticky-nav offset =====
document.addEventListener('click', e => {
  const a = e.target.closest('a[href^="#"], a[href*="/#"]');
  if (!a) return;
  const href = a.getAttribute('href');
  const hashIdx = href.indexOf('#');
  if (hashIdx === -1) return;
  const id = href.slice(hashIdx + 1);
  if (!id) return;
  const target = document.getElementById(id);
  if (!target) return;
  e.preventDefault();
  const offset = ($('.nav')?.offsetHeight ?? 0) + 8;
  const top = target.getBoundingClientRect().top + window.scrollY - offset;
  window.scrollTo({ top, behavior: 'smooth' });
  history.replaceState(null, '', '#' + id);
});

// ===== Cookie consent =====
const CONSENT_KEY = 'kuko_cookie_consent';
const banner = document.getElementById('cookie-banner');
const reopenBtn = document.getElementById('cookie-reopen');

export function getConsent() { return localStorage.getItem(CONSENT_KEY); }
function setConsent(value) {
  localStorage.setItem(CONSENT_KEY, value);
  document.dispatchEvent(new CustomEvent('kuko:consent', { detail: { value } }));
}
function showBanner() { if (banner) banner.hidden = false; }
function hideBanner() { if (banner) banner.hidden = true; }

if (banner) {
  if (!getConsent()) showBanner();
  banner.querySelectorAll('[data-cookie-action]').forEach(btn => {
    btn.addEventListener('click', () => {
      setConsent(btn.dataset.cookieAction === 'accept' ? 'accepted' : 'denied');
      hideBanner();
    });
  });
}
reopenBtn?.addEventListener('click', () => showBanner());

// Also bind accept buttons in the modal cookie-gate
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-cookie-action]');
  if (!btn) return;
  if (!banner || btn.closest('#cookie-banner')) return;
  setConsent(btn.dataset.cookieAction === 'accept' ? 'accepted' : 'denied');
  hideBanner();
});

// ===== Lazy-load feature modules =====
// Versioned URLs injected by the layout (Asset::url adds ?v=<mtime>) so a
// changed gallery.js/map.js is not served stale from the CDN/browser cache —
// a bare './gallery.js' specifier carries no cache-busting query.
const A = window.__kukoAssets || {};
import(A.gallery || './gallery.js').catch(err => console.warn('gallery.js failed', err));
import(A.map || './map.js').catch(err => console.warn('map.js failed', err));
