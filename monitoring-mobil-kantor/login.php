<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? AND status = "active" LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['id'];
        flash('success', 'Login berhasil. Selamat datang, ' . $user['name'] . '.');
        redirect('dashboard.php');
    } else {
        $error = 'Email atau password salah.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login - SUCOFINDO FIRST</title>
    <link rel="stylesheet" href="<?= e(base_path('assets/css/style.css')) ?>">
</head>
<body class="auth-page">
    <a class="auth-back" href="<?= e(base_path('index.php')) ?>">← Landing Page</a>
    <main class="auth-shell">
        <div class="auth-logo">🚘</div>
        <h1>SUCOFINDO FIRST</h1>
        <p>Sistem Monitoring Mobil Kantor</p>

        <section class="login-card">
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= e($error) ?></div>
            <?php endif; ?>

            <form method="post" class="grid">
                <div class="form-group">
                    <label>Email / Username</label>
                    <input class="input" type="email" name="email" placeholder="Masukkan email atau username" required autofocus>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input class="input" type="password" name="password" placeholder="Masukkan password" required>
                </div>
                <div class="login-options">
                    <label><input type="checkbox" name="remember"> Ingat saya</label>
                    <a href="#" onclick="alert('Hubungi administrator untuk reset password.'); return false;">Lupa password?</a>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Login</button>
            </form>

            <div class="hint">
                <strong>Akun demo:</strong><br>
                Admin: admin@kantor.local / admin123<br>
                Pengguna: user@kantor.local / user123
            </div>
        </section>
        <footer>© <?= date('Y') ?> Monitoring Mobil Kantor</footer>
    </main>
</body>
</html>
