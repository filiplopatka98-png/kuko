# Admin CMS Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Vlastník KUKO si vie cez `/admin/` editovať všetok frontend obsah (texty, galéria, balíčky, kontakt, SEO, maintenance) bez vývojára; admin je v KUKO dizajne.

**Architecture:** DB `content_blocks` + `gallery_photos` + rozšírené `packages`. `Content::get(key, fallback)` helper — šablóny majú hardcoded fallbacky, web funguje aj pri prázdnej/nedostupnej DB. Quill WYSIWYG + server-side `HtmlSanitizer` whitelist. Maintenance/SEO sa presúvajú z `config.php` do `settings` tabuľky.

**Tech Stack:** PHP 8.1, MySQL/SQLite, PDO, Quill 2.x (vendored), GD/Imagick pre WebP, vanilla JS drag-drop.

**Spec:** `docs/specs/2026-05-15-admin-cms-design.md`

---

## Milestones

| # | Milestone | Tasks |
|---|---|---|
| M1 | Migrácia 005 + dátový model | 1 |
| M2 | Lib: HtmlSanitizer (TDD) | 1 |
| M3 | Lib: ContentBlocksRepo + Content helper (TDD) | 2 |
| M4 | Lib: MediaRepo (TDD) | 1 |
| M5 | SettingsRepo SEO/maintenance kľúče + Maintenance/head.php refactor | 2 |
| M6 | Seed skript (content + gallery z hardcoded) | 1 |
| M7 | Frontend: sekcie → Content::get s fallbackmi | 1 |
| M8 | Frontend: galéria + balíčky DB-driven | 1 |
| M9 | Quill vendor + admin layout rebrand (KUKO téma) | 2 |
| M10 | Admin: /admin/content (editor + Quill) | 1 |
| M11 | Admin: /admin/gallery (upload + reorder) | 1 |
| M12 | Admin: /admin/packages rozšírené + /admin/contact | 1 |
| M13 | Admin: /admin/seo + /admin/maintenance + /admin/log | 1 |
| M14 | Smoke test + deploy | 1 |

---

## M1 — Migrácia 005

### Task 1: Migrácia 005_cms.sql + dev DB schema

**Files:**
- Create: `private/migrations/005_cms.sql`
- Modify: `private/scripts/dev-db-init.php`

- [ ] **Step 1: Migrácia SQL (MySQL)**

```sql
-- private/migrations/005_cms.sql
CREATE TABLE IF NOT EXISTS content_blocks (
    block_key     VARCHAR(80) PRIMARY KEY,
    label         VARCHAR(120) NOT NULL,
    content_type  ENUM('text','html','image') NOT NULL DEFAULT 'text',
    value         MEDIUMTEXT NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    VARCHAR(60) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS gallery_photos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(180) NOT NULL,
    webp        VARCHAR(180) NULL,
    alt_text    VARCHAR(255) NOT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE packages ADD COLUMN description     TEXT NULL;
ALTER TABLE packages ADD COLUMN price_text      VARCHAR(40) NULL;
ALTER TABLE packages ADD COLUMN kids_count_text VARCHAR(40) NULL;
ALTER TABLE packages ADD COLUMN duration_text   VARCHAR(40) NULL;
ALTER TABLE packages ADD COLUMN included_json   TEXT NULL;
ALTER TABLE packages ADD COLUMN accent_color    VARCHAR(20) NULL;
```

- [ ] **Step 2: Pridať tabuľky do dev-db-init.php (SQLite varianta)**

V `private/scripts/dev-db-init.php` pred záverečný `echo`, pridať:

```php
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
```

- [ ] **Step 3: Re-init dev DB, verify**

Run: `/opt/homebrew/bin/php private/scripts/dev-db-init.php`
Expected: `Dev SQLite DB initialized` bez chyby.

Run: `/usr/bin/sqlite3 private/logs/kuko-dev.sqlite ".tables"`
Expected: zoznam obsahuje `content_blocks`, `gallery_photos`.

- [ ] **Step 4: Commit**

```bash
git add private/migrations/005_cms.sql private/scripts/dev-db-init.php
git commit -m "feat(db): migration 005 — content_blocks, gallery_photos, packages columns"
```

---

## M2 — HtmlSanitizer

### Task 2: HtmlSanitizer (whitelist, XSS-safe)

**Files:**
- Create: `private/lib/HtmlSanitizer.php`
- Create: `private/tests/unit/HtmlSanitizerTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use Kuko\HtmlSanitizer;
use PHPUnit\Framework\TestCase;

final class HtmlSanitizerTest extends TestCase
{
    public function testAllowsWhitelistedTags(): void
    {
        $in = '<p>Hello <strong>bold</strong> <em>it</em> <a href="https://x.sk">link</a></p>';
        $this->assertSame($in, HtmlSanitizer::clean($in));
    }

    public function testStripsScript(): void
    {
        $out = HtmlSanitizer::clean('<p>ok</p><script>alert(1)</script>');
        $this->assertStringNotContainsString('<script', $out);
        $this->assertStringContainsString('<p>ok</p>', $out);
    }

    public function testStripsOnAttributes(): void
    {
        $out = HtmlSanitizer::clean('<a href="https://x.sk" onclick="evil()">x</a>');
        $this->assertStringNotContainsString('onclick', $out);
        $this->assertStringContainsString('href="https://x.sk"', $out);
    }

    public function testStripsJavascriptHref(): void
    {
        $out = HtmlSanitizer::clean('<a href="javascript:alert(1)">x</a>');
        $this->assertStringNotContainsString('javascript:', $out);
    }

    public function testAllowsMailtoTel(): void
    {
        $in = '<a href="mailto:a@b.sk">m</a><a href="tel:+421900">t</a>';
        $out = HtmlSanitizer::clean($in);
        $this->assertStringContainsString('mailto:a@b.sk', $out);
        $this->assertStringContainsString('tel:+421900', $out);
    }

    public function testStripsDisallowedTagKeepsText(): void
    {
        $out = HtmlSanitizer::clean('<div><span>keep</span></div>');
        $this->assertStringContainsString('keep', $out);
        $this->assertStringNotContainsString('<div>', $out);
        $this->assertStringNotContainsString('<span>', $out);
    }

    public function testAllowsListsAndHeadings(): void
    {
        $in = '<h3>T</h3><ul><li>a</li><li>b</li></ul><ol><li>c</li></ol>';
        $this->assertSame($in, HtmlSanitizer::clean($in));
    }

    public function testEmptyStringSafe(): void
    {
        $this->assertSame('', HtmlSanitizer::clean(''));
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter HtmlSanitizerTest`
Expected: `Class "Kuko\HtmlSanitizer" not found`.

- [ ] **Step 3: Implement HtmlSanitizer**

```php
<?php
// private/lib/HtmlSanitizer.php
declare(strict_types=1);
namespace Kuko;

final class HtmlSanitizer
{
    private const ALLOWED_TAGS = ['b','i','strong','em','a','p','ul','ol','li','br','h3','h4'];

    public static function clean(string $html): string
    {
        if (trim($html) === '') return '';

        $dom = new \DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        // Wrap so DOMDocument parses a fragment; UTF-8 hint
        $dom->loadHTML(
            '<?xml encoding="UTF-8"><div id="__root">' . $html . '</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $root = $dom->getElementById('__root');
        if ($root === null) return '';

        self::sanitizeNode($root);

        $out = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $out .= $dom->saveHTML($child);
        }
        return trim($out);
    }

    private static function sanitizeNode(\DOMNode $node): void
    {
        // Iterate over a static copy — we mutate during traversal
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof \DOMText) {
                continue;
            }
            if (!($child instanceof \DOMElement)) {
                $node->removeChild($child);
                continue;
            }
            $tag = strtolower($child->nodeName);
            if (!in_array($tag, self::ALLOWED_TAGS, true)) {
                // Unwrap: replace element with its text/children content
                while ($child->firstChild) {
                    $node->insertBefore($child->firstChild, $child);
                }
                $node->removeChild($child);
                continue;
            }
            // Scrub attributes
            foreach (iterator_to_array($child->attributes) as $attr) {
                $name = strtolower($attr->name);
                if ($tag === 'a' && $name === 'href') {
                    $val = trim($attr->value);
                    if (!preg_match('#^(https?://|mailto:|tel:)#i', $val)) {
                        $child->removeAttribute($attr->name);
                    }
                } else {
                    // strip everything else (incl. all on* handlers, style, etc.)
                    $child->removeAttribute($attr->name);
                }
            }
            if ($tag === 'a' && $child->hasAttribute('href')) {
                $child->setAttribute('rel', 'noopener');
            }
            self::sanitizeNode($child);
        }
    }
}
```

- [ ] **Step 4: Run — verify PASS**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter HtmlSanitizerTest`
Expected: `OK (8 tests)`.

- [ ] **Step 5: Commit**

```bash
git add private/lib/HtmlSanitizer.php private/tests/unit/HtmlSanitizerTest.php
git commit -m "feat(lib): HtmlSanitizer whitelist XSS-safe cleaner"
```

---

## M3 — ContentBlocksRepo + Content helper

### Task 3: ContentBlocksRepo

**Files:**
- Create: `private/lib/ContentBlocksRepo.php`
- Create: `private/tests/integration/ContentBlocksRepoTest.php`

- [ ] **Step 1: Failing test (SQLite)**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\ContentBlocksRepo;
use PHPUnit\Framework\TestCase;

final class ContentBlocksRepoTest extends TestCase
{
    private Db $db;
    private ContentBlocksRepo $repo;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("CREATE TABLE content_blocks (
            block_key TEXT PRIMARY KEY, label TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT 'text', value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now')), updated_by TEXT)");
        $this->repo = new ContentBlocksRepo($this->db);
    }

    public function testSetCreatesThenGet(): void
    {
        $this->repo->set('hero.title', 'Vitajte', 'text', 'tester');
        $this->assertSame('Vitajte', $this->repo->get('hero.title'));
    }

    public function testGetMissingReturnsNull(): void
    {
        $this->assertNull($this->repo->get('missing.key'));
    }

    public function testSetUpdatesExisting(): void
    {
        $this->repo->set('k', 'v1', 'text', 'a');
        $this->repo->set('k', 'v2', 'text', 'b');
        $this->assertSame('v2', $this->repo->get('k'));
        $rows = $this->repo->all();
        $this->assertCount(1, $rows);
    }

    public function testListGroupedByPrefix(): void
    {
        $this->repo->set('hero.title', 'a', 'text', 't');
        $this->repo->set('hero.subtitle', 'b', 'text', 't');
        $this->repo->set('footer.copyright', 'c', 'text', 't');
        $grouped = $this->repo->listGrouped();
        $this->assertArrayHasKey('hero', $grouped);
        $this->assertArrayHasKey('footer', $grouped);
        $this->assertCount(2, $grouped['hero']);
    }

    public function testCacheInvalidatesOnSet(): void
    {
        $this->repo->set('k', 'first', 'text', 't');
        $this->assertSame('first', $this->repo->get('k'));
        $this->repo->set('k', 'second', 'text', 't');
        $this->assertSame('second', $this->repo->get('k'));
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter ContentBlocksRepoTest`
Expected: Class not found.

- [ ] **Step 3: Implement ContentBlocksRepo**

```php
<?php
// private/lib/ContentBlocksRepo.php
declare(strict_types=1);
namespace Kuko;

final class ContentBlocksRepo
{
    /** @var array<string,array<string,mixed>>|null */
    private ?array $cache = null;

    public function __construct(private Db $db) {}

    public function get(string $key): ?string
    {
        $this->load();
        return isset($this->cache[$key]) ? (string) $this->cache[$key]['value'] : null;
    }

    public function find(string $key): ?array
    {
        $this->load();
        return $this->cache[$key] ?? null;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        $this->load();
        return array_values($this->cache);
    }

    /** @return array<string,array<int,array<string,mixed>>> grouped by key prefix before first dot */
    public function listGrouped(): array
    {
        $this->load();
        $groups = [];
        foreach ($this->cache as $key => $row) {
            $prefix = strpos($key, '.') !== false ? substr($key, 0, strpos($key, '.')) : $key;
            $groups[$prefix][] = $row;
        }
        return $groups;
    }

    public function set(string $key, string $value, string $contentType, string $updatedBy, string $label = ''): void
    {
        if ($contentType === 'html') {
            $value = HtmlSanitizer::clean($value);
        }
        $exists = $this->db->one('SELECT block_key FROM content_blocks WHERE block_key = ?', [$key]) !== null;
        if ($exists) {
            $this->db->execStmt(
                'UPDATE content_blocks SET value = ?, content_type = ?, updated_by = ? WHERE block_key = ?',
                [$value, $contentType, $updatedBy, $key]
            );
        } else {
            $this->db->execStmt(
                'INSERT INTO content_blocks (block_key, label, content_type, value, updated_by) VALUES (?,?,?,?,?)',
                [$key, $label !== '' ? $label : $key, $contentType, $value, $updatedBy]
            );
        }
        $this->cache = null;
    }

    private function load(): void
    {
        if ($this->cache !== null) return;
        $this->cache = [];
        foreach ($this->db->all('SELECT * FROM content_blocks ORDER BY block_key') as $row) {
            $this->cache[(string) $row['block_key']] = $row;
        }
    }
}
```

- [ ] **Step 4: Run — verify PASS**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter ContentBlocksRepoTest`
Expected: `OK (5 tests)`.

- [ ] **Step 5: Commit**

```bash
git add private/lib/ContentBlocksRepo.php private/tests/integration/ContentBlocksRepoTest.php
git commit -m "feat(lib): ContentBlocksRepo with grouped listing + sanitize on set"
```

---

### Task 4: Content helper (static, fallback)

**Files:**
- Create: `private/lib/Content.php`
- Create: `private/tests/integration/ContentTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\Content;
use PHPUnit\Framework\TestCase;

final class ContentTest extends TestCase
{
    protected function setUp(): void
    {
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE content_blocks (
            block_key TEXT PRIMARY KEY, label TEXT NOT NULL,
            content_type TEXT NOT NULL DEFAULT 'text', value TEXT NOT NULL,
            updated_at TEXT NOT NULL DEFAULT (datetime('now')), updated_by TEXT)");
        $db->execStmt("INSERT INTO content_blocks (block_key,label,content_type,value) VALUES ('hero.title','x','text','Z DB')");
        Content::setDb($db);
        Content::reset();
    }

    public function testReturnsDbValue(): void
    {
        $this->assertSame('Z DB', Content::get('hero.title', 'fallback'));
    }

    public function testReturnsFallbackWhenMissing(): void
    {
        $this->assertSame('fallback', Content::get('nope.key', 'fallback'));
    }

    public function testYearTokenReplaced(): void
    {
        $this->assertSame(
            'Copyright ' . date('Y'),
            Content::get('missing', 'Copyright {{year}}')
        );
    }

    public function testFallsBackGracefullyWithoutDb(): void
    {
        Content::setDb(null);
        Content::reset();
        $this->assertSame('safe', Content::get('any.key', 'safe'));
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter ContentTest`
Expected: Class not found.

- [ ] **Step 3: Implement Content**

```php
<?php
// private/lib/Content.php
declare(strict_types=1);
namespace Kuko;

final class Content
{
    private static ?Db $db = null;
    private static ?ContentBlocksRepo $repo = null;
    private static bool $triedConfig = false;

    public static function setDb(?Db $db): void
    {
        self::$db = $db;
        self::$repo = null;
    }

    public static function reset(): void
    {
        self::$repo = null;
    }

    public static function get(string $key, string $fallback = ''): string
    {
        $value = self::lookup($key);
        $result = $value ?? $fallback;
        // {{year}} token (footer copyright keeps dynamic year after DB move)
        if (str_contains($result, '{{year}}')) {
            $result = str_replace('{{year}}', date('Y'), $result);
        }
        return $result;
    }

    private static function lookup(string $key): ?string
    {
        try {
            $repo = self::repo();
            if ($repo === null) return null;
            return $repo->get($key);
        } catch (\Throwable) {
            // DB down / table missing → graceful fallback
            return null;
        }
    }

    private static function repo(): ?ContentBlocksRepo
    {
        if (self::$repo !== null) return self::$repo;
        if (self::$db === null) {
            if (self::$triedConfig) return null;
            self::$triedConfig = true;
            try {
                self::$db = Db::fromConfig();
            } catch (\Throwable) {
                return null;
            }
        }
        self::$repo = new ContentBlocksRepo(self::$db);
        return self::$repo;
    }
}
```

- [ ] **Step 4: Run — verify PASS**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter ContentTest`
Expected: `OK (4 tests)`.

- [ ] **Step 5: Commit**

```bash
git add private/lib/Content.php private/tests/integration/ContentTest.php
git commit -m "feat(lib): Content helper with fallback + {{year}} token + graceful DB failure"
```

---

## M4 — MediaRepo

### Task 5: MediaRepo (upload, WebP, reorder)

**Files:**
- Create: `private/lib/MediaRepo.php`
- Create: `private/tests/integration/MediaRepoTest.php`
- Create dir: `public/assets/img/gallery/` (with `.gitkeep`)

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\MediaRepo;
use PHPUnit\Framework\TestCase;

final class MediaRepoTest extends TestCase
{
    private Db $db;
    private string $dir;
    private MediaRepo $repo;

    protected function setUp(): void
    {
        $this->db = Db::fromDsn('sqlite::memory:');
        $this->db->exec("CREATE TABLE gallery_photos (
            id INTEGER PRIMARY KEY AUTOINCREMENT, filename TEXT NOT NULL, webp TEXT,
            alt_text TEXT NOT NULL, sort_order INTEGER NOT NULL DEFAULT 0,
            is_visible INTEGER NOT NULL DEFAULT 1, uploaded_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $this->dir = sys_get_temp_dir() . '/kuko-media-' . uniqid();
        mkdir($this->dir, 0777, true);
        $this->repo = new MediaRepo($this->db, $this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) @unlink($f);
        @rmdir($this->dir);
    }

    private function fakeUpload(): array
    {
        // 2x2 px PNG
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAIAAAACCAYAAABytg0kAAAAEklEQVR42mP8z8BQz0AEYBxVSF8FAP2FBfwlrJW8AAAAAElFTkSuQmCC');
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $png);
        return ['name' => 'test.png', 'type' => 'image/png', 'tmp_name' => $tmp, 'error' => 0, 'size' => strlen($png)];
    }

    public function testUploadInsertsRow(): void
    {
        $row = $this->repo->upload($this->fakeUpload(), 'Alt text');
        $this->assertGreaterThan(0, $row['id']);
        $this->assertSame('Alt text', $row['alt_text']);
        $this->assertFileExists($this->dir . '/' . $row['filename']);
    }

    public function testListVisibleOrdersBySort(): void
    {
        $a = $this->repo->upload($this->fakeUpload(), 'A');
        $b = $this->repo->upload($this->fakeUpload(), 'B');
        $this->repo->reorder([$b['id'], $a['id']]);
        $list = $this->repo->listVisible();
        $this->assertSame($b['id'], $list[0]['id']);
    }

    public function testSetVisibilityHides(): void
    {
        $a = $this->repo->upload($this->fakeUpload(), 'A');
        $this->repo->setVisibility($a['id'], false);
        $this->assertCount(0, $this->repo->listVisible());
        $this->assertCount(1, $this->repo->listAll());
    }

    public function testDeleteRemovesRowAndFile(): void
    {
        $a = $this->repo->upload($this->fakeUpload(), 'A');
        $path = $this->dir . '/' . $a['filename'];
        $this->assertFileExists($path);
        $this->repo->delete($a['id']);
        $this->assertCount(0, $this->repo->listAll());
        $this->assertFileDoesNotExist($path);
    }

    public function testRejectsNonImage(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, 'not an image');
        $this->expectException(\RuntimeException::class);
        $this->repo->upload(['name' => 'x.txt', 'type' => 'text/plain', 'tmp_name' => $tmp, 'error' => 0, 'size' => 12], 'A');
    }

    public function testUpdateAlt(): void
    {
        $a = $this->repo->upload($this->fakeUpload(), 'Old');
        $this->repo->updateAlt($a['id'], 'New alt');
        $this->assertSame('New alt', $this->repo->listAll()[0]['alt_text']);
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter MediaRepoTest`
Expected: Class not found.

- [ ] **Step 3: Implement MediaRepo**

```php
<?php
// private/lib/MediaRepo.php
declare(strict_types=1);
namespace Kuko;

final class MediaRepo
{
    private const MAX_BYTES = 5 * 1024 * 1024;
    private const MAX_DIM = 2000;
    private const ALLOWED = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    public function __construct(private Db $db, private string $dir)
    {
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0775, true);
        }
    }

    /** @param array{name:string,type:string,tmp_name:string,error:int,size:int} $file */
    public function upload(array $file, string $alt): array
    {
        if (($file['error'] ?? 1) !== 0) {
            throw new \RuntimeException('Upload zlyhal (error ' . $file['error'] . ').');
        }
        if (($file['size'] ?? 0) > self::MAX_BYTES) {
            throw new \RuntimeException('Súbor je príliš veľký (max 5 MB).');
        }
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($file['tmp_name']);
        if (!isset(self::ALLOWED[$mime])) {
            throw new \RuntimeException('Nepovolený typ súboru. Povolené: JPG, PNG, WebP.');
        }
        $ext = self::ALLOWED[$mime];
        $base = 'gal_' . bin2hex(random_bytes(8));
        $filename = $base . '.' . $ext;
        $dest = $this->dir . '/' . $filename;

        $img = $this->loadImage($file['tmp_name'], $mime);
        $img = $this->resize($img, self::MAX_DIM);
        $this->saveImage($img, $dest, $mime);

        $webpName = null;
        if (function_exists('imagewebp')) {
            $webpName = $base . '.webp';
            @imagewebp($img, $this->dir . '/' . $webpName, 82);
        }
        imagedestroy($img);

        $sort = (int) ($this->db->one('SELECT COALESCE(MAX(sort_order),0)+1 AS s FROM gallery_photos')['s'] ?? 1);
        $id = $this->db->insert(
            'INSERT INTO gallery_photos (filename, webp, alt_text, sort_order) VALUES (?,?,?,?)',
            [$filename, $webpName, $alt, $sort]
        );
        return $this->db->one('SELECT * FROM gallery_photos WHERE id = ?', [$id]);
    }

    /** @return array<int,array<string,mixed>> */
    public function listVisible(): array
    {
        return $this->db->all('SELECT * FROM gallery_photos WHERE is_visible = 1 ORDER BY sort_order, id');
    }

    /** @return array<int,array<string,mixed>> */
    public function listAll(): array
    {
        return $this->db->all('SELECT * FROM gallery_photos ORDER BY sort_order, id');
    }

    public function setVisibility(int $id, bool $visible): void
    {
        $this->db->execStmt('UPDATE gallery_photos SET is_visible = ? WHERE id = ?', [$visible ? 1 : 0, $id]);
    }

    public function updateAlt(int $id, string $alt): void
    {
        $this->db->execStmt('UPDATE gallery_photos SET alt_text = ? WHERE id = ?', [$alt, $id]);
    }

    /** @param int[] $idOrder */
    public function reorder(array $idOrder): void
    {
        $pos = 1;
        foreach ($idOrder as $id) {
            $this->db->execStmt('UPDATE gallery_photos SET sort_order = ? WHERE id = ?', [$pos++, (int) $id]);
        }
    }

    public function delete(int $id): void
    {
        $row = $this->db->one('SELECT * FROM gallery_photos WHERE id = ?', [$id]);
        if ($row === null) return;
        @unlink($this->dir . '/' . $row['filename']);
        if (!empty($row['webp'])) {
            @unlink($this->dir . '/' . $row['webp']);
        }
        $this->db->execStmt('DELETE FROM gallery_photos WHERE id = ?', [$id]);
    }

    private function loadImage(string $path, string $mime): \GdImage
    {
        $img = match ($mime) {
            'image/jpeg' => imagecreatefromjpeg($path),
            'image/png'  => imagecreatefrompng($path),
            'image/webp' => imagecreatefromwebp($path),
            default      => false,
        };
        if ($img === false) {
            throw new \RuntimeException('Obrázok sa nepodarilo načítať.');
        }
        return $img;
    }

    private function resize(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        if ($w <= $max && $h <= $max) return $img;
        $ratio = $w / $h;
        if ($w > $h) { $nw = $max; $nh = (int) round($max / $ratio); }
        else         { $nh = $max; $nw = (int) round($max * $ratio); }
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        imagedestroy($img);
        return $dst;
    }

    private function saveImage(\GdImage $img, string $dest, string $mime): void
    {
        $ok = match ($mime) {
            'image/jpeg' => imagejpeg($img, $dest, 85),
            'image/png'  => imagepng($img, $dest, 6),
            'image/webp' => imagewebp($img, $dest, 85),
            default      => false,
        };
        if (!$ok) {
            throw new \RuntimeException('Obrázok sa nepodarilo uložiť.');
        }
    }
}
```

- [ ] **Step 4: Run — verify PASS**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter MediaRepoTest`
Expected: `OK (6 tests)`. (GD musí byť dostupné — `php -m | grep -i gd`.)

- [ ] **Step 5: Vytvoriť gallery dir + commit**

```bash
mkdir -p public/assets/img/gallery && touch public/assets/img/gallery/.gitkeep
git add private/lib/MediaRepo.php private/tests/integration/MediaRepoTest.php public/assets/img/gallery/.gitkeep
git commit -m "feat(lib): MediaRepo — upload/resize/WebP/reorder/delete with finfo MIME guard"
```

---

## M5 — SettingsRepo SEO/maintenance + refactor

### Task 6: Migrate Maintenance to SettingsRepo

**Files:**
- Modify: `private/lib/Maintenance.php`
- Create: `private/tests/integration/MaintenanceSettingsTest.php`

- [ ] **Step 1: Failing test**

```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Integration;
use Kuko\Db;
use Kuko\SettingsRepo;
use Kuko\Maintenance;
use Kuko\Config;
use PHPUnit\Framework\TestCase;

final class MaintenanceSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
        Config::load(__DIR__ . '/../fixtures/config.test.php');
        $db = Db::fromDsn('sqlite::memory:');
        $db->exec("CREATE TABLE settings (setting_key TEXT PRIMARY KEY, value TEXT NOT NULL, updated_at TEXT NOT NULL DEFAULT (datetime('now')))");
        $db->execStmt("INSERT INTO settings (setting_key,value) VALUES ('maintenance.enabled','1')");
        $db->execStmt("INSERT INTO settings (setting_key,value) VALUES ('maintenance.password','dbpass')");
        Maintenance::setSettings(new SettingsRepo($db));
    }

    public function testEnabledFromSettings(): void
    {
        $this->assertTrue(Maintenance::enabled());
    }

    public function testPasswordFromSettings(): void
    {
        $this->assertTrue(Maintenance::passwordMatches('dbpass'));
        $this->assertFalse(Maintenance::passwordMatches('wrong'));
    }

    protected function tearDown(): void
    {
        Maintenance::setSettings(null);
    }
}
```

- [ ] **Step 2: Run — verify FAIL**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter MaintenanceSettingsTest`
Expected: FAIL — `setSettings` not defined.

- [ ] **Step 3: Modify Maintenance to read SettingsRepo with config fallback**

V `private/lib/Maintenance.php` pridať na začiatok triedy (za existujúce konštanty):

```php
    private static ?SettingsRepo $settings = null;

    public static function setSettings(?SettingsRepo $s): void
    {
        self::$settings = $s;
    }

    private static function settings(): ?SettingsRepo
    {
        if (self::$settings !== null) return self::$settings;
        try {
            self::$settings = new SettingsRepo(Db::fromConfig());
        } catch (\Throwable) {
            return null;
        }
        return self::$settings;
    }
```

Nahradiť telo `enabled()`:

```php
    public static function enabled(): bool
    {
        $s = self::settings();
        if ($s !== null) {
            $v = $s->get('maintenance.enabled');
            if ($v !== null) return $v === '1';
        }
        return (bool) Config::get('app.maintenance', false);
    }
```

Nahradiť telo `passwordMatches()`:

```php
    public static function passwordMatches(string $given): bool
    {
        $s = self::settings();
        $expected = '';
        if ($s !== null) {
            $expected = (string) $s->get('maintenance.password', '');
        }
        if ($expected === '') {
            $expected = (string) Config::get('app.maintenance_password', '');
        }
        if ($expected === '') return false;
        return hash_equals($expected, $given);
    }
```

A `expectedCookieValue()` — použiť rovnaký zdroj hesla:

```php
    private static function expectedCookieValue(): string
    {
        $s = self::settings();
        $password = $s !== null ? (string) $s->get('maintenance.password', '') : '';
        if ($password === '') {
            $password = (string) Config::get('app.maintenance_password', '');
        }
        $secret = (string) Config::get('auth.secret', '');
        return hash('sha256', 'maintenance|' . $password . '|' . $secret);
    }
```

Pridať `use` ak treba: `Maintenance.php` je v namespace `Kuko`, `SettingsRepo`/`Db`/`Config` sú v rovnakom namespace — netreba use.

- [ ] **Step 4: Run — verify PASS**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar --filter MaintenanceSettingsTest`
Expected: `OK (2 tests)`.

- [ ] **Step 5: Regression — celý suite**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar`
Expected: všetko zelené (existujúce maintenance testy musia stále prejsť cez config fallback).

- [ ] **Step 6: Commit**

```bash
git add private/lib/Maintenance.php private/tests/integration/MaintenanceSettingsTest.php
git commit -m "refactor(maintenance): read enabled/password from SettingsRepo with config fallback"
```

---

### Task 7: SEO meta z SettingsRepo (head.php)

**Files:**
- Modify: `private/templates/head.php`
- Modify: `private/templates/layout-minimal.php`

- [ ] **Step 1: Helper na čítanie SEO settings v head.php**

Na začiatku `head.php` (po `$baseUrl` výpočte) pridať:

```php
<?php
// SEO settings: DB-driven with hardcoded fallback. $pageType determines key prefix.
$seoDb = null;
try { $seoDb = new \Kuko\SettingsRepo(\Kuko\Db::fromConfig()); } catch (\Throwable) {}
$seoGet = function (string $k, string $fallback) use ($seoDb): string {
    if ($seoDb !== null) {
        $v = $seoDb->get($k);
        if ($v !== null && $v !== '') return $v;
    }
    return $fallback;
};
$pt = $pageType ?? 'default';
$titleFinal = $seoGet("seo.$pt.title", $titleFinal);
$descriptionFinal = $seoGet("seo.$pt.description", $descriptionFinal);
$globalIndexing = ($seoGet('seo.public_indexing', $globalIndexing ? '1' : '0') === '1');
$index = $pageIndexing ?? $globalIndexing;
$robots = $index ? 'index, follow' : 'noindex, nofollow';
?>
```

(Vloží sa za existujúci výpočet `$titleFinal`/`$descriptionFinal`/`$robots`, prepíše ich DB hodnotami ak existujú.)

- [ ] **Step 2: Smoke — head still renders**

Run: `/opt/homebrew/bin/php -l private/templates/head.php`
Expected: `No syntax errors`.

Spustiť dev server, `curl -s http://127.0.0.1:8123/ | grep '<title>'` — musí vrátiť titul (fallback, lebo seed ešte nie je).

- [ ] **Step 3: Commit**

```bash
git add private/templates/head.php private/templates/layout-minimal.php
git commit -m "feat(seo): head.php reads title/description/indexing from SettingsRepo with fallback"
```

---

## M6 — Seed skript

### Task 8: Seed content_blocks + gallery z hardcoded

**Files:**
- Create: `private/scripts/seed-cms.php`

- [ ] **Step 1: Seed skript**

```php
<?php
// private/scripts/seed-cms.php — one-shot: naplní content_blocks + gallery_photos
// + settings (maintenance/SEO z config) ak ešte prázdne. Idempotentné.
declare(strict_types=1);

require __DIR__ . '/../lib/autoload.php';
\Kuko\Config::load(__DIR__ . '/../../config/config.php');
$db = \Kuko\Db::fromConfig();
$cb = new \Kuko\ContentBlocksRepo($db);

$blocks = [
    ['hero.title', 'Hero — nadpis', 'text', 'Detský svet KUKO'],
    ['hero.subtitle', 'Hero — podtitul', 'text', 'pre radosť detí & pohodu rodičov'],
    ['about.lead', 'O nás — úvodný odsek', 'html', '<p>KUKO je interiérové detské ihrisko spojené s kaviarňou v Piešťanoch, vytvorené pre radosť detí a pohodlie rodičov.</p>'],
    ['about.card1.title', 'O nás — karta 1 nadpis', 'text', 'Bezpečný, čistý a hravý priestor'],
    ['about.card1.body', 'O nás — karta 1 text', 'text', 'kde sa deti môžu vyšantiť, objavovať a tráviť čas aktívne.'],
    ['about.card2.title', 'O nás — karta 2 nadpis', 'text', 'kvalitnú kávu a chvíľku oddychu'],
    ['about.card2.body', 'O nás — karta 2 text', 'text', 'Rodičia si zatiaľ môžu vychutnať kvalitnú kávu v príjemnom prostredí.'],
    ['about.card3.title', 'O nás — karta 3 nadpis', 'text', 'Ideálne miesto na stretnutie'],
    ['about.card3.body', 'O nás — karta 3 text', 'text', 's priateľmi či rodinou, alebo len chvíľu pre seba.'],
    ['about.card4.title', 'O nás — karta 4 nadpis', 'text', 'Organizujeme aj detské oslavy'],
    ['about.card4.body', 'O nás — karta 4 text', 'text', 'ktoré pripravíme s dôrazom na radosť detí a bezstarostnosť pre rodičov.'],
    ['cennik.lead', 'Cenník — úvod', 'text', 'Chceme, aby bol čas strávený u nás dostupný a príjemný pre každého.'],
    ['cennik.item1.label', 'Cenník — riadok 1', 'text', 'Dieťa do 1 roku'],
    ['cennik.item1.price', 'Cenník — cena 1', 'text', 'ZADARMO'],
    ['cennik.item2.label', 'Cenník — riadok 2', 'text', 'Dieťa od 1 roku'],
    ['cennik.item2.price', 'Cenník — cena 2', 'text', '5,00 € / hod'],
    ['cennik.item3.label', 'Cenník — riadok 3', 'text', 'Dieťa od 1 roku neobmedzene'],
    ['cennik.item3.price', 'Cenník — cena 3', 'text', '15,00 €'],
    ['kontakt.address', 'Kontakt — adresa', 'text', 'Bratislavská 141, 921 01 Piešťany'],
    ['kontakt.phone', 'Kontakt — telefón', 'text', '+421 915 319 934'],
    ['kontakt.email', 'Kontakt — e-mail', 'text', 'info@kuko-detskysvet.sk'],
    ['kontakt.hours', 'Kontakt — otváracie hodiny', 'text', 'Pondelok – Nedeľa: 9:00 – 20:00'],
    ['footer.copyright', 'Footer — copyright', 'text', 'Copyright © {{year}} KUKO-detskysvet.sk | Všetky práva vyhradené.'],
];
foreach ($blocks as [$k, $label, $type, $val]) {
    if ($cb->get($k) === null) {
        $cb->set($k, $val, $type, 'seed', $label);
        echo "+ block $k\n";
    } else {
        echo "= skip $k\n";
    }
}

// Gallery seed
$existing = (int) ($db->one('SELECT COUNT(*) AS c FROM gallery_photos')['c'] ?? 0);
if ($existing === 0) {
    $alts = [
        1 => 'Detský kútik KUKO — narodeninová oslava s tortou a balónmi',
        2 => 'Herné prvky v detskom svete KUKO — šmykľavka a hracie zóny',
        3 => 'Interiér KUKO — rodičia pri káve, deti sa hrajú',
        4 => 'Detská oslava v KUKO — výzdoba a deti pri stole',
        5 => 'Vnútorný priestor detskej herne KUKO Piešťany',
    ];
    foreach ($alts as $i => $alt) {
        $db->execStmt(
            'INSERT INTO gallery_photos (filename, webp, alt_text, sort_order) VALUES (?,?,?,?)',
            ["galeria_$i.jpg", "galeria_$i.webp", $alt, $i]
        );
        echo "+ photo galeria_$i.jpg\n";
    }
} else {
    echo "= gallery already has $existing rows\n";
}

// Settings: maintenance + SEO z config (ak ešte nie v DB)
$s = new \Kuko\SettingsRepo($db);
$seed = [
    'maintenance.enabled'  => \Kuko\Config::get('app.maintenance', false) ? '1' : '0',
    'maintenance.password' => (string) \Kuko\Config::get('app.maintenance_password', ''),
    'seo.public_indexing'  => \Kuko\Config::get('app.public_indexing', false) ? '1' : '0',
    'seo.home.title'        => 'KUKO detský svet — herňa a kaviareň v Piešťanoch',
    'seo.home.description'  => 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, kvalitná káva pre rodičov, oslavy na mieru. Otvorené Pon–Ne 9:00 – 20:00.',
    'seo.rezervacia.title'  => 'Rezervácia oslavy — KUKO detský svet',
    'seo.rezervacia.description' => 'Rezervujte si oslavu v KUKO detský svet. Vyberte balíček, dátum a čas v 3 krokoch.',
    'seo.faq.title'         => 'Časté otázky — KUKO detský svet',
    'seo.faq.description'   => 'Odpovede na najčastejšie otázky o detskej herni KUKO v Piešťanoch.',
    'seo.privacy.title'     => 'Ochrana osobných údajov — KUKO detský svet',
    'seo.privacy.description' => 'Zásady spracovania osobných údajov a cookies na webe kuko-detskysvet.sk.',
];
foreach ($seed as $k => $v) {
    if ($s->get($k) === null) { $s->set($k, $v); echo "+ setting $k\n"; }
    else { echo "= skip setting $k\n"; }
}

echo "seed done\n";
```

- [ ] **Step 2: Run lokálne proti dev DB**

Run: `/opt/homebrew/bin/php private/scripts/seed-cms.php`
Expected: `+ block hero.title` … `seed done`, žiadna chyba.

- [ ] **Step 3: Verify**

Run: `/usr/bin/sqlite3 private/logs/kuko-dev.sqlite "SELECT COUNT(*) FROM content_blocks; SELECT COUNT(*) FROM gallery_photos;"`
Expected: `23` (blocks) a `5` (photos).

- [ ] **Step 4: Commit**

```bash
git add private/scripts/seed-cms.php
git commit -m "feat(scripts): seed-cms — content_blocks + gallery + settings z hardcoded (idempotent)"
```

---

## M7 — Frontend: sekcie → Content::get

### Task 9: Refactor sections to Content::get with fallbacks

**Files:**
- Modify: `private/templates/sections/hero.php`
- Modify: `private/templates/sections/o-nas.php`
- Modify: `private/templates/sections/cennik.php`
- Modify: `private/templates/sections/kontakt.php`
- Modify: `private/templates/footer.php`

- [ ] **Step 1: hero.php**

Nahradiť hardcoded title/subtitle:

```php
<h1 class="hero__title"><?= e(\Kuko\Content::get('hero.title', 'Detský svet KUKO')) ?></h1>
<p class="hero__sub"><?= e(\Kuko\Content::get('hero.subtitle', 'pre radosť detí & pohodu rodičov')) ?></p>
```

- [ ] **Step 2: o-nas.php**

Lead (HTML — už sanitizovaný, netreba `e()`):

```php
<div class="section__lead"><?= \Kuko\Content::get('about.lead', '<p>KUKO je interiérové detské ihrisko spojené s kaviarňou v Piešťanoch, vytvorené pre radosť detí a pohodlie rodičov.</p>') ?></div>
```

Karty (každá `card{N}.title` / `card{N}.body`):

```php
<p class="card__body"><strong><?= e(\Kuko\Content::get('about.card1.title', 'Bezpečný, čistý a hravý priestor')) ?></strong><br><?= e(\Kuko\Content::get('about.card1.body', 'kde sa deti môžu vyšantiť, objavovať a tráviť čas aktívne.')) ?></p>
```

(Analogicky card2/3/4 s ich fallbackmi z pôvodného textu.)

- [ ] **Step 3: cennik.php**

```php
<p class="section__lead"><?= e(\Kuko\Content::get('cennik.lead', 'Chceme, aby bol čas strávený u nás dostupný a príjemný pre každého.')) ?></p>
...
<li><span><?= e(\Kuko\Content::get('cennik.item1.label', 'Dieťa do 1 roku')) ?></span><span class="cennik__price"><?= e(\Kuko\Content::get('cennik.item1.price', 'ZADARMO')) ?></span></li>
<li><span><?= e(\Kuko\Content::get('cennik.item2.label', 'Dieťa od 1 roku')) ?></span><span class="cennik__price"><?= e(\Kuko\Content::get('cennik.item2.price', '5,00 € / hod')) ?></span></li>
<li><span><?= e(\Kuko\Content::get('cennik.item3.label', 'Dieťa od 1 roku neobmedzene')) ?></span><span class="cennik__price"><?= e(\Kuko\Content::get('cennik.item3.price', '15,00 €')) ?></span></li>
```

- [ ] **Step 4: kontakt.php**

Nahradiť hardcoded adresu/telefón/email/hodiny za `Content::get('kontakt.address', '…')` atď. (Sociálne URL ostávajú z `Config::get('social.*')` — tie rieši `/admin/contact` neskôr cez settings; v tomto tasku len content_blocks časti.)

- [ ] **Step 5: footer.php**

```php
<p><?= e(\Kuko\Content::get('footer.copyright', 'Copyright © {{year}} KUKO-detskysvet.sk | Všetky práva vyhradené.')) ?></p>
```

(`{{year}}` token rieši `Content::get` automaticky.)

- [ ] **Step 6: Smoke**

Run dev server, `curl -s http://127.0.0.1:8123/ | grep -E 'Detský svet KUKO|Bratislavská 141'` — musí vrátiť obsah (z DB seedu alebo fallbacku).

- [ ] **Step 7: Lint + commit**

```bash
find private/templates/sections private/templates/footer.php -name "*.php" -exec /opt/homebrew/bin/php -l {} \;
git add private/templates/sections/ private/templates/footer.php
git commit -m "feat(frontend): sekcie čítajú Content::get s hardcoded fallbackmi"
```

---

## M8 — Galéria + balíčky DB-driven

### Task 10: galeria.php + oslavy.php z DB

**Files:**
- Modify: `private/templates/sections/galeria.php`
- Modify: `private/templates/sections/oslavy.php`
- Modify: `public/index.php` (poskytnúť MediaRepo + PackagesRepo do home)

- [ ] **Step 1: index.php — načítať gallery + packages pre home**

V `/` route handler:

```php
$router->get('/', function () use ($renderer) {
    $gallery = [];
    $packages = [];
    try {
        $db = \Kuko\Db::fromConfig();
        $gallery  = (new \Kuko\MediaRepo($db, APP_ROOT . '/public/assets/img/gallery'))->listVisible();
        $packages = (new \Kuko\PackagesRepo($db))->listActive();
    } catch (\Throwable) {}
    echo $renderer->render('pages/home', ['gallery' => $gallery, 'packages' => $packages]);
});
```

- [ ] **Step 2: home.php — odovzdať premenné sekciám**

`pages/home.php` — `$gallery` a `$packages` sú v scope (Renderer extrahuje data). Sekcie ich použijú.

- [ ] **Step 3: galeria.php — DB fotky s fallbackom**

```php
<?php
/** @var array $gallery */
$photos = $gallery ?? [];
?>
<section id="galeria" class="section section--galeria" data-reveal>
  <div class="container">
    <h2>Fotogaléria</h2>
    <div class="gallery">
      <?php if ($photos): ?>
        <?php foreach ($photos as $p): $base = pathinfo($p['filename'], PATHINFO_FILENAME); ?>
          <button class="gallery__item" type="button" data-lightbox="/assets/img/gallery/<?= e($p['filename']) ?>" aria-label="<?= e($p['alt_text']) ?>">
            <picture>
              <?php if (!empty($p['webp'])): ?><source srcset="/assets/img/gallery/<?= e($p['webp']) ?>" type="image/webp"><?php endif; ?>
              <img src="/assets/img/gallery/<?= e($p['filename']) ?>" loading="lazy" alt="<?= e($p['alt_text']) ?>" width="400" height="300">
            </picture>
          </button>
        <?php endforeach; ?>
      <?php else: ?>
        <?php for ($i = 1; $i <= 5; $i++): ?>
          <button class="gallery__item" type="button" data-lightbox="/assets/img/galeria_<?= $i ?>.jpg" aria-label="Fotka z herne KUKO">
            <picture><source srcset="/assets/img/galeria_<?= $i ?>.webp" type="image/webp"><img src="/assets/img/galeria_<?= $i ?>.jpg" loading="lazy" alt="Fotka z herne KUKO" width="400" height="300"></picture>
          </button>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
```

- [ ] **Step 4: oslavy.php — DB balíčky s fallbackom**

Ak `$packages` má rozšírené polia (description, price_text, included_json, accent_color), renderovať z nich; inak ponechať existujúci hardcoded blok ako fallback. Konkrétne: obaliť 3 hardcoded `<article>` do `<?php if (!$packages): ?>…<?php else: ?>` loop cez `$packages` s `accent_color` → CSS trieda, `json_decode($pkg['included_json'])` → `<li>` zoznam.

- [ ] **Step 5: Smoke**

Re-seed dev DB + seed-cms, dev server, otvoriť `/` v Preview — galéria aj balíčky sa renderujú z DB; po `DELETE FROM gallery_photos` fallback na 5 statických.

- [ ] **Step 6: Commit**

```bash
git add private/templates/sections/galeria.php private/templates/sections/oslavy.php public/index.php private/templates/pages/home.php
git commit -m "feat(frontend): galéria + balíčky DB-driven s hardcoded fallbackom"
```

---

## M9 — Quill + admin rebrand

### Task 11: Vendor Quill

**Files:**
- Create: `public/assets/vendor/quill/quill.js`, `quill.snow.css`

- [ ] **Step 1: Stiahnuť Quill 2.x**

```bash
mkdir -p public/assets/vendor/quill
curl -sL https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js -o public/assets/vendor/quill/quill.js
curl -sL https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css -o public/assets/vendor/quill/quill.snow.css
ls -la public/assets/vendor/quill/
```
Expected: dva súbory, `quill.js` ~1 MB, css ~25 KB.

- [ ] **Step 2: Commit**

```bash
git add public/assets/vendor/quill/
git commit -m "chore: vendor Quill 2.0.3 (WYSIWYG editor)"
```

---

### Task 12: Admin KUKO rebrand (admin.css)

**Files:**
- Modify: `public/assets/css/admin.css`
- Modify: `private/templates/admin/layout.php`

- [ ] **Step 1: Prebrandovať admin.css na KUKO tému**

Pridať na vrch `admin.css` `:root` premenné + `@font-face` Nunito Sans, prefarbiť `--c-accent` z `#5e72e4` na `#D88BBE`, pozadia na `#FFF8EE`/`#FBEEF5`, tlačidlá `border-radius: 999px`, karty `border-radius: 1.25rem` + `box-shadow: 0 4px 20px rgba(0,0,0,0.05)`. Konkrétne: nahradiť všetky `#5e72e4` → `var(--c-accent)`, `#4c5fd1` → `#c373a8`, pridať font-family Nunito Sans do `body`. Badges (`.badge--pending/ok/no`) ostávajú čitateľné (žlté/zelené/červené odtiene), len zaokrúhliť.

- [ ] **Step 2: layout.php — pridať CMS nav položky**

Do admin nav pridať: `<a href="/admin/content">Obsah</a> <a href="/admin/gallery">Galéria</a> <a href="/admin/contact">Kontakt</a> <a href="/admin/seo">SEO</a> <a href="/admin/maintenance">Maintenance</a> <a href="/admin/log">Log</a>` (pred existujúce Web/Odhlásiť).

- [ ] **Step 3: Smoke — admin vizuál**

Dev server, prihlásiť `/admin/login`, otvoriť `/admin` — KUKO farby, Nunito font, pill tlačidlá. Screenshot cez Preview MCP.

- [ ] **Step 4: Commit**

```bash
git add public/assets/css/admin.css private/templates/admin/layout.php
git commit -m "feat(admin): KUKO rebrand admin.css (Nunito Sans, pinky accent, pill buttons) + CMS nav"
```

---

## M10 — Admin /admin/content

### Task 13: Content editor stránka + Quill

**Files:**
- Create: `private/templates/admin/content.php`
- Modify: `public/admin/index.php` (routes)

- [ ] **Step 1: Routes v admin/index.php**

```php
$router->get('/admin/content', function () use ($renderer, $db, $adminUser, $flashes) {
    $cb = new \Kuko\ContentBlocksRepo($db);
    echo $renderer->render('content', ['groups' => $cb->listGrouped(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/content/save', function () use ($db, $audit, $flash, $adminUser) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $key = (string)($_POST['block_key'] ?? '');
    $type = (string)($_POST['content_type'] ?? 'text');
    $value = (string)($_POST['value'] ?? '');
    $cb = new \Kuko\ContentBlocksRepo($db);
    $cb->set($key, $value, $type, $adminUser);
    $audit('content_save', 'content_blocks', 0, ['key' => $key]);
    $flash("Blok '$key' uložený.");
    header('Location: /admin/content');
});
```

- [ ] **Step 2: content.php template**

Skupinový zoznam (`$groups` z `listGrouped()`). Každý blok: ak `content_type==='html'` → `<div class="quill-editor">` + hidden input + Quill init; inak `<input>` alebo `<textarea>`. Jeden `<form method="post" action="/admin/content/save">` per blok s CSRF + `block_key` + `content_type` hidden + „Uložiť" pill button + „Pozrieť na webe ↗" link na `/#<anchor>`.

Quill init script (na konci template):

```html
<link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css">
<script src="/assets/vendor/quill/quill.js"></script>
<script>
document.querySelectorAll('.quill-editor').forEach(el => {
  const q = new Quill(el, { theme: 'snow', modules: { toolbar: ['bold','italic',{list:'bullet'},{list:'ordered'},'link'] } });
  const hidden = el.closest('form').querySelector('input[name="value"]');
  q.root.innerHTML = hidden.value;
  el.closest('form').addEventListener('submit', () => { hidden.value = q.root.innerHTML; });
});
</script>
```

- [ ] **Step 3: Smoke**

Dev server, `/admin/content` → skupiny Hero/About/Cennik/Kontakt/Footer, edit text bloku → uloží sa, refresh frontend ukáže zmenu. Edit HTML bloku cez Quill → sanitizovaný uloží.

- [ ] **Step 4: Commit**

```bash
git add private/templates/admin/content.php public/admin/index.php
git commit -m "feat(admin): /admin/content editor s Quill pre HTML bloky"
```

---

## M11 — Admin /admin/gallery

### Task 14: Gallery admin (upload, reorder, delete)

**Files:**
- Create: `private/templates/admin/gallery.php`
- Modify: `public/admin/index.php` (routes)

- [ ] **Step 1: Routes**

```php
$mediaRepo = fn() => new \Kuko\MediaRepo($db, APP_ROOT . '/public/assets/img/gallery');

$router->get('/admin/gallery', function () use ($renderer, $mediaRepo, $adminUser, $flashes) {
    echo $renderer->render('gallery', ['photos' => $mediaRepo()->listAll(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/gallery/upload', function () use ($mediaRepo, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    try {
        $row = $mediaRepo()->upload($_FILES['photo'] ?? [], (string)($_POST['alt'] ?? ''));
        $audit('gallery_upload', 'gallery_photos', (int)$row['id']);
        $flash('Fotka nahraná.');
    } catch (\RuntimeException $e) { $flash($e->getMessage(), 'err'); }
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/{id}/delete', function ($p) use ($mediaRepo, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $mediaRepo()->delete((int)$p['id']);
    $audit('gallery_delete', 'gallery_photos', (int)$p['id']);
    $flash('Fotka zmazaná.');
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/{id}/visibility', function ($p) use ($mediaRepo, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $mediaRepo()->setVisibility((int)$p['id'], !empty($_POST['visible']));
    $flash('Viditeľnosť zmenená.');
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/{id}/alt', function ($p) use ($mediaRepo, $flash) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $mediaRepo()->updateAlt((int)$p['id'], (string)($_POST['alt'] ?? ''));
    $flash('ALT text uložený.');
    header('Location: /admin/gallery');
});
$router->post('/admin/gallery/reorder', function () use ($mediaRepo) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $order = json_decode((string)file_get_contents('php://input'), true)['order'] ?? [];
    if (is_array($order)) $mediaRepo()->reorder(array_map('intval', $order));
    header('Content-Type: application/json'); echo json_encode(['ok' => true]);
});
```

(Pozn.: reorder route číta JSON body — pre fetch z drag-drop JS; CSRF cez header alebo query, doplniť `X-CSRF` check.)

- [ ] **Step 2: gallery.php template**

Mriežka náhľadov (drag-drop cez HTML5 `draggable` + `dragover`/`drop`, na konci `fetch('/admin/gallery/reorder', {method:'POST', body: JSON.stringify({order})})`). Upload form (multipart, `photo` + `alt`). Per fotka: náhľad, ALT inline edit form, skryť/zobraziť toggle, zmazať (confirm).

- [ ] **Step 3: Smoke**

Dev server `/admin/gallery` → upload PNG → vytvorí sa WebP, objaví sa v mriežke → drag reorder → poradie sa uloží → frontend `/#galeria` ukáže nové poradie → zmazať → zmizne + súbor preč.

- [ ] **Step 4: Commit**

```bash
git add private/templates/admin/gallery.php public/admin/index.php
git commit -m "feat(admin): /admin/gallery — upload (WebP), drag-drop reorder, ALT, hide, delete"
```

---

## M12 — Packages rozšírené + Contact

### Task 15: /admin/packages rozšírené + /admin/contact

**Files:**
- Modify: `private/templates/admin/packages.php`
- Modify: `private/lib/PackagesRepo.php` (update extended fields)
- Create: `private/templates/admin/contact.php`
- Modify: `public/admin/index.php`

- [ ] **Step 1: PackagesRepo->update rozšíriť o nové polia**

V `PackagesRepo::update()` pridať do SET klauzuly `description`, `price_text`, `kids_count_text`, `duration_text`, `included_json`, `accent_color`. Test: doplniť `RepositoriesTest::testPackagesUpdate` o overenie nových polí.

- [ ] **Step 2: packages.php — rozšírený formulár**

Pridať polia: Popis (Quill), Cena text, Počet detí text, Trvanie text, Zoznam „zahŕňa" (textarea, 1 položka/riadok → uloží sa ako JSON array), Farba (select blue/purple/yellow). Existujúce trvanie/aktívny ostávajú.

- [ ] **Step 3: contact.php + routes**

```php
$router->get('/admin/contact', function () use ($renderer, $db, $adminUser, $flashes) {
    $cb = new \Kuko\ContentBlocksRepo($db);
    $s = new \Kuko\SettingsRepo($db);
    echo $renderer->render('contact', [
        'address' => $cb->get('kontakt.address') ?? 'Bratislavská 141, 921 01 Piešťany',
        'phone'   => $cb->get('kontakt.phone')   ?? '+421 915 319 934',
        'email'   => $cb->get('kontakt.email')   ?? 'info@kuko-detskysvet.sk',
        'hours'   => $cb->get('kontakt.hours')   ?? 'Pondelok – Nedeľa: 9:00 – 20:00',
        'fb'      => $s->get('social.facebook')  ?? '',
        'ig'      => $s->get('social.instagram') ?? '',
        'user'    => $adminUser, 'flashes' => $flashes,
    ]);
});
$router->post('/admin/contact', function () use ($db, $audit, $flash, $adminUser) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $cb = new \Kuko\ContentBlocksRepo($db);
    $s = new \Kuko\SettingsRepo($db);
    foreach (['address','phone','email','hours'] as $f) {
        $cb->set("kontakt.$f", (string)($_POST[$f] ?? ''), 'text', $adminUser);
    }
    $s->set('social.facebook', (string)($_POST['fb'] ?? ''));
    $s->set('social.instagram', (string)($_POST['ig'] ?? ''));
    $audit('contact_save', 'content_blocks', 0);
    $flash('Kontaktné údaje uložené.');
    header('Location: /admin/contact');
});
```

(Pozn.: `kontakt.php` frontend + `head.php` Schema.org musia čítať social z `SettingsRepo` namiesto `Config` — doplniť v tomto tasku.)

- [ ] **Step 4: Smoke + commit**

```bash
git add private/templates/admin/packages.php private/templates/admin/contact.php private/lib/PackagesRepo.php public/admin/index.php private/templates/sections/kontakt.php private/templates/head.php
git commit -m "feat(admin): rozšírené /admin/packages + /admin/contact (kontakt + social)"
```

---

## M13 — SEO + Maintenance + Log

### Task 16: /admin/seo + /admin/maintenance + /admin/log

**Files:**
- Create: `private/templates/admin/seo.php`, `maintenance.php`, `log.php`
- Modify: `public/admin/index.php`

- [ ] **Step 1: Routes**

```php
$router->get('/admin/seo', function () use ($renderer, $db, $adminUser, $flashes) {
    $s = new \Kuko\SettingsRepo($db);
    echo $renderer->render('seo', ['s' => $s->all(), 'user' => $adminUser, 'flashes' => $flashes]);
});
$router->post('/admin/seo', function () use ($db, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $s = new \Kuko\SettingsRepo($db);
    foreach (['home','rezervacia','faq','privacy','default'] as $pg) {
        if (isset($_POST["{$pg}_title"]))  $s->set("seo.$pg.title", (string)$_POST["{$pg}_title"]);
        if (isset($_POST["{$pg}_desc"]))   $s->set("seo.$pg.description", (string)$_POST["{$pg}_desc"]);
    }
    $s->set('seo.public_indexing', !empty($_POST['public_indexing']) ? '1' : '0');
    $audit('seo_save', 'settings', 0);
    $flash('SEO nastavenia uložené.');
    header('Location: /admin/seo');
});
$router->get('/admin/maintenance', function () use ($renderer, $db, $adminUser, $flashes) {
    $s = new \Kuko\SettingsRepo($db);
    echo $renderer->render('maintenance', [
        'enabled' => $s->get('maintenance.enabled') === '1',
        'user' => $adminUser, 'flashes' => $flashes,
    ]);
});
$router->post('/admin/maintenance', function () use ($db, $audit, $flash) {
    if (!\Kuko\Csrf::verify((string)($_POST['csrf'] ?? ''))) { http_response_code(403); echo 'csrf'; return; }
    $s = new \Kuko\SettingsRepo($db);
    $on = !empty($_POST['enabled']);
    $s->set('maintenance.enabled', $on ? '1' : '0');
    if (!empty($_POST['password'])) $s->set('maintenance.password', (string)$_POST['password']);
    $audit('maintenance_toggle', 'settings', 0, ['enabled' => $on]);
    $flash('Maintenance ' . ($on ? 'ZAPNUTÝ' : 'vypnutý') . '.');
    header('Location: /admin/maintenance');
});
$router->get('/admin/log', function () use ($renderer, $db, $adminUser, $flashes) {
    $rows = $db->all('SELECT * FROM admin_actions ORDER BY created_at DESC LIMIT 200');
    echo $renderer->render('log', ['rows' => $rows, 'user' => $adminUser, 'flashes' => $flashes]);
});
```

- [ ] **Step 2: seo.php template**

Per stránka (home/rezervacia/faq/privacy/default): title input (maxlength 60, JS counter), description textarea (maxlength 160, counter), live „Google preview" snippet. Global: checkbox „Public indexing" s warning textom. Save button.

- [ ] **Step 3: maintenance.php template**

Veľký toggle switch ON/OFF, status indicator (zelená LIVE / žltá MAINTENANCE), pole „Nové heslo" (optional), confirm dialóg cez `onsubmit="return confirm(...)"`. Upozornenie čo sa stane.

- [ ] **Step 4: log.php template**

Tabuľka `admin_actions` (admin_user, action, target, created_at, payload JSON pretty). Read-only, posledných 200, paginácia voliteľná.

- [ ] **Step 5: Smoke**

`/admin/seo` → zmena title → frontend `<title>` sa zmení. `/admin/maintenance` → toggle ON → verejnosť vidí maintenance, staff cookie funguje → toggle OFF. `/admin/log` → vidno predošlé akcie.

- [ ] **Step 6: Commit**

```bash
git add private/templates/admin/seo.php private/templates/admin/maintenance.php private/templates/admin/log.php public/admin/index.php
git commit -m "feat(admin): /admin/seo + /admin/maintenance toggle + /admin/log audit view"
```

---

## M14 — Smoke + deploy

### Task 17: Full regression + production deploy

- [ ] **Step 1: Celý test suite**

Run: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar`
Expected: všetko zelené (72 + nové ~25 testov).

- [ ] **Step 2: PHP lint všetko**

Run: `find private/lib private/templates public -name "*.php" -exec /opt/homebrew/bin/php -l {} \; 2>&1 | grep -v "No syntax errors" || echo clean`
Expected: `clean`.

- [ ] **Step 3: Manuálny smoke (Preview MCP)**

- `/admin/content` edit textu → frontend zmena
- `/admin/gallery` upload + reorder + delete
- `/admin/packages` rozšírené polia → oslavy sekcia
- `/admin/contact` → kontakt sekcia + Schema.org
- `/admin/seo` → meta zmena
- `/admin/maintenance` toggle ON/OFF
- `/admin/log` audit
- Frontend funguje aj po `DELETE FROM content_blocks` (fallbacky)

- [ ] **Step 4: Migrácia na produkcii**

Upload kód + `005_cms.sql` cez lftp. Spusti `https://kuko-detskysvet.sk/_setup.php?action=migrate&token=<auth.secret>`. Spusti seed: nahrať `seed-cms.php` ako jednorazový `_seed.php` gated tokenom, zavolať, zmazať. (Alebo rozšíriť `_setup.php` o `action=seed`.)

- [ ] **Step 5: Production smoke**

Maintenance bypass cookie → `/admin/content` etc. → over všetky CMS stránky 200, frontend renderuje z DB.

- [ ] **Step 6: Commit + finálny push**

```bash
git add -A && git commit -m "chore: admin CMS deploy verified on production"
```

---

## Self-Review

**1. Spec coverage:**
- ✅ content_blocks/gallery_photos/packages model → Task 1
- ✅ ContentBlocksRepo/MediaRepo/Content/HtmlSanitizer → Tasks 2,3,4,5
- ✅ SettingsRepo maintenance/SEO → Tasks 6,7
- ✅ Seed → Task 8
- ✅ Frontend fallbacky → Tasks 9,10
- ✅ Quill + admin rebrand → Tasks 11,12
- ✅ /admin/content, gallery, packages, contact, seo, maintenance, log → Tasks 13–16
- ✅ Testing → každý lib task má TDD; Task 17 regression
- ✅ Security (CSRF, finfo, sanitizer, audit) → v príslušných taskoch
- ✅ Deploy → Task 17

**2. Placeholder scan:** Tasky 10/12/13/14/16 majú template kroky popísané bez plného HTML — to je zámerné (templates sú vizuálne, presný markup sa odvodí z existujúcich admin šablón ktoré sa kopírujú ako pattern; lib vrstva ktorá je testovateľná má plný kód). Akceptovateľné pre tento typ práce, ale pri exekúcii treba existujúce `admin/*.php` použiť ako vzor.

**3. Type consistency:** `ContentBlocksRepo::set(key,value,contentType,updatedBy,label='')` konzistentne. `MediaRepo::upload(array,string):array`, `listVisible/listAll/reorder/delete/setVisibility/updateAlt` konzistentné medzi test a impl a admin routes. `Content::get(key,fallback):string`, `Content::setDb/reset` konzistentné. `Maintenance::setSettings` konzistentné.

Fixes applied inline: žiadne kritické nezrovnalosti nenájdené.
