<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = (int)($_GET['id'] ?? 0);
$params = [$id];
$sql = "SELECT b.*, u.name requester, COALESCE(NULLIF(b.department, ''), u.department) AS department, c.name car_name, c.plate_number, c.capacity, d.name driver_name, d.phone driver_phone
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
$page_subtitle = 'Detail booking, mobil, driver, penumpang, kilometer, uang jalan driver, dan realisasi.';
$booking['return_date'] = $booking['return_date'] ?: $booking['date'];
$totalKm = ($booking['km_start'] !== null && $booking['km_end'] !== null) ? (int)$booking['km_end'] - (int)$booking['km_start'] : null;
$notaTotal    = array_sum(array_map(fn($x) => (float)$x['amount'], $expenses));
$fuelCost     = array_sum(array_map(fn($x) => $x['category'] === 'BBM' ? (float)$x['amount'] : 0, $expenses));
$tollCost     = array_sum(array_map(fn($x) => $x['category'] === 'Tol' ? (float)$x['amount'] : 0, $expenses));
$parkingCost  = array_sum(array_map(fn($x) => $x['category'] === 'Parkir' ? (float)$x['amount'] : 0, $expenses));
$otherCost    = array_sum(array_map(fn($x) => !in_array($x['category'], ['BBM','Tol','Parkir']) ? (float)$x['amount'] : 0, $expenses));
$allowanceUsed = (float)($booking['allowance'] ?? 0);

$terpakai      = round($notaTotal);
$sisaUang      = max(round($allowanceUsed - $notaTotal), 0);
$kekuranganUang = max(round($notaTotal - $allowanceUsed), 0);

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<?php
$statusCardClass = match($booking['status']) {
    'approved', 'running' => 'card-blue',
    'completed'           => 'card-green',
    'rejected'            => 'card-red',
    default               => 'card-orange',
};
?>
<div class="calendar-toolbar" style="margin-bottom:16px;background:var(--surface,#fff);padding:14px 20px;border-radius:12px;border:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:12px">
        <button type="button" class="btn-back-icon" onclick="if(window.history.length > 1 && document.referrer){ history.back(); } else { location.href='<?= e(base_path('calendar.php')) ?>'; }" title="Kembali">‹</button>
        <span style="color:var(--border)">|</span>
        <span style="font-weight:700;color:var(--text);font-size:14px">Detail Booking Perjalanan <?= e($booking['code']) ?></span>
    </div>
    <div class="actions" style="gap:8px">
        <a class="btn btn-outline btn-sm" href="<?= e(base_path('calendar.php')) ?>">📅 Kalender</a>
        <a class="btn btn-outline btn-sm" href="<?= e(base_path('history.php')) ?>">📌 Riwayat</a>
    </div>
</div>

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
            <span>Total Realisasi</span>
            <strong style="font-size:18px"><?= e(rupiah($notaTotal)) ?></strong>
            <small>pengeluaran riil</small>
        </div>
        <div class="icon">🧾</div>
    </div>
    <div class="card stat card-green">
        <div>
            <span>Jumlah Realisasi</span>
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
            <div class="detail-item"><span>Bidang (Departemen)</span><strong style="color:var(--blue,#2563ff)"><?= e($booking['department'] ?? '-') ?></strong></div>
            <div class="detail-item"><span>Tujuan</span><strong><?= e($booking['destination']) ?></strong></div>
            <div class="detail-item"><span>Jumlah Penumpang</span><strong><?= e($booking['passenger_count']) ?></strong></div>
            <div class="detail-item full"><span>Keperluan</span><strong><?= e($booking['purpose'] ?? '-') ?></strong></div>
        </div>
        <h3 style="margin-top:16px">Daftar Penumpang</h3>
        <p><?= $passengers ? implode(', ', array_map('e', array_column($passengers, 'name'))) : '-' ?></p>
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
            <div class="alert alert-danger" style="margin-top:14px"><strong>Kekurangan uang: <?= e(rupiah($kekuranganUang)) ?></strong><br>Total realisasi lebih besar dari uang jalan yang diberikan admin.</div>
        <?php endif; ?>
        <p class="text-muted">Terpakai dihitung dari total realisasi yang tercatat untuk perjalanan ini. Sisa dihitung dari uang jalan dikurangi total realisasi.</p>
    </div>

    <div class="card">
        <h2>Timeline Status</h2>
        <div class="timeline">
            <div class="timeline-item"><strong>Booking diajukan</strong><br><span class="text-muted"><?= e($booking['created_at']) ?></span></div>
            <?php if (in_array($booking['status'], ['approved','running','completed'])): ?><div class="timeline-item"><strong>Booking disetujui</strong><br><span class="text-muted">Mobil dan driver siap digunakan</span></div><?php endif; ?>
            <?php if (in_array($booking['status'], ['running','completed'])): ?><div class="timeline-item"><strong>Driver berangkat</strong><br><span class="text-muted">KM awal: <?= e($booking['km_start'] ?? '-') ?></span></div><?php endif; ?>
            <?php if ($booking['status'] === 'completed'): ?><div class="timeline-item"><strong>Perjalanan selesai</strong><br><span class="text-muted">KM akhir: <?= e($booking['km_end'] ?? '-') ?></span></div><?php endif; ?>
            <?php if ($expenses): ?><div class="timeline-item"><strong>Realisasi diupload</strong><br><span class="text-muted"><?= count($expenses) ?> realisasi tercatat</span></div><?php endif; ?>
            <?php if ($booking['status'] === 'rejected'): ?><div class="timeline-item"><strong>Booking ditolak</strong><br><span class="text-muted"><?= e($booking['admin_note'] ?? '-') ?></span></div><?php endif; ?>
        </div>
    </div>
</section>

<section class="grid grid-2" style="margin-top:18px">
    <div class="card">
        <h2>Aksi</h2>
        <div class="actions">
            <a class="btn btn-outline" href="<?= e(base_path('calendar.php')) ?>">Kembali ke Kalender</a>
        </div>
        <?php if (is_admin()): ?>
            <?php
            $allCars = db()->query("SELECT id, name, plate_number FROM cars WHERE status != 'inactive' ORDER BY name")->fetchAll();
            $allDrivers = db()->query("SELECT id, name FROM drivers WHERE status != 'inactive' ORDER BY name")->fetchAll();
            $currentSt = $booking['status'];
            $isNonPending = in_array($currentSt, ['approved', 'running', 'completed', 'rejected'], true);
            // Hanya rejected yang sepenuhnya dikunci (semua field readonly)
            $isFullyLocked = ($currentSt === 'rejected');
            // KM dikunci hanya saat rejected
            $isKmLocked = ($currentSt === 'rejected');
            // Apakah KM Berangkat wajib diisi?
            $kmStartRequired = in_array($currentSt, ['running', 'completed'], true);
            // Apakah KM Kembali wajib diisi?
            $kmEndRequired = ($currentSt === 'completed');

            // Allowed next statuses according to workflow:
            // pending  -> [pending, approved, rejected]
            // approved -> [approved, running]
            // running  -> [running, completed]
            // completed -> locked
            // rejected  -> locked
            $allowedNext = match ($currentSt) {
                'pending'  => ['pending', 'approved', 'rejected'],
                'approved' => ['approved', 'running'],
                'running'  => ['running', 'completed'],
                default    => [$currentSt]
            };
            ?>
            <form method="post" action="<?= e(base_path('actions_booking.php')) ?>" class="grid" style="margin-top:16px">
                <?= csrf_field() ?>
                <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
                <div class="form-grid">
                    <div class="form-group"><label>Status</label>
                        <?php if ($isFullyLocked): ?>
                            <input class="input" type="text" value="<?= e(status_label($currentSt)) ?>" readonly style="background:var(--surface);cursor:default;color:var(--text);font-weight:600">
                        <?php else: ?>
                            <select name="status">
                                <?php foreach ($allowedNext as $s): ?>
                                    <option value="<?= e($s) ?>" <?= $currentSt===$s?'selected':'' ?>><?= e(status_label($s)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group"><label>Mobil Ditugaskan</label>
                        <?php if ($isNonPending): ?>
                            <?php
                            $carLabel = $booking['car_name'] ? $booking['car_name'] . ($booking['plate_number'] ? ' (' . $booking['plate_number'] . ')' : '') : 'Belum Ditugaskan';
                            ?>
                            <input class="input" type="text" value="<?= e($carLabel) ?>" readonly style="background:var(--surface);cursor:default;color:var(--text);font-weight:600">
                        <?php else: ?>
                            <select name="car_id">
                                <option value="">-- Pilih Mobil --</option>
                                <?php foreach ($allCars as $ac): ?>
                                    <option value="<?= (int)$ac['id'] ?>" <?= (int)$booking['car_id'] === (int)$ac['id'] ? 'selected' : '' ?>>
                                        <?= e($ac['name']) ?> (<?= e($ac['plate_number']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group"><label>Driver Ditugaskan</label>
                        <?php if ($isNonPending): ?>
                            <input class="input" type="text" value="<?= e($booking['driver_name'] ?: 'Belum Ditugaskan') ?>" readonly style="background:var(--surface);cursor:default;color:var(--text);font-weight:600">
                        <?php else: ?>
                            <select name="driver_id">
                                <option value="">-- Pilih Driver --</option>
                                <?php foreach ($allDrivers as $ad): ?>
                                    <option value="<?= (int)$ad['id'] ?>" <?= (int)$booking['driver_id'] === (int)$ad['id'] ? 'selected' : '' ?>>
                                        <?= e($ad['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-group"><label>Uang Muka (UMK)</label><input class="input" type="number" min="0" step="1" name="advance_amount" value="<?= (int)$booking['advance_amount'] ?>" <?= $isFullyLocked ? 'readonly style="background:var(--surface);cursor:default"' : '' ?>></div>
                    <div class="form-group"><label>Uang Jalan / Uang Saku Driver</label><input class="input" type="number" min="0" step="any" name="allowance" value="<?= e($allowanceUsed) ?>" <?= $isFullyLocked ? 'readonly style="background:var(--surface);cursor:default"' : '' ?>></div>
                    <div class="form-group">
                        <label>KM Berangkat <?= $kmStartRequired ? '<span style="color:var(--red)">*</span>' : '' ?></label>
                        <input class="input" id="km_start_input" type="number" min="0" max="999999999999999" step="1" name="km_start"
                            value="<?= e($booking['km_start']) ?>"
                            <?= $isKmLocked ? 'readonly style="background:var(--surface);cursor:default;color:var(--muted)"' : '' ?>
                            <?= $kmStartRequired ? 'required' : '' ?>
                            placeholder="Masukkan odometer berangkat">
                        <div id="km_start_error" style="color:var(--red,#dc2626);font-size:12px;margin-top:4px;display:none"></div>
                        <small style="color:var(--muted);font-size:11.5px;margin-top:4px;display:block">
                            <?php if ($isKmLocked): ?>
                                🔒 Dikunci — status booking ditolak
                            <?php elseif ($kmStartRequired): ?>
                                <span style="color:var(--red,#dc2626);font-weight:600">★ Wajib diisi</span> — status saat ini: <strong><?= e(status_label($currentSt)) ?></strong>
                            <?php else: ?>
                                Opsional — boleh diisi sekarang atau nanti
                            <?php endif; ?>
                        </small>
                    </div>
                    <div class="form-group">
                        <label>KM Kembali <?= $kmEndRequired ? '<span style="color:var(--red)">*</span>' : '' ?></label>
                        <input class="input" id="km_end_input" type="number" min="0" max="999999999999999" step="1" name="km_end"
                            value="<?= e($booking['km_end']) ?>"
                            <?= $isKmLocked ? 'readonly style="background:var(--surface);cursor:default;color:var(--muted)"' : '' ?>
                            <?= $kmEndRequired ? 'required' : '' ?>
                            placeholder="Masukkan odometer kembali">
                        <div id="km_end_error" style="color:var(--red,#dc2626);font-size:12px;margin-top:4px;display:none"></div>
                        <small style="color:var(--muted);font-size:11.5px;margin-top:4px;display:block">
                            <?php if ($isKmLocked): ?>
                                🔒 Dikunci — status booking ditolak
                            <?php elseif ($kmEndRequired): ?>
                                <span style="color:var(--red,#dc2626);font-weight:600">★ Wajib diisi</span> — perjalanan selesai, isi KM akhir
                            <?php elseif ($currentSt === 'running'): ?>
                                Opsional — isi jika perjalanan telah selesai
                            <?php else: ?>
                                Opsional — boleh diisi sekarang atau nanti
                            <?php endif; ?>
                        </small>
                    </div>
                    <?php if ($currentSt === 'rejected'): ?>
                        <div class="form-group full"><label>Catatan Admin / Alasan Penolakan</label><textarea name="admin_note" readonly style="background:var(--surface);cursor:default"><?= e($booking['admin_note']) ?></textarea></div>
                    <?php endif; ?>
                </div>
                <p class="text-muted">Uang jalan yang diinput adalah nominal uang yang diberikan admin kepada driver untuk perjalanan ini.</p>
                <?php if (!$isFullyLocked): ?>
                    <button class="btn btn-success" type="submit" id="btn_simpan_perubahan">Simpan Perubahan</button>
                <?php endif; ?>
            </form>
<script>
(function() {
    var form = document.querySelector('form[action*="actions_booking"]');
    if (!form) return;

    var kmStart = document.getElementById('km_start_input');
    var kmEnd   = document.getElementById('km_end_input');
    var errStart = document.getElementById('km_start_error');
    var errEnd   = document.getElementById('km_end_error');

    function showError(el, msg) {
        el.style.display = msg ? 'block' : 'none';
        el.textContent = msg || '';
    }

    function clearErrors() {
        showError(errStart, '');
        showError(errEnd, '');
    }

    // Cegah input karakter non-angka
    [kmStart, kmEnd].forEach(function(inp) {
        if (!inp || inp.readOnly) return;
        inp.addEventListener('keypress', function(e) {
            if (!/^[0-9]$/.test(e.key) && !['Backspace','Delete','Tab','ArrowLeft','ArrowRight','Enter'].includes(e.key)) {
                e.preventDefault();
            }
        });
        inp.addEventListener('input', function() {
            // Hapus karakter non-digit
            if (this.value !== '' && !/^\d+$/.test(this.value)) {
                this.value = this.value.replace(/\D/g, '');
            }
            // Hapus nilai negatif (min=0 harusnya sudah handle, ini safety net)
            if (this.value !== '' && parseInt(this.value, 10) < 0) {
                this.value = 0;
            }
            clearErrors();
        });
    });

    // Validasi saat blur km_end
    if (kmEnd && !kmEnd.readOnly) {
        kmEnd.addEventListener('blur', function() {
            var start = kmStart && kmStart.value !== '' ? parseInt(kmStart.value, 10) : null;
            var end   = this.value !== '' ? parseInt(this.value, 10) : null;
            if (end !== null && start !== null && end < start) {
                showError(errEnd, 'KM Kembali (' + end + ') tidak boleh lebih kecil dari KM Berangkat (' + start + ').');
            } else {
                showError(errEnd, '');
            }
        });
    }

    // Validasi saat form submit
    form.addEventListener('submit', function(e) {
        clearErrors();
        var valid = true;

        var startVal = kmStart && kmStart.value !== '' ? parseInt(kmStart.value, 10) : null;
        var endVal   = kmEnd   && kmEnd.value   !== '' ? parseInt(kmEnd.value,   10) : null;

        // Validasi negatif
        if (kmStart && !kmStart.readOnly && startVal !== null && startVal < 0) {
            showError(errStart, 'KM Berangkat tidak boleh bernilai negatif.');
            valid = false;
        }
        if (kmEnd && !kmEnd.readOnly && endVal !== null && endVal < 0) {
            showError(errEnd, 'KM Kembali tidak boleh bernilai negatif.');
            valid = false;
        }

        // Validasi km_end >= km_start
        if (startVal !== null && endVal !== null && endVal < startVal) {
            showError(errEnd, 'KM Kembali (' + endVal + ') tidak boleh lebih kecil dari KM Berangkat (' + startVal + ').');
            valid = false;
        }

        // Validasi required (diperkuat JS untuk pesan bahasa Indonesia)
        if (kmStart && kmStart.required && kmStart.value.trim() === '') {
            showError(errStart, 'KM Berangkat wajib diisi untuk status ini.');
            valid = false;
        }
        if (kmEnd && kmEnd.required && kmEnd.value.trim() === '') {
            showError(errEnd, 'KM Kembali wajib diisi untuk status Selesai.');
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            // Scroll ke elemen error pertama
            var firstErr = form.querySelector('#km_start_error[style*="block"], #km_end_error[style*="block"]');
            if (firstErr) firstErr.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    });
})();
</script>
        <?php endif; ?>
    </div>

    <div class="card">
        <h2>Ringkasan Realisasi</h2>
        <div class="kpi-line"><span>BBM</span><strong><?= e(rupiah($fuelCost)) ?></strong></div>
        <div class="kpi-line"><span>Tol</span><strong><?= e(rupiah($tollCost)) ?></strong></div>
        <div class="kpi-line"><span>Parkir</span><strong><?= e(rupiah($parkingCost)) ?></strong></div>
        <div class="kpi-line"><span>Lainnya</span><strong><?= e(rupiah($otherCost)) ?></strong></div>
        <div class="kpi-line" style="margin-top:8px;padding-top:8px;border-top:2px solid var(--border)"><span>Total Keseluruhan</span><strong style="font-size:16px;color:var(--blue)"><?= e(rupiah($notaTotal)) ?></strong></div>
    </div>
</section>

<section class="card" style="margin-top:18px">
    <div class="calendar-toolbar">
        <div>
            <h2 style="font-size:18px;font-weight:700">Realisasi</h2>
            <p class="text-muted" style="font-size:13px;margin-top:4px">Data realisasi perjalanan ini terhubung secara otomatis dengan Kalender &amp; Jadwal (Read-Only).</p>
        </div>
    </div>
    <div style="overflow-x:auto">
        <table class="table" style="font-size:13px;white-space:nowrap">
            <thead>
                <tr style="background:var(--bg-subtle)">
                    <th style="padding:10px">MOBIL &amp; PLAT</th>
                    <th style="padding:10px">DRIVER</th>
                    <th style="padding:10px">TUJUAN</th>
                    <th style="padding:10px">USER (PENGGUNA)</th>
                    <th style="padding:10px">BIDANG (DEPT)</th>
                    <th style="padding:10px">UMK (UANG MUKA)</th>
                    <th style="padding:10px">REALISASI</th>
                    <th style="padding:10px">LEBIH / KURANG</th>
                    <th style="padding:10px">STATUS</th>
                    <th style="padding:10px">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $umk       = (float)($booking['advance_amount'] ?? 0);
                $selisih   = $umk - $notaTotal;
                ?>
                <tr>
                    <td>
                        <strong><?= e($booking['car_name'] ?? 'Mobil belum dipilih') ?></strong>
                        <br><small class="text-muted"><?= e($booking['plate_number'] ?? '-') ?></small>
                    </td>
                    <td><strong><?= e($booking['driver_name'] ?? '-') ?></strong></td>
                    <td><?= e($booking['destination']) ?></td>
                    <td><?= e($booking['requester']) ?></td>
                    <td><?= e($booking['department'] ?? '-') ?></td>
                    <td style="font-weight:600;color:var(--primary)"><?= e(rupiah($umk)) ?></td>
                    <td style="font-weight:600"><?= e(rupiah($notaTotal)) ?></td>
                    <td style="font-weight:700;color:<?= $selisih < 0 ? 'var(--red)' : 'var(--green)' ?>">
                        <?= e(rupiah($selisih)) ?>
                    </td>
                    <td>
                        <span class="badge <?= status_class($booking['status']) ?>">
                            <?= e(status_label($booking['status'])) ?>
                        </span>
                    </td>
                    <td>
                        <a class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:12px" href="<?= e(base_path('calendar.php')) ?>">Lihat Kalender</a>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
