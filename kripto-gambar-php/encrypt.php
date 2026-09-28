<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$userId = currentUserId();
$activeKey = getActiveKeyForUser($userId);
$error = null;
$result = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$activeKey) {
        $error = 'Buat pasangan kunci RSA dulu di halaman Kunci RSA.';
    } elseif (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Tidak ada berkas yang berhasil diunggah.';
    } else {
        $file = $_FILES['image'];

        if ($file['size'] > MAX_UPLOAD_BYTES) {
            $error = 'Berkas melebihi batas ' . formatBytes(MAX_UPLOAD_BYTES) . '.';
        } else {
            $mime = mime_content_type($file['tmp_name']);
            if (strpos($mime, 'image/') !== 0) {
                $error = 'Berkas yang diunggah bukan gambar.';
            } else {
                try {
                    $plainBytes = file_get_contents($file['tmp_name']);
                    $dims = @getimagesize($file['tmp_name']);
                    $width = $dims ? $dims[0] : null;
                    $height = $dims ? $dims[1] : null;
                    $sha256 = hash('sha256', $plainBytes);

                    // ---- inti skema hybrid ----
                    $enc = encryptImage($plainBytes);                                  // AES-256-GCM
                    $wrappedKey = wrapAesKey($enc['key'], $activeKey['public_key']);    // RSA-OAEP

                    $token = bin2hex(random_bytes(16));
                    $path = encryptedFilePath($userId, $token);
                    file_put_contents($path, $enc['ciphertext']);

                    $db = getDB();
                    $stmt = $db->prepare(
                        'INSERT INTO encrypted_images
                           (user_id, rsa_key_id, original_name, mime_type, original_size, encrypted_size,
                            width, height, storage_path, aes_key_encrypted, aes_iv, aes_tag,
                            sha256_original, encryption_ms)
                         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
                    );
                    $stmt->execute([
                        $userId, $activeKey['id'], $file['name'], $mime,
                        strlen($plainBytes), strlen($enc['ciphertext']),
                        $width, $height,
                        str_replace(BASE_PATH, '', $path),
                        $wrappedKey, bin2hex($enc['iv']), bin2hex($enc['tag']),
                        $sha256, round($enc['ms'], 2),
                    ]);
                    $imageId = (int) $db->lastInsertId();

                    logActivity($userId, 'encrypt', $imageId, formatBytes(strlen($plainBytes)));

                    $result = [
                        'id' => $imageId,
                        'original_size' => strlen($plainBytes),
                        'encrypted_size' => strlen($enc['ciphertext']),
                        'ms' => $enc['ms'],
                        'entropy_plain' => shannonEntropy($plainBytes),
                        'entropy_cipher' => shannonEntropy($enc['ciphertext']),
                        'wrapped_key_preview' => substr($wrappedKey, 0, 120),
                        'width' => $width, 'height' => $height,
                        'name' => $file['name'],
                        'preview_b64' => base64_encode($plainBytes),
                        'mime' => $mime,
                    ];
                } catch (Throwable $e) {
                    $error = 'Enkripsi gagal: ' . $e->getMessage();
                }
            }
        }
    }
}

$pageTitle = 'Enkripsi';
$active = 'encrypt';
require_once __DIR__ . '/partials/header.php';
?>

<h1 class="page">Enkripsi gambar</h1>
<p class="lede">Gambar dienkripsi di server dengan kunci AES-256 acak yang baru untuk setiap berkas, lalu kunci itu dikunci dengan kunci publik RSA aktifmu.</p>

<?php if (!$activeKey): ?>
  <div class="alert err">Kamu belum punya kunci RSA. <a href="<?= e(url('/keys.php')) ?>">Buat dulu di sini</a>.</div>
<?php endif; ?>
<?php if ($error): ?><div class="alert err"><?= e($error) ?></div><?php endif; ?>

<div class="stage">
  <h2>Unggah berkas</h2>
  <form method="post" enctype="multipart/form-data">
    <div class="field">
      <label for="image">Berkas gambar (maks <?= formatBytes(MAX_UPLOAD_BYTES) ?>)</label>
      <input type="file" id="image" name="image" accept="image/*" required <?= $activeKey ? '' : 'disabled' ?>>
    </div>
    <button type="submit" <?= $activeKey ? '' : 'disabled' ?>>Enkripsi sekarang</button>
  </form>
</div>

<?php if ($result): ?>
<div class="stage">
  <h2>Hasil enkripsi</h2>
  <div class="pair">
    <div>
      <div class="frame"><img src="data:<?= e($result['mime']) ?>;base64,<?= $result['preview_b64'] ?>" alt=""></div>
      <div class="caption"><?= e($result['name']) ?> · <?= (int)$result['width'] ?>×<?= (int)$result['height'] ?> px</div>
    </div>
    <div>
      <div class="frame" style="align-items:flex-start;justify-content:flex-start;padding:12px">
        <div class="mono" style="opacity:.8">Ciphertext tersimpan di server sebagai berkas biner. Tidak ditampilkan di sini karena tidak lagi berupa gambar yang bisa dirender — buka riwayat untuk lihat metadatanya.</div>
      </div>
      <div class="caption">disimpan di uploads/encrypted/</div>
    </div>
  </div>

  <div class="metrics">
    <div class="metric"><div class="v"><?= formatBytes($result['original_size']) ?></div><div class="k">Ukuran asli</div></div>
    <div class="metric"><div class="v"><?= formatBytes($result['encrypted_size']) ?></div><div class="k">Ukuran ciphertext</div></div>
    <div class="metric"><div class="v"><?= number_format($result['ms'], 1) ?> ms</div><div class="k">Waktu enkripsi AES</div></div>
    <div class="metric good"><div class="v"><?= number_format($result['entropy_cipher'], 4) ?></div><div class="k">Entropi ciphertext (ideal 8.0)</div></div>
    <div class="metric"><div class="v"><?= number_format($result['entropy_plain'], 4) ?></div><div class="k">Entropi gambar asli</div></div>
  </div>

  <h3 style="margin-top:20px;font-size:.9rem;color:var(--ink-soft)">Kunci AES setelah dibungkus RSA-OAEP (potongan awal, base64)</h3>
  <div class="well mono"><?= e($result['wrapped_key_preview']) ?>…</div>

  <div class="row" style="margin-top:18px">
    <a class="btn" href="<?= e(url('/decrypt.php')) ?>?id=<?= $result['id'] ?>">Coba dekripsi</a>
    <a class="btn ghost" href="<?= e(url('/history.php')) ?>">Lihat riwayat</a>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/partials/footer.php'; ?>
