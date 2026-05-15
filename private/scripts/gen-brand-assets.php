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

// --- Rainbow crop: top portion (rainbow arc + kid faces), no KUKO/script ---
// Crop fraction tuned via visual self-verification (see DT-1 notes).
$cropFrac = 0.44;
$cropW = $W;
$cropH = (int) round($H * $cropFrac);
$crop = imagecreatetruecolor($cropW, $cropH);
$white = imagecolorallocate($crop, 255, 255, 255);
imagefilledrectangle($crop, 0, 0, $cropW, $cropH, $white);
imagecopy($crop, $src, 0, 0, 0, 0, $cropW, $cropH);
[$rainbow, $rw, $rh] = scaleToWidth($crop, $cropW, $cropH, 320);
writePair($rainbow, $outDir, 'rainbow', $rw, $rh);

fwrite(STDOUT, "Done.\n");
