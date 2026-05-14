<?php
/** @var bool|null $error */
$title = 'KUKO detský svet — práve aktualizujeme';
$description = 'Práve aktualizujeme detský svet. O chvíľu sme späť.';
ob_start();
?>
<style>
  :root { color-scheme: light; }
  html, body { margin: 0; padding: 0; height: 100%; }
  body {
    font-family: "Nunito Sans", system-ui, sans-serif;
    background: linear-gradient(180deg, #FFF8EE 0%, #FBEEF5 100%);
    color: #3D3D3D;
    display: flex; align-items: center; justify-content: center;
    min-height: 100vh;
    padding: var(--s-3, 1.5rem);
  }
  .maint {
    max-width: 520px; width: 100%; text-align: center;
    padding: 2.5rem 2rem;
    background: white;
    border-radius: 1.5rem;
    box-shadow: 0 30px 80px rgba(0,0,0,0.08);
  }
  .maint__rainbow {
    width: 200px; height: 110px; margin: 0 auto 1rem;
    background: radial-gradient(ellipse at 50% 100%, transparent 35%, #9ED7E3 36%, #9ED7E3 50%, transparent 51%),
                radial-gradient(ellipse at 50% 100%, transparent 50%, #F7D87E 51%, #F7D87E 65%, transparent 66%),
                radial-gradient(ellipse at 50% 100%, transparent 65%, #F8B49D 66%, #F8B49D 80%, transparent 81%),
                radial-gradient(ellipse at 50% 100%, transparent 80%, #C9A8E1 81%, #C9A8E1 95%, transparent 96%);
    background-size: 100% 220%; background-position: 0 0; background-repeat: no-repeat;
  }
  .maint h1 { font-size: clamp(1.8rem, 4vw, 2.3rem); margin: 0 0 0.5rem; color: #D88BBE; }
  .maint__lead { color: #7A7A7A; margin: 0 0 1.5rem; font-size: 1.05rem; }
  .maint__contact { background: #FBEEF5; border-radius: 1rem; padding: 1rem; margin-bottom: 1.5rem; font-size: 0.95rem; }
  .maint__contact strong { color: #D88BBE; }
  .maint__form { display: flex; gap: 0.5rem; }
  .maint__form input {
    flex: 1; padding: 0.7rem 1rem; border: 1px solid rgba(0,0,0,0.15);
    border-radius: 999px; font: inherit; background: white;
  }
  .maint__form input:focus { outline: none; border-color: #D88BBE; box-shadow: 0 0 0 3px rgba(216,139,190,0.2); }
  .maint__form button {
    padding: 0.7rem 1.4rem; background: #D88BBE; color: white;
    border: 0; border-radius: 999px; font: inherit; font-weight: 700; cursor: pointer;
    text-transform: uppercase; letter-spacing: 0.02em; font-size: 0.85rem;
  }
  .maint__form button:hover { background: #c373a8; }
  .maint__error { color: #c0392b; background: #fdecea; padding: 0.5rem 0.75rem; border-radius: 0.5rem; margin: 0 0 1rem; font-size: 0.9rem; }
  .maint__staff { color: #aaa; font-size: 0.8rem; margin-top: 1rem; }
</style>
<div class="maint">
  <div class="maint__rainbow" aria-hidden="true"></div>
  <h1>Práve aktualizujeme detský svet 🌈</h1>
  <p class="maint__lead">Robíme niekoľko vylepšení pre lepší zážitok. O chvíľu sme späť!</p>

  <div class="maint__contact">
    <strong>Potrebujete sa s nami spojiť?</strong><br>
    <a href="tel:+421915319934" style="color:#3D3D3D">📞 +421 915 319 934</a><br>
    <a href="mailto:info@kuko-detskysvet.sk" style="color:#3D3D3D">✉️ info@kuko-detskysvet.sk</a>
  </div>

  <?php if (!empty($error)): ?>
    <p class="maint__error">Nesprávne heslo. Skúste znova.</p>
  <?php endif; ?>

  <form method="post" action="/maintenance" class="maint__form" autocomplete="off">
    <input type="password" name="password" placeholder="Heslo pre staff" autocomplete="current-password" required autofocus>
    <button type="submit">Vstúpiť</button>
  </form>

  <p class="maint__staff">Toto okno je viditeľné len počas údržby.</p>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout-minimal.php';
