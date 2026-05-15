<?php
$gallery  = $gallery  ?? [];
$packages = $packages ?? [];
$title       = 'KUKO detský svet — herňa a kaviareň v Piešťanoch';
$description = 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, kvalitná káva pre rodičov, oslavy na mieru. Otvorené Pon–Ne 9:00 – 20:00.';
$canonical   = '/';
$pageType    = 'home';
ob_start();
?>
  <?php require __DIR__ . '/../sections/hero.php'; ?>
  <?php require __DIR__ . '/../sections/o-nas.php'; ?>
  <?php require __DIR__ . '/../sections/cennik.php'; ?>
  <?php require __DIR__ . '/../sections/oslavy.php'; ?>
  <?php require __DIR__ . '/../sections/galeria.php'; ?>
  <?php require __DIR__ . '/../sections/kontakt.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
