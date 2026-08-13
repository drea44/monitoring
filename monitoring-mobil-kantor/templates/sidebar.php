<?php
$current = basename($_SERVER['PHP_SELF']);
function nav_active(string $file, string $current): string { return $file === $current ? 'active' : ''; }
?>
<aside class="sidebar">
    <div class="brand">
        <div class="brand-icon">
            <i class="ph-fill ph-car" style="font-size:24px;color:#fff;"></i>
        </div>
        <div>
            <strong>SUCOFINDO FIRST</strong>
            <small>Monitoring Mobil Kantor</small>
        </div>
    </div>
    <nav class="nav">
        <div class="nav-section"><span>Menu Utama</span></div>

        <a class="nav-item <?= nav_active('dashboard.php', $current) ?>" href="<?= e(base_path('dashboard.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-squares-four"></i>
            </span>
            <span class="nav-label">Dashboard</span>
            <?php if(nav_active('dashboard.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <a class="nav-item <?= nav_active('booking.php', $current) ?>" href="<?= e(base_path('booking.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-clipboard-text"></i>
            </span>
            <span class="nav-label">Booking Mobil</span>
            <?php if(nav_active('booking.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <a class="nav-item <?= nav_active('calendar.php', $current) ?>" href="<?= e(base_path('calendar.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-calendar-dots"></i>
            </span>
            <span class="nav-label">Kalender &amp; Jadwal</span>
            <?php if(nav_active('calendar.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <a class="nav-item <?= nav_active('history.php', $current) ?>" href="<?= e(base_path('history.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-clock-counter-clockwise"></i>
            </span>
            <span class="nav-label">Riwayat Booking</span>
            <?php if(nav_active('history.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <a class="nav-item <?= nav_active('reports.php', $current) ?>" href="<?= e(base_path('reports.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-chart-bar"></i>
            </span>
            <span class="nav-label">Report Keuangan</span>
            <?php if(nav_active('reports.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <?php if (is_admin()): ?>
        <div class="nav-section nav-section-admin"><span>Admin</span></div>

        <a class="nav-item <?= nav_active('cars.php', $current) ?>" href="<?= e(base_path('cars.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-van"></i>
            </span>
            <span class="nav-label">Armada Mobil</span>
            <?php if(nav_active('cars.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <a class="nav-item <?= nav_active('drivers.php', $current) ?>" href="<?= e(base_path('drivers.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-steering-wheel"></i>
            </span>
            <span class="nav-label">Driver</span>
            <?php if(nav_active('drivers.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>

        <a class="nav-item <?= nav_active('users.php', $current) ?>" href="<?= e(base_path('users.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-users-three"></i>
            </span>
            <span class="nav-label">User</span>
            <?php if(nav_active('users.php', $current) === 'active'): ?><span class="nav-dot"></span><?php endif; ?>
        </a>
        <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-divider"></div>
        <a class="nav-item logout-item" href="<?= e(base_path('logout.php')) ?>">
            <span class="nav-icon">
                <i class="ph-duotone ph-sign-out"></i>
            </span>
            <span class="nav-label">Logout</span>
        </a>
    </div>
</aside>
