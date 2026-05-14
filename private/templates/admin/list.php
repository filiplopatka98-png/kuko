<?php
/** @var array $rows */
/** @var array $filter */
/** @var string $user */
$title = 'Rezervácie — KUKO admin';
$statusBadge = static fn(string $s): string => match($s) {
    'pending'   => 'badge badge--pending',
    'confirmed' => 'badge badge--ok',
    'cancelled' => 'badge badge--no',
    default     => 'badge',
};
ob_start();
?>
<form class="admin-filter" method="get" action="/admin">
  <select name="status" aria-label="Status">
    <option value="">Všetky statusy</option>
    <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= ($filter['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <select name="package" aria-label="Balíček">
    <option value="">Všetky balíčky</option>
    <?php foreach (['mini','maxi','closed'] as $p): ?>
      <option value="<?= $p ?>" <?= ($filter['package'] ?? '') === $p ? 'selected' : '' ?>><?= strtoupper($p) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($filter['from'] ?? '') ?>" aria-label="Od dátumu">
  <input type="date" name="to"   value="<?= e($filter['to']   ?? '') ?>" aria-label="Do dátumu">
  <button type="submit">Filtrovať</button>
  <a href="/admin" class="admin-filter__reset">Reset</a>
</form>

<?php if (!$rows): ?>
  <p class="admin-empty">Žiadne rezervácie nezodpovedajú filtru.</p>
<?php else: ?>
<table class="admin-table">
  <thead>
    <tr>
      <th>#</th><th>Vytvorené</th><th>Balíček</th><th>Termín</th>
      <th>Meno</th><th>Telefón</th><th>Status</th><th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int) $r['id'] ?></td>
      <td><?= e($r['created_at']) ?></td>
      <td><?= e(strtoupper((string) $r['package'])) ?></td>
      <td><?= e($r['wished_date']) ?> <?= e(substr((string) $r['wished_time'], 0, 5)) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
      <td><span class="<?= $statusBadge((string) $r['status']) ?>"><?= e($r['status']) ?></span></td>
      <td><a href="/admin/reservation/<?= (int) $r['id'] ?>">Detail →</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
