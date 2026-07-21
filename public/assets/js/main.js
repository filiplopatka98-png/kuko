// KUKO detský svet — main client script
const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

// ===== Hamburger toggle =====
const navToggle = $('.nav__toggle');
const navMenu = $('#primary-nav');
if (navToggle && navMenu) {
  const setMenu = (open) => {
    navToggle.setAttribute('aria-expanded', String(open));
    navToggle.setAttribute('aria-label', open ? 'Zavrieť menu' : 'Otvoriť menu');
    navMenu.classList.toggle('is-open', open);
  };
  navToggle.addEventListener('click', () => {
    setMenu(navToggle.getAttribute('aria-expanded') !== 'true');
  });
  navMenu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => setMenu(false)));
  // Esc closes the open menu and returns focus to the toggle.
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && navToggle.getAttribute('aria-expanded') === 'true') {
      setMenu(false);
      navToggle.focus();
    }
  });
}

// Sticky header is now pure CSS (only the .nav__band pins on desktop; the
// whole .nav pins on mobile). No JS class toggling → no layout-shift jank.

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
  const offset = ($('.nav__band')?.offsetHeight ?? 0) + 8;
  const top = target.getBoundingClientRect().top + window.scrollY - offset;
  window.scrollTo({ top, behavior: 'smooth' });
  history.replaceState(null, '', '#' + id);
});

// Cookie consent is handled by the standalone /assets/js/cookie-consent.js
// module (loaded by cookie-banner.php) — single source of truth across the
// full site and the reservation page's minimal layout.

// ===== Lazy-load feature modules =====
// Versioned URLs injected by the layout (Asset::url adds ?v=<mtime>) so a
// changed gallery.js/map.js is not served stale from the CDN/browser cache —
// a bare './gallery.js' specifier carries no cache-busting query.
// Only fetch a module when its target element is present on the page — both
// modules early-return otherwise, so this just skips two dead requests on
// pages without a gallery (no [data-lightbox]) or a map (no #map).
const A = window.__kukoAssets || {};
if (document.querySelector('[data-lightbox]')) {
  import(A.gallery || './gallery.js').catch(err => console.warn('gallery.js failed', err));
}
if (document.getElementById('map')) {
  import(A.map || './map.js').catch(err => console.warn('map.js failed', err));
}
