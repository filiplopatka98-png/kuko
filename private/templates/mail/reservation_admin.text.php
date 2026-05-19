<?php /** @var array $r */ ?>
Nová rezervácia oslavy

<?= \Kuko\MailContent::introText('reservation_admin', $r) ?>


Termín: <?= $r['wished_date'] ?> o <?= substr((string) $r['wished_time'], 0, 5) ?>

Počet detí: <?= (int) $r['kids_count'] ?>

Meno: <?= $r['name'] ?>

Telefón: <?= $r['phone'] ?>

E-mail: <?= $r['email'] ?>

Poznámka: <?= $r['note'] ?? '—' ?>


Admin: https://kuko-detskysvet.sk/admin/
