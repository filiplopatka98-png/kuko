# Quality Roadmap — Sprint 1 (Top-8 Most Impactful) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ship the 8 highest-impact quality items from `docs/plans/2026-05-14-roadmap-quality.md` so the site is security-hardened, GDPR-compliant, calendar-accessible, and favicon/OG-complete — the prerequisites the roadmap names before flipping `maintenance` off and `public_indexing` on.

**Architecture:** Vanilla PHP 8.1, one-class-per-file (`Kuko\` namespace), PHPUnit 10.5 (phar). Reuse existing libs (`RateLimit`, `Auth`, `Csrf`, `ReservationRepo`). Asset generation via PHP GD (no ImageMagick on this box). All DB changes via numbered migration `006_*.sql`. Graceful degradation everywhere (DB down → site still serves).

**Tech Stack:** PHP 8.1 (`/opt/homebrew/bin/php`), PHPUnit phar (`private/lib/vendor/phpunit.phar`), SQLite dev DB, MySQL prod, Apache `.htaccess`, GD.

**Out of scope for subagents — Owner/manual action items (documented, NOT implemented here):**
- P1 Lighthouse baseline run (owner runs in Chrome, saves screenshots to `docs/audits/`)
- A1 axe DevTools scan (owner runs extension; this plan implements the calendar a11y fixes A4)
- S3 Google Business Profile create/verify (owner-only, external)
- B2 HSTS preload registration at hstspreload.org (owner, only after HSTS header is live + stable)

These are listed for the user in the final report, not built.

---

## File Structure

- `private/migrations/006_quality.sql` — NEW: login_attempts index reuse note (no schema change needed; admin_actions reused). Actually: no DB migration needed for Sprint 1 — login logging reuses `admin_actions`. File omitted.
- `private/lib/LoginThrottle.php` — NEW: wraps `RateLimit` for admin-login brute-force (per-IP + per-username buckets)
- `private/lib/Auth.php` — MODIFY: add `logAttempt()` hook point is in admin/index.php, not here. Keep Auth unchanged unless needed.
- `private/lib/Privacy.php` — NEW: `anonymizeReservation(int $id)` + `exportByEmail(string $email)` PII logic, reused by cron + admin action
- `private/cron/retention.php` — NEW: CLI script, anonymizes reservations older than 6 months
- `private/templates/admin/login.php` — MODIFY: add CSRF hidden field
- `public/admin/index.php` — MODIFY: login POST verifies CSRF + throttle + audit; add `/admin/reservation/{id}/anonymize` route + `/admin/gdpr` export route
- `private/templates/admin/gdpr.php` — NEW: email → reservations export view
- `private/templates/admin/layout.php` — MODIFY: add "GDPR" nav link
- `public/.htaccess` — MODIFY: add HSTS header
- `public/assets/js/rezervacia.js` — MODIFY: calendar ARIA + keyboard nav
- `public/assets/css/main.css` — MODIFY: calendar focus-visible style
- `private/scripts/gen-favicons.php` — NEW: GD script generating favicon PNGs/ICO + og-cover from logo.png
- `public/favicon.ico`, `public/favicon-32.png`, `public/apple-touch-icon.png`, `public/icon-192.png`, `public/icon-512.png`, `public/assets/img/og-cover.jpg` — generated assets (committed)
- `public/manifest.webmanifest` — NEW
- `private/templates/head.php` — MODIFY: full favicon `<link>` set + manifest + og:image → og-cover
- `private/tests/...` — test files per task below

---

### Task 1: Admin login CSRF token

**Files:**
- Modify: `private/templates/admin/login.php` (form body)
- Modify: `public/admin/index.php:33-45` (POST /admin/login handler)
- Test: `private/tests/integration/AdminLoginCsrfTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use Kuko\Csrf;
use PHPUnit\Framework\TestCase;

final class AdminLoginCsrfTest extends TestCase
{
    public function testLoginTemplateContainsCsrfField(): void
    {
        $tpl = file_get_contents(\dirname(__DIR__, 2) . '/private/templates/admin/login.php');
        $this->assertStringContainsString('name="csrf"', $tpl);
        $this->assertStringContainsString('Csrf::token()', $tpl);
    }

    public function testLoginPostHandlerVerifiesCsrf(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 2) . '/public/admin/index.php');
        // The POST /admin/login closure must call Csrf::verify before Auth::attempt
        $this->assertMatchesRegularExpression(
            '/post\(.\/admin\/login.*?Csrf::verify.*?Auth::attempt/s',
            $idx
        );
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter AdminLoginCsrf private/tests`
Expected: FAIL (no `name="csrf"` in login template)

- [ ] **Step 3: Add CSRF field to login template**

In `private/templates/admin/login.php`, immediately after the `<form ...>` opening tag and before the `<h1>`, add:

```php
  <input type="hidden" name="csrf" value="<?= e(\Kuko\Csrf::token()) ?>">
```

- [ ] **Step 4: Verify CSRF in POST handler**

In `public/admin/index.php`, the `$router->post('/admin/login', ...)` closure: add the CSRF check as the FIRST statement inside the closure body, before reading `$user`:

```php
$router->post('/admin/login', function () use ($renderer) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        echo $renderer->render('login', ['error' => true, 'next' => (string) ($_POST['next'] ?? '/admin')]);
        return;
    }
    $user     = trim((string) ($_POST['username'] ?? ''));
    // ... rest unchanged
```

- [ ] **Step 5: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter AdminLoginCsrf private/tests`
Expected: PASS (2 tests)

- [ ] **Step 6: Lint + commit**

```bash
/opt/homebrew/bin/php -l private/templates/admin/login.php && /opt/homebrew/bin/php -l public/admin/index.php
git add private/templates/admin/login.php public/admin/index.php private/tests/integration/AdminLoginCsrfTest.php
git commit -m "$(cat <<'EOF'
fix: add CSRF token to admin login form (roadmap B1)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 2: Brute-force protection on admin login

**Files:**
- Create: `private/lib/LoginThrottle.php`
- Modify: `public/admin/index.php` (POST /admin/login)
- Test: `private/tests/unit/LoginThrottleTest.php`

**Context:** `Kuko\RateLimit` (constructor `(string $dir, int $max=3, int $windowSec=3600)`, method `allow(string $ipHash, string $bucket): bool`) already exists and is file-based. We wrap it: max 5 bad attempts per IP-hash per hour AND per-username per hour. A *successful* login clears the buckets. Rate-limit dir: `APP_ROOT . '/private/logs/ratelimit'` (same dir RateLimit already uses for reservations — confirm via existing reservation usage; if different, use `Config::get('security.ratelimit_dir')` fallback to `APP_ROOT.'/private/logs/ratelimit'`).

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use Kuko\LoginThrottle;
use PHPUnit\Framework\TestCase;

final class LoginThrottleTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kuko-throttle-' . bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            array_map('unlink', glob($this->dir . '/*') ?: []);
            rmdir($this->dir);
        }
    }

    public function testAllowsUpToFiveBadAttemptsThenBlocks(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 5; $i++) {
            $this->assertTrue($t->permit('1.2.3.4', 'admin'), "attempt $i should be permitted");
            $t->recordFailure('1.2.3.4', 'admin');
        }
        $this->assertFalse($t->permit('1.2.3.4', 'admin'), '6th attempt blocked by IP bucket');
    }

    public function testSuccessClearsBuckets(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 5; $i++) { $t->permit('9.9.9.9', 'bob'); $t->recordFailure('9.9.9.9', 'bob'); }
        $this->assertFalse($t->permit('9.9.9.9', 'bob'));
        $t->recordSuccess('9.9.9.9', 'bob');
        $this->assertTrue($t->permit('9.9.9.9', 'bob'), 'buckets cleared after success');
    }

    public function testPerUsernameBlockSpansIps(): void
    {
        $t = new LoginThrottle($this->dir, 5, 3600);
        for ($i = 0; $i < 5; $i++) { $t->permit('10.0.0.' . $i, 'victim'); $t->recordFailure('10.0.0.' . $i, 'victim'); }
        $this->assertFalse($t->permit('10.0.0.99', 'victim'), 'username bucket blocks even from a fresh IP');
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter LoginThrottle private/tests`
Expected: FAIL (`Kuko\LoginThrottle` not found)

- [ ] **Step 3: Implement LoginThrottle**

Create `private/lib/LoginThrottle.php`. It maintains its own JSON counter files (does NOT consume RateLimit's `allow()` increment, because we must increment only on failure and clear on success — RateLimit increments on every check). Use a sha1 of ip and a sanitized username as bucket keys.

```php
<?php
declare(strict_types=1);
namespace Kuko;

/**
 * Brute-force throttle for admin login. Independent of RateLimit because
 * we must count only failures and reset on success (RateLimit counts every
 * probe). File-based so it works on WebSupport (no shared memory).
 */
final class LoginThrottle
{
    public function __construct(
        private string $dir,
        private int $max = 5,
        private int $windowSec = 3600,
    ) {
        if (!is_dir($this->dir)) {
            @mkdir($this->dir, 0700, true);
        }
    }

    /** True if another attempt is allowed for this ip+username. */
    public function permit(string $ip, string $username): bool
    {
        return $this->count($this->ipFile($ip)) < $this->max
            && $this->count($this->userFile($username)) < $this->max;
    }

    public function recordFailure(string $ip, string $username): void
    {
        $this->bump($this->ipFile($ip));
        $this->bump($this->userFile($username));
    }

    public function recordSuccess(string $ip, string $username): void
    {
        @unlink($this->ipFile($ip));
        @unlink($this->userFile($username));
    }

    private function ipFile(string $ip): string
    {
        return $this->dir . '/login_ip_' . sha1($ip) . '.json';
    }

    private function userFile(string $username): string
    {
        $u = strtolower(trim($username));
        return $this->dir . '/login_user_' . sha1($u === '' ? '(empty)' : $u) . '.json';
    }

    private function count(string $file): int
    {
        if (!is_file($file)) return 0;
        $s = json_decode((string) file_get_contents($file), true);
        if (!is_array($s) || !isset($s['start'], $s['count'])) return 0;
        if (time() - (int) $s['start'] >= $this->windowSec) return 0;
        return (int) $s['count'];
    }

    private function bump(string $file): void
    {
        $now = time();
        $s = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $p = json_decode((string) file_get_contents($file), true);
            if (is_array($p) && isset($p['start'], $p['count']) && $now - (int) $p['start'] < $this->windowSec) {
                $s = $p;
            }
        }
        $s['count'] = (int) $s['count'] + 1;
        file_put_contents($file, json_encode($s), LOCK_EX);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter LoginThrottle private/tests`
Expected: PASS (3 tests)

- [ ] **Step 5: Wire into login POST handler**

In `public/admin/index.php`, inside `$router->post('/admin/login', ...)`, AFTER the CSRF check (Task 1) and AFTER reading `$user`/`$pass`, add throttle gating. Use the existing IP-hash secret pattern already used by `$audit`:

```php
    $ipRaw = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $throttle = new \Kuko\LoginThrottle(APP_ROOT . '/private/logs/ratelimit');
    if (!$throttle->permit($ipRaw, $user)) {
        http_response_code(429);
        echo $renderer->render('login', ['error' => true, 'locked' => true, 'next' => $next]);
        return;
    }

    if (Auth::attempt($user, $pass, $remember)) {
        $throttle->recordSuccess($ipRaw, $user);
        header('Location: ' . $next);
        return;
    }
    $throttle->recordFailure($ipRaw, $user);
    http_response_code(401);
    echo $renderer->render('login', ['error' => true, 'next' => $next]);
```

In `private/templates/admin/login.php`, change the error block to also show a lockout message:

```php
  <?php if (!empty($locked)): ?>
    <p class="login__error">Príliš veľa pokusov. Skúste znova o hodinu.</p>
  <?php elseif (!empty($error)): ?>
    <p class="login__error">Nesprávne meno alebo heslo.</p>
  <?php endif; ?>
```

- [ ] **Step 6: Run full suite + lint + commit**

```bash
/opt/homebrew/bin/php private/lib/vendor/phpunit.phar private/tests
/opt/homebrew/bin/php -l private/lib/LoginThrottle.php && /opt/homebrew/bin/php -l public/admin/index.php
git add private/lib/LoginThrottle.php public/admin/index.php private/templates/admin/login.php private/tests/unit/LoginThrottleTest.php
git commit -m "$(cat <<'EOF'
feat: brute-force throttle on admin login (roadmap B1)

5 failed attempts / hour per IP and per username; success clears buckets.

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 3: Login attempt audit logging

**Files:**
- Modify: `public/admin/index.php` (login POST: log success + failure with IP/UA)
- Test: `private/tests/integration/LoginAuditTest.php`

**Context:** `admin_actions` table already has `admin_user, action, target_table, target_id, payload_json, ip_hash, created_at`. Log auth events there: `action='login_ok'|'login_fail'|'login_locked'`, `target_table='auth'`, `target_id=0`, `payload_json` carries `{"ua": "<first 255 chars of User-Agent>"}`. The `$audit` closure is defined AFTER the login routes in index.php, so add a small local logging closure available to the login closure. Reuse the same ip_hash formula `hash('sha256', $ip . '|' . security.ip_hash_secret)`.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use PHPUnit\Framework\TestCase;

final class LoginAuditTest extends TestCase
{
    public function testLoginHandlerLogsAuthEvents(): void
    {
        $idx = file_get_contents(\dirname(__DIR__, 2) . '/public/admin/index.php');
        $this->assertStringContainsString("'login_ok'", $idx);
        $this->assertStringContainsString("'login_fail'", $idx);
        $this->assertStringContainsString("target_table", $idx);
        // UA must be captured into the payload
        $this->assertMatchesRegularExpression('/HTTP_USER_AGENT.{0,120}substr/s', $idx);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter LoginAudit private/tests`
Expected: FAIL (`'login_ok'` absent)

- [ ] **Step 3: Add an auth-logging closure and call it**

In `public/admin/index.php`, BEFORE the `$router->post('/admin/login', ...)` route, add a self-contained logger (it opens its own DB connection in a try/catch so a DB outage never blocks login):

```php
$logAuth = function (string $action, string $user) {
    try {
        $db = Db::fromConfig();
        $secret = (string) \Kuko\Config::get('security.ip_hash_secret', '');
        $ua = substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255);
        $db->execStmt(
            'INSERT INTO admin_actions (admin_user, action, target_table, target_id, payload_json, ip_hash) VALUES (?,?,?,?,?,?)',
            [
                $user !== '' ? $user : '(none)',
                $action,
                'auth',
                0,
                json_encode(['ua' => $ua]),
                hash('sha256', ((string) ($_SERVER['REMOTE_ADDR'] ?? '')) . '|' . $secret),
            ]
        );
    } catch (\Throwable $e) {
        error_log('[LoginAudit] ' . $e->getMessage());
    }
};
```

Then in the login POST closure, add `use ($renderer, $logAuth)` and call:
- after `$throttle->permit(...)` returns false → `$logAuth('login_locked', $user);`
- after `Auth::attempt` true → `$logAuth('login_ok', $user);`
- after `Auth::attempt` false → `$logAuth('login_fail', $user);`

(The closure's `use` list must include `$logAuth`.)

- [ ] **Step 4: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter LoginAudit private/tests`
Expected: PASS

- [ ] **Step 5: Manual smoke (dev) + commit**

```bash
/opt/homebrew/bin/php -l public/admin/index.php
git add public/admin/index.php private/tests/integration/LoginAuditTest.php
git commit -m "$(cat <<'EOF'
feat: log admin auth events (ok/fail/locked) with IP+UA (roadmap B1)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 4: HSTS header

**Files:**
- Modify: `public/.htaccess` (mod_headers block)
- Test: `private/tests/unit/HtaccessHstsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class HtaccessHstsTest extends TestCase
{
    public function testHstsHeaderPresent(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 2) . '/public/.htaccess');
        $this->assertStringContainsString('Strict-Transport-Security', $h);
        $this->assertStringContainsString('max-age=31536000', $h);
        $this->assertStringContainsString('includeSubDomains', $h);
        $this->assertStringContainsString('preload', $h);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter HtaccessHsts private/tests`
Expected: FAIL

- [ ] **Step 3: Add the header**

In `public/.htaccess`, inside the existing `<IfModule mod_headers.c>` block, add after the `Permissions-Policy` line:

```apache
    Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload"
```

- [ ] **Step 4: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter HtaccessHsts private/tests`
Expected: PASS

- [ ] **Step 5: Commit**

```bash
git add public/.htaccess private/tests/unit/HtaccessHstsTest.php
git commit -m "$(cat <<'EOF'
feat: add HSTS header (roadmap B2)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 5: Data retention cron + right-to-be-forgotten + email export

**Files:**
- Create: `private/lib/Privacy.php`
- Create: `private/cron/retention.php`
- Create: `private/templates/admin/gdpr.php`
- Modify: `public/admin/index.php` (routes: `/admin/gdpr` GET+POST, `/admin/reservation/{id}/anonymize` POST)
- Modify: `private/templates/admin/layout.php` (nav link)
- Test: `private/tests/integration/PrivacyTest.php`

**Context:** `reservations` PII columns: `name, phone, email, note, user_agent`. Anonymization keeps `package, wished_date, wished_time, kids_count, status, created_at` (stats) and scrubs PII to fixed placeholders + sets `status` unchanged. Retention rule: any reservation with `created_at < now - 6 months` gets anonymized (idempotent — re-running is safe because scrubbed rows already have `email = ''`). Export = list reservations matching an exact email (case-insensitive).

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;

use Kuko\Db;
use Kuko\Privacy;
use PHPUnit\Framework\TestCase;

final class PrivacyTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec(
            "CREATE TABLE reservations (id INTEGER PRIMARY KEY AUTOINCREMENT, package TEXT, wished_date TEXT, wished_time TEXT,
             kids_count INTEGER, name TEXT, phone TEXT, email TEXT, note TEXT, status TEXT DEFAULT 'pending',
             ip_hash TEXT DEFAULT '', user_agent TEXT, created_at TEXT)"
        );
        $this->db->execStmt(
            "INSERT INTO reservations (package,wished_date,wished_time,kids_count,name,phone,email,note,status,user_agent,created_at)
             VALUES ('mini','2025-01-01','14:00',8,'Old Client','+421900111222','old@x.sk','poznamka','confirmed','UA1', ?)",
            [(new \DateTimeImmutable('-7 months'))->format('Y-m-d H:i:s')]
        );
        $this->db->execStmt(
            "INSERT INTO reservations (package,wished_date,wished_time,kids_count,name,phone,email,note,status,user_agent,created_at)
             VALUES ('maxi','2026-06-01','15:00',12,'Fresh Client','+421900333444','fresh@x.sk','','pending','UA2', ?)",
            [(new \DateTimeImmutable('-2 months'))->format('Y-m-d H:i:s')]
        );
    }

    public function testAnonymizeScrubsPiiKeepsStats(): void
    {
        $p = new Privacy($this->db);
        $p->anonymizeReservation(1);
        $r = $this->db->all('SELECT * FROM reservations WHERE id=1')[0];
        $this->assertSame('', (string) $r['email']);
        $this->assertSame('', (string) $r['phone']);
        $this->assertStringNotContainsStringIgnoringCase('Old Client', (string) $r['name']);
        $this->assertSame('', (string) $r['note']);
        $this->assertSame('', (string) $r['user_agent']);
        // stats preserved
        $this->assertSame('mini', (string) $r['package']);
        $this->assertSame(8, (int) $r['kids_count']);
        $this->assertSame('confirmed', (string) $r['status']);
    }

    public function testPurgeOlderThanAnonymizesOnlyOldRows(): void
    {
        $p = new Privacy($this->db);
        $n = $p->purgeOlderThan(6);
        $this->assertSame(1, $n);
        $fresh = $this->db->all("SELECT email FROM reservations WHERE id=2")[0];
        $this->assertSame('fresh@x.sk', (string) $fresh['email']);
    }

    public function testPurgeIsIdempotent(): void
    {
        $p = new Privacy($this->db);
        $this->assertSame(1, $p->purgeOlderThan(6));
        $this->assertSame(0, $p->purgeOlderThan(6));
    }

    public function testExportByEmailCaseInsensitive(): void
    {
        $p = new Privacy($this->db);
        $rows = $p->exportByEmail('FRESH@X.SK');
        $this->assertCount(1, $rows);
        $this->assertSame('Fresh Client', (string) $rows[0]['name']);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter Privacy private/tests`
Expected: FAIL (`Kuko\Privacy` not found)

- [ ] **Step 3: Implement Privacy**

Create `private/lib/Privacy.php`:

```php
<?php
declare(strict_types=1);
namespace Kuko;

final class Privacy
{
    public function __construct(private Db $db) {}

    public function anonymizeReservation(int $id): void
    {
        $this->db->execStmt(
            "UPDATE reservations SET name = 'anonymizovaný', phone = '', email = '', note = '', user_agent = '' WHERE id = ?",
            [$id]
        );
    }

    /** Anonymizes reservations whose created_at is older than $months. Returns count affected. */
    public function purgeOlderThan(int $months): int
    {
        $cutoff = (new \DateTimeImmutable("-{$months} months"))->format('Y-m-d H:i:s');
        $rows = $this->db->all(
            "SELECT id FROM reservations WHERE created_at < ? AND email <> ''",
            [$cutoff]
        );
        foreach ($rows as $r) {
            $this->anonymizeReservation((int) $r['id']);
        }
        return count($rows);
    }

    /** @return array<int,array<string,mixed>> */
    public function exportByEmail(string $email): array
    {
        return $this->db->all(
            'SELECT * FROM reservations WHERE LOWER(email) = LOWER(?) ORDER BY created_at DESC',
            [trim($email)]
        );
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter Privacy private/tests`
Expected: PASS (4 tests)

- [ ] **Step 5: Create the cron script**

Create `private/cron/retention.php`:

```php
<?php
declare(strict_types=1);
require dirname(__DIR__) . '/lib/App.php';
\Kuko\App::bootstrap();

$months = (int) \Kuko\Config::get('privacy.retention_months', 6);
try {
    $db = \Kuko\Db::fromConfig();
    $n = (new \Kuko\Privacy($db))->purgeOlderThan($months);
    fwrite(STDOUT, '[retention] anonymized ' . $n . " reservation(s) older than {$months} months\n");
} catch (\Throwable $e) {
    fwrite(STDERR, '[retention] ERROR ' . $e->getMessage() . "\n");
    exit(1);
}
```

- [ ] **Step 6: Add admin GDPR routes + anonymize action**

In `public/admin/index.php`, after the existing reservation routes, add:

```php
$router->post('/admin/reservation/{id}/anonymize', function (array $p) use ($db, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string) ($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $id = (int) $p['id'];
    (new \Kuko\Privacy($db))->anonymizeReservation($id);
    $audit('anonymize', 'reservations', $id);
    $flash('Rezervácia #' . $id . ' anonymizovaná (PII vymazané, štatistika zachovaná).');
    header('Location: /admin/reservation/' . $id);
});

$router->get('/admin/gdpr', function () use ($renderer, $adminUser, $flashes) {
    $email = trim((string) ($_GET['email'] ?? ''));
    $rows  = [];
    if ($email !== '') {
        $rows = (new \Kuko\Privacy(\Kuko\Db::fromConfig()))->exportByEmail($email);
    }
    echo $renderer->render('gdpr', ['email' => $email, 'rows' => $rows, 'user' => $adminUser, 'flashes' => $flashes]);
});
```

Add a delete/anonymize button to `private/templates/admin/detail.php` (find the existing action buttons area; add a form):

```php
<form method="post" action="/admin/reservation/<?= (int)$r['id'] ?>/anonymize"
      onsubmit="return confirm('Anonymizovať? PII (meno, telefón, e-mail, poznámka) sa nenávratne vymažú. Štatistika zostane.');">
  <input type="hidden" name="csrf" value="<?= e(\Kuko\Csrf::token()) ?>">
  <button type="submit" class="btn btn--danger">Anonymizovať (GDPR)</button>
</form>
```

- [ ] **Step 7: Create gdpr.php template**

Create `private/templates/admin/gdpr.php`:

```php
<?php
/** @var string $email */
/** @var array<int,array<string,mixed>> $rows */
/** @var string $user */
$title = 'GDPR — KUKO admin';
ob_start();
?>
<h2>GDPR — žiadosti dotknutej osoby</h2>
<p class="admin-lead">Zadajte e-mail klienta pre výpis jeho rezervácií (právo na prístup, čl. 15 GDPR).</p>
<form method="get" action="/admin/gdpr" class="admin-form">
  <label class="admin-field">
    <span>E-mail klienta</span>
    <input type="email" name="email" value="<?= e($email) ?>" required>
  </label>
  <div class="admin-form__actions"><button type="submit">Vyhľadať</button></div>
</form>
<?php if ($email !== ''): ?>
  <p><strong><?= count($rows) ?></strong> rezervácií pre <?= e($email) ?>.</p>
  <table class="admin-table">
    <thead><tr><th>#</th><th>Balíček</th><th>Dátum</th><th>Meno</th><th>Telefón</th><th>Stav</th></tr></thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><?= e((string)$r['package']) ?></td>
        <td><?= e((string)$r['wished_date']) ?> <?= e((string)$r['wished_time']) ?></td>
        <td><?= e((string)$r['name']) ?></td>
        <td><?= e((string)$r['phone']) ?></td>
        <td><?= e((string)$r['status']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
```

Add nav link in `private/templates/admin/layout.php` next to the "Log" link:

```php
    <a href="/admin/gdpr">GDPR</a>
```

- [ ] **Step 8: Run full suite + lint + commit**

```bash
/opt/homebrew/bin/php private/lib/vendor/phpunit.phar private/tests
for f in private/lib/Privacy.php private/cron/retention.php private/templates/admin/gdpr.php public/admin/index.php private/templates/admin/layout.php; do /opt/homebrew/bin/php -l "$f"; done
/opt/homebrew/bin/php private/cron/retention.php   # dev smoke (sqlite): should print "[retention] anonymized N ..."
git add private/lib/Privacy.php private/cron/retention.php private/templates/admin/gdpr.php private/templates/admin/layout.php private/templates/admin/detail.php public/admin/index.php private/tests/integration/PrivacyTest.php
git commit -m "$(cat <<'EOF'
feat: GDPR retention cron + anonymize action + email export (roadmap B4)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 6: Calendar accessibility (ARIA + keyboard navigation)

**Files:**
- Modify: `public/assets/js/rezervacia.js` (calendar render + key handlers)
- Modify: `public/assets/css/main.css` (`:focus-visible` for calendar cells)
- Test: `private/tests/unit/CalendarA11yTest.php` (static assertions on the JS source — no JS test runner in this stack)

**Context:** No JS test runner exists; verification is (a) PHP test asserting the JS source contains the required ARIA wiring and key handling, and (b) manual keyboard smoke documented in the step. Calendar grid container has class `.calendar__grid`; day cells are buttons/divs created in `rezervacia.js`. The implementer MUST read `public/assets/js/rezervacia.js` first to match existing variable names and DOM construction; the code below is the required end-state behavior, adapt insertion points to the actual structure.

Required behavior:
- Grid container gets `role="grid"`, `aria-label="Kalendár dostupných termínov"`
- Each week wrapper `role="row"`; each day cell `role="gridcell"`
- Selectable day: `tabindex` roving (selected/active day `tabindex="0"`, others `tabindex="-1"`); `aria-selected="true"` on chosen day
- Unavailable day (closed/full/past): `aria-disabled="true"` (keep visual disabled)
- Each cell `aria-label` like `"Streda 15. máj, dostupné, 19 voľných termínov"` or `"Sobota 18. máj, plne obsadené"`
- Keyboard on grid: ArrowLeft/Right ±1 day, ArrowUp/Down ±7 days, Home/End first/last of week, PageUp/PageDown prev/next month, Enter/Space select focused day
- After selecting a day, an `aria-live="polite"` region announces `"Vybraný 15. máj. 19 voľných termínov nižšie."`

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class CalendarA11yTest extends TestCase
{
    private string $js;

    protected function setUp(): void
    {
        $this->js = file_get_contents(\dirname(__DIR__, 2) . '/public/assets/js/rezervacia.js');
    }

    public function testGridRolesPresent(): void
    {
        $this->assertStringContainsString("role', 'grid'", $this->js);
        $this->assertStringContainsString("role', 'gridcell'", $this->js);
        $this->assertStringContainsString('aria-selected', $this->js);
        $this->assertStringContainsString('aria-disabled', $this->js);
        $this->assertStringContainsString('aria-label', $this->js);
    }

    public function testKeyboardHandlersPresent(): void
    {
        foreach (['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End', 'PageUp', 'PageDown'] as $k) {
            $this->assertStringContainsString($k, $this->js, "missing key handler: $k");
        }
    }

    public function testLiveRegionPresent(): void
    {
        $this->assertStringContainsString('aria-live', $this->js);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter CalendarA11y private/tests`
Expected: FAIL

- [ ] **Step 3: Read rezervacia.js, then implement**

Implementer: read `public/assets/js/rezervacia.js` fully. Locate (a) where the grid container is created/queried, (b) the per-day cell creation loop, (c) the day-click handler. Apply:

1. On grid container creation: `grid.setAttribute('role','grid'); grid.setAttribute('aria-label','Kalendár dostupných termínov');`
2. Wrap each week row element with `row.setAttribute('role','row');` (if rows aren't separate elements, set `role="row"` on a 7-cell wrapper; if the grid is a flat CSS grid, add a visually-structural `role="row"` per 7 cells — minimal: set grid `role="grid"` and each cell `role="gridcell"`, acceptable per ARIA APG when rows are implied; still add row wrappers if the DOM already groups weeks).
3. Each day cell: `cell.setAttribute('role','gridcell');` plus:
   - available: `cell.setAttribute('aria-label', dayName + ' ' + dayNum + '. ' + monthName + ', dostupné, ' + freeCount + ' voľných termínov'); cell.setAttribute('tabindex', isActive ? '0' : '-1');`
   - unavailable: `cell.setAttribute('aria-disabled','true'); cell.setAttribute('aria-label', dayName + ' ' + dayNum + '. ' + monthName + ', ' + (isPast ? 'v minulosti' : 'plne obsadené'));`
   - selected day: `cell.setAttribute('aria-selected','true');` (and `'false'` on others)
4. Add a visually-hidden live region once (near the calendar): create `<div class="sr-only" aria-live="polite" id="cal-live"></div>` if absent; on day select set its `textContent` to `'Vybraný ' + dayNum + '. ' + monthName + '. ' + freeCount + ' voľných termínov nižšie.'`
5. Add `grid.addEventListener('keydown', ...)` implementing the key map above. Move focus with `nextCell.focus()` and update roving tabindex (focused → `0`, previously focused → `-1`). Enter/Space → trigger the existing day-select logic for the focused cell.

Use the existing `MONTH_NAMES` / day-name constants already in the file (the summary notes `MONTH_NAMES` exists). Do not re-declare them.

- [ ] **Step 4: Add focus-visible CSS**

In `public/assets/css/main.css`, add near the calendar styles:

```css
.calendar__grid [role="gridcell"]:focus-visible {
  outline: 3px solid var(--c-accent, #D88BBE);
  outline-offset: 2px;
  border-radius: 6px;
}
.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px;
  overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; border: 0;
}
```

(If `.sr-only` already exists in main.css, do not duplicate — verify with grep first.)

- [ ] **Step 5: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter CalendarA11y private/tests`
Expected: PASS (3 tests)

- [ ] **Step 6: Manual keyboard smoke**

Start dev server, open `/rezervacia`, pick a package to reach the calendar. Tab to the calendar, confirm: arrows move focus between days, focused day has visible accent outline, Enter selects, screen-reader live region text updates (inspect `#cal-live` textContent in DevTools). Report what was observed.

- [ ] **Step 7: Lint + commit**

```bash
/opt/homebrew/bin/php -l private/tests/unit/CalendarA11yTest.php
node --check public/assets/js/rezervacia.js 2>/dev/null || echo "(node not present — skip JS syntax check)"
git add public/assets/js/rezervacia.js public/assets/css/main.css private/tests/unit/CalendarA11yTest.php
git commit -m "$(cat <<'EOF'
feat: calendar keyboard navigation + ARIA grid semantics (roadmap A4)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 7: Multi-device favicon set + web app manifest

**Files:**
- Create: `private/scripts/gen-favicons.php` (GD generator from `public/assets/img/logo.png`)
- Create (generated, committed): `public/favicon.ico`, `public/favicon-32.png`, `public/favicon-16.png`, `public/apple-touch-icon.png`, `public/icon-192.png`, `public/icon-512.png`
- Create: `public/manifest.webmanifest`
- Modify: `private/templates/head.php` (icon `<link>` set + manifest)
- Test: `private/tests/unit/FaviconAssetsTest.php`

**Context:** `public/assets/img/logo.png` (2.2 MB) is the source. GD is available; ImageMagick is not. `.ico` cannot be written by GD directly — generate PNGs with GD, and write a minimal single-image 32×32 `favicon.ico` by wrapping the 32×32 PNG in an ICO container (the script includes a tiny ICO writer). All icons square; logo is letterboxed onto a `#FBEEF5` (brand theme) background to avoid distortion.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class FaviconAssetsTest extends TestCase
{
    private string $pub;

    protected function setUp(): void
    {
        $this->pub = \dirname(__DIR__, 2) . '/public';
    }

    public function testIconFilesExistWithCorrectDimensions(): void
    {
        $expect = [
            '/favicon-16.png' => 16, '/favicon-32.png' => 32,
            '/apple-touch-icon.png' => 180, '/icon-192.png' => 192, '/icon-512.png' => 512,
        ];
        foreach ($expect as $rel => $size) {
            $f = $this->pub . $rel;
            $this->assertFileExists($f, "$rel missing");
            [$w, $h] = getimagesize($f);
            $this->assertSame($size, $w, "$rel width");
            $this->assertSame($size, $h, "$rel height");
        }
        $this->assertFileExists($this->pub . '/favicon.ico');
    }

    public function testManifestIsValidJson(): void
    {
        $m = json_decode((string) file_get_contents($this->pub . '/manifest.webmanifest'), true);
        $this->assertIsArray($m);
        $this->assertSame('KUKO detský svet', $m['name']);
        $this->assertNotEmpty($m['icons']);
    }

    public function testHeadReferencesIcons(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 2) . '/private/templates/head.php');
        $this->assertStringContainsString('apple-touch-icon', $h);
        $this->assertStringContainsString('manifest.webmanifest', $h);
        $this->assertStringContainsString('rel="icon"', $h);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter FaviconAssets private/tests`
Expected: FAIL (icon files absent)

- [ ] **Step 3: Write the generator script**

Create `private/scripts/gen-favicons.php`:

```php
<?php
declare(strict_types=1);
// Generates favicon/app-icon set from public/assets/img/logo.png using GD.
$root = dirname(__DIR__, 1);
$pub  = dirname(__DIR__) . '/../public';
$pub  = realpath(dirname(__DIR__, 1)) ? dirname(__DIR__, 1) . '/../public' : $pub;
$pub  = dirname(__DIR__, 1); // private/
$pub  = dirname($pub) . '/public';
$src  = $pub . '/assets/img/logo.png';
if (!is_file($src)) { fwrite(STDERR, "logo.png not found at $src\n"); exit(1); }

$logo = imagecreatefrompng($src);
$lw = imagesx($logo); $lh = imagesy($logo);

function render(int $size, $logo, int $lw, int $lh): \GdImage {
    $img = imagecreatetruecolor($size, $size);
    imagealphablending($img, false);
    imagesavealpha($img, true);
    $bg = imagecolorallocate($img, 0xFB, 0xEE, 0xF5); // brand theme #FBEEF5
    imagefilledrectangle($img, 0, 0, $size, $size, $bg);
    imagealphablending($img, true);
    $pad = (int) round($size * 0.10);
    $box = $size - 2 * $pad;
    $scale = min($box / $lw, $box / $lh);
    $dw = (int) round($lw * $scale); $dh = (int) round($lh * $scale);
    $dx = (int) (($size - $dw) / 2); $dy = (int) (($size - $dh) / 2);
    imagecopyresampled($img, $logo, $dx, $dy, 0, 0, $dw, $dh, $lw, $lh);
    return $img;
}

$sizes = [16 => 'favicon-16.png', 32 => 'favicon-32.png', 180 => 'apple-touch-icon.png',
          192 => 'icon-192.png', 512 => 'icon-512.png'];
foreach ($sizes as $sz => $name) {
    $im = render($sz, $logo, $lw, $lh);
    imagepng($im, $pub . '/' . $name, 9);
    fwrite(STDOUT, "wrote $name ({$sz}px)\n");
}

// Minimal single-image ICO (32x32, 32bpp BMP-in-ICO)
$ico32 = render(32, $logo, $lw, $lh);
ob_start(); imagepng($ico32); $png = ob_get_clean();
// ICO with embedded PNG (supported by all modern browsers, simplest reliable form)
$ico  = pack('vvv', 0, 1, 1);                       // reserved, type=1(ico), count=1
$ico .= pack('CCCC', 32, 32, 0, 0);                 // w,h,colors,reserved
$ico .= pack('vv', 1, 32);                          // planes, bpp
$ico .= pack('VV', strlen($png), 22);               // size, offset
$ico .= $png;
file_put_contents($pub . '/favicon.ico', $ico);
fwrite(STDOUT, "wrote favicon.ico\n");
```

Note: keep the `$pub` resolution simple — replace the messy lines with a single clean resolver. Implementer: use exactly:

```php
$pub = dirname(__DIR__, 2) . '/public';
$src = $pub . '/assets/img/logo.png';
```

(Drop the duplicated `$pub`/`$root` scratch lines from the draft above; they were illustrative.)

- [ ] **Step 4: Run the generator**

Run: `/opt/homebrew/bin/php private/scripts/gen-favicons.php`
Expected stdout: `wrote favicon-16.png (16px)` … `wrote favicon.ico`

- [ ] **Step 5: Create manifest.webmanifest**

Create `public/manifest.webmanifest`:

```json
{
  "name": "KUKO detský svet",
  "short_name": "KUKO",
  "description": "Detská herňa a kaviareň v Piešťanoch",
  "start_url": "/",
  "display": "standalone",
  "background_color": "#FFF8EE",
  "theme_color": "#FBEEF5",
  "icons": [
    { "src": "/icon-192.png", "sizes": "192x192", "type": "image/png" },
    { "src": "/icon-512.png", "sizes": "512x512", "type": "image/png" }
  ]
}
```

- [ ] **Step 6: Wire icons into head.php**

In `private/templates/head.php`, replace the single `<!-- Icons -->` `<link rel="icon" href="/favicon.ico">` line with:

```html
<!-- Icons -->
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" type="image/png" sizes="32x32" href="/favicon-32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/favicon-16.png">
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
<link rel="manifest" href="/manifest.webmanifest">
```

- [ ] **Step 7: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter FaviconAssets private/tests`
Expected: PASS (3 tests)

- [ ] **Step 8: Lint + commit (assets included)**

```bash
/opt/homebrew/bin/php -l private/scripts/gen-favicons.php && /opt/homebrew/bin/php -l private/templates/head.php
git add private/scripts/gen-favicons.php public/favicon.ico public/favicon-16.png public/favicon-32.png public/apple-touch-icon.png public/icon-192.png public/icon-512.png public/manifest.webmanifest private/templates/head.php private/tests/unit/FaviconAssetsTest.php
git commit -m "$(cat <<'EOF'
feat: multi-device favicon set + web app manifest (roadmap F1/F2)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 8: Dedicated OG cover image

**Files:**
- Modify: `private/scripts/gen-favicons.php` → rename concept to also emit `og-cover.jpg`, OR add to the same script (preferred: same script, one more block)
- Create (generated, committed): `public/assets/img/og-cover.jpg` (1200×630)
- Modify: `private/templates/head.php` (default `og:image` → og-cover unless `$ogImage` passed)
- Test: `private/tests/unit/OgCoverTest.php`

**Context:** Current default `$ogImageUrl` = `hero.jpg` (wrong aspect ratio). Generate a 1200×630 branded cover: brand background `#FBEEF5`, centered logo, site URL text at bottom. GD `imagettftext` needs a font — reuse `public/assets/fonts/NunitoSans.ttf`.

- [ ] **Step 1: Write the failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class OgCoverTest extends TestCase
{
    public function testOgCoverExistsAt1200x630(): void
    {
        $f = \dirname(__DIR__, 2) . '/public/assets/img/og-cover.jpg';
        $this->assertFileExists($f);
        [$w, $h] = getimagesize($f);
        $this->assertSame(1200, $w);
        $this->assertSame(630, $h);
    }

    public function testHeadDefaultsOgImageToOgCover(): void
    {
        $h = file_get_contents(\dirname(__DIR__, 2) . '/private/templates/head.php');
        $this->assertStringContainsString('og-cover.jpg', $h);
    }
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter OgCover private/tests`
Expected: FAIL

- [ ] **Step 3: Add og-cover generation block to gen-favicons.php**

Append to `private/scripts/gen-favicons.php` before the end:

```php
// --- OG cover 1200x630 ---
$ogW = 1200; $ogH = 630;
$og = imagecreatetruecolor($ogW, $ogH);
$bg = imagecolorallocate($og, 0xFB, 0xEE, 0xF5);
imagefilledrectangle($og, 0, 0, $ogW, $ogH, $bg);
$boxH = (int) ($ogH * 0.52);
$scale = min(($ogW * 0.5) / $lw, $boxH / $lh);
$dw = (int) ($lw * $scale); $dh = (int) ($lh * $scale);
imagecopyresampled($og, $logo, (int) (($ogW - $dw) / 2), (int) ($ogH * 0.16),
                   0, 0, $dw, $dh, $lw, $lh);
$font = dirname(__DIR__, 1) . '/../public/assets/fonts/NunitoSans.ttf';
$font = dirname(__DIR__, 2) . '/public/assets/fonts/NunitoSans.ttf';
if (is_file($font)) {
    $ink = imagecolorallocate($og, 0x3D, 0x3D, 0x3D);
    $txt = 'kuko-detskysvet.sk';
    $bb = imagettfbbox(34, 0, $font, $txt);
    $tw = $bb[2] - $bb[0];
    imagettftext($og, 34, 0, (int) (($ogW - $tw) / 2), (int) ($ogH * 0.88), $ink, $font, $txt);
}
imagejpeg($og, dirname(__DIR__, 2) . '/public/assets/img/og-cover.jpg', 88);
fwrite(STDOUT, "wrote og-cover.jpg (1200x630)\n");
```

(Implementer: keep only the correct single `$font = dirname(__DIR__, 2) . '/public/assets/fonts/NunitoSans.ttf';` line — remove the duplicated draft line above it.)

- [ ] **Step 4: Run the generator**

Run: `/opt/homebrew/bin/php private/scripts/gen-favicons.php`
Expected: includes `wrote og-cover.jpg (1200x630)`

- [ ] **Step 5: Default og:image to og-cover in head.php**

In `private/templates/head.php`, change:

```php
$ogImageUrl = $ogImage ?? ($baseUrl . '/assets/img/hero.jpg');
```

to:

```php
$ogImageUrl = $ogImage ?? ($baseUrl . '/assets/img/og-cover.jpg');
```

- [ ] **Step 6: Run test to verify it passes**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter OgCover private/tests`
Expected: PASS (2 tests)

- [ ] **Step 7: Lint + commit**

```bash
/opt/homebrew/bin/php -l private/scripts/gen-favicons.php && /opt/homebrew/bin/php -l private/templates/head.php
git add private/scripts/gen-favicons.php public/assets/img/og-cover.jpg private/templates/head.php private/tests/unit/OgCoverTest.php
git commit -m "$(cat <<'EOF'
feat: dedicated 1200x630 OG cover image (roadmap F3)

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

### Task 9: Sprint 1 regression + roadmap bookkeeping + deploy

**Files:**
- Modify: `docs/plans/2026-05-14-roadmap-quality.md` (check off shipped items, add "Sprint 1 shipped" note)
- Modify: `private/scripts` deploy note — N/A (deploy is manual lftp as documented)

- [ ] **Step 1: Full regression**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar private/tests`
Expected: all green (previous 110 + new Sprint 1 tests)

- [ ] **Step 2: Lint sweep**

```bash
find private/lib private/templates private/cron public -name '*.php' -print0 | xargs -0 -n1 /opt/homebrew/bin/php -l | grep -v 'No syntax errors' || echo "ALL CLEAN"
```

- [ ] **Step 3: Dev smoke**

Start dev server (existing `private/scripts` / router via `php -S`), curl `/`, `/rezervacia`, `/faq`, `/ochrana-udajov`, `/admin/login` → expect 200; `/admin` unauth → 302.

- [ ] **Step 4: Update roadmap-quality.md**

Mark these as `[x]`: B1 brute-force, B1 login CSRF (§B1), B2 HSTS, B4 retention cron, B4 right-to-be-forgotten, B4 data subject access, A4 calendar a11y items, F1 favicon set, F2 manifest, F3 og-cover. Add a blockquote near the top:

```markdown
> **Sprint 1 shipped** (2026-05-15): admin login CSRF + brute-force throttle + auth audit log; HSTS; GDPR retention cron + anonymize action + email export; calendar keyboard/ARIA a11y; multi-device favicons + manifest + dedicated OG cover. Owner action items remaining: Lighthouse baseline, axe scan, Google Business Profile, HSTS preload registration.
```

- [ ] **Step 5: Commit bookkeeping**

```bash
git add docs/plans/2026-05-14-roadmap-quality.md
git commit -m "$(cat <<'EOF'
docs: mark Sprint 1 quality items shipped

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

- [ ] **Step 6: Production deploy (lftp mirror + cron registration note)**

Deploy code via the documented lftp SFTP mirror (host `kuko-detskysvet.sk:22`, user `filip.kuko-detskysvet.sk`). No DB migration this sprint (no schema change). After deploy, verify with the staff-bypass cookie that public pages still 503 for the public (maintenance gate untouched) and `robots.txt` still `Disallow: /` (public_indexing untouched). Report the cron command the owner must register in the WebSupport panel:
`/usr/bin/php /path/to/private/cron/retention.php` monthly.

---

## Owner Action Items (not built — surface in final report)

1. **P1 Lighthouse baseline** — owner runs Lighthouse (mobile+desktop) on staging-with-staff-cookie, saves screenshots to `docs/audits/2026-05-15-lighthouse-baseline-*.png`.
2. **A1 axe DevTools scan** — owner runs axe extension on `/`, `/rezervacia` steps, `/faq`, `/ochrana-udajov`, `/admin`; logs findings as a follow-up backlog.
3. **S3 Google Business Profile** — owner creates/verifies GBP (category "Children's amusement center", hours Mon–Sun 9:00–20:00, NAP matching the site exactly).
4. **B2 HSTS preload registration** — only AFTER HSTS header is confirmed live and stable in prod for a few days, owner submits the domain at hstspreload.org.

## Self-Review

- **Spec coverage:** Sprint 1 = roadmap's own "top 8" (B1 brute-force, B2 HSTS, P1 baseline=owner, A1+A4 = A4 built/A1 owner, S3=owner, B4 retention, F1 favicons, F3 og-cover) + the documented B1 login-CSRF quick win. Non-code items explicitly carved out as Owner Action Items — covered, not silently dropped.
- **Placeholder scan:** the two generator-script blocks had illustrative duplicate `$pub`/`$font` lines; each is followed by an explicit "implementer: use exactly this single line" instruction — no ambiguity left.
- **Type consistency:** `LoginThrottle` methods `permit/recordFailure/recordSuccess` consistent across test + impl + call site. `Privacy` methods `anonymizeReservation/purgeOlderThan/exportByEmail` consistent across test + impl + cron + routes.
