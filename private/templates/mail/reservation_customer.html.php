<?php /** @var array $r */ /** @var string $statusLink */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Ďakujeme za vašu rezerváciu! 🎉</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<?= \Kuko\MailContent::introHtml('reservation_customer', $r) ?>
<p>Status rezervácie môžete sledovať tu: <a href="<?= e($statusLink) ?>"><?= e($statusLink) ?></a></p>
<hr style="border:0;border-top:1px solid #eee;margin:1.5rem 0">
<p style="font-size:0.9rem;color:#777"><strong>Zmena alebo zrušenie termínu:</strong> volajte prosím <a href="tel:+421915319934">+421 915 319 934</a> alebo napíšte na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>. Cez web rezerváciu meniť nedá.</p>
<p>Tešíme sa na vás!<br><strong>Tím KUKO detský svet</strong></p>
</body></html>
