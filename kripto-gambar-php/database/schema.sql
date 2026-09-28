-- =========================================================
-- Skema database: Implementasi Kriptografi Hybrid RSA + AES-256
-- untuk Pengamanan Data Gambar
-- =========================================================
-- Import lewat phpMyAdmin, atau:
--   mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS kripto_gambar
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE kripto_gambar;

-- ---------------------------------------------------------
-- Pengguna
-- ---------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(50)  NOT NULL UNIQUE,
  email         VARCHAR(120) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Pasangan kunci RSA milik tiap pengguna.
-- Server HANYA menyimpan kunci PUBLIK. Kunci privat ditampilkan satu
-- kali saja ke pengguna tepat setelah dibuat (lihat keys.php), lalu
-- tidak pernah disimpan di server dalam bentuk apa pun -- pengguna
-- sendiri yang bertanggung jawab menyimpannya, dan wajib menempelkannya
-- secara manual setiap kali ingin mendekripsi gambar (lihat decrypt.php).
-- Ini praktik yang benar untuk kriptografi asimetris: pemegang kunci
-- privat adalah satu-satunya pihak yang bisa membuka data.
-- ---------------------------------------------------------
CREATE TABLE rsa_keys (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NOT NULL,
  public_key          TEXT NOT NULL,
  fingerprint         VARCHAR(64) NOT NULL,   -- SHA-256 dari kunci publik
  is_active           TINYINT(1) NOT NULL DEFAULT 1,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_rsa_user (user_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Gambar yang telah dienkripsi.
-- File ciphertext disimpan di disk (uploads/encrypted/), tabel
-- ini menyimpan metadata dan bahan-bahan dekripsinya.
-- ---------------------------------------------------------
CREATE TABLE encrypted_images (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id             INT UNSIGNED NOT NULL,
  rsa_key_id          INT UNSIGNED NOT NULL,
  original_name       VARCHAR(255) NOT NULL,
  mime_type           VARCHAR(100) NOT NULL,
  original_size       INT UNSIGNED NOT NULL,   -- byte, sebelum dienkripsi
  encrypted_size       INT UNSIGNED NOT NULL,  -- byte, setelah dienkripsi
  width               SMALLINT UNSIGNED DEFAULT NULL,
  height              SMALLINT UNSIGNED DEFAULT NULL,
  storage_path        VARCHAR(255) NOT NULL,   -- path file ciphertext di server
  aes_key_encrypted   TEXT NOT NULL,           -- kunci AES, dibungkus RSA-OAEP, base64
  aes_iv              VARCHAR(32) NOT NULL,    -- IV AES-GCM, hex 12 byte
  aes_tag             VARCHAR(32) NOT NULL,    -- tag autentikasi GCM, hex 16 byte
  sha256_original     VARCHAR(64) NOT NULL,    -- checksum gambar asli, untuk verifikasi
  encryption_ms       DECIMAL(8,2) DEFAULT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (rsa_key_id) REFERENCES rsa_keys(id) ON DELETE RESTRICT,
  INDEX idx_img_user (user_id),
  INDEX idx_img_created (created_at)
) ENGINE=InnoDB;

-- ---------------------------------------------------------
-- Jejak aktivitas -- berguna untuk bab pengujian / lampiran skripsi
-- ---------------------------------------------------------
CREATE TABLE activity_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NOT NULL,
  image_id    INT UNSIGNED DEFAULT NULL,
  action      ENUM('generate_key','encrypt','decrypt','decrypt_failed','download','delete_image','delete_key') NOT NULL,
  detail      VARCHAR(255) DEFAULT NULL,
  ip_address  VARCHAR(45) DEFAULT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (image_id) REFERENCES encrypted_images(id) ON DELETE SET NULL,
  INDEX idx_log_user (user_id)
) ENGINE=InnoDB;
