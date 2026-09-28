<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$userId = currentUserId();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_key') {
    $id = (int) ($_POST['id'] ?? 0);
    $result = deleteRsaKey($userId, $id);
    flash($result['ok'] ? 'ok' : 'err', $result['ok'] ? 'Pasangan kunci berhasil dihapus.' : $result['error']);
    header('Location: ' . url('/keys.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    try {
        $result = createAndStoreKeyPair($userId);
        // Kunci privat cuma "lewat" lewat flash sekali pakai -- tidak pernah
        // ditulis ke database, dan akan hilang begitu halaman ini dibuka lagi.
        flash('ok', 'Pasangan kunci RSA-2048 baru berhasil dibuat. SIMPAN kunci privat di bawah ini sekarang -- ini satu-satunya kesempatan melihatnya.');
        flash('new_private_key', $result['private']);
        flash('new_public_key', $result['public']);
        flash('new_fingerprint', $result['fingerprint']);
        header('Location: ' . url('/keys.php'));
        exit;
    } catch (Throwable $e) {
        $error = 'Gagal membuat kunci: ' . $e->getMessage();
    }
}

$activeKey = getActiveKeyForUser($userId);
$db = getDB();
$historyStmt = $db->prepare('SELECT id, fingerprint, is_active, created_at FROM rsa_keys WHERE user_id = ? ORDER BY created_at DESC');
$historyStmt->execute([$userId]);
$keyHistory = $historyStmt->fetchAll();

$okMsg = flash('ok');
$errMsg = flash('err');
$newPrivateKey = flash('new_private_key');
$newPublicKey = flash('new_public_key');
$newFingerprint = flash('new_fingerprint');

$pageTitle = 'Kunci RSA';
$active = 'keys';
require_once __DIR__ . '/partials/header.php';
?>

<h1 class="page">Kunci RSA</h1>
<p class="lede">Kunci publik dipakai server untuk mengunci kunci AES setiap kali kamu mengenkripsi gambar. Kunci privat <strong>tidak pernah disimpan di server</strong> — server hanya menyimpan kunci publik. Kamu bertanggung jawab menyimpan kunci privatmu sendiri, dan akan menempelkannya secara manual setiap kali ingin membuka gambar di halaman Dekripsi.</p>

<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>
<?php if ($errMsg): ?><div class="alert err"><?= e($errMsg) ?></div><?php endif; ?>

<?php if ($newPrivateKey): ?>
<div class="stage" style="border-left-color:var(--danger)">
  <h2 style="color:var(--danger)">Simpan kunci privat ini sekarang</h2>
  <p class="hint"><strong>Ini satu-satunya kesempatan kamu melihat kunci privat ini.</strong> Setelah meninggalkan halaman ini, server tidak menyimpan salinannya sama sekali. Unduh atau salin ke tempat aman — kamu akan membutuhkannya setiap kali mendekripsi gambar yang dikunci dengan pasangan kunci ini.</p>

  <label for="privOut">Kunci privat — PEM</label>
  <textarea id="privOut" class="mono" readonly onclick="this.select()" style="min-height:170px"><?= e($newPrivateKey) ?></textarea>

  <div class="row">
    <button type="button" onclick="copyKey()">Salin ke clipboard</button>
    <a class="btn ghost" id="dlPriv" download="kunci-privat.pem">Unduh sebagai berkas</a>
  </div>
  <p class="hint mono" id="copyMsg" style="min-height:1.2em"></p>

  <h3 style="margin-top:22px;font-size:.85rem;color:var(--ink-soft)">Sidik jari kunci publik pasangannya (SHA-256)</h3>
  <div class="well mono"><?= e(chunk_split($newFingerprint, 8, ' ')) ?></div>
</div>

<script>
  document.getElementById('dlPriv').href =
    'data:application/x-pem-file;base64,' + btoa(document.getElementById('privOut').value);

  function copyKey() {
    const ta = document.getElementById('privOut');
    ta.select();
    navigator.clipboard.writeText(ta.value).then(() => {
      document.getElementById('copyMsg').textContent = 'Tersalin ke clipboard.';
    }).catch(() => {
      document.getElementById('copyMsg').textContent = 'Gagal menyalin otomatis -- salin manual dari kotak di atas.';
    });
  }
</script>
<?php elseif ($okMsg): ?>
  <div class="alert ok"><?= e($okMsg) ?></div>
<?php endif; ?>

<div class="stage">
  <h2>Kunci aktif</h2>
  <?php if ($activeKey): ?>
    <p class="hint">Sidik jari kunci publik (SHA-256):</p>
    <div class="well mono"><?= e(chunk_split($activeKey['fingerprint'], 8, ' ')) ?></div>
    <p class="hint" style="margin-top:14px">Dibuat <?= e(date('d M Y H:i', strtotime($activeKey['created_at']))) ?>.
      Membuat kunci baru akan menonaktifkan kunci ini untuk enkripsi berikutnya — gambar lama yang sudah dikunci dengan kunci ini tetap butuh kunci privat yang sama (yang lama) untuk dibuka.</p>
  <?php else: ?>
    <p class="hint">Belum ada pasangan kunci. Buat satu untuk mulai mengenkripsi gambar.</p>
  <?php endif; ?>

  <form method="post" style="margin-top:16px" onsubmit="return confirm('Yakin? Kunci privat hanya ditampilkan sekali setelah ini dibuat.')">
    <input type="hidden" name="action" value="generate">
    <button type="submit"><?= $activeKey ? 'Buat kunci baru (ganti yang aktif)' : 'Buat pasangan kunci RSA-2048' ?></button>
  </form>
</div>

<?php if ($keyHistory): ?>
<div class="stage">
  <h2>Riwayat kunci</h2>
  <p class="hint">Kunci hanya bisa dihapus kalau sudah tidak ada gambar yang menggunakannya.</p>
  <table>
    <thead><tr><th>Sidik jari</th><th>Status</th><th>Dibuat</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($keyHistory as $k): ?>
      <tr>
        <td class="mono"><?= e(substr($k['fingerprint'], 0, 24)) ?>…</td>
        <td><?= $k['is_active'] ? '<span class="badge">aktif</span>' : 'tidak aktif' ?></td>
        <td><?= e(date('d M Y H:i', strtotime($k['created_at']))) ?></td>
        <td>
          <form method="post" onsubmit="return confirm('Hapus pasangan kunci ini? Tindakan ini tidak bisa dibatalkan.')" style="display:inline">
            <input type="hidden" name="action" value="delete_key">
            <input type="hidden" name="id" value="<?= (int)$k['id'] ?>">
            <button type="submit" class="ghost" style="padding:4px 10px;font-size:.8rem;color:var(--danger);border-color:var(--danger)">Hapus</button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
