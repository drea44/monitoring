<?php
require __DIR__ . '/../monitoring-mobil-kantor/config/database.php';
$pdo = db();

try {
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Clear transaction & relational tables
    $pdo->exec("TRUNCATE TABLE expenses");
    $pdo->exec("TRUNCATE TABLE passengers");
    $pdo->exec("TRUNCATE TABLE bookings");
    $pdo->exec("TRUNCATE TABLE budget_entries");
    
    // Clear notification dismissals if table exists
    $pdo->exec("TRUNCATE TABLE notification_dismissals");
    
    // Reset status of cars and drivers that were marked as 'used' or 'assigned'
    $pdo->exec("UPDATE cars SET status = 'available' WHERE status = 'used'");
    $pdo->exec("UPDATE drivers SET status = 'available' WHERE status = 'assigned'");
    
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "BERHASIL: Semua data transaksi (bookings, expenses, passengers, budget_entries, notification_dismissals) telah dikosongkan.\n";
    echo "Data master mobil (cars), driver (drivers), dan pengguna (users) tetap dipertahankan.\n";
    echo "Status mobil & driver yang sebelumnya 'used'/'assigned' telah dikembalikan menjadi 'available'.\n";

} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
