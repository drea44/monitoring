<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/auth.php';

$user = current_user();
if (!$user) {
    echo json_encode(['ok' => false, 'error' => 'Unauthenticated']);
    exit;
}

$userId = (int)($user['id'] ?? 0);

try {
    $allNotifs = [];

    if (is_admin()) {
        $sum = budget_summary();
        $totalUsed = (float)($sum['allowance'] ?? 0) + (float)($sum['expense'] ?? 0);
        $sisa = (float)($sum['dropping'] ?? 0) - $totalUsed;
        if ($sisa < 10000000) {
            $allNotifs[] = ['id' => 'budget_warning_' . date('Y-m-d')];
        }
    }

    $todayBookings = db()->query(
        "SELECT b.id FROM bookings b WHERE b.date = CURDATE() AND b.status IN ('approved', 'running', 'completed')"
    )->fetchAll();
    foreach ($todayBookings as $tb) {
        $allNotifs[] = ['id' => 'booking_today_' . $tb['id']];
    }

    if (is_admin()) {
        $recentDrops = db()->query(
            "SELECT id FROM budget_entries WHERE entry_date >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)"
        )->fetchAll();
        foreach ($recentDrops as $rd) {
            $allNotifs[] = ['id' => 'budget_drop_' . $rd['id']];
        }
    }

    $pendingBookings = db()->query("SELECT b.id FROM bookings b WHERE b.status = 'pending'")->fetchAll();
    foreach ($pendingBookings as $pb) {
        $allNotifs[] = ['id' => 'booking_pending_' . $pb['id']];
    }

    if (is_admin()) {
        $maintenanceCars = db()->query("SELECT plate_number FROM cars WHERE status = 'maintenance'")->fetchAll();
        foreach ($maintenanceCars as $mc) {
            $allNotifs[] = ['id' => 'car_maintenance_' . preg_replace('/[^a-zA-Z0-9]/', '', $mc['plate_number'])];
        }

        $leaveDrivers = db()->query("SELECT name FROM drivers WHERE status = 'leave'")->fetchAll();
        foreach ($leaveDrivers as $ld) {
            $allNotifs[] = ['id' => 'driver_leave_' . preg_replace('/[^a-zA-Z0-9]/', '', $ld['name'])];
        }
    }

    $dismissedKeys = [];
    if ($userId > 0) {
        try {
            $stmt = db()->prepare("SELECT notif_key FROM notification_dismissals WHERE user_id = ?");
            $stmt->execute([$userId]);
            $dismissedKeys = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (Throwable $e) { $dismissedKeys = []; }
    }

    $activeNotifs = array_filter($allNotifs, function($n) use ($dismissedKeys) {
        $key = md5($n['id']);
        return !in_array($key, $dismissedKeys, true);
    });

    echo json_encode([
        'ok' => true,
        'count' => count($activeNotifs),
        'pending_count' => count($pendingBookings)
    ]);

} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
}
