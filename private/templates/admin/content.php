<?php
/** @var array<string,array<int,array<string,mixed>>> $groups */
/** @var string $user */
/** @var array $flashes */
$title = 'Obsah — KUKO admin';
$csrf = \Kuko\Csrf::token();
$groupTitles = [
    'hero'    => 'Hero',
    'about'   => 'O nás',
    'cennik'  => 'Cenník',
    'kontakt' => 'Kontakt',
    'footer'  => 'Footer',
];
$groupAnchors = [
    'hero'    => '/#domov',
    'about'   => '/#o-nas',
    'cennik'  => '/#cennik',
    'kontakt' => '/#kontakt',
    'footer'  => '/',
];
ob_start();
?>
<h2>Obsah webu</h2>
<p class="admin-lead">Úprava textových blokov webu. HTML bloky majú vizuálny editor; ostatné sú obyčajný text. Po uložení sa zmena prejaví na webe okamžite.</p>

<?php foreach ($groups as $gkey => $blocks): ?>
  <section class="admin-form">
    <h3>
      <?= e($groupTitles[$gkey] ?? ucfirst((string) $gkey)) ?>
      <a href="<?= e($groupAnchors[$gkey] ?? '/') ?>" target="_blank" rel="noopener" class="admin-link">Pozrieť na webe ↗</a>
    </h3>

    <?php foreach ($blocks as $block): ?>
      <?php
        $bkey  = (string) $block['block_key'];
        $btype = (string) $block['content_type'];
        $bval  = (string) $block['value'];
        $blabel = (string) $block['label'];
      ?>
      <form method="post" action="/admin/content/save" class="admin-field">
        <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="block_key" value="<?= e($bkey) ?>">
        <input type="hidden" name="content_type" value="<?= e($btype) ?>">
        <span><?= e($blabel) ?> <small><?= e($bkey) ?></small></span>

        <?php if ($btype === 'html'): ?>
          <div class="quill-editor"></div>
          <textarea name="value" hidden><?= e($bval) ?></textarea>
        <?php elseif (strlen($bval) > 60 || str_ends_with($bkey, '.lead')): ?>
          <textarea name="value" rows="3"><?= e($bval) ?></textarea>
        <?php else: ?>
          <input type="text" name="value" value="<?= e($bval) ?>">
        <?php endif; ?>

        <div class="admin-form__actions">
          <button type="submit">Uložiť</button>
        </div>
      </form>
    <?php endforeach; ?>
  </section>
<?php endforeach; ?>

<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
<script src="/assets/vendor/quill/quill.js"></script>
<script>
document.querySelectorAll('.quill-editor').forEach(function(el){
  var form = el.closest('form');
  var hidden = form.querySelector('textarea[name="value"]');
  var q = new Quill(el, { theme:'snow', modules:{ toolbar:['bold','italic',{list:'ordered'},{list:'bullet'},'link'] } });
  q.root.innerHTML = hidden.value;
  form.addEventListener('submit', function(){ hidden.value = q.root.innerHTML; });
});
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
