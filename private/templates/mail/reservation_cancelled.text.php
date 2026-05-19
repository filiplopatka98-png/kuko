<?php /** @var array $r */ ?>
Dobrý deň <?= $r['name'] ?>,


<?= \Kuko\MailContent::introText('reservation_cancelled', $r) ?>
<?php if (!empty($r['cancelled_reason'])): ?>

Dôvod: <?= $r['cancelled_reason'] ?>
<?php endif; ?>

Ak chcete dohodnúť iný termín, zavolajte na +421 915 319 934 alebo napíšte na info@kuko-detskysvet.sk.

Ďakujeme za pochopenie,
Tím KUKO detský svet
