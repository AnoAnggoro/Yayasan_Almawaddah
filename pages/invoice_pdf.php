<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Download library FPDF dari: http://www.fpdf.org/
// Atau buat versi sederhana dengan output buffer

date_default_timezone_set('Asia/Jakarta');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID pembayaran tidak valid');
}

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

// Format data
$bulanIndo = [
    1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
];

$tanggalBayar = date('d', strtotime($data['tanggal_bayar'])) . ' ' . 
                $bulanIndo[date('n', strtotime($data['tanggal_bayar']))] . ' ' . 
                date('Y', strtotime($data['tanggal_bayar']));

$waktuCetak = date('d') . ' ' . $bulanIndo[date('n')] . ' ' . date('Y, H:i') . ' WIB';
$noTransaksi = 'PAY' . str_pad($data['id'], 6, '0', STR_PAD_LEFT);
$filename = 'Invoice_' . $noTransaksi . '_' . date('Ymd') . '.pdf';

// Function terbilang
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

// Generate HTML for PDF
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= $filename ?></title>
    <style>
        @page { margin: 20px; }
        body { font-family: Arial, sans-serif; margin: 0; padding: 20px; }
        .container { max-width: 800px; margin: 0 auto; border: 2px solid #333; padding: 30px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #10b981; padding-bottom: 20px; }
        .header h1 { color: #10b981; margin: 0 0 5px 0; font-size: 24px; }
        .header p { color: #666; margin: 5px 0; font-size: 12px; }
        .info-section { margin-bottom: 20px; }
        .info-section h3 { background: #f0f9ff; padding: 10px; border-left: 4px solid #10b981; margin: 10px 0; font-size: 14px; }
        .info-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e5e7eb; font-size: 12px; }
        .info-row strong { color: #374151; }
        .total-section { background: #10b981; color: white; padding: 20px; text-align: center; border-radius: 8px; margin: 20px 0; }
        .total-section h2 { font-size: 32px; margin: 10px 0; }
        .total-section p { margin: 5px 0; font-size: 12px; }
        .signature-section { margin-top: 40px; display: flex; justify-content: space-between; }
        .signature-box { text-align: center; width: 45%; font-size: 12px; }
        .signature-line { height: 60px; }
        .signature-name { border-top: 1px solid #333; padding-top: 5px; margin-top: 10px; display: inline-block; min-width: 150px; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 2px dashed #ccc; color: #666; font-size: 11px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>BUKTI PEMBAYARAN</h1>
            <p><strong>Yayasan Al Mawaddah</strong></p>
            <p>Jl. Kalibata Timur Rt 008 Rw 08 No.25 Kel. Kalibata Kec. Pancoran Jakarta 12740</p>
            <p>Telp: (021) 7975356 | NSRA: 101231740223</p>
        </div>

        <div class="info-section">
            <div class="info-row">
                <strong>No. Transaksi:</strong>
                <span>#<?= $noTransaksi ?></span>
            </div>
            <div class="info-row">
                <strong>Tanggal Pembayaran:</strong>
                <span><?= $tanggalBayar ?></span>
            </div>
        </div>

        <div class="info-section">
            <h3>Data Siswa</h3>
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
        </div>

        <div class="info-section">
            <h3>Rincian Pembayaran</h3>
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
        </div>

        <div class="total-section">
            <p style="opacity: 0.9;">Total Pembayaran</p>
            <h2>Rp <?= number_format($data['jumlah'], 0, ',', '.') ?></h2>
            <p style="margin-top: 10px;">Terbilang: <strong><?= ucwords(terbilang($data['jumlah'])) ?> Rupiah</strong></p>
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
            <p>Bukti pembayaran ini sah dan dihasilkan oleh sistem</p>
            <p>Dicetak pada: <?= $waktuCetak ?></p>
            <p style="margin-top: 10px; font-style: italic;">Simpan bukti pembayaran ini sebagai arsip</p>
        </div>
    </div>
</body>
</html>
<?php
$html = ob_get_clean();

// Gunakan library wkhtmltopdf atau DomPDF
// Untuk solusi sederhana, gunakan browser print to PDF
// Atau install library melalui composer

// Alternatif 1: Menggunakan wkhtmltopdf (jika sudah terinstall di server)
if (file_exists('C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe')) {
    $temp_html = tempnam(sys_get_temp_dir(), 'invoice') . '.html';
    $temp_pdf = tempnam(sys_get_temp_dir(), 'invoice') . '.pdf';
    
    file_put_contents($temp_html, $html);
    
    $wkhtmltopdf = '"C:/Program Files/wkhtmltopdf/bin/wkhtmltopdf.exe"';
    exec("$wkhtmltopdf $temp_html $temp_pdf");
    
    if (file_exists($temp_pdf)) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($temp_pdf));
        readfile($temp_pdf);
        
        unlink($temp_html);
        unlink($temp_pdf);
        exit();
    }
}

// Alternatif 2: Menggunakan mPDF (lightweight, pure PHP)
// Download mPDF dari: https://github.com/mpdf/mpdf
// Letakkan di folder vendor/mpdf

if (file_exists('../vendor/mpdf/mpdf.php')) {
    require_once '../vendor/mpdf/mpdf.php';
    $mpdf = new mPDF('utf-8', 'A4');
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, 'D'); // D = Download
    exit();
}

// Alternatif 3: Fallback - Trigger browser print dialog
header('Content-Type: text/html; charset=utf-8');
echo $html;
echo '<script>
setTimeout(function() { 
    window.print(); 
    setTimeout(function() { 
        window.close(); 
    }, 100);
}, 500);
</script>';
?>
