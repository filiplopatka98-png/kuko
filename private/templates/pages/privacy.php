<?php
$title = 'Ochrana osobných údajov — KUKO detský svet';
$description = 'Zásady spracovania osobných údajov a cookies na webe kuko-detskysvet.sk.';
$canonical = '/ochrana-udajov';
$pageIndexing = false;
ob_start();
?>
<div class="section">
  <div class="container" style="max-width: 800px;">
    <h1>Ochrana osobných údajov</h1>
    <p>Posledná aktualizácia: <?= date('j. n. Y') ?></p>

<?= \Kuko\Content::get('privacy.body', <<<'HTML'
    <h2 class="legal-h2">1. Prevádzkovateľ</h2>
    <p>Prevádzkovateľom webu kuko-detskysvet.sk je KUKO detský svet, Bratislavská 141, 921 01 Piešťany, e-mail <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>.</p>

    <h2 class="legal-h2">2. Rozsah a účel spracovania</h2>
    <p>Pri rezervácii oslavy spracúvame údaje, ktoré ste nám poskytli prostredníctvom formulára: meno, telefón, e-mail, požadovaný dátum a čas oslavy, počet detí a poznámku. Tieto údaje spracúvame výlučne na účel vybavenia vašej rezervácie a kontaktu vo veci oslavy.</p>

    <h2 class="legal-h2">3. Právny základ</h2>
    <p>Spracovanie prebieha na základe vašej žiadosti o rezerváciu (predzmluvné konanie podľa čl. 6 ods. 1 písm. b GDPR) a nášho oprávneného záujmu zabezpečiť funkčnosť rezervačného systému (čl. 6 ods. 1 písm. f GDPR).</p>

    <h2 class="legal-h2">4. Doba uchovávania</h2>
    <p>Údaje uchovávame po dobu potrebnú na vybavenie rezervácie a 6 mesiacov po jej skončení, následne sú anonymizované alebo vymazané.</p>

    <h2 class="legal-h2">5. Cookies a Google reCAPTCHA</h2>
    <p>Web používa nasledujúce cookies:</p>
    <ul>
      <li><strong>Technické cookies</strong> (PHPSESSID, cookie_consent) — nevyhnutné pre fungovanie a uloženie vášho rozhodnutia o cookies. Tieto cookies nevyžadujú váš súhlas.</li>
      <li><strong>Google reCAPTCHA</strong> (_GRECAPTCHA) — slúži na ochranu rezervačného formulára pred spamom. Spoločnosť Google týmto môže získať údaje o vašom správaní na stránke. Cookie sa nahrá iba po vašom súhlase. Viac informácií: <a href="https://policies.google.com/privacy" target="_blank" rel="noopener">Google Privacy Policy</a>.</li>
    </ul>
    <p>Súhlas s cookies môžete kedykoľvek odvolať kliknutím na „Cookie nastavenia" v pätičke.</p>

    <h2 class="legal-h2">6. Vaše práva</h2>
    <p>V súlade s GDPR máte právo na prístup k svojim údajom, ich opravu, vymazanie, obmedzenie spracúvania, prenosnosť, ako aj právo namietať a podať sťažnosť na Úrade na ochranu osobných údajov SR. Ohľadom vašich práv nás môžete kontaktovať na <a href="mailto:info@kuko-detskysvet.sk">info@kuko-detskysvet.sk</a>.</p>

    <p class="legal-back"><a href="/">&larr; Späť na domov</a></p>

HTML) ?>  </div>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
