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

    $todayBookings = db()->query(
        "SELECT b.id FROM bookings b WHERE b.date = CURDATE() AND b.status IN ('approved', 'running', 'completed')"
    )->fetchAll();
    foreach ($todayBookings as $tb) {
        $allNotifs[] = ['id' => 'booking_today_' . $tb['id']];
    }

    if (is_admin()) {
        $pendingBookings = db()->query("SELECT b.id FROM bookings b WHERE b.status = 'pending'")->fetchAll();
        foreach ($pendingBookings as $pb) {
            $allNotifs[] = ['id' => 'booking_pending_' . $pb['id']];
        }

        $maintenanceCars = db()->query("SELECT plate_number FROM cars WHERE status = 'maintenance'")->fetchAll();
        foreach ($maintenanceCars as $mc) {
            $allNotifs[] = ['id' => 'car_maintenance_' . preg_replace('/[^a-zA-Z0-9]/', '', $mc['plate_number'])];
        }

        $leaveDrivers = db()->query("SELECT name FROM drivers WHERE status = 'leave'")->fetchAll();
        foreach ($leaveDrivers as $ld) {
            $allNotifs[] = ['id' => 'driver_leave_' . preg_replace('/[^a-zA-Z0-9]/', '', $ld['name'])];
        }
    }

    $pendingBookings = is_admin() ? array_filter($allNotifs, fn($n) => str_starts_with($n['id'], 'booking_pending_')) : [];

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
