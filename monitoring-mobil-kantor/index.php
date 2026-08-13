<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>SUCOFINDO FIRST — Monitoring Mobil Kantor</title>
    <link rel="stylesheet" href="<?= e(base_path('assets/css/style.css')) ?>">
</head>
<body class="landing-page">
    <header class="landing-nav">
        <a class="landing-brand" href="<?= e(base_path('index.php')) ?>">
            <span class="brand-symbol">🚘</span>
            <strong>SUCOFINDO FIRST</strong>
        </a>
        <a class="btn btn-ghost-light" href="<?= e(base_path('login.php')) ?>">Masuk / Login <span>→</span></a>
    </header>

    <main class="landing-wrap">
        <section class="hero-copy">
            <div class="hero-badge"><span></span> Sistem Monitoring Armada Kantor</div>
            <h1>Pantau Mobil Kantor<br>Lebih Cerdas, <em>Aman & Terencana</em></h1>
            <p>Kelola booking, penjadwalan, driver, uang jalan, dan laporan operasional kendaraan kantor dalam satu sistem terintegrasi secara real-time.</p>
            <div class="hero-actions">
                <a class="btn btn-primary btn-lg" href="<?= e(base_path('login.php')) ?>">Masuk / Login <span>→</span></a>
                <a class="btn btn-outline-light btn-lg" href="#fitur">Lihat Fitur</a>
            </div>
            <div class="hero-metrics">
                <div><strong>25+</strong><span>Total Mobil</span></div>
                <div><strong>18+</strong><span>Total Driver</span></div>
                <div><strong>120+</strong><span>Booking/Bulan</span></div>
                <div><strong>99%</strong><span>Uptime Sistem</span></div>
            </div>
        </section>

        <section class="hero-visual" aria-label="Preview aplikasi">
            <div class="city-glow"></div>
            <div class="browser-card">
                <div class="browser-bar"><i></i><i></i><i></i><span></span></div>
                <div class="browser-grid">
                    <div class="mini-card calendar-mini">
                        <div class="mini-title">Kalender & Jadwal</div>
                        <strong><?= e(bulan_tahun_id(date('Y-m'))) ?></strong>
                        <div class="mini-days">
                            <?php for($i=1;$i<=28;$i++): ?><span class="<?= $i===12?'active':'' ?>"><?= $i ?></span><?php endfor; ?>
                        </div>
                    </div>
                    <div class="mini-card status-mini">
                        <div class="mini-title">Status Armada</div>
                        <p><span class="dot success"></span>Mobil Tersedia <strong>18 unit</strong></p>
                        <p><span class="dot info"></span>Sedang Digunakan <strong>7 unit</strong></p>
                        <p><span class="dot warning"></span>Perawatan <strong>2 unit</strong></p>
                        <p><span class="dot danger"></span>Tidak Tersedia <strong>1 unit</strong></p>
                    </div>
                    <div class="mini-card vehicle-mini">
                        <div class="car-illustration">🚘</div>
                        <div><strong>Toyota Innova Reborn</strong><span>B 1234 KTR · Digunakan</span></div>
                    </div>
                    <div class="mini-card driver-mini">
                        <div class="avatar-mini">BS</div>
                        <div><strong>Budi Santoso</strong><span>Driver · Online</span></div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <section class="landing-features" id="fitur">
        <div class="feature-pill">Booking Mobil</div>
        <div class="feature-pill">Kalender Jadwal</div>
        <div class="feature-pill">Uang Jalan</div>
        <div class="feature-pill">Dropping Anggaran</div>
        <div class="feature-pill">Report Keuangan</div>
        <div class="feature-pill">Upload Realisasi</div>
    </section>

    <footer class="landing-footer">© <?= date('Y') ?> Monitoring Mobil Kantor · Sistem Operasional Armada</footer>
</body>
</html>
