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

// ===== Sticky nav: collapse the logo row once the topbar scrolls out =====
// Driven by an IntersectionObserver on the topbar (a stable element ABOVE the
// sticky nav, so its visibility does NOT change when the nav's own height
// changes) — this avoids the scroll-position feedback loop that makes a
// scrollY-threshold approach oscillate/jank.
const navStick = $('.nav');
const topbarStick = $('.topbar');
if (navStick && topbarStick && 'IntersectionObserver' in window) {
  const io = new IntersectionObserver(
    ([entry]) => navStick.classList.toggle('is-stuck', !entry.isIntersecting),
    { threshold: 0 }
  );
  io.observe(topbarStick);
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

// Cookie consent is handled by the standalone /assets/js/cookie-consent.js
// module (loaded by cookie-banner.php) — single source of truth across the
// full site and the reservation page's minimal layout.

// ===== Lazy-load feature modules =====
// Versioned URLs injected by the layout (Asset::url adds ?v=<mtime>) so a
// changed gallery.js/map.js is not served stale from the CDN/browser cache —
// a bare './gallery.js' specifier carries no cache-busting query.
const A = window.__kukoAssets || {};
import(A.gallery || './gallery.js').catch(err => console.warn('gallery.js failed', err));
import(A.map || './map.js').catch(err => console.warn('map.js failed', err));
