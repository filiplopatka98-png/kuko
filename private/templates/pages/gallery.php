<?php
$gallery = $gallery ?? [];
$galleryAlts = [
    1 => 'Detský kútik KUKO — narodeninová oslava s tortou a balónmi',
    2 => 'Herné prvky v detskom svete KUKO — šmykľavka a hracie zóny',
    3 => 'Interiér KUKO — rodičia pri káve, deti sa hrajú',
    4 => 'Detská oslava v KUKO — výzdoba a deti pri stole',
    5 => 'Vnútorný priestor detskej herne KUKO Piešťany',
];
$title       = 'Fotogaléria — KUKO detský svet';
$description = 'Fotogaléria detskej herne a kaviarne KUKO v Piešťanoch — interiér, hracie zóny, oslavy a atmosféra, ktorú u nás deti milujú.';
$canonical   = '/galeria';
$pageType    = 'gallery';
ob_start();
?>
  <section class="section section--galeria" data-reveal>
    <div class="container">
      <img class="section__rainbow" src="<?= e(\Kuko\Asset::url('/assets/img/rainbow.png')) ?>" alt="" aria-hidden="true" width="260" height="120">
      <h1>Fotogaléria</h1>
      <p class="section__lead">Nazrite do nášho priestoru a atmosféry, ktorú u nás deti milujú.</p>
      <div class="gallery">
        <?php if (!empty($gallery)): ?>
          <?php foreach ($gallery as $photo):
            $alt  = (string) ($photo['alt_text'] ?? 'Fotka z herne KUKO Piešťany');
            $jpg  = '/assets/img/gallery/' . $photo['filename'];
            $webp = !empty($photo['webp']) ? '/assets/img/gallery/' . $photo['webp'] : null;
          ?>
          <button class="gallery__item" type="button" data-lightbox="<?= e($jpg) ?>"<?php if ($webp !== null): ?> data-lightbox-webp="<?= e($webp) ?>"<?php endif; ?> aria-label="Otvoriť: <?= e($alt) ?>">
            <picture>
              <?php if ($webp !== null): ?>
              <source srcset="<?= e($webp) ?>" type="image/webp">
              <?php endif; ?>
              <img src="<?= e($jpg) ?>" loading="lazy" alt="<?= e($alt) ?>" width="400" height="300">
            </picture>
          </button>
          <?php endforeach; ?>
        <?php else: ?>
          <?php for ($i = 1; $i <= 5; $i++): $alt = $galleryAlts[$i] ?? 'Fotka z herne KUKO Piešťany'; ?>
          <button class="gallery__item" type="button" data-lightbox="/assets/img/galeria_<?= $i ?>.jpg" data-lightbox-webp="/assets/img/galeria_<?= $i ?>.webp" aria-label="Otvoriť: <?= e($alt) ?>">
            <picture>
              <source srcset="/assets/img/galeria_<?= $i ?>.webp" type="image/webp">
              <img src="/assets/img/galeria_<?= $i ?>.jpg" loading="lazy" alt="<?= e($alt) ?>" width="400" height="300">
            </picture>
          </button>
          <?php endfor; ?>
        <?php endif; ?>
      </div>
      <aside class="cta-panel" aria-label="Rezervácia oslavy">
        <h2 class="cta-panel__title"><?= e(\Kuko\Content::get('cta.reservation.heading', 'Páči sa vám u nás?')) ?></h2>
        <p class="cta-panel__text"><?= e(\Kuko\Content::get('cta.reservation.text', 'Rezervujte si oslavu v KUKO — vyberte balíček, dátum a čas v 3 krokoch.')) ?></p>
        <a class="btn" href="/rezervacia">Rezervovať oslavu</a>
      </aside>
    </div>
  </section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
