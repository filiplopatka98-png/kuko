# Quality Sprint 2 — SEO finalization + Performance

> Execute via subagent-driven-development (implementer → spec review → code-quality review per task), deploy after the sprint. Roadmap order: SEO then Performance. Spec = `docs/plans/2026-05-14-roadmap-quality.md`.

**Already-done discoveries (do NOT redo):** canonical/hreflang/og:image-dims/LocalBusiness schema/FAQPage all live in `head.php`; every `<img>` already has width+height (CLS handled); meta description is DB-driven via `/admin/seo` (`Seo::resolve`). Long-tail keywords / analytics / Google Business Profile = owner/content tasks, not code.

**Tech:** PHP 8.1 `/opt/homebrew/bin/php`; PHPUnit phar; node v20 present (`node`/`npx`). Deploy = lftp mirror (documented). 142 tests currently green.

---

### S2-T1 — Per-page noindex for helper pages
Helper pages must never be indexed even after global `public_indexing` flips ON. `Seo::resolve($pageType,…,$globalIndexing,$pageIndexing)` returns `noindex,nofollow` when effective index is false; `$pageIndexing` (4th-from arg, nullable bool) overrides global. `home.php` sets `$pageType='home'`, `faq.php` `'faq'`. Gap: `reservation-status.php`, `404.php`, the privacy page (`ochrana-udajov`), `maintenance.php` don't force noindex.

**Files:** `private/templates/pages/reservation-status.php`, `private/templates/pages/404.php`, the privacy page template, `private/templates/pages/maintenance.php`; test `private/tests/unit/HelperPagesNoindexTest.php`.

- Test first: assert each of those templates sets `$pageIndexing = false;` (string grep `dirname(__DIR__,3)`-rooted). Run → RED.
- Implement: add `$pageIndexing = false;` near each page's existing `$pageType`/title setup (before the layout/head require). Find the exact privacy template path by grepping routes in `public/index.php` for `ochrana-udajov`. Do not alter page content.
- Test → GREEN. Full suite green.
- Commit `feat: force noindex on privacy/404/status/maintenance pages (roadmap S1)`.

### S2-T2 — Single H1 per page (reservation.php has 4)
`private/templates/pages/reservation.php` has 4 `<h1>` (lines ~32/52/96/145, one per wizard step) → SEO/a11y violation (one H1 per document). Keep ONE page-level H1, demote step titles to `<h2>`.

**Files:** `private/templates/pages/reservation.php`; test `private/tests/unit/SingleH1Test.php`.
- Test first: parse `reservation.php`, assert exactly ONE `<h1` occurrence. Also assert `home.php`/`404.php`/`reservation-status.php`/`maintenance.php` each have exactly one `<h1`. Run → RED (reservation has 4).
- Implement: in reservation.php, change the 4 step `<h1>` to `<h2>` and add a single visually-appropriate page `<h1>` (e.g. inside the stepper header / first step container) reading "Rezervácia oslavy" — reuse existing heading CSS classes; if a step heading must remain the visual focus, make the page h1 the first step's heading and convert the other 3 step headings to h2 (net exactly one h1, no duplicate visible jump). Keep wizard behavior/markup classes intact (JS targets unaffected — verify no JS selects `h1`).
- Test → GREEN. Full suite green. `node --check` not needed (no JS change). Manual note: confirm stepper still renders.
- Commit `fix: exactly one H1 on reservation page (roadmap S2/A2)`.

### S2-T3 — Font WOFF2 + subsetting
`public/assets/fonts/NunitoSans.ttf` (~556 KB) + Italic (~556 KB) shipped as TTF only. Add WOFF2 (better compression) and, if tooling exists, subset to latin+latin-ext.

**Files:** `public/assets/fonts/*.woff2` (generated), `private/templates/layout-minimal.php` + `public/assets/css/*` @font-face `src`, `private/scripts/` (a generator note); test asserting woff2 exists + @font-face references it.
- First detect tooling: `which woff2_compress fonttools pyftsubset`; `npx --yes <pkg> --version` probes. If a TTF→WOFF2 path exists (`woff2_compress`, or `npx ttf2woff2`, or fonttools `pyftsubset --flavor=woff2`), generate `NunitoSans.woff2` (+Italic). Prefer subsetting to `U+0000-024F,U+1E00-1EFF,U+20A0-20BF` (latin + latin-ext + € ) when fonttools available; else plain WOFF2 of full font; else if NO tooling, SKIP generation, report BLOCKED-partial and still wire @font-face to prefer woff2 if file present.
- Test: assert `NunitoSans.woff2` exists in fonts dir; assert `layout-minimal.php` and admin.css/main.css `@font-face` list `woff2` format BEFORE `truetype`. (If generation impossible, the implementer reports it and the test for file-existence is skipped/deferred — report honestly, do not fake.)
- Add `format("woff2")` source first in every `@font-face` (layout-minimal.php inline style, `public/assets/css/admin.css`, `public/assets/css/main.css`), keeping ttf fallback.
- Full suite green. Commit `feat: WOFF2 fonts (+ subset if tooling) (roadmap P2)`.

### S2-T4 — CSS/JS minification at deploy
Serve minified `main.css`/`rezervacia.css`/`admin.css`/`main.js`/`rezervacia.js` etc. without breaking dev. Approach: a build script `private/scripts/build-assets.php` (or shell) that produces `*.min.css`/`*.min.js` next to sources using `npx --yes esbuild`/`csso-cli` (node v20 present). Templates reference the source path; `Kuko\Asset::url()` already cache-busts. Minification strategy that survives SFTP-only prod: generate `.min` files locally, commit them, and have `Asset::url()` (or the template) point to `.min.*` when it exists.
- Simplest robust: extend `Kuko\Asset::url($path)` — if a sibling `*.min.<ext>` exists under docRoot for the requested non-min asset, serve that (still `?v=filemtime` of the min file). Add `Asset::url` "prefer .min" logic + unit test. Build script generates the .min files (esbuild for JS `--minify`, csso for CSS); commit generated `.min.*`.
- TDD: test `Asset::url('/assets/css/main.css')` returns `/assets/css/main.min.css?v=…` when `main.min.css` exists in the test docroot, else the original. Then implement, then generate real .min files via the script, commit them.
- If `npx` offline/unavailable, report and fall back: ship the prefer-min logic + script but note .min files must be generated where node is available. Do not block the sprint.
- Full suite green. Commit `feat: serve minified css/js via Asset (roadmap P2/S4)`.

### S2-T5 — Responsive hero srcset
Hero currently one large image. Add `srcset`/`sizes` for the hero `<img>`/`<picture>` (768/1280/1920 widths) using GD-generated variants (extend `private/scripts/gen-favicons.php` pattern or a new `gen-hero-variants.php`). Keep existing webp preload.
- Generate `hero-768.webp/jpg`, `hero-1280.*`, `hero-1920.*` from `public/assets/img/hero.jpg` via GD; wire `<source srcset>`/`<img srcset sizes>` in `private/templates/sections/hero.php`. Test: variant files exist at expected widths; hero.php contains `srcset`.
- Full suite green. Commit `feat: responsive hero srcset (roadmap P2)`.

### S2-T6 — Regression + bookkeeping + deploy
- Full suite green; lint sweep; dev smoke (/,/rezervacia,/faq,/ochrana-udajov,/admin/login 200; /admin 302).
- Check off shipped items in `roadmap-quality.md`, add Sprint 2 blockquote.
- git push origin main; lftp mirror public/ + private/; verify prod safety invariants (public / still 503, robots Disallow) + new assets 200.
- Commit bookkeeping; push.

---
Owner/content items NOT in this sprint (report at end): long-tail keyword copy edits (via /admin content), Plausible/Search Console/UptimeRobot, Google Business Profile, HSTS preload registration, Lighthouse/axe runs. Deferred prod-path bugs (.htpasswd FIXED; MediaRepo gallery path #2 still open) tracked in sprint1-STATUS.md.
