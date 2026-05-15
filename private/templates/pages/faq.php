<?php
$title       = 'Časté otázky — KUKO detský svet';
$description = 'Odpovede na najčastejšie otázky o detskej herni KUKO v Piešťanoch — ceny, oslavy, otváracie hodiny, vek detí, rezervácie.';
$canonical   = '/faq';
$pageType    = 'faq';

// FAQ items come from the structured `faq.items` setting via the Faq
// helper (single source of truth — also feeds the JSON-LD in head.php).
// The /faq route renders this template without a SettingsRepo, so build
// one here; on any DB fault Faq::items() degrades to the 6 defaults, so
// the page (and schema) always render — matching the Content/Seo
// graceful-fallback philosophy.
try {
    $faqItems = \Kuko\Faq::items(new \Kuko\SettingsRepo(\Kuko\Db::fromConfig()));
} catch (\Throwable $e) {
    error_log('[faq.php] settings unavailable, using defaults: ' . $e->getMessage());
    $faqItems = \Kuko\Faq::defaults();
}
ob_start();
?>
  <section class="section section--faq" data-reveal>
    <div class="container">
      <h1>Často kladené otázky</h1>
      <?= \Kuko\Content::get('faq.intro', <<<'HTML'
<p class="section__lead">Najčastejšie veci, ktoré sa nás rodičia pýtajú pred prvou návštevou.</p>

HTML) ?>      <div class="faq">
<?php foreach ($faqItems as $it): ?>        <details class="faq__item">
          <summary><?= e($it['q']) ?></summary>
          <div class="faq__a"><?= $it['a'] /* sanitised on save; defaults are trusted */ ?></div>
        </details>
<?php endforeach; ?>      </div>
      <p style="text-align:center; margin-top:2rem;"><a class="btn" href="/">&larr; Späť na domov</a></p>
    </div>
  </section>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout.php';
