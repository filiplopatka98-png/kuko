<?php
/** @var array<int,array<string,mixed>> $rows */
/** @var string $user */
$title = 'Blokované obdobia — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Blokované obdobia</h2>
<p class="admin-lead">Sviatky, dovolenky, jednorazové výnimky. Termín v týchto obdobiach nebude pre klienta dostupný.</p>

<details class="admin-collapsible" open>
  <summary>Pridať nové</summary>
  <form method="post" action="/admin/blocked-periods" class="admin-form admin-form--inline">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <div class="admin-form__row">
      <label class="admin-field">
        <span>Od dátumu</span>
        <input type="date" name="date_from" required>
      </label>
      <label class="admin-field">
        <span>Do dátumu</span>
        <input type="date" name="date_to" required>
      </label>
      <label class="admin-field">
        <span>Od času (voliteľné, prázdne = celý deň)</span>
        <input type="time" name="time_from">
      </label>
      <label class="admin-field">
        <span>Do času (voliteľné)</span>
        <input type="time" name="time_to">
      </label>
    </div>
    <label class="admin-field">
      <span>Dôvod (poznámka)</span>
      <input type="text" name="reason" maxlength="255" placeholder="napr. Vianoce, plánovaný servis…">
    </label>
    <div class="admin-form__actions">
      <button type="submit">Pridať blokáciu</button>
    </div>
  </form>
</details>

<?php if (!$rows): ?>
  <p class="admin-empty">Žiadne blokované obdobia.</p>
<?php else: ?>
<table class="admin-table">
  <thead><tr><th>Od</th><th>Do</th><th>Čas</th><th>Dôvod</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= e($r['date_from']) ?></td>
      <td><?= e($r['date_to']) ?></td>
      <td><?php if ($r['time_from'] || $r['time_to']): ?><?= e(substr((string)$r['time_from'], 0, 5)) ?>–<?= e(substr((string)$r['time_to'], 0, 5)) ?><?php else: ?><em>celý deň</em><?php endif; ?></td>
      <td><?= e($r['reason'] ?? '—') ?></td>
      <td>
        <form method="post" action="/admin/blocked-periods/<?= (int)$r['id'] ?>/delete" style="display:inline" onsubmit="return confirm('Naozaj zmazať?');">
          <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
          <button type="submit" class="admin-btn-link">Zmazať</button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
