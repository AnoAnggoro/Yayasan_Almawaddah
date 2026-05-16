<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM nilai WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header("Location: nilai.php?success=delete");
    exit();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $murid_id = $_POST['murid_id'];
    $aspek_id = $_POST['aspek_id'];
    $semester = $_POST['semester'];
    $nilai = $_POST['nilai'] ?? null;
    $penilaian = $_POST['penilaian'];
    $keterangan = $_POST['keterangan'] ?? '';
    
    // Get tingkat from murid
    $stmt_murid = $db->prepare("SELECT tingkat FROM murid WHERE id = :id");
    $stmt_murid->bindParam(':id', $murid_id);
    $stmt_murid->execute();
    $murid_data = $stmt_murid->fetch(PDO::FETCH_ASSOC);
    $tingkat = $murid_data['tingkat'];

    if ($id) {
        // Update
        $stmt = $db->prepare("UPDATE nilai SET murid_id=:murid_id, tingkat=:tingkat, aspek_id=:aspek_id, semester=:semester, nilai=:nilai, penilaian=:penilaian, keterangan=:keterangan WHERE id=:id");
        $stmt->bindParam(':id', $id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO nilai (murid_id, tingkat, aspek_id, semester, nilai, penilaian, keterangan) VALUES (:murid_id, :tingkat, :aspek_id, :semester, :nilai, :penilaian, :keterangan)");
    }
    
    $stmt->bindParam(':murid_id', $murid_id);
    $stmt->bindParam(':tingkat', $tingkat);
    $stmt->bindParam(':aspek_id', $aspek_id);
    $stmt->bindParam(':semester', $semester);
    $stmt->bindParam(':nilai', $nilai);
    $stmt->bindParam(':penilaian', $penilaian);
    $stmt->bindParam(':keterangan', $keterangan);
    $stmt->execute();
    
    header("Location: nilai.php?success=save");
    exit();
}

// Get statistics - NEW
$total_aktif = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL")->fetchColumn();
$total_lulus = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Lulus'")->fetchColumn();
$total_pindah = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Pindah'")->fetchColumn();
$total_keluar = $db->query("SELECT COUNT(*) FROM murid WHERE status_murid = 'Keluar'")->fetchColumn();

// Get filter values - auto detect semester based on current month
$current_month = date('n'); // 1-12
$current_year = date('Y');
$default_tahun_ajaran = $current_month >= 7 ? $current_year . '/' . ($current_year + 1) : ($current_year - 1) . '/' . $current_year;
$default_semester = ($current_month >= 1 && $current_month <= 6) ? 'Semester 2' : 'Semester 1';

$filter_semester = $_GET['semester'] ?? $default_semester;
$filter_tingkat = $_GET['tingkat'] ?? '';
$filter_aspek = $_GET['aspek'] ?? '';
$filter_status_murid = $_GET['status_murid'] ?? ''; // CHANGED: Default ke kosong (semua status)
$filter_tahun_ajaran = $_GET['tahun_ajaran'] ?? '';

// Build query with new filters
$where_clause = "WHERE n.semester = :semester";
if ($filter_tingkat) {
    $where_clause .= " AND n.tingkat = :tingkat";
}
if ($filter_aspek) {
    $where_clause .= " AND n.aspek_id = :aspek";
}
// NEW: Status Murid Filter - FIXED
if ($filter_status_murid) {
    if ($filter_status_murid == 'Aktif') {
        $where_clause .= " AND (m.status_murid = 'Aktif' OR m.status_murid IS NULL)";
    } elseif ($filter_status_murid == 'Tidak Aktif') {
        $where_clause .= " AND m.status_murid IN ('Lulus', 'Pindah', 'Keluar')";
    } elseif (in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])) {
        $where_clause .= " AND m.status_murid = :status_spesifik";
    }
}
// FIXED: Add tahun ajaran filter to WHERE clause
if ($filter_tahun_ajaran) {
    $where_clause .= " AND m.tahun_masuk = :tahun_ajaran";
}
// NOTE: If $filter_status_murid is empty, no filter applied = show all statuses

// Get nilai data
$query = "SELECT n.*, m.nisn, m.nama, m.tingkat, m.status_murid, m.tahun_masuk, a.nama_aspek 
          FROM nilai n 
          JOIN murid m ON n.murid_id = m.id 
          JOIN aspek_penilaian a ON n.aspek_id = a.id 
          $where_clause 
          ORDER BY m.nama";
$stmt = $db->prepare($query);
$stmt->bindParam(':semester', $filter_semester);
if ($filter_tingkat) {
    $stmt->bindParam(':tingkat', $filter_tingkat);
}
if ($filter_aspek) {
    $stmt->bindParam(':aspek', $filter_aspek);
}
if ($filter_status_murid && in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])) {
    $stmt->bindParam(':status_spesifik', $filter_status_murid);
}
// FIXED: Bind tahun_ajaran parameter correctly
if ($filter_tahun_ajaran) {
    $stmt->bindParam(':tahun_ajaran', $filter_tahun_ajaran);
}
$stmt->execute();
$nilai_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all murid for dropdown
$murid_stmt = $db->query("SELECT id, nisn, nama, tingkat, status_murid FROM murid WHERE status_murid = 'Aktif' OR status_murid IS NULL ORDER BY nama");
$murid_options = $murid_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all aspek for dropdown
$aspek_stmt = $db->query("SELECT id, nama_aspek FROM aspek_penilaian ORDER BY nama_aspek");
$aspek_options = $aspek_stmt->fetchAll(PDO::FETCH_ASSOC);

// NEW: Get distinct tahun ajaran for filter
$tahun_stmt = $db->query("SELECT DISTINCT tahun_masuk FROM murid WHERE tahun_masuk IS NOT NULL ORDER BY tahun_masuk DESC");
$tahun_ajaran_list = $tahun_stmt->fetchAll(PDO::FETCH_COLUMN);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM nilai WHERE id = :id");
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
    <title>Data Nilai - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-grade"></i>
            <h2>⭐ Data Nilai</h2>
        </div>

        <!-- Statistics Cards - SIMPLE VERSION -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $total_aktif ?></div>
                <div class="stat-label">Murid Aktif</div>
            </div>
            <div class="stat-card danger">
                <div class="stat-icon">🎓</div>
                <div class="stat-value"><?= $total_lulus ?></div>
                <div class="stat-label">Lulus</div>
            </div>
            <div class="stat-card warning">
                <div class="stat-icon">🚚</div>
                <div class="stat-value"><?= $total_pindah ?></div>
                <div class="stat-label">Pindah</div>
            </div>
            <div class="stat-card info">
                <div class="stat-icon">🚪</div>
                <div class="stat-value"><?= $total_keluar ?></div>
                <div class="stat-label">Keluar</div>
            </div>
        </div>

        <!-- Filter Section - AUTO FILTER -->
        <div class="content-card">
            <div style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
                <button type="button" class="btn btn-success" onclick="openModal()">
                    ➕ Input Nilai
                </button>
                
                <select id="filterTingkat" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
                    <option value="">Semua Tingkat</option>
                    <option value="Kelompok A" <?= $filter_tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                    <option value="Kelompok B" <?= $filter_tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                </select>
                
                <select id="filterAspek" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px; max-width: 200px;">
                    <option value="">Semua Aspek</option>
                    <?php foreach ($aspek_options as $aspek): ?>
                    <option value="<?= $aspek['id'] ?>" <?= $filter_aspek == $aspek['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars(substr($aspek['nama_aspek'], 0, 30)) ?>...
                    </option>
                    <?php endforeach; ?>
                </select>

                <select id="filterSemester" onchange="autoFilter()" style="padding: 10px; border: 1px solid #e2e8f0; border-radius: 6px;">
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
                    <option value="<?= $tahun ?>" <?= $filter_tahun_ajaran == $tahun ? 'selected' : '' ?>>
                        <?= $tahun ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" class="btn" onclick="resetFilter()" 
                        style="background: #6c757d; color: white; padding: 10px 15px;"
                        title="Reset Filter">
                    🔄 Reset
                </button>
                
                <input type="text" id="searchInput" placeholder="🔍 Cari NISN, nama, tingkat, aspek..." 
                       style="flex: 1; min-width: 250px; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                       onkeyup="searchTable()">
            </div>
        </div>

        <!-- Data Table - UPDATE to show status and angkatan -->
        <div class="content-card">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                <h3 style="margin: 0;">Daftar Nilai
                    <?php if ($filter_status_murid): ?>
                        <span class="badge badge-<?= 
                            $filter_status_murid == 'Aktif' ? 'success' : 
                            ($filter_status_murid == 'Lulus' ? 'primary' : 'danger')
                        ?>" style="font-size: 12px; margin-left: 10px;">
                            <?= $filter_status_murid ?>
                        </span>
                    <?php endif; ?>
                    <?php if ($filter_tahun_ajaran): ?>
                        <span class="badge badge-info" style="font-size: 12px; margin-left: 10px;">
                            📚 <?= $filter_tahun_ajaran ?>
                        </span>
                    <?php endif; ?>
                </h3>
                <div style="font-size: 14px; color: #64748b;">
                    Total: <strong><?= count($nilai_list) ?></strong> data
                </div>
            </div>
            
            <?php if (count($nilai_list) > 0): ?>
            <div style="overflow-x: auto;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama Siswa</th>
                            <th>Tingkat</th>
                            <th>Angkatan</th>
                            <th>Status</th>
                            <th>Aspek</th>
                            <th>Penilaian</th>
                            <th>Keterangan</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nilai_list as $index => $nilai): 
                            $status_murid = $nilai['status_murid'] ?? 'Aktif';
                            $is_non_aktif = in_array($status_murid, ['Lulus', 'Pindah', 'Keluar']);
                        ?>
                        <tr <?= $is_non_aktif ? 'style="background: #fee2e2;"' : '' ?>>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($nilai['nisn']) ?></td>
                            <td><?= htmlspecialchars($nilai['nama']) ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $nilai['tingkat'] == 'Kelompok A' ? 'primary' : 'purple'
                                ?>">
                                    <?= $nilai['tingkat'] ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($nilai['tahun_masuk'] ?? '-') ?></td>
                            <td>
                                <span class="badge badge-<?= 
                                    $status_murid == 'Aktif' ? 'success' : 
                                    ($status_murid == 'Lulus' ? 'primary' : 'danger')
                                ?>">
                                    <?= $status_murid ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($nilai['nama_aspek']) ?></td>
                            <td>
                                <span class="badge badge-info">
                                    <?= htmlspecialchars($nilai['penilaian']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($nilai['keterangan']) ?: '-' ?></td>
                            <td>
                                <div style="display: flex; gap: 5px;">
                                    <a href="javascript:void(0)" onclick="editNilai(<?= htmlspecialchars(json_encode($nilai)) ?>)" class="btn btn-warning" title="Edit">✏️</a>
                                    <a href="?delete=<?= $nilai['id'] ?>&tingkat=<?= $filter_tingkat ?>&aspek=<?= $filter_aspek ?>&semester=<?= $filter_semester ?>&status_murid=<?= $filter_status_murid ?>&tahun_ajaran=<?= $filter_tahun_ajaran ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data nilai ini?')" title="Hapus">🗑️</a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($filter_status_murid == 'Tidak Aktif' || in_array($filter_status_murid, ['Lulus', 'Pindah', 'Keluar'])): ?>
            <div style="margin-top: 15px; padding: 12px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 6px;">
                <strong>📁 Info:</strong> Menampilkan data nilai murid dengan status: <strong><?= $filter_status_murid ?></strong>
            </div>
            <?php endif; ?>
            
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
                <p>Tidak ada data nilai</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="nilaiModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Input Nilai</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="nilaiForm">
                <input type="hidden" name="id" id="nilai_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Pilih Murid <span class="required">*</span></label>
                        <select name="murid_id" id="murid_id" required onchange="checkMuridStatusNilai()">
                            <option value="">Pilih Murid</option>
                            <?php foreach ($murid_options as $murid): 
                                $status_label = ($murid['status_murid'] ?? 'Aktif') !== 'Aktif' ? ' (' . $murid['status_murid'] . ')' : '';
                            ?>
                            <option value="<?= $murid['id'] ?>" 
                                    data-tingkat="<?= $murid['tingkat'] ?>"
                                    data-status="<?= $murid['status_murid'] ?? 'Aktif' ?>">
                                <?= htmlspecialchars($murid['nama']) ?> - <?= $murid['tingkat'] ?><?= $status_label ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="nilai_status_warning" style="display: none; margin-top: 8px; padding: 8px; background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 4px;">
                            <small style="color: #92400e; font-weight: 600;">
                                ℹ️ <span id="nilai_warning_text"></span>
                            </small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Aspek Penilaian <span class="required">*</span></label>
                        <select name="aspek_id" id="aspek_id" required style="height: auto;">
                            <option value="">Pilih Aspek Penilaian</option>
                            <?php foreach ($aspek_options as $aspek): ?>
                            <option value="<?= $aspek['id'] ?>">
                                <?= htmlspecialchars($aspek['nama_aspek']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <small style="color: #718096; font-size: 12px; margin-top: 5px; display: block;">
                            Pilih salah satu aspek penilaian
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Penilaian <span class="required">*</span></label>
                        <select name="penilaian" id="penilaian" required>
                            <option value="">Pilih Penilaian</option>
                            <option value="Sangat Menguasai (SM)">Sangat Menguasai (SM)</option>
                            <option value="Menguasai (M)">Menguasai (M)</option>
                            <option value="Mulai Menguasai (MM)">Mulai Menguasai (MM)</option>
                            <option value="Belum Menguasai (BM)">Belum Menguasai (BM)</option>
                        </select>
                        <small style="color: #718096; font-size: 12px; margin-top: 5px; display: block;">
                            Pilih tingkat penguasaan kompetensi anak
                        </small>
                    </div>

                    <div class="form-group">
                        <label>Semester <span class="required">*</span></label>
                        <select name="semester" id="semester" required>
                            <option value="">Pilih Semester</option>
                            <option value="Semester 1" selected>Semester 1</option>
                            <option value="Semester 2">Semester 2</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Keterangan</label>
                        <textarea name="keterangan" id="keterangan" rows="3" placeholder="Keterangan tambahan"></textarea>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function openModal() {
            document.getElementById('nilaiModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Input Nilai';
            document.getElementById('nilaiForm').reset();
            document.getElementById('nilai_id').value = '';
        }

        function closeModal() {
            document.getElementById('nilaiModal').style.display = 'none';
        }

        function editNilai(data) {
            document.getElementById('nilaiModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit Nilai';
            document.getElementById('nilai_id').value = data.id;
            document.getElementById('murid_id').value = data.murid_id;
            document.getElementById('aspek_id').value = data.aspek_id;
            document.getElementById('penilaian').value = data.penilaian;
            document.getElementById('semester').value = data.semester;
            document.getElementById('keterangan').value = data.keterangan || '';
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                
                // Search in: NISN, Nama, Tingkat, Angkatan, Status, Aspek, Penilaian, Keterangan
                const nisn = cells[1]?.textContent || '';
                const nama = cells[2]?.textContent || '';
                const tingkat = cells[3]?.textContent || '';
                const angkatan = cells[4]?.textContent || '';
                const status = cells[5]?.textContent || '';
                const aspek = cells[6]?.textContent || '';
                const penilaian = cells[7]?.textContent || '';
                const keterangan = cells[8]?.textContent || '';
                
                const searchText = (nisn + nama + tingkat + angkatan + status + aspek + penilaian + keterangan).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        function checkMuridStatusNilai() {
            const select = document.getElementById('murid_id');
            const option = select.options[select.selectedIndex];
            const status = option.getAttribute('data-status');
            const warning = document.getElementById('nilai_status_warning');
            const warningText = document.getElementById('nilai_warning_text');
            
            if (status && status !== 'Aktif') {
                warningText.textContent = `Murid berstatus "${status}". Input nilai tetap dapat dilakukan untuk kelengkapan data rapot.`;
                warning.style.display = 'block';
            } else {
                warning.style.display = 'none';
            }
        }

        function autoFilter() {
            const tingkat = document.getElementById('filterTingkat').value;
            const aspek = document.getElementById('filterAspek').value;
            const semester = document.getElementById('filterSemester').value;
            const statusMurid = document.getElementById('filterStatusMurid').value;
            const angkatan = document.getElementById('filterAngkatan').value;
            
            let url = 'nilai.php?';
            const params = [];
            
            if (tingkat) params.push('tingkat=' + encodeURIComponent(tingkat));
            if (aspek) params.push('aspek=' + encodeURIComponent(aspek));
            if (semester) params.push('semester=' + encodeURIComponent(semester));
            if (statusMurid) params.push('status_murid=' + encodeURIComponent(statusMurid));
            if (angkatan) params.push('tahun_ajaran=' + encodeURIComponent(angkatan));
            
            window.location.href = url + params.join('&');
        }
        
        function resetFilter() {
            window.location.href = 'nilai.php';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('nilaiModal');
            if (event.target == modal) {
                closeModal();
            }
        }

        <?php if (isset($_GET['edit']) && $edit_data): ?>
        editNilai(<?= json_encode($edit_data) ?>);
        <?php endif; ?>
    </script>
</body>
</html>
