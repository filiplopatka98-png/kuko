<?php
/** @var array<string,string> $contact */
/** @var string $user */
/** @var array $flashes */
$title = 'Kontakt — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Kontaktné údaje</h2>
<p class="admin-lead">Adresa, telefón, e-mail a otváracie hodiny sa zobrazujú v sekcii Kontakt. Odkazy na sociálne siete sa zobrazujú v pätke kontaktu aj v štruktúrovaných dátach (Schema.org). Po uložení sa zmena prejaví na webe okamžite.</p>

<form method="post" action="/admin/contact" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <label class="admin-field">
    <span>Adresa</span>
    <input type="text" name="address" maxlength="255" value="<?= e($contact['address']) ?>" required>
  </label>

  <label class="admin-field">
    <span>Telefón</span>
    <input type="text" name="phone" maxlength="60" value="<?= e($contact['phone']) ?>" required>
  </label>

  <label class="admin-field">
    <span>E-mail</span>
    <input type="email" name="email" maxlength="120" value="<?= e($contact['email']) ?>" required>
  </label>

  <label class="admin-field">
    <span>Otváracie hodiny (text)</span>
    <textarea name="hours" rows="2" required><?= e($contact['hours']) ?></textarea>
  </label>

  <label class="admin-field">
    <span>Facebook URL</span>
    <input type="url" name="facebook" maxlength="255" value="<?= e($contact['facebook']) ?>" placeholder="https://facebook.com/...">
  </label>

  <label class="admin-field">
    <span>Instagram URL</span>
    <input type="url" name="instagram" maxlength="255" value="<?= e($contact['instagram']) ?>" placeholder="https://instagram.com/...">
  </label>

  <div class="admin-form__actions">
    <button type="submit">Uložiť</button>
  </div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
