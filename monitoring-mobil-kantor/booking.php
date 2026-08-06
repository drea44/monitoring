<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$title = 'Booking Mobil - Monitoring Mobil Kantor';
$page_title = 'Form Booking Mobil Kantor';
$page_subtitle = 'Isi jadwal perjalanan, bidang, rincian penumpang, dan cek ketersediaan armada serta driver secara real-time.';

$form = [
    'date'            => $_POST['date'] ?? '',
    'return_date'     => $_POST['return_date'] ?? ($_POST['date'] ?? ''),
    'start_time'      => $_POST['start_time'] ?? '',
    'end_time'        => $_POST['end_time'] ?? '',
    'destination'     => $_POST['destination'] ?? '',
    'purpose'         => $_POST['purpose'] ?? '',
    'passenger_count' => $_POST['passenger_count'] ?? '1',
    'department'      => $_POST['department'] ?? (current_user()['department'] ?? ''),
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date           = $_POST['date'] ?? '';
    $returnDate     = $_POST['return_date'] ?? $date;
    $start          = $_POST['start_time'] ?? '';
    $end            = $_POST['end_time'] ?? '';
    $destination    = trim($_POST['destination'] ?? '');
    $purpose        = trim($_POST['purpose'] ?? '');
    $department     = trim($_POST['department'] ?? '');
    $passengerCount = max(1, (int)($_POST['passenger_count'] ?? 1));
    $advanceAmount  = max(0, (float)preg_replace('/\D/', '', (string)($_POST['advance_amount'] ?? 0)));
    $carId          = !empty($_POST['car_id'])    ? (int)$_POST['car_id']    : null;
    $driverId       = !empty($_POST['driver_id']) ? (int)$_POST['driver_id'] : null;
    $passengers     = array_filter(array_map('trim', $_POST['passengers'] ?? []));

    $startDT = null;
    $endDT   = null;
    if ($date && $returnDate && $start && $end) {
        $startDT = DateTime::createFromFormat('Y-m-d H:i', $date . ' ' . $start);
        $endDT   = DateTime::createFromFormat('Y-m-d H:i', $returnDate . ' ' . $end);
    }

    if (!$date || !$returnDate || !$start || !$end || !$destination || !$department) {
        flash('danger', 'Tanggal berangkat, tanggal pulang, jam, bidang (departemen), dan tujuan wajib diisi.');
    } elseif (!$startDT || !$endDT) {
        flash('danger', 'Format tanggal atau jam tidak valid.');
    } elseif ($startDT >= $endDT) {
        flash('danger', 'Tanggal/jam pulang harus lebih besar dari tanggal/jam berangkat.');
    } else {
        if ($department !== '') {
            try {
                db()->prepare('UPDATE users SET department = ? WHERE id = ?')->execute([$department, current_user()['id']]);
            } catch (Throwable $e) {}
        }

        $carBusy = false;
        if ($carId) {
            $stmtCar = db()->prepare("SELECT 1 FROM bookings
                WHERE car_id = ?
                  AND status IN ('pending','approved','running')
                  AND TIMESTAMP(date, start_time) < TIMESTAMP(?, ?)
                  AND TIMESTAMP(COALESCE(return_date, date), end_time) > TIMESTAMP(?, ?)");
            $stmtCar->execute([$carId, $returnDate, $end, $date, $start]);
            if ($stmtCar->fetchColumn()) $carBusy = true;
        }

        $driverBusy = false;
        if ($driverId) {
            $stmtDriver = db()->prepare("SELECT 1 FROM bookings
                WHERE driver_id = ?
                  AND status IN ('pending','approved','running')
                  AND TIMESTAMP(date, start_time) < TIMESTAMP(?, ?)
                  AND TIMESTAMP(COALESCE(return_date, date), end_time) > TIMESTAMP(?, ?)");
            $stmtDriver->execute([$driverId, $returnDate, $end, $date, $start]);
            if ($stmtDriver->fetchColumn()) $driverBusy = true;
        }

        if ($carBusy) {
            flash('danger', 'Mobil yang dipilih sudah memiliki jadwal booking lain pada jam tersebut.');
        } elseif ($driverBusy) {
            flash('danger', 'Driver yang dipilih sudah memiliki jadwal tugas lain pada jam tersebut.');
        } else {
            $pdo = db();
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO bookings (code, user_id, department, car_id, driver_id, date, return_date, start_time, end_time, destination, purpose, passenger_count, advance_amount, status)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([booking_code(), current_user()['id'], $department, $carId, $driverId, $date, $returnDate, $start, $end, $destination, $purpose, $passengerCount, $advanceAmount]);
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
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<style>

.booking-page-wrap {
    display: grid;
    grid-template-columns: 1fr 340px;
    gap: 20px;
    align-items: start;
}
@media (max-width: 992px) {
    .booking-page-wrap { grid-template-columns: 1fr; }
}

.booking-banner {
    background: linear-gradient(135deg, var(--navy, #0f172a) 0%, #1e3a8a 100%);
    border-radius: 14px;
    padding: 20px 24px;
    color: #fff;
    margin-bottom: 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 16px;
    box-shadow: 0 4px 20px rgba(15, 23, 42, 0.12);
}
.booking-banner-title h2 {
    margin: 0 0 4px;
    font-size: 19px;
    font-weight: 800;
    color: #fff;
}
.booking-banner-title p {
    margin: 0;
    font-size: 13px;
    color: rgba(255, 255, 255, 0.78);
}
.booking-steps-pills {
    display: flex;
    align-items: center;
    gap: 8px;
}
.step-pill {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.12);
    border: 1px solid rgba(255, 255, 255, 0.2);
    font-size: 12px;
    font-weight: 700;
    color: #fff;
}
.step-pill.active {
    background: var(--blue, #2563ff);
    border-color: var(--blue, #2563ff);
}

.form-section-card {
    background: var(--surface, #fff);
    border: 1.5px solid var(--border, #e2eaf4);
    border-radius: 14px;
    padding: 20px;
    margin-bottom: 18px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
}
.form-section-head {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
    padding-bottom: 12px;
    border-bottom: 1px solid var(--border, #e2eaf4);
}
.form-section-num {
    width: 28px;
    height: 28px;
    border-radius: 50%;
    background: var(--blue-light, #eff6ff);
    color: var(--blue, #2563ff);
    font-weight: 800;
    font-size: 13px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}
.form-section-title {
    font-size: 14.5px;
    font-weight: 800;
    color: var(--text, #0f172a);
    margin: 0;
}

.trip-card {
    background: var(--surface, #fff);
    border: 1.5px solid var(--border, #e2eaf4);
    border-radius: 12px;
    overflow: hidden;
}
.trip-row { display: flex; align-items: stretch; }
.trip-row-divider { height: 1px; background: var(--border, #e2eaf4); }
.trip-row-btn {
    display: flex; align-items: center; flex: 1; gap: 12px;
    background: none; border: none; padding: 13px 16px;
    text-align: left; cursor: pointer; min-width: 0;
    transition: background .15s;
}
.trip-row-btn:hover { background: var(--blue-light, #eff6ff); }
.trip-row-btn:focus { outline: none; }
.trip-icon {
    width: 34px; height: 34px; border-radius: 8px; flex-shrink: 0;
    background: var(--blue-light, #eff6ff);
    display: flex; align-items: center; justify-content: center;
}
.trip-icon-ret { background: var(--green-light, #ecfdf5); }
.trip-date-lbl {
    font-size: 10.5px; font-weight: 700; color: var(--muted, #64748b);
    margin-bottom: 2px; letter-spacing: .4px; text-transform: uppercase;
}
.trip-date-val   { font-size: 14px; font-weight: 700; color: var(--text, #0f172a); }
.trip-date-empty { font-size: 13px; font-weight: 500; color: var(--muted-2, #94a3b8); }
.trip-date-info  { flex: 1; min-width: 0; }

.drp-wrap { position: relative; }
.drp-overlay { display: none; position: fixed; inset: 0; z-index: 998; }
.drp-overlay.open { display: block; }
.drp-cal {
    display: none; position: absolute; z-index: 999;
    top: calc(100% + 6px); left: 0;
    background: #fff; border: 1.5px solid var(--border, #e2eaf4);
    border-radius: 14px; box-shadow: 0 8px 32px rgba(15, 47, 82, .14);
    padding: 16px 18px; min-width: 290px; user-select: none;
}
.drp-cal.open { display: block; }
.drp-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 14px; }
.drp-month-lbl { font-size: 14px; font-weight: 700; color: var(--text, #0f172a); }
.drp-nav {
    width: 30px; height: 30px; border-radius: 50%;
    border: 1.5px solid var(--border, #e2eaf4); background: #fff;
    cursor: pointer; font-size: 18px; color: var(--text, #0f172a);
    display: flex; align-items: center; justify-content: center;
    transition: all .15s; line-height: 1;
}
.drp-nav:hover { background: var(--blue, #2563ff); color: #fff; border-color: var(--blue, #2563ff); }
.drp-grid { display: grid; grid-template-columns: repeat(7, 1fr); text-align: center; gap: 1px 0; }
.drp-dh { font-size: 11px; font-weight: 700; color: var(--muted, #64748b); padding: 4px 0 8px; }
.drp-dh.sun { color: #ef4444; }
.drp-cell { padding: 2px 0; cursor: pointer; }
.drp-day {
    width: 34px; height: 34px; margin: auto;
    display: flex; align-items: center; justify-content: center;
    border-radius: 50%; font-size: 13px; font-weight: 500;
    color: var(--text, #0f172a); transition: background .1s, color .1s;
}
.drp-cell:hover:not(.disabled) .drp-day { background: var(--blue-light, #eff6ff); }
.drp-cell.today .drp-day { border: 1.5px solid var(--blue, #2563ff); color: var(--blue, #2563ff); font-weight: 700; }
.drp-cell.sel-start .drp-day,
.drp-cell.sel-end   .drp-day { background: var(--blue, #2563ff)!important; color: #fff!important; font-weight: 700; }
.drp-cell.in-range  { background: var(--blue-light, #eff6ff); }
.drp-cell.sel-start { border-radius: 50% 0 0 50%; }
.drp-cell.sel-end   { border-radius: 0 50% 50% 0; }
.drp-cell.sel-start.sel-end { border-radius: 50%; }
.drp-cell.sun .drp-day { color: #ef4444; }
.drp-cell.sel-start .drp-day,
.drp-cell.sel-end   .drp-day { color: #fff!important; }
.drp-cell.disabled  { opacity: .4; cursor: not-allowed; pointer-events: none; }
.drp-hint {
    font-size: 11.5px; color: var(--blue, #2563ff); font-weight: 600;
    text-align: center; margin-top: 10px; padding-top: 8px;
    border-top: 1px solid var(--border, #e2eaf4);
}
.drp-actions { display: flex; justify-content: space-between; align-items: center; margin-top: 8px; }
.drp-btn-clear { font-size: 12px; font-weight: 600; color: var(--muted, #64748b); background: none; border: none; cursor: pointer; padding: 4px 8px; }
.drp-btn-done { font-size: 12px; font-weight: 700; color: #fff; background: var(--blue, #2563ff); border: none; border-radius: 7px; cursor: pointer; padding: 6px 16px; }

.booking-side-panel {
    position: sticky;
    top: 20px;
}
.side-summary-card {
    background: var(--surface, #fff);
    border: 1.5px solid var(--border, #e2eaf4);
    border-radius: 14px;
    padding: 20px;
    box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
}
.side-summary-title {
    font-size: 15px;
    font-weight: 800;
    color: var(--text, #0f172a);
    margin: 0 0 14px;
    padding-bottom: 10px;
    border-bottom: 1px solid var(--border, #e2eaf4);
}
.side-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 13px;
    margin-bottom: 10px;
}
.side-item span { color: var(--muted, #64748b); }
.side-item strong { color: var(--text, #0f172a); font-weight: 700; }
</style>

<div class="booking-banner">
    <div class="booking-banner-title">
        <h2>Permohonan Booking Mobil Office</h2>
        <p>Lengkapi formulir di bawah ini untuk mengajukan peminjaman kendaraan operasional</p>
    </div>
    <div class="booking-steps-pills">
        <div class="step-pill active">1. Jadwal</div>
        <div class="step-pill active">2. Rincian</div>
        <div class="step-pill active">3. Armada</div>
    </div>
</div>

<form method="post" id="bookingForm">
    <div class="booking-page-wrap">
        
        <div class="booking-main-col">

            <div class="form-section-card">
                <div class="form-section-head">
                    <div class="form-section-num">1</div>
                    <h3 class="form-section-title">Jadwal & Bidang Perjalanan</h3>
                </div>

                <div class="form-grid">
                    <!-- TRIP DATE CARD -->
                    <div class="form-group full drp-wrap" style="margin-bottom:6px">
                        <label style="margin-bottom:8px;display:block">Jadwal Perjalanan</label>
                        <div class="trip-card">
                            <input type="hidden" id="inputDate"       name="date"        value="<?= e($form['date']) ?>">
                            <input type="hidden" id="inputReturnDate" name="return_date" value="<?= e($form['return_date'] ?: $form['date']) ?>">

                            <!-- Row 1: Tanggal Berangkat -->
                            <div class="trip-row">
                                <button type="button" class="trip-row-btn" id="btnBerangkat" onclick="openCal('start')">
                                    <div class="trip-icon">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--blue,#2563ff)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="17" rx="3"/><line x1="3" y1="9" x2="21" y2="9"/>
                                            <line x1="8" y1="3" x2="8" y2="6"/><line x1="16" y1="3" x2="16" y2="6"/>
                                        </svg>
                                    </div>
                                    <div class="trip-date-info">
                                        <div class="trip-date-lbl">Tanggal Berangkat</div>
                                        <div id="displayBerangkat" class="trip-date-empty">Pilih tanggal</div>
                                    </div>
                                </button>
                            </div>

                            <!-- Row 2: Tanggal Pulang -->
                            <div class="trip-row-divider"></div>
                            <div class="trip-row">
                                <button type="button" class="trip-row-btn" id="btnPulang" onclick="openCal('end')">
                                    <div class="trip-icon trip-icon-ret">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--green,#059669)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="3" y="4" width="18" height="17" rx="3"/><line x1="3" y1="9" x2="21" y2="9"/>
                                            <line x1="8" y1="3" x2="8" y2="6"/><line x1="16" y1="3" x2="16" y2="6"/>
                                        </svg>
                                    </div>
                                    <div class="trip-date-info">
                                        <div class="trip-date-lbl">Tanggal Pulang</div>
                                        <div id="displayPulang" class="trip-date-empty">Pilih tanggal</div>
                                    </div>
                                </button>
                            </div>
                        </div>

                        <!-- Dropdown Calendar -->
                        <div class="drp-overlay" id="drpOverlay" onclick="closeCal()"></div>
                        <div class="drp-cal" id="drpCal">
                            <div class="drp-header">
                                <button type="button" class="drp-nav" onclick="calNav(-1)">&#8249;</button>
                                <span class="drp-month-lbl" id="drpMonthLbl"></span>
                                <button type="button" class="drp-nav" onclick="calNav(1)">&#8250;</button>
                            </div>
                            <div class="drp-grid" id="drpGrid"></div>
                            <div class="drp-hint" id="drpHint">Pilih tanggal berangkat</div>
                            <div class="drp-actions">
                                <button type="button" class="drp-btn-clear" onclick="calClear()">Hapus</button>
                                <button type="button" class="drp-btn-done"  onclick="closeCal()">Selesai</button>
                            </div>
                        </div>
                    </div>

                    <!-- Jam Inputs -->
                    <div class="form-group">
                        <label>Jam Berangkat</label>
                        <input class="input" type="time" name="start_time" value="<?= e($form['start_time']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Jam Kembali</label>
                        <input class="input" type="time" name="end_time" value="<?= e($form['end_time']) ?>" required>
                    </div>

                    <!-- Bidang Select -->
                    <div class="form-group full">
                        <label>Bidang (Departemen)</label>
                        <select name="department" class="input" required>
                            <option value="">-- Pilih Bidang --</option>
                            <?php
                            $departments = [
                                'Dukungan Bisnis',
                                'Penjualan dan Dukungan Operasi',
                                'Bidang Inspeksi Umum',
                                'Bidang Inspeksi Teknik',
                                'Bidang Inspeksi dan Pengujian'
                            ];
                            $selectedDept = $form['department'];
                            foreach ($departments as $dept):
                            ?>
                                <option value="<?= e($dept) ?>" <?= $selectedDept === $dept ? 'selected' : '' ?>><?= e($dept) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tujuan & Keperluan -->
                    <div class="form-group full">
                        <label>Tujuan Perjalanan</label>
                        <input class="input" type="text" name="destination" value="<?= e($form['destination']) ?>" placeholder="Contoh: Kantor Cabang Bandung" required>
                    </div>
                    <div class="form-group full">
                        <label>Keperluan Perjalanan</label>
                        <textarea name="purpose" rows="2" placeholder="Contoh: Meeting client, audit cabang, kunjungan site operasional"><?= e($form['purpose']) ?></textarea>
                    </div>
                </div>
            </div>

            <!-- SECTION 2: RINCIAN PENUMPANG & UMK -->
            <div class="form-section-card">
                <div class="form-section-head">
                    <div class="form-section-num">2</div>
                    <h3 class="form-section-title">Rincian Penumpang & UMK</h3>
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label>Jumlah Penumpang</label>
                        <input class="input" type="number" name="passenger_count" min="1" value="<?= e($form['passenger_count']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Uang Muka (UMK)</label>
                        <input class="input input-currency" type="text" inputmode="numeric" name="advance_amount" value="<?= e($_POST['advance_amount'] ?? '0') ?>" placeholder="0">
                        <small class="text-muted" style="font-size:11px">Estimasi UMK diajukan untuk perjalanan ini</small>
                    </div>
                    <div class="form-group full">
                        <label>Daftar Nama Penumpang</label>
                        <div id="passengerList" class="grid" style="gap:8px">
                            <?php $oldPassengers = $_POST['passengers'] ?? ['']; ?>
                            <?php foreach ($oldPassengers as $passenger): ?>
                                <input class="input" name="passengers[]" value="<?= e($passenger) ?>" placeholder="Nama penumpang">
                            <?php endforeach; ?>
                        </div>
                        <button class="btn btn-outline btn-sm" type="button" onclick="addPassenger()" style="margin-top:8px">+ Tambah Penumpang</button>
                    </div>
                </div>
            </div>

            <!-- SECTION 3: HASIL CEK ARMADA & DRIVER -->
            <div class="form-section-card">
                <div class="form-section-head" style="justify-content:space-between">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="form-section-num">3</div>
                        <h3 class="form-section-title">Pilih Mobil & Driver Ready</h3>
                    </div>
                    <button class="btn btn-primary btn-sm" type="button" onclick="checkAvailability()">Cek Ketersediaan Armada</button>
                </div>
                <div id="availabilityResult">
                    <p class="text-muted" style="font-size:13px;margin:0;padding:12px;background:var(--surface-2,#f8fafc);border-radius:10px;border:1px dashed var(--border,#e2eaf4);text-align:center">
                        Isi tanggal & jam perjalanan, lalu klik <strong>"Cek Ketersediaan Armada"</strong> untuk memilih mobil dan driver yang ready.
                    </p>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN: STICKY SUMMARY & ACTIONS -->
        <div class="booking-side-panel">
            <div class="side-summary-card">
                <h3 class="side-summary-title">Ringkasan Pengajuan</h3>
                
                <div class="side-item">
                    <span>Pemesan</span>
                    <strong><?= e(current_user()['name']) ?></strong>
                </div>
                <div class="side-item">
                    <span>Status Awal</span>
                    <strong style="color:var(--orange,#d97706)">Pending Approval</strong>
                </div>
                <div style="height:1px;background:var(--border,#e2eaf4);margin:12px 0"></div>

                <div style="display:flex;flex-direction:column;gap:6px;margin-bottom:12px">
                    <div class="side-item" style="margin-bottom:0">
                        <span>🗓️ Berangkat</span>
                        <strong id="summaryBerangkat" style="font-size:12px;color:var(--blue,#2563ff)">—</strong>
                    </div>
                    <div class="side-item" style="margin-bottom:0">
                        <span>🏠 Pulang</span>
                        <strong id="summaryPulang" style="font-size:12px;color:var(--green,#059669)">—</strong>
                    </div>
                </div>
                <div style="height:1px;background:var(--border,#e2eaf4);margin:12px 0"></div>

                <div style="display:flex;flex-direction:column;gap:10px;margin-top:16px">
                    <button class="btn btn-success" type="submit" style="width:100%;font-size:14px;padding:12px;font-weight:800">Ajukan Permohonan Booking</button>
                    <button class="btn btn-outline" type="button" onclick="fullResetForm()" style="width:100%;font-size:13px;padding:9px">Reset Formulir</button>
                </div>
            </div>
        </div>

    </div>
</form>

<script>
var MONTHS_LONG = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
var DAYS_SH     = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
var MONTHS_SH   = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

var cal = { startDate:'', endDate:'', viewY:0, viewM:0, step:0, mode:'start' };

function todayISO() {
    var t = new Date();
    return t.getFullYear() + '-' + pad(t.getMonth()+1) + '-' + pad(t.getDate());
}
function pad(n){ return String(n).padStart(2,'0'); }
function fmtLong(s) {
    if (!s) return '';
    var d = new Date(s + 'T00:00:00');
    return DAYS_SH[d.getDay()] + ', ' + pad(d.getDate()) + ' ' + MONTHS_SH[d.getMonth()] + ' ' + d.getFullYear();
}

function openCal(mode) {
    cal.mode = mode || 'start';
    var ref  = (mode === 'end' && cal.endDate) ? cal.endDate : (cal.startDate || todayISO());
    var d    = new Date(ref + 'T00:00:00');
    cal.viewY = d.getFullYear();
    cal.viewM = d.getMonth();
    cal.step  = (mode === 'end' && cal.startDate) ? 1 : 0;
    document.getElementById('drpOverlay').classList.add('open');
    document.getElementById('drpCal').classList.add('open');
    renderCal();
}
function closeCal() {
    document.getElementById('drpOverlay').classList.remove('open');
    document.getElementById('drpCal').classList.remove('open');
}
function calNav(d) {
    cal.viewM += d;
    if (cal.viewM < 0)  { cal.viewM = 11; cal.viewY--; }
    if (cal.viewM > 11) { cal.viewM = 0;  cal.viewY++; }
    renderCal();
}
function calClear() {
    cal.startDate = ''; cal.endDate = ''; cal.step = 0; cal.mode = 'start';
    document.getElementById('inputDate').value       = '';
    document.getElementById('inputReturnDate').value = '';
    var b = document.getElementById('displayBerangkat');
    b.textContent = 'Pilih tanggal'; b.className = 'trip-date-empty';
    var p = document.getElementById('displayPulang');
    if (p) { p.textContent = 'Pilih tanggal'; p.className = 'trip-date-empty'; }
    /* Reset sidebar summary */
    var sb = document.getElementById('summaryBerangkat');
    var sp = document.getElementById('summaryPulang');
    if (sb) sb.textContent = '\u2014';
    if (sp) sp.textContent = '\u2014';
    renderCal();
}

function fullResetForm() {
    var form = document.getElementById('bookingForm');
    if (form) form.reset();
    calClear();
    /* Reset availability result */
    var res = document.getElementById('availabilityResult');
    if (res) res.innerHTML = '<p class="text-muted" style="font-size:13px;margin:0;padding:12px;background:var(--surface-2,#f8fafc);border-radius:10px;border:1px dashed var(--border,#e2eaf4);text-align:center">Isi tanggal &amp; jam perjalanan, lalu klik <strong>"Cek Ketersediaan Armada"</strong> untuk memilih mobil dan driver yang ready.</p>';
    /* Reset hidden car/driver fields if they exist */
    var carId = document.getElementById('selectedCarId');
    var drvId = document.getElementById('selectedDriverId');
    if (carId) carId.value = '';
    if (drvId) drvId.value = '';
}

function calSelect(ds) {
    if (cal.mode === 'start') {
        /* Picking departure date: DO NOT auto-fill return date */
        cal.startDate = ds;
        /* If return date is now before departure, reset it */
        if (cal.endDate && cal.endDate < cal.startDate) {
            cal.endDate = '';
        }
        applyDates();
        closeCal();
    } else {
        /* Picking return date */
        if (ds < cal.startDate) {
            /* Swap: chosen day is before departure, treat as new departure */
            cal.startDate = ds;
            cal.endDate   = '';
        } else {
            cal.endDate = ds;
        }
        applyDates();
        closeCal();
    }
}

function applyDates() {
    document.getElementById('inputDate').value       = cal.startDate;
    /* Only submit return_date if explicitly chosen; otherwise submit same as start */
    document.getElementById('inputReturnDate').value = cal.endDate || cal.startDate;

    var b = document.getElementById('displayBerangkat');
    b.textContent = fmtLong(cal.startDate) || 'Pilih tanggal';
    b.className   = cal.startDate ? 'trip-date-val' : 'trip-date-empty';

    var p = document.getElementById('displayPulang');
    if (p) {
        /* Show empty state if return date not yet selected */
        p.textContent = cal.endDate ? fmtLong(cal.endDate) : 'Pilih tanggal';
        p.className   = cal.endDate ? 'trip-date-val' : 'trip-date-empty';
    }

    /* Update side panel summary live */
    var sb = document.getElementById('summaryBerangkat');
    var sp = document.getElementById('summaryPulang');
    if (sb) sb.textContent = fmtLong(cal.startDate) || '—';
    if (sp) sp.textContent = cal.endDate ? fmtLong(cal.endDate) : (cal.startDate ? fmtLong(cal.startDate) + ' (1 hari)' : '—');
}

function renderCal() {
    var y = cal.viewY, m = cal.viewM;
    document.getElementById('drpMonthLbl').textContent = MONTHS_LONG[m] + ' ' + y;

    var today    = todayISO();
    var firstDow = (new Date(y, m, 1).getDay() + 6) % 7;
    var daysInM  = new Date(y, m+1, 0).getDate();
    var headers  = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];

    var html = '';
    headers.forEach(function(h, i) {
        html += '<div class="drp-dh' + (i===6?' sun':'') + '">' + h + '</div>';
    });
    for (var i = 0; i < firstDow; i++) html += '<div></div>';

    for (var d = 1; d <= daysInM; d++) {
        var ds  = y + '-' + pad(m+1) + '-' + pad(d);
        var dow = new Date(y, m, d).getDay();
        var cls = 'drp-cell';
        if (dow === 0)    cls += ' sun';
        if (ds === today) cls += ' today';
        if (ds < today)   cls += ' disabled';
        if (ds === cal.startDate) cls += ' sel-start';
        if (ds === cal.endDate)   cls += ' sel-end';
        if (cal.startDate && cal.endDate && ds > cal.startDate && ds < cal.endDate) cls += ' in-range';

        html += '<div class="' + cls + '" onclick="calSelect(\'' + ds + '\')">' +
                '<div class="drp-day">' + d + '</div></div>';
    }
    document.getElementById('drpGrid').innerHTML = html;

    var hint = document.getElementById('drpHint');
    if (cal.mode === 'end') {
        hint.textContent = 'Pilih tanggal pulang';
    } else {
        hint.textContent = 'Pilih tanggal berangkat';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    cal.startDate = document.getElementById('inputDate').value;
    /* Only restore endDate if it differs from startDate (true multi-day trip) */
    var rawEnd = document.getElementById('inputReturnDate').value;
    cal.endDate = (rawEnd && rawEnd !== cal.startDate) ? rawEnd : '';

    if (cal.startDate) {
        var b = document.getElementById('displayBerangkat');
        b.textContent = fmtLong(cal.startDate); b.className = 'trip-date-val';
        /* Update sidebar */
        var sb = document.getElementById('summaryBerangkat');
        if (sb) sb.textContent = fmtLong(cal.startDate);
    }
    if (cal.endDate) {
        var p = document.getElementById('displayPulang');
        if (p) { p.textContent = fmtLong(cal.endDate); p.className = 'trip-date-val'; }
        var sp = document.getElementById('summaryPulang');
        if (sp) sp.textContent = fmtLong(cal.endDate);
    } else if (cal.startDate) {
        var sp2 = document.getElementById('summaryPulang');
        if (sp2) sp2.textContent = fmtLong(cal.startDate) + ' (1 hari)';
    }
});
</script>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
