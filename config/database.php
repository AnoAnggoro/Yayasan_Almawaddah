<?php
// Pengaturan aplikasi. Nilai produksi ditaruh di config/config.local.php
// (tidak ikut ke git), kalau file itu belum ada dipakai default XAMPP.
function app_config($kunci = null, $default = null) {
    static $config = null;
    if ($config === null) {
        $local = __DIR__ . '/config.local.php';
        $config = file_exists($local) ? require $local : [
            'host' => 'localhost',
            'name' => 'almawaddah_db',
            'user' => 'root',
            'pass' => '',
            'debug' => true,
            'registrasi_publik' => true
        ];
    }
    return $kunci === null ? $config : ($config[$kunci] ?? $default);
}

// Di server publik, pesan error tidak boleh tampil ke pengunjung:
// isinya bocorkan path file, nama tabel, dan potongan query.
error_reporting(E_ALL);
ini_set('display_errors', app_config('debug', true) ? '1' : '0');
ini_set('log_errors', '1');
// Escape output ke HTML (cegah XSS). Dipakai saat echo data ke halaman.
function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

class Database {
    private $config;
    public $conn;

    public function __construct() {
        $this->config = app_config();
    }

    public function getConnection() {
        try {
            $this->conn = new PDO(
                "mysql:host={$this->config['host']};dbname={$this->config['name']};charset=utf8mb4",
                $this->config['user'],
                $this->config['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
        } catch (PDOException $ex) {
            // Jangan tampilkan pesan asli ke pengunjung: bocor nama database/user.
            error_log('Koneksi database gagal: ' . $ex->getMessage());
            http_response_code(503);
            exit('Koneksi database sedang bermasalah. Hubungi administrator.');
        }
        return $this->conn;
    }
}
