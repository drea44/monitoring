<?php
$allNotifs = [];
$user = current_user();
$userId = (int)($user['id'] ?? 0);

try {
    if (is_admin()) {
        $sum = budget_summary();
        $sisa = (float)($sum['dropping'] ?? 0) - (float)($sum['allowance'] ?? 0);
        if ($sisa < 10000000) {
            $allNotifs[] = [
                'id' => 'budget_warning_' . date('Y-m-d'),
                'type' => 'budget_warning',
                'title' => 'Sisa Anggaran Menipis!',
                'message' => 'Sisa anggaran tinggal ' . rupiah($sisa) . '. Segera dropping dana baru.',
                'link' => base_path('budget.php'),
                'time' => date('Y-m-d'),
                'badge_class' => 'badge-danger',
                'badge_label' => 'Critical',
                'icon' => '🚨'
            ];
        }
    }

    $todayBookings = db()->query(
        "SELECT b.id, b.code, b.destination, u.name requester, b.status 
         FROM bookings b 
         JOIN users u ON u.id = b.user_id 
         WHERE b.date = CURDATE() AND b.status IN ('approved', 'running', 'completed')"
    )->fetchAll();
    foreach ($todayBookings as $tb) {
        $statusLbl = match($tb['status']) {
            'approved' => 'Disetujui',
            'running' => 'Berjalan',
            'completed' => 'Selesai',
            default => 'Aktif'
        };
        $allNotifs[] = [
            'id' => 'booking_today_' . $tb['id'],
            'type' => 'booking_today',
            'title' => 'Booking Hari Ini: ' . $tb['code'],
            'message' => 'Tujuan ' . $tb['destination'] . ' oleh ' . $tb['requester'] . ' (' . $statusLbl . ')',
            'link' => base_path('booking_detail.php?id=' . $tb['id']),
            'time' => date('Y-m-d'),
            'badge_class' => 'badge-info',
            'badge_label' => 'Hari Ini',
            'icon' => '📅'
        ];
    }

    if (is_admin()) {
        $recentDrops = db()->query(
            "SELECT id, amount, reference_no, entry_date 
             FROM budget_entries 
             WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY) 
             ORDER BY created_at DESC 
             LIMIT 10"
        )->fetchAll();
        foreach ($recentDrops as $rd) {
            $allNotifs[] = [
                'id' => 'budget_drop_' . $rd['id'],
                'type' => 'budget_drop',
                'title' => 'Dropping Anggaran Masuk',
                'message' => 'Dana masuk ' . rupiah($rd['amount']) . ' (' . $rd['reference_no'] . ')',
                'link' => base_path('budget.php'),
                'time' => $rd['entry_date'],
                'badge_class' => 'badge-success',
                'badge_label' => 'Dropping',
                'icon' => '📥'
            ];
        }
    }

    $pendingBookings = db()->query(
        "SELECT b.id, b.code, b.destination, u.name requester, b.date
         FROM bookings b
         JOIN users u ON u.id = b.user_id
         WHERE b.status = 'pending'
         ORDER BY b.created_at DESC"
    )->fetchAll();
    foreach ($pendingBookings as $pb) {
        $allNotifs[] = [
            'id' => 'booking_pending_' . $pb['id'],
            'type' => 'booking_pending',
            'title' => 'Pengajuan Booking Baru',
            'message' => $pb['code'] . ' tujuan ' . $pb['destination'] . ' oleh ' . $pb['requester'],
            'link' => base_path('booking_detail.php?id=' . $pb['id']),
            'time' => $pb['date'],
            'badge_class' => 'badge-warning',
            'badge_label' => 'Pending',
            'icon' => '⏳'
        ];
    }

    if (is_admin()) {
        $maintenanceCars = db()->query("SELECT name, plate_number FROM cars WHERE status = 'maintenance'")->fetchAll();
        foreach ($maintenanceCars as $mc) {
            $allNotifs[] = [
                'id' => 'car_maintenance_' . preg_replace('/[^a-zA-Z0-9]/', '', $mc['plate_number']),
                'type' => 'car_maintenance',
                'title' => 'Mobil Perlu Perawatan',
                'message' => $mc['name'] . ' (' . $mc['plate_number'] . ') berstatus perawatan',
                'link' => base_path('cars.php'),
                'time' => date('Y-m-d'),
                'badge_class' => 'badge-muted',
                'badge_label' => 'Perawatan',
                'icon' => '🔧'
            ];
        }

        $leaveDrivers = db()->query("SELECT name FROM drivers WHERE status = 'leave'")->fetchAll();
        foreach ($leaveDrivers as $ld) {
            $allNotifs[] = [
                'id' => 'driver_leave_' . preg_replace('/[^a-zA-Z0-9]/', '', $ld['name']),
                'type' => 'driver_leave',
                'title' => 'Driver Sedang Cuti/Izin',
                'message' => $ld['name'] . ' sedang tidak tersedia',
                'link' => base_path('drivers.php'),
                'time' => date('Y-m-d'),
                'badge_class' => 'badge-muted',
                'badge_label' => 'Cuti',
                'icon' => '👤'
            ];
        }
    }
} catch (Throwable $e) {}

$typePriority = [
    'budget_warning' => 1,
    'booking_pending' => 2,
    'booking_today' => 3,
    'budget_drop' => 4,
    'car_maintenance' => 5,
    'driver_leave' => 5
];

usort($allNotifs, function($a, $b) use ($typePriority) {
    $pA = $typePriority[$a['type']] ?? 99;
    $pB = $typePriority[$b['type']] ?? 99;
    if ($pA !== $pB) {
        return $pA <=> $pB;
    }
    return strcmp($b['time'], $a['time']);
});

$dismissedKeys = [];
if ($userId > 0) {
    try {
        $dismissedKeys = db()->query("SELECT notif_key FROM notification_dismissals WHERE user_id = $userId")->fetchAll(PDO::FETCH_COLUMN) ?: [];
    } catch (Throwable $e) { $dismissedKeys = []; }
}

$activeNotifs = array_values(array_filter($allNotifs, function($n) use ($dismissedKeys) {
    $key = md5($n['id']);
    return !in_array($key, $dismissedKeys, true);
}));

$displayNotifs = array_slice($activeNotifs, 0, 5);
$hasNotif = count($displayNotifs) > 0;
?>
<header class="topbar">
    <div class="topbar-left">
        <h1><?= e($page_title ?? 'Dashboard') ?></h1>
        <p><?= e($page_subtitle ?? 'Sistem monitoring penggunaan mobil kantor') ?></p>
    </div>
    <div class="topbar-actions">
        <div class="topbar-dropdown-wrap" id="notifWrap">
            <button class="notif-btn" id="notifBtn" title="Notifikasi" aria-label="Notifikasi"
                    onclick="toggleTopbarDropdown('notifDropdown')">
                <i class="ph-duotone ph-bell"></i>
                <?php if ($hasNotif): ?>
                    <span class="notif-badge"></span>
                <?php endif; ?>
            </button>
            <div class="topbar-dropdown topbar-dropdown-right" id="notifDropdown">
                <div class="topbar-dropdown-header">
                    <strong>Notifikasi</strong>
                    <div style="display:flex;align-items:center;gap:8px">
                        <?php if ($hasNotif): ?>
                            <span class="badge badge-danger" id="notifCountBadge"><?= count($activeNotifs) ?> baru</span>
                        <?php endif; ?>
                        <?php if ($hasNotif): ?>
                            <button onclick="dismissAllNotifs(event)" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:11.5px;font-weight:700;padding:2px 6px;border-radius:6px;transition:color .15s" onmouseover="this.style.color='var(--red)'" onmouseout="this.style.color='var(--muted)'">Hapus Semua</button>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($displayNotifs): ?>
                    <?php foreach ($displayNotifs as $i => $n):
                        $notifKey = md5($n['id']);
                    ?>
                        <div class="notif-item" data-notif-key="<?= e($notifKey) ?>">
                            <a href="<?= e($n['link']) ?>" style="display:flex;align-items:center;gap:0;flex:1;min-width:0;text-decoration:none;color:inherit">
                                <div style="font-size:16px;margin-right:10px;display:flex;align-items:center;justify-content:center;width:30px;height:30px;background:var(--bg);border-radius:8px;flex-shrink:0"><?= $n['icon'] ?></div>
                                <div class="notif-item-body">
                                    <div class="notif-item-title"><?= e($n['title']) ?></div>
                                    <div class="notif-item-sub"><?= e($n['message']) ?></div>
                                </div>
                                <span class="badge <?= e($n['badge_class']) ?>" style="flex-shrink:0;margin-right:6px"><?= e($n['badge_label']) ?></span>
                            </a>
                            <button onclick="dismissNotif(event, '<?= e($notifKey) ?>')" title="Hapus notifikasi"
                                style="background:none;border:none;cursor:pointer;color:var(--muted-2);font-size:15px;line-height:1;padding:4px;border-radius:6px;flex-shrink:0;transition:color .15s,background .15s"
                                onmouseover="this.style.color='var(--red)';this.style.background='var(--red-light)'"
                                onmouseout="this.style.color='var(--muted-2)';this.style.background='none'">✕</button>
                        </div>
                    <?php endforeach; ?>
                    <div id="notifEmpty" class="notif-empty" style="display:none">
                        <span>🔔</span>
                        <p>Tidak ada notifikasi baru</p>
                    </div>
                    <a href="<?= e(base_path('dashboard.php')) ?>" class="notif-footer" id="notifFooter">
                        Lihat ringkasan dashboard →
                    </a>
                <?php else: ?>
                    <div class="notif-empty">
                        <span>🔔</span>
                        <p>Tidak ada notifikasi baru</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="topbar-dropdown-wrap" id="profileWrap">
            <div class="user-pill" id="profileBtn"
                 onclick="toggleTopbarDropdown('profileDropdown')" style="cursor:pointer">
                <div class="user-avatar"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></div>
                <div class="user-info">
                    <span><?= e($user['name'] ?? 'User') ?></span>
                    <strong><?= e(($user['role'] ?? '-') === 'admin' ? 'Super Admin' : 'Pengguna') ?></strong>
                </div>
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24"
                     fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"
                     stroke-linejoin="round" style="color:var(--muted);margin-left:4px">
                    <polyline points="6 9 12 15 18 9"></polyline>
                </svg>
            </div>
            <div class="topbar-dropdown topbar-dropdown-right" id="profileDropdown">
                <div class="topbar-dropdown-header">
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="user-avatar" style="width:36px;height:36px;font-size:13px"><?= e(strtoupper(substr($user['name'] ?? 'U', 0, 1))) ?></div>
                        <div>
                            <div style="font-weight:800;font-size:13.5px"><?= e($user['name'] ?? '-') ?></div>
                            <div style="font-size:11.5px;color:var(--muted)"><?= e($user['email'] ?? '-') ?></div>
                        </div>
                    </div>
                </div>
                <div class="profile-menu">
                    <a href="<?= e(base_path('users.php')) ?>" class="profile-menu-item">
                        <i class="ph-duotone ph-user-gear"></i> Manajemen User
                    </a>
                    <a href="<?= e(base_path('dashboard.php')) ?>" class="profile-menu-item">
                        <i class="ph-duotone ph-gauge"></i> Dashboard
                    </a>
                    <div class="profile-menu-divider"></div>
                    <a href="<?= e(base_path('logout.php')) ?>" class="profile-menu-item profile-menu-danger">
                        <i class="ph-duotone ph-sign-out"></i> Logout
                    </a>
                </div>
            </div>
        </div>

    </div>
</header>

<?php if ($flash = flash()): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?php if ($flash['type'] === 'success'): ?>✅<?php elseif ($flash['type'] === 'danger'): ?>❌<?php else: ?>⚠️<?php endif; ?>
        <?= e($flash['message']) ?>
    </div>
<?php endif; ?>

<script>
const DISMISS_API_URL = '<?= base_path("api/dismiss_notif.php") ?>';

function updateNotifBadges() {
    const items = document.querySelectorAll('.notif-item[data-notif-key]');
    let visibleCount = 0;
    items.forEach(item => {
        if (item.style.display !== 'none') visibleCount++;
    });
    const badge = document.getElementById('notifCountBadge');
    const bellBadge = document.querySelector('#notifBtn .notif-badge');
    const emptyEl = document.getElementById('notifEmpty');
    const footerEl = document.getElementById('notifFooter');
    if (badge) badge.textContent = visibleCount + ' baru';
    if (visibleCount === 0) {
        if (badge) badge.style.display = 'none';
        if (bellBadge) bellBadge.style.display = 'none';
        if (emptyEl) emptyEl.style.display = 'flex';
        if (footerEl) footerEl.style.display = 'none';
    } else {
        if (badge) badge.style.display = '';
        if (bellBadge) bellBadge.style.display = '';
        if (emptyEl) emptyEl.style.display = 'none';
        if (footerEl) footerEl.style.display = '';
    }
}

function dismissNotif(e, key) {
    e.preventDefault();
    e.stopPropagation();
    const item = document.querySelector('[data-notif-key="' + key + '"]');
    if (item) {
        item.style.transition = 'opacity .2s, transform .2s';
        item.style.opacity = '0';
        item.style.transform = 'translateX(8px)';
        setTimeout(() => {
            item.style.display = 'none';
            updateNotifBadges();
        }, 200);
    }
    fetch(DISMISS_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ key: key })
    }).catch(err => console.error('Dismiss error:', err));
}

function dismissAllNotifs(e) {
    e.stopPropagation();
    const items = document.querySelectorAll('.notif-item[data-notif-key]');
    const allKeys = [];
    items.forEach(item => {
        allKeys.push(item.dataset.notifKey);
        item.style.transition = 'opacity .18s';
        item.style.opacity = '0';
        setTimeout(() => { item.style.display = 'none'; }, 200);
    });
    setTimeout(updateNotifBadges, 220);

    fetch(DISMISS_API_URL, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ all_keys: allKeys })
    }).catch(err => console.error('Dismiss all error:', err));
}

function toggleTopbarDropdown(id) {
    const all = document.querySelectorAll('.topbar-dropdown');
    all.forEach(d => {
        if (d.id !== id) d.classList.remove('open');
    });
    document.getElementById(id).classList.toggle('open');
}
document.addEventListener('click', function(e) {
    if (!e.target.closest('.topbar-dropdown-wrap')) {
        document.querySelectorAll('.topbar-dropdown').forEach(d => d.classList.remove('open'));
    }
});
</script>
