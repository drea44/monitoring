<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$title        = 'Riwayat Booking - Monitoring Mobil Kantor';
$page_title   = 'Riwayat Booking';
$page_subtitle = is_admin() ? 'Semua riwayat booking pengguna dalam sistem.' : 'Riwayat booking yang pernah Anda ajukan.';

$filterStatus = $_GET['status'] ?? '';
$allowedStatus = ['pending','approved','running','completed','rejected'];
if (!in_array($filterStatus, $allowedStatus)) $filterStatus = '';

$keyword = trim($_GET['q'] ?? '');

$params = [];
$sql = "SELECT b.*, u.name requester, c.name car_name, d.name driver_name
        FROM bookings b
        JOIN users u ON u.id = b.user_id
        LEFT JOIN cars c ON c.id = b.car_id
        LEFT JOIN drivers d ON d.id = b.driver_id";
$where = [];
if (!is_admin()) { $where[] = 'b.user_id = ?'; $params[] = current_user()['id']; }
if ($filterStatus) { $where[] = 'b.status = ?'; $params[] = $filterStatus; }
if ($keyword)      { $where[] = '(b.code LIKE ? OR b.destination LIKE ? OR u.name LIKE ?)'; for($i=0;$i<3;$i++) $params[]='%'.$keyword.'%'; }
if ($where) $sql .= ' WHERE ' . implode(' AND ', $where);
$sql .= ' ORDER BY b.date DESC, b.start_time DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

$all = db()->query(
    (!is_admin() ? "SELECT status FROM bookings WHERE user_id=" . (int)current_user()['id'] : "SELECT status FROM bookings")
)->fetchAll(\PDO::FETCH_COLUMN);
$counts = array_count_values($all);
$totalAll = count($all);

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section class="grid grid-5" style="margin-bottom:22px">
    <div class="card stat card-blue" style="cursor:pointer" onclick="location='<?= e(base_path('history.php')) ?>'">
        <div>
            <span>Semua</span>
            <strong><?= $totalAll ?></strong>
            <small>total booking</small>
        </div>
        <div class="icon">📋</div>
    </div>
    <div class="card stat card-orange" style="cursor:pointer" onclick="location='<?= e(base_path('history.php?status=pending')) ?>'">
        <div>
            <span>Pending</span>
            <strong><?= $counts['pending'] ?? 0 ?></strong>
            <small>menunggu</small>
        </div>
        <div class="icon">⏳</div>
    </div>
    <div class="card stat card-blue" style="cursor:pointer;background:linear-gradient(135deg,#0ea5e9,#0284c7)" onclick="location='<?= e(base_path('history.php?status=approved')) ?>'">
        <div>
            <span>Disetujui</span>
            <strong><?= $counts['approved'] ?? 0 ?></strong>
            <small>approved</small>
        </div>
        <div class="icon">✅</div>
    </div>
    <div class="card stat card-green" style="cursor:pointer" onclick="location='<?= e(base_path('history.php?status=completed')) ?>'">
        <div>
            <span>Selesai</span>
            <strong><?= $counts['completed'] ?? 0 ?></strong>
            <small>completed</small>
        </div>
        <div class="icon">🏁</div>
    </div>
    <div class="card stat" style="cursor:pointer;background:linear-gradient(135deg,#ef4444,#b91c1c);box-shadow:0 12px 32px rgba(239,68,68,.38)" onclick="location='<?= e(base_path('history.php?status=rejected')) ?>'">
        <div>
            <span>Ditolak</span>
            <strong><?= $counts['rejected'] ?? 0 ?></strong>
            <small>rejected</small>
        </div>
        <div class="icon">❌</div>
    </div>
</section>

<section class="card">
    <div class="calendar-toolbar" style="margin-bottom:16px">
        <div>
            <h2>
                Daftar Booking
                <?php if ($filterStatus): ?>
                    <span class="badge <?= e(status_class($filterStatus)) ?>" style="font-size:13px;vertical-align:middle;margin-left:8px">
                        <?= e(status_label($filterStatus)) ?>
                    </span>
                <?php endif; ?>
            </h2>
            <p class="text-muted" style="margin-top:3px">
                <?= count($rows) ?> booking ditemukan
                <?php if ($filterStatus): ?>
                    &mdash; <a href="<?= e(base_path('history.php')) ?>" style="color:var(--blue);font-weight:700">Lihat semua</a>
                <?php endif; ?>
            </p>
        </div>
        <div class="actions">
            <form method="get" style="display:flex;gap:6px;align-items:center">
                <?php if ($filterStatus): ?>
                    <input type="hidden" name="status" value="<?= e($filterStatus) ?>">
                <?php endif; ?>
                <input class="input" name="q" value="<?= e($keyword) ?>"
                       placeholder="Cari kode, tujuan…"
                       style="width:180px;height:38px;font-size:13px">
                <button class="btn btn-outline btn-sm" type="submit" style="height:38px">Cari</button>
            </form>
            <?php if (is_admin()): ?>
                <a class="btn btn-primary" href="<?= e(base_path('booking.php')) ?>">+ Booking</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="history-tabs">
        <a href="<?= e(base_path('history.php'.($keyword?'?q='.urlencode($keyword):''))) ?>"
           class="history-tab <?= $filterStatus==='' ? 'active' : '' ?>">
            Semua <span class="tab-count"><?= $totalAll ?></span>
        </a>
        <?php foreach(['pending'=>'Pending','approved'=>'Disetujui','running'=>'Berjalan','completed'=>'Selesai','rejected'=>'Ditolak'] as $s=>$lbl): ?>
        <a href="<?= e(base_path('history.php?status='.$s.($keyword?'&q='.urlencode($keyword):''))) ?>"
           class="history-tab <?= $filterStatus===$s ? 'active' : '' ?>">
            <?= $lbl ?> <span class="tab-count"><?= $counts[$s] ?? 0 ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <div class="table-wrap">
        <table class="table">
            <thead>
                <tr>
                    <th>Kode</th>
                    <th>Periode</th>
                    <th>Pemesan</th>
                    <th>Tujuan</th>
                    <th>Mobil</th>
                    <th>Driver</th>
                    <th>Uang Jalan</th>
                    <th>Status</th>
                    <th style="text-align:center">Aksi</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach($rows as $row): ?>
                <tr>
                    <td>
                        <a href="<?= e(base_path('booking_detail.php?id='.$row['id'])) ?>"
                           style="font-weight:800;color:var(--blue);font-size:13px"><?= e($row['code']) ?></a>
                    </td>
                    <td style="white-space:nowrap">
                        <div style="font-size:13px;font-weight:600"><?= e(periode_tanggal($row['date'], $row['return_date'] ?? $row['date'])) ?></div>
                        <div style="font-size:11.5px;color:var(--muted)"><?= e(periode_jam($row['start_time'], $row['end_time'])) ?></div>
                    </td>
                    <td style="font-size:13px"><?= e($row['requester']) ?></td>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= e($row['destination']) ?></div>
                        <?php if (!empty($row['passengers'])): ?>
                            <div style="font-size:11.5px;color:var(--muted)">👥 <?= e($row['passengers']) ?> penumpang</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:600;font-size:13px"><?= e($row['car_name'] ?? '—') ?></div>
                    </td>
                    <td style="font-size:13px"><?= e($row['driver_name'] ?? '—') ?></td>
                    <td style="font-weight:700;color:<?= $row['allowance'] > 0 ? 'var(--orange)' : 'var(--muted-2)' ?>">
                        <?= $row['allowance'] > 0 ? e(rupiah($row['allowance'])) : '—' ?>
                    </td>
                    <td>
                        <span class="badge <?= e(status_class($row['status'])) ?>"><?= e(status_label($row['status'])) ?></span>
                    </td>
                    <td style="text-align:center">
                        <a class="btn btn-sm btn-outline"
                           href="<?= e(base_path('booking_detail.php?id='.$row['id'])) ?>">Detail</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if(!$rows): ?>
                <tr><td colspan="9" style="text-align:center;padding:48px;color:var(--muted)">
                    <div style="font-size:36px;margin-bottom:10px">📭</div>
                    <div style="font-weight:700;font-size:15px;color:var(--text-2)">Tidak Ada Booking</div>
                    <div style="font-size:13px;margin-top:6px">Belum ada booking yang sesuai filter.</div>
                </td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

<style>
.history-tabs {
    display: flex;
    gap: 0;
    border-bottom: 2px solid var(--border);
    margin-bottom: 0;
    overflow-x: auto;
    scrollbar-width: none
}
.history-tabs::-webkit-scrollbar { display: none }
.history-tab {
    padding: 10px 18px;
    font-size: 13px;
    font-weight: 700;
    color: var(--muted);
    text-decoration: none;
    border-bottom: 2.5px solid transparent;
    margin-bottom: -2px;
    white-space: nowrap;
    transition: color .15s, border-color .15s;
    display: flex;
    align-items: center;
    gap: 6px
}
.history-tab:hover { color: var(--text) }
.history-tab.active {
    color: var(--blue);
    border-bottom-color: var(--blue)
}
.tab-count {
    background: var(--surface-2);
    border-radius: 20px;
    padding: 1px 7px;
    font-size: 11px;
    font-weight: 800;
    color: var(--muted)
}
.history-tab.active .tab-count {
    background: var(--blue-light);
    color: var(--blue)
}

.grid-5 {
    grid-template-columns: repeat(5, 1fr)
}
@media (max-width: 1100px) {
    .grid-5 { grid-template-columns: repeat(3, 1fr) }
}
@media (max-width: 700px) {
    .grid-5 { grid-template-columns: repeat(2, 1fr) }
}
</style>
