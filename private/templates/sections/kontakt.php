<section id="kontakt" class="section section--kontakt" data-reveal>
  <div class="container">
    <h2>Kde nás nájdete?</h2>
    <p class="section__lead">Tešíme sa na vašu návštevu!</p>
    <div class="kontakt__grid">
      <div id="map" class="kontakt__map" aria-label="Mapa s polohou KUKO detský svet">
        <noscript><p style="padding:1rem">Mapa vyžaduje JavaScript. Adresa: Bratislavská 141, 921 01 Piešťany.</p></noscript>
      </div>
      <div class="kontakt__cards">
        <div class="contact-card contact-card--blue">
          <span class="contact-card__icon" aria-hidden="true">🏠</span>
          <div>
            <p class="contact-card__title">Navštívte náš Detský svet KUKO:</p>
            <p class="contact-card__value"><strong><?= e(\Kuko\Content::get('kontakt.address', 'Bratislavská 141, 921 01 Piešťany')) ?></strong></p>
          </div>
        </div>
        <div class="contact-card contact-card--peach">
          <span class="contact-card__icon" aria-hidden="true">📞</span>
          <div>
            <p class="contact-card__title">Máte otázky? Kontaktujte nás:</p>
            <p class="contact-card__value">
              <a href="tel:+421915319934"><?= e(\Kuko\Content::get('kontakt.phone', '+421 915 319 934')) ?></a> |
              <a href="mailto:info@kuko-detskysvet.sk"><?= e(\Kuko\Content::get('kontakt.email', 'info@kuko-detskysvet.sk')) ?></a>
            </p>
          </div>
        </div>
        <div class="contact-card contact-card--yellow">
          <span class="contact-card__icon" aria-hidden="true">⏰</span>
          <div>
            <p class="contact-card__title">Otváracie hodiny — sme tu pre vás každý deň:</p>
            <p class="contact-card__value"><strong><?= e(\Kuko\Content::get('kontakt.hours', 'Pondelok – Nedeľa: 9:00 – 20:00')) ?></strong></p>
          </div>
        </div>
        <div class="contact-card contact-card--purple">
          <p class="contact-card__title">Sledujte nás na sociálnych sieťach:</p>
          <div class="contact-card__socials">
            <a href="<?= e(\Kuko\Config::get('social.facebook', '#')) ?>" aria-label="Facebook" rel="noopener" target="_blank">
              <img src="/assets/icons/facebook-app-symbol.svg" alt="Facebook" width="18" height="18">
            </a>
            <a href="<?= e(\Kuko\Config::get('social.instagram', '#')) ?>" aria-label="Instagram" rel="noopener" target="_blank">
              <img src="/assets/icons/instagram.svg" alt="Instagram" width="18" height="18">
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
