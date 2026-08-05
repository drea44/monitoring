<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$params = [$id];
$sql = "SELECT b.*, u.name requester, u.department, c.name car_name, c.plate_number, c.capacity, d.name driver_name, d.phone driver_phone
        FROM bookings b
        JOIN users u ON u.id=b.user_id
        LEFT JOIN cars c ON c.id=b.car_id
        LEFT JOIN drivers d ON d.id=b.driver_id
        WHERE b.id = ?";
if (!is_admin()) {
    $sql .= ' AND b.user_id = ?';
    $params[] = current_user()['id'];
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$booking = $stmt->fetch();
if (!$booking) {
    flash('danger', 'Booking tidak ditemukan.');
    redirect('dashboard.php');
}

$stmt = db()->prepare('SELECT name FROM passengers WHERE booking_id = ? ORDER BY id');
$stmt->execute([$id]);
$passengers = $stmt->fetchAll();
$stmt = db()->prepare('SELECT * FROM expenses WHERE booking_id = ? ORDER BY created_at DESC');
$stmt->execute([$id]);
$expenses = $stmt->fetchAll();

$title = 'Detail Booking - Monitoring Mobil Kantor';
$page_title = 'Detail Perjalanan ' . $booking['code'];
$page_subtitle = 'Detail booking, mobil, driver, penumpang, kilometer, uang jalan driver, dan nota.';
$booking['return_date'] = $booking['return_date'] ?: $booking['date'];
$totalKm = ($booking['km_start'] !== null && $booking['km_end'] !== null) ? (int)$booking['km_end'] - (int)$booking['km_start'] : null;
$notaTotal    = array_sum(array_map(fn($x) => (float)$x['amount'], $expenses));
$fuelCost     = array_sum(array_map(fn($x) => $x['category'] === 'BBM' ? (float)$x['amount'] : 0, $expenses));
$tollCost     = array_sum(array_map(fn($x) => $x['category'] === 'Tol' ? (float)$x['amount'] : 0, $expenses));
$parkingCost  = array_sum(array_map(fn($x) => $x['category'] === 'Parkir' ? (float)$x['amount'] : 0, $expenses));
$otherCost    = array_sum(array_map(fn($x) => !in_array($x['category'], ['BBM','Tol','Parkir']) ? (float)$x['amount'] : 0, $expenses));
$allowanceUsed = (float)($booking['allowance'] ?? 0);

$terpakai      = $notaTotal;
$sisaUang      = max($allowanceUsed - $notaTotal, 0);
$kekuranganUang = max($notaTotal - $allowanceUsed, 0);

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<?php
$statusCardClass = match($booking['status']) {
    'approved', 'running' => 'card-blue',
    'completed' => 'card-green',
    'rejected'  => 'card-red',
    default     => 'card-orange'
};
?>
<section class="grid grid-4">
    <div class="card stat <?= $statusCardClass ?>">
        <div>
            <span>Status Perjalanan</span>
            <strong><span class="badge" style="background:rgba(255,255,255,0.22);color:#fff;border:1px solid rgba(255,255,255,0.35);padding:4px 10px;border-radius:8px"><?= e(status_label($booking['status'])) ?></span></strong>
        </div>
        <div class="icon">📌</div>
    </div>
    <div class="card stat card-blue">
        <div>
            <span>Uang Jalan Diberikan</span>
            <strong style="font-size:18px"><?= e(rupiah($allowanceUsed)) ?></strong>
            <small>budget driver</small>
        </div>
        <div class="icon">💵</div>
    </div>
    <div class="card stat card-purple">
        <div>
            <span>Total Nota</span>
            <strong style="font-size:18px"><?= e(rupiah($notaTotal)) ?></strong>
            <small>pengeluaran riil</small>
        </div>
        <div class="icon">🧾</div>
    </div>
    <div class="card stat card-green">
        <div>
            <span>Jumlah Nota</span>
            <strong style="font-size:18px"><?= e(count($expenses)) ?></strong>
            <small>terlampir</small>
        </div>
        <div class="icon">📎</div>
    </div>
</section>

<section class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h2>Informasi Booking</h2>
        <div class="detail-grid">
            <div class="detail-item"><span>Tanggal Berangkat</span><strong><?= e(tanggal_id($booking['date'])) ?></strong></div>
            <div class="detail-item"><span>Tanggal Pulang</span><strong><?= e(tanggal_id($booking['return_date'])) ?></strong></div>
            <div class="detail-item"><span>Jam</span><strong><?= e(periode_jam($booking['start_time'], $booking['end_time'])) ?></strong></div>
            <div class="detail-item"><span>Pemesan</span><strong><?= e($booking['requester']) ?></strong></div>
            <div class="detail-item"><span>Departemen</span><strong><?= e($booking['department'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>Tujuan</span><strong><?= e($booking['destination']) ?></strong></div>
            <div class="detail-item"><span>Jumlah Penumpang</span><strong><?= e($booking['passenger_count']) ?></strong></div>
            <div class="detail-item full"><span>Keperluan</span><strong><?= e($booking['purpose'] ?? '-') ?></strong></div>
        </div>
        <h3 style="margin-top:16px">Daftar Penumpang</h3>
        <p><?= $passengers ? e(implode(', ', array_column($passengers, 'name'))) : '-' ?></p>
    </div>
    <div class="card">
        <h2>Mobil & Driver</h2>
        <div class="detail-grid">
            <div class="detail-item"><span>Mobil</span><strong><?= e($booking['car_name'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>Plat Nomor</span><strong><?= e($booking['plate_number'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>Kapasitas</span><strong><?= e($booking['capacity'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>Driver</span><strong><?= e($booking['driver_name'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>No. HP Driver</span><strong><?= e($booking['driver_phone'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>KM Berangkat</span><strong><?= e($booking['km_start'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>KM Kembali</span><strong><?= e($booking['km_end'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>Total Jarak</span><strong><?= $totalKm === null ? '-' : e($totalKm . ' KM') ?></strong></div>
        </div>
    </div>
</section>

<section class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h2>Rincian Uang Jalan Driver</h2>
        <div class="kpi-line"><span>Uang Jalan / Uang Saku Driver Perjalanan Ini</span><strong><?= e(rupiah($allowanceUsed)) ?></strong></div>
        <div class="kpi-line"><span>Terpakai</span><strong><?= e(rupiah($terpakai)) ?></strong></div>
        <div class="kpi-line"><span>Sisa</span><strong><?= e(rupiah($sisaUang)) ?></strong></div>
        <?php if ($kekuranganUang > 0): ?>
            <div class="alert alert-danger" style="margin-top:14px"><strong>Kekurangan uang: <?= e(rupiah($kekuranganUang)) ?></strong><br>Total nota lebih besar dari uang jalan yang diberikan admin.</div>
        <?php endif; ?>
        <p class="text-muted">Terpakai dihitung dari total nota yang tercatat untuk perjalanan ini. Sisa dihitung dari uang jalan dikurangi total nota.</p>
    </div>

    <div class="card">
        <h2>Timeline Status</h2>
        <div class="timeline">
            <div class="timeline-item"><strong>Booking diajukan</strong><br><span class="text-muted"><?= e($booking['created_at']) ?></span></div>
            <?php if (in_array($booking['status'], ['approved','running','completed'])): ?><div class="timeline-item"><strong>Booking disetujui</strong><br><span class="text-muted">Mobil dan driver siap digunakan</span></div><?php endif; ?>
            <?php if (in_array($booking['status'], ['running','completed'])): ?><div class="timeline-item"><strong>Driver berangkat</strong><br><span class="text-muted">KM awal: <?= e($booking['km_start'] ?? '-') ?></span></div><?php endif; ?>
            <?php if ($booking['status'] === 'completed'): ?><div class="timeline-item"><strong>Perjalanan selesai</strong><br><span class="text-muted">KM akhir: <?= e($booking['km_end'] ?? '-') ?></span></div><?php endif; ?>
            <?php if ($expenses): ?><div class="timeline-item"><strong>Nota diupload</strong><br><span class="text-muted"><?= count($expenses) ?> nota tercatat</span></div><?php endif; ?>
            <?php if ($booking['status'] === 'rejected'): ?><div class="timeline-item"><strong>Booking ditolak</strong><br><span class="text-muted"><?= e($booking['admin_note'] ?? '-') ?></span></div><?php endif; ?>
        </div>
    </div>
</section>

<section class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h2>Aksi</h2>
        <div class="actions">
            <a class="btn btn-outline" href="<?= e(base_path('calendar.php')) ?>">Kembali ke Kalender</a>
            <a class="btn btn-primary" href="<?= e(base_path('expense_upload.php?booking_id=' . $booking['id'])) ?>">Upload Nota</a>
        </div>
        <?php if (is_admin()): ?>
            <?php
            $allCars = db()->query("SELECT id, name, plate_number FROM cars WHERE status != 'inactive' ORDER BY name")->fetchAll();
            $allDrivers = db()->query("SELECT id, name FROM drivers WHERE status != 'inactive' ORDER BY name")->fetchAll();
            ?>
            <form method="post" action="<?= e(base_path('actions_booking.php')) ?>" class="grid" style="margin-top:16px">
                <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Status</label>
                        <select name="status">
                            <?php foreach (['pending','approved','running','completed','rejected'] as $s): ?>
                                <option value="<?= e($s) ?>" <?= $booking['status']===$s?'selected':'' ?>><?= e(status_label($s)) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Mobil Ditugaskan</label>
                        <select name="car_id">
                            <option value="">-- Pilih Mobil --</option>
                            <?php foreach ($allCars as $ac): ?>
                                <option value="<?= (int)$ac['id'] ?>" <?= (int)$booking['car_id'] === (int)$ac['id'] ? 'selected' : '' ?>>
                                    <?= e($ac['name']) ?> (<?= e($ac['plate_number']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Driver Ditugaskan</label>
                        <select name="driver_id">
                            <option value="">-- Pilih Driver --</option>
                            <?php foreach ($allDrivers as $ad): ?>
                                <option value="<?= (int)$ad['id'] ?>" <?= (int)$booking['driver_id'] === (int)$ad['id'] ? 'selected' : '' ?>>
                                    <?= e($ad['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group"><label>Uang Muka (UMK)</label><input class="input" type="number" min="0" step="any" name="advance_amount" value="<?= e($booking['advance_amount']) ?>"></div>
                    <div class="form-group"><label>Uang Jalan / Uang Saku Driver</label><input class="input" type="number" min="0" step="any" name="allowance" value="<?= e($allowanceUsed) ?>"></div>
                    <div class="form-group"><label>KM Berangkat</label><input class="input" type="number" min="0" max="999999999999999" step="1" name="km_start" value="<?= e($booking['km_start']) ?>"></div>
                    <div class="form-group"><label>KM Kembali</label><input class="input" type="number" min="0" max="999999999999999" step="1" name="km_end" value="<?= e($booking['km_end']) ?>"></div>
                    <div class="form-group full"><label>Catatan Admin / Alasan Penolakan</label><textarea name="admin_note"><?= e($booking['admin_note']) ?></textarea></div>
                </div>
                <p class="text-muted">Uang jalan yang diinput adalah nominal uang yang diberikan admin kepada driver untuk perjalanan ini.</p>
                <button class="btn btn-success" type="submit">Simpan Perubahan</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Ringkasan Nota</h2>
        <div class="kpi-line"><span>Total Nota Upload</span><strong><?= e(rupiah($notaTotal)) ?></strong></div>
        <div class="kpi-line"><span>BBM Tercatat</span><strong><?= e(rupiah($fuelCost)) ?></strong></div>
        <div class="kpi-line"><span>Tol Tercatat</span><strong><?= e(rupiah($tollCost)) ?></strong></div>
        <div class="kpi-line"><span>Parkir Tercatat</span><strong><?= e(rupiah($parkingCost)) ?></strong></div>
        <div class="kpi-line"><span>Lainnya Tercatat</span><strong><?= e(rupiah($otherCost)) ?></strong></div>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="calendar-toolbar">
        <h2>Daftar Nota & Pengeluaran</h2>
        <a class="btn btn-primary" href="<?= e(base_path('expense_upload.php?booking_id=' . $booking['id'])) ?>">+ Upload Nota</a>
    </div>
    <table class="table">
        <thead><tr><th>Tanggal</th><th>Kategori</th><th>Nominal</th><th>File</th><th>Status</th><th>Catatan</th></tr></thead>
        <tbody>
            <?php foreach ($expenses as $ex): ?>
            <tr>
                <td><?= e(tanggal_id($ex['expense_date'])) ?></td>
                <td><?= e($ex['category']) ?></td>
                <td><?= e(rupiah($ex['amount'])) ?></td>
                <td><?= $ex['receipt_file'] ? '<a class="btn btn-outline" target="_blank" href="'.e(base_path('uploads/nota/'.$ex['receipt_file'])).'">Preview</a>' : '-' ?></td>
                <td><span class="badge <?= $ex['verified'] ? 'badge-success' : 'badge-warning' ?>"><?= $ex['verified'] ? 'Terverifikasi' : 'Belum Verifikasi' ?></span></td>
                <td><?= e($ex['note']) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$expenses): ?><tr><td colspan="6">Belum ada nota.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
