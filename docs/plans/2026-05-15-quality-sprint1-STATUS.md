# Sprint 1 — Stav / Handoff (2026-05-15)

Tracking dokument pre Quality Roadmap Sprint 1. Plán: `docs/plans/2026-05-15-quality-sprint1.md`. Roadmap: `docs/plans/2026-05-14-roadmap-quality.md`. Vetva: `main`. Pracujeme cez subagent-driven-development (implementer → spec review → code-quality review per task).

## Kontext / prostredie
- PHP binárka: `/opt/homebrew/bin/php` (NIE `php`)
- Testy: `/opt/homebrew/bin/php private/lib/vendor/phpunit.phar private/tests`
- Posledný stav testov: **OK (115 tests, 214 assertions)** — zelené
- Deploy = manuálne lftp SFTP mirror (host `kuko-detskysvet.sk:22`, user `filip.kuko-detskysvet.sk`); maintenance gate stále ON, public_indexing stále OFF (pred-launch). Sprint 1 sa zatiaľ NEDEPLOYOVAL — deploy je až T9.

## Hotové (commitnuté na main)

| Commit | Čo |
|---|---|
| `87ea9ce` | Sprint 1 plán |
| `98a07cc` | T1: CSRF token v admin login forme + verify v POST handleri |
| `90bc8e4` | T1: silnejší test (CSRF overený PRED čítaním credentials) |
| `63655b8` | T1: fix po code-review — CSRF zlyhanie = 403 + presná hláška „token vypršal" (konzistentné s kódom) |
| `1332d07` | T2: `Kuko\LoginThrottle` brute-force (5 zlých/h per IP + per username, success vyčistí buckety) + zapojené do login POST + `locked` hláška v šablóne |

### T1 — Admin login CSRF token — ✅ HOTOVÉ (spec ✅ + code-quality ✅ po fixe)
- `private/templates/admin/login.php`: hidden `csrf` field
- `public/admin/index.php`: `Csrf::verify` ako prvý príkaz v POST /admin/login, zlyhanie → 403 + `['expired'=>true]`
- Šablóna error blok poradie: `locked → expired → error`
- Test: `private/tests/integration/AdminLoginCsrfTest.php`

### T2 — Brute-force throttle — ✅ implementácia + spec review ✅
- `private/lib/LoginThrottle.php` (file-based, `permit/recordFailure/recordSuccess`, dir `APP_ROOT/private/logs/ratelimit`)
- Zapojené do `public/admin/index.php` POST /admin/login: permit→429+`locked`, success→recordSuccess+redirect, bad creds→recordFailure+401
- Test: `private/tests/unit/LoginThrottleTest.php` (3 testy)
- **Spec review: ✅ COMPLIANT.** Poznámka: window-expiry vetva (`count()` vráti 0 pre starý bucket) nie je pokrytá testom — spec to nevyžaduje, ale je to jediná netestovaná netriviálna vetva.

## ✅ SPRINT 1 KOMPLET A NASADENÝ (2026-05-15)

T1–T9 hotové. Produkčný deploy cez lftp mirror overený: favicon.ico/manifest/og-cover 200, HSTS header live, robots.txt stále `Disallow: /` (indexácia OFF), public `/` 503 (maintenance stále chráni), /admin/login 200. 131 testov zelených.

**Otvorené follow-ups (nie blokátory deployu, ale pred go-live):**
1. Owner zaregistruje mesačný cron na WebSupporte: `/usr/bin/php /kuko-detskysvet.sk/private/cron/retention.php` (GDPR retention).
2. **Go-live blokátor:** opraviť odložený `.htpasswd` prod bug (viď nižšie) — inak sa nikto neprihlási do prod adminu.
3. Owner action items: Lighthouse baseline, axe scan, Google Business Profile, HSTS preload registrácia.

## ⏭️ KDE POKRAČOVAŤ (presný bod)

**Hotové T1–T8** (každá spec+code-quality review prešla, všetky fixy zapracované). T9 lokálne časti hotové: full suite **OK (131 tests, 280 assertions)**, lint čistý, dev smoke OK (/,  /rezervacia, /faq, /ochrana-udajov, /admin/login → 200; /admin unauth → 302), roadmap-quality.md zaškrtnuté + Sprint 1 blockquote.

**Zostáva už LEN produkčný deploy (T9 záver)** — čaká na explicitné OK od usera (zápis na shared infra). Deploy = lftp SFTP mirror `public/`→`web/`, `private/`→`private/` (bez DB migrácie — Sprint 1 nemá schema zmenu). Maintenance gate zostáva ON, public_indexing OFF. Po deploy: owner zaregistruje mesačný cron `/usr/bin/php .../private/cron/retention.php`.

**POZOR pred go-live:** odložený `.htpasswd` prod bug (nižšie) treba opraviť skôr než sa vypne maintenance — inak sa nikto neprihlási do prod adminu. Sprint 1 deploy je ale bezpečný aj bez toho (maintenance chráni public, do adminu sa aj tak zatiaľ nikto neprihlasuje).

Commity Sprint 1: `98a07cc 90bc8e4 63655b8 1332d07 270133a 60fae27 c20adcf 0683d04 33482ff e254838 d39e873 ef247ee 8cf5381` + docs `db502ab`. Working tree čistý.

## Stav úloh

- **T1–T6** — ✅ HOTOVÉ (impl + spec review + code-quality review, všetky fixy zapracované)
- **T7** — ⏳ Favicon set: `private/scripts/gen-favicons.php` (GD z `public/assets/img/logo.png`, ImageMagick NIE je) → favicon.ico/16/32/apple-touch/192/512 + `public/manifest.webmanifest` + `head.php` link set. Pozn.: `head.php` dnes odkazuje `/favicon.ico` ktorý NEEXISTUJE (404) — T7 to opraví. Plán „Task 7".
- **T8** — ⏳ `public/assets/img/og-cover.jpg` 1200×630 (rozšíriť gen-favicons.php, font `NunitoSans.ttf`) + `head.php` default og:image → og-cover. Plán „Task 8".
- **T9** — ⏳ Plný regression + lint sweep + dev smoke + zaškrtnúť hotové v `roadmap-quality.md` + **produkčný deploy** (lftp; bez DB migrácie — Sprint 1 nemá schema zmenu) + owner musí zaregistrovať mesačný cron `/usr/bin/php .../private/cron/retention.php`. **POZOR:** pred go-live (maintenance off) treba ešte opraviť odložený `.htpasswd` prod bug (viď nižšie) — inak sa nikto neprihlási do prod adminu.

## 🐞 ZNÁMY PROD BUG — admin login (odložené, opraviť PRED go-live)

**Symptóm:** na produkcii sa NEDÁ prihlásiť do adminu ani so správnym menom/heslom (lokálne OK).

**Príčina:** `Auth::loadHtpasswd()` (`private/lib/Auth.php:101`) hľadá `APP_ROOT . '/public/admin/.htpasswd'`. Na prod `APP_ROOT = kuko-detskysvet.sk/` (z `web/index.php` → `../private/lib/App.php` → `dirname(__DIR__,2)`), ale verejný adresár je `web/`, nie `public/`. Súbor sa nikdy nenájde → prázdne entries → každý login zlyhá. Admin login sa na prod nikdy reálne neoveril (deploy/seed šli cez token-gated `_setup.php`).

**Dohodnutá oprava (NEROBIŤ teraz — user rozhodol odložiť, spraviť s ostatnými zmenami):**
- Zmeniť `Auth.php` aby `.htpasswd` čítal z `APP_ROOT . '/config/.htpasswd'` (cesta funguje lokálne aj na prod, je mimo DocumentRootu = bezpečnejšie než `web/admin/`).
- TDD test na path resolution.
- Vygenerovať `config/.htpasswd` (bcrypt, gitignore — pridať `/config/.htpasswd` do `.gitignore`).
- Deploy: Auth.php + upload `config/.htpasswd` → `kuko-detskysvet.sk/config/.htpasswd`.

**Blokátor pre go-live:** áno — bez opravy sa nikto nevie prihlásiť do prod adminu.
**STAV (2026-05-15):** `.htpasswd` cesta OPRAVENÁ (Auth číta `config/.htpasswd`, commit `e28b3c4`, nasadené, login funguje). Tento blokátor je VYRIEŠENÝ.

## 🐞 ZNÁMY PROD BUG #2 — MediaRepo gallery path (rovnaká rodina, odložené)

`public/index.php:44` konštruuje `new MediaRepo($db, APP_ROOT.'/public/assets/img/gallery')`. Na prod `APP_ROOT=kuko-detskysvet.sk/`, ale verejný adresár je `web/` → cesta `…/public/assets/img/gallery` neexistuje. Homepage **display** galérie funguje (šablóna používa URL `/assets/img/gallery/...`, nie filesystem), ale **admin upload/delete fotky** na prod zapisuje/maže v neexistujúcej ceste → upload novej fotky cez admin na prod zlyhá. **Fix:** MediaRepo by mal cestu k webrootu rozlíšiť robustne ako `Kuko\Asset::docRoot()` (DOCUMENT_ROOT → APP_ROOT/public → APP_ROOT/web). Odložené — spraviť spolu s ostatnými prod-path opravami pred go-live. Nie je blokátor pre verejný launch (admin upload nie je launch-critical), ale opraviť skoro.

## Owner action items (NEBUDUJEME — nahlásiť userovi na konci Sprintu 1)
1. P1 Lighthouse baseline (owner spustí v Chrome, screenshoty do `docs/audits/`)
2. A1 axe DevTools scan (owner spustí rozšírenie)
3. S3 Google Business Profile (owner vytvorí/overí)
4. B2 HSTS preload registrácia na hstspreload.org (až keď je HSTS stabilné v prod)

## Po Sprinte 1
Roadmap hovorí: po týchto 8 → prepnúť `maintenance` flag false + `public_indexing` true. Potom pokračovať ďalšími sprintami zvyšku `roadmap-quality.md` (SEO obsah, performance minifikácia/critical CSS/font subsetting, zvyšok a11y, UX detaily vrátane U1 symetrický buffer). Po #3 nasleduje user priorita #1 (go-live prerekvizity: SMTP heslo, reCAPTCHA test).

---

## Sprint 2 KOMPLET A NASADENÝ (2026-05-15)

S2-T1..T6 hotové (každá impl + spec + code-quality review + fixy), nasadené na prod, overené (woff2/min css+js/hero-768 = 200; public / 503 + robots Disallow nezmenené). 157 PHPUnit testov green. Commity `95c663a`…`af2752e`, pushnuté na GitHub.

Dodané: per-page noindex; single H1 reservation; WOFF2 (~60% menšie) + preload fix; CSS/JS minifikácia (Asset prefers .min + build-assets.php + stale-min guard); responsive hero (768px mobil, 146→50KB, media-scoped preload).

Otvorené (mimo Sprint 2): MediaRepo prod-path bug #2 (admin upload); hero.jpg PNG-content/.jpg-ext (spawnnutý cleanup task); zvyšok roadmap-quality.md = Sprint 3 (a11y A2/A3/A5/A6, UX U1/U3/U4, security B3/B5/B6/B7, S5 analytics-owner, S3 GBP-owner).

---

## Sprint 3 — ROZPRACOVANÝ (2026-05-15)

Plán: `docs/plans/2026-05-15-quality-sprint3.md`. **T1–T3 hotové (impl+spec+code-quality review APPROVED), commitnuté na `main`, NIE sú ešte nasadené (deploy je batchnutý v T8).** 172 PHPUnit testov green.

- ✅ S3-T1 (A5) `05ff94e` — `--c-text-soft` #7A7A7A→#6A6A6A (AA 5.1:1) v main/rezervacia/admin.css; site-wide `:where(...):focus-visible`; reduced-motion fix pre `.step.is-active` fadeIn v rezervacia.css.
- ✅ S3-T2 (A2) `a47043e` + fix `89d334b` — skip-link + `<main id="main" tabindex="-1">` vo všetkých 3 layoutoch; refactor: `<main>` zhora z 6 page templates do layoutov (page→`<div>`, footer presunutý do layout.php — DRY, bez regresie), `display:contents` odstránený (zachoval landmark v a11y strome).
- ✅ S3-T3 (A3) `69c98bb` — `aria-required` na f-kids/f-name/f-phone/f-email; `.req`+legend „* povinné"; `#form-error` role=alert+aria-live=assertive; success region role=status+aria-live=polite; rezervacia.js overené že už správne announce-uje (bez JS zmeny).

**ZOSTÁVA (presný bod pokračovania) — pokračovať v `docs/plans/2026-05-15-quality-sprint3.md`:**
- ⏳ S3-T4 (U1) symetrický time-slot buffer v `Availability::forDate()` (~riadok 105-108: subtract `[rStart-buffer, rEnd]`, clamp 0) + test pre pre-buffer prípad (MINI 12:00–14:00 pred MAXI 14:00 musí byť odmietnuté pri buffer 30).
- ⏳ S3-T5 (U3) SK phone validácia v `Reservation.php:45` (strip separátory → `^(\+421|0)[0-9]{9}$`) + email `<datalist>` + sessionStorage persist + default time 14:00 v rezervacia.js.
- ⏳ S3-T6 (U4) .ics download + Google Calendar link na success kroku.
- ⏳ S3-T7 (B6/B5) session cookie hardening v App.php (secure/httponly/samesite/use_strict_mode, guard `PHP_SAPI!=='cli'` + session_status NONE) + `private/cron/db-backup.php` + `docs/RECOVERY.md`.
- ⏳ S3-T8 regression + roadmap bookkeeping + **deploy celého Sprintu 3** (push + lftp; rebuild build-assets.php; overiť prod invarianty). POZOR: T1–T3 sa nasadia až tu spolu s T4–T7.

Owner/manuál (nebudovať): B3 (PHPMailer CVE, Leaflet/reCAPTCHA review), B7 (OWASP ZAP, manual pentest, subdomain takeover, DNS DMARC/SPF/DKIM), A6 plain-language copy (cez /admin), + go-live owner items (SMTP, reCAPTCHA browser test, GDPR cron registrácia, Lighthouse/axe, Google Business Profile, HSTS preload).

Po Sprinte 3: userove „pripomienky k dizajnu" (čaká, vymenili sme poradie), potom go-live (#1: flip maintenance OFF + public_indexing ON).

---

## ✅ Sprint 3 KOMPLET A NASADENÝ (2026-05-15)

S3-T1..T8 hotové (každá impl + spec + code-quality review APPROVED), nasadené na prod, overené (cache-busted: main.min.css má #6A6A6A+skip-link; rezervacia.min.js má VCALENDAR+kuko_resv_draft; server sizes == local; public / 503 + robots Disallow nezmenené; /admin/login 200 so skip-link). **194 PHPUnit testov green.** Commity `05ff94e`…`c8a5386`, pushnuté na GitHub, `HEAD==origin/main`.

Dodané: A5 AA kontrast + site-wide focus-visible + reduced-motion; A2 skip-link + single <main id=main> (footer DRY do layout.php); A3 form aria-required/alert/status; U1 SYMETRICKÝ buffer (pred+po rezervácii); U3 SK phone validácia + email datalist + sessionStorage draft + default 14:00; U4 add-to-calendar (.ics+Google) na success; B6 session cookie hardening (inert pod CLI/testami); B5 db-backup cron + docs/RECOVERY.md.

Pozn.: WebSupport edge cachuje bare asset URL (bez query) ~stale; reálni používatelia dostávajú čerstvé cez `?v=filemtime` (Asset::url, Sprint 2) — overené že server súbory == local.

Stav roadmap-quality.md: Sprint 1+2+3 odškrtnuté. Zostáva owner/manuál: B3 (PHPMailer CVE, Leaflet/reCAPTCHA review), B7 (OWASP ZAP, manual pentest, subdomain takeover, DNS DMARC/SPF/DKIM), A6 plain-language copy (cez /admin), S5 analytics, S3 GBP; + go-live owner items (SMTP heslo, reCAPTCHA browser test, GDPR cron registrácia na WebSupporte, Lighthouse/axe baseline, Google Business Profile, HSTS preload registrácia). Po nich: flip maintenance OFF + public_indexing ON.

Ďalej podľa user plánu: **userove „pripomienky k dizajnu"** (čaká), potom go-live (#1).

---

## Design corrections — ROZPRACOVANÉ (2026-05-15)

Plán: `docs/plans/2026-05-15-design-fixes.md` (user feedback vs `screenshots/`). **DT-1 + DT-2 hotové (impl+review APPROVED), commitnuté na `main`, NIE sú ešte nasadené (deploy batchnutý v DT-8).** 200 PHPUnit testov green.

- ✅ DT-1 `2f1ddcf` — správny brand logo + rainbow graphic vygenerované z `assets/Logo.jpeg` (`private/scripts/gen-brand-assets.php`): `public/assets/img/logo.png|webp` (600×442, prepísal starý zlý logo), `rainbow.png|webp` (320×104, čistá dúha+dievča, crop 0.44). Vizuálne overené.
- ✅ DT-2 `09636ab` — header prebudovaný na 3 riadky podľa `1-hero.png`: topbar (mail+tel s ikonami vľavo, „Sledujte nás:"+FB/IG vpravo cez Social::url), centrované logo, ružový (#FBEEF5) nav band; footer logo cez Asset::url v správnej veľkosti. Hamburger main.js kontrakt overený funkčný (.nav__toggle/#primary-nav/.is-open + CSS open-state ≤768px). a11y zachované.

**ZOSTÁVA (presný bod) — pokračovať v `docs/plans/2026-05-15-design-fixes.md`:**
- ⏳ DT-3 Hero: 3. textová linka ako editovateľný `Content::get('hero.tagline', …)` blok (placeholder fallback; owner doplní presný text cez /admin/content) + seed-cms.php + .min.
- ⏳ DT-4 O nás: hrubší border, 4 ikony z `public/assets/icons/` (playground/coffee/friendship/balloons, väčšie, 4. chýbala), button „Rezervovať oslavu" absolútne na spodnom borderi poslednej (fialovej) karty.
- ⏳ DT-5 Oslavy: hrubé bordery, ikona-badge nad nadpisom prechádzajúca cez TOP border, button cez BOTTOM border (ako DT-4), ikony z assets (balloon/little-kid/uzavreta).
- ⏳ DT-6 Galéria: rainbow nad nadpisom, 6 obrázkov 3×2 s 30px radius (6. = dočasne reuse existujúcej — seed/copy galeria_5 ako 6.), button „Prejsť do galérie" → nová route `/galeria` + `pages/gallery.php` (VŠETKY DB fotky cez MediaRepo->listVisible, lightbox, 1×h1, layout.php).
- ⏳ DT-7 Kontakt: hrubé bordery, ikony z assets (contact-us/clock…), „Sledujte nás" v JEDNOM riadku s FB/IG logami.
- ⏳ DT-8 regression + bookkeeping + **deploy celých design-fixes** (DT-1..DT-7 sa nasadia naraz; push+lftp; overiť prod invarianty + nové assety cache-busted).

Owner doplní neskôr: presný hero tagline text (cez /admin/content) + reálna 6. galéria fotka (cez /admin galéria). Po design-fixes: go-live (#1) owner items + flip maintenance OFF.

---

## ✅ Design corrections DT-1..DT-8 — NASADENÉ (2026-05-15)

DT-1..DT-7 hotové (každá impl + review; konsolidovaný review APPROVED), DT-8 deploy. 219 PHPUnit testov green. Commity `2f1ddcf`…`12ff356`.
- DT-1 logo+rainbow z Logo.jpeg · DT-2 header rebuild (topbar/center logo/pink nav) + footer logo · DT-3 hero tagline (editovateľný blok) · DT-4 O nás (hrubé bordery+ikony+straddle CTA) · DT-5 Oslavy (bordery+top icon badge+straddle CTA) · DT-6 galéria (rainbow+6 grid 30px radius+/galeria stránka, seed 6. fotka idempotentne) · DT-7 Kontakt (bordery+asset ikony+social v jednom riadku).
- Prod seed cez `_setup.php?action=seed&token=` (pridané, idempotentné): doplní `hero.tagline` content blok + 6. galéria fotku; ostatné = skip.
- Owner doplní cez /admin: presný hero tagline text + reálnu 6. galéria fotku (teraz dočasne = galeria_5).

**DT-1..DT-8 OVERENÉ NA PROD (2026-05-15):** prod seed idempotentný (hero.tagline blok + 6. galéria fotka prítomné), nový logo/rainbow/icons assety 200, /galeria route existuje (503 = maintenance ako všetky public routes pred launchom), safety invarianty držia, _setup.php self-destruct OK. Commity po `7e68094`, GitHub sync.

## ⏭️ ZOSTÁVA: DT-9 — Admin WP-style layout (NOVÁ POŽIADAVKA, ďalšia session)

User chce admin prerobiť ako WordPress admin: **ľavý sidebar menu**, sekcia **Stránky**, sekcia **Nastavenia** (kam pôjde aj maintenance aj logy), **Rezervácie** ako samostatná položka s **tabmi** (blokácie atď.). Veľká architektonická zmena admin layoutu (`private/templates/admin/layout.php` + všetky admin templaty + nav štruktúra v `public/admin/index.php`). Treba vlastný plán (mapovať súčasné /admin routes → nové sekcie/sidebar/taby) + subagent-driven exekúcia + review + deploy. Súčasné admin sekcie: rezervácie(list/detail/calendar), packages, content, gallery, contact, seo, maintenance, log, gdpr, opening-hours, blocked-periods.

---

## ✅ DT-9 Admin WP-style layout — HOTOVÉ + NASADENÉ (2026-05-15)

`fabae78` + fix `33e89dd` (review APPROVED po fixe). 223 testov green. Iba `private/templates/admin/layout.php` + `public/assets/css/admin.css`(+min) + test — ŽIADNE route/section-template zmeny.
- Ľavý sidebar: **Rezervácie** (top-level, tab bar Zoznam/Kalendár/Blokácie/Otváracie hodiny na resv-group routes) · skupina **STRÁNKY** (Obsah/Balíčky/Galéria/Kontakt) · skupina **NASTAVENIA** (SEO/Maintenance/Logy/GDPR/Všeobecné) · footer iCal/Web/@user/Odhlásiť.
- Active-state exact-or-boundary (`/admin/log`≠`/admin/logout`, `/admin/seo`≠`/admin/settings`); `/admin` dashboard exact; reservations-group logika zachovaná. Pure-CSS responsive hamburger (≤900px), `.sr-only` doplnené do admin.css. a11y zachované (skip-link first, single `<main id=main>`). Login (layout-minimal) neovplyvnený, auth gate nezmenený.

---

## Admin IA v2 — ROZPRACOVANÉ (2026-05-15). Plán: docs/plans/2026-05-15-admin-ia-v2.md

**HOTOVÉ + commitnuté na `main`, NIE nasadené (deploy batchnutý v AD-5):** 278 PHPUnit testov green.
- ✅ AD-1 `cb34bee` — globálne štýlovanie všetkých admin inputov/selectov/textarea (.admin-main baseline + accent focus ring), de-dup .admin-field/.admin-filter.
- ✅ AD-2 `82919fa` — galéria homepage výber: migrácia **006_gallery_homepage.sql** (on_homepage stĺpec), MediaRepo setHomepage(cap 6)/homepageSet(picked≤6 + PHP-shuffle random fill), admin checkbox route /admin/gallery/{id}/homepage, homepage `/` používa homepageSet(), /galeria stále listVisible() (všetky). seed-cms idempotentne označí prvých 6.
- ✅ AD-3 `08647d7` — faq.intro/faq.items/privacy.body ako Content bloky (fallback byte-identický; výstup nezmenený). FAQ JSON-LD ostáva static + NOTE komentár.
- ✅ AD-3b `c44c7c8` + sec-fix `065f9d2` — HtmlSanitizer rozšírený (h2/div/details/summary + class + relative/#/./ href), FAQ/privacy round-trip cez set() lossless; privacy inline style→.legal-h2/.legal-back triedy. **Security: opravený //host protocol-relative bypass.** XSS guards (script/style/iframe/on*/js:/data:) zachované.

### ⚠️ DEPLOY PORADIE (kritické — pre AD-5 / ďalšiu session)
1. lftp nasadiť VŠETOK kód AD-1..AD-3b+AD-4 (vrátane `private/lib/HtmlSanitizer.php`, `006_gallery_homepage.sql`, MediaRepo, admin templaty, public/index.php, seed-cms.php, main.css/min, admin.css/min).
2. Prod: `_setup.php?action=migrate&token=` → aplikuje **006** (on_homepage stĺpec). MUSÍ byť pred seedom (seed-cms dopytuje on_homepage).
3. AŽ POTOM `_setup.php?action=seed&token=` — seed-cms je idempotentný; seeduje faq/privacy bloky + označí 6 on_homepage. **Smie sa spustiť LEN keď je už nasadený opravený HtmlSanitizer (AD-3b+065f9d2)** — inak by `$cb->set` uložil zmangľovaný FAQ/privacy markup. Poradie: kód → migrate(006) → seed.
4. Overiť: public / 503 + robots Disallow nezmenené, /admin/login 200, /galeria všetky fotky, homepage 6 (curated+random), FAQ/privacy vyzerajú rovnako (z DB == fallback, lossless).

### ⏭️ ZOSTÁVA — AD-4 (najväčší task) + AD-5
**AD-4 Admin IA restructure** (plán docs/plans/2026-05-15-admin-ia-v2.md sekcia AD-4): prepísať `private/templates/admin/layout.php` sidebar na cieľové IA (Rezervácie / Stránky / Galéria / Nastavenia, single items + footer); pridať tab bary: Rezervácie (Zoznam/Kalendár/Blokácie/Otváracie hodiny/**Balíčky**/**Nastavenia**) keď `$isResvGroup` (rozšíriť o /admin/packages,/admin/settings), Nastavenia (Kontakt/Maintenance/Logy/GDPR) nový `$isSettingsGroup` {/admin/contact,/admin/maintenance,/admin/log,/admin/gdpr}. Nové routy `/admin/pages` (zoznam 5 stránok: Domov/Rezervácia/Fotogaléria/FAQ/Ochrana údajov) + `/admin/pages/{page}` + `/admin/pages/{page}/save` → `pages.php`+`page-edit.php` s pod-tabmi Obsah|SEO (Domov: hero/about/cennik/kontakt/footer bloky; FAQ: faq.*; Privacy: privacy.body; ostatné: SEO-only) — REUSE existujúcu logiku z content.php save + seo.php save (ContentBlocksRepo + SettingsRepo, CSRF, audit, flash). `/admin/content` a `/admin/seo` → redirect na `/admin/pages`. `/admin/settings`(Rezervácie tab), `/admin/contact`(Nastavenia tab), `/admin/packages`(Rezervácie tab) handlery nezmenené, mení sa len nav grouping. Zachovať skip-link + single `<main id=main>` + normalizovaný `$path` (rtrim) + $active/$aria + pill taby. Aktualizovať AdminWpLayoutTest + nové testy. a11y zachované.
**AD-5**: full regression + lint + dev smoke (všetky admin routy, /admin/pages list+editor, tab bary, galéria checkboxy ≤6, FAQ/privacy z DB==fallback) + bookkeeping + push + lftp + prod migrate(006)→seed (poradie vyššie) + overenie invariantov.

---

## ✅ Admin IA v2 (AD-1..AD-5) — KOMPLET a NASADENÉ (2026-05-15)

AD-1..AD-4b cez subagent-driven (každý implementer→review; AD-3b prísny security review odhalil+opravil //host open-redirect bypass). AD-5 deploy: kód→migrate(006)→seed (poradie dodržané; sanitizer nasadený PRED seedom → FAQ/privacy bloky lossless). 289 PHPUnit testov green. Commity `cb34bee`…`08e6238`.
- AD-1 globálne admin input styling. AD-2 galéria homepage výber (migr.006 on_homepage, max6+random, /galeria=všetky). AD-3 FAQ/privacy editovateľné Content bloky. AD-3b HtmlSanitizer rozšírený (details/summary/div/h2/class/relat.href) + sec-fix //host. AD-4a „Stránky" zoznam+per-page Obsah|SEO editor (+seo.gallery). AD-4b sidebar IA: Rezervácie/Stránky/Galéria/Nastavenia + tab bary (Rezervácie: Zoznam/Kalendár/Blokácie/Otv.hodiny/Balíčky/Nastavenia; Nastavenia: Kontakt/Maintenance/Logy/GDPR).
- Prod overené: migr.006 applied, seed idempotentný (privacy.body/faq.intro/faq.items prítomné, 6 on_homepage, seo.gallery), server súbory==local (HtmlSanitizer/layout/admin.css), public 503+robots Disallow nezmenené, /admin/login 200, _setup self-destruct. GitHub sync.
- Owner doplní cez /admin: presný hero tagline, reálna 6. galéria fotka, výber homepage fotiek (checkbox max6), per-page obsah/SEO.

**Zostáva už LEN go-live #1 (owner/manuál):** SMTP heslo, reCAPTCHA browser test, GDPR retention cron registrácia na WebSupporte, Lighthouse/axe, Google Business Profile, HSTS preload → potom flip maintenance OFF + public_indexing ON. Žiadny ďalší kód odo mňa nie je potrebný.

---

## ✅ Pre-launch QA (2026-05-16) — READY for go-live

Code prod==local==`021e545`, **310 PHPUnit testov green**, lint čistý, git sync.
- Verejné stránky (/, /rezervacia, /faq, /ochrana-udajov, /galeria): všetky **200**, presne **1 `<h1>`**, 1 `<main>`, skip-link, správny JSON-LD (/ LocalBusiness, /faq +FAQPage 6 otázok, /galeria, /ochrana-udajov, /rezervacia). Žiadne PHP errory/warningy počas crawlu.
- FAQ repeater: 6 otázok renderuje + FAQPage schema auto z `Faq::items`.
- Admin: všetkých 14 routov auth-gated (unauth → 302 login), /admin/login 200.
- Safety invarianty držia: public 503 (maintenance), robots `Disallow: /` (noindex), sitemap 200.
- 🔧 **QA našla a opravila reálny SEO bug:** `/rezervacia` + `/ochrana-udajov` mali generický duplicitný `<title>`/meta (chýbal `$pageType` → Seo::resolve padlo na `seo.default`). Opravené (pridaný `$pageType` + canonical), distinct tituly overené, regresný test `PageSeoTypeTest` (pokrýva všetky public stránky), nasadené.
- ⚠️ Admin UI za auth nebol klik-testovaný automaticky (bez creds) — pokryté 310 testami + štruktúrnym review; owner nech spraví rýchly manuálny klik-through pri go-live.

**Verdikt: žiadne vývojárske blokátory. Pripravené na go-live #1 (owner kroky).**

---

## ✅ Frontend remarks batch (2026-05-16) — IMPLEMENTOVANÉ + LOKÁLNE OVERENÉ, NASADENIE POZASTAVENÉ (rozhodnutie usera)

Plán `docs/plans/2026-05-16-frontend-remarks.md`. 13 pripomienok cez subagent-driven (implementer→spec+quality review per task). **6 commitov LEN LOKÁLNE na `main`** (`e24280e`…`fa23c09`) — user zvolil „Hold everything": NEpushovať, NEnasadzovať; nasadenie spraví neskôr.
- **R1** (#13) CSS tokeny presne podľa design palety (main.css+admin.css+rezervacia.css); WCAG AA overené (#62534C na cream 7.01:1, #725F56 5.75:1).
- **R2** (#1,#11) konzistentné topbar ikony 18px, veľké logo cez topbar (neg. margin), topbar bez border-bottom.
- **R3** (#2,#3,#4,#5) badge bez cream borderu (shadow ostáva), emoji 👶/⏰ → `little-kid.svg`/`clock.svg` (Asset::url + raw v HEREDOC), editovateľný blok `oslavy.note` (seed===fallback, byte-identické), dot bullets v `.package__incl`.
- **R4** (#6,#7) `.section__rainbow` `rotate(-5deg)` + margin `0 auto .25rem` (bližšie k nadpisu); lightbox otvára webp (`data-lightbox-webp`, ~70–140KB vs ~2MB) + preload prev/next na open aj navigate; a11y (focus/Esc/šípky) zachované.
- **R5** (#9,#10) FAQ/galéria h1 centrovaný (scoped, homepage netknutá), „Späť na domov" → editovateľný rezervačný CTA `<aside>` (`cta.faq.*` / `cta.reservation.*`, seed===fallback), nav+footer `/#galeria`→`/galeria`. Admin: faq prefixes `['faq','cta']`, gallery `['cta']`.
- **R6** (#8) otváracie hodiny ikona → `clock-1.svg` (žltý smajlík). **Adresa/domček ikona POZASTAVENÁ** — čaká sa na house SVG od usera (potom 1-riadkový follow-up swap).

**Regresia (lokálne, všetko green):** PHPUnit **334 testov** · PHP lint čistý · stale-min guard green · dev smoke (`/`,`/faq`,`/galeria`,`/rezervacia`,`/ochrana-udajov` → 200, 1×`<h1>`, 0 PHP chýb) · `seed-cms.php` nacvičený na dev DB — 5 nových blokov (`oslavy.note`, `cta.faq.heading/text`, `cta.reservation.heading/text`) idempotentne insertne a renderuje sa.

**ZOSTÁVA na nasadenie (keď user povie):** `git push` → `lftp` mirror public/+private/ → token-gated prod seed (5 nových blokov) → overiť invarianty (public 503, robots `Disallow: /`). + open dependency: house SVG (#8 adresa).

---

## ✅ Frontend remarks batch — NASADENÉ na produkciu (2026-05-16)

GitHub push `b35c1e9..cb15be6` (origin/main). Surgical lftp deploy: 17 zmenených súborov (9→`web/`, 8→`private/`; docs/testy NEdeployované) — `--only-newer` mirror by re-uploadol celý strom (git checkout resetuje mtimes), preto cielený `put` len reálne zmenených súborov z `git diff b35c1e9..cb15be6`. Prod seed cez token-gated `_setup.php?action=seed` — idempotentný: všetko existujúce `= skip`, pridané LEN 5 nových blokov (`oslavy.note`, `cta.faq.heading/text`, `cta.reservation.heading/text`); `_setup.php` self-destruct OK. **Invarianty overené:** public `/`=**503** (maintenance stále ON), `robots.txt`=`Disallow: /` (indexácia OFF), `/admin/login`=200, sitemap=200, `home.svg` 200 a byte-identický s repom, `main.min.css` 200 (19194 B). Prod `config.php` nedotknutý (maintenance:true, public_indexing:false). DB migrácia žiadna (len content blocks). SFTP heslo ani prod config nezostali na disku (shred).

**Stav: celý 13-pripomienkový batch hotový, reviewnutý, nasadený. Žiadne otvorené závislosti.** Zostáva už len go-live #1 (owner kroky — SMTP, reCAPTCHA test, GDPR cron, Lighthouse, GBP, HSTS → flip maintenance OFF + indexing ON).

---

## ✅ Post-deploy fix batch (2026-05-16) — 4 vizuálne opravy NASADENÉ

Po prvom deployi user nahlásil 4 veci; opravené subagent-driven (implementer→review), vizuálne overené na dev serveri (Claude Preview), JEDEN deploy. Commity `73038ba`,`9954287`,`d5a0432`,`a1b87c7` (push `8de79d7..a1b87c7`).
- **#1 hlavička (regresia z R2):** `.nav__brand-row{margin-top:-42px}` ťahal nepriehľadný biely `.nav` cez topbar → topbar obsah zmizol. Fix: záporný offset presunutý na `.nav__brand` (z-index:210), topbar v normálnom flow viditeľný, prekrýva len priehľadné logo PNG. Overené desktop screenshotom (sedí s 1-hero.png). Známy kompromis: pri scrolle je vrchol loga tesný (sticky stav) — akceptované.
- **#2 dúha:** zväčšená (width 400, bbox 433), tilt `rotate(-8deg)` zostáva (ľavý ~nadpis / pravý vyššie), `#galeria`-scoped `margin-top:-13rem` → presah cez rozhranie oslavy(`--bg-cream`)/galéria(`--bg-pink-soft`) namerané 155/165px ≈ 50/50; `margin-bottom:-8px` bližšie k nadpisu; samostatná /galeria sa NEťahá pod sticky header (shared rule bez záporného margin-top).
- **#3 lightbox:** textové glyfy `‹/›/×` → vycentrované SVG v 48px kruhu; pridaný pás 6 miniatúr pod hlavnou fotkou s `is-active`/`aria-current`, klik=prepnutie, klávesnica zachovaná, otvára webp. Overené screenshotom otvoreného lightboxu.
- **#4 kontakt:** `.contact-card__value a` → `--c-text` + 700 + bez podčiarknutia; počítané štýly identické s adresou/hodinami (rgb(98,83,76), 700, Nunito Sans).

Deploy: len 3 súbory (`main.css`,`main.min.css`,`gallery.js`) → `web/`; testy/docs nedeployované; **žiadny DB seed** (len CSS/JS). Prod==repo byte-identicky (overené curl/diff). Invarianty držia: public `/`=503, robots `Disallow: /`, /admin/login=200, sitemap=200. Prod config nedotknutý. SFTP heslo `shred`-nuté. Suite 340 testov green. Žiadne otvorené závislosti.

---

## ✅ Iterácia 2 fix batch (2026-05-16) — NASADENÉ (commit bc4e11b)

User dal presné CSS hodnoty + nahlásil že lightbox stále nesedí. 4 zmeny, vizuálne overené (Claude Preview), 1 deploy (push `5086a56..bc4e11b`, lftp 5 súborov).
- **Logo:** `.nav__brand` presne `margin-top:-56px; margin-bottom:-16px` (ostatné nedotknuté).
- **Sticky collapse:** main.js scroll handler → `.nav.is-stuck` keď `scrollY > topbar.offsetHeight+4`; CSS `@media(min-width:769px){.nav.is-stuck .nav__brand-row{display:none}}` (mobil ponecháva hamburger v rade). Overené eval: scrolled→display:none, band visible, restored hore.
- **Dúha:** `.section__rainbow` presne `width:350px; margin-top:-132px; margin-bottom:-85px; margin-left:calc(50% - 225px); margin-right:auto; transform:rotate(-15deg)`. Zrušený `#galeria` scoping → **identická homepage aj /galeria** (overené eval na oboch, žiadny clipping; `.section--galeria{overflow:visible}`).
- **🔧 ROOT CAUSE lightboxu:** `main.min.js` robil `import("./gallery.js")` **bez `?v=`** → WebSupport edge cachuje holé asset URL → user dostával STARÝ gallery.js (staré šípky, žiadne miniatúry) hoci nový bol na serveri. Fix: `layout.php` vstrekuje `window.__kukoAssets={gallery:Asset::url('/assets/js/gallery.js'),map:…}` (Asset::url pridá `?v=<mtime>`), main.js importuje tie verzované URL. Overené na prode: main.min.js má `import(v.gallery||"./gallery.js")`, `gallery.js?v=` servíruje nový obsah (lightbox__thumb ×3).

Deploy: 5 súborov (`main.css/.min`, `main.js/.min` → web/; `layout.php` → private/). Žiadny DB seed. Prod==repo byte-identicky (4 statické overené diffom). Invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. Prod config nedotknutý. SFTP heslo `shred`. Suite 344 zelená. Žiadne otvorené závislosti.

---

## ✅ Header-jank + Reservation-system overhaul — NASADENÉ (2026-05-16, commits 04ac700 + f21c691)

Push `fb915d5..f21c691`, lftp 10 súborov (7 web/ assets + Availability.php, expire-pending.php, reservation.php). Žiadny DB seed/migrácia.
- **04ac700 header-jank:** sticky-collapse riadený `IntersectionObserver(topbar)` namiesto scrollY prahu → koniec oscilácie/„skákania". Overené dev: docHeight konštantná pri scrolle, jeden čistý prechod, mobil zachováva hamburger. Prod main.min.js má IntersectionObserver.
- **f21c691 rezervácia (decízie usera):**
  - **#3 fix:** `Availability` — žiadny package neblokuje celý deň; každá rezervácia blokuje len svoj čas+buffer a ten čas je nedostupný pre hocijaký balíček (blocks_full_day logika odstránená; blocked_full_day ostáva len pre admin all-day blocked_period). Overené cez prod-identický dev API: mini@14:00 → deň ostáva bookovateľný (09:00–11:30, 16:30–18:00), month=available; cross-package overené.
  - Pending staršie ako 1 mesiac neblokujú slot (availability filter `created_at >=` cutoff) + nový cron `private/cron/expire-pending.php` (pending→cancelled).
  - Nový krok 4 „Zhrnutie" (kompletný prehľad pred odoslaním) → thank-you (success krok zachovaný).
  - Indikátory krokov klikateľné: späť vždy, vpred len ak splnené podmienky (role=button, klávesnica, aria-disabled).
  - Povinný GDPR checkbox (link /ochrana-udajov) gate-uje submit.
  - Kalendár/„späť" šípky = vycentrované SVG (boli textové glyfy); zrušený auto-výber 14:00.
- 7 statických assetov prod==repo byte-identicky (vrátane rezervacia.min.js s novou wizard logikou). Invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. SFTP heslo shred. Suite **350 testov** zelená.

**⚠️ OWNER krok (manuál na WebSupporte):** zaregistrovať nový cron, napr. denne:
`/usr/bin/php /data/.../kuko-detskysvet.sk/private/cron/expire-pending.php`
(rovnako ako existujúce retention.php / db-backup.php). Bez neho funguje len availability-filter safety-net (slot sa uvoľní v zobrazení, ale pending zostane v DB ako pending).

---

## ✅ Rezervácia: thank-you / custom validation / sr-only + README+CLAUDE — NASADENÉ (2026-05-16, commit 8169919)

Push `17f0f79..8169919`, lftp 5 súborov (reservation.php → private/, rezervacia.{css,min.css,js,min.js} → web/). Bez DB seed/migrácie. README.md/CLAUDE.md = repo docs (nenasadené).
- **T1 thank-you fix:** odstránené `location.hash`/`popstate` krokové smerovanie — popstate na `#hotovo` hash spadol do reset-na-krok-1 vetvy, takže success/poďakovacia stránka sa nikdy nezobrazila. Nav pokrývajú Späť tlačidlá + klikateľné indikátory. Dev-overené: submit → „Ďakujeme!" sa zobrazí a ostane (screenshot).
- **T2 custom validácia:** nahradené natívne `reportValidity()` bubliny vlastnou inline validáciou (`.field__error`, `aria-invalid`, `.has-error`, `aria-describedby`, SK hlášky, focus prvého chybného, live-clear). Dev-overené.
- **T5 sr-only:** `.sr-only` doplnené do `rezervacia.css` (stránka používa layout-minimal bez main.css) → `calendar-announcer` je vizuálne skrytý (ostáva v a11y strome). Dev-overené (1×1 clip).
- README.md + CLAUDE.md vytvorené.
- 4 statické assety prod==repo byte-identicky; invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. SFTP heslo shred. Suite **353 testov** zelená (+3 regresné T1/T2/T5).

(Pozn.: stále platí owner krok zaregistrovať cron `expire-pending.php` — viď DEPLOY.md §11.)

---

## ✅ Thank-you redesign + mobile design pass — NASADENÉ (2026-05-18, commits 5e33ec8/f17ece9/617fc78)

Push `c9d638b..617fc78`, lftp 9 súborov (nav.php/reservation.php/kontakt.php → private/, main+rezervacia .css/.min.css/.js/.min.js → web/). Bez DB seed/migrácie.
- **5e33ec8** thank-you redizajn: nový text „Ďakujeme za rezerváciu!" + vrelý podtext, väčšia 🎉 ikona v pastelovom krúžku, panel; len 2 buttony vedľa seba (Google kalendár + Späť na domov), .ics odstránené (aj ICS v JS); header (brand+kroky) skrytý na success (`.rezervacia.is-finished`).
- **f17ece9** mobil header: topbar (mail/tel/social) presunutý do hamburger panelu (`#primary-nav .nav__contact`), logo vľavo+menšie, ružový okrúhly hamburger → X; opravený 1px skip-link prúžok (top -44→-60px) a uppercase v kontakt linkoch.
- **617fc78** mobil batch 2: otvorené menu `position:absolute` (prekrýva obsah, neposúva), social ikony okrúhle 38px, logo 52→57px; Cenník foto nalepené na box (gap 0), balíčky single-col gap `--s-10`, Fotogaléria 2 stĺpce na mobile, Kontakt „Sledujte nás:" 1 riadok + menšie ikony, footer menu tesnejšie medzery.
- 6 statických assetov prod==repo byte-identicky; invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. SFTP heslo shred. Suite **363 testov** zelená (+MobileHeaderTest/MobileSectionsTest/AddToCalendarTest aktualizované/pridané). Owner cron `expire-pending.php` stále čaká na registráciu (DEPLOY.md §11).

---

## ✅ Veľký admin/UX batch — NASADENÉ (2026-05-19, commits 40675a8…b51034c)

Push `617fc78..b51034c` (15 commitov), lftp **47 súborov** (40 web/ + 7 private/ → vrátane novej `private/lib/{MailContent,CalendarLink}.php`, mail partialov a `_setup.php` na seed). Poradie kód → migrate (6× skip, žiadna nová) → seed.

**Seed (idempotentný, len pridal):** `+ block cookies.body`, `+ setting seo.cookies.title`, `+ setting seo.cookies.description`; všetko ostatné `= skip` (existujúce hodnoty zachované — vrátane `privacy.body`).

Obsah batchu:
- **Cookies:** /zasady-cookies stránka + granulárny consent banner + nastavenia v samostatnom modali; reCAPTCHA badge skrytý + povinná Google atribúcia; privacy §5 skrátené + cross-link.
- **Rezervácia (frontend):** homepage badge ikony na balíčkoch, auto-scroll na časy po kliku na deň, „Počet detí" presunutý pod meno/tel/e-mail.
- **Admin Stránky:** akordeóny pre obsah, helper texty → tooltipy, FAQ ↑/↓/delete SVG ikon-tlačidlá, WYSIWYG ⇄ HTML prepínač, galéria upratané, Blokácie inputy 50 %.
- **SEO:** per-page OG obrázok upload + Google-style náhľad (miniatúra vľavo, text vpravo); fallback = predvolený OG cover; nový `og-cover.jpg` z aktuálneho loga; tooltipy k počítadlám znakov.
- **Admin kalendár:** klik na deň → zoznam rezervácií pod kalendárom.
- **E-maily:** predmet + hlavný text editovateľné per typ (`/admin/emails`) so serverovým náhľadom celého e-mailu; každý e-mail vždy obsahuje kompletné dáta rezervácie + brandovanú pätičku (logo, kontakty) cez zdieľané `_details`/`_footer` partialy.
- **Admin menu:** iCal export odstránený (link + route); nové tlačidlo „Pridať do Google kalendára" na každej rezervácii; „Web ↗" prvá položka v dizajne menu; „Odhlásiť" v dizajne nav-itemu, zarovnaná dole.

7 kľúčových statických assetov prod==repo byte-identicky (vrátane `og-cover.jpg`, `cookie-consent.min.js`). Invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. `_setup.php` po seede zmazaný (delete → 200, následný request 503). Prod config NEPREPÍSANÝ (len čítaný do /tmp, shred). SFTP heslo shred. Suite **391 testov** zelená. Dočasný lokálny admin `kukodev` odstránený z `config/.htpasswd` (restore z /tmp/htpasswd.bak — gitignored, nikdy nešiel na prod). Owner cron `expire-pending.php` stále čaká na registráciu (DEPLOY.md §11).

**Pozn.:** `privacy.body` v prod DB ostáva v pôvodnom znení (seed je insert-only, neprepisuje existujúce bloky — chráni admin úpravy). Nové skrátené §5 s cross-linkom na /zasady-cookies sa prejaví až keď owner blok upraví cez /admin/pages (privacy), alebo na vyžiadanie.

---

## 🔜 Zmena domény: kuko-detskysvet.sk → kukodetskysvet.sk (PRED launchom)

**Časovanie:** sprav PRED go-live. Web je za maintenance + `noindex`, žiadne
indexované URL ani backlinky → žiadna SEO strata, žiadna 301 migrácia. Po
launchi by tá istá zmena znamenala redirect mapu + re-indexáciu.

**Architektúra:** URL sa odvodzujú z `Config::get('app.url')` (canonical,
hreflang, OG/Twitter, schema, sitemap.xml, robots.txt, odkazy v e-mailoch,
admin odkaz). Hlavná zmena = 1 hodnota v prod `config/config.php` (nie v gite).

### Kódový batch (Claude — pripraviť pred launchom)
- Zladiť hardcoded **fallbacky** `'https://kuko-detskysvet.sk'` na novú doménu
  (len fallback; funkčne nie kritické): `head.php`, `mail/_footer.{html,text}`,
  `mail/reservation_admin.{html,text}`, `admin/page-edit.php`, `admin/seo.php`.
- Zameniť hardcoded e-mail `info@kuko-detskysvet.sk` → `info@kukodetskysvet.sk`
  (ak sa mení aj mailbox): `head.php` (schema), `sections/kontakt.php`,
  `nav.php`, `pages/{privacy,cookies,reservation-status,maintenance}.php`,
  `mail/_footer.*`, `lib/Faq.php`, `scripts/seed-cms.php`, `config.example.php`,
  + aktualizovať príslušné testy (HtmlSanitizerExtended/Faq/Header/MobileHeader).
- Doménové zmienky v právnych textoch (seed-cms.php: privacy.body, cookies.body,
  footer.copyright) prepísať na novú doménu.
- Suite zelená + build-assets + commit; deploy v rámci bežnej mechaniky.

### DB obsah (owner — seed NEPREPÍŠE existujúce bloky)
Po deployi upraviť v admine (alebo cielený DB update):
- `/admin/contact` → e-mail (a skontrolovať telefón/adresu/hodiny).
- `/admin/pages` → *Ochrana údajov* a *Zásady cookies*: prepísať doménu/e-mail
  v texte; *footer copyright* ak obsahuje doménu.

### Owner / infra (WebSupport + DNS — mimo kódu)
1. Registrácia `kukodetskysvet.sk`; DNS na WebSupport; pridať doménu/alias.
2. **SSL** certifikát pre novú doménu (Let's Encrypt v paneli).
3. Nový **mailbox** `info@kukodetskysvet.sk` + SMTP údaje → prod `config.php`
   (`mail.user/pass/from_email/admin_to`).
4. **reCAPTCHA**: pridať novú doménu k existujúcemu kľúču (alebo nový kľúč) →
   `recaptcha.site_key/secret_key` v configu.
5. Prod `config/config.php` → `app.url = https://kukodetskysvet.sk`.
6. Rozhodnúť: stará doména **301 → nová** (zachovať nasmerovanú), alebo opustiť.

---

## ✅ GO-LIVE — presný owner checklist (poradie)

Predpoklad: kódový stav je nasadený (HEAD na prod), suite zelená, web za 503.

**A. Doména (ak sa mení — sprav pred zvyškom)**
1. Owner: registrácia domény + DNS + alias + SSL na WebSupporte.
2. Owner: nový mailbox + SMTP heslo.
3. Claude: kódový batch (fallbacky + e-mail/legal literály) → deploy.
4. Owner: prod `config/config.php` → `app.url` + `mail.*` + `recaptcha.*` nové.
5. Owner: v `/admin` upraviť DB obsah (kontakt e-mail, privacy/cookies texty).

**B. Funkčné predpoklady**
6. Owner: mailbox `info@…` vytvorený; `mail.*` v prod configu vyplnené
   (`smtp.websupport.sk`, port 465, ssl, user, pass).
7. Owner: reCAPTCHA kľúče pre (novú) doménu v configu; `recaptcha.min_score=0.5`.
8. Owner (WebSupport → Cron) zaregistrovať (cesta:
   `/data/6/b/6b8003ed-75ba-4200-a84c-84c39b8a754e/kuko-detskysvet.sk`):
   - denne `…/private/cron/expire-pending.php`
   - mesačne `…/private/cron/retention.php`
   - týždenne `…/private/cron/db-backup.php`

**C. Overenie pred otvorením (web stále za 503)**
9. Test rezervácie end-to-end na prod: odoslať rezerváciu → prísť admin aj
   zákaznícky e-mail (over SMTP aj reCAPTCHA skóre); v `/admin` zmeniť status
   → prísť potvrdzovací/zrušovací e-mail. Skontrolovať pätičku/údaje v e-maile.
10. `/admin/emails` — finálne texty; `/admin/contact` — kontakty; SEO tituly/
    popisy/OG obrázky per stránka v `/admin/pages`.
11. Lighthouse (mobil) na kľúčových stránkach; opraviť prípadné regresie.

**D. Spustenie (ireverzibilné — verejnosť + Google)**
12. `/admin/maintenance` → vypnúť údržbu (web prestane vracať 503).
13. `/admin/seo` (alebo Stránky) → zapnúť indexáciu (`robots` → `index,follow`,
    `robots.txt` → `Allow`, sitemap aktívna).
14. Overiť: `/` = 200, `robots.txt` = `Allow`, `sitemap.xml` = 200, náhodná
    stránka má správny canonical na (novú) doménu, OG obrázok sa načíta.

**E. Po spustení**
15. Google Search Console: pridať (novú) doménu, odoslať `sitemap.xml`.
16. Google Business Profile: web URL + NAP konzistentné s webom.
17. (Voliteľné) HSTS hlavička po overení, že HTTPS všade funguje.
18. Sledovať `private/logs/` (mail/rate/error) prvých pár dní.

---

## ✅ Header jank fix + transparent logo + mobile menu spacing — NASADENÉ (2026-05-20, commits 5b49b67…2d07fa7)

Push `b51034c..2d07fa7` (header batch + docs), lftp **7 súborov** (nav.php → private/, main.css/.min.css/.js/.min.js + logo.png/.webp → web/). Žiadne DB zmeny.

- **5b49b67/1ecf8fb** header bez janku, pure CSS: `.nav__band` (pink lišta) presunutá ako body-level sibling → `position:sticky; top:0` pinuje na celej stránke. Topbar + logo `.nav` v normálnom toku → odscrolujú (logo „sa skryje"). Žiadny JS observer, žiadny `display:none` na pinned prvku → výška dokumentu konštantná pri scrolle (overené `docHeight` stable na všetkých pozíciách). Mobile: `.nav { display:none }`, kompaktné logo + okrúhly hamburger v lište; menu dropdown pod lištou.
- **f06bb9d** transparentné logo (flood-fill bieleho pozadia, vnútorné biele plochy zachované) → `logo.png/.webp`; `.nav__brand--bar { margin:0 }` aby kompaktné logo nedostalo `-56px` od veľkého varianta a nevyšlo mimo obrazovku; e-mail/telefón v mobilnom menu vycentrované.
- **2d07fa7** kontaktné odkazy v mobilnom menu zbavené dedeného `.nav__menu a { padding; border-bottom }` → medzera e-mail/telefón = len `gap` (10 px), žiadny veľký priestor nad telefónom.

6 statických assetov prod==repo byte-identicky (vrátane nového transparentného `logo.png/.webp` a `main.min.css/.min.js`). Invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. SFTP heslo shred. Suite **391 testov** zelená (regresné testy prepísané na nový pure-CSS sticky model). Owner cron `expire-pending.php` stále čaká na registráciu (DEPLOY.md §11). Maintenance/indexácia nezmenené (pred-launch).

---

## ✅ O nás card__body fix + balíčky editovateľné per-field — NASADENÉ (2026-05-20, commits d863898 + d1fa362)

Push `2d07fa7..d1fa362`, lftp **5 súborov** (seed-cms.php + o-nas.php + oslavy.php → private/, main.css/.min.css → web/) + _setup.php pre seed. Po seede _setup.php zmazaný (delete → 200, request → 503).

- **d863898** *O nás karty:* `<p class="card__body">` → `<div class="card__body">` — admin editor (Quill) ukladá obsah ako `<p>...</p>`, vnútorný `<p>` zatváral vonkajší a vznikalo prázdne `.card__body` + osamotený paragraf → divné medzery. Pridané `.card__body > p { margin: 0 }`. Regression test `OnasCardsTest::testCardBodyIsDivNotParagraph`.
- **d1fa362** *Balíčky editovateľné per-field:* zrušený all-or-nothing `$hasExtended` gate, ktorý padal späť na verbatim hardcoded HTML ak ktorékoľvek zo 4 extended polí bolo prázdne (a v migrácii 002 boli NULL → admin úpravy sa neukazovali). Refaktor: jednotný render path s per-package `$defaults` a `$pick()` per-field fallback; description ako `<div>` (rovnaký editor-`<p>` fix); idempotentný packages seed v `seed-cms.php` (`+ package mini/maxi/closed` na čistom DB, `= skip already filled` na opakovanom behu). Regression testy `OslavyCardsTest::testPerFieldFallbackNotAllOrNothingGate` + `testPackageDescIsDivNotParagraph`.

Seed na prode: `+ package mini/maxi/closed` (všetky 6 extended polí naplnené defaultmi — admin úpravy ak sú už v DB zachované insert-where-empty sémantikou).

Invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200, `_setup.php` zmazaný (`?action=path` → 503). 2 statické assety prod==repo byte-identicky. SFTP heslo + tmp config shred. Suite **394 testov** zelená. Maintenance/indexácia nezmenené (pred-launch).

---

## ✅ Indexácia admin záložka + card padding/shadow — NASADENÉ (2026-05-20, commits 09736f2 + 0eeaf83)

Push `d1fa362..0eeaf83`, lftp **5 súborov** (indexing.php + layout.php + admin/index.php → private/, main.css/.min.css → web/). Žiadne DB zmeny.

- **09736f2** *Indexácia* — `seo.public_indexing` mal route + storage, ale prepínač bol osirelý (žiadny GET formulár). Nová samostatná stránka `/admin/indexing` v *Nastaveniach* (vlastný tab vedľa Maintenance), checkbox „Povoliť indexáciu vyhľadávačmi" so stavovým bannerom + confirm pri prepnutí. Maintenance a Indexácia ostávajú **úplne nezávislé prepínače**, owner riadi každý zvlášť.
- **0eeaf83** *.card padding-bottom = 3rem* (kvôli straddle „Rezervovať oslavu") + jemný pokojový tieň `0 3px 10px rgba(216,139,190,0.25)` na všetkých `.btn--straddle` (Rezervovať oslavu + 3× Rezervovať balíček). Hover tieň z `.btn:hover` zostáva.

Invarianty: public `/`=503, robots `Disallow:/`, /admin/login=200, sitemap=200. 2 statické assety prod==repo byte-identicky. SFTP heslo shred. Suite **394 testov** zelená. Maintenance/indexácia nezmenené (pred-launch).

---

## ✅ Microinteractions pass — NASADENÉ (2026-05-22, commit 45a3689)

Push `0eeaf83..45a3689`, lftp **6 súborov** (main.css/.min.css + rezervacia.css/.min.css/.js/.min.js → web/). Žiadne DB zmeny, žiadne template zmeny.

Pure-CSS hover/focus/active polish (+2 riadky JS pre rezervačný spinner toggle):

- **Karty O nás + balíčky:** translateY(-4px) + jemný tieň pri hover (gated `@media (hover: hover)`).
- **Desktop top nav + footer menu:** animovaný „underline-grow" cez `::after` pseudo-element (`@media (min-width: 769px)`).
- **Galéria:** prepracované — zoom **iba vnútorného `<img>`** (`transform: scale(1.08)`), dlaždica si drží presnú šírku/výšku (overené 188×141 px konštantne); container `overflow: hidden` orezáva zväčšený obrázok → grid sa neposúva.
- **Tlačidlá:** `.btn:active { transform: scale(.98) }` taktilné stlačenie (vrátane `.btn--straddle` a rezervačného „Odoslať").
- **Sociálne ikony:** scale(1.10) pop pri hover — topbar, mobilný hamburger panel, kontakt karta „Sledujte nás".
- **Package badge:** mikro-rotácia (-6deg) + scale(1.06) pri hover karty balíčka.
- **Text inputy:** focus tint na `--bg-pink-soft` (pridáva sa k existujúcemu focus-visible outline; oba CSS súbory).
- **Cookie banner:** fade-up animation keď sa zobrazí (`.cookie-banner:not([hidden])` keyframes).
- **Rezervačný submit:** inline spinner pseudo-element počas `.is-loading` (JS toggle); rešpektuje reduced-motion.

Bezpečnosť: každý hover gated `@media (hover: hover)` (touch sa nezasekne), iba `transform`+`opacity` (GPU-kompozícia), globálne `prefers-reduced-motion: reduce` vypína všetko.

4 statické assety prod==repo byte-identicky (vrátane min variantov; main.min.css po krátkej CDN race overené v 3 retries). SFTP heslo shred. Suite **394 testov** zelená.

**Pozn.:** verejné `/` práve vracia **200** (nie 503) — owner medzičasom manuálne vypol Maintenance cez `/admin/maintenance` pre vlastnú live ukážku. `robots.txt` ostáva `Disallow: /` → Indexácia OFF, web sa nedostane do Google (toto sú samostatné prepínače od commitu 09736f2). `/admin/login`=200, `sitemap.xml`=200.

---

## ✅ Dynamický /llms.txt + docs — NASADENÉ (2026-05-22, commit 1ebfb49)

Push `45a3689..1ebfb49`, lftp **3 súbory** (`LlmsTxt.php` nový + `Maintenance.php` bypass → private/, `index.php` route + robots ad → web/). Žiadne DB zmeny.

- **`/llms.txt`** generovaný cez `\Kuko\LlmsTxt::render($db)` z tých istých zdrojov ako homepage (`Content::get` + `PackagesRepo`). Fallbacky byte-identické so seed-cms.php + oslavy.php $defaults (triple source of truth — popísané v CLAUDE.md). Pri výpadku DB padá na defaulty bez fatálu.
- Gated by `seo.public_indexing` — OFF → HTTP 404 + `Not Found\n` (overené); ON → plný Markdown s aktuálnymi admin dátami.
- `Maintenance::shouldBypass()` rozšírený o `/llms.txt` (rovnaký režim ako robots/sitemap — crawler musí vidieť SEO direktívy aj počas údržby).
- `robots.txt` keď je Indexácia ON pridáva `LLM-Content: <base>/llms.txt` riadok — crawler tak nájde brief.
- Docs: CLAUDE.md (triple source of truth pre balíčky, LlmsTxt konvencia, Maintenance vs Indexácia separation, „nikdy `<p>` okolo `Content::get` HTML" pravidlo), README.md (nová „SEO + AI crawlers" sekcia, aktualizovaný admin/lib zoznam), DEPLOY.md (smoke-test sekcia pre crawl súbory, vyjasnenie že robots/sitemap/llms sú dynamické routy).

Overené na prode: `/llms.txt` → 404 (Indexácia OFF), `robots.txt` → `Disallow: /`, `/admin/login`=200, `sitemap.xml`=200. SFTP heslo shred. Suite **398 testov** zelená (+4 LlmsTxtTest).

**Po flipnutí Indexácie ON (`/admin/indexing`) sa `/llms.txt` automaticky aktivuje** s aktuálnym Markdown briefom z DB — žiadny build, žiadny re-deploy.

---

## ✅ Domain flip — kukodetskysvet.sk LIVE (2026-06-04, commit 7349612)

Migrácia z `kuko-detskysvet.sk` na `kukodetskysvet.sk` (bez pomlčky).

**Owner urobil:** DNS + SSL na novej doméne, alias na hostingu, mailbox `info@kukodetskysvet.sk`, reCAPTCHA pridanie domény, **nová MySQL DB s premigrovanými dátami zo starej** (content_blocks, packages, settings, gallery_photos, reservations), upravil prod `config/config.php` (app.url + db.* + mail.* na nové).

**Deploy:** novy SFTP host=`kukodetskysvet.sk`, user=`filip.kukodetskysvet.sk`. Na novom účte bol `web/` mirror zo starého (≤ commit 1ebfb49), ale **`private/` chýbal** → app by 500-l. Riešenie: **full mirror `private/`** (`lftp mirror -R --exclude=^tests/ --exclude=^logs/ --exclude-glob=phpunit.phar`, 115 súborov) + **put `public/admin/index.php`** (jediný web/ file zmenený medzi `1ebfb49..7349612`). Plný mirror je bezpečný keď je cieľ prázdny — `mirror --only-newer` warning sa týkal incremental diff deployov, nie inicializácie.

**Overené z novej domény:**
- `https://kukodetskysvet.sk/` = 200 (maintenance vypnutá v novej DB)
- `https://kukodetskysvet.sk/robots.txt` = `Disallow: /` (Indexácia OFF, pred-launch)
- `https://kukodetskysvet.sk/llms.txt` = 404 (Indexácia OFF)
- `https://kukodetskysvet.sk/admin/login` = 200
- `https://kukodetskysvet.sk/sitemap.xml` = 200
- 7 kľúčových assetov prod==repo byte-identicky (main.css/.min.css, admin.min.css, main.min.js, rezervacia.min.js, og-cover.jpg, logo.png)

**Žiadne DB zmeny pri deployi** — dáta sú už v novej DB z migrácie. Seed neutrálne by inserter chýbajúce content_blocks, ale netreba spúšťať (DB úplná).

**Pozn.:** content_blocks z migrovanej DB stále obsahujú starú doménu/e-mail v `kontakt.email`, `privacy.body`, `cookies.body`, `footer.copyright`, prípadne `mail.<typ>.intro` settings — owner doplní cez `/admin/contact`, `/admin/pages`, `/admin/emails` (insert-only seed by ich neprepísal). Toto je obsahový krok, nie kódový.

SFTP heslo shred. Suite **398 testov** zelená pred deployom. Stará doména `kuko-detskysvet.sk` zatiaľ stále beží — owner sa rozhodne (301 redirect na novú, alebo nechať vypršať).

---

## ✅ Post-domain-flip DB cleanup + helper — NASADENÉ (2026-06-04, commit pending)

Po doménovej migrácii ostávali v premigrovanej DB texty so starou doménou v viacerých blokoch (legal stránky, FAQ, footer, SEO popisy). Riešenie: nový **`?action=fix-domain`** v `public/_setup.php` — token-gated bulk REPLACE pre `content_blocks.value` aj `settings.value`. Idempotentný (druhý beh = `= nothing to fix`). Defaultné mapovanie `kuko-detskysvet.sk → kukodetskysvet.sk` + `KUKO-detskysvet.sk → KUKOdetskysvet.sk`; podporuje `?old=&new=&oldT=&newT=` override pre budúce premenovania.

**Zmeny v DB na prode (8):**
- `content_blocks.cookies.body`, `faq.items`, `kontakt.email`, `privacy.body`, `footer.copyright`
- `settings.faq.items`, `seo.cookies.description`, `seo.privacy.description`

Druhý beh: `= nothing to fix` (idempotent). `_setup.php` zmazaný (`?action=delete` → 200; následný request → 404).

**HSTS:** už aktívne v repo `.htaccess` (preložené z roll-outu pred-launch); overené headers na novej doméne: `strict-transport-security: max-age=31536000; includeSubDomains; preload` + CSP + X-Content-Type-Options + X-Frame-Options + Referrer-Policy.

Suite **398 testov** zelená.

**Zostávajúce vlastnícke kroky:** funkčné testy (testovacia rezervácia → e-maily fungujú), cron registrácia na novom hostingu (`expire-pending.php`, `retention.php`, `db-backup.php`), `/admin/indexing` ON pre go-live, Google Search Console + Business Profile, rozhodnutie o starej doméne (301 redirect alebo nechať vypršať).

---

## 2026-07-14 — Bezpečnostný + UX audit admin časti (remediation)

Kompletná náprava oboch auditov (bezpečnosť + admin UI/UX). **Nenasadené** — čaká na „go".

**Bezpečnosť:**
- `_setup.php` odstránený z repo → presunuté za admin login: `/admin/tools` (`\Kuko\DeployTools`, POST+CSRF, žiadny token v URL). Migrácie/Seed/Smoke/Náhrada textu.
- Remember-me cookie: `iat` v HMAC podpise (`user|iat|sig`) → starý/ukradnutý cookie neplatí server-side; `session_regenerate_id` pri obnove.
- Admin session timeout: idle 8h / absolút 24h (`Auth`, config `admin.idle_timeout`/`absolute_timeout`).
- `LoginThrottle`: username lock zúžený na (username+IP) → koniec account-lockout DoS.
- Maintenance: heslo hashované (`password_hash`/`verify`, aj v seede) + rate-limit na `/maintenance` POST.
- `\Kuko\ClientIp` (config `security.trust_proxy`) pre IP za proxy; admin DB chyba generická; audit `update_settings` loguje whitelist (nie CSRF token); `display_errors` vždy off v prod.
- **CSP s nonce** (`\Kuko\Csp`, emitované z PHP; `.htaccess` CSP odstránené): `script-src` bez `unsafe-inline`, inline `<script>` majú nonce, inline `on*` handlery → `admin.js` + `data-*`.

**UX:**
- Doplnené chýbajúce CSS triedy: `.admin-banner*` (Maintenance/Indexácia status box), `.admin-table-wrap` (mobil scroll, obalené tabuľky), `.admin-counter--over`, `.admin-muted`, `.admin-link`.
- Zoznam rezervácií: vyhľadávanie (meno/tel/e-mail) + stránkovanie + počítadlo (`ReservationRepo::count()`/`q`).
- IA: premenované „Nastavenia" → **„Web & systém"** (top-nav) a **„Pravidlá rezervácií"** (tab); pridaný tab „Nástroje".
- Detail: upozornenie na e-mail pri zmene statusu + podmienené pole „Dôvod zrušenia"; inline štýly → triedy.
- Kalendár: SK názvy mesiacov + mobilný scroll. Flash správy: × + auto-dismiss.

**Vizuálne overené** na dev serveri (login, zoznam+hľadanie, bannery, Nástroje POST flow, detail toggle, kalendár, verejná homepage) — 0 CSP porušení, CSP hlavička má nonce a bez `unsafe-inline`.

Suite **409 testov** zelená (+11). Pridané: `CspTest`, `ClientIpTest`, rozšírené `LoginThrottleTest`/`MaintenanceSettingsTest`/`ReservationRepoTest`.

**Vlastnícky krok pred deployom:** reCAPTCHA v3 na `/rezervacia` smoke-test s ostrým kľúčom (nedá sa overiť lokálne — dev nemá secret). Ak by nový CSP blokoval reCAPTCHA, fallback = pridať `'unsafe-inline'` len do public `script-src` v `\Kuko\Csp::policy('public')`.

---

## 2026-07-21 — Celkový audit + remediácia (6 domén)

Audit (security, a11y, SEO/AEO, performance, code/funkcie, UI/UX) + implementácia
v 6 dávkach. Suite **435 testov** zelená (baseline 409 → +26). Owner rozhodnutia
viď `memory/full-audit-2026-07-21.md`. Vizuálne overené na dev serveri.

**Batch 1 — correctness:** `blocks_full_day` teraz reálne blokuje celý deň pre
`closed` balíček (reason `reserved_full_day`); validácia blokovaných období
(celý deň / rozsah od<do); atomický presun termínu (transakcia+rollback);
GDPR retencia viazaná na `wished_date` (nie `created_at`).

**Batch 2 — bezpečnosť (defense-in-depth):** remember-me cookie sa invaliduje
zmenou hesla (fingerprint bcrypt hashu v HMAC); fail-closed pri prázdnom
`auth.secret`/maintenance kľúči; `ClientIp` berie pravý XFF hop za proxy;
logout len POST+CSRF; `App::isHttps()` (X-Forwarded-Proto) pre session cookie
Secure vo všetkých API; LIKE wildcard escape (`ESCAPE '!'`); `strip_tags` na
`text` content bloky.

**Batch 3 — výkon:** `Db::fromConfig()` per-request singleton (~5 spojení → 1);
Leaflet CSS/preconnect len na homepage; podmienený import gallery/map;
schema.org `image[]` → webp. **Obrázky 24 MB → 1,86 MB** (`.jpg` súbory boli
mislabeled PNG → reálny progresívny JPEG q82); `Inter.ttf` (803 KB) odstránený.
Odložené (chýba tooling): woff2 subset fontov (návod: pyftsubset latin+latin-ext+SK).
`img/galeria_N.*` root set je statický fallback (nie duplicita na zmazanie).

**Batch 4 — a11y + kontrast:** brand `--c-accent` `#D88BBE` → `#A8478A`
(WCAG AA: biely-na-ňom 5,34:1, na-kréme 5,10:1); admin `--c-accent-dark`
`#8E3A74`; sivé `#7A7A7A`/`#aaa` → `#6A6A6A`; no-js reveal gating (`html.js`
+ nonce script); lightbox `role=dialog`+focus-trap; fokus pri prechode krokov;
mobilné menu aria-label toggle+Esc; `scope="row"` v tabuľkách; focus-visible
do `rezervacia.css`. (Slot šípková navigácia odložená — funguje ako tab-stopy.)

**Batch 5 — SEO/AEO:** zdieľané `_head-social.php` + `_head-schema.php` partialy
(rezervačná stránka má teraz OG/Twitter/ikony/JSON-LD — jeden zdroj pravdy);
`CafeOrCoffeeShop` typ; `hasOfferCatalog` (DB-driven z price_text); priceRange
`€€`; sitemap `lastmod` = max(content_blocks.updated_at); `/llms.txt` inline FAQ;
404 meta description.

**Batch 6 — UX:** rezervačný empty-state (nula balíčkov); hint pri plnom mesiaci;
mobilný názov aktívneho kroku; „Potrebujete pomoc?" footer vo wizarde;
add-to-calendar `ctz=Europe/Bratislava` (DST-safe); nav poradie (cenník pred
oslavy) + „Rezervovať" CTA + „Časté otázky" v menu (aj footer); referencia
rezervácie + „sledovať stav" na success (API vracia `view_token`); admin status
`data-confirm` (upozornenie na e-mail); admin galéria ↑/↓ reorder + feedback;
tools náhrada `{old}→{new}` echo v confirm + `required`. Bonus: oddelený
try/catch pre admin vs zákaznícky mail v `api/reservation.php`.

**Pozn. pre prod deploy:** seed je insert-only → texty „v 3 krokoch"→„v 4 krokoch"
a FAQ veta na existujúcej prod DB sa zmenia cez `/admin/tools` → *Hromadná
náhrada textu* (napr. `v 3 krokoch` → `v 4 krokoch`). Go-live config potvrdiť:
`auth.secret` neprázdny, `recaptcha.secret_key`, `security.trust_proxy`.
