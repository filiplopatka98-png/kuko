<?php
/** @var bool $enabled */
/** @var bool $hasPassword */
/** @var string $user */
/** @var array $flashes */
$title = 'Maintenance — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Údržbový režim</h2>
<p class="admin-lead">Keď je zapnutý, verejnosť vidí údržbovú stránku. Personál sa dostane na web cez heslo. Admin (/admin) a prihlásenie zostávajú dostupné aj počas údržby.</p>

<?php if ($enabled): ?>
  <div class="admin-banner admin-banner--warn">🔧 MAINTENANCE ZAPNUTÝ — verejnosť vidí údržbovú stránku</div>
<?php else: ?>
  <div class="admin-banner admin-banner--ok">✅ Web je verejne dostupný</div>
<?php endif; ?>

<form method="post" action="/admin/maintenance" class="admin-form" id="maint-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <label class="admin-field admin-field--check">
    <input type="checkbox" name="enabled" value="1" id="maint-toggle"<?= $enabled ? ' checked' : '' ?>>
    <span>Zapnúť údržbový režim</span>
  </label>

  <label class="admin-field">
    <span>Heslo pre personál</span>
    <input type="password" name="password" autocomplete="new-password"
           placeholder="Nechaj prázdne pre zachovanie aktuálneho">
    <small>Toto heslo zadáva personál na obídenie údržbovej stránky.
      <?= $hasPassword ? '<strong>Heslo je nastavené.</strong>' : '<strong>Heslo nie je nastavené.</strong>' ?></small>
  </label>

  <div class="admin-form__actions">
    <button type="submit" class="admin-pill">Uložiť</button>
  </div>
</form>

<script>
(function () {
  var form = document.getElementById('maint-form');
  var toggle = document.getElementById('maint-toggle');
  var wasEnabled = <?= $enabled ? 'true' : 'false' ?>;
  if (!form || !toggle) return;
  form.addEventListener('submit', function (e) {
    var willEnable = toggle.checked;
    if (willEnable && !wasEnabled) {
      if (!confirm('Naozaj zapnúť údržbu? Verejnosť uvidí údržbovú stránku.')) { e.preventDefault(); }
    } else if (!willEnable && wasEnabled) {
      if (!confirm('Vypnúť údržbu? Web bude verejne dostupný.')) { e.preventDefault(); }
    }
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
