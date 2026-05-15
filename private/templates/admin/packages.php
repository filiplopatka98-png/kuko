<?php
/** @var array<int,array<string,mixed>> $rows */
/** @var string $user */
$title = 'Balíčky — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Balíčky osláv</h2>
<p class="admin-lead">Trvanie ovplyvňuje generovanie voľných slotov. Ak balíček blokuje celý deň (napr. „Uzavretá spoločnosť"), v daný deň nepôjde rezervovať žiadnu inú oslavu.</p>

<?php foreach ($rows as $r): ?>
<form method="post" action="/admin/packages/<?= e($r['code']) ?>" class="admin-form admin-package-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <h3><?= e($r['code']) ?></h3>
  <div class="admin-form__row">
    <label class="admin-field">
      <span>Názov</span>
      <input type="text" name="name" maxlength="120" required value="<?= e($r['name']) ?>">
    </label>
    <label class="admin-field">
      <span>Trvanie (min)</span>
      <input type="number" name="duration_min" min="15" max="1440" step="5" required value="<?= (int)$r['duration_min'] ?>">
    </label>
    <label class="admin-field">
      <span>Poradie</span>
      <input type="number" name="sort_order" min="0" max="99" value="<?= (int)$r['sort_order'] ?>">
    </label>
    <label class="admin-field admin-field--check">
      <input type="checkbox" name="blocks_full_day" value="1" <?= (int)$r['blocks_full_day'] === 1 ? 'checked' : '' ?>>
      <span>Blokuje celý deň</span>
    </label>
    <label class="admin-field admin-field--check">
      <input type="checkbox" name="is_active" value="1" <?= (int)$r['is_active'] === 1 ? 'checked' : '' ?>>
      <span>Aktívny</span>
    </label>
  </div>
  <div class="admin-form__row">
    <label class="admin-field">
      <span>Cena (text)</span>
      <input type="text" name="price_text" maxlength="40" value="<?= e((string)($r['price_text'] ?? '')) ?>" placeholder="120 – 150 € / balíček">
    </label>
    <label class="admin-field">
      <span>Počet detí (text)</span>
      <input type="text" name="kids_count_text" maxlength="40" value="<?= e((string)($r['kids_count_text'] ?? '')) ?>" placeholder="do 10">
    </label>
    <label class="admin-field">
      <span>Trvanie (text)</span>
      <input type="text" name="duration_text" maxlength="40" value="<?= e((string)($r['duration_text'] ?? '')) ?>" placeholder="2 hodiny">
    </label>
    <label class="admin-field">
      <span>Farba karty</span>
      <?php $accent = in_array($r['accent_color'] ?? '', ['blue','purple','yellow'], true) ? $r['accent_color'] : 'blue'; ?>
      <select name="accent_color">
        <option value="blue"   <?= $accent === 'blue'   ? 'selected' : '' ?>>Modrá</option>
        <option value="purple" <?= $accent === 'purple' ? 'selected' : '' ?>>Fialová</option>
        <option value="yellow" <?= $accent === 'yellow' ? 'selected' : '' ?>>Žltá</option>
      </select>
    </label>
  </div>
  <label class="admin-field">
    <span>Popis (vizuálny editor)</span>
    <div class="quill-editor"></div>
    <textarea name="description" hidden><?= e((string)($r['description'] ?? '')) ?></textarea>
  </label>
  <label class="admin-field">
    <span>Čo balíček zahŕňa <small>(jedna položka na riadok)</small></span>
    <?php $items = json_decode((string)($r['included_json'] ?? '[]'), true); if (!is_array($items)) $items = []; ?>
    <textarea name="included" rows="4"><?= e(implode("\n", $items)) ?></textarea>
  </label>
  <div class="admin-form__actions">
    <button type="submit">Uložiť</button>
  </div>
</form>
<?php endforeach; ?>

<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
<script src="/assets/vendor/quill/quill.js"></script>
<script>
document.querySelectorAll('.quill-editor').forEach(function(el){
  var form = el.closest('form');
  var hidden = form.querySelector('textarea[name="description"]');
  var q = new Quill(el, { theme:'snow', modules:{ toolbar:['bold','italic',{list:'ordered'},{list:'bullet'},'link'] } });
  q.root.innerHTML = hidden.value;
  form.addEventListener('submit', function(){ hidden.value = q.root.innerHTML; });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
