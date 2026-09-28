-- =========================================================
-- Migrasi: menghapus penyimpanan kunci privat dari server.
-- Jalankan ini HANYA kalau kamu sudah pernah mengimpor schema.sql
-- versi sebelumnya (yang punya kolom private_key_encrypted).
-- Kalau database masih baru/kosong, tidak perlu file ini --
-- cukup impor database/schema.sql versi terbaru.
-- =========================================================
USE kripto_gambar;

ALTER TABLE rsa_keys
  DROP COLUMN private_key_encrypted,
  DROP COLUMN private_key_iv;
