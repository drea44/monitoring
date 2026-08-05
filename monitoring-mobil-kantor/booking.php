<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$title = 'Booking Mobil - Monitoring Mobil Kantor';
$page_title = 'Booking Mobil';
$page_subtitle = 'Pilih tanggal berangkat dan tanggal pulang, cek mobil serta driver yang ready, lalu ajukan booking.';

$form = [
    'date'            => $_POST['date'] ?? '',
    'return_date'     => $_POST['return_date'] ?? ($_POST['date'] ?? ''),
    'start_time'      => $_POST['start_time'] ?? '',
    'end_time'        => $_POST['end_time'] ?? '',
    'destination'     => $_POST['destination'] ?? '',
    'purpose'         => $_POST['purpose'] ?? '',
    'passenger_count' => $_POST['passenger_count'] ?? '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date           = $_POST['date'] ?? '';
    $returnDate     = $_POST['return_date'] ?? $date;
    $start          = $_POST['start_time'] ?? '';
    $end            = $_POST['end_time'] ?? '';
    $destination    = trim($_POST['destination'] ?? '');
    $purpose        = trim($_POST['purpose'] ?? '');
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

    if (!$date || !$returnDate || !$start || !$end || !$destination) {
        flash('danger', 'Tanggal berangkat, tanggal pulang, jam, dan tujuan wajib diisi.');
    } elseif (!$startDT || !$endDT) {
        flash('danger', 'Format tanggal atau jam tidak valid.');
    } elseif ($startDT >= $endDT) {
        flash('danger', 'Tanggal/jam pulang harus lebih besar dari tanggal/jam berangkat.');
    } else {
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
                $stmt = $pdo->prepare("INSERT INTO bookings (code, user_id, car_id, driver_id, date, return_date, start_time, end_time, destination, purpose, passenger_count, advance_amount, status)
                                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
                $stmt->execute([booking_code(), current_user()['id'], $carId, $driverId, $date, $returnDate, $start, $end, $destination, $purpose, $passengerCount, $advanceAmount]);
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
/* ── Trip Date Card ─────────────────────────────────── */
.trip-card {
    background: var(--surface,#fff);
    border: 1.5px solid var(--border,#e2eaf4);
    border-radius: 12px;
    overflow: hidden;
}
.trip-row { display:flex; align-items:stretch; }
.trip-row-divider { height:1px; background:var(--border,#e2eaf4); }
.trip-row-btn {
    display:flex; align-items:center; flex:1; gap:12px;
    background:none; border:none; padding:13px 16px;
    text-align:left; cursor:pointer; min-width:0;
    transition:background .15s;
}
.trip-row-btn:hover { background:var(--blue-light,#eff6ff); }
.trip-row-btn:focus { outline:none; }
.trip-icon {
    width:34px; height:34px; border-radius:8px; flex-shrink:0;
    background:var(--blue-light,#eff6ff);
    display:flex; align-items:center; justify-content:center;
}
.trip-icon-ret { background:var(--green-light,#ecfdf5); }
.trip-date-lbl {
    font-size:10.5px; font-weight:700; color:var(--muted,#64748b);
    margin-bottom:2px; letter-spacing:.4px; text-transform:uppercase;
}
.trip-date-val   { font-size:14px; font-weight:700; color:var(--text,#0f172a); }
.trip-date-empty { font-size:13px; font-weight:500; color:var(--muted-2,#94a3b8); }
.trip-date-info  { flex:1; min-width:0; }
.trip-toggle-wrap {
    display:flex; flex-direction:column; align-items:center;
    justify-content:center; gap:5px; flex-shrink:0;
    padding:12px 16px; min-width:88px;
    border-left:1px solid var(--border,#e2eaf4);
    background:var(--surface-2,#f8fafc);
}
.trip-toggle-lbl {
    font-size:10px; font-weight:700; color:var(--muted,#64748b);
    text-transform:uppercase; letter-spacing:.4px;
}
/* Toggle switch */
.tsw { position:relative; display:inline-block; width:44px; height:24px; }
.tsw input { opacity:0; width:0; height:0; }
.tsw-sl {
    position:absolute; inset:0; background:var(--border-2,#cbd5e8);
    border-radius:24px; cursor:pointer; transition:background .22s;
}
.tsw-sl::before {
    content:''; position:absolute; width:18px; height:18px; left:3px; bottom:3px;
    background:#fff; border-radius:50%; transition:transform .22s;
    box-shadow:0 1px 3px rgba(0,0,0,.2);
}
.tsw input:checked + .tsw-sl { background:var(--blue,#2563ff); }
.tsw input:checked + .tsw-sl::before { transform:translateX(20px); }

/* ── Dropdown Calendar ─────────────────────────────── */
.drp-wrap { position:relative; }
.drp-overlay {
    display:none; position:fixed; inset:0; z-index:998;
}
.drp-overlay.open { display:block; }
.drp-cal {
    display:none; position:absolute; z-index:999;
    top:calc(100% + 6px); left:0;
    background:#fff; border:1.5px solid var(--border,#e2eaf4);
    border-radius:14px; box-shadow:0 8px 32px rgba(15,47,82,.14);
    padding:16px 18px; min-width:290px; user-select:none;
}
.drp-cal.open { display:block; }
.drp-header {
    display:flex; align-items:center;
    justify-content:space-between; margin-bottom:14px;
}
.drp-month-lbl { font-size:14px; font-weight:700; color:var(--text,#0f172a); }
.drp-nav {
    width:30px; height:30px; border-radius:50%;
    border:1.5px solid var(--border,#e2eaf4); background:#fff;
    cursor:pointer; font-size:18px; color:var(--text,#0f172a);
    display:flex; align-items:center; justify-content:center;
    transition:all .15s; line-height:1;
}
.drp-nav:hover { background:var(--blue,#2563ff); color:#fff; border-color:var(--blue,#2563ff); }
.drp-grid {
    display:grid; grid-template-columns:repeat(7,1fr);
    text-align:center; gap:1px 0;
}
.drp-dh {
    font-size:11px; font-weight:700; color:var(--muted,#64748b);
    padding:4px 0 8px;
}
.drp-dh.sun { color:#ef4444; }
.drp-cell { padding:2px 0; cursor:pointer; }
.drp-day {
    width:34px; height:34px; margin:auto;
    display:flex; align-items:center; justify-content:center;
    border-radius:50%; font-size:13px; font-weight:500;
    color:var(--text,#0f172a); transition:background .1s,color .1s;
}
.drp-cell:hover:not(.disabled) .drp-day { background:var(--blue-light,#eff6ff); }
.drp-cell.today .drp-day { border:1.5px solid var(--blue,#2563ff); color:var(--blue,#2563ff); font-weight:700; }
.drp-cell.sel-start .drp-day,
.drp-cell.sel-end   .drp-day { background:var(--blue,#2563ff)!important; color:#fff!important; font-weight:700; }
.drp-cell.in-range  { background:var(--blue-light,#eff6ff); }
.drp-cell.sel-start { border-radius:50% 0 0 50%; }
.drp-cell.sel-end   { border-radius:0 50% 50% 0; }
.drp-cell.sel-start.sel-end { border-radius:50%; }
.drp-cell.sun .drp-day { color:#ef4444; }
.drp-cell.sel-start .drp-day,
.drp-cell.sel-end   .drp-day { color:#fff!important; }
.drp-cell.disabled  { opacity:.4; cursor:not-allowed; pointer-events:none; }
.drp-hint {
    font-size:11.5px; color:var(--blue,#2563ff); font-weight:600;
    text-align:center; margin-top:10px; padding-top:8px;
    border-top:1px solid var(--border,#e2eaf4);
}
.drp-actions {
    display:flex; justify-content:space-between; align-items:center;
    margin-top:8px;
}
.drp-btn-clear {
    font-size:12px; font-weight:600; color:var(--muted,#64748b);
    background:none; border:none; cursor:pointer; padding:4px 8px;
}
.drp-btn-done {
    font-size:12px; font-weight:700; color:#fff;
    background:var(--blue,#2563ff); border:none;
    border-radius:7px; cursor:pointer; padding:6px 16px;
}
</style>

<form method="post" class="card" id="bookingForm">
    <div class="form-grid">

        <!-- TRIP DATE CARD -->
        <div class="form-group full drp-wrap" style="margin-bottom:4px">
            <label style="margin-bottom:8px;display:block">Jadwal Perjalanan</label>
            <div class="trip-card">
                <!-- Hidden submission fields -->
                <input type="hidden" id="inputDate"       name="date"        value="<?= e($form['date']) ?>">
                <input type="hidden" id="inputReturnDate" name="return_date" value="<?= e($form['return_date'] ?: $form['date']) ?>">

                <!-- Row 1: Berangkat -->
                <div class="trip-row">
                    <button type="button" class="trip-row-btn" id="btnBerangkat" onclick="openCal('start')">
                        <div class="trip-icon">
                            <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--blue,#2563ff)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="4" width="18" height="17" rx="3"/>
                                <line x1="3" y1="9" x2="21" y2="9"/>
                                <line x1="8" y1="3" x2="8" y2="6"/>
                                <line x1="16" y1="3" x2="16" y2="6"/>
                            </svg>
                        </div>
                        <div class="trip-date-info">
                            <div class="trip-date-lbl">Tanggal Berangkat</div>
                            <div id="displayBerangkat" class="trip-date-empty">Pilih tanggal</div>
                        </div>
                    </button>
                    <div class="trip-toggle-wrap">
                        <span class="trip-toggle-lbl">Pulang Pergi</span>
                        <label class="tsw">
                            <input type="checkbox" id="roundTripToggle" onchange="onToggle()">
                            <span class="tsw-sl"></span>
                        </label>
                    </div>
                </div>

                <!-- Row 2: Pulang (only when toggle ON) -->
                <div id="returnSection" style="display:none">
                    <div class="trip-row-divider"></div>
                    <div class="trip-row">
                        <button type="button" class="trip-row-btn" onclick="openCal('end')">
                            <div class="trip-icon trip-icon-ret">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="var(--green,#059669)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                    <rect x="3" y="4" width="18" height="17" rx="3"/>
                                    <line x1="3" y1="9" x2="21" y2="9"/>
                                    <line x1="8" y1="3" x2="8" y2="6"/>
                                    <line x1="16" y1="3" x2="16" y2="6"/>
                                </svg>
                            </div>
                            <div class="trip-date-info">
                                <div class="trip-date-lbl">Tanggal Pulang</div>
                                <div id="displayPulang" class="trip-date-empty">Pilih tanggal</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Shared dropdown calendar -->
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

        <!-- Time inputs -->
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
        <div class="form-group">
            <label>Uang Muka (UMK)</label>
            <input class="input" type="number" min="0" step="any" name="advance_amount" value="<?= e($_POST['advance_amount'] ?? '0') ?>" placeholder="0">
            <small class="text-muted" style="font-size:11px">Nominal UMK yang diajukan untuk perjalanan ini</small>
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
        <button class="btn" type="button" onclick="fullResetForm()">Reset Form</button>
    </div>
    <div id="availabilityResult" style="margin-top:18px"></div>
</form>

<script>
var MONTHS_LONG = ['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
var DAYS_SH     = ['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
var MONTHS_SH   = ['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];

var cal = { startDate:'', endDate:'', viewY:0, viewM:0, step:0, mode:'start' };

/* ── Helpers ── */
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
function isRound() { return document.getElementById('roundTripToggle').checked; }

/* ── Open / Close ── */
function openCal(mode) {
    cal.mode = mode;
    var ref  = cal.startDate || todayISO();
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
    cal.startDate = ''; cal.endDate = ''; cal.step = 0;
    document.getElementById('inputDate').value       = '';
    document.getElementById('inputReturnDate').value = '';
    var b = document.getElementById('displayBerangkat');
    b.textContent = 'Pilih tanggal'; b.className = 'trip-date-empty';
    var p = document.getElementById('displayPulang');
    if (p) { p.textContent = 'Pilih tanggal'; p.className = 'trip-date-empty'; }
    renderCal();
}

function fullResetForm() {
    var form = document.getElementById('bookingForm');
    if (form) form.reset();
    calClear();
    var t = document.getElementById('roundTripToggle');
    if (t) t.checked = false;
    var s = document.getElementById('returnSection');
    if (s) s.style.display = 'none';
    var res = document.getElementById('availabilityResult');
    if (res) res.innerHTML = '';
}

/* ── Select a date ── */
function calSelect(ds) {
    if (!isRound()) {
        /* Single-day: only one date needed */
        cal.startDate = ds; cal.endDate = ds;
        applyDates(); closeCal(); return;
    }
    if (cal.step === 0) {
        /* First click = departure */
        cal.startDate = ds; cal.endDate = ''; cal.step = 1;
        renderCal();
    } else {
        /* Second click = return */
        if (ds < cal.startDate) {
            /* Clicked before start → restart */
            cal.startDate = ds; cal.endDate = ''; cal.step = 1;
            renderCal();
        } else {
            cal.endDate = ds;
            applyDates(); closeCal();
        }
    }
}

/* ── Apply to hidden inputs & display ── */
function applyDates() {
    document.getElementById('inputDate').value       = cal.startDate;
    document.getElementById('inputReturnDate').value = cal.endDate || cal.startDate;

    var b = document.getElementById('displayBerangkat');
    b.textContent = fmtLong(cal.startDate) || 'Pilih tanggal';
    b.className   = cal.startDate ? 'trip-date-val' : 'trip-date-empty';

    var p = document.getElementById('displayPulang');
    if (p) {
        var rv = (cal.endDate && cal.endDate !== cal.startDate) ? cal.endDate : cal.startDate;
        p.textContent = fmtLong(rv) || 'Pilih tanggal';
        p.className   = rv ? 'trip-date-val' : 'trip-date-empty';
    }
}

/* ── Toggle round trip ── */
function onToggle() {
    var on  = isRound();
    var sec = document.getElementById('returnSection');
    sec.style.display = on ? 'block' : 'none';
    if (!on) {
        /* Toggle OFF: return date = departure date */
        cal.endDate = cal.startDate;
        document.getElementById('inputReturnDate').value = cal.startDate;
    }
}

/* ── Render calendar grid ── */
function renderCal() {
    var y = cal.viewY, m = cal.viewM;
    document.getElementById('drpMonthLbl').textContent = MONTHS_LONG[m] + ' ' + y;

    var today    = todayISO();
    var firstDow = (new Date(y, m, 1).getDay() + 6) % 7; // Mon=0
    var daysInM  = new Date(y, m+1, 0).getDate();
    var headers  = ['Sen','Sel','Rab','Kam','Jum','Sab','Min'];

    var html = '';
    headers.forEach(function(h, i) {
        html += '<div class="drp-dh' + (i===6?' sun':'') + '">' + h + '</div>';
    });
    for (var i = 0; i < firstDow; i++) html += '<div></div>';

    for (var d = 1; d <= daysInM; d++) {
        var ds  = y + '-' + pad(m+1) + '-' + pad(d);
        var dow = new Date(y, m, d).getDay(); // 0=Sun
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

    /* Update hint */
    var hint = document.getElementById('drpHint');
    if (!isRound()) {
        hint.textContent = 'Pilih tanggal berangkat';
    } else if (cal.step === 0 || !cal.startDate) {
        hint.textContent = 'Pilih tanggal berangkat';
    } else if (!cal.endDate) {
        hint.textContent = 'Sekarang pilih tanggal pulang';
    } else {
        hint.textContent = fmtLong(cal.startDate) + '  \u2192  ' + fmtLong(cal.endDate);
    }
}

/* ── Init on page load ── */
document.addEventListener('DOMContentLoaded', function () {
    cal.startDate = document.getElementById('inputDate').value;
    cal.endDate   = document.getElementById('inputReturnDate').value;
    var round     = cal.endDate && cal.endDate !== cal.startDate;

    if (cal.startDate) {
        var b = document.getElementById('displayBerangkat');
        b.textContent = fmtLong(cal.startDate); b.className = 'trip-date-val';
    }
    if (round) {
        document.getElementById('roundTripToggle').checked = true;
        document.getElementById('returnSection').style.display = 'block';
        var p = document.getElementById('displayPulang');
        if (p && cal.endDate) { p.textContent = fmtLong(cal.endDate); p.className = 'trip-date-val'; }
    }
});
</script>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
