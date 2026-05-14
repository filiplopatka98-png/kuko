<?php /** @var array $r */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Nová rezervácia oslavy</h2>
<p>Prišla nová požiadavka na balíček <strong><?= e(strtoupper((string) $r['package'])) ?></strong>.</p>
<table style="border-collapse:collapse;width:100%">
  <tr><td style="padding:4px 8px;vertical-align:top"><strong>Termín:</strong></td><td style="padding:4px 8px"><?= e($r['wished_date']) ?> o <?= e(substr((string) $r['wished_time'], 0, 5)) ?></td></tr>
  <tr><td style="padding:4px 8px;vertical-align:top"><strong>Počet detí:</strong></td><td style="padding:4px 8px"><?= (int) $r['kids_count'] ?></td></tr>
  <tr><td style="padding:4px 8px;vertical-align:top"><strong>Meno:</strong></td><td style="padding:4px 8px"><?= e($r['name']) ?></td></tr>
  <tr><td style="padding:4px 8px;vertical-align:top"><strong>Telefón:</strong></td><td style="padding:4px 8px"><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td></tr>
  <tr><td style="padding:4px 8px;vertical-align:top"><strong>E-mail:</strong></td><td style="padding:4px 8px"><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td></tr>
  <tr><td style="padding:4px 8px;vertical-align:top"><strong>Poznámka:</strong></td><td style="padding:4px 8px"><?= nl2br(e($r['note'] ?? '—')) ?></td></tr>
</table>
<p style="margin-top:2rem">
  <a href="https://kuko-detskysvet.sk/admin/" style="background:#D88BBE;color:white;padding:0.75rem 1.5rem;border-radius:999px;text-decoration:none">Otvoriť admin</a>
</p>
</body></html>
