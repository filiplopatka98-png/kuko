<?php
$gallery = $gallery ?? [];
$galleryAlts = [
    1 => 'Detský kútik KUKO — narodeninová oslava s tortou a balónmi',
    2 => 'Herné prvky v detskom svete KUKO — šmykľavka a hracie zóny',
    3 => 'Interiér KUKO — rodičia pri káve, deti sa hrajú',
    4 => 'Detská oslava v KUKO — výzdoba a deti pri stole',
    5 => 'Vnútorný priestor detskej herne KUKO Piešťany',
];
?>
<section id="galeria" class="section section--galeria" data-reveal>
  <div class="container">
    <h2>Fotogaléria</h2>
    <p class="section__lead">Nazrite do nášho priestoru a atmosféry, ktorú u nás deti milujú.</p>
    <div class="gallery">
      <?php if (!empty($gallery)): ?>
        <?php foreach ($gallery as $photo):
          $alt  = (string) ($photo['alt_text'] ?? 'Fotka z herne KUKO Piešťany');
          $jpg  = '/assets/img/gallery/' . $photo['filename'];
          $webp = !empty($photo['webp']) ? '/assets/img/gallery/' . $photo['webp'] : null;
        ?>
        <button class="gallery__item" type="button" data-lightbox="<?= e($jpg) ?>" aria-label="Otvoriť: <?= e($alt) ?>">
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
        <button class="gallery__item" type="button" data-lightbox="/assets/img/galeria_<?= $i ?>.jpg" aria-label="Otvoriť: <?= e($alt) ?>">
          <picture>
            <source srcset="/assets/img/galeria_<?= $i ?>.webp" type="image/webp">
            <img src="/assets/img/galeria_<?= $i ?>.jpg" loading="lazy" alt="<?= e($alt) ?>" width="400" height="300">
          </picture>
        </button>
        <?php endfor; ?>
      <?php endif; ?>
    </div>
  </div>
</section>
