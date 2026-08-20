<?php
session_start();
require_once 'config/database.php';

$database = new Database();
$db = $database->getConnection();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Email harus diisi!';
    } else {
        // Cek apakah email ada
        $stmt = $db->prepare("SELECT id, username FROM users WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Generate token (lebih pendek - 8 karakter)
            $token = bin2hex(random_bytes(4)); // 8 karakter hexadecimal
            $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
            
            // Simpan token ke database
            $stmt = $db->prepare("UPDATE users SET reset_token = :token, reset_expires = :expires WHERE id = :id");
            $stmt->bindParam(':token', $token);
            $stmt->bindParam(':expires', $expires);
            $stmt->bindParam(':id', $user['id']);
            
            if ($stmt->execute()) {
                // Buat link reset (lebih bersih tanpa path penuh)
                $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
                $host = $_SERVER['HTTP_HOST'];
                $base_path = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
                $reset_link = $protocol . "://" . $host . $base_path . "/reset_password.php?token=" . $token;
                
                // TODO: Kirim email (untuk sementara tampilkan link)
                $success = "Link reset password: <br><a href='$reset_link' target='_blank' style='color: #10b981; word-break: break-all;'>$reset_link</a>";
                
                // Log untuk debugging
                error_log("Reset link created: " . $reset_link);
            } else {
                $error = 'Terjadi kesalahan. Silakan coba lagi!';
            }
        } else {
            // Tetap tampilkan pesan sukses untuk keamanan (jangan kasih tahu email tidak terdaftar)
            $success = 'Jika email terdaftar, link reset password telah dikirim.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lupa Password - Yayasan Al Mawaddah</title>
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
                    <h1>Lupa Password</h1>
                    <p>Masukkan email Anda untuk reset password</p>
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
                    <span><?= $success ?></span>
                </div>
                <?php endif; ?>

                <?php if (!$success): ?>
                <form method="POST" action="">
                    <div class="form-group">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" placeholder="Masukkan email Anda" required>
                    </div>

                    <button type="submit" class="btn-login">
                        Kirim Link Reset
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
</body>
</html>
