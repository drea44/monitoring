-- Patch versi UX ringkas Dropping Anggaran.
-- Dropping hanya dipakai untuk input anggaran dan ringkasan dashboard.
-- Kategori Makan dihapus dari nota dan data lama dipindahkan ke Lainnya.

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES
('budget_dropping_amount', '25000000'),
('budget_note', 'Dropping anggaran operasional kendaraan kantor.');

UPDATE expenses SET category='Lainnya' WHERE category='Makan';
ALTER TABLE expenses MODIFY COLUMN category ENUM('BBM','Tol','Parkir','Lainnya') NOT NULL DEFAULT 'Lainnya';
