<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$title = 'Kalender Penggunaan - Monitoring Mobil Kantor';
$page_title = 'Kalender Penggunaan Mobil';
$page_subtitle = 'Klik event booking untuk melihat mobil, driver, penumpang, kilometer, dan biaya.';

$month = $_GET['month'] ?? date('Y-m');
if (!preg_match('/^\d{4}-\d{2}$/', $month)) $month = date('Y-m');
$firstDay = new DateTime($month . '-01');
$start = clone $firstDay;
$start->modify('monday this week');
$end = clone $start;
$end->modify('+41 days');

$params = [$end->format('Y-m-d'), $start->format('Y-m-d')];
$sql = "SELECT b.*, u.name requester, c.name car_name, c.plate_number, d.name driver_name
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
$bookings = [];
foreach ($stmt->fetchAll() as $b) {
    $b['return_date'] = $b['return_date'] ?: $b['date'];
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
            <div class="day<?= $muted ?>">
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
                    <button type="button" class="event <?= e($booking['status']) ?>" onclick="openBookingModal(<?= (int)$booking['id'] ?>)">
                        <strong><?= e($timeLabel) ?> <?= e($booking['car_name'] ?? 'Mobil belum dipilih') ?></strong>
                        <span><?= e($booking['driver_name'] ?? 'Driver belum dipilih') ?> · <?= e($booking['destination']) ?></span>
                    </button>
                <?php endforeach; ?>
            </div>
        <?php $cursor->modify('+1 day'); endwhile; ?>
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
