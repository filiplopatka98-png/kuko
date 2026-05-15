<?php
// private/scripts/build-assets.php — local asset minifier (CLI).
//
// Generates sibling *.min.css / *.min.js next to each own CSS/JS source.
// These .min.* files ARE committed to the repo: the SFTP-only production host
// has no build step, so prod simply receives them via lftp. At request time
// \Kuko\Asset::url() automatically prefers a *.min.<ext> sibling when present
// (still cache-busting with ?v=filemtime of the .min file).
//
// Re-run this script after editing any source CSS/JS, then commit the updated
// .min.* files. Safe to run repeatedly (idempotent overwrite).
//
// Tools (via npx, node required): CSS -> csso-cli (fallback clean-css-cli),
// JS -> esbuild --minify (--format=esm for type=module entrypoints).
declare(strict_types=1);

$publicDir = __DIR__ . '/../../public';

// [relative path, isEsModule] — main.js & rezervacia.js are loaded type=module.
$cssInputs = [
    '/assets/css/main.css',
    '/assets/css/rezervacia.css',
    '/assets/css/admin.css',
];
$jsInputs = [
    ['/assets/js/main.js', true],
    ['/assets/js/rezervacia.js', true],
    ['/assets/js/gallery.js', false],
    ['/assets/js/map.js', false],
];

function minPathOf(string $in): string
{
    $ext = pathinfo($in, PATHINFO_EXTENSION);
    return substr($in, 0, -strlen('.' . $ext)) . '.min.' . $ext;
}

function run(array $argv): array
{
    $cmd = implode(' ', array_map('escapeshellarg', $argv)) . ' 2>&1';
    exec($cmd, $out, $code);
    return [$code, implode("\n", $out)];
}

$ok = 0;
$skipped = 0;

// ---- CSS ----
foreach ($cssInputs as $rel) {
    $in = $publicDir . $rel;
    $out = $publicDir . minPathOf($rel);
    if (!is_file($in)) {
        fwrite(STDERR, "WARNING: missing source $rel — skipped\n");
        $skipped++;
        continue;
    }
    $before = filesize($in);

    [$code, $log] = run(['npx', '--yes', 'csso-cli', $in, '-o', $out]);
    if ($code !== 0 || !is_file($out) || filesize($out) === 0) {
        @unlink($out);
        [$code, $log] = run(['npx', '--yes', 'clean-css-cli', '-o', $out, $in]);
    }
    if ($code !== 0 || !is_file($out) || filesize($out) === 0) {
        @unlink($out);
        fwrite(STDERR, "WARNING: no working CSS minifier for $rel (csso-cli/clean-css-cli unavailable). $log\n");
        $skipped++;
        continue;
    }
    $after = filesize($out);
    printf("CSS  %s -> %s  %d -> %d bytes (%.0f%%)\n", $rel, minPathOf($rel), $before, $after, $after / $before * 100);
    $ok++;
}

// ---- JS ----
foreach ($jsInputs as [$rel, $isModule]) {
    $in = $publicDir . $rel;
    $out = $publicDir . minPathOf($rel);
    if (!is_file($in)) {
        fwrite(STDERR, "WARNING: missing source $rel — skipped\n");
        $skipped++;
        continue;
    }
    $before = filesize($in);

    $args = ['npx', '--yes', 'esbuild', $in, '--minify', '--outfile=' . $out];
    if ($isModule) {
        $args[] = '--format=esm';
    }
    [$code, $log] = run($args);
    if ($code !== 0 || !is_file($out) || filesize($out) === 0) {
        @unlink($out);
        fwrite(STDERR, "WARNING: esbuild unavailable for $rel — skipped. $log\n");
        $skipped++;
        continue;
    }
    $after = filesize($out);
    printf("JS   %s -> %s  %d -> %d bytes (%.0f%%)\n", $rel, minPathOf($rel), $before, $after, $after / $before * 100);
    $ok++;
}

printf("\nDone: %d minified, %d skipped.\n", $ok, $skipped);
exit(0);
