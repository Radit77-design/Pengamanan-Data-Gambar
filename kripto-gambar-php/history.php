<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$userId = currentUserId();
$db = getDB();
$notice = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'delete_image') {
        $id = (int) ($_POST['id'] ?? 0);
        $result = deleteEncryptedImage($userId, $id);
        $notice = $result['ok']
            ? ['ok', 'Gambar dan berkas ciphertext-nya berhasil dihapus.']
            : ['err', $result['error']];
    } elseif ($action === 'delete_all_images') {
        $count = deleteAllEncryptedImages($userId);
        $notice = ['ok', "Berhasil menghapus $count gambar dari riwayat."];
    }
}

$stmt = $db->prepare(
    'SELECT id, original_name, original_size, encrypted_size, width, height, encryption_ms, created_at
     FROM encrypted_images WHERE user_id = ? ORDER BY created_at DESC'
);
$stmt->execute([$userId]);
$images = $stmt->fetchAll();

$logStmt = $db->prepare(
    'SELECT action, detail, created_at FROM activity_log WHERE user_id = ? ORDER BY created_at DESC LIMIT 15'
);
$logStmt->execute([$userId]);
$logs = $logStmt->fetchAll();

$pageTitle = 'Riwayat';
$active = 'history';
require_once __DIR__ . '/partials/header.php';
?>

<h1 class="page">Riwayat</h1>
<p class="lede">Semua gambar yang pernah kamu enkripsi</p>

<?php if ($notice): ?>
  <div class="alert <?= $notice[0] === 'ok' ? 'ok' : 'err' ?>"><?= e($notice[1]) ?></div>
<?php endif; ?>

<div class="stage">
  <div class="row" style="justify-content:space-between;align-items:baseline">
    <h2 style="margin:0">Gambar tersimpan</h2>
    <?php if ($images): ?>
      <form method="post" onsubmit="return confirm('Hapus SEMUA gambar dan ciphertext-nya? Tindakan ini tidak bisa dibatalkan.')">
        <input type="hidden" name="action" value="delete_all_images">
        <button type="submit" class="danger" style="padding:7px 14px;font-size:.82rem">Hapus semua</button>
      </form>
    <?php endif; ?>
  </div>

  <?php if (!$images): ?>
    <p class="hint">Belum ada gambar. <a href="<?= e(url('/encrypt.php')) ?>">Enkripsi gambar pertamamu</a>.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Nama</th><th>Dimensi</th><th>Ukuran</th><th>Waktu enkripsi</th><th>Dibuat</th><th></th><th></th></tr></thead>
      <tbody>
      <?php foreach ($images as $img): ?>
        <tr>
          <td><?= e($img['original_name']) ?></td>
          <td><?= (int)$img['width'] ?>×<?= (int)$img['height'] ?></td>
          <td><?= formatBytes((int)$img['original_size']) ?> → <?= formatBytes((int)$img['encrypted_size']) ?></td>
          <td><?= number_format((float)$img['encryption_ms'], 1) ?> ms</td>
          <td><?= e(date('d M Y H:i', strtotime($img['created_at']))) ?></td>
          <td><a href="<?= e(url('/decrypt.php')) ?>?id=<?= (int)$img['id'] ?>">Dekripsi</a></td>
          <td>
            <form method="post" onsubmit="return confirm('Hapus gambar ini beserta ciphertext-nya? Tindakan ini tidak bisa dibatalkan.')" style="display:inline">
              <input type="hidden" name="action" value="delete_image">
              <input type="hidden" name="id" value="<?= (int)$img['id'] ?>">
              <button type="submit" class="ghost" style="padding:4px 10px;font-size:.8rem;color:var(--danger);border-color:var(--danger)">Hapus</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="stage">
  <h2>Jejak aktivitas</h2>
  <?php if (!$logs): ?>
    <p class="hint">Belum ada aktivitas tercatat.</p>
  <?php else: ?>
    <table>
      <thead><tr><th>Aksi</th><th>Detail</th><th>Waktu</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
        <tr>
          <td><span class="badge"><?= e($l['action']) ?></span></td>
          <td><?= e($l['detail'] ?? '—') ?></td>
          <td><?= e(date('d M Y H:i:s', strtotime($l['created_at']))) ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
