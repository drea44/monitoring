<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();
header('Content-Type: application/json');

$id = (int) ($_GET['id'] ?? 0);
$params = [$id];
$sql = "SELECT b.*, u.name requester, c.name car_name, c.plate_number, d.name driver_name
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
$totalKm = ($booking['km_start'] !== null && $booking['km_end'] !== null) ? ((int)$booking['km_end'] - (int)$booking['km_start']) . ' KM' : '-';
$tripExpense = (float)$booking['fuel_cost'] + (float)$booking['toll_cost'] + (float)$booking['parking_cost'] + (float)$booking['other_cost'];
$allowanceUsed = (float)($booking['allowance'] ?? 0);
$booking['return_date'] = $booking['return_date'] ?: $booking['date'];
$booking['status_label'] = status_label($booking['status']);
$booking['date_label'] = tanggal_id($booking['date']);
$booking['return_date_label'] = tanggal_id($booking['return_date']);
$booking['period_label'] = periode_tanggal($booking['date'], $booking['return_date']);
$booking['start_time'] = substr($booking['start_time'], 0, 5);
$booking['end_time'] = substr($booking['end_time'], 0, 5);
$booking['total_km'] = $totalKm;
$booking['allowance_label'] = rupiah($allowanceUsed);
$booking['trip_expense_label'] = rupiah($tripExpense);
$booking['fuel_label'] = rupiah($booking['fuel_cost']);
$booking['other_total_label'] = rupiah((float)$booking['toll_cost'] + (float)$booking['parking_cost'] + (float)$booking['other_cost']);

echo json_encode(['ok' => true, 'booking' => $booking, 'passengers' => $passengers]);
