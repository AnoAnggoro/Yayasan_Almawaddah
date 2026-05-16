<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

$murid_id = $_GET['murid_id'] ?? null;
$semester = $_GET['semester'] ?? 'Semester 1';
$tahun = $_GET['tahun'] ?? date('Y') . '/' . (date('Y') + 1);

if (!$murid_id) {
    header('Location: rapot.php');
    exit();
}

// Get murid data
$stmt = $db->prepare("SELECT * FROM murid WHERE id = :id");
$stmt->bindParam(':id', $murid_id);
$stmt->execute();
$murid = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$murid) {
    header('Location: rapot.php');
    exit();
}

// Get nilai data - FIXED: Removed kategori column
$stmt = $db->prepare("SELECT n.*, a.nama_aspek 
                      FROM nilai n 
                      JOIN aspek_penilaian a ON n.aspek_id = a.id 
                      WHERE n.murid_id = :murid_id AND n.semester = :semester 
                      ORDER BY a.nama_aspek");
$stmt->bindParam(':murid_id', $murid_id);
$stmt->bindParam(':semester', $semester);
$stmt->execute();
$nilai_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Function to get badge color - SIMPLIFIED
function getPenilaianBadge($penilaian) {
    return 'info'; // All same color
}

// Function to get emoji - REMOVED
function getPenilaianEmoji($penilaian) {
    return ''; // No emoji
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rapot - <?= htmlspecialchars($murid['nama']) ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            body { background: white; }
            .rapot-container { box-shadow: none; }
        }
        
        .rapot-container {
            max-width: 900px;
            margin: 20px auto;
            background: white;
            padding: 40px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        
        .rapot-header {
            text-align: center;
            border-bottom: 3px solid #10b981;
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .rapot-header h1 {
            margin: 0;
            color: #10b981;
            font-size: 28px;
        }
        
        .rapot-header h2 {
            margin: 5px 0;
            color: #334155;
            font-size: 20px;
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
        
        .info-row {
            display: grid;
            grid-template-columns: 150px 1fr;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: 600;
            color: #64748b;
        }
        
        .nilai-section {
            margin-bottom: 30px;
        }
        
        .section-header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 12px 20px;
            border-radius: 8px 8px 0 0;
            font-weight: 600;
            font-size: 16px;
        }
        
        .nilai-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        
        .nilai-table th {
            background: #f1f5f9;
            padding: 12px;
            text-align: left;
            border: 1px solid #e2e8f0;
            font-weight: 600;
            color: #334155;
        }
        
        .nilai-table td {
            padding: 12px;
            border: 1px solid #e2e8f0;
        }
        
        .nilai-table tr:hover {
            background: #f8fafc;
        }
        
        .penilaian-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        
        .badge-success { 
            background: #6c757d; 
            color: white; 
        }
        
        .badge-primary { 
            background: #6c757d; 
            color: white; 
        }
        
        .badge-warning { 
            background: #6c757d; 
            color: white; 
        }
        
        .badge-danger { 
            background: #6c757d; 
            color: white; 
        }
        
        .badge-info {
            background: #f0f0f0ff;
            color: black;
        }
        
        .keterangan-section {
            background: #fffbeb;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
        
        .keterangan-section h4 {
            margin: 0 0 10px 0;
            color: #92400e;
        }
        
        .legend {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .legend-title {
            font-weight: 600;
            margin-bottom: 10px;
            color: #334155;
        }
        
        .legend-item {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 5px;
        }
        
        .ttd-section {
            margin-top: 50px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
        }
        
        .ttd-box {
            text-align: center;
        }
        
        .ttd-line {
            border-bottom: 1px solid #000;
            margin: 60px 50px 5px 50px;
        }
        
        .report-footer {
            margin-top: 50px;
            padding-top: 30px;
        }
        
        .signature-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
            margin-bottom: 40px;
            padding-right: 50px;
        }
        
        .signature-box {
            text-align: center;
            min-width: 250px;
        }
        
        .signature-box p {
            margin: 5px 0;
            color: #000;
        }
        
        .footer-signature-line {
            margin-top: 70px;
            padding-top: 5px;
            border-top: 1px solid #000;
            display: inline-block;
            min-width: 200px;
        }
        
        .school-footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 3px solid #10b981;
            background: #f8f9fa;
            padding-bottom: 20px;
        }
        
        .school-name {
            font-size: 20px;
            font-weight: bold;
            color: #10b981;
            margin: 10px 0;
            letter-spacing: 1px;
        }
        
        .school-address {
            font-size: 14px;
            font-weight: normal;
            color: #64748b;
            margin: 8px 0;
        }
        
        #realTimeDate {
            font-weight: normal;
        }
    </style>
</head>
<body>
    <div class="rapot-container">
        <div class="rapot-header">
            <h1>RAPOT PERKEMBANGAN ANAK</h1>
            <h2>RA AL-MAWADDAH</h2>
            <p style="margin: 5px 0; color: #64748b;">Tahun Ajaran <?= htmlspecialchars($tahun) ?> - <?= htmlspecialchars($semester) ?></p>
        </div>

        <div class="student-info">
            <div class="info-row">
                <div class="info-label">NISN</div>
                <div>: <?= htmlspecialchars($murid['nisn']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Nama Lengkap</div>
                <div>: <?= htmlspecialchars($murid['nama']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Tingkat</div>
                <div>: <?= htmlspecialchars($murid['tingkat']) ?></div>
            </div>
            <div class="info-row">
                <div class="info-label">Tempat, Tgl Lahir</div>
                <div>: <?= htmlspecialchars($murid['tempat_lahir']) ?>, <?= date('d-m-Y', strtotime($murid['tanggal_lahir'])) ?></div>
            </div>
        </div>

        <div class="legend">
            <div class="legend-title">📊 Keterangan Penilaian:</div>
            <div class="legend-item" style="display: block; margin-bottom: 8px;">
                <strong>SM</strong> = Sangat Menguasai - Anak sudah sangat menguasai kompetensi
            </div>
            <div class="legend-item" style="display: block; margin-bottom: 8px;">
                <strong>M</strong> = Menguasai - Anak sudah menguasai kompetensi dengan baik
            </div>
            <div class="legend-item" style="display: block; margin-bottom: 8px;">
                <strong>MM</strong> = Mulai Menguasai - Anak mulai menguasai kompetensi
            </div>
            <div class="legend-item" style="display: block; margin-bottom: 8px;">
                <strong>BM</strong> = Belum Menguasai - Anak belum menguasai kompetensi
            </div>
        </div>

        <?php if (count($nilai_list) > 0): ?>
            <div class="nilai-section">
                <div class="section-header">
                    📚 Penilaian Perkembangan Anak
                </div>
                <table class="nilai-table">
                    <thead>
                        <tr>
                            <th width="5%">No</th>
                            <th width="50%">Aspek Penilaian</th>
                            <th width="25%">Penilaian</th>
                            <th width="20%">Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($nilai_list as $index => $nilai): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= htmlspecialchars($nilai['nama_aspek']) ?></td>
                            <td>
                                <span class="penilaian-badge badge-<?= getPenilaianBadge($nilai['penilaian']) ?>">
                                    <?= htmlspecialchars($nilai['penilaian']) ?>
                                </span>
                            </td>
                            <td><?= htmlspecialchars($nilai['keterangan']) ?: '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="keterangan-section">
                <h4>💡 Catatan Umum</h4>
                <p>Rapot ini menunjukkan perkembangan kemampuan anak selama <?= htmlspecialchars($semester) ?>. 
                Terus dampingi dan dukung perkembangan anak dengan penuh kasih sayang. 
                Konsultasikan dengan guru untuk perkembangan optimal anak.</p>
            </div>

            <div class="ttd-section">
                <div class="ttd-box">
                    <p>Orang Tua/Wali</p>
                    <div class="ttd-line"></div>
                    <p style="margin: 0;">(...........................)</p>
                </div>
                <div class="ttd-box">
                    <p>Wali Kelas</p>
                    <div class="ttd-line"></div>
                    <p style="margin: 0;">(...........................)</p>
                </div>
            </div>

          

        <?php else: ?>
            <div style="text-align: center; padding: 60px 20px; background: #fef3c7; border-radius: 8px;">
                <div style="font-size: 48px; margin-bottom: 15px;">📝</div>
                <h3 style="color: #92400e; margin: 0 0 10px 0;">Data Nilai Belum Tersedia</h3>
                <p style="color: #78350f; margin: 0;">Silakan hubungi wali kelas untuk informasi lebih lanjut.</p>
            </div>
        <?php endif; ?>

        <!-- Report Footer (Untuk Print/PDF) -->
        <div class="report-footer">
            <!-- Garis Pemisah -->
        
            
            <div class="signature-section">
                <div class="signature-box">
                    <p id="realTimeDate"></p>
                    <p>Kepala Sekolah</p>
                    <div class="footer-signature-line">
                        <strong>( RUSDIANA )</strong>
                    </div>
                </div>
            </div>
            
        

        <div class="no-print" style="margin-top: 30px; text-align: center; display: flex; gap: 10px; justify-content: center;">
            <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak Rapot</button>
            <button onclick="window.close()" class="btn" style="background: #6c757d; color: white;">✕ Tutup</button>
        </div>
    </div>

    <script>
        // Nama bulan dalam bahasa Indonesia
        const namaBulan = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        // Update real-time date
        function updateDateTime() {
            const now = new Date();
            const tanggal = now.getDate();
            const bulan = namaBulan[now.getMonth()];
            const tahun = now.getFullYear();
            
            const kota = 'Jakarta'; // Default kota
      
            
            // Update untuk signature (footer)
            const realTimeDateElement = document.getElementById('realTimeDate');
            if (realTimeDateElement) {
                realTimeDateElement.textContent = `${kota}, ${tanggal} ${bulan} ${tahun}`;
            }
        }

        // Update setiap detik
        setInterval(updateDateTime, 1000);
        
        // Initial update
        updateDateTime();

        // Auto print if requested
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('auto_print') === '1') {
            window.onload = function() {
                setTimeout(() => {
                    window.print();
                }, 500);
            };
        }
    </script>
</body>
</html>
