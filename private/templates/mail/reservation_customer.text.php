<?php /** @var array $r */ ?>
Dobrý deň <?= $r['name'] ?>,


prijali sme vašu požiadavku na rezerváciu balíčka <?= strtoupper((string) $r['package']) ?> dňa <?= $r['wished_date'] ?> o <?= substr((string) $r['wished_time'], 0, 5) ?> pre <?= (int) $r['kids_count'] ?> detí.

Ozveme sa vám do 24 hodín.

KUKO detský svet
info@kuko-detskysvet.sk | +421 915 319 934
