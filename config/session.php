<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit();
    }
}

function getUserData() {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    require_once __DIR__ . '/database.php';
    $database = new Database();
    $db = $database->getConnection();
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Update session dengan data terbaru jika ada
    if ($user) {
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        // Update nama_lengkap jika ada di database
        if (isset($user['nama_lengkap']) && !empty($user['nama_lengkap'])) {
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        } elseif (!isset($_SESSION['nama_lengkap'])) {
            // Jika tidak ada nama_lengkap di DB maupun session, gunakan username
            $_SESSION['nama_lengkap'] = $user['username'];
        }
    }
    
    return $user;
}
?>
