-- Skema database Yayasan Al Mawaddah
-- Dibuat dari struktur database yang dipakai aplikasi (file lama sudah tidak sinkron:
-- kolom reset_token, nama_lengkap, email, tahun_masuk dll belum ada di sana).
--
-- Cara pakai di hosting:
--   1. Buat database kosong lewat cPanel, catat nama/user/password-nya
--   2. Import file ini lewat phpMyAdmin
--   3. Salin config/config.local.example.php jadi config/config.local.php, isi kredensialnya
--   4. Buat akun admin pertama dengan perintah di bagian paling bawah file ini
--
-- File ini SENGAJA tidak berisi data murid/guru/pembayaran (data pribadi)
-- dan tidak berisi akun beserta passwordnya.

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `absensi` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `murid_id` int(11) NOT NULL,
  `tanggal` date NOT NULL,
  `tingkat` varchar(50) NOT NULL,
  `status` enum('Hadir','Sakit','Izin','Alpa') NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `murid_id` (`murid_id`)
) ENGINE=InnoDB AUTO_INCREMENT=12 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `aspek_penilaian` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nama_aspek` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `guru` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nik` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `jenis_kelamin` enum('Laki-laki','Perempuan') NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `telepon` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `pendidikan_terakhir` varchar(50) DEFAULT NULL,
  `jurusan` varchar(100) DEFAULT NULL,
  `kategori` enum('PNS','Non PNS') NOT NULL,
  `jabatan` varchar(100) DEFAULT NULL,
  `guru_kelas` varchar(50) DEFAULT NULL,
  `tanggal_masuk` date DEFAULT NULL,
  `status` enum('Aktif','Tidak Aktif') DEFAULT 'Aktif',
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nik` (`nik`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `jadwal_kbm` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tingkat` varchar(50) NOT NULL,
  `hari` enum('Senin','Selasa','Rabu','Kamis','Jumat','Sabtu') NOT NULL,
  `waktu` varchar(50) NOT NULL,
  `tema` varchar(100) DEFAULT NULL,
  `guru_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `guru_id` (`guru_id`),
  CONSTRAINT `jadwal_kbm_ibfk_1` FOREIGN KEY (`guru_id`) REFERENCES `guru` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=39 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `murid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nisn` varchar(20) NOT NULL,
  `nik` varchar(20) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `tingkat` varchar(50) NOT NULL,
  `jenis_kelamin` enum('L','P') NOT NULL,
  `tempat_lahir` varchar(100) DEFAULT NULL,
  `tanggal_lahir` date DEFAULT NULL,
  `nama_ibu_kandung` varchar(100) DEFAULT NULL,
  `alamat` text DEFAULT NULL,
  `status_murid` varchar(20) DEFAULT 'Aktif',
  `tahun_masuk` varchar(10) DEFAULT NULL,
  `foto` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nisn` (`nisn`),
  UNIQUE KEY `nik` (`nik`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `nilai` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `murid_id` int(11) NOT NULL,
  `tingkat` varchar(50) NOT NULL,
  `aspek_id` int(11) NOT NULL,
  `semester` enum('Semester 1','Semester 2') NOT NULL,
  `nilai` decimal(5,2) DEFAULT NULL,
  `penilaian` text DEFAULT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `murid_id` (`murid_id`),
  KEY `aspek_id` (`aspek_id`)
) ENGINE=InnoDB AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `pembayaran` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `murid_id` int(11) NOT NULL,
  `jenis_pembayaran` varchar(50) NOT NULL,
  `bulan` varchar(20) DEFAULT NULL,
  `tahun` year(4) NOT NULL,
  `tingkat` varchar(50) NOT NULL,
  `jumlah` decimal(10,2) NOT NULL,
  `tanggal_bayar` date DEFAULT NULL,
  `metode_pembayaran` varchar(50) DEFAULT NULL,
  `status` enum('Lunas','Belum Bayar') DEFAULT 'Belum Bayar',
  `keterangan` text DEFAULT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `murid_id` (`murid_id`),
  CONSTRAINT `pembayaran_ibfk_1` FOREIGN KEY (`murid_id`) REFERENCES `murid` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `pengumuman` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(200) NOT NULL,
  `isi` text NOT NULL,
  `kategori` enum('Umum','Penting','Mendesak') DEFAULT 'Umum',
  `target` enum('Semua','Guru','Orang Tua') DEFAULT 'Semua',
  `tanggal_berlaku` date DEFAULT NULL,
  `tanggal_berakhir` date DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `status` enum('Aktif','Nonaktif') DEFAULT 'Aktif',
  PRIMARY KEY (`id`),
  KEY `created_by` (`created_by`),
  CONSTRAINT `pengumuman_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `rapot` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `murid_id` int(11) NOT NULL,
  `tahun_ajaran` varchar(20) NOT NULL,
  `semester` enum('Semester 1','Semester 2') NOT NULL,
  `tingkat` varchar(50) NOT NULL,
  `status_rapot` enum('Draft','Final') DEFAULT 'Draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `murid_id` (`murid_id`),
  CONSTRAINT `rapot_ibfk_1` FOREIGN KEY (`murid_id`) REFERENCES `murid` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `nama_lengkap` varchar(255) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `role` enum('admin','operator') NOT NULL DEFAULT 'operator',
  `status` varchar(20) DEFAULT 'Tidak Aktif',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reset_token` varchar(255) DEFAULT NULL,
  `reset_expires` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Data acuan aspek penilaian
LOCK TABLES `aspek_penilaian` WRITE;
INSERT INTO `aspek_penilaian` (`id`, `nama_aspek`, `keterangan`, `created_at`) VALUES (1,'Anak mengenal dan percaya kepada Allah SWT melalui Asmaul Husna dan CiptaanNya','Nilai Agama dan Moral','2025-12-23 07:06:43'),(2,'Anak mengenal Al-Quran dan Al-Hadist sebagai pedoman hidupnya','Nilai Agama dan Moral','2025-12-23 07:06:43'),(3,'Anak mempraktekkan ibadah sehari-hari dengan tuntunan orang dewasa','Nilai Agama dan Moral','2025-12-23 07:06:43'),(4,'Anak membiasakan berakhlakul karimah di lingkungan rumah, madrasah, dan lingkungan sekitarnya dengan','Nilai Agama dan Moral','2025-12-23 07:06:43'),(5,'Anak meneladani kisah Nabi Muhammad SAW dan para sahabat serta cerita-cerita islami','Nilai Agama dan Moral','2025-12-23 07:06:43'),(6,'Anak mengenal kosa kata bahasa arab secara sederhana','Bahasa','2025-12-23 07:06:43'),(7,'Anak berpartisipasi aktif dalam menjaga kebersihan, kesehatan dan keselamatan diri sebagai bentuk ra','Fisik Motorik','2025-12-23 07:06:43'),(8,'Anak menghargai alam dengan cara merawat dan menunjukkan rasa sayang terhadap makhluk hidup yang mer','Sosial Emosional','2025-12-23 07:06:43');
UNLOCK TABLES;

-- ---------------------------------------------------------------------------
-- Akun admin pertama
-- ---------------------------------------------------------------------------
-- Password WAJIB berupa hash, jangan teks biasa. Bikin hash-nya dulu:
--
--   php -r "echo password_hash('PasswordPilihanAnda', PASSWORD_DEFAULT);"
--
-- lalu tempel hasilnya menggantikan HASH_DISINI:
--
-- INSERT INTO users (username, nama_lengkap, email, password, role, status)
-- VALUES ('admin', 'Administrator', 'admin@domain-anda.com', 'HASH_DISINI', 'admin', 'Aktif');

SET FOREIGN_KEY_CHECKS = 1;
