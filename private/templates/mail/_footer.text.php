<?php
$cAddress = \Kuko\Content::get('kontakt.address', 'Bratislavská 141, 921 01 Piešťany');
$cPhone   = \Kuko\Content::get('kontakt.phone', '+421 915 319 934');
$cEmail   = \Kuko\Content::get('kontakt.email', 'info@kuko-detskysvet.sk');
$cHours   = \Kuko\Content::get('kontakt.hours', 'Pondelok – Nedeľa: 9:00 – 20:00');
$cFb      = \Kuko\Social::url('facebook', '');
$cIg      = \Kuko\Social::url('instagram', '');
?>

--
KUKO detský svet
<?= $cAddress ?>

Tel.: <?= $cPhone ?> · <?= $cEmail ?>

<?= $cHours ?>
<?php if ($cFb !== ''): ?>
Facebook: <?= $cFb ?>
<?php endif; ?>
<?php if ($cIg !== ''): ?>
Instagram: <?= $cIg ?>
<?php endif; ?>
