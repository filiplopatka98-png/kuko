<?php /** @var array $r */ /** @var string $statusLink */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Rezervácia bola zrušená</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<p>Vaša rezervácia balíčka <strong><?= e(strtoupper((string) $r['package'])) ?></strong> dňa <strong><?= e($r['wished_date']) ?></strong> o <strong><?= e(substr((string) $r['wished_time'], 0, 5)) ?></strong> bola zrušená.</p>
<?php if (!empty($r['cancelled_reason'])): ?>
<p>Dôvod: <em><?= e($r['cancelled_reason']) ?></em></p>
<?php endif; ?>
<p>Ak chcete dohodnúť iný termín, zavolajte na <a href="tel:+421915319934">+421 915 319 934</a> alebo nám napíšte na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>. Radi Vám pomôžeme.</p>
<p>Ďakujeme za pochopenie.<br><strong>Tím KUKO detský svet</strong></p>
</body></html>
