<?php
// Initialize a SQLite database for local dev testing.
// Usage: php private/scripts/dev-db-init.php
declare(strict_types=1);

require __DIR__ . '/../lib/autoload.php';

$dbPath = __DIR__ . '/../../private/logs/kuko-dev.sqlite';
if (file_exists($dbPath)) {
    echo "Removing existing dev DB at $dbPath\n";
    unlink($dbPath);
}
$pdo = new PDO('sqlite:' . $dbPath, null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys = ON');

// SQLite-flavoured schema (mirrors migrations but with SQLite types)
$pdo->exec("CREATE TABLE reservations (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    package TEXT NOT NULL,
    wished_date TEXT NOT NULL,
    wished_time TEXT NOT NULL,
    kids_count INTEGER NOT NULL,
    name TEXT NOT NULL,
    phone TEXT NOT NULL,
    email TEXT NOT NULL,
    note TEXT,
    status TEXT NOT NULL DEFAULT 'pending',
    confirmed_at TEXT,
    cancelled_at TEXT,
    cancelled_reason TEXT,
    ip_hash TEXT NOT NULL,
    view_token TEXT UNIQUE,
    recaptcha_score REAL,
    user_agent TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)");
$pdo->exec("CREATE TABLE admin_actions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    admin_user TEXT NOT NULL,
    action TEXT NOT NULL,
    target_table TEXT NOT NULL,
    target_id INTEGER NOT NULL,
    payload_json TEXT,
    ip_hash TEXT NOT NULL,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
)");
$pdo->exec("CREATE TABLE packages (
    code TEXT PRIMARY KEY,
    name TEXT NOT NULL,
    duration_min INTEGER NOT NULL,
    blocks_full_day INTEGER NOT NULL DEFAULT 0,
    is_active INTEGER NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0
)");
$pdo->exec("INSERT INTO packages (code, name, duration_min, blocks_full_day, sort_order) VALUES
  ('mini',  'KUKO MINI',           120, 0, 1),
  ('maxi',  'KUKO MAXI',           180, 0, 2),
  ('closed','Uzavretá spoločnosť', 240, 1, 3)");

$pdo->exec("CREATE TABLE opening_hours (
    weekday INTEGER PRIMARY KEY,
    is_open INTEGER NOT NULL DEFAULT 1,
    open_from TEXT NOT NULL DEFAULT '09:00',
    open_to TEXT NOT NULL DEFAULT '20:00'
)");
for ($d = 0; $d < 7; $d++) {
    $pdo->prepare("INSERT INTO opening_hours (weekday) VALUES (?)")->execute([$d]);
}

$pdo->exec("CREATE TABLE blocked_periods (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    date_from TEXT NOT NULL,
    date_to TEXT NOT NULL,
    time_from TEXT,
    time_to TEXT,
    reason TEXT,
    created_at TEXT NOT NULL DEFAULT (datetime('now'))
)");

$pdo->exec("CREATE TABLE settings (
    setting_key TEXT PRIMARY KEY,
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now'))
)");
foreach (
    [
        ['buffer_min', '30'],
        ['horizon_days', '180'],
        ['lead_hours', '24'],
        ['slot_increment_min', '30'],
    ] as $kv
) {
    $stmt = $pdo->prepare('INSERT INTO settings (setting_key, value) VALUES (?, ?)');
    $stmt->execute($kv);
}

$pdo->exec("CREATE TABLE content_blocks (
    block_key TEXT PRIMARY KEY,
    label TEXT NOT NULL,
    content_type TEXT NOT NULL DEFAULT 'text',
    value TEXT NOT NULL,
    updated_at TEXT NOT NULL DEFAULT (datetime('now')),
    updated_by TEXT
)");
$pdo->exec("CREATE TABLE gallery_photos (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    webp TEXT,
    alt_text TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_visible INTEGER NOT NULL DEFAULT 1,
    uploaded_at TEXT NOT NULL DEFAULT (datetime('now'))
)");
$pdo->exec("ALTER TABLE packages ADD COLUMN description TEXT");
$pdo->exec("ALTER TABLE packages ADD COLUMN price_text TEXT");
$pdo->exec("ALTER TABLE packages ADD COLUMN kids_count_text TEXT");
$pdo->exec("ALTER TABLE packages ADD COLUMN duration_text TEXT");
$pdo->exec("ALTER TABLE packages ADD COLUMN included_json TEXT");
$pdo->exec("ALTER TABLE packages ADD COLUMN accent_color TEXT");

echo "Dev SQLite DB initialized at $dbPath\n";
echo "Update config/config.php:\n";
echo "  'db' => ['host' => 'sqlite:' . __DIR__ . '/../private/logs/kuko-dev.sqlite', 'name' => '', 'user' => '', 'pass' => '', 'charset' => 'utf8mb4']\n";
echo "...or use the dev-config helper.\n";
