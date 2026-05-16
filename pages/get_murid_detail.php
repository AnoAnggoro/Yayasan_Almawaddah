<?php
// Set error reporting untuk debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors to avoid breaking JSON

require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Set header before any output
header('Content-Type: application/json; charset=utf-8');

try {
    $database = new Database();
    $db = $database->getConnection();

    $murid_id = $_GET['murid_id'] ?? null;

    if (!$murid_id) {
        echo json_encode(['error' => 'ID murid tidak valid'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    // Get murid data including tahun_masuk
    $stmt = $db->prepare("SELECT * FROM murid WHERE id = :id");
    $stmt->bindParam(':id', $murid_id, PDO::PARAM_INT);
    $stmt->execute();
    $murid = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$murid) {
        echo json_encode(['error' => 'Murid tidak ditemukan'], JSON_UNESCAPED_UNICODE);
        exit();
    }

    echo json_encode($murid, JSON_UNESCAPED_UNICODE);
    
} catch (PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
    echo json_encode(['error' => 'Terjadi kesalahan: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
exit();
?>
