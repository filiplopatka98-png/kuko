<?php
/** @var bool|null $error */
/** @var string|null $next */
$title = 'Prihlásenie — KUKO admin';
ob_start();
?>
<style>
  html, body { margin: 0; padding: 0; min-height: 100vh; }
  body { display: flex; align-items: center; justify-content: center; background: #f8f9fa; }
  .login {
    max-width: 360px; width: 100%; padding: 2rem;
    background: white; border-radius: 0.75rem;
    box-shadow: 0 8px 30px rgba(0,0,0,0.08);
    margin: 2rem;
  }
  .login h1 { margin: 0 0 0.25rem; font-size: 1.4rem; color: #2c3e50; }
  .login__sub { margin: 0 0 1.5rem; color: #888; font-size: 0.9rem; }
  .login__field { margin-bottom: 0.75rem; }
  .login__field label { display: block; font-size: 0.85rem; color: #555; font-weight: 600; margin-bottom: 0.25rem; }
  .login__field input {
    width: 100%; padding: 0.55rem 0.75rem;
    border: 1px solid #d0d7de; border-radius: 0.375rem;
    font: inherit; background: white; box-sizing: border-box;
  }
  .login__field input:focus { outline: none; border-color: #5e72e4; box-shadow: 0 0 0 3px rgba(94,114,228,0.2); }
  .login__remember { display: flex; align-items: center; gap: 0.5rem; margin: 0.75rem 0 1rem; font-size: 0.9rem; color: #555; }
  .login__btn {
    width: 100%; padding: 0.65rem; background: #5e72e4; color: white;
    border: 0; border-radius: 0.375rem; cursor: pointer; font: inherit; font-weight: 600;
  }
  .login__btn:hover { background: #4c5fd1; }
  .login__error { color: #c0392b; background: #fdecea; padding: 0.5rem 0.75rem; border-radius: 0.375rem; margin-bottom: 1rem; font-size: 0.9rem; }
</style>
<form method="post" action="/admin/login" class="login" autocomplete="off">
  <h1>KUKO admin</h1>
  <p class="login__sub">Prihláste sa pre prístup k rezerváciám.</p>

  <?php if (!empty($error)): ?>
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
