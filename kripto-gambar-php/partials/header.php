<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= isset($pageTitle) ? e($pageTitle) . ' — ' : '' ?>Kriptografi Pengamanan Data Gambar</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,700;12..96,800&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= e(url('/assets/css/style.css')) ?>">
</head>
<body>
<div class="wrap">
  <div class="topbar">
    <a class="brand" href="<?= e(url('/index.php')) ?>">🔐 Kripto Pengamanan Data Gambar</a>
    <div class="nav">
      <?php if (isLoggedIn()): ?>
        <a href="<?= e(url('/index.php')) ?>" class="<?= ($active ?? '') === 'home' ? 'active' : '' ?>">Beranda</a>
        <a href="<?= e(url('/keys.php')) ?>" class="<?= ($active ?? '') === 'keys' ? 'active' : '' ?>">Kunci RSA</a>
        <a href="<?= e(url('/encrypt.php')) ?>" class="<?= ($active ?? '') === 'encrypt' ? 'active' : '' ?>">Enkripsi</a>
        <a href="<?= e(url('/history.php')) ?>" class="<?= ($active ?? '') === 'history' ? 'active' : '' ?>">Riwayat</a>
        <span class="userchip"><?= e(currentUsername()) ?></span>
        <a href="<?= e(url('/auth/logout.php')) ?>">Keluar</a>
      <?php else: ?>
        <a href="<?= e(url('/auth/login.php')) ?>">Masuk</a>
        <a href="<?= e(url('/auth/register.php')) ?>" class="btn ghost" style="padding:6px 14px">Daftar</a>
      <?php endif; ?>
    </div>
  </div>
