<?php
/**
 * Fungsi pembantu umum dipakai lintas halaman.
 */

function formatBytes(int $bytes): string
{
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1048576) return number_format($bytes / 1024, 1) . ' KB';
    return number_format($bytes / 1048576, 2) . ' MB';
}

function e(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Membangun URL yang benar terhadap folder proyek, apa pun nama
 * foldernya. Pakai ini untuk SEMUA href, form action, dan header
 * Location -- jangan pernah menulis path absolut "/xxx.php" langsung.
 */
function url(string $path): string
{
    return rtrim(BASE_URL, '/') . '/' . ltrim($path, '/');
}

function flash(string $key, ?string $value = null)
{
    if ($value !== null) {
        $_SESSION['flash'][$key] = $value;
        return null;
    }
    $v = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $v;
}

/** Mengambil pasangan kunci RSA aktif milik pengguna, atau null kalau belum ada. */
function getActiveKeyForUser(int $userId): ?array
{
    $db = getDB();
    $stmt = $db->prepare(
        'SELECT * FROM rsa_keys WHERE user_id = ? AND is_active = 1 ORDER BY created_at DESC LIMIT 1'
    );
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/**
 * Menonaktifkan kunci lama lalu membuat & menyimpan pasangan kunci RSA baru.
 * Server hanya menyimpan kunci PUBLIK -- kunci privat dikembalikan di sini
 * untuk ditampilkan SATU KALI ke pengguna (lihat keys.php), lalu tidak
 * pernah disimpan lagi di server dalam bentuk apa pun.
 */
function createAndStoreKeyPair(int $userId): array
{
    $db = getDB();
    $pair = generateRsaKeyPair();

    $db->beginTransaction();
    try {
        $db->prepare('UPDATE rsa_keys SET is_active = 0 WHERE user_id = ?')->execute([$userId]);

        $stmt = $db->prepare(
            'INSERT INTO rsa_keys (user_id, public_key, fingerprint, is_active) VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([$userId, $pair['public'], $pair['fingerprint']]);
        $keyId = (int) $db->lastInsertId();
        $db->commit();
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }

    logActivity($userId, 'generate_key', null, 'fingerprint ' . substr($pair['fingerprint'], 0, 16) . '…');

    return [
        'id' => $keyId,
        'public' => $pair['public'],
        'private' => $pair['private'], // hanya untuk ditampilkan sekali, TIDAK disimpan
        'fingerprint' => $pair['fingerprint'],
    ];
}

/** Path aman untuk berkas ciphertext di disk, unik per baris database nantinya. */
function encryptedFilePath(int $userId, string $token): string
{
    $dir = UPLOAD_DIR . $userId . '/';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return $dir . $token . '.bin';
}

/**
 * Menghapus satu gambar terenkripsi: baris database + berkas ciphertext
 * di disk. Hanya berhasil kalau gambar itu benar milik $userId.
 */
function deleteEncryptedImage(int $userId, int $imageId): array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT storage_path, original_name FROM encrypted_images WHERE id = ? AND user_id = ?');
    $stmt->execute([$imageId, $userId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'Gambar tidak ditemukan, atau bukan milikmu.'];
    }

    $db->prepare('DELETE FROM encrypted_images WHERE id = ?')->execute([$imageId]);

    $fullPath = BASE_PATH . $row['storage_path'];
    if (is_file($fullPath)) {
        @unlink($fullPath);
    }

    logActivity($userId, 'delete_image', null, $row['original_name']);

    return ['ok' => true];
}

/**
 * Menghapus semua gambar terenkripsi milik pengguna sekaligus (berkas + baris).
 * @return int jumlah gambar yang berhasil dihapus
 */
function deleteAllEncryptedImages(int $userId): int
{
    $db = getDB();
    $stmt = $db->prepare('SELECT id FROM encrypted_images WHERE user_id = ?');
    $stmt->execute([$userId]);
    $ids = array_column($stmt->fetchAll(), 'id');

    $count = 0;
    foreach ($ids as $id) {
        if (deleteEncryptedImage($userId, (int) $id)['ok']) {
            $count++;
        }
    }
    return $count;
}

/**
 * Menghapus satu pasangan kunci RSA (baris kunci publik + fingerprint).
 * Ditolak kalau masih ada gambar yang dikunci dengan pasangan kunci ini --
 * supaya tidak ada gambar yang kehilangan jejak kuncinya.
 */
function deleteRsaKey(int $userId, int $keyId): array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT id, fingerprint FROM rsa_keys WHERE id = ? AND user_id = ?');
    $stmt->execute([$keyId, $userId]);
    $key = $stmt->fetch();

    if (!$key) {
        return ['ok' => false, 'error' => 'Kunci tidak ditemukan, atau bukan milikmu.'];
    }

    $countStmt = $db->prepare('SELECT COUNT(*) c FROM encrypted_images WHERE rsa_key_id = ?');
    $countStmt->execute([$keyId]);
    $n = (int) $countStmt->fetch()['c'];

    if ($n > 0) {
        return [
            'ok' => false,
            'error' => "Tidak bisa dihapus: masih ada $n gambar yang dikunci dengan pasangan kunci ini. "
                . 'Hapus dulu gambar-gambar itu di halaman Riwayat, baru kunci ini bisa dihapus.',
        ];
    }

    $db->prepare('DELETE FROM rsa_keys WHERE id = ?')->execute([$keyId]);
    logActivity($userId, 'delete_key', null, 'fingerprint ' . substr($key['fingerprint'], 0, 16) . '…');

    return ['ok' => true];
}
