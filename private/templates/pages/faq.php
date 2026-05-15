<?php
$title       = 'Časté otázky — KUKO detský svet';
$description = 'Odpovede na najčastejšie otázky o detskej herni KUKO v Piešťanoch — ceny, oslavy, otváracie hodiny, vek detí, rezervácie.';
$canonical   = '/faq';
$pageType    = 'faq';
ob_start();
?>
<main>
  <section class="section section--faq" data-reveal>
    <div class="container">
      <h1>Často kladené otázky</h1>
      <p class="section__lead">Najčastejšie veci, ktoré sa nás rodičia pýtajú pred prvou návštevou.</p>
      <div class="faq">
        <details class="faq__item">
          <summary>Aké sú ceny vstupu do KUKO?</summary>
          <p>Dieťa do 1 roku má vstup <strong>zadarmo</strong>. Dieťa od 1 roku platí <strong>5 € za hodinu</strong>, alebo <strong>15 € na celý deň neobmedzene</strong>.</p>
        </details>
        <details class="faq__item">
          <summary>Akú oslavu si môžem zarezervovať?</summary>
          <p>Ponúkame 3 balíčky osláv: <strong>KUKO MINI</strong> (do 10 detí, 2 hodiny, 120–150 €), <strong>KUKO MAXI</strong> (do 20 detí, 3 hodiny, 220–260 €) a <strong>Uzavretá spoločnosť</strong> (celé KUKO len pre vás, 4 hodiny, 350 €). <a href="/rezervacia">Rezervovať</a>.</p>
        </details>
        <details class="faq__item">
          <summary>Aké sú otváracie hodiny?</summary>
          <p>KUKO je otvorený <strong>každý deň</strong>, Pondelok – Nedeľa od 9:00 do 20:00.</p>
        </details>
        <details class="faq__item">
          <summary>Pre aký vek detí je KUKO vhodný?</summary>
          <p>KUKO je vhodný pre deti od narodenia. Pre najmenších máme bezpečné kojenecké zóny, pre väčšie deti aktívne hracie prvky. Rodičia sú za bezpečnosť svojich detí v priestore zodpovední.</p>
        </details>
        <details class="faq__item">
          <summary>Kde sa KUKO nachádza?</summary>
          <p>Nájdete nás na <strong>Bratislavskej 141, 921 01 Piešťany</strong>. Pozrite si mapu v sekcii <a href="/#kontakt">Kontakt</a>.</p>
        </details>
        <details class="faq__item">
          <summary>Ako môžem zrušiť alebo zmeniť rezerváciu?</summary>
          <p>Zmenu alebo zrušenie termínu vybavíme telefonicky na <a href="tel:+421915319934">+421 915 319 934</a> alebo e-mailom na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>. Cez web rezerváciu meniť nedá.</p>
        </details>
      </div>
      <p style="text-align:center; margin-top:2rem;"><a class="btn" href="/">&larr; Späť na domov</a></p>
    </div>
  </section>
</main>
<?php require __DIR__ . '/../footer.php'; ?>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
