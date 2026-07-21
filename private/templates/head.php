<?php
/** @var string|null $title */
/** @var string|null $description */
/** @var string|null $canonical */         // path relative to app.url, e.g. '/rezervacia'
/** @var string|null $ogImage */
/** @var bool|null $pageIndexing */         // override; null = global app.public_indexing
/** @var string|null $pageType */           // 'home' | 'rezervacia' | 'gallery' | 'faq' | 'privacy' | 'cookies' | 'status'

$siteName = 'KUKO detský svet';
$titleFinal = $title ?? 'KUKO detský svet — herňa a kaviareň v Piešťanoch';
$descriptionFinal = $description ?? 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, káva pre rodičov, oslavy na mieru. Pondelok – Nedeľa 9:00 – 20:00.';
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
$baseUrl = rtrim((string) \Kuko\Config::get('app.url', 'https://kukodetskysvet.sk'), '/');
$canonicalUrl = $baseUrl . ($canonical ?? '/');
$ogImageUrl = $ogImage ?? ($baseUrl . '/assets/img/og-cover.jpg');

// Indexing: pre-launch noindex,nofollow on everything. Per-page override possible.
$globalIndexing = (bool) \Kuko\Config::get('app.public_indexing', false);

// DB-backed SEO overrides (/admin/seo editor). DB wins; hardcoded/passed-in
// values remain the fallback. The site must NOT break if the DB is unavailable.
$seo = \Kuko\Seo::resolve($pageType ?? null, $titleFinal, $descriptionFinal, $globalIndexing, $pageIndexing ?? null);
$titleFinal = $seo['title'];
$descriptionFinal = $seo['description'];
$robots = $seo['robots'];
// Per-page OG image (admin → Stránky → SEO) wins; then a page-passed
// $ogImage; then the site-wide default. Relative paths get the base URL.
if (!empty($seo['image'])) {
    $ogImageUrl = preg_match('~^https?://~', $seo['image'])
        ? $seo['image']
        : $baseUrl . '/' . ltrim($seo['image'], '/');
}
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($titleFinal) ?></title>
<meta name="description" content="<?= e($descriptionFinal) ?>">
<meta name="robots" content="<?= e($robots) ?>">
<meta name="theme-color" content="#FBEEF5">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<link rel="canonical" href="<?= e($canonicalUrl) ?>">
<link rel="alternate" hreflang="sk-SK" href="<?= e($canonicalUrl) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e($canonicalUrl) ?>">
<?php if ($siteKey !== ''): ?>
<meta name="recaptcha-site-key" content="<?= e($siteKey) ?>">
<?php endif; ?>

<?php require __DIR__ . '/_head-social.php'; ?>

<!-- Performance hints -->
<?php if (($pageType ?? '') === 'home'): // Leaflet map lives only in the homepage kontakt section ?>
<link rel="preconnect" href="https://unpkg.com" crossorigin>
<link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
<?php endif; ?>
<link rel="preload" href="/assets/fonts/NunitoSans.woff2" as="font" type="font/woff2" crossorigin>
<?php if (($pageType ?? '') === 'home'): ?>
<link rel="preload" as="image" href="<?= e($baseUrl) ?>/assets/img/hero-768.webp" type="image/webp" media="(max-width: 768px)" fetchpriority="high">
<link rel="preload" as="image" href="<?= e($baseUrl) ?>/assets/img/hero.webp" type="image/webp" media="(min-width: 769px)" fetchpriority="high">
<?php endif; ?>

<!-- Stylesheets -->
<link rel="stylesheet" href="<?= e(\Kuko\Asset::url('/assets/css/main.css')) ?>">
<?php if (($pageType ?? '') === 'home'): // Leaflet CSS only where the map renders ?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<?php endif; ?>

<!-- Schema.org -->
<?php require __DIR__ . '/_head-schema.php'; ?>

<?php if (($pageType ?? '') === 'faq'): ?>
<?php
// FAQPage JSON-LD — auto-generated from the same structured `faq.items`
// source as the visible accordion (Faq helper). Always valid: on any DB
// fault Faq::items() degrades to the 6 defaults, so the schema is always
// present and in sync with the page.
try {
    $faqSchemaItems = \Kuko\Faq::items(new \Kuko\SettingsRepo(\Kuko\Db::fromConfig()));
} catch (\Throwable $e) {
    error_log('[head.php] FAQ schema settings unavailable, using defaults: ' . $e->getMessage());
    $faqSchemaItems = \Kuko\Faq::defaults();
}
?>
<script type="application/ld+json" nonce="<?= e(\Kuko\Csp::nonce()) ?>">
<?= \Kuko\Faq::schemaJson($faqSchemaItems) ?>
</script>
<?php endif; ?>
