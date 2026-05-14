<section id="galeria" class="section section--galeria" data-reveal>
  <div class="container">
    <h2>Fotogaléria</h2>
    <p class="section__lead">Nazrite do nášho priestoru a atmosféry, ktorú u nás deti milujú.</p>
    <div class="gallery">
      <?php for ($i = 1; $i <= 5; $i++): ?>
        <button class="gallery__item" type="button" data-lightbox="/assets/img/galeria_<?= $i ?>.jpg" aria-label="Otvoriť fotku <?= $i ?>">
          <picture>
            <source srcset="/assets/img/galeria_<?= $i ?>.webp" type="image/webp">
            <img src="/assets/img/galeria_<?= $i ?>.jpg" loading="lazy" alt="Fotka z herne KUKO" width="400" height="300">
          </picture>
        </button>
      <?php endfor; ?>
    </div>
  </div>
</section>
