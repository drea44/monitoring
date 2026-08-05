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

$id = (int)($input['booking_id'] ?? 0);

if (!$id) {
    echo json_encode(['ok' => false, 'message' => 'ID booking tidak valid']);
    exit;
}

try {
    $stmtDate = db()->prepare('SELECT date, return_date, start_time, end_time, driver_id, car_id, advance_amount, allowance, destination FROM bookings WHERE id = ?');
    $stmtDate->execute([$id]);
    $existingRow = $stmtDate->fetch();

    if (!$existingRow) {
        echo json_encode(['ok' => false, 'message' => 'Booking tidak ditemukan']);
        exit;
    }

    $startDate  = $existingRow['date'];
    $returnDate = $existingRow['return_date'] ?: $startDate;
    $startTime  = $existingRow['start_time'];
    $endTime    = $existingRow['end_time'];

    // Driver ID
    if (array_key_exists('driver_id', $input)) {
        $driverId = !empty($input['driver_id']) ? (int)$input['driver_id'] : null;
    } else {
        $driverId = $existingRow['driver_id'];
    }

    // Check driver overlap if changing/assigning driver
    if ($driverId && (int)$driverId !== (int)$existingRow['driver_id']) {
        $stmtDriver = db()->prepare("SELECT 1 FROM bookings
            WHERE driver_id = ?
              AND id <> ?
              AND status IN ('pending','approved','running')
              AND TIMESTAMP(date, start_time) < TIMESTAMP(?, ?)
              AND TIMESTAMP(COALESCE(return_date, date), end_time) > TIMESTAMP(?, ?)");
        $stmtDriver->execute([(int)$driverId, $id, $returnDate, $endTime, $startDate, $startTime]);
        if ($stmtDriver->fetchColumn()) {
            echo json_encode(['ok' => false, 'message' => 'Driver yang dipilih sudah memiliki tugas lain pada jam/tanggal tersebut']);
            exit;
        }
    }

    // Destination
    if (array_key_exists('destination', $input)) {
        $destination = trim($input['destination'] ?? '');
        $destination = $destination !== '' ? $destination : $existingRow['destination'];
    } else {
        $destination = $existingRow['destination'];
    }

    // Allowance & Advance Amount
    if (array_key_exists('allowance', $input)) {
        $allowance = (float)$input['allowance'];
    } else {
        $allowance = (float)$existingRow['allowance'];
    }

    if (array_key_exists('advance_amount', $input)) {
        $advanceAmt = (float)$input['advance_amount'];
    } else {
        $advanceAmt = (float)$existingRow['advance_amount'];
    }

    // Durasi Hari
    if (array_key_exists('durasi', $input) && !empty($input['durasi'])) {
        $durasiHari = max(1, (int)$input['durasi']);
        $dateStart  = new DateTime($startDate);
        $newReturn  = clone $dateStart;
        $newReturn->modify('+' . ($durasiHari - 1) . ' days');
        $returnDate = $newReturn->format('Y-m-d');
    }

    $stmt = db()->prepare('UPDATE bookings SET driver_id=?, destination=?, return_date=?, advance_amount=?, allowance=? WHERE id=?');
    $stmt->execute([$driverId, $destination, $returnDate, $advanceAmt, $allowance, $id]);

    // Realisasi Nota
    $updateRealisasi = array_key_exists('realisasi', $input) || array_key_exists('nota_total', $input);
    if ($updateRealisasi) {
        $realisasiAmount = (float)($input['realisasi'] ?? $input['nota_total'] ?? 0);
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
