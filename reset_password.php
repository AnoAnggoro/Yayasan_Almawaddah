<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';
$valid_token = false;
$user_id = null;

// Check token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Debug: Cek token yang diterima
    error_log("Token received: " . $token);
    
    $stmt = $db->prepare("SELECT id, username, reset_expires FROM users WHERE reset_token = :token");
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Cek apakah token masih valid (belum expired)
        if ($user['reset_expires'] && strtotime($user['reset_expires']) > time()) {
            $valid_token = true;
            $user_id = $user['id'];
        } else {
            $error = 'Link reset password sudah kedaluwarsa! Silakan minta link baru.';
        }
    } else {
        $error = 'Link reset password tidak valid!';
    }
} else {
    $error = 'Token tidak ditemukan!';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $valid_token) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($password)) {
        $error = 'Password harus diisi!';
    } elseif (strlen($password) < 6) {
        $error = 'Password minimal 6 karakter!';
    } elseif ($password !== $confirm_password) {
        $error = 'Password dan konfirmasi password tidak cocok!';
    } else {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear token
        $stmt = $db->prepare("UPDATE users SET password = :password, reset_token = NULL, reset_expires = NULL WHERE id = :id");
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':id', $user_id);
        
        if ($stmt->execute()) {
            $success = 'Password berhasil direset! Silakan login dengan password baru.';
            $valid_token = false; // Prevent form from showing again
        } else {
            $error = 'Terjadi kesalahan. Silakan coba lagi!';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Yayasan Al Mawaddah</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <div class="login-page">
        <div class="login-container">
            <div class="login-card">
                <div class="login-header">
                    <div class="logo-circle">
                        <i class="icon-graduation"></i>
                    </div>
                    <h1>Reset Password</h1>
                    <p>Masukkan password baru Anda</p>
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
                    <br><br>
                    <a href="index.php" class="btn-login" style="display: inline-block; padding: 10px 20px; margin-top: 10px;">
                        Login Sekarang
                    </a>
                </div>
                <?php endif; ?>

                <?php if ($valid_token && !$success): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="password">Password Baru</label>
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

                    <button type="submit" class="btn-login">
                        <i class="icon-key"></i> Reset Password
                    </button>
                </form>
                <?php endif; ?>

                <div class="footer-text" style="margin-top: 20px;">
                    <p><a href="index.php" style="color: #10b981; text-decoration: none; font-weight: 600;">← Kembali ke Login</a></p>
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
