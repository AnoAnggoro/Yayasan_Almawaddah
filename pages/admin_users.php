<?php
require_once '../config/session.php';
require_once '../config/database.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

$success = '';
$error = '';

// Handle reset password
if (isset($_POST['reset_password'])) {
    $user_id = $_POST['user_id'];
    // Password acak sekali pakai, bukan password statis yang sama untuk semua orang.
    $default_password = bin2hex(random_bytes(5));
    $hashed_password = password_hash($default_password, PASSWORD_DEFAULT);
    
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE id = :id");
    $stmt->bindParam(':password', $hashed_password);
    $stmt->bindParam(':id', $user_id);
    
    if ($stmt->execute()) {
        $success = "Password baru: <strong>" . e($default_password) . "</strong> &mdash; catat sekarang, hanya ditampilkan sekali. Minta pemiliknya segera menggantinya.";
    } else {
        $error = 'Gagal mereset password!';
    }
}

// Get all users
$stmt = $db->query("SELECT id, username, nama_lengkap, email, role, status, created_at FROM users ORDER BY created_at DESC");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola User - Yayasan Al Mawaddah</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    
    <div class="container">
        <div class="page-header">
            <h1>Kelola Akun User</h1>
            <p>Daftar semua akun pengguna sistem</p>
        </div>

        <?php if ($success): ?>
        <div style="background: #d1fae5; border: 1px solid #10b981; color: #065f46; padding: 12px 15px; border-radius: 8px; margin-bottom: 20px;">
            <?= $success ?>
        </div>
        <?php endif; ?>

        <?php if ($error): ?>
        <div class="alert-error">
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>

        <div style="background: #fff3cd; padding: 15px; border-radius: 8px; border: 1px solid #f59e0b; margin-bottom: 20px;">
            <strong>⚠️ Catatan Keamanan:</strong><br>
            <small>Password yang tersimpan dalam bentuk hash (terenkripsi) tidak dapat dilihat. Anda hanya dapat mereset password ke default: <strong>password123</strong></small>
        </div>

        <div class="card">
            <div class="card-body">
                <div style="overflow-x: auto;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Nama Lengkap</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Terdaftar</th>
                                <th>Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?= e($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['username']) ?></td>
                                <td><?= htmlspecialchars($user['nama_lengkap']) ?></td>
                                <td><?= htmlspecialchars($user['email']) ?></td>
                                <td>
                                    <span style="background: <?= $user['role'] === 'admin' ? '#3b82f6' : '#10b981' ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                        <?= ucfirst($user['role']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span style="background: <?= $user['status'] === 'Aktif' ? '#10b981' : '#ef4444' ?>; color: white; padding: 4px 12px; border-radius: 12px; font-size: 12px;">
                                        <?= e($user['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('d/m/Y', strtotime($user['created_at'])) ?></td>
                                <td>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Reset password ke default: password123?');">
                <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= e($user['id']) ?>">
                                        <button type="submit" name="reset_password" class="btn-action btn-warning" title="Reset Password">
                                            🔑 Reset
                                        </button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <style>
        .btn-action {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            margin: 0 2px;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
        }
        .btn-warning:hover {
            background: #d97706;
        }
    </style>
</body>
</html>
