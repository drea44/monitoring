<?php
// Load .env file jika ada (untuk hosting yang tidak support system env vars)
require_once __DIR__ . '/env.php';

/**
 * Ambil nilai environment variable dengan fallback.
 *
 * @param string $key      Nama variable
 * @param string $default  Nilai default jika tidak ada
 */
function env(string $key, string $default = ''): string
{
    $val = getenv($key);
    if ($val !== false && $val !== '') {
        return $val;
    }
    return $_ENV[$key] ?? $default;
}

// ---------------------------------------------------------------
// Database configuration
// Nilai dibaca dari environment variable (file .env atau system).
// Fallback ke XAMPP defaults agar tetap berjalan di lokal.
// ---------------------------------------------------------------
define('DB_HOST',    env('DB_HOST',    '127.0.0.1'));
define('DB_PORT',    env('DB_PORT',    '3306'));
define('DB_NAME',    env('DB_NAME',    'monitoring_mobil_kantor'));
define('DB_USER',    env('DB_USER',    'root'));
define('DB_PASS',    env('DB_PASS',    ''));
define('DB_CHARSET', env('DB_CHARSET', 'utf8mb4'));

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensure_app_schema($pdo);
        } catch (PDOException $e) {
            // Fallback ke root tanpa password jika koneksi pertama gagal (XAMPP lokal)
            try {
                $pdo = new PDO($dsn, 'root', '', $options);
                ensure_app_schema($pdo);
            } catch (PDOException $ex) {
                die('Koneksi database gagal. Periksa konfigurasi .env atau hubungi administrator.');
            }
        }
    }

    return $pdo;
}

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

        $deptColumn = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'department'")->fetch();
        if (!$deptColumn) {
            $pdo->exec("ALTER TABLE bookings ADD COLUMN department VARCHAR(100) NULL AFTER user_id");
            $pdo->exec("UPDATE bookings b JOIN users u ON u.id = b.user_id SET b.department = u.department WHERE b.department IS NULL OR b.department = ''");
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
        }
    } catch (Throwable $e) {
    }
}
