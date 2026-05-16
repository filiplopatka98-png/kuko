# Frontend remarks batch (2026-05-16) — plan

> Execute via subagent-driven-development (implementer → review per task), ONE deploy at the end. Decisions captured from user Q&A.

## Decisions
- #8 address icon: USER WILL SEND a house SVG → that sub-item waits. Hours icon → `clock-1.svg` (yellow smiley) now.
- #11 logo: BIG logo overlapping the topbar (top edge at page top, mail/tel/social to the sides), topbar NO border-bottom — per `screenshots/1-hero.png`.
- #13 colors: align CSS tokens to the EXACT design palette (global).

## Color map (#13) — design palette → tokens
F7EFFA, FDF7FF (pale pinks), 9DDBE8 blue, FBC3AA peach, F8E179 yellow, EFE1F5 light-purple, FFFFFF, D0BBDA purple, FEF9F3 cream, 62534C / 725F56 browns.
- `--c-blue`  #9ED7E3 → **#9DDBE8**
- `--c-peach` #F8B49D → **#FBC3AA**
- `--c-yellow` #F7D87E → **#F8E179**
- `--c-purple` #C9A8E1 → **#D0BBDA**
- `--bg-cream` #FFF8EE → **#FEF9F3**
- `--bg-pink-soft` #FBEEF5 → **#FDF7FF** (use #F7EFFA / #EFE1F5 as secondary tints where a slightly stronger pink/lilac is needed)
- `--c-white` #FFFFFF → unchanged (#FFFFFF)
- `--c-text` #3D3D3D → **#62534C**
- `--c-text-soft` #6A6A6A → **#725F56**
- `--c-accent` #D88BBE, `--c-pink` #F5C3DE — NOT in the list (brand accent for CTAs/active states). LEAVE UNCHANGED unless review shows a contrast/clash issue; flag for user. (Icons use #9ddbe8/#fbc3aa/#f8e179/#62534c which now match the tokens — good.)
- **MUST verify WCAG AA** (Sprint-3 A5 invariant + ContrastFocusTest): brown `#62534C` text on `#FEF9F3`/white ≥ 4.5:1; `#725F56` soft text ≥ 4.5:1 (or only on large text). Pick the darker brown for body if needed. Keep `.legal-h2`/site contrast green.

## Tasks
- **R1 (#13)** colors: retune `:root` tokens in `public/assets/css/main.css` + `public/assets/css/admin.css` to the map above; rebuild `.min`; verify suite + ContrastFocus + visual tokens; no markup change.
- **R2 (#1,#11)** header: consistent topbar icon sizes (desktop==mobile, no shrink); BIG logo overlapping topbar per 1-hero.png; `.topbar` remove `border-bottom`; keep hamburger/skip-link/3-row semantics + responsive.
- **R3 (#2,#3,#4,#5)** oslavy.php + CSS: `.package__badge` remove the cream/white border (keep solid colour + optional soft shadow); replace emoji 👶/⏰ with `little-kid.svg` (počet detí) + `clock.svg` (časový harmonogram) via Asset::url, sized ~18-20px, colour ok on the white meta pill; add editable content block `oslavy.note` rendered below the package grid with the exact text: `*Konečná cena závisí od možností prispôsobenia - Každý balíček si môžete upraviť podľa vašich predstáv: predĺženie času oslavy, výzdoba na mieru (téma, farby), catering pre deti aj rodičov, torta alebo sweet bar, špeciálne požiadavky…` (seed it idempotently in seed-cms; fallback === seed); `.package__incl` items: drop the literal `✓ `, add purple/pink dot bullets via `li::before` per `screenshots/4-balicky.png`. Keep per-package dynamic+fallback rendering, single h1 rules (section uses h2/h3), straddle CTA.
- **R4 (#6,#7)** `.section__rainbow`: ensure a clearly visible slight tilt (~ -5deg) AND reduce bottom margin so it sits closer to the "Fotogaléria"/heading; verify rotate not overridden. Lightbox perf: `public/assets/js/gallery.js` + the `data-lightbox` source in `sections/galeria.php` & `pages/gallery.php` — open the **webp** (≤140KB) instead of the ~2MB jpg, and PRELOAD the previous/next image on open & on navigate (so switching is instant). Keep accessibility (focus, esc, arrows) intact.
- **R5 (#9,#10)** FAQ (`pages/faq.php`) + Fotogaléria (`pages/gallery.php`): center the `<h1>`; REMOVE the "← Späť na domov" button; ADD a reservation CTA block (soft panel: short heading + `<a class="btn" href="/rezervacia">Rezervovať oslavu</a>`) — make the CTA heading/text editable content blocks (`cta.faq.*` / shared `cta.reservation.*`) with sensible Slovak defaults, seeded idempotently. Nav menu + footer: change the "Fotogaléria" link from `/#galeria` to `/galeria` (the standalone page). Keep exactly one `<h1>` per page; a11y intact.
- **R6 (#8)** kontakt.php: hours icon → `clock-1.svg` (yellow smiley, matches design) via Asset::url. Address icon: WAIT for user's house SVG; until provided keep current inline house but note pending. (Implement hours now; address icon swap is a tiny follow-up when SVG arrives.)
- **R7** regression (full suite green, lint, dev smoke all pages 200 / one h1 / no PHP errors), bookkeeping, push + lftp deploy + seed (new content blocks) + prod safety-invariant verify.

Notes: keep Sprint-3 a11y everywhere (skip link, single `<main>`, focus-visible, contrast AA). One deploy at R7 (not per task). Stale-min guard: rebuild `.min` whenever css/js source changes & commit it. House SVG (#8) is the only open dependency on the user.
