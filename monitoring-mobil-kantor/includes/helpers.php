<?php
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function base_path(string $path = ''): string
{
    return rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . base_path($path));
    exit;
}

function rupiah($amount): string
{
    return 'Rp ' . number_format((float) $amount, 0, ',', '.');
}

function tanggal_id(?string $date): string
{
    if (!$date) return '-';
    $bulan = [1=>'Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
    $ts = strtotime($date);
    if ($ts === false) return '-';
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function bulan_tahun_id(?string $ym): string
{
    if (!$ym) $ym = date('Y-m');
    $bulanFull = [1=>'Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
    $ts = strtotime(strlen($ym) === 7 ? $ym . '-01' : $ym);
    if ($ts === false) return '-';
    $m = (int)date('n', $ts);
    $y = date('Y', $ts);
    return ($bulanFull[$m] ?? '') . ' ' . $y;
}

function periode_tanggal(?string $startDate, ?string $endDate): string
{
    if (!$startDate) return '-';
    $endDate = $endDate ?: $startDate;
    if ($startDate === $endDate) return tanggal_id($startDate);
    return tanggal_id($startDate) . ' - ' . tanggal_id($endDate);
}

function periode_jam(?string $startTime, ?string $endTime): string
{
    $start = $startTime ? substr($startTime, 0, 5) : '-';
    $end = $endTime ? substr($endTime, 0, 5) : '-';
    return $start . ' - ' . $end;
}

function status_label(string $status): string
{
    return [
        'pending' => 'Pending',
        'approved' => 'Disetujui',
        'running' => 'Berjalan',
        'completed' => 'Selesai',
        'rejected' => 'Ditolak',
    ][$status] ?? ucfirst($status);
}

function status_class(string $status): string
{
    return [
        'pending' => 'badge-warning',
        'approved' => 'badge-success',
        'running' => 'badge-info',
        'completed' => 'badge-primary',
        'rejected' => 'badge-danger',
    ][$status] ?? 'badge-muted';
}

function flash(?string $type = null, ?string $message = null): ?array
{
    if ($type && $message) {
        $_SESSION['flash'] = ['type' => $type, 'message' => $message];
        return null;
    }
    if (!empty($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

function booking_code(): string
{
    return 'BK-' . date('ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
}

function valid_date_value(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

function valid_time_value(string $time): bool
{
    $dt = DateTime::createFromFormat('H:i', $time) ?: DateTime::createFromFormat('H:i:s', $time);
    return (bool) $dt;
}

function get_app_setting(string $key, $default = null)
{
    try {
        $stmt = db()->prepare('SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1');
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value === false ? $default : $value;
    } catch (Throwable $e) {
        return $default;
    }
}

function set_app_setting(string $key, $value): void
{
    $stmt = db()->prepare('INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)');
    $stmt->execute([$key, (string) $value]);
}

function booking_trip_used(array $booking): float
{
    return (float)($booking['allowance'] ?? 0)
        + (float)($booking['fuel_cost'] ?? 0)
        + (float)($booking['toll_cost'] ?? 0)
        + (float)($booking['parking_cost'] ?? 0)
        + (float)($booking['other_cost'] ?? 0);
}

function budget_total_dropping(): float
{
    try {
        $total = db()->query('SELECT COALESCE(SUM(amount),0) FROM budget_entries')->fetchColumn();
        return (float) $total;
    } catch (Throwable $e) {
        return (float) get_app_setting('budget_dropping_amount', 0);
    }
}

function budget_summary(?string $month = null): array
{
    $whereDrop = '';
    $whereBook = "WHERE status <> 'rejected'";
    $paramsDrop = [];
    $paramsBook = [];

    if (!empty($month)) {
        $whereDrop = "WHERE DATE_FORMAT(entry_date, '%Y-%m') = ?";
        $paramsDrop[] = $month;
        $whereBook .= " AND DATE_FORMAT(date, '%Y-%m') = ?";
        $paramsBook[] = $month;
    }

    try {
        $stmtDrop = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM budget_entries $whereDrop");
        $stmtDrop->execute($paramsDrop);
        $dropping = (float)$stmtDrop->fetchColumn();
    } catch (Throwable $e) { $dropping = 0; }

    try {
        $stmtBook = db()->prepare("SELECT
            COALESCE(SUM(allowance),0) AS allowance_total,
            COALESCE(SUM(fuel_cost + toll_cost + parking_cost + other_cost),0) AS expense_total
            FROM bookings $whereBook");
        $stmtBook->execute($paramsBook);
        $row = $stmtBook->fetch() ?: [];
    } catch (Throwable $e) {
        $row = ['allowance_total' => 0, 'expense_total' => 0];
    }

    $allowance = (float)($row['allowance_total'] ?? 0);
    $expense   = (float)($row['expense_total'] ?? 0);

    return [
        'dropping'  => $dropping,
        'allowance' => $allowance,
        'expense'   => $expense,
        'remaining' => $dropping - $allowance,
    ];
}

function sync_all_cars_and_drivers_status(): void
{
    try {
        $activeCarsSql = "SELECT DISTINCT car_id FROM bookings WHERE (status = 'running' OR (status = 'approved' AND CURDATE() BETWEEN date AND COALESCE(return_date, date))) AND car_id IS NOT NULL";
        db()->exec("UPDATE cars SET status = 'available' WHERE status = 'used' AND id NOT IN ($activeCarsSql)");
        db()->exec("UPDATE cars SET status = 'used' WHERE status NOT IN ('maintenance', 'inactive') AND id IN ($activeCarsSql)");

        $activeDriversSql = "SELECT DISTINCT driver_id FROM bookings WHERE (status = 'running' OR (status = 'approved' AND CURDATE() BETWEEN date AND COALESCE(return_date, date))) AND driver_id IS NOT NULL";
        db()->exec("UPDATE drivers SET status = 'available' WHERE status = 'assigned' AND id NOT IN ($activeDriversSql)");
        db()->exec("UPDATE drivers SET status = 'assigned' WHERE status NOT IN ('leave', 'inactive') AND id IN ($activeDriversSql)");
    } catch (Throwable $e) {}
}
