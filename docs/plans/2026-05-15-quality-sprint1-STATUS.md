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

## Owner action items (NEBUDUJEME — nahlásiť userovi na konci Sprintu 1)
1. P1 Lighthouse baseline (owner spustí v Chrome, screenshoty do `docs/audits/`)
2. A1 axe DevTools scan (owner spustí rozšírenie)
3. S3 Google Business Profile (owner vytvorí/overí)
4. B2 HSTS preload registrácia na hstspreload.org (až keď je HSTS stabilné v prod)

## Po Sprinte 1
Roadmap hovorí: po týchto 8 → prepnúť `maintenance` flag false + `public_indexing` true. Potom pokračovať ďalšími sprintami zvyšku `roadmap-quality.md` (SEO obsah, performance minifikácia/critical CSS/font subsetting, zvyšok a11y, UX detaily vrátane U1 symetrický buffer). Po #3 nasleduje user priorita #1 (go-live prerekvizity: SMTP heslo, reCAPTCHA test).
