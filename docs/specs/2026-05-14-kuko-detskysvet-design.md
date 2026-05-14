# KUKO detský svet — Web Design Spec

- **Date:** 2026-05-14
- **Status:** Draft for approval
- **Owner:** Filip Lopatka
- **Domain:** kuko-detskysvet.sk
- **Hosting:** WebSupport (PHP 8.x, MySQL/MariaDB, Apache .htaccess)

## 1. Goal

Verejná prezentačná stránka detskej herne + kaviarne KUKO v Piešťanoch, ktorá:

1. Predstaví priestor, otváracie hodiny, cenník vstupu a polohu.
2. Umožní rodičom rezervovať jeden z troch balíčkov detskej oslavy formou online formulára. Rezervácia je „dopyt" — finálne potvrdenie urobí majiteľ telefonicky/e-mailom.
3. Vlastníkovi poskytne ľahký admin pre prehľad rezervácií a zmenu ich statusu.

## 2. Scope

**In scope:**

- Jednostránkový web s anchor sekciami: Hero / O nás / Cenník vstupu / Detské oslavy / Fotogaléria / Kde nás nájdete / Footer.
- Samostatná stránka *Ochrana osobných údajov* (linkovaná z footera a cookie banneru).
- Modal pre rezerváciu oslavy (3 balíčky), validácia, reCAPTCHA v3, odoslanie 2 e-mailov (admin + autoreply zákazníkovi), zápis do DB.
- Admin panel `/admin/` chránený HTTP Basic Auth: zoznam rezervácií, filter (status, balíček, dátum), detail, zmena statusu (`pending` / `confirmed` / `cancelled`).
- Cookie consent banner — bez súhlasu sa nenahrá reCAPTCHA ani sa nedá odoslať formulár.
- Leaflet + OpenStreetMap mapa s markerom na Bratislavská 141, Piešťany.
- Lightbox fotogaléria (vanilla JS).
- SEO meta + Open Graph + Schema.org LocalBusiness, `sitemap.xml`, `robots.txt`.
- Responsívnosť (desktop + tablet + mobil), a11y (semantika, ARIA, klávesnica, kontrast WCAG AA).
- WebP konverzia obrázkov pri deploy-i (raz, manuálny `imagemin`/`cwebp` skript, žiadny runtime build).
- Scroll-reveal animácie (jemné fade-up cez `IntersectionObserver`).

**Out of scope (now):**

- E-commerce / platby online.
- Online rezervácia bežného vstupu do herne (walk-in).
- Kalendárový picker s real-time dostupnosťou (rezervácia je dopyt, nie locked slot).
- SMS notifikácie.
- Viacjazyčnosť (len SK).
- Public-facing CMS (obsah edituje vývojár v HTML/PHP).
- Klientsky účet (login pre rodičov).

## 3. Tech stack a non-functional

- **PHP 8.1+** (typed properties, enums, `readonly`).
- **MySQL 8 / MariaDB 10.6+** (utf8mb4).
- **Vanilla HTML/CSS/JS**, žiadny build pipeline. CSS rozdelený do `main.css` (verejné) + `admin.css` (admin). JS modulárny cez `<script type="module">`.
- **PHPMailer** (jediná závislosť, vendor-ujeme priamo do `private/lib/phpmailer/` bez Composeru — WebSupport nemusí mať Composer dostupný v shell-i).
- **Leaflet** + **OpenStreetMap** tiles (CDN load, žiadne API key, žiadne tracking).
- **Google reCAPTCHA v3** (invisible scoring, threshold ≥ 0.5).
- **Bez frameworkov** (žiadny React/Vue/jQuery).

Non-functional požiadavky:

- HTTPS forced na produkcii (po overení SSL).
- Stránka má LCP < 2.5s na mobile (LTE), CLS < 0.1.
- Žiadny verejný endpoint nesmie blokovať dlhšie ako 2s.
- Logy v `private/logs/` (PHP error log + mail audit + admin actions). Rotácia mesačná, retencia 6 mesiacov.

## 4. Adresárová štruktúra

```
kuko-detskysvet/
├── config/
│   ├── config.example.php       # už existuje
│   └── config.php               # gitignored, produkčné hodnoty
├── private/                     # mimo webroot ideálne, alebo zablokované cez .htaccess
│   ├── lib/
│   │   ├── Config.php           # singleton loader
│   │   ├── Db.php               # PDO wrapper, prepared statements
│   │   ├── Mailer.php           # PHPMailer wrapper (SMTP)
│   │   ├── Csrf.php             # token issue/verify (session-backed)
│   │   ├── RateLimit.php        # IP-based, file-store
│   │   ├── Recaptcha.php        # v3 verify volanie
│   │   ├── Reservation.php      # model + validator
│   │   ├── Renderer.php         # micro template engine (include + escape helpers)
│   │   ├── Router.php           # path → handler mapping
│   │   ├── Auth.php             # admin Basic Auth helper (čítanie REMOTE_USER)
│   │   ├── Logger.php           # PSR-3-like, file backend
│   │   └── phpmailer/           # vendored
│   ├── templates/
│   │   ├── layout.php           # <html>, <head>, <body> shell
│   │   ├── head.php             # meta, OG, Schema.org, fonts, CSS
│   │   ├── nav.php              # top nav (desktop + hamburger)
│   │   ├── footer.php
│   │   ├── sections/
│   │   │   ├── hero.php
│   │   │   ├── o-nas.php
│   │   │   ├── cennik.php
│   │   │   ├── oslavy.php       # 3 karty + modal
│   │   │   ├── galeria.php
│   │   │   └── kontakt.php      # mapa + kontakt karty
│   │   ├── cookie-banner.php
│   │   ├── pages/
│   │   │   ├── ochrana-udajov.php
│   │   │   └── 404.php
│   │   ├── admin/
│   │   │   ├── layout.php
│   │   │   ├── list.php
│   │   │   └── detail.php
│   │   └── mail/
│   │       ├── reservation_admin.html.php
│   │       ├── reservation_admin.text.php
│   │       ├── reservation_customer.html.php
│   │       └── reservation_customer.text.php
│   ├── migrations/
│   │   ├── run.php              # CLI: aplikuje pending migrácie
│   │   └── 001_init.sql
│   ├── cron/
│   │   └── .gitkeep             # zatiaľ prázdne
│   └── logs/                    # gitignored
├── public/                      # DocumentRoot
│   ├── .htaccess                # už existuje, front-controller + bezpečnosť
│   ├── index.php                # front controller (router)
│   ├── robots.txt
│   ├── sitemap.xml
│   ├── favicon.ico
│   ├── admin/
│   │   ├── .htaccess            # AuthType Basic
│   │   ├── .htpasswd            # gitignored
│   │   └── index.php            # bootstrap admin (dispatch cez Router)
│   ├── api/
│   │   ├── .htaccess            # POST-only, deny GET
│   │   └── reservation.php      # endpoint
│   └── assets/
│       ├── css/
│       │   ├── main.css
│       │   └── admin.css
│       ├── js/
│       │   ├── main.js          # nav, smooth scroll, scroll reveal, cookies
│       │   ├── reservation.js   # modal + form + reCAPTCHA load
│       │   ├── gallery.js       # lightbox
│       │   └── map.js           # leaflet init
│       ├── img/                 # WebP + fallback PNG
│       ├── icons/               # SVGs (už existujú)
│       └── fonts/               # už existujú
├── assets/                      # zdrojové assets od dizajnéra (PNG, SVG, fonty)
├── screenshots/                 # zdrojové referenčné screenshoty
└── docs/
    └── specs/
        └── 2026-05-14-kuko-detskysvet-design.md
```

## 5. Routing

Front controller `public/index.php` parsuje `REQUEST_URI` a dispatch-uje:

| Path                              | Method | Handler                          |
|-----------------------------------|--------|----------------------------------|
| `/`                               | GET    | render homepage                  |
| `/ochrana-udajov`                 | GET    | render privacy page              |
| `/api/reservation`                | POST   | `public/api/reservation.php`     |
| `/robots.txt`, `/sitemap.xml`     | GET    | served by Apache (static)        |
| `/assets/...`                     | GET    | served by Apache (static)        |
| 404                               | any    | render 404 page                  |

Admin panel má vlastný entry point `public/admin/index.php` s Basic Auth na úrovni Apache (`public/admin/.htaccess`). Vnútorné admin routy:

| Path                              | Method  | Akcia                            |
|-----------------------------------|---------|----------------------------------|
| `/admin/`                         | GET     | zoznam rezervácií                |
| `/admin/?status=pending`          | GET     | filter                           |
| `/admin/reservation/<id>`         | GET     | detail                           |
| `/admin/reservation/<id>/status`  | POST    | zmena statusu (CSRF chránené)    |

## 6. Frontend

### 6.1 Vizuálny štýl

**Farebná paleta** (extrahovaná zo screenshotov):

| Token              | Hex       | Použitie                                    |
|--------------------|-----------|---------------------------------------------|
| `--bg-cream`       | `#FFF8EE` | hlavné pozadie sekcií                       |
| `--bg-pink-soft`   | `#FBEEF5` | pozadie cenníka, nav baru                   |
| `--c-blue`         | `#9ED7E3` | karta „bezpečný priestor", balíček MINI     |
| `--c-peach`        | `#F8B49D` | karta „káva"                                |
| `--c-yellow`       | `#F7D87E` | karta „stretnutie", balíček MAXI je purple  |
| `--c-purple`       | `#C9A8E1` | karta „oslavy", balíček Uzavretá            |
| `--c-text`         | `#3D3D3D` | telo textu                                  |
| `--c-text-soft`    | `#7A7A7A` | sekundárny text                             |
| `--c-accent`       | `#D88BBE` | CTA tlačidlá (pinky-purple)                 |

**Typografia:**

- Headings: **Nunito Sans** (už máme v `assets/fonts/`) — weight 700 pre H1/H2, 600 pre H3.
- Body: **Nunito Sans** — weight 400, line-height 1.6.
- Logo „detský svet" script — embedded v PNG/SVG logu, nie webfont.
- Inter ako fallback (už máme TTF).
- Self-host fontov (`@font-face`), zabráni FOIT.

**Spacing & layout:**

- 8px grid (token `--s-1: 0.5rem` až `--s-12: 6rem`).
- Border-radius: karty `--r-card: 1.25rem` (~20px), tlačidlá `--r-btn: 999px` (pill).
- Container max-width 1200px, mobile padding 1rem, desktop 2rem.
- Vzdušné sekcie: padding-block `--s-10` (5rem) na desktope.

### 6.2 Sekcie

1. **Hero**
   - Background image `hero.png` s tmavým gradient overlay (rgba(0,0,0,0.25)).
   - Centrovaný nadpis „Detský svet KUKO" + podtitul „pre radosť detí & pohodu rodičov".
   - 2 CTA: `Rezervovať oslavu` (scrolluje na #oslavy + otvára modal po dolete), `Pozrieť cenník` (scroll na #cennik).
   - Nav bar nad hero: logo vľavo, menu (Domov, O detskom svete, Detské oslavy, Cenník služieb, Fotogaléria, Kontakt) vpravo. Telefón a e-mail v tenkom topbare nad navom.

2. **O nás**
   - Nadpis „O nás" + úvodný odsek (text zo screenshotu).
   - 4 karty v gride 4 stĺpce (desktop), 2×2 (tablet), 1 stĺpec (mobil). Každá má farebný outline (blue/peach/yellow/purple) + ikonu zo SVG sady (playground / coffee / friendship / balloons + little-kid).
   - 4. karta má CTA `Rezervovať oslavu`.

3. **Cenník vstupu**
   - 2-stĺpcový layout: fotka troch detí vľavo, biely card panel vpravo s nadpisom „Cenník" + 3 riadky cenníka (Dieťa do 1 roku ZADARMO, Dieťa od 1 roku 5,00 €/hod, Dieťa od 1 roku neobmedzene 15,00 €).
   - Mobile: stack pod seba.

4. **Detské oslavy** (Detské KUKO oslavy)
   - Nadpis + 3 karty balíčkov v gride (blue / purple / yellow).
   - Každá karta: ikona klobúk, názov balíčka, popis (text zo screenshotu), čas (Počet detí + Časový harmonogram s ikonkou hodín), cena, zoznam zahŕňa (✓ items), tlačidlo `REZERVOVAŤ BALÍČEK` (otvára modal s prednastaveným balíčkom).

5. **Fotogaléria**
   - Nadpis + krátky popis.
   - Grid 3×2 (desktop), 2×N (tablet), 1×N (mobil). Klik otvorí lightbox (vlastný, ~80 riadkov JS, klávesnica ←/→/ESC).
   - 6 fotiek v zdroji (zatiaľ máme 5, pridáme 6.; alebo grid 5 v rovnomernom layoute).
   - Lazy loading (`loading="lazy"`).

6. **Kde nás nájdete**
   - 2-stĺpcový layout: Leaflet mapa (centrovaná na 48.5916, 17.8364 = Piešťany, marker na presnej adrese), 4 kontakt karty vpravo:
     - 🏠 Navštívte náš Detský svet KUKO — Bratislavská 141, 921 01 Piešťany
     - 📞 Máte otázky? Kontaktujte nás — +421 915 319 934 | info@kuko-detskysvet.sk
     - ⏰ Otváracie hodiny — Pondelok – Nedeľa: 9:00 – 20:00
     - 🌐 Sledujte nás na sociálnych sieťach — FB + IG ikony
   - Marker je vlastná SVG ikona v farbe `--c-accent`.

7. **Footer**
   - Logo (Image_logo.png) centrovaný.
   - Tenký pinky-soft pás s nav linkmi (DOMOV, O DETSKOM SVETE, DETSKÉ OSLAVY, CENNÍK SLUŽIEB, FOTOGALÉRIA, KONTAKT).
   - Pod tým: copyright „Copyright © 2026 KUKO-detskysvet.sk | Všetky práva vyhradené." + drobný link na `/ochrana-udajov`.

### 6.3 Responsívnosť

- Breakpoints: `< 640` (mobile), `640–1024` (tablet), `> 1024` (desktop).
- Nav: pod 768px hamburger ikona, menu sa otvára full-screen overlay.
- Hero: pod 640px znížená výška, menší font.
- Galéria/o-nás karty: stackujú sa do 1/2 stĺpcov.
- Touch targets ≥ 44×44 px.

### 6.4 Accessibility

- Semantické tagy: `<header>`, `<nav>`, `<main>`, `<section>` s `aria-labelledby`, `<footer>`.
- SVG ikony majú `<title>` + `role="img"` alebo sú dekoratívne s `aria-hidden`.
- Modal: trap focus, `Esc` zatvára, `aria-modal="true"`, `aria-labelledby` na nadpis.
- Lightbox: rovnaké pravidlá.
- Cookie banner: `role="dialog"`, fokus na prvé tlačidlo pri zobrazení.
- Kontrast WCAG AA (overené po implementácii cez axe).
- `prefers-reduced-motion` rešpektované pre scroll-reveal animácie.

## 7. Backend

### 7.1 Konfigurácia

`config/config.example.php` zostáva ako šablóna. Produkčný `config/config.php` (gitignored) ho mirroruje s naplnenými hodnotami. Pridáme nové sekcie:

```php
'recaptcha' => [
    'site_key'   => '',
    'secret_key' => '',
    'min_score'  => 0.5,
],
'admin' => [
    // .htpasswd handles credentials; len UI metadata
    'session_lifetime' => 3600,
],
'security' => [
    'rate_limit_per_hour' => 3,
    'csrf_lifetime'       => 3600,
],
```

`private/lib/Config.php` načíta súbor a sprístupní cez `Config::get('db.host')`.

### 7.2 Dátový model

Migrácia `001_init.sql`:

```sql
CREATE TABLE reservations (
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

CREATE TABLE rate_limits (
    ip_hash    CHAR(64) NOT NULL,
    bucket     VARCHAR(40) NOT NULL,
    count      SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    window_at  DATETIME NOT NULL,
    PRIMARY KEY (ip_hash, bucket),
    INDEX idx_window (window_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE admin_actions (
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
```

`ip_hash` = `sha256(ip + secret)` — nikdy neukladáme raw IP (GDPR-friendly).

### 7.3 Rezervačný flow

1. Klient klikne na `REZERVOVAŤ BALÍČEK` v karte balíčka → JS otvorí modal s prednastavenou hodnotou `package`.
2. Modal obsahuje polia: `package` (radio prednastavený, required), `wished_date` (date input, min = dnes, required), `wished_time` (time input, step 30min, required), `kids_count` (number 1–50, required), `name` (text 2–120, required), `phone` (tel, SK regex, required), `email` (email RFC, required), `note` (textarea, max 1000, **optional** — UX dôvod, nie každý zákazník má dodatok).
3. Honeypot pole `<input name="website" type="text" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px">` (boti ho vyplnia).
4. CSRF token v skrytom poli, hodnota set-nutá pri renderovaní stránky cez `Csrf::token()`.
5. **Cookie consent kontrola:** ak nesúhlasil, submit button je `disabled` a nad ním sa zobrazí inline správa „Pre odoslanie rezervácie potvrďte cookies." s tlačidlom `Súhlasím`. Po súhlase sa načíta reCAPTCHA v3 skript a button sa odomkne.
6. Submit → JS zavolá `grecaptcha.execute(siteKey, {action: 'reservation'})` → získa token.
7. `fetch POST /api/reservation` s JSON body: `{package, wished_date, wished_time, kids_count, name, phone, email, note, website, csrf, recaptcha_token}`.
8. Server (`public/api/reservation.php`):
   - Verifikuje CSRF token (`Csrf::verify`).
   - Kontroluje honeypot `website` — ak nie je prázdne → 200 s fake success (nelogovať detaily, vrátime success aby boti nevedeli že sme ich chytili). Žiadny insert, žiadny e-mail.
   - Verifikuje reCAPTCHA voláním `https://www.google.com/recaptcha/api/siteverify` so `secret` a `response`. Ak `score < 0.5` alebo `success != true` → 400 `{error: 'spam_blocked'}`.
   - Rate-limit per IP (sha256): max 3 odoslania/hodinu pre bucket `reservation`. Inkrementuje, pri prekročení 429.
   - Validuje všetky polia (server-side, klient-side validation neverí).
   - Insert do `reservations` so `status = 'pending'`, `recaptcha_score`.
   - Pošle 2 e-maily cez `Mailer` (PHPMailer + WebSupport SMTP):
     - Admin: subject `[KUKO] Nová rezervácia oslavy — <balíček>`, telo HTML + plain s detailmi a linkom do admin panelu.
     - Customer: subject `Potvrdenie prijatia rezervácie — KUKO detský svet`, friendly text že prijali sme dopyt a ozveme sa do 24h, kontakty.
   - Vráti `{ok: true, message: 'Ďakujeme, ozveme sa do 24h.'}`.
9. Frontend zobrazí success state v modali (nahradí formulár veľkou confirmation kartou s emoji 🎉 + zatvoriť tlačidlom).

### 7.4 Admin

- **Autentifikácia:** HTTP Basic Auth na úrovni Apache cez `/admin/.htaccess` → `/admin/.htpasswd` (1 používateľ, bcrypt hash cez `htpasswd -nB`). Žiadny PHP login flow, žiadny logout (zatvorenie prehliadača vyčistí auth).
- **PHP session** v admine slúži IBA na CSRF token store (žiadne auth state, identitu admin používateľa čítame z `$_SERVER['REMOTE_USER']`).
- Dispatch vnútorných admin route v `public/admin/index.php`.
- **List view:** tabuľka rezervácií zoradená `created_at DESC`. Stĺpce: ID, Vytvorené, Balíček, Dátum priania, Meno, Telefón, Status (farebný badge), Akcie (Detail). Filter v URL: `?status=pending&package=maxi&from=2026-05-01&to=2026-06-30`.
- **Detail view:** všetky polia rezervácie + tlačidlá pre zmenu statusu (POST s CSRF). História zmien z `admin_actions` ako audit log.
- **Status change:** POST `/admin/reservation/<id>/status` s `{status, csrf}` → update + insert do `admin_actions`. Žiadny mail neodosielame automaticky (majiteľ klienta osloví telefonicky).

### 7.5 E-mail (Mailer)

- PHPMailer cez WebSupport SMTP (`smtp.websupport.sk:465`, SSL, login info@kuko-detskysvet.sk).
- Šablóny HTML + plain (PHP s `htmlspecialchars`) v `private/templates/mail/`.
- Reply-To na customer e-mail v admin notifikácii — majiteľ vie odpovedať priamo.
- Error pri odoslaní sa loguje, ale rezervácia ostáva v DB ako pending; majiteľ ju vidí v admine.

### 7.6 Bezpečnosť

- HTTPS forced cez `.htaccess` (uncomment po overení SSL).
- CSP header (cez `.htaccess`): `default-src 'self'; img-src 'self' data: tile.openstreetmap.org; script-src 'self' https://www.google.com/recaptcha/ https://www.gstatic.com/recaptcha/; frame-src https://www.google.com/recaptcha/; style-src 'self' 'unsafe-inline'; font-src 'self'`.
- Hlavičky `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`, `Permissions-Policy` — už nastavené v existujúcom `.htaccess`.
- Všetky DB volania cez PDO prepared statements.
- Všetky outputy cez `htmlspecialchars($x, ENT_QUOTES|ENT_HTML5, 'UTF-8')` (helper `e()`).
- CSRF na všetkých POST formulároch (verejný i admin).
- Session cookie: `HttpOnly`, `Secure`, `SameSite=Lax`.
- `.env`-like secrety nikde v gite (config.php je gitignored).
- `private/` adresár buď nad webroot (preferované na WebSupport), alebo zablokovaný cez root `.htaccess` (`RewriteRule ^private/ - [F]`).

### 7.7 Cookies & consent

| Cookie               | Účel                                       | Kategória   | Vyžaduje súhlas |
|----------------------|--------------------------------------------|-------------|-----------------|
| `PHPSESSID`          | session pre CSRF a admin auth state        | technická   | nie             |
| `cookie_consent`     | uložené rozhodnutie (`accepted`/`denied`)  | technická   | nie             |
| `_GRECAPTCHA`        | Google reCAPTCHA v3                        | nevyhnutná\*| **áno**         |

\* reCAPTCHA cookie sa právne kategorizuje variabilne; konzervatívne ju gate-ujeme za consent.

**Banner UX:**

- Zobrazí sa pri prvej návšteve (žiadny `cookie_consent` v localStorage), fixed bottom, neblokuje obsah.
- Texty SK, link na `/ochrana-udajov`.
- 2 tlačidlá: `Súhlasím` (uloží `accepted`, načíta reCAPTCHA), `Odmietnuť` (uloží `denied`, reCAPTCHA sa nenačíta, submit rezervácie ostane zablokovaný).
- Re-open cez link „Cookie nastavenia" vo footri.

### 7.8 SEO

- `<title>` per page (homepage: „Detský svet KUKO — herňa a kaviareň v Piešťanoch | Oslavy a rodinný čas"), `<meta name="description">` 150–160 znakov.
- Open Graph + Twitter Card: `og:image` z `hero.png` zmenšený na 1200×630.
- `<link rel="canonical">`.
- Schema.org **LocalBusiness** JSON-LD v `<head>`:

  ```json
  {
    "@context": "https://schema.org",
    "@type": "ChildCare",
    "name": "KUKO detský svet",
    "address": { "streetAddress": "Bratislavská 141", "postalCode": "921 01", "addressLocality": "Piešťany", "addressCountry": "SK" },
    "telephone": "+421915319934",
    "email": "info@kuko-detskysvet.sk",
    "openingHours": "Mo-Su 09:00-20:00",
    "geo": { "latitude": 48.5916, "longitude": 17.8364 }
  }
  ```

- `sitemap.xml` s 2 URL (homepage, ochrana-udajov), `robots.txt` allow all.
- Lazy-loaded obrázky, atribúty `width`/`height` (CLS).

### 7.9 Performance

- WebP variants pre všetky raster obrázky (`hero.webp`, `galeria_*.webp`), `<picture>` s PNG fallback.
- CSS minifikované manuálne (alebo cez `npx csso main.css -o main.min.css` v deploy skripte).
- Font subsetting (latin + latin-ext) cez `unicode-range`.
- Apache gzip + cache headers už nastavené v `.htaccess`.
- Žiadny JS bundler — moduly priamo `import { ... } from './gallery.js'`.

## 8. Deployment

- **WebSupport** — pravdepodobne sdielaný hosting. Pred-deploy checklist:
  1. SSH/FTP prístup overený.
  2. DB vytvorená vo WebSupport adminoch (utf8mb4), credentials do `config.php`.
  3. SMTP credentials (mailbox info@kuko-detskysvet.sk) v `config.php`.
  4. reCAPTCHA v3 site/secret kľúče.
  5. `.htpasswd` vygenerovaný (bcrypt cez `htpasswd -nB <user>`), nahraný do `public/admin/`.
  6. Migrácie spustené cez `php private/migrations/run.php`.
  7. Logo (Image_logo.png) skontrolované, či má transparentné pozadie; ak nie, exportovať SVG/PNG correctly.
- Deploy = rsync alebo SFTP upload `public/` + `private/` + `config/`. Žiadny build krok.
- Pred go-live: nasadiť na staging subdoménu (napr. `kuko-staging.lopatka.sk`), prejsť funkcie, otestovať e-maily, axe a11y scan, Lighthouse.

## 9. Testing strategy

Vzhľadom na rozsah a stack (PHP bez frameworku, vanilla JS) — minimal automated, max manual / TDD-light.

- **Server-side:** PHPUnit pre `Reservation` validátor, `RateLimit`, `Csrf`, `Recaptcha` (s mockom HTTP klienta). `Db` testovaný cez integrácie proti SQLite in-memory s rovnakou schémou.
- **End-to-end:** ručná checklist (validácia, spam guard, e-mail doručenie, admin workflow, mobile look, a11y) — checklist do `docs/qa/manual-checklist.md` pri implementácii.
- **Vizuálna regresia:** porovnanie so screenshotmi vizuálne, manuálne. Žiadny automated visual regression v tejto fáze.

## 10. Otvorené body / TODO pre implementáciu

- Logo súbor: zvoliť canonicalnú verziu (PNG vs JPEG vs SVG). Aktuálne sú `Image_logo.png` aj `Logo.jpeg` — overiť, ktorá je s transparentným pozadím a vyššou kvalitou; ideálne dostať SVG export.
- Galéria má v screenshote 6 fotiek (3×2), v `assets/` máme 5 (`galeria_1`–`galeria_5`). Buď znížiť grid na 5, alebo doplniť 6. fotku.
- Texty balíčkov MINI/MAXI/Uzavretá — finálne dotiahnuté (časť je zo screenshotu rozmazaná). Pred implementáciou potrebujeme presné copy.
- Otváracie hodiny — overiť, či sú naozaj Po–Ne 9–20 každý deň (vrátane sviatkov?). Ak nie, doplniť výnimky.
- Logo a hero — overiť, či dizajnér má SVG/vyššie rozlíšenie pre @2x retina display.
- Sociálne siete — FB a IG URL adresy. V screenshote sú ikony, ale URL chýbajú.

## 11. Fázovanie / milestones

1. **M1 — Skeleton + frontend statika** (~3 dni práce)
   - Front controller, router, base layout.
   - Všetky 7 sekcií homepage v HTML/CSS, hero, o-nás, cenník, oslavy (bez funkčného modalu), galéria (bez lightbox), kontakt (bez mapy), footer.
   - Responsívnosť + a11y.

2. **M2 — Interaktivita** (~2 dni)
   - Lightbox galéria, Leaflet mapa, smooth scroll, scroll reveal, nav hamburger.
   - Cookie banner UI + localStorage logika.

3. **M3 — Rezervácie (frontend + backend + DB)** (~3 dni)
   - Modal formulár, validácia, reCAPTCHA integrácia.
   - PHP endpoint, validácia, rate limit, CSRF, honeypot.
   - DB migrácia, model, mail templates, PHPMailer + SMTP.
   - End-to-end test rezervácie.

4. **M4 — Admin panel** (~2 dni)
   - Basic Auth, list + filter, detail, status change, audit log.

5. **M5 — Polish + deploy** (~2 dni)
   - SEO meta, Schema.org, sitemap, robots.
   - WebP konverzia, performance audit (Lighthouse), a11y audit (axe).
   - Staging deploy, e-mail end-to-end test, prod deploy.

Celkový estimate: **~12 dní práce** (bez tlaku).

## 12. Decision log

| Decision                                | Rationale                                                                 |
|-----------------------------------------|---------------------------------------------------------------------------|
| Vanilla HTML/CSS/JS bez frameworku      | Malý web, žiadny build step, pixel-perfect kontrola, jednoduchá údržba.   |
| PHP + MySQL na WebSupport               | Existujúci scaffold, hosting limitácie, žiadne Node runtime.              |
| Žiadny CMS                              | Obsah sa mení zriedka, vývojár edituje priamo. Šetrí komplexitu.          |
| reCAPTCHA v3 + cookie banner            | Užívateľské rozhodnutie. Cena: trochu UX trenie, zisk: lepšia anti-spam.  |
| Basic Auth pre admin                    | 1 admin, žiadny self-service. Minimum kódu, štandardné riešenie.          |
| DB pre rezervácie (nie len e-mail)      | Súčasť „bez tlaku" scope. Umožní admin overview + retenciu + štatistiky.  |
| Leaflet + OSM (nie Google Maps)         | Bez API key, bez tracking. Stačí na statickú mapu.                        |
| Bez frontend buildu                     | YAGNI; deploy = FTP upload; jednoduchšia údržba pre budúcnosť.            |
| WebP s PNG fallback                     | Performance LCP, ale ešte podpora pre staré prehliadače.                  |
| IP hash, nie raw IP                     | GDPR data minimization. Stačí pre rate limit + audit.                     |
