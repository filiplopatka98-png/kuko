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
| `/admin/seo` | Globálne SEO settings: meta title default, meta description default, OG image upload, indexing on/off |
| `/admin/maintenance` | Toggle maintenance mode + zmena hesla z UI (dnes len v config.php) |
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
| `/admin/seo` global settings | 3 | ½ dňa |
| `/admin/users` správa | 4 | ½ dňa |
| `/admin/maintenance` toggle | 2 | ½ dňa |
| **Spolu** | **40** | **~7 dní** |

## Out of scope (zatiaľ)

- Markdown editor pre privacy page (zatiaľ ostáva PHP súbor)
- Versioning obsahu (kto kedy čo zmenil — máme `admin_actions` audit log, stačí)
- Multi-jazyčnosť (len SK)
- Page builder / drag-drop layout
- Newsletter manažment
- Public reviews / hodnotenia
- Online platby
- SMS notifikácie

## Priorita

Pre vlastníka KUKO je tento CMS pravdepodobne najdôležitejší krok po nasadení — bez neho sa každá zmena textu volá vývojárovi. Odporúčam ako **NEXT najväčšiu iteráciu** po SEO+security wrap-upe a otestovanej rezervačnej flow s reálnymi e-mailami.
