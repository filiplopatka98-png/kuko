<?php
$title = 'Stránka nenájdená — KUKO detský svet';
$pageIndexing = false;
ob_start();
?>
<div class="section" style="text-align:center;">
  <div class="container">
    <h1>404</h1>
    <p>Stránka, ktorú hľadáte, neexistuje alebo bola presunutá.</p>
    <p><a class="btn" href="/">&larr; Späť na domov</a></p>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
