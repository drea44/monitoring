<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';

    if ($action === 'add') {
        $stmt = db()->prepare('INSERT INTO cars (name, plate_number, capacity, last_km, status, notes) VALUES (?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim($_POST['name'] ?? ''), trim($_POST['plate_number'] ?? ''),
            (int)($_POST['capacity'] ?? 4), (int)($_POST['last_km'] ?? 0),
            $_POST['status'] ?? 'available', trim($_POST['notes'] ?? '')
        ]);
        flash('success', 'Mobil berhasil ditambahkan.');

    } elseif ($action === 'edit') {
        $stmt = db()->prepare('UPDATE cars SET name=?, plate_number=?, capacity=?, last_km=?, status=?, notes=? WHERE id=?');
        $stmt->execute([
            trim($_POST['name'] ?? ''), trim($_POST['plate_number'] ?? ''),
            (int)($_POST['capacity'] ?? 4), (int)($_POST['last_km'] ?? 0),
            $_POST['status'] ?? 'available', trim($_POST['notes'] ?? ''),
            (int)($_POST['id'] ?? 0)
        ]);
        flash('success', 'Data mobil berhasil diperbarui.');

    } elseif ($action === 'status') {
        $allowed = ['available', 'used', 'maintenance', 'inactive'];
        $status  = in_array($_POST['status'] ?? '', $allowed) ? $_POST['status'] : 'available';
        $stmt    = db()->prepare('UPDATE cars SET status=? WHERE id=?');
        $stmt->execute([$status, (int)($_POST['id'] ?? 0)]);
        flash('success', 'Status mobil berhasil diubah.');

    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            $stmt = db()->prepare('DELETE FROM cars WHERE id=?');
            $stmt->execute([$id]);
            flash('success', 'Armada mobil berhasil dihapus.');
        } catch (Throwable $e) {
            flash('danger', 'Gagal menghapus mobil: Mobil ini masih terhubung dengan riwayat booking.');
        }
    }

    redirect('cars.php');
}

$title       = 'Data Mobil - Monitoring Mobil Kantor';
$page_title  = 'Armada Mobil';
$page_subtitle = 'Kelola armada, plat nomor, kapasitas, KM, dan status kendaraan kantor.';
$cars = db()->query('SELECT * FROM cars ORDER BY name')->fetchAll();

function carStatusBadge(string $s): string {
    return match($s) {
        'available'   => 'badge-success',
        'maintenance' => 'badge-warning',
        'used'        => 'badge-info',
        'inactive'    => 'badge-muted',
        default       => 'badge-muted'
    };
}
function carStatusLabel(string $s): string {
    return match($s) {
        'available'   => 'Tersedia',
        'maintenance' => 'Perawatan',
        'used'        => 'Dipakai',
        'inactive'    => 'Nonaktif',
        default       => ucfirst($s)
    };
}
function carStatusIcon(string $s): string {
    return match($s) {
        'available'   => '🟢',
        'maintenance' => '🟡',
        'used'        => '🔵',
        'inactive'    => '⚫',
        default       => '⚪'
    };
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section>

    <div class="card" style="margin-bottom:20px">
        <div class="calendar-toolbar">
            <div>
                <h2>Daftar Armada</h2>
                <p class="text-muted"><?= count($cars) ?> kendaraan terdaftar</p>
            </div>
            <button class="btn btn-primary" onclick="openModal('carAddModal')">+ Tambah Mobil</button>
        </div>
        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Kendaraan</th>
                        <th>Plat</th>
                        <th>Kapasitas</th>
                        <th>KM Terakhir</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($cars as $car): ?>
                    <tr>
                        <td>
                            <strong><?= e($car['name']) ?></strong>
                            <?php if (!empty($car['notes'])): ?>
                            <br><small class="text-muted"><?= e($car['notes']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><span class="plate-tag"><?= e($car['plate_number']) ?></span></td>
                        <td><?= e($car['capacity']) ?> org</td>
                        <td><?= e(number_format($car['last_km'])) ?> km</td>
                        <td>
                            <span class="badge <?= carStatusBadge($car['status']) ?>">
                                <?= carStatusIcon($car['status']) ?> <?= carStatusLabel($car['status']) ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap">
                            <div class="actions" style="flex-wrap:nowrap">
                                <button class="btn btn-sm btn-primary"
                                    onclick="openStatusModal(<?= (int)$car['id'] ?>, '<?= e($car['name']) ?>', '<?= e($car['status']) ?>')">
                                    Status
                                </button>
                                <button class="btn btn-sm btn-outline"
                                    onclick="openEditModal(<?= (int)$car['id'] ?>, '<?= e(addslashes($car['name'])) ?>', '<?= e($car['plate_number']) ?>', <?= (int)$car['capacity'] ?>, <?= (int)$car['last_km'] ?>, '<?= e($car['status']) ?>', '<?= e(addslashes($car['notes'] ?? '')) ?>')">
                                    Edit
                                </button>
                                 <form method="post" style="display:inline" id="deleteCarForm<?= (int)$car['id'] ?>">
                                     <input type="hidden" name="action" value="delete">
                                     <input type="hidden" name="id" value="<?= (int)$car['id'] ?>">
                                     <button class="btn btn-sm btn-danger" type="button" onclick="openDeleteCarModal(<?= (int)$car['id'] ?>, '<?= e(addslashes($car['name'])) ?>', '<?= e(addslashes($car['plate_number'])) ?>')">Hapus</button>
                                 </form>
                             </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$cars): ?>
                    <tr><td colspan="6" style="text-align:center;color:var(--muted);padding:32px">Belum ada data kendaraan.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="cars-bottom-grid">

        <div class="card car-summary-card">
            <h2>Ringkasan Status</h2>
            <?php
            $summary = ['available' => 0, 'used' => 0, 'maintenance' => 0, 'inactive' => 0];
            foreach ($cars as $c) {
                $k = $c['status'];
                if (isset($summary[$k])) $summary[$k]++;
                else $summary['inactive']++;
            }
            $total = max(1, count($cars));
            ?>
            <div class="status-list" style="margin-top:12px">
                <div class="status-row">
                    <span>🟢 Tersedia</span>
                    <strong><?= $summary['available'] ?> unit</strong>
                    <div class="progress success"><span style="width:<?= round($summary['available']/$total*100) ?>%"></span></div>
                </div>
                <div class="status-row">
                    <span>🔵 Dipakai</span>
                    <strong><?= $summary['used'] ?> unit</strong>
                    <div class="progress"><span style="width:<?= round($summary['used']/$total*100) ?>%"></span></div>
                </div>
                <div class="status-row">
                    <span>🟡 Perawatan</span>
                    <strong><?= $summary['maintenance'] ?> unit</strong>
                    <div class="progress warning"><span style="width:<?= round($summary['maintenance']/$total*100) ?>%"></span></div>
                </div>
                <div class="status-row">
                    <span>⚫ Nonaktif</span>
                    <strong><?= $summary['inactive'] ?> unit</strong>
                    <div class="progress danger"><span style="width:<?= round($summary['inactive']/$total*100) ?>%"></span></div>
                </div>
            </div>
            <div class="utilization">
                <span class="text-muted">Utilisasi Armada</span>
                <strong><?= round(($summary['used'] + $summary['available']) / $total * 100) ?>%</strong>
                <br><span class="text-muted">operasional</span>
            </div>
        </div>

        <div class="card">
            <h2>Info Cepat</h2>
            <div style="display:flex;flex-direction:column;gap:10px;margin-top:12px">
            <?php foreach ($cars as $car): ?>
                <div class="car-info-row">
                    <div class="car-info-icon">🚗</div>
                    <div style="flex:1;min-width:0">
                        <div style="font-weight:700;font-size:14px;color:#0f172a"><?= e($car['name']) ?></div>
                        <div style="font-size:12px;color:var(--muted)"><?= e($car['plate_number']) ?> · <?= e($car['capacity']) ?> org · <?= e(number_format($car['last_km'])) ?> km</div>
                    </div>
                    <span class="badge <?= carStatusBadge($car['status']) ?>"><?= carStatusLabel($car['status']) ?></span>
                </div>
            <?php endforeach; ?>
            </div>
        </div>

    </div>
</section>

<div class="modal-backdrop" id="carAddModal">
    <div class="modal">
        <div class="modal-header">
            <h2>🚗 Tambah Kendaraan Baru</h2>
            <button class="close" onclick="closeModal('carAddModal')">✕</button>
        </div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Nama Kendaraan</label><input class="input" name="name" placeholder="Contoh: Toyota Avanza" required></div>
            <div class="form-group"><label>Plat Nomor</label><input class="input" name="plate_number" placeholder="Contoh: B 1234 KTR" required></div>
            <div class="form-group"><label>Kapasitas Penumpang</label><input class="input" type="number" name="capacity" value="4" min="1" required></div>
            <div class="form-group"><label>KM Awal</label><input class="input" type="number" name="last_km" value="0" min="0" required></div>
            <div class="form-group"><label>Status Awal</label>
                <select name="status">
                    <option value="available">🟢 Tersedia</option>
                    <option value="maintenance">🟡 Perawatan</option>
                    <option value="inactive">⚫ Nonaktif</option>
                </select>
            </div>
            <div class="form-group full"><label>Catatan (opsional)</label><textarea name="notes" placeholder="Contoh: Perlu servis berkala setiap 5.000 km"></textarea></div>
            <div class="full actions">
                <button class="btn btn-primary" type="submit">Simpan Kendaraan</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('carAddModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="carEditModal">
    <div class="modal">
        <div class="modal-header">
            <h2>✏️ Edit Data Kendaraan</h2>
            <button class="close" onclick="closeModal('carEditModal')">✕</button>
        </div>
        <form method="post" class="form-grid" id="carEditForm">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editCarId">
            <div class="form-group"><label>Nama Kendaraan</label><input class="input" name="name" id="editCarName" required></div>
            <div class="form-group"><label>Plat Nomor</label><input class="input" name="plate_number" id="editCarPlate" required></div>
            <div class="form-group"><label>Kapasitas Penumpang</label><input class="input" type="number" name="capacity" id="editCarCapacity" min="1" required></div>
            <div class="form-group"><label>KM Terakhir</label><input class="input" type="number" name="last_km" id="editCarKm" min="0" required></div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="editCarStatus">
                    <option value="available">🟢 Tersedia</option>
                    <option value="used">🔵 Dipakai</option>
                    <option value="maintenance">🟡 Perawatan</option>
                    <option value="inactive">⚫ Nonaktif</option>
                </select>
            </div>
            <div class="form-group full"><label>Catatan</label><textarea name="notes" id="editCarNotes"></textarea></div>
            <div class="full actions">
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('carEditModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="carStatusModal">
    <div class="modal" style="max-width:420px">
        <div class="modal-header">
            <h2>⚡ Ubah Status Kendaraan</h2>
            <button class="close" onclick="closeModal('carStatusModal')">✕</button>
        </div>
        <form method="post">
            <input type="hidden" name="action" value="status">
            <input type="hidden" name="id" id="statusCarId">
            <p style="color:var(--muted);margin:0 0 20px">Kendaraan: <strong id="statusCarName"></strong></p>
            <div class="form-group">
                <label>Pilih Status Baru</label>
                <div class="status-btn-grid">
                    <label class="status-btn-option available">
                        <input type="radio" name="status" value="available"> 🟢 Tersedia
                    </label>
                    <label class="status-btn-option used">
                        <input type="radio" name="status" value="used"> 🔵 Sedang Dipakai
                    </label>
                    <label class="status-btn-option maintenance">
                        <input type="radio" name="status" value="maintenance"> 🟡 Perawatan
                    </label>
                    <label class="status-btn-option inactive">
                        <input type="radio" name="status" value="inactive"> ⚫ Nonaktif
                    </label>
                </div>
            </div>
            <div class="actions" style="margin-top:20px">
                <button class="btn btn-primary" type="submit">Simpan Status</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('carStatusModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

<script>
function openEditModal(id, name, plate, capacity, km, status, notes) {
    document.getElementById('editCarId').value = id;
    document.getElementById('editCarName').value = name;
    document.getElementById('editCarPlate').value = plate;
    document.getElementById('editCarCapacity').value = capacity;
    document.getElementById('editCarKm').value = km;
    document.getElementById('editCarStatus').value = status;
    document.getElementById('editCarNotes').value = notes;
    openModal('carEditModal');
}
function openStatusModal(id, name, currentStatus) {
    document.getElementById('statusCarId').value = id;
    document.getElementById('statusCarName').textContent = name;
    const radios = document.querySelectorAll('#carStatusModal input[name="status"]');
    radios.forEach(r => { r.checked = (r.value === currentStatus); });
    document.querySelectorAll('.status-btn-option').forEach(el => el.classList.remove('selected'));
    const cur = document.querySelector(`#carStatusModal input[value="${currentStatus}"]`);
    if (cur) cur.closest('.status-btn-option').classList.add('selected');
    openModal('carStatusModal');
}
document.querySelectorAll('.status-btn-option input').forEach(r => {
    r.addEventListener('change', function() {
        document.querySelectorAll('.status-btn-option').forEach(el => el.classList.remove('selected'));
        this.closest('.status-btn-option').classList.add('selected');
    });
});
</script>

<div class="modal-backdrop" id="deleteCarModal">
    <div class="modal modal-confirm">
        <div class="modal-confirm-icon">
            <span class="icon-circle danger">⚠️</span>
        </div>
        <h2>Hapus Armada Mobil</h2>
        <p class="text-muted" style="font-size:13px;line-height:1.5;margin-bottom:14px">
            Apakah Anda yakin ingin menghapus mobil ini dari armada kantor?
        </p>
        <div class="confirm-summary-box">
            <div class="summary-row">
                <span class="label">Nama Mobil</span>
                <strong class="val" id="modalDeleteCarName">-</strong>
            </div>
            <div class="summary-row">
                <span class="label">Plat Nomor</span>
                <strong class="val" id="modalDeleteCarPlate">-</strong>
            </div>
        </div>
        <div class="actions" style="margin-top:20px;display:flex;gap:10px">
            <button class="btn btn-outline" type="button" style="flex:1" onclick="closeModal('deleteCarModal')">Batal</button>
            <button class="btn btn-danger" type="button" id="modalDeleteCarSubmitBtn" style="flex:1">Ya, Hapus Mobil</button>
        </div>
    </div>
</div>

<script>
let activeDeleteCarFormId = null;
function openDeleteCarModal(id, name, plate) {
    activeDeleteCarFormId = 'deleteCarForm' + id;
    document.getElementById('modalDeleteCarName').innerText = name;
    document.getElementById('modalDeleteCarPlate').innerText = plate;
    openModal('deleteCarModal');
}
document.getElementById('modalDeleteCarSubmitBtn')?.addEventListener('click', function() {
    if (activeDeleteCarFormId) document.getElementById(activeDeleteCarFormId)?.submit();
});
</script>
