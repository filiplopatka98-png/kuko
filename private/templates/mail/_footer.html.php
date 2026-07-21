<?php
$mailBase = rtrim((string) \Kuko\Config::get('app.url', 'https://kukodetskysvet.sk'), '/');
$cAddress = \Kuko\Content::get('kontakt.address', 'Bratislavská 141, 921 01 Piešťany');
$cPhone   = \Kuko\Content::get('kontakt.phone', '+421 915 319 934');
$cEmail   = \Kuko\Content::get('kontakt.email', 'info@kukodetskysvet.sk');
$cHours   = \Kuko\Content::get('kontakt.hours', 'Pondelok – Nedeľa: 9:00 – 20:00');
$cFb      = \Kuko\Social::url('facebook', '');
$cIg      = \Kuko\Social::url('instagram', '');
?>
<hr style="border:0;border-top:1px solid #eee;margin:2rem 0 1.25rem">
<table style="border-collapse:collapse;width:100%;font-size:0.85rem;color:#777">
  <tr>
    <td style="padding:0 12px 0 0;vertical-align:top;width:84px">
      <img src="<?= e($mailBase) ?>/assets/img/logo.png" alt="KUKO detský svet" width="72" style="display:block;width:72px;height:auto">
    </td>
    <td style="vertical-align:top;line-height:1.6">
      <strong style="color:#A8478A">KUKO detský svet</strong><br>
      <?= e($cAddress) ?><br>
      Tel.: <a href="tel:<?= e(preg_replace('/\s+/', '', $cPhone)) ?>" style="color:#777"><?= e($cPhone) ?></a>
      &nbsp;·&nbsp; <a href="mailto:<?= e($cEmail) ?>" style="color:#777"><?= e($cEmail) ?></a><br>
      <?= e($cHours) ?>
      <?php if ($cFb !== '' || $cIg !== ''): ?>
      <br>
      <?php if ($cFb !== ''): ?><a href="<?= e($cFb) ?>" style="color:#A8478A;text-decoration:none">Facebook</a><?php endif; ?>
      <?php if ($cFb !== '' && $cIg !== ''): ?> &nbsp;·&nbsp; <?php endif; ?>
      <?php if ($cIg !== ''): ?><a href="<?= e($cIg) ?>" style="color:#A8478A;text-decoration:none">Instagram</a><?php endif; ?>
      <?php endif; ?>
    </td>
  </tr>
</table>
