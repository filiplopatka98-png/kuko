<div class="cookie-banner" id="cookie-banner" role="dialog" aria-modal="false" aria-labelledby="cookie-title" hidden>
  <div class="cookie-banner__inner container">
    <div class="cookie-banner__body">
      <p id="cookie-title" class="cookie-banner__title"><strong>Súbory cookies</strong></p>
      <p class="cookie-banner__text">Používame nevyhnutné cookies pre fungovanie webu a — po vašom súhlase — Google reCAPTCHA na ochranu rezervačného formulára pred spamom. Analytické a marketingové cookies používame iba s vaším súhlasom. Viac v <a href="/zasady-cookies">Zásadách cookies</a>.</p>

      <div class="cookie-settings" id="cookie-settings" hidden>
        <ul class="cookie-settings__list">
          <li class="cookie-cat">
            <label>
              <input type="checkbox" checked disabled>
              <span><strong>Nevyhnutné</strong> — potrebné pre základné fungovanie a uloženie vášho rozhodnutia. Vždy aktívne.</span>
            </label>
          </li>
          <li class="cookie-cat">
            <label>
              <input type="checkbox" data-cookie-cat="recaptcha">
              <span><strong>reCAPTCHA</strong> — ochrana rezervačného formulára pred spamom (Google). Bez nej nie je možné odoslať rezerváciu.</span>
            </label>
          </li>
          <li class="cookie-cat">
            <label>
              <input type="checkbox" data-cookie-cat="analytics">
              <span><strong>Analytické</strong> — anonymné štatistiky návštevnosti (napr. Google Analytics).</span>
            </label>
          </li>
          <li class="cookie-cat">
            <label>
              <input type="checkbox" data-cookie-cat="marketing">
              <span><strong>Marketingové</strong> — meranie a personalizácia reklamy.</span>
            </label>
          </li>
        </ul>
        <button type="button" class="btn btn--ghost cookie-banner__save" data-cookie-action="save">Uložiť výber</button>
      </div>
    </div>
    <div class="cookie-banner__actions">
      <button type="button" class="btn btn--ghost" data-cookie-action="deny">Odmietnuť</button>
      <button type="button" class="btn btn--ghost" data-cookie-action="settings">Nastavenia</button>
      <button type="button" class="btn" data-cookie-action="accept">Súhlasím</button>
    </div>
  </div>
</div>
<script type="module" src="<?= e(\Kuko\Asset::url('/assets/js/cookie-consent.js')) ?>"></script>
