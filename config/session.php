<?php
// Cookie session dikunci dulu, harus sebelum session_start().
// Di belakang Cloudflare/proxy, koneksi ke PHP bisa tetap http walau pengunjung
// mengakses lewat https. Protokol aslinya ada di header X-Forwarded-Proto.
function is_https() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

$https = is_https();
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,   // tidak bisa dibaca JavaScript (curi session lewat XSS)
    'secure' => $https,   // hanya dikirim lewat HTTPS kalau situsnya sudah HTTPS
    'samesite' => 'Lax'   // cookie tidak ikut request dari situs lain
]);
session_start();

// ---------- CSRF ----------
function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Dipanggil di dalam setiap form POST untuk menyisipkan token tersembunyi.
function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Satu pintu untuk semua halaman: setiap POST wajib bawa token yang cocok.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        exit('Sesi kedaluwarsa atau form tidak sah. Muat ulang halaman lalu coba lagi.');
    }
}

// ---------- Login ----------
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: ../index.php");
        exit();
    }
}

// Dipanggil setelah username/password terbukti benar: ganti ID session
// supaya session yang sudah dipegang penyerang sebelum login jadi tidak berlaku.
function loginUser($user) {
    session_regenerate_id(true);
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['nama_lengkap'] = $user['nama_lengkap'] ?? $user['username'];
    $_SESSION['role'] = $user['role'];
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
        if (isset($user['nama_lengkap']) && !empty($user['nama_lengkap'])) {
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
        } elseif (!isset($_SESSION['nama_lengkap'])) {
            $_SESSION['nama_lengkap'] = $user['username'];
        }
    }

    return $user;
}
