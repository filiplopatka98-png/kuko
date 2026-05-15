<?php
declare(strict_types=1);

/**
 * One-time generator for the responsive mobile hero variants.
 *
 * Loads public/assets/img/hero.jpg and produces a 768px-wide variant
 * (aspect ratio preserved) as both hero-768.jpg and hero-768.webp.
 *
 * The generated assets ARE committed to the repo. This script only needs
 * to be re-run if public/assets/img/hero.jpg itself changes. It is
 * idempotent / safely re-runnable (it overwrites the outputs).
 *
 * Usage: php private/scripts/gen-hero-variants.php
 */

$pub = \dirname(__DIR__, 2) . '/public';
$imgDir = $pub . '/assets/img';
$src = $imgDir . '/hero.jpg';

if (!is_file($src)) {
    fwrite(STDERR, "Source not found: $src\n");
    exit(1);
}

$info = getimagesize($src);
if ($info === false) {
    fwrite(STDERR, "Could not read image: $src\n");
    exit(1);
}
[$srcW, $srcH] = $info;
$srcType = $info[2];

$targetW = 768;
$targetH = (int) round($srcH * ($targetW / $srcW));

// Decode by actual content type, not the (potentially misleading) extension:
// the committed hero.jpg is in fact a PNG.
$srcImg = match ($srcType) {
    IMAGETYPE_JPEG => imagecreatefromjpeg($src),
    IMAGETYPE_PNG  => imagecreatefrompng($src),
    IMAGETYPE_WEBP => imagecreatefromwebp($src),
    default        => false,
};
if ($srcImg === false) {
    fwrite(STDERR, "Could not decode source image (type $srcType): $src\n");
    exit(1);
}

$dst = imagecreatetruecolor($targetW, $targetH);
imagecopyresampled($dst, $srcImg, 0, 0, 0, 0, $targetW, $targetH, $srcW, $srcH);

$jpgOut = $imgDir . '/hero-768.jpg';
$webpOut = $imgDir . '/hero-768.webp';

imagejpeg($dst, $jpgOut, 82);
imagewebp($dst, $webpOut, 80);

foreach ([$jpgOut, $webpOut] as $f) {
    clearstatcache(true, $f);
    printf("wrote %s (%d bytes)\n", $f, filesize($f));
}
