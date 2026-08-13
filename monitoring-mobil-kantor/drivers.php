<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? 'add';

    if ($action === 'add') {
        $stmt = db()->prepare('INSERT INTO drivers (name, phone, status, notes) VALUES (?, ?, ?, ?)');
        $stmt->execute([
            trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''),
            $_POST['status'] ?? 'available', trim($_POST['notes'] ?? '')
        ]);
        flash('success', 'Driver berhasil ditambahkan.');

    } elseif ($action === 'edit') {
        $stmt = db()->prepare('UPDATE drivers SET name=?, phone=?, status=?, notes=? WHERE id=?');
        $stmt->execute([
            trim($_POST['name'] ?? ''), trim($_POST['phone'] ?? ''),
            $_POST['status'] ?? 'available', trim($_POST['notes'] ?? ''),
            (int)($_POST['id'] ?? 0)
        ]);
        flash('success', 'Data driver berhasil diperbarui.');

    } elseif ($action === 'status') {
        $allowed = ['available', 'assigned', 'leave', 'inactive'];
        $status  = in_array($_POST['status'] ?? '', $allowed) ? $_POST['status'] : 'available';
        $stmt    = db()->prepare('UPDATE drivers SET status=? WHERE id=?');
        $stmt->execute([$status, (int)($_POST['id'] ?? 0)]);
        flash('success', 'Status driver berhasil diubah.');

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = db()->prepare('DELETE FROM drivers WHERE id=?');
            $stmt->execute([$id]);
            flash('success', 'Driver berhasil dihapus.');
        } catch (Throwable $e) {
            flash('danger', 'Gagal menghapus driver: Data driver ini masih terhubung dengan riwayat booking.');
        }
    }

    redirect('drivers.php');
}

$title         = 'Data Driver - Monitoring Mobil Kantor';
$page_title    = 'Data Driver';
$page_subtitle = 'Kelola driver, nomor HP, status ketersediaan, dan statistik perjalanan bulan ini.';

$drivers = db()->query("SELECT d.*,
    COUNT(DISTINCT b.id) trips,
    COALESCE(SUM(DISTINCT b.allowance),0) total_allowance,
    COALESCE((
        SELECT SUM(e.amount) 
        FROM expenses e 
        JOIN bookings b2 ON b2.id = e.booking_id 
        WHERE b2.driver_id = d.id 
          AND b2.status <> 'rejected'
          AND b2.date <= LAST_DAY(CURDATE())
          AND COALESCE(b2.return_date, b2.date) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    ), 0) total_expense
    FROM drivers d
    LEFT JOIN bookings b ON b.driver_id=d.id
        AND b.status <> 'rejected'
        AND b.date <= LAST_DAY(CURDATE())
        AND COALESCE(b.return_date, b.date) >= DATE_FORMAT(CURDATE(), '%Y-%m-01')
    GROUP BY d.id ORDER BY d.name")->fetchAll();

function driverStatusBadge(string $s): string {
    return match($s) {
        'available' => 'badge-success',
        'assigned'  => 'badge-info',
        'leave'     => 'badge-warning',
        'inactive'  => 'badge-muted',
        default     => 'badge-muted'
    };
}
function driverStatusLabel(string $s): string {
    return match($s) {
        'available' => 'Tersedia',
        'assigned'  => 'Bertugas',
        'leave'     => 'Cuti/Izin',
        'inactive'  => 'Nonaktif',
        default     => ucfirst($s)
    };
}
function driverStatusIcon(string $s): string {
    return match($s) {
        'available' => '🟢',
        'assigned'  => '🔵',
        'leave'     => '🟡',
        'inactive'  => '⚫',
        default     => '⚪'
    };
}
function driverInitial(string $name): string {
    $parts = explode(' ', trim($name));
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section class="card">
    <div class="calendar-toolbar">
        <div>
            <h2>Daftar Driver</h2>
            <p class="text-muted"><?= count($drivers) ?> driver terdaftar</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('driverAddModal')">+ Tambah Driver</button>
    </div>

    <div class="driver-grid">
    <?php foreach ($drivers as $driver):
        $total = (float)$driver['total_allowance'] - (float)$driver['total_expense'];
    ?>
        <div class="driver-card">
            <div class="driver-card-top">
                <div class="driver-avatar-lg" style="background:<?= match($driver['status']) { 'available' => 'linear-gradient(135deg,#059669,#047857)', 'assigned' => 'linear-gradient(135deg,#2563ff,#1240a8)', 'leave' => 'linear-gradient(135deg,#f97316,#c2580a)', default => 'linear-gradient(135deg,#6b7280,#374151)' } ?>">
                    <?= e(driverInitial($driver['name'])) ?>
                </div>
                <div style="flex:1;min-width:0">
                    <div class="driver-name" style="font-size:16px;font-weight:800;color:var(--dark)"><?= e($driver['name']) ?></div>
                    <div class="driver-phone" style="font-size:13px">📞 <?= e($driver['phone'] ?: '-') ?></div>
                    <span class="badge <?= driverStatusBadge($driver['status']) ?>" style="margin-top:6px;display:inline-flex">
                        <?= driverStatusIcon($driver['status']) ?> <?= driverStatusLabel($driver['status']) ?>
                    </span>
                </div>
            </div>

            <div class="driver-card-actions">
                <button class="btn btn-sm btn-primary"
                    onclick="openDriverStatusModal(<?= (int)$driver['id'] ?>, '<?= e($driver['name']) ?>', '<?= e($driver['status']) ?>')">
                    Ubah Status
                </button>
                <button class="btn btn-sm btn-outline"
                    onclick="openDriverEditModal(<?= (int)$driver['id'] ?>, '<?= e(addslashes($driver['name'])) ?>', '<?= e($driver['phone']) ?>', '<?= e($driver['status']) ?>', '<?= e(addslashes($driver['notes'] ?? '')) ?>')">
                    Edit
                </button>
                <button class="btn btn-sm btn-danger" type="button" onclick="openDeleteDriverModal(<?= (int)$driver['id'] ?>, '<?= e(addslashes($driver['name'])) ?>')">Hapus</button>
            </div>
            <div class="driver-stats">
                <div class="driver-stat-item">
                    <span>Perjalanan</span>
                    <strong><?= e($driver['trips']) ?>x</strong>
                </div>
                <div class="driver-stat-item">
                    <span>Uang Jalan</span>
                    <strong><?= e(rupiah($driver['total_allowance'])) ?></strong>
                </div>
                <div class="driver-stat-item">
                    <span>Biaya Perjalanan</span>
                    <strong><?= e(rupiah($driver['total_expense'])) ?></strong>
                </div>
                <div class="driver-stat-item highlight">
                    <span>Sisa Uang Jalan</span>
                    <strong style="color:<?= $total >= 0 ? 'var(--blue)' : 'var(--red)' ?>"><?= e(rupiah($total)) ?></strong>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$drivers): ?>
        <div style="grid-column:1/-1;text-align:center;padding:48px;color:var(--muted)">
            Belum ada data driver. Klik <strong>+ Tambah Driver</strong> untuk menambahkan.
        </div>
    <?php endif; ?>
    </div>
</section>

<div class="modal-backdrop" id="driverAddModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h2>👤 Tambah Driver Baru</h2>
            <button class="close" onclick="closeModal('driverAddModal')">✕</button>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Nama Driver</label><input class="input" name="name" placeholder="Nama lengkap driver" required></div>
            <div class="form-group"><label>No HP</label><input class="input" name="phone" placeholder="0812-xxxx-xxxx"></div>
            <div class="form-group"><label>Status Awal</label>
                <select name="status">
                    <option value="available">🟢 Tersedia</option>
                    <option value="leave">🟡 Cuti / Izin</option>
                    <option value="inactive">⚫ Nonaktif</option>
                </select>
            </div>
            <div class="form-group full"><label>Catatan (opsional)</label>
                <textarea name="notes" placeholder="Catatan khusus tentang driver ini..."></textarea>
            </div>
            <div class="full actions">
                <button class="btn btn-primary" type="submit">Simpan Driver</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('driverAddModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="driverEditModal">
    <div class="modal" style="max-width:520px">
        <div class="modal-header">
            <h2>✏️ Edit Data Driver</h2>
            <button class="close" onclick="closeModal('driverEditModal')">✕</button>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editDriverId">
            <div class="form-group"><label>Nama Driver</label><input class="input" name="name" id="editDriverName" required></div>
            <div class="form-group"><label>No HP</label><input class="input" name="phone" id="editDriverPhone"></div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="editDriverStatus">
                    <option value="available">🟢 Tersedia</option>
                    <option value="assigned">🔵 Sedang Bertugas</option>
                    <option value="leave">🟡 Cuti / Izin</option>
                    <option value="inactive">⚫ Nonaktif</option>
                </select>
            </div>
            <div class="form-group full"><label>Catatan</label>
                <textarea name="notes" id="editDriverNotes"></textarea>
            </div>
            <div class="full actions">
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('driverEditModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="driverStatusModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h2>⚡ Ubah Status Driver</h2>
            <button class="close" onclick="closeModal('driverStatusModal')">✕</button>
        </div>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" id="dStatusDriverId">
            <p style="color:var(--muted);margin:0 0 20px">Driver: <strong id="dStatusDriverName"></strong></p>
            <div class="form-group">
                <label>Pilih Status Baru</label>
                <div class="status-btn-grid">
                    <label class="status-btn-option available">
                        <input type="radio" name="status" value="available"> 🟢 Tersedia
                    </label>
                    <label class="status-btn-option used">
                        <input type="radio" name="status" value="assigned"> 🔵 Sedang Bertugas
                    </label>
                    <label class="status-btn-option maintenance">
                        <input type="radio" name="status" value="leave"> 🟡 Cuti / Izin
                    </label>
                    <label class="status-btn-option inactive">
                        <input type="radio" name="status" value="inactive"> ⚫ Nonaktif
                    </label>
                </div>
            </div>
            <div class="actions" style="margin-top:20px">
                <button class="btn btn-primary" type="submit">Simpan Status</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('driverStatusModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

<script>
function openDriverEditModal(id, name, phone, status, notes) {
    document.getElementById('editDriverId').value    = id;
    document.getElementById('editDriverName').value  = name;
    document.getElementById('editDriverPhone').value = phone;
    document.getElementById('editDriverStatus').value = status;
    document.getElementById('editDriverNotes').value = notes;
    openModal('driverEditModal');
}
function openDriverStatusModal(id, name, currentStatus) {
    document.getElementById('dStatusDriverId').value     = id;
    document.getElementById('dStatusDriverName').textContent = name;
    const radios = document.querySelectorAll('#driverStatusModal input[name="status"]');
    radios.forEach(r => r.checked = (r.value === currentStatus));
    document.querySelectorAll('#driverStatusModal .status-btn-option').forEach(el => el.classList.remove('selected'));
    const cur = document.querySelector(`#driverStatusModal input[value="${currentStatus}"]`);
    if (cur) cur.closest('.status-btn-option').classList.add('selected');
    openModal('driverStatusModal');
}
document.querySelectorAll('#driverStatusModal .status-btn-option input, #carStatusModal .status-btn-option input').forEach(r => {
    r.addEventListener('change', function () {
        this.closest('.status-btn-grid').querySelectorAll('.status-btn-option').forEach(el => el.classList.remove('selected'));
        this.closest('.status-btn-option').classList.add('selected');
    });
});
</script>

<div class="modal-backdrop" id="deleteDriverModal">
    <div class="modal modal-confirm">
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="modalDeleteDriverId">
            <div class="modal-confirm-icon">
                <span class="icon-circle danger">⚠️</span>
            </div>
            <h2>Hapus Data Driver</h2>
            <p class="text-muted" style="font-size:13px;line-height:1.5;margin-bottom:14px">
                Apakah Anda yakin ingin menghapus driver ini dari sistem?
            </p>
            <div class="confirm-summary-box">
                <div class="summary-row">
                    <span class="label">Nama Driver</span>
                    <strong class="val" id="modalDeleteDriverName">-</strong>
                </div>
            </div>
            <div class="actions" style="margin-top:20px;display:flex;gap:10px">
                <button class="btn btn-outline" type="button" style="flex:1" onclick="closeModal('deleteDriverModal')">Batal</button>
                <button class="btn btn-danger" type="submit" style="flex:1">Ya, Hapus Driver</button>
            </div>
        </form>
    </div>
</div>

<script>
function openDeleteDriverModal(id, name) {
    document.getElementById('modalDeleteDriverId').value = id;
    document.getElementById('modalDeleteDriverName').innerText = name;
    openModal('deleteDriverModal');
}
</script>
