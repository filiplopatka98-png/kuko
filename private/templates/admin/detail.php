<?php
/** @var array $r */
/** @var string $gcal */
/** @var string $user */
$title = 'Rezervácia #' . (int) $r['id'] . ' — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<p><a href="/admin">&larr; Späť na zoznam</a></p>
<h2>Rezervácia #<?= (int) $r['id'] ?></h2>
<table class="admin-detail">
  <tr><th scope="row">Balíček</th><td><?= e(strtoupper((string) $r['package'])) ?></td></tr>
  <tr><th scope="row">Termín</th><td><?= e($r['wished_date']) ?> o <?= e(substr((string) $r['wished_time'], 0, 5)) ?></td></tr>
  <tr><th scope="row">Počet detí</th><td><?= (int) $r['kids_count'] ?></td></tr>
  <tr><th scope="row">Meno</th><td><?= e($r['name']) ?></td></tr>
  <tr><th scope="row">Telefón</th><td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td></tr>
  <tr><th scope="row">E-mail</th><td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td></tr>
  <tr><th scope="row">Poznámka</th><td><?= nl2br(e($r['note'] ?? '—')) ?></td></tr>
  <tr><th scope="row">Vytvorené</th><td><?= e($r['created_at']) ?></td></tr>
  <tr><th scope="row">Potvrdené</th><td><?= e($r['confirmed_at'] ?? '—') ?></td></tr>
  <tr><th scope="row">Zrušené</th><td><?= e($r['cancelled_at'] ?? '—') ?><?php if (!empty($r['cancelled_reason'])): ?> &mdash; <em><?= e($r['cancelled_reason']) ?></em><?php endif; ?></td></tr>
  <tr><th scope="row">reCAPTCHA</th><td><?= e($r['recaptcha_score'] ?? '—') ?></td></tr>
  <tr><th scope="row">Status</th><td><strong><?= e($r['status']) ?></strong></td></tr>
</table>

<?php if (!empty($gcal)): ?>
<p class="admin-detail-gcal">
  <a class="admin-pill" href="<?= e($gcal) ?>" target="_blank" rel="noopener">Pridať do Google kalendára</a>
</p>
<?php endif; ?>

<div class="admin-actions-grid">
  <form method="post" action="/admin/reservation/<?= (int) $r['id'] ?>/status" class="admin-status-form" data-confirm="Uložiť zmenu statusu? Pri potvrdení alebo zrušení sa klientovi automaticky odošle e-mail.">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label>Zmeniť status:
      <select name="status" data-status-select>
        <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
          <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="admin-reason-field" data-reason-field<?= $r['status'] === 'cancelled' ? '' : ' hidden' ?>>
      <span>Dôvod zrušenia</span>
      <input type="text" name="reason" maxlength="255" placeholder="napr. klient sa neozval">
    </label>
    <button type="submit">Uložiť</button>
    <p class="admin-muted admin-status-note">Pri zmene na <strong>potvrdené</strong> alebo <strong>zrušené</strong> sa klientovi automaticky odošle e-mail.</p>
  </form>

  <form method="post" action="/admin/reservation/<?= (int) $r['id'] ?>/move" class="admin-status-form">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <h3 class="admin-form__h3-flush">Presunúť termín</h3>
    <label>Nový dátum
      <input type="date" name="wished_date" required value="<?= e($r['wished_date']) ?>">
    </label>
    <label>Nový čas
      <input type="time" name="wished_time" required step="1800" value="<?= e(substr((string)$r['wished_time'], 0, 5)) ?>">
    </label>
    <button type="submit">Presunúť</button>
    <p class="admin-muted admin-status-note">Backend overí dostupnosť rovnako ako pri novej rezervácii.</p>
  </form>
</div>

<div class="admin-detail-gdpr">
  <form method="post" action="/admin/reservation/<?= (int) $r['id'] ?>/anonymize"
        data-confirm="Anonymizovať? PII (meno, telefón, e-mail, poznámka) sa nenávratne vymažú. Štatistika zostane.">
    <input type="hidden" name="csrf" value="<?= e(\Kuko\Csrf::token()) ?>">
    <button type="submit" class="admin-btn-link">Anonymizovať (GDPR)</button>
  </form>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
