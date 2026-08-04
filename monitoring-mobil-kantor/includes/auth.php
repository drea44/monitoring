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
    // Sync status mobil & driver dilakukan via polling live_stats.php (setiap 30 detik),
    // di dashboard.php saat load, dan di setiap aksi perubahan status booking.
    // Tidak perlu dijalankan di setiap halaman karena menjalankan 4 UPDATE query besar.
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
