<dialog class="modal" id="reservation-modal" aria-labelledby="resv-title">
  <form class="modal__form" id="reservation-form" novalidate>
    <button type="button" class="modal__close" data-close-modal aria-label="Zavrieť">&times;</button>
    <h2 id="resv-title">Rezervácia oslavy</h2>
    <p class="modal__lead">Vyplňte detaily, ozveme sa do 24 hodín na potvrdenie.</p>

    <div class="modal__cookie-gate" id="modal-cookie-gate" hidden>
      <p>Pre odoslanie rezervácie potrebujeme váš súhlas s cookies (Google reCAPTCHA chráni formulár pred spamom).</p>
      <button type="button" class="btn" data-cookie-action="accept">Súhlasím s cookies</button>
    </div>

    <fieldset class="modal__fields" id="modal-fields">
      <input type="hidden" name="csrf" value="<?= e(\Kuko\Csrf::token()) ?>">
      <input type="text" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">

      <label class="field">
        <span>Balíček</span>
        <select name="package" required>
          <option value="mini">KUKO MINI</option>
          <option value="maxi">KUKO MAXI</option>
          <option value="closed">Uzavretá spoločnosť</option>
        </select>
      </label>

      <div class="field-row" style="grid-template-columns: 1fr 1fr;">
        <label class="field">
          <span>Dátum</span>
          <input type="date" name="wished_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
        </label>
        <label class="field">
          <span>Počet detí</span>
          <input type="number" name="kids_count" required min="1" max="50" value="10">
        </label>
      </div>

      <div class="field">
        <span>Dostupné časy</span>
        <input type="hidden" name="wished_time" id="wished_time" required>
        <div class="slot-picker" id="slot-picker" role="radiogroup" aria-label="Dostupné časové sloty">
          <p class="slot-picker__hint">Vyberte dátum a balíček.</p>
        </div>
      </div>

      <label class="field">
        <span>Meno a priezvisko</span>
        <input type="text" name="name" required minlength="2" maxlength="120" autocomplete="name">
      </label>

      <div class="field-row" style="grid-template-columns: 1fr 1fr;">
        <label class="field">
          <span>Telefón</span>
          <input type="tel" name="phone" required autocomplete="tel">
        </label>
        <label class="field">
          <span>E-mail</span>
          <input type="email" name="email" required autocomplete="email">
        </label>
      </div>

      <label class="field">
        <span>Poznámka (voliteľné)</span>
        <textarea name="note" rows="3" maxlength="1000" placeholder="Téma oslavy, alergie, špeciálne želania…"></textarea>
      </label>

      <p class="modal__error" id="modal-error" hidden></p>

      <div class="modal__actions" id="modal-actions">
        <button type="button" class="btn btn--ghost" data-close-modal>Zrušiť</button>
        <button type="submit" class="btn" id="modal-submit">Odoslať rezerváciu</button>
      </div>
    </fieldset>

    <div class="modal__success" id="modal-success" hidden>
      <p class="modal__success-emoji" aria-hidden="true">🎉</p>
      <h3>Ďakujeme!</h3>
      <p>Prijali sme vašu rezerváciu. Ozveme sa do 24 hodín.</p>
      <button type="button" class="btn" data-close-modal>Zavrieť</button>
    </div>
  </form>
</dialog>
