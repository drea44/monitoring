<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_id'])) {
    $id = (int)$_POST['verify_id'];
    db()->prepare('UPDATE expenses SET verified=1, verified_at=NOW() WHERE id=?')->execute([$id]);

    if (!empty($_POST['ajax'])) {
        header('Content-Type: application/json');
        echo json_encode([
            'ok' => true,
            'id' => $id,
            'verified_at' => date('d/m/Y')
        ]);
        exit;
    }

    flash('success', 'Nota berhasil diverifikasi.');
    redirect('expenses.php');
}

$title         = 'Nota & Pengeluaran - Monitoring Mobil Kantor';
$page_title    = 'Nota & Pengeluaran';
$page_subtitle = 'Kelola dan verifikasi nota pengeluaran perjalanan kendaraan kantor.';

$stmt = db()->query(
    "SELECT e.*, b.code, b.destination, b.date, b.return_date, c.name car_name, d.name driver_name
     FROM expenses e
     JOIN bookings b ON b.id = e.booking_id
     LEFT JOIN cars c ON c.id = b.car_id
     LEFT JOIN drivers d ON d.id = b.driver_id
     ORDER BY e.created_at DESC"
);
$expenses = $stmt->fetchAll();

$totalNota      = array_sum(array_map(fn($x) => (float)$x['amount'], $expenses));
$verifiedCount  = count(array_filter($expenses, fn($x) => (int)$x['verified'] === 1));
$unverifiedCount = count($expenses) - $verifiedCount;
$totalVerified  = array_sum(array_map(fn($x) => (int)$x['verified'] ? (float)$x['amount'] : 0, $expenses));

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section class="grid grid-4" style="margin-bottom:22px">
    <div class="card stat card-blue">
        <div>
            <span>Jumlah Nota</span>
            <strong><?= count($expenses) ?></strong>
            <small>total nota</small>
        </div>
        <div class="icon">🧾</div>
    </div>
    <div class="card stat card-purple">
        <div>
            <span>Total Pengeluaran</span>
            <strong style="font-size:20px"><?= e(rupiah($totalNota)) ?></strong>
            <small>seluruh nota</small>
        </div>
        <div class="icon">💰</div>
    </div>
    <div class="card stat card-green">
        <div>
            <span>Terverifikasi</span>
            <strong id="statVerifiedCount"><?= $verifiedCount ?></strong>
            <small id="statVerifiedAmount"><?= e(rupiah($totalVerified)) ?></small>
        </div>
        <div class="icon">✅</div>
    </div>
    <div class="card stat card-orange">
        <div>
            <span>Belum Verifikasi</span>
            <strong id="statUnverifiedCount"><?= $unverifiedCount ?></strong>
            <small>perlu ditinjau</small>
        </div>
        <div class="icon">⏳</div>
    </div>
</section>

<section class="card">
    <div class="calendar-toolbar" style="margin-bottom:18px">
        <div>
            <h2>Daftar Nota Pengeluaran</h2>
            <p class="text-muted"><?= count($expenses) ?> nota ditemukan</p>
        </div>
        <div class="actions">
            <?php if ($unverifiedCount > 0): ?>
                <span id="statUnverifiedBadge" class="badge badge-warning" style="font-size:13px;padding:7px 14px">
                    ⚠️ <span id="badgeUnverifiedCount"><?= $unverifiedCount ?></span> belum diverifikasi
                </span>
            <?php else: ?>
                <span id="statUnverifiedBadge" class="badge badge-warning" style="font-size:13px;padding:7px 14px;display:none">
                    ⚠️ <span id="badgeUnverifiedCount">0</span> belum diverifikasi
                </span>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$expenses): ?>
        <div class="empty-state">
            <div style="font-size:48px;margin-bottom:12px">🧾</div>
            <h3>Belum Ada Nota</h3>
            <p>Nota pengeluaran akan muncul setelah driver mengunggah bukti perjalanan.</p>
        </div>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Booking</th>
                    <th>Mobil & Driver</th>
                    <th>Kategori</th>
                    <th style="text-align:right">Nominal</th>
                    <th>Nota</th>
                    <th>Status</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($expenses as $ex): ?>
                <tr id="expenseRow<?= (int)$ex['id'] ?>" class="<?= !(int)$ex['verified'] ? 'row-unverified' : '' ?>" data-amount="<?= (float)$ex['amount'] ?>">
                    <td style="white-space:nowrap">
                        <div style="font-weight:600;font-size:13px"><?= e(tanggal_id($ex['expense_date'])) ?></div>
                    </td>
                    <td>
                        <a href="<?= e(base_path('booking_detail.php?id=' . $ex['booking_id'])) ?>"
                           style="font-weight:800;color:var(--blue);font-size:13px">
                            <?= e($ex['code']) ?>
                        </a>
                        <div style="font-size:12px;color:var(--muted);margin-top:2px"><?= e($ex['destination']) ?></div>
                        <div style="font-size:11px;color:var(--muted-2)"><?= e(periode_tanggal($ex['date'], $ex['return_date'] ?? $ex['date'])) ?></div>
                    </td>
                    <td>
                        <div style="font-weight:700;font-size:13px"><?= e($ex['car_name'] ?? '-') ?></div>
                        <div style="font-size:12px;color:var(--muted)">👤 <?= e($ex['driver_name'] ?? '-') ?></div>
                    </td>
                    <td>
                        <span class="badge badge-info"><?= e($ex['category']) ?></span>
                    </td>
                    <td style="text-align:right">
                        <strong style="font-size:14px;color:var(--text)"><?= e(rupiah($ex['amount'])) ?></strong>
                    </td>
                    <td>
                        <?php if ($ex['receipt_file']): ?>
                            <a class="btn btn-sm btn-outline" target="_blank"
                               href="<?= e(base_path('uploads/nota/' . $ex['receipt_file'])) ?>">
                               📎 Lihat
                            </a>
                        <?php else: ?>
                            <span style="color:var(--muted-2);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                    <td id="expenseStatusCell<?= (int)$ex['id'] ?>">
                        <?php if ((int)$ex['verified']): ?>
                            <span class="badge badge-success">✅ Terverifikasi</span>
                            <?php if ($ex['verified_at']): ?>
                                <div style="font-size:11px;color:var(--muted);margin-top:3px"><?= e(date('d/m/Y', strtotime($ex['verified_at']))) ?></div>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge badge-warning">⏳ Belum</span>
                        <?php endif; ?>
                    </td>
                    <td id="expenseActionCell<?= (int)$ex['id'] ?>" style="text-align:center">
                        <?php if (!(int)$ex['verified']): ?>
                            <form method="post" id="verifyForm<?= (int)$ex['id'] ?>">
                                <input type="hidden" name="verify_id" value="<?= (int)$ex['id'] ?>">
                                <button class="btn btn-sm btn-success" type="button"
                                        onclick="openVerifyModal(<?= (int)$ex['id'] ?>, '<?= e(addslashes($ex['code'])) ?>', '<?= e(addslashes($ex['category'])) ?>', '<?= e(addslashes(rupiah($ex['amount']))) ?>', '<?= e(addslashes($ex['driver_name'] ?? '-')) ?>', '<?= e(addslashes($ex['car_name'] ?? '-')) ?>', <?= (float)$ex['amount'] ?>)">
                                    Verifikasi
                                </button>
                            </form>
                        <?php else: ?>
                            <span style="color:var(--muted-2);font-size:12px">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="expenses-summary-bar">
        <div class="expenses-summary-item">
            <span>Total <?= count($expenses) ?> Nota</span>
            <strong><?= e(rupiah($totalNota)) ?></strong>
        </div>
        <div class="expenses-summary-item" style="color:var(--green)">
            <span>Terverifikasi (<span id="sumVerifiedCount"><?= $verifiedCount ?></span>)</span>
            <strong id="sumVerifiedAmount"><?= e(rupiah($totalVerified)) ?></strong>
        </div>
        <div class="expenses-summary-item" style="color:var(--orange)">
            <span>Belum Verifikasi (<span id="sumUnverifiedCount"><?= $unverifiedCount ?></span>)</span>
            <strong id="sumUnverifiedAmount"><?= e(rupiah($totalNota - $totalVerified)) ?></strong>
        </div>
    </div>
    <?php endif; ?>
</section>

</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

<style>
.row-unverified td {
    background: #fffcf0 !important
}
.row-unverified:hover td {
    background: #fef9c3 !important
}

.expenses-summary-bar {
    display: flex;
    gap: 0;
    border-top: 2px solid var(--border);
    margin-top: 0;
    background: var(--surface-2);
    border-radius: 0 0 var(--radius-md) var(--radius-md)
}

.expenses-summary-item {
    flex: 1;
    padding: 14px 18px;
    border-right: 1px solid var(--border);
    display: flex;
    flex-direction: column;
    gap: 4px
}

.expenses-summary-item:last-child {
    border-right: none
}

.expenses-summary-item span {
    font-size: 11.5px;
    color: var(--muted);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .04em
}

.expenses-summary-item strong {
    font-size: 16px;
    font-weight: 900;
    letter-spacing: -.02em
}
</style>

<div class="modal-backdrop" id="verifyConfirmModal">
    <div class="modal modal-confirm">
        <div class="modal-confirm-icon">
            <span class="icon-circle">🛡️</span>
        </div>
        <h2>Konfirmasi Verifikasi Nota</h2>
        <p class="text-muted" style="font-size:13px;line-height:1.5;margin-bottom:14px">
            Apakah Anda yakin ingin memverifikasi nota pengeluaran ini?
        </p>

        <div class="confirm-summary-box">
            <div class="summary-row">
                <span class="label">Kode Booking</span>
                <strong class="val" id="modalVerifyCode">-</strong>
            </div>
            <div class="summary-row">
                <span class="label">Mobil & Driver</span>
                <strong class="val" id="modalVerifyVehicle">-</strong>
            </div>
            <div class="summary-row">
                <span class="label">Kategori Nota</span>
                <span class="badge badge-info" id="modalVerifyCategory">-</span>
            </div>
            <div class="summary-row highlight">
                <span class="label">Nominal Nota</span>
                <strong class="val-amount" id="modalVerifyAmount">Rp 0</strong>
            </div>
        </div>

        <div class="actions" style="margin-top:20px;display:flex;gap:10px">
            <button class="btn btn-outline" type="button" style="flex:1" onclick="closeModal('verifyConfirmModal')">
                Batal
            </button>
            <button class="btn btn-success" type="button" id="modalVerifySubmitBtn" style="flex:1">
                ✓ Ya, Verifikasi
            </button>
        </div>
    </div>
</div>

<script>
let currentVerifyId = null;
let currentVerifyAmount = 0;

function openVerifyModal(id, code, category, amountFormatted, driver, car, amountNum) {
    currentVerifyId = id;
    currentVerifyAmount = amountNum || 0;
    const modalCard = document.querySelector('#verifyConfirmModal .modal-confirm');
    if (modalCard) {
        modalCard.innerHTML = `
            <div class="modal-confirm-icon">
                <span class="icon-circle">🛡️</span>
            </div>
            <h2>Konfirmasi Verifikasi Nota</h2>
            <p class="text-muted" style="font-size:13px;line-height:1.5;margin-bottom:14px">
                Apakah Anda yakin ingin memverifikasi nota pengeluaran ini?
            </p>

            <div class="confirm-summary-box">
                <div class="summary-row">
                    <span class="label">Kode Booking</span>
                    <strong class="val">${code}</strong>
                </div>
                <div class="summary-row">
                    <span class="label">Mobil & Driver</span>
                    <strong class="val">${car} (${driver})</strong>
                </div>
                <div class="summary-row">
                    <span class="label">Kategori Nota</span>
                    <span class="badge badge-info">${category}</span>
                </div>
                <div class="summary-row highlight">
                    <span class="label">Nominal Nota</span>
                    <strong class="val-amount">${amountFormatted}</strong>
                </div>
            </div>

            <div class="actions" style="margin-top:20px;display:flex;gap:10px">
                <button class="btn btn-outline" type="button" style="flex:1" onclick="closeModal('verifyConfirmModal')">
                    Batal
                </button>
                <button class="btn btn-success" type="button" style="flex:1" onclick="executeRealtimeVerification()">
                    ✓ Ya, Verifikasi
                </button>
            </div>
        `;
    }
    openModal('verifyConfirmModal');
}

function executeRealtimeVerification() {
    if (!currentVerifyId) return;
    const id = currentVerifyId;
    const modalCard = document.querySelector('#verifyConfirmModal .modal-confirm');
    
    if (modalCard) {
        modalCard.innerHTML = `
            <div class="success-animation-wrap">
                <div class="success-checkmark-circle">
                    <svg class="checkmark-svg" viewBox="0 0 52 52">
                        <circle class="checkmark-circle" cx="26" cy="26" r="23" fill="none"/>
                        <path class="checkmark-check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8"/>
                    </svg>
                </div>
                <h2 class="success-title">Verifikasi Berhasil!</h2>
                <p class="success-text">✓ Nota pengeluaran telah terverifikasi secara real-time</p>
            </div>
        `;
    }

    const formData = new FormData();
    formData.append('verify_id', id);
    formData.append('ajax', '1');

    fetch('expenses.php', {
        method: 'POST',
        body: formData
    })
    .then(res => res.json())
    .then(data => {
        if (data && data.ok) {
            const row = document.getElementById('expenseRow' + id);
            if (row) row.classList.remove('row-unverified');
            
            const statusCell = document.getElementById('expenseStatusCell' + id);
            if (statusCell) {
                statusCell.innerHTML = `<span class="badge badge-success">✅ Terverifikasi</span><div style="font-size:11px;color:var(--muted);margin-top:3px">${data.verified_at}</div>`;
            }

            const actionCell = document.getElementById('expenseActionCell' + id);
            if (actionCell) {
                actionCell.innerHTML = `<span style="color:var(--muted-2);font-size:12px">—</span>`;
            }

            updateExpensesPageStats();
            if (typeof updateRealTimeStats === 'function') updateRealTimeStats();
        }
    })
    .catch(err => console.error('Verification error:', err));

    setTimeout(() => {
        closeModal('verifyConfirmModal');
    }, 1300);
}

function updateExpensesPageStats() {
    const elVerCount = document.getElementById('statVerifiedCount');
    const elUnverCount = document.getElementById('statUnverifiedCount');
    const elSumVerCount = document.getElementById('sumVerifiedCount');
    const elSumUnverCount = document.getElementById('sumUnverifiedCount');
    const elBadge = document.getElementById('statUnverifiedBadge');
    const elBadgeCount = document.getElementById('badgeUnverifiedCount');

    if (elVerCount && elUnverCount) {
        let ver = parseInt(elVerCount.innerText || '0') + 1;
        let unver = Math.max(0, parseInt(elUnverCount.innerText || '0') - 1);
        
        elVerCount.innerText = ver;
        elUnverCount.innerText = unver;
        if (elSumVerCount) elSumVerCount.innerText = ver;
        if (elSumUnverCount) elSumUnverCount.innerText = unver;
        if (elBadgeCount) elBadgeCount.innerText = unver;
        if (elBadge) elBadge.style.display = unver <= 0 ? 'none' : 'inline-block';
    }
}
</script>
