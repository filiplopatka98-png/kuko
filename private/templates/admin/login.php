<?php
/** @var bool|null $error */
/** @var string|null $next */
$title = 'Prihlásenie — KUKO admin';
$stylesheets = ['/assets/css/admin.css'];
ob_start();
?>
<style>
  html, body { margin: 0; padding: 0; min-height: 100vh; }
  body {
    display: flex; align-items: center; justify-content: center; background: #FFF8EE;
    font-family: "Nunito Sans", system-ui, sans-serif; color: #3D3D3D;
  }
  .login {
    max-width: 360px; width: 100%; padding: 2rem;
    background: #fff; border-radius: 0.75rem;
    box-shadow: 0 8px 30px rgba(216,139,190,0.12);
    border: 1px solid #F3E4EE;
    margin: 2rem;
    font-family: "Nunito Sans", system-ui, sans-serif;
  }
  .login h1 { margin: 0 0 0.25rem; font-size: 1.4rem; color: #3D3D3D; }
  .login__sub { margin: 0 0 1.5rem; color: #7A7A7A; font-size: 0.9rem; }
  .login__field { margin-bottom: 0.75rem; }
  .login__field label { display: block; font-size: 0.85rem; color: #3D3D3D; font-weight: 600; margin-bottom: 0.25rem; }
  .login__field input {
    width: 100%; padding: 0.55rem 0.75rem;
    border: 1px solid #E7D4DF; border-radius: 0.375rem;
    font: inherit; background: white; box-sizing: border-box;
  }
  .login__field input:focus { outline: none; border-color: #D88BBE; box-shadow: 0 0 0 3px rgba(216,139,190,0.25); }
  .login__remember { display: flex; align-items: center; gap: 0.5rem; margin: 0.75rem 0 1rem; font-size: 0.9rem; color: #7A7A7A; }
  .login__btn {
    width: 100%; padding: 0.65rem; background: #D88BBE; color: #fff;
    border: 0; border-radius: 0.375rem; cursor: pointer; font: inherit; font-weight: 600;
  }
  .login__btn:hover { background: #c373a8; }
  .login__error { color: #c0392b; background: #fdecea; padding: 0.5rem 0.75rem; border-radius: 0.375rem; margin-bottom: 1rem; font-size: 0.9rem; }
</style>
<form method="post" action="/admin/login" class="login" autocomplete="off">
  <input type="hidden" name="csrf" value="<?= e(\Kuko\Csrf::token()) ?>">
  <h1>KUKO admin</h1>
  <p class="login__sub">Prihláste sa pre prístup k rezerváciám.</p>

  <?php if (!empty($locked)): ?>
    <p class="login__error">Príliš veľa pokusov. Skúste znova o hodinu.</p>
  <?php elseif (!empty($expired)): ?>
    <p class="login__error">Bezpečnostný token vypršal. Skúste sa prihlásiť znova.</p>
  <?php elseif (!empty($error)): ?>
    <p class="login__error">Nesprávne meno alebo heslo.</p>
  <?php endif; ?>

  <input type="hidden" name="next" value="<?= e($next ?? '/admin') ?>">

  <div class="login__field">
    <label for="username">Používateľ</label>
    <input type="text" name="username" id="username" required autofocus autocomplete="username">
  </div>
  <div class="login__field">
    <label for="password">Heslo</label>
    <input type="password" name="password" id="password" required autocomplete="current-password">
  </div>
  <label class="login__remember">
    <input type="checkbox" name="remember" value="1"> Zostať prihlásený 30 dní
  </label>
  <button type="submit" class="login__btn">Prihlásiť sa</button>
</form>
<?php
$content = ob_get_clean();
require __DIR__ . '/../layout-minimal.php';
