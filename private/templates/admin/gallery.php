<?php
/** @var array<int,array<string,mixed>> $photos */
/** @var string $user */
/** @var array $flashes */
$title = 'Galéria — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Galéria</h2>
<p class="admin-lead">Fotky galérie na webe. Povolené: JPG, PNG, WebP (max 5 MB). Obrázky sa automaticky zmenšia a uloží sa aj WebP verzia.</p>

<details class="admin-collapsible" open>
  <summary>Nahrať fotku</summary>
  <form method="post" action="/admin/gallery/upload" enctype="multipart/form-data" class="admin-form admin-form--inline">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="admin-form__row">
      <label class="admin-field">
        <span>Súbor</span>
        <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required>
      </label>
      <label class="admin-field">
        <span>Popis fotky (ALT text)</span>
        <input type="text" name="alt" placeholder="Popis fotky (ALT text)" required maxlength="255">
      </label>
    </div>
    <div class="admin-form__actions">
      <button type="submit">Nahrať</button>
    </div>
  </form>
</details>

<?php if (!$photos): ?>
  <p class="admin-empty">Žiadne fotky.</p>
<?php else: ?>
<?php $homepageCount = 0; foreach ($photos as $__ph) { if (!empty($__ph['on_homepage'])) $homepageCount++; } ?>
<p class="admin-lead gal-hint">Presúvaj fotky myšou pre zmenu poradia.</p>
<p class="admin-lead gal-hp-counter">Na homepage: <strong id="galHpCount"><?= $homepageCount ?></strong>/6
  <span class="gal-hp-note">(ak je menej ako 6, zvyšok homepage doplní náhodné viditeľné fotky)</span></p>
<div class="gal-grid" id="galGrid">
  <?php foreach ($photos as $ph): ?>
    <?php
      $pid     = (int) $ph['id'];
      $fname   = (string) $ph['filename'];
      $webp    = $ph['webp'] ?? null;
      $alt     = (string) $ph['alt_text'];
      $sort    = (int) $ph['sort_order'];
      $visible = !empty($ph['is_visible']);
      $onHome  = !empty($ph['on_homepage']);
    ?>
    <div class="gal-card<?= $visible ? '' : ' gal-card--hidden' ?>" draggable="true" data-id="<?= $pid ?>">
      <div class="gal-card__handle" title="Presunúť (alebo ťahaním)">
        <button type="button" class="gal-move" data-move="up" aria-label="Posunúť vyššie">↑</button>
        <span class="gal-card__pos">⠿ #<?= $sort ?></span>
        <button type="button" class="gal-move" data-move="down" aria-label="Posunúť nižšie">↓</button>
      </div>
      <picture>
        <?php if ($webp): ?><source srcset="/assets/img/gallery/<?= e((string) $webp) ?>" type="image/webp"><?php endif; ?>
        <img src="/assets/img/gallery/<?= e($fname) ?>" width="200" loading="lazy" alt="<?= e($alt) ?>">
      </picture>
      <div class="gal-card__body">
        <form method="post" action="/admin/gallery/<?= $pid ?>/alt" class="gal-alt">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <input type="text" name="alt" value="<?= e($alt) ?>" maxlength="255" placeholder="ALT text (popis fotky)" aria-label="ALT text">
          <button type="submit" class="gal-btn gal-btn--save">Uložiť</button>
        </form>
        <div class="gal-card__foot">
          <form method="post" action="/admin/gallery/<?= $pid ?>/homepage" class="gal-hp">
            <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="on" value="<?= $onHome ? '0' : '1' ?>">
            <label class="gal-hp__label">
              <input type="checkbox" class="gal-hp__box" <?= $onHome ? 'checked' : '' ?> data-submit-on-change>
              <span>Na homepage</span>
            </label>
          </form>
          <div class="gal-card__actions">
            <form method="post" action="/admin/gallery/<?= $pid ?>/visibility">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <?php if ($visible): ?>
                <input type="hidden" name="visible" value="0">
                <button type="submit" class="gal-btn">Skryť</button>
              <?php else: ?>
                <input type="hidden" name="visible" value="1">
                <button type="submit" class="gal-btn">Zobraziť</button>
              <?php endif; ?>
            </form>
            <form method="post" action="/admin/gallery/<?= $pid ?>/delete" data-confirm="Naozaj zmazať fotku?">
              <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
              <button type="submit" class="gal-btn gal-btn--danger" aria-label="Zmazať fotku">Zmazať</button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<p id="galStatus" class="gal-status" role="status" aria-live="polite" hidden></p>
<?php endif; ?>

<style>
.gal-hint { font-style: italic; }
.gal-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 1rem; margin-top: 1rem; }
.gal-card { border: 1px solid #e3d9e6; border-radius: 10px; padding: .6rem; background: #fff; cursor: grab; display: flex; flex-direction: column; }
.gal-card.gal-card--dragging { opacity: .4; }
.gal-card--hidden { opacity: .5; background: #f6f4f7; }
.gal-card__handle { font-size: .8rem; color: #777; user-select: none; margin-bottom: .4rem; display: flex; align-items: center; justify-content: space-between; gap: .4rem; }
.gal-move { border: 1px solid #d9c7df; background: #fff; border-radius: 6px; width: 26px; height: 26px; cursor: pointer; font-size: .9rem; line-height: 1; color: var(--c-text); }
.gal-move:hover { background: #faf5fc; border-color: var(--c-accent); }
.gal-status { margin-top: .75rem; padding: .5rem .8rem; border-radius: 6px; background: #eef8ee; color: #1e6b2e; font-size: .85rem; }
.gal-status--error { background: #fdecea; color: #c0392b; }
.gal-card img { display: block; width: 100%; height: auto; border-radius: 6px; }
.gal-card__body { display: flex; flex-direction: column; gap: .5rem; margin-top: .5rem; }
.gal-alt { display: flex; gap: .35rem; align-items: center; }
.gal-alt input { flex: 1; min-width: 0; }
.gal-card__foot { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding-top: .5rem; border-top: 1px solid #efe7f1; flex-wrap: wrap; }
.gal-card__actions { display: flex; gap: .4rem; }
.gal-card__actions form { display: inline; margin: 0; }
.gal-hp { margin: 0; }
.gal-hp__label { display: flex; align-items: center; gap: .35rem; font-size: .85rem; cursor: pointer; white-space: nowrap; }
.gal-hp__box:disabled + span { color: #aaa; }
.gal-hp-counter { margin-top: .4rem; }
.gal-hp-note { font-size: .8rem; color: #888; font-style: italic; }
/* compact, consistent gallery buttons (no more scattered red links) */
.gal-btn { padding: .3rem .7rem; font-size: .8rem; font-weight: 600; border: 1px solid #d9c7df;
  background: #fff; color: var(--c-text); border-radius: 6px; cursor: pointer; line-height: 1.4; }
.gal-btn:hover { background: #faf5fc; border-color: var(--c-accent); }
.gal-btn--save { border-color: var(--c-accent); color: var(--c-accent); }
.gal-btn--danger { border-color: #e3b4ad; color: #c0392b; }
.gal-btn--danger:hover { background: #fdecea; border-color: #c0392b; }
</style>
<script nonce="<?= e(\Kuko\Csp::nonce()) ?>">
(function () {
  var grid = document.getElementById('galGrid');
  if (!grid) return;
  var token = <?= json_encode($csrf) ?>;
  var statusEl = document.getElementById('galStatus');
  var dragged = null;

  function say(msg, isError) {
    if (!statusEl) return;
    statusEl.textContent = msg;
    statusEl.hidden = false;
    statusEl.classList.toggle('gal-status--error', !!isError);
  }
  function renumber() {
    grid.querySelectorAll('.gal-card__pos').forEach(function (h, i) {
      h.textContent = '⠿ #' + (i + 1);
    });
  }
  function persistOrder() {
    var order = Array.prototype.map.call(grid.querySelectorAll('.gal-card'), function (c) {
      return parseInt(c.getAttribute('data-id'), 10);
    });
    say('Ukladám poradie…', false);
    fetch('/admin/gallery/reorder', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({ order: order })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res && res.ok) { renumber(); say('Poradie uložené.', false); }
      else { say('Poradie sa nepodarilo uložiť. Skúste znova.', true); }
    }).catch(function () { say('Poradie sa nepodarilo uložiť (chyba siete).', true); });
  }

  grid.addEventListener('dragstart', function (e) {
    var card = e.target.closest('.gal-card');
    if (!card) return;
    dragged = card;
    card.classList.add('gal-card--dragging');
    e.dataTransfer.effectAllowed = 'move';
  });
  grid.addEventListener('dragend', function () {
    if (dragged) dragged.classList.remove('gal-card--dragging');
    dragged = null;
  });
  grid.addEventListener('dragover', function (e) {
    e.preventDefault();
    var over = e.target.closest('.gal-card');
    if (!over || over === dragged || !dragged) return;
    var rect = over.getBoundingClientRect();
    var after = (e.clientY - rect.top) > rect.height / 2;
    grid.insertBefore(dragged, after ? over.nextSibling : over);
  });
  grid.addEventListener('drop', function (e) {
    e.preventDefault();
    persistOrder();
  });

  // Keyboard/touch reordering via the ↑/↓ buttons (drag-and-drop is unavailable
  // on touch and to keyboard users).
  grid.addEventListener('click', function (e) {
    var btn = e.target.closest('.gal-move');
    if (!btn) return;
    var card = btn.closest('.gal-card');
    if (!card) return;
    if (btn.getAttribute('data-move') === 'up') {
      if (card.previousElementSibling) grid.insertBefore(card, card.previousElementSibling);
    } else {
      if (card.nextElementSibling) grid.insertBefore(card.nextElementSibling, card);
    }
    btn.focus();
    persistOrder();
  });
})();
(function () {
  // Client nicety only — server-side enforcement (setHomepage) is the source of truth.
  var boxes = Array.prototype.slice.call(document.querySelectorAll('.gal-hp__box'));
  var counter = document.getElementById('galHpCount');
  if (!boxes.length) return;
  function sync() {
    var n = boxes.filter(function (b) { return b.checked; }).length;
    if (counter) counter.textContent = n;
    boxes.forEach(function (b) { b.disabled = (n >= 6 && !b.checked); });
  }
  boxes.forEach(function (b) { b.addEventListener('change', sync); });
  sync();
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
