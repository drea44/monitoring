<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

if (!current_user()) {
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

try {
    sync_all_cars_and_drivers_status();

    $carsTotal       = (int)db()->query("SELECT COUNT(*) FROM cars WHERE status <> 'inactive'")->fetchColumn();
    $carsAvailable   = (int)db()->query("SELECT COUNT(*) FROM cars WHERE status = 'available'")->fetchColumn();
    $carsUsed        = (int)db()->query("SELECT COUNT(*) FROM cars WHERE status = 'used'")->fetchColumn();
    $carsMaintenance = (int)db()->query("SELECT COUNT(*) FROM cars WHERE status = 'maintenance'")->fetchColumn();

    $driversTotal     = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status <> 'inactive'")->fetchColumn();
    $driversAvailable = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'available'")->fetchColumn();
    $driversAssigned  = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'assigned'")->fetchColumn();
    $driversLeave     = (int)db()->query("SELECT COUNT(*) FROM drivers WHERE status = 'leave'")->fetchColumn();

    $pendingCount = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'pending'")->fetchColumn();
    $runningCount = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status = 'running'")->fetchColumn();
    $todayCount   = (int)db()->query("SELECT COUNT(*) FROM bookings WHERE status <> 'rejected' AND (DATE(date) = CURDATE() OR DATE(created_at) = CURDATE())")->fetchColumn();

    $utilization  = $carsTotal > 0 ? round(($carsUsed / $carsTotal) * 100) : 0;

    echo json_encode([
        'ok' => true,
        'stats' => [
            'cars_total'        => $carsTotal,
            'cars_available'    => $carsAvailable,
            'cars_used'         => $carsUsed,
            'cars_maintenance'  => $carsMaintenance,
            'drivers_total'     => $driversTotal,
            'drivers_available' => $driversAvailable,
            'drivers_assigned'  => $driversAssigned,
            'drivers_leave'     => $driversLeave,
            'utilization'       => $utilization,
            'pending_count'     => $pendingCount,
            'running_count'     => $runningCount,
            'today_count'       => $todayCount,
        ]
    ]);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
