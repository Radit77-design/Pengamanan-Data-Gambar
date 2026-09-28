<?php
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    header('Location: ' . url('/index.php'));
    exit;
}

$error = null;
$okMsg = flash('ok');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = attemptLogin($_POST['username'] ?? '', $_POST['password'] ?? '');
    if ($result['ok']) {
        header('Location: ' . url('/index.php'));
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Masuk';
require_once __DIR__ . '/../partials/header.php';
?>

<div class="stage auth-card">
  <h1 class="page" style="font-size:1.6rem">Masuk</h1>
  <p class="lede"></p>

  <?php if ($okMsg): ?><div class="alert ok"><?= e($okMsg) ?></div><?php endif; ?>
  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

  <form method="post" novalidate>
    <div class="field">
      <label for="username">Nama pengguna atau email</label>
      <input type="text" id="username" name="username" required value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">Kata sandi</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit">Masuk</button>
  </form>
  <p style="margin-top:16px;font-size:.88rem;color:var(--ink-soft)">
    Belum punya akun? <a href="<?= e(url('/auth/register.php')) ?>">Daftar di sini</a>.
  </p>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
