<section id="domov" class="hero" data-reveal>
  <div class="hero__bg" aria-hidden="true"></div>
  <div class="hero__overlay" aria-hidden="true"></div>
  <div class="container hero__content">
    <h1 class="hero__title"><?= e(\Kuko\Content::get('hero.title', 'Detský svet KUKO')) ?></h1>
    <p class="hero__sub"><?= e(\Kuko\Content::get('hero.subtitle', 'pre radosť detí & pohodu rodičov')) ?></p>
    <p class="hero__tagline"><?= e(\Kuko\Content::get('hero.tagline', 'Bezpečné a hravé miesto pre vaše deti v Piešťanoch')) ?></p>
    <div class="hero__cta">
      <a class="btn" href="/rezervacia">Rezervovať oslavu</a>
      <a class="btn btn--ghost" href="#cennik">Pozrieť cenník</a>
    </div>
  </div>
</section>
