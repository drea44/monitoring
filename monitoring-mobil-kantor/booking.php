<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$title = 'Booking Mobil - Monitoring Mobil Kantor';
$page_title = 'Booking Mobil';
$page_subtitle = 'Pilih tanggal berangkat dan tanggal pulang, cek mobil serta driver yang ready, lalu ajukan booking.';

$form = [
    'date' => $_POST['date'] ?? '',
    'return_date' => $_POST['return_date'] ?? ($_POST['date'] ?? ''),
    'start_time' => $_POST['start_time'] ?? '',
    'end_time' => $_POST['end_time'] ?? '',
    'destination' => $_POST['destination'] ?? '',
    'purpose' => $_POST['purpose'] ?? '',
    'passenger_count' => $_POST['passenger_count'] ?? '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'] ?? '';
    $returnDate = $_POST['return_date'] ?? $date;
    $start = $_POST['start_time'] ?? '';
    $end = $_POST['end_time'] ?? '';
    $destination = trim($_POST['destination'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $passengerCount = max(1, (int)($_POST['passenger_count'] ?? 1));
    $carId = !empty($_POST['car_id']) ? (int)$_POST['car_id'] : null;
    $driverId = !empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;
    $passengers = array_filter(array_map('trim', $_POST['passengers'] ?? []));

    $startDT = null;
    $endDT = null;
    if ($date && $returnDate && $start && $end) {
        $startDT = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $start);
        $endDT = DateTime::createFromFormat('Y-m-d H:i', $returnDate . ' ' . $end);
    }

    if (!$date || !$returnDate || !$start || !$end || !$destination) {
        flash('danger', 'Tanggal berangkat, tanggal pulang, jam, dan tujuan wajib diisi.');
    } elseif (!$startDT || !$endDT) {
        flash('danger', 'Format tanggal atau jam tidak valid.');
    } elseif ($startDT >= $endDT) {
        flash('danger', 'Tanggal/jam pulang harus lebih besar dari tanggal/jam berangkat.');
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO bookings (code, user_id, car_id, driver_id, date, return_date, start_time, end_time, destination, purpose, passenger_count, status)
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([booking_code(), current_user()['id'], $carId, $driverId, $date, $returnDate, $start, $end, $destination, $purpose, $passengerCount]);
            $bookingId = (int)$pdo->lastInsertId();
            $passengerStmt = $pdo->prepare('INSERT INTO passengers (booking_id, name) VALUES (?, ?)');
            foreach ($passengers as $name) {
                $passengerStmt->execute([$bookingId, $name]);
            }
            $pdo->commit();
            flash('success', 'Booking berhasil diajukan dan menunggu persetujuan admin.');
            redirect('booking_detail.php?id=' . $bookingId);
        } catch (Exception $e) {
            $pdo->rollBack();
            flash('danger', 'Gagal menyimpan booking: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<form method="post" class="card" id="bookingForm">
    <div class="form-grid">
        <div class="form-group">
            <label>Tanggal Berangkat</label>
            <input class="input" type="date" name="date" value="<?= e($form['date']) ?>" required>
        </div>
        <div class="form-group">
            <label>Tanggal Pulang</label>
            <input class="input" type="date" name="return_date" value="<?= e($form['return_date']) ?>" required>
        </div>
        <div class="form-group">
            <label>Jam Berangkat</label>
            <input class="input" type="time" name="start_time" value="<?= e($form['start_time']) ?>" required>
        </div>
        <div class="form-group">
            <label>Jam Kembali</label>
            <input class="input" type="time" name="end_time" value="<?= e($form['end_time']) ?>" required>
        </div>
        <div class="form-group">
            <label>Jumlah Penumpang</label>
            <input class="input" type="number" name="passenger_count" min="1" value="<?= e($form['passenger_count']) ?>" required>
        </div>
        <div class="form-group full">
            <label>Tujuan</label>
            <input class="input" type="text" name="destination" value="<?= e($form['destination']) ?>" placeholder="Contoh: Bandung" required>
        </div>
        <div class="form-group full">
            <label>Keperluan Perjalanan</label>
            <textarea name="purpose" placeholder="Contoh: Meeting client, audit cabang, kunjungan site"><?= e($form['purpose']) ?></textarea>
        </div>
        <div class="form-group full">
            <label>Nama Penumpang</label>
            <div id="passengerList" class="grid">
                <?php $oldPassengers = $_POST['passengers'] ?? ['']; ?>
                <?php foreach ($oldPassengers as $passenger): ?>
                    <input class="input" name="passengers[]" value="<?= e($passenger) ?>" placeholder="Nama penumpang">
                <?php endforeach; ?>
            </div>
            <button class="btn btn-outline" type="button" onclick="addPassenger()">+ Tambah Penumpang</button>
        </div>
    </div>

    <div class="actions" style="margin-top:18px">
        <button class="btn btn-primary" type="button" onclick="checkAvailability()">Cek Ketersediaan</button>
        <button class="btn btn-success" type="submit">Ajukan Booking</button>
        <button class="btn" type="reset">Reset Form</button>
    </div>

    <div id="availabilityResult" style="margin-top:18px"></div>
</form>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
