<section id="o-nas" class="section section--o-nas" data-reveal>
  <div class="container">
    <h2>O nás</h2>
    <div class="section__lead"><?= \Kuko\Content::get('about.lead', '<p>KUKO je interiérové detské ihrisko spojené s kaviarňou v Piešťanoch, vytvorené pre radosť detí a pohodlie rodičov. Mysleli sme na všetko, čo robí detský svet skutočne príjemným:</p>') ?></div>
    <div class="cards-grid">
      <div class="card card--blue">
        <img class="card__icon" src="<?= e(\Kuko\Asset::url('/assets/icons/playground.svg')) ?>" alt="" aria-hidden="true" width="64" height="64">
        <p class="card__body"><?= \Kuko\Content::get('about.card1', '<strong>Bezpečný, čistý a hravý priestor,</strong><br>kde sa deti môžu vyšantiť, objavovať a tráviť čas aktívne.') ?></p>
      </div>
      <div class="card card--peach">
        <img class="card__icon" src="<?= e(\Kuko\Asset::url('/assets/icons/coffee.svg')) ?>" alt="" aria-hidden="true" width="64" height="64">
        <p class="card__body"><?= \Kuko\Content::get('about.card2', 'Rodičia si zatiaľ môžu vychutnať <strong>kvalitnú kávu a chvíľku oddychu</strong> v príjemnom prostredí.') ?></p>
      </div>
      <div class="card card--yellow">
        <img class="card__icon" src="<?= e(\Kuko\Asset::url('/assets/icons/friendship.svg')) ?>" alt="" aria-hidden="true" width="64" height="64">
        <p class="card__body"><?= \Kuko\Content::get('about.card3', '<strong>Ideálne miesto na stretnutie</strong> s priateľmi či rodinou, alebo len chvíľu pre seba, zatiaľ čo sa deti zabavia.') ?></p>
      </div>
      <div class="card card--purple">
        <img class="card__icon" src="<?= e(\Kuko\Asset::url('/assets/icons/balloons.svg')) ?>" alt="" aria-hidden="true" width="64" height="64">
        <p class="card__body"><?= \Kuko\Content::get('about.card4', '<strong>Organizujeme aj detské oslavy,</strong> ktoré pripravíme s dôrazom na radosť detí a bezstarostnosť pre rodičov.') ?></p>
        <a class="btn btn--straddle card__cta" href="/rezervacia">Rezervovať oslavu</a>
      </div>
    </div>
  </div>
</section>
