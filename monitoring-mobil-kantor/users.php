node -v<?php
require_once __DIR__ . '/includes/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token($_POST['csrf_token'] ?? null);
    $action = $_POST['action'] ?? 'add';

    if ($action === 'add') {
        $password = password_hash($_POST['password'] ?: 'user123', PASSWORD_DEFAULT);
        $stmt = db()->prepare('INSERT INTO users (name, email, password, role, department, phone, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([
            trim($_POST['name'] ?? ''), trim($_POST['email'] ?? ''), $password,
            $_POST['role'] ?? 'user', trim($_POST['department'] ?? ''),
            trim($_POST['phone'] ?? ''), $_POST['status'] ?? 'active'
        ]);
        flash('success', 'User berhasil ditambahkan.');
    } elseif ($action === 'edit') {
        $stmt = db()->prepare('UPDATE users SET name=?, email=?, role=?, department=?, phone=?, status=? WHERE id=?');
        $stmt->execute([
            trim($_POST['name'] ?? ''), trim($_POST['email'] ?? ''),
            $_POST['role'] ?? 'user', trim($_POST['department'] ?? ''),
            trim($_POST['phone'] ?? ''), $_POST['status'] ?? 'active',
            (int)($_POST['id'] ?? 0)
        ]);
        flash('success', 'Data user berhasil diperbarui.');
    }
    redirect('users.php');
}

$title         = 'Pengaturan User - Monitoring Mobil Kantor';
$page_title    = 'Manajemen User';
$page_subtitle = 'Kelola akun admin dan pengguna yang dapat mengakses sistem.';
$users = db()->query('SELECT id, name, email, role, department, phone, status, created_at FROM users ORDER BY role DESC, name')->fetchAll();

function userInitial(string $name): string {
    $parts = explode(' ', trim($name));
    return strtoupper(substr($parts[0], 0, 1) . (isset($parts[1]) ? substr($parts[1], 0, 1) : ''));
}
function userRoleGradient(string $role): string {
    return $role === 'admin'
        ? 'linear-gradient(135deg,#7c3aed,#5b21b6)'
        : 'linear-gradient(135deg,#2563ff,#1240a8)';
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<?php
$totalUsers  = count($users);
$adminCount  = count(array_filter($users, fn($u) => $u['role'] === 'admin'));
$userCount   = $totalUsers - $adminCount;
$activeCount = count(array_filter($users, fn($u) => $u['status'] === 'active'));
?>
<section class="grid grid-4" style="margin-bottom:20px">
    <div class="card stat card-blue">
        <div><span>Total User</span><strong><?= $totalUsers ?></strong><small>terdaftar</small></div>
        <div class="icon">👥</div>
    </div>
    <div class="card stat card-purple">
        <div><span>Administrator</span><strong><?= $adminCount ?></strong><small>akses penuh</small></div>
        <div class="icon">🛡️</div>
    </div>
    <div class="card stat card-blue">
        <div><span>Pengguna</span><strong><?= $userCount ?></strong><small>akses terbatas</small></div>
        <div class="icon">👤</div>
    </div>
    <div class="card stat card-green">
        <div><span>Aktif</span><strong><?= $activeCount ?></strong><small>bisa login</small></div>
        <div class="icon">✅</div>
    </div>
</section>

<section class="card">
    <div class="calendar-toolbar">
        <div>
            <h2>Daftar User</h2>
            <p class="text-muted"><?= $totalUsers ?> akun terdaftar dalam sistem</p>
        </div>
        <button class="btn btn-primary" onclick="openModal('userAddModal')">+ Tambah User</button>
    </div>

    <div class="user-grid">
    <?php foreach ($users as $u): ?>
        <div class="user-card">
            <div class="user-card-avatar" style="background:<?= userRoleGradient($u['role']) ?>">
                <?= e(userInitial($u['name'])) ?>
            </div>
            <div class="user-card-info">
                <div class="user-card-name"><?= e($u['name']) ?></div>
                <div class="user-card-email">✉ <?= e($u['email']) ?></div>
                <?php if ($u['phone']): ?><div class="user-card-email">📞 <?= e($u['phone']) ?></div><?php endif; ?>
                <?php if ($u['department']): ?><div class="user-card-email">🏢 <?= e($u['department']) ?></div><?php endif; ?>
                <div class="user-card-badges">
                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-primary' : 'badge-muted' ?>">
                        <?= $u['role'] === 'admin' ? '🛡️ Admin' : '👤 User' ?>
                    </span>
                    <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?>">
                        <?= $u['status'] === 'active' ? '🟢 Aktif' : '🔴 Nonaktif' ?>
                    </span>
                </div>
            </div>
            <button class="btn btn-sm btn-outline" style="align-self:flex-start;flex-shrink:0"
                onclick="openUserEdit(<?= (int)$u['id'] ?>,'<?= e(addslashes($u['name'])) ?>','<?= e($u['email']) ?>','<?= e($u['role']) ?>','<?= e(addslashes($u['department'] ?? '')) ?>','<?= e($u['phone'] ?? '') ?>','<?= e($u['status']) ?>')">
                ✏️ Edit
            </button>
        </div>
    <?php endforeach; ?>
    <?php if (!$users): ?>
        <div class="empty-state">
            <div style="font-size:48px;margin-bottom:12px">👤</div>
            <h3>Belum Ada User</h3>
            <p>Klik <strong>+ Tambah User</strong> untuk menambahkan akun pertama.</p>
        </div>
    <?php endif; ?>
    </div>
</section>

<div class="modal-backdrop" id="userAddModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h2>👤 Tambah User Baru</h2>
            <button class="close" onclick="closeModal('userAddModal')">✕</button>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="add">
            <div class="form-group"><label>Nama Lengkap</label><input class="input" name="name" placeholder="Nama user" required></div>
            <div class="form-group"><label>Email</label><input class="input" type="email" name="email" placeholder="email@kantor.com" required></div>
            <div class="form-group"><label>Password</label><input class="input" name="password" value="user123" placeholder="Min. 6 karakter"></div>
            <div class="form-group"><label>Role Akses</label>
                <select name="role">
                    <option value="user">👤 Pengguna</option>
                    <option value="admin">🛡️ Administrator</option>
                </select>
            </div>
            <div class="form-group"><label>Bidang (Departemen)</label>
                <select name="department">
                    <option value="">-- Pilih Bidang --</option>
                    <option value="Dukungan Bisnis">Dukungan Bisnis</option>
                    <option value="Penjualan dan Dukungan Operasi">Penjualan dan Dukungan Operasi</option>
                    <option value="Bidang Inspeksi Umum">Bidang Inspeksi Umum</option>
                    <option value="Bidang Inspeksi Teknik">Bidang Inspeksi Teknik</option>
                    <option value="Bidang Inspeksi dan Pengujian">Bidang Inspeksi dan Pengujian</option>
                </select>
            </div>
            <div class="form-group"><label>No HP</label><input class="input" name="phone" placeholder="0812-xxxx-xxxx"></div>
            <div class="form-group"><label>Status</label>
                <select name="status">
                    <option value="active">🟢 Aktif</option>
                    <option value="inactive">🔴 Nonaktif</option>
                </select>
            </div>
            <div class="full actions">
                <button class="btn btn-primary" type="submit">Simpan User</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('userAddModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-backdrop" id="userEditModal">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <h2>✏️ Edit User</h2>
            <button class="close" onclick="closeModal('userEditModal')">✕</button>
        </div>
        <form method="post" class="form-grid">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editUserId">
            <div class="form-group"><label>Nama Lengkap</label><input class="input" name="name" id="editUserName" required></div>
            <div class="form-group"><label>Email</label><input class="input" type="email" name="email" id="editUserEmail" required></div>
            <div class="form-group"><label>Role Akses</label>
                <select name="role" id="editUserRole">
                    <option value="user">👤 Pengguna</option>
                    <option value="admin">🛡️ Administrator</option>
                </select>
            </div>
            <div class="form-group"><label>Bidang (Departemen)</label>
                <select name="department" id="editUserDept">
                    <option value="">-- Pilih Bidang --</option>
                    <option value="Dukungan Bisnis">Dukungan Bisnis</option>
                    <option value="Penjualan dan Dukungan Operasi">Penjualan dan Dukungan Operasi</option>
                    <option value="Bidang Inspeksi Umum">Bidang Inspeksi Umum</option>
                    <option value="Bidang Inspeksi Teknik">Bidang Inspeksi Teknik</option>
                    <option value="Bidang Inspeksi dan Pengujian">Bidang Inspeksi dan Pengujian</option>
                </select>
            </div>
            <div class="form-group"><label>No HP</label><input class="input" name="phone" id="editUserPhone"></div>
            <div class="form-group"><label>Status</label>
                <select name="status" id="editUserStatus">
                    <option value="active">🟢 Aktif</option>
                    <option value="inactive">🔴 Nonaktif</option>
                </select>
            </div>
            <div class="full actions">
                <button class="btn btn-primary" type="submit">Simpan Perubahan</button>
                <button class="btn btn-outline" type="button" onclick="closeModal('userEditModal')">Batal</button>
            </div>
        </form>
    </div>
</div>

</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

<script>
function openUserEdit(id, name, email, role, dept, phone, status) {
    document.getElementById('editUserId').value     = id;
    document.getElementById('editUserName').value   = name;
    document.getElementById('editUserEmail').value  = email;
    document.getElementById('editUserRole').value   = role;
    document.getElementById('editUserDept').value   = dept;
    document.getElementById('editUserPhone').value  = phone;
    document.getElementById('editUserStatus').value = status;
    openModal('userEditModal');
}
</script>
