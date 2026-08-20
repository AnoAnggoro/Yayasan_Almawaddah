<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get filter values - auto detect based on current date
$current_month = date('n');
$current_year = date('Y');

// Determine tahun ajaran
if ($current_month >= 7) {
    $default_tahun = $current_year . '/' . ($current_year + 1);
    $default_semester = 'Semester 1';
} else {
    $default_tahun = ($current_year - 1) . '/' . $current_year;
    $default_semester = 'Semester 2';
}

$filter_tahun = $_GET['tahun_ajaran'] ?? $default_tahun;
$filter_semester = $_GET['semester'] ?? $default_semester;
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_status_murid = $_GET['status_murid'] ?? ''; // CHANGED: Default ke kosong (semua status)

// Build query with status murid filter
$where_clause = "WHERE m.id IS NOT NULL";

// FIXED: Status Murid Filter
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

if ($filter_tingkat) {
    $where_clause .= " AND m.tingkat = :tingkat";
}

// Get murid list with rapot status and tahun_masuk
$query = "SELECT m.*, 
          (SELECT COUNT(*) FROM nilai n WHERE n.murid_id = m.id AND n.semester = :semester) as total_nilai,
          (SELECT COUNT(*) FROM aspek_penilaian) as total_aspek
          FROM murid m 
          $where_clause 
          ORDER BY m.nama";
$stmt = $db->prepare($query);
$stmt->bindParam(':semester', $filter_semester);
if ($filter_tingkat) {
    $stmt->bindParam(':tingkat', $filter_tingkat);
}
if ($filter_status_murid && in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])) {
    $stmt->bindParam(':status_murid_spesifik', $filter_status_murid);
}
$stmt->execute();
$murid_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get statistics
$total_aktif = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL")->fetchColumn();
$total_lulus = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Lulus'")->fetchColumn();
$total_pindah = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Pindah'")->fetchColumn();
$total_keluar = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Keluar'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Rapot - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-report"></i>
            <h2>📄 Data Rapot</h2>
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

        <div class="content-card" style="margin-bottom: 20px;">
            <h3 style="margin: 0 0 10px 0;">📁 Arsip</h3>
            <p style="margin: 0; color: #64748b;">
                Total Arsip: <strong style="color: #f59e0b;"><?= $total_lulus + $total_pindah + $total_keluar ?></strong> Murid
            </p>
        </div>

        <!-- Filter Section - AUTO FILTER -->
        <div class="content-card">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap;">
                <select id="filterTahunAjaran" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="2023/2024" <?= $filter_tahun == '2023/2024' ? 'selected' : '' ?>>2023/2024</option>
                    <option value="2024/2025" <?= $filter_tahun == '2024/2025' ? 'selected' : '' ?>>2024/2025</option>
                    <option value="2025/2026" <?= $filter_tahun == '2025/2026' ? 'selected' : '' ?>>2025/2026</option>
                </select>

                <select id="filterSemester" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="Semester 1" <?= $filter_semester == 'Semester 1' ? 'selected' : '' ?>>Semester 1</option>
                    <option value="Semester 2" <?= $filter_semester == 'Semester 2' ? 'selected' : '' ?>>Semester 2</option>
                </select>
                
                <select id="filterTingkat" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Tingkat</option>
                    <option value="Kelompok A" <?= $filter_tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                    <option value="Kelompok B" <?= $filter_tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                </select>

                <select id="filterStatusMurid" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Status</option>
                    <option value="Aktif" <?= $filter_status_murid == 'Aktif' ? 'selected' : '' ?>>✅ Aktif</option>
                    <option value="Tidak Aktif" <?= $filter_status_murid == 'Tidak Aktif' ? 'selected' : '' ?>>📁 Arsip (Semua)</option>
                    <option value="Lulus" <?= $filter_status_murid == 'Lulus' ? 'selected' : '' ?>>🎓 Lulus</option>
                    <option value="Pindah" <?= $filter_status_murid == 'Pindah' ? 'selected' : '' ?>>🚚 Pindah</option>
                    <option value="Keluar" <?= $filter_status_murid == 'Keluar' ? 'selected' : '' ?>>🚪 Keluar</option>
                </select>

                <button type="button" class="btn" onclick="resetFilter()" 
                        style="background: #6c757d; color: white; padding: 10px 15px;"
                        title="Reset Filter">
                    🔄 Reset
                </button>
                
                <input type="text" id="searchInput" placeholder="🔍 Cari NISN, nama, tingkat, semester, status, angkatan..." 
                       style="flex: 1; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                       onkeyup="searchTable()">
            </div>
        </div>

        <!-- Data Table -->
        <div class="content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Daftar Rapot Siswa
                    <?php if ($filter_status_murid): ?>
                        <span class="badge badge-<?= 
                            $filter_status_murid == 'Aktif' ? 'success' : 
                            ($filter_status_murid == 'Lulus' ? 'primary' : 'danger')
                        ?>" style="font-size: 12px; margin-left: 10px;">
                            <?= e($filter_status_murid) ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="font-size: 14px; color: #64748b;">
                    Total: <strong><?= count($murid_list) ?></strong> siswa
                </div>
            </div>
            
            <?php if (count($murid_list) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Tingkat</th>
                            <th>Status</th>
                            <th>Angkatan</th>
                            <th>Semester</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($murid_list as $index => $murid): 
                            $status_murid = $murid['status_murid'] ?? 'Aktif';
                            $is_non_aktif = in_array($status_murid, ['Lulus', 'Pindah', 'Keluar']);
                            
                            // Calculate lama belajar
                            $angkatan_info = '-';
                            $tahun_ke = '';
                            if ($murid['tahun_masuk']) {
                                $tahun_masuk = (int)explode('/', $murid['tahun_masuk'])[0];
                                $tahun_sekarang = (int)date('Y');
                                $bulan_sekarang = (int)date('n');
                                $tahun_ajaran = $bulan_sekarang >= 7 ? $tahun_sekarang : $tahun_sekarang - 1;
                                $lama_belajar = $tahun_ajaran - $tahun_masuk + 1;
                                
                                $angkatan_info = $murid['tahun_masuk'];
                                $tahun_ke = $lama_belajar > 0 ? " (Tahun ke-$lama_belajar)" : " (Belum mulai)";
                            }
                        ?>
                        <tr <?= $is_non_aktif ? 'style="background: #fef3c7;"' : '' ?>>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($murid['nisn']) ?></td>
                            <td><?= htmlspecialchars($murid['nama']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $murid['tingkat'] == 'Kelompok A' ? 'pink' : 'purple'
                                ?>">
                                    <?= e($murid['tingkat']) ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge badge-<?= 
                                    $status_murid == 'Aktif' ? 'success' : 
                                    ($status_murid == 'Lulus' ? 'primary' : 'danger')
                                ?>">
                                    <?= e($status_murid) ?>
                                </span>
                            </td>
                            <td>
                                <strong><?= e($angkatan_info) ?></strong>
                                <?php if ($tahun_ke): ?>
                                    <br><small style="color: <?= $is_non_aktif ? '#92400e' : '#10b981' ?>;"><?= e($tahun_ke) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= e($filter_semester) ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="rapot_view.php?murid_id=<?= e($murid['id']) ?>&semester=<?= urlencode($filter_semester) ?>&tahun=<?= urlencode($filter_tahun) ?>" 
                                       class="btn btn-success" 
                                       title="Lihat Rapot"
                                       target="_blank">
                                        👁️ Lihat
                                    </a>
                                    <a href="rapot_print.php?murid_id=<?= e($murid['id']) ?>&semester=<?= urlencode($filter_semester) ?>&tahun=<?= urlencode($filter_tahun) ?>" 
                                       class="btn btn-warning" 
                                       title="Cetak Rapot"
                                       target="_blank">
                                        🖨️ Cetak
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($filter_status_murid == 'Tidak Aktif' || in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])): ?>
            <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                <strong>📁 Info Arsip:</strong> Menampilkan rapot siswa dengan status: <strong><?= e($filter_status_murid) ?></strong>. 
                Data rapot tetap tersimpan sebagai arsip meskipun siswa sudah tidak aktif.
            </div>
            <?php elseif (count($murid_list) > 0 && $filter_status_murid == ''): ?>
                <?php 
                $count_non_aktif = 0;
                foreach ($murid_list as $m) {
                    if (in_array($m['status_murid'] ?? 'Aktif', ['Lulus', 'Pindah', 'Keluar'])) {
                        $count_non_aktif++;
                    }
                }
                if ($count_non_aktif > 0): 
                ?>
                <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                    <strong>⚠️ Perhatian:</strong> Terdapat <strong><?= e($count_non_aktif) ?></strong> data dengan latar kuning (siswa tidak aktif: Lulus/Pindah/Keluar)
                </div>
                <?php endif; ?>
            <?php endif; ?>

            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                <p>Tidak ada data siswa
                    <?php if ($filter_status_murid): ?>
                        dengan status <strong><?= e($filter_status_murid) ?></strong>
                    <?php endif; ?>
                </p>
                <?php if ($filter_status_murid || $filter_tingkat): ?>
                <a href="rapot.php?tahun_ajaran=<?= e($filter_tahun) ?>&semester=<?= e($filter_semester) ?>" class="btn btn-primary" style="margin-top: 15px; text-decoration: none;">
                    🔄 Reset Filter
                </a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function autoFilter() {
            const tahunAjaran = document.getElementById('filterTahunAjaran').value;
            const semester = document.getElementById('filterSemester').value;
            const tingkat = document.getElementById('filterTingkat').value;
            const statusMurid = document.getElementById('filterStatusMurid').value;
            
            let url = 'rapot.php?';
            const params = [];
            
            if (tahunAjaran) params.push('tahun_ajaran=' + encodeURIComponent(tahunAjaran));
            if (semester) params.push('semester=' + encodeURIComponent(semester));
            if (tingkat) params.push('tingkat=' + encodeURIComponent(tingkat));
            if (statusMurid) params.push('status_murid=' + encodeURIComponent(statusMurid));
            
            window.location.href = url + params.join('&');
        }
        
        function resetFilter() {
            window.location.href = 'rapot.php';
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            if (!table) return; // tabel kosong
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                
                // Search in: NISN, Nama, Tingkat, Status, Angkatan, Semester
                const nisn = cells[1]?.textContent || '';
                const nama = cells[2]?.textContent || '';
                const tingkat = cells[3]?.textContent || '';
                const status = cells[4]?.textContent || '';
                const angkatan = cells[5]?.textContent || '';
                const semester = cells[6]?.textContent || '';
                
                const searchText = (nisn + nama + tingkat + status + angkatan + semester).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }
    </script>
</body>
</html>
