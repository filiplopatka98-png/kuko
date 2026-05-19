<?php
$title = 'Zásady používania cookies — KUKO detský svet';
$description = 'Aké cookies používame na webe kuko-detskysvet.sk, na čo slúžia a ako spravovať svoj súhlas.';
$canonical = '/zasady-cookies';
$pageType = 'cookies';
$pageIndexing = false;
ob_start();
?>
<div class="section">
  <div class="container" style="max-width: 800px;">
    <h1>Zásady používania cookies</h1>
    <p>Posledná aktualizácia: <?= date('j. n. Y') ?></p>

<?= \Kuko\Content::get('cookies.body', <<<'HTML'
    <h2 class="legal-h2">1. Čo sú cookies</h2>
    <p>Cookies sú malé textové súbory, ktoré sa ukladajú vo vašom prehliadači pri návšteve webu. Slúžia na zabezpečenie základnej funkčnosti a — len s vaším súhlasom — na ďalšie účely uvedené nižšie.</p>

    <h2 class="legal-h2">2. Aké cookies používame</h2>
    <ul class="cookie-list">
      <li><strong>PHPSESSID</strong> — technická relácia (chod webu) · trvanie: relácia · kategória: <em>Nevyhnutné</em></li>
      <li><strong>kuko_cookie_consent</strong> — uloženie vášho rozhodnutia o cookies · trvanie: ~6 mesiacov · kategória: <em>Nevyhnutné</em></li>
      <li><strong>_GRECAPTCHA</strong> — Google reCAPTCHA, ochrana rezervačného formulára pred spamom · trvanie: ~6 mesiacov · kategória: <em>reCAPTCHA (so súhlasom)</em></li>
    </ul>
    <p><strong>Analytické a marketingové cookies</strong> momentálne nepoužívame. Súhlasové kategórie sú pripravené dopredu — ak v budúcnosti pridáme napríklad Google Analytics alebo marketingové nástroje, načítajú sa iba ak ste danú kategóriu povolili.</p>

    <h2 class="legal-h2">3. Právny základ</h2>
    <p>Nevyhnutné cookies používame na základe oprávneného záujmu zabezpečiť funkčnosť webu (čl. 6 ods. 1 písm. f GDPR) a nevyžadujú váš súhlas. Ostatné kategórie (reCAPTCHA, analytické, marketingové) spracúvame výlučne na základe vášho súhlasu (čl. 6 ods. 1 písm. a GDPR), ktorý môžete kedykoľvek odvolať.</p>

    <h2 class="legal-h2">4. Tretie strany</h2>
    <p>Google reCAPTCHA je služba spoločnosti Google. Po udelení súhlasu môže Google získať údaje o vašom správaní na stránke. Viac: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.</p>

    <h2 class="legal-h2">5. Ako spravovať súhlas</h2>
    <p>Pri prvej návšteve sa zobrazí cookie lišta, kde môžete súhlas udeliť, odmietnuť alebo si vybrať jednotlivé kategórie cez „Nastavenia". Svoje rozhodnutie môžete kedykoľvek zmeniť kliknutím na <strong>„Cookie nastavenia"</strong> v pätičke webu.</p>

    <h2 class="legal-h2">6. Kontakt</h2>
    <p>V prípade otázok k spracúvaniu cookies nás kontaktujte na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>. Spracúvanie osobných údajov upravuje <a href="/ochrana-udajov">Ochrana osobných údajov</a>.</p>

    <p class="legal-back"><a href="/">&larr; Späť na domov</a></p>

HTML) ?>  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
