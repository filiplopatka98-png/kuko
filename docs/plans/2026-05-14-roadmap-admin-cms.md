# Roadmap — Admin CMS pre frontend obsah

**Status:** Backlog. Implementuje sa po SEO + bezpečnostnej fáze.

## Cieľ

Vlastník si vie cez `/admin/` upraviť všetok obsah na verejnej stránke — texty, fotky, ceny, kontakty, balíčky, sociálne siete, otváracie hodiny, FAQ — bez potreby editovať PHP súbory ani volať vývojára.

Dnes je obsah rozdrobený: časť v `private/templates/sections/*.php` (texty), časť v `config/config.php` (social URLs, kontakty), časť v DB (balíčky, otváracie hodiny). Treba to konsolidovať do jedného edit point-u.

---

## Architektúra

### Nová tabuľka `content_blocks`

```sql
CREATE TABLE content_blocks (
    block_key      VARCHAR(80) PRIMARY KEY,
    label          VARCHAR(120) NOT NULL,       -- Human-readable name in admin UI
    content_type   ENUM('text','html','image','json') NOT NULL DEFAULT 'text',
    value          MEDIUMTEXT NOT NULL,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by     VARCHAR(60) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Seed:
```sql
INSERT INTO content_blocks (block_key, label, content_type, value) VALUES
  ('hero.title',          'Hero – nadpis',           'text', 'Detský svet KUKO'),
  ('hero.subtitle',       'Hero – podtitul',         'text', 'pre radosť detí & pohodu rodičov'),
  ('hero.cta_primary',    'Hero – tlačidlo 1',       'text', 'Rezervovať oslavu'),
  ('about.lead',          'O nás – úvodný odsek',    'html', 'KUKO je interiérové detské ihrisko…'),
  ('about.card_1.title',  'O nás – karta 1 nadpis',  'text', 'Bezpečný, čistý a hravý priestor'),
  ('about.card_1.body',   'O nás – karta 1 popis',   'text', 'kde sa deti môžu vyšantiť…'),
  -- … 4 karty × 2 polia
  ('cennik.lead',         'Cenník – úvod',           'text', 'Chceme, aby bol čas u nás dostupný…'),
  ('cennik.item_1.label', 'Cenník – riadok 1',       'text', 'Dieťa do 1 roku'),
  ('cennik.item_1.price', 'Cenník – cena 1',         'text', 'ZADARMO'),
  -- … 3 riadky × 2 polia
  ('kontakt.address',     'Adresa',                  'text', 'Bratislavská 141, 921 01 Piešťany'),
  ('kontakt.phone',       'Telefón',                 'text', '+421 915 319 934'),
  ('kontakt.email',       'E-mail',                  'text', 'info@kuko-detskysvet.sk'),
  ('kontakt.hours',       'Otváracie hodiny – text', 'text', 'Pondelok – Nedeľa: 9:00 – 20:00'),
  ('kontakt.maps_lat',    'GPS lat',                 'text', '48.58128'),
  ('kontakt.maps_lon',    'GPS lon',                 'text', '17.81575'),
  ('social.facebook',     'Facebook URL',            'text', 'https://www.facebook.com/profile.php?id=61587744202735'),
  ('social.instagram',    'Instagram URL',           'text', 'https://www.instagram.com/kuko.detskysvet'),
  ('footer.copyright',    'Footer copyright',        'text', 'Copyright © {{year}} KUKO-detskysvet.sk');
```

### Nová tabuľka `gallery_photos`

```sql
CREATE TABLE gallery_photos (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    filename   VARCHAR(180) NOT NULL,           -- e.g. "galeria_xyz.jpg"
    webp       VARCHAR(180) NULL,
    alt_text   VARCHAR(255) NOT NULL,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
```

### Existing tables to extend

```sql
ALTER TABLE packages
  ADD COLUMN description       TEXT NULL AFTER name,
  ADD COLUMN price_text         VARCHAR(40) NULL,     -- e.g. "120 – 150 € / balíček"
  ADD COLUMN kids_count_text    VARCHAR(40) NULL,     -- e.g. "do 10"
  ADD COLUMN duration_text      VARCHAR(40) NULL,     -- e.g. "2 hodiny"
  ADD COLUMN included_json      JSON NULL,            -- ["Vyhradený stôl pre rodičov", "Občerstvenie…", …]
  ADD COLUMN accent_color       VARCHAR(20) NULL;     -- "blue" | "purple" | "yellow"
```

### Nová `ContentBlocksRepo` + `MediaRepo` lib

```php
namespace Kuko;

final class ContentBlocksRepo {
    public function get(string $key, string $default = ''): string;
    public function set(string $key, string $value, string $updatedBy): void;
    public function all(): array;
    public function listGrouped(): array;  // groups by prefix before "." for admin UI
}

final class MediaRepo {
    public function uploadGalleryPhoto(array $uploadedFile, string $alt): array; // returns photo row
    public function deleteGalleryPhoto(int $id): void;
    public function reorderGalleryPhotos(array $idOrder): void;
    public function setVisibility(int $id, bool $visible): void;
}
```

### Templates: `Kuko\Content` helper

```php
namespace Kuko;

final class Content {
    private static ?ContentBlocksRepo $repo = null;
    public static function get(string $key, string $fallback = ''): string;  // cached for request
}
```

Use in templates:
```php
<h1><?= e(Content::get('hero.title', 'Detský svet KUKO')) ?></h1>
```

Templates retain hardcoded fallbacks so deployment with empty DB still renders.

---

## Admin UI

### Routes

| Path | View |
|---|---|
| `/admin/content` | Zoznam všetkých `content_blocks` skupinovaný (Hero / O nás / Cenník / Oslavy / Kontakt / Footer / Sociálne / FAQ) |
| `/admin/content/{key}` | Inline edit form. HTML editor pre `content_type=html` (TinyMCE alebo plain textarea) |
| `/admin/gallery` | Náhľady fotiek, drag-and-drop reorder, „pridať novú", „skryť", „zmazať", inline edit ALT |
| `/admin/gallery/upload` | POST handler — multipart upload, validácia (max 5 MB, image/* MIME), auto-WebP konverzia, sort_order = MAX+1 |
| `/admin/packages` (rozšírené) | Pridať polia description, price_text, included_json (zoznam s + button), accent_color picker, kids_count_text, duration_text |
| `/admin/hours` (existujúce) | Bez zmeny |
| `/admin/social` | Form pre FB, IG, prípadne TikTok URL — momentálne v config, presunúť do `content_blocks` |
| `/admin/seo` | **Detailne nižšie ↓** — globálne + per-page meta title/description/OG, indexing toggle |
| `/admin/maintenance` | **Detailne nižšie ↓** — toggle maintenance + zmena hesla |
| `/admin/users` | Správa admin používateľov (pridať/zmazať/zmena hesla) — momentálne ručná úprava `.htpasswd` |

### UI patterns

- **Inline edit:** klik na text → input/textarea → Enter alebo „Uložiť" tlačidlo. Cancel cez ESC.
- **Group view:** karty pre každú sekciu (Hero, O nás, Cenník, …) s zoznamom kľúčov a posledným update.
- **Preview link:** vedľa každého bloku ikona „pozrieť na frontend" — otvorí v novej karte URL + scrolluje na anchor.
- **Image upload:** drag-and-drop zóna, progress bar, automatický crop/resize (max 2000×2000), WebP variant.

---

## Migration path z hardcoded → DB

1. **Migrácia 005**: vytvor `content_blocks`, `gallery_photos`, ALTER `packages`. Seed s existujúcimi hodnotami z templates.
2. **Lib code**: `ContentBlocksRepo`, `MediaRepo`, `Content` helper.
3. **Templates**: refactor `sections/*.php` aby používali `Content::get()` s hardcoded fallbacky. Bez DB sa stránka stále vykreslí.
4. **Admin UI**: `/admin/content` ako prvý — najprostredie pre vlastníka.
5. **Galéria**: `/admin/gallery` druhá — fotky sú najčastejšie menené.
6. **Balíčky rozšírenie**: pridať polia + edit v admine.
7. **Sociálne** + maintenance + SEO admin: doplnkové.

## Implementation breakdown

| Block | Tasks | Effort |
|---|---|---|
| Schema + repos + helper | 5 | 1 deň |
| Templates refactor s fallbackmi | 8 | 1 deň |
| `/admin/content` group view + inline edit | 6 | 1,5 dňa |
| `/admin/gallery` upload/reorder/delete | 8 | 1,5 dňa |
| Packages rozšírenie | 4 | ½ dňa |
| `/admin/seo` global + per-page (detailne nižšie) | 6 | 1 deň |
| `/admin/users` správa | 4 | ½ dňa |
| `/admin/maintenance` toggle (detailne nižšie) | 3 | ½ dňa |
| **Spolu** | **44** | **~8 dní** |

---

## Maintenance + SEO admin (detail)

### `/admin/maintenance` — toggle UI

Momentálne sa maintenance prepína editom `config/config.php` (`maintenance` flag + `maintenance_password`). Pre vlastníka chceme:

- **Toggle "Maintenance mode" ON/OFF** — checkbox/switch v admine. Pri prepnutí sa zapíše do DB tabuľky `settings` (key `maintenance.enabled`, value `1`/`0`).
- **Zmena maintenance hesla** — pole "Nové heslo" + confirm. Hash sa neukladá (heslo musí byť plaintext, lebo cookie sa generuje cez `hash_equals` proti rovnakej hodnote pri každom requeste). Bude v settings table.
- **Status indicator** — vizuálna informácia o aktuálnom stave: zelený "LIVE" alebo žltý "MAINTENANCE ON od HH:MM".
- **Bezpečnostná poistka** — pri toggle ON sa hlási dialóg „Naozaj zapnúť? Verejnosť uvidí maintenance page." Pri toggle OFF tiež confirmation, aby owner nevypol nedopatrením pred otestovaním.
- **Audit log** — každý toggle sa zapíše do `admin_actions` (action=`maintenance_toggle`, payload obsahuje from→to a admin user).

**Migration:** `settings` tabuľka už existuje. Stačí UPSERT 2 nových kľúčov (`maintenance.enabled`, `maintenance.password`). Hodnoty z config sa pri prvom load-e migrujú do DB (one-shot).

**Code touch:**
- `Maintenance::enabled()` → čítať zo settings cache, nie z config
- `Maintenance::passwordMatches()` → settings.maintenance_password
- `Auth::user()` aj počas maintenance prejde (login je whitelisted, bypass cookie funguje aj pri DB-driven flag-u)

### `/admin/seo` — meta editor

Momentálne sú meta title + description per stránka hardcoded v PHP templates (`pages/home.php`, `pages/privacy.php`, `pages/reservation.php`). Treba presunúť do DB tak, aby owner mohol zmeniť bez deploy.

**Fields per page:**
- `seo.home.title` (default „KUKO detský svet — herňa a kaviareň v Piešťanoch")
- `seo.home.description` (default „Detská herňa a kaviareň v Piešťanoch…")
- `seo.home.og_image` (URL alebo upload, default `/assets/img/og-cover.jpg`)
- `seo.rezervacia.title` (default „Rezervácia oslavy — KUKO detský svet")
- `seo.rezervacia.description`
- `seo.privacy.title`
- `seo.privacy.description`
- `seo.default.title` (fallback ak per-page chýba)
- `seo.default.description`

**Global:**
- `seo.public_indexing` (toggle s warning „Po zapnutí budú stránky indexovateľné v Google. Vypni len ak vieš čo robíš.") — nahradí `config.app.public_indexing`
- `seo.og_image_default` — fallback OG image
- `seo.robots_extras` — voliteľné directives navyše (napr. `Disallow: /admin/`)

**Code touch:**
- `head.php` → `Content::get('seo.{pageType}.title', $fallback)`
- `Content::get()` cache → invalidácia pri save z admin UI

**UI:**
- Tabuľka per stránka s 2 textovými poliami (title 60 znakov limit + counter, description 160 znakov + counter)
- Live preview „How will this look in Google search results?" — sniepet preview vpravo
- "Reset to default" button per pole
- Bulk save tlačidlo

**Validácia:**
- Title 30–60 znakov ideálne (warning ak mimo)
- Description 120–160 znakov ideálne (warning ak mimo)

**Audit:** každé save → `admin_actions` so payload-om diff (čo sa zmenilo).

### Sub-tasks summary

| Block | Tasks |
|---|---|
| Maintenance toggle UI | 3 (settings read/write, UI form, audit log) |
| SEO global settings | 2 (indexing flag z DB, fallback titles/descriptions) |
| SEO per-page editor | 4 (DB schema, edit form, live preview snippet, validation) |
| **Spolu (pridané)** | **9 (cca 1,5 dňa navyše)** |

---

## Odložené (Phase 2 — po hlavnom CMS)

Tieto boli explicitne mimo scope hlavnej CMS iterácie (spec
`docs/specs/2026-05-15-admin-cms-design.md`), ale **patria do plánu na neskôr**
— nezabudnúť, len nie teraz:

- **Privacy page text editor** — `/admin` editovanie `/ochrana-udajov`. Legal
  text, ostáva PHP súbor. Pridať keď bude treba meniť GDPR znenie cez UI.
  Riziko: needá sa „pokaziť" právny text WYSIWYG-om → radšej štruktúrovaný editor
  alebo verzionovaný markdown. ~½ dňa.
- **Mail šablóny editor** — `/admin/mail-templates` pre úpravu
  reservation_admin/customer/confirmed/cancelled HTML+text. Tokeny ako
  `{{name}}`, `{{date}}` placeholder system. ~1 deň.
- **`/rezervacia` UI texty** — step labels, help texty, success správa cez
  content_blocks (`rezervacia.step1.title`, …). Drobné, ~½ dňa.
- **`/admin/users`** — správa viacerých admin používateľov (pridať/zmazať/
  reset hesla), nahradí ručný `.htpasswd`. ~½ dňa.
- **Plné verziovanie + rollback obsahu** — každá zmena bloku ukladá verziu,
  možnosť vrátiť. Audit log + `/admin/log` (v hlavnom scope) stačí na prehľad;
  rollback je nadstavba. ~1,5 dňa.
- **Live preview** — real-time náhľad zmeny pred uložením (iframe split-view).
  V hlavnom scope len „otvoriť web v novej karte" link. ~1 deň.

## Out of scope úplne (mimo plánu)

- Multi-jazyčnosť (len SK)
- Page builder / drag-drop layout sekcií
- Newsletter manažment
- Public reviews / hodnotenia
- Online platby
- SMS notifikácie

## Priorita

Pre vlastníka KUKO je tento CMS pravdepodobne najdôležitejší krok po nasadení — bez neho sa každá zmena textu volá vývojárovi. Odporúčam ako **NEXT najväčšiu iteráciu** po SEO+security wrap-upe a otestovanej rezervačnej flow s reálnymi e-mailami.
