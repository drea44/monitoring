<?php
require_once __DIR__ . '/../includes/auth.php';
require_login();

header('Content-Type: application/json');

$user = current_user();
$userId = (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$key = trim($input['key'] ?? '');
$allKeys = $input['all_keys'] ?? [];

if ($key !== '') {
    $stmt = db()->prepare("INSERT IGNORE INTO notification_dismissals (user_id, notif_key) VALUES (?, ?)");
    $stmt->execute([$userId, $key]);
    echo json_encode(['success' => true, 'dismissed' => $key]);
    exit;
}

if (!empty($allKeys) && is_array($allKeys)) {
    $stmt = db()->prepare("INSERT IGNORE INTO notification_dismissals (user_id, notif_key) VALUES (?, ?)");
    foreach ($allKeys as $k) {
        if (!empty($k)) {
            $stmt->execute([$userId, trim($k)]);
        }
    }
    echo json_encode(['success' => true, 'dismissed_all' => true]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'No key provided']);
