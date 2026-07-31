# Monitoring Mobil Kantor - XAMPP

Project PHP Native + MySQL untuk monitoring penggunaan mobil kantor.

## Update versi ini

- Tampilan diperbarui dengan gaya dark-blue corporate seperti referensi:
  - Landing page modern
  - Login page dark-blue dengan card putih
  - Dashboard admin dengan sidebar navy, stat card, chart, status armada, dan tabel aktivitas
- Report Keuangan tetap menggunakan konsep Opsi B:
  - Opening Balance
  - Closing Balance
  - Debit / Credit
  - Balance berjalan
  - Filter periode, mobil, driver, status, jenis transaksi, dan search
  - Copy Table, Download Excel/CSV, Print/PDF
- Dropping Anggaran tetap dipakai dan tampil di menu Dropping Anggaran serta card Dashboard.
- Detail booking menampilkan uang jalan, terpakai berdasarkan total nota, sisa, dan kekurangan jika nota melebihi uang jalan.

## Cara install di Mac XAMPP

1. Extract ZIP.
2. Copy folder `monitoring-mobil-kantor` ke:

```text
/Applications/XAMPP/htdocs/
```

3. Jalankan XAMPP:
   - Apache Web Server
   - MySQL Database

4. Buka phpMyAdmin:

```text
http://localhost/phpmyadmin
```

5. Drop database lama jika ingin mulai bersih:

```sql
DROP DATABASE IF EXISTS monitoring_mobil_kantor;
```

6. Import file:

```text
monitoring-mobil-kantor/database/monitoring_mobil.sql
```

7. Buka aplikasi:

```text
http://localhost/monitoring-mobil-kantor
```

## Akun Demo

Admin:

```text
Email    : admin@kantor.local
Password : admin123
```

Pengguna:

```text
Email    : user@kantor.local
Password : user123
```

## Struktur utama

- `index.php` = landing page
- `login.php` = halaman login
- `dashboard.php` = dashboard admin/pengguna
- `booking.php` = form booking mobil
- `booking_detail.php` = detail perjalanan, uang jalan, nota, dan status
- `budget.php` = dropping anggaran
- `reports.php` = report keuangan mutasi debit/credit
- `database/monitoring_mobil.sql` = database utama
