// Lightbox gallery — vanilla, keyboard accessible
const items = Array.from(document.querySelectorAll('[data-lightbox]'));
if (items.length) {
  const lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.hidden = true;
  lb.innerHTML = `
    <button type="button" class="lightbox__btn lightbox__btn--close" aria-label="Zavrieť">&times;</button>
    <button type="button" class="lightbox__btn lightbox__btn--prev" aria-label="Predchádzajúca">&lsaquo;</button>
    <img class="lightbox__img" alt="">
    <button type="button" class="lightbox__btn lightbox__btn--next" aria-label="Ďalšia">&rsaquo;</button>
  `;
  document.body.appendChild(lb);

  const img = lb.querySelector('.lightbox__img');
  const btnClose = lb.querySelector('.lightbox__btn--close');
  const btnPrev  = lb.querySelector('.lightbox__btn--prev');
  const btnNext  = lb.querySelector('.lightbox__btn--next');

  let idx = 0;
  let lastFocus = null;

  const show = (i) => {
    idx = (i + items.length) % items.length;
    img.src = items[idx].dataset.lightbox;
    img.alt = items[idx].querySelector('img')?.alt ?? '';
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
