<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Get filter parameters
$jenis_laporan = $_GET['jenis'] ?? '';
$tanggal_dari = $_GET['tanggal_dari'] ?? '';
$tanggal_sampai = $_GET['tanggal_sampai'] ?? '';
$tingkat = $_GET['tingkat'] ?? '';
$status = $_GET['status'] ?? '';
$tahun = $_GET['tahun'] ?? date('Y');
$angkatan = $_GET['angkatan'] ?? '';
$semester = $_GET['semester'] ?? '';

$laporan_data = [];
$laporan_title = '';

// Generate report based on type
if ($jenis_laporan) {
    switch ($jenis_laporan) {
        case 'murid':
            $laporan_title = '📊 Laporan Data Murid';
            $where = "WHERE 1=1";
            if ($tingkat) $where .= " AND tingkat = :tingkat";
            if ($angkatan) $where .= " AND angkatan = :angkatan";
            if ($tanggal_dari && $tanggal_sampai) {
                $where .= " AND tanggal_masuk BETWEEN :tanggal_dari AND :tanggal_sampai";
            }
            if ($status) {
                if ($status == 'Aktif') {
                    $where .= " AND (status_murid = 'Aktif' OR status_murid IS NULL)";
                } else {
                    $where .= " AND status_murid = :status";
                }
            }
            
            $query = "SELECT * FROM murid $where ORDER BY tingkat, nama";
            $stmt = $db->prepare($query);
            if ($tingkat) $stmt->bindParam(':tingkat', $tingkat);
            if ($angkatan) $stmt->bindParam(':angkatan', $angkatan);
            if ($tanggal_dari && $tanggal_sampai) {
                $stmt->bindParam(':tanggal_dari', $tanggal_dari);
                $stmt->bindParam(':tanggal_sampai', $tanggal_sampai);
            }
            if ($status && $status != 'Aktif') $stmt->bindParam(':status', $status);
            $stmt->execute();
            $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'absensi':
            $laporan_title = '📋 Laporan Absensi';
            $where = "WHERE 1=1";
            if ($tanggal_dari && $tanggal_sampai) {
                $where .= " AND a.tanggal BETWEEN :tanggal_dari AND :tanggal_sampai";
            }
            if ($tingkat) $where .= " AND a.tingkat = :tingkat";
            if ($semester) $where .= " AND a.semester = :semester";
            if ($angkatan) $where .= " AND m.angkatan = :angkatan";
            if ($status) {
                if ($status == 'Aktif') {
                    $where .= " AND (m.status_murid = 'Aktif' OR m.status_murid IS NULL)";
                } else {
                    $where .= " AND m.status_murid = :status";
                }
            }
            
            $query = "SELECT a.*, m.nisn, m.nama FROM absensi a 
                      JOIN murid m ON a.murid_id = m.id 
                      $where ORDER BY a.tanggal DESC, m.nama";
            $stmt = $db->prepare($query);
            if ($tanggal_dari && $tanggal_sampai) {
                $stmt->bindParam(':tanggal_dari', $tanggal_dari);
                $stmt->bindParam(':tanggal_sampai', $tanggal_sampai);
            }
            if ($tingkat) $stmt->bindParam(':tingkat', $tingkat);
            if ($semester) $stmt->bindParam(':semester', $semester);
            if ($angkatan) $stmt->bindParam(':angkatan', $angkatan);
            if ($status && $status != 'Aktif') $stmt->bindParam(':status', $status);
            $stmt->execute();
            $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'nilai':
            $laporan_title = '⭐ Laporan Nilai';
            $where = "WHERE 1=1";
            if ($tingkat) $where .= " AND n.tingkat = :tingkat";
            if ($angkatan) $where .= " AND m.angkatan = :angkatan";
            if ($semester) $where .= " AND n.semester = :semester";
            if ($tanggal_dari && $tanggal_sampai) {
                $where .= " AND n.created_at BETWEEN :tanggal_dari AND :tanggal_sampai";
            }
            if ($status) {
                if ($status == 'Aktif') {
                    $where .= " AND (m.status_murid = 'Aktif' OR m.status_murid IS NULL)";
                } else {
                    $where .= " AND m.status_murid = :status";
                }
            }
            
            $query = "SELECT n.*, m.nisn, m.nama, a.nama_aspek 
                      FROM nilai n 
                      JOIN murid m ON n.murid_id = m.id 
                      JOIN aspek_penilaian a ON n.aspek_id = a.id 
                      $where ORDER BY m.nama";
            $stmt = $db->prepare($query);
            if ($tingkat) $stmt->bindParam(':tingkat', $tingkat);
            if ($angkatan) $stmt->bindParam(':angkatan', $angkatan);
            if ($semester) $stmt->bindParam(':semester', $semester);
            if ($tanggal_dari && $tanggal_sampai) {
                $stmt->bindParam(':tanggal_dari', $tanggal_dari);
                $stmt->bindParam(':tanggal_sampai', $tanggal_sampai);
            }
            if ($status && $status != 'Aktif') $stmt->bindParam(':status', $status);
            $stmt->execute();
            $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
            
        case 'pembayaran':
            $laporan_title = '💰 Laporan Pembayaran';
            $where = "WHERE 1=1";
            if ($tanggal_dari && $tanggal_sampai) {
                $where .= " AND p.tanggal_bayar BETWEEN :tanggal_dari AND :tanggal_sampai";
            }
            if ($tingkat) $where .= " AND p.tingkat = :tingkat";
            if ($angkatan) $where .= " AND m.angkatan = :angkatan";
            if ($semester) $where .= " AND p.semester = :semester";
            if ($status) {
                if ($status == 'Aktif') {
                    $where .= " AND (m.status_murid = 'Aktif' OR m.status_murid IS NULL)";
                } else {
                    $where .= " AND m.status_murid = :status";
                }
            }
            
            $query = "SELECT p.*, m.nisn, m.nama FROM pembayaran p 
                      JOIN murid m ON p.murid_id = m.id 
                      $where ORDER BY p.tanggal_bayar DESC";
            $stmt = $db->prepare($query);
            if ($tanggal_dari && $tanggal_sampai) {
                $stmt->bindParam(':tanggal_dari', $tanggal_dari);
                $stmt->bindParam(':tanggal_sampai', $tanggal_sampai);
            }
            if ($tingkat) $stmt->bindParam(':tingkat', $tingkat);
            if ($angkatan) $stmt->bindParam(':angkatan', $angkatan);
            if ($semester) $stmt->bindParam(':semester', $semester);
            if ($status && $status != 'Aktif') $stmt->bindParam(':status', $status);
            $stmt->execute();
            $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Calculate total
            $total_pembayaran = 0;
            foreach ($laporan_data as $row) {
                $total_pembayaran += $row['jumlah'];
            }
            break;
            
        case 'guru':
            $laporan_title = '👨‍🏫 Laporan Data Guru';
            
            $where = "WHERE 1=1";
            if ($status) $where .= " AND status = :status";
            if ($angkatan) $where .= " AND angkatan = :angkatan";
            if ($semester) $where .= " AND semester = :semester";
            if ($tanggal_dari && $tanggal_sampai) {
                $where .= " AND tanggal_masuk BETWEEN :tanggal_dari AND :tanggal_sampai";
            }
            
            $query = "SELECT * FROM guru $where ORDER BY nama";
            $stmt = $db->prepare($query);
            if ($status) $stmt->bindParam(':status', $status);
            if ($angkatan) $stmt->bindParam(':angkatan', $angkatan);
            if ($semester) $stmt->bindParam(':semester', $semester);
            if ($tanggal_dari && $tanggal_sampai) {
                $stmt->bindParam(':tanggal_dari', $tanggal_dari);
                $stmt->bindParam(':tanggal_sampai', $tanggal_sampai);
            }
            $stmt->execute();
            $laporan_data = $stmt->fetchAll(PDO::FETCH_ASSOC);
            break;
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan - Yayasan Al Mawaddah</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        @media print {
            .no-print { display: none !important; }
            .main-content { padding: 0; }
            .header, .navigation { display: none !important; }
            .report-footer {
                page-break-inside: avoid;
                margin-top: 50px;
            }
        }
        .report-footer {
            margin-top: 50px;
            padding-top: 30px;
            background: white;
        }
        .signature-section {
            display: flex;
            justify-content: flex-end;
            margin-top: 40px;
            margin-bottom: 100px;
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
        .signature-line {
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
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header no-print">
            <i class="icon-report"></i>
            <h2>📊 Laporan</h2>
        </div>

        <!-- Filter Section -->
        <div class="content-card no-print">
            <h3 style="margin-bottom: 15px;">🔍 Pilih Jenis Laporan</h3>
            <form method="GET" id="filterForm">
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 15px;">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label>Jenis Laporan</label>
                        <select name="jenis" id="jenis_laporan" onchange="toggleFilters()" required>
                            <option value="">-- Pilih Jenis --</option>
                            <option value="murid" <?= $jenis_laporan == 'murid' ? 'selected' : '' ?>>📚 Data Murid</option>
                            <option value="guru" <?= $jenis_laporan == 'guru' ? 'selected' : '' ?>>👨‍🏫 Data Guru</option>
                            <option value="absensi" <?= $jenis_laporan == 'absensi' ? 'selected' : '' ?>>📋 Absensi</option>
                            <option value="nilai" <?= $jenis_laporan == 'nilai' ? 'selected' : '' ?>>⭐ Nilai</option>
                            <option value="pembayaran" <?= $jenis_laporan == 'pembayaran' ? 'selected' : '' ?>>💰 Pembayaran</option>
                        </select>
                    </div>

                    <div class="form-group filter-date" style="margin-bottom: 0; display: none;">
                        <label>Tanggal Dari</label>
                        <input type="date" name="tanggal_dari" value="<?= $tanggal_dari ?>">
                    </div>

                    <div class="form-group filter-date" style="margin-bottom: 0; display: none;">
                        <label>Tanggal Sampai</label>
                        <input type="date" name="tanggal_sampai" value="<?= $tanggal_sampai ?>">
                    </div>

                    <div class="form-group filter-tingkat" style="margin-bottom: 0; display: none;">
                        <label>Tingkat</label>
                        <select name="tingkat">
                            <option value="">Semua Tingkat</option>
                            <option value="Kelompok A" <?= $tingkat == 'Kelompok A' ? 'selected' : '' ?>>Kelompok A</option>
                            <option value="Kelompok B" <?= $tingkat == 'Kelompok B' ? 'selected' : '' ?>>Kelompok B</option>
                        </select>
                    </div>

                    <div class="form-group filter-status" style="margin-bottom: 0; display: none;">
                        <label>Status</label>
                        <select name="status">
                            <option value="">Semua Status</option>
                            <option value="Aktif" <?= $status == 'Aktif' ? 'selected' : '' ?>>Aktif</option>
                            <option value="Lulus" <?= $status == 'Lulus' ? 'selected' : '' ?>>Lulus</option>
                            <option value="Pindah" <?= $status == 'Pindah' ? 'selected' : '' ?>>Pindah</option>
                            <option value="Keluar" <?= $status == 'Keluar' ? 'selected' : '' ?>>Keluar</option>
                        </select>
                    </div>

                    <div class="form-group filter-angkatan" style="margin-bottom: 0; display: none;">
                        <label>Angkatan</label>
                        <select name="angkatan">
                            <option value="">Semua Angkatan</option>
                            <?php 
                            $currentYear = date('Y');
                            for ($y = $currentYear; $y >= $currentYear - 10; $y--): 
                            ?>
                            <option value="<?= $y ?>" <?= $angkatan == $y ? 'selected' : '' ?>><?= $y ?></option>
                            <?php endfor; ?>
                        </select>
                    </div>

                    <div class="form-group filter-semester" style="margin-bottom: 0; display: none;">
                        <label>Semester</label>
                        <select name="semester">
                            <option value="">Semua Semester</option>
                            <option value="1" <?= $semester == '1' ? 'selected' : '' ?>>Semester 1</option>
                            <option value="2" <?= $semester == '2' ? 'selected' : '' ?>>Semester 2</option>
                        </select>
                    </div>

                    <div class="form-group no-print" style="margin-bottom: 0;">
                        
                    </div>
                </div>

                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">📊 Tampilkan Laporan</button>
                    <?php if ($jenis_laporan): ?>
                    <button type="button" class="btn btn-success" onclick="window.print()">🖨️ Cetak</button>
                    <button type="button" class="btn" style="background: #10b981; color: white;" onclick="exportExcel()">📥 Export Excel</button>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <?php if ($jenis_laporan && count($laporan_data) > 0): ?>
        <!-- Report Header -->
        <div style="text-align: center; margin-bottom: 30px;">
            <h2 style="margin: 0; color: #10b981;"><?= $laporan_title ?></h2>
           

        <!-- Report Content -->
        <div class="content-card">
            <div style="overflow-x: auto;">
                <?php if ($jenis_laporan == 'murid'): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                               <div class="school-footer">
                    <p class="school-name">YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</p>
                    <p class="school-address">Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740</p>
                    <p class="school-address">Telp: (021) 7975356 | NSRA: 101231740223</p>
                </div>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama</th>
                            <th>Tingkat</th>
                            <th>JK</th>
                            <th>Tempat, Tgl Lahir</th>
                            <th>Nama Ibu</th>
                            <th>Alamat</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_data as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $row['nisn'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['tingkat'] ?></td>
                            <td><?= $row['jenis_kelamin'] == 'L' ? 'L' : 'P' ?></td>
                            <td><?= $row['tempat_lahir'] ?>, <?= date('d/m/Y', strtotime($row['tanggal_lahir'])) ?></td>
                            <td><?= $row['nama_ibu_kandung'] ?></td>
                            <td><?= $row['alamat'] ?></td>
                            <td><?= $row['status_murid'] ?? 'Aktif' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php elseif ($jenis_laporan == 'absensi'): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                               <div class="school-footer">
                    <p class="school-name">YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</p>
                    <p class="school-address">Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740</p>
                    <p class="school-address">Telp: (021) 7975356 | NSRA: 101231740223</p>
                </div>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>NISN</th>
                            <th>Nama</th>
                            <th>Tingkat</th>
                            <th>Status</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_data as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal'])) ?></td>
                            <td><?= $row['nisn'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['tingkat'] ?></td>
                            <td><?= $row['status'] ?></td>
                            <td><?= $row['keterangan'] ?: '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php elseif ($jenis_laporan == 'nilai'): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                               <div class="school-footer">
                    <p class="school-name">YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</p>
                    <p class="school-address">Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740</p>
                    <p class="school-address">Telp: (021) 7975356 | NSRA: 101231740223</p>
                </div>
                            <th>No</th>
                            <th>NISN</th>
                            <th>Nama</th>
                            <th>Tingkat</th>
                            <th>Aspek Penilaian</th>
                            <th>Penilaian</th>
                            <th>Semester</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_data as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $row['nisn'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['tingkat'] ?></td>
                            <td><?= $row['nama_aspek'] ?></td>
                            <td><?= $row['penilaian'] ?></td>
                            <td><?= $row['semester'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <?php elseif ($jenis_laporan == 'pembayaran'): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                               <div class="school-footer">
                    <p class="school-name">YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</p>
                    <p class="school-address">Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740</p>
                    <p class="school-address">Telp: (021) 7975356 | NSRA: 101231740223</p>
                </div>
                            <th>No</th>
                            <th>Tanggal</th>
                            <th>NISN</th>
                            <th>Nama</th>
                            <th>Jenis</th>
                            <th>Bulan</th>
                            <th>Jumlah</th>
                            <th>Metode</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_data as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= date('d/m/Y', strtotime($row['tanggal_bayar'])) ?></td>
                            <td><?= $row['nisn'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['jenis_pembayaran'] ?></td>
                            <td><?= $row['bulan'] ?: '-' ?></td>
                            <td>Rp <?= number_format($row['jumlah'], 0, ',', '.') ?></td>
                            <td><?= $row['metode_pembayaran'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <tr style="background: #e0f2fe; font-weight: bold;">
                            <td colspan="6" style="text-align: right; padding: 15px;">TOTAL:</td>
                            <td colspan="2" style="padding: 15px;">Rp <?= number_format($total_pembayaran, 0, ',', '.') ?></td>
                        </tr>
                    </tbody>
                </table>

                <?php elseif ($jenis_laporan == 'guru'): ?>
                <table class="data-table">
                    <thead>
                        <tr>
                            <div class="school-footer">
                    <p class="school-name">YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</p>
                    <p class="school-address">Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740</p>
                    <p class="school-address">Telp: (021) 7975356 | NSRA: 101231740223</p>
                </div>
                            <th>No</th>
                            <th>NIK</th>
                            <th>Nama</th>
                            <th>JK</th>
                            <th>Kategori</th>
                            <th>Jabatan</th>
                            <th>Guru Kelas</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($laporan_data as $index => $row): ?>
                        <tr>
                            <td><?= $index + 1 ?></td>
                            <td><?= $row['nik'] ?></td>
                            <td><?= $row['nama'] ?></td>
                            <td><?= $row['jenis_kelamin'] ?></td>
                            <td><?= $row['kategori'] ?></td>
                            <td><?= $row['jabatan'] ?></td>
                            <td><?= $row['guru_kelas'] ?: '-' ?></td>
                            <td><?= $row['status'] ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>

            <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 8px;" class="no-print">
                <strong>Total Data:</strong> <?= count($laporan_data) ?> record
            </div>

            <!-- Report Footer (Untuk Print/PDF) -->
            <div class="report-footer">
                <!-- Garis Pemisah -->
                <div style="border-top: 2px dashed #d1d5db; margin: 40px 0 30px 0;"></div>
                
                <div class="signature-section">
                    <div class="signature-box">
                        <p id="realTimeDate"></p>
                        <p>Kepala Sekolah</p>
                        <div class="signature-line">
                            <strong>( RUDSDIANA )</strong>
                        </div>
                    </div>
                </div>
                
                <p style="margin: 5px 0; color: #64748b;">Yayasan Pendidikan dan Sosial Al Mawaddah</p>
            <?php if ($tanggal_dari && $tanggal_sampai): ?>
            <p style="margin: 5px 0; color: #64748b;">
                Periode: <?= date('d/m/Y', strtotime($tanggal_dari)) ?> - <?= date('d/m/Y', strtotime($tanggal_sampai)) ?>
            </p>
            <?php endif; ?>
            <p style="margin: 5px 0; color: #64748b;">
                Dicetak: <span id="printDateTime"></span>
            </p>
        </div> 
            </div>
        </div>

        <?php elseif ($jenis_laporan): ?>
        <div class="empty-state">
            <div style="font-size: 48px; margin-bottom: 15px;">📭</div>
            <p>Tidak ada data untuk ditampilkan</p>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Nama bulan dalam bahasa Indonesia
        const namaBulan = [
            'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
            'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
        ];

        const hari = [
            'Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'
        ];

        // Update real-time date dan time
        function updateDateTime() {
            const now = new Date();
            const tanggal = now.getDate();
            const bulan = namaBulan[now.getMonth()];
            const tahun = now.getFullYear();
            const jam = String(now.getHours()).padStart(2, '0');
            const menit = String(now.getMinutes()).padStart(2, '0');
            
            const kota = document.getElementById('kotaSelect') ? 
                        document.getElementById('kotaSelect').value : 'Jakarta';
            const namaHari = hari[now.getDay()];
            
            // Update untuk signature (footer)
            const realTimeDateElement = document.getElementById('realTimeDate');
            if (realTimeDateElement) {
                realTimeDateElement.textContent = `${kota}, ${namaHari} ${tanggal} ${bulan} ${tahun}`;
            }
            
            // Update untuk header (dicetak)
            const printDateTimeElement = document.getElementById('printDateTime');
            if (printDateTimeElement) {
                printDateTimeElement.textContent = `${tanggal}/${String(now.getMonth() + 1).padStart(2, '0')}/${tahun} ${jam}:${menit} WIB`;
            }
        }

        // Update setiap detik
        setInterval(updateDateTime, 1000);
        
        // Update saat kota diubah
        const kotaSelect = document.getElementById('kotaSelect');
       
        
        // Initial update
        updateDateTime();

        function toggleFilters() {
            const jenis = document.getElementById('jenis_laporan').value;
            const dateFilters = document.querySelectorAll('.filter-date');
            const tingkatFilter = document.querySelectorAll('.filter-tingkat');
            const statusFilter = document.querySelectorAll('.filter-status');
            const angkatanFilter = document.querySelectorAll('.filter-angkatan');
            const semesterFilter = document.querySelectorAll('.filter-semester');
            
            // Hide all filters first
            dateFilters.forEach(f => f.style.display = 'none');
            tingkatFilter.forEach(f => f.style.display = 'none');
            statusFilter.forEach(f => f.style.display = 'none');
            angkatanFilter.forEach(f => f.style.display = 'none');
            semesterFilter.forEach(f => f.style.display = 'none');
            
            // Show filters based on report type
            if (jenis === 'murid') {
                dateFilters.forEach(f => f.style.display = 'block');
                tingkatFilter.forEach(f => f.style.display = 'block');
                statusFilter.forEach(f => f.style.display = 'block');
                angkatanFilter.forEach(f => f.style.display = 'block');
            }
            
            if (jenis === 'absensi') {
                dateFilters.forEach(f => f.style.display = 'block');
                tingkatFilter.forEach(f => f.style.display = 'block');
                statusFilter.forEach(f => f.style.display = 'block');
                angkatanFilter.forEach(f => f.style.display = 'block');
                semesterFilter.forEach(f => f.style.display = 'block');
            }
            
            if (jenis === 'nilai') {
                dateFilters.forEach(f => f.style.display = 'block');
                tingkatFilter.forEach(f => f.style.display = 'block');
                statusFilter.forEach(f => f.style.display = 'block');
                angkatanFilter.forEach(f => f.style.display = 'block');
                semesterFilter.forEach(f => f.style.display = 'block');
            }
            
            if (jenis === 'pembayaran') {
                dateFilters.forEach(f => f.style.display = 'block');
                tingkatFilter.forEach(f => f.style.display = 'block');
                statusFilter.forEach(f => f.style.display = 'block');
                angkatanFilter.forEach(f => f.style.display = 'block');
                semesterFilter.forEach(f => f.style.display = 'block');
            }
            
            if (jenis === 'guru') {
                dateFilters.forEach(f => f.style.display = 'block');
                statusFilter.forEach(f => f.style.display = 'block');
                angkatanFilter.forEach(f => f.style.display = 'block');
                semesterFilter.forEach(f => f.style.display = 'block');
            }
        }
        
        function exportExcel() {
            const table = document.querySelector('.data-table');
            if (!table) return;
            
            let html = table.outerHTML;
            const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'laporan_<?= $jenis_laporan ?>_<?= date('Y-m-d') ?>.xls';
            a.click();
        }
        
        // Initialize filters on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleFilters();
            updateDateTime();
        });
    </script>
</body>
</html>
