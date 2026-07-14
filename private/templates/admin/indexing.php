<?php
/** @var bool $enabled */
/** @var string $user */
/** @var array $flashes */
$title = 'Indexácia — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Indexácia vo vyhľadávačoch</h2>
<p class="admin-lead">Riadi <code>robots.txt</code> celého webu a <code>&lt;meta name="robots"&gt;</code> na každej stránke. Údržbu (503) toto NEovplyvňuje — tie sú samostatné prepínače.</p>

<?php if ($enabled): ?>
  <div class="admin-banner admin-banner--ok">✅ Indexácia ZAPNUTÁ — Google a ďalšie vyhľadávače môžu web indexovať</div>
<?php else: ?>
  <div class="admin-banner admin-banner--warn">🔒 Indexácia VYPNUTÁ — <code>robots.txt</code> = <code>Disallow: /</code>, každá stránka má <code>noindex, nofollow</code></div>
<?php endif; ?>

<form method="post" action="/admin/indexing" class="admin-form" id="idx-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">

  <label class="admin-field admin-field--check">
    <input type="checkbox" name="enabled" value="1" id="idx-toggle"<?= $enabled ? ' checked' : '' ?>>
    <span>Povoliť indexáciu vyhľadávačmi</span>
  </label>
  <small class="admin-lead" style="margin-top:-0.25rem">
    Zapni až keď je web hotový (texty, fotky, kontakty, e-maily otestované). Po zapnutí trvá Googlu pár dní kým web zaindexuje.
  </small>

  <div class="admin-form__actions">
    <button type="submit" class="admin-pill">Uložiť</button>
  </div>
</form>

<script nonce="<?= e(\Kuko\Csp::nonce()) ?>">
(function () {
  var form = document.getElementById('idx-form');
  var toggle = document.getElementById('idx-toggle');
  var wasEnabled = <?= $enabled ? 'true' : 'false' ?>;
  if (!form || !toggle) return;
  form.addEventListener('submit', function (e) {
    var willEnable = toggle.checked;
    if (willEnable && !wasEnabled) {
      if (!confirm('Naozaj zapnúť indexáciu? Google začne web indexovať.')) { e.preventDefault(); }
    } else if (!willEnable && wasEnabled) {
      if (!confirm('Vypnúť indexáciu? Web zmizne z výsledkov vyhľadávania (môže trvať dni/týždne).')) { e.preventDefault(); }
    }
  });
})();
</script>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
