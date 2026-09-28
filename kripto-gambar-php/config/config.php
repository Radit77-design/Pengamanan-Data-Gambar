<?php
/**
 * Konfigurasi umum aplikasi.
 */

define('BASE_PATH', dirname(__DIR__));
define('UPLOAD_DIR', BASE_PATH . '/uploads/encrypted/');
define('MAX_UPLOAD_BYTES', 10 * 1024 * 1024); // 10 MB

// Kalau muncul error "Gagal membangkitkan kunci RSA ... BIO routines::no
// such file" saat membuat kunci, artinya PHP tidak menemukan openssl.cnf
// secara otomatis. Cari berkas itu di folder instalasi XAMPP-mu (paling
// sering di C:\xampp\apache\bin\openssl.cnf), lalu isi baris di bawah ini
// dan hapus tanda komentarnya. Pakai garis miring depan (/), bukan (\).
// define('OPENSSL_CNF_PATH', 'C:/xampp/apache/bin/openssl.cnf');

/**
 * BASE_URL dideteksi otomatis, bukan ditulis tetap. Ini penting karena
 * proyek ini bisa ditaruh di htdocs langsung (http://localhost/) atau
 * di subfolder (http://localhost/kripto-gambar-php/) tergantung cara
 * orang mengekstraknya -- deteksi otomatis membuat semua tautan tetap
 * benar di kedua kasus, tanpa perlu mengedit konstanta manual.
 */
$docRoot     = str_replace('\\', '/', rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/'));
$projectRoot = str_replace('\\', '/', rtrim(BASE_PATH, '/'));
$detected    = '';
if ($docRoot !== '' && strpos($projectRoot, $docRoot) === 0) {
    $detected = substr($projectRoot, strlen($docRoot));
}
define('BASE_URL', $detected); // contoh hasil: '' atau '/kripto-gambar-php'

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Jakarta');

require_once __DIR__ . '/database.php';
require_once BASE_PATH . '/includes/crypto.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/includes/functions.php';
