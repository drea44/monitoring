<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user = current_user();
$title = 'Dashboard - Monitoring Mobil Kantor';
$page_title = is_admin() ? 'Dashboard' : 'Dashboard Pengguna';
$page_subtitle = is_admin() ? 'Ringkasan booking, armada, driver, uang jalan, dan dropping anggaran.' : 'Ajukan booking dan lihat status penggunaan mobil kantor.';

if (is_admin()) {
    sync_all_cars_and_drivers_status();
    $budget = budget_summary();
    $stats = [
        'today'           => db()->query("SELECT COUNT(*) FROM bookings WHERE status <> 'rejected' AND (DATE(date) = CURDATE() OR DATE(created_at) = CURDATE())")->fetchColumn(),
        'running'         => db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'running'")->fetchColumn(),
        'cars_available'  => db()->query("SELECT COUNT(*) FROM cars WHERE status = 'available'")->fetchColumn(),
        'cars_used'       => db()->query("SELECT COUNT(*) FROM cars WHERE status = 'used'")->fetchColumn(),
        'cars_maintenance'=> db()->query("SELECT COUNT(*) FROM cars WHERE status = 'maintenance'")->fetchColumn(),
        'drivers_available'=>db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'available'")->fetchColumn(),
        'pending'         => db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn(),
        'allowance_month' => db()->query("SELECT COALESCE(SUM(allowance),0) FROM bookings WHERE status <> 'rejected' AND MONTH(date)=MONTH(CURDATE()) AND YEAR(date)=YEAR(CURDATE())")->fetchColumn(),
    ];

    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime('-' . $i . ' months');
        $ym = date('Y-m', $ts);
        $label = date('M', $ts);
        $stmt = db()->prepare("SELECT
            COUNT(*) total,
            SUM(status='completed') completed,
            SUM(status='rejected') rejected
            FROM bookings WHERE DATE_FORMAT(date, '%Y-%m') = ?");
        $stmt->execute([$ym]);
        $row = $stmt->fetch() ?: ['total'=>0,'completed'=>0,'rejected'=>0];
        $months[] = ['label'=>$label, 'total'=>(int)$row['total'], 'completed'=>(int)$row['completed'], 'rejected'=>(int)$row['rejected']];
    }
    $maxChart = max(1, max(array_map(fn($m) => max($m['total'], $m['completed'], $m['rejected']), $months)));

    $recent = db()->query("SELECT b.*, u.name requester, c.name car_name, c.plate_number, d.name driver_name FROM bookings b JOIN users u ON u.id=b.user_id LEFT JOIN cars c ON c.id=b.car_id LEFT JOIN drivers d ON d.id=b.driver_id ORDER BY b.created_at DESC LIMIT 8")->fetchAll();
} else {
    $stmt = db()->prepare("SELECT b.*, c.name car_name, c.plate_number, d.name driver_name FROM bookings b LEFT JOIN cars c ON c.id=b.car_id LEFT JOIN drivers d ON d.id=b.driver_id WHERE b.user_id = ? ORDER BY b.created_at DESC LIMIT 8");
    $stmt->execute([$user['id']]);
    $recent = $stmt->fetchAll();
}

include __DIR__ . '/templates/header.php';
include __DIR__ . '/templates/sidebar.php';
?>
<main class="main">
<?php include __DIR__ . '/templates/topbar.php'; ?>

<?php if (is_admin()): ?>
    <section class="grid grid-5" style="margin-bottom:22px">
        <a class="card stat card-blue" href="<?= e(base_path('booking.php')) ?>">
            <div><span>Booking Hari Ini</span><strong id="statToday"><?= e($stats['today']) ?></strong><small>aktif hari ini</small></div>
            <div class="icon">🗓️</div>
        </a>
        <a class="card stat card-orange" href="<?= e(base_path('history.php?status=pending')) ?>">
            <div><span>Booking Pending</span><strong id="statPending"><?= e($stats['pending']) ?></strong><small>menunggu approval</small></div>
            <div class="icon">⏳</div>
        </a>
        <a class="card stat card-blue" href="<?= e(base_path('cars.php')) ?>">
            <div><span>Mobil Tersedia</span><strong id="statCarsAvailable"><?= e($stats['cars_available']) ?></strong><small>unit ready</small></div>
            <div class="icon">🚘</div>
        </a>
        <a class="card stat card-purple" href="<?= e(base_path('history.php?status=running')) ?>">
            <div><span>Mobil Digunakan</span><strong id="statCarsUsed"><?= e($stats['running']) ?></strong><small>sedang berjalan</small></div>
            <div class="icon">🚦</div>
        </a>
        <a class="card stat card-green" href="<?= e(base_path('drivers.php')) ?>">
            <div><span>Driver Tersedia</span><strong id="statDriversAvailable"><?= e($stats['drivers_available']) ?></strong><small>siap bertugas</small></div>
            <div class="icon">👥</div>
        </a>
    </section>

    <section class="dashboard-grid">
        <?php
        $chartLabels    = array_column($months, 'label');
        $chartTotal     = array_column($months, 'total');
        $chartCompleted = array_column($months, 'completed');
        $chartRejected  = array_column($months, 'rejected');
        ?>
        <div class="card chart-card">
            <div class="chart-head" style="margin-bottom:14px">
                <div>
                    <h2>Ringkasan Aktivitas Booking</h2>
                    <p class="text-muted">Grafik interaktif 6 bulan terakhir</p>
                </div>
            </div>
            <div style="position:relative;height:240px;width:100%">
                <canvas id="bookingChart"></canvas>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const ctx = document.getElementById('bookingChart');
            if (!ctx) return;
            new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: <?= json_encode($chartLabels) ?>,
                    datasets: [
                        {
                            label: 'Booking',
                            data: <?= json_encode($chartTotal) ?>,
                            backgroundColor: '#2563ff',
                            borderRadius: 6,
                            barPercentage: 0.75,
                            categoryPercentage: 0.6
                        },
                        {
                            label: 'Selesai',
                            data: <?= json_encode($chartCompleted) ?>,
                            backgroundColor: '#10b981',
                            borderRadius: 6,
                            barPercentage: 0.75,
                            categoryPercentage: 0.6
                        },
                        {
                            label: 'Dibatalkan',
                            data: <?= json_encode($chartRejected) ?>,
                            backgroundColor: '#ef4444',
                            borderRadius: 6,
                            barPercentage: 0.75,
                            categoryPercentage: 0.6
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            align: 'end',
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                font: { family: 'Plus Jakarta Sans, sans-serif', size: 12, weight: '600' }
                            }
                        },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 13, weight: 'bold' },
                            bodyFont: { size: 12 },
                            padding: 10,
                            cornerRadius: 8,
                            displayColors: true
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false },
                            ticks: { font: { family: 'Plus Jakarta Sans, sans-serif' } }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0, font: { family: 'Plus Jakarta Sans, sans-serif' } },
                            grid: { color: '#f1f5f9' }
                        }
                    }
                }
            });
        });
        </script>

        <div class="card">
            <h2 style="margin-bottom:14px">Status Armada & Driver</h2>
            <?php 
            $carsAvailable = (int)$stats['cars_available'];
            $carsUsed      = (int)$stats['cars_used'];
            $carsMaint     = (int)$stats['cars_maintenance'];
            $carsInactive  = (int)db()->query("SELECT COUNT(*) FROM cars WHERE status = 'inactive'")->fetchColumn();
            $carsTotal     = max(1, $carsAvailable + $carsUsed + $carsMaint + $carsInactive);

            $driversAvailable = (int)$stats['drivers_available'];
            $driversAssigned  = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'assigned'")->fetchColumn();
            $driversLeave     = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'leave'")->fetchColumn();
            $driversTotal     = max(1, $driversAvailable + $driversAssigned + $driversLeave);
            ?>

            <div style="font-weight:700;font-size:12px;color:var(--blue);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px">
                🚘 Armada Mobil (<?= e($carsTotal) ?> Unit)
            </div>
            <div class="status-list" style="margin-bottom:18px">
                <div class="status-row">
                    <span>Mobil Tersedia</span>
                    <strong id="widgetCarsAvailable"><?= e($carsAvailable) ?> unit</strong>
                    <div class="progress success"><span id="barCarsAvailable" style="width:<?= e(round($carsAvailable / $carsTotal * 100)) ?>%"></span></div>
                </div>
                <div class="status-row">
                    <span>Mobil Digunakan</span>
                    <strong id="widgetCarsUsed"><?= e($carsUsed) ?> unit</strong>
                    <div class="progress"><span id="barCarsUsed" style="width:<?= e(round($carsUsed / $carsTotal * 100)) ?>%"></span></div>
                </div>
                <div class="status-row">
                    <span>Mobil Perawatan</span>
                    <strong id="widgetCarsMaint"><?= e($carsMaint) ?> unit</strong>
                    <div class="progress warning"><span id="barCarsMaint" style="width:<?= e(round($carsMaint / $carsTotal * 100)) ?>%"></span></div>
                </div>
                <?php if ($carsInactive > 0): ?>
                <div class="status-row">
                    <span>Mobil Nonaktif</span>
                    <strong id="widgetCarsInactive"><?= e($carsInactive) ?> unit</strong>
                    <div class="progress danger"><span id="barCarsInactive" style="width:<?= e(round($carsInactive / $carsTotal * 100)) ?>%"></span></div>
                </div>
                <?php endif; ?>
            </div>

            <div style="font-weight:700;font-size:12px;color:var(--green);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px">
                👥 Driver (<?= e($driversTotal) ?> Personel)
            </div>
            <div class="status-list">
                <div class="status-row">
                    <span>Driver Tersedia</span>
                    <strong id="widgetDriversAvailable"><?= e($driversAvailable) ?> org</strong>
                    <div class="progress success"><span id="barDriversAvailable" style="width:<?= e(round($driversAvailable / $driversTotal * 100)) ?>%"></span></div>
                </div>
                <div class="status-row">
                    <span>Driver Bertugas</span>
                    <strong id="widgetDriversAssigned"><?= e($driversAssigned) ?> org</strong>
                    <div class="progress"><span id="barDriversAssigned" style="width:<?= e(round($driversAssigned / $driversTotal * 100)) ?>%"></span></div>
                </div>
                <?php if ($driversLeave > 0): ?>
                <div class="status-row">
                    <span>Driver Cuti / Izin</span>
                    <strong id="widgetDriversLeave"><?= e($driversLeave) ?> org</strong>
                    <div class="progress warning"><span id="barDriversLeave" style="width:<?= e(round($driversLeave / $driversTotal * 100)) ?>%"></span></div>
                </div>
                <?php endif; ?>
            </div>

            <div class="utilization" style="margin-top:16px;padding-top:14px;border-top:1px dashed var(--border)">
                <span class="text-muted">Tingkat Utilisasi Armada</span><br>
                <strong id="widgetUtilization"><?= e($carsTotal ? round(($carsUsed / $carsTotal) * 100) : 0) ?>%</strong><br>
                <span id="widgetUtilizationSub" class="text-muted"><?= ($carsUsed > 0 ? 'Sedang Beroperasi' : 'Siap Bertugas') ?></span>
            </div>
        </div>
    </section>
<?php else: ?>
    <section class="grid grid-3">
        <a class="card stat card-blue" href="<?= e(base_path('booking.php')) ?>"><div><span>Ajukan</span><strong>Booking</strong><small>cek mobil dan driver</small></div><div class="icon">📝</div></a>
        <a class="card stat card-green" href="<?= e(base_path('calendar.php')) ?>"><div><span>Lihat</span><strong>Kalender</strong><small>jadwal armada</small></div><div class="icon">🗓️</div></a>
        <a class="card stat card-purple" href="<?= e(base_path('history.php')) ?>"><div><span>Cek</span><strong>Status</strong><small>riwayat booking</small></div><div class="icon">📌</div></a>
    </section>
<?php endif; ?>

<section class="card" style="margin-top:18px">
    <div class="calendar-toolbar">
        <div><h2>Aktivitas Booking Terbaru</h2><p class="text-muted">Daftar booking terbaru di sistem</p></div>
        <a class="btn btn-primary" href="<?= e(base_path('booking.php')) ?>">+ Booking Mobil</a>
    </div>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>No.</th><th>Tanggal</th><th>Tujuan</th><th>Mobil</th><th>Driver</th><th>Status</th><th>Waktu Booking</th><th>Aksi</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $idx => $row): ?>
                <tr>
                    <td><?= e($idx + 1) ?></td>
                    <td><?= e(periode_tanggal($row['date'], $row['return_date'] ?? $row['date'])) ?></td>
                    <td><strong><?= e($row['destination']) ?></strong><br><small class="text-muted"><?= e($row['code']) ?></small></td>
                    <td><?= e($row['car_name'] ?? '-') ?><?php if (!empty($row['plate_number'])): ?><br><small class="text-muted"><?= e($row['plate_number']) ?></small><?php endif; ?></td>
                    <td><?= e($row['driver_name'] ?? '-') ?></td>
                    <td><span class="badge <?= e(status_class($row['status'])) ?>"><?= e(status_label($row['status'])) ?></span></td>
                    <td><?= e(substr($row['start_time'], 0, 5)) ?> WIB</td>
                    <td><a class="btn btn-outline" href="<?= e(base_path('booking_detail.php?id=' . $row['id'])) ?>">Detail</a></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?><tr><td colspan="8">Belum ada booking.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
</main>
<?php include __DIR__ . '/templates/footer.php'; ?>
