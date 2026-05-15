<?php
/** @var array<string,array<string,mixed>> $pages */
/** @var string $user */
/** @var array $flashes */
$title = 'Stránky — KUKO admin';
ob_start();
?>
<h2>Stránky</h2>
<p class="admin-lead">Zoznam stránok webu. Kliknutím na „Upraviť" upravíte obsah aj SEO danej stránky na jednom mieste.</p>

<table class="admin-table">
  <thead>
    <tr>
      <th>Stránka</th>
      <th>URL</th>
      <th></th>
    </tr>
  </thead>
  <tbody>
  <?php foreach ($pages as $key => $cfg): ?>
    <tr>
      <td><strong><?= e((string) $cfg['label']) ?></strong></td>
      <td>
        <a href="<?= e((string) $cfg['url']) ?>" target="_blank" rel="noopener" class="admin-link">
          <?= e((string) $cfg['url']) ?> ↗
        </a>
      </td>
      <td>
        <a href="/admin/pages/<?= e((string) $key) ?>" class="admin-pill">Upraviť</a>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
