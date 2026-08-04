<?php
require_once __DIR__ . '/../includes/auth.php';
require_admin();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'message' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$updateDriver = array_key_exists('driver_id', $input);
$driverId    = $updateDriver ? ((!empty($input['driver_id']) ? (int)$input['driver_id'] : null)) : null;
$destination = trim($input['destination'] ?? '');
$durasiHari  = array_key_exists('durasi', $input) ? max(1, (int)$input['durasi']) : null;

$updateAdvance = array_key_exists('advance_amount', $input);
$advanceAmt  = $updateAdvance ? (float)$input['advance_amount'] : null;

$updateAllowance = array_key_exists('allowance', $input);
$allowance   = $updateAllowance ? (float)$input['allowance'] : null;

$updateRealisasi = array_key_exists('realisasi', $input) || array_key_exists('nota_total', $input);
$realisasiAmount = (float)($input['realisasi'] ?? $input['nota_total'] ?? 0);

$id = (int)($input['booking_id'] ?? 0);

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'ID booking tidak valid']);
    exit;
}

try {
    $stmtDate = db()->prepare('SELECT date, driver_id, advance_amount, allowance FROM bookings WHERE id = ?');
    $stmtDate->execute([$id]);
    $existingRow = $stmtDate->fetch();

    if (!$existingRow) {
        echo json_encode(['ok' => false, 'message' => 'Booking tidak ditemukan']);
        exit;
    }

    $startDate  = $existingRow['date'];
    if (!$updateDriver) {
        $driverId = $existingRow['driver_id'];
    }
    if (!$updateAllowance) {
        $allowance = (float)$existingRow['allowance'];
    }
    if (!$updateAdvance) {
        $advanceAmt = (float)$existingRow['advance_amount'];
    }

    if ($durasiHari !== null) {
        $dateStart  = new DateTime($startDate);
        $newReturn  = clone $dateStart;
        $newReturn->modify('+' . ($durasiHari - 1) . ' days');
        $returnDate = $newReturn->format('Y-m-d');
        $stmt = db()->prepare('UPDATE bookings SET driver_id=?, destination=?, return_date=?, advance_amount=?, allowance=? WHERE id=?');
        $stmt->execute([$driverId, $destination ?: null, $returnDate, $advanceAmt, $allowance, $id]);
    } else {
        $stmt = db()->prepare('UPDATE bookings SET driver_id=?, destination=?, advance_amount=?, allowance=? WHERE id=?');
        $stmt->execute([$driverId, $destination ?: null, $advanceAmt, $allowance, $id]);
    }

    if ($updateRealisasi) {
        $stmtExp = db()->prepare('SELECT * FROM expenses WHERE booking_id = ? ORDER BY id');
        $stmtExp->execute([$id]);
        $expenses = $stmtExp->fetchAll();

        if (empty($expenses)) {
            if ($realisasiAmount > 0) {
                $stmtIns = db()->prepare("INSERT INTO expenses (booking_id, category, amount, expense_date, note) VALUES (?, 'Lainnya', ?, CURRENT_DATE, 'Input dari Kalender')");
                $stmtIns->execute([$id, $realisasiAmount]);
            }
        } else {
            $currentTotal = array_sum(array_map(fn($x) => (float)$x['amount'], $expenses));
            if (abs($currentTotal - $realisasiAmount) > 0.001) {
                if (count($expenses) === 1) {
                    $stmtUpd = db()->prepare('UPDATE expenses SET amount = ? WHERE id = ?');
                    $stmtUpd->execute([$realisasiAmount, $expenses[0]['id']]);
                } else {
                    $diff = $realisasiAmount - $currentTotal;
                    $firstExp = $expenses[0];
                    $newFirst = max(0, (float)$firstExp['amount'] + $diff);
                    $stmtUpd = db()->prepare('UPDATE expenses SET amount = ? WHERE id = ?');
                    $stmtUpd->execute([$newFirst, $firstExp['id']]);
                }
            }
        }
    }

    sync_all_cars_and_drivers_status();

    echo json_encode(['ok' => true, 'message' => 'Booking berhasil diperbarui']);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'message' => 'Gagal menyimpan: ' . $e->getMessage()]);
}
