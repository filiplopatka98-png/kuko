<?php /** @var array $r */ /** @var string $statusLink */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#A8478A">Ďakujeme za vašu rezerváciu! 🎉</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<?= \Kuko\MailContent::introHtml('reservation_customer', $r) ?>
<?php include __DIR__ . '/_details.html.php'; ?>
<p>Status rezervácie môžete sledovať tu: <a href="<?= e($statusLink) ?>" style="color:#A8478A"><?= e($statusLink) ?></a></p>
<p style="font-size:0.9rem;color:#777">Zmena alebo zrušenie termínu je možné len telefonicky alebo e-mailom (kontakt nižšie), nie cez web.</p>
<?php include __DIR__ . '/_footer.html.php'; ?>
</body></html>
