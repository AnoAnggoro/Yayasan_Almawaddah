<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get edit data if exists
$edit_data = null;
if (isset($_GET['edit'])) {
    $query = "SELECT * FROM pengumuman WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':id', $_GET['edit']);
    $stmt->execute();
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                $query = "INSERT INTO pengumuman (judul, isi, kategori, target, tanggal_berlaku, tanggal_berakhir, created_by) 
                          VALUES (:judul, :isi, :kategori, :target, :tanggal_berlaku, :tanggal_berakhir, :created_by)";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':judul', $_POST['judul']);
                $stmt->bindParam(':isi', $_POST['isi']);
                $stmt->bindParam(':kategori', $_POST['kategori']);
                $stmt->bindParam(':target', $_POST['target']);
                $tanggal_berlaku = !empty($_POST['tanggal_berlaku']) ? $_POST['tanggal_berlaku'] : null;
                $tanggal_berakhir = !empty($_POST['tanggal_berakhir']) ? $_POST['tanggal_berakhir'] : null;
                $stmt->bindParam(':tanggal_berlaku', $tanggal_berlaku);
                $stmt->bindParam(':tanggal_berakhir', $tanggal_berakhir);
                $user_id = $_SESSION['user_id'];
                $stmt->bindParam(':created_by', $user_id);
                $stmt->execute();
                $_SESSION['success'] = 'Pengumuman berhasil ditambahkan';
                break;
                
            case 'edit':
                $query = "UPDATE pengumuman SET 
                          judul = :judul, 
                          isi = :isi, 
                          kategori = :kategori, 
                          target = :target, 
                          tanggal_berlaku = :tanggal_berlaku, 
                          tanggal_berakhir = :tanggal_berakhir 
                          WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':judul', $_POST['judul']);
                $stmt->bindParam(':isi', $_POST['isi']);
                $stmt->bindParam(':kategori', $_POST['kategori']);
                $stmt->bindParam(':target', $_POST['target']);
                $tanggal_berlaku = !empty($_POST['tanggal_berlaku']) ? $_POST['tanggal_berlaku'] : null;
                $tanggal_berakhir = !empty($_POST['tanggal_berakhir']) ? $_POST['tanggal_berakhir'] : null;
                $stmt->bindParam(':tanggal_berlaku', $tanggal_berlaku);
                $stmt->bindParam(':tanggal_berakhir', $tanggal_berakhir);
                $stmt->bindParam(':id', $_POST['id']);
                $stmt->execute();
                $_SESSION['success'] = 'Pengumuman berhasil diupdate';
                break;
                
            case 'delete':
                $query = "DELETE FROM pengumuman WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_POST['id']);
                $stmt->execute();
                $_SESSION['success'] = 'Pengumuman berhasil dihapus';
                break;
                
            case 'toggle_status':
                $query = "UPDATE pengumuman SET status = IF(status='Aktif', 'Nonaktif', 'Aktif') WHERE id = :id";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':id', $_POST['id']);
                $stmt->execute();
                $_SESSION['success'] = 'Status pengumuman berhasil diubah';
                break;
        }
        header('Location: pengumuman.php');
        exit;
    }
}

// Get all announcements
$query = "SELECT p.*, u.username FROM pengumuman p 
          LEFT JOIN users u ON p.created_by = u.id 
          ORDER BY p.created_at DESC";
$stmt = $db->query($query);
$announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kelola Pengumuman - Yayasan Al Mawaddah</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-container {
            display: none;
            margin-top: 15px;
        }
        
        .form-container.show {
            display: block;
            animation: slideDown 0.3s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .btn-toggle {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .btn-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }
        
        .btn-toggle.active {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border-left: 4px solid #10b981;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .badge-kategori {
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-umum { background: #e0f2fe; color: #0369a1; }
        .badge-penting { background: #fef3c7; color: #92400e; }
        .badge-mendesak { background: #fee2e2; color: #991b1b; }
        
        /* Samakan ukuran semua tombol aksi */
        .btn-aksi {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 4px;
            font-size: 12px;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            min-width: 35px;
            text-align: center;
        }
        
        .btn-aksi:hover {
            transform: translateY(-2px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .btn-edit {
            background: #3b82f6;
            color: white;
        }
        
        .btn-edit:hover {
            background: #2563eb;
        }
        
        .btn-status {
            background: #f59e0b;
            color: white;
        }
        
        .btn-status:hover {
            background: #d97706;
        }
        
        .btn-hapus {
            background: #ef4444;
            color: white;
        }
        
        .btn-hapus:hover {
            background: #dc2626;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <h2>📢 Kelola Pengumuman</h2>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            ✅ <?= $_SESSION['success']; unset($_SESSION['success']); ?>
        </div>
        <?php endif; ?>

        <!-- Toggle Button -->
        <div style="margin-bottom: 20px;">
            <button type="button" class="btn-toggle" id="toggleFormBtn" onclick="toggleForm()">
                <span id="toggleIcon">➕</span>
                <span id="toggleText"><?= $edit_data ? 'Edit Pengumuman' : 'Tambah Pengumuman Baru' ?></span>
            </button>
            <?php if ($edit_data): ?>
            <a href="pengumuman.php" class="btn" style="background: #6b7280; color: white; text-decoration: none; padding: 12px 24px; border-radius: 8px; display: inline-block;">
                ❌ Batal Edit
            </a>
            <?php endif; ?>
        </div>

        <!-- Add/Edit Announcement Form -->
        <div class="content-card form-container <?= $edit_data ? 'show' : '' ?>" id="formContainer">
            <h3 style="margin-bottom: 20px; color: #10b981;">
                <?= $edit_data ? '✏️ Edit Pengumuman' : '📝 Form Pengumuman Baru' ?>
            </h3>
            <form method="POST">
                <input type="hidden" name="action" value="<?= $edit_data ? 'edit' : 'add' ?>">
                <?php if ($edit_data): ?>
                <input type="hidden" name="id" value="<?= e($edit_data['id']) ?>">
                <?php endif; ?>
                
                <div class="form-group">
                    <label>Judul Pengumuman <span style="color: red;">*</span></label>
                    <input type="text" name="judul" placeholder="Masukkan judul pengumuman" 
                           value="<?= $edit_data ? htmlspecialchars($edit_data['judul']) : '' ?>" required>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Kategori <span style="color: red;">*</span></label>
                        <select name="kategori" required>
                            <option value="Umum" <?= $edit_data && $edit_data['kategori'] == 'Umum' ? 'selected' : '' ?>>📢 Umum</option>
                            <option value="Penting" <?= $edit_data && $edit_data['kategori'] == 'Penting' ? 'selected' : '' ?>>⚠️ Penting</option>
                            <option value="Mendesak" <?= $edit_data && $edit_data['kategori'] == 'Mendesak' ? 'selected' : '' ?>>🚨 Mendesak</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Target Penerima <span style="color: red;">*</span></label>
                        <select name="target" required>
                            <option value="Semua" <?= $edit_data && $edit_data['target'] == 'Semua' ? 'selected' : '' ?>>👥 Semua</option>
                            <option value="Guru" <?= $edit_data && $edit_data['target'] == 'Guru' ? 'selected' : '' ?>>👨‍🏫 Guru</option>
                            <option value="Orang Tua" <?= $edit_data && $edit_data['target'] == 'Orang Tua' ? 'selected' : '' ?>>👨‍👩‍👧 Orang Tua</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label>Tanggal Berlaku</label>
                        <input type="date" name="tanggal_berlaku" 
                               value="<?= $edit_data ? $edit_data['tanggal_berlaku'] : '' ?>">
                        <small style="color: #666;">Kosongkan jika berlaku sekarang</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Tanggal Berakhir</label>
                        <input type="date" name="tanggal_berakhir" 
                               value="<?= $edit_data ? $edit_data['tanggal_berakhir'] : '' ?>">
                        <small style="color: #666;">Kosongkan jika tidak ada batas waktu</small>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Isi Pengumuman <span style="color: red;">*</span></label>
                    <textarea name="isi" rows="6" placeholder="Masukkan isi pengumuman" required><?= $edit_data ? htmlspecialchars($edit_data['isi']) : '' ?></textarea>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        💾 <?= $edit_data ? 'Update' : 'Simpan' ?> Pengumuman
                    </button>
                    <button type="button" class="btn" style="background: #6b7280;" onclick="toggleForm()">❌ Batal</button>
                </div>
            </form>
        </div>

        <!-- List of Announcements -->
        <div class="content-card">
            <h3 style="margin-bottom: 20px;">📋 Daftar Pengumuman</h3>
            
            <?php if (count($announcements) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Judul</th>
                            <th>Kategori</th>
                            <th>Target</th>
                            <th>Isi</th>
                            <th>Periode</th>
                            <th>Tanggal Posting</th>
                            <th>Dibuat Oleh</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($announcements as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><strong><?= htmlspecialchars($row['judul']) ?></strong></td>
                            <td>
                                <span class="badge-kategori badge-<?= strtolower($row['kategori']) ?>">
                                    <?= e($row['kategori']) ?>
                                </span>
                            </td>
                            <td><?= e($row['target']) ?></td>
                            <td><?= substr(htmlspecialchars($row['isi']), 0, 80) ?><?= strlen($row['isi']) > 80 ? '...' : '' ?></td>
                            <td>
                                <?php if ($row['tanggal_berlaku'] || $row['tanggal_berakhir']): ?>
                                    <?= $row['tanggal_berlaku'] ? date('d/m/Y', strtotime($row['tanggal_berlaku'])) : '-' ?>
                                    <br>s/d<br>
                                    <?= $row['tanggal_berakhir'] ? date('d/m/Y', strtotime($row['tanggal_berakhir'])) : '-' ?>
                                <?php else: ?>
                                    <span style="color: #999;">Tidak terbatas</span>
                                <?php endif; ?>
                            </td>
                            <td><?= date('d/m/Y H:i', strtotime($row['created_at'])) ?></td>
                            <td><?= htmlspecialchars($row['username'] ?? '-') ?></td>
                            <td>
                                <span class="badge <?= $row['status'] == 'Aktif' ? 'badge-success' : 'badge-secondary' ?>">
                                    <?= e($row['status']) ?>
                                </span>
                            </td>
                            <td style="white-space: nowrap;">
                                <a href="?edit=<?= e($row['id']) ?>" class="btn-aksi btn-edit" title="Edit">
                                    ✏️
                                </a>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button type="submit" class="btn-aksi btn-status" title="Ubah Status">
                                        <?= $row['status'] == 'Aktif' ? '🔒' : '✅' ?>
                                    </button>
                                </form>
                                <form method="POST" style="display: inline;" onsubmit="return confirm('Yakin ingin menghapus pengumuman ini?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                    <button type="submit" class="btn-aksi btn-hapus" title="Hapus">🗑️</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                <p>Belum ada pengumuman</p>
                <p style="font-size: 14px; color: #999;">Klik tombol "Tambah Pengumuman Baru" untuk membuat pengumuman pertama</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function toggleForm() {
            const formContainer = document.getElementById('formContainer');
            const toggleBtn = document.getElementById('toggleFormBtn');
            const toggleIcon = document.getElementById('toggleIcon');
            const toggleText = document.getElementById('toggleText');
            
            if (formContainer.classList.contains('show')) {
                formContainer.classList.remove('show');
                toggleBtn.classList.remove('active');
                toggleIcon.textContent = '➕';
                toggleText.textContent = 'Tambah Pengumuman Baru';
            } else {
                formContainer.classList.add('show');
                toggleBtn.classList.add('active');
                toggleIcon.textContent = '❌';
                toggleText.textContent = 'Tutup Form';
            }
        }
        
        // Auto show form if editing
        <?php if ($edit_data): ?>
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('formContainer').classList.add('show');
        });
        <?php endif; ?>
    </script>
</body>
</html>
