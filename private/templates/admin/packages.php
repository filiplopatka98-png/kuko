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
  <div class="admin-form__actions">
    <button type="submit">Uložiť</button>
  </div>
</form>
<?php endforeach; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
