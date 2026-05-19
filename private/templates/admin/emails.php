<?php
/** @var array<string,array{label:string,subject:string,intro:string,defaults:array{subject:string,intro:string},fullHtml:string}> $types */
/** @var string $user */
/** @var array $flashes */
$title = 'E-maily — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Texty e-mailov</h2>
<p class="admin-lead">Upravte predmet a hlavný text automatických e-mailov. Štruktúrované údaje (termín, kontakt, odkaz na rezerváciu, pätička) sa dopĺňajú automaticky a nedajú sa tu meniť.</p>
<p class="admin-lead admin-mail-tokens">Zástupné značky (nahradia sa skutočnými údajmi):
<code>{name}</code> meno · <code>{package}</code> balíček · <code>{date}</code> dátum · <code>{time}</code> čas · <code>{kids}</code> počet detí</p>

<form method="post" action="/admin/emails" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <?php foreach ($types as $key => $t): ?>
  <details class="admin-fieldset admin-acc admin-mail" open>
    <summary><?= e($t['label']) ?></summary>

    <label class="admin-field">
      <span>Predmet</span>
      <input type="text" name="<?= e($key) ?>_subject"
             value="<?= e($t['subject']) ?>"
             placeholder="<?= e($t['defaults']['subject']) ?>">
    </label>

    <label class="admin-field">
      <span>Hlavný text</span>
      <textarea name="<?= e($key) ?>_intro" rows="5"
                placeholder="<?= e($t['defaults']['intro']) ?>"><?= e($t['intro']) ?></textarea>
      <small>Prázdny riadok oddelí odsek. Predvolený text necháte prázdnym poľom.</small>
    </label>

    <div class="admin-mail-full">
      <span class="admin-mail-preview__label">Náhľad e-mailu (ukážkové dáta — aktualizuje sa po Uložiť)</span>
      <iframe class="admin-mail-frame" title="Náhľad e-mailu" sandbox
              srcdoc="<?= e($t['fullHtml']) ?>"></iframe>
    </div>
  </details>
  <?php endforeach; ?>

  <div class="admin-form__actions">
    <button type="submit" class="admin-pill">Uložiť</button>
  </div>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
