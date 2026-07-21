// Shared admin behaviours, extracted from inline on* handlers so the CSP can
// drop 'unsafe-inline' from script-src. Loaded as an external ('self') script,
// so it needs no nonce.
(function () {
  'use strict';

  // form[data-confirm="message"] → native confirm() gate before submit.
  // The message may contain {fieldName} placeholders, replaced with that named
  // field's current value (e.g. echo "old → new" back to the admin).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!(form instanceof HTMLFormElement)) return;
    var msg = form.getAttribute('data-confirm');
    if (!msg) return;
    msg = msg.replace(/\{(\w+)\}/g, function (m, name) {
      var field = form.elements[name];
      return field ? String(field.value) : m;
    });
    if (!window.confirm(msg)) {
      e.preventDefault();
    }
  });

  // [data-submit-on-change] → submit the owning form when the control changes
  // (used by the gallery "Na homepage" checkbox).
  document.addEventListener('change', function (e) {
    var el = e.target;
    if (el && el.hasAttribute && el.hasAttribute('data-submit-on-change') && el.form) {
      el.form.submit();
    }
  });

  // Reservation detail: show the "Dôvod zrušenia" field only when the status
  // select is on "cancelled".
  function toggleReason(sel) {
    var field = sel.form && sel.form.querySelector('[data-reason-field]');
    if (field) field.hidden = sel.value !== 'cancelled';
  }
  document.querySelectorAll('select[data-status-select]').forEach(function (sel) {
    toggleReason(sel);
    sel.addEventListener('change', function () { toggleReason(sel); });
  });

  // Flash messages: dismiss on × click, and auto-dismiss after a few seconds.
  document.addEventListener('click', function (e) {
    var btn = e.target.closest ? e.target.closest('[data-flash-close]') : null;
    if (!btn) return;
    var flash = btn.closest('[data-flash]');
    if (flash) flash.remove();
  });
  document.querySelectorAll('[data-flash]').forEach(function (flash) {
    setTimeout(function () {
      flash.style.transition = 'opacity .4s ease';
      flash.style.opacity = '0';
      setTimeout(function () { flash.remove(); }, 400);
    }, 6000);
  });
})();
