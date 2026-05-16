// Lightbox gallery — vanilla, keyboard accessible
const items = Array.from(document.querySelectorAll('[data-lightbox]'));
if (items.length) {
  const svgChevron = (d) =>
    `<svg viewBox="0 0 24 24" width="26" height="26" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="display:block">${d}</svg>`;
  const ICON_PREV  = svgChevron('<polyline points="15 4 7 12 15 20"></polyline>');
  const ICON_NEXT  = svgChevron('<polyline points="9 4 17 12 9 20"></polyline>');
  const ICON_CLOSE = svgChevron('<line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line>');

  const lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.hidden = true;
  lb.innerHTML = `
    <button type="button" class="lightbox__btn lightbox__btn--close" aria-label="Zavrieť">${ICON_CLOSE}</button>
    <button type="button" class="lightbox__btn lightbox__btn--prev" aria-label="Predchádzajúca">${ICON_PREV}</button>
    <div class="lightbox__stage">
      <img class="lightbox__img" alt="">
      <div class="lightbox__thumbs"></div>
    </div>
    <button type="button" class="lightbox__btn lightbox__btn--next" aria-label="Ďalšia">${ICON_NEXT}</button>
  `;
  document.body.appendChild(lb);

  const img = lb.querySelector('.lightbox__img');
  const btnClose = lb.querySelector('.lightbox__btn--close');
  const btnPrev  = lb.querySelector('.lightbox__btn--prev');
  const btnNext  = lb.querySelector('.lightbox__btn--next');
  const thumbsEl = lb.querySelector('.lightbox__thumbs');

  let idx = 0;
  let lastFocus = null;

  const srcFor = (el) => el.dataset.lightboxWebp || el.dataset.lightbox;
  const altFor = (el) => el.querySelector('img')?.alt ?? '';

  // Build the thumbnail strip (one button per item). Hidden when < 2 items.
  const thumbs = [];
  if (items.length < 2) {
    thumbsEl.hidden = true;
  } else {
    items.forEach((el, i) => {
      const t = document.createElement('button');
      t.type = 'button';
      t.className = 'lightbox__thumb';
      t.setAttribute('aria-label', altFor(el) || `Fotka ${i + 1}`);
      const ti = document.createElement('img');
      ti.src = srcFor(el);
      ti.alt = '';
      ti.loading = 'lazy';
      t.appendChild(ti);
      t.addEventListener('click', () => show(i));
      thumbsEl.appendChild(t);
      thumbs.push(t);
    });
  }

  const syncThumbs = () => {
    if (!thumbs.length) return;
    thumbs.forEach((t, i) => {
      const active = i === idx;
      t.classList.toggle('is-active', active);
      if (active) {
        t.setAttribute('aria-current', 'true');
      } else {
        t.removeAttribute('aria-current');
      }
    });
    const active = thumbs[idx];
    if (active && !lb.hidden && typeof active.scrollIntoView === 'function') {
      active.scrollIntoView({ block: 'nearest', inline: 'center' });
    }
  };

  const show = (i) => {
    idx = (i + items.length) % items.length;
    img.src = srcFor(items[idx]);
    img.alt = altFor(items[idx]);
    const n = items.length;
    if (n > 1) {
      new Image().src = srcFor(items[(idx - 1 + n) % n]);
      new Image().src = srcFor(items[(idx + 1) % n]);
    }
    syncThumbs();
  };

  const open = (i) => {
    lastFocus = document.activeElement;
    lb.hidden = false;
    show(i);
    btnClose.focus();
    document.body.style.overflow = 'hidden';
  };
  const close = () => {
    lb.hidden = true;
    document.body.style.overflow = '';
    if (lastFocus instanceof HTMLElement) lastFocus.focus();
  };

  items.forEach((el, i) => el.addEventListener('click', () => open(i)));
  btnClose.addEventListener('click', close);
  btnPrev.addEventListener('click', () => show(idx - 1));
  btnNext.addEventListener('click', () => show(idx + 1));
  lb.addEventListener('click', e => { if (e.target === lb) close(); });
  document.addEventListener('keydown', e => {
    if (lb.hidden) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(idx - 1);
    if (e.key === 'ArrowRight') show(idx + 1);
  });
}
