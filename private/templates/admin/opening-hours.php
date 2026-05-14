<?php
/** @var array<int,array<string,mixed>> $hours */
/** @var string $user */
$title = 'Otváracie hodiny — KUKO admin';
$csrf = \Kuko\Csrf::token();
$names = \Kuko\OpeningHoursRepo::WEEKDAY_NAMES;
// Display order: Monday first
$order = [1, 2, 3, 4, 5, 6, 0];
ob_start();
?>
<h2>Otváracie hodiny</h2>
<p class="admin-lead">Definuje, kedy je herňa otvorená. Klient v rezervačnom formulári uvidí len termíny v rámci týchto hodín.</p>

<form method="post" action="/admin/opening-hours" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <table class="admin-table admin-table--hours">
    <thead><tr><th>Deň</th><th>Otvorené</th><th>Od</th><th>Do</th></tr></thead>
    <tbody>
    <?php foreach ($order as $d): $h = $hours[$d] ?? null; ?>
      <tr>
        <td><strong><?= e($names[$d]) ?></strong></td>
        <td><label><input type="checkbox" name="is_open[<?= $d ?>]" value="1" <?= !empty($h['is_open']) ? 'checked' : '' ?>> otvorené</label></td>
        <td><input type="time" name="open_from[<?= $d ?>]" value="<?= e(substr((string)($h['open_from'] ?? '09:00'), 0, 5)) ?>"></td>
        <td><input type="time" name="open_to[<?= $d ?>]"   value="<?= e(substr((string)($h['open_to']   ?? '20:00'), 0, 5)) ?>"></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="admin-form__actions">
    <button type="submit">Uložiť</button>
  </div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
