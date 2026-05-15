<?php
/** @var string $page */
/** @var string $label */
/** @var string $url */
/** @var string $seoKey */
/** @var array<string,array<int,array<string,mixed>>> $groups */
/** @var string $seoTitle */
/** @var string $seoDesc */
/** @var string $user */
/** @var array $flashes */
$title = 'Upraviť: ' . $label . ' — KUKO admin';
$csrf  = \Kuko\Csrf::token();
$baseUrl = rtrim((string) \Kuko\Config::get('app.url', 'https://kuko-detskysvet.sk'), '/');
$hasContent = !empty($groups);
ob_start();
?>
<h2>Upraviť stránku: <?= e($label) ?>
  <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="admin-link">Pozrieť na webe ↗</a>
</h2>
<p class="admin-lead">Upravte textový obsah aj SEO (Google výsledok) tejto stránky. Zmena sa prejaví na webe okamžite.</p>

<nav class="admin-tabs" aria-label="Sekcie stránky">
  <button type="button" class="admin-tab is-active" data-pagetab="obsah" aria-current="page">Obsah</button>
  <button type="button" class="admin-tab" data-pagetab="seo">SEO</button>
</nav>

<form method="post" action="/admin/pages/<?= e($page) ?>/save" class="admin-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <section data-pagepanel="obsah">
  <?php if (!$hasContent): ?>
    <p class="admin-lead">Táto stránka nemá editovateľný textový obsah — upravte len SEO.</p>
  <?php else: ?>
    <?php foreach ($groups as $gkey => $blocks): ?>
      <fieldset class="admin-fieldset">
        <legend><?= e(ucfirst((string) $gkey)) ?></legend>
        <?php foreach ($blocks as $block): ?>
          <?php
            $bkey   = (string) $block['block_key'];
            $btype  = (string) $block['content_type'];
            $bval   = (string) $block['value'];
            $blabel = (string) $block['label'];
          ?>
          <div class="admin-field">
            <span><?= e($blabel) ?> <small><?= e($bkey) ?></small></span>
            <input type="hidden" name="blocks[<?= e($bkey) ?>][key]" value="<?= e($bkey) ?>">
            <?php if ($btype === 'html'): ?>
              <input type="hidden" name="blocks[<?= e($bkey) ?>][type]" value="html">
              <div class="quill-editor" data-quill-for="<?= e($bkey) ?>"></div>
              <textarea name="blocks[<?= e($bkey) ?>][value]" hidden><?= e($bval) ?></textarea>
            <?php elseif (strlen($bval) > 60 || str_ends_with($bkey, '.lead')): ?>
              <input type="hidden" name="blocks[<?= e($bkey) ?>][type]" value="text">
              <textarea name="blocks[<?= e($bkey) ?>][value]" rows="3"><?= e($bval) ?></textarea>
            <?php else: ?>
              <input type="hidden" name="blocks[<?= e($bkey) ?>][type]" value="text">
              <input type="text" name="blocks[<?= e($bkey) ?>][value]" value="<?= e($bval) ?>">
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </fieldset>
    <?php endforeach; ?>
  <?php endif; ?>
  </section>

  <section data-pagepanel="seo" hidden>
    <fieldset class="admin-fieldset" data-seo-page="<?= e($seoKey) ?>">
      <legend><?= e($label) ?> <small>(<?= e($url) ?>)</small></legend>

      <label class="admin-field">
        <span>Titulok</span>
        <input type="text" name="seo_title" maxlength="65" value="<?= e($seoTitle) ?>"
               data-seo-title oninput="kukoSeo(this)">
        <small class="admin-counter"><span data-seo-title-count>0</span>/60 znakov</small>
      </label>

      <label class="admin-field">
        <span>Popis (meta description)</span>
        <textarea name="seo_description" maxlength="170" rows="2"
                  data-seo-desc oninput="kukoSeo(this)"><?= e($seoDesc) ?></textarea>
        <small class="admin-counter"><span data-seo-desc-count>0</span>/155 znakov</small>
      </label>

      <div class="admin-seo-preview" aria-hidden="true">
        <div class="admin-seo-preview__url"><?= e($baseUrl . $url) ?></div>
        <div class="admin-seo-preview__title" data-seo-prev-title></div>
        <div class="admin-seo-preview__desc" data-seo-prev-desc></div>
      </div>
    </fieldset>
  </section>

  <div class="admin-form__actions">
    <button type="submit" class="admin-pill">Uložiť stránku</button>
  </div>
</form>

<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
<script src="/assets/vendor/quill/quill.js"></script>
<script>
// Sub-tab toggle (Obsah | SEO)
(function () {
  var tabs = document.querySelectorAll('[data-pagetab]');
  var panels = document.querySelectorAll('[data-pagepanel]');
  tabs.forEach(function (tab) {
    tab.addEventListener('click', function () {
      var name = tab.getAttribute('data-pagetab');
      tabs.forEach(function (t) {
        var on = t === tab;
        t.classList.toggle('is-active', on);
        if (on) { t.setAttribute('aria-current', 'page'); } else { t.removeAttribute('aria-current'); }
      });
      panels.forEach(function (p) {
        p.hidden = p.getAttribute('data-pagepanel') !== name;
      });
    });
  });
})();
// Quill init — one editor per .quill-editor, synced to its sibling hidden textarea on submit
document.querySelectorAll('.quill-editor').forEach(function (el) {
  var wrap = el.closest('.admin-field');
  var hidden = wrap.querySelector('textarea[hidden]');
  var q = new Quill(el, { theme: 'snow', modules: { toolbar: ['bold', 'italic', { list: 'ordered' }, { list: 'bullet' }, 'link'] } });
  q.root.innerHTML = hidden.value;
  el.closest('form').addEventListener('submit', function () { hidden.value = q.root.innerHTML; });
});
// SEO counter + Google preview (mirrors seo.php)
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
