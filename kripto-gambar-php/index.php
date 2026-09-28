<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$db = getDB();
$userId = currentUserId();

$activeKey = getActiveKeyForUser($userId);

$countStmt = $db->prepare('SELECT COUNT(*) c FROM encrypted_images WHERE user_id = ?');
$countStmt->execute([$userId]);
$imageCount = $countStmt->fetch()['c'];

$sizeStmt = $db->prepare('SELECT COALESCE(SUM(original_size),0) s FROM encrypted_images WHERE user_id = ?');
$sizeStmt->execute([$userId]);
$totalSize = $sizeStmt->fetch()['s'];

$recentStmt = $db->prepare(
    'SELECT id, original_name, original_size, created_at FROM encrypted_images
     WHERE user_id = ? ORDER BY created_at DESC LIMIT 5'
);
$recentStmt->execute([$userId]);
$recent = $recentStmt->fetchAll();

$pageTitle = 'Beranda';
$active = 'home';
require_once __DIR__ . '/partials/header.php';
?>

<h1 class="page">Halo, <?= e(currentUsername()) ?></h1>
<p class="lede">Ringkasan aktivitas pengamanan data gambar</p>

<div class="metrics">
  <div class="metric"><div class="v"><?= (int)$imageCount ?></div><div class="k">Gambar terenkripsi</div></div>
  <div class="metric"><div class="v"><?= formatBytes((int)$totalSize) ?></div><div class="k">Total data diamankan</div></div>
  <div class="metric <?= $activeKey ? 'good' : '' ?>">
    <div class="v"><?= $activeKey ? 'Aktif' : 'Belum ada' ?></div>
    <div class="k">Kunci RSA</div>
  </div>
</div>

<div class="stage" style="margin-top:24px">
  <h2>Langkah berikutnya</h2>
  <p class="hint">
    <?php if (!$activeKey): ?>
      Kamu belum punya pasangan kunci RSA. Buat dulu di halaman Kunci RSA sebelum bisa mengenkripsi gambar.
    <?php else: ?>
      Kunci RSA sudah siap. Unggah gambar di halaman Enkripsi untuk mulai mengamankannya.
    <?php endif; ?>
  </p>
  <div class="row">
    <?php if (!$activeKey): ?>
      <a class="btn" href="<?= e(url('/keys.php')) ?>">Buat pasangan kunci</a>
    <?php else: ?>
      <a class="btn" href="<?= e(url('/encrypt.php')) ?>">Enkripsi gambar baru</a>
      <a class="btn ghost" href="<?= e(url('/keys.php')) ?>">Kelola kunci</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($recent): ?>
<div class="stage">
  <h2>Gambar terbaru</h2>
  <table>
    <thead><tr><th>Nama berkas</th><th>Ukuran</th><th>Waktu</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($recent as $row): ?>
      <tr>
        <td><?= e($row['original_name']) ?></td>
        <td><?= formatBytes((int)$row['original_size']) ?></td>
        <td><?= e(date('d M Y H:i', strtotime($row['created_at']))) ?></td>
        <td><a href="<?= e(url('/decrypt.php')) ?>?id=<?= (int)$row['id'] ?>">Buka</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
