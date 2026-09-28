<?php
require_once __DIR__ . '/config/config.php';
requireLogin();

$userId = currentUserId();
$imageId = (int) ($_GET['id'] ?? 0);

$db = getDB();
$stmt = $db->prepare('SELECT original_name, mime_type FROM encrypted_images WHERE id = ? AND user_id = ?');
$stmt->execute([$imageId, $userId]);
$image = $stmt->fetch();

$buffer = $_SESSION['dl_' . $imageId] ?? null;

if (!$image || $buffer === null) {
    http_response_code(404);
    die('Berkas tidak tersedia. Dekripsi ulang lewat halaman riwayat terlebih dahulu.');
}

logActivity($userId, 'download', $imageId);

header('Content-Type: ' . $image['mime_type']);
header('Content-Disposition: attachment; filename="pulih-' . basename($image['original_name']) . '"');
header('Content-Length: ' . strlen($buffer));
echo $buffer;

// Buffer sekali pakai -- dibersihkan setelah diunduh.
unset($_SESSION['dl_' . $imageId]);
exit;
