<?php /** @var array $r */ ?>
<table style="border-collapse:collapse;width:100%;margin:1rem 0;font-size:0.95rem">
  <tr><td style="padding:6px 10px;vertical-align:top;color:#777;width:38%"><strong>Balíček</strong></td><td style="padding:6px 10px"><?= e(strtoupper((string) ($r['package'] ?? ''))) ?></td></tr>
  <tr style="background:#faf5fc"><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Dátum</strong></td><td style="padding:6px 10px"><?= e((string) ($r['wished_date'] ?? '')) ?></td></tr>
  <tr><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Čas</strong></td><td style="padding:6px 10px"><?= e(substr((string) ($r['wished_time'] ?? ''), 0, 5)) ?></td></tr>
  <tr style="background:#faf5fc"><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Počet detí</strong></td><td style="padding:6px 10px"><?= (int) ($r['kids_count'] ?? 0) ?></td></tr>
  <tr><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Meno</strong></td><td style="padding:6px 10px"><?= e((string) ($r['name'] ?? '')) ?></td></tr>
  <tr style="background:#faf5fc"><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Telefón</strong></td><td style="padding:6px 10px"><a href="tel:<?= e(preg_replace('/\s+/', '', (string) ($r['phone'] ?? ''))) ?>" style="color:#A8478A"><?= e((string) ($r['phone'] ?? '')) ?></a></td></tr>
  <tr><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>E-mail</strong></td><td style="padding:6px 10px"><a href="mailto:<?= e((string) ($r['email'] ?? '')) ?>" style="color:#A8478A"><?= e((string) ($r['email'] ?? '')) ?></a></td></tr>
  <tr style="background:#faf5fc"><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Poznámka</strong></td><td style="padding:6px 10px"><?= nl2br(e((string) ($r['note'] ?? '') !== '' ? (string) $r['note'] : '—')) ?></td></tr>
  <?php if (!empty($r['cancelled_reason'])): ?>
  <tr><td style="padding:6px 10px;vertical-align:top;color:#777"><strong>Dôvod zrušenia</strong></td><td style="padding:6px 10px"><?= e((string) $r['cancelled_reason']) ?></td></tr>
  <?php endif; ?>
</table>
