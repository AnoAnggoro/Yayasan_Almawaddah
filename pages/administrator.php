<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Only admin can access this page
if ($_SESSION['role'] !== 'admin') {
    header('Location: beranda.php');
    exit();
}

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Prevent deleting yourself
    if ($id != $_SESSION['user_id']) {
        $stmt = $db->prepare("DELETE FROM users WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        header("Location: administrator.php?success=delete");
        exit();
    } else {
        header("Location: administrator.php?error=self_delete");
        exit();
    }
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $username = $_POST['username'];
    $nama_lengkap = $_POST['nama_lengkap'];
    $email = $_POST['email'];
    $role = $_POST['role'];
    $password = $_POST['password'] ?? '';
    
    if ($id) {
        // Update
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE users SET username=:username, nama_lengkap=:nama_lengkap, email=:email, role=:role, password=:password WHERE id=:id");
            $stmt->bindParam(':password', $hashed_password);
        } else {
            $stmt = $db->prepare("UPDATE users SET username=:username, nama_lengkap=:nama_lengkap, email=:email, role=:role WHERE id=:id");
        }
        $stmt->bindParam(':id', $id);
    } else {
        // Insert - require password
        if (empty($password)) {
            header("Location: administrator.php?error=password_required");
            exit();
        }
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO users (username, nama_lengkap, email, role, password) VALUES (:username, :nama_lengkap, :email, :role, :password)");
        $stmt->bindParam(':password', $hashed_password);
    }
    
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':nama_lengkap', $nama_lengkap);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    
    header("Location: administrator.php?success=save");
    exit();
}

// Get statistics - FIXED: Remove status column references
$total_users = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_admin = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$total_operator = $db->query("SELECT COUNT(*) FROM users WHERE role = 'operator'")->fetchColumn();

// Get all users
$stmt = $db->query("SELECT * FROM users ORDER BY id DESC");
$users_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Administrator - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>👤 Manajemen Administrator</h2>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👥</div>
                <div class="stat-value"><?= e($total_users) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👑</div>
                <div class="stat-value"><?= e($total_admin) ?></div>
                <div class="stat-label">Administrator</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">🔧</div>
                <div class="stat-value"><?= e($total_operator) ?></div>
                <div class="stat-label">Operator</div>
            </div>
        </div>

        <!-- Button & Search -->
        <div class="content-card">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
                <button type="button" class="btn btn-primary" onclick="openModal()">
                    ➕ Tambah User
                </button>
                
                <input type="text" id="searchInput" placeholder="🔍 Cari username, nama, email, role..." 
                       style="flex: 1; max-width: 400px; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                       onkeyup="searchTable()">
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_GET['success'])): ?>
        <div class="content-card" style="background: #d1fae5; border-left: 4px solid #10b981;">
            <p style="margin: 0; color: #065f46;">
                <strong>✅ Berhasil!</strong>
                <?php if ($_GET['success'] == 'save'): ?>
                    Data berhasil disimpan.
                <?php elseif ($_GET['success'] == 'delete'): ?>
                    User berhasil dihapus.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
        <div class="content-card" style="background: #fee2e2; border-left: 4px solid #ef4444;">
            <p style="margin: 0; color: #991b1b;">
                <strong>❌ Error!</strong>
                <?php if ($_GET['error'] == 'self_delete'): ?>
                    Anda tidak dapat menghapus akun Anda sendiri!
                <?php elseif ($_GET['error'] == 'password_required'): ?>
                    Password harus diisi untuk user baru!
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>

        <!-- Data Table -->
        <div class="content-card">
            <h3>Daftar User</h3>
            
            <?php if (count($users_list) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Username</th>
                            <th>Nama Lengkap</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Dibuat</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users_list as $index => $user): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($user['username']) ?></td>
                            <td><?= htmlspecialchars($user['nama_lengkap'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= $user['role'] == 'admin' ? 'primary' : 'info' ?>">
                                    <?= $user['role'] == 'admin' ? '👑 Admin' : '🔧 Operator' ?>
                                </span>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($user['created_at'])) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="javascript:void(0)" onclick="editUser(<?= htmlspecialchars(json_encode($user)) ?>)" class="btn btn-warning" title="Edit">✏️</a>
                                    <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                    <a href="?delete=<?= e($user['id']) ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus user ini?')" title="Hapus">🗑️</a>
                                    <?php else: ?>
                                    <button class="btn btn-danger" disabled title="Tidak bisa hapus diri sendiri" style="opacity: 0.5; cursor: not-allowed;">🔒</button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>Belum ada data user.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="userModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah User</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="userForm">
                <input type="hidden" name="id" id="user_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Username <span class="required">*</span></label>
                        <input type="text" name="username" id="username" placeholder="username" required>
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama_lengkap" id="nama_lengkap" placeholder="Nama Lengkap" required>
                    </div>

                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="email" placeholder="email@example.com" required>
                    </div>

                    <div class="form-group">
                        <label>Role <span class="required">*</span></label>
                        <select name="role" id="role" required>
                            <option value="">Pilih Role</option>
                            <option value="admin">👑 Administrator</option>
                            <option value="operator">🔧 Operator</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label id="passwordLabel">Password <span class="required">*</span></label>
                        <input type="password" name="password" id="password" placeholder="Minimal 6 karakter">
                        <small style="color: #718096; font-size: 12px;" id="passwordHint">
                            Untuk user baru, password wajib diisi. Untuk edit, kosongkan jika tidak ingin mengubah password.
                        </small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function openModal() {
            document.getElementById('userModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Tambah User';
            document.getElementById('userForm').reset();
            document.getElementById('user_id').value = '';
            document.getElementById('password').required = true;
            document.getElementById('passwordLabel').innerHTML = 'Password <span class="required">*</span>';
        }

        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        function editUser(data) {
            document.getElementById('userModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit User';
            document.getElementById('user_id').value = data.id;
            document.getElementById('username').value = data.username;
            document.getElementById('nama_lengkap').value = data.nama_lengkap || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('role').value = data.role;
            document.getElementById('password').value = '';
            document.getElementById('password').required = false;
            document.getElementById('passwordLabel').innerHTML = 'Password';
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            if (!table) return; // tabel kosong
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                
                // Search in: Username, Nama, Email, Role
                const username = cells[1]?.textContent || '';
                const nama = cells[2]?.textContent || '';
                const email = cells[3]?.textContent || '';
                const role = cells[4]?.textContent || '';
                
                const searchText = (username + nama + email + role).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('userModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        <?php if (isset($_GET['edit']) && $edit_data): ?>
        editUser(<?= json_encode($edit_data) ?>);
        <?php endif; ?>
    </script>
</body>
</html>
