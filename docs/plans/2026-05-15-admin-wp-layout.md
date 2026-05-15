# DT-9 — Admin WordPress-style layout

> Execute via subagent-driven-development (implementer → spec review → code-quality review), then deploy. Goal: re-skin the admin into a WP-style left-sidebar IA WITHOUT changing routes or backend logic.

**Key principle:** every admin template sets `$content` then `require __DIR__.'/layout.php'`. So rewriting `private/templates/admin/layout.php` + the admin CSS re-skins ALL admin pages centrally. Routes in `public/admin/index.php` and the individual templates' bodies stay UNCHANGED (zero backend risk).

**Current admin routes (unchanged — just regrouped in the sidebar):**
- Rezervácie group: `/admin` (list), `/admin/reservation/{id}` (detail), `/admin/calendar`, `/admin/blocked-periods`, `/admin/opening-hours`, `/admin/calendar.ics`
- Stránky (web content) group: `/admin/content`, `/admin/packages`, `/admin/gallery`, `/admin/contact`
- Nastavenia group: `/admin/seo`, `/admin/maintenance`, `/admin/log`, `/admin/gdpr`, `/admin/settings`
- Always: `/` (Web ↗), `/admin/logout`, `@user`

## Target IA (left sidebar, WP-style)

```
[ KUKO admin ]                ← sidebar header (logo/title)
─────────────────
▸ Rezervácie                  ← top-level; its pages show a TAB BAR:
     (tabs: Zoznam | Kalendár | Blokácie | Otváracie hodiny)
─────────────────
STRÁNKY                       ← group label
  • Obsah            /admin/content
  • Balíčky          /admin/packages
  • Galéria          /admin/gallery
  • Kontakt          /admin/contact
─────────────────
NASTAVENIA                    ← group label
  • SEO              /admin/seo
  • Maintenance      /admin/maintenance
  • Logy             /admin/log
  • GDPR             /admin/gdpr
  • Všeobecné        /admin/settings
─────────────────
  iCal export   ·  Web ↗
  @user         ·  Odhlásiť
```

- **Rezervácie** is a single top-level sidebar entry linking to `/admin`. When the current path is any of the reservations-group routes (`/admin`, `/admin/reservation/...`, `/admin/calendar`, `/admin/blocked-periods`, `/admin/opening-hours`), the main content area shows a horizontal TAB BAR at the top: **Zoznam** (`/admin`) · **Kalendár** (`/admin/calendar`) · **Blokácie** (`/admin/blocked-periods`) · **Otváracie hodiny** (`/admin/opening-hours`); the active tab is highlighted by current path. (Detail page `/admin/reservation/{id}` highlights the "Zoznam" tab / Rezervácie.)
- Sidebar groups **STRÁNKY** and **NASTAVENIA** are labeled sections with their links.
- Active sidebar link + active tab are highlighted based on the current request path (compute in layout.php from `$_SERVER['REQUEST_URI']` via `parse_url(..., PHP_URL_PATH)`, normalized — handle `/admin` exactly + `str_starts_with` for sub-paths; reservation detail → Rezervácie active).
- Responsive: ≤900px the sidebar collapses to a top bar with a hamburger that toggles the sidebar (reuse a simple CSS `:target` or a tiny inline JS toggle — keep minimal; admin has no main.js. A pure-CSS checkbox/`details` toggle or a 6-line inline script is fine). Keep the existing `.skip-link` + `<main id="main" tabindex="-1">` (a11y from Sprint 3 — MUST preserve).
- Keep KUKO admin branding (admin.css tokens: cream/pink/accent `#D88BBE`, Nunito Sans). The sidebar uses the brand palette (e.g. white sidebar on cream page, pink active state).

## Task DT-9 (single implementer task)

**Files:**
- Rewrite `private/templates/admin/layout.php`: sidebar IA above + reservations tab bar (conditional on path) + flash messages + preserve `<a class="skip-link" href="#main">` and `<main class="admin-main" id="main" tabindex="-1">`. Compute `$path` from `$_SERVER['REQUEST_URI']`. Keep `$title`/`$user`/`$flashes` contract (templates pass these — DO NOT change template→layout variable contract). Logo: use the corrected `/assets/img/logo.png` via `\Kuko\Asset::url()` small in the sidebar header (optional, nice touch) — or just text "KUKO admin"; keep it lightweight.
- Rewrite the header/nav portion of `public/assets/css/admin.css` into a sidebar layout: `body` becomes a flex/grid with a fixed-width left `<aside class="admin-sidebar">` (~240px) + `.admin-main` taking the rest; group labels, link list, active states, the reservations tab bar (`.admin-tabs`), responsive collapse ≤900px. KEEP all existing component styles (tables, forms, cards, badges, calendar, `.skip-link`, `.sr-only`, focus-visible) intact — only replace the top-`.admin-header`/`nav` rules with sidebar rules and ADD tab + sidebar rules. admin.css is in build-assets → run `/opt/homebrew/bin/php private/scripts/build-assets.php` and commit regenerated `admin.min.css` (stale-min guard test).
- Do NOT modify `public/admin/index.php` routes or any admin section template body (list/detail/calendar/blocked-periods/opening-hours/content/packages/gallery/contact/seo/maintenance/log/gdpr/settings) — they only provide `$content`. (If a template currently relies on a class that the old header provided, verify; layout-only change should be safe.)
- Test `private/tests/unit/AdminWpLayoutTest.php`:
```php
<?php
declare(strict_types=1);
namespace Kuko\Tests\Unit;
use PHPUnit\Framework\TestCase;
final class AdminWpLayoutTest extends TestCase
{
    private string $l;
    protected function setUp(): void { $this->l = file_get_contents(\dirname(__DIR__, 3) . '/private/templates/admin/layout.php'); }
    public function testSidebarStructure(): void
    {
        $this->assertStringContainsString('admin-sidebar', $this->l);
        $this->assertMatchesRegularExpression('/STR\x{00C1}NKY|Str\x{00E1}nky/u', $this->l);   // group label
        $this->assertStringContainsString('Nastavenia', $this->l);
        $this->assertStringContainsString('Rezerv\xC3\xA1cie', $this->l);
    }
    public function testReservationTabsPresent(): void
    {
        $this->assertStringContainsString('admin-tabs', $this->l);
        foreach (['/admin/calendar','/admin/blocked-periods','/admin/opening-hours'] as $h) {
            $this->assertStringContainsString('href="' . $h . '"', $this->l);
        }
    }
    public function testA11yPreserved(): void
    {
        $this->assertStringContainsString('class="skip-link"', $this->l);
        $this->assertStringContainsString('id="main"', $this->l);
        $this->assertSame(1, substr_count($this->l, '<main'), 'exactly one <main>');
    }
    public function testAllSectionLinksPresent(): void
    {
        foreach (['/admin/content','/admin/packages','/admin/gallery','/admin/contact','/admin/seo','/admin/maintenance','/admin/log','/admin/gdpr','/admin/settings','/admin/logout'] as $h) {
            $this->assertStringContainsString('href="' . $h . '"', $this->l);
        }
    }
}
```
(Confirm `dirname(__DIR__,3)` depth; fix the UTF-8 regex bytes if needed — use `\x{00C1}` PCRE codepoints, must fail on OLD layout.php which has a flat `<header class="admin-header">` nav.)

**Steps:** TDD RED → rewrite layout.php → rewrite admin.css sidebar/tab rules → build-assets → GREEN. FULL suite `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar private/tests` → all prior 219 + 4 new green (esp. existing admin-related tests + a11y/SkipLink + stale-min). Lint changed PHP/test. Dev smoke: serve, log in is not needed — `curl -s http://127.0.0.1:PORT/admin` redirects 302 to login (auth gate unchanged); instead curl `/admin/login` 200 (renders via layout-minimal, NOT this layout — confirm login still fine) and structurally inspect layout.php output by rendering a known admin template path through the test/dev (or just structural HTML review). Re-confirm: every old nav destination still reachable from the new sidebar; active-state logic correct for `/admin`, `/admin/calendar`, `/admin/reservation/5`, `/admin/seo`.

**Deploy (DT-9 done):** push + lftp mirror `private/templates/admin/layout.php` + `public/assets/css/admin.css` + `admin.min.css` + the test; verify prod `/admin/login` 200, `/admin` 302 (auth gate intact), safety invariants (public `/` 503, robots Disallow) unchanged; bookkeeping in STATUS doc.

Notes: routes/templates unchanged → minimal regression surface. Preserve Sprint-3 a11y (skip link, single `<main id=main>`, focus-visible). Keep it visually consistent with the public-site KUKO rebrand (same palette/fonts). No new dependencies; admin has no JS framework — sidebar mobile toggle = pure CSS or ≤8-line inline script.
