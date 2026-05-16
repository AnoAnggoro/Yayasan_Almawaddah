<?php
$user = getUserData();
$nama_lengkap = $_SESSION['nama_lengkap'] ?? $_SESSION['username'] ?? 'User';
$role = $_SESSION['role'] ?? 'operator';

// Get initials from nama_lengkap
function getInitials($name) {
    $words = explode(' ', trim($name));
    if (count($words) >= 2) {
        return strtoupper(substr($words[0], 0, 1) . substr($words[1], 0, 1));
    }
    return strtoupper(substr($name, 0, 2));
}
$initials = getInitials($nama_lengkap);
?>
<header class="header">
    <div class="header-left">
        <div class="header-logo">
            <img src="../assets/img/logo_almawaddah.png" alt="Logo Yayasan Al Mawaddah" style="width: 50px; height: 50px; object-fit: contain; border-radius: 8px;">
        </div>
        <div class="header-title">
            <h1 style="font-size: 18px; margin: 0; line-height: 1.3;">Yayasan Al Mawaddah</h1>
            <p style="font-size: 12px; margin: 3px 0 0 0; opacity: 0.9;">Sistem Informasi Manajemen Pendidikan</p>
        </div>
    </div>
    <div class="header-right">
        <div class="user-info">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 8px;">
                <div style="width: 40px; height: 40px; background: rgba(255,255,255,0.3); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 14px; font-weight: bold; border: 2px solid rgba(255,255,255,0.5); color: white;">
                    <?= $initials ?>
                </div>
                <div style="text-align: left;">
                    <div style="font-size: 14px; font-weight: 600; margin-bottom: 2px;">
                        <?= htmlspecialchars($nama_lengkap) ?>
                    </div>
                    <div style="font-size: 11px; opacity: 0.85; background: rgba(255,255,255,0.2); padding: 2px 8px; border-radius: 10px; display: inline-block;">
                        <?= $role == 'admin' ? '👑 Administrator' : '🔧 Operator' ?>
                    </div>
                </div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout" onclick="return confirm('Yakin ingin logout?')">
            🚪 Logout
        </a>
    </div>
</header>
