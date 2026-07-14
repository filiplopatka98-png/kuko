<?php
// private/scripts/seed-cms.php — one-shot idempotent: content_blocks + gallery_photos
// + maintenance/SEO settings from hardcoded/config. Safe to run repeatedly.
declare(strict_types=1);

require_once __DIR__ . '/../lib/autoload.php';
if (!\Kuko\Config::isLoaded()) {
    \Kuko\Config::load(__DIR__ . '/../../config/config.php');
}
$db = \Kuko\Db::fromConfig();
$cb = new \Kuko\ContentBlocksRepo($db);

// NOTE: dual source of truth — these values are duplicated as hardcoded fallbacks
// in the section templates (Task 9) and head.php. Edit BOTH places together to avoid drift.
$blocks = [
    // [block_key, label, content_type, value] — values copied verbatim from templates
    ['hero.title', 'Hero — nadpis', 'text', 'Detský svet KUKO'],
    ['hero.subtitle', 'Hero — podtitul', 'text', 'pre radosť detí & pohodu rodičov'],
    ['hero.tagline', 'Hero — tretí riadok (tagline)', 'text', 'Bezpečné a hravé miesto pre vaše deti v Piešťanoch'],
    ['about.lead', 'O nás — úvodný odsek', 'html', '<p>KUKO je interiérové detské ihrisko spojené s kaviarňou v Piešťanoch, vytvorené pre radosť detí a pohodlie rodičov. Mysleli sme na všetko, čo robí detský svet skutočne príjemným:</p>'],
    ['about.card1', 'O nás — karta 1', 'html', '<strong>Bezpečný, čistý a hravý priestor,</strong><br>kde sa deti môžu vyšantiť, objavovať a tráviť čas aktívne.'],
    ['about.card2', 'O nás — karta 2', 'html', 'Rodičia si zatiaľ môžu vychutnať <strong>kvalitnú kávu a chvíľku oddychu</strong> v príjemnom prostredí.'],
    ['about.card3', 'O nás — karta 3', 'html', '<strong>Ideálne miesto na stretnutie</strong> s priateľmi či rodinou, alebo len chvíľu pre seba, zatiaľ čo sa deti zabavia.'],
    ['about.card4', 'O nás — karta 4', 'html', '<strong>Organizujeme aj detské oslavy,</strong> ktoré pripravíme s dôrazom na radosť detí a bezstarostnosť pre rodičov.'],
    ['cennik.lead', 'Cenník — úvod', 'text', 'Chceme, aby bol čas strávený u nás dostupný a príjemný pre každého.'],
    ['cennik.item1.label', 'Cenník — riadok 1', 'text', 'Dieťa do 1 roku'],
    ['cennik.item1.price', 'Cenník — cena 1', 'text', 'ZADARMO'],
    ['cennik.item2.label', 'Cenník — riadok 2', 'text', 'Dieťa od 1 roku'],
    ['cennik.item2.price', 'Cenník — cena 2', 'text', '5,00 € / hod'],
    ['cennik.item3.label', 'Cenník — riadok 3', 'text', 'Dieťa od 1 roku neobmedzene'],
    ['cennik.item3.price', 'Cenník — cena 3', 'text', '15,00 €'],
    ['kontakt.address', 'Kontakt — adresa', 'text', 'Bratislavská 141, 921 01 Piešťany'],
    ['kontakt.phone', 'Kontakt — telefón', 'text', '+421 915 319 934'],
    ['kontakt.email', 'Kontakt — e-mail', 'text', 'info@kukodetskysvet.sk'],
    ['kontakt.hours', 'Kontakt — otváracie hodiny', 'text', 'Pondelok – Nedeľa: 9:00 – 20:00'],
    ['oslavy.note', 'Oslavy — poznámka pod balíčkami', 'text', '*Konečná cena závisí od možností prispôsobenia - Každý balíček si môžete upraviť podľa vašich predstáv: predĺženie času oslavy, výzdoba na mieru (téma, farby), catering pre deti aj rodičov, torta alebo sweet bar, špeciálne požiadavky…'],
    ['footer.copyright', 'Footer — copyright', 'text', 'Copyright © {{year}} KUKOdetskysvet.sk | Všetky práva vyhradené.'],
    ['cta.faq.heading', 'CTA (FAQ) — nadpis', 'text', 'Plánujete oslavu pre svoje dieťa?'],
    ['cta.faq.text', 'CTA (FAQ) — text', 'text', 'Rezervujte si termín online za pár minút — vyberte balíček, dátum a čas.'],
    ['cta.reservation.heading', 'CTA (rezervácia) — nadpis', 'text', 'Páči sa vám u nás?'],
    ['cta.reservation.text', 'CTA (rezervácia) — text', 'text', 'Rezervujte si oslavu v KUKO — vyberte balíček, dátum a čas v 3 krokoch.'],
    // Editable pages (admin "Stránky") — values copied verbatim from the
    // hardcoded fallbacks in pages/privacy.php and pages/faq.php.
    ['privacy.body', 'Ochrana údajov — text', 'html', <<<'HTML'
    <h2 class="legal-h2">1. Prevádzkovateľ</h2>
    <p>Prevádzkovateľom webu kukodetskysvet.sk je KUKO detský svet, Bratislavská 141, 921 01 Piešťany, e-mail <a href="mailto:info@kukodetskysvet.sk">info@kukodetskysvet.sk</a>.</p>

    <h2 class="legal-h2">2. Rozsah a účel spracovania</h2>
    <p>Pri rezervácii oslavy spracúvame údaje, ktoré ste nám poskytli prostredníctvom formulára: meno, telefón, e-mail, požadovaný dátum a čas oslavy, počet detí a poznámku. Tieto údaje spracúvame výlučne na účel vybavenia vašej rezervácie a kontaktu vo veci oslavy.</p>

    <h2 class="legal-h2">3. Právny základ</h2>
    <p>Spracovanie prebieha na základe vašej žiadosti o rezerváciu (predzmluvné konanie podľa čl. 6 ods. 1 písm. b GDPR) a nášho oprávneného záujmu zabezpečiť funkčnosť rezervačného systému (čl. 6 ods. 1 písm. f GDPR).</p>

    <h2 class="legal-h2">4. Doba uchovávania</h2>
    <p>Údaje uchovávame po dobu potrebnú na vybavenie rezervácie a 6 mesiacov po jej skončení, následne sú anonymizované alebo vymazané.</p>

    <h2 class="legal-h2">5. Cookies</h2>
    <p>Web používa nevyhnutné cookies a — len s vaším súhlasom — Google reCAPTCHA (ochrana formulára pred spamom), prípadne v budúcnosti analytické či marketingové nástroje. Podrobný prehľad jednotlivých cookies a správu svojho súhlasu nájdete v <a href="/zasady-cookies">Zásadách používania cookies</a>.</p>

    <h2 class="legal-h2">6. Vaše práva</h2>
    <p>V súlade s GDPR máte právo na prístup k svojim údajom, ich opravu, vymazanie, obmedzenie spracúvania, prenosnosť, ako aj právo namietať a podať sťažnosť na Úrade na ochranu osobných údajov SR. Ohľadom vašich práv nás môžete kontaktovať na <a href="mailto:info@kukodetskysvet.sk">info@kukodetskysvet.sk</a>.</p>

    <p class="legal-back"><a href="/">&larr; Späť na domov</a></p>
HTML],
    ['cookies.body', 'Zásady cookies — text', 'html', <<<'HTML'
    <h2 class="legal-h2">1. Čo sú cookies</h2>
    <p>Cookies sú malé textové súbory, ktoré sa ukladajú vo vašom prehliadači pri návšteve webu. Slúžia na zabezpečenie základnej funkčnosti a — len s vaším súhlasom — na ďalšie účely uvedené nižšie.</p>

    <h2 class="legal-h2">2. Aké cookies používame</h2>
    <ul class="cookie-list">
      <li><strong>PHPSESSID</strong> — technická relácia (chod webu) · trvanie: relácia · kategória: <em>Nevyhnutné</em></li>
      <li><strong>kuko_cookie_consent</strong> — uloženie vášho rozhodnutia o cookies · trvanie: ~6 mesiacov · kategória: <em>Nevyhnutné</em></li>
      <li><strong>_GRECAPTCHA</strong> — Google reCAPTCHA, ochrana rezervačného formulára pred spamom · trvanie: ~6 mesiacov · kategória: <em>reCAPTCHA (so súhlasom)</em></li>
    </ul>
    <p><strong>Analytické a marketingové cookies</strong> momentálne nepoužívame. Súhlasové kategórie sú pripravené dopredu — ak v budúcnosti pridáme napríklad Google Analytics alebo marketingové nástroje, načítajú sa iba ak ste danú kategóriu povolili.</p>

    <h2 class="legal-h2">3. Právny základ</h2>
    <p>Nevyhnutné cookies používame na základe oprávneného záujmu zabezpečiť funkčnosť webu (čl. 6 ods. 1 písm. f GDPR) a nevyžadujú váš súhlas. Ostatné kategórie (reCAPTCHA, analytické, marketingové) spracúvame výlučne na základe vášho súhlasu (čl. 6 ods. 1 písm. a GDPR), ktorý môžete kedykoľvek odvolať.</p>

    <h2 class="legal-h2">4. Tretie strany</h2>
    <p>Google reCAPTCHA je služba spoločnosti Google. Po udelení súhlasu môže Google získať údaje o vašom správaní na stránke. Viac: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.</p>

    <h2 class="legal-h2">5. Ako spravovať súhlas</h2>
    <p>Pri prvej návšteve sa zobrazí cookie lišta, kde môžete súhlas udeliť, odmietnuť alebo si vybrať jednotlivé kategórie cez „Nastavenia". Svoje rozhodnutie môžete kedykoľvek zmeniť kliknutím na <strong>„Cookie nastavenia"</strong> v pätičke webu.</p>

    <h2 class="legal-h2">6. Kontakt</h2>
    <p>V prípade otázok k spracúvaniu cookies nás kontaktujte na <a href="mailto:info@kukodetskysvet.sk">info@kukodetskysvet.sk</a>. Spracúvanie osobných údajov upravuje <a href="/ochrana-udajov">Ochrana osobných údajov</a>.</p>

    <p class="legal-back"><a href="/">&larr; Späť na domov</a></p>

HTML],
    ['faq.intro', 'FAQ — úvod', 'html', <<<'HTML'
<p class="section__lead">Najčastejšie veci, ktoré sa nás rodičia pýtajú pred prvou návštevou.</p>
HTML],
    // NOTE: the FAQ questions/answers are no longer a content block — they
    // are a structured repeater stored in the `faq.items` SETTING (seeded
    // below from \Kuko\Faq::defaults(), the single source of truth shared
    // with pages/faq.php + head.php JSON-LD). Any legacy faq.items
    // content_block row on an existing DB is now orphaned/unused; left in
    // place (do not delete prod data) — it's harmless.
];
foreach ($blocks as [$k, $label, $type, $val]) {
    if ($cb->get($k) === null) { $cb->set($k, $val, $type, 'seed', $label); echo "+ block $k\n"; }
    else { echo "= skip $k\n"; }
}

// Gallery seed (only if empty) — static images galeria_1..5.jpg with their ALT texts,
// plus a 6th row reusing galeria_5.{jpg,webp} so the homepage shows a full 3×2 grid.
// The 6th is a temporary reuse; the owner replaces it via /admin gallery later.
$existing = (int) ($db->one('SELECT COUNT(*) AS c FROM gallery_photos')['c'] ?? 0);
if ($existing === 0) {
    $photos = [
        ['galeria_1.jpg', 'galeria_1.webp', 'Detský kútik KUKO — narodeninová oslava s tortou a balónmi', 1],
        ['galeria_2.jpg', 'galeria_2.webp', 'Herné prvky v detskom svete KUKO — šmykľavka a hracie zóny',   2],
        ['galeria_3.jpg', 'galeria_3.webp', 'Interiér KUKO — rodičia pri káve, deti sa hrajú',              3],
        ['galeria_4.jpg', 'galeria_4.webp', 'Detská oslava v KUKO — výzdoba a deti pri stole',              4],
        ['galeria_5.jpg', 'galeria_5.webp', 'Vnútorný priestor detskej herne KUKO Piešťany',               5],
    ];
    foreach ($photos as [$jpg, $webp, $alt, $sort]) {
        $db->execStmt('INSERT INTO gallery_photos (filename, webp, alt_text, sort_order) VALUES (?,?,?,?)',
            [$jpg, $webp, $alt, $sort]);
        echo "+ photo $jpg (#$sort)\n";
    }
} else { echo "= gallery already has $existing rows\n"; }

// Always-run idempotent insert of the 6th starter photo (sort_order = 6), a reuse
// of galeria_5.{jpg,webp} so the homepage shows a full 3×2 grid. This runs OUTSIDE
// the empty-table guard above so it also lands on existing prod DBs that already
// have the original 5 rows. Keyed on sort_order = 6: re-running finds it present
// and skips, so no duplicate. The empty-table block above seeds only galeria_1..5.
$hasSixth = (int) ($db->one('SELECT COUNT(*) AS c FROM gallery_photos WHERE sort_order = 6')['c'] ?? 0);
if ($hasSixth === 0) {
    $db->execStmt('INSERT INTO gallery_photos (filename, webp, alt_text, sort_order) VALUES (?,?,?,?)',
        ['galeria_5.jpg', 'galeria_5.webp', 'Hracia zóna v KUKO', 6]);
    echo "+ photo galeria_5.jpg (#6) — 6th starter inserted\n";
} else { echo "= 6th starter photo (sort_order=6) already present\n"; }

// Homepage gallery default: if nothing is curated yet (zero on_homepage=1),
// mark the first 6 visible photos (by sort_order,id) so a fresh/existing prod
// DB shows a sensible full grid. Guarded so re-running does not churn or
// override an owner's later selection.
$onHome = (int) ($db->one('SELECT COUNT(*) AS c FROM gallery_photos WHERE on_homepage = 1')['c'] ?? 0);
if ($onHome === 0) {
    $first6 = $db->all('SELECT id FROM gallery_photos WHERE is_visible = 1 ORDER BY sort_order, id LIMIT 6');
    foreach ($first6 as $r) {
        $db->execStmt('UPDATE gallery_photos SET on_homepage = 1 WHERE id = ?', [(int) $r['id']]);
    }
    echo '+ homepage gallery default — marked ' . count($first6) . " visible photo(s) on_homepage\n";
} else {
    echo "= homepage gallery already curated ($onHome on_homepage)\n";
}

// Settings: maintenance + SEO defaults (only if key absent)
$s = new \Kuko\SettingsRepo($db);
// NOTE: dual source of truth — the seo.* values below are duplicated as hardcoded
// fallbacks in head.php (and the section templates, Task 9). Edit BOTH places to avoid drift.
$seed = [
    'maintenance.enabled'  => \Kuko\Config::get('app.maintenance', false) ? '1' : '0',
    // Store the staff bypass password hashed (never plaintext in the DB). Empty
    // config → empty setting (bypass stays disabled until set in /admin).
    'maintenance.password' => (function () {
        $p = (string) \Kuko\Config::get('app.maintenance_password', '');
        return $p === '' ? '' : password_hash($p, PASSWORD_BCRYPT);
    })(),
    'seo.public_indexing'  => \Kuko\Config::get('app.public_indexing', false) ? '1' : '0',
    'seo.default.title'       => 'KUKO detský svet — herňa a kaviareň v Piešťanoch',
    'seo.default.description' => 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, kvalitná káva pre rodičov, oslavy na mieru. Otvorené Pon–Ne 9:00 – 20:00.',
    'seo.home.title'        => 'KUKO detský svet — herňa a kaviareň v Piešťanoch',
    'seo.home.description'  => 'Detská herňa a kaviareň v Piešťanoch. Bezpečný hravý priestor pre deti, kvalitná káva pre rodičov, oslavy na mieru. Otvorené Pon–Ne 9:00 – 20:00.',
    'seo.rezervacia.title'  => 'Rezervácia oslavy — KUKO detský svet',
    'seo.rezervacia.description' => 'Rezervujte si oslavu v KUKO detský svet. Vyberte balíček, dátum a čas v 3 krokoch.',
    'seo.faq.title'         => 'Časté otázky — KUKO detský svet',
    'seo.faq.description'   => 'Odpovede na najčastejšie otázky o detskej herni KUKO v Piešťanoch — ceny, oslavy, otváracie hodiny, vek detí, rezervácie.',
    'seo.privacy.title'     => 'Ochrana osobných údajov — KUKO detský svet',
    'seo.privacy.description' => 'Zásady spracovania osobných údajov a cookies na webe kukodetskysvet.sk.',
    'seo.cookies.title'     => 'Zásady používania cookies — KUKO detský svet',
    'seo.cookies.description' => 'Aké cookies používame na webe kukodetskysvet.sk, na čo slúžia a ako spravovať svoj súhlas.',
    'seo.gallery.title'     => 'Fotogaléria — KUKO detský svet',
    'seo.gallery.description' => 'Pozrite si fotografie z detskej herne a osláv v KUKO Piešťany.',
    // FAQ repeater source of truth — migrates the old 6 Q/A into the
    // structured `faq.items` setting. \Kuko\Faq::defaults() is the SAME
    // array Faq::items() falls back to, so seed === in-app default.
    'faq.items' => json_encode(\Kuko\Faq::defaults(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
];
foreach ($seed as $k => $v) {
    if ($s->get($k) === null) { $s->set($k, $v); echo "+ setting $k\n"; }
    else { echo "= skip setting $k\n"; }
}

// === Packages (mini/maxi/closed) ===
// Migration 005 added description/price_text/kids_count_text/duration_text/
// included_json/accent_color but left them NULL on the 3 existing rows. Without
// this seed, the homepage falls back to verbatim hardcoded text and admin
// edits do nothing visible. We fill ONLY empty fields per row, so any admin
// edit already in place is preserved (insert-where-empty semantics).
$packageDefaults = [
    'mini' => [
        'name'            => 'Oslava KUKO MINI',
        'accent_color'    => 'blue',
        'description'     => 'Bázový balíček pre menšie oslavy s priateľmi. Zahŕňa prenájom časti herne na 2 hodiny.',
        'price_text'      => '120 – 150 € / balíček',
        'kids_count_text' => 'do 10',
        'duration_text'   => '2 hodiny',
        'included'        => ['Vyhradený stôl pre rodičov', 'Občerstvenie pre deti', 'Animátorka v cene'],
    ],
    'maxi' => [
        'name'            => 'Oslava KUKO MAXI',
        'accent_color'    => 'purple',
        'description'     => 'Pre väčšie deti a väčšie skupiny. Plne vybavená oslava s programom.',
        'price_text'      => '220 – 260 € / balíček',
        'kids_count_text' => 'do 20',
        'duration_text'   => '3 hodiny',
        'included'        => ['Vyhradený priestor', 'Občerstvenie + nápoje', 'Animátorka + program', 'Tematická výzdoba'],
    ],
    'closed' => [
        'name'            => 'Uzavretá spoločnosť',
        'accent_color'    => 'yellow',
        'description'     => 'Doprajte svojmu dieťaťu oslavu, na ktorú bude ešte dlho spomínať. Pri uzavretej spoločnosti máte celé KUKO len pre seba — v pokojnej a príjemnej atmosfére. Deti si môžu naplno užiť všetky herné prvky a spoločné chvíle s kamarátmi, zatiaľ čo rodičia si vychutnajú oslavu bez stresu a zbytočného zhonu. Počas celej oslavy je vám k dispozícii aj náš personál, ktorý sa postará o pohodlie a hladký priebeh.',
        'price_text'      => '350 € / balíček',
        'kids_count_text' => 'neobmedzene',
        'duration_text'   => '4 hodiny',
        'included'        => ['Celá herňa len pre vás', 'Personál k dispozícii', 'Pokojná atmosféra bez verejnosti', 'Plný komfort pre rodičov'],
    ],
];
$pkgRepo = new \Kuko\PackagesRepo($db);
foreach ($packageDefaults as $code => $d) {
    $row = $pkgRepo->find($code);
    if ($row === null) { echo "= skip package $code (no row)\n"; continue; }
    $changed = [];
    $merged = [
        'name'            => ((string) ($row['name']            ?? '')) !== '' ? (string) $row['name']            : $d['name'],
        'duration_min'    => (int)    ($row['duration_min']    ?? 120),
        'blocks_full_day' => (int)    ($row['blocks_full_day'] ?? 0),
        'is_active'       => (int)    ($row['is_active']       ?? 1),
        'sort_order'      => (int)    ($row['sort_order']      ?? 0),
        'description'     => ((string) ($row['description']     ?? '')) !== '' ? (string) $row['description']     : $d['description'],
        'price_text'      => ((string) ($row['price_text']      ?? '')) !== '' ? (string) $row['price_text']      : $d['price_text'],
        'kids_count_text' => ((string) ($row['kids_count_text'] ?? '')) !== '' ? (string) $row['kids_count_text'] : $d['kids_count_text'],
        'duration_text'   => ((string) ($row['duration_text']   ?? '')) !== '' ? (string) $row['duration_text']   : $d['duration_text'],
        'included_json'   => ((string) ($row['included_json']   ?? '')) !== '' ? (string) $row['included_json']   : json_encode($d['included'], JSON_UNESCAPED_UNICODE),
        'accent_color'    => in_array((string) ($row['accent_color'] ?? ''), ['blue','purple','yellow'], true) ? (string) $row['accent_color'] : $d['accent_color'],
    ];
    foreach (['description','price_text','kids_count_text','duration_text','included_json','accent_color'] as $f) {
        if (((string) ($row[$f] ?? '')) === '' || ($f === 'accent_color' && !in_array((string) ($row[$f] ?? ''), ['blue','purple','yellow'], true))) {
            $changed[] = $f;
        }
    }
    if ($changed === []) { echo "= skip package $code (already filled)\n"; continue; }
    $pkgRepo->update($code, $merged);
    echo "+ package $code (filled: " . implode(',', $changed) . ")\n";
}

echo "seed done\n";
