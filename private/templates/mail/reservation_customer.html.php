<?php /** @var array $r */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Ďakujeme za vašu rezerváciu! 🎉</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<p>prijali sme vašu požiadavku na rezerváciu balíčka <strong><?= e(strtoupper((string) $r['package'])) ?></strong> dňa <strong><?= e($r['wished_date']) ?></strong> o <strong><?= e(substr((string) $r['wished_time'], 0, 5)) ?></strong> pre <strong><?= (int) $r['kids_count'] ?></strong> detí.</p>
<p>Ozveme sa vám do 24 hodín na telefón alebo e-mail uvedený v rezervácii.</p>
<p>Ak by ste medzitým potrebovali niečo doplniť, napíšte nám na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a> alebo zavolajte <a href="tel:+421915319934">+421 915 319 934</a>.</p>
<p>Tešíme sa na vás!<br><strong>Tím KUKO detský svet</strong></p>
</body></html>
