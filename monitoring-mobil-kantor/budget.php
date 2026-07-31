<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'delete_entry') {
        $entryId = (int)($_POST['entry_id'] ?? 0);
        if ($entryId > 0) {
            try {
                db()->prepare('DELETE FROM budget_entries WHERE id = ?')->execute([$entryId]);
                flash('success', 'Entri dropping anggaran berhasil dihapus.');
            } catch (Throwable $e) {
                flash('danger', 'Gagal menghapus entri dropping: ' . $e->getMessage());
            }
        }
        redirect('budget.php');
    }

    $entryDate  = $_POST['entry_date'] ?? date('Y-m-d');
    $amount     = (float)($_POST['amount'] ?? 0);
    $referenceNo = trim($_POST['reference_no'] ?? '');
    $description = trim($_POST['description'] ?? '');

    if (!valid_date_value($entryDate)) {
        flash('danger', 'Tanggal dropping tidak valid.');
    } elseif ($amount <= 0) {
        flash('danger', 'Nominal dropping harus lebih dari 0.');
    } else {
        if ($referenceNo === '') {
            $referenceNo = 'DROP-' . date('ymd', strtotime($entryDate)) . '-' . strtoupper(substr(bin2hex(random_bytes(2)), 0, 4));
        }
        if ($description === '') {
            $description = 'Dropping anggaran operasional kendaraan';
        }
        try {
            $stmt = db()->prepare('INSERT INTO budget_entries (entry_date, reference_no, description, amount, created_by) VALUES (?, ?, ?, ?, ?)');
            $stmt->execute([$entryDate, $referenceNo, $description, $amount, current_user()['id'] ?? null]);
            flash('success', 'Dropping anggaran berhasil ditambahkan.');
        } catch (Throwable $e) {
            flash('danger', 'Gagal menyimpan dropping anggaran: ' . $e->getMessage());
        }
    }
    redirect('budget.php');
}

$monthParam = trim($_GET['month'] ?? '');
if ($monthParam === 'all') {
    $selectedMonth = 'all';
    $selectedMonthName = 'Semua Periode';
    $filterWhereDropping = '';
    $filterWhereBooking  = '';
    $paramsDropping      = [];
    $paramsBooking       = [];
} else {
    $selectedMonth = (preg_match('/^\d{4}-\d{2}$/', $monthParam)) ? $monthParam : date('Y-m');
    $selectedMonthName = bulan_tahun_id($selectedMonth);
    $filterWhereDropping = " WHERE DATE_FORMAT(entry_date, '%Y-%m') = ? ";
    $paramsDropping      = [$selectedMonth];
    $filterWhereBooking  = " AND DATE_FORMAT(date, '%Y-%m') = ? ";
    $paramsBooking       = [$selectedMonth];
}

$title         = 'Dropping Anggaran - Monitoring Mobil Kantor';
$page_title    = 'Dropping Anggaran (' . $selectedMonthName . ')';
$page_subtitle = 'Catat dana dropping operasional kendaraan periode ' . $selectedMonthName . '. Sisa dihitung dari total dropping dikurangi uang jalan driver.';
$budget        = budget_summary($selectedMonth === 'all' ? null : $selectedMonth);

$entries = [];
try {
    $stmt = db()->prepare("SELECT be.*, u.name created_by_name
                            FROM budget_entries be
                            LEFT JOIN users u ON u.id = be.created_by
                            $filterWhereDropping
                            ORDER BY be.entry_date DESC, be.id DESC
                            LIMIT 50");
    $stmt->execute($paramsDropping);
    $entries = $stmt->fetchAll();
} catch (Throwable $e) { $entries = []; }

$recent = [];
try {
    $stmt = db()->prepare("SELECT b.*, c.name car_name, d.name driver_name
                           FROM bookings b
                           LEFT JOIN cars c ON c.id=b.car_id
                           LEFT JOIN drivers d ON d.id=b.driver_id
                           WHERE b.status <> 'rejected' AND b.allowance > 0 $filterWhereBooking
                           ORDER BY b.updated_at DESC
                           LIMIT 50");
    $stmt->execute($paramsBooking);
    $recent = $stmt->fetchAll();
} catch (Throwable $e) { $recent = []; }

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section class="grid grid-2" style="margin-bottom:22px">

    <div class="card">
        <div style="margin-bottom:20px">
            <h2 style="margin-bottom:4px">Tambah Dropping Anggaran</h2>
            <p class="text-muted" style="font-size:13px">Setiap dropping yang diinput akan masuk sebagai dana masuk pada Report Keuangan.</p>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add">
            <div class="form-group">
                <label>Tanggal Dropping</label>
                <input class="input" type="date" name="entry_date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>Nominal Dropping</label>
                <input class="input" type="number" min="1" step="any" name="amount" placeholder="Contoh: 25000000" required>
            </div>
            <div class="form-group">
                <label>Reference No. <span style="color:var(--muted);font-weight:400">(opsional)</span></label>
                <input class="input" name="reference_no" placeholder="Contoh: DROP-0726-001">
            </div>
            <div class="form-group full">
                <label>Keterangan <span style="color:var(--muted);font-weight:400">(opsional)</span></label>
                <textarea name="description" placeholder="Contoh: Dropping anggaran operasional kendaraan bulan <?= e($selectedMonthName) ?>"></textarea>
            </div>
            <div class="full">
                <button class="btn btn-success btn-lg" type="submit" style="width:100%">
                    💾 Simpan Dropping Anggaran
                </button>
            </div>
        </form>
    </div>

    <div class="card">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px">
            <div>
                <h2 style="margin-bottom:2px">Ringkasan Budget</h2>
                <span class="badge badge-info" style="font-weight:700">📅 Periode <?= e($selectedMonthName) ?></span>
            </div>
        </div>

        <div class="budget-kpi-row">
            <div class="budget-kpi-icon" style="background:linear-gradient(135deg,#7c3aed,#5b21b6)">🏦</div>
            <div>
                <div class="budget-kpi-label">Total Dropping Anggaran (<?= e($selectedMonthName) ?>)</div>
                <div class="budget-kpi-value" style="color:var(--blue)"><?= e(rupiah($budget['dropping'])) ?></div>
            </div>
        </div>

        <div class="budget-kpi-row">
            <div class="budget-kpi-icon" style="background:linear-gradient(135deg,#f97316,#c2580a)">💵</div>
            <div>
                <div class="budget-kpi-label">Total Uang Jalan Driver (<?= e($selectedMonthName) ?>)</div>
                <div class="budget-kpi-value" style="color:var(--orange)"><?= e(rupiah($budget['allowance'])) ?></div>
            </div>
        </div>

        <div style="border-top:2px dashed var(--border);margin:16px 0;padding-top:16px">
            <div class="budget-kpi-row">
                <div class="budget-kpi-icon" style="background:<?= $budget['remaining'] >= 0 ? 'linear-gradient(135deg,#059669,#047857)' : 'linear-gradient(135deg,#dc2626,#991b1b)' ?>">🧮</div>
                <div>
                    <div class="budget-kpi-label">Sisa Dropping (Selisih <?= e($selectedMonthName) ?>)</div>
                    <div class="budget-kpi-value" style="color:<?= $budget['remaining'] >= 0 ? 'var(--green)' : 'var(--red)' ?>;font-size:20px">
                        <?= e(rupiah($budget['remaining'])) ?>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($budget['remaining'] < 0): ?>
            <div class="alert alert-danger">
                ⚠️ Pemakaian uang jalan periode <?= e($selectedMonthName) ?> sudah melebihi total dropping anggaran!
            </div>
        <?php endif; ?>

        <?php 
        $pct = $budget['dropping'] > 0 ? min(100, round(($budget['allowance'] / $budget['dropping']) * 100)) : 0;
        ?>
        <div style="margin-top:16px">
            <div style="display:flex;justify-content:space-between;align-items:center;font-size:12.5px;font-weight:700;margin-bottom:6px">
                <span style="color:var(--muted)">Utilisasi Anggaran (<?= e($selectedMonthName) ?>)</span>
                <span style="color:<?= $pct >= 90 ? 'var(--red)' : ($pct >= 70 ? 'var(--orange)' : 'var(--green)') ?>;font-weight:900"><?= $pct ?>%</span>
            </div>
            <div class="progress <?= $pct >= 90 ? 'danger' : ($pct >= 70 ? 'warning' : 'success') ?>" style="height:12px">
                <span style="width:<?= max(0, min(100, $pct)) ?>%"></span>
            </div>
            <small class="text-muted" style="display:block;margin-top:6px;font-size:11.5px">
                <?php if ($budget['dropping'] <= 0): ?>
                    ℹ️ Belum ada dropping anggaran yang diinput pada bulan <?= e($selectedMonthName) ?>.
                <?php else: ?>
                    Terpakai <?= e(rupiah($budget['allowance'])) ?> dari total dropping <?= e(rupiah($budget['dropping'])) ?>.
                <?php endif; ?>
            </small>
        </div>

        <div class="actions" style="margin-top:20px">
            <a class="btn btn-primary" href="<?= e(base_path('reports.php')) ?>">Lihat Report Keuangan →</a>
        </div>
    </div>
</section>

<!-- Filter Toolbar Riwayat -->
<div class="card" style="margin-bottom:18px;padding:14px 20px">
    <form method="get" style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
        <strong style="font-size:13px;color:var(--dark)">🔍 Pilih Periode Bulan:</strong>
        <input type="month" name="month" class="input" value="<?= e($selectedMonth !== 'all' ? $selectedMonth : date('Y-m')) ?>" style="width:auto;padding:6px 12px;font-size:13px">
        <button type="submit" class="btn btn-primary btn-sm">Terapkan Filter</button>
        <a href="budget.php?month=all" class="<?= $selectedMonth === 'all' ? 'btn btn-primary btn-sm' : 'btn btn-outline btn-sm' ?>">Semua Periode</a>
        <?php if (!empty($_GET['month'])): ?>
            <a href="budget.php" class="btn btn-outline btn-sm">Reset ke Bulan Ini</a>
        <?php endif; ?>
        <span class="badge badge-info" style="font-size:12px">Bulan Aktif: <?= e($selectedMonthName) ?></span>
    </form>
</div>

<section class="grid grid-2">
    <div class="card">
        <div class="calendar-toolbar" style="margin-bottom:14px">
            <h2>Riwayat Dropping</h2>
            <span class="badge badge-muted"><?= count($entries) ?> entri (<?= e($selectedMonthName) ?>)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Tanggal</th>
                        <th>Ref. No.</th>
                        <th>Keterangan</th>
                        <th style="text-align:right">Nominal</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($entries as $entry): ?>
                    <tr>
                        <td style="white-space:nowrap"><?= e(tanggal_id($entry['entry_date'])) ?></td>
                        <td><span class="plate-tag"><?= e($entry['reference_no']) ?></span></td>
                        <td>
                            <?= e($entry['description']) ?>
                            <br><small class="text-muted">Oleh <?= e($entry['created_by_name'] ?? '-') ?></small>
                        </td>
                        <td style="text-align:right"><strong style="color:var(--green)"><?= e(rupiah($entry['amount'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$entries): ?>
                    <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--muted)">Belum ada dropping anggaran.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="calendar-toolbar" style="margin-bottom:14px">
            <h2>Riwayat Uang Jalan</h2>
            <span class="badge badge-muted"><?= count($recent) ?> entri (<?= e($selectedMonthName) ?>)</span>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Periode</th>
                        <th>Kode Booking</th>
                        <th>Driver</th>
                        <th style="text-align:right">Uang Jalan</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($recent as $row): ?>
                    <tr>
                        <td style="white-space:nowrap;font-size:12px"><?= e(periode_tanggal($row['date'], $row['return_date'] ?? $row['date'])) ?></td>
                        <td>
                            <a href="<?= e(base_path('booking_detail.php?id=' . $row['id'])) ?>" style="color:var(--blue);font-weight:700">
                                <?= e($row['code']) ?>
                            </a>
                            <br><small class="text-muted"><?= e($row['destination']) ?></small>
                        </td>
                        <td><?= e($row['driver_name'] ?? '-') ?></td>
                        <td style="text-align:right"><strong style="color:var(--orange)"><?= e(rupiah($row['allowance'])) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$recent): ?>
                    <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--muted)">Belum ada uang jalan tercatat.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
