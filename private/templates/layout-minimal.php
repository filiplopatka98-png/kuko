<?php
/** @var string $content */
/** @var string|null $title */
/** @var string|null $description */
/** @var string|null $canonical */
/** @var bool|null   $pageIndexing */
/** @var array<int,string>|null $stylesheets */
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
$baseUrl = rtrim((string) \Kuko\Config::get('app.url', ''), '/');
$canonicalUrl = $baseUrl . ($canonical ?? '/');
$globalIndexing = (bool) \Kuko\Config::get('app.public_indexing', false);
$index = $pageIndexing ?? $globalIndexing;
$robots = $index ? 'index, follow' : 'noindex, nofollow';
?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KUKO detský svet') ?></title>
<meta name="description" content="<?= e($description ?? '') ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="#FBEEF5">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<link rel="alternate" hreflang="sk-SK" href="<?= e($canonicalUrl) ?>">
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
