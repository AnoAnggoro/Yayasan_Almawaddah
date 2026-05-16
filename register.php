<?php
session_start();
require_once 'config/database.php';

// Redirect if already logged in
if (isset($_SESSION['user_id'])) {
    header('Location: pages/dashboard.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($nama_lengkap) || empty($email) || empty($password)) {
        $error = 'Semua field harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } else {
        // Check if username exists
        $stmt = $db->prepare("SELECT id FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $error = 'Username sudah digunakan!';
        } else {
            // Check if email exists
            $stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $error = 'Email sudah terdaftar!';
            } else {
                // Insert new user (default role: operator, status: Tidak Aktif - waiting approval)
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $role = 'operator';
                $status = 'Tidak Aktif'; // Need admin approval
                
                $stmt = $db->prepare("INSERT INTO users (username, nama_lengkap, email, password, role, status) VALUES (:username, :nama_lengkap, :email, :password, :role, :status)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':nama_lengkap', $nama_lengkap);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':status', $status);
                
                if ($stmt->execute()) {
                    $success = 'Registrasi berhasil! Menunggu persetujuan admin untuk aktivasi akun.';
                } else {
                    $error = 'Terjadi kesalahan saat registrasi!';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registrasi - Yayasan Al Mawaddah</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container" style="max-width: 500px;">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo-circle">
                        <i class="icon-graduation"></i>
                    </div>
                    <h1>Registrasi Akun</h1>
                    <p>Yayasan Al Mawaddah</p>
                </div>

                <?php if ($error): ?>
                <div class="alert-error">
                    <span>⚠️</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
                <?php endif; ?>

                <?php if ($success): ?>
                <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px; font-size: 14px;">
                    <span>✅</span>
                    <span><?= htmlspecialchars($success) ?></span>
                </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Username" required 
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="nama_lengkap">Nama Lengkap</label>
                        <input type="text" id="nama_lengkap" name="nama_lengkap" placeholder="Nama Lengkap" required
                               value="<?= htmlspecialchars($_POST['nama_lengkap'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="email@example.com" required
                               value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                    </div>

                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-input">
                            <input type="password" id="password" name="password" placeholder="Password (min. 6 karakter)" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('password')">
                                <i class="icon-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Konfirmasi Password</label>
                        <div class="password-input">
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Ulangi Password" required>
                            <button type="button" class="toggle-password" onclick="togglePassword('confirm_password')">
                                <i class="icon-eye"></i>
                            </button>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 25px;">
                        <div style="background: #fff3cd; padding: 12px; border-radius: 6px; border: 1px solid #f59e0b;">
                            <small style="color: #92400e; font-size: 13px;">
                                ℹ️ Akun akan diaktifkan setelah disetujui oleh administrator
                            </small>
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        <i class="icon-user"></i> Daftar Sekarang
                    </button>
                </form>

                <div class="footer-text" style="margin-top: 20px;">
                    <p>Sudah punya akun? <a href="index.php" style="color: #10b981; text-decoration: none; font-weight: 600;">Login di sini</a></p>
                </div>

                <div class="footer-text">
                    <p>&copy; 2024 Yayasan Al Mawaddah. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            field.type = field.type === 'password' ? 'text' : 'password';
        }
    </script>
</body>
</html>
