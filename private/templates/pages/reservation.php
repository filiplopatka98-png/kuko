<?php
/** @var array<int,array<string,mixed>> $packages */
$title = 'Rezervácia oslavy — KUKO detský svet';
$description = 'Rezervujte si oslavu v KUKO detský svet. Vyberte balíček, dátum a čas v 3 krokoch.';
$csrf = \Kuko\Csrf::token();
$siteKey = \Kuko\Config::get('recaptcha.site_key', '');
ob_start();
?>
<main class="rezervacia" id="rezervacia">
  <header class="rezervacia__header">
    <a href="/" class="rezervacia__brand" aria-label="KUKO detský svet">
      <img src="/assets/img/logo.png" alt="" width="60" height="42">
      <span>KUKO rezervácia</span>
    </a>
    <ol class="rezervacia__steps" role="list">
      <li data-step-indicator="1" class="is-active"><span>1</span> Balíček</li>
      <li data-step-indicator="2"><span>2</span> Termín</li>
      <li data-step-indicator="3"><span>3</span> Kontakt</li>
    </ol>
  </header>

  <form id="rezervacia-form" novalidate>
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <input type="hidden" name="website" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px" aria-hidden="true">
    <input type="hidden" name="package" id="f-package" value="">
    <input type="hidden" name="wished_date" id="f-date" value="">
    <input type="hidden" name="wished_time" id="f-time" value="">

    <!-- ===== KROK 1: BALÍČEK ===== -->
    <section class="step is-active" data-step="1">
      <div class="step__inner">
        <h1>Vyberte si balíček</h1>
        <p class="step__lead">Začnite výberom typu oslavy. Detaily vyplníte v ďalšom kroku.</p>
        <div class="package-picker">
          <?php foreach ($packages as $pkg): ?>
            <button type="button" class="package-card package-card--<?= e($pkg['code']) ?>" data-pick-package="<?= e($pkg['code']) ?>" data-duration="<?= (int) $pkg['duration_min'] ?>">
              <span class="package-card__icon" aria-hidden="true">🎩</span>
              <h2><?= e($pkg['name']) ?></h2>
              <p class="package-card__meta"><?= (int) $pkg['duration_min'] ?> minút<?= (int) $pkg['blocks_full_day'] === 1 ? ' · celý deň' : '' ?></p>
              <p class="package-card__cta">Vybrať →</p>
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </section>

    <!-- ===== KROK 2: TERMÍN ===== -->
    <section class="step" data-step="2">
      <div class="step__inner step__inner--wide">
        <div class="step__heading">
          <button type="button" class="step__back" data-go-step="1" aria-label="Späť">‹</button>
          <h1>Vyberte deň a čas</h1>
        </div>
        <p class="step__lead">
          Vybraný balíček: <strong id="selected-package-name">—</strong>
          (<span id="selected-package-duration">—</span> min)
        </p>

        <div class="calendar" id="calendar" aria-label="Kalendár dostupnosti">
          <div class="calendar__nav">
            <button type="button" class="calendar__navbtn" data-cal-nav="prev" aria-label="Predchádzajúci mesiac">‹</button>
            <h2 class="calendar__title" id="calendar-title">—</h2>
            <button type="button" class="calendar__navbtn" data-cal-nav="next" aria-label="Ďalší mesiac">›</button>
          </div>
          <div class="calendar__head">
            <div>Po</div><div>Ut</div><div>St</div><div>Št</div><div>Pia</div><div>So</div><div>Ne</div>
          </div>
          <div class="calendar__grid" id="calendar-grid" role="grid">
            <p class="calendar__hint">Načítavam…</p>
          </div>
          <div class="calendar__legend">
            <span><span class="dot dot--available"></span> voľné</span>
            <span><span class="dot dot--full"></span> plné / nedostupné</span>
            <span><span class="dot dot--closed"></span> zatvorené</span>
            <span><span class="dot dot--past"></span> minulosť</span>
          </div>
        </div>

        <div class="slot-section" id="slot-section" hidden>
          <h3 id="slot-section-title">Voľné časy</h3>
          <div class="slot-grid" id="slot-grid" role="radiogroup" aria-label="Časové sloty"></div>
        </div>

        <div class="step__actions">
          <button type="button" class="btn btn--ghost" data-go-step="1">‹ Späť</button>
          <button type="button" class="btn" data-go-step="3" id="to-step-3" disabled>Pokračovať na kontakt ›</button>
        </div>
      </div>
    </section>

    <!-- ===== KROK 3: KONTAKT ===== -->
    <section class="step" data-step="3">
      <div class="step__inner">
        <div class="step__heading">
          <button type="button" class="step__back" data-go-step="2" aria-label="Späť">‹</button>
          <h1>Vaše údaje</h1>
        </div>
        <p class="step__lead">
          <strong id="summary-package">—</strong> · <strong id="summary-date">—</strong> · <strong id="summary-time">—</strong>
        </p>

        <div class="field">
          <label for="f-kids">Počet detí</label>
          <input type="number" name="kids_count" id="f-kids" required min="1" max="50" value="10">
        </div>
        <div class="field">
          <label for="f-name">Meno a priezvisko</label>
          <input type="text" name="name" id="f-name" required minlength="2" maxlength="120" autocomplete="name">
        </div>
        <div class="field-row">
          <div class="field">
            <label for="f-phone">Telefón</label>
            <input type="tel" name="phone" id="f-phone" required autocomplete="tel">
          </div>
          <div class="field">
            <label for="f-email">E-mail</label>
            <input type="email" name="email" id="f-email" required autocomplete="email">
          </div>
        </div>
        <div class="field">
          <label for="f-note">Poznámka (voliteľné)</label>
          <textarea name="note" id="f-note" rows="3" maxlength="1000" placeholder="Téma oslavy, alergie, špeciálne želania…"></textarea>
        </div>

        <?php if ($siteKey): ?>
        <div class="cookie-gate" id="cookie-gate" hidden>
          <p>Pre odoslanie potrebujeme váš súhlas s cookies (Google reCAPTCHA chráni formulár pred spamom).</p>
          <button type="button" class="btn" data-cookie-action="accept">Súhlasím s cookies</button>
        </div>
        <?php endif; ?>

        <p class="step__error" id="form-error" hidden></p>

        <div class="step__actions">
          <button type="button" class="btn btn--ghost" data-go-step="2">‹ Späť</button>
          <button type="submit" class="btn" id="submit-btn">Odoslať rezerváciu</button>
        </div>
      </div>
    </section>

    <!-- ===== ÚSPECH ===== -->
    <section class="step" data-step="success">
      <div class="step__inner step__inner--success">
        <p class="step__emoji" aria-hidden="true">🎉</p>
        <h1>Ďakujeme!</h1>
        <p>Prijali sme vašu rezerváciu. Ozveme sa do 24 hodín.</p>
        <p id="success-link"></p>
        <p><a class="btn" href="/">Späť na domov</a></p>
      </div>
    </section>
  </form>
</main>

<?php require __DIR__ . '/../cookie-banner.php'; ?>

<script type="module" src="<?= e(\Kuko\Asset::url('/assets/js/rezervacia.js')) ?>"></script>
<?php
$content = ob_get_clean();
$stylesheets = ['/assets/css/rezervacia.css'];
$title = 'Rezervácia oslavy — KUKO detský svet';
require __DIR__ . '/../layout-minimal.php';
