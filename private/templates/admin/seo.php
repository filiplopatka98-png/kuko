<?php
/** @var array<string,string> $seo */
/** @var bool $indexing */
/** @var string $user */
/** @var array $flashes */
$title = 'SEO — KUKO admin';
$csrf = \Kuko\Csrf::token();

$pages = [
    'default'    => ['Predvolené (fallback)', '/'],
    'home'       => ['Domov',                 '/'],
    'rezervacia' => ['Rezervácia',            '/rezervacia'],
    'faq'        => ['Časté otázky',          '/faq'],
    'privacy'    => ['Ochrana údajov',        '/ochrana-udajov'],
];
$baseUrl = rtrim((string) \Kuko\Config::get('app.url', 'https://kuko-detskysvet.sk'), '/');
ob_start();
?>
<h2>SEO — meta a indexovanie</h2>
<p class="admin-lead">Titulok a popis pre každú stránku (Google výsledok). Zmena sa prejaví na webe okamžite. Ideálna dĺžka titulku ~60 znakov, popisu ~155 znakov.</p>

<form method="post" action="/admin/seo" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <fieldset class="admin-fieldset admin-fieldset--warn">
    <legend>Globálne indexovanie</legend>
    <label class="admin-field admin-field--check">
      <input type="checkbox" name="public_indexing" value="1"<?= $indexing ? ' checked' : '' ?>>
      <span>Povoliť indexovanie webu vyhľadávačmi (Google)</span>
    </label>
    <p class="admin-warn">⚠️ Po zapnutí bude web indexovaný Googlom (robots.txt + meta robots). Nechaj VYPNUTÉ kým web nie je finálny.</p>
  </fieldset>

<?php foreach ($pages as $pg => [$label, $url]): ?>
  <?php
    $tVal = (string) ($seo["seo.$pg.title"] ?? '');
    $dVal = (string) ($seo["seo.$pg.description"] ?? '');
  ?>
  <fieldset class="admin-fieldset" data-seo-page="<?= e($pg) ?>">
    <legend><?= e($label) ?> <small>(<?= e($url) ?>)</small></legend>

    <label class="admin-field">
      <span>Titulok</span>
      <input type="text" name="<?= e($pg) ?>_title" maxlength="65" value="<?= e($tVal) ?>"
             data-seo-title oninput="kukoSeo(this)">
      <small class="admin-counter"><span data-seo-title-count>0</span>/60 znakov</small>
    </label>

    <label class="admin-field">
      <span>Popis (meta description)</span>
      <textarea name="<?= e($pg) ?>_description" maxlength="170" rows="2"
                data-seo-desc oninput="kukoSeo(this)"><?= e($dVal) ?></textarea>
      <small class="admin-counter"><span data-seo-desc-count>0</span>/155 znakov</small>
    </label>

    <div class="admin-seo-preview" aria-hidden="true">
      <div class="admin-seo-preview__url"><?= e($baseUrl . $url) ?></div>
      <div class="admin-seo-preview__title" data-seo-prev-title></div>
      <div class="admin-seo-preview__desc" data-seo-prev-desc></div>
    </div>
  </fieldset>
<?php endforeach; ?>

  <div class="admin-form__actions">
    <button type="submit" class="admin-pill">Uložiť SEO</button>
  </div>
</form>

<script>
(function () {
  function render(fs) {
    var t = fs.querySelector('[data-seo-title]');
    var d = fs.querySelector('[data-seo-desc]');
    var tc = fs.querySelector('[data-seo-title-count]');
    var dc = fs.querySelector('[data-seo-desc-count]');
    var pt = fs.querySelector('[data-seo-prev-title]');
    var pd = fs.querySelector('[data-seo-prev-desc]');
    var tv = (t && t.value) || '';
    var dv = (d && d.value) || '';
    if (tc) { tc.textContent = String(tv.length); tc.parentNode.classList.toggle('admin-counter--over', tv.length > 60); }
    if (dc) { dc.textContent = String(dv.length); dc.parentNode.classList.toggle('admin-counter--over', dv.length > 155); }
    if (pt) pt.textContent = tv || '(prázdny titulok)';
    if (pd) pd.textContent = dv || '(prázdny popis)';
  }
  window.kukoSeo = function (el) {
    var fs = el.closest('fieldset[data-seo-page]');
    if (fs) render(fs);
  };
  document.querySelectorAll('fieldset[data-seo-page]').forEach(render);
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
