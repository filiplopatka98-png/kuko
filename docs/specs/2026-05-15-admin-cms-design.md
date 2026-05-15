# Admin CMS — Design Spec

- **Date:** 2026-05-15
- **Status:** Approved (user confirmed 2026-05-15)
- **Owner:** Filip Lopatka
- **Builds on:** `docs/plans/2026-05-14-roadmap-admin-cms.md`

## Goal

Vlastník KUKO si vie cez `/admin/` upraviť všetok relevantný frontend obsah — texty sekcií, fotogalériu, balíčky osláv, kontakty, sociálne siete, otváracie hodiny, SEO meta a maintenance režim — bez editovania PHP súborov a bez vývojára. Admin je vizuálne v KUKO dizajne (farby, fonty, komponenty).

## Klúčové rozhodnutia (z brainstormingu)

| Otázka | Rozhodnutie |
|---|---|
| Fázovanie | Celý CMS naraz (~8 dní), jeden veľký deploy |
| Rozsah obsahu | Texty sekcií + galéria + balíčky + kontakt/social/hodiny + SEO + maintenance |
| HTML editor | Quill + server-side whitelist sanitizer |
| Galéria upload | Auto WebP + resize (max 2000px) + drag-drop poradie |
| Admin dizajn | Farby + fonty + komponenty (KUKO téma), login/maintenance/error mimo redesign |
| Users mgmt | Neskôr (1 admin stačí) |
| História zmien | Audit log + UI náhľad `/admin/log` |
| Storage prístup | A — DB `content_blocks` + `Content::get()` s hardcoded fallbackmi |

## Architektúra

### Dátový model — migrácia `005_cms.sql`

```sql
CREATE TABLE content_blocks (
    block_key     VARCHAR(80) PRIMARY KEY,
    label         VARCHAR(120) NOT NULL,
    content_type  ENUM('text','html','image') NOT NULL DEFAULT 'text',
    value         MEDIUMTEXT NOT NULL,
    updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by    VARCHAR(60) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE gallery_photos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename    VARCHAR(180) NOT NULL,        -- napr. "gal_<uniqid>.jpg"
    webp        VARCHAR(180) NULL,
    alt_text    VARCHAR(255) NOT NULL,
    sort_order  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_visible  TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE packages
    ADD COLUMN description     TEXT NULL AFTER name,
    ADD COLUMN price_text      VARCHAR(40) NULL,
    ADD COLUMN kids_count_text VARCHAR(40) NULL,
    ADD COLUMN duration_text   VARCHAR(40) NULL,
    ADD COLUMN included_json   TEXT NULL,        -- JSON array of strings (TEXT pre SQLite kompat)
    ADD COLUMN accent_color    VARCHAR(20) NULL; -- 'blue' | 'purple' | 'yellow'
```

Nové `settings` kľúče (UPSERT v migrácii, hodnoty migrované z `config.php` one-shot pri prvom admin load-e):
- `maintenance.enabled` (`0`/`1`), `maintenance.password`
- `seo.public_indexing` (`0`/`1`)
- `seo.home.title`, `seo.home.description`, `seo.rezervacia.title`, `seo.rezervacia.description`, `seo.faq.title`, `seo.faq.description`, `seo.privacy.title`, `seo.privacy.description`, `seo.default.title`, `seo.default.description`

**Seed:** `content_blocks` z aktuálnych hardcoded hodnôt (hero title/subtitle/CTA, about lead + 4 karty × title/body, cennik lead + 3 riadky × label/price, kontakt address/phone/email/hours, footer copyright). `gallery_photos` z existujúcich `galeria_1..5.jpg` s ALT-mi z `galeria.php`.

**Vyjasnenia:**
- `content_type='image'` v `content_blocks` je **rezervovaný pre budúcnosť** (napr. výmena hero pozadia). V tejto iterácii sa obrázky menia LEN cez fotogalériu (`gallery_photos`); sekčné pozadia (`hero.jpg`, `cennik.jpg`) ostávajú statické assety. Žiadny image-type blok sa zatiaľ neseeduje.
- Footer copyright blok podporuje token `{{year}}` — `Content::get()` ho pri renderovaní nahradí aktuálnym rokom (`date('Y')`). Tým sa zachová dnešné dynamické správanie aj po presune do DB.

### Lib vrstva (`private/lib/`)

| Trieda | Zodpovednosť |
|---|---|
| `ContentBlocksRepo` | `get(key)`, `set(key,value,by)`, `all()`, `listGrouped()` (group podľa prefixu pred `.`). Request-cache |
| `MediaRepo` | `uploadGalleryPhoto($file,$alt)` → validácia (≤5 MB, jpg/png/webp MIME), uniqid filename, resize ≤2000px, WebP variant, insert s `sort_order=MAX+1`; `delete($id)` (zmaže aj súbory); `reorder($idOrder[])`; `setVisibility($id,$bool)`; `updateAlt($id,$alt)` |
| `Content` | `Content::get($key,$fallback='')` — statická request-cache, lazy `ContentBlocksRepo`. Použitie v šablónach |
| `HtmlSanitizer` | `clean($html)` — whitelist tagov `b,i,strong,em,a,p,ul,ol,li,br,h3,h4`, `a[href]` len `http(s)://` alebo `mailto:`/`tel:`, strip všetkých `on*` atribútov a `<script>/<style>`. Volané pri `set()` na `content_type=html` |

`SettingsRepo` rozšírený: žiadne API zmeny, len nové kľúče. `Maintenance::enabled()` + `Maintenance::passwordMatches()` čítajú zo `SettingsRepo` (fallback na `config.app.*` ak settings prázdne — migračné obdobie). `head.php` SEO meta číta zo `SettingsRepo` s fallbackom na hardcoded.

### Admin stránky + routy

Pridané do `public/admin/index.php` (existujúci Router pattern, `Auth::requireLogin()` gate, CSRF na POST, audit cez `$audit(...)`):

| Route | Method | Akcia |
|---|---|---|
| `/admin/content` | GET | Skupinový zoznam blokov |
| `/admin/content/save` | POST | Uloží jeden blok (key, value), HTML sa sanitizuje |
| `/admin/gallery` | GET | Mriežka fotiek |
| `/admin/gallery/upload` | POST | Multipart upload → WebP → insert |
| `/admin/gallery/{id}/delete` | POST | Zmaže fotku + súbory |
| `/admin/gallery/{id}/visibility` | POST | Toggle is_visible |
| `/admin/gallery/{id}/alt` | POST | Update ALT |
| `/admin/gallery/reorder` | POST | JSON `[id,…]` nové poradie |
| `/admin/packages` | GET | Rozšírený formulár (existujúci route, viac polí) |
| `/admin/packages/{code}` | POST | Update vrátane description/price_text/included/color |
| `/admin/contact` | GET/POST | Telefón, e-mail, adresa, GPS, FB, IG (content_blocks + settings) |
| `/admin/seo` | GET/POST | Per-page title/description + global indexing toggle |
| `/admin/maintenance` | GET/POST | Toggle + zmena hesla + status |
| `/admin/log` | GET | Read-only `admin_actions` tabuľka (paginované, posledných 200) |

Admin nav (layout.php) dostane nové položky: **Obsah · Galéria · Balíčky · Kontakt · SEO · Maintenance · Log** + existujúce Rezervácie/Kalendár/Hodiny/Blokácie/Nastavenia.

### Frontend integrácia

- `sections/hero.php`, `o-nas.php`, `cennik.php`, `kontakt.php`, `footer.php` → `<?= e(\Kuko\Content::get('hero.title', 'Detský svet KUKO')) ?>`. Hardcoded text zostáva ako fallback (web renderuje aj pri prázdnej/nedostupnej DB).
- HTML bloky (about lead, package description) → `<?= \Kuko\Content::get('about.lead', '<p>…</p>') ?>` (už sanitizované pri uložení, fallback je dôveryhodný).
- `sections/galeria.php` → číta `MediaRepo->listVisible()`; fallback na 5 statických `galeria_*.jpg` ak DB prázdne.
- `sections/oslavy.php` → číta rozšírené `packages` (description, price_text, included_json, accent_color); fallback na hardcoded ak polia NULL.
- `head.php` → SEO meta z `SettingsRepo` s hardcoded fallbackmi; indexing flag z DB.
- `Maintenance` → `enabled()`/`passwordMatches()` z `SettingsRepo`.

### Admin dizajn (KUKO téma)

`public/assets/css/admin.css` prebrandovaný:
- Font: **Nunito Sans** (self-hosted, už máme `/assets/fonts/`)
- Paleta: `--c-accent: #D88BBE`, `--bg-cream: #FFF8EE`, `--bg-pink-soft: #FBEEF5`, `--c-text: #3D3D3D`, `--c-text-soft: #7A7A7A`, status badges KUKO odtieňmi
- Komponenty: biele karty `box-shadow: 0 4px 20px rgba(0,0,0,0.05)`, **pill tlačidlá** `border-radius: 999px`, zaoblené rohy `1.25rem`, inputy s accent focus ring
- Layout štruktúra (header nav, main container) ostáva — mení sa len vizuál
- Quill toolbar prefarbený do palety

Mimo redesignu: `login.php` (ostáva funkčný generický), `maintenance.php` (už KUKO branded), error stránky.

## Bezpečnosť

- Všetky admin POST cez CSRF token (`Csrf::verify`)
- `Auth::requireLogin()` na všetkých CMS routách (okrem login/logout)
- Upload: MIME whitelist (`image/jpeg|png|webp`), veľkosť ≤ 5 MB, `finfo` overenie (nie len prípona), uniqid filename (žiadne user-controlled paths), uloženie do `public/assets/img/gallery/` mimo PHP exekúcie
- `HtmlSanitizer` na všetkom HTML obsahu pred uložením (XSS prevencia) — whitelist, nie blacklist
- Audit log pre každý content save / upload / delete / maintenance toggle / SEO zmenu

## Testing

PHPUnit (cez SQLite in-memory):
- `ContentBlocksRepoTest` — get/set/all/listGrouped, request-cache invalidácia
- `MediaRepoTest` — upload (mock $_FILES + tmp), reorder, delete čistí súbory, visibility
- `HtmlSanitizerTest` — XSS vektory (`<script>`, `onerror=`, `javascript:`, nested), whitelist passthrough
- `ContentTest` — fallback keď key chýba, cache

Manuálny smoke (Preview MCP): upload fotky, edit textu cez Quill, toggle maintenance, SEO Google preview, drag-drop reorder.

## Migračná stratégia (hardcoded → DB)

1. Migrácia 005: tabuľky + ALTER + settings UPSERT
2. Lib: repos, Content, HtmlSanitizer, vendor Quill (`public/assets/vendor/quill/`)
3. Šablóny: refactor na `Content::get()` s fallbackmi (web funguje pred aj po migrácii)
4. Admin: stránky + KUKO rebrand admin.css
5. Seed: jednorazový skript naplní content_blocks/gallery_photos z aktuálnych hodnôt
6. Deploy + smoke + commit

Web NIKDY nesmie spadnúť kvôli prázdnej DB — fallbacky to garantujú.

## Estimate

~8 dní (44 roadmap taskov + Quill integrácia + admin rebrand + testy).

## Mimo scope tejto iterácie → odložené do roadmapy

Pridané do `docs/plans/2026-05-14-roadmap-admin-cms.md` sekcia „Odložené (Phase 2)":
- Privacy page text editor (legal — ostáva PHP)
- Mail šablóny editor
- `/rezervacia` step labels / UI texty
- `/admin/users` (správa viacerých adminov)
- Plné verziovanie + rollback obsahu
- Live preview (real-time náhľad pred uložením)
