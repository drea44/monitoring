<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
sync_all_cars_and_drivers_status();
header('Content-Type: application/json');

$date = $_GET['date'] ?? '';
$returnDate = $_GET['return_date'] ?? $date;
$start = $_GET['start_time'] ?? '';
$end = $_GET['end_time'] ?? '';

if (!$date || !$returnDate || !$start || !$end) {
    echo json_encode(['cars' => [], 'drivers' => [], 'error' => 'Parameter tidak lengkap']);
    exit;
}

$startDT = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $start);
$endDT = DateTime::createFromFormat('Y-m-d H:i', $returnDate . ' ' . $end);
if (!$startDT || !$endDT || $startDT >= $endDT) {
    echo json_encode(['cars' => [], 'drivers' => [], 'error' => 'Tanggal/jam pulang harus lebih besar dari tanggal/jam berangkat']);
    exit;
}

$busySql = "SELECT 1 FROM bookings b
            WHERE b.status IN ('pending','approved','running')
              AND TIMESTAMP(b.date, b.start_time) < TIMESTAMP(:return_date, :end_time)
              AND TIMESTAMP(COALESCE(b.return_date, b.date), b.end_time) > TIMESTAMP(:date, :start_time)";

$cars = db()->prepare("SELECT * FROM cars c
    WHERE c.status = 'available'
      AND NOT EXISTS ($busySql AND b.car_id = c.id)
    ORDER BY c.name");
$cars->execute(['date' => $date, 'return_date' => $returnDate, 'start_time' => $start, 'end_time' => $end]);

$drivers = db()->prepare("SELECT * FROM drivers d
    WHERE d.status = 'available'
      AND NOT EXISTS ($busySql AND b.driver_id = d.id)
    ORDER BY d.name");
$drivers->execute(['date' => $date, 'return_date' => $returnDate, 'start_time' => $start, 'end_time' => $end]);

echo json_encode(['cars' => $cars->fetchAll(), 'drivers' => $drivers->fetchAll()]);
