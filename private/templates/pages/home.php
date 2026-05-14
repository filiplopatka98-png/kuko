<?php
ob_start();
?>
<main>
  <?php require __DIR__ . '/../sections/hero.php'; ?>
  <?php require __DIR__ . '/../sections/o-nas.php'; ?>
  <?php require __DIR__ . '/../sections/cennik.php'; ?>
  <?php require __DIR__ . '/../sections/oslavy.php'; ?>
  <?php require __DIR__ . '/../sections/galeria.php'; ?>
  <?php require __DIR__ . '/../sections/kontakt.php'; ?>
</main>
<?php require __DIR__ . '/../footer.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
