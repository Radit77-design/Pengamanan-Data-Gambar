<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$userId = currentUserId();
$imageId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

$db = getDB();
$stmt = $db->prepare(
    'SELECT ei.*, rk.fingerprint AS expected_fingerprint
     FROM encrypted_images ei
     JOIN rsa_keys rk ON rk.id = ei.rsa_key_id
     WHERE ei.id = ? AND ei.user_id = ?'
);
$stmt->execute([$imageId, $userId]);
$image = $stmt->fetch();

$error = null;
$decrypted = null;
$pastedKey = trim($_POST['private_key_pem'] ?? '');

if (!$image) {
    $error = 'Gambar tidak ditemukan, atau bukan milikmu.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($pastedKey === '') {
        $error = 'Tempelkan kunci privat RSA-mu dulu di kotak di bawah.';
    } else {
        try {
            // Cek dulu sidik jarinya sebelum mencoba operasi RSA yang lebih berat,
            // supaya pesan errornya jelas kalau kunci yang ditempel salah.
            $fp = fingerprintOfPrivateKey($pastedKey);
            if ($fp['fingerprint'] !== $image['expected_fingerprint']) {
                $error = 'Kunci privat ini tidak cocok dengan kunci yang dipakai mengenkripsi gambar ini (sidik jari kunci publik pasangannya berbeda). Pastikan kamu menempelkan kunci privat yang benar.';
            } else {
                $aesKey = unwrapAesKey($image['aes_key_encrypted'], $pastedKey);
                $ciphertext = file_get_contents(BASE_PATH . $image['storage_path']);
                $plain = decryptImage($ciphertext, $aesKey, hex2bin($image['aes_iv']), hex2bin($image['aes_tag']));

                if ($plain === false) {
                    logActivity($userId, 'decrypt_failed', $imageId, 'verifikasi tag GCM gagal');
                    $error = 'Dekripsi ditolak: tag autentikasi GCM tidak cocok. Ciphertext mungkin telah berubah sejak dienkripsi.';
                } elseif (hash('sha256', $plain) !== $image['sha256_original']) {
                    $error = 'Peringatan: checksum SHA-256 tidak cocok dengan gambar asli.';
                } else {
                    logActivity($userId, 'decrypt', $imageId);
                    $decrypted = ['b64' => base64_encode($plain), 'mime' => $image['mime_type']];
                    $_SESSION['dl_' . $imageId] = $plain; // buffer sekali pakai untuk tombol unduh
                }
            }
        } catch (Throwable $e) {
            $error = 'Gagal mendekripsi: ' . $e->getMessage();
        }
    }
}

$pageTitle = 'Dekripsi';
$active = 'history';
require_once __DIR__ . '/partials/header.php';
?>

<h1 class="page">Dekripsi gambar</h1>
<p class="lede">Kunci privatmu tidak disimpan di server. Untuk membuka gambar, tempelkan kunci privat PEM</p>

<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<?php if ($image): ?>
<div class="stage">
  <h2><?= e($image['original_name']) ?></h2>
  <p class="hint">
    Diunggah <?= e(date('d M Y H:i', strtotime($image['created_at']))) ?> ·
    <?= formatBytes((int)$image['original_size']) ?> asli, <?= formatBytes((int)$image['encrypted_size']) ?> terenkripsi ·
    perlu kunci privat dengan sidik jari <span class="mono"><?= e(substr($image['expected_fingerprint'],0,16)) ?>…</span>
  </p>

  <?php if (!$decrypted): ?>
    <form method="post">
      <input type="hidden" name="id" value="<?= $imageId ?>">

      <div class="field">
        <label for="pkfile">Unggah berkas kunci privat (.pem / .txt) — opsional, atau tempel langsung di bawah</label>
        <input type="file" id="pkfile" accept=".pem,.txt,text/plain">
      </div>

      <div class="field">
        <label for="private_key_pem">Kunci privat — PEM</label>
        <textarea id="private_key_pem" name="private_key_pem" placeholder="-----BEGIN PRIVATE KEY-----" required><?= e($pastedKey) ?></textarea>
      </div>

      <button type="submit">Dekripsi</button>
    </form>
    <script>
      document.getElementById('pkfile').addEventListener('change', function (e) {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = ev => { document.getElementById('private_key_pem').value = ev.target.result; };
        reader.readAsText(file);
      });
    </script>
  <?php else: ?>
    <div class="pair">
      <div>
        <div class="frame"><img src="data:<?= e($decrypted['mime']) ?>;base64,<?= $decrypted['b64'] ?>" alt=""></div>
        <div class="caption">Berhasil dipulihkan · tag GCM terverifikasi · checksum cocok</div>
      </div>
    </div>
    <div class="row" style="margin-top:16px">
      <a class="btn" href="<?= e(url('/download.php')) ?>?id=<?= $imageId ?>">Unduh gambar asli</a>
      <a class="btn ghost" href="<?= e(url('/history.php')) ?>">Kembali ke riwayat</a>
    </div>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
