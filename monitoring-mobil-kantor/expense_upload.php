<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$bookingId = (int)($_GET['booking_id'] ?? $_POST['booking_id'] ?? 0);
$params = [$bookingId];
$sql = "SELECT b.*, c.name car_name, d.name driver_name FROM bookings b LEFT JOIN cars c ON c.id=b.car_id LEFT JOIN drivers d ON d.id=b.driver_id WHERE b.id=?";
if (!is_admin()) {
    $sql .= ' AND b.user_id=?';
    $params[] = current_user()['id'];
}
$stmt = db()->prepare($sql);
$stmt->execute($params);
$booking = $stmt->fetch();
if (!$booking) {
    flash('danger', 'Booking tidak ditemukan.');
    redirect('history.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);

    $allowedCategories = ['BBM', 'Tol', 'Parkir', 'Lainnya'];
    $category = $_POST['category'] ?? 'Lainnya';
    if (!in_array($category, $allowedCategories, true)) $category = 'Lainnya';
    $amount = (float)preg_replace('/\D/', '', (string)($_POST['amount'] ?? 0));
    $expenseDate = $_POST['expense_date'] ?? date('Y-m-d');
    $note = trim($_POST['note'] ?? '');
    $fileName = null;

    if ($amount <= 0) {
        flash('danger', 'Nominal realisasi harus lebih dari Rp 0.');
        redirect('expense_upload.php?booking_id=' . $bookingId);
    }

    if (!empty($_FILES['receipt']['name'])) {
        $allowedExt = ['jpg','jpeg','png','pdf'];
        $ext = strtolower(pathinfo($_FILES['receipt']['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExt, true)) {
            flash('danger', 'Format nota hanya boleh JPG, PNG, atau PDF.');
            redirect('expense_upload.php?booking_id=' . $bookingId);
        }
        if ($_FILES['receipt']['size'] > 3 * 1024 * 1024) {
            flash('danger', 'Ukuran file maksimal 3 MB.');
            redirect('expense_upload.php?booking_id=' . $bookingId);
        }
        $fileName = 'nota_' . $bookingId . '_' . time() . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
        $target = __DIR__ . '/uploads/nota/' . $fileName;
        if (!move_uploaded_file($_FILES['receipt']['tmp_name'], $target)) {
            flash('danger', 'Gagal upload file nota. Pastikan folder uploads/nota bisa ditulis.');
            redirect('expense_upload.php?booking_id=' . $bookingId);
        }
    }

    $stmt = db()->prepare('INSERT INTO expenses (booking_id, category, amount, receipt_file, expense_date, note) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->execute([$bookingId, $category, $amount, $fileName, $expenseDate, $note]);

    flash('success', 'Realisasi berhasil disimpan sebagai pengeluaran perjalanan.');
    redirect('booking_detail.php?id=' . $bookingId);
}

$stmt = db()->prepare('SELECT COALESCE(SUM(e.amount),0) FROM expenses e WHERE e.booking_id = ?');
$stmt->execute([$bookingId]);
$tripExpense = (float)$stmt->fetchColumn();

$title = 'Upload Realisasi - Monitoring Mobil Kantor';
$page_title = 'Upload Realisasi';
$page_subtitle = 'Upload bukti transaksi untuk BBM, tol, parkir, atau pengeluaran lainnya.';
include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>
<div class="calendar-toolbar" style="margin-bottom:16px;background:var(--surface,#fff);padding:14px 20px;border-radius:12px;border:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px">
    <div style="display:flex;align-items:center;gap:12px">
        <button type="button" class="btn-back-icon" onclick="if(window.history.length > 1 && document.referrer){ history.back(); } else { location.href='<?= e(base_path('booking_detail.php?id=' . $bookingId)) ?>'; }" title="Kembali">‹</button>
        <span style="color:var(--border)">|</span>
        <span style="font-weight:700;color:var(--text);font-size:14px">Upload Realisasi Perjalanan <?= e($booking['code']) ?></span>
    </div>
    <a class="btn btn-outline btn-sm" href="<?= e(base_path('booking_detail.php?id=' . $bookingId)) ?>">Detail Booking</a>
</div>

<section class="grid grid-2">
    <form method="post" enctype="multipart/form-data" class="card">
        <?= csrf_field() ?>
        <input type="hidden" name="booking_id" value="<?= (int)$bookingId ?>">
        <h2>Form Upload Realisasi</h2>
        <div class="form-grid">
            <div class="form-group">
                <label>Kode Booking</label>
                <input class="input" value="<?= e($booking['code']) ?>" readonly>
            </div>
            <div class="form-group">
                <label>Tanggal Nota</label>
                <input class="input" type="date" name="expense_date" value="<?= e(date('Y-m-d')) ?>" required>
            </div>
            <div class="form-group">
                <label>Kategori Biaya</label>
                <select name="category" required>
                    <option>BBM</option>
                    <option>Tol</option>
                    <option>Parkir</option>
                    <option>Lainnya</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nominal</label>
                <input class="input" type="number" name="amount" min="0" placeholder="Contoh: 150000" required>
            </div>
            <div class="form-group full">
                <label>Upload File Realisasi</label>
                <label class="dropzone">
                    Klik untuk pilih file realisasi<br>
                    <span class="text-muted">JPG, PNG, PDF maksimal 3 MB</span>
                    <input type="file" name="receipt" accept=".jpg,.jpeg,.png,.pdf" style="display:none" onchange="previewUpload(this)">
                </label>
                <div id="uploadPreview"></div>
            </div>
            <div class="form-group full">
                <label>Catatan</label>
                <textarea name="note" placeholder="Contoh: Tol Jakarta-Bandung PP"></textarea>
            </div>
        </div>
        <div class="actions" style="margin-top:18px">
            <button class="btn btn-primary" type="submit">Simpan Realisasi</button>
            <a class="btn btn-outline" href="<?= e(base_path('booking_detail.php?id=' . $bookingId)) ?>">Kembali</a>
        </div>
    </form>

    <div class="card">
        <h2>Detail Perjalanan & Anggaran</h2>
        <div class="kpi-line"><span>Periode</span><strong><?= e(periode_tanggal($booking['date'], $booking['return_date'] ?? $booking['date'])) ?></strong></div>
        <div class="kpi-line"><span>Mobil</span><strong><?= e($booking['car_name'] ?? '-') ?></strong></div>
        <div class="kpi-line"><span>Driver</span><strong><?= e($booking['driver_name'] ?? '-') ?></strong></div>
        <div class="kpi-line"><span>Tujuan</span><strong><?= e($booking['destination']) ?></strong></div>
        <div class="kpi-line"><span>Status</span><strong><?= e(status_label($booking['status'])) ?></strong></div>
        <div class="kpi-line"><span>Uang Jalan / Uang Saku Driver</span><strong><?= e(rupiah($booking['allowance'] ?? 0)) ?></strong></div>
        <div class="kpi-line"><span>Total Realisasi Perjalanan Ini</span><strong><?= e(rupiah($tripExpense)) ?></strong></div>
        <p class="text-muted" style="margin-top:12px">Halaman ini hanya mencatat realisasi pengeluaran untuk perjalanan tersebut.</p>
    </div>
</section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
