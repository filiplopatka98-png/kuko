<?php
/** @var array<int,array<string,mixed>>|null $packages */
$packages = $packages ?? [];

/**
 * Per-package presentation defaults (used as a per-field fallback when the
 * matching column in `packages` is empty). The admin (/admin/packages) can
 * override every one of these fields independently — there is no all-or-
 * nothing gate. An empty field falls back to the default below; a non-empty
 * field wins. Keep these in sync with the seed-cms.php packages seed.
 */
$packageIcons = [
    'mini'   => '/assets/icons/badge-balloon.svg',
    'maxi'   => '/assets/icons/badge-balloons.svg',
    'closed' => '/assets/icons/badge-crown.svg',
];
$defaults = [
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
$iconFor = static fn(string $code): string => $packageIcons[$code] ?? '/assets/icons/badge-balloon.svg';

/* Build the ordered render list. Prefer DB order (listActive); otherwise
   fall back to the 3 default codes so the section always renders. */
if (!empty($packages)) {
    $renderList = [];
    foreach ($packages as $p) {
        $renderList[] = ['code' => (string) ($p['code'] ?? ''), 'row' => $p];
    }
} else {
    $renderList = [
        ['code' => 'mini',   'row' => null],
        ['code' => 'maxi',   'row' => null],
        ['code' => 'closed', 'row' => null],
    ];
}

/** Pick a non-empty DB value, else the default, else ''. */
$pick = static function (?array $row, ?array $def, string $key): string {
    $v = $row[$key] ?? null;
    if (is_string($v) && $v !== '') return $v;
    $d = $def[$key] ?? null;
    return is_string($d) ? $d : '';
};

$articles = [];
foreach ($renderList as $entry) {
    $code = $entry['code'];
    $row  = $entry['row'];
    $def  = $defaults[$code] ?? null;
    if ($def === null && $row === null) continue;

    $name        = $pick($row, $def, 'name');
    $description = $pick($row, $def, 'description');
    $priceText   = $pick($row, $def, 'price_text');
    $kidsText    = $pick($row, $def, 'kids_count_text');
    $durText     = $pick($row, $def, 'duration_text');

    $accent = $pick($row, $def, 'accent_color');
    if (!in_array($accent, ['blue', 'purple', 'yellow'], true)) {
        $accent = $def['accent_color'] ?? 'blue';
    }

    $included = null;
    if (!empty($row['included_json'])) {
        $j = json_decode((string) $row['included_json'], true);
        if (is_array($j) && $j !== []) $included = $j;
    }
    if ($included === null) $included = $def['included'] ?? [];

    ob_start();
    ?>
<article class="package package--<?= e($accent) ?>">
        <span class="package__badge" aria-hidden="true"><img src="<?= e(\Kuko\Asset::url($iconFor($code))) ?>" alt="" width="36" height="36"></span>
        <header class="package__head"><h3><?= e($name) ?></h3></header>
        <div class="package__desc"><?= $description ?></div>
        <ul class="package__meta">
          <?php if ($kidsText !== ''): ?>
          <li><span class="ic" aria-hidden="true"><img src="<?= e(\Kuko\Asset::url('/assets/icons/little-kid.svg')) ?>" alt="" width="18" height="18"></span> Počet detí: <?= e($kidsText) ?></li>
          <?php endif; ?>
          <?php if ($durText !== ''): ?>
          <li><span class="ic" aria-hidden="true"><img src="<?= e(\Kuko\Asset::url('/assets/icons/clock.svg')) ?>" alt="" width="18" height="18"></span> Časový harmonogram: <?= e($durText) ?></li>
          <?php endif; ?>
        </ul>
        <?php if ($priceText !== ''): ?>
        <p class="package__price"><?= e($priceText) ?></p>
        <?php endif; ?>
        <ul class="package__incl">
          <?php foreach ($included as $item): ?>
          <li><?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn--straddle package__cta" href="/rezervacia?balicek=<?= e($code) ?>">Rezervovať balíček</a>
      </article>
<?php
    $articles[] = trim((string) ob_get_clean());
}
?>
<section id="oslavy" class="section section--oslavy" data-reveal>
  <div class="container">
    <h2>Detské KUKO oslavy</h2>
    <p class="section__lead">Vyberte si balíček, ktorý vám sedí, a my sa postaráme o zvyšok.</p>
    <div class="packages-grid">
            <?= implode("\n\n      ", $articles) . "\n          " ?></div>
    <p class="oslavy__note"><?= e(\Kuko\Content::get('oslavy.note', '*Konečná cena závisí od možností prispôsobenia - Každý balíček si môžete upraviť podľa vašich predstáv: predĺženie času oslavy, výzdoba na mieru (téma, farby), catering pre deti aj rodičov, torta alebo sweet bar, špeciálne požiadavky…')) ?></p>
  </div>
</section>
