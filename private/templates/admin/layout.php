<?php
/** @var string $content */
/** @var string $title */
/** @var string $user */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$isResvGroup = (
    $path === '/admin'
    || str_starts_with($path, '/admin/reservation')
    || str_starts_with($path, '/admin/calendar')
    || str_starts_with($path, '/admin/blocked-periods')
    || str_starts_with($path, '/admin/opening-hours')
);
/**
 * Active-state helper.
 * '/admin' is matched exactly (the dashboard / reservations list);
 * every other admin destination is matched by str_starts_with prefix.
 */
$active = static function (string $href) use ($path): bool {
    if ($href === '/admin') {
        return $path === '/admin';
    }
    return str_starts_with($path, $href);
};
$aria = static function (bool $on): string {
    return $on ? ' is-active" aria-current="page' : '';
};
?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KUKO admin') ?></title>
<link rel="stylesheet" href="<?= e(\Kuko\Asset::url('/assets/css/admin.css')) ?>">
</head>
<body class="admin-body">
<a class="skip-link" href="#main">Preskočiť na obsah</a>
<input type="checkbox" id="admin-nav-toggle" class="admin-nav-toggle sr-only">
<label for="admin-nav-toggle" class="admin-hamburger" aria-label="Prepnúť navigáciu">
  <span></span><span></span><span></span>
</label>
<aside class="admin-sidebar">
  <div class="admin-sidebar__brand">
    <img src="<?= e(\Kuko\Asset::url('/assets/img/logo.png')) ?>" alt="" width="36" height="36" class="admin-sidebar__logo">
    <span>KUKO admin</span>
  </div>
  <nav class="admin-sidebar__nav" aria-label="Admin">
    <a href="/admin" class="admin-nav-item<?= $aria($isResvGroup) ?>">Rezervácie</a>

    <div class="admin-nav-group">
      <span class="admin-nav-label">STRÁNKY</span>
      <a href="/admin/content" class="admin-nav-item<?= $aria($active('/admin/content')) ?>">Obsah</a>
      <a href="/admin/packages" class="admin-nav-item<?= $aria($active('/admin/packages')) ?>">Balíčky</a>
      <a href="/admin/gallery" class="admin-nav-item<?= $aria($active('/admin/gallery')) ?>">Galéria</a>
      <a href="/admin/contact" class="admin-nav-item<?= $aria($active('/admin/contact')) ?>">Kontakt</a>
    </div>

    <div class="admin-nav-group">
      <span class="admin-nav-label">NASTAVENIA</span>
      <a href="/admin/seo" class="admin-nav-item<?= $aria($active('/admin/seo')) ?>">SEO</a>
      <a href="/admin/maintenance" class="admin-nav-item<?= $aria($active('/admin/maintenance')) ?>">Maintenance</a>
      <a href="/admin/log" class="admin-nav-item<?= $aria($active('/admin/log')) ?>">Logy</a>
      <a href="/admin/gdpr" class="admin-nav-item<?= $aria($active('/admin/gdpr')) ?>">GDPR</a>
      <a href="/admin/settings" class="admin-nav-item<?= $aria($active('/admin/settings')) ?>">Všeobecné</a>
    </div>
  </nav>
  <div class="admin-sidebar__footer">
    <a href="/admin/calendar.ics" title="iCal export pre Google/Apple Calendar">iCal export</a>
    <a href="/" target="_blank" rel="noopener">Web ↗</a>
    <span class="admin-user">@<?= e($user ?? '') ?></span>
    <a href="/admin/logout" class="admin-logout">Odhlásiť</a>
  </div>
</aside>
<div class="admin-content">
<?php foreach (($flashes ?? []) as $f): ?>
  <div class="admin-flash admin-flash--<?= e($f['type'] ?? 'ok') ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<?php if ($isResvGroup): ?>
  <nav class="admin-tabs" aria-label="Rezervácie">
    <a href="/admin" class="admin-tab<?= $aria($path === '/admin' || str_starts_with($path, '/admin/reservation')) ?>">Zoznam</a>
    <a href="/admin/calendar" class="admin-tab<?= $aria($active('/admin/calendar')) ?>">Kalendár</a>
    <a href="/admin/blocked-periods" class="admin-tab<?= $aria($active('/admin/blocked-periods')) ?>">Blokácie</a>
    <a href="/admin/opening-hours" class="admin-tab<?= $aria($active('/admin/opening-hours')) ?>">Otváracie hodiny</a>
  </nav>
<?php endif; ?>
<main class="admin-main" id="main" tabindex="-1"><?= $content ?></main>
</div>
</body>
</html>
