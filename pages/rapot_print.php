<?php
// Redirect to rapot_view with auto print parameter
$murid_id = $_GET['murid_id'] ?? null;
$semester = $_GET['semester'] ?? 'Semester 1';
$tahun = $_GET['tahun'] ?? date('Y') . '/' . (date('Y') + 1);

if (!$murid_id) {
    header('Location: rapot.php');
    exit();
}

$url = 'rapot_view.php?murid_id=' . urlencode($murid_id) . 
       '&semester=' . urlencode($semester) . 
       '&tahun=' . urlencode($tahun) . 
       '&auto_print=1';

header('Location: ' . $url);
exit();
?>
