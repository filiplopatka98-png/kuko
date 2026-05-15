<?php
// private/scripts/seed-cms.php — one-shot idempotent: content_blocks + gallery_photos
// + maintenance/SEO settings from hardcoded/config. Safe to run repeatedly.
declare(strict_types=1);

require __DIR__ . '/../lib/autoload.php';
\Kuko\Config::load(__DIR__ . '/../../config/config.php');
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
    ['kontakt.email', 'Kontakt — e-mail', 'text', 'info@kuko-detskysvet.sk'],
    ['kontakt.hours', 'Kontakt — otváracie hodiny', 'text', 'Pondelok – Nedeľa: 9:00 – 20:00'],
    ['footer.copyright', 'Footer — copyright', 'text', 'Copyright © {{year}} KUKO-detskysvet.sk | Všetky práva vyhradené.'],
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
        ['galeria_5.jpg', 'galeria_5.webp', 'Hracia zóna v KUKO',                                          6],
    ];
    foreach ($photos as [$jpg, $webp, $alt, $sort]) {
        $db->execStmt('INSERT INTO gallery_photos (filename, webp, alt_text, sort_order) VALUES (?,?,?,?)',
            [$jpg, $webp, $alt, $sort]);
        echo "+ photo $jpg (#$sort)\n";
    }
} else { echo "= gallery already has $existing rows\n"; }

// Settings: maintenance + SEO defaults (only if key absent)
$s = new \Kuko\SettingsRepo($db);
// NOTE: dual source of truth — the seo.* values below are duplicated as hardcoded
// fallbacks in head.php (and the section templates, Task 9). Edit BOTH places to avoid drift.
$seed = [
    'maintenance.enabled'  => \Kuko\Config::get('app.maintenance', false) ? '1' : '0',
    'maintenance.password' => (string) \Kuko\Config::get('app.maintenance_password', ''),
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
    'seo.privacy.description' => 'Zásady spracovania osobných údajov a cookies na webe kuko-detskysvet.sk.',
];
foreach ($seed as $k => $v) {
    if ($s->get($k) === null) { $s->set($k, $v); echo "+ setting $k\n"; }
    else { echo "= skip setting $k\n"; }
}
echo "seed done\n";
