<?php /** @var array $r */ /** @var string $statusLink */ ?>
Dobrý deň <?= $r['name'] ?>,


<?= \Kuko\MailContent::introText('reservation_confirmed', $r) ?>

<?php include __DIR__ . '/_details.text.php'; ?>

Status rezervácie: <?= $statusLink ?>


Zmena alebo zrušenie termínu je možné len telefonicky alebo e-mailom (kontakt nižšie), nie cez web.
<?php include __DIR__ . '/_footer.text.php'; ?>
