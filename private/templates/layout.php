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
<script>window.__kukoAssets={gallery:<?= json_encode(\Kuko\Asset::url('/assets/js/gallery.js'), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>,map:<?= json_encode(\Kuko\Asset::url('/assets/js/map.js'), JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) ?>};</script>
<script type="module" src="<?= e(\Kuko\Asset::url('/assets/js/main.js')) ?>"></script>
</body>
</html>
