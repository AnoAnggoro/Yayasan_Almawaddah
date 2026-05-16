<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);

require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $db = $database->getConnection();

    $murid_id = $_GET['murid_id'] ?? null;
    $tahun = $_GET['tahun'] ?? date('Y');

    if (!$murid_id) {
        echo json_encode([], JSON_UNESCAPED_UNICODE);
        exit();
    }

    $bulan_list = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];

    $riwayat = [];
    foreach ($bulan_list as $bulan) {
        $bulan_tahun = $bulan . ' ' . $tahun;
        $stmt = $db->prepare("SELECT * FROM pembayaran WHERE murid_id = :murid_id AND jenis_pembayaran = 'SPP' AND bulan = :bulan");
        $stmt->bindParam(':murid_id', $murid_id, PDO::PARAM_INT);
        $stmt->bindParam(':bulan', $bulan_tahun, PDO::PARAM_STR);
        $stmt->execute();
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $riwayat[] = [
            'bulan' => $bulan_tahun,
            'status' => $data ? 'Lunas' : 'Belum Bayar',
            'tanggal' => $data ? $data['tanggal_bayar'] : null,
            'jumlah' => $data ? $data['jumlah'] : 0
        ];
    }

    echo json_encode($riwayat, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit();
?>
