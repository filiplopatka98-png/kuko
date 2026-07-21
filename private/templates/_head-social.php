<?php
/**
 * Shared Open Graph + Twitter Card + icon links, included by both head.php and
 * layout-minimal.php so link previews (Messenger/FB/Instagram/X) work on the
 * reservation page too — it is an indexable, shareable conversion URL.
 *
 * Expects in scope: $siteName, $titleFinal, $descriptionFinal, $ogImageUrl,
 * $canonicalUrl, $pageType.
 *
 * @var string $siteName
 * @var string $titleFinal
 * @var string $descriptionFinal
 * @var string $ogImageUrl
 * @var string $canonicalUrl
 * @var string|null $pageType
 */
?>
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
