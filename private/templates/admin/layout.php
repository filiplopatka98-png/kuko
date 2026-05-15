<?php /** @var string $content */ /** @var string $title */ /** @var string $user */ ?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KUKO admin') ?></title>
<link rel="stylesheet" href="<?= e(\Kuko\Asset::url('/assets/css/admin.css')) ?>">
</head>
<body>
<header class="admin-header">
  <div class="admin-header__inner">
    <h1>KUKO admin</h1>
    <nav>
      <a href="/admin">Rezervácie</a>
      <a href="/admin/calendar">Kalendár</a>
      <a href="/admin/opening-hours">Hodiny</a>
      <a href="/admin/blocked-periods">Blokácie</a>
      <a href="/admin/packages">Balíčky</a>
      <a href="/admin/content">Obsah</a>
      <a href="/admin/gallery">Galéria</a>
      <a href="/admin/contact">Kontakt</a>
      <a href="/admin/seo">SEO</a>
      <a href="/admin/maintenance">Maintenance</a>
      <a href="/admin/log">Log</a>
      <a href="/admin/gdpr">GDPR</a>
      <a href="/admin/settings">Nastavenia</a>
      <a href="/admin/calendar.ics" title="iCal export pre Google/Apple Calendar">📅 iCal</a>
      <a href="/" target="_blank">Web ↗</a>
      <span class="admin-user">@<?= e($user ?? '') ?></span>
      <a href="/admin/logout" class="admin-logout">Odhlásiť</a>
    </nav>
  </div>
</header>
<?php foreach (($flashes ?? []) as $f): ?>
  <div class="admin-flash admin-flash--<?= e($f['type'] ?? 'ok') ?>"><?= e($f['msg']) ?></div>
<?php endforeach; ?>
<main class="admin-main"><?= $content ?></main>
</body>
</html>
