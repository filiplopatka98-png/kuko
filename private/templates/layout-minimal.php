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

$titleFinal = $title ?? 'KUKO detský svet';
$descriptionFinal = $description ?? '';

// DB-backed SEO overrides (/admin/seo editor). DB wins; passed-in values remain
// the fallback. The site must NOT break if the DB is unavailable.
$seo = \Kuko\Seo::resolve($pageType ?? null, $titleFinal, $descriptionFinal, $globalIndexing, $pageIndexing ?? null);
$titleFinal = $seo['title'];
$descriptionFinal = $seo['description'];
$robots = $seo['robots'];
?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titleFinal) ?></title>
<meta name="description" content="<?= e($descriptionFinal) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="#FBEEF5">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<link rel="alternate" hreflang="sk-SK" href="<?= e($canonicalUrl) ?>">
<?php if ($siteKey !== ''): ?>
<meta name="recaptcha-site-key" content="<?= e($siteKey) ?>">
<?php endif; ?>
<link rel="preload" href="/assets/fonts/NunitoSans.woff2" as="font" type="font/woff2" crossorigin>
<link rel="icon" href="/favicon.ico">
<style>
@font-face {
  font-family: "Nunito Sans";
  src: url("/assets/fonts/NunitoSans.woff2") format("woff2"), url("/assets/fonts/NunitoSans.ttf") format("truetype");
  font-weight: 100 900;
  font-display: swap;
}
</style>
<?php foreach (($stylesheets ?? []) as $href): ?>
<link rel="stylesheet" href="<?= e(\Kuko\Asset::url($href)) ?>">
<?php endforeach; ?>
</head>
<body>
<?= $content ?>
</body>
</html>
