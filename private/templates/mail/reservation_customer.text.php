<?php /** @var array $r */ /** @var string $statusLink */ ?>
Dobrý deň <?= $r['name'] ?>,


prijali sme vašu požiadavku na rezerváciu balíčka <?= strtoupper((string) $r['package']) ?> dňa <?= $r['wished_date'] ?> o <?= substr((string) $r['wished_time'], 0, 5) ?> pre <?= (int) $r['kids_count'] ?> detí.

Status rezervácie: <?= $statusLink ?>


Ozveme sa vám do 24 hodín.

Zmena alebo zrušenie termínu cez telefón: +421 915 319 934 alebo info@kuko-detskysvet.sk.

KUKO detský svet
Bratislavská 141, 921 01 Piešťany
