# Implementasi Kriptografi Hybrid RSA + AES-256 untuk Pengamanan Data Gambar

Aplikasi web PHP + MySQL. Setiap pengguna punya pasangan kunci RSA-2048
sendiri. Gambar yang diunggah dienkripsi dengan AES-256-GCM memakai
kunci sesi acak, lalu kunci sesi itu dikunci dengan RSA-OAEP milik
pengguna. **Server hanya menyimpan kunci publik** -- kunci privat
ditampilkan satu kali saat dibuat, lalu pengguna menempelkannya
sendiri secara manual setiap kali ingin mendekripsi gambar.

## Kebutuhan
- XAMPP (Apache + MySQL + PHP 8.0 ke atas, ekstensi `openssl` dan `pdo_mysql` aktif — keduanya aktif secara default di XAMPP)

## Instalasi

1. **Salin folder proyek**
   Salin seluruh folder `kripto-gambar-php` ke `C:\xampp\htdocs\kripto-gambar-php`.

2. **Buat database**
   Buka phpMyAdmin (`http://localhost/phpmyadmin`), lalu impor
   `database/schema.sql` (tab Import, pilih berkasnya, klik Go). Ini
   membuat database `kripto_gambar` beserta empat tabelnya.

3. **Sesuaikan kredensial database (kalau perlu)**
   Buka `config/database.php`. Default XAMPP: host `localhost`, user
   `root`, password kosong — biasanya tidak perlu diubah.

4. **Beri folder upload izin tulis**
   Pastikan folder `uploads/encrypted/` bisa ditulis oleh Apache. Di
   Windows biasanya tidak masalah; di Linux/Mac jalankan
   `chmod -R 775 uploads/`.

5. **Buka aplikasi**
   Jalankan Apache dan MySQL dari XAMPP Control Panel, lalu buka
   `http://localhost/kripto-gambar-php/`. Daftar akun, buat pasangan
   kunci RSA (unduh/salin kunci privatnya -- hanya ditampilkan sekali!),
   lalu mulai enkripsi gambar.

> Sudah pernah mengimpor `schema.sql` versi lama (yang punya kolom
> `private_key_encrypted`)? Jalankan
> `database/migrate_v2_hapus_private_key.sql` lewat phpMyAdmin untuk
> memperbarui strukturnya, atau kalau belum ada kunci yang berhasil
> dibuat, lebih gampang hapus saja database-nya dan impor ulang
> `schema.sql` dari awal.

## Struktur database

```
users            -- akun pengguna, password di-hash dengan password_hash()
rsa_keys         -- HANYA kunci publik + sidik jari per pengguna;
                    kunci privat tidak pernah disimpan di sini
encrypted_images -- metadata tiap gambar: ukuran, dimensi, path ciphertext,
                    kunci AES yang sudah dibungkus RSA, IV, tag GCM, checksum
activity_log     -- jejak generate_key / encrypt / decrypt / download
```

Berkas ciphertext gambar sendiri disimpan di disk
(`uploads/encrypted/<user_id>/<token>.bin`), bukan di database —
praktik umum untuk data biner besar, database cukup menyimpan path
dan metadatanya.

## Alur kriptografi

```
Enkripsi:
  gambar asli --AES-256-GCM(kunci sesi acak)--> ciphertext + tag
  kunci sesi  --RSA-OAEP(kunci publik pengguna)--> kunci terbungkus
  simpan: ciphertext (disk), kunci terbungkus + IV + tag (database)

Dekripsi:
  pengguna menempelkan kunci privat PEM miliknya secara manual
  kunci privat RSA --RSA-OAEP--> buka kunci sesi AES
  kunci sesi AES --AES-256-GCM--> buka ciphertext, verifikasi tag
```

## Catatan keamanan untuk laporan/skripsi

- **Kenapa hybrid**: RSA-OAEP 2048-bit hanya sanggup mengenkripsi
  ±190 byte sekali jalan dan jauh lebih lambat dari AES, sehingga
  tidak praktis untuk data gambar yang besar. AES-256-GCM menangani
  datanya, RSA hanya mengunci kunci AES yang pendek.
- **Kenapa GCM, bukan ECB/CBC**: GCM memberi kerahasiaan sekaligus
  integritas lewat tag autentikasi 16 byte. Ciphertext yang diubah
  satu bit pun akan gagal diverifikasi dan dekripsi ditolak
  (`decryptImage()` mengembalikan `false`), bukan menghasilkan gambar
  rusak diam-diam.
- **Kunci privat tidak pernah menyentuh server lebih dari sekali**.
  Ia hanya ada di memori server sesaat saat dibangkitkan, ditampilkan
  satu kali ke pengguna untuk diunduh/disalin, lalu dibuang. Server
  hanya menyimpan kunci publik. Implikasinya: kalau pengguna
  kehilangan kunci privatnya, seluruh gambar yang dikunci dengannya
  tidak bisa dipulihkan oleh siapa pun, termasuk pemilik server. Ini
  trade-off yang disengaja dan sesuai praktik baku kriptografi
  asimetris -- server yang menjadi perantara enkripsi seharusnya tidak
  pernah punya kemampuan membuka data kliennya sendiri.
- **Uji avalanche**: karena AES-GCM adalah mode counter, satu bit
  plaintext berubah hanya membalik satu bit ciphertext di posisi yang
  sama — bukan ~50% seperti pada cipher blok mode ECB/CBC murni. Yang
  tepat diuji untuk GCM adalah: (a) IV baru menghasilkan ciphertext
  yang sama sekali berbeda meski plaintext identik, dan (b) tag
  autentikasi berubah drastis saat plaintext berubah sedikit pun. Ini
  sudah didemonstrasikan di versi front-end (artifact) sebelumnya;
  silakan direplikasi di sisi PHP dengan pola yang sama bila
  dibutuhkan untuk bab pengujian.

## Pemecahan masalah

**"Gagal membangkitkan kunci RSA: error:10000080:BIO routines::no such file"**
Ini masalah umum di XAMPP Windows: PHP tidak tahu lokasi berkas
`openssl.cnf`. Kode sudah mencoba menebak lokasinya otomatis di
tempat-tempat umum (`C:\xampp\apache\bin\openssl.cnf`, dll). Kalau
masih gagal, cari sendiri berkas `openssl.cnf` di dalam folder
instalasi XAMPP-mu (biasanya lewat pencarian file Windows), lalu buka
`config/config.php` dan tambahkan baris berikut (hapus tanda `//` di
depannya, ganti path sesuai lokasi yang kamu temukan, pakai garis
miring depan `/` bukan `\`):

```php
define('OPENSSL_CNF_PATH', 'C:/xampp/apache/bin/openssl.cnf');
```

**Halaman menampilkan "Not Found" dari Apache**
Biasanya karena tautan di aplikasi mengarah ke `localhost/...`
padahal proyek ditaruh di subfolder seperti
`localhost/kripto-gambar-php/...`. Versi ini sudah mendeteksi folder
proyek secara otomatis (lihat `BASE_URL` di `config/config.php`) --
kalau errornya masih muncul, pastikan kamu memakai berkas-berkas dari
paket terbaru, bukan salinan lama.

**Koneksi database gagal**
Pastikan MySQL menyala di XAMPP Control Panel dan database
`kripto_gambar` sudah diimpor dari `database/schema.sql`. Kalau kamu
mengubah user/password MySQL dari default, sesuaikan juga di
`config/database.php`.

## Pengembangan lanjutan yang masuk akal

- Tanda tangan digital (RSA-PSS) supaya penerima bisa memverifikasi
  identitas pengirim, bukan cuma kerahasiaan datanya.
- Enkripsi kunci privat dengan passphrase terpisah dari password
  login.
- Endpoint API (mengembalikan JSON) supaya bisa diuji dengan Postman
  atau dipakai front-end terpisah (mis. versi HTML yang sebelumnya
  dibuat bisa disambungkan ke sini).
