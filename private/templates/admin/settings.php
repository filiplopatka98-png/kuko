<?php
/** @var array<string,string> $settings */
/** @var string $user */
/** @var array $flashes */
$title = 'Nastavenia — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Nastavenia rezervácií</h2>
<form method="post" action="/admin/settings" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <label class="admin-field">
    <span>Buffer medzi rezerváciami (minúty)</span>
    <input type="number" name="buffer_min" min="0" max="240" value="<?= e($settings['buffer_min'] ?? '30') ?>" required>
    <small>Voľný čas medzi koncom jednej a začiatkom ďalšej oslavy (úprata, rekonfigurácia priestoru).</small>
  </label>

  <label class="admin-field">
    <span>Booking horizont (dni)</span>
    <input type="number" name="horizon_days" min="1" max="730" value="<?= e($settings['horizon_days'] ?? '180') ?>" required>
    <small>Koľko dní dopredu môže klient rezervovať.</small>
  </label>

  <label class="admin-field">
    <span>Lead time (hodiny)</span>
    <input type="number" name="lead_hours" min="0" max="168" value="<?= e($settings['lead_hours'] ?? '24') ?>" required>
    <small>Minimálny predstih medzi „teraz" a rezerváciou. Nastavte 0 ak má byť možné rezervovať okamžite.</small>
  </label>

  <label class="admin-field">
    <span>Slot increment (minúty)</span>
    <input type="number" name="slot_increment_min" min="5" max="120" value="<?= e($settings['slot_increment_min'] ?? '30') ?>" required>
    <small>Krok pre generovanie dostupných časov (napr. 30 = ponuky 09:00, 09:30, 10:00…).</small>
  </label>

  <div class="admin-form__actions">
    <button type="submit">Uložiť</button>
  </div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
