<?php
require_once 'config/session.php';
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: pages/beranda.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';

$blokir_sampai = $_SESSION['login_blokir'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (time() < $blokir_sampai) {
        $error = 'Terlalu banyak percobaan gagal. Coba lagi ' . ceil(($blokir_sampai - time()) / 60) . ' menit lagi.';
    } elseif (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi!';
    } else {
        $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                if ($user['status'] === 'Aktif') {
                    unset($_SESSION['login_gagal'], $_SESSION['login_blokir']);
                    loginUser($user);
                    
                    header('Location: pages/beranda.php');
                    exit();
                } else {
                    $error = 'Akun Anda belum diaktifkan. Hubungi administrator!';
                }
            } else {
                $error = 'Username atau password salah!';
            }
        } else {
            $error = 'Username atau password salah!';
        }
        
        // ponytail: hitungan percobaan disimpan di session (per-browser) + jeda 0,5 detik.
        // Cukup untuk bot iseng; kalau butuh lebih kuat, catat per-IP di tabel database.
        if ($error === 'Username atau password salah!') {
            usleep(500000);
            $_SESSION['login_gagal'] = ($_SESSION['login_gagal'] ?? 0) + 1;
            if ($_SESSION['login_gagal'] >= 5) {
                $_SESSION['login_blokir'] = time() + 300;
                $_SESSION['login_gagal'] = 0;
                $error = 'Terlalu banyak percobaan gagal. Coba lagi 5 menit lagi.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="manifest.json">
    <link rel="stylesheet" href="assets/css/style.css">

</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo-circle">
                        <img src="assets/img/logo_almawaddah.png" alt="Logo Yayasan Al Mawaddah">
                    </div>
                   <h1>Yayasan al Mawaddah</h1>
                <p>Sistem Informasi Pendidikan & Sosial</p>
                </div>

                <?php if ($error): ?>
                <div class="alert-error">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                <?= csrf_field() ?>
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Username" required>
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input">
                            <input type="password" id="password" name="password" placeholder="Password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword()">
                                <i class="icon-eye"></i>
                            </button>
                        </div>
                    </div>

                       <div class="form-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="remember"> Ingat Saya
                    </label>
                </div>
                    <div class="form-group" style="text-align: right; margin-top: -10px; margin-bottom: 20px;">
                        <a href="forgot_password.php" style="color: #10b981; text-decoration: none; font-size: 14px; font-weight: 500;">Lupa Password?</a>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="icon-login"></i> Login
                    </button>
                </form>

                <div class="footer-text" style="margin-top: 20px;">
                    <p>Belum punya akun? <a href="register.php" style="color: #10b981; text-decoration: none; font-weight: 600;">Daftar di sini</a></p>
                </div>

                <div class="footer-text">
                    <p>&copy; 2024 Yayasan Al Mawaddah. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('password');
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    </script>
    <!-- app.js juga mendaftarkan service worker; index.php ini start_url PWA-nya -->
    <script src="assets/js/app.js"></script>
</body>
</html>
