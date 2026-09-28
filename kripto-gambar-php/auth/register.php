<?php
require_once __DIR__ . '/../config/config.php';

if (isLoggedIn()) {
    header('Location: ' . url('/index.php'));
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = registerUser(
        $_POST['username'] ?? '',
        $_POST['email'] ?? '',
        $_POST['password'] ?? ''
    );

    if ($result['ok']) {
        flash('ok', 'Akun berhasil dibuat. Silakan masuk.');
        header('Location: ' . url('/auth/login.php'));
        exit;
    }
    $error = $result['error'];
}

$pageTitle = 'Daftar';
require_once __DIR__ . '/../partials/header.php';
?>

<div class="stage auth-card">
  <h1 class="page" style="font-size:1.6rem">Buat akun</h1>
  <p class="lede"></p>

  <?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

  <form method="post" novalidate>
    <div class="field">
      <label for="username">Nama pengguna</label>
      <input type="text" id="username" name="username" required minlength="3"
             value="<?= e($_POST['username'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" required
             value="<?= e($_POST['email'] ?? '') ?>">
    </div>
    <div class="field">
      <label for="password">Kata sandi (minimal 8 karakter)</label>
      <input type="password" id="password" name="password" required minlength="8">
    </div>
    <button type="submit">Daftar</button>
  </form>
  <p style="margin-top:16px;font-size:.88rem;color:var(--ink-soft)">
    Sudah punya akun? <a href="<?= e(url('/auth/login.php')) ?>">Masuk di sini</a>.
  </p>
</div>

<?php require_once __DIR__ . '/../partials/footer.php'; ?>
