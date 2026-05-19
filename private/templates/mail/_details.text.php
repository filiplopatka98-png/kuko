<?php /** @var array $r */ ?>
— Detaily rezervácie —
Balíček: <?= strtoupper((string) ($r['package'] ?? '')) ?>

Dátum: <?= (string) ($r['wished_date'] ?? '') ?>

Čas: <?= substr((string) ($r['wished_time'] ?? ''), 0, 5) ?>

Počet detí: <?= (int) ($r['kids_count'] ?? 0) ?>

Meno: <?= (string) ($r['name'] ?? '') ?>

Telefón: <?= (string) ($r['phone'] ?? '') ?>

E-mail: <?= (string) ($r['email'] ?? '') ?>

Poznámka: <?= (string) ($r['note'] ?? '') !== '' ? (string) $r['note'] : '—' ?>
<?php if (!empty($r['cancelled_reason'])): ?>

Dôvod zrušenia: <?= (string) $r['cancelled_reason'] ?>
<?php endif; ?>
