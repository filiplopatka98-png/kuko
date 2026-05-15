<?php
/** @var array $r */
$title = 'Status rezervácie — KUKO detský svet';
$description = 'Prehľad vašej rezervácie v KUKO detský svet.';
$pageIndexing = false;

$statusLabel = match ((string) $r['status']) {
    'pending'   => ['Čaká na potvrdenie', '#856404', '#fff3cd'],
    'confirmed' => ['Potvrdená', '#155724', '#d4edda'],
    'cancelled' => ['Zrušená', '#721c24', '#f8d7da'],
    default     => ['Neznámy', '#555', '#ececef'],
};
ob_start();
?>
<div class="section">
  <div class="container" style="max-width: 720px;">
    <h1>Status rezervácie</h1>
    <div style="background: <?= e($statusLabel[2]) ?>; color: <?= e($statusLabel[1]) ?>; padding: 0.75rem 1.25rem; border-radius: 999px; display: inline-block; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 2rem;">
      <?= e($statusLabel[0]) ?>
    </div>

    <table style="width:100%; border-collapse:collapse;">
      <tr><td style="padding:0.5rem 0; color:#7A7A7A">Balíček</td><td style="padding:0.5rem 0;"><strong><?= e(strtoupper((string) $r['package'])) ?></strong></td></tr>
      <tr><td style="padding:0.5rem 0; color:#7A7A7A">Dátum</td><td style="padding:0.5rem 0;"><strong><?= e($r['wished_date']) ?></strong></td></tr>
      <tr><td style="padding:0.5rem 0; color:#7A7A7A">Čas</td><td style="padding:0.5rem 0;"><strong><?= e(substr((string) $r['wished_time'], 0, 5)) ?></strong></td></tr>
      <tr><td style="padding:0.5rem 0; color:#7A7A7A">Počet detí</td><td style="padding:0.5rem 0;"><?= (int) $r['kids_count'] ?></td></tr>
      <tr><td style="padding:0.5rem 0; color:#7A7A7A">Meno</td><td style="padding:0.5rem 0;"><?= e($r['name']) ?></td></tr>
      <?php if ($r['status'] === 'cancelled' && !empty($r['cancelled_reason'])): ?>
        <tr><td style="padding:0.5rem 0; color:#7A7A7A">Dôvod zrušenia</td><td style="padding:0.5rem 0;"><em><?= e($r['cancelled_reason']) ?></em></td></tr>
      <?php endif; ?>
    </table>

    <div style="margin-top:2rem; padding:1.25rem; background:var(--bg-pink-soft); border-radius: var(--r-card);">
      <p style="margin:0 0 0.5rem"><strong>Zmena alebo zrušenie?</strong></p>
      <p style="margin:0; color:var(--c-text-soft);">Cez web rezerváciu meniť nedá. Zavolajte prosím na <a href="tel:+421915319934">+421 915 319 934</a> alebo napíšte na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>.</p>
    </div>

    <p style="margin-top:2rem;"><a href="/">&larr; Späť na domov</a></p>
  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
