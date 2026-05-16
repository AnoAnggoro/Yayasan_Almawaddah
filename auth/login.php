<?php
session_start();
require_once '../config/database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    try {
        $database = new Database();
        $db = $database->getConnection();

        $query = "SELECT * FROM users WHERE username = :username LIMIT 1";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            // Check password (supports both hashed and plain text for demo)
            $passwordValid = password_verify($password, $user['password']) || $password === $user['password'];
            
            if ($passwordValid) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['nama'] = $user['nama'];
                $_SESSION['role'] = $user['role'];
                
                header("Location: ../pages/beranda.php");
                exit();
            }
        }
        
        header("Location: ../index.php?error=1");
        exit();
    } catch (Exception $e) {
        error_log("Login error: " . $e->getMessage());
        header("Location: ../index.php?error=2");
        exit();
    }
}
?>
