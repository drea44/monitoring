<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;

    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT id, name, email, role, department, phone, status FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch() ?: null;
    }
    return $user;
}

function require_login(): void
{
    if (!current_user()) {
        redirect('login.php');
    }
    sync_all_cars_and_drivers_status();
}

function require_admin(): void
{
    require_login();
    if ((current_user()['role'] ?? '') !== 'admin') {
        flash('danger', 'Akses hanya untuk admin.');
        redirect('dashboard.php');
    }
}

function is_admin(): bool
{
    return (current_user()['role'] ?? '') === 'admin';
}
