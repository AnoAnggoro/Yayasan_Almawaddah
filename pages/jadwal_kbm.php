<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM jadwal_kbm WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header("Location: jadwal_kbm.php?success=delete");
    exit();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $tingkat = $_POST['tingkat'];
    $hari = $_POST['hari'];
    $waktu_mulai = $_POST['waktu_mulai'];
    $waktu_selesai = $_POST['waktu_selesai'];
    $waktu = $waktu_mulai . ' - ' . $waktu_selesai;
    $tema = $_POST['tema'];
    $guru_id = $_POST['guru_id'];

    if ($id) {
        // Update
        $stmt = $db->prepare("UPDATE jadwal_kbm SET tingkat=:tingkat, hari=:hari, waktu=:waktu, tema=:tema, guru_id=:guru_id WHERE id=:id");
        $stmt->bindParam(':id', $id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO jadwal_kbm (tingkat, hari, waktu, tema, guru_id) VALUES (:tingkat, :hari, :waktu, :tema, :guru_id)");
    }
    
    $stmt->bindParam(':tingkat', $tingkat);
    $stmt->bindParam(':hari', $hari);
    $stmt->bindParam(':waktu', $waktu);
    $stmt->bindParam(':tema', $tema);
    $stmt->bindParam(':guru_id', $guru_id);
    $stmt->execute();
    
    header("Location: jadwal_kbm.php?success=save");
    exit();
}

// Get filter - sekarang support "Semua" untuk melihat semua tingkat
$filter_tingkat = $_GET['tingkat'] ?? 'Semua';

// Get jadwal by days
$days = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
$jadwal_by_day = [];

foreach ($days as $day) {
    if ($filter_tingkat == 'Semua') {
        $query = "SELECT j.*, g.nama as nama_guru 
                  FROM jadwal_kbm j 
                  LEFT JOIN guru g ON j.guru_id = g.id 
                  WHERE j.hari = :hari 
                  ORDER BY j.waktu, j.tingkat";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':hari', $day);
    } else {
        $query = "SELECT j.*, g.nama as nama_guru 
                  FROM jadwal_kbm j 
                  LEFT JOIN guru g ON j.guru_id = g.id 
                  WHERE j.tingkat = :tingkat AND j.hari = :hari 
                  ORDER BY j.waktu";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':tingkat', $filter_tingkat);
        $stmt->bindParam(':hari', $day);
    }
    $stmt->execute();
    $jadwal_by_day[$day] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all guru for dropdown
$guru_stmt = $db->query("SELECT id, nama FROM guru WHERE status='Aktif' ORDER BY nama");
$guru_options = $guru_stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM jadwal_kbm WHERE id = :id");
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
    <title>Jadwal KBM - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .schedule-item {
            background: linear-gradient(135deg, #5a8c6a 0%, #5a8c6a 100%);
            color: white;
            padding: 10px;
            border-radius: 8px;
            margin-bottom: 8px;
            position: relative;
        }
        
        .schedule-item:last-child {
            margin-bottom: 0;
        }
        
        .schedule-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-bottom: 5px;
            font-weight: 600;
        }
        
        .schedule-tema {
            font-weight: 600;
            margin-bottom: 5px;
            font-size: 13px;
        }
        
        .schedule-guru {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .schedule-actions {
            display: flex;
            gap: 5px;
            margin-top: 8px;
        }
        
        .btn-icon {
            background: rgba(255,255,255,0.2);
            border: none;
            padding: 4px 8px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            color: white;
            transition: all 0.3s;
        }
        
        .btn-icon:hover {
            background: rgba(255,255,255,0.4);
        }
        
        .btn-detail {
            background: rgba(255,255,255,0.9);
            color: #171719ff;
            font-weight: 600;
            flex: 1;
            text-align: center;
        }
        
        .multiple-schedule {
            max-height: 200px;
            overflow-y: auto;
            position: relative;
        }
        
        .multiple-schedule::-webkit-scrollbar {
            width: 6px;
        }
        
        .multiple-schedule::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 3px;
        }
        
        .multiple-schedule::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        .schedule-count {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 10px;
            font-weight: bold;
            display: block;
            width: fit-content;
            margin: 8px auto 0;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
            z-index: 1;
        }
        
        /* Modal Detail */
        .detail-modal .modal-content {
            max-width: 500px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #5a8c6a;
        }
        
        .detail-item h4 {
            margin: 0 0 10px 0;
            color: #5a8c6a;
            font-size: 16px;
        }
        
        .detail-row {
            display: flex;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .detail-label {
            font-weight: 600;
            width: 120px;
            color: #555;
        }
        
        .detail-value {
            color: #333;
        }
        
        .detail-actions {
            margin-top: 10px;
            display: flex;
            gap: 8px;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-schedule"></i>
            <h2>📅 Jadwal KBM</h2>
        </div>

        <!-- Filter & Button -->
        <div class="content-card">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
                <div style="display: flex; gap: 10px; align-items: center;">
                    <label style="margin: 0; font-weight: 500;">Pilih Tingkat:</label>
                    <select id="filterTingkat" onchange="filterByTingkat()" style="padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;">
                        <option value="Semua" <?= $filter_tingkat == 'Semua' ? 'selected' : '' ?>>Semua Tingkat</option>
                        <option value="Kelompok A" <?= $filter_tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                        <option value="Kelompok B" <?= $filter_tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                    </select>
                </div>
                
                <div style="display: flex; gap: 10px; align-items: center; flex: 1; max-width: 500px;">
                    <input type="text" id="searchInput" placeholder="🔍 Cari tingkat, tema, atau nama guru..." 
                           style="flex: 1; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                           onkeyup="searchSchedule()">
                    
                    <button type="button" class="btn btn-success" onclick="openModal()">
                        ➕ Tambah Jadwal
                    </button>
                </div>
            </div>
        </div>

        <!-- Jadwal Table -->
        <div class="content-card">
            <div style="overflow-x: auto;">
                <table class="jadwal-table">
                    <thead>
                        <tr>
                            <th class="time-column">Waktu</th>
                            <th>Senin</th>
                            <th>Selasa</th>
                            <th>Rabu</th>
                            <th>Kamis</th>
                            <th>Jumat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Get all unique time slots
                        $all_jadwal = [];
                        foreach ($jadwal_by_day as $jadwals) {
                            foreach ($jadwals as $j) {
                                $all_jadwal[] = $j;
                            }
                        }
                        
                        // Get unique times
                        $times = [];
                        foreach ($all_jadwal as $j) {
                            if (!in_array($j['waktu'], $times)) {
                                $times[] = $j['waktu'];
                            }
                        }
                        sort($times);
                        
                        foreach ($times as $time):
                        ?>
                        <tr>
                            <td class="time-cell"><?= e($time) ?></td>
                            <?php foreach ($days as $day): 
                                if ($day == 'Sabtu') continue; // Skip Saturday
                                
                                // Find ALL jadwal for this day and time
                                $jadwal_list = [];
                                foreach ($jadwal_by_day[$day] as $jadwal) {
                                    if ($jadwal['waktu'] == $time) {
                                        $jadwal_list[] = $jadwal;
                                    }
                                }
                                
                                $count = count($jadwal_list);
                            ?>
                            <td class="schedule-cell">
                                <?php if ($count > 0): ?>
                                    <div class="multiple-schedule">
                                        
                                        <?php foreach ($jadwal_list as $jadwal): ?>
                                        <div class="schedule-item">
                                            <?php if ($filter_tingkat == 'Semua'): ?>
                                                <span class="schedule-badge"><?= htmlspecialchars($jadwal['tingkat']) ?></span>
                                            <?php endif; ?>
                                            <div class="schedule-tema"><?= htmlspecialchars($jadwal['tema']) ?></div>
                                            <div class="schedule-guru">👨‍🏫 <?= htmlspecialchars($jadwal['nama_guru'] ?? '-') ?></div>
                                            <div class="schedule-actions">
                                                <button onclick="viewDetail(<?= htmlspecialchars(json_encode($jadwal)) ?>)" class="btn-icon btn-detail" title="Lihat Detail">👁️ Detail</button>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                        
                                        <?php if ($count > 1): ?>
                                            <span class="schedule-count">📚 <?= e($count) ?> Jadwal</span>
                                        <?php endif; ?>
                                    </div>
                                <?php else: ?>
                                <div class="empty-schedule">-</div>
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="jadwalModal" class="modal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Jadwal</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="jadwalForm">
                <input type="hidden" name="id" id="jadwal_id">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label>Tingkat <span class="required">*</span></label>
                        <select name="tingkat" id="tingkat" required>
                            <option value="">Pilih Tingkat</option>
                            <option value="Kelompok A">Kelompok A</option>
                            <option value="Kelompok B">Kelompok B</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Hari <span class="required">*</span></label>
                        <select name="hari" id="hari" required>
                            <option value="">Pilih Hari</option>
                            <option value="Senin">Senin</option>
                            <option value="Selasa">Selasa</option>
                            <option value="Rabu">Rabu</option>
                            <option value="Kamis">Kamis</option>
                            <option value="Jumat">Jumat</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Waktu Mulai <span class="required">*</span></label>
                        <input type="time" name="waktu_mulai" id="waktu_mulai" required>
                    </div>

                    <div class="form-group">
                        <label>Waktu Selesai <span class="required">*</span></label>
                        <input type="time" name="waktu_selesai" id="waktu_selesai" required>
                    </div>

                    <div class="form-group">
                        <label>Tema/Kegiatan <span class="required">*</span></label>
                        <input type="text" name="tema" id="tema" placeholder="Contoh: Tema Diri Sendiri" required>
                    </div>

                    <div class="form-group">
                        <label>Guru Pengajar <span class="required">*</span></label>
                        <select name="guru_id" id="guru_id" required>
                            <option value="">Pilih Guru</option>
                            <?php foreach ($guru_options as $guru): ?>
                            <option value="<?= e($guru['id']) ?>">
                                <?= htmlspecialchars($guru['nama']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-success">Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail -->
    <div id="detailModal" class="modal detail-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>📋 Detail Jadwal</h3>
                <button type="button" class="modal-close" onclick="closeDetailModal()">&times;</button>
            </div>
            
            <div class="modal-body" id="detailContent">
                <!-- Content will be inserted by JavaScript -->
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeDetailModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function openModal() {
            document.getElementById('jadwalModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Tambah Jadwal';
            document.getElementById('jadwalForm').reset();
            document.getElementById('jadwal_id').value = '';
            document.getElementById('tingkat').value = <?= json_encode($filter_tingkat, JSON_HEX_TAG) ?>;
        }

        function closeModal() {
            document.getElementById('jadwalModal').style.display = 'none';
        }

        function editJadwal(data) {
            document.getElementById('jadwalModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit Jadwal';
            document.getElementById('jadwal_id').value = data.id;
            document.getElementById('tingkat').value = data.tingkat;
            document.getElementById('hari').value = data.hari;
            
            // Split waktu
            const waktuParts = data.waktu.split(' - ');
            document.getElementById('waktu_mulai').value = waktuParts[0];
            document.getElementById('waktu_selesai').value = waktuParts[1];
            
            document.getElementById('tema').value = data.tema;
            document.getElementById('guru_id').value = data.guru_id || '';
        }

        function viewDetail(data) {
            const modal = document.getElementById('detailModal');
            const content = document.getElementById('detailContent');
            
            content.innerHTML = `
                <div class="detail-item">
                    <h4>${data.tema}</h4>
                    <div class="detail-row">
                        <span class="detail-label">🎓 Tingkat:</span>
                        <span class="detail-value">${data.tingkat}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">📅 Hari:</span>
                        <span class="detail-value">${data.hari}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">⏰ Waktu:</span>
                        <span class="detail-value">${data.waktu}</span>
                    </div>
                    <div class="detail-row">
                        <span class="detail-label">👨‍🏫 Guru:</span>
                        <span class="detail-value">${data.nama_guru || '-'}</span>
                    </div>
                    <div class="detail-actions">
                        <button onclick="editJadwal(${JSON.stringify(data).replace(/"/g, '&quot;')}); closeDetailModal();" class="btn btn-primary" style="flex: 1;">✏️ Edit</button>
                        <a href="?delete=${data.id}&tingkat=<?= urlencode($filter_tingkat) ?>" onclick="return confirm('Yakin hapus jadwal ini?')" class="btn btn-danger" style="flex: 1; text-decoration: none; text-align: center;">🗑️ Hapus</a>
                    </div>
                </div>
            `;
            
            modal.style.display = 'flex';
        }
        
        function closeDetailModal() {
            document.getElementById('detailModal').style.display = 'none';
        }

        function filterByTingkat() {
            const tingkat = document.getElementById('filterTingkat').value;
            window.location.href = 'jadwal_kbm.php?tingkat=' + encodeURIComponent(tingkat);
        }

        function searchSchedule() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const scheduleItems = document.querySelectorAll('.schedule-item');

            scheduleItems.forEach(item => {
                // Search in: Badge (Tingkat), Tema, Guru
                const badge = item.querySelector('.schedule-badge')?.textContent || '';
                const tema = item.querySelector('.schedule-tema')?.textContent || '';
                const guru = item.querySelector('.schedule-guru')?.textContent || '';
                
                const searchText = (badge + tema + guru).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    item.style.display = '';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        window.onclick = function(event) {
            const modal = document.getElementById('jadwalModal');
            const detailModal = document.getElementById('detailModal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == detailModal) {
                closeDetailModal();
            }
        }

        <?php if (isset($_GET['edit']) && $edit_data): ?>
        editJadwal(<?= json_encode($edit_data) ?>);
        <?php endif; ?>
    </script>
</body>
</html>
