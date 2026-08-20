<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Set timezone to Indonesian time
date_default_timezone_set('Asia/Jakarta');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID pembayaran tidak valid');
}

$downloadMode = isset($_GET['download']) && $_GET['download'] == '1';

// Get payment data
$query = "SELECT p.*, m.nisn, m.nama, m.tingkat, m.alamat 
          FROM pembayaran p 
          JOIN murid m ON p.murid_id = m.id 
          WHERE p.id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $id);
$stmt->execute();
$data = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$data) {
    die('Data pembayaran tidak ditemukan');
}

// Format tanggal dalam bahasa Indonesia
$bulanIndo = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

$tanggalBayar = date('d', strtotime($data['tanggal_bayar'])) . ' ' . 
                $bulanIndo[date('n', strtotime($data['tanggal_bayar']))] . ' ' . 
                date('Y', strtotime($data['tanggal_bayar']));

$waktuCetak = date('d') . ' ' . $bulanIndo[date('n')] . ' ' . date('Y, H:i') . ' WIB';

$noTransaksi = '#PAY' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);

function terbilang($angka) {
    $angka = abs($angka);
    $baca = array('', 'Satu', 'Dua', 'Tiga', 'Empat', 'Lima', 'Enam', 'Tujuh', 'Delapan', 'Sembilan', 'Sepuluh', 'Sebelas');
    $terbilang = '';

    if ($angka < 12) {
        $terbilang = ' ' . $baca[$angka];
    } else if ($angka < 20) {
        $terbilang = terbilang($angka - 10) . ' Belas';
    } else if ($angka < 100) {
        $terbilang = terbilang($angka / 10) . ' Puluh' . terbilang($angka % 10);
    } else if ($angka < 200) {
        $terbilang = ' Seratus' . terbilang($angka - 100);
    } else if ($angka < 1000) {
        $terbilang = terbilang($angka / 100) . ' Ratus' . terbilang($angka % 100);
    } else if ($angka < 2000) {
        $terbilang = ' Seribu' . terbilang($angka - 1000);
    } else if ($angka < 1000000) {
        $terbilang = terbilang($angka / 1000) . ' Ribu' . terbilang($angka % 1000);
    } else if ($angka < 1000000000) {
        $terbilang = terbilang($angka / 1000000) . ' Juta' . terbilang($angka % 1000000);
    }

    return $terbilang;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice Pembayaran - <?= e($noTransaksi) ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            padding: 20px;
            background: #f3f4f6;
        }
        .invoice-container { 
            max-width: 800px; 
            margin: 0 auto; 
            background: white;
            border: 2px solid #333; 
            padding: 30px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header { 
            text-align: center; 
            margin-bottom: 30px; 
            border-bottom: 3px solid #10b981; 
            padding-bottom: 20px; 
        }
        .header h1 { 
            color: #10b981; 
            margin-bottom: 10px;
            font-size: 28px;
        }
        .header p { 
            color: #666; 
            margin: 5px 0;
        }
        .header .subtitle {
            font-size: 12px;
            color: #999;
        }
        .info-section { 
            margin-bottom: 20px; 
        }
        .info-section h3 { 
            background: #f0f9ff; 
            padding: 10px; 
            border-left: 4px solid #10b981; 
            margin-bottom: 10px;
            font-size: 14px;
            color: #1f2937;
        }
        .info-row { 
            display: flex; 
            justify-content: space-between; 
            padding: 8px 0; 
            border-bottom: 1px solid #e5e7eb; 
        }
        .info-row strong { 
            color: #374151; 
            font-size: 13px;
        }
        .info-row span {
            color: #1f2937;
            font-size: 13px;
            text-align: right;
        }
        .total-section { 
            background: linear-gradient(135deg, #10b981 0%, #059669 100%); 
            color: white; 
            padding: 20px; 
            text-align: center; 
            border-radius: 8px; 
            margin: 20px 0; 
        }
        .total-section p {
            margin: 5px 0;
            opacity: 0.9;
        }
        .total-section h2 { 
            font-size: 36px; 
            margin: 10px 0;
            font-weight: bold;
        }
        .terbilang {
            font-size: 13px;
            margin-top: 10px;
            background: rgba(255,255,255,0.2);
            padding: 8px;
            border-radius: 4px;
        }
        .footer { 
            text-align: center; 
            margin-top: 30px; 
            padding-top: 20px; 
            border-top: 2px dashed #ccc; 
            color: #666; 
            font-size: 12px; 
        }
        .footer p {
            margin: 5px 0;
        }
        .signature-section { 
            margin-top: 50px; 
            display: flex; 
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .signature-box { 
            text-align: center; 
            width: 45%; 
        }
        .signature-box p {
            font-weight: 600;
            color: #374151;
            margin-bottom: 10px;
        }
        .signature-line { 
            height: 70px; 
        }
        .signature-name { 
            border-top: 1px solid #333; 
            padding-top: 5px; 
            margin-top: 10px;
            display: inline-block;
            min-width: 150px;
        }
        .button-container {
            text-align: center;
            margin-top: 30px;
            padding: 20px;
            background: #f9fafb;
            border-radius: 8px;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 16px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        .btn-primary {
            background: #10b981;
            color: white;
        }
        .btn-primary:hover {
            background: #059669;
        }
        .btn-secondary {
            background: #64748b;
            color: white;
        }
        .btn-secondary:hover {
            background: #475569;
        }
        
        /* Print Styles */
        @media print {
            body { 
                padding: 0;
                background: white;
            }
            .invoice-container {
                border: none;
                box-shadow: none;
                padding: 20px;
                max-width: 100%;
            }
            .button-container {
                display: none !important;
            }
            .no-print {
                display: none !important;
            }
            @page { 
                margin: 1cm;
                size: A4;
            }
            /* Prevent page breaks inside important sections */
            .info-section,
            .total-section,
            .signature-section {
                page-break-inside: avoid;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <div class="header">
            <h1>BUKTI PEMBAYARAN</h1>
            <p><strong>YAYASAN PENDIDIKAN DAN SOSIAL AL MAWADDAH</strong></p>
            <p class="subtitle">Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740 | Telp: (021) 7975356 | NSRA: 101231740223</p>
        </div>

        <div class="info-section">
            <div class="info-row">
                <strong>No. Transaksi:</strong>
                <span><?= e($noTransaksi) ?></span>
            </div>
            <div class="info-row">
                <strong>Tanggal Pembayaran:</strong>
                <span><?= e($tanggalBayar) ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>📋 Data Siswa</h3>
            <div class="info-row">
                <strong>NISN:</strong>
                <span><?= htmlspecialchars($data['nisn']) ?></span>
            </div>
            <div class="info-row">
                <strong>Nama:</strong>
                <span><?= htmlspecialchars($data['nama']) ?></span>
            </div>
            <div class="info-row">
                <strong>Tingkat:</strong>
                <span><?= htmlspecialchars($data['tingkat']) ?></span>
            </div>
            <?php if ($data['alamat']): ?>
            <div class="info-row">
                <strong>Alamat:</strong>
                <span><?= htmlspecialchars($data['alamat']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="info-section">
            <h3>💳 Rincian Pembayaran</h3>
            <div class="info-row">
                <strong>Jenis Pembayaran:</strong>
                <span><?= htmlspecialchars($data['jenis_pembayaran']) ?></span>
            </div>
            <?php if ($data['bulan']): ?>
            <div class="info-row">
                <strong>Bulan:</strong>
                <span><?= htmlspecialchars($data['bulan']) ?></span>
            </div>
            <?php endif; ?>
            <div class="info-row">
                <strong>Metode Pembayaran:</strong>
                <span><?= htmlspecialchars($data['metode_pembayaran'] ?? '-') ?></span>
            </div>
            <?php if ($data['keterangan']): ?>
            <div class="info-row">
                <strong>Keterangan:</strong>
                <span><?= htmlspecialchars($data['keterangan']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <div class="total-section">
            <p>Total Pembayaran</p>
            <h2>Rp <?= number_format($data['jumlah'], 0, ',', '.') ?></h2>
            <div class="terbilang">
                <em>Terbilang: <?= ucwords(terbilang($data['jumlah'])) ?> Rupiah</em>
            </div>
        </div>

        <div class="signature-section">
            <div class="signature-box">
                <p>Orang Tua/Wali</p>
                <div class="signature-line"></div>
                <div class="signature-name">(...........................)</div>
            </div>
            <div class="signature-box">
                <p>Petugas</p>
                <div class="signature-line"></div>
                <div class="signature-name">(...........................)</div>
            </div>
        </div>

        <div class="footer">
            <p><strong>Bukti pembayaran ini sah dan dihasilkan oleh sistem</strong></p>
            <p>Dicetak pada: <?= e($waktuCetak) ?></p>
            <p style="margin-top: 10px; font-style: italic;">⚠️ Simpan bukti pembayaran ini sebagai arsip</p>
        </div>
    </div>

    <div class="button-container no-print">
        <button onclick="window.print()" class="btn btn-primary">
            🖨️ <?= $downloadMode ? 'Download PDF (Print to PDF)' : 'Cetak Invoice' ?>
        </button>
        <button onclick="window.close()" class="btn btn-secondary">
            ✕ Tutup
        </button>
    </div>

    <?php if ($downloadMode): ?>
    <div class="no-print" style="position: fixed; top: 20px; left: 50%; transform: translateX(-50%); background: #fef3c7; border: 2px solid #f59e0b; padding: 15px 25px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); z-index: 9999; max-width: 500px;">
        <p style="margin: 0; color: #92400e; font-size: 14px; text-align: center;">
            <strong>📥 Cara Download PDF:</strong><br>
            <span style="font-size: 13px;">
                1. Klik tombol "Download PDF" dibawah<br>
                2. Pada dialog print, pilih printer: <strong>"Save as PDF"</strong><br>
                3. Klik <strong>Save</strong> dan pilih lokasi penyimpanan
            </span>
        </p>
    </div>
    <?php endif; ?>

    <script>
        <?php if ($downloadMode): ?>
        // Auto trigger print dialog untuk save as PDF dengan delay
        window.onload = function() {
            // Delay untuk memastikan halaman sudah ter-render sempurna
            setTimeout(function() {
                window.print();
            }, 800);
        }
        
        // Handle after print
        window.onafterprint = function() {
            setTimeout(function() {
                var confirmClose = confirm('Apakah file PDF sudah tersimpan?\n\nKlik OK untuk menutup window ini.');
                if (confirmClose) {
                    window.close();
                }
            }, 500);
        }
        <?php endif; ?>
    </script>
</body>
</html>
