<?php /** @var string $content */ ?>
<!doctype html>
<html lang="sk">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body>
<a class="skip-link" href="#main">Preskočiť na obsah</a>
<?php require __DIR__ . '/nav.php'; ?>
<main id="main" tabindex="-1"><?= $content ?></main>
<?php require __DIR__ . '/footer.php'; ?>
<?php require __DIR__ . '/cookie-banner.php'; ?>
<script type="module" src="<?= e(\Kuko\Asset::url('/assets/js/main.js')) ?>"></script>
</body>
</html>
