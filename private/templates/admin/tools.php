<?php
/** @var string $output */
/** @var string $user */
/** @var array $flashes */
$title = 'Nástroje — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<h2>Systémové nástroje (deploy)</h2>
<p class="admin-lead">Databázové operácie počas nasadenia. Predtým bežali cez verejný <code>_setup.php</code> s tokenom v URL — teraz sú za prihlásením. Poradie pri nasadení: <strong>kód → Migrácie → Seed</strong>.</p>

<div class="admin-actions-grid">
  <form method="post" action="/admin/tools/run" data-confirm="Spustiť migrácie databázy?">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="migrate">
    <h3 style="margin:0 0 .5rem">Migrácie</h3>
    <p class="admin-lead" style="margin:0 0 .75rem">Aplikuje nové <code>*.sql</code> migrácie (idempotentné — už aplikované preskočí).</p>
    <button type="submit">Spustiť migrácie</button>
  </form>

  <form method="post" action="/admin/tools/run" data-confirm="Spustiť seed obsahu?">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="seed">
    <h3 style="margin:0 0 .5rem">Seed obsahu</h3>
    <p class="admin-lead" style="margin:0 0 .75rem">Doplní chýbajúce content bloky/nastavenia (insert-only — existujúce neprepíše).</p>
    <button type="submit">Spustiť seed</button>
  </form>

  <form method="post" action="/admin/tools/run">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="smoke">
    <h3 style="margin:0 0 .5rem">Smoke test</h3>
    <p class="admin-lead" style="margin:0 0 .75rem">Overí pripojenie k DB a vypíše tabuľky (bez zmien).</p>
    <button type="submit">Spustiť smoke test</button>
  </form>

  <form method="post" action="/admin/tools/run" data-confirm="Naozaj nahradiť „{old}" → „{new}" vo VŠETKÝCH blokoch a nastaveniach? Táto operácia je nevratná.">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="fix-domain">
    <h3 style="margin:0 0 .5rem">Hromadná náhrada textu</h3>
    <label class="admin-field"><span>Pôvodný text</span>
      <input type="text" name="old" placeholder="napr. stara-domena.sk" required></label>
    <label class="admin-field"><span>Nový text</span>
      <input type="text" name="new" placeholder="napr. nova-domena.sk"></label>
    <button type="submit">Nahradiť</button>
  </form>
</div>

<?php if ($output !== ''): ?>
  <h3 style="margin-top:2rem">Výstup</h3>
  <pre class="admin-tools-output"><?= e($output) ?></pre>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
