<?php
/**
 * Fungsi autentikasi berbasis session PHP.
 * Password akun sekaligus dipakai sebagai bahan menurunkan kunci
 * yang melindungi kunci privat RSA (lihat crypto.php), jadi ia
 * disimpan sekali di session per permintaan login -- BUKAN disimpan
 * permanen di mana pun selain sebagai hash di kolom password_hash.
 */

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']);
}

function requireLogin(): void
{
    if (!isLoggedIn()) {
        header('Location: ' . url('/auth/login.php'));
        exit;
    }
}

function currentUserId(): ?int
{
    return $_SESSION['user_id'] ?? null;
}

function currentUsername(): ?string
{
    return $_SESSION['username'] ?? null;
}

function registerUser(string $username, string $email, string $password): array
{
    $username = trim($username);
    $email = trim($email);

    if (strlen($username) < 3) {
        return ['ok' => false, 'error' => 'Nama pengguna minimal 3 karakter.'];
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['ok' => false, 'error' => 'Alamat email tidak valid.'];
    }
    if (strlen($password) < 8) {
        return ['ok' => false, 'error' => 'Kata sandi minimal 8 karakter.'];
    }

    $db = getDB();

    $check = $db->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $check->execute([$username, $email]);
    if ($check->fetch()) {
        return ['ok' => false, 'error' => 'Nama pengguna atau email sudah dipakai.'];
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $db->prepare('INSERT INTO users (username, email, password_hash) VALUES (?, ?, ?)');
    $stmt->execute([$username, $email, $hash]);

    return ['ok' => true, 'user_id' => (int) $db->lastInsertId()];
}

function attemptLogin(string $username, string $password): array
{
    $db = getDB();
    $stmt = $db->prepare('SELECT id, username, password_hash FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return ['ok' => false, 'error' => 'Nama pengguna atau kata sandi salah.'];
    }

    session_regenerate_id(true);
    $_SESSION['user_id']  = (int) $user['id'];
    $_SESSION['username'] = $user['username'];

    return ['ok' => true];
}

function logoutUser(): void
{
    $_SESSION = [];
    session_destroy();
}

function logActivity(int $userId, string $action, ?int $imageId = null, ?string $detail = null): void
{
    $db = getDB();
    $stmt = $db->prepare(
        'INSERT INTO activity_log (user_id, image_id, action, detail, ip_address) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$userId, $imageId, $action, $detail, $_SERVER['REMOTE_ADDR'] ?? null]);
}
