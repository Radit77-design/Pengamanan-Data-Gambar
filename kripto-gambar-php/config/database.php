<?php
/**
 * Koneksi database memakai PDO.
 * Sesuaikan konstanta di bawah dengan pengaturan XAMPP-mu.
 * Default MySQL XAMPP: host localhost, user root, password kosong.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'kripto_gambar');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

function getDB(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die('Koneksi database gagal. Pastikan MySQL di XAMPP menyala dan database "kripto_gambar" '
                . 'sudah diimpor dari database/schema.sql. Detail: ' . htmlspecialchars($e->getMessage()));
        }
    }

    return $pdo;
}
