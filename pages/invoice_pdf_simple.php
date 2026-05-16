<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

date_default_timezone_set('Asia/Jakarta');

$database = new Database();
$db = $database->getConnection();

$id = $_GET['id'] ?? null;
if (!$id) {
    die('ID pembayaran tidak valid');
}

// Redirect ke halaman print dengan auto download
header('Location: invoice_print.php?id=' . $id . '&auto=1&download=1');
exit();
?>
