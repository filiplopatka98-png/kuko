<?php
/** @var array $rows */
/** @var array $filter */
/** @var int $page */
/** @var int $pages */
/** @var int $total */
/** @var int $perPage */
/** @var string $user */
$title = 'Rezervácie — KUKO admin';
$statusBadge = static fn(string $s): string => match($s) {
    'pending'   => 'badge badge--pending',
    'confirmed' => 'badge badge--ok',
    'cancelled' => 'badge badge--no',
    default     => 'badge',
};
// Build a query string for pagination links, preserving the active filters.
$pageUrl = static function (int $p) use ($filter): string {
    $q = array_filter([
        'status'  => $filter['status']  ?? '',
        'package' => $filter['package'] ?? '',
        'from'    => $filter['from']    ?? '',
        'to'      => $filter['to']      ?? '',
        'q'       => $filter['q']       ?? '',
        'page'    => $p,
    ], fn($v) => $v !== '' && $v !== null);
    return '/admin?' . http_build_query($q);
};
$from = $total > 0 ? (($page - 1) * $perPage) + 1 : 0;
$to   = min($page * $perPage, $total);
ob_start();
?>
<form class="admin-filter" method="get" action="/admin">
  <input type="search" name="q" value="<?= e($filter['q'] ?? '') ?>" placeholder="Hľadať meno / telefón / e-mail" aria-label="Hľadať">
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
<p class="admin-muted" style="margin:0 0 .75rem">Zobrazené <strong><?= $from ?>–<?= $to ?></strong> z <strong><?= $total ?></strong> rezervácií.</p>
<div class="admin-table-wrap">
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
</div>
<?php if ($pages > 1): ?>
<nav class="admin-pager" aria-label="Stránkovanie">
  <?php if ($page > 1): ?>
    <a class="admin-btn" href="<?= e($pageUrl($page - 1)) ?>" rel="prev">← Predchádzajúce</a>
  <?php else: ?>
    <span class="admin-btn admin-btn--disabled" aria-disabled="true">← Predchádzajúce</span>
  <?php endif; ?>
  <span class="admin-pager__status">Strana <?= $page ?> / <?= $pages ?></span>
  <?php if ($page < $pages): ?>
    <a class="admin-btn" href="<?= e($pageUrl($page + 1)) ?>" rel="next">Ďalšie →</a>
  <?php else: ?>
    <span class="admin-btn admin-btn--disabled" aria-disabled="true">Ďalšie →</span>
  <?php endif; ?>
</nav>
<?php endif; ?>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
