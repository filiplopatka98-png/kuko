<?php
/** @var string|null $user */
$title = 'Nenájdené — KUKO admin';
ob_start();
?>
<h2>404 — Nenájdené</h2>
<p><a href="/admin">&larr; Späť na zoznam</a></p>
<?php
$content = ob_get_clean();
$user = $user ?? '';
require __DIR__ . '/layout.php';
