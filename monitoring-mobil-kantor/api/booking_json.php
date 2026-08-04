<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
$params = [$id];
$sql = "SELECT b.*, u.name requester, c.name car_name, c.plate_number, d.name driver_name,
               COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.booking_id = b.id), 0) AS nota_total
        FROM bookings b
        JOIN users u ON u.id=b.user_id
        LEFT JOIN cars c ON c.id=b.car_id
        LEFT JOIN drivers d ON d.id=b.driver_id
        WHERE b.id = ?";
if (!is_admin()) {
    $sql .= " AND b.user_id = ?";
    $params[] = current_user()['id'];
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$booking = $stmt->fetch();
if (!$booking) {
    echo json_encode(['ok' => false]);
    exit;
}
$stmt = db()->prepare('SELECT name FROM passengers WHERE booking_id = ? ORDER BY id');
$stmt->execute([$id]);
$passengers = $stmt->fetchAll();

// Hitung durasi
$dateStart = new DateTime($booking['date']);
$dateEnd   = new DateTime($booking['return_date'] ?: $booking['date']);
$durasiHari = (int)$dateStart->diff($dateEnd)->days + 1;

// Ambil daftar driver aktif
$stmtDrivers = db()->prepare("SELECT id, name FROM drivers WHERE status != 'inactive' ORDER BY name");
$stmtDrivers->execute();
$allDrivers = $stmtDrivers->fetchAll();

$totalKm = ($booking['km_start'] !== null && $booking['km_end'] !== null) ? ((int)$booking['km_end'] - (int)$booking['km_start']) . ' KM' : '-';
$allowanceUsed = (float)($booking['allowance'] ?? 0);
$advanceAmt    = (float)($booking['advance_amount'] ?? 0);
$notaTotal     = (float)($booking['nota_total'] ?? 0);
// Sisa uang muka = UMK - total nota
$selisihUMK    = $advanceAmt - $notaTotal;
// Sisa uang jalan driver = allowance - nota
$sisaUangJalan = max($allowanceUsed - $notaTotal, 0);
$kekurangan    = max($notaTotal - $allowanceUsed, 0);

$booking['return_date']       = $booking['return_date'] ?: $booking['date'];
$booking['status_label']      = status_label($booking['status']);
$booking['date_label']        = tanggal_id($booking['date']);
$booking['return_date_label'] = tanggal_id($booking['return_date']);
$booking['period_label']      = periode_tanggal($booking['date'], $booking['return_date']);
$booking['start_time']        = substr($booking['start_time'], 0, 5);
$booking['end_time']          = substr($booking['end_time'], 0, 5);
$booking['total_km']          = $totalKm;
$booking['durasi_hari']       = $durasiHari;
$booking['advance_amount']    = $advanceAmt;
$booking['allowance']         = $allowanceUsed;
$booking['nota_total']        = $notaTotal;
$booking['selisih_umk']       = $selisihUMK;
$booking['sisa_uang_jalan']   = $sisaUangJalan;
$booking['kekurangan']        = $kekurangan;
$booking['advance_label']    = rupiah($advanceAmt);
$booking['allowance_label']   = rupiah($allowanceUsed);
$booking['nota_total_label']  = rupiah($notaTotal);
$booking['selisih_umk_label'] = rupiah($selisihUMK);

echo json_encode(['ok' => true, 'booking' => $booking, 'passengers' => $passengers, 'drivers' => $allDrivers]);
