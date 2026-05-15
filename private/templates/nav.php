<?php
$fb = \Kuko\Social::url('facebook', '');
$ig = \Kuko\Social::url('instagram', '');
?>
<div class="topbar">
  <div class="container topbar__inner">
    <div class="topbar__contact">
      <a href="mailto:info@kuko-detskysvet.sk" class="topbar__link">
        <svg class="topbar__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <rect x="1.5" y="3" width="13" height="10" rx="1.5" fill="none" stroke="currentColor" stroke-width="1.3"/>
          <path d="M2 4l6 4.5L14 4" fill="none" stroke="currentColor" stroke-width="1.3" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <span>info@kuko-detskysvet.sk</span>
      </a>
      <a href="tel:+421915319934" class="topbar__link">
        <svg class="topbar__icon" width="16" height="16" viewBox="0 0 16 16" aria-hidden="true" focusable="false">
          <path d="M5.1 2.2c.3-.3.8-.3 1 .1l1.2 2c.2.3.1.7-.1 1l-.9.9c-.1.1-.1.3-.1.4.3.9 1.4 2 2.3 2.3.2 0 .3 0 .4-.1l.9-.9c.2-.3.6-.3 1-.1l2 1.2c.4.2.4.7.1 1l-.9.9c-.5.5-1.2.7-1.9.5C7.4 10.6 5.4 8.6 3.7 4.9c-.2-.7 0-1.4.5-1.9z" fill="currentColor"/>
        </svg>
        <span>+421 915 319 934</span>
      </a>
    </div>
    <div class="topbar__social">
      <span class="topbar__social-label">Sledujte nás:</span>
      <?php if ($fb !== ''): ?>
      <a href="<?= e($fb) ?>" class="topbar__social-link" aria-label="Facebook" rel="noopener" target="_blank">
        <img src="<?= e(\Kuko\Asset::url('/assets/icons/facebook-app-symbol.svg')) ?>" width="16" height="16" alt="Facebook">
      </a>
      <?php endif; ?>
      <?php if ($ig !== ''): ?>
      <a href="<?= e($ig) ?>" class="topbar__social-link" aria-label="Instagram" rel="noopener" target="_blank">
        <img src="<?= e(\Kuko\Asset::url('/assets/icons/instagram.svg')) ?>" width="16" height="16" alt="Instagram">
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>
<header class="nav">
  <div class="container nav__brand-row">
    <a href="/" class="nav__brand" aria-label="KUKO detský svet — domov">
      <img src="<?= e(\Kuko\Asset::url('/assets/img/logo.png')) ?>" alt="KUKO detský svet" width="200" height="148">
    </a>
    <button class="nav__toggle" aria-controls="primary-nav" aria-expanded="false" aria-label="Otvoriť menu">
      <span></span><span></span><span></span>
    </button>
  </div>
  <div class="nav__band">
    <nav id="primary-nav" class="nav__menu container" aria-label="Hlavná navigácia">
      <a href="/#domov">Domov</a>
      <a href="/#o-nas">O detskom svete</a>
      <a href="/#oslavy">Detské oslavy</a>
      <a href="/#cennik">Cenník služieb</a>
      <a href="/#galeria">Fotogaléria</a>
      <a href="/#kontakt">Kontakt</a>
    </nav>
  </div>
</header>
