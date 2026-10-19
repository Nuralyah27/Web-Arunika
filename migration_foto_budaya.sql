-- Jalankan sekali pada database arunika_db jika kolom belum ada
ALTER TABLE cultures ADD COLUMN image_path VARCHAR(255) NULL;
