USE monitoring_mobil_kantor;

CREATE TABLE IF NOT EXISTS budget_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    reference_no VARCHAR(80) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_budget_entry_date(entry_date)
) ENGINE=InnoDB;

INSERT INTO budget_entries (entry_date, reference_no, description, amount, created_by)
SELECT CURDATE(), 'DROP-MIGRASI-AWAL', 'Migrasi nilai dropping anggaran lama', CAST(setting_value AS DECIMAL(14,2)), 1
FROM app_settings
WHERE setting_key = 'budget_dropping_amount'
  AND CAST(setting_value AS DECIMAL(14,2)) > 0
  AND NOT EXISTS (SELECT 1 FROM budget_entries);
