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
<p class="admin-lead gal-hint">Presúvaj fotky myšou pre zmenu poradia.</p>
<div class="gal-grid" id="galGrid">
  <?php foreach ($photos as $ph): ?>
    <?php
      $pid     = (int) $ph['id'];
      $fname   = (string) $ph['filename'];
      $webp    = $ph['webp'] ?? null;
      $alt     = (string) $ph['alt_text'];
      $sort    = (int) $ph['sort_order'];
      $visible = !empty($ph['is_visible']);
    ?>
    <div class="gal-card<?= $visible ? '' : ' gal-card--hidden' ?>" draggable="true" data-id="<?= $pid ?>">
      <div class="gal-card__handle" title="Presunúť">⠿ #<?= $sort ?></div>
      <picture>
        <?php if ($webp): ?><source srcset="/assets/img/gallery/<?= e((string) $webp) ?>" type="image/webp"><?php endif; ?>
        <img src="/assets/img/gallery/<?= e($fname) ?>" width="200" loading="lazy" alt="<?= e($alt) ?>">
      </picture>
      <form method="post" action="/admin/gallery/<?= $pid ?>/alt" class="gal-alt">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="text" name="alt" value="<?= e($alt) ?>" maxlength="255" placeholder="ALT text">
        <button type="submit" class="admin-btn-link">Uložiť ALT</button>
      </form>
      <div class="gal-card__actions">
        <form method="post" action="/admin/gallery/<?= $pid ?>/visibility" style="display:inline">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <?php if ($visible): ?>
            <input type="hidden" name="visible" value="0">
            <button type="submit" class="admin-btn-link">Skryť</button>
          <?php else: ?>
            <input type="hidden" name="visible" value="1">
            <button type="submit" class="admin-btn-link">Zobraziť</button>
          <?php endif; ?>
        </form>
        <form method="post" action="/admin/gallery/<?= $pid ?>/delete" style="display:inline" onsubmit="return confirm('Naozaj zmazať fotku?');">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <button type="submit" class="admin-btn-link">Zmazať</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<style>
.gal-hint { font-style: italic; }
.gal-grid { display: flex; flex-wrap: wrap; gap: 1rem; margin-top: 1rem; }
.gal-card { width: 220px; border: 1px solid #ddd; border-radius: 8px; padding: .6rem; background: #fff; cursor: grab; }
.gal-card.gal-card--dragging { opacity: .4; }
.gal-card--hidden { opacity: .45; background: #f3f3f3; }
.gal-card__handle { font-size: .85rem; color: #888; user-select: none; margin-bottom: .3rem; }
.gal-card img { display: block; width: 200px; height: auto; border-radius: 4px; }
.gal-alt { display: flex; gap: .3rem; align-items: center; margin: .5rem 0; }
.gal-alt input { flex: 1; min-width: 0; }
.gal-card__actions { display: flex; gap: .8rem; }
</style>
<script>
(function () {
  var grid = document.getElementById('galGrid');
  if (!grid) return;
  var token = <?= json_encode($csrf) ?>;
  var dragged = null;

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
    var order = Array.prototype.map.call(grid.querySelectorAll('.gal-card'), function (c) {
      return parseInt(c.getAttribute('data-id'), 10);
    });
    fetch('/admin/gallery/reorder', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': token },
      body: JSON.stringify({ order: order })
    }).then(function (r) { return r.json(); }).then(function (res) {
      if (res && res.ok) {
        grid.querySelectorAll('.gal-card__handle').forEach(function (h, i) {
          h.textContent = '⠿ #' + (i + 1);
        });
      }
    }).catch(function () {});
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
