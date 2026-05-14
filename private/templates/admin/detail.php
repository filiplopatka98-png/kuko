<?php
/** @var array $r */
/** @var string $user */
$title = 'Rezervácia #' . (int) $r['id'] . ' — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<p><a href="/admin">&larr; Späť na zoznam</a></p>
<h2>Rezervácia #<?= (int) $r['id'] ?></h2>
<table class="admin-detail">
  <tr><th>Balíček</th><td><?= e(strtoupper((string) $r['package'])) ?></td></tr>
  <tr><th>Termín</th><td><?= e($r['wished_date']) ?> o <?= e(substr((string) $r['wished_time'], 0, 5)) ?></td></tr>
  <tr><th>Počet detí</th><td><?= (int) $r['kids_count'] ?></td></tr>
  <tr><th>Meno</th><td><?= e($r['name']) ?></td></tr>
  <tr><th>Telefón</th><td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td></tr>
  <tr><th>E-mail</th><td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td></tr>
  <tr><th>Poznámka</th><td><?= nl2br(e($r['note'] ?? '—')) ?></td></tr>
  <tr><th>Vytvorené</th><td><?= e($r['created_at']) ?></td></tr>
  <tr><th>Aktualizované</th><td><?= e($r['updated_at']) ?></td></tr>
  <tr><th>reCAPTCHA score</th><td><?= e($r['recaptcha_score'] ?? '—') ?></td></tr>
  <tr><th>User agent</th><td><code style="font-size:0.75rem"><?= e($r['user_agent'] ?? '—') ?></code></td></tr>
  <tr><th>Status</th><td><strong><?= e($r['status']) ?></strong></td></tr>
</table>

<form method="post" action="/admin/reservation/<?= (int) $r['id'] ?>/status" class="admin-status-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <label>Zmeniť status:
    <select name="status">
      <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit">Uložiť</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
