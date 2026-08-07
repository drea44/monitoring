-- Query untuk mengosongkan semua data transaksi kecuali Driver, Mobil, dan Users

SET FOREIGN_KEY_CHECKS = 0;

-- Kosongkan tabel transaksi dan riwayat
TRUNCATE TABLE expenses;
TRUNCATE TABLE passengers;
TRUNCATE TABLE bookings;
TRUNCATE TABLE budget_entries;
TRUNCATE TABLE notification_dismissals;

-- Reset status mobil dan driver menjadi 'available'
UPDATE cars SET status = 'available' WHERE status = 'used';
UPDATE drivers SET status = 'available' WHERE status = 'assigned';

SET FOREIGN_KEY_CHECKS = 1;
