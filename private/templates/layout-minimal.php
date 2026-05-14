<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $description */
/** @var array<int,string>|null $stylesheets */
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KUKO detský svet') ?></title>
<meta name="description" content="<?= e($description ?? '') ?>">
<meta name="robots" content="noindex, nofollow">
<meta name="theme-color" content="#FBEEF5">
<?php if ($siteKey !== ''): ?>
<meta name="recaptcha-site-key" content="<?= e($siteKey) ?>">
<?php endif; ?>
<link rel="preload" href="/assets/fonts/NunitoSans.ttf" as="font" type="font/ttf" crossorigin>
<link rel="icon" href="/favicon.ico">
<style>
@font-face {
  font-family: "Nunito Sans";
  src: url("/assets/fonts/NunitoSans.ttf") format("truetype-variations"), url("/assets/fonts/NunitoSans.ttf") format("truetype");
  font-weight: 100 900;
  font-display: swap;
}
</style>
<?php foreach (($stylesheets ?? []) as $href): ?>
<link rel="stylesheet" href="<?= e($href) ?>">
<?php endforeach; ?>
</head>
<body>
<?= $content ?>
</body>
</html>
