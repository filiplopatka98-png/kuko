<?php /** @var array $r */ /** @var string $statusLink */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Vaša rezervácia je potvrdená! 🎉</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<p>tešíme sa Vás oznámiť, že Vaša rezervácia balíčka <strong><?= e(strtoupper((string) $r['package'])) ?></strong> dňa <strong><?= e($r['wished_date']) ?></strong> o <strong><?= e(substr((string) $r['wished_time'], 0, 5)) ?></strong> pre <strong><?= (int) $r['kids_count'] ?></strong> detí je potvrdená.</p>
<p>Tešíme sa na Vás!</p>
<p>Status svojej rezervácie môžete kedykoľvek skontrolovať tu: <a href="<?= e($statusLink) ?>"><?= e($statusLink) ?></a></p>
<hr style="border:0;border-top:1px solid #eee;margin:1.5rem 0">
<p style="font-size:0.9rem;color:#777"><strong>Zmena alebo zrušenie termínu:</strong> volajte prosím <a href="tel:+421915319934">+421 915 319 934</a> alebo napíšte na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>. Cez web rezerváciu meniť nedá.</p>
<p><strong>KUKO detský svet</strong><br>Bratislavská 141, 921 01 Piešťany</p>
</body></html>
