# Roadmap — SEO + bezpečnosť

**Status:** Backlog. Implementuje sa po dokončení aktuálnej rezervačnej rework iterácie.

Tento dokument lízne dve oblasti, ktoré sú teraz „good enough" ale chceme ich postupne dotiahnuť pred plným launch-om mimo maintenance mode-u.

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
- [ ] **`og:image` exact size** (1200×630) — aktuálne ukazuje na hero.jpg ktorá môže byť iná. Vyrobiť dedikované `og-cover.jpg`

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
- [ ] **Brute-force ochrana admin login-u** — momentálne neobmedzené pokusy. Pridať rate-limit per IP + per username (max 5 zlých pokusov za hodinu, potom timeout 1h). Reuse existing `RateLimit` lib
- [ ] **2FA pre admin** (TOTP cez Google Authenticator) — niekedy v Q3 keď to dáva zmysel z hľadiska traffic-u
- [ ] **CAPTCHA aj na admin login** — invisible reCAPTCHA v3, threshold 0.7 (vyššie ako pre rezerváciu)
- [ ] **Audit log enrichment** — admin_actions už loguje, ale chýba IP/UA, neúspešné login pokusy

### B2. Transport + headers (priority: high, hotové)
- [x] HTTPS forced (hotové)
- [x] HTTP/2 (hotové cez WebSupport openresty)
- [x] X-Content-Type-Options: nosniff
- [x] X-Frame-Options: SAMEORIGIN
- [x] Referrer-Policy
- [x] Permissions-Policy
- [x] CSP s explicit allowlist
- [ ] **HSTS** (`Strict-Transport-Security: max-age=31536000; includeSubDomains; preload`) — pridať do `.htaccess`
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
- [ ] **Data retention cron** — automatický cleanup rezervácií starších ako 6 mesiacov + anonymizácia. Skript do `private/cron/`, WebSupport cron 1× mesačne
- [ ] **„Pravo na zabudnutie"** — admin akcia "anonymizovať rezerváciu" (zachová štatistiky, vymaže PII)
- [ ] **Žiadosti o údaje** — keď klient požiada o výpis svojich rezervácií, owner cez admin filtruje podľa e-mailu, exportuje
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

## Estimated total effort

| Block | Tasks | Effort |
|---|---|---|
| SEO | 24 | ~3 dni |
| Bezpečnosť | 26 | ~3 dni |
| **Spolu** | **50** | **~6 dní** |

Realisticky to rozložíme do 2–3 sprintov, po každom audit (Lighthouse pre SEO, ZAP scan pre bezpečnosť).

## Priorita pre next sprint (5 most impactful)

1. **B1.brute-force admin** — najakútnejšie po dnešnom session-auth refactore
2. **B2.HSTS** — 1 riadok v .htaccess, big security win
3. **S2.FAQ sekcia + Schema FAQPage** — najvýznamnejší SEO boost (rich snippets v Google)
4. **S3.Google Business Profile** — local SEO base
5. **B4.Data retention cron** — GDPR vyžaduje
