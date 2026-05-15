<?php
/** @var string|null $title */
/** @var string|null $description */
/** @var string|null $canonical */         // path relative to app.url, e.g. '/rezervacia'
/** @var string|null $ogImage */
/** @var bool|null $pageIndexing */         // override; null = global app.public_indexing
/** @var string|null $pageType */           // 'home' | 'reservation' | 'privacy' | 'status' | 'maintenance'

$siteName = 'KUKO detský svet';
$titleFinal = $title ?? 'KUKO detský svet — herňa a kaviareň v Piešťanoch';
$descriptionFinal = $description ?? 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, káva pre rodičov, oslavy na mieru. Pondelok – Nedeľa 9:00 – 20:00.';
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
$baseUrl = rtrim((string) \Kuko\Config::get('app.url', 'https://kuko-detskysvet.sk'), '/');
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

<!-- Open Graph -->
<meta property="og:type" content="<?= e(($pageType ?? '') === 'home' ? 'website' : 'article') ?>">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($titleFinal) ?>">
<meta property="og:description" content="<?= e($descriptionFinal) ?>">
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:url" content="<?= e($canonicalUrl) ?>">
<meta property="og:locale" content="sk_SK">

<!-- Twitter Card -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?= e($titleFinal) ?>">
<meta name="twitter:description" content="<?= e($descriptionFinal) ?>">
<meta name="twitter:image" content="<?= e($ogImageUrl) ?>">

<!-- Icons -->
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">

<!-- Performance hints -->
<link rel="preconnect" href="https://unpkg.com" crossorigin>
<link rel="preconnect" href="https://tile.openstreetmap.org" crossorigin>
<link rel="preload" href="/assets/fonts/NunitoSans.woff2" as="font" type="font/woff2" crossorigin>
<?php if (($pageType ?? '') === 'home'): ?>
<link rel="preload" as="image" href="<?= e($baseUrl) ?>/assets/img/hero-768.webp" type="image/webp" media="(max-width: 768px)" fetchpriority="high">
<link rel="preload" as="image" href="<?= e($baseUrl) ?>/assets/img/hero.webp" type="image/webp" media="(min-width: 769px)" fetchpriority="high">
<?php endif; ?>

<!-- Stylesheets -->
<link rel="stylesheet" href="<?= e(\Kuko\Asset::url('/assets/css/main.css')) ?>">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">

<!-- Schema.org -->
<?php $schemaPriceRange = '5 € – 350 €'; ?>
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": ["ChildCare", "LocalBusiness"],
  "@id": "<?= e($baseUrl) ?>/#business",
  "name": "<?= e($siteName) ?>",
  "image": [
    "<?= e($baseUrl) ?>/assets/img/hero.jpg",
    "<?= e($baseUrl) ?>/assets/img/cennik.jpg",
    "<?= e($baseUrl) ?>/assets/img/galeria_1.jpg"
  ],
  "logo": "<?= e($baseUrl) ?>/assets/img/logo.png",
  "url": "<?= e($baseUrl) ?>/",
  "telephone": "+421915319934",
  "email": "info@kuko-detskysvet.sk",
  "priceRange": "<?= e($schemaPriceRange) ?>",
  "currenciesAccepted": "EUR",
  "paymentAccepted": "Cash, Credit Card",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Bratislavská 141",
    "postalCode": "921 01",
    "addressLocality": "Piešťany",
    "addressRegion": "Trnavský kraj",
    "addressCountry": "SK"
  },
  "geo": { "@type": "GeoCoordinates", "latitude": 48.58128, "longitude": 17.81575 },
  "hasMap": "https://www.google.com/maps/?q=48.58128,17.81575",
  "openingHoursSpecification": [{
    "@type": "OpeningHoursSpecification",
    "dayOfWeek": ["Monday","Tuesday","Wednesday","Thursday","Friday","Saturday","Sunday"],
    "opens": "09:00", "closes": "20:00"
  }],
  "sameAs": [
    <?php
    $social = array_filter([\Kuko\Social::url('facebook', ''), \Kuko\Social::url('instagram', '')]);
    echo implode(",\n    ", array_map(fn($u) => '"' . e($u) . '"', $social));
    ?>
  ]
}
</script>

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
<script type="application/ld+json">
<?= \Kuko\Faq::schemaJson($faqSchemaItems) ?>
</script>
<?php endif; ?>
