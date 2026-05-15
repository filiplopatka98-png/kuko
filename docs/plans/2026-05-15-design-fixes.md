# Design Corrections (user feedback vs screenshots/) — implementation plan

> Execute via subagent-driven-development (implementer → spec review → code-quality review per task), deploy at end. Design reference = `screenshots/1-hero.png … 7-footer.png` + `full-page.png`. Implementers MUST view the relevant screenshot(s) with the Read tool before coding a section.

**Asset facts (verified):**
- Correct brand logo = `assets/Logo.jpeg` (2048×1508, white bg, JPEG: rainbow + "KUKO" bubble + girl(left)/boy(right) faces + "detský svet" script). Current `public/assets/img/logo.png` is WRONG (it's the 1536×1024 photo-ish image). Header + footer must use the correct logo.
- Icon SVGs already in `public/assets/icons/`: `playground.svg coffee.svg friendship.svg balloons.svg balloon.svg little-kid.svg uzavreta.svg contact-us.svg contact-us-1.svg clock.svg clock-1.svg instagram.svg facebook-app-symbol.svg` (+ Group_*.svg decorative dots / party-hat).
- No standalone rainbow asset and no `galeria_6`. Decisions: rainbow = cropped from `Logo.jpeg` top region; 6th gallery photo = temporarily reuse an existing `galeria_*` (user will supply real one later); standalone `/galeria` page = ALL DB gallery photos (existing `MediaRepo`/gallery system) with lightbox.
- Tokens: cream `#FFF8EE`, soft pink `#FBEEF5`, accent `#D88BBE`, card border colors blue `#9ED7E3` / peach `#F8B49D` / yellow `#F7D87E` / purple `#C9A8E1` (see admin.css `:root`; main.css has its own — reuse main.css palette). `Kuko\Asset::url()` cache-busts + prefers `.min`; after editing any source CSS/JS run `/opt/homebrew/bin/php private/scripts/build-assets.php` and commit regenerated `.min` (stale-min guard test). PHP `/opt/homebrew/bin/php`; 194 tests currently green; deploy = lftp mirror.

---

### DT-1 — Asset prep (logo + rainbow)
Create `private/scripts/gen-brand-assets.php` (GD): from `assets/Logo.jpeg` produce:
- `public/assets/img/logo.png` (transparent NOT possible from JPEG; keep white bg — header/footer bgs are white in the design, acceptable) resized to max width 640px, + `logo.webp`. OVERWRITE the wrong existing logo.png/webp.
- `public/assets/img/rainbow.png` + `.webp`: crop the TOP rainbow+faces region of `Logo.jpeg` (approx the top ~58% height, full width, excluding the "KUKO detský svet" lettering) → resized to ~max width 320px. (Reason about Logo.jpeg dims 2048×1508: the rainbow arc + the two kid faces occupy roughly y=0 … ~0.6*H; the "KUKO"/"detský svet" text is the lower ~40%. Crop `[0, 0, 2048, round(1508*0.60)]` then trim/resize. Verify visually by also writing a tiny check, or accept the ratio — keep it simple.)
Header comment: one-time generator; assets committed. Test `private/tests/unit/BrandAssetsTest.php`: logo.png/webp + rainbow.png/webp exist, are valid images, logo width ≤ 640. Commit incl. binaries.

### DT-2 — Header rebuild + footer logo (`nav.php`, `footer.php`, CSS)
View `screenshots/1-hero.png` (header) and `7-footer.png`. Current `nav.php`: a `.topbar` (mail/tel text links) + `.nav` (logo left, hamburger, menu inline). REBUILD to match design:
- **Top bar** (thin, above logo): LEFT = mail + phone, each prefixed with a small icon (use `contact-us.svg` for phone; for mail use a clean inline mail SVG or `contact-us-1.svg` — pick whichever visually reads as envelope/phone; user said "s inou ikonkou ako si dal" → must HAVE icons, currently none). RIGHT = text "Sledujte nás:" followed by Facebook + Instagram icon links (`facebook-app-symbol.svg`, `instagram.svg`) using `\Kuko\Social::url('facebook')`/`('instagram')` (fallback: hide if empty).
- **Logo CENTERED** below the top bar (the correct `logo.png` from DT-1), `<a href="/">`, sized ~ height 90-110px.
- **Nav menu BELOW the logo on a pink background** (`#FBEEF5` / soft-pink full-width band): DOMOV · O DETSKOM SVETE · DETSKÉ OSLAVY · CENNÍK SLUŽIEB · FOTOGALÉRIA · KONTAKT (keep existing hrefs `/#domov` etc.; keep the mobile hamburger working — on mobile collapse the menu; topbar can wrap/stack). Preserve `id="primary-nav"`, `.nav__toggle`, aria attrs so `main.js` hamburger still works (read main.js for the exact selectors it toggles and KEEP them).
- **Footer** (`footer.php`): replace `logo.png` reference — it now points at the corrected logo automatically (same path) so footer just needs correct sizing; verify it shows the right logo centered (it will, since DT-1 overwrote logo.png). No content change needed beyond confirming `<img>` uses `\Kuko\Asset::url('/assets/img/logo.png')` (add Asset::url cache-bust if not already) and sizing matches `7-footer.png`.
- Rewrite the header/topbar/nav CSS in `public/assets/css/main.css` (find existing `.topbar`/`.nav` rules; replace with the new 3-row structure: topbar row, centered logo row, pink nav band). Keep it responsive (mobile: hamburger toggles the menu; topbar stacks). Use `Asset::url()` for the logo img. Rebuild `.min`.
Test: `nav.php` contains topbar mail+phone with `<svg`/`<img` icon refs, a centered logo `<a href="/">`, social links via Social, and the nav menu inside a pink-bg wrapper; `main.css` has the pink nav band rule. `main.js` hamburger selectors preserved (assert `id="primary-nav"` + `.nav__toggle` still present).

### DT-3 — Hero third text line (`hero.php`)
View `screenshots/1-hero.png`. Add a THIRD text line below the existing `hero.subtitle`, as an editable content block: `<p class="hero__tagline"><?= e(\Kuko\Content::get('hero.tagline', 'Bezpečné, hravé miesto pre vaše deti v Piešťanoch')) ?></p>` (placeholder fallback — the owner will set the real copy via /admin/content; keep it a Content block so it's editable with zero code). Style `.hero__tagline` consistently (smaller than subtitle, same light color, centered) in main.css; rebuild .min. Keep the single `<h1>` (SingleH1 test). Add `hero.tagline` to `private/scripts/seed-cms.php` content_blocks seed list (so it appears in /admin/content) with the placeholder. Test: hero.php references `hero.tagline`; seed-cms lists it.

### DT-4 — "O nás" cards (`sections/o-nas.php`, CSS)
View `screenshots/2-onas.png`. Four cards (blue/peach/yellow/purple) with THICK rounded colored borders. Each card: icon (from `public/assets/icons/`: card1=`playground.svg`, card2=`coffee.svg`, card3=`friendship.svg`, card4=`balloons.svg`), bold lead line + sub text. Fixes:
- Thicker border (≈3px) on each card, larger corner radius, the 4 brand colors per card (match screenshot order).
- Icons BIGGER than current and the 4th (balloons) icon was MISSING — ensure all 4 render, sized to match the design (~56-72px).
- The "REZERVOVAŤ OSLAVU" button must sit centered on the BOTTOM border of the LAST (purple) card — i.e. absolutely positioned straddling the card's bottom edge (half above/half below the border), as in 2-onas.png. Implement via the card being `position:relative` and the button `position:absolute; left:50%; transform:translate(-50%,50%); bottom:0`. Use real icon files (inline `<img src="/assets/icons/...">` via `Asset::url`). Read current o-nas.php to keep the content blocks (about.card1..4) intact.
Rebuild .min. Test: o-nas.php references the 4 icon SVGs; CSS has the straddling-button rule + thick border.

### DT-5 — "Detské KUKO oslavy" cards (`sections/oslavy.php`, CSS)
View `screenshots/4-balicky.png`. Three package cards (blue/purple/yellow) with THICK borders. Per card: a circular icon badge ABOVE the heading that straddles the TOP border (half above the card), and the "REZERVOVAŤ BALÍČEK" button straddling the BOTTOM border (same treatment as DT-4). Icons from assets (map: mini→`balloon.svg`, maxi→`little-kid.svg`, closed/uzavretá→`uzavreta.svg`; adjust if a better visual match exists among the icons). Keep per-package dynamic/fallback rendering and content intact (only structural/visual). Rebuild .min. Test: oslavy.php references the 3 icons; CSS has top-straddle icon badge + bottom-straddle button + thick border.

### DT-6 — Fotogaléria + standalone /galeria (`sections/galeria.php`, new `pages/gallery.php`, route, CSS)
View `screenshots/5-galeria.png`. Changes:
- Rainbow graphic (`rainbow.png` from DT-1) centered ABOVE the "Fotogaléria" heading.
- Grid of **6** images, 3×2, each with **30px border-radius**. Currently the homepage shows DB photos (seeded 5) — to guarantee 6, the homepage gallery shows the first 6 visible photos; since only 5 exist, temporarily include one existing photo twice OR seed a 6th `gallery_photos` row reusing an existing `galeria_*` file (simplest: in `galeria.php` fallback/loop, if fewer than 6, pad by repeating — but cleaner: add a 6th seeded row in seed-cms.php reusing `galeria_5.jpg`/`.webp` with a distinct alt, and ensure the file exists in `public/assets/img/gallery/` (DT note: gallery dir resolution uses `Asset::docRoot()`; the starter files galeria_1..5 are in `public/assets/img/gallery/` — copy galeria_5 as a 6th if needed). Implement so the homepage shows exactly 6 with 30px radius; user will swap the real 6th later.
- A "Prejsť do galériu" button below the grid → links to `/galeria`.
- New route `/galeria` in `public/index.php` rendering a new `private/templates/pages/gallery.php` that lists ALL visible DB gallery photos (use the same `\Kuko\MediaRepo(... ->listVisible())` as the homepage, no 6-cap) in a responsive grid with the existing lightbox behavior (reuse `gallery.js` / the `data-lightbox` pattern from `sections/galeria.php`). Page uses the normal site layout (header/footer), has exactly one `<h1>` ("Fotogaléria"), proper `<title>`/meta via the head pattern, and `$pageType`/canonical set; it should be indexable when the site goes public (do NOT set `$pageIndexing=false`). Add `/galeria` to `sitemap.xml` generation if the sitemap enumerates routes (check public/index.php sitemap).
Rebuild .min. Tests: galeria.php has rainbow img + 6 items + 30px radius CSS + Prejsť button; `/galeria` route returns 200 and renders all photos with one h1; gallery.js lightbox works on the new page.

### DT-7 — "Kde nás nájdete?" (`sections/kontakt.php`, CSS)
View `screenshots/6-kde-nas-najdete.png`. Map left with thick purple rounded border. Right column: info cards with THICK colored borders + icons from `public/assets/icons/` (address→`contact-us.svg` or a home glyph, phone→`contact-us-1.svg`, hours→`clock.svg`). The "Sledujte nás na sociálnych sieťach:" label must be on the SAME ROW as the Facebook + Instagram circular icon links (currently likely stacked). Icons all from assets. Keep existing content/links (address/phone/email/hours via Content + Social). Rebuild .min. Test: kontakt.php references the icon SVGs; social label+icons in one fl/flex row; CSS thick borders.

### DT-8 — Regression + bookkeeping + deploy
- Re-run `build-assets.php`; full suite `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar private/tests` green; lint sweep; dev smoke (/,/galeria,/rezervacia,/faq,/ochrana-udajov,/admin/login 200; /admin 302; one `<h1>` on / and /galeria; skip-link present).
- Append a "Design corrections shipped" note to `docs/plans/2026-05-15-quality-sprint1-STATUS.md`.
- git push origin main; lftp mirror public/ + private/; verify prod safety invariants (public / 503, robots Disallow) + new assets (logo.png/rainbow.png/galeria 200) cache-busted; /galeria reachable behind staff bypass.
- Commit bookkeeping; push.

---
Notes: keep every change minimal & matching the screenshots; do NOT redesign beyond the listed feedback. Preserve a11y from Sprint 3 (skip link, single `<main>`, focus-visible, aria) — especially: new /galeria page must have skip link + single `<main id=main>` via the layout (it uses `layout.php` which already provides them). Hero must keep exactly one `<h1>`. Owner will: provide final hero tagline copy + real 6th gallery photo via /admin.
