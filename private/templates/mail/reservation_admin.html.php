<?php /** @var array $r */ ?>
<!doctype html>
<html lang="sk">
<body style="font-family:system-ui,sans-serif;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Nová rezervácia oslavy</h2>
<?= \Kuko\MailContent::introHtml('reservation_admin', $r) ?>
<?php include __DIR__ . '/_details.html.php'; ?>
<p style="margin-top:1.5rem">
  <a href="<?= e(rtrim((string) \Kuko\Config::get('app.url', 'https://kuko-detskysvet.sk'), '/')) ?>/admin/" style="background:#D88BBE;color:white;padding:0.75rem 1.5rem;border-radius:999px;text-decoration:none">Otvoriť admin</a>
</p>
<?php include __DIR__ . '/_footer.html.php'; ?>
</body></html>
