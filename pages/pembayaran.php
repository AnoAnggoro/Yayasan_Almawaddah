<?php
require_once '../config/session.php';
require_once '../config/database.php';
require_once '../config/upload.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Delete bukti file if exists
    $stmt_file = $db->prepare("SELECT bukti_pembayaran FROM pembayaran WHERE id = :id");
    $stmt_file->bindParam(':id', $id);
    $stmt_file->execute();
    $file_data = $stmt_file->fetch(PDO::FETCH_ASSOC);
    
    if ($file_data && $file_data['bukti_pembayaran'] && file_exists('../uploads/bukti/' . $file_data['bukti_pembayaran'])) {
        unlink('../uploads/bukti/' . $file_data['bukti_pembayaran']);
    }
    
    $stmt = $db->prepare("DELETE FROM pembayaran WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header("Location: pembayaran.php?success=delete");
    exit();
}

// Handle Pembayaran Batch (Lunas beberapa bulan sekaligus)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_payment'])) {
    $murid_id = $_POST['murid_id'];
    $bulan_terpilih = $_POST['bulan_terpilih'] ?? [];
    $jumlah_per_bulan = $_POST['jumlah'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    // Get tingkat from murid
    $stmt_murid = $db->prepare("SELECT tingkat FROM murid WHERE id = :id");
    $stmt_murid->bindParam(':id', $murid_id);
    $stmt_murid->execute();
    $murid_data = $stmt_murid->fetch(PDO::FETCH_ASSOC);
    $tingkat = $murid_data['tingkat'];
    
    // Handle bukti upload untuk batch
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran_batch']) && $_FILES['bukti_pembayaran_batch']['error'] === UPLOAD_ERR_OK) {
        $nama_berkas = simpan_upload($_FILES['bukti_pembayaran_batch'], '../uploads/bukti', 'bukti_batch', ['jpg', 'jpeg', 'png', 'pdf'], $upload_error);
        if ($nama_berkas) {
            $bukti_pembayaran = $nama_berkas;
        }
    }
    
    // Insert pembayaran untuk setiap bulan yang dipilih
    foreach ($bulan_terpilih as $bulan) {
        $tahun = date('Y');
        $status = 'Lunas';
        
        $stmt = $db->prepare("INSERT INTO pembayaran (murid_id, jenis_pembayaran, bulan, tahun, tingkat, jumlah, tanggal_bayar, metode_pembayaran, status, keterangan, bukti_pembayaran) VALUES (:murid_id, 'SPP', :bulan, :tahun, :tingkat, :jumlah, :tanggal_bayar, :metode, :status, :keterangan, :bukti)");
        $stmt->bindParam(':murid_id', $murid_id);
        $stmt->bindParam(':bulan', $bulan);
        $stmt->bindParam(':tahun', $tahun);
        $stmt->bindParam(':tingkat', $tingkat);
        $stmt->bindParam(':jumlah', $jumlah_per_bulan);
        $stmt->bindParam(':tanggal_bayar', $tanggal_bayar);
        $stmt->bindParam(':metode', $metode_pembayaran);
        $stmt->bindParam(':status', $status);
        $stmt->bindParam(':keterangan', $keterangan);
        $stmt->bindParam(':bukti', $bukti_pembayaran);
        $stmt->execute();
    }
    
    header("Location: pembayaran.php?success=batch");
    exit();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($_POST['batch_payment'])) {
    $id = $_POST['id'] ?? null;
    $murid_id = $_POST['murid_id'];
    $jenis_pembayaran = $_POST['jenis_pembayaran'];
    $bulan = $_POST['bulan'] ?? null;
    $tahun = date('Y');
    $jumlah = $_POST['jumlah'];
    $tanggal_bayar = $_POST['tanggal_bayar'];
    $metode_pembayaran = $_POST['metode_pembayaran'];
    $keterangan = $_POST['keterangan'] ?? '';
    $status = 'Lunas';
    
    // Get tingkat from murid
    $stmt_murid = $db->prepare("SELECT tingkat FROM murid WHERE id = :id");
    $stmt_murid->bindParam(':id', $murid_id);
    $stmt_murid->execute();
    $murid_data = $stmt_murid->fetch(PDO::FETCH_ASSOC);
    $tingkat = $murid_data['tingkat'];

    // Handle bukti upload
    $bukti_pembayaran = '';
    if (isset($_FILES['bukti_pembayaran']) && $_FILES['bukti_pembayaran']['error'] === UPLOAD_ERR_OK) {
        $nama_berkas = simpan_upload($_FILES['bukti_pembayaran'], '../uploads/bukti', 'bukti', ['jpg', 'jpeg', 'png', 'pdf'], $upload_error);

        if ($nama_berkas) {
            $bukti_pembayaran = $nama_berkas;

            // Hapus berkas lama kalau ini update
            if ($id) {
                $stmt_old = $db->prepare("SELECT bukti_pembayaran FROM pembayaran WHERE id = :id");
                $stmt_old->bindParam(':id', $id);
                $stmt_old->execute();
                $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
                if ($old_data && $old_data['bukti_pembayaran'] && file_exists('../uploads/bukti/' . $old_data['bukti_pembayaran'])) {
                    unlink('../uploads/bukti/' . $old_data['bukti_pembayaran']);
                }
            }
        }
    }

    if ($id) {
        // Update
        if ($bukti_pembayaran) {
            $stmt = $db->prepare("UPDATE pembayaran SET murid_id=:murid_id, jenis_pembayaran=:jenis, bulan=:bulan, tahun=:tahun, tingkat=:tingkat, jumlah=:jumlah, tanggal_bayar=:tanggal_bayar, metode_pembayaran=:metode, status=:status, keterangan=:keterangan, bukti_pembayaran=:bukti WHERE id=:id");
            $stmt->bindParam(':bukti', $bukti_pembayaran);
        } else {
            $stmt = $db->prepare("UPDATE pembayaran SET murid_id=:murid_id, jenis_pembayaran=:jenis, bulan=:bulan, tahun=:tahun, tingkat=:tingkat, jumlah=:jumlah, tanggal_bayar=:tanggal_bayar, metode_pembayaran=:metode, status=:status, keterangan=:keterangan WHERE id=:id");
        }
        $stmt->bindParam(':id', $id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO pembayaran (murid_id, jenis_pembayaran, bulan, tahun, tingkat, jumlah, tanggal_bayar, metode_pembayaran, status, keterangan, bukti_pembayaran) VALUES (:murid_id, :jenis, :bulan, :tahun, :tingkat, :jumlah, :tanggal_bayar, :metode, :status, :keterangan, :bukti)");
        $stmt->bindParam(':bukti', $bukti_pembayaran);
    }
    
    $stmt->bindParam(':murid_id', $murid_id);
    $stmt->bindParam(':jenis', $jenis_pembayaran);
    $stmt->bindParam(':bulan', $bulan);
    $stmt->bindParam(':tahun', $tahun);
    $stmt->bindParam(':tingkat', $tingkat);
    $stmt->bindParam(':jumlah', $jumlah);
    $stmt->bindParam(':tanggal_bayar', $tanggal_bayar);
    $stmt->bindParam(':metode', $metode_pembayaran);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':keterangan', $keterangan);
    $stmt->execute();
    
    header("Location: pembayaran.php?success=save");
    exit();
}

// Get filter values with date range
$filter_tanggal_dari = $_GET['tanggal_dari'] ?? '';
$filter_tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$filter_tahun = $_GET['tahun'] ?? date('Y');
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_jenis = $_GET['jenis'] ?? '';

// Build query with date range
$where_clause = "WHERE 1=1";

if ($filter_tanggal_dari && $filter_tanggal_sampai) {
    $where_clause .= " AND p.tanggal_bayar BETWEEN :tanggal_dari AND :tanggal_sampai";
} elseif ($filter_tanggal_dari) {
    $where_clause .= " AND p.tanggal_bayar >= :tanggal_dari";
} elseif ($filter_tanggal_sampai) {
    $where_clause .= " AND p.tanggal_bayar <= :tanggal_sampai";
}

if ($filter_tahun) {
    $where_clause .= " AND p.tahun = :tahun";
}
if ($filter_tingkat) {
    $where_clause .= " AND p.tingkat = :tingkat";
}
if ($filter_jenis) {
    $where_clause .= " AND p.jenis_pembayaran = :jenis";
}

// Get current month for SPP statistics
$bulan_indonesia = [
    'January' => 'Januari',
    'February' => 'Februari', 
    'March' => 'Maret',
    'April' => 'April',
    'May' => 'Mei',
    'June' => 'Juni',
    'July' => 'Juli',
    'August' => 'Agustus',
    'September' => 'September',
    'October' => 'Oktober',
    'November' => 'November',
    'December' => 'Desember'
];
$current_month_en = date('F');
$current_month = $bulan_indonesia[$current_month_en] . ' ' . date('Y');

// Get statistics - Sudah Bayar SPP Bulan Ini
$stmt_lunas = $db->prepare("SELECT COUNT(DISTINCT murid_id) FROM pembayaran WHERE jenis_pembayaran='SPP' AND bulan = :bulan AND status='Lunas'");
$stmt_lunas->bindParam(':bulan', $current_month);
$stmt_lunas->execute();
$stats_lunas = $stmt_lunas->fetchColumn();

// Get total murid aktif (hanya yang statusnya Aktif)
$total_murid = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL")->fetchColumn();

// Belum Bayar SPP = Total Murid - Yang Sudah Bayar
$stats_belum = $total_murid - $stats_lunas;

// Total pemasukan (semua pembayaran lunas)
$stats_total = $db->query("SELECT COALESCE(SUM(jumlah), 0) FROM pembayaran WHERE status='Lunas'")->fetchColumn();

// Get list of students who haven't paid this month's SPP (ONLY ACTIVE students)
$query_belum_bayar = "SELECT m.id, m.nisn, m.nama, m.tingkat, m.status_murid 
                      FROM murid m 
                      WHERE (m.status_murid = 'Aktif' OR m.status_murid IS NULL)
                      AND m.id NOT IN (
                          SELECT DISTINCT murid_id 
                          FROM pembayaran 
                          WHERE jenis_pembayaran='SPP' 
                          AND bulan = :bulan 
                          AND status='Lunas'
                      )
                      ORDER BY m.tingkat, m.nama";
$stmt_belum = $db->prepare($query_belum_bayar);
$stmt_belum->bindParam(':bulan', $current_month);
$stmt_belum->execute();
$murid_belum_bayar = $stmt_belum->fetchAll(PDO::FETCH_ASSOC);

// Function to get payment history per student
function getRiwayatPembayaran($db, $murid_id) {
    $tahun = date('Y');
    $bulan_list = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    
    $riwayat = [];
    foreach ($bulan_list as $bulan) {
        $bulan_tahun = $bulan . ' ' . $tahun;
        $stmt = $db->prepare("SELECT * FROM pembayaran WHERE murid_id = :murid_id AND jenis_pembayaran = 'SPP' AND bulan = :bulan");
        $stmt->bindParam(':murid_id', $murid_id);
        $stmt->bindParam(':bulan', $bulan_tahun);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $riwayat[] = [
            'bulan' => $bulan_tahun,
            'status' => $data ? 'Lunas' : 'Belum Bayar',
            'tanggal' => $data ? $data['tanggal_bayar'] : null,
            'jumlah' => $data ? $data['jumlah'] : 0
        ];
    }
    
    return $riwayat;
}

// Get pembayaran data with date range
$query = "SELECT p.*, m.nisn, m.nama 
          FROM pembayaran p 
          JOIN murid m ON p.murid_id = m.id 
          $where_clause 
          ORDER BY p.tanggal_bayar DESC, p.created_at DESC";
$stmt = $db->prepare($query);

if ($filter_tanggal_dari && $filter_tanggal_sampai) {
    $stmt->bindParam(':tanggal_dari', $filter_tanggal_dari);
    $stmt->bindParam(':tanggal_sampai', $filter_tanggal_sampai);
} elseif ($filter_tanggal_dari) {
    $stmt->bindParam(':tanggal_dari', $filter_tanggal_dari);
} elseif ($filter_tanggal_sampai) {
    $stmt->bindParam(':tanggal_sampai', $filter_tanggal_sampai);
}

if ($filter_tahun) {
    $stmt->bindParam(':tahun', $filter_tahun);
}
if ($filter_tingkat) {
    $stmt->bindParam(':tingkat', $filter_tingkat);
}
if ($filter_jenis) {
    $stmt->bindParam(':jenis', $filter_jenis);
}
$stmt->execute();
$pembayaran_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all ACTIVE murid for dropdown with tahun_masuk
$murid_stmt = $db->query("SELECT id, nisn, nama, tingkat, status_murid, tahun_masuk FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL ORDER BY nama");
$murid_options = $murid_stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM pembayaran WHERE id = :id");
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
    <title>Pembayaran - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-payment"></i>
            <h2>💰 Pembayaran SPP</h2>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= e($stats_lunas) ?></div>
                <div class="stat-label">Sudah Bayar SPP Bulan Ini</div>
            </div>
            <div class="stat-card danger" style="cursor: pointer;" onclick="showBelumBayar()">
                <div class="stat-icon">❗</div>
                <div class="stat-value"><?= e($stats_belum) ?></div>
                <div class="stat-label">Belum Bayar SPP Bulan Ini</div>
            </div>
            <div class="stat-card" style="background: linear-gradient(135deg, #f9c74f, #f8961e);">
                <div class="stat-icon">💵</div>
                <div class="stat-value">Rp <?= number_format($stats_total, 0, ',', '.') ?></div>
                <div class="stat-label">Total Pemasukan</div>
            </div>
        </div>

        <!-- Filter Section - AUTO FILTER with Date Range -->
        <div class="content-card">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <input type="date" id="filterTanggalDari" value="<?= e($filter_tanggal_dari) ?>" 
                       onchange="autoFilter()"
                       placeholder="Tanggal Dari"
                       style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">

                <input type="date" id="filterTanggalSampai" value="<?= e($filter_tanggal_sampai) ?>" 
                       onchange="autoFilter()"
                       placeholder="Tanggal Sampai"
                       style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">

                <select id="filterJenis" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Jenis</option>
                    <option value="SPP" <?= $filter_jenis == 'SPP' ? 'selected' : '' ?>>SPP</option>
                    <option value="Uang Pangkal" <?= $filter_jenis == 'Uang Pangkal' ? 'selected' : '' ?>>Uang Pangkal</option>
                    <option value="Uang Seragam" <?= $filter_jenis == 'Uang Seragam' ? 'selected' : '' ?>>Uang Seragam</option>
                    <option value="Uang Buku" <?= $filter_jenis == 'Uang Buku' ? 'selected' : '' ?>>Uang Buku</option>
                    <option value="Uang Kegiatan" <?= $filter_jenis == 'Uang Kegiatan' ? 'selected' : '' ?>>Uang Kegiatan</option>
                    <option value="Lainnya" <?= $filter_jenis == 'Lainnya' ? 'selected' : '' ?>>Lainnya</option>
                </select>

                <select id="filterTingkat" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Tingkat</option>
                    <option value="Kelompok A" <?= $filter_tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                    <option value="Kelompok B" <?= $filter_tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                </select>

                <button type="button" class="btn" onclick="resetFilter()" 
                        style="background: #6c757d; color: white; padding: 10px 15px;"
                        title="Reset Filter">
                    🔄 Reset
                </button>
            </div>
        </div>

        <!-- Button Input -->
        <div class="content-card">
            <div style="display: flex; gap: 10px; flex-wrap: wrap; justify-content: space-between; align-items: center;">
                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn btn-success" onclick="openModal()">
                        ➕ Input Pembayaran
                    </button>
                    <button type="button" class="btn" style="background: #ef4444; color: white;" onclick="showBelumBayar()">
                        📋 Lihat Belum Bayar (<?= e($stats_belum) ?>)
                    </button>
                </div>
                
                <input type="text" id="searchInput" placeholder="🔍 Cari NISN, nama, tingkat, jenis pembayaran, bulan, metode..." 
                       style="flex: 1; max-width: 350px; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                       onkeyup="searchTable()">
            </div>
        </div>

        <!-- Data Table with Bukti Column -->
        <div class="content-card">
            <h3>Data Pembayaran</h3>
            
            <?php if (count($pembayaran_list) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Tingkat</th>
                            <th>Jenis Pembayaran</th>
                            <th>Bulan</th>
                            <th>Tahun</th>
                            <th>Jumlah</th>
                            <th>Tanggal Bayar</th>
                            <th>Metode</th>
                            <th>Bukti</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pembayaran_list as $index => $bayar): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($bayar['nisn']) ?></td>
                            <td><?= htmlspecialchars($bayar['nama']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $bayar['tingkat'] == 'Kelompok A' ? 'primary' : 'purple'
                                ?>">
                                    <?= e($bayar['tingkat']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge" style="background: #10b981; color: white;">
                                    <?= htmlspecialchars($bayar['jenis_pembayaran']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($bayar['bulan'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($bayar['tahun']) ?></td>
                            <td><strong>Rp <?= number_format($bayar['jumlah'], 0, ',', '.') ?></strong></td>
                            <td><?= $bayar['tanggal_bayar'] ? date('d/m/Y', strtotime($bayar['tanggal_bayar'])) : '-' ?></td>
                            <td><?= htmlspecialchars($bayar['metode_pembayaran'] ?? '-') ?></td>
                            <td>
                                <?php if ($bayar['bukti_pembayaran']): ?>
                                    <a href="../uploads/bukti/<?= e($bayar['bukti_pembayaran']) ?>" 
                                       target="_blank" 
                                       class="btn btn-success" 
                                       style="font-size: 11px; padding: 4px 8px;"
                                       title="Lihat Bukti">
                                        📄 Lihat
                                    </a>
                                <?php else: ?>
                                    <span style="color: #999; font-size: 12px;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="javascript:void(0)" onclick="lihatDetail(<?= htmlspecialchars(json_encode($bayar)) ?>)" class="btn btn-success" title="Lihat Detail">👁️</a>
                                    <a href="javascript:void(0)" onclick="editPembayaran(<?= htmlspecialchars(json_encode($bayar)) ?>)" class="btn btn-warning" title="Edit">✏️</a>
                                    <a href="?delete=<?= e($bayar['id']) ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data pembayaran ini?')" title="Hapus">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <p>Tidak ada data pembayaran</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Form with Upload Bukti -->
    <div id="pembayaranModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Input Pembayaran</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="pembayaranForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="id" id="pembayaran_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Murid <span class="required">*</span></label>
                        <select name="murid_id" id="murid_id" required onchange="showMuridInfo()">
                            <option value="">Pilih Murid</option>
                            <?php foreach ($murid_options as $murid): 
                                $info_angkatan = $murid['tahun_masuk'] ? ' (Angkatan ' . $murid['tahun_masuk'] . ')' : '';
                                $status_murid = $murid['status_murid'] ?? 'Aktif';
                            ?>
                            <option value="<?= e($murid['id']) ?>" 
                                    data-angkatan="<?= htmlspecialchars($murid['tahun_masuk'] ?? '') ?>"
                                    data-tingkat="<?= htmlspecialchars($murid['tingkat']) ?>"
                                    data-status="<?= htmlspecialchars($status_murid) ?>">
                                <?= htmlspecialchars($murid['nama']) ?> - <?= e($murid['tingkat']) ?><?= e($info_angkatan) ?>
                                <?php if ($status_murid !== 'Aktif'): ?>
                                    (<?= e($status_murid) ?>)
                                <?php endif; ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="murid_info_display" style="margin-top: 8px; padding: 8px; background: #e0f2fe; border-radius: 4px; display: none;">
                            <small style="color: #075985; font-weight: 500;">
                                <span id="info_text"></span>
                            </small>
                        </div>
                        <div id="murid_status_warning" style="display: none; margin-top: 8px; padding: 8px; background: #fee2e2; border-left: 4px solid #ef4444; border-radius: 4px;">
                            <small style="color: #dc2626; font-weight: 600;">
                                ⚠️ <span id="status_warning_text"></span>
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Jenis Pembayaran <span class="required">*</span></label>
                        <select name="jenis_pembayaran" id="jenis_pembayaran" required onchange="toggleBulan()">
                            <option value="">Pilih Jenis Pembayaran</option>
                            <option value="SPP">SPP</option>
                            <option value="Uang Pangkal">Uang Pangkal</option>
                            <option value="Uang Seragam">Uang Seragam</option>
                            <option value="Uang Buku">Uang Buku</option>
                            <option value="Uang Kegiatan">Uang Kegiatan</option>
                            <option value="Lainnya">Lainnya</option>
                        </select>
                    </div>

                    <div class="form-group" id="bulan_group">
                        <label>Bulan</label>
                        <select name="bulan" id="bulan">
                            <option value="">Pilih Bulan</option>
                            <option value="Januari <?= date('Y') ?>">Januari <?= date('Y') ?></option>
                            <option value="Februari <?= date('Y') ?>">Februari <?= date('Y') ?></option>
                            <option value="Maret <?= date('Y') ?>">Maret <?= date('Y') ?></option>
                            <option value="April <?= date('Y') ?>">April <?= date('Y') ?></option>
                            <option value="Mei <?= date('Y') ?>">Mei <?= date('Y') ?></option>
                            <option value="Juni <?= date('Y') ?>">Juni <?= date('Y') ?></option>
                            <option value="Juli <?= date('Y') ?>">Juli <?= date('Y') ?></option>
                            <option value="Agustus <?= date('Y') ?>">Agustus <?= date('Y') ?></option>
                            <option value="September <?= date('Y') ?>">September <?= date('Y') ?></option>
                            <option value="Oktober <?= date('Y') ?>">Oktober <?= date('Y') ?></option>
                            <option value="November <?= date('Y') ?>">November <?= date('Y') ?></option>
                            <option value="Desember <?= date('Y') ?>">Desember <?= date('Y') ?></option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Jumlah Pembayaran <span class="required">*</span></label>
                        <input type="number" name="jumlah" id="jumlah" placeholder="500000" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Bayar <span class="required">*</span></label>
                        <input type="date" name="tanggal_bayar" id="tanggal_bayar" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Metode Pembayaran <span class="required">*</span></label>
                        <select name="metode_pembayaran" id="metode_pembayaran" required>
                            <option value="">Pilih Metode</option>
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-Wallet">E-Wallet (OVO/GoPay/Dana)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Upload Bukti Pembayaran</label>
                        <div style="margin-bottom: 10px;" id="preview_container" style="display: none;">
                            <img id="preview_bukti" src="" style="max-width: 100%; max-height: 200px; border-radius: 6px; display: none;">
                            <a id="current_bukti" href="" target="_blank" style="display: none; color: #10b981; text-decoration: underline;">📄 Lihat Bukti Saat Ini</a>
                        </div>
                        <input type="file" name="bukti_pembayaran" id="bukti_pembayaran" accept="image/*,application/pdf" onchange="previewBukti(this)">
                        <small style="color: #718096; font-size: 12px;">Format: JPG, PNG, PDF (Max 5MB) - Opsional</small>
                    </div>

                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="keterangan" rows="3" placeholder="Keterangan tambahan (opsional)"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #f9c74f; color: #333;" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Belum Bayar -->
    <div id="belumBayarModal" class="modal">
        <div class="modal-content" style="max-width: 1000px;">
            <div class="modal-header">
                <h3>📋 Daftar Belum Bayar SPP - <?= e($current_month) ?></h3>
                <button type="button" class="modal-close" onclick="closeBelumBayar()">&times;</button>
            </div>
            
            <div class="modal-body">
                <?php if (count($murid_belum_bayar) > 0): ?>
                    <div style="margin-bottom: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                        <strong>Total: <?= count($murid_belum_bayar) ?> murid</strong> belum bayar SPP bulan ini
                    </div>
                    
                    <div style="overflow-x: auto;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>NISN</th>
                                    <th>Nama Siswa</th>
                                    <th>Tingkat</th>
                                    <th>Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($murid_belum_bayar as $index => $murid): ?>
                                <tr>
                                    <td><?= $index + 1 ?></td>
                                    <td><?= htmlspecialchars($murid['nisn']) ?></td>
                                    <td><?= htmlspecialchars($murid['nama']) ?></td>
                                    <td>
                                        <span class="badge badge-<?= 
                                            $murid['tingkat'] == 'Kelompok A' ? 'primary' : 'purple'
                                        ?>">
                                            <?= e($murid['tingkat']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                                            <button type="button" class="btn" style="background: #3b82f6; color: white; font-size: 11px; padding: 6px 10px;" onclick="viewMuridDetail(<?= e($murid['id']) ?>)" title="Lihat Detail Murid">
                                                👁️ Detail
                                            </button>
                                            <button type="button" class="btn btn-success" style="font-size: 11px; padding: 6px 10px;" onclick="inputPembayaranDari(<?= e($murid['id']) ?>, '<?= htmlspecialchars($murid['nama']) ?>')">
                                                💰 Bayar Bulan Ini
                                            </button>
                                            <button type="button" class="btn btn-primary" style="font-size: 11px; padding: 6px 10px;" onclick="lihatRiwayat(<?= e($murid['id']) ?>, '<?= htmlspecialchars($murid['nama']) ?>')">
                                                📊 Riwayat & Bayar Lunas
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 40px;">
                        <div style="font-size: 48px; margin-bottom: 15px;">🎉</div>
                        <h3 style="color: #10b981; margin-bottom: 10px;">Semua Sudah Bayar!</h3>
                        <p style="color: #64748b;">Tidak ada murid yang belum bayar SPP bulan ini.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" style="background: #64748b; color: white;" onclick="closeBelumBayar()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Riwayat & Pembayaran Lunas -->
    <div id="riwayatModal" class="modal">
        <div class="modal-content" style="max-width: 900px;">
            <div class="modal-header">
                <h3 id="riwayatTitle">📊 Riwayat Pembayaran SPP</h3>
                <button type="button" class="modal-close" onclick="closeRiwayat()">&times;</button>
            </div>
            
            <div class="modal-body" id="riwayatContent">
                <!-- Content will be loaded by JavaScript -->
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" style="background: #64748b; color: white;" onclick="closeRiwayat()">Tutup</button>
            </div>
        </div>
    </div>

    <!-- Modal Pembayaran Batch -->
    <div id="batchModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3 id="batchTitle">💰 Pembayaran Lunas / Cicilan</h3>
                <button type="button" class="modal-close" onclick="closeBatch()">&times;</button>
            </div>
            
            <form method="POST" id="batchForm" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="batch_payment" value="1">
                <input type="hidden" name="murid_id" id="batch_murid_id">
                
                <div class="modal-body">
                    <div style="margin-bottom: 20px; padding: 12px; background: #e0f2fe; border-left: 4px solid #0284c7; border-radius: 6px;">
                        <strong>Nama:</strong> <span id="batch_murid_nama"></span><br>
                        <strong>Pilih bulan yang ingin dibayar:</strong>
                    </div>

                    <div id="bulan_checkbox_list" style="display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 10px; margin-bottom: 20px;">
                        <!-- Checkboxes will be generated by JavaScript -->
                    </div>

                    <div class="form-group">
                        <label>Jumlah per Bulan <span class="required">*</span></label>
                        <input type="number" name="jumlah" id="batch_jumlah" placeholder="500000" required>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Bayar <span class="required">*</span></label>
                        <input type="date" name="tanggal_bayar" id="batch_tanggal" value="<?= date('Y-m-d') ?>" required>
                    </div>

                    <div class="form-group">
                        <label>Metode Pembayaran <span class="required">*</span></label>
                        <select name="metode_pembayaran" id="batch_metode" required>
                            <option value="">Pilih Metode</option>
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer Bank">Transfer Bank</option>
                            <option value="E-Wallet">E-Wallet (OVO/GoPay/Dana)</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Upload Bukti Pembayaran</label>
                        <div style="margin-bottom: 10px;" id="preview_container_batch" style="display: none;">
                            <img id="preview_bukti_batch" src="" style="max-width: 100%; max-height: 200px; border-radius: 6px; display: none;">
                        </div>
                        <input type="file" name="bukti_pembayaran_batch" id="bukti_pembayaran_batch" accept="image/*,application/pdf" onchange="previewBuktiBatch(this)">
                        <small style="color: #718096; font-size: 12px;">Format: JPG, PNG, PDF (Max 5MB) - Bukti untuk semua bulan yang dipilih - Opsional</small>
                    </div>

                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="batch_keterangan" rows="2" placeholder="Contoh: Pembayaran lunas 3 bulan"></textarea>
                    </div>

                    <div id="total_bayar" style="padding: 15px; background: #f0fdf4; border: 2px solid #10b981; border-radius: 8px; text-align: center;">
                        <strong style="font-size: 14px; color: #166534;">Total Pembayaran:</strong>
                        <div style="font-size: 24px; font-weight: bold; color: #10b981; margin-top: 5px;" id="total_amount">Rp 0</div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn" style="background: #f9c74f; color: #333;" onclick="closeBatch()">Batal</button>
                    <button type="submit" class="btn btn-success">💰 Bayar Sekarang</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail Murid - UPDATE -->
    <div id="muridDetailModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3>👨‍🎓 Detail Murid & Riwayat Pembayaran</h3>
                <button type="button" class="modal-close" onclick="closeMuridDetail()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <h4 style="margin: 0 0 15px 0; color: #334155;">Data Murid</h4>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px;">
                        <div>
                            <label style="font-size: 12px; color: #718096; font-weight: 600;">NISN</label>
                            <p id="detail_nisn" style="margin: 5px 0; font-size: 14px; color: #1f2937;"></p>
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #718096; font-weight: 600;">NIK</label>
                            <p id="detail_nik" style="margin: 5px 0; font-size: 14px; color: #1f2937;"></p>
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #718096; font-weight: 600;">Nama Lengkap</label>
                            <p id="detail_nama" style="margin: 5px 0; font-size: 14px; color: #1f2937;"></p>
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #718096; font-weight: 600;">Tingkat</label>
                            <p id="detail_tingkat" style="margin: 5px 0; font-size: 14px; color: #1f2937;"></p>
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #718096; font-weight: 600;">Tahun Masuk / Angkatan</label>
                            <p id="detail_tahun_masuk" style="margin: 5px 0; font-size: 14px; color: #1f2937;"></p>
                        </div>
                    </div>
                </div>

                <!-- Year Selector - UPDATED DYNAMIC -->
                <div style="background: #fff; padding: 15px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px;">
                    <div style="display: flex; gap: 10px; align-items: center; justify-content: space-between;">
                        <label style="font-weight: 600; color: #334155;">📅 Pilih Tahun Riwayat:</label>
                        <select id="riwayat_tahun" onchange="changeRiwayatYear()" style="padding: 8px 12px; border: 1px solid #e2e8f0; border-radius: 6px;">
                            <!-- Will be populated by JavaScript -->
                        </select>
                    </div>
                </div>

                <div style="background: #fff; padding: 20px; border-radius: 8px; border: 1px solid #e2e8f0;">
                    <h4 style="margin: 0 0 15px 0; color: #334155;">📊 Riwayat Pembayaran SPP <span id="riwayat_year_display"><?= date('Y') ?></span></h4>
                    <div id="detail_riwayat_content">
                        <!-- Content will be loaded by JavaScript -->
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" style="background: #64748b; color: white;" onclick="closeMuridDetail()">Tutup</button>
                <button type="button" class="btn btn-success" onclick="bayarDariDetail()">💰 Bayar Lunas/Cicilan</button>
            </div>
        </div>
    </div>

    <!-- Modal Detail Pembayaran -->
    <div id="detailModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3>📄 Detail Pembayaran</h3>
                <button type="button" class="modal-close" onclick="closeDetailModal()">&times;</button>
            </div>
            
            <div class="modal-body" id="detailContent">
                <!-- Content will be loaded by JavaScript -->
            </div>

            <div class="modal-footer">
                <button type="button" class="btn" style="background: #64748b; color: white;" onclick="closeDetailModal()">Tutup</button>
                <button type="button" class="btn btn-primary" onclick="printInvoice()">🖨️ Cetak Invoice</button>
                <button type="button" class="btn btn-success" onclick="downloadInvoice()">📥 Download PDF</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function previewBukti(input) {
            const preview = document.getElementById('preview_bukti');
            const container = document.getElementById('preview_container');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        container.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                    container.style.display = 'block';
                    container.innerHTML = '<p style="color: #10b981;">📄 File PDF terpilih: ' + file.name + '</p>';
                }
            }
        }

        function previewBuktiBatch(input) {
            const preview = document.getElementById('preview_bukti_batch');
            const container = document.getElementById('preview_container_batch');
            
            if (input.files && input.files[0]) {
                const file = input.files[0];
                const fileType = file.type;
                
                if (fileType.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        preview.src = e.target.result;
                        preview.style.display = 'block';
                        container.style.display = 'block';
                    }
                    reader.readAsDataURL(file);
                } else {
                    preview.style.display = 'none';
                    container.style.display = 'block';
                    container.innerHTML = '<p style="color: #10b981;">📄 File PDF terpilih: ' + file.name + '</p>';
                }
            }
        }

        function openModal() {
            document.getElementById('pembayaranModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Input Pembayaran';
            document.getElementById('pembayaranForm').reset();
            document.getElementById('pembayaran_id').value = '';
            document.getElementById('tanggal_bayar').value = '<?= date('Y-m-d') ?>';
            document.getElementById('preview_container').style.display = 'none';
            toggleBulan();
        }

        function closeModal() {
            document.getElementById('pembayaranModal').style.display = 'none';
        }

        function toggleBulan() {
            const jenis = document.getElementById('jenis_pembayaran').value;
            const bulanGroup = document.getElementById('bulan_group');
            const bulanSelect = document.getElementById('bulan');
            
            if (jenis === 'SPP') {
                bulanGroup.style.display = 'block';
                bulanSelect.required = true;
            } else {
                bulanGroup.style.display = 'none';
                bulanSelect.required = false;
                bulanSelect.value = '';
            }
        }

        function editPembayaran(data) {
            document.getElementById('pembayaranModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit Pembayaran';
            document.getElementById('pembayaran_id').value = data.id;
            document.getElementById('murid_id').value = data.murid_id;
            document.getElementById('jenis_pembayaran').value = data.jenis_pembayaran;
            document.getElementById('bulan').value = data.bulan || '';
            document.getElementById('jumlah').value = data.jumlah;
            document.getElementById('tanggal_bayar').value = data.tanggal_bayar;
            document.getElementById('metode_pembayaran').value = data.metode_pembayaran || '';
            document.getElementById('keterangan').value = data.keterangan || '';

            const previewContainer = document.getElementById('preview_container');
            const currentBukti = document.getElementById('current_bukti');
            
            if (data.bukti_pembayaran) {
                currentBukti.href = '../uploads/bukti/' + data.bukti_pembayaran;
                currentBukti.style.display = 'inline';
                previewContainer.style.display = 'block';
            } else {
                previewContainer.style.display = 'none';
            }
            
            toggleBulan();
        }

        function showBelumBayar() {
            document.getElementById('belumBayarModal').style.display = 'flex';
        }

        function closeBelumBayar() {
            document.getElementById('belumBayarModal').style.display = 'none';
        }

        function inputPembayaranDari(muridId, muridNama) {
            closeBelumBayar();
            openModal();
            document.getElementById('murid_id').value = muridId;
            document.getElementById('jenis_pembayaran').value = 'SPP';
            
            const currentMonth = <?= json_encode($current_month, JSON_HEX_TAG) ?>;
            document.getElementById('bulan').value = currentMonth;
            
            toggleBulan();
            
            setTimeout(() => {
                document.getElementById('jumlah').focus();
            }, 100);
        }

        let currentDetailMuridId = null;
        let currentDetailMuridNama = null;

        function viewMuridDetail(muridId) {
            currentDetailMuridId = muridId;
            document.getElementById('muridDetailModal').style.display = 'flex';
            
            fetch('get_murid_detail.php?murid_id=' + muridId)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.error) {
                        alert(data.error);
                        closeMuridDetail();
                        return;
                    }
                    
                    currentDetailMuridNama = data.nama;
                    document.getElementById('detail_nisn').textContent = data.nisn || '-';
                    document.getElementById('detail_nik').textContent = data.nik || '-';
                    document.getElementById('detail_nama').textContent = data.nama;
                    document.getElementById('detail_tingkat').textContent = data.tingkat;
                    
                    if (data.tahun_masuk) {
                        const tahunMasuk = parseInt(data.tahun_masuk.split('/')[0]);
                        const tahunSekarang = new Date().getFullYear();
                        const bulanSekarang = new Date().getMonth() + 1;
                        const tahunAjaran = bulanSekarang >= 7 ? tahunSekarang : tahunSekarang - 1;
                        const lamaBelajar = tahunAjaran - tahunMasuk + 1;
                        
                        document.getElementById('detail_tahun_masuk').innerHTML = 
                            `<strong>${data.tahun_masuk}</strong><br>` +
                            `<small style="color: #10b981;">Tahun ke-${lamaBelajar} bersekolah</small>`;
                        
                        populateYearSelector(data.tahun_masuk);
                    } else {
                        document.getElementById('detail_tahun_masuk').textContent = '-';
                        populateYearSelector(null);
                    }
                    
                    const currentYear = new Date().getFullYear();
                    document.getElementById('riwayat_tahun').value = currentYear;
                    loadRiwayatDetail(muridId, currentYear);
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Gagal memuat data murid. Error: ' + error.message);
                    closeMuridDetail();
                });
        }

        function closeMuridDetail() {
            document.getElementById('muridDetailModal').style.display = 'none';
            currentDetailMuridId = null;
            currentDetailMuridNama = null;
        }

        function bayarDariDetail() {
            if (currentDetailMuridId && currentDetailMuridNama) {
                closeMuridDetail();
                bayarLunas(currentDetailMuridId, currentDetailMuridNama);
            }
        }

        function changeRiwayatYear() {
            if (currentDetailMuridId) {
                const tahun = document.getElementById('riwayat_tahun').value;
                document.getElementById('riwayat_year_display').textContent = tahun;
                loadRiwayatDetail(currentDetailMuridId, tahun);
            }
        }

        function loadRiwayatDetail(muridId, tahun) {
            fetch('get_riwayat.php?murid_id=' + muridId + '&tahun=' + tahun)
                .then(response => response.json())
                .then(data => {
                    let html = '<div style="overflow-x: auto;"><table class="data-table"><thead><tr><th>Bulan</th><th>Status</th><th>Tanggal Bayar</th><th>Jumlah</th></tr></thead><tbody>';
                    
                    let totalBelumBayar = 0;
                    let totalLunas = 0;
                    
                    data.forEach(item => {
                        const statusBadge = item.status === 'Lunas' 
                            ? '<span class="badge badge-success">✅ Lunas</span>' 
                            : '<span class="badge badge-danger">❌ Belum Bayar</span>';
                        const tanggal = item.tanggal ? new Date(item.tanggal).toLocaleDateString('id-ID') : '-';
                        const jumlah = item.jumlah > 0 ? 'Rp ' + Number(item.jumlah).toLocaleString('id-ID') : '-';
                        
                        if (item.status === 'Lunas') {
                            totalLunas++;
                        } else {
                            totalBelumBayar++;
                        }
                        
                        html += `<tr><td>${item.bulan}</td><td>${statusBadge}</td><td>${tanggal}</td><td>${jumlah}</td></tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    
                    html += `
                        <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin-top: 15px;">
                            <div style="background: #d1fae5; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold; color: #065f46;">${totalLunas}</div>
                                <div style="font-size: 12px; color: #047857;">Bulan Sudah Bayar</div>
                            </div>
                            <div style="background: #fee2e2; padding: 15px; border-radius: 8px; text-align: center;">
                                <div style="font-size: 24px; font-weight: bold; color: #991b1b;">${totalBelumBayar}</div>
                                <div style="font-size: 12px; color: #dc2626;">Bulan Belum Bayar</div>
                            </div>
                        </div>
                    `;
                    
                    const currentYear = parseInt(tahun);
                    html += `
                        <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px;">
                            ${currentYear > 2020 ? `<button type="button" class="btn btn-primary" onclick="navigateYear(${currentYear - 1})">◀ ${currentYear - 1}</button>` : ''}
                            ${currentYear < new Date().getFullYear() ? `<button type="button" class="btn btn-primary" onclick="navigateYear(${currentYear + 1})">${currentYear + 1} ▶</button>` : ''}
                        </div>
                    `;
                    
                    document.getElementById('detail_riwayat_content').innerHTML = html;
                });
        }

        function navigateYear(tahun) {
            document.getElementById('riwayat_tahun').value = tahun;
            changeRiwayatYear();
        }

        function lihatRiwayat(muridId, muridNama) {
            document.getElementById('riwayatTitle').textContent = '📊 Riwayat Pembayaran SPP - ' + muridNama;
            document.getElementById('riwayatModal').style.display = 'flex';
            
            const currentYear = new Date().getFullYear();
            
            fetch('get_riwayat.php?murid_id=' + muridId + '&tahun=' + currentYear)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600;">Tahun: ${currentYear}</span>
                            <div style="display: flex; gap: 8px;">
                                ${currentYear > 2020 ? `<button type="button" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="loadRiwayatYear(${muridId}, '${muridNama.replace(/'/g, "\\'")}', ${currentYear - 1})">◀ ${currentYear - 1}</button>` : ''}
                                ${currentYear < new Date().getFullYear() ? `<button type="button" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="loadRiwayatYear(${muridId}, '${muridNama.replace(/'/g, "\\'")}', ${currentYear + 1})">${currentYear + 1} ▶</button>` : ''}
                            </div>
                        </div>
                    `;
                    
                    html += '<div style="overflow-x: auto;"><table class="data-table"><thead><tr><th>Bulan</th><th>Status</th><th>Tanggal Bayar</th><th>Jumlah</th></tr></thead><tbody>';
                    
                    data.forEach(item => {
                        const statusBadge = item.status === 'Lunas' 
                            ? '<span class="badge badge-success">✅ Lunas</span>' 
                            : '<span class="badge badge-danger">❌ Belum Bayar</span>';
                        const tanggal = item.tanggal ? new Date(item.tanggal).toLocaleDateString('id-ID') : '-';
                        const jumlah = item.jumlah > 0 ? 'Rp ' + Number(item.jumlah).toLocaleString('id-ID') : '-';
                        
                        html += `<tr><td>${item.bulan}</td><td>${statusBadge}</td><td>${tanggal}</td><td>${jumlah}</td></tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    html += `<div style="margin-top: 20px; text-align: center;"><button type="button" class="btn btn-success" onclick="bayarLunas(${muridId}, '${muridNama.replace(/'/g, "\\'")}')">💰 Bayar Lunas / Cicilan</button></div>`;
                    
                    document.getElementById('riwayatContent').innerHTML = html;
                });
        }

        function loadRiwayatYear(muridId, muridNama, tahun) {
            fetch('get_riwayat.php?murid_id=' + muridId + '&tahun=' + tahun)
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div style="background: #f0f9ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; display: flex; justify-content: space-between; align-items: center;">
                            <span style="font-weight: 600;">Tahun: ${tahun}</span>
                            <div style="display: flex; gap: 8px;">
                                ${tahun > 2020 ? `<button type="button" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="loadRiwayatYear(${muridId}, '${muridNama.replace(/'/g, "\\'")}', ${tahun - 1})">◀ ${tahun - 1}</button>` : ''}
                                ${tahun < new Date().getFullYear() ? `<button type="button" class="btn btn-primary" style="padding: 6px 12px; font-size: 12px;" onclick="loadRiwayatYear(${muridId}, '${muridNama.replace(/'/g, "\\'")}', ${tahun + 1})">${tahun + 1} ▶</button>` : ''}
                            </div>
                        </div>
                    `;
                    
                    html += '<div style="overflow-x: auto;"><table class="data-table"><thead><tr><th>Bulan</th><th>Status</th><th>Tanggal Bayar</th><th>Jumlah</th></tr></thead><tbody>';
                    
                    data.forEach(item => {
                        const statusBadge = item.status === 'Lunas' 
                            ? '<span class="badge badge-success">✅ Lunas</span>' 
                            : '<span class="badge badge-danger">❌ Belum Bayar</span>';
                        const tanggal = item.tanggal ? new Date(item.tanggal).toLocaleDateString('id-ID') : '-';
                        const jumlah = item.jumlah > 0 ? 'Rp ' + Number(item.jumlah).toLocaleString('id-ID') : '-';
                        
                        html += `<tr><td>${item.bulan}</td><td>${statusBadge}</td><td>${tanggal}</td><td>${jumlah}</td></tr>`;
                    });
                    
                    html += '</tbody></table></div>';
                    html += `<div style="margin-top: 20px; text-align: center;"><button type="button" class="btn btn-success" onclick="bayarLunas(${muridId}, '${muridNama.replace(/'/g, "\\'")}')">💰 Bayar Lunas / Cicilan</button></div>`;
                    
                    document.getElementById('riwayatContent').innerHTML = html;
                });
        }

        function closeRiwayat() {
            document.getElementById('riwayatModal').style.display = 'none';
        }

        function bayarLunas(muridId, muridNama) {
            closeRiwayat();
            closeMuridDetail(); // Close detail modal if open
            
            document.getElementById('batchModal').style.display = 'flex';
            document.getElementById('batch_murid_id').value = muridId;
            document.getElementById('batch_murid_nama').textContent = muridNama;
            
            // Reset form
            document.getElementById('batchForm').reset();
            document.getElementById('batch_tanggal').value = '<?= date('Y-m-d') ?>';
            
            const currentYear = new Date().getFullYear();
            
            fetch('get_riwayat.php?murid_id=' + muridId + '&tahun=' + currentYear)
                .then(response => response.json())
                .then(data => {
                    const bulanList = document.getElementById('bulan_checkbox_list');
                    bulanList.innerHTML = '';
                    
                    let hasUnpaid = false;
                    
                    data.forEach(item => {
                        if (item.status === 'Belum Bayar') {
                            hasUnpaid = true;
                            const div = document.createElement('div');
                            div.style.cssText = 'padding: 10px; background: #fef3c7; border-radius: 6px;';
                            div.innerHTML = `
                                <label style="display: flex; align-items: center; cursor: pointer;">
                                    <input type="checkbox" name="bulan_terpilih[]" value="${item.bulan}" onchange="hitungTotal()" style="margin-right: 8px;">
                                    <span>${item.bulan}</span>
                                </label>
                            `;
                            bulanList.appendChild(div);
                        }
                    });
                    
                    if (!hasUnpaid) {
                        bulanList.innerHTML = `
                            <div style="grid-column: 1 / -1; text-align: center; padding: 20px; background: #d1fae5; border-radius: 8px;">
                                <div style="font-size: 32px; margin-bottom: 10px;">🎉</div>
                                <h4 style="margin: 0 0 5px 0; color: #065f46;">Semua Sudah Lunas!</h4>
                                <p style="margin: 0; color: #047857; font-size: 14px;">Semua pembayaran SPP tahun ${currentYear} sudah lunas</p>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    console.error('Error loading payment history:', error);
                    alert('Gagal memuat data riwayat pembayaran');
                    closeBatch();
                });
        }

        function closeBatch() {
            document.getElementById('batchModal').style.display = 'none';
            document.getElementById('batchForm').reset();
            document.getElementById('preview_container_batch').style.display = 'none';
            document.getElementById('total_amount').textContent = 'Rp 0';
        }

        function hitungTotal() {
            const checkboxes = document.querySelectorAll('input[name="bulan_terpilih[]"]:checked');
            const jumlah = parseInt(document.getElementById('batch_jumlah').value) || 0;
            const total = checkboxes.length * jumlah;
            
            document.getElementById('total_amount').textContent = 'Rp ' + total.toLocaleString('id-ID');
        }

        document.getElementById('batch_jumlah').addEventListener('input', hitungTotal);

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            if (!table) return; // tabel kosong
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                let found = false;
                
                // Search in: NISN, Nama, Tingkat, Jenis, Bulan, Metode
                const nisn = cells[1]?.textContent || '';
                const nama = cells[2]?.textContent || '';
                const tingkat = cells[3]?.textContent || '';
                const jenis = cells[4]?.textContent || '';
                const bulan = cells[5]?.textContent || '';
                const metode = cells[9]?.textContent || '';
                
                const searchText = (nisn + nama + tingkat + jenis + bulan + metode).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        let currentPaymentData = null;

        function lihatDetail(data) {
            currentPaymentData = data;
            document.getElementById('detailModal').style.display = 'flex';
            
            const content = document.getElementById('detailContent');
            
            const bulanIndo = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 
                              'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
            
            const dateBayar = new Date(data.tanggal_bayar);
            const tanggalBayar = dateBayar.getDate() + ' ' + 
                                bulanIndo[dateBayar.getMonth()] + ' ' + 
                                dateBayar.getFullYear();
            
            const now = new Date();
            const tanggalSekarang = now.getDate() + ' ' + 
                                   bulanIndo[now.getMonth()] + ' ' + 
                                   now.getFullYear();
            const waktuSekarang = ('0' + now.getHours()).slice(-2) + ':' + 
                                 ('0' + now.getMinutes()).slice(-2) + ' WIB';
            
            let buktiSection = '';
            if (data.bukti_pembayaran) {
                const fileExt = data.bukti_pembayaran.split('.').pop().toLowerCase();
                if (['jpg', 'jpeg', 'png', 'gif'].includes(fileExt)) {
                    buktiSection = `
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #334155;">Bukti Pembayaran</h4>
                        <img src="../uploads/bukti/${data.bukti_pembayaran}" style="max-width: 100%; border-radius: 6px; border: 1px solid #e2e8f0;">
                    </div>
                    `;
                } else {
                    buktiSection = `
                    <div style="background: white; padding: 15px; border-radius: 6px; margin-bottom: 15px;">
                        <h4 style="margin: 0 0 10px 0; color: #334155;">Bukti Pembayaran</h4>
                        <a href="../uploads/bukti/${data.bukti_pembayaran}" target="_blank" class="btn btn-success">📄 Lihat Bukti PDF</a>
                    </div>
                    `;
                }
            }
            
            content.innerHTML = `
                <div style="border: 2px solid #e2e8f0; border-radius: 8px; padding: 20px, 10px; background: #f8f9fa;">
                    <div style="text-align: center; margin-bottom: 20px; border-bottom: 2px solid #10b981; padding-bottom: 15px;">
                        <h2 style="margin: 0; color: #10b981;">BUKTI PEMBAYARAN</h2>
                        <p style="margin: 5px 0; color: #64748b;">YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</p>
                    </div>
                    
                    ${buktiSection}
                    
                    <div style="background: white; padding: 15px; border-radius: 6px, 10px; margin-bottom: 15px;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>No. Transaksi:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">#PAY${String(data.id).padStart(6, '0')}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>Tanggal Bayar:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${tanggalBayar}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>NISN:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${data.nisn}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>Nama:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${data.nama}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>Jenis:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${data.jenis_pembayaran}</td>
                            </tr>
                            ${data.bulan ? `<tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>Bulan:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${data.bulan}</td>
                            </tr>` : ''}
                            <tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>Metode:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${data.metode_pembayaran || '-'}</td>
                            </tr>
                            ${data.keterangan ? `<tr>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;"><strong>Keterangan:</strong></td>
                                <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0; text-align: right;">${data.keterangan}</td>
                            </tr>` : ''}
                        </table>
                    </div>
                    
                    <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); padding: 15px; border-radius: 6px; color: white; text-align: center;">
                        <p style="margin: 0; font-size: 14px; opacity: 0.9;">Total Pembayaran</p>
                        <h2 style="margin: 5px 0 0 0; font-size: 32px;">Rp ${Number(data.jumlah).toLocaleString('id-ID')}</h2>
                    </div>
                    
                    <div style="margin-top: 20px; padding-top: 15px; border-top: 2px dashed #e2e8f0; text-align: center; color: #64748b; font-size: 12px;">
                        <p style="margin: 5px 0;">Dicetak pada: ${tanggalSekarang}, ${waktuSekarang}</p>
                        <p style="margin: 5px 0;">Status: <strong style="color: #10b981;">LUNAS</strong></p>
                    </div>
                </div>
            `;
        }

        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
            currentPaymentData = null;
        }

        function printInvoice() {
            if (!currentPaymentData) return;
            
            // Open invoice print page in new window
            const printWindow = window.open('invoice_print.php?id=' + currentPaymentData.id, '_blank');
        }

        function downloadInvoice() {
            if (!currentPaymentData) return;
            
            // Open print dialog with instructions to save as PDF
            const printWindow = window.open('invoice_print.php?id=' + currentPaymentData.id + '&download=1', '_blank', 'width=900,height=700');
            
            // Show instruction
            setTimeout(() => {
                if (!printWindow || printWindow.closed || typeof printWindow.closed == 'undefined') {
                    alert('Popup terblokir! Silakan izinkan popup untuk download PDF.\n\nCara download:\n1. Klik tombol Download PDF\n2. Izinkan popup\n3. Di dialog print, pilih "Save as PDF"\n4. Klik Save');
                }
            }, 500);
        }

        window.onclick = function(event) {
            const modal = document.getElementById('pembayaranModal');
            const belumBayarModal = document.getElementById('belumBayarModal');
            const riwayatModal = document.getElementById('riwayatModal');
            const batchModal = document.getElementById('batchModal');
            const detailModal = document.getElementById('detailModal');
            const muridDetailModal = document.getElementById('muridDetailModal');
            
            if (event.target == modal) closeModal();
            if (event.target == belumBayarModal) closeBelumBayar();
            if (event.target == riwayatModal) closeRiwayat();
            if (event.target == batchModal) closeBatch();
            if (event.target == detailModal) closeDetailModal();
            if (event.target == muridDetailModal) closeMuridDetail();
        }

        toggleBulan();

        <?php if (isset($_GET['edit']) && $edit_data): ?>
        editPembayaran(<?= json_encode($edit_data) ?>);
        <?php endif; ?>

        function showMuridInfo() {
            const select = document.getElementById('murid_id');
            const option = select.options[select.selectedIndex];
            const angkatan = option.getAttribute('data-angkatan');
            const tingkat = option.getAttribute('data-tingkat');
            const status = option.getAttribute('data-status');
            const infoDisplay = document.getElementById('murid_info_display');
            const infoText = document.getElementById('info_text');
            const statusWarning = document.getElementById('murid_status_warning');
            const statusWarningText = document.getElementById('status_warning_text');
            
            // Check status murid
            if (status && status !== 'Aktif') {
                statusWarningText.textContent = `Murid ini berstatus "${status}". Pembayaran SPP otomatis dihentikan untuk murid yang sudah ${status}.`;
                statusWarning.style.display = 'block';
            } else {
                statusWarning.style.display = 'none';
            }
            
            if (angkatan && angkatan !== '') {
                const tahunMasuk = parseInt(angkatan.split('/')[0]);
                const tahunSekarang = new Date().getFullYear();
                const bulanSekarang = new Date().getMonth() + 1;
                const tahunAjaran = bulanSekarang >= 7 ? tahunSekarang : tahunSekarang - 1;
                const lamaBelajar = tahunAjaran - tahunMasuk;
                
                let keterangan = '';
                if (lamaBelajar === 0) {
                    keterangan = 'Tahun pertama (Murid baru)';
                } else if (lamaBelajar === 1) {
                    keterangan = 'Tahun ke-2';
                } else if (lamaBelajar === 2) {
                    keterangan = 'Tahun ke-3';
                } else if (lamaBelajar > 2) {
                    keterangan = 'Tahun ke-' + (lamaBelajar + 1);
                } else {
                    keterangan = 'Belum mulai';
                }
                
                infoText.innerHTML = `📚 Angkatan ${angkatan} | ${keterangan} | Tingkat: ${tingkat}`;
                infoDisplay.style.display = 'block';
            } else {
                infoDisplay.style.display = 'none';
            }
        }

        function populateYearSelector(tahunMasuk) {
            const select = document.getElementById('riwayat_tahun');
            const currentYear = new Date().getFullYear();
            const currentMonth = new Date().getMonth() + 1;
            const tahunAjaranSekarang = currentMonth >= 7 ? currentYear : currentYear - 1;
            
            select.innerHTML = '';
            
            let startYear = tahunMasuk ? parseInt(tahunMasuk.split('/')[0]) : 2020;
            const endYear = currentYear + 1;
            
            for (let y = endYear; y >= startYear; y--) {
                const option = document.createElement('option');
                option.value = y;
                option.textContent = y;
                if (y === currentYear) {
                    option.selected = true;
                }
                select.appendChild(option);
            }
        }
    </script>
</body>
</html>
