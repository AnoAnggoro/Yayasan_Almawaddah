-- Database untuk Yayasan Al Mawaddah

CREATE DATABASE IF NOT EXISTS yayasan_almawaddah;
USE yayasan_almawaddah;

-- Tabel Admin/User
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    nama VARCHAR(100) NOT NULL,
    role ENUM('admin', 'guru') DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Guru
CREATE TABLE IF NOT EXISTS guru (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nik VARCHAR(50) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    jenis_kelamin ENUM('Laki-laki', 'Perempuan') NOT NULL,
    kategori ENUM('PNS', 'Non PNS') NOT NULL,
    jabatan VARCHAR(100),
    guru_kelas VARCHAR(50),
    status ENUM('Aktif', 'Tidak Aktif') DEFAULT 'Aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Murid
CREATE TABLE IF NOT EXISTS murid (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nisn VARCHAR(20) UNIQUE NOT NULL,
    nik VARCHAR(20) UNIQUE NOT NULL,
    nama VARCHAR(100) NOT NULL,
    tingkat VARCHAR(50) NOT NULL,
    jenis_kelamin ENUM('L', 'P') NOT NULL,
    tempat_lahir VARCHAR(100),
    tanggal_lahir DATE,
    nama_ibu_kandung VARCHAR(100),
    alamat TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Absensi
CREATE TABLE IF NOT EXISTS absensi (
    id INT PRIMARY KEY AUTO_INCREMENT,
    murid_id INT NOT NULL,
    tanggal DATE NOT NULL,
    tingkat VARCHAR(50) NOT NULL,
    status ENUM('Hadir', 'Sakit', 'Izin', 'Alpa') NOT NULL,
    keterangan TEXT,
    FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Aspek Penilaian
CREATE TABLE IF NOT EXISTS aspek_penilaian (
    id INT PRIMARY KEY AUTO_INCREMENT,
    nama_aspek VARCHAR(100) NOT NULL,
    keterangan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Nilai
CREATE TABLE IF NOT EXISTS nilai (
    id INT PRIMARY KEY AUTO_INCREMENT,
    murid_id INT NOT NULL,
    tingkat VARCHAR(50) NOT NULL,
    aspek_id INT NOT NULL,
    semester ENUM('Semester 1', 'Semester 2') NOT NULL,
    nilai DECIMAL(5,2),
    penilaian TEXT,
    keterangan TEXT,
    FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE CASCADE,
    FOREIGN KEY (aspek_id) REFERENCES aspek_penilaian(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Jadwal KBM
CREATE TABLE IF NOT EXISTS jadwal_kbm (
    id INT PRIMARY KEY AUTO_INCREMENT,
    tingkat VARCHAR(50) NOT NULL,
    hari ENUM('Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu') NOT NULL,
    waktu VARCHAR(50) NOT NULL,
    tema VARCHAR(100),
    guru_id INT,
    FOREIGN KEY (guru_id) REFERENCES guru(id) ON DELETE SET NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Rapot
CREATE TABLE IF NOT EXISTS rapot (
    id INT PRIMARY KEY AUTO_INCREMENT,
    murid_id INT NOT NULL,
    tahun_ajaran VARCHAR(20) NOT NULL,
    semester ENUM('Semester 1', 'Semester 2') NOT NULL,
    tingkat VARCHAR(50) NOT NULL,
    status_rapot ENUM('Draft', 'Final') DEFAULT 'Draft',
    FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Pembayaran (UPDATE - lebih fleksibel)
DROP TABLE IF EXISTS pembayaran_spp;
CREATE TABLE IF NOT EXISTS pembayaran (
    id INT PRIMARY KEY AUTO_INCREMENT,
    murid_id INT NOT NULL,
    jenis_pembayaran VARCHAR(50) NOT NULL,
    bulan VARCHAR(20),
    tahun YEAR NOT NULL,
    tingkat VARCHAR(50) NOT NULL,
    jumlah DECIMAL(10,2) NOT NULL,
    tanggal_bayar DATE,
    metode_pembayaran VARCHAR(50),
    status ENUM('Lunas', 'Belum Bayar') DEFAULT 'Belum Bayar',
    keterangan TEXT,
    FOREIGN KEY (murid_id) REFERENCES murid(id) ON DELETE CASCADE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert default admin (UPDATE)
DELETE FROM users WHERE username = 'admin';
INSERT INTO users (username, password, nama, role) VALUES 
('admin', 'admin123', 'Administrator', 'admin');
-- Untuk production, gunakan password hash:
-- ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Administrator', 'admin');

-- Insert aspek penilaian (UPDATE)
DELETE FROM aspek_penilaian;
INSERT INTO aspek_penilaian (nama_aspek, keterangan) VALUES 
('Anak mengenal dan percaya kepada Allah SWT melalui Asmaul Husna dan CiptaanNya', 'Nilai Agama dan Moral'),
('Anak mengenal Al-Quran dan Al-Hadist sebagai pedoman hidupnya', 'Nilai Agama dan Moral'),
('Anak mempraktekkan ibadah sehari-hari dengan tuntunan orang dewasa', 'Nilai Agama dan Moral'),
('Anak membiasakan berakhlakul karimah di lingkungan rumah, madrasah, dan lingkungan sekitarnya dengan menghargai perbedaan', 'Nilai Agama dan Moral'),
('Anak meneladani kisah Nabi Muhammad SAW dan para sahabat serta cerita-cerita islami', 'Nilai Agama dan Moral'),
('Anak mengenal kosa kata bahasa arab secara sederhana', 'Bahasa'),
('Anak berpartisipasi aktif dalam menjaga kebersihan, kesehatan dan keselamatan diri sebagai bentuk rasa sayang terhadap dirinya dan rasa syukur kepada Allah SWT', 'Fisik Motorik'),
('Anak menghargai alam dengan cara merawat dan menunjukkan rasa sayang terhadap makhluk hidup yang merupakan ciptaan Allah SWT', 'Sosial Emosional');
