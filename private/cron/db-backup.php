<?php
declare(strict_types=1);

/**
 * Database backup cron (belt-and-suspenders).
 *
 * WebSupport already takes its own daily backups of hosting accounts, but those
 * are operator-controlled and not trivially restorable by us. This script makes
 * an INDEPENDENT, app-owned dump we control.
 *
 * OWNER ACTION REQUIRED:
 *   1. Register this in the WebSupport cron panel to run WEEKLY, e.g.:
 *        php /data/.../kuko-detskysvet.sk/private/cron/db-backup.php
 *   2. Periodically DOWNLOAD the newest private/logs/backups/kuko-*.sql.gz
 *      OFFSITE (a backup that only lives on the same server is not a backup).
 *
 * The MySQL password is read from the PRODUCTION config/config.php (which is
 * never committed). It is passed to mysqldump via a transient MYSQL_PWD env
 * var — never on the argv/process list (so it can't leak via `ps`).
 *
 * Behaviour:
 *   - dev (db.host starts with "sqlite:")  -> copy the sqlite file
 *   - prod (MySQL)                         -> mysqldump | gzip
 *   - keeps only the newest 8 kuko-* backups, deletes older
 *   - fails closed: on mysqldump error nothing truncated is left behind, exit 1
 */

require dirname(__DIR__) . '/lib/App.php';
\Kuko\App::bootstrap();

const KEEP = 8;

$backupDir = APP_ROOT . '/private/logs/backups';
if (!is_dir($backupDir) && !@mkdir($backupDir, 0700, true) && !is_dir($backupDir)) {
    fwrite(STDERR, "[db-backup] ERROR cannot create backup dir: {$backupDir}\n");
    exit(1);
}
@chmod($backupDir, 0700);

$host = (string) \Kuko\Config::get('db.host', '');
$name = (string) \Kuko\Config::get('db.name', '');
$user = (string) \Kuko\Config::get('db.user', '');
$pass = (string) \Kuko\Config::get('db.pass', '');

$stamp = date('Ymd-His');

if (str_starts_with($host, 'sqlite:')) {
    // ---- DEV: sqlite file copy ----
    $path = substr($host, strlen('sqlite:'));
    if ($path === '' || $path === ':memory:' || str_starts_with($path, ':memory:')) {
        fwrite(STDOUT, "[db-backup] skipped: in-memory sqlite, nothing to back up\n");
        exit(0);
    }
    if (!is_file($path)) {
        fwrite(STDERR, "[db-backup] ERROR sqlite file not found: {$path}\n");
        exit(1);
    }
    $dest = $backupDir . '/kuko-' . $stamp . '.sqlite';
    if (!@copy($path, $dest)) {
        fwrite(STDERR, "[db-backup] ERROR sqlite copy failed: {$path} -> {$dest}\n");
        @unlink($dest);
        exit(1);
    }
    @chmod($dest, 0600);
    prune($backupDir);
    fwrite(STDOUT, '[db-backup] OK sqlite copied -> ' . $dest . ' (' . filesize($dest) . " bytes)\n");
    exit(0);
}

// ---- PROD: mysqldump | gzip ----
$dest = $backupDir . '/kuko-' . $stamp . '.sql.gz';

$cmd = 'mysqldump --single-transaction --quick --no-tablespaces'
     . ' --host=' . escapeshellarg($host)
     . ' --user=' . escapeshellarg($user)
     . ' ' . escapeshellarg($name)
     . ' | gzip -c > ' . escapeshellarg($dest);

$descriptors = [
    0 => ['file', '/dev/null', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w'],
];
// Password via env so it never appears in argv / `ps`.
$env = $_ENV;
$env['MYSQL_PWD'] = $pass;

$proc = proc_open(['bash', '-o', 'pipefail', '-c', $cmd], $descriptors, $pipes, null, $env);
if (!is_resource($proc)) {
    fwrite(STDERR, "[db-backup] ERROR could not start mysqldump pipeline\n");
    @unlink($dest);
    exit(1);
}
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($proc);

if ($exit !== 0) {
    fwrite(STDERR, "[db-backup] ERROR mysqldump pipeline failed (exit {$exit})\n");
    if ($stderr !== '' && $stderr !== false) {
        fwrite(STDERR, '[db-backup] ' . trim($stderr) . "\n");
    }
    @unlink($dest); // do not leave a truncated/empty archive
    exit(1);
}
if (!is_file($dest) || filesize($dest) === 0) {
    fwrite(STDERR, "[db-backup] ERROR backup file missing or empty after dump\n");
    @unlink($dest);
    exit(1);
}
@chmod($dest, 0600);
prune($backupDir);
fwrite(STDOUT, '[db-backup] OK mysqldump -> ' . $dest . ' (' . filesize($dest) . " bytes)\n");
exit(0);

/**
 * Keep only the newest KEEP "kuko-*" files in $dir, delete the rest.
 */
function prune(string $dir): void
{
    $files = glob($dir . '/kuko-*');
    if ($files === false || count($files) <= KEEP) {
        return;
    }
    // Sort newest-first by mtime.
    usort($files, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));
    foreach (array_slice($files, KEEP) as $old) {
        @unlink($old);
    }
}
