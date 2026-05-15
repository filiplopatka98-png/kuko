<?php
$packages = $packages ?? [];
$hasExtended = false;
foreach ($packages as $p) {
    if (($p['price_text'] ?? null) !== null
        && ($p['description'] ?? null) !== null
        && ($p['included_json'] ?? null) !== null
        && ($p['accent_color'] ?? null) !== null) {
        $hasExtended = true;
        break;
    }
}
?>
<section id="oslavy" class="section section--oslavy" data-reveal>
  <div class="container">
    <h2>Detské KUKO oslavy</h2>
    <p class="section__lead">Vyberte si balíček, ktorý vám sedí, a my sa postaráme o zvyšok.</p>
    <div class="packages-grid">
      <?php if ($hasExtended): ?>
        <?php foreach ($packages as $pkg):
          if (($pkg['price_text'] ?? null) === null
              || ($pkg['description'] ?? null) === null
              || ($pkg['included_json'] ?? null) === null
              || ($pkg['accent_color'] ?? null) === null) {
              continue;
          }
          $accent   = in_array($pkg['accent_color'], ['blue', 'purple', 'yellow'], true) ? $pkg['accent_color'] : 'blue';
          $included = json_decode((string) $pkg['included_json'], true);
          if (!is_array($included)) { $included = []; }
        ?>
      <article class="package package--<?= e($accent) ?>">
        <header class="package__head"><span class="package__hat" aria-hidden="true">🎩</span><h3><?= e($pkg['name']) ?></h3></header>
        <p class="package__desc"><?= e($pkg['description']) ?></p>
        <ul class="package__meta">
          <?php if (!empty($pkg['kids_count_text'])): ?>
          <li><span class="ic" aria-hidden="true">👶</span> Počet detí: <?= e($pkg['kids_count_text']) ?></li>
          <?php endif; ?>
          <?php if (!empty($pkg['duration_text'])): ?>
          <li><span class="ic" aria-hidden="true">⏰</span> Časový harmonogram: <?= e($pkg['duration_text']) ?></li>
          <?php endif; ?>
        </ul>
        <p class="package__price"><?= e($pkg['price_text']) ?></p>
        <ul class="package__incl">
          <?php foreach ($included as $item): ?>
          <li>✓ <?= e($item) ?></li>
          <?php endforeach; ?>
        </ul>
        <a class="btn package__cta" href="/rezervacia?balicek=<?= e($pkg['code']) ?>">Rezervovať balíček</a>
      </article>
        <?php endforeach; ?>
      <?php else: ?>
      <article class="package package--blue">
        <header class="package__head"><span class="package__hat" aria-hidden="true">🎩</span><h3>Oslava KUKO MINI</h3></header>
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
        <a class="btn package__cta" href="/rezervacia?balicek=mini">Rezervovať balíček</a>
      </article>

      <article class="package package--purple">
        <header class="package__head"><span class="package__hat" aria-hidden="true">🎩</span><h3>Oslava KUKO MAXI</h3></header>
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
        <a class="btn package__cta" href="/rezervacia?balicek=maxi">Rezervovať balíček</a>
      </article>

      <article class="package package--yellow">
        <header class="package__head"><span class="package__hat" aria-hidden="true">🎩</span><h3>Uzavretá spoločnosť</h3></header>
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
        <a class="btn package__cta" href="/rezervacia?balicek=closed">Rezervovať balíček</a>
      </article>
      <?php endif; ?>
    </div>
  </div>
</section>
