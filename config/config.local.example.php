<?php
// Salin file ini jadi config/config.local.php di server, lalu isi nilai aslinya.
// config.local.php sengaja tidak ikut git (lihat .gitignore).
return [
    'host' => 'localhost',
    'name' => 'nama_database',
    'user' => 'user_database',
    'pass' => 'password_database',

    // false di server publik: pesan error hanya masuk log, tidak tampil ke pengunjung
    'debug' => false,

    // false kalau tidak ingin orang luar bisa mendaftar sendiri lewat register.php
    'registrasi_publik' => false
];
