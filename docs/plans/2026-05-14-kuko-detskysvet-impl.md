# KUKO detský svet — Implementation Plan

**Goal:** Postaviť kompletný web kuko-detskysvet.sk podľa specu — one-pager s rezervačným modálom pre 3 balíčky osláv, DB + admin, hosted on WebSupport.

**Architecture:** Vanilla HTML/CSS/JS frontend + PHP 8.1 backend (PDO, PHPMailer, Leaflet, reCAPTCHA v3). Žiadny build step, žiadny framework. Front controller v `public/index.php`, business logika v `private/lib/`, šablóny v `private/templates/`, DB MariaDB/MySQL.

**Tech Stack:** PHP 8.1+, MySQL/MariaDB, PDO, PHPMailer (vendored), Vanilla JS modules, Leaflet + OSM, Google reCAPTCHA v3.

**Spec:** `docs/specs/2026-05-14-kuko-detskysvet-design.md`

---

## Milestones overview

| # | Milestone                              | Tasks |
|---|----------------------------------------|-------|
| 1 | Backend foundation (Config, Db, Router, Renderer, front controller, tests) | 8 |
| 2 | Frontend statika (HTML/CSS všetkých 7 sekcií + privacy page) | 12 |
| 3 | Frontend interakcia (smooth scroll, hamburger, lightbox, mapa, scroll reveal) | 5 |
| 4 | Cookie consent (banner + localStorage) | 2 |
| 5 | Rezervácie backend (Csrf, RateLimit, Recaptcha, Reservation model, Mailer, migrácia, endpoint) | 10 |
| 6 | Rezervácie frontend (modal, validácia, reCAPTCHA load, submit) | 5 |
| 7 | Admin panel (Basic Auth, list, detail, status change, audit) | 6 |
| 8 | Polish & deploy (WebP, SEO, sitemap, deploy docs) | 5 |

---

## M1 — Backend foundation

### Task 1.1: Test harness setup (PHPUnit bez Composeru)

**Files:**
- Create: `private/tests/bootstrap.php`
- Create: `private/tests/run.php`
- Create: `phpunit.xml`

- [ ] **Step 1: Stiahnuť PHPUnit phar**

Run: `mkdir -p private/lib/vendor && curl -L -o private/lib/vendor/phpunit.phar https://phar.phpunit.de/phpunit-10.5.phar && chmod +x private/lib/vendor/phpunit.phar`
Expected: ~5MB súbor stiahnutý.

- [ ] **Step 2: Vytvoriť phpunit.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="private/tests/bootstrap.php" colors="true" failOnWarning="true" failOnRisky="true">
    <testsuites>
        <testsuite name="unit">
            <directory>private/tests/unit</directory>
        </testsuite>
        <testsuite name="integration">
            <directory>private/tests/integration</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

- [ ] **Step 3: Bootstrap test prostredia**

```php
<?php
// private/tests/bootstrap.php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__, 2));
define('TESTING', true);

require APP_ROOT . '/private/lib/autoload.php';
```

- [ ] **Step 4: Autoload (PSR-4 ručne, bez Composeru)**

```php
<?php
// private/lib/autoload.php
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    $prefix = 'Kuko\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($file)) require $file;
});
```

- [ ] **Step 5: Test runner shortcut**

```php
<?php
// private/tests/run.php — wrapper aby sme nemuseli písať dlhý path
passthru(escapeshellcmd(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/../lib/vendor/phpunit.phar') . ' -c ' . escapeshellarg(dirname(__DIR__, 2) . '/phpunit.xml') . ' ' . implode(' ', array_map('escapeshellarg', array_slice($argv, 1))));
```

- [ ] **Step 6: Sanity test**

```php
<?php
// private/tests/unit/SanityTest.php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;

final class SanityTest extends TestCase
{
    public function testTrue(): void { $this->assertTrue(true); }
}
```

- [ ] **Step 7: Run + verify**

Run: `php private/tests/run.php`
Expected: `OK (1 test, 1 assertion)`

- [ ] **Step 8: Update .gitignore**

```
# Test artifacts
/private/lib/vendor/phpunit.phar
/.phpunit.cache/
```

- [ ] **Step 9: Commit**

```bash
git add phpunit.xml private/tests/ private/lib/autoload.php .gitignore
git commit -m "chore: add PHPUnit test harness without Composer"
```

---

### Task 1.2: Config loader

**Files:**
- Create: `private/lib/Config.php`
- Create: `private/tests/unit/ConfigTest.php`
- Create: `private/tests/fixtures/config.test.php`

- [ ] **Step 1: Test fixture**

```php
<?php
// private/tests/fixtures/config.test.php
return [
    'app' => ['env' => 'test', 'debug' => true],
    'db'  => ['host' => 'localhost', 'name' => 'kuko_test'],
];
```

- [ ] **Step 2: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
    }

    public function testGetTopLevel(): void
    {
        $this->assertSame('test', Config::get('app.env'));
    }

    public function testGetNested(): void
    {
        $this->assertSame('kuko_test', Config::get('db.name'));
    }

    public function testGetDefault(): void
    {
        $this->assertSame('fallback', Config::get('missing.key', 'fallback'));
    }

    public function testMissingWithoutDefaultThrows(): void
    {
        $this->expectException(\RuntimeException::class);
        Config::get('totally.missing');
    }
}
```

- [ ] **Step 3: Run test — verify FAIL**

Run: `php private/tests/run.php --filter ConfigTest`
Expected: Errors — `Config not found`.

- [ ] **Step 4: Implement Config**

```php
<?php
// private/lib/Config.php
declare(strict_types=1);
namespace Kuko;

final class Config
{
    private static ?array $data = null;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            throw new \RuntimeException("Config file not found: $path");
        }
        self::$data = require $path;
    }

    public static function reset(): void { self::$data = null; }

    public static function get(string $key, mixed $default = null): mixed
    {
        if (self::$data === null) {
            throw new \RuntimeException('Config not loaded');
        }
        $parts = explode('.', $key);
        $value = self::$data;
        foreach ($parts as $p) {
            if (!is_array($value) || !array_key_exists($p, $value)) {
                if (func_num_args() < 2) {
                    throw new \RuntimeException("Config key missing: $key");
                }
                return $default;
            }
            $value = $value[$p];
        }
        return $value;
    }
}
```

- [ ] **Step 5: Run tests — verify PASS**

Run: `php private/tests/run.php --filter ConfigTest`
Expected: `OK (4 tests)`.

- [ ] **Step 6: Commit**

```bash
git add private/lib/Config.php private/tests/unit/ConfigTest.php private/tests/fixtures/config.test.php
git commit -m "feat(lib): add Config loader with dot-notation access"
```

---

### Task 1.3: Db (PDO wrapper)

**Files:**
- Create: `private/lib/Db.php`
- Create: `private/tests/integration/DbTest.php`

- [ ] **Step 1: Failing integration test (SQLite in-memory)**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use PHPUnit\Framework\TestCase;

final class DbTest extends TestCase
{
    private Db $db;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec('CREATE TABLE t (id INTEGER PRIMARY KEY, name TEXT)');
    }

    public function testInsertAndFetch(): void
    {
        $id = $this->db->insert('INSERT INTO t (name) VALUES (?)', ['alice']);
        $this->assertGreaterThan(0, $id);
        $row = $this->db->one('SELECT * FROM t WHERE id = ?', [$id]);
        $this->assertSame('alice', $row['name']);
    }

    public function testFetchAll(): void
    {
        $this->db->insert('INSERT INTO t (name) VALUES (?)', ['a']);
        $this->db->insert('INSERT INTO t (name) VALUES (?)', ['b']);
        $rows = $this->db->all('SELECT name FROM t ORDER BY id');
        $this->assertSame(['a', 'b'], array_column($rows, 'name'));
    }

    public function testUpdate(): void
    {
        $id = $this->db->insert('INSERT INTO t (name) VALUES (?)', ['x']);
        $affected = $this->db->execStmt('UPDATE t SET name = ? WHERE id = ?', ['y', $id]);
        $this->assertSame(1, $affected);
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `php private/tests/run.php --filter DbTest`
Expected: `Class Kuko\Db not found`.

- [ ] **Step 3: Implement Db**

```php
<?php
// private/lib/Db.php
declare(strict_types=1);
namespace Kuko;

final class Db
{
    public function __construct(private \PDO $pdo) {}

    public static function fromConfig(): self
    {
        $cfg = Config::get('db');
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $cfg['host'], $cfg['name'], $cfg['charset'] ?? 'utf8mb4');
        return new self(new \PDO($dsn, $cfg['user'], $cfg['pass'], [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            \PDO::ATTR_EMULATE_PREPARES   => false,
        ]));
    }

    public static function fromDsn(string $dsn): self
    {
        return new self(new \PDO($dsn, null, null, [
            \PDO::ATTR_ERRMODE            => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]));
    }

    public function exec(string $sql): int { return (int) $this->pdo->exec($sql); }

    public function execStmt(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function insert(string $sql, array $params = []): int
    {
        $this->execStmt($sql, $params);
        return (int) $this->pdo->lastInsertId();
    }

    public function one(string $sql, array $params = []): ?array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row === false ? null : $row;
    }

    public function all(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function pdo(): \PDO { return $this->pdo; }
}
```

- [ ] **Step 4: Run — verify PASS**

Run: `php private/tests/run.php --filter DbTest`
Expected: `OK (3 tests)`.

- [ ] **Step 5: Commit**

```bash
git add private/lib/Db.php private/tests/integration/DbTest.php
git commit -m "feat(lib): add Db PDO wrapper with named methods"
```

---

### Task 1.4: Router

**Files:**
- Create: `private/lib/Router.php`
- Create: `private/tests/unit/RouterTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Router;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testStaticRoute(): void
    {
        $r = new Router();
        $r->get('/', fn() => 'home');
        $match = $r->match('GET', '/');
        $this->assertNotNull($match);
        $this->assertSame('home', ($match->handler)());
    }

    public function testParamRoute(): void
    {
        $r = new Router();
        $r->get('/admin/reservation/{id}', fn($params) => 'detail-' . $params['id']);
        $match = $r->match('GET', '/admin/reservation/42');
        $this->assertSame('detail-42', ($match->handler)($match->params));
    }

    public function testNoMatch(): void
    {
        $r = new Router();
        $r->get('/', fn() => 'home');
        $this->assertNull($r->match('GET', '/missing'));
    }

    public function testMethodMismatch(): void
    {
        $r = new Router();
        $r->get('/', fn() => 'home');
        $this->assertNull($r->match('POST', '/'));
    }

    public function testTrailingSlashIgnored(): void
    {
        $r = new Router();
        $r->get('/ochrana-udajov', fn() => 'p');
        $this->assertNotNull($r->match('GET', '/ochrana-udajov/'));
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `php private/tests/run.php --filter RouterTest`
Expected: Class not found.

- [ ] **Step 3: Implement Router**

```php
<?php
// private/lib/Router.php
declare(strict_types=1);
namespace Kuko;

final class RouteMatch
{
    public function __construct(public readonly \Closure $handler, public readonly array $params) {}
}

final class Router
{
    /** @var array<int,array{method:string,pattern:string,handler:\Closure}> */
    private array $routes = [];

    public function get(string $pattern, \Closure $handler): void  { $this->add('GET',  $pattern, $handler); }
    public function post(string $pattern, \Closure $handler): void { $this->add('POST', $pattern, $handler); }

    private function add(string $method, string $pattern, \Closure $handler): void
    {
        $this->routes[] = ['method' => $method, 'pattern' => $pattern, 'handler' => $handler];
    }

    public function match(string $method, string $path): ?RouteMatch
    {
        $path = '/' . trim($path, '/');
        if ($path === '/' && $method === 'GET') {
            foreach ($this->routes as $r) {
                if ($r['method'] === 'GET' && $r['pattern'] === '/') return new RouteMatch($r['handler'], []);
            }
        }
        foreach ($this->routes as $r) {
            if ($r['method'] !== $method) continue;
            $regex = '#^' . preg_replace('#\{([a-z_][a-z0-9_]*)\}#i', '(?P<$1>[^/]+)', rtrim($r['pattern'], '/')) . '/?$#';
            if (preg_match($regex, $path, $m)) {
                $params = array_filter($m, fn($k) => !is_int($k), ARRAY_FILTER_USE_KEY);
                return new RouteMatch($r['handler'], $params);
            }
        }
        return null;
    }
}
```

- [ ] **Step 4: Run — verify PASS**

Run: `php private/tests/run.php --filter RouterTest`
Expected: `OK (5 tests)`.

- [ ] **Step 5: Commit**

```bash
git add private/lib/Router.php private/tests/unit/RouterTest.php
git commit -m "feat(lib): add Router with pattern matching"
```

---

### Task 1.5: Renderer (template engine)

**Files:**
- Create: `private/lib/Renderer.php`
- Create: `private/tests/unit/RendererTest.php`
- Create: `private/tests/fixtures/templates/hello.php`

- [ ] **Step 1: Fixture template**

```php
<?php /** @var string $name */ ?>
<h1>Hello, <?= htmlspecialchars($name, ENT_QUOTES|ENT_HTML5, 'UTF-8') ?></h1>
```

- [ ] **Step 2: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Renderer;
use PHPUnit\Framework\TestCase;

final class RendererTest extends TestCase
{
    public function testRendersTemplate(): void
    {
        $r = new Renderer(__DIR__ . '/../fixtures/templates');
        $html = $r->render('hello', ['name' => 'KUKO']);
        $this->assertStringContainsString('<h1>Hello, KUKO</h1>', $html);
    }

    public function testEscapesData(): void
    {
        $r = new Renderer(__DIR__ . '/../fixtures/templates');
        $html = $r->render('hello', ['name' => '<script>x</script>']);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    public function testMissingTemplateThrows(): void
    {
        $r = new Renderer(__DIR__ . '/../fixtures/templates');
        $this->expectException(\RuntimeException::class);
        $r->render('nonexistent');
    }
}
```

- [ ] **Step 3: Run — verify FAIL**

Run: `php private/tests/run.php --filter RendererTest`
Expected: Class not found.

- [ ] **Step 4: Implement Renderer**

```php
<?php
// private/lib/Renderer.php
declare(strict_types=1);
namespace Kuko;

final class Renderer
{
    public function __construct(private string $baseDir) {}

    public function render(string $template, array $data = []): string
    {
        $file = $this->baseDir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public static function escape(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
```

- [ ] **Step 5: Run — verify PASS**

Run: `php private/tests/run.php --filter RendererTest`
Expected: `OK (3 tests)`.

- [ ] **Step 6: Helper function `e()` pre šablóny**

Create: `private/lib/helpers.php`

```php
<?php
declare(strict_types=1);

if (!function_exists('e')) {
    function e(mixed $v): string
    {
        return htmlspecialchars((string) $v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
```

A v `autoload.php` na koniec pridať: `require __DIR__ . '/helpers.php';`

- [ ] **Step 7: Commit**

```bash
git add private/lib/Renderer.php private/lib/helpers.php private/lib/autoload.php private/tests/unit/RendererTest.php private/tests/fixtures/templates/hello.php
git commit -m "feat(lib): add Renderer template engine + e() helper"
```

---

### Task 1.6: Front controller + bootstrap

**Files:**
- Create: `public/index.php`
- Create: `private/lib/App.php`
- Modify: `config/config.example.php` (pridať `recaptcha`, `admin`, `security` sekcie)

- [ ] **Step 1: Rozšíriť config template**

Modify: `config/config.example.php` — pridať do návratového array-u nové sekcie:

```php
'recaptcha' => [
    'site_key'   => '',
    'secret_key' => '',
    'min_score'  => 0.5,
],
'admin' => [
    'session_lifetime' => 3600,
],
'security' => [
    'ip_hash_secret'       => '',
    'rate_limit_per_hour'  => 3,
    'csrf_lifetime'        => 3600,
],
'social' => [
    'facebook' => '',
    'instagram' => '',
],
```

- [ ] **Step 2: Vytvoriť reálny config.php (gitignored) ako kópiu**

Run: `cp config/config.example.php config/config.php`
(Pre dev sa vyplní neskôr. Súbor je v .gitignore.)

- [ ] **Step 3: App bootstrap**

```php
<?php
// private/lib/App.php
declare(strict_types=1);
namespace Kuko;

final class App
{
    public static function bootstrap(): void
    {
        define('APP_ROOT', dirname(__DIR__, 2));
        require APP_ROOT . '/private/lib/autoload.php';
        Config::load(APP_ROOT . '/config/config.php');
        date_default_timezone_set(Config::get('app.tz', 'Europe/Bratislava'));
        if (Config::get('app.debug', false)) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
```

- [ ] **Step 4: Front controller**

```php
<?php
// public/index.php
declare(strict_types=1);

require dirname(__DIR__) . '/private/lib/App.php';
\Kuko\App::bootstrap();

use Kuko\Router;
use Kuko\Renderer;

$router = new Router();
$renderer = new Renderer(APP_ROOT . '/private/templates');

$router->get('/', function () use ($renderer) {
    echo $renderer->render('pages/home');
});

$router->get('/ochrana-udajov', function () use ($renderer) {
    echo $renderer->render('pages/privacy');
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$match = $router->match($_SERVER['REQUEST_METHOD'], $path);

if ($match === null) {
    http_response_code(404);
    echo $renderer->render('pages/404');
    return;
}

($match->handler)($match->params);
```

- [ ] **Step 5: Placeholder template-y aby load fungoval**

Create `private/templates/pages/home.php`, `pages/privacy.php`, `pages/404.php` s minimálnym `<h1>` obsahom.

```php
<?php // private/templates/pages/home.php ?>
<!doctype html>
<html lang="sk">
<head><meta charset="utf-8"><title>KUKO — placeholder</title></head>
<body><h1>KUKO detský svet — coming soon</h1></body>
</html>
```

(Analogicky pre privacy a 404.)

- [ ] **Step 6: Local smoke test cez PHP built-in server**

Run: `php -S 127.0.0.1:8000 -t public/`
Otvoriť v prehliadači `http://127.0.0.1:8000/` — placeholder homepage.
Otvoriť `http://127.0.0.1:8000/missing` — 404.

- [ ] **Step 7: Commit**

```bash
git add config/config.example.php private/lib/App.php public/index.php private/templates/pages/
git commit -m "feat: front controller + App bootstrap + placeholder templates"
```

---

### Task 1.7: Update .gitignore + initial directory structure

**Files:**
- Modify: `.gitignore`
- Create: `private/logs/.gitkeep`
- Modify: `private/cron/.gitkeep` (ensure exists)

- [ ] **Step 1: Doplniť .gitignore**

Pridať do `.gitignore`:

```
# Tests
/.phpunit.cache/
/private/lib/vendor/phpunit.phar

# Logs
/private/logs/*
!/private/logs/.gitkeep

# Auth
/public/admin/.htpasswd
```

- [ ] **Step 2: Vytvoriť `.gitkeep` v prázdnych priečinkoch**

Run: `touch private/logs/.gitkeep private/cron/.gitkeep`

- [ ] **Step 3: Commit**

```bash
git add .gitignore private/logs/.gitkeep private/cron/.gitkeep
git commit -m "chore: gitignore tests, logs, htpasswd; keep empty dirs"
```

---

### Task 1.8: Initial all-green check

- [ ] **Step 1: Spustiť všetky testy**

Run: `php private/tests/run.php`
Expected: všetky testy zelené, žiadne warnings.

- [ ] **Step 2: Spustiť PHP linter**

Run: `find private/lib public -name "*.php" -exec php -l {} \;`
Expected: každý súbor `No syntax errors detected`.

---

## M2 — Frontend statika

### Task 2.1: Base layout + head (meta + Schema.org)

**Files:**
- Create: `private/templates/layout.php`
- Create: `private/templates/head.php`
- Modify: `private/templates/pages/home.php`

- [ ] **Step 1: head.php — meta + OG + Schema.org**

```php
<?php
/** @var string $title */
/** @var string $description */
$title = $title ?? 'KUKO detský svet — herňa a kaviareň v Piešťanoch';
$description = $description ?? 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, káva pre rodičov, oslavy na mieru. Pondelok – Nedeľa 9:00 – 20:00.';
?>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e($description) ?>">
<meta name="theme-color" content="#FBEEF5">
<link rel="canonical" href="https://kuko-detskysvet.sk/">
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($title) ?>">
<meta property="og:description" content="<?= e($description) ?>">
<meta property="og:image" content="https://kuko-detskysvet.sk/assets/img/og.jpg">
<meta property="og:url" content="https://kuko-detskysvet.sk/">
<meta property="og:locale" content="sk_SK">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" href="/favicon.ico">
<link rel="preload" href="/assets/fonts/NunitoSans.ttf" as="font" type="font/ttf" crossorigin>
<link rel="stylesheet" href="/assets/css/main.css">
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ChildCare",
  "name": "KUKO detský svet",
  "image": "https://kuko-detskysvet.sk/assets/img/hero.jpg",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Bratislavská 141",
    "postalCode": "921 01",
    "addressLocality": "Piešťany",
    "addressCountry": "SK"
  },
  "telephone": "+421915319934",
  "email": "info@kuko-detskysvet.sk",
  "openingHours": "Mo-Su 09:00-20:00",
  "geo": { "@type": "GeoCoordinates", "latitude": 48.5916, "longitude": 17.8364 },
  "priceRange": "€€"
}
</script>
```

- [ ] **Step 2: layout.php — page shell**

```php
<?php /** @var string $content */ ?>
<!doctype html>
<html lang="sk">
<head>
<?php require __DIR__ . '/head.php'; ?>
</head>
<body>
<?= $content ?>
<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
```

- [ ] **Step 3: home.php cez layout**

```php
<?php
ob_start();
?>
<main id="domov">
  <?php require __DIR__ . '/../sections/hero.php'; ?>
  <?php require __DIR__ . '/../sections/o-nas.php'; ?>
  <?php require __DIR__ . '/../sections/cennik.php'; ?>
  <?php require __DIR__ . '/../sections/oslavy.php'; ?>
  <?php require __DIR__ . '/../sections/galeria.php'; ?>
  <?php require __DIR__ . '/../sections/kontakt.php'; ?>
</main>
<?php require __DIR__ . '/../footer.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
```

- [ ] **Step 4: Stub sections (each one minimal placeholder)**

```php
<?php // private/templates/sections/hero.php ?>
<section id="hero"><h1>Hero placeholder</h1></section>
```

Vytvoriť aj `o-nas.php`, `cennik.php`, `oslavy.php`, `galeria.php`, `kontakt.php`, `nav.php`, `footer.php` ako placeholder.

- [ ] **Step 5: Verify rendering**

Run: `php -S 127.0.0.1:8000 -t public/`
Browser: vidím 6 stub sekcií jednu pod druhou.

- [ ] **Step 6: Commit**

```bash
git add private/templates/
git commit -m "feat(frontend): base layout + head with meta and JSON-LD"
```

---

### Task 2.2: CSS — design tokens + typography + reset

**Files:**
- Create: `public/assets/css/main.css`
- Move: fonty z `assets/fonts/` (zdrojové) skopírovať do `public/assets/fonts/` (už existuje pre NunitoSans*.ttf)

- [ ] **Step 1: CSS reset + tokens + base**

```css
/* public/assets/css/main.css */
:root {
  --bg-cream: #FFF8EE;
  --bg-pink-soft: #FBEEF5;
  --c-blue: #9ED7E3;
  --c-peach: #F8B49D;
  --c-yellow: #F7D87E;
  --c-purple: #C9A8E1;
  --c-pink: #F5C3DE;
  --c-text: #3D3D3D;
  --c-text-soft: #7A7A7A;
  --c-accent: #D88BBE;
  --c-white: #FFFFFF;

  --s-1: 0.5rem;
  --s-2: 1rem;
  --s-3: 1.5rem;
  --s-4: 2rem;
  --s-6: 3rem;
  --s-8: 4rem;
  --s-10: 5rem;
  --s-12: 6rem;

  --r-card: 1.25rem;
  --r-btn: 999px;

  --container: 1200px;

  --font-body: "Nunito Sans", system-ui, sans-serif;
  --shadow-card: 0 4px 20px rgba(0,0,0,0.05);
}

@font-face {
  font-family: "Nunito Sans";
  src: url("/assets/fonts/NunitoSans.ttf") format("truetype-variations");
  font-weight: 100 900;
  font-display: swap;
}
@font-face {
  font-family: "Nunito Sans";
  src: url("/assets/fonts/NunitoSans-Italic.ttf") format("truetype-variations");
  font-weight: 100 900;
  font-style: italic;
  font-display: swap;
}

*, *::before, *::after { box-sizing: border-box; }
html { -webkit-text-size-adjust: 100%; scroll-behavior: smooth; }
@media (prefers-reduced-motion: reduce) {
  html { scroll-behavior: auto; }
  *, *::before, *::after { animation: none !important; transition: none !important; }
}
body {
  margin: 0;
  font-family: var(--font-body);
  font-size: 1rem;
  line-height: 1.6;
  color: var(--c-text);
  background: var(--bg-cream);
  -webkit-font-smoothing: antialiased;
}

h1, h2, h3, h4 { font-weight: 700; line-height: 1.2; margin: 0 0 var(--s-2); }
h1 { font-size: clamp(2rem, 4vw, 3rem); }
h2 { font-size: clamp(1.5rem, 3vw, 2.25rem); text-align: center; }
h3 { font-size: 1.25rem; }
p  { margin: 0 0 var(--s-2); }

a { color: var(--c-accent); text-decoration: none; }
a:hover { text-decoration: underline; }

button { font: inherit; cursor: pointer; }

img, svg { max-width: 100%; height: auto; display: block; }

.container { max-width: var(--container); margin-inline: auto; padding-inline: var(--s-2); }
@media (min-width: 768px) { .container { padding-inline: var(--s-4); } }

.section { padding-block: var(--s-8); }
@media (min-width: 768px) { .section { padding-block: var(--s-10); } }

.section__lead { max-width: 720px; margin-inline: auto; text-align: center; color: var(--c-text-soft); }

.btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: var(--s-1);
  padding: 0.75rem 1.5rem;
  background: var(--c-accent);
  color: var(--c-white);
  border: none;
  border-radius: var(--r-btn);
  font-weight: 700;
  font-size: 0.95rem;
  letter-spacing: 0.02em;
  text-transform: uppercase;
  transition: transform 0.15s, box-shadow 0.15s;
  text-decoration: none;
}
.btn:hover { transform: translateY(-2px); box-shadow: 0 6px 18px rgba(216,139,190,0.35); text-decoration: none; }
.btn--ghost { background: transparent; color: var(--c-text); border: 2px solid currentColor; }
.btn--ghost:hover { background: rgba(0,0,0,0.04); }

.sr-only {
  position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden;
  clip: rect(0,0,0,0); white-space: nowrap; border: 0;
}
```

- [ ] **Step 2: Otvoriť v prehliadači, overiť že fonty + farby fungujú**

Run: `php -S 127.0.0.1:8000 -t public/`
DevTools → Network → font sa loaduje. Body je krémový, h1 čierny v Nunito Sans.

- [ ] **Step 3: Commit**

```bash
git add public/assets/css/main.css
git commit -m "feat(css): design tokens, typography, base components"
```

---

### Task 2.3: Nav + topbar (responsive)

**Files:**
- Modify: `private/templates/nav.php`
- Modify: `public/assets/css/main.css` (append nav styles)

- [ ] **Step 1: nav.php template**

```php
<?php /** @var array $config */ ?>
<div class="topbar">
  <div class="container topbar__inner">
    <a href="mailto:info@kuko-detskysvet.sk" class="topbar__link">info@kuko-detskysvet.sk</a>
    <a href="tel:+421915319934" class="topbar__link">+421 915 319 934</a>
  </div>
</div>
<header class="nav">
  <div class="container nav__inner">
    <a href="#domov" class="nav__brand" aria-label="KUKO detský svet — domov">
      <img src="/assets/img/logo.png" alt="" width="120" height="80">
    </a>
    <button class="nav__toggle" aria-controls="primary-nav" aria-expanded="false" aria-label="Otvoriť menu">
      <span></span><span></span><span></span>
    </button>
    <nav id="primary-nav" class="nav__menu">
      <a href="#domov">Domov</a>
      <a href="#o-nas">O detskom svete</a>
      <a href="#oslavy">Detské oslavy</a>
      <a href="#cennik">Cenník služieb</a>
      <a href="#galeria">Fotogaléria</a>
      <a href="#kontakt">Kontakt</a>
    </nav>
  </div>
</header>
```

- [ ] **Step 2: Skopírovať logo do public/assets/img/**

Run: `mkdir -p public/assets/img && cp assets/Image_logo.png public/assets/img/logo.png`

- [ ] **Step 3: Nav CSS (append to main.css)**

```css
/* Nav */
.topbar { background: var(--c-white); border-bottom: 1px solid rgba(0,0,0,0.05); font-size: 0.85rem; }
.topbar__inner { display: flex; justify-content: space-between; padding-block: 0.4rem; gap: var(--s-2); }
.topbar__link { color: var(--c-text-soft); }
@media (max-width: 640px) { .topbar { display: none; } }

.nav { background: var(--c-white); position: sticky; top: 0; z-index: 50; box-shadow: 0 2px 12px rgba(0,0,0,0.04); }
.nav__inner { display: flex; align-items: center; justify-content: space-between; padding-block: var(--s-1); }
.nav__brand img { width: 90px; height: auto; }
.nav__menu { display: flex; gap: var(--s-3); }
.nav__menu a { color: var(--c-text); font-weight: 600; font-size: 0.9rem; text-transform: uppercase; letter-spacing: 0.05em; }
.nav__menu a:hover { color: var(--c-accent); text-decoration: none; }

.nav__toggle { display: none; background: none; border: 0; padding: 0.5rem; flex-direction: column; gap: 4px; }
.nav__toggle span { display: block; width: 24px; height: 2px; background: var(--c-text); transition: transform 0.2s; }

@media (max-width: 768px) {
  .nav__toggle { display: flex; }
  .nav__menu {
    position: fixed; inset: 60px 0 auto 0; flex-direction: column; gap: 0; background: var(--c-white);
    padding: var(--s-3); max-height: 0; overflow: hidden; transition: max-height 0.3s ease; box-shadow: 0 8px 20px rgba(0,0,0,0.08);
  }
  .nav__menu.is-open { max-height: 80vh; }
  .nav__menu a { padding: var(--s-1) 0; border-bottom: 1px solid rgba(0,0,0,0.05); }
}
```

- [ ] **Step 4: Pridať nav do layout.php pred main**

Modify layout.php — pridať `<?php require __DIR__ . '/nav.php'; ?>` hneď za `<body>`.

- [ ] **Step 5: Verify**

Browser refresh — nav na vrchu, telefón/email v topbare, sticky, na mobile (DevTools 375px) hamburger viditeľný.

- [ ] **Step 6: Commit**

```bash
git add private/templates/nav.php private/templates/layout.php public/assets/css/main.css public/assets/img/logo.png
git commit -m "feat(frontend): responsive nav + topbar with logo"
```

---

### Task 2.4: Hero section

**Files:**
- Modify: `private/templates/sections/hero.php`
- Modify: `public/assets/css/main.css`
- Copy: `assets/hero.png` → `public/assets/img/hero.jpg` (a vytvoríme aj WebP neskôr v M8)

- [ ] **Step 1: Skopírovať hero obrázok**

Run: `cp assets/hero.png public/assets/img/hero.jpg`
(WebP konverzia v M8.)

- [ ] **Step 2: Hero template**

```php
<section id="domov" class="hero">
  <div class="hero__bg" style="background-image: url('/assets/img/hero.jpg')" aria-hidden="true"></div>
  <div class="hero__overlay" aria-hidden="true"></div>
  <div class="container hero__content">
    <h1 class="hero__title">Detský svet KUKO</h1>
    <p class="hero__sub">pre radosť detí &amp; pohodu rodičov</p>
    <div class="hero__cta">
      <button type="button" class="btn" data-open-reservation data-package="">Rezervovať oslavu</button>
      <a class="btn btn--ghost" href="#cennik">Pozrieť cenník</a>
    </div>
  </div>
</section>
```

- [ ] **Step 3: Hero CSS (append)**

```css
.hero { position: relative; isolation: isolate; min-height: 60vh; display: grid; place-items: center; color: var(--c-white); padding-block: var(--s-10); text-align: center; }
.hero__bg { position: absolute; inset: 0; background-size: cover; background-position: center; z-index: -2; }
.hero__overlay { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(0,0,0,0.15), rgba(0,0,0,0.45)); z-index: -1; }
.hero__title { color: var(--c-white); text-shadow: 0 2px 12px rgba(0,0,0,0.3); }
.hero__sub { font-size: 1.15rem; opacity: 0.95; margin-bottom: var(--s-3); }
.hero__cta { display: flex; flex-wrap: wrap; gap: var(--s-2); justify-content: center; }
.hero .btn--ghost { color: var(--c-white); }
.hero .btn--ghost:hover { background: rgba(255,255,255,0.15); }
```

- [ ] **Step 4: Verify**

Browser — hero zaberá > 60% výšky obrazovky, image visible cez overlay, nadpis + 2 CTA viditeľné.

- [ ] **Step 5: Commit**

```bash
git add private/templates/sections/hero.php public/assets/css/main.css public/assets/img/hero.jpg
git commit -m "feat(frontend): hero section with overlay and CTAs"
```

---

### Task 2.5: O nás section (4 cards)

**Files:**
- Modify: `private/templates/sections/o-nas.php`
- Modify: `public/assets/css/main.css`
- Copy: ikony zo `assets/` do `public/assets/icons/` (už sú tam)

- [ ] **Step 1: O nás template**

```php
<section id="o-nas" class="section section--o-nas">
  <div class="container">
    <h2>O nás</h2>
    <p class="section__lead">KUKO je interiérové detské ihrisko spojené s kaviarňou v Piešťanoch, vytvorené pre radosť detí a pohodlie rodičov. Mysleli sme na všetko, čo robí detský svet skutočne príjemným:</p>
    <div class="cards-grid">
      <div class="card card--blue">
        <img class="card__icon" src="/assets/icons/playground.svg" alt="" width="48" height="48">
        <p class="card__body"><strong>Bezpečný, čistý a hravý priestor,</strong><br>kde sa deti môžu vyšantiť, objavovať a tráviť čas aktívne.</p>
      </div>
      <div class="card card--peach">
        <img class="card__icon" src="/assets/icons/coffee.svg" alt="" width="48" height="48">
        <p class="card__body">Rodičia si zatiaľ môžu vychutnať <strong>kvalitnú kávu a chvíľku oddychu</strong> v príjemnom prostredí.</p>
      </div>
      <div class="card card--yellow">
        <img class="card__icon" src="/assets/icons/friendship.svg" alt="" width="48" height="48">
        <p class="card__body"><strong>Ideálne miesto na stretnutie</strong> s priateľmi či rodinou, alebo len chvíľu pre seba, zatiaľ čo sa deti zabavia.</p>
      </div>
      <div class="card card--purple">
        <img class="card__icon" src="/assets/icons/balloons.svg" alt="" width="48" height="48">
        <p class="card__body"><strong>Organizujeme aj detské oslavy,</strong> ktoré pripravíme s dôrazom na radosť detí a bezstarostnosť pre rodičov.</p>
        <button class="card__cta btn" data-open-reservation data-package="">Rezervovať oslavu</button>
      </div>
    </div>
  </div>
</section>
```

- [ ] **Step 2: Cards CSS (append)**

```css
.cards-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: var(--s-3);
  margin-top: var(--s-4);
}
@media (max-width: 1024px) { .cards-grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px)  { .cards-grid { grid-template-columns: 1fr; } }

.card {
  background: var(--c-white);
  border-radius: var(--r-card);
  padding: var(--s-3);
  border: 3px solid;
  display: flex;
  flex-direction: column;
  align-items: center;
  text-align: center;
  gap: var(--s-2);
}
.card--blue   { border-color: var(--c-blue); }
.card--peach  { border-color: var(--c-peach); }
.card--yellow { border-color: var(--c-yellow); }
.card--purple { border-color: var(--c-purple); }

.card__icon { width: 48px; height: 48px; margin-top: var(--s-1); }
.card__body { color: var(--c-text); font-size: 0.95rem; margin: 0; }
.card__cta { margin-top: auto; }
```

- [ ] **Step 3: Verify**

Browser — 4 karty s farebnými rámikmi + ikony + texty.

- [ ] **Step 4: Commit**

```bash
git add private/templates/sections/o-nas.php public/assets/css/main.css
git commit -m "feat(frontend): O nas section with 4 colored cards"
```

---

### Task 2.6: Cenník vstupu section

**Files:**
- Modify: `private/templates/sections/cennik.php`
- Modify: `public/assets/css/main.css`
- Copy: `assets/cennik.png` → `public/assets/img/cennik.jpg`

- [ ] **Step 1: Skopírovať foto**

Run: `cp assets/cennik.png public/assets/img/cennik.jpg`

- [ ] **Step 2: Template**

```php
<section id="cennik" class="section section--cennik">
  <div class="container cennik__inner">
    <img class="cennik__photo" src="/assets/img/cennik.jpg" alt="Šťastné deti v herni KUKO" loading="lazy" width="600" height="400">
    <div class="cennik__panel">
      <h2>Cenník</h2>
      <p class="section__lead">Chceme, aby bol čas strávený u nás dostupný a príjemný pre každého.</p>
      <ul class="cennik__list">
        <li><span>Dieťa do 1 roku</span><span class="cennik__price">ZADARMO</span></li>
        <li><span>Dieťa od 1 roku</span><span class="cennik__price">5,00 € / hod</span></li>
        <li><span>Dieťa od 1 roku neobmedzene</span><span class="cennik__price">15,00 €</span></li>
      </ul>
    </div>
  </div>
</section>
```

- [ ] **Step 3: Cenník CSS (append)**

```css
.section--cennik { background: var(--bg-pink-soft); border-radius: 0; }
.cennik__inner { display: grid; grid-template-columns: 1fr 1fr; gap: var(--s-4); align-items: center; }
.cennik__photo { border-radius: var(--r-card); object-fit: cover; aspect-ratio: 3/2; }
.cennik__panel { background: var(--c-white); padding: var(--s-4); border-radius: var(--r-card); box-shadow: var(--shadow-card); }
.cennik__list { list-style: none; padding: 0; margin: var(--s-3) 0 0; display: flex; flex-direction: column; gap: var(--s-2); }
.cennik__list li {
  display: flex; justify-content: space-between; align-items: center;
  background: var(--c-white); border: 1px solid rgba(0,0,0,0.06); border-radius: var(--r-btn);
  padding: 0.75rem 1.25rem; gap: var(--s-2);
}
.cennik__price { font-weight: 700; color: var(--c-accent); }
@media (max-width: 768px) {
  .cennik__inner { grid-template-columns: 1fr; }
  .cennik__photo { aspect-ratio: 16/9; }
}
```

- [ ] **Step 4: Verify**

Browser — 2 stĺpce, panel s cenami v pill rows; pod 768px stack.

- [ ] **Step 5: Commit**

```bash
git add private/templates/sections/cennik.php public/assets/css/main.css public/assets/img/cennik.jpg
git commit -m "feat(frontend): Cennik section with photo + price panel"
```

---

### Task 2.7: Detské oslavy section (3 balíčky + modal placeholder)

**Files:**
- Modify: `private/templates/sections/oslavy.php`
- Modify: `public/assets/css/main.css`

> Pozn.: Presné copy textov balíčkov je TODO zo specu, použijeme placeholder text. Po finalnom dorovnaní podmienime úpravu.

- [ ] **Step 1: Template**

```php
<section id="oslavy" class="section section--oslavy">
  <div class="container">
    <h2>Detské KUKO oslavy</h2>
    <p class="section__lead">Vyberte si balíček, ktorý vám sedí, a my sa postaráme o zvyšok.</p>
    <div class="packages-grid">
      <article class="package package--blue">
        <header class="package__head"><span class="package__hat">🎩</span><h3>Oslava KUKO MINI</h3></header>
        <p class="package__desc">Báze balíček pre menšie oslavy s priateľmi. Zahŕňa prenájom časti herne na 2 hodiny.</p>
        <ul class="package__meta">
          <li><span class="ic">👶</span> Počet detí: do 10</li>
          <li><span class="ic">⏰</span> Časový harmonogram: 2 hodiny</li>
        </ul>
        <p class="package__price">120 – 150 € / balíček</p>
        <ul class="package__incl">
          <li>✓ Vyhradený stôl pre rodičov</li>
          <li>✓ Občerstvenie pre deti</li>
          <li>✓ Animátorka v cene</li>
        </ul>
        <button class="btn package__cta" data-open-reservation data-package="mini">Rezervovať balíček</button>
      </article>

      <article class="package package--purple">
        <header class="package__head"><span class="package__hat">🎩</span><h3>Oslava KUKO MAXI</h3></header>
        <p class="package__desc">Pre väčšie deti a väčšie skupiny. Plne vybavená oslava s programom.</p>
        <ul class="package__meta">
          <li><span class="ic">👶</span> Počet detí: do 20</li>
          <li><span class="ic">⏰</span> Časový harmonogram: 3 hodiny</li>
        </ul>
        <p class="package__price">220 – 260 € / balíček</p>
        <ul class="package__incl">
          <li>✓ Vyhradený priestor</li>
          <li>✓ Občerstvenie + nápoje</li>
          <li>✓ Animátorka + program</li>
          <li>✓ Tematická výzdoba</li>
        </ul>
        <button class="btn package__cta" data-open-reservation data-package="maxi">Rezervovať balíček</button>
      </article>

      <article class="package package--yellow">
        <header class="package__head"><span class="package__hat">🎩</span><h3>Uzavretá spoločnosť</h3></header>
        <p class="package__desc">Exkluzívna oslava — celá herňa je iba pre vás, bez verejnosti, s plným servisom.</p>
        <ul class="package__meta">
          <li><span class="ic">👶</span> Počet detí: neobmedzene</li>
          <li><span class="ic">⏰</span> Časový harmonogram: 4 hodiny</li>
        </ul>
        <p class="package__price">350 € / balíček</p>
        <ul class="package__incl">
          <li>✓ Celá herňa</li>
          <li>✓ Plné catering</li>
          <li>✓ Animátorky + program</li>
          <li>✓ Tematická výzdoba</li>
        </ul>
        <button class="btn package__cta" data-open-reservation data-package="closed">Rezervovať balíček</button>
      </article>
    </div>
  </div>
</section>
```

- [ ] **Step 2: Oslavy CSS (append)**

```css
.section--oslavy { background: var(--bg-cream); }
.packages-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--s-3); margin-top: var(--s-4); }
@media (max-width: 1024px) { .packages-grid { grid-template-columns: 1fr; max-width: 480px; margin-inline: auto; } }

.package { background: var(--c-white); border-radius: var(--r-card); padding: var(--s-3); border: 3px solid; display: flex; flex-direction: column; gap: var(--s-2); }
.package--blue   { border-color: var(--c-blue); }
.package--purple { border-color: var(--c-purple); }
.package--yellow { border-color: var(--c-yellow); }
.package__head { display: flex; align-items: center; gap: var(--s-1); }
.package__head h3 { margin: 0; }
.package__hat { font-size: 1.5rem; }
.package__desc { color: var(--c-text-soft); font-size: 0.9rem; }
.package__meta { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.25rem; font-size: 0.9rem; }
.package__meta .ic { display: inline-block; width: 1.25rem; }
.package__price { font-weight: 700; font-size: 1.1rem; color: var(--c-accent); margin: var(--s-1) 0; }
.package__incl { list-style: none; padding: 0; margin: 0 0 var(--s-2); font-size: 0.9rem; display: flex; flex-direction: column; gap: 0.25rem; }
.package__cta { margin-top: auto; align-self: stretch; }
```

- [ ] **Step 3: Verify**

Browser — 3 karty rovnakej výšky vďaka flex column + margin auto na CTA.

- [ ] **Step 4: Commit**

```bash
git add private/templates/sections/oslavy.php public/assets/css/main.css
git commit -m "feat(frontend): Oslavy section with 3 package cards (placeholder copy)"
```

---

### Task 2.8: Fotogaléria section

**Files:**
- Modify: `private/templates/sections/galeria.php`
- Modify: `public/assets/css/main.css`
- Copy: `assets/galeria_*.png` → `public/assets/img/galeria_*.jpg`

- [ ] **Step 1: Skopírovať fotky**

Run: `for i in 1 2 3 4 5; do cp "assets/galeria_${i}.png" "public/assets/img/galeria_${i}.jpg"; done`

- [ ] **Step 2: Template (5 fotiek — galéria má TODO ohľadom 6.)**

```php
<section id="galeria" class="section section--galeria">
  <div class="container">
    <h2>Fotogaléria</h2>
    <p class="section__lead">Nazrite do nášho priestoru a atmosféry, ktorú u každej deti milujeme.</p>
    <div class="gallery">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <button class="gallery__item" data-lightbox="/assets/img/galeria_<?= $i ?>.jpg" type="button" aria-label="Otvoriť fotku <?= $i ?>">
          <img src="/assets/img/galeria_<?= $i ?>.jpg" loading="lazy" alt="Fotka z herne KUKO" width="400" height="280">
        </button>
      <?php endfor; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 3: Galéria CSS (append)**

```css
.section--galeria { background: var(--bg-pink-soft); }
.gallery { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--s-2); margin-top: var(--s-4); }
@media (max-width: 768px) { .gallery { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 480px) { .gallery { grid-template-columns: 1fr; } }
.gallery__item { padding: 0; border: 0; background: none; border-radius: var(--r-card); overflow: hidden; cursor: zoom-in; transition: transform 0.2s; }
.gallery__item:hover { transform: scale(1.03); }
.gallery__item img { width: 100%; aspect-ratio: 4/3; object-fit: cover; display: block; }
```

- [ ] **Step 4: Verify**

Browser — 3-stĺpcový grid fotiek, hover scaling.

- [ ] **Step 5: Commit**

```bash
git add private/templates/sections/galeria.php public/assets/css/main.css public/assets/img/galeria_*.jpg
git commit -m "feat(frontend): Galeria section with 5-photo grid"
```

---

### Task 2.9: Kontakt section (map placeholder + cards)

**Files:**
- Modify: `private/templates/sections/kontakt.php`
- Modify: `public/assets/css/main.css`

- [ ] **Step 1: Template**

```php
<section id="kontakt" class="section section--kontakt">
  <div class="container">
    <h2>Kde nás nájdete?</h2>
    <p class="section__lead">Tešíme sa na vašu návštevu!</p>
    <div class="kontakt__grid">
      <div id="map" class="kontakt__map" aria-label="Mapa s polohou KUKO detský svet">
        <noscript>Mapa vyžaduje JavaScript. Adresa: Bratislavská 141, 921 01 Piešťany.</noscript>
      </div>
      <div class="kontakt__cards">
        <div class="contact-card contact-card--blue">
          <span class="contact-card__icon" aria-hidden="true">🏠</span>
          <div>
            <p class="contact-card__title">Navštívte náš Detský svet KUKO:</p>
            <p class="contact-card__value"><strong>Bratislavská 141, 921 01 Piešťany</strong></p>
          </div>
        </div>
        <div class="contact-card contact-card--peach">
          <span class="contact-card__icon" aria-hidden="true">📞</span>
          <div>
            <p class="contact-card__title">Máte otázky? Kontaktujte nás:</p>
            <p class="contact-card__value">
              <a href="tel:+421915319934">+421 915 319 934</a> |
              <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>
            </p>
          </div>
        </div>
        <div class="contact-card contact-card--yellow">
          <span class="contact-card__icon" aria-hidden="true">⏰</span>
          <div>
            <p class="contact-card__title">Otváracie hodiny — sme tu pre vás každý deň:</p>
            <p class="contact-card__value"><strong>Pondelok – Nedeľa: 9:00 – 20:00</strong></p>
          </div>
        </div>
        <div class="contact-card contact-card--purple">
          <p class="contact-card__title">Sledujte nás na sociálnych sieťach:</p>
          <div class="contact-card__socials">
            <a href="#" aria-label="Facebook" rel="noopener"><img src="/assets/icons/facebook-app-symbol.svg" alt="Facebook" width="24" height="24"></a>
            <a href="#" aria-label="Instagram" rel="noopener"><img src="/assets/icons/instagram.svg" alt="Instagram" width="24" height="24"></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
```

(Pozn.: social URLs sú TODO zo specu — placeholder `href="#"`.)

- [ ] **Step 2: Kontakt CSS (append)**

```css
.section--kontakt { background: var(--bg-cream); }
.kontakt__grid { display: grid; grid-template-columns: 1.2fr 1fr; gap: var(--s-4); margin-top: var(--s-4); }
@media (max-width: 1024px) { .kontakt__grid { grid-template-columns: 1fr; } }
.kontakt__map { aspect-ratio: 4/3; border-radius: var(--r-card); background: #e9eef5; border: 3px solid var(--c-purple); overflow: hidden; }
.kontakt__cards { display: flex; flex-direction: column; gap: var(--s-2); }
.contact-card {
  background: var(--c-white); border-radius: var(--r-card); padding: var(--s-2) var(--s-3);
  border: 2px solid; display: flex; align-items: center; gap: var(--s-2);
}
.contact-card--blue   { border-color: var(--c-blue); }
.contact-card--peach  { border-color: var(--c-peach); }
.contact-card--yellow { border-color: var(--c-yellow); }
.contact-card--purple { border-color: var(--c-purple); flex-direction: column; align-items: flex-start; }
.contact-card__icon { font-size: 1.5rem; flex-shrink: 0; }
.contact-card__title { font-size: 0.85rem; color: var(--c-text-soft); margin: 0; }
.contact-card__value { margin: 0.15rem 0 0; font-size: 0.95rem; }
.contact-card__socials { display: flex; gap: var(--s-1); margin-top: 0.5rem; }
.contact-card__socials a { width: 36px; height: 36px; background: var(--bg-pink-soft); border-radius: 50%; display: grid; place-items: center; }
.contact-card__socials img { width: 18px; height: 18px; }
```

- [ ] **Step 3: Commit**

```bash
git add private/templates/sections/kontakt.php public/assets/css/main.css
git commit -m "feat(frontend): Kontakt section with map placeholder + cards"
```

---

### Task 2.10: Footer

**Files:**
- Modify: `private/templates/footer.php`
- Modify: `public/assets/css/main.css`

- [ ] **Step 1: Footer template**

```php
<footer class="footer">
  <div class="container footer__inner">
    <div class="footer__logo">
      <img src="/assets/img/logo.png" alt="KUKO detský svet" width="180" height="120">
    </div>
  </div>
  <div class="footer__nav-bg">
    <nav class="container footer__nav" aria-label="Pätička">
      <a href="#domov">Domov</a>
      <a href="#o-nas">O detskom svete</a>
      <a href="#oslavy">Detské oslavy</a>
      <a href="#cennik">Cenník služieb</a>
      <a href="#galeria">Fotogaléria</a>
      <a href="#kontakt">Kontakt</a>
    </nav>
  </div>
  <div class="container footer__copy">
    <p>Copyright © <?= date('Y') ?> KUKO-detskysvet.sk | Všetky práva vyhradené.</p>
    <p><a href="/ochrana-udajov">Ochrana osobných údajov</a> · <button type="button" class="link-btn" id="cookie-reopen">Cookie nastavenia</button></p>
  </div>
</footer>
```

- [ ] **Step 2: Footer CSS (append)**

```css
.footer { background: var(--c-white); padding-top: var(--s-6); margin-top: var(--s-8); }
.footer__inner { display: grid; place-items: center; padding-bottom: var(--s-3); }
.footer__nav-bg { background: var(--bg-pink-soft); padding-block: var(--s-2); }
.footer__nav { display: flex; gap: var(--s-4); justify-content: center; flex-wrap: wrap; }
.footer__nav a { color: var(--c-text); font-weight: 600; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; }
.footer__nav a:hover { color: var(--c-accent); text-decoration: none; }
.footer__copy { text-align: center; padding-block: var(--s-2); font-size: 0.85rem; color: var(--c-text-soft); }
.footer__copy p { margin: 0.25rem 0; }
.link-btn { background: none; border: 0; color: var(--c-accent); cursor: pointer; padding: 0; font: inherit; text-decoration: underline; }
```

- [ ] **Step 3: Commit**

```bash
git add private/templates/footer.php public/assets/css/main.css
git commit -m "feat(frontend): footer with logo, nav and cookie re-open"
```

---

### Task 2.11: Privacy page

**Files:**
- Modify: `private/templates/pages/privacy.php`

- [ ] **Step 1: Privacy page template**

```php
<?php
$title = 'Ochrana osobných údajov — KUKO detský svet';
$description = 'Zásady spracovania osobných údajov a cookies na webe kuko-detskysvet.sk.';
ob_start();
?>
<main class="section">
  <div class="container" style="max-width: 800px;">
    <h1>Ochrana osobných údajov</h1>
    <p>Posledná aktualizácia: <?= date('j. n. Y') ?></p>

    <h2 style="text-align:left;">1. Prevádzkovateľ</h2>
    <p>Prevádzkovateľom webu kuko-detskysvet.sk je KUKO detský svet, Bratislavská 141, 921 01 Piešťany, e-mail info@kuko-detskysvet.sk.</p>

    <h2 style="text-align:left;">2. Rozsah a účel spracovania</h2>
    <p>Pri rezervácii oslavy spracúvame údaje, ktoré ste nám poskytli prostredníctvom formulára: meno, telefón, e-mail, požadovaný dátum a čas oslavy, počet detí a poznámku. Tieto údaje spracúvame výlučne na účel vybavenia vašej rezervácie a kontaktu vo veci oslavy.</p>

    <h2 style="text-align:left;">3. Právny základ</h2>
    <p>Spracovanie prebieha na základe vašej žiadosti o rezerváciu (predzmluvné konanie podľa čl. 6 ods. 1 písm. b GDPR) a nášho oprávneného záujmu zabezpečiť funkčnosť rezervačného systému (čl. 6 ods. 1 písm. f GDPR).</p>

    <h2 style="text-align:left;">4. Doba uchovávania</h2>
    <p>Údaje uchovávame po dobu potrebnú na vybavenie rezervácie a 6 mesiacov po jej skončení, následne sú anonymizované alebo vymazané.</p>

    <h2 style="text-align:left;">5. Cookies a Google reCAPTCHA</h2>
    <p>Web používa nasledujúce cookies:</p>
    <ul>
      <li><strong>Technické cookies</strong> (PHPSESSID, cookie_consent) — nevyhnutné pre fungovanie a uloženie vášho rozhodnutia o cookies. Tieto cookies nevyžadujú váš súhlas.</li>
      <li><strong>Google reCAPTCHA</strong> (_GRECAPTCHA) — slúži na ochranu rezervačného formulára pred spamom. Spoločnosť Google týmto môže získať údaje o vašom správaní na stránke. Cookie sa nahrá iba po vašom súhlase. Viac informácií: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.</li>
    </ul>
    <p>Súhlas s cookies môžete kedykoľvek odvolať kliknutím na „Cookie nastavenia" v pätičke.</p>

    <h2 style="text-align:left;">6. Vaše práva</h2>
    <p>V súlade s GDPR máte právo na prístup k svojim údajom, ich opravu, vymazanie, obmedzenie spracúvania, prenosnosť, ako aj právo namietať a podať sťažnosť na Úrade na ochranu osobných údajov SR. Ohľadom vašich práv nás môžete kontaktovať na info@kuko-detskysvet.sk.</p>

    <p style="margin-top: 3rem;"><a href="/">← Späť na domov</a></p>
  </div>
</main>
<?php
require __DIR__ . '/../footer.php';
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
```

- [ ] **Step 2: Smoke test**

`http://127.0.0.1:8000/ochrana-udajov` → renders privacy page.

- [ ] **Step 3: Commit**

```bash
git add private/templates/pages/privacy.php
git commit -m "feat(frontend): privacy policy page with GDPR + cookies disclosure"
```

---

### Task 2.12: 404 page

**Files:**
- Modify: `private/templates/pages/404.php`

- [ ] **Step 1: 404 template**

```php
<?php
$title = 'Stránka nenájdená — KUKO detský svet';
ob_start();
?>
<main class="section" style="text-align:center;">
  <div class="container">
    <h1>404</h1>
    <p>Stránka, ktorú hľadáte, neexistuje alebo bola presunutá.</p>
    <p><a class="btn" href="/">← Späť na domov</a></p>
  </div>
</main>
<?php
require __DIR__ . '/../footer.php';
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
```

- [ ] **Step 2: Commit**

```bash
git add private/templates/pages/404.php
git commit -m "feat(frontend): friendly 404 page"
```

---

## M3 — Frontend interakcia (vanilla JS)

### Task 3.1: main.js — nav toggle + smooth scroll + scroll reveal

**Files:**
- Create: `public/assets/js/main.js`
- Modify: `public/assets/css/main.css` (append `[data-reveal]` styles)

- [ ] **Step 1: Reveal CSS**

```css
[data-reveal] {
  opacity: 0;
  transform: translateY(20px);
  transition: opacity 0.6s ease, transform 0.6s ease;
}
[data-reveal].is-visible { opacity: 1; transform: none; }
@media (prefers-reduced-motion: reduce) {
  [data-reveal] { opacity: 1; transform: none; transition: none; }
}
```

- [ ] **Step 2: main.js**

```js
// public/assets/js/main.js
const $ = (s, c = document) => c.querySelector(s);
const $$ = (s, c = document) => Array.from(c.querySelectorAll(s));

// Hamburger toggle
const toggle = $('.nav__toggle');
const menu = $('#primary-nav');
if (toggle && menu) {
  toggle.addEventListener('click', () => {
    const expanded = toggle.getAttribute('aria-expanded') === 'true';
    toggle.setAttribute('aria-expanded', String(!expanded));
    menu.classList.toggle('is-open');
  });
  menu.querySelectorAll('a').forEach(a => a.addEventListener('click', () => {
    toggle.setAttribute('aria-expanded', 'false');
    menu.classList.remove('is-open');
  }));
}

// Scroll reveal
const revealEls = $$('[data-reveal]');
if (revealEls.length && 'IntersectionObserver' in window) {
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        e.target.classList.add('is-visible');
        io.unobserve(e.target);
      }
    });
  }, { threshold: 0.1 });
  revealEls.forEach(el => io.observe(el));
} else {
  revealEls.forEach(el => el.classList.add('is-visible'));
}

// Smooth scroll for anchor links (sticky nav offset)
document.addEventListener('click', e => {
  const a = e.target.closest('a[href^="#"]');
  if (!a) return;
  const id = a.getAttribute('href').slice(1);
  if (!id) return;
  const target = document.getElementById(id);
  if (!target) return;
  e.preventDefault();
  const offset = ($('.nav')?.offsetHeight ?? 0) + 8;
  const top = target.getBoundingClientRect().top + window.scrollY - offset;
  window.scrollTo({ top, behavior: 'smooth' });
  history.replaceState(null, '', '#' + id);
});

// Lazy load extra modules
import('./gallery.js').catch(() => {});
import('./map.js').catch(() => {});
```

- [ ] **Step 3: Pridať `data-reveal` na sekcie**

Modify section templates (hero, o-nas, cennik, oslavy, galeria, kontakt) — pridať atribút `data-reveal` na `<section>`.

- [ ] **Step 4: Verify v prehliadači**

- Hamburger funguje pod 768px.
- Scrolovanie cez nav linky je smooth a zaráža správne pod sticky nav.
- Sekcie sa fade-in pri scroll-e.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/main.js public/assets/css/main.css private/templates/sections/
git commit -m "feat(frontend): hamburger, smooth scroll, scroll reveal"
```

---

### Task 3.2: gallery.js — lightbox

**Files:**
- Create: `public/assets/js/gallery.js`
- Modify: `public/assets/css/main.css` (lightbox styles)

- [ ] **Step 1: Lightbox CSS**

```css
.lightbox {
  position: fixed; inset: 0; background: rgba(0,0,0,0.85); display: grid; place-items: center;
  z-index: 1000; padding: var(--s-3); animation: fadeIn 0.2s;
}
.lightbox[hidden] { display: none; }
.lightbox__img { max-width: 95vw; max-height: 88vh; border-radius: var(--r-card); }
.lightbox__btn {
  position: absolute; background: rgba(255,255,255,0.15); border: 0; color: white;
  width: 44px; height: 44px; border-radius: 50%; font-size: 1.5rem; display: grid; place-items: center;
  cursor: pointer;
}
.lightbox__btn:hover { background: rgba(255,255,255,0.3); }
.lightbox__btn--prev { left: var(--s-2); }
.lightbox__btn--next { right: var(--s-2); }
.lightbox__btn--close { top: var(--s-2); right: var(--s-2); }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
```

- [ ] **Step 2: gallery.js**

```js
// public/assets/js/gallery.js
const items = Array.from(document.querySelectorAll('[data-lightbox]'));
if (items.length) {
  const lb = document.createElement('div');
  lb.className = 'lightbox';
  lb.hidden = true;
  lb.innerHTML = `
    <button type="button" class="lightbox__btn lightbox__btn--close" aria-label="Zavrieť">×</button>
    <button type="button" class="lightbox__btn lightbox__btn--prev" aria-label="Predchádzajúca">‹</button>
    <img class="lightbox__img" alt="">
    <button type="button" class="lightbox__btn lightbox__btn--next" aria-label="Ďalšia">›</button>
  `;
  document.body.appendChild(lb);

  const img = lb.querySelector('.lightbox__img');
  const btnClose = lb.querySelector('.lightbox__btn--close');
  const btnPrev = lb.querySelector('.lightbox__btn--prev');
  const btnNext = lb.querySelector('.lightbox__btn--next');

  let idx = 0;
  let lastFocus = null;

  const show = (i) => {
    idx = (i + items.length) % items.length;
    img.src = items[idx].dataset.lightbox;
    img.alt = items[idx].querySelector('img')?.alt ?? '';
  };

  const open = (i) => {
    lastFocus = document.activeElement;
    lb.hidden = false;
    show(i);
    btnClose.focus();
    document.body.style.overflow = 'hidden';
  };
  const close = () => {
    lb.hidden = true;
    document.body.style.overflow = '';
    lastFocus?.focus();
  };

  items.forEach((el, i) => el.addEventListener('click', () => open(i)));
  btnClose.addEventListener('click', close);
  btnPrev.addEventListener('click', () => show(idx - 1));
  btnNext.addEventListener('click', () => show(idx + 1));
  lb.addEventListener('click', e => { if (e.target === lb) close(); });
  document.addEventListener('keydown', e => {
    if (lb.hidden) return;
    if (e.key === 'Escape') close();
    if (e.key === 'ArrowLeft') show(idx - 1);
    if (e.key === 'ArrowRight') show(idx + 1);
  });
}
```

- [ ] **Step 3: Verify**

Klik na fotku → lightbox sa otvorí, šípky/klávesnica fungujú, ESC zatvorí, scroll body je locked.

- [ ] **Step 4: Commit**

```bash
git add public/assets/js/gallery.js public/assets/css/main.css
git commit -m "feat(frontend): lightbox gallery with keyboard nav"
```

---

### Task 3.3: map.js — Leaflet init

**Files:**
- Create: `public/assets/js/map.js`
- Modify: `private/templates/head.php` (Leaflet CSS link)

- [ ] **Step 1: Leaflet CSS link v head**

Append to `head.php`:

```php
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
      integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="">
```

- [ ] **Step 2: map.js**

```js
// public/assets/js/map.js
const mapEl = document.getElementById('map');
if (mapEl) {
  const { default: L } = await import('https://unpkg.com/leaflet@1.9.4/dist/leaflet-src.esm.js');
  const lat = 48.5916, lon = 17.8364;
  const map = L.map(mapEl, { scrollWheelZoom: false }).setView([lat, lon], 16);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
  }).addTo(map);
  const icon = L.divIcon({
    html: '<div style="width:30px;height:30px;border-radius:50% 50% 50% 0;background:#D88BBE;transform:rotate(-45deg);box-shadow:0 4px 10px rgba(0,0,0,0.3)"></div>',
    className: '',
    iconSize: [30, 30],
    iconAnchor: [15, 30],
  });
  L.marker([lat, lon], { icon }).addTo(map).bindPopup('<strong>KUKO detský svet</strong><br>Bratislavská 141<br>921 01 Piešťany');
}
```

> Pozn.: `lat/lon` sú aproximácie pre Bratislavskú 141, Piešťany. Pri implementácii overiť presné súradnice (Google Maps → pravý klik → kopírovať lat/lon).

- [ ] **Step 3: Update CSP v .htaccess**

Modify `public/.htaccess` — pridať `unpkg.com` a `tile.openstreetmap.org`:

```apache
<IfModule mod_headers.c>
    Header set Content-Security-Policy "default-src 'self'; img-src 'self' data: https://*.tile.openstreetmap.org; script-src 'self' 'unsafe-inline' https://unpkg.com https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; frame-src https://www.google.com/recaptcha/; style-src 'self' 'unsafe-inline' https://unpkg.com; font-src 'self'; connect-src 'self' https://www.google.com/recaptcha/"
</IfModule>
```

- [ ] **Step 4: Verify**

Mapa sa renderuje v sekcii Kontakt, marker na správnom mieste.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/map.js private/templates/head.php public/.htaccess
git commit -m "feat(frontend): Leaflet map with OSM tiles + custom marker"
```

---

### Task 3.4: Final smoke test celej statiky

- [ ] **Step 1: Spustiť server**

Run: `php -S 127.0.0.1:8000 -t public/`

- [ ] **Step 2: Manual checklist**

- [ ] Homepage načíta všetkých 7 sekcií.
- [ ] Nav je sticky, hamburger funguje pod 768px.
- [ ] Hero má pozadie a 2 CTA.
- [ ] O nás karty (4) s farebnými rámikmi.
- [ ] Cenník: 3 riadky pill.
- [ ] Oslavy: 3 karty rovnakej výšky.
- [ ] Galéria: lightbox.
- [ ] Mapa: marker.
- [ ] Footer: nav + cookie reopen tlačidlo.
- [ ] `/ochrana-udajov` načíta.
- [ ] `/neexistuje` → 404 page.
- [ ] DevTools Lighthouse → a11y > 90, Performance > 80.

- [ ] **Step 3: Commit prípadné fixes**

---

## M4 — Cookie consent

### Task 4.1: Banner template + CSS

**Files:**
- Create: `private/templates/cookie-banner.php`
- Modify: `private/templates/layout.php` (include banner)
- Modify: `public/assets/css/main.css`

- [ ] **Step 1: Banner template**

```php
<div class="cookie-banner" id="cookie-banner" role="dialog" aria-modal="false" aria-labelledby="cookie-title" hidden>
  <div class="cookie-banner__inner container">
    <div>
      <p id="cookie-title" class="cookie-banner__title"><strong>Súbory cookies</strong></p>
      <p class="cookie-banner__text">Pre ochranu rezervačného formulára pred spamom používame Google reCAPTCHA, ktorá ukladá cookies. Bez vášho súhlasu rezervačný formulár nebude funkčný. Viac v <a href="/ochrana-udajov">Ochrane údajov</a>.</p>
    </div>
    <div class="cookie-banner__actions">
      <button type="button" class="btn btn--ghost" data-cookie-action="deny">Odmietnuť</button>
      <button type="button" class="btn" data-cookie-action="accept">Súhlasím</button>
    </div>
  </div>
</div>
```

- [ ] **Step 2: Include do layout.php**

Modify layout.php pred `</body>`:

```php
<?php require __DIR__ . '/cookie-banner.php'; ?>
```

- [ ] **Step 3: CSS banner**

```css
.cookie-banner {
  position: fixed; bottom: var(--s-2); left: var(--s-2); right: var(--s-2);
  background: var(--c-white); border: 2px solid var(--c-purple); border-radius: var(--r-card);
  box-shadow: 0 10px 30px rgba(0,0,0,0.15); padding: var(--s-2); z-index: 100;
  animation: slideUp 0.3s;
}
.cookie-banner[hidden] { display: none; }
.cookie-banner__inner { display: flex; align-items: center; gap: var(--s-3); flex-wrap: wrap; }
.cookie-banner__title { margin: 0 0 0.25rem; }
.cookie-banner__text { margin: 0; font-size: 0.9rem; }
.cookie-banner__actions { display: flex; gap: var(--s-1); margin-left: auto; flex-shrink: 0; }
@media (max-width: 640px) {
  .cookie-banner__actions { margin-left: 0; width: 100%; justify-content: flex-end; }
}
@keyframes slideUp { from { transform: translateY(20px); opacity: 0; } to { transform: none; opacity: 1; } }
```

- [ ] **Step 4: Commit**

```bash
git add private/templates/cookie-banner.php private/templates/layout.php public/assets/css/main.css
git commit -m "feat(frontend): cookie consent banner UI"
```

---

### Task 4.2: Banner JS logika

**Files:**
- Modify: `public/assets/js/main.js` (append)

- [ ] **Step 1: Append cookie banner code do main.js**

```js
// Cookie consent
const CONSENT_KEY = 'kuko_cookie_consent';
const banner = document.getElementById('cookie-banner');
const reopenBtn = document.getElementById('cookie-reopen');

function getConsent() { return localStorage.getItem(CONSENT_KEY); }
function setConsent(value) {
  localStorage.setItem(CONSENT_KEY, value);
  document.dispatchEvent(new CustomEvent('kuko:consent', { detail: { value } }));
}
function showBanner() { banner.hidden = false; }
function hideBanner() { banner.hidden = true; }

if (banner) {
  if (!getConsent()) showBanner();
  banner.querySelectorAll('[data-cookie-action]').forEach(btn => {
    btn.addEventListener('click', () => {
      setConsent(btn.dataset.cookieAction === 'accept' ? 'accepted' : 'denied');
      hideBanner();
    });
  });
}
reopenBtn?.addEventListener('click', showBanner);
```

- [ ] **Step 2: Verify**

- Prvá návšteva → banner viditeľný.
- Klik na Súhlasím → banner zmizne, localStorage má `kuko_cookie_consent=accepted`.
- Klik na „Cookie nastavenia" v pätičke → banner znova.

- [ ] **Step 3: Commit**

```bash
git add public/assets/js/main.js
git commit -m "feat(frontend): cookie banner logic with localStorage + reopen"
```

---

## M5 — Rezervácie backend

### Task 5.1: Csrf class

**Files:**
- Create: `private/lib/Csrf.php`
- Create: `private/tests/unit/CsrfTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Csrf;
use PHPUnit\Framework\TestCase;

final class CsrfTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        Csrf::reset();
    }

    public function testTokenIsString64(): void
    {
        $t = Csrf::token();
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $t);
    }

    public function testTokenIsStable(): void
    {
        $a = Csrf::token();
        $b = Csrf::token();
        $this->assertSame($a, $b);
    }

    public function testVerifyAccepts(): void
    {
        $t = Csrf::token();
        $this->assertTrue(Csrf::verify($t));
    }

    public function testVerifyRejectsWrong(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::verify(str_repeat('0', 64)));
    }

    public function testVerifyRejectsEmpty(): void
    {
        Csrf::token();
        $this->assertFalse(Csrf::verify(''));
    }
}
```

- [ ] **Step 2: Implement Csrf**

```php
<?php
// private/lib/Csrf.php
declare(strict_types=1);
namespace Kuko;

final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            if (defined('TESTING')) {
                // simulate session via $_SESSION superglobal
            } else {
                session_start();
            }
        }
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function verify(string $given): bool
    {
        if ($given === '' || !isset($_SESSION[self::KEY])) return false;
        return hash_equals($_SESSION[self::KEY], $given);
    }

    public static function reset(): void
    {
        unset($_SESSION[self::KEY]);
    }
}
```

- [ ] **Step 3: Run + verify**

Run: `php private/tests/run.php --filter CsrfTest`
Expected: `OK (5 tests)`.

- [ ] **Step 4: Commit**

```bash
git add private/lib/Csrf.php private/tests/unit/CsrfTest.php
git commit -m "feat(lib): Csrf token issue and verify"
```

---

### Task 5.2: Reservation model + validator

**Files:**
- Create: `private/lib/Reservation.php`
- Create: `private/tests/unit/ReservationTest.php`

- [ ] **Step 1: Failing test (input validation)**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Reservation;
use PHPUnit\Framework\TestCase;

final class ReservationTest extends TestCase
{
    private function valid(): array
    {
        return [
            'package'      => 'mini',
            'wished_date'  => date('Y-m-d', strtotime('+7 days')),
            'wished_time'  => '14:00',
            'kids_count'   => 8,
            'name'         => 'Jana Mrkvičková',
            'phone'        => '+421915123456',
            'email'        => 'jana@example.com',
            'note'         => 'Téma: pirátska oslava',
        ];
    }

    public function testValidPasses(): void
    {
        $errors = Reservation::validate($this->valid());
        $this->assertSame([], $errors);
    }

    public function testInvalidPackage(): void
    {
        $d = $this->valid(); $d['package'] = 'unknown';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('package', $errors);
    }

    public function testDateInPast(): void
    {
        $d = $this->valid(); $d['wished_date'] = '2020-01-01';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('wished_date', $errors);
    }

    public function testKidsRange(): void
    {
        foreach ([0, -1, 51] as $bad) {
            $d = $this->valid(); $d['kids_count'] = $bad;
            $errors = Reservation::validate($d);
            $this->assertArrayHasKey('kids_count', $errors);
        }
    }

    public function testEmailRequired(): void
    {
        $d = $this->valid(); $d['email'] = 'not-an-email';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testPhoneFormat(): void
    {
        $d = $this->valid(); $d['phone'] = 'abc';
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('phone', $errors);
    }

    public function testNoteOptional(): void
    {
        $d = $this->valid(); unset($d['note']);
        $errors = Reservation::validate($d);
        $this->assertSame([], $errors);
    }

    public function testNoteTooLong(): void
    {
        $d = $this->valid(); $d['note'] = str_repeat('x', 1500);
        $errors = Reservation::validate($d);
        $this->assertArrayHasKey('note', $errors);
    }
}
```

- [ ] **Step 2: Implement Reservation**

```php
<?php
// private/lib/Reservation.php
declare(strict_types=1);
namespace Kuko;

final class Reservation
{
    public const PACKAGES = ['mini', 'maxi', 'closed'];
    public const STATUSES = ['pending', 'confirmed', 'cancelled'];

    /** @return array<string,string> field => error message */
    public static function validate(array $d): array
    {
        $errors = [];

        $pkg = (string)($d['package'] ?? '');
        if (!in_array($pkg, self::PACKAGES, true)) {
            $errors['package'] = 'Neznámy balíček.';
        }

        $date = (string)($d['wished_date'] ?? '');
        $dateObj = \DateTimeImmutable::createFromFormat('Y-m-d', $date);
        if (!$dateObj || $dateObj->format('Y-m-d') !== $date) {
            $errors['wished_date'] = 'Neplatný dátum.';
        } elseif ($dateObj < new \DateTimeImmutable('today')) {
            $errors['wished_date'] = 'Dátum nemôže byť v minulosti.';
        }

        $time = (string)($d['wished_time'] ?? '');
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time)) {
            $errors['wished_time'] = 'Neplatný čas (formát HH:MM).';
        }

        $kids = filter_var($d['kids_count'] ?? null, FILTER_VALIDATE_INT);
        if ($kids === false || $kids < 1 || $kids > 50) {
            $errors['kids_count'] = 'Počet detí musí byť 1 – 50.';
        }

        $name = trim((string)($d['name'] ?? ''));
        $len = function_exists('mb_strlen') ? mb_strlen($name) : strlen($name);
        if ($len < 2 || $len > 120) {
            $errors['name'] = 'Meno musí mať 2 – 120 znakov.';
        }

        $phone = (string)($d['phone'] ?? '');
        if (!preg_match('/^\+?[0-9 ()\/-]{7,20}$/', $phone)) {
            $errors['phone'] = 'Neplatný telefón.';
        }

        $email = (string)($d['email'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Neplatný e-mail.';
        }

        $note = (string)($d['note'] ?? '');
        $noteLen = function_exists('mb_strlen') ? mb_strlen($note) : strlen($note);
        if ($noteLen > 1000) {
            $errors['note'] = 'Poznámka môže mať max 1000 znakov.';
        }

        return $errors;
    }
}
```

- [ ] **Step 3: Run + verify**

Run: `php private/tests/run.php --filter ReservationTest`
Expected: `OK (8 tests)`.

- [ ] **Step 4: Commit**

```bash
git add private/lib/Reservation.php private/tests/unit/ReservationTest.php
git commit -m "feat(lib): Reservation validator with 8 rule tests"
```

---

### Task 5.3: RateLimit (file-based for simplicity)

**Files:**
- Create: `private/lib/RateLimit.php`
- Create: `private/tests/unit/RateLimitTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\RateLimit;
use PHPUnit\Framework\TestCase;

final class RateLimitTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/kuko-rl-' . uniqid();
        mkdir($this->dir, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->dir)) {
            foreach (glob($this->dir . '/*') as $f) @unlink($f);
            @rmdir($this->dir);
        }
    }

    public function testAllowsUnderLimit(): void
    {
        $rl = new RateLimit($this->dir, max: 3, windowSec: 3600);
        for ($i = 0; $i < 3; $i++) {
            $this->assertTrue($rl->allow('hash1', 'res'));
        }
    }

    public function testBlocksOverLimit(): void
    {
        $rl = new RateLimit($this->dir, max: 2, windowSec: 3600);
        $rl->allow('h', 'res');
        $rl->allow('h', 'res');
        $this->assertFalse($rl->allow('h', 'res'));
    }

    public function testWindowExpires(): void
    {
        $rl = new RateLimit($this->dir, max: 1, windowSec: 1);
        $rl->allow('h', 'res');
        sleep(2);
        $this->assertTrue($rl->allow('h', 'res'));
    }
}
```

- [ ] **Step 2: Implement RateLimit**

```php
<?php
// private/lib/RateLimit.php
declare(strict_types=1);
namespace Kuko;

final class RateLimit
{
    public function __construct(
        private string $dir,
        private int $max = 3,
        private int $windowSec = 3600,
    ) {
        if (!is_dir($dir)) {
            mkdir($dir, 0700, true);
        }
    }

    public function allow(string $ipHash, string $bucket): bool
    {
        $file = $this->dir . '/' . preg_replace('/[^a-f0-9]/i', '', $ipHash) . '.' . preg_replace('/[^a-z0-9_]/i', '', $bucket) . '.json';
        $now = time();
        $state = ['start' => $now, 'count' => 0];
        if (is_file($file)) {
            $raw = file_get_contents($file);
            $parsed = $raw === false ? null : json_decode($raw, true);
            if (is_array($parsed) && isset($parsed['start'], $parsed['count']) && $now - $parsed['start'] < $this->windowSec) {
                $state = $parsed;
            }
        }
        if ($state['count'] >= $this->max) return false;
        $state['count']++;
        file_put_contents($file, json_encode($state), LOCK_EX);
        return true;
    }
}
```

- [ ] **Step 3: Run + verify**

Run: `php private/tests/run.php --filter RateLimitTest`
Expected: `OK (3 tests)`.

- [ ] **Step 4: Commit**

```bash
git add private/lib/RateLimit.php private/tests/unit/RateLimitTest.php
git commit -m "feat(lib): file-based RateLimit per IP+bucket"
```

---

### Task 5.4: Recaptcha verifier

**Files:**
- Create: `private/lib/Recaptcha.php`
- Create: `private/tests/unit/RecaptchaTest.php`

- [ ] **Step 1: Failing test (mock HTTP via interface)**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\Recaptcha;
use Kuko\HttpClient;
use PHPUnit\Framework\TestCase;

final class RecaptchaTest extends TestCase
{
    private function client(array $response): HttpClient
    {
        return new class($response) implements HttpClient {
            public function __construct(private array $resp) {}
            public function postForm(string $url, array $params): array { return $this->resp; }
        };
    }

    public function testValidScore(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => true, 'score' => 0.9, 'action' => 'reservation']));
        $result = $r->verify('token', 'reservation');
        $this->assertTrue($result->ok);
        $this->assertSame(0.9, $result->score);
    }

    public function testLowScore(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => true, 'score' => 0.2, 'action' => 'reservation']));
        $this->assertFalse($r->verify('token', 'reservation')->ok);
    }

    public function testFailedResponse(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => false, 'error-codes' => ['invalid-input-response']]));
        $this->assertFalse($r->verify('bad', 'reservation')->ok);
    }

    public function testActionMismatch(): void
    {
        $r = new Recaptcha('secret', 0.5, $this->client(['success' => true, 'score' => 0.9, 'action' => 'other']));
        $this->assertFalse($r->verify('token', 'reservation')->ok);
    }
}
```

- [ ] **Step 2: Implement HttpClient + Recaptcha**

```php
<?php
// private/lib/HttpClient.php
declare(strict_types=1);
namespace Kuko;

interface HttpClient
{
    public function postForm(string $url, array $params): array;
}

final class CurlHttpClient implements HttpClient
{
    public function postForm(string $url, array $params): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $body = curl_exec($ch);
        if ($body === false) {
            curl_close($ch);
            return ['success' => false, 'error-codes' => ['curl-failed']];
        }
        curl_close($ch);
        $data = json_decode((string)$body, true);
        return is_array($data) ? $data : ['success' => false, 'error-codes' => ['bad-response']];
    }
}
```

```php
<?php
// private/lib/Recaptcha.php
declare(strict_types=1);
namespace Kuko;

final class RecaptchaResult
{
    public function __construct(
        public readonly bool $ok,
        public readonly ?float $score,
        public readonly ?string $reason,
    ) {}
}

final class Recaptcha
{
    public function __construct(
        private string $secret,
        private float $minScore,
        private HttpClient $http,
    ) {}

    public function verify(string $token, string $expectedAction): RecaptchaResult
    {
        if ($this->secret === '') {
            // dev mode bypass — explicit warning, never in prod
            return new RecaptchaResult(true, 1.0, 'no-secret');
        }
        $r = $this->http->postForm('https://www.google.com/recaptcha/api/siteverify', [
            'secret'   => $this->secret,
            'response' => $token,
        ]);
        if (empty($r['success'])) {
            return new RecaptchaResult(false, null, 'failed:' . implode(',', (array)($r['error-codes'] ?? [])));
        }
        if (($r['action'] ?? '') !== $expectedAction) {
            return new RecaptchaResult(false, (float)($r['score'] ?? 0), 'action-mismatch');
        }
        $score = (float)($r['score'] ?? 0);
        if ($score < $this->minScore) {
            return new RecaptchaResult(false, $score, 'low-score');
        }
        return new RecaptchaResult(true, $score, null);
    }
}
```

- [ ] **Step 3: Run + verify**

Run: `php private/tests/run.php --filter RecaptchaTest`
Expected: `OK (4 tests)`.

- [ ] **Step 4: Commit**

```bash
git add private/lib/Recaptcha.php private/lib/HttpClient.php private/tests/unit/RecaptchaTest.php
git commit -m "feat(lib): Recaptcha v3 verifier with HttpClient interface"
```

---

### Task 5.5: PHPMailer vendor

**Files:**
- Create: `private/lib/phpmailer/PHPMailer.php`, `SMTP.php`, `Exception.php`
- Modify: `private/lib/autoload.php`

- [ ] **Step 1: Stiahnuť PHPMailer release**

Run:
```bash
mkdir -p private/lib/phpmailer
curl -L https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.tar.gz | tar -xz -C /tmp/
cp /tmp/PHPMailer-6.9.1/src/PHPMailer.php /tmp/PHPMailer-6.9.1/src/SMTP.php /tmp/PHPMailer-6.9.1/src/Exception.php private/lib/phpmailer/
rm -rf /tmp/PHPMailer-6.9.1
```

- [ ] **Step 2: Append k autoload.php**

```php
spl_autoload_register(function (string $class): void {
    $prefix = 'PHPMailer\\PHPMailer\\';
    $baseDir = __DIR__ . '/phpmailer/';
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) return;
    $relative = substr($class, $len);
    $file = $baseDir . $relative . '.php';
    if (file_exists($file)) require $file;
});
```

- [ ] **Step 3: Smoke test load**

Run: `php -r "require 'private/lib/autoload.php'; var_dump(class_exists('PHPMailer\\PHPMailer\\PHPMailer'));"`
Expected: `bool(true)`.

- [ ] **Step 4: Commit**

```bash
git add private/lib/phpmailer/ private/lib/autoload.php
git commit -m "chore: vendor PHPMailer 6.9.1 without Composer"
```

---

### Task 5.6: Mailer class

**Files:**
- Create: `private/lib/Mailer.php`
- Create: `private/lib/MailerInterface.php`
- Create: `private/tests/unit/FakeMailerTest.php`

- [ ] **Step 1: Interface + fake for tests**

```php
<?php
// private/lib/MailerInterface.php
declare(strict_types=1);
namespace Kuko;

interface MailerInterface
{
    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool;
}

final class FakeMailer implements MailerInterface
{
    /** @var array<int,array<string,string|null>> */
    public array $sent = [];
    public bool $shouldFail = false;

    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool
    {
        if ($this->shouldFail) return false;
        $this->sent[] = compact('to', 'subject', 'htmlBody', 'textBody', 'replyTo');
        return true;
    }
}
```

- [ ] **Step 2: Real Mailer (PHPMailer wrapper)**

```php
<?php
// private/lib/Mailer.php
declare(strict_types=1);
namespace Kuko;

use PHPMailer\PHPMailer\PHPMailer;

final class Mailer implements MailerInterface
{
    public function __construct(private array $cfg) {}

    public function send(string $to, string $subject, string $htmlBody, string $textBody, ?string $replyTo = null): bool
    {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = $this->cfg['host'];
            $mail->Port       = (int)$this->cfg['port'];
            $mail->SMTPAuth   = true;
            $mail->Username   = $this->cfg['user'];
            $mail->Password   = $this->cfg['pass'];
            $mail->SMTPSecure = $this->cfg['encryption']; // 'ssl' or 'tls'
            $mail->CharSet    = 'UTF-8';
            $mail->setFrom($this->cfg['from_email'], $this->cfg['from_name']);
            if ($replyTo !== null) $mail->addReplyTo($replyTo);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;
            return $mail->send();
        } catch (\Throwable $e) {
            error_log('[Mailer] ' . $e->getMessage());
            return false;
        }
    }
}
```

- [ ] **Step 3: FakeMailer test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\FakeMailer;
use PHPUnit\Framework\TestCase;

final class FakeMailerTest extends TestCase
{
    public function testRecordsSends(): void
    {
        $m = new FakeMailer();
        $this->assertTrue($m->send('a@b.com', 'sub', '<p>x</p>', 'x'));
        $this->assertCount(1, $m->sent);
        $this->assertSame('a@b.com', $m->sent[0]['to']);
    }

    public function testFailsWhenFlagged(): void
    {
        $m = new FakeMailer();
        $m->shouldFail = true;
        $this->assertFalse($m->send('a@b.com', 'x', 'x', 'x'));
    }
}
```

- [ ] **Step 4: Run + verify**

Run: `php private/tests/run.php --filter FakeMailerTest`
Expected: `OK (2 tests)`.

- [ ] **Step 5: Commit**

```bash
git add private/lib/Mailer.php private/lib/MailerInterface.php private/tests/unit/FakeMailerTest.php
git commit -m "feat(lib): Mailer + interface + FakeMailer for tests"
```

---

### Task 5.7: Migration system + reservations table

**Files:**
- Create: `private/migrations/001_init.sql`
- Create: `private/migrations/run.php`

- [ ] **Step 1: Migration SQL**

```sql
-- private/migrations/001_init.sql
CREATE TABLE IF NOT EXISTS reservations (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    package       ENUM('mini','maxi','closed') NOT NULL,
    wished_date   DATE NOT NULL,
    wished_time   TIME NOT NULL,
    kids_count    TINYINT UNSIGNED NOT NULL,
    name          VARCHAR(120) NOT NULL,
    phone         VARCHAR(40)  NOT NULL,
    email         VARCHAR(180) NOT NULL,
    note          TEXT NULL,
    status        ENUM('pending','confirmed','cancelled') NOT NULL DEFAULT 'pending',
    ip_hash       CHAR(64) NOT NULL,
    recaptcha_score DECIMAL(3,2) NULL,
    user_agent    VARCHAR(255) NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status_date (status, wished_date),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admin_actions (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    admin_user    VARCHAR(60) NOT NULL,
    action        VARCHAR(40) NOT NULL,
    target_table  VARCHAR(40) NOT NULL,
    target_id     INT UNSIGNED NOT NULL,
    payload_json  JSON NULL,
    ip_hash       CHAR(64) NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_target (target_table, target_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS migrations (
    name VARCHAR(120) PRIMARY KEY,
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

- [ ] **Step 2: Migration runner**

```php
<?php
// private/migrations/run.php
declare(strict_types=1);

require __DIR__ . '/../lib/autoload.php';
\Kuko\Config::load(__DIR__ . '/../../config/config.php');

$db = \Kuko\Db::fromConfig();
$db->exec('CREATE TABLE IF NOT EXISTS migrations (name VARCHAR(120) PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB');

$applied = array_column($db->all('SELECT name FROM migrations'), 'name');
$files = glob(__DIR__ . '/*.sql');
sort($files);

foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        echo "= skip $name\n";
        continue;
    }
    echo "+ apply $name\n";
    $sql = file_get_contents($file);
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        $db->exec($stmt);
    }
    $db->execStmt('INSERT INTO migrations (name) VALUES (?)', [$name]);
    echo "  done\n";
}

echo "all migrations applied\n";
```

- [ ] **Step 3: Commit**

(Reálne spustenie čaká na nakonfigurovanú DB v M8; runner sám sa tu nespúšťa.)

```bash
git add private/migrations/
git commit -m "feat(db): migration runner + 001_init.sql with reservations table"
```

---

### Task 5.8: Reservation repository

**Files:**
- Create: `private/lib/ReservationRepo.php`
- Create: `private/tests/integration/ReservationRepoTest.php`

- [ ] **Step 1: Failing integration test (SQLite)**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\ReservationRepo;
use PHPUnit\Framework\TestCase;

final class ReservationRepoTest extends TestCase
{
    private Db $db;
    private ReservationRepo $repo;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("
            CREATE TABLE reservations (
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
              ip_hash TEXT NOT NULL,
              recaptcha_score REAL,
              user_agent TEXT,
              created_at TEXT NOT NULL DEFAULT (datetime('now')),
              updated_at TEXT NOT NULL DEFAULT (datetime('now'))
            )
        ");
        $this->repo = new ReservationRepo($this->db);
    }

    private function input(): array
    {
        return [
            'package' => 'mini', 'wished_date' => '2026-06-01', 'wished_time' => '14:00',
            'kids_count' => 10, 'name' => 'Test', 'phone' => '+421900000', 'email' => 't@t.sk',
            'note' => 'n', 'ip_hash' => str_repeat('a', 64), 'recaptcha_score' => 0.9, 'user_agent' => 'phpunit',
        ];
    }

    public function testInsert(): void
    {
        $id = $this->repo->create($this->input());
        $this->assertGreaterThan(0, $id);
        $row = $this->repo->find($id);
        $this->assertSame('mini', $row['package']);
        $this->assertSame('pending', $row['status']);
    }

    public function testListByStatus(): void
    {
        $this->repo->create($this->input());
        $this->repo->create($this->input() + ['package' => 'maxi']);
        $rows = $this->repo->list(['status' => 'pending']);
        $this->assertCount(2, $rows);
    }

    public function testChangeStatus(): void
    {
        $id = $this->repo->create($this->input());
        $this->assertTrue($this->repo->setStatus($id, 'confirmed'));
        $this->assertSame('confirmed', $this->repo->find($id)['status']);
    }

    public function testChangeStatusRejectsInvalid(): void
    {
        $id = $this->repo->create($this->input());
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->setStatus($id, 'bogus');
    }
}
```

- [ ] **Step 2: Implement ReservationRepo**

```php
<?php
// private/lib/ReservationRepo.php
declare(strict_types=1);
namespace Kuko;

final class ReservationRepo
{
    public function __construct(private Db $db) {}

    public function create(array $d): int
    {
        return $this->db->insert(
            'INSERT INTO reservations (package, wished_date, wished_time, kids_count, name, phone, email, note, ip_hash, recaptcha_score, user_agent)
             VALUES (:package, :wished_date, :wished_time, :kids_count, :name, :phone, :email, :note, :ip_hash, :recaptcha_score, :user_agent)',
            [
                ':package'         => $d['package'],
                ':wished_date'     => $d['wished_date'],
                ':wished_time'     => $d['wished_time'],
                ':kids_count'      => (int)$d['kids_count'],
                ':name'            => $d['name'],
                ':phone'           => $d['phone'],
                ':email'           => $d['email'],
                ':note'            => $d['note'] ?? null,
                ':ip_hash'         => $d['ip_hash'],
                ':recaptcha_score' => $d['recaptcha_score'] ?? null,
                ':user_agent'      => $d['user_agent'] ?? null,
            ]
        );
    }

    public function find(int $id): ?array
    {
        return $this->db->one('SELECT * FROM reservations WHERE id = ?', [$id]);
    }

    /** @param array{status?:string,package?:string,from?:string,to?:string,limit?:int,offset?:int} $filter */
    public function list(array $filter = []): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filter['status'])) {
            $where[] = 'status = ?';
            $params[] = $filter['status'];
        }
        if (!empty($filter['package'])) {
            $where[] = 'package = ?';
            $params[] = $filter['package'];
        }
        if (!empty($filter['from'])) {
            $where[] = 'wished_date >= ?';
            $params[] = $filter['from'];
        }
        if (!empty($filter['to'])) {
            $where[] = 'wished_date <= ?';
            $params[] = $filter['to'];
        }
        $limit  = max(1, min(500, (int)($filter['limit']  ?? 50)));
        $offset = max(0, (int)($filter['offset'] ?? 0));
        return $this->db->all(
            'SELECT * FROM reservations WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT ' . $limit . ' OFFSET ' . $offset,
            $params
        );
    }

    public function setStatus(int $id, string $status): bool
    {
        if (!in_array($status, Reservation::STATUSES, true)) {
            throw new \InvalidArgumentException("Invalid status: $status");
        }
        return $this->db->execStmt('UPDATE reservations SET status = ? WHERE id = ?', [$status, $id]) > 0;
    }
}
```

- [ ] **Step 3: Run + verify**

Run: `php private/tests/run.php --filter ReservationRepoTest`
Expected: `OK (4 tests)`.

- [ ] **Step 4: Commit**

```bash
git add private/lib/ReservationRepo.php private/tests/integration/ReservationRepoTest.php
git commit -m "feat(lib): ReservationRepo CRUD + filter + status change"
```

---

### Task 5.9: Mail templates

**Files:**
- Create: `private/templates/mail/reservation_admin.html.php`
- Create: `private/templates/mail/reservation_admin.text.php`
- Create: `private/templates/mail/reservation_customer.html.php`
- Create: `private/templates/mail/reservation_customer.text.php`

- [ ] **Step 1: Admin notification HTML**

```php
<?php /** @var array $r */ ?>
<!doctype html>
<html lang="sk"><body style="font-family:system-ui;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Nová rezervácia oslavy</h2>
<p>Vrátila sa nová požiadavka na rezerváciu balíčka <strong><?= e(strtoupper($r['package'])) ?></strong>.</p>
<table style="border-collapse:collapse;width:100%">
  <tr><td><strong>Termín:</strong></td><td><?= e($r['wished_date']) ?> o <?= e($r['wished_time']) ?></td></tr>
  <tr><td><strong>Počet detí:</strong></td><td><?= e($r['kids_count']) ?></td></tr>
  <tr><td><strong>Meno:</strong></td><td><?= e($r['name']) ?></td></tr>
  <tr><td><strong>Telefón:</strong></td><td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td></tr>
  <tr><td><strong>E-mail:</strong></td><td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td></tr>
  <tr><td valign="top"><strong>Poznámka:</strong></td><td><?= nl2br(e($r['note'] ?? '—')) ?></td></tr>
</table>
<p style="margin-top:2rem"><a href="https://kuko-detskysvet.sk/admin/" style="background:#D88BBE;color:white;padding:0.75rem 1.5rem;border-radius:999px;text-decoration:none">Otvoriť admin</a></p>
</body></html>
```

- [ ] **Step 2: Admin text plain**

```php
<?php /** @var array $r */ ?>
Nová rezervácia oslavy — balíček <?= strtoupper($r['package']) ?>

Termín: <?= $r['wished_date'] ?> o <?= $r['wished_time'] ?>
Počet detí: <?= $r['kids_count'] ?>
Meno: <?= $r['name'] ?>
Telefón: <?= $r['phone'] ?>
E-mail: <?= $r['email'] ?>
Poznámka: <?= $r['note'] ?? '—' ?>

Admin: https://kuko-detskysvet.sk/admin/
```

- [ ] **Step 3: Customer autoreply HTML + text**

```php
<?php /** @var array $r */ ?>
<!doctype html>
<html lang="sk"><body style="font-family:system-ui;line-height:1.5;max-width:600px;margin:0 auto;padding:1rem;color:#3D3D3D">
<h2 style="color:#D88BBE">Ďakujeme za vašu rezerváciu! 🎉</h2>
<p>Dobrý deň <?= e($r['name']) ?>,</p>
<p>prijali sme vašu požiadavku na rezerváciu balíčka <strong><?= e(strtoupper($r['package'])) ?></strong> dňa <strong><?= e($r['wished_date']) ?></strong> o <strong><?= e($r['wished_time']) ?></strong> pre <strong><?= e($r['kids_count']) ?></strong> detí.</p>
<p>Ozveme sa vám do 24 hodín na telefónne číslo alebo e-mail uvedený v rezervácii.</p>
<p>Ak by ste medzitým potrebovali niečo doplniť, napíšte nám na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a> alebo zavolajte +421 915 319 934.</p>
<p>Tešíme sa na vás!<br><strong>Tím KUKO detský svet</strong></p>
</body></html>
```

```php
Dobrý deň <?= $r['name'] ?>,

prijali sme vašu požiadavku na rezerváciu balíčka <?= strtoupper($r['package']) ?> dňa <?= $r['wished_date'] ?> o <?= $r['wished_time'] ?> pre <?= $r['kids_count'] ?> detí.

Ozveme sa vám do 24 hodín.

KUKO detský svet
info@kuko-detskysvet.sk | +421 915 319 934
```

- [ ] **Step 4: Commit**

```bash
git add private/templates/mail/
git commit -m "feat(mail): admin notification + customer autoreply templates"
```

---

### Task 5.10: API endpoint reservation.php

**Files:**
- Create: `public/api/reservation.php`
- Create: `public/api/.htaccess`
- Modify: `public/index.php` (delegovať `/api/reservation` POST)

- [ ] **Step 1: api/.htaccess — POST only**

```apache
<FilesMatch "\.php$">
    Order allow,deny
    Allow from all
</FilesMatch>
<LimitExcept POST>
    Order deny,allow
    Deny from all
</LimitExcept>
```

- [ ] **Step 2: api/reservation.php**

```php
<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/lib/App.php';
\Kuko\App::bootstrap();
session_start();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed']);
    return;
}

$raw = file_get_contents('php://input') ?: '';
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_json']);
    return;
}

// Honeypot — bot detection (fake success to prevent leakage)
if (!empty($data['website'])) {
    echo json_encode(['ok' => true, 'message' => 'Ďakujeme, ozveme sa do 24h.']);
    return;
}

// CSRF
$csrf = (string)($data['csrf'] ?? '');
if (!\Kuko\Csrf::verify($csrf)) {
    http_response_code(403);
    echo json_encode(['error' => 'csrf_invalid']);
    return;
}

// Rate limit
$secret = \Kuko\Config::get('security.ip_hash_secret', '');
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$ipHash = hash('sha256', $ip . '|' . $secret);
$rl = new \Kuko\RateLimit(APP_ROOT . '/private/logs/rate', \Kuko\Config::get('security.rate_limit_per_hour', 3));
if (!$rl->allow($ipHash, 'reservation')) {
    http_response_code(429);
    echo json_encode(['error' => 'rate_limited']);
    return;
}

// reCAPTCHA
$captcha = new \Kuko\Recaptcha(
    \Kuko\Config::get('recaptcha.secret_key', ''),
    (float)\Kuko\Config::get('recaptcha.min_score', 0.5),
    new \Kuko\CurlHttpClient()
);
$result = $captcha->verify((string)($data['recaptcha_token'] ?? ''), 'reservation');
if (!$result->ok) {
    http_response_code(400);
    echo json_encode(['error' => 'spam_blocked', 'reason' => $result->reason]);
    return;
}

// Validate
$errors = \Kuko\Reservation::validate($data);
if ($errors) {
    http_response_code(422);
    echo json_encode(['error' => 'validation', 'fields' => $errors]);
    return;
}

// Persist
$db = \Kuko\Db::fromConfig();
$repo = new \Kuko\ReservationRepo($db);
$id = $repo->create([
    'package'         => $data['package'],
    'wished_date'     => $data['wished_date'],
    'wished_time'     => $data['wished_time'],
    'kids_count'      => (int)$data['kids_count'],
    'name'            => trim((string)$data['name']),
    'phone'           => trim((string)$data['phone']),
    'email'           => trim((string)$data['email']),
    'note'            => trim((string)($data['note'] ?? '')) ?: null,
    'ip_hash'         => $ipHash,
    'recaptcha_score' => $result->score,
    'user_agent'      => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 250),
]);

// Mail
$mailCfg = \Kuko\Config::get('mail');
$mailer = new \Kuko\Mailer($mailCfg);
$renderer = new \Kuko\Renderer(APP_ROOT . '/private/templates/mail');

$record = $repo->find($id);
$adminHtml = $renderer->render('reservation_admin.html', ['r' => $record]);
$adminText = $renderer->render('reservation_admin.text', ['r' => $record]);
$mailer->send($mailCfg['admin_to'], "[KUKO] Nová rezervácia — " . strtoupper((string)$record['package']), $adminHtml, $adminText, (string)$record['email']);

$custHtml = $renderer->render('reservation_customer.html', ['r' => $record]);
$custText = $renderer->render('reservation_customer.text', ['r' => $record]);
$mailer->send((string)$record['email'], 'Potvrdenie prijatia rezervácie — KUKO detský svet', $custHtml, $custText);

echo json_encode(['ok' => true, 'id' => $id, 'message' => 'Ďakujeme, ozveme sa do 24h.']);
```

- [ ] **Step 3: Smoke test cez curl**

Run:
```bash
php -S 127.0.0.1:8000 -t public/ &
sleep 1
curl -X POST http://127.0.0.1:8000/api/reservation -H 'Content-Type: application/json' -d '{}'
```
Expected: JSON s `csrf_invalid` alebo `bad_json` (CSRF token chýba). Endpoint responds, neumiera.

- [ ] **Step 4: Commit**

```bash
git add public/api/reservation.php public/api/.htaccess
git commit -m "feat(api): POST /api/reservation with CSRF, rate limit, captcha, mailing"
```

---

## M6 — Rezervácie frontend

### Task 6.1: Modal HTML + CSS

**Files:**
- Create: `private/templates/reservation-modal.php`
- Modify: `private/templates/layout.php` (include modal)
- Modify: `public/assets/css/main.css`

- [ ] **Step 1: Modal template**

```php
<?php /** @var \Kuko\Csrf $csrf */ ?>
<dialog class="modal" id="reservation-modal" aria-labelledby="resv-title">
  <form class="modal__form" id="reservation-form" novalidate>
    <button type="button" class="modal__close" data-close-modal aria-label="Zavrieť">×</button>
    <h2 id="resv-title">Rezervácia oslavy</h2>
    <p class="modal__lead">Vyplňte detaily, ozveme sa do 24 hodín na potvrdenie.</p>

    <div class="modal__cookie-gate" id="modal-cookie-gate" hidden>
      <p>Pre odoslanie rezervácie potrebujeme váš súhlas s cookies (Google reCAPTCHA chráni formulár pred spamom).</p>
      <button type="button" class="btn" data-cookie-action="accept">Súhlasím s cookies</button>
    </div>

    <fieldset class="modal__fields">
      <input type="hidden" name="csrf" value="<?= e(\Kuko\Csrf::token()) ?>">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

      <label class="field">
        <span>Balíček</span>
        <select name="package" required>
          <option value="mini">KUKO MINI</option>
          <option value="maxi">KUKO MAXI</option>
          <option value="closed">Uzavretá spoločnosť</option>
        </select>
      </label>

      <div class="field-row">
        <label class="field">
          <span>Dátum</span>
          <input type="date" name="wished_date" required min="<?= date('Y-m-d') ?>">
        </label>
        <label class="field">
          <span>Čas</span>
          <input type="time" name="wished_time" required step="1800">
        </label>
        <label class="field">
          <span>Počet detí</span>
          <input type="number" name="kids_count" required min="1" max="50" value="10">
        </label>
      </div>

      <label class="field">
        <span>Meno a priezvisko</span>
        <input type="text" name="name" required minlength="2" maxlength="120">
      </label>

      <div class="field-row">
        <label class="field">
          <span>Telefón</span>
          <input type="tel" name="phone" required>
        </label>
        <label class="field">
          <span>E-mail</span>
          <input type="email" name="email" required>
        </label>
      </div>

      <label class="field">
        <span>Poznámka (voliteľné)</span>
        <textarea name="note" rows="3" maxlength="1000" placeholder="Téma oslavy, alergie, špeciálne želania…"></textarea>
      </label>

      <p class="modal__error" id="modal-error" hidden></p>

      <div class="modal__actions">
        <button type="button" class="btn btn--ghost" data-close-modal>Zrušiť</button>
        <button type="submit" class="btn" id="modal-submit">Odoslať rezerváciu</button>
      </div>
    </fieldset>

    <div class="modal__success" id="modal-success" hidden>
      <p class="modal__success-emoji" aria-hidden="true">🎉</p>
      <h3>Ďakujeme!</h3>
      <p>Prijali sme vašu rezerváciu. Ozveme sa do 24 hodín.</p>
      <button type="button" class="btn" data-close-modal>Zavrieť</button>
    </div>
  </form>
</dialog>
```

- [ ] **Step 2: Include do layout.php**

Pred cookie banner pridať:

```php
<?php require __DIR__ . '/reservation-modal.php'; ?>
```

- [ ] **Step 3: Modal CSS**

```css
.modal { padding: 0; border: 0; border-radius: var(--r-card); max-width: 600px; width: 90vw; box-shadow: 0 30px 60px rgba(0,0,0,0.25); }
.modal::backdrop { background: rgba(0,0,0,0.5); }
.modal__form { padding: var(--s-3) var(--s-4); position: relative; }
.modal__close {
  position: absolute; top: 0.5rem; right: 0.5rem;
  background: transparent; border: 0; width: 36px; height: 36px; border-radius: 50%; font-size: 1.5rem; cursor: pointer;
}
.modal__close:hover { background: rgba(0,0,0,0.05); }
.modal__lead { color: var(--c-text-soft); margin-bottom: var(--s-2); }
.modal__fields { border: 0; padding: 0; display: flex; flex-direction: column; gap: var(--s-2); }
.modal__cookie-gate { background: var(--bg-pink-soft); padding: var(--s-2); border-radius: var(--r-card); margin-bottom: var(--s-2); text-align: center; }
.field { display: flex; flex-direction: column; gap: 0.25rem; }
.field span { font-size: 0.85rem; color: var(--c-text-soft); }
.field input, .field select, .field textarea {
  padding: 0.625rem 0.875rem; border: 1px solid rgba(0,0,0,0.15); border-radius: 0.5rem; font: inherit;
  background: var(--c-white); transition: border-color 0.15s;
}
.field input:focus, .field select:focus, .field textarea:focus {
  outline: none; border-color: var(--c-accent); box-shadow: 0 0 0 3px rgba(216,139,190,0.2);
}
.field-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: var(--s-2); }
@media (max-width: 560px) { .field-row { grid-template-columns: 1fr; } }
.modal__error { color: #c0392b; background: #fdecea; padding: 0.75rem; border-radius: 0.5rem; }
.modal__actions { display: flex; justify-content: flex-end; gap: var(--s-1); margin-top: var(--s-2); flex-wrap: wrap; }
.modal__success { text-align: center; padding: var(--s-4) 0; }
.modal__success-emoji { font-size: 3rem; margin: 0; }
.field input:disabled, .field select:disabled, .field textarea:disabled, .btn:disabled { opacity: 0.6; cursor: not-allowed; }
```

- [ ] **Step 4: Commit**

```bash
git add private/templates/reservation-modal.php private/templates/layout.php public/assets/css/main.css
git commit -m "feat(frontend): reservation modal HTML + CSS with cookie gate"
```

---

### Task 6.2: reservation.js — modal logic + reCAPTCHA + submit

**Files:**
- Create: `public/assets/js/reservation.js`
- Modify: `public/assets/js/main.js` (import reservation.js)

- [ ] **Step 1: reservation.js**

```js
// public/assets/js/reservation.js
const modal = document.getElementById('reservation-modal');
const form = document.getElementById('reservation-form');
const errorBox = document.getElementById('modal-error');
const successBox = document.getElementById('modal-success');
const fields = form?.querySelector('.modal__fields');
const submitBtn = document.getElementById('modal-submit');
const cookieGate = document.getElementById('modal-cookie-gate');

const RECAPTCHA_SITE_KEY = document.querySelector('meta[name="recaptcha-site-key"]')?.content ?? '';
let recaptchaLoaded = false;

function consentAccepted() {
  return localStorage.getItem('kuko_cookie_consent') === 'accepted';
}

function loadRecaptcha() {
  if (recaptchaLoaded || !RECAPTCHA_SITE_KEY) return Promise.resolve();
  recaptchaLoaded = true;
  return new Promise((resolve) => {
    const s = document.createElement('script');
    s.src = `https://www.google.com/recaptcha/api.js?render=${encodeURIComponent(RECAPTCHA_SITE_KEY)}`;
    s.async = true;
    s.onload = () => resolve();
    document.head.appendChild(s);
  });
}

function updateCookieGate() {
  if (!consentAccepted()) {
    cookieGate.hidden = false;
    submitBtn.disabled = true;
  } else {
    cookieGate.hidden = true;
    submitBtn.disabled = false;
    loadRecaptcha();
  }
}

function openModal(pkg) {
  if (pkg) form.querySelector('[name="package"]').value = pkg;
  form.reset();
  if (pkg) form.querySelector('[name="package"]').value = pkg;
  errorBox.hidden = true;
  successBox.hidden = true;
  fields.hidden = false;
  modal.querySelector('.modal__actions').hidden = false;
  updateCookieGate();
  if (typeof modal.showModal === 'function') modal.showModal();
  else modal.setAttribute('open', '');
}

function closeModal() {
  if (typeof modal.close === 'function') modal.close();
  else modal.removeAttribute('open');
}

document.querySelectorAll('[data-open-reservation]').forEach(btn => {
  btn.addEventListener('click', () => openModal(btn.dataset.package || ''));
});
document.querySelectorAll('[data-close-modal]').forEach(btn => {
  btn.addEventListener('click', closeModal);
});
modal?.addEventListener('click', (e) => { if (e.target === modal) closeModal(); });

document.addEventListener('kuko:consent', updateCookieGate);

form?.addEventListener('submit', async (e) => {
  e.preventDefault();
  errorBox.hidden = true;
  submitBtn.disabled = true;
  submitBtn.textContent = 'Odosielam…';

  try {
    if (!consentAccepted()) throw new Error('Pre odoslanie potvrďte cookies.');
    await loadRecaptcha();

    const recaptchaToken = await new Promise((resolve, reject) => {
      if (!window.grecaptcha) return reject(new Error('reCAPTCHA sa nepodarilo načítať.'));
      window.grecaptcha.ready(() => {
        window.grecaptcha.execute(RECAPTCHA_SITE_KEY, { action: 'reservation' }).then(resolve).catch(reject);
      });
    });

    const formData = new FormData(form);
    const payload = Object.fromEntries(formData);
    payload.recaptcha_token = recaptchaToken;
    payload.kids_count = Number(payload.kids_count);

    const res = await fetch('/api/reservation', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify(payload),
    });
    const json = await res.json();

    if (!res.ok) {
      if (json.error === 'validation' && json.fields) {
        throw new Error('Skontrolujte: ' + Object.values(json.fields).join(' '));
      }
      throw new Error(json.error === 'rate_limited' ? 'Príliš veľa pokusov. Skúste neskôr.' : 'Odoslanie zlyhalo. Skúste prosím znova alebo zavolajte.');
    }

    fields.hidden = true;
    modal.querySelector('.modal__actions').hidden = true;
    successBox.hidden = false;
  } catch (err) {
    errorBox.textContent = err.message;
    errorBox.hidden = false;
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Odoslať rezerváciu';
  }
});
```

- [ ] **Step 2: Import v main.js**

Append: `import('./reservation.js').catch(() => {});`

- [ ] **Step 3: Add `<meta name="recaptcha-site-key">` v head.php**

```php
<?php
require dirname(__DIR__) . '/lib/Config.php';
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
?>
<meta name="recaptcha-site-key" content="<?= e($siteKey) ?>">
```

(Pozor: head.php už nemusí includovať Config, lebo App::bootstrap to spravil. Skontrolovať.)

- [ ] **Step 4: Verify manual flow**

- Otvoriť homepage → kliknúť „Rezervovať balíček" (MAXI) → modal otvorený s prednastaveným MAXI.
- Bez cookie consent: submit disabled + gate viditeľný.
- Súhlas cookies → submit enabled.
- Odoslať s neplatnými údajmi → server vráti `validation`, error sa zobrazí.
- Odoslať platne → success state.

- [ ] **Step 5: Commit**

```bash
git add public/assets/js/reservation.js public/assets/js/main.js private/templates/head.php
git commit -m "feat(frontend): reservation modal logic with cookie gate + reCAPTCHA"
```

---

## M7 — Admin panel

### Task 7.1: Apache Basic Auth + admin entry

**Files:**
- Create: `public/admin/.htaccess`
- Create: `public/admin/index.php`

- [ ] **Step 1: .htaccess (Basic Auth)**

```apache
AuthType Basic
AuthName "KUKO Admin"
AuthUserFile /full/path/to/public/admin/.htpasswd
Require valid-user

# Stále routujeme cez admin/index.php
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [QSA,L]
```

> Pozn.: `AuthUserFile` musí byť absolútna cesta v produkčnom prostredí. V deploy docs sa nastaví. Na lokálnom dev si Basic Auth necháme cez `dev` user (heslo `dev`) — vygenerujeme si nižšie.

- [ ] **Step 2: Vygenerovať dev .htpasswd**

Run:
```bash
htpasswd -nbB dev devpass > public/admin/.htpasswd
chmod 600 public/admin/.htpasswd
```
(Súbor je v .gitignore, nikdy do gitu.)

- [ ] **Step 3: admin/index.php**

```php
<?php
declare(strict_types=1);
require dirname(__DIR__, 2) . '/private/lib/App.php';
\Kuko\App::bootstrap();
session_start();

use Kuko\Db;
use Kuko\Renderer;
use Kuko\Router;
use Kuko\ReservationRepo;

$db = Db::fromConfig();
$repo = new ReservationRepo($db);
$renderer = new Renderer(APP_ROOT . '/private/templates/admin');

$router = new Router();

$router->get('/admin', function () use ($renderer, $repo) {
    $filter = [
        'status'  => $_GET['status']  ?? null,
        'package' => $_GET['package'] ?? null,
        'from'    => $_GET['from']    ?? null,
        'to'      => $_GET['to']      ?? null,
    ];
    $rows = $repo->list(array_filter($filter, fn($v) => $v !== null && $v !== ''));
    echo $renderer->render('list', ['rows' => $rows, 'filter' => $filter, 'user' => $_SERVER['PHP_AUTH_USER'] ?? '?']);
});

$router->get('/admin/reservation/{id}', function ($p) use ($renderer, $repo) {
    $row = $repo->find((int)$p['id']);
    if (!$row) { http_response_code(404); echo 'Not found'; return; }
    echo $renderer->render('detail', ['r' => $row, 'user' => $_SERVER['PHP_AUTH_USER'] ?? '?']);
});

$router->post('/admin/reservation/{id}/status', function ($p) use ($repo, $db) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) {
        http_response_code(403); echo 'csrf'; return;
    }
    $status = (string)($_POST['status'] ?? '');
    try {
        $repo->setStatus((int)$p['id'], $status);
        $secret = \Kuko\Config::get('security.ip_hash_secret', '');
        $db->execStmt(
            'INSERT INTO admin_actions (admin_user, action, target_table, target_id, payload_json, ip_hash) VALUES (?,?,?,?,?,?)',
            [$_SERVER['PHP_AUTH_USER'] ?? '?', 'set_status', 'reservations', (int)$p['id'], json_encode(['status' => $status]), hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . $secret)]
        );
    } catch (\InvalidArgumentException) {
        http_response_code(400); echo 'bad status'; return;
    }
    header('Location: /admin/reservation/' . (int)$p['id']);
});

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/admin', PHP_URL_PATH) ?: '/admin';
$match = $router->match($_SERVER['REQUEST_METHOD'], $path);
if ($match === null) {
    http_response_code(404); echo 'Admin route not found'; return;
}
($match->handler)($match->params ?? []);
```

- [ ] **Step 4: Update .gitignore (.htpasswd)**

Už pridané v Task 1.7.

- [ ] **Step 5: Commit**

```bash
git add public/admin/.htaccess public/admin/index.php
git commit -m "feat(admin): Basic Auth + dispatch router with list/detail/status"
```

---

### Task 7.2: Admin templates (layout + list + detail)

**Files:**
- Create: `private/templates/admin/layout.php`
- Create: `private/templates/admin/list.php`
- Create: `private/templates/admin/detail.php`
- Create: `public/assets/css/admin.css`

- [ ] **Step 1: Admin layout**

```php
<?php /** @var string $content */ /** @var string $title */ ?>
<!doctype html>
<html lang="sk">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'KUKO admin') ?></title>
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body>
<header class="admin-header">
  <div class="admin-header__inner">
    <h1>KUKO admin</h1>
    <nav><a href="/admin">Rezervácie</a> · <a href="/">Web ↗</a> · <span class="admin-user">@<?= e($user ?? '') ?></span></nav>
  </div>
</header>
<main class="admin-main"><?= $content ?></main>
</body>
</html>
```

- [ ] **Step 2: list.php**

```php
<?php
/** @var array $rows */
/** @var array $filter */
/** @var string $user */
$title = 'Rezervácie — KUKO admin';
ob_start();
$statusBadge = fn(string $s) => match($s) {
    'pending'   => 'badge badge--pending',
    'confirmed' => 'badge badge--ok',
    'cancelled' => 'badge badge--no',
    default     => 'badge'
};
?>
<form class="admin-filter" method="get" action="/admin">
  <select name="status">
    <option value="">Všetky statusy</option>
    <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
      <option value="<?= $s ?>" <?= ($filter['status'] ?? '') === $s ? 'selected' : '' ?>><?= $s ?></option>
    <?php endforeach; ?>
  </select>
  <select name="package">
    <option value="">Všetky balíčky</option>
    <?php foreach (['mini','maxi','closed'] as $p): ?>
      <option value="<?= $p ?>" <?= ($filter['package'] ?? '') === $p ? 'selected' : '' ?>><?= strtoupper($p) ?></option>
    <?php endforeach; ?>
  </select>
  <input type="date" name="from" value="<?= e($filter['from'] ?? '') ?>" placeholder="Od">
  <input type="date" name="to"   value="<?= e($filter['to'] ?? '') ?>"   placeholder="Do">
  <button type="submit">Filtrovať</button>
</form>

<?php if (!$rows): ?>
  <p>Žiadne rezervácie.</p>
<?php else: ?>
<table class="admin-table">
  <thead><tr><th>#</th><th>Vytvorené</th><th>Balíček</th><th>Termín</th><th>Meno</th><th>Telefón</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td>#<?= (int)$r['id'] ?></td>
      <td><?= e($r['created_at']) ?></td>
      <td><?= e(strtoupper($r['package'])) ?></td>
      <td><?= e($r['wished_date']) ?> <?= e(substr((string)$r['wished_time'], 0, 5)) ?></td>
      <td><?= e($r['name']) ?></td>
      <td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td>
      <td><span class="<?= $statusBadge((string)$r['status']) ?>"><?= e($r['status']) ?></span></td>
      <td><a href="/admin/reservation/<?= (int)$r['id'] ?>">Detail →</a></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
```

- [ ] **Step 3: detail.php**

```php
<?php
/** @var array $r */
/** @var string $user */
$title = 'Rezervácia #' . (int)$r['id'] . ' — KUKO admin';
$csrf = \Kuko\Csrf::token();
ob_start();
?>
<p><a href="/admin">← Späť na zoznam</a></p>
<h2>Rezervácia #<?= (int)$r['id'] ?></h2>
<table class="admin-detail">
  <tr><th>Balíček</th><td><?= e(strtoupper($r['package'])) ?></td></tr>
  <tr><th>Termín</th><td><?= e($r['wished_date']) ?> o <?= e(substr((string)$r['wished_time'], 0, 5)) ?></td></tr>
  <tr><th>Počet detí</th><td><?= (int)$r['kids_count'] ?></td></tr>
  <tr><th>Meno</th><td><?= e($r['name']) ?></td></tr>
  <tr><th>Telefón</th><td><a href="tel:<?= e($r['phone']) ?>"><?= e($r['phone']) ?></a></td></tr>
  <tr><th>E-mail</th><td><a href="mailto:<?= e($r['email']) ?>"><?= e($r['email']) ?></a></td></tr>
  <tr><th>Poznámka</th><td><?= nl2br(e($r['note'] ?? '—')) ?></td></tr>
  <tr><th>Vytvorené</th><td><?= e($r['created_at']) ?></td></tr>
  <tr><th>reCAPTCHA score</th><td><?= e($r['recaptcha_score'] ?? '—') ?></td></tr>
  <tr><th>Status</th><td><strong><?= e($r['status']) ?></strong></td></tr>
</table>

<form method="post" action="/admin/reservation/<?= (int)$r['id'] ?>/status" class="admin-status-form">
  <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  <label>Zmeniť status:
    <select name="status">
      <?php foreach (['pending','confirmed','cancelled'] as $s): ?>
        <option value="<?= $s ?>" <?= $r['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit">Uložiť</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
```

- [ ] **Step 4: admin.css**

```css
body { font-family: system-ui, sans-serif; margin: 0; background: #f8f9fa; color: #2c3e50; }
.admin-header { background: white; border-bottom: 1px solid #e0e6ed; }
.admin-header__inner { max-width: 1100px; margin: 0 auto; padding: 1rem; display: flex; align-items: center; justify-content: space-between; gap: 2rem; }
.admin-header h1 { font-size: 1.25rem; margin: 0; }
.admin-header nav { display: flex; gap: 1rem; align-items: center; font-size: 0.9rem; }
.admin-user { color: #888; font-size: 0.85rem; }
.admin-main { max-width: 1100px; margin: 0 auto; padding: 1.5rem 1rem; }
.admin-filter { display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
.admin-filter select, .admin-filter input, .admin-filter button {
  padding: 0.5rem 0.75rem; border-radius: 0.375rem; border: 1px solid #d0d7de; background: white; font: inherit;
}
.admin-filter button { background: #5e72e4; color: white; border-color: #5e72e4; cursor: pointer; }
.admin-table { width: 100%; border-collapse: collapse; background: white; border-radius: 0.5rem; overflow: hidden; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.admin-table th, .admin-table td { padding: 0.6rem 0.8rem; text-align: left; border-bottom: 1px solid #f0f0f0; font-size: 0.9rem; }
.admin-table th { background: #fafbfc; font-weight: 600; }
.admin-detail { background: white; border-radius: 0.5rem; padding: 1rem 1.5rem; max-width: 720px; }
.admin-detail th { text-align: left; padding-right: 1rem; color: #777; font-weight: 600; vertical-align: top; }
.admin-detail td { padding: 0.3rem 0; }
.admin-status-form { margin-top: 1rem; padding: 1rem; background: white; border-radius: 0.5rem; display: flex; gap: 0.5rem; align-items: center; max-width: 720px; }
.admin-status-form button { padding: 0.5rem 1rem; background: #5e72e4; color: white; border: 0; border-radius: 0.375rem; cursor: pointer; font: inherit; }
.badge { display: inline-block; padding: 0.15rem 0.5rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; }
.badge--pending  { background: #fff3cd; color: #856404; }
.badge--ok       { background: #d4edda; color: #155724; }
.badge--no       { background: #f8d7da; color: #721c24; }
```

- [ ] **Step 5: Commit**

```bash
git add private/templates/admin/ public/assets/css/admin.css
git commit -m "feat(admin): list + detail templates + admin.css"
```

---

### Task 7.3: Admin end-to-end smoke

- [ ] **Step 1: Inject test data**

Run (raz vytvoríme cez seed SQL alebo cez priamy API call):

```bash
sqlite3 /tmp/kuko_test.db < private/migrations/001_init.sql
# alebo proti reálnej MySQL DB cez private/migrations/run.php
```

(Skutočný admin sa nedá lokálne otestovať bez DB; necháme to na deploy fázu.)

- [ ] **Step 2: Commit (smoke deferred)**

---

## M8 — Polish & deploy

### Task 8.1: WebP konverzia obrázkov

**Files:**
- Create: `private/scripts/optimize-images.sh`
- Modify: `private/templates/sections/*.php` (use `<picture>`)

- [ ] **Step 1: Optimize script**

```bash
#!/usr/bin/env bash
# private/scripts/optimize-images.sh
set -e
cd "$(dirname "$0")/../../public/assets/img"
for f in *.jpg *.png; do
  [ -f "$f" ] || continue
  base="${f%.*}"
  cwebp -q 82 "$f" -o "${base}.webp"
  echo "✓ ${base}.webp"
done
```

Run: `chmod +x private/scripts/optimize-images.sh && ./private/scripts/optimize-images.sh`

(Vyžaduje `cwebp` — `brew install webp`.)

- [ ] **Step 2: Použiť `<picture>` v sections**

V hero.php:
```php
<div class="hero__bg" style="background-image: url('/assets/img/hero.webp'), url('/assets/img/hero.jpg')" aria-hidden="true"></div>
```

V galeria.php:
```php
<picture>
  <source srcset="/assets/img/galeria_<?= $i ?>.webp" type="image/webp">
  <img src="/assets/img/galeria_<?= $i ?>.jpg" loading="lazy" alt="Fotka z herne KUKO" width="400" height="280">
</picture>
```

(Lightbox `data-lightbox` smeruje na webp; src fallback na jpg ostáva.)

- [ ] **Step 3: Commit**

```bash
git add private/scripts/optimize-images.sh public/assets/img/*.webp private/templates/sections/
git commit -m "perf: WebP variants + picture fallback for raster images"
```

---

### Task 8.2: SEO — sitemap + robots

**Files:**
- Create: `public/sitemap.xml`
- Create: `public/robots.txt`

- [ ] **Step 1: sitemap.xml**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
  <url><loc>https://kuko-detskysvet.sk/</loc><changefreq>monthly</changefreq><priority>1.0</priority></url>
  <url><loc>https://kuko-detskysvet.sk/ochrana-udajov</loc><changefreq>yearly</changefreq><priority>0.3</priority></url>
</urlset>
```

- [ ] **Step 2: robots.txt**

```
User-agent: *
Allow: /
Disallow: /admin/
Disallow: /api/

Sitemap: https://kuko-detskysvet.sk/sitemap.xml
```

- [ ] **Step 3: Commit**

```bash
git add public/sitemap.xml public/robots.txt
git commit -m "seo: sitemap.xml + robots.txt"
```

---

### Task 8.3: Audit (Lighthouse + axe)

- [ ] **Step 1: Lighthouse cez Chrome DevTools**

Spustiť `php -S 127.0.0.1:8000 -t public/` a v Chrome → DevTools → Lighthouse → Mobile + Desktop. Cieľ:
- Performance > 80
- Accessibility > 90
- Best Practices > 90
- SEO > 90

- [ ] **Step 2: axe DevTools**

Spustiť axe rozšírenie na všetkých sekciách, opraviť issues.

- [ ] **Step 3: Manual a11y check**

- Klávesnica: TAB cez nav, hero CTA, oslavy CTA, modal sa otvára Enter-om.
- Modal: focus trap funguje, ESC zatvára.
- Screen reader test (VoiceOver na macOS): hlavička, nav, sekcie, modal sa správne popisujú.

- [ ] **Step 4: Commit prípadné fixes**

---

### Task 8.4: Deployment dokumentácia

**Files:**
- Create: `docs/DEPLOY.md`

- [ ] **Step 1: DEPLOY.md**

```markdown
# Deployment — KUKO detský svet

## Pre-deploy checklist

1. **Doména a hosting**
   - kuko-detskysvet.sk DNS smeruje na WebSupport.
   - SSL aktivovaný (Let's Encrypt automaticky vo WebSupport admine).

2. **Databáza**
   - Vo WebSupport admin → Databázy → vytvoriť MySQL/MariaDB DB (utf8mb4).
   - Poznamenať host, name, user, password.

3. **E-mail**
   - Mailbox info@kuko-detskysvet.sk vytvorený vo WebSupport admine.
   - SMTP login = e-mailová adresa, heslo = ako pre IMAP.

4. **reCAPTCHA**
   - Na https://www.google.com/recaptcha/admin/create vytvoriť v3 site key pre `kuko-detskysvet.sk`.
   - Poznamenať site key a secret.

5. **Config**
   - `cp config/config.example.php config/config.php`
   - Vyplniť všetky hodnoty (db, mail, recaptcha, ip_hash_secret = `openssl rand -hex 32`, social URLs).

6. **Admin auth**
   - Vygenerovať `.htpasswd`:
     ```bash
     htpasswd -nbB admin '<silne-heslo>' > public/admin/.htpasswd
     chmod 600 public/admin/.htpasswd
     ```
   - V `public/admin/.htaccess` upraviť `AuthUserFile` na absolútnu cestu.

7. **Migrácie**
   - `php private/migrations/run.php` (lokálne s prod creds alebo cez WebSupport SSH).

## Upload

Najprv staging:

1. Cez SFTP nahrať `public/` do verejného webroot (napr. `/web/`).
2. Cez SFTP nahrať `private/` MIMO webroot (ideálne `/private/`); ak nemožno, ostáva v repo a chránime cez `.htaccess`.
3. Cez SFTP nahrať `config/` MIMO webroot.

Štruktúra na WebSupporte:
```
/web/             # DocumentRoot
  index.php
  .htaccess
  admin/...
  api/...
  assets/...
/private/         # mimo webrootu (ideálne)
/config/
```

## Post-deploy smoke

- Otvoriť homepage → vidím všetkých 7 sekcií, mapa loaduje.
- Otvoriť /ochrana-udajov.
- Cookie banner sa zobrazí, súhlas funguje.
- Vyplniť testovaciu rezerváciu → e-mail dorazí (sandbox e-mail).
- Prihlásiť sa do /admin/ → vidieť rezerváciu, zmeniť status.

## Force HTTPS

V `public/.htaccess` odkomentovať:

```apache
RewriteCond %{HTTPS} !=on
RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
```

## Backup

WebSupport robí denné DB zálohy. Pre extra istotu:
- Týždenne stiahnuť SQL dump cez `mysqldump` cez SSH.
- Mesačná archivácia rezervácií starších ako 6m (TODO: cron skript v M9 ak treba).
```

- [ ] **Step 2: Commit**

```bash
git add docs/DEPLOY.md
git commit -m "docs: deployment guide for WebSupport"
```

---

### Task 8.5: Final all-green

- [ ] **Step 1: All tests pass**

Run: `php private/tests/run.php`
Expected: 100% green.

- [ ] **Step 2: PHP lint**

Run: `find private/lib private/tests public -name "*.php" -exec php -l {} \;`
Expected: žiadne errors.

- [ ] **Step 3: Final manual checklist**

- [ ] Homepage načíta < 2s.
- [ ] Všetky sekcie viditeľné a štýlované.
- [ ] Cookie banner sa zobrazí pri 1. návšteve.
- [ ] Modal sa otvára + valída + submituje.
- [ ] Admin chránený, list + detail + status funguje.
- [ ] Privacy + 404 renderujú.
- [ ] Lighthouse a11y > 90.

---

## Self-review (vykonané pri písaní)

1. **Spec coverage:** Všetky sekcie specu zmapované na tasky. Sekcia 10 specu (Otvorené body) je explicitne v plánu označené ako placeholder (oslavy copy, počet fotiek v galérii, sociálne URLs, presné súradnice mapy).
2. **Placeholder scan:** Žiadne TBD bez kontextu. Otvorené veci sú flagované s konkrétnym TODO. Placeholder copy pre balíčky je výslovne uvedené.
3. **Type consistency:** `Reservation::PACKAGES`, `Reservation::STATUSES` použité konzistentne v Repo, API, admin. `Csrf::token/verify` rovnaký podpis všade.
4. **Ambiguity:** Cookie gate flow je explicitné: bez consent sa modal otvára, ale submit blokovaný + gate widget. reCAPTCHA sa loaduje až po consent.

---

## Otvorené body — vyžadujú user input pred go-live

Tieto sú zo specu §10 a v pláne nahradené placeholder hodnotami:

1. **Logo source** — `assets/Image_logo.png` vs `Logo.jpeg`; ideálne SVG.
2. **Copy balíčkov** (MINI/MAXI/Uzavretá) — presné texty, počty detí, ceny, zoznamy zahŕňa.
3. **Galéria** — 5 alebo 6 fotiek; aktuálne plán používa 5.
4. **Sociálne siete** — FB, IG URLs.
5. **Súradnice mapy** — presné lat/lon Bratislavská 141.
6. **Otváracie hodiny výnimky** — sviatky?
7. **Provozné kontakty** — IČO/IBAN do footera/privacy ak treba?
