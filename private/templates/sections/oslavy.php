<?php
$packages = $packages ?? [];

/* Map package code -> circular badge icon (assets). */
$packageIcons = [
    'mini'   => '/assets/icons/badge-balloon.svg',
    'maxi'   => '/assets/icons/badge-balloons.svg',
    'closed' => '/assets/icons/badge-crown.svg',
];
$iconFor = static function (string $code) use ($packageIcons): string {
    return $packageIcons[$code] ?? '/assets/icons/badge-balloon.svg';
};

/*
 * Per-package hardcoded fallback cards (verbatim original markup).
 * Trusted developer markup — output raw. Keyed by package `code`.
 * Byte-identical to the original static section so that when a
 * package has no extended fields it renders exactly as before.
 */
$hardcoded = [
    'mini' => <<<'HTML'
<article class="package package--blue">
        <span class="package__badge" aria-hidden="true"><img src="/assets/icons/badge-balloon.svg" alt="" width="36" height="36"></span>
        <header class="package__head"><h3>Oslava KUKO MINI</h3></header>
        <p class="package__desc">Bázový balíček pre menšie oslavy s priateľmi. Zahŕňa prenájom časti herne na 2 hodiny.</p>
        <ul class="package__meta">
          <li><span class="ic" aria-hidden="true">👶</span> Počet detí: do 10</li>
          <li><span class="ic" aria-hidden="true">⏰</span> Časový harmonogram: 2 hodiny</li>
        </ul>
        <p class="package__price">120 – 150 € / balíček</p>
        <ul class="package__incl">
          <li>✓ Vyhradený stôl pre rodičov</li>
          <li>✓ Občerstvenie pre deti</li>
          <li>✓ Animátorka v cene</li>
        </ul>
        <a class="btn btn--straddle package__cta" href="/rezervacia?balicek=mini">Rezervovať balíček</a>
      </article>
HTML,
    'maxi' => <<<'HTML'
<article class="package package--purple">
        <span class="package__badge" aria-hidden="true"><img src="/assets/icons/badge-balloons.svg" alt="" width="36" height="36"></span>
        <header class="package__head"><h3>Oslava KUKO MAXI</h3></header>
        <p class="package__desc">Pre väčšie deti a väčšie skupiny. Plne vybavená oslava s programom.</p>
        <ul class="package__meta">
          <li><span class="ic" aria-hidden="true">👶</span> Počet detí: do 20</li>
          <li><span class="ic" aria-hidden="true">⏰</span> Časový harmonogram: 3 hodiny</li>
        </ul>
        <p class="package__price">220 – 260 € / balíček</p>
        <ul class="package__incl">
          <li>✓ Vyhradený priestor</li>
          <li>✓ Občerstvenie + nápoje</li>
          <li>✓ Animátorka + program</li>
          <li>✓ Tematická výzdoba</li>
        </ul>
        <a class="btn btn--straddle package__cta" href="/rezervacia?balicek=maxi">Rezervovať balíček</a>
      </article>
HTML,
    'closed' => <<<'HTML'
<article class="package package--yellow">
        <span class="package__badge" aria-hidden="true"><img src="/assets/icons/badge-crown.svg" alt="" width="36" height="36"></span>
        <header class="package__head"><h3>Uzavretá spoločnosť</h3></header>
        <p class="package__desc">Doprajte svojmu dieťaťu oslavu, na ktorú bude ešte dlho spomínať. Pri uzavretej spoločnosti máte celé KUKO len pre seba — v pokojnej a príjemnej atmosfére. Deti si môžu naplno užiť všetky herné prvky a spoločné chvíle s kamarátmi, zatiaľ čo rodičia si vychutnajú oslavu bez stresu a zbytočného zhonu. Počas celej oslavy je vám k dispozícii aj náš personál, ktorý sa postará o pohodlie a hladký priebeh.</p>
        <ul class="package__meta">
          <li><span class="ic" aria-hidden="true">👶</span> Počet detí: neobmedzene</li>
          <li><span class="ic" aria-hidden="true">⏰</span> Časový harmonogram: 4 hodiny</li>
        </ul>
        <p class="package__price">350 € / balíček</p>
        <ul class="package__incl">
          <li>✓ Celá herňa len pre vás</li>
          <li>✓ Personál k dispozícii</li>
          <li>✓ Pokojná atmosféra bez verejnosti</li>
          <li>✓ Plný komfort pre rodičov</li>
        </ul>
        <a class="btn btn--straddle package__cta" href="/rezervacia?balicek=closed">Rezervovať balíček</a>
      </article>
HTML,
];

/*
 * Build the ordered render list. Prefer the DB-provided $packages
 * (listActive() order). If no DB rows at all, fall back to the three
 * static codes so the section always renders all 3 offerings.
 */
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

$articles = [];
foreach ($renderList as $entry) {
    $code = $entry['code'];
    $p    = $entry['row'];

    $hasExtended = $p !== null
        && !empty($p['price_text'])
        && !empty($p['description'])
        && !empty($p['included_json'])
        && !empty($p['accent_color']);

    if ($hasExtended) {
        $accent   = in_array($p['accent_color'], ['blue', 'purple', 'yellow'], true) ? $p['accent_color'] : 'blue';
        $included = json_decode((string) $p['included_json'], true);
        if (!is_array($included)) { $included = []; }

        ob_start();
        ?>
<article class="package package--<?= e($accent) ?>">
        <span class="package__badge" aria-hidden="true"><img src="<?= e(\Kuko\Asset::url($iconFor($code))) ?>" alt="" width="36" height="36"></span>
        <header class="package__head"><h3><?= e($p['name'] ?? '') ?></h3></header>
        <p class="package__desc"><?= $p['description'] ?></p>
        <ul class="package__meta">
          <?php if (!empty($p['kids_count_text'])): ?>
          <li><span class="ic" aria-hidden="true">👶</span> Počet detí: <?= e($p['kids_count_text']) ?></li>
          <?php endif; ?>
          <?php if (!empty($p['duration_text'])): ?>
          <li><span class="ic" aria-hidden="true">⏰</span> Časový harmonogram: <?= e($p['duration_text']) ?></li>
          <?php endif; ?>
        </ul>
        <p class="package__price"><?= e($p['price_text']) ?></p>
        <ul class="package__incl">
          <?php foreach ($included as $item): ?>
          <li>✓ <?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn btn--straddle package__cta" href="/rezervacia?balicek=<?= e($code) ?>">Rezervovať balíček</a>
      </article>
<?php
        $articles[] = trim((string) ob_get_clean());
    } elseif (isset($hardcoded[$code])) {
        $articles[] = $hardcoded[$code];
    }
    // Unknown code with no extended data is skipped safely.
}
?>
<section id="oslavy" class="section section--oslavy" data-reveal>
  <div class="container">
    <h2>Detské KUKO oslavy</h2>
    <p class="section__lead">Vyberte si balíček, ktorý vám sedí, a my sa postaráme o zvyšok.</p>
    <div class="packages-grid">
            <?= implode("\n\n      ", $articles) . "\n          " ?></div>
  </div>
</section>
