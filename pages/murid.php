<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM murid WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header("Location: murid.php?success=delete");
    exit();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nisn = $_POST['nisn'];
    $nik = $_POST['nik'];
    $nama = $_POST['nama'];
    $tingkat = $_POST['tingkat'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $nama_ibu_kandung = $_POST['nama_ibu_kandung'];
    $alamat = $_POST['alamat'];
    $status_murid = $_POST['status_murid'] ?? 'Aktif';
    $tahun_masuk = $_POST['tahun_masuk'] ?? null;

    // Handle foto upload
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'murid_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = '../uploads/murid/' . $new_filename;
            
            if (!is_dir('../uploads/murid')) {
                mkdir('../uploads/murid', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                $foto = $new_filename;
                
                if ($id) {
                    $stmt_old = $db->prepare("SELECT foto FROM murid WHERE id = :id");
                    $stmt_old->bindParam(':id', $id);
                    $stmt_old->execute();
                    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
                    if ($old_data && $old_data['foto'] && file_exists('../uploads/murid/' . $old_data['foto'])) {
                        unlink('../uploads/murid/' . $old_data['foto']);
                    }
                }
            }
        }
    }

    if ($id) {
        // Update
        if ($foto) {
            $stmt = $db->prepare("UPDATE murid SET nisn=:nisn, nik=:nik, nama=:nama, tingkat=:tingkat, jenis_kelamin=:jk, tempat_lahir=:tempat_lahir, tanggal_lahir=:tanggal_lahir, nama_ibu_kandung=:nama_ibu_kandung, alamat=:alamat, foto=:foto, status_murid=:status, tahun_masuk=:tahun_masuk WHERE id=:id");
            $stmt->bindParam(':foto', $foto);
        } else {
            $stmt = $db->prepare("UPDATE murid SET nisn=:nisn, nik=:nik, nama=:nama, tingkat=:tingkat, jenis_kelamin=:jk, tempat_lahir=:tempat_lahir, tanggal_lahir=:tanggal_lahir, nama_ibu_kandung=:nama_ibu_kandung, alamat=:alamat, status_murid=:status, tahun_masuk=:tahun_masuk WHERE id=:id");
        }
        $stmt->bindParam(':id', $id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO murid (nisn, nik, nama, tingkat, jenis_kelamin, tempat_lahir, tanggal_lahir, nama_ibu_kandung, alamat, foto, status_murid, tahun_masuk) VALUES (:nisn, :nik, :nama, :tingkat, :jk, :tempat_lahir, :tanggal_lahir, :nama_ibu_kandung, :alamat, :foto, :status, :tahun_masuk)");
        $stmt->bindParam(':foto', $foto);
    }
    
    $stmt->bindParam(':nisn', $nisn);
    $stmt->bindParam(':nik', $nik);
    $stmt->bindParam(':nama', $nama);
    $stmt->bindParam(':tingkat', $tingkat);
    $stmt->bindParam(':jk', $jenis_kelamin);
    $stmt->bindParam(':tempat_lahir', $tempat_lahir);
    $stmt->bindParam(':tanggal_lahir', $tanggal_lahir);
    $stmt->bindParam(':nama_ibu_kandung', $nama_ibu_kandung);
    $stmt->bindParam(':alamat', $alamat);
    $stmt->bindParam(':status', $status_murid);
    $stmt->bindParam(':tahun_masuk', $tahun_masuk);
    $stmt->execute();
    
    header("Location: murid.php?success=save");
    exit();
}

// Get statistics for info card - UPDATED
$total_murid = $db->query("SELECT COUNT(*) FROM murid")->fetchColumn();
$total_aktif = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL")->fetchColumn();
$total_lulus = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Lulus'")->fetchColumn();
$total_pindah = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Pindah'")->fetchColumn();
$total_keluar = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Keluar'")->fetchColumn();

// Get current academic year
$current_month = date('n');
$current_year = date('Y');
$tahun_ajaran_sekarang = $current_month >= 7 ? $current_year . '/' . ($current_year + 1) : ($current_year - 1) . '/' . $current_year;

// Get statistics by angkatan
$stmt_angkatan = $db->query("SELECT tahun_masuk, COUNT(*) as jumlah FROM murid WHERE tahun_masuk IS NOT NULL GROUP BY tahun_masuk ORDER BY tahun_masuk DESC");
$stats_angkatan = $stmt_angkatan->fetchAll(PDO::FETCH_ASSOC);

// Filter
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_status = $_GET['status_murid'] ?? '';
$filter_tahun_angkatan = $_GET['tahun_angkatan'] ?? ''; // NEW FILTER

$where_clause = "WHERE 1=1";
if ($filter_tingkat) {
    $where_clause .= " AND tingkat = :tingkat";
}
if ($filter_status) {
    if ($filter_status == 'Aktif') {
        $where_clause .= " AND (status_murid = 'Aktif' OR status_murid IS NULL)";
    } elseif ($filter_status == 'Tidak Aktif') {
        $where_clause .= " AND status_murid IN ('Lulus', 'Pindah', 'Keluar')";
    } else {
        $where_clause .= " AND status_murid = :status_spesifik";
    }
}
// NEW: Filter by tahun angkatan
if ($filter_tahun_angkatan) {
    $where_clause .= " AND tahun_masuk = :tahun_angkatan";
}

// Get all murid
$query = "SELECT * FROM murid $where_clause ORDER BY nama";
$stmt = $db->prepare($query);
if ($filter_tingkat) {
    $stmt->bindParam(':tingkat', $filter_tingkat);
}
if ($filter_status && !in_array($filter_status, ['Aktif', 'Tidak Aktif', ''])) {
    $stmt->bindParam(':status_spesifik', $filter_status);
}
if ($filter_tahun_angkatan) {
    $stmt->bindParam(':tahun_angkatan', $filter_tahun_angkatan);
}
$stmt->execute();
$murid_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM murid WHERE id = :id");
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
    <title>Daftar Murid - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-student"></i>
            <h2>👨‍🎓 Daftar Murid</h2>
        </div>

        <!-- Statistics Cards - SIMPLE VERSION -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= e($total_aktif) ?></div>
                <div class="stat-label">Murid Aktif</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">🎓</div>
                <div class="stat-value"><?= e($total_lulus) ?></div>
                <div class="stat-label">Lulus</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">🚚</div>
                <div class="stat-value"><?= e($total_pindah) ?></div>
                <div class="stat-label">Pindah</div>
            </div>
            <div class="stat-card info">
                <div class="stat-icon">🚪</div>
                <div class="stat-value"><?= e($total_keluar) ?></div>
                <div class="stat-label">Keluar</div>
            </div>
        </div>

        <!-- Angkatan Statistics - SIMPLE VERSION -->
        <div class="content-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 15px 0;">📚 Per Angkatan</h3>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <?php foreach ($stats_angkatan as $angkatan): ?>
                    <div style="background: #f3f4f6; padding: 8px 16px; border-radius: 6px; border: 1px solid #e5e7eb;">
                        <strong style="color: #1f2937;"><?= e($angkatan['tahun_masuk']) ?></strong>: 
                        <span style="color: #64748b;"><?= e($angkatan['jumlah']) ?> murid</span>
                        <?php if ($angkatan['tahun_masuk'] == $tahun_ajaran_sekarang): ?>
                            <span style="background: #10b981; color: white; padding: 2px 8px; border-radius: 4px; margin-left: 6px; font-size: 11px;">Aktif</span>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Button Tambah & Filter - UPDATED with Angkatan -->
        <div class="content-card">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
                <button type="button" class="btn btn-primary" onclick="openModal()">
                    ➕ Tambah Murid
                </button>
                
                <div style="display: flex; gap: 10px; align-items: center; flex: 1; max-width: 800px;">
                    <input type="text" id="searchInput" placeholder="🔍 Cari NISN, NIK, nama, tingkat, alamat, status, angkatan..." 
                           style="flex: 1; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                           onkeyup="searchTable()">
                    
                    <select id="filterTingkat" onchange="filterData()" style="padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Semua Tingkat</option>
                        <option value="Kelompok A" <?= $filter_tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                        <option value="Kelompok B" <?= $filter_tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                    </select>
                    
                    <select id="filterStatus" onchange="filterData()" style="padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Semua Status</option>
                        <option value="Aktif" <?= $filter_status == 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                        <option value="Tidak Aktif" <?= $filter_status == 'Tidak Aktif' ? 'selected' : '' ?>>📁 Arsip</option>
                        <option value="Lulus" <?= $filter_status == 'Lulus' ? 'selected' : '' ?>>🎓 Lulus</option>
                        <option value="Pindah" <?= $filter_status == 'Pindah' ? 'selected' : '' ?>>🚚 Pindah</option>
                        <option value="Keluar" <?= $filter_status == 'Keluar' ? 'selected' : '' ?>>🚪 Keluar</option>
                    </select>
                    
                    <!-- NEW: Filter Tahun Angkatan -->
                    <select id="filterAngkatan" onchange="filterData()" style="padding: 10px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="">Semua Angkatan</option>
                        <?php foreach ($stats_angkatan as $angkatan): ?>
                        <option value="<?= e($angkatan['tahun_masuk']) ?>" <?= $filter_tahun_angkatan == $angkatan['tahun_masuk'] ? 'selected' : '' ?>>
                            📚 <?= e($angkatan['tahun_masuk']) ?> (<?= e($angkatan['jumlah']) ?> murid)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- Data Table - UPDATE header badges -->
        <div class="content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Daftar Murid
                    <?php if ($filter_status): ?>
                        <span class="badge badge-<?= 
                            $filter_status == 'Aktif' ? 'success' : 
                            ($filter_status == 'Lulus' ? 'primary' : 'danger')
                        ?>" style="font-size: 12px; margin-left: 10px;">
                            <?= e($filter_status) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_tahun_angkatan): ?>
                        <span class="badge badge-purple" style="font-size: 12px; margin-left: 10px;">
                            📚 Angkatan <?= e($filter_tahun_angkatan) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_tingkat): ?>
                        <span class="badge badge-info" style="font-size: 12px; margin-left: 10px;">
                            <?= e($filter_tingkat) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="font-size: 14px; color: #64748b;">
                    Total: <strong><?= count($murid_list) ?></strong> murid
                </div>
            </div>
            
            <?php if (count($murid_list) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>Tingkat</th>
                            <th>Jenis Kelamin</th>
                            <th>Tempat, Tanggal Lahir</th>
                            <th>Nama Ibu Kandung</th>
                            <th>Alamat</th>
                            <th>Angkatan</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($murid_list as $index => $murid): 
                            $status_murid = $murid['status_murid'] ?? 'Aktif';
                            $is_non_aktif = in_array($status_murid, ['Lulus', 'Pindah', 'Keluar']);
                            
                            // Calculate info angkatan
                            $angkatan_info = '-';
                            $is_old_angkatan = false;
                            if ($murid['tahun_masuk']) {
                                $angkatan_info = $murid['tahun_masuk'];
                                $is_old_angkatan = $murid['tahun_masuk'] != $tahun_ajaran_sekarang;
                            }
                        ?>
                        <tr <?= $is_non_aktif ? 'style="background: #fee2e2;"' : ($is_old_angkatan ? 'style="background: #fef3c7;"' : '') ?>>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($murid['nisn']) ?></td>
                            <td><?= htmlspecialchars($murid['nik']) ?></td>
                            <td><?= htmlspecialchars($murid['nama']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $murid['tingkat'] == 'Kelompok A' ? 'primary' : 
                                    ($murid['tingkat'] == 'Kelompok B' ? 'purple' : 
                                    ($murid['tingkat'] == 'TK A' ? 'success' : 'pink')) 
                                ?>">
                                    <?= e($murid['tingkat']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= $murid['jenis_kelamin'] == 'L' ? 'info' : 'pink' ?>">
                                    <?= $murid['jenis_kelamin'] == 'L' ? 'Laki-laki' : 'Perempuan' ?>
                                </span>
                            </td>
                            <td>
                                <?= htmlspecialchars($murid['tempat_lahir']) ?>, 
                                <?= date('d-m-Y', strtotime($murid['tanggal_lahir'])) ?>
                            </td>
                            <td><?= htmlspecialchars($murid['nama_ibu_kandung']) ?></td>
                            <td><?= htmlspecialchars($murid['alamat']) ?></td>
                            <td>
                                <strong><?= e($angkatan_info) ?></strong>
                                <?php if ($is_old_angkatan): ?>
                                    <br><small style="color: #92400e;">📁 Angkatan Lama</small>
                                <?php elseif ($murid['tahun_masuk'] == $tahun_ajaran_sekarang): ?>
                                    <br><small style="color: #10b981;">✨ Angkatan Aktif</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge badge-<?= 
                                    $murid['status_murid'] == 'Aktif' ? 'success' : 
                                    ($murid['status_murid'] == 'Lulus' ? 'primary' : 'danger')
                                ?>">
                                    <?= e($murid['status_murid'] ?? 'Aktif') ?>
                                </span>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="javascript:void(0)" onclick="viewMurid(<?= htmlspecialchars(json_encode($murid)) ?>)" class="btn btn-success" title="Lihat Detail">👁️</a>
                                    <a href="javascript:void(0)" onclick="editMurid(<?= htmlspecialchars(json_encode($murid)) ?>)" class="btn btn-warning" title="Edit">✏️</a>
                                    <a href="?delete=<?= e($murid['id']) ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data ini?')" title="Hapus">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Info Messages -->
            <?php if ($filter_status == 'Tidak Aktif' || in_array($filter_status, ['Lulus', 'Pindah', 'Keluar'])): ?>
            <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                <strong>📁 Info Arsip:</strong> Menampilkan murid dengan status: <strong><?= e($filter_status) ?></strong>
            </div>
            <?php else: ?>
                <?php 
                $count_non_aktif = 0;
                $count_old_angkatan = 0;
                foreach ($murid_list as $m) {
                    if (in_array($m['status_murid'] ?? 'Aktif', ['Lulus', 'Pindah', 'Keluar'])) {
                        $count_non_aktif++;
                    }
                    if ($m['tahun_masuk'] && $m['tahun_masuk'] != $tahun_ajaran_sekarang) {
                        $count_old_angkatan++;
                    }
                }
                ?>
                <?php if ($count_non_aktif > 0): ?>
                <div style="margin-top: 15px; padding: 12px; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 6px;">
                    <strong>⚠️ Perhatian:</strong> Terdapat <strong><?= e($count_non_aktif) ?></strong> murid tidak aktif (latar merah)
                </div>
                <?php endif; ?>
                <?php if ($count_old_angkatan > 0): ?>
                <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                    <strong>📁 Info:</strong> Terdapat <strong><?= e($count_old_angkatan) ?></strong> murid angkatan lama (latar kuning)
                </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                <p>Tidak ada data murid
                    <?php if ($filter_status): ?>
                        dengan status <strong><?= e($filter_status) ?></strong>
                    <?php endif; ?>
                    <?php if ($filter_tahun_angkatan): ?>
                        angkatan <strong><?= e($filter_tahun_angkatan) ?></strong>
                    <?php endif; ?>
                </p>
                <?php if ($filter_status || $filter_tingkat || $filter_tahun_angkatan): ?>
                <a href="murid.php" class="btn btn-primary" style="margin-top: 15px; text-decoration: none;">
                    🔄 Reset Filter
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Form Add/Edit -->
    <div id="muridModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Murid</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="muridForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="murid_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Foto Siswa</label>
                        <div style="text-align: center; margin-bottom: 15px;">
                            <img id="preview_foto" src="../assets/img/default-student.svg" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0;">
                        </div>
                        <input type="file" name="foto" id="foto" accept="image/*" onchange="previewImage(this)">
                        <small style="color: #718096; font-size: 12px;">Format: JPG, PNG, GIF (Max 2MB)</small>
                    </div>
                    <div class="form-group">
                        <label>NISN <span class="required">*</span></label>
                        <input type="text" name="nisn" id="nisn" placeholder="0051234567" required>
                    </div>

                    <div class="form-group">
                        <label>NIK <span class="required">*</span></label>
                        <input type="text" name="nik" id="nik" placeholder="3174012015050001" required>
                    </div>

                    <div class="form-group">
                        <label>Nama Lengkap <span class="required">*</span></label>
                        <input type="text" name="nama" id="nama" placeholder="Nama Lengkap" required>
                    </div>

                    <div class="form-group">
                        <label>Tingkat <span class="required">*</span></label>
                        <select name="tingkat" id="tingkat" required>
                            <option value="">Pilih Tingkat</option>
                            <option value="Kelompok A">Kelompok A</option>
                            <option value="Kelompok B">Kelompok B</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jenis Kelamin <span class="required">*</span></label>
                        <select name="jenis_kelamin" id="jenis_kelamin" required>
                            <option value="">Pilih Jenis Kelamin</option>
                            <option value="L">Laki-laki</option>
                            <option value="P">Perempuan</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Tempat Lahir <span class="required">*</span></label>
                        <input type="text" name="tempat_lahir" id="tempat_lahir" placeholder="Jakarta" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir" required>
                    </div>

                    <div class="form-group">
                        <label>Nama Ibu Kandung <span class="required">*</span></label>
                        <input type="text" name="nama_ibu_kandung" id="nama_ibu_kandung" placeholder="Nama Ibu Kandung" required>
                    </div>

                    <div class="form-group">
                        <label>Alamat <span class="required">*</span></label>
                        <textarea name="alamat" id="alamat" rows="3" placeholder="Alamat Lengkap" required></textarea>
                    </div>

                    <div class="form-group">
                        <label>Status Murid <span class="required">*</span></label>
                        <select name="status_murid" id="status_murid" required onchange="confirmStatusChange()">
                            <option value="Aktif">Aktif</option>
                            <option value="Lulus">Lulus</option>
                            <option value="Pindah">Pindah</option>
                            <option value="Keluar">Keluar</option>
                        </select>
                        <small style="color: #718096; font-size: 12px;">
                            ⚠️ Mengubah status ke "Lulus/Pindah/Keluar" akan menghentikan pembayaran SPP otomatis
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Tahun Masuk / Angkatan</label>
                        <select name="tahun_masuk" id="tahun_masuk">
                            <option value="">Pilih Tahun Masuk</option>
                            <?php 
                            $current_year = date('Y');
                            $current_month = date('n');
                            
                            // Jika bulan >= Juli, tahun ajaran dimulai dari tahun ini
                            $start_year = $current_month >= 7 ? $current_year : $current_year - 1;
                            
                            // Generate 5 tahun ke belakang dan 2 tahun ke depan
                            for ($y = $start_year + 2; $y >= $start_year - 5; $y--): 
                                $tahun_ajaran = $y . '/' . ($y + 1);
                            ?>
                            <option value="<?= e($tahun_ajaran) ?>"><?= e($tahun_ajaran) ?></option>
                            <?php endfor; ?>
                        </select>
                        <small style="color: #718096; font-size: 12px;">Tahun ajaran saat murid pertama masuk</small>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail View -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>Detail Murid</h3>
                <button type="button" class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img id="view_foto" src="../assets/img/default-student.svg" 
                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #3b82f6;">
                </div>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>NISN</label>
                        <p id="view_nisn"></p>
                    </div>
                    <div class="detail-item">
                        <label>NIK</label>
                        <p id="view_nik"></p>
                    </div>
                    <div class="detail-item">
                        <label>Nama Lengkap</label>
                        <p id="view_nama"></p>
                    </div>
                    <div class="detail-item">
                        <label>Tingkat</label>
                        <p id="view_tingkat"></p>
                    </div>
                    <div class="detail-item">
                        <label>Jenis Kelamin</label>
                        <p id="view_jenis_kelamin"></p>
                    </div>
                    <div class="detail-item">
                        <label>Tempat, Tanggal Lahir</label>
                        <p id="view_ttl"></p>
                    </div>
                    <div class="detail-item">
                        <label>Nama Ibu Kandung</label>
                        <p id="view_ibu"></p>
                    </div>
                    <div class="detail-item full-width">
                        <label>Alamat</label>
                        <p id="view_alamat"></p>
                    </div>
                    <div class="detail-item">
                        <label>Status Murid</label>
                        <p id="view_status_murid"></p>
                    </div>
                    <div class="detail-item">
                        <label>Tahun Masuk / Angkatan</label>
                        <p id="view_tahun_masuk"></p>
                    </div>
                </div>

                
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeViewModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function openModal() {
            document.getElementById('muridModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Tambah Murid';
            document.getElementById('muridForm').reset();
            document.getElementById('murid_id').value = '';
        }

        function closeModal() {
            document.getElementById('muridModal').style.display = 'none';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_foto').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        let originalStatus = 'Aktif';

        function confirmStatusChange() {
            const select = document.getElementById('status_murid');
            const newStatus = select.value;
            
            if (originalStatus === 'Aktif' && newStatus !== 'Aktif') {
                const confirmed = confirm(
                    `⚠️ PERHATIAN!\n\n` +
                    `Mengubah status murid ke "${newStatus}" akan:\n` +
                    `• Menghentikan pembayaran SPP otomatis\n` +
                    `• Murid tidak akan muncul di daftar "Belum Bayar"\n` +
                    `• Data absensi tetap bisa diinput untuk arsip\n\n` +
                    `Apakah Anda yakin?`
                );
                
                if (!confirmed) {
                    select.value = originalStatus;
                }
            }
        }

        function editMurid(data) {
            document.getElementById('muridModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit Murid';
            document.getElementById('murid_id').value = data.id;
            document.getElementById('nisn').value = data.nisn;
            document.getElementById('nik').value = data.nik;
            document.getElementById('nama').value = data.nama;
            document.getElementById('tingkat').value = data.tingkat;
            document.getElementById('jenis_kelamin').value = data.jenis_kelamin;
            document.getElementById('tempat_lahir').value = data.tempat_lahir;
            document.getElementById('tanggal_lahir').value = data.tanggal_lahir;
            document.getElementById('nama_ibu_kandung').value = data.nama_ibu_kandung;
            document.getElementById('alamat').value = data.alamat;
            document.getElementById('status_murid').value = data.status_murid || 'Aktif';
            originalStatus = data.status_murid || 'Aktif';
            document.getElementById('tahun_masuk').value = data.tahun_masuk || '';
            
            if (data.foto) {
                document.getElementById('preview_foto').src = '../uploads/murid/' + data.foto;
            } else {
                document.getElementById('preview_foto').src = '../assets/img/default-student.svg';
            }
        }

        function viewMurid(data) {
            document.getElementById('viewModal').style.display = 'flex';
            
            if (data.foto) {
                document.getElementById('view_foto').src = '../uploads/murid/' + data.foto;
            } else {
                document.getElementById('view_foto').src = '../assets/img/default-student.svg';
            }
            
            document.getElementById('view_nisn').textContent = data.nisn;
            document.getElementById('view_nik').textContent = data.nik;
            document.getElementById('view_nama').textContent = data.nama;
            document.getElementById('view_tingkat').textContent = data.tingkat;
            document.getElementById('view_jenis_kelamin').textContent = data.jenis_kelamin == 'L' ? 'Laki-laki' : 'Perempuan';
            
            // Fix tanggal parsing
            const tanggalParts = data.tanggal_lahir.split('-');
            const tanggal = new Date(tanggalParts[0], tanggalParts[1] - 1, tanggalParts[2]);
            
            const bulanIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const formattedDate = tanggal.getDate() + ' ' + 
                                 bulanIndo[tanggal.getMonth()] + ' ' + 
                                 tanggal.getFullYear();
            
            document.getElementById('view_ttl').textContent = data.tempat_lahir + ', ' + formattedDate;
            document.getElementById('view_ibu').textContent = data.nama_ibu_kandung;
            document.getElementById('view_alamat').textContent = data.alamat;
            document.getElementById('view_status_murid').textContent = data.status_murid || 'Aktif';
            document.getElementById('view_tahun_masuk').textContent = data.tahun_masuk || '-';
        }

        function filterData() {
            const tingkat = document.getElementById('filterTingkat').value;
            const status = document.getElementById('filterStatus').value;
            const angkatan = document.getElementById('filterAngkatan').value; // NEW
            let url = 'murid.php?';
            if (tingkat) url += 'tingkat=' + encodeURIComponent(tingkat) + '&';
            if (status) url += 'status_murid=' + encodeURIComponent(status) + '&';
            if (angkatan) url += 'tahun_angkatan=' + encodeURIComponent(angkatan); // NEW
            window.location.href = url;
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            if (!table) return; // tabel kosong
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                
                // Search in: NISN, NIK, Nama, Tingkat, JK, Tempat Lahir, Ibu, Alamat, Angkatan, Status
                const nisn = cells[1]?.textContent || '';
                const nik = cells[2]?.textContent || '';
                const nama = cells[3]?.textContent || '';
                const tingkat = cells[4]?.textContent || '';
                const jk = cells[5]?.textContent || '';
                const ttl = cells[6]?.textContent || '';
                const ibu = cells[7]?.textContent || '';
                const alamat = cells[8]?.textContent || '';
                const angkatan = cells[9]?.textContent || '';
                const status = cells[10]?.textContent || '';
                
                const searchText = (nisn + nik + nama + tingkat + jk + ttl + ibu + alamat + angkatan + status).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('muridModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == viewModal) {
                closeViewModal();
            }
        }

        <?php if (isset($_GET['edit']) && $edit_data): ?>
        editMurid(<?= json_encode($edit_data) ?>);
        <?php endif; ?>
    </script>
</body>
</html>
