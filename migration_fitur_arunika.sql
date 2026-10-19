-- Jalankan sekali pada database arunika_db yang sudah berisi tabel cultures.
-- Jika kolom tertentu sudah ada, hapus baris ALTER untuk kolom tersebut sebelum menjalankan.
ALTER TABLE cultures ADD COLUMN image_path VARCHAR(255) NULL;
ALTER TABLE cultures ADD COLUMN usage_notes TEXT NULL;

-- Tabel kuis menyimpan riwayat percobaan agar fitur belajar bisa dikembangkan.
CREATE TABLE IF NOT EXISTS quiz_attempts (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL,
  score INT NOT NULL DEFAULT 0,
  total_questions INT NOT NULL DEFAULT 0,
  completed_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_quiz_user (user_id),
  CONSTRAINT fk_quiz_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
