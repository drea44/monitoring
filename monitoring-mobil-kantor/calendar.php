<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$title = 'Kalender Penggunaan - Monitoring Mobil Kantor';
$page_title = 'Kalender & Jadwal Penggunaan Mobil';
$page_subtitle = 'Melihat kalender penggunaan mobil serta matriks jadwal harian booking per tanggal.';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$firstDay = new DateTime($month . '-01');
$start = clone $firstDay;
$start->modify('monday this week');
$end = clone $start;
$end->modify('+41 days');

$params = [$end->format('Y-m-d'), $start->format('Y-m-d')];
$sql = "SELECT b.*, u.name requester, u.department, c.name car_name, c.plate_number, d.name driver_name,
               COALESCE((SELECT SUM(e.amount) FROM expenses e WHERE e.booking_id = b.id), 0) AS nota_total
        FROM bookings b
        JOIN users u ON u.id=b.user_id
        LEFT JOIN cars c ON c.id=b.car_id
        LEFT JOIN drivers d ON d.id=b.driver_id
        WHERE b.date <= ? AND COALESCE(b.return_date, b.date) >= ?";
if (!is_admin()) {
    $sql .= " AND b.user_id = ?";
    $params[] = current_user()['id'];
}
$sql .= " ORDER BY b.date, b.start_time";
$stmt = db()->prepare($sql);
$stmt->execute($params);

$rawBookings = $stmt->fetchAll();
$bookings = [];
$allMonthBookings = [];

foreach ($rawBookings as $b) {
    $b['return_date'] = $b['return_date'] ?: $b['date'];
    $allMonthBookings[] = $b;
    $bookingStart = new DateTime(max($b['date'], $start->format('Y-m-d')));
    $bookingEnd = new DateTime(min($b['return_date'], $end->format('Y-m-d')));
    $cursorBooking = clone $bookingStart;
    while ($cursorBooking <= $bookingEnd) {
        $key = $cursorBooking->format('Y-m-d');
        $bookings[$key][] = $b;
        $cursorBooking->modify('+1 day');
    }
}

$prev = (clone $firstDay)->modify('-1 month')->format('Y-m');
$next = (clone $firstDay)->modify('+1 month')->format('Y-m');
$days = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<section class="card">
    <div class="calendar-toolbar">
        <a class="btn btn-outline" href="?month=<?= e($prev) ?>">← Bulan Sebelumnya</a>
        <h2><?= e($firstDay->format('F Y')) ?></h2>
        <a class="btn btn-outline" href="?month=<?= e($next) ?>">Bulan Berikutnya →</a>
    </div>
    <div class="calendar">
        <?php foreach ($days as $day): ?><div class="day-name"><?= e($day) ?></div><?php endforeach; ?>
        <?php
        $cursor = clone $start;
        while ($cursor <= $end):
            $date = $cursor->format('Y-m-d');
            $muted = $cursor->format('Y-m') !== $month ? ' muted' : '';
        ?>
            <div class="day<?= $muted ?>" style="cursor:pointer" onclick="openDayBookingsModal('<?= $date ?>')">
                <div class="date-num"><?= e($cursor->format('d')) ?></div>
                <?php foreach (($bookings[$date] ?? []) as $booking): ?>
                    <?php
                        $isStartDay = $date === $booking['date'];
                        $isEndDay = $date === $booking['return_date'];
                        if ($isStartDay && $isEndDay) {
                            $timeLabel = substr($booking['start_time'],0,5) . '-' . substr($booking['end_time'],0,5);
                        } elseif ($isStartDay) {
                            $timeLabel = 'Mulai ' . substr($booking['start_time'],0,5);
                        } elseif ($isEndDay) {
                            $timeLabel = 'Selesai ' . substr($booking['end_time'],0,5);
                        } else {
                            $timeLabel = 'Seharian';
                        }
                    ?>
                    <button type="button" class="event <?= e($booking['status']) ?>" onclick="event.stopPropagation(); openDayBookingsModal('<?= $date ?>')">
                        <strong><?= e($timeLabel) ?> <?= e($booking['car_name'] ?? 'Mobil belum dipilih') ?></strong>
                        <span><?= e($booking['driver_name'] ?? 'Driver belum dipilih') ?> · <?= e($booking['destination']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php $cursor->modify('+1 day'); endwhile; ?>
    </div>
</section>

<section class="card" style="margin-top:24px">
    <div class="calendar-toolbar">
        <div>
            <h2 style="font-size:18px;font-weight:700">📊 Matriks Jadwal Booking Harian (<?= e($firstDay->format('F Y')) ?>)</h2>
            <p class="text-muted" style="font-size:13px;margin-top:4px">Menampilkan semua booking per hari lengkap dengan Driver, Tujuan, User, Bidang, Uang Muka, Realisasi &amp; Selisih.</p>
        </div>
    </div>
    <div style="overflow-x:auto">
        <table class="table" style="font-size:13px;white-space:nowrap">
            <thead>
                <tr style="background:var(--bg-subtle)">
                    <th style="padding:10px">TANGGAL</th>
                    <th style="padding:10px">MOBIL &amp; PLAT</th>
                    <th style="padding:10px">DRIVER</th>
                    <th style="padding:10px">TUJUAN</th>
                    <th style="padding:10px">USER (PENGGUNA)</th>
                    <th style="padding:10px">BIDANG (DEPT)</th>
                    <th style="padding:10px">UMK (UANG MUKA)</th>
                    <th style="padding:10px">REALISASI</th>
                    <th style="padding:10px">LEBIH / KURANG</th>
                    <th style="padding:10px">STATUS</th>
                    <th style="padding:10px">AKSI</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $hasRows = false;
                
                $daysInMonth = (int)$firstDay->format('t');
                for ($d = 1; $d <= $daysInMonth; $d++):
                    $currentDateStr = $firstDay->format('Y-m-') . sprintf('%02d', $d);
                    $dayBookings = $bookings[$currentDateStr] ?? [];
                    if (empty($dayBookings)) continue;
                    $hasRows = true;
                    foreach ($dayBookings as $idx => $b):
                    $umk       = (float)($b['advance_amount'] ?? 0);
                    // Realisasi = total nota riil yang diupload (bukan allowance/uang jalan)
                    $notaTotal = (float)($b['nota_total'] ?? 0);
                    // Uang jalan driver (terpisah dari UMK)
                    $uangJalan = (float)($b['allowance'] ?? 0);
                    // Lebih/Kurang = UMK - Realisasi nota
                    $selisih   = $umk - $notaTotal;
                ?>
                    <tr>
                        <?php if ($idx === 0): ?>
                            <td rowspan="<?= count($dayBookings) ?>" style="font-weight:700;vertical-align:top;background:var(--bg);border-right:1px solid var(--border)">
                                <?= e(tanggal_id($currentDateStr)) ?>
                            </td>
                        <?php endif; ?>
                        <td>
                            <strong><?= e($b['car_name'] ?? 'Mobil belum dipilih') ?></strong>
                            <br><small class="text-muted"><?= e($b['plate_number'] ?? '-') ?></small>
                        </td>
                        <td><strong><?= e($b['driver_name'] ?? '-') ?></strong></td>
                        <td><?= e($b['destination']) ?></td>
                        <td><?= e($b['requester']) ?></td>
                        <td><?= e($b['department'] ?? '-') ?></td>
                        <td style="font-weight:600;color:var(--primary)"><?= e(rupiah($umk)) ?></td>
                        <td style="font-weight:600"><?= e(rupiah($notaTotal)) ?></td>
                        <td style="font-weight:700;color:<?= $selisih < 0 ? 'var(--red)' : 'var(--green)' ?>">
                            <?= e(rupiah($selisih)) ?>
                        </td>
                        <td>
                            <span class="badge <?= status_class($b['status']) ?>">
                                <?= e(status_label($b['status'])) ?>
                            </span>
                        </td>
                        <td>
                            <a class="btn btn-outline" style="padding:4px 10px;font-size:12px" href="booking_detail.php?id=<?= (int)$b['id'] ?>">Detail / Edit</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endfor; ?>
                <?php if (!$hasRows): ?>
                    <tr>
                        <td colspan="12" style="text-align:center;padding:24px" class="text-muted">
                            Belum ada jadwal booking pada bulan ini.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal-backdrop" id="bookingModal">
    <div class="modal">
        <div class="modal-header">
            <h2>Detail Booking</h2>
            <button class="close" onclick="closeModal('bookingModal')">✕</button>
        </div>
        <div id="bookingModalBody"></div>
    </div>
</div>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>

