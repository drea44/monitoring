<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$date = trim($_GET['date'] ?? '');
if (!valid_date_value($date)) {
    echo json_encode(['ok' => false, 'message' => 'Tanggal tidak valid']);
    exit;
}

$params = [$date, $date];
$sql = "SELECT b.*, u.name requester, u.department, c.name car_name, c.plate_number, d.name driver_name,
               COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.booking_id = b.id), 0) AS nota_total
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        LEFT JOIN cars c ON c.id = b.car_id
        LEFT JOIN drivers d ON d.id = b.driver_id
        WHERE b.date <= ? AND COALESCE(b.return_date, b.date) >= ?";

if (!is_admin()) {
    $sql .= " AND b.user_id = ?";
    $params[] = current_user()['id'];
}
$sql .= " ORDER BY b.start_time, b.id";

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rawBookings = $stmt->fetchAll();

$stmtDrivers = db()->prepare("SELECT id, name FROM drivers WHERE status != 'inactive' ORDER BY name");
$stmtDrivers->execute();
$allDrivers = $stmtDrivers->fetchAll();

$bookings = [];
foreach ($rawBookings as $b) {
    $dateStart = new DateTime($b['date']);
    $dateEnd   = new DateTime($b['return_date'] ?: $b['date']);
    $durasiHari = (int)$dateStart->diff($dateEnd)->days + 1;

    $advanceAmt = (float)($b['advance_amount'] ?? 0);
    $allowance  = (float)($b['allowance'] ?? 0);
    $notaTotal  = (float)($b['nota_total'] ?? 0);

    $selisih = $advanceAmt - $notaTotal;

    $b['durasi_hari']    = $durasiHari;
    $b['advance_amount'] = $advanceAmt;
    $b['allowance']      = $allowance;
    $b['nota_total']     = $notaTotal;
    $b['selisih']        = $selisih;
    $b['status_label']   = status_label($b['status']);
    $b['status_class']   = status_class($b['status']);
    $b['start_time']     = substr($b['start_time'], 0, 5);
    $b['end_time']       = substr($b['end_time'], 0, 5);
    $bookings[] = $b;
}

echo json_encode([
    'ok'       => true,
    'date'     => $date,
    'date_label' => tanggal_id($date),
    'bookings' => $bookings,
    'drivers'  => $allDrivers
]);
