-- =========================================================
-- Migrasi: menambah pilihan aksi 'delete_image' dan 'delete_key'
-- ke kolom activity_log.action, untuk mendukung fitur hapus riwayat.
-- Jalankan ini kalau database kamu dibuat SEBELUM fitur hapus ada.
-- Kalau database masih baru, tidak perlu file ini -- cukup impor
-- database/schema.sql versi terbaru.
-- =========================================================
USE kripto_gambar;

ALTER TABLE activity_log
  MODIFY action ENUM('generate_key','encrypt','decrypt','decrypt_failed','download','delete_image','delete_key') NOT NULL;
