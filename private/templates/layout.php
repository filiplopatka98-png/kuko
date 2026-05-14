<?php /** @var string $content */ ?>
<!doctype html>
<html lang="sk">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body>
<?php require __DIR__ . '/nav.php'; ?>
<?= $content ?>
<?php require __DIR__ . '/reservation-modal.php'; ?>
<?php require __DIR__ . '/cookie-banner.php'; ?>
<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
