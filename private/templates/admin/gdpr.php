<?php
/** @var string $email */
/** @var array<int,array<string,mixed>> $rows */
/** @var string $user */
/** @var array $flashes */
$title = 'GDPR — KUKO admin';
ob_start();
?>
<h2>GDPR — žiadosti dotknutej osoby</h2>
<p class="admin-lead">Zadajte e-mail klienta pre výpis jeho rezervácií (právo na prístup, čl. 15 GDPR).</p>
<form method="get" action="/admin/gdpr" class="admin-form">
  <label class="admin-field">
    <span>E-mail klienta</span>
    <input type="email" name="email" value="<?= e($email) ?>" required>
  </label>
  <div class="admin-form__actions"><button type="submit">Vyhľadať</button></div>
</form>
<?php if ($email !== ''): ?>
  <p><strong><?= count($rows) ?></strong> rezervácií pre <?= e($email) ?>.</p>
  <?php if (!empty($rows)): ?>
  <div class="admin-table-wrap">
    <table class="admin-table">
      <thead>
        <tr><th>#</th><th>Balíček</th><th>Dátum</th><th>Meno</th><th>Telefón</th><th>Stav</th></tr>
      </thead>
      <tbody>
      <?php foreach ($rows as $r): ?>
        <tr>
          <td><?= (int) $r['id'] ?></td>
          <td><?= e((string) $r['package']) ?></td>
          <td><?= e((string) $r['wished_date']) ?> <?= e((string) $r['wished_time']) ?></td>
          <td><?= e((string) $r['name']) ?></td>
          <td><?= e((string) $r['phone']) ?></td>
          <td><?= e((string) $r['status']) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php else: ?>
    <p class="admin-muted">Žiadne rezervácie nenájdené.</p>
  <?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
