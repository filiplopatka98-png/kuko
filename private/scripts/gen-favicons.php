<?php
declare(strict_types=1);
// Generates favicon/app-icon set from public/assets/img/logo.png using GD.
$pub = dirname(__DIR__, 2) . '/public';
$src = $pub . '/assets/img/logo.png';
if (!is_file($src)) { fwrite(STDERR, "logo.png not found at $src\n"); exit(1); }

$logo = imagecreatefrompng($src);
$lw = imagesx($logo); $lh = imagesy($logo);

function render_icon(int $size, \GdImage $logo, int $lw, int $lh): \GdImage {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $bg = imagecolorallocate($img, 0xFB, 0xEE, 0xF5);
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);
    imagealphablending($img, true);
    $pad = (int) round($size * 0.10);
    $box = $size - 2 * $pad;
    $scale = min($box / $lw, $box / $lh);
    $dw = (int) round($lw * $scale); $dh = (int) round($lh * $scale);
    imagecopyresampled($img, $logo, (int)(($size-$dw)/2), (int)(($size-$dh)/2), 0, 0, $dw, $dh, $lw, $lh);
    return $img;
}

$sizes = [16=>'favicon-16.png',32=>'favicon-32.png',180=>'apple-touch-icon.png',192=>'icon-192.png',512=>'icon-512.png'];
foreach ($sizes as $sz => $name) {
    $im = render_icon($sz, $logo, $lw, $lh);
    imagepng($im, $pub . '/' . $name, 9);
    fwrite(STDOUT, "wrote $name ({$sz}px)\n");
}

// favicon.ico = ICO container wrapping a 32x32 PNG (modern browsers support PNG-in-ICO)
$ico32 = render_icon(32, $logo, $lw, $lh);
ob_start(); imagepng($ico32); $png = ob_get_clean();
$ico  = pack('vvv', 0, 1, 1);
$ico .= pack('CCCC', 32, 32, 0, 0);
$ico .= pack('vv', 1, 32);
$ico .= pack('VV', strlen($png), 22);
$ico .= $png;
file_put_contents($pub . '/favicon.ico', $ico);
fwrite(STDOUT, "wrote favicon.ico\n");

// --- OG cover 1200x630 ---
$ogW = 1200; $ogH = 630;
$og = imagecreatetruecolor($ogW, $ogH);
$bg = imagecolorallocate($og, 0xFB, 0xEE, 0xF5);
imagefilledrectangle($og, 0, 0, $ogW, $ogH, $bg);
$boxH = (int) ($ogH * 0.52);
$scale = min(($ogW * 0.5) / $lw, $boxH / $lh);
$dw = (int) ($lw * $scale); $dh = (int) ($lh * $scale);
imagecopyresampled($og, $logo, (int)(($ogW-$dw)/2), (int)($ogH*0.16), 0, 0, $dw, $dh, $lw, $lh);
$font = dirname(__DIR__, 2) . '/public/assets/fonts/NunitoSans.ttf';
if (is_file($font)) {
    $ink = imagecolorallocate($og, 0x3D, 0x3D, 0x3D);
    $txt = 'kuko-detskysvet.sk';
    $bb = imagettfbbox(34, 0, $font, $txt);
    $tw = $bb[2] - $bb[0];
    imagettftext($og, 34, 0, (int)(($ogW-$tw)/2), (int)($ogH*0.88), $ink, $font, $txt);
}
imagejpeg($og, $pub . '/assets/img/og-cover.jpg', 88);
fwrite(STDOUT, "wrote og-cover.jpg (1200x630)\n");
