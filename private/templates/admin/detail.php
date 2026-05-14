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
  <tr><th>Potvrdené</th><td><?= e($r['confirmed_at'] ?? '—') ?></td></tr>
  <tr><th>Zrušené</th><td><?= e($r['cancelled_at'] ?? '—') ?><?php if (!empty($r['cancelled_reason'])): ?> &mdash; <em><?= e($r['cancelled_reason']) ?></em><?php endif; ?></td></tr>
  <tr><th>reCAPTCHA</th><td><?= e($r['recaptcha_score'] ?? '—') ?></td></tr>
  <tr><th>Status</th><td><strong><?= e($r['status']) ?></strong></td></tr>
</table>

<div class="admin-actions-grid">
  <form method="post" action="/admin/reservation/<?= (int) $r['id'] ?>/status" class="admin-status-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label>Zmeniť status:
      <select name="status">
        <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label style="display:flex;align-items:center;gap:0.5rem">
      <span>Dôvod (ak ruším)</span>
      <input type="text" name="reason" maxlength="255" placeholder="napr. klient sa neozval">
    </label>
    <button type="submit">Uložiť</button>
  </form>

  <form method="post" action="/admin/reservation/<?= (int) $r['id'] ?>/move" class="admin-status-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h3 style="margin:0">Presunúť termín</h3>
    <label>Nový dátum
      <input type="date" name="wished_date" required value="<?= e($r['wished_date']) ?>">
    </label>
    <label>Nový čas
      <input type="time" name="wished_time" required step="1800" value="<?= e(substr((string)$r['wished_time'], 0, 5)) ?>">
    </label>
    <button type="submit">Presunúť</button>
    <p style="margin:0;color:#888;font-size:0.85rem">Backend overí dostupnosť rovnako ako pri novej rezervácii.</p>
  </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
