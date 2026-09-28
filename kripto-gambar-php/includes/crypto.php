<?php
/**
 * ================================================================
 *  Fungsi kriptografi hybrid: RSA-2048 (OAEP) + AES-256-GCM
 * ================================================================
 *  Semua fungsi di sini murni memakai ekstensi OpenSSL bawaan PHP
 *  (php_openssl), tidak ada pustaka pihak ketiga.
 *
 *  Alur:
 *    1. generateRsaKeyPair()   -> pasangan kunci RSA-2048. Kunci privat
 *                                 ditampilkan SATU KALI ke pengguna
 *                                 (lihat keys.php) lalu tidak pernah
 *                                 disimpan di server dalam bentuk apa
 *                                 pun -- hanya kunci publik yang masuk
 *                                 ke database.
 *    2. encryptImage()         -> AES-256-GCM mengenkripsi byte gambar
 *    3. wrapAesKey()           -> RSA-OAEP mengunci kunci AES dengan
 *                                 kunci publik
 *    4. unwrapAesKey() + decryptImage() -> kebalikannya saat membuka;
 *                                 kunci privat ditempel manual oleh
 *                                 pengguna di halaman Dekripsi, tidak
 *                                 pernah dikirim atau disimpan server
 *                                 di luar satu permintaan HTTP itu.
 * ================================================================
 */

/**
 * Mencari berkas konfigurasi openssl.cnf.
 *
 * Di Windows, ekstensi OpenSSL PHP sering tidak tahu di mana openssl.cnf
 * berada, sehingga openssl_pkey_new() gagal dengan pesan
 * "error:10000080:BIO routines::no such file". XAMPP sebenarnya sudah
 * menyertakan berkas ini -- kita cukup menunjukkan lokasinya secara
 * eksplisit lewat opsi 'config'.
 */
function opensslConfigPath(): ?string
{
    // Kalau instalasimu tidak ketemu otomatis, isi path pastinya di
    // config/config.php lewat: define('OPENSSL_CNF_PATH', 'C:/lokasi/openssl.cnf');
    if (defined('OPENSSL_CNF_PATH') && OPENSSL_CNF_PATH && is_file(OPENSSL_CNF_PATH)) {
        return OPENSSL_CNF_PATH;
    }

    $candidates = [
        getenv('OPENSSL_CONF') ?: null,
        getenv('SSLEAY_CONF') ?: null,
        // Lokasi umum di berbagai versi XAMPP Windows
        'C:/xampp/apache/bin/openssl.cnf',
        'C:/xampp/php/extras/ssl/openssl.cnf',
        'C:/xampp/php/openssl.cnf',
        'C:/xampp/apache/conf/openssl.cnf',
        // Lokasi umum di Linux/macOS (biasanya tidak perlu, tapi jaga-jaga)
        '/etc/ssl/openssl.cnf',
        '/usr/lib/ssl/openssl.cnf',
        '/etc/pki/tls/openssl.cnf',
    ];

    foreach ($candidates as $path) {
        if ($path && is_file($path)) {
            return $path;
        }
    }

    return null;
}

/**
 * Membangkitkan pasangan kunci RSA 2048 bit.
 * @return array{public:string, private:string, fingerprint:string}
 */
function generateRsaKeyPair(): array
{
    $config = [
        'private_key_bits' => 2048,
        'private_key_type' => OPENSSL_KEYTYPE_RSA,
    ];

    $cnfPath = opensslConfigPath();
    if ($cnfPath) {
        $config['config'] = $cnfPath;
    }

    $res = openssl_pkey_new($config);
    if ($res === false) {
        $hint = $cnfPath
            ? ''
            : ' PHP tidak menemukan berkas openssl.cnf secara otomatis di server ini. '
              . 'Cari berkas "openssl.cnf" di dalam folder instalasi XAMPP-mu (biasanya di '
              . 'C:\\xampp\\apache\\bin\\openssl.cnf), lalu tambahkan baris '
              . "define('OPENSSL_CNF_PATH', 'C:/xampp/apache/bin/openssl.cnf'); "
              . 'di config/config.php (pakai garis miring depan, bukan backslash).';
        throw new RuntimeException('Gagal membangkitkan kunci RSA: ' . openssl_error_string() . $hint);
    }

    openssl_pkey_export($res, $privateKeyPem, null, $cnfPath ? ['config' => $cnfPath] : []);
    if ($privateKeyPem === null) {
        $hint = $cnfPath
            ? " (memakai konfigurasi di $cnfPath, tapi tetap gagal -- coba periksa apakah berkas itu memang berisi konfigurasi OpenSSL yang valid)"
            : ' PHP tidak menemukan openssl.cnf untuk mengekspor kunci. Ikuti petunjuk OPENSSL_CNF_PATH di config/config.php.';
        throw new RuntimeException('Gagal mengekspor kunci privat: ' . openssl_error_string() . $hint);
    }
    $details = openssl_pkey_get_details($res);
    $publicKeyPem = $details['key'];

    $fingerprint = hash('sha256', $publicKeyPem);

    return [
        'public'      => $publicKeyPem,
        'private'     => $privateKeyPem,
        'fingerprint' => $fingerprint,
    ];
}

/**
 * Mengenkripsi byte gambar dengan AES-256-GCM.
 * Membangkitkan kunci sesi dan IV baru setiap dipanggil.
 *
 * @return array{key:string, iv:string, ciphertext:string, tag:string, ms:float}
 *   key: raw 32 byte, iv: raw 12 byte, tag: raw 16 byte
 */
function encryptImage(string $plainBytes): array
{
    $key = random_bytes(32); // AES-256
    $iv  = random_bytes(12); // ukuran IV standar untuk GCM
    $tag = '';

    $t0 = microtime(true);
    $ciphertext = openssl_encrypt(
        $plainBytes,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag,
        '',   // additional authenticated data, tidak dipakai di sini
        16    // panjang tag dalam byte
    );
    $ms = (microtime(true) - $t0) * 1000;

    if ($ciphertext === false) {
        throw new RuntimeException('Enkripsi AES-GCM gagal: ' . openssl_error_string());
    }

    return ['key' => $key, 'iv' => $iv, 'ciphertext' => $ciphertext, 'tag' => $tag, 'ms' => $ms];
}

/**
 * Membuka ciphertext AES-256-GCM. Mengembalikan false kalau tag
 * autentikasi tidak cocok -- artinya data sudah diubah atau kunci salah.
 */
function decryptImage(string $ciphertext, string $key, string $iv, string $tag)
{
    return openssl_decrypt(
        $ciphertext,
        'aes-256-gcm',
        $key,
        OPENSSL_RAW_DATA,
        $iv,
        $tag
    );
}

/**
 * Membungkus (encrypt) kunci sesi AES dengan kunci publik RSA, padding OAEP.
 */
function wrapAesKey(string $aesKeyRaw, string $publicKeyPem): string
{
    $pubKey = openssl_pkey_get_public($publicKeyPem);
    if ($pubKey === false) {
        throw new RuntimeException('Kunci publik RSA tidak valid.');
    }

    $ok = openssl_public_encrypt($aesKeyRaw, $wrapped, $pubKey, OPENSSL_PKCS1_OAEP_PADDING);
    if (!$ok) {
        throw new RuntimeException('Gagal membungkus kunci AES dengan RSA: ' . openssl_error_string());
    }

    return base64_encode($wrapped);
}

/**
 * Membuka kembali kunci sesi AES memakai kunci privat RSA.
 */
function unwrapAesKey(string $wrappedB64, string $privateKeyPem): string
{
    $privKey = openssl_pkey_get_private($privateKeyPem);
    if ($privKey === false) {
        throw new RuntimeException('Kunci privat RSA tidak valid.');
    }

    $wrapped = base64_decode($wrappedB64);
    $ok = openssl_private_decrypt($wrapped, $aesKeyRaw, $privKey, OPENSSL_PKCS1_OAEP_PADDING);
    if (!$ok) {
        throw new RuntimeException('Gagal membuka kunci AES. Kunci privat tidak cocok dengan kunci publik yang dipakai mengenkripsi.');
    }

    return $aesKeyRaw;
}

/**
 * Menghitung entropi Shannon (bit/byte) -- dipakai di halaman analisis.
 */
function shannonEntropy(string $bytes): float
{
    $len = strlen($bytes);
    if ($len === 0) return 0.0;

    $freq = array_fill(0, 256, 0);
    for ($i = 0; $i < $len; $i++) {
        $freq[ord($bytes[$i])]++;
    }

    $entropy = 0.0;
    foreach ($freq as $count) {
        if ($count === 0) continue;
        $p = $count / $len;
        $entropy -= $p * log($p, 2);
    }

    return $entropy;
}

/**
 * Memvalidasi PEM kunci privat yang ditempel pengguna secara manual,
 * dan menghitung sidik jari kunci publik pasangannya -- supaya
 * halaman dekripsi bisa memberi pesan yang jelas SEBELUM mencoba
 * proses RSA kalau ternyata kunci yang ditempel salah/bukan pasangannya.
 *
 * @return array{fingerprint:string} melempar RuntimeException kalau PEM tidak valid
 */
function fingerprintOfPrivateKey(string $privateKeyPem): array
{
    $priv = openssl_pkey_get_private($privateKeyPem);
    if ($priv === false) {
        throw new RuntimeException('Teks yang ditempel bukan kunci privat RSA yang valid: ' . openssl_error_string());
    }

    $details = openssl_pkey_get_details($priv);
    if ($details === false || ($details['type'] ?? null) !== OPENSSL_KEYTYPE_RSA) {
        throw new RuntimeException('Kunci yang ditempel bukan kunci RSA.');
    }

    return ['fingerprint' => hash('sha256', $details['key'])];
}
