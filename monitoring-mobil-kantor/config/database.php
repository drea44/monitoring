<?php
/**
 * Konfigurasi database untuk XAMPP.
 * Default XAMPP: host localhost, user root, password kosong.
 */
const DB_HOST = 'localhost';
const DB_NAME = 'monitoring_mobil_kantor';
const DB_USER = 'root';
const DB_PASS = '';
const DB_CHARSET = 'utf8mb4';

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensure_app_schema($pdo);
        } catch (PDOException $e) {
            die('Koneksi database gagal. Pastikan database sudah di-import di XAMPP/phpMyAdmin. Detail: ' . $e->getMessage());
        }
    }

    return $pdo;
}

/**
 * Patch ringan agar project lama tetap jalan setelah update:
 * - menambah return_date untuk tanggal pulang
 * - mengubah kolom KM ke BIGINT supaya input KM besar tidak menyebabkan HTTP 500
 * - menambah advance_amount lama agar database versi sebelumnya tetap kompatibel
 * - menambah app_settings untuk Dropping Anggaran
 * - menyesuaikan kategori nota agar tidak memakai kategori Makan
 */
function ensure_app_schema(PDO $pdo): void
{
    static $done = false;
    if ($done) return;
    $done = true;

    try {
        $column = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'return_date'")->fetch();
        if (!$column) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN return_date DATE NULL AFTER date");
            $pdo->exec("UPDATE bookings SET return_date = date WHERE return_date IS NULL");
            $pdo->exec("ALTER TABLE bookings MODIFY COLUMN return_date DATE NOT NULL");
        } else {
            $pdo->exec("UPDATE bookings SET return_date = date WHERE return_date IS NULL");
        }

        $advanceColumn = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'advance_amount'")->fetch();
        if (!$advanceColumn) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0 AFTER allowance");
        }

        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN km_start BIGINT NULL");
        $pdo->exec("ALTER TABLE bookings MODIFY COLUMN km_end BIGINT NULL");
        $pdo->exec("ALTER TABLE cars MODIFY COLUMN last_km BIGINT NOT NULL DEFAULT 0");

        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT NULL,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $pdo->prepare("INSERT IGNORE INTO app_settings (setting_key, setting_value) VALUES (?, ?)");
        $stmt->execute(['budget_dropping_amount', '25000000']);
        $stmt->execute(['budget_note', 'Dropping anggaran operasional kendaraan kantor.']);

        try {
            $pdo->exec("UPDATE expenses SET category='Lainnya' WHERE category='Makan'");
            $pdo->exec("ALTER TABLE expenses MODIFY COLUMN category ENUM('BBM','Tol','Parkir','Lainnya') NOT NULL DEFAULT 'Lainnya'");
        } catch (Throwable $e) {
            // Abaikan jika tabel/kolom belum tersedia atau hak ALTER tidak ada.
        }
    } catch (Throwable $e) {
        // Jika user MySQL tidak punya hak ALTER, aplikasi tetap berjalan memakai struktur yang ada.
        // Jalankan file patch di folder database lewat phpMyAdmin untuk patch manual.
    }
}
