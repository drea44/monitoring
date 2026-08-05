<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect('dashboard.php');

$id = (int)($_POST['booking_id'] ?? 0);
$status = $_POST['status'] ?? 'pending';
$allowed = ['pending','approved','running','completed','rejected'];
if (!in_array($status, $allowed, true)) $status = 'pending';
$allowance = (float)preg_replace('/\D/', '', (string)($_POST['allowance'] ?? 0));
$advanceAmount = array_key_exists('advance_amount', $_POST) ? max(0, (float)preg_replace('/\D/', '', (string)$_POST['advance_amount'])) : null;
$kmStartRaw = trim((string)($_POST['km_start'] ?? ''));
$kmEndRaw = trim((string)($_POST['km_end'] ?? ''));
$note = trim($_POST['admin_note'] ?? '');

function parse_km_value(string $value, string $label, int $bookingId): ?int
{
    if ($value === '') return null;
    if (!preg_match('/^\d{1,15}$/', $value)) {
        flash('danger', $label . ' harus berupa angka positif maksimal 15 digit.');
        redirect('booking_detail.php?id=' . $bookingId);
    }
    return (int)$value;
}

$kmStart = parse_km_value($kmStartRaw, 'KM Berangkat', $id);
$kmEnd = parse_km_value($kmEndRaw, 'KM Kembali', $id);

$carIdInput = array_key_exists('car_id', $_POST) ? (!empty($_POST['car_id']) ? (int)$_POST['car_id'] : null) : null;
$driverIdInput = array_key_exists('driver_id', $_POST) ? (!empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null) : null;

$existingBookingStmt = db()->prepare('SELECT date, return_date, start_time, end_time, car_id, driver_id, km_start, km_end FROM bookings WHERE id=?');
$existingBookingStmt->execute([$id]);
$existingBooking = $existingBookingStmt->fetch();

$carId = $existingBooking ? $existingBooking['car_id'] : null;
$driverId = $existingBooking ? $existingBooking['driver_id'] : null;

if ($existingBooking) {
    if ($kmStartRaw === '' && $existingBooking['km_start'] !== null) {
        $kmStart = (int)$existingBooking['km_start'];
    }
    if ($kmEndRaw === '' && $existingBooking['km_end'] !== null) {
        $kmEnd = (int)$existingBooking['km_end'];
    }
    if (array_key_exists('car_id', $_POST)) {
        $carId = $carIdInput;
    }
    if (array_key_exists('driver_id', $_POST)) {
        $driverId = $driverIdInput;
    }
}

if ($allowance < 0) {
    flash('danger', 'Uang jalan / uang saku terpakai tidak boleh minus.');
    redirect('booking_detail.php?id=' . $id);
}

if ($kmStart !== null && $kmEnd !== null && $kmEnd < $kmStart) {
    flash('danger', 'KM kembali tidak boleh lebih kecil dari KM berangkat.');
    redirect('booking_detail.php?id=' . $id);
}

try {
    if ($advanceAmount !== null) {
        $stmt = db()->prepare('UPDATE bookings SET status=?, car_id=?, driver_id=?, allowance=?, advance_amount=?, km_start=?, km_end=?, admin_note=? WHERE id=?');
        $stmt->execute([$status, $carId, $driverId, $allowance, $advanceAmount, $kmStart, $kmEnd, $note, $id]);
    } else {
        $stmt = db()->prepare('UPDATE bookings SET status=?, car_id=?, driver_id=?, allowance=?, km_start=?, km_end=?, admin_note=? WHERE id=?');
        $stmt->execute([$status, $carId, $driverId, $allowance, $kmStart, $kmEnd, $note, $id]);
    }

    if ($status === 'completed' && $kmEnd !== null && $carId > 0) {
        db()->prepare('UPDATE cars SET last_km=? WHERE id=?')->execute([(int)$kmEnd, (int)$carId]);
    }

    sync_all_cars_and_drivers_status();

    flash('success', 'Detail booking berhasil diperbarui. Status mobil & driver berhasil disinkronkan.');
} catch (Throwable $e) {
    flash('danger', 'Gagal menyimpan perubahan: ' . $e->getMessage());
}

redirect('booking_detail.php?id=' . $id);
