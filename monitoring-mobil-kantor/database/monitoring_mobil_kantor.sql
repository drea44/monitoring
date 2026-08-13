CREATE DATABASE IF NOT EXISTS monitoring_mobil_kantor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE monitoring_mobil_kantor;

DROP TABLE IF EXISTS expenses;
DROP TABLE IF EXISTS budget_entries;
DROP TABLE IF EXISTS passengers;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS drivers;
DROP TABLE IF EXISTS cars;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS app_settings;

CREATE TABLE app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(160) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin','user') NOT NULL DEFAULT 'user',
    department VARCHAR(120) NULL,
    phone VARCHAR(50) NULL,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;


CREATE TABLE budget_entries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entry_date DATE NOT NULL,
    reference_no VARCHAR(80) NOT NULL,
    description TEXT NULL,
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_budget_entries_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_budget_entry_date(entry_date)
) ENGINE=InnoDB;

CREATE TABLE cars (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    plate_number VARCHAR(30) NOT NULL UNIQUE,
    capacity INT NOT NULL DEFAULT 4,
    last_km BIGINT NOT NULL DEFAULT 0,
    status ENUM('available','used','maintenance','inactive') NOT NULL DEFAULT 'available',
    photo VARCHAR(255) NULL,
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE drivers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    phone VARCHAR(50) NULL,
    status ENUM('available','assigned','leave','inactive') NOT NULL DEFAULT 'available',
    notes TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    car_id INT NULL,
    driver_id INT NULL,
    date DATE NOT NULL,
    return_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    destination VARCHAR(200) NOT NULL,
    purpose TEXT NULL,
    passenger_count INT NOT NULL DEFAULT 1,
    km_start BIGINT NULL,
    km_end BIGINT NULL,
    allowance DECIMAL(14,2) NOT NULL DEFAULT 0,
    advance_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    fuel_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    toll_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    parking_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    other_cost DECIMAL(14,2) NOT NULL DEFAULT 0,
    status ENUM('pending','approved','running','completed','rejected') NOT NULL DEFAULT 'pending',
    admin_note TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_car FOREIGN KEY (car_id) REFERENCES cars(id) ON DELETE SET NULL,
    CONSTRAINT fk_bookings_driver FOREIGN KEY (driver_id) REFERENCES drivers(id) ON DELETE SET NULL,
    INDEX idx_booking_date_time(date, return_date, start_time, end_time),
    INDEX idx_booking_status(status)
) ENGINE=InnoDB;

CREATE TABLE passengers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    name VARCHAR(120) NOT NULL,
    CONSTRAINT fk_passengers_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE expenses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    category ENUM('BBM','Tol','Parkir','Lainnya') NOT NULL DEFAULT 'Lainnya',
    amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    receipt_file VARCHAR(255) NULL,
    expense_date DATE NOT NULL,
    note TEXT NULL,
    verified TINYINT(1) NOT NULL DEFAULT 0,
    verified_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_expenses_booking FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    INDEX idx_expense_date(expense_date),
    INDEX idx_expense_verified(verified)
) ENGINE=InnoDB;


INSERT INTO app_settings (setting_key, setting_value) VALUES
('budget_dropping_amount', '25000000'),
('budget_note', 'Dropping anggaran operasional kendaraan kantor.');

INSERT INTO users (name, email, password, role, department, phone) VALUES
('Admin Fleet', 'admin@kantor.local', '$2y$12$GdFIobZWNP6VdTkrUOJni.a7MuTHTts2ylxnoSOWIAvOBLgrCTOqC', 'admin', 'GA / Operasional', '081200000001'),
('Pengguna Demo', 'user@kantor.local', '$2y$12$UejHI7DnJeLEcuGTrFVAkeb/fa4MXRIn5L1OU7nvecABSiRVpBTAy', 'user', 'Sales', '081200000002'),
('Rina Sari', 'rina@kantor.local', '$2y$12$UejHI7DnJeLEcuGTrFVAkeb/fa4MXRIn5L1OU7nvecABSiRVpBTAy', 'user', 'HRD', '081200000003');


INSERT INTO budget_entries (entry_date, reference_no, description, amount, created_by) VALUES
('2026-07-01', 'DROP-260701-001', 'Dropping anggaran operasional kendaraan bulan Juli', 25000000, 1);

INSERT INTO cars (name, plate_number, capacity, last_km, status, notes) VALUES
('Toyota Innova Reborn', 'B 1234 KTR', 7, 45820, 'available', 'Mobil operasional utama'),
('Toyota Avanza', 'B 5678 ADM', 6, 38215, 'available', 'Cocok untuk perjalanan dalam kota'),
('Mitsubishi Xpander', 'B 9012 OPS', 7, 29500, 'available', 'Kabin luas'),
('Honda HR-V', 'B 3344 HRV', 5, 22610, 'maintenance', 'Service berkala');

INSERT INTO drivers (name, phone, status, notes) VALUES
('Budi Santoso', '0812-1000-1001', 'available', 'Driver senior'),
('Andi Wijaya', '0812-1000-1002', 'available', 'Area Jabodetabek'),
('Rahmat Hidayat', '0812-1000-1003', 'available', 'Luar kota'),
('Dedi Saputra', '0812-1000-1004', 'leave', 'Cuti');

INSERT INTO bookings (code, user_id, car_id, driver_id, date, return_date, start_time, end_time, destination, purpose, passenger_count, km_start, km_end, allowance, advance_amount, fuel_cost, toll_cost, parking_cost, other_cost, status, admin_note) VALUES
('BK-260710-A001', 2, 1, 1, '2026-07-10', '2026-07-10', '08:00:00', '15:00:00', 'Bandung', 'Meeting dengan client', 5, 45820, 46140, 250000, 0, 300000, 130000, 25000, 0, 'approved', 'Disetujui karena mobil dan driver tersedia.'),
('BK-260711-A002', 2, 2, 2, '2026-07-11', '2026-07-11', '09:00:00', '12:00:00', 'Bekasi', 'Kunjungan site project', 3, NULL, NULL, 0, 0, 0, 0, 0, 0, 'pending', NULL),
('BK-260712-A003', 3, 3, 3, '2026-07-12', '2026-07-12', '07:30:00', '18:00:00', 'Cirebon', 'Audit cabang', 6, 29500, NULL, 300000, 0, 500000, 210000, 35000, 0, 'running', 'Sedang digunakan'),
('BK-260705-A004', 2, 1, 1, '2026-07-05', '2026-07-05', '08:30:00', '17:30:00', 'Karawang', 'Training cabang', 4, 45480, 45820, 250000, 0, 260000, 90000, 20000, 50000, 'completed', 'Selesai dan nota diverifikasi.');

INSERT INTO passengers (booking_id, name) VALUES
(1, 'Ahmad'), (1, 'Sinta'), (1, 'Dewi'), (1, 'Fajar'), (1, 'Nadia'),
(2, 'Raka'), (2, 'Tono'), (2, 'Maya'),
(3, 'Rina'), (3, 'Bagas'), (3, 'Putri'), (3, 'Yoga'), (3, 'Dimas'), (3, 'Lia'),
(4, 'Dewi'), (4, 'Arman'), (4, 'Tari'), (4, 'Bayu');

INSERT INTO expenses (booking_id, category, amount, receipt_file, expense_date, note, verified, verified_at) VALUES
(1, 'BBM', 300000, NULL, '2026-07-10', 'Isi BBM sebelum pulang', 0, NULL),
(1, 'Tol', 130000, NULL, '2026-07-10', 'Tol Jakarta-Bandung PP', 0, NULL),
(4, 'BBM', 260000, NULL, '2026-07-05', 'BBM perjalanan Karawang', 1, '2026-07-05 19:00:00'),
(4, 'Tol', 90000, NULL, '2026-07-05', 'Tol PP', 1, '2026-07-05 19:01:00'),
(4, 'Parkir', 20000, NULL, '2026-07-05', 'Parkir lokasi training', 1, '2026-07-05 19:02:00');
