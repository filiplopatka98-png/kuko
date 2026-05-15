<section id="cennik" class="section section--cennik" data-reveal>
  <div class="container cennik__inner">
    <picture>
      <source srcset="/assets/img/cennik.webp" type="image/webp">
      <img class="cennik__photo" src="/assets/img/cennik.jpg" alt="Tri šťastné deti s tortou v detskej herni KUKO Piešťany" loading="lazy" width="600" height="400">
    </picture>
    <div class="cennik__panel">
      <h2>Cenník</h2>
      <p class="section__lead"><?= e(\Kuko\Content::get('cennik.lead', 'Chceme, aby bol čas strávený u nás dostupný a príjemný pre každého.')) ?></p>
      <ul class="cennik__list">
        <li><span><?= e(\Kuko\Content::get('cennik.item1.label', 'Dieťa do 1 roku')) ?></span><span class="cennik__price"><?= e(\Kuko\Content::get('cennik.item1.price', 'ZADARMO')) ?></span></li>
        <li><span><?= e(\Kuko\Content::get('cennik.item2.label', 'Dieťa od 1 roku')) ?></span><span class="cennik__price"><?= e(\Kuko\Content::get('cennik.item2.price', '5,00 € / hod')) ?></span></li>
        <li><span><?= e(\Kuko\Content::get('cennik.item3.label', 'Dieťa od 1 roku neobmedzene')) ?></span><span class="cennik__price"><?= e(\Kuko\Content::get('cennik.item3.price', '15,00 €')) ?></span></li>
      </ul>
    </div>
  </div>
</section>
