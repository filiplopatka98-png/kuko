<?php
/** @var array<string,array{label:string,subject:string,intro:string,defaults:array{subject:string,intro:string}}> $types */
/** @var array<string,string> $sample */
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
  <details class="admin-fieldset admin-acc admin-mail" data-mail open>
    <summary><?= e($t['label']) ?></summary>

    <label class="admin-field">
      <span>Predmet</span>
      <input type="text" name="<?= e($key) ?>_subject" data-mail-subject
             value="<?= e($t['subject']) ?>"
             placeholder="<?= e($t['defaults']['subject']) ?>">
    </label>

    <label class="admin-field">
      <span>Hlavný text</span>
      <textarea name="<?= e($key) ?>_intro" rows="5" data-mail-intro
                placeholder="<?= e($t['defaults']['intro']) ?>"><?= e($t['intro']) ?></textarea>
      <small>Prázdny riadok oddelí odsek. Predvolený text necháte prázdnym poľom.</small>
    </label>

    <div class="admin-mail-preview" aria-live="polite">
      <span class="admin-mail-preview__label">Ukážka</span>
      <div class="admin-mail-preview__subject" data-mail-pv-subject></div>
      <div class="admin-mail-preview__body" data-mail-pv-body></div>
    </div>
  </details>
  <?php endforeach; ?>

  <div class="admin-form__actions">
    <button type="submit" class="admin-pill">Uložiť</button>
  </div>
</form>

<script>
(function () {
  var TOKENS = <?= json_encode($sample, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  function sub(s) {
    return s.replace(/\{(name|package|date|time|kids)\}/g, function (m, k) {
      return TOKENS['{' + k + '}'] != null ? TOKENS['{' + k + '}'] : m;
    });
  }
  function esc(s) {
    return s.replace(/[&<>"]/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c];
    });
  }
  function bodyHtml(txt) {
    return sub(txt).trim().split(/\n{2,}/).filter(function (b) {
      return b.trim() !== '';
    }).map(function (b) {
      return '<p>' + esc(b.trim()).replace(/\n/g, '<br>') + '</p>';
    }).join('');
  }
  document.querySelectorAll('[data-mail]').forEach(function (box) {
    var subEl  = box.querySelector('[data-mail-subject]');
    var intro  = box.querySelector('[data-mail-intro]');
    var pvSub  = box.querySelector('[data-mail-pv-subject]');
    var pvBody = box.querySelector('[data-mail-pv-body]');
    function render() {
      var sVal = subEl.value || subEl.getAttribute('placeholder') || '';
      var iVal = intro.value || intro.getAttribute('placeholder') || '';
      pvSub.textContent = sub(sVal);
      pvBody.innerHTML = bodyHtml(iVal);
    }
    subEl.addEventListener('input', render);
    intro.addEventListener('input', render);
    render();
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
