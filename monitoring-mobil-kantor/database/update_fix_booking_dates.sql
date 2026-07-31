USE monitoring_mobil_kantor;

-- Patch untuk versi sebelumnya:
-- 1) tambah tanggal pulang / return_date
-- 2) ubah kolom kilometer menjadi BIGINT agar input KM besar tidak error 500

ALTER TABLE bookings ADD COLUMN IF NOT EXISTS return_date DATE NULL AFTER date;
UPDATE bookings SET return_date = date WHERE return_date IS NULL;
ALTER TABLE bookings MODIFY COLUMN return_date DATE NOT NULL;

ALTER TABLE bookings MODIFY COLUMN km_start BIGINT NULL;
ALTER TABLE bookings MODIFY COLUMN km_end BIGINT NULL;
ALTER TABLE cars MODIFY COLUMN last_km BIGINT NOT NULL DEFAULT 0;
