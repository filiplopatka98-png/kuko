<?php
/**
 * Shared LocalBusiness JSON-LD, included by both head.php (full layout) and
 * layout-minimal.php (reservation page) so the business schema has a single
 * source of truth — no drift between the two <head>s.
 *
 * Expects in scope: string $baseUrl (no trailing slash), string $siteName.
 *
 * @var string $baseUrl
 * @var string $siteName
 */
$baseUrl  = $baseUrl  ?? rtrim((string) \Kuko\Config::get('app.url', 'https://kukodetskysvet.sk'), '/');
$siteName = $siteName ?? 'KUKO detský svet';

// Offer catalog — built from the same PackagesRepo the site renders from, so
// admin price edits propagate here too. Prices are parsed leniently from the
// free-text price_text ("120 – 150 € / balíček"); a package whose price does
// not parse is simply omitted. Degrades to no catalog if the DB is unavailable.
$offers = [];
try {
    $pkgs = (new \Kuko\PackagesRepo(\Kuko\Db::fromConfig()))->listActive();
    foreach ($pkgs as $p) {
        if (!preg_match('/(\d+)(?:\s*[–-]\s*(\d+))?/u', (string) ($p['price_text'] ?? ''), $m)) {
            continue;
        }
        $min = (float) $m[1];
        $max = isset($m[2]) && $m[2] !== '' ? (float) $m[2] : $min;
        $offers[] = [
            'name'  => (string) $p['name'],
            'min'   => $min,
            'max'   => $max,
        ];
    }
} catch (\Throwable $e) {
    error_log('[_head-schema] packages unavailable for Offer catalog: ' . $e->getMessage());
}
?>
<script type="application/ld+json" nonce="<?= e(\Kuko\Csp::nonce()) ?>">
{
  "@context": "https://schema.org",
  "@type": ["ChildCare", "CafeOrCoffeeShop", "LocalBusiness"],
  "@id": "<?= e($baseUrl) ?>/#business",
  "name": "<?= e($siteName) ?>",
  "image": [
    "<?= e($baseUrl) ?>/assets/img/hero.webp",
    "<?= e($baseUrl) ?>/assets/img/cennik.webp",
    "<?= e($baseUrl) ?>/assets/img/galeria_1.webp"
  ],
  "logo": "<?= e($baseUrl) ?>/assets/img/logo.png",
  "url": "<?= e($baseUrl) ?>/",
  "telephone": "+421915319934",
  "email": "info@kukodetskysvet.sk",
  "priceRange": "€€",
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
<?php if ($offers !== []): ?>
  "hasOfferCatalog": {
    "@type": "OfferCatalog",
    "name": "Balíčky detských osláv",
    "itemListElement": [
      <?php
      echo implode(",\n      ", array_map(function (array $o) {
          $svc = json_encode($o['name'], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG);
          $spec = $o['min'] === $o['max']
              ? '"price": "' . $o['min'] . '", "priceCurrency": "EUR"'
              : '"minPrice": "' . $o['min'] . '", "maxPrice": "' . $o['max'] . '", "priceCurrency": "EUR"';
          return '{ "@type": "Offer", "itemOffered": { "@type": "Service", "name": ' . $svc . ' }, '
               . '"priceSpecification": { "@type": "PriceSpecification", ' . $spec . ' } }';
      }, $offers));
      ?>

    ]
  },
<?php endif; ?>
  "sameAs": [
    <?php
    $social = array_filter([\Kuko\Social::url('facebook', ''), \Kuko\Social::url('instagram', '')]);
    echo implode(",\n    ", array_map(fn($u) => '"' . e($u) . '"', $social));
    ?>
  ]
}
</script>
