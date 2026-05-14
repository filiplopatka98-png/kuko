<?php /** @var string $content */ /** @var string $title */ /** @var string $user */ ?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KUKO admin') ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<header class="admin-header">
  <div class="admin-header__inner">
    <h1>KUKO admin</h1>
    <nav>
      <a href="/admin">Rezervácie</a>
      <a href="/" target="_blank">Web ↗</a>
      <span class="admin-user">@<?= e($user ?? '') ?></span>
    </nav>
  </div>
</header>
<main class="admin-main"><?= $content ?></main>
</body>
</html>
