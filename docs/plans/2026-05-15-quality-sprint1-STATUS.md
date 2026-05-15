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
