<?php /** @var array $r */ ?>
Dobrý deň <?= $r['name'] ?>,


<?= \Kuko\MailContent::introText('reservation_cancelled', $r) ?>

<?php include __DIR__ . '/_details.text.php'; ?>

Ak chcete dohodnúť iný termín, ozvite sa nám — radi vám pomôžeme. Kontakt nájdete nižšie.
<?php include __DIR__ . '/_footer.text.php'; ?>
