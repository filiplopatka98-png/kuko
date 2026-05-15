<?php
declare(strict_types=1);

/**
 * One-time brand asset generator (design fix DT-1).
 *
 * Produces the correct site logo and a "rainbow" gallery-heading graphic from
 * the official source `assets/Logo.jpeg`. The generated binaries are committed
 * to the repo; this script does NOT run at request time. Rerun it manually only
 * if `assets/Logo.jpeg` changes:
 *
 *   /opt/homebrew/bin/php private/scripts/gen-brand-assets.php
 *
 * Requires GD (no ImageMagick).
 */

$root = dirname(__DIR__, 2); // private/scripts/ -> repo root
$srcPath = $root . '/assets/Logo.jpeg';
$outDir  = $root . '/public/assets/img';

$src = imagecreatefromjpeg($srcPath);
if ($src === false) {
    fwrite(STDERR, "Failed to load $srcPath\n");
    exit(1);
}
$W = imagesx($src);
$H = imagesy($src);
fwrite(STDOUT, "Source: $srcPath ({$W}x{$H})\n");

/**
 * Resize a truecolor image to a max width, preserving aspect ratio, on an
 * opaque white background. Returns a fresh image resource.
 */
function scaleToWidth($img, int $srcW, int $srcH, int $maxW)
{
    $dstW = min($maxW, $srcW);
    $dstH = (int) round($srcH * ($dstW / $srcW));
    $dst = imagecreatetruecolor($dstW, $dstH);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $white);
    imagecopyresampled($dst, $img, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);
    return [$dst, $dstW, $dstH];
}

function writePair($img, string $outDir, string $base, int $w, int $h): void
{
    $png = "$outDir/$base.png";
    $webp = "$outDir/$base.webp";
    imagepng($img, $png, 9);
    imagewebp($img, $webp, 86);
    fwrite(STDOUT, "Wrote $png ({$w}x{$h})\n");
    fwrite(STDOUT, "Wrote $webp ({$w}x{$h})\n");
}

// --- Full logo: scale whole image to max width 600, white-flattened ---
[$logo, $lw, $lh] = scaleToWidth($src, $W, $H, 600);
writePair($logo, $outDir, 'logo', $lw, $lh);

// --- Rainbow: scaled directly from the TRANSPARENT source (rainbow + 2 kid
// faces, no KUKO text) so the gallery heading graphic has no white box. ---
$rbSrcPath = $root . '/assets/Image_logo.png';
$rbSrc = imagecreatefrompng($rbSrcPath);
if ($rbSrc === false) {
    fwrite(STDERR, "Failed to load $rbSrcPath\n");
    exit(1);
}
$rbW = imagesx($rbSrc);
$rbH = imagesy($rbSrc);
fwrite(STDOUT, "Rainbow source: $rbSrcPath ({$rbW}x{$rbH})\n");
$rw = min(320, $rbW);
$rh = (int) round($rbH * ($rw / $rbW));
$rainbow = imagecreatetruecolor($rw, $rh);
imagealphablending($rainbow, false);
imagesavealpha($rainbow, true);
$transparent = imagecolorallocatealpha($rainbow, 0, 0, 0, 127);
imagefilledrectangle($rainbow, 0, 0, $rw, $rh, $transparent);
imagecopyresampled($rainbow, $rbSrc, 0, 0, 0, 0, $rw, $rh, $rbW, $rbH);
$rbPng = "$outDir/rainbow.png";
$rbWebp = "$outDir/rainbow.webp";
imagepng($rainbow, $rbPng, 9);
imagewebp($rainbow, $rbWebp, 86);
fwrite(STDOUT, "Wrote $rbPng ({$rw}x{$rh}) [transparent]\n");
fwrite(STDOUT, "Wrote $rbWebp ({$rw}x{$rh}) [transparent]\n");

fwrite(STDOUT, "Done.\n");
