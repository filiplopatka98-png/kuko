<?php
/** @var string|null $title */
/** @var string|null $description */
$title = $title ?? 'KUKO detský svet — herňa a kaviareň v Piešťanoch';
$description = $description ?? 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, káva pre rodičov, oslavy na mieru. Pondelok – Nedeľa 9:00 – 20:00.';
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#FBEEF5">
<?php if ($siteKey !== ''): ?>
<meta name="recaptcha-site-key" content="<?= e($siteKey) ?>">
<?php endif; ?>
<link rel="canonical" href="https://kuko-detskysvet.sk/">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="https://kuko-detskysvet.sk/assets/img/hero.jpg">
<meta property="og:url" content="https://kuko-detskysvet.sk/">
<meta property="og:locale" content="sk_SK">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.ico">
<link rel="preload" href="/assets/fonts/NunitoSans.ttf" as="font" type="font/ttf" crossorigin>
<link rel="stylesheet" href="/assets/css/main.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ChildCare",
  "name": "KUKO detský svet",
  "image": "https://kuko-detskysvet.sk/assets/img/hero.jpg",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Bratislavská 141",
    "postalCode": "921 01",
    "addressLocality": "Piešťany",
    "addressCountry": "SK"
  },
  "telephone": "+421915319934",
  "email": "info@kuko-detskysvet.sk",
  "openingHours": "Mo-Su 09:00-20:00",
  "geo": { "@type": "GeoCoordinates", "latitude": 48.5916, "longitude": 17.8364 },
  "priceRange": "€€"
}
</script>
