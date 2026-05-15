<section id="o-nas" class="section section--o-nas" data-reveal>
  <div class="container">
    <h2>O nás</h2>
    <div class="section__lead"><?= \Kuko\Content::get('about.lead', '<p>KUKO je interiérové detské ihrisko spojené s kaviarňou v Piešťanoch, vytvorené pre radosť detí a pohodlie rodičov. Mysleli sme na všetko, čo robí detský svet skutočne príjemným:</p>') ?></div>
    <div class="cards-grid">
      <div class="card card--blue">
        <img class="card__icon" src="/assets/icons/playground.svg" alt="" width="48" height="48">
        <p class="card__body"><strong><?= e(\Kuko\Content::get('about.card1.title', 'Bezpečný, čistý a hravý priestor,')) ?></strong><br><?= e(\Kuko\Content::get('about.card1.body', 'kde sa deti môžu vyšantiť, objavovať a tráviť čas aktívne.')) ?></p>
      </div>
      <div class="card card--peach">
        <img class="card__icon" src="/assets/icons/coffee.svg" alt="" width="48" height="48">
        <p class="card__body"><?= e(\Kuko\Content::get('about.card2.body', 'Rodičia si zatiaľ môžu vychutnať kvalitnú kávu a chvíľku oddychu v príjemnom prostredí.')) ?></p>
      </div>
      <div class="card card--yellow">
        <img class="card__icon" src="/assets/icons/friendship.svg" alt="" width="48" height="48">
        <p class="card__body"><?= e(\Kuko\Content::get('about.card3.body', 'Ideálne miesto na stretnutie s priateľmi či rodinou, alebo len chvíľu pre seba, zatiaľ čo sa deti zabavia.')) ?></p>
      </div>
      <div class="card card--purple">
        <img class="card__icon" src="/assets/icons/balloons.svg" alt="" width="48" height="48">
        <p class="card__body"><?= e(\Kuko\Content::get('about.card4.body', 'Organizujeme aj detské oslavy, ktoré pripravíme s dôrazom na radosť detí a bezstarostnosť pre rodičov.')) ?></p>
        <a class="btn card__cta" href="/rezervacia">Rezervovať oslavu</a>
      </div>
    </div>
  </div>
</section>
