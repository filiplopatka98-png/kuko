<?php
declare(strict_types=1);

/**
 * Set / reset an admin password (the "I forgot my password" recovery path).
 *
 * Usage: php private/scripts/admin-passwd.php
 *
 * Writes APP_ROOT/config/.htpasswd (bcrypt, one user per line, mode 0600).
 * The resulting file is gitignored — to take effect on PRODUCTION you must
 * deploy it to kuko-detskysvet.sk/config/.htpasswd (see docs/WORKFLOW.md).
 */

require __DIR__ . '/../lib/App.php';
\Kuko\App::bootstrap();

$file = APP_ROOT . '/config/.htpasswd';

fwrite(STDOUT, 'Používateľ: ');
$user = trim((string) fgets(STDIN));

fwrite(STDOUT, 'Heslo: ');
@system('stty -echo');
$pass1 = rtrim((string) fgets(STDIN), "\r\n");
@system('stty echo');
fwrite(STDOUT, "\n");

fwrite(STDOUT, 'Heslo znova: ');
@system('stty -echo');
$pass2 = rtrim((string) fgets(STDIN), "\r\n");
@system('stty echo');
fwrite(STDOUT, "\n");

if ($pass1 !== $pass2) {
    fwrite(STDERR, "Chyba: heslá sa nezhodujú.\n");
    exit(1);
}

$contents = is_file($file) ? (string) file_get_contents($file) : '';

try {
    $new = \Kuko\Htpasswd::upsert($contents, $user, $pass1);
} catch (\InvalidArgumentException $e) {
    fwrite(STDERR, 'Chyba: ' . $e->getMessage() . "\n");
    exit(1);
}

$dir = dirname($file);
if (!is_dir($dir)) {
    mkdir($dir, 0700, true);
}
file_put_contents($file, $new);
chmod($file, 0600);

fwrite(STDOUT, 'Heslo nastavené pre používateľa: ' . $user . '  (config/.htpasswd)' . "\n");
