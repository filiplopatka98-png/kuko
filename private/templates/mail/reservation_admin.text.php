<?php /** @var array $r */ ?>
Nová rezervácia oslavy

<?= \Kuko\MailContent::introText('reservation_admin', $r) ?>

<?php include __DIR__ . '/_details.text.php'; ?>

Admin: <?= rtrim((string) \Kuko\Config::get('app.url', 'https://kukodetskysvet.sk'), '/') ?>/admin/
<?php include __DIR__ . '/_footer.text.php'; ?>
