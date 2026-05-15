# Roadmap — kvalita (SEO + bezpečnosť + performance + accessibility + favicons + UX)

**Status:** Backlog. Implementuje sa po Admin CMS iterácii (docs/plans/2026-05-14-roadmap-admin-cms.md).

> **Quality Sprint 1 shipped** (2026-05-15, commits `98a07cc`…`8cf5381`): admin login CSRF token + brute-force throttle (`LoginThrottle`, 5/h per IP+user) + auth audit log (login_ok/fail/locked + IP/UA); HSTS header; GDPR retention cron (`private/cron/retention.php`) + anonymize action + email export (`/admin/gdpr`); calendar keyboard nav + ARIA grid a11y; multi-device favicon set + `manifest.webmanifest` + dedicated 1200×630 OG cover. 131 PHPUnit tests green. **Deferred go-live blocker:** prod `.htpasswd` path bug (Auth reads `public/admin/.htpasswd` but prod webroot is `web/`) — see `docs/plans/2026-05-15-quality-sprint1-STATUS.md`. Owner action items pending: Lighthouse baseline, axe scan, Google Business Profile, HSTS preload registration.

> **Admin CMS shipped** (2026-05-15): content_blocks / gallery / packages-extended / contact / seo / maintenance / log admin stránky live; Quill WYSIWYG + HtmlSanitizer; per-page SEO + maintenance toggle teraz DB-driven cez SettingsRepo. Follow-ups deferred (už v docs/plans/2026-05-14-roadmap-admin-cms.md Phase 2): privacy editor, mail templates editor, /admin/users, content versioning/rollback, live preview.

Tento dokument zhŕňa kvalitatívne oblasti, ktoré chceme dotiahnuť pred plným launch-om mimo maintenance mode-u. Šesť častí:

1. **SEO** — technické, obsahové, local
2. **Bezpečnosť** — app + transport + GDPR + backup
3. **Performance** — Core Web Vitals + Lighthouse + bundle/cache
4. **Accessibility** — WCAG AA + screen reader + keyboard + calendar a11y
5. **Favicons + ikony** — multi-device + PWA manifest
6. **UX detaily** — time slot buffer (symetria), calendar interactions, drobnosti

---

## SEO

### S1. Technické základy (priority: high, ~½ dňa)
- [x] Robots.txt + sitemap.xml (hotové)
- [x] Schema.org LocalBusiness JSON-LD (hotové, treba zaktualizovať GPS — hotové)
- [x] Open Graph + Twitter Card meta (hotové)
- [ ] **Pridať `<meta name="robots" content="noindex">` na privacy + 404 + rezervacia status page** (aby sa tieto pomocné strany neindexovali nezávisle)
- [ ] **`<meta name="robots" content="index">` na homepage explicitne** (clarification pre crawlerov, dnes je default)
- [ ] **Canonical URL** doplniť na podstránky (`/ochrana-udajov`, `/rezervacia`) — momentálne všade ukazuje na `/`
- [ ] **hreflang sk-SK** v `<html lang="sk">` + `<link rel="alternate" hreflang="sk-SK">` — robíme len SK takže stačí jeden
- [x] **`og:image` exact size** (1200×630) (Sprint 1: dedikovaný og-cover.jpg) — aktuálne ukazuje na hero.jpg ktorá môže byť iná. Vyrobiť dedikované `og-cover.jpg`

### S2. Obsahové SEO (priority: high, ~1 deň)
- [ ] **H1 audit:** každá stránka má presne 1 H1. Skontrolovať že hero "Detský svet KUKO" je jediný H1 na homepage
- [ ] **ALT texty** všetkých obrázkov — galéria má teraz generický "Fotka z herne KUKO". Doplniť konkrétne popisy ("Detský kútik s hracími prvkami", "Birthday party v KUKO", atď.)
- [ ] **Štruktúrované URL** — momentálne `/`, `/ochrana-udajov`, `/rezervacia`. Žiadne potrebné podstránky.
- [ ] **Meta description** per sekcia/strana — momentálne globálne, treba per stránku v `head.php`
- [ ] **Long-tail keywords** v texte: "detská herňa Piešťany", "detská oslava Piešťany", "narodeniny pre dieťa", "indoor playground" — naturálne v texte
- [ ] **FAQ sekcia** (možno nová sekcia na home alebo /rezervacia): "Aké sú ceny?", "Môžem rezervovať na poslednú chvíľu?", "Aký je vek detí?" — Schema.org FAQPage markup pre rich snippets v Google

### S3. Lokálne SEO (priority: medium, ~½ dňa)
- [ ] **Google Business Profile** — vytvoriť/overiť (overenie kódom alebo poštou) so správnymi hodinami, fotkami, kategóriou „Children's amusement center"
- [ ] **Schema LocalBusiness** rozšíriť: pridať `priceRange`, `aggregateRating` (až keď budú recenzie), `hasMap` URL, `image` array (viaceré fotky), `paymentAccepted`
- [ ] **NAP konzistencia** (Name, Address, Phone) — overiť, že je rovnaké všade: na webe, GBP, FB stránka, IG bio. Akékoľvek nesúlady škodia local rank
- [ ] **Pridať sa do local directories:** Firmy.sk, Zlatestranky.sk, Inforuk.sk
- [ ] **Backlinky:** požiadať partnerov (kaviarne, fotografi pre oslavy) o spätné odkazy

### S4. Performance (priority: medium, ~1 deň)
- [ ] **Lighthouse audit** (cieľ: Performance > 90 mobile, Accessibility > 95)
- [ ] **WebP optimalizácia** — už máme, ale skontrolovať či sa správne servuje cez `<picture>`
- [ ] **Lazy loading** — `loading="lazy"` na galériu ✓, na hero pridat `fetchpriority="high"` na 1. obrázok
- [ ] **CSS minifikácia** — `main.css` má ~600 riadkov; cez `npx csso main.css -o main.min.css` v deploy skripte
- [ ] **JS minifikácia + tree-shake** — moduly sú malé, ale `npx esbuild --bundle --minify main.js` by zaokrúhlilo na ~3 KB
- [ ] **Preconnect** to `unpkg.com` a `tile.openstreetmap.org` v `<head>` pre rýchlejšie načítanie mapy
- [ ] **Cache headers** — `.htaccess` má `Expires` pre statiky, treba overiť že prehliadač ich rešpektuje
- [ ] **Core Web Vitals:** LCP < 2.5s, CLS < 0.1, INP < 200ms — namerajte pred a po WebP/minifikácii

### S5. Analytics + monitoring (priority: low, ~½ dňa)
- [ ] **Plausible Analytics** (privacy-first, bez cookies → nepotrebuje banner) — cca 9 USD/mes alebo self-host
- [ ] **Google Search Console** — overiť doménu, sledovať crawl errors, query performance, position
- [ ] **UptimeRobot** — 5-minútový ping na homepage, alert email pri downe

---

## Bezpečnosť

### B1. Application layer (priority: high, ~½ dňa)
- [x] CSRF token na formulároch (hotové)
- [x] reCAPTCHA v3 s cookie consent gate (hotové)
- [x] Rate limit per IP (file-based, hotové)
- [x] Honeypot na rezervačnom formulári (hotové)
- [x] CSP header (hotové)
- [x] **Brute-force ochrana admin login-u** — `Kuko\LoginThrottle` (5 zlých/h per IP + per username, success vyčistí buckety), 429 + lockout hláška (Sprint 1)
- [ ] **2FA pre admin** (TOTP cez Google Authenticator) — niekedy v Q3 keď to dáva zmysel z hľadiska traffic-u
- [ ] **CAPTCHA aj na admin login** — invisible reCAPTCHA v3, threshold 0.7 (vyššie ako pre rezerváciu)
- [x] **Audit log enrichment** (Sprint 1: login_ok/fail/locked + IP/UA do admin_actions) — admin_actions už loguje, ale chýba IP/UA, neúspešné login pokusy
- [x] **Login POST CSRF token** (Sprint 1: hidden field + verify pred credentials, 403 + „token vypršal")  — — `/admin/login` POST (`public/admin/index.php`) neoveruje CSRF token; login formulár (`private/templates/admin/login.php`) nemá csrf field. Nízke riziko (login je z podstaty neautentizovaný, cieľom sú credentials nie state-change) ale logged-in-CSRF / login-CSRF hardening je good practice. Pridať CSRF token do login formulára + overiť v POST handleri. ~15 min

### B2. Transport + headers (priority: high, hotové)
- [x] HTTPS forced (hotové)
- [x] HTTP/2 (hotové cez WebSupport openresty)
- [x] X-Content-Type-Options: nosniff
- [x] X-Frame-Options: SAMEORIGIN
- [x] Referrer-Policy
- [x] Permissions-Policy
- [x] CSP s explicit allowlist
- [x] **HSTS** (Sprint 1, .htaccess `always set`) (`Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`) — pridať do `.htaccess`
- [ ] **HSTS preload** registrácia (https://hstspreload.org) — len keď je si istý, že nikdy nepôjdeš späť na http

### B3. Dependencies + supply chain (priority: medium, ~1 deň)
- [ ] **PHPMailer security audit** — verzia 6.9.1 je aktuálna (k 2026-05). Sledovať CVE feed
- [ ] **Leaflet CDN** — používame `unpkg.com/leaflet@1.9.4`, integrity hash je v `<link>` (✓). Verziu možno bumpnúť na najnovšiu po teste
- [ ] **reCAPTCHA cookie:** Google si ukladá cookies, ale to je očakávané + máme cookie consent. Periodicky review Google policy
- [ ] **Self-host fontov** (hotové, máme NunitoSans v `/assets/fonts/`)
- [ ] **No 3rd-party JS without SRI** — Leaflet ✓, reCAPTCHA ✗ (Google nemá stable hashes). Akceptovateľné.

### B4. Data privacy + GDPR (priority: high, ~½ dňa)
- [x] Cookie consent banner pred reCAPTCHA load (hotové)
- [x] Privacy policy /ochrana-udajov (hotové)
- [x] IP hash, nie raw IP v DB (hotové)
- [x] **Data retention cron** (Sprint 1: `private/cron/retention.php`, mesačne) — automatický cleanup rezervácií starších ako 6 mesiacov + anonymizácia. Skript do `private/cron/`, WebSupport cron 1× mesačne
- [x] **„Pravo na zabudnutie"** (Sprint 1: admin akcia „Anonymizovať (GDPR)") — admin akcia "anonymizovať rezerváciu" (zachová štatistiky, vymaže PII)
- [x] **Žiadosti o údaje** (Sprint 1: `/admin/gdpr` výpis podľa e-mailu) — keď klient požiada o výpis svojich rezervácií, owner cez admin filtruje podľa e-mailu, exportuje
- [ ] **Cookie audit** — len `PHPSESSID`, `cookie_consent`, `kuko_staff`, `kuko_admin`, `_GRECAPTCHA`. Žiadne 3rd-party tracking

### B5. Backup + recovery (priority: high, ~½ dňa)
- [ ] **Automatický DB backup** — WebSupport robí denné zálohy, ale dobré pridať vlastný cron týždenne s download cez SFTP do lokálu/Backblaze B2
- [ ] **Git je SVR** — kód je v repo, ale config.php nie. Doplniť: bezpečne uložiť kópiu config.php (1Password / Bitwarden) ako single-source-of-truth
- [ ] **Recovery dokumentácia** — krok-po-kroku: keby spadol DB server, ako obnovit?

### B6. Hardening (priority: medium, ~½ dňa)
- [ ] **PHP `display_errors` = off na prod** (už máme `app.debug = false`)
- [ ] **`error_log` mimo webroot** — momentálne logy idú do `private/logs/`, ✓
- [ ] **Disabled PHP functions** v `.htaccess` (pôvodne pre `eval`, `exec`, `shell_exec` — WebSupport pravdepodobne má svoje obmedzenia)
- [ ] **Session hardening** — `session.use_strict_mode = 1`, `session.cookie_secure = 1`, `session.cookie_httponly = 1`, `session.cookie_samesite = Lax` (väčšina máme)
- [ ] **PHP-FPM pool** — overiť že `pm.max_children` je nastavený rozumne (zákazka pre WebSupport support, ak chce viac stability)
- [ ] **fail2ban-like** — WebSupport pravdepodobne má svoju ochranu, treba sa spýtať

### B7. Penetration testing (priority: low, ~1 deň)
- [ ] **OWASP ZAP scan** — automatický scan top 10 zraniteľností. Po deploy spustiť proti staging URL
- [ ] **Manual review** — XSS pokus v form fieldoch, SQL injection pokus v API, CSRF replay test, session fixation test
- [ ] **Subdomain takeover** — overiť že žiadne CNAME nesmeruje na unowned services
- [ ] **DNS audit** — DMARC/SPF/DKIM nastavené pre `info@kuko-detskysvet.sk` cez WebSupport DNS panel

---

---

## Performance / rýchlosť

### P1. Core Web Vitals — namerať baseline + cieľ (priority: high, ~½ dňa)
- [ ] **Lighthouse audit** (mobile + desktop) — namerať aktuálny stav Performance, Accessibility, Best Practices, SEO. Uložiť ako baseline screenshot do `docs/audits/`
- [ ] **Cieľové hodnoty po roadmape:** Performance > 90 mobile, > 95 desktop. LCP < 2.0s, CLS < 0.05, INP < 200ms, TBT < 200ms
- [ ] **PageSpeed Insights** (Google) — porovnať real-world data z CrUX po prvom týždni public live
- [ ] **WebPageTest** — multi-region testing, identify slowest geographic regions

### P2. Asset optimization (priority: high, ~1 deň)
- [x] WebP obrázky (hotové)
- [x] Preconnect hints (unpkg.com, tile.openstreetmap.org) (hotové)
- [x] Hero image preload + fetchpriority="high" (hotové)
- [x] Lazy loading na galériu (hotové)
- [ ] **CSS minifikácia** — `main.css` ~600 riadkov, `rezervacia.css` ~250 riadkov, `admin.css` ~150. Cez `npx csso *.css -o *.min.css` v deploy skripte. Bonus: gzip ich Apache už robí
- [ ] **Critical CSS inlining** — extract above-the-fold CSS (~5 KB) priamo do `<style>` v `<head>`, zvyšok asynchronously. Cez `critical` npm tool
- [ ] **JS minifikácia + bundle** — moduly sú malé, ale `npx esbuild --bundle --minify` by ich zlúčilo do ~5 KB total. ES modules sa stratia (treba `--format=esm`)
- [ ] **Font subsetting** — `NunitoSans.ttf` má všetky glyphs (CJK, Cyrillic). Subsetnúť na `latin + latin-ext` ušetrí 60–70 % size. Cez `pyftsubset` (fonttools) alebo `glyphhanger`
- [ ] **Font format upgrade** — pridať WOFF2 variant (lepšia kompresia než TTF), keep TTF ako fallback
- [ ] **Image format audit** — overiť že WebP varianty sa správne servujú cez `<picture>` (DevTools Network — `image/webp` Content-Type)
- [ ] **Responsive images** — `srcset` pre hero podľa viewport (768w, 1280w, 1920w varianty), aktuálne servujeme jeden veľký
- [ ] **Image dimensions atribúty** — všetky `<img>` majú `width` + `height` atribúty (CLS prevention), audit cez axe

### P3. Caching strategy (priority: high, ~½ dňa)
- [x] `Expires` headers v `.htaccess` (hotové) — fonty 1 rok, CSS/JS 1 mesiac, images 1 rok
- [x] Gzip cez `mod_deflate` (hotové)
- [ ] **Brotli compression** — overiť či openresty/Apache na WebSupporte podporuje (`AddOutputFilterByType BROTLI_COMPRESS …`). 15–20 % menšie payloady než gzip
- [ ] **Cache-busting** — momentálne CSS/JS sa cachujú 1 mesiac, ale pri zmene používateľ nevie ako force-reload. Pridať `?v={git_sha}` query string do `<link>` a `<script>` (cez App bootstrap)
- [ ] **Service Worker** (optional) — offline support, instant repeat-visits. Cez Workbox config. Out-of-scope ak nemáme PWA ambície
- [ ] **CDN** — momentálne všetko z origin servera (WebSupport). Cloudflare free tier by pomohol s global edge. Out-of-scope ak je traffic SK-centric

### P4. Server-side optimization (priority: medium, ~½ dňa)
- [ ] **PHP OPcache** — overiť že je zapnutý a config (`opcache.revalidate_freq=60` aspoň). WebSupport pravdepodobne má default settings, treba si overiť
- [ ] **MySQL query cache / indexy** — `EXPLAIN SELECT` na critical queries (reservations list, month availability)
- [ ] **HTTP/2 server push** — riskantné (deprecating), prefer `<link rel="preload">` čo už máme
- [ ] **HTTP/103 Early Hints** — moderné, ale WebSupport pravdepodobne nepodporuje cez user-config. Skip
- [ ] **DB connection pooling** — irelevantné pre PHP-FPM (každý req nový conn). Out-of-scope

### P5. Monitoring (priority: low, ~½ dňa)
- [ ] **Real User Monitoring (RUM)** — Plausible je metrics-only, nesleduje CWV. Pridať Cloudflare Web Analytics (zdarma, RUM, žiadne cookies) alebo `web-vitals.js` lib s vlastným endpointom
- [ ] **Error tracking** — Sentry free tier alebo self-hosted GlitchTip. JS chyby + PHP exceptions
- [ ] **Performance budget** — `lighthouse-ci` v deploy pipeline, fail build ak Performance < 85

---

## Accessibility (WCAG AA)

### A1. Audit (priority: high, ~½ dňa)
- [ ] **axe DevTools** rozšírenie — full scan každej stránky (home, rezervacia kroky 1/2/3, privacy, admin)
- [ ] **Lighthouse a11y** score — cieľ > 95
- [ ] **Manual keyboard test** — TAB cez všetky interaktívne elementy, žiadne traps, focus indikátor viditeľný
- [ ] **Screen reader test** — VoiceOver (macOS), NVDA (Windows): hlavička, nav, sekcie, modal vyplnenie, success state — všetko správne anoncované

### A2. Semantic markup (priority: high, ~½ dňa)
- [x] Semantické HTML5 (`<header>`, `<nav>`, `<main>`, `<section>`, `<footer>`) — väčšina hotové
- [x] ARIA labels na ikon-buttons (hotové väčšina)
- [ ] **H-hierarchia audit** — každá stránka má 1 H1, sub-headings v poradí H2 → H3 → H4. Žiadne preskakovania
- [ ] **Landmark regions** — overiť že každá stránka má presne `<main>`, voliteľne `<aside>` pre sidebar
- [ ] **Skip link** — `<a href="#main" class="sr-only-focusable">Skip to content</a>` na vrch každej stránky pre keyboard users

### A3. Forms a11y (priority: high, ~½ dňa)
- [ ] **Label associations** — každý `<input>` má `<label for="id">` (rezervacia.php OK, admin forms OK)
- [ ] **Required indicators** — `aria-required="true"` na required poliach + visual asterisk
- [ ] **Error messages** — `aria-describedby` linking input k error msg, `role="alert"` + `aria-live="polite"` na error container
- [ ] **Inline validation announce** — keď klient niečo zle vyplní, screen reader by mal počuť error bez submit-u
- [ ] **Success state announce** — po submit-e success div má `role="status"` + `aria-live="polite"`
- [ ] **Disabled buttons** — `aria-disabled="true"` + zachovať fokusovateľnosť (alebo `:disabled` ale potom musí byť jasné prečo)

### A4. Calendar a11y (priority: high, ~1 deň)
- [ ] **Role="grid"** na `.calendar__grid` (hotové), `role="row"` na týždne, `role="gridcell"` na bunky
- [x] **Keyboard navigation:** (Sprint 1) šípky pre pohyb medzi dňami, Home/End pre prvý/posledný v týždni, PageUp/PageDown pre mesiac, Enter pre výber
- [x] **aria-selected** na vybraný deň (Sprint 1)
- [x] **aria-disabled** na nedostupné dni (Sprint 1) (closed_day, full, past) — momentálne `disabled` atribút, treba doplniť ARIA
- [x] **aria-label per cell** (Sprint 1) — „Streda 15. máj, dostupné, 19 voľných časov" / „Sobota 18. máj, plne obsadené"
- [x] **aria-live region** (Sprint 1) — po výbere dňa announce „Vybraný 15. máj. 19 voľných časov pod kalendárom."
- [ ] **Focus trap v modal-like UX** — multi-step formulár nie je modal, ale focus by mal po prechode kroku ísť na prvý interaktívny prvok v novom kroku

### A5. Visual / color (priority: medium, ~½ dňa)
- [x] Color contrast WCAG AA pre body text (`#3D3D3D` na `#FFF8EE` = 11.3:1 ✓)
- [ ] **Sub-text contrast audit** — `#7A7A7A` na `#FFF8EE` = 4.0:1 — pod AA pre normal text (4.5:1). Buď použiť `#666` (5.4:1) alebo zväčšiť na large text
- [ ] **Calendar status farby** — zelená/červená pre available/full nie sú vhodné pre farbosleposť. Pridať aj ikon (✓/✗) alebo text label
- [ ] **Focus indicator** — `:focus-visible` outline pre keyboard, viditeľný aj na dark backgrounds (akcent ring je OK pre svetlé, ale na hero overlay zlý)
- [ ] **prefers-reduced-motion** — hotové pre scroll reveal, treba audit aj na rezervacia step transitions (`fadeIn` animácia)
- [ ] **prefers-contrast: more** — voliteľné, výrazne odlišný high-contrast variant

### A6. Cognitive (priority: medium, ~½ dňa)
- [ ] **Plain language audit** — SK texty sú jednoduchá slovenčina, ale skontrolovať dlhé súvetia v Uzavretá spoločnosť popise (5 viet, zjednodušiť)
- [ ] **Error recovery** — pri chybe formulára jasne povedať čo opraviť + ako (nie len „neplatný telefón")
- [ ] **Time estimates** — pri rezervácii „odhadovaný čas vyplnenia 1 min" v step 3 môže pomôcť
- [ ] **Confirmation before destructive** — admin už má `onsubmit="return confirm(...)"` na delete blocked, treba audit na všetkých delete/cancel akciách
- [ ] **No flashing content** — overiť že nič nebliká > 3× za sekundu

---

## Favicons + ikony + PWA manifest

Momentálne máme len jeden `favicon.ico` v `public/`. Pre kompletný multi-device support:

### F1. Favicon set (priority: medium, ~½ dňa)
- [x] **favicon.ico** (Sprint 1) — multi-resolution (16×16, 32×32, 48×48) v jednom .ico súbore. Cez https://realfavicongenerator.net/ alebo `imagemagick`
- [ ] **favicon.svg** — moderné prehliadače (Chrome 80+, Safari 12+, Firefox 41+) preferujú SVG. Mali by sme mať KUKO dúhu monochromatickú alebo full-color
- [x] **Apple touch icon** (Sprint 1) — `apple-touch-icon.png` 180×180 (homepage save to iPhone home screen)
- [x] **Android Chrome icons** (Sprint 1: icon-192/512) — 192×192 a 512×512 PNG pre add-to-homescreen
- [ ] **Safari pinned tab** — `safari-pinned-tab.svg` monochrome, theme-color
- [ ] **Microsoft tile** — `mstile-150x150.png` + `browserconfig.xml` pre Windows Start screen pinning (málo používané, optional)
- [x] **HTML refs** (Sprint 1) — `<link rel="icon" href="/favicon.svg" type="image/svg+xml">`, `<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">`, atď.

### F2. Web App Manifest (priority: low, ~½ dňa)
- [x] **`/manifest.webmanifest`** (Sprint 1) — JSON s name, short_name, icons array, theme_color, background_color, display: "standalone", start_url: "/"
- [ ] **Theme color meta** — `<meta name="theme-color" content="#FBEEF5">` (máme), variant pre tmavý mode
- [ ] **Apple splash screens** — pre PWA-like skúsenosť na iOS (12 variant per device size). Cez https://progressier.com/pwa-icons-and-ios-splash-screen-generator
- [ ] **Status:** je PWA reálne ambícia? Bez service worker-u to nedáva veľký sense. Asi skip.

### F3. OG / Social images (priority: medium, ~½ dňa)
- [x] **og-cover.jpg** — 1200×630 (Sprint 1) dedikovaný OG obrázok (nie reuse hero.jpg ktorá je vyšších rozmerov a iného aspect ratia). KUKO logo + dúha + URL
- [ ] **Twitter Card image** — môže byť rovnaký 1200×630
- [ ] **Facebook OG validator** — testovať cez https://developers.facebook.com/tools/debug/
- [ ] **LinkedIn / Slack preview** — testovať aké preview generujú

---

## UX detaily

### U1. Time slot buffer — symetria (priority: medium, ~½ dňa)
Aktuálne `Availability` aplikuje buffer **iba na pravej strane** existujúcej rezervácie:
- MAXI 14:00–17:00 + buffer 30 min → blokuje 14:00–17:30
- Nová MINI 12:00–14:00 je povolená (končí presne pri 14:00, žiadny buffer **pred**)

To znamená, že personál má 30 min buffer **po** MAXI ale **0 min pred** MAXI. Pre konzistentnú „one room" logiku by buffer mal byť **symetrický** — aj pred existujúcou rezerváciou:

```php
// Subtract [r_start - buffer, r_end + buffer] namiesto [r_start, r_end + buffer]
```

**Decision needed:** chceš symetrický buffer? Vyžaduje update `Availability::forDate()` a `testExistingReservationBlocksWithBuffer` test.

### U2. Calendar interactions (priority: low, ~½ dňa)
- [ ] **Drag-select** range — voliteľne pre admin (napr. dovolenka 3 dni naraz)
- [ ] **Hover preview** — hover na deň v kalendári zobrazí tooltip s prvými 3 voľnými časmi (desktop only)
- [ ] **Today shortcut** — button „Dnes" v calendar nav-e pre rýchly návrat
- [ ] **Month-year jump** — klik na nadpis mesiaca otvorí year/month picker (skip-around)

### U3. Form quality-of-life (priority: low, ~½ dňa)
- [ ] **Phone validation** — momentálne regex `/^\+?[0-9 ()\/-]{7,20}$/` je voľný. Pre SK by sme mohli vyžadovať `+421` alebo `0` prefix
- [ ] **Email autocomplete suggestions** — `autocomplete="email"` máme, but `<input list>` s gmail/outlook common domains
- [ ] **Smart defaults** — predvyplniť time na 14:00 (najobľúbenejší slot pre oslavy)
- [ ] **Persist on reload** — ak omylom F5 počas formulára, neuložiť ale zachovať stav cez `sessionStorage`

### U4. Drobnosti
- [ ] **Smooth animácia step transition** — zmena z krok 1 → 2 by mala mať plynulejšie prechody než aktuálny fadeIn (slide left/right by lepšie pasoval k stepperu)
- [ ] **Success modal/page kontextový obsah** — po rezervácii zobraziť tlačidlo „Pridať do Google Calendar" s `webcal://` linkom alebo `.ics` download

---

## Estimated total effort

| Block | Tasks | Effort |
|---|---|---|
| SEO | 24 | ~3 dni |
| Bezpečnosť | 26 | ~3 dni |
| Performance | 27 | ~3 dni |
| Accessibility | 27 | ~3 dni |
| Favicons + PWA | 11 | 1 deň |
| UX detaily | 12 | 1,5 dňa |
| **Spolu** | **127** | **~14,5 dní** |

Realisticky rozložené do 4–5 sprintov, po každom audit (Lighthouse, axe, ZAP, manual a11y).

## Priorita pre next sprint (top 8 most impactful)

1. **B1. Brute-force ochrana admin login** — najakútnejšie po session-auth refactore
2. **B2. HSTS header** — 1 riadok v .htaccess
3. **P1. Lighthouse baseline + auditovať CWV** — bez baseline-u neviem či sa zlepšuje
4. **A1+A4. axe scan + calendar a11y** — calendar je najkomplexnejšia UI komponenta, najviac a11y bodov
5. **S3. Google Business Profile** — local SEO base, manuálny task pre vlastníka
6. **B4. Data retention cron** — GDPR vyžaduje
7. **F1. Multi-device favicon set** — kvalitatívna nutnosť pred public launch
8. **F3. OG cover dedikovaný** — social shares vyzerajú lepšie

**Po týchto 8 úlohách:** pretiahnúť maintenance flag `false` a public_indexing flag `true`.
