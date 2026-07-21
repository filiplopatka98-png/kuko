<?php /** @var array $r */ /** @var string $statusLink */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#A8478A">Rezervácia bola zrušená</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<?= \Kuko\MailContent::introHtml('reservation_cancelled', $r) ?>
<?php include __DIR__ . '/_details.html.php'; ?>
<p>Ak chcete dohodnúť iný termín, ozvite sa nám — radi vám pomôžeme. Kontakt nájdete nižšie.</p>
<?php include __DIR__ . '/_footer.html.php'; ?>
</body></html>
