<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM absensi WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header("Location: absensi.php?success=delete");
    exit();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $tanggal = $_POST['tanggal'];
    $murid_id = $_POST['murid_id'];
    $status = $_POST['status'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    // Get tingkat from murid
    $stmt_murid = $db->prepare("SELECT tingkat FROM murid WHERE id = :id");
    $stmt_murid->bindParam(':id', $murid_id);
    $stmt_murid->execute();
    $murid_data = $stmt_murid->fetch(PDO::FETCH_ASSOC);
    $tingkat = $murid_data['tingkat'];

    if ($id) {
        // Update
        $stmt = $db->prepare("UPDATE absensi SET tanggal=:tanggal, murid_id=:murid_id, tingkat=:tingkat, status=:status, keterangan=:keterangan WHERE id=:id");
        $stmt->bindParam(':id', $id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO absensi (tanggal, murid_id, tingkat, status, keterangan) VALUES (:tanggal, :murid_id, :tingkat, :status, :keterangan)");
    }
    
    $stmt->bindParam(':tanggal', $tanggal);
    $stmt->bindParam(':murid_id', $murid_id);
    $stmt->bindParam(':tingkat', $tingkat);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':keterangan', $keterangan);
    $stmt->execute();
    
    header("Location: absensi.php?success=save");
    exit();
}

// Get statistics - ADDED
$total_aktif = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL")->fetchColumn();
$total_lulus = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Lulus'")->fetchColumn();
$total_pindah = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Pindah'")->fetchColumn();
$total_keluar = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Keluar'")->fetchColumn();

// Get today's attendance stats - ADDED
$today = date('Y-m-d');
$stmt_today = $db->prepare("SELECT COUNT(DISTINCT a.murid_id) FROM absensi a JOIN murid m ON a.murid_id = m.id WHERE a.tanggal = :tanggal AND (m.status_murid = 'Aktif' OR m.status_murid IS NULL)");
$stmt_today->bindParam(':tanggal', $today);
$stmt_today->execute();
$absen_hari_ini = $stmt_today->fetchColumn();
$belum_absen = $total_aktif - $absen_hari_ini;

// Get filter values - set default ke hari ini
$filter_tanggal = $_GET['tanggal'] ?? date('Y-m-d');
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_status_murid = $_GET['status_murid'] ?? ''; // CHANGED: Default ke kosong (semua status)
$filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? '';
$filter_semester = $_GET['semester'] ?? '';

// Build query
$where_clause = "WHERE a.tanggal = :tanggal";
if ($filter_tingkat) {
    $where_clause .= " AND a.tingkat = :tingkat";
}
// Filter by student status - FIXED
if ($filter_status_murid) {
    if ($filter_status_murid == 'Aktif') {
        $where_clause .= " AND (m.status_murid = 'Aktif' OR m.status_murid IS NULL)";
    } elseif ($filter_status_murid == 'Tidak Aktif') {
        $where_clause .= " AND m.status_murid IN ('Lulus', 'Pindah', 'Keluar')";
    } elseif (in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])) {
        $where_clause .= " AND m.status_murid = :status_murid_spesifik";
    }
}
// NOTE: If $filter_status_murid is empty, no filter applied = show all statuses
// Filter by tahun ajaran
if ($filter_tahun_ajaran) {
    $where_clause .= " AND m.tahun_masuk = :tahun_ajaran";
}
// NEW: Filter by semester (based on tanggal)
if ($filter_semester) {
    if ($filter_semester == 'Semester 1') {
        // Semester 1: Juli - Desember
        $where_clause .= " AND MONTH(a.tanggal) >= 7 AND MONTH(a.tanggal) <= 12";
    } elseif ($filter_semester == 'Semester 2') {
        // Semester 2: Januari - Juni
        $where_clause .= " AND MONTH(a.tanggal) >= 1 AND MONTH(a.tanggal) <= 6";
    }
}

// Get absensi data
$query = "SELECT a.*, m.nisn, m.nama, m.tingkat, m.status_murid, m.tahun_masuk 
          FROM absensi a 
          JOIN murid m ON a.murid_id = m.id 
          $where_clause 
          ORDER BY m.nama";
$stmt = $db->prepare($query);
$stmt->bindParam(':tanggal', $filter_tanggal);
if ($filter_tingkat) {
    $stmt->bindParam(':tingkat', $filter_tingkat);
}
if ($filter_status_murid && in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])) {
    $stmt->bindParam(':status_murid_spesifik', $filter_status_murid);
}
if ($filter_tahun_ajaran) {
    $stmt->bindParam(':tahun_ajaran', $filter_tahun_ajaran);
}
$stmt->execute();
$absensi_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all ACTIVE murid for dropdown - UPDATED
$murid_stmt = $db->query("SELECT id, nisn, nama, tingkat, status_murid FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL ORDER BY nama");
$murid_options = $murid_stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Get distinct tahun ajaran for filter
$tahun_stmt = $db->query("SELECT DISTINCT tahun_masuk FROM murid WHERE tahun_masuk IS NOT NULL ORDER BY tahun_masuk DESC");
$tahun_ajaran_list = $tahun_stmt->fetchAll(PDO::FETCH_COLUMN);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM absensi WHERE id = :id");
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
    <title>Absensi Siswa - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-attendance"></i>
            <h2>📋 Absensi Siswa</h2>
        </div>

        <div class="page-subtitle">
            Kelola data absensi harian siswa
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

        <!-- Today Stats - SIMPLE VERSION -->
        <div class="content-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0;">📊 Absensi Hari Ini</h3>
            <p style="margin: 0; color: #64748b;">
                <strong style="color: #10b981;"><?= e($absen_hari_ini) ?></strong> Sudah Absen | 
                <strong style="color: #ef4444;"><?= e($belum_absen) ?></strong> Belum Absen
            </p>
        </div>

        <!-- Filter Section - AUTO FILTER (No Button) -->
        <div class="content-card">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-primary" onclick="openModal()">
                    ➕ Input Absensi
                </button>
                
                <input type="date" id="filterTanggal" value="<?= e($filter_tanggal) ?>" 
                       onchange="autoFilter()"
                       style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                
                <select id="filterTingkat" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Tingkat</option>
                    <option value="Kelompok A" <?= $filter_tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                    <option value="Kelompok B" <?= $filter_tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                </select>
                
                <select id="filterSemester" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Semester</option>
                    <option value="Semester 1" <?= $filter_semester == 'Semester 1' ? 'selected' : '' ?>>📚 Sem 1</option>
                    <option value="Semester 2" <?= $filter_semester == 'Semester 2' ? 'selected' : '' ?>>📖 Sem 2</option>
                </select>
                
                <select id="filterStatusMurid" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $filter_status_murid == 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                    <option value="Tidak Aktif" <?= $filter_status_murid == 'Tidak Aktif' ? 'selected' : '' ?>>❌ Tidak Aktif</option>
                    <option value="Lulus" <?= $filter_status_murid == 'Lulus' ? 'selected' : '' ?>>🎓 Lulus</option>
                    <option value="Pindah" <?= $filter_status_murid == 'Pindah' ? 'selected' : '' ?>>🚚 Pindah</option>
                    <option value="Keluar" <?= $filter_status_murid == 'Keluar' ? 'selected' : '' ?>>🚪 Keluar</option>
                </select>
                
                <select id="filterAngkatan" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Angkatan</option>
                    <?php foreach ($tahun_ajaran_list as $tahun): ?>
                    <option value="<?= e($tahun) ?>" <?= $filter_tahun_ajaran == $tahun ? 'selected' : '' ?>>
                        📚 <?= e($tahun) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                
                <button type="button" class="btn" onclick="resetFilter()" 
                        style="background: #6c757d; color: white; padding: 10px 15px;"
                        title="Reset Filter">
                    🔄 Reset
                </button>
                
                <input type="text" id="searchInput" placeholder="🔍 Cari tanggal, NISN, nama, tingkat..." 
                       style="flex: 1; min-width: 250px; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                       onkeyup="searchTable()">
            </div>
        </div>

        <!-- Data Table - UPDATE header badges -->
        <div class="content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Data Absensi 
                    <?php if ($filter_semester): ?>
                        <span class="badge badge-info" style="font-size: 12px; margin-left: 10px;">
                            <?= e($filter_semester) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_status_murid): ?>
                        <span class="badge badge-<?= 
                            $filter_status_murid == 'Aktif' ? 'success' : 
                            ($filter_status_murid == 'Lulus' ? 'primary' : 'danger')
                        ?>" style="font-size: 12px; margin-left: 10px;">
                            <?= e($filter_status_murid) ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_tahun_ajaran): ?>
                        <span class="badge badge-purple" style="font-size: 12px; margin-left: 10px;">
                            📚 <?= e($filter_tahun_ajaran) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="font-size: 14px; color: #64748b;">
                    Total: <strong><?= count($absensi_list) ?></strong> data
                </div>
            </div>
            
            <?php if (count($absensi_list) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Tingkat</th>
                            <th>Angkatan</th>
                            <th>Status Murid</th>
                            <th>Status Absensi</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($absensi_list as $index => $absensi): 
                            $murid_status = $absensi['status_murid'] ?? 'Aktif';
                            $is_non_aktif = in_array($murid_status, ['Lulus', 'Pindah', 'Keluar']);
                        ?>
                        <tr <?= $is_non_aktif ? 'style="background: #fee2e2;"' : '' ?>>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d/m/Y', strtotime($absensi['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($absensi['nisn']) ?></td>
                            <td><?= htmlspecialchars($absensi['nama']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $absensi['tingkat'] == 'Kelompok A' ? 'primary' : 'purple'
                                ?>">
                                    <?= e($absensi['tingkat']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($absensi['tahun_masuk'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $murid_status == 'Aktif' ? 'success' : 
                                    ($murid_status == 'Lulus' ? 'primary' : 'danger')
                                ?>">
                                    <?= e($murid_status) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= 
                                    $absensi['status'] == 'Hadir' ? 'success' : 
                                    ($absensi['status'] == 'Sakit' ? 'warning' : 
                                    ($absensi['status'] == 'Izin' ? 'info' : 'danger')) 
                                ?>">
                                    <?= e($absensi['status']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($absensi['keterangan']) ?: '-' ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="javascript:void(0)" onclick="viewAbsensi(<?= htmlspecialchars(json_encode($absensi)) ?>)" class="btn btn-success" title="Lihat Detail">👁️</a>
                                    <a href="javascript:void(0)" onclick="editAbsensi(<?= htmlspecialchars(json_encode($absensi)) ?>)" class="btn btn-warning" title="Edit">✏️</a>
                                    <a href="?delete=<?= e($absensi['id']) ?>&tanggal=<?= e($filter_tanggal) ?>&tingkat=<?= e($filter_tingkat) ?>&status_murid=<?= e($filter_status_murid) ?>&tahun_ajaran=<?= e($filter_tahun_ajaran) ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data absensi ini?')" title="Hapus">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($filter_status_murid == 'Tidak Aktif' || in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])): ?>
            <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                <strong>ℹ️ Info:</strong> Menampilkan data absensi murid dengan status: <strong><?= e($filter_status_murid) ?></strong>
            </div>
            <?php elseif (count($absensi_list) > 0 && $filter_status_murid == ''): ?>
                <?php 
                $count_non_aktif = 0;
                foreach ($absensi_list as $a) {
                    if (in_array($a['status_murid'] ?? 'Aktif', ['Lulus', 'Pindah', 'Keluar'])) {
                        $count_non_aktif++;
                    }
                }
                if ($count_non_aktif > 0): 
                ?>
                <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                    <strong>⚠️ Perhatian:</strong> Terdapat <strong><?= e($count_non_aktif) ?></strong> data dengan latar merah (murid tidak aktif: Lulus/Pindah/Keluar)
                </div>
                <?php endif; ?>
            <?php endif; ?>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                <p>Tidak ada data absensi 
                    <?php if ($filter_status_murid): ?>
                        untuk murid <strong><?= e($filter_status_murid) ?></strong>
                    <?php endif; ?>
                    <?php if ($filter_tahun_ajaran): ?>
                        angkatan <strong><?= e($filter_tahun_ajaran) ?></strong>
                    <?php endif; ?>
                    pada tanggal <strong><?= date('d/m/Y', strtotime($filter_tanggal)) ?></strong>
                </p>
                <?php if ($filter_status_murid || $filter_tingkat || $filter_tahun_ajaran): ?>
                <a href="absensi.php?tanggal=<?= e($filter_tanggal) ?>" class="btn btn-primary" style="margin-top: 15px; text-decoration: none;">
                    🔄 Reset Filter
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="absensiModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Input Absensi</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="absensiForm">
                <input type="hidden" name="id" id="absensi_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tanggal <span class="required">*</span></label>
                        <input type="date" name="tanggal" id="tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Siswa <span class="required">*</span></label>
                        <select name="murid_id" id="murid_id" required onchange="checkMuridStatus()">
                            <option value="">Pilih Siswa</option>
                            <?php foreach ($murid_options as $murid): ?>
                            <option value="<?= e($murid['id']) ?>" 
                                    data-tingkat="<?= e($murid['tingkat']) ?>"
                                    data-status="<?= e($murid['status_murid'] ?? 'Aktif') ?>">
                                <?= htmlspecialchars($murid['nama']) ?> - <?= e($murid['tingkat']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="status_warning" style="display: none; margin-top: 8px; padding: 8px; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 4px;">
                            <small style="color: #dc2626; font-weight: 600;">
                                ⚠️ <span id="warning_text"></span>
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" id="status" required>
                            <option value="">Pilih Status</option>
                            <option value="Hadir" selected>Hadir</option>
                            <option value="Sakit">Sakit</option>
                            <option value="Izin">Izin</option>
                            <option value="Alpa">Alpa</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="keterangan" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">✕ Batal</button>
                    <button type="submit" class="btn btn-primary">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail View -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>📋 Detail Absensi</h3>
                <button type="button" class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Tanggal</label>
                        <p id="view_tanggal"></p>
                    </div>
                    <div class="detail-item">
                        <label>NISN</label>
                        <p id="view_nisn"></p>
                    </div>
                    <div class="detail-item">
                        <label>Nama Siswa</label>
                        <p id="view_nama"></p>
                    </div>
                    <div class="detail-item">
                        <label>Tingkat</label>
                        <p id="view_tingkat"></p>
                    </div>
                    <div class="detail-item">
                        <label>Status Murid</label>
                        <p id="view_status_murid"></p>
                    </div>
                    <div class="detail-item">
                        <label>Status Kehadiran</label>
                        <p id="view_status"></p>
                    </div>
                    <div class="detail-item full-width">
                        <label>Keterangan</label>
                        <p id="view_keterangan"></p>
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
            document.getElementById('absensiModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Input Absensi';
            document.getElementById('absensiForm').reset();
            document.getElementById('absensi_id').value = '';
            document.getElementById('tanggal').value = '<?= date('Y-m-d') ?>';
        }

        function closeModal() {
            document.getElementById('absensiModal').style.display = 'none';
        }

        function editAbsensi(data) {
            document.getElementById('absensiModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit Absensi';
            document.getElementById('absensi_id').value = data.id;
            document.getElementById('tanggal').value = data.tanggal;
            document.getElementById('murid_id').value = data.murid_id;
            document.getElementById('status').value = data.status;
            document.getElementById('keterangan').value = data.keterangan || '';
        }

        function viewAbsensi(data) {
            document.getElementById('viewModal').style.display = 'flex';
            
            // Fix tanggal parsing
            const tanggalParts = data.tanggal.split('-');
            const tanggal = new Date(tanggalParts[0], tanggalParts[1] - 1, tanggalParts[2]);
            
            const bulanIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const formattedDate = tanggal.getDate() + ' ' + 
                                 bulanIndo[tanggal.getMonth()] + ' ' + 
                                 tanggal.getFullYear();
            
            document.getElementById('view_tanggal').textContent = formattedDate;
            document.getElementById('view_nisn').textContent = data.nisn;
            document.getElementById('view_nama').textContent = data.nama;
            document.getElementById('view_tingkat').textContent = data.tingkat;
            
            // Display student status - text only
            const muridStatus = data.status_murid || 'Aktif';
            document.getElementById('view_status_murid').textContent = muridStatus;
            
            // Display attendance status - text only
            document.getElementById('view_status').textContent = data.status;
            document.getElementById('view_keterangan').textContent = data.keterangan || '-';
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            if (!table) return; // tabel kosong
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                
                // Search in: Tanggal, NISN, Nama, Tingkat, Angkatan, Status Murid, Status Absensi, Keterangan
                const tanggal = cells[1]?.textContent || '';
                const nisn = cells[2]?.textContent || '';
                const nama = cells[3]?.textContent || '';
                const tingkat = cells[4]?.textContent || '';
                const angkatan = cells[5]?.textContent || '';
                const statusMurid = cells[6]?.textContent || '';
                const statusAbsensi = cells[7]?.textContent || '';
                const keterangan = cells[8]?.textContent || '';
                
                const searchText = (tanggal + nisn + nama + tingkat + angkatan + statusMurid + statusAbsensi + keterangan).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        function autoFilter() {
            const tanggal = document.getElementById('filterTanggal').value;
            const tingkat = document.getElementById('filterTingkat').value;
            const semester = document.getElementById('filterSemester').value;
            const statusMurid = document.getElementById('filterStatusMurid').value;
            const angkatan = document.getElementById('filterAngkatan').value;
            
            let url = 'absensi.php?';
            const params = [];
            
            if (tanggal) params.push('tanggal=' + encodeURIComponent(tanggal));
            if (tingkat) params.push('tingkat=' + encodeURIComponent(tingkat));
            if (semester) params.push('semester=' + encodeURIComponent(semester));
            if (statusMurid) params.push('status_murid=' + encodeURIComponent(statusMurid));
            if (angkatan) params.push('tahun_ajaran=' + encodeURIComponent(angkatan));
            
            window.location.href = url + params.join('&');
        }
        
        function resetFilter() {
            window.location.href = 'absensi.php?tanggal=<?= date('Y-m-d') ?>';
        }

        function checkMuridStatus() {
            const muridSelect = document.getElementById('murid_id');
            const selectedOption = muridSelect.options[muridSelect.selectedIndex];
            const status = selectedOption.getAttribute('data-status');
            const warningText = document.getElementById('warning_text');
            const statusWarning = document.getElementById('status_warning');

            if (status === 'Tidak Aktif') {
                warningText.textContent = 'Murid ini tidak aktif (Lulus/Pindah/Keluar).';
                statusWarning.style.display = 'block';
            } else {
                statusWarning.style.display = 'none';
            }
        }
    </script>
</body>
</html>
