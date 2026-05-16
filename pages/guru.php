<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

$database = new Database();
$db = $database->getConnection();

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM guru WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    header("Location: guru.php?success=delete");
    exit();
}

// Handle Add/Edit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $nik = $_POST['nik'];
    $nama = $_POST['nama'];
    $jenis_kelamin = $_POST['jenis_kelamin'];
    $tempat_lahir = $_POST['tempat_lahir'];
    $tanggal_lahir = $_POST['tanggal_lahir'];
    $alamat = $_POST['alamat'];
    $telepon = $_POST['telepon'];
    $email = $_POST['email'];
    $pendidikan_terakhir = $_POST['pendidikan_terakhir'];
    $jurusan = $_POST['jurusan'];
    $kategori = $_POST['kategori'];
    $jabatan = $_POST['jabatan'];
    $guru_kelas = $_POST['guru_kelas'];
    $tanggal_masuk = $_POST['tanggal_masuk'];
    $status = $_POST['status'];
    
    // Handle foto upload
    $foto = '';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] == 0) {
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        $filename = $_FILES['foto']['name'];
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        
        if (in_array($ext, $allowed)) {
            $new_filename = 'guru_' . time() . '_' . uniqid() . '.' . $ext;
            $upload_path = '../uploads/guru/' . $new_filename;
            
            // Create directory if not exists
            if (!is_dir('../uploads/guru')) {
                mkdir('../uploads/guru', 0777, true);
            }
            
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $upload_path)) {
                $foto = $new_filename;
                
                // Delete old photo if updating
                if ($id) {
                    $stmt_old = $db->prepare("SELECT foto FROM guru WHERE id = :id");
                    $stmt_old->bindParam(':id', $id);
                    $stmt_old->execute();
                    $old_data = $stmt_old->fetch(PDO::FETCH_ASSOC);
                    if ($old_data && $old_data['foto'] && file_exists('../uploads/guru/' . $old_data['foto'])) {
                        unlink('../uploads/guru/' . $old_data['foto']);
                    }
                }
            }
        }
    }

    if ($id) {
        // Update
        if ($foto) {
            $stmt = $db->prepare("UPDATE guru SET nik=:nik, nama=:nama, jenis_kelamin=:jk, tempat_lahir=:tempat_lahir, tanggal_lahir=:tanggal_lahir, alamat=:alamat, telepon=:telepon, email=:email, pendidikan_terakhir=:pendidikan, jurusan=:jurusan, kategori=:kategori, jabatan=:jabatan, guru_kelas=:guru_kelas, tanggal_masuk=:tanggal_masuk, status=:status, foto=:foto WHERE id=:id");
            $stmt->bindParam(':foto', $foto);
        } else {
            $stmt = $db->prepare("UPDATE guru SET nik=:nik, nama=:nama, jenis_kelamin=:jk, tempat_lahir=:tempat_lahir, tanggal_lahir=:tanggal_lahir, alamat=:alamat, telepon=:telepon, email=:email, pendidikan_terakhir=:pendidikan, jurusan=:jurusan, kategori=:kategori, jabatan=:jabatan, guru_kelas=:guru_kelas, tanggal_masuk=:tanggal_masuk, status=:status WHERE id=:id");
        }
        $stmt->bindParam(':id', $id);
    } else {
        // Insert
        $stmt = $db->prepare("INSERT INTO guru (nik, nama, jenis_kelamin, tempat_lahir, tanggal_lahir, alamat, telepon, email, pendidikan_terakhir, jurusan, kategori, jabatan, guru_kelas, tanggal_masuk, status, foto) VALUES (:nik, :nama, :jk, :tempat_lahir, :tanggal_lahir, :alamat, :telepon, :email, :pendidikan, :jurusan, :kategori, :jabatan, :guru_kelas, :tanggal_masuk, :status, :foto)");
        $stmt->bindParam(':foto', $foto);
    }
    
    $stmt->bindParam(':nik', $nik);
    $stmt->bindParam(':nama', $nama);
    $stmt->bindParam(':jk', $jenis_kelamin);
    $stmt->bindParam(':tempat_lahir', $tempat_lahir);
    $stmt->bindParam(':tanggal_lahir', $tanggal_lahir);
    $stmt->bindParam(':alamat', $alamat);
    $stmt->bindParam(':telepon', $telepon);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':pendidikan', $pendidikan_terakhir);
    $stmt->bindParam(':jurusan', $jurusan);
    $stmt->bindParam(':kategori', $kategori);
    $stmt->bindParam(':jabatan', $jabatan);
    $stmt->bindParam(':guru_kelas', $guru_kelas);
    $stmt->bindParam(':tanggal_masuk', $tanggal_masuk);
    $stmt->bindParam(':status', $status);
    $stmt->execute();
    
    header("Location: guru.php?success=save");
    exit();
}

// Get all guru
$stmt = $db->query("SELECT * FROM guru ORDER BY nama");
$guru_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

$edit_data = null;
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $db->prepare("SELECT * FROM guru WHERE id = :id");
    $stmt->bindParam(':id', $edit_id);
    $stmt->execute();
    $edit_data = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Guru - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-teacher"></i>
            <h2>👨‍🏫 Data Guru</h2>
        </div>

        <!-- Button Tambah -->
        <div class="content-card">
            <div style="display: flex; gap: 15px; align-items: center; flex-wrap: wrap; justify-content: space-between;">
                <button type="button" class="btn btn-primary" onclick="openModal()">
                    ➕ Tambah Guru
                </button>
                
               <input type="text" id="searchInput" placeholder="🔍 Cari NIK, nama, jabatan, kategori, status..." 
                       style="flex: 1; max-width: 400px; padding: 10px 15px; border: 1px solid #e2e8f0; border-radius: 6px;"
                       onkeyup="searchTable()">
            </div>
        </div>

        <!-- Data Table -->
        <div class="content-card">
            <h3>Daftar Guru</h3>
            
            <?php if (count($guru_list) > 0): ?>
            <div style="overflow-x: auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>No</th>
                        <th>NIK</th>
                        <th>Nama</th>
                        <th>JK</th>
                        <th>Telepon</th>
                        <th>Pendidikan</th>
                        <th>Kategori</th>
                        <th>Jabatan</th>
                        <th>Guru Kelas</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($guru_list as $index => $guru): ?>
                    <tr>
                        <td><?= $index + 1 ?></td>
                        <td><?= htmlspecialchars($guru['nik']) ?></td>
                        <td><?= htmlspecialchars($guru['nama']) ?></td>
                        <td><?= $guru['jenis_kelamin'] ?></td>
                        <td><?= htmlspecialchars($guru['telepon'] ?? '-') ?></td>
                        <td><?= htmlspecialchars($guru['pendidikan_terakhir'] ?? '-') ?></td>
                        <td><span class="badge <?= $guru['kategori'] == 'PNS' ? 'badge-success' : 'badge-info' ?>"><?= $guru['kategori'] ?></span></td>
                        <td><?= htmlspecialchars($guru['jabatan']) ?></td>
                        <td><?= htmlspecialchars($guru['guru_kelas'] ?? '-') ?></td>
                        <td><span class="badge <?= $guru['status'] == 'Aktif' ? 'badge-success' : 'badge-danger' ?>"><?= $guru['status'] ?></span></td>
                        <td>
                            <div style="display: flex; gap: 5px;">
                                <a href="javascript:void(0)" onclick="viewGuru(<?= htmlspecialchars(json_encode($guru)) ?>)" class="btn btn-success" title="Lihat Detail">👁️</a>
                                <a href="javascript:void(0)" onclick="editGuru(<?= htmlspecialchars(json_encode($guru)) ?>)" class="btn btn-warning">✏️</a>
                                <a href="?delete=<?= $guru['id'] ?>" class="btn btn-danger" onclick="return confirm('Yakin hapus data ini?')">🗑️</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 48px; margin-bottom: 15px;">👨‍🏫</div>
                <p>Belum ada data guru.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Modal Form -->
    <div id="guruModal" class="modal">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h3 id="modalTitle">Tambah Guru</h3>
                <button type="button" class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            
            <form method="POST" id="guruForm" enctype="multipart/form-data">
                <input type="hidden" name="id" id="guru_id">
                
                <div class="modal-body">
                    <!-- Foto Profile -->
                    <div class="form-group">
                        <label>Foto Profil</label>
                        <div style="text-align: center; margin-bottom: 15px;">
                            <img id="preview_foto" src="../assets/img/default-avatar.png" 
                                 style="width: 120px; height: 120px; border-radius: 50%; object-fit: cover; border: 3px solid #e2e8f0;">
                        </div>
                        <input type="file" name="foto" id="foto" accept="image/*" onchange="previewImage(this)">
                        <small style="color: #718096; font-size: 12px;">Format: JPG, PNG, GIF (Max 2MB)</small>
                    </div>

                    <h4 style="margin: 20px 0 15px 0; color: #10b981; border-bottom: 2px solid #10b981; padding-bottom: 8px;">📋 Data Pribadi</h4>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>NIK <span class="required">*</span></label>
                            <input type="text" name="nik" id="nik" placeholder="3174086201730001" required>
                        </div>

                        <div class="form-group">
                            <label>Nama Lengkap <span class="required">*</span></label>
                            <input type="text" name="nama" id="nama" placeholder="Nama Lengkap" required>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Jenis Kelamin <span class="required">*</span></label>
                            <select name="jenis_kelamin" id="jenis_kelamin" required>
                                <option value="">Pilih Jenis Kelamin</option>
                                <option value="Laki-laki">Laki-laki</option>
                                <option value="Perempuan">Perempuan</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Tempat Lahir <span class="required">*</span></label>
                            <input type="text" name="tempat_lahir" id="tempat_lahir" placeholder="Kota Kelahiran" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Tanggal Lahir <span class="required">*</span></label>
                        <input type="date" name="tanggal_lahir" id="tanggal_lahir" required>
                    </div>

                    <div class="form-group">
                        <label>Alamat Lengkap <span class="required">*</span></label>
                        <textarea name="alamat" id="alamat" rows="3" placeholder="Alamat lengkap tempat tinggal" required></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>No. Telepon <span class="required">*</span></label>
                            <input type="tel" name="telepon" id="telepon" placeholder="08123456789" required>
                        </div>

                        <div class="form-group">
                            <label>Email</label>
                            <input type="email" name="email" id="email" placeholder="email@example.com">
                        </div>
                    </div>

                    <h4 style="margin: 20px 0 15px 0; color: #10b981; border-bottom: 2px solid #10b981; padding-bottom: 8px;">🎓 Pendidikan</h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Pendidikan Terakhir <span class="required">*</span></label>
                            <select name="pendidikan_terakhir" id="pendidikan_terakhir" required>
                                <option value="">Pilih Pendidikan</option>
                                <option value="SMA/SMK">SMA/SMK</option>
                                <option value="D3">D3</option>
                                <option value="S1">S1</option>
                                <option value="S2">S2</option>
                                <option value="S3">S3</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Jurusan</label>
                            <input type="text" name="jurusan" id="jurusan" placeholder="Contoh: Pendidikan Anak Usia Dini">
                        </div>
                    </div>

                    <h4 style="margin: 20px 0 15px 0; color: #10b981; border-bottom: 2px solid #10b981; padding-bottom: 8px;">💼 Data Kepegawaian</h4>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Kategori <span class="required">*</span></label>
                            <select name="kategori" id="kategori" required>
                                <option value="">Pilih Kategori</option>
                                <option value="PNS">PNS</option>
                                <option value="Non PNS">Non PNS</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Jabatan <span class="required">*</span></label>
                            <select name="jabatan" id="jabatan" required>
                                <option value="">Pilih Jabatan</option>
                                <option value="Kepala Madrasah">Kepala Madrasah</option>
                                <option value="Wakil Kepala Madrasah">Wakil Kepala Madrasah</option>
                                <option value="Guru Kelas">Guru Kelas</option>
                                <option value="Guru Mata Pelajaran">Guru Mata Pelajaran</option>
                                <option value="Guru BK">Guru BK</option>
                                <option value="Staff TU">Staff TU</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Guru Kelas</label>
                            <input type="text" name="guru_kelas" id="guru_kelas" placeholder="Contoh: Kelompok A atau -">
                        </div>

                        <div class="form-group">
                            <label>Tanggal Bergabung <span class="required">*</span></label>
                            <input type="date" name="tanggal_masuk" id="tanggal_masuk" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Status <span class="required">*</span></label>
                        <select name="status" id="status" required>
                            <option value="">Pilih Status</option>
                            <option value="Aktif" selected>Aktif</option>
                            <option value="Tidak Aktif">Tidak Aktif</option>
                        </select>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-cancel" onclick="closeModal()">Batal</button>
                    <button type="submit" class="btn btn-primary">💾 Simpan</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal Detail View -->
    <div id="viewModal" class="modal">
        <div class="modal-content" style="max-width: 700px;">
            <div class="modal-header">
                <h3>👨‍🏫 Detail Guru</h3>
                <button type="button" class="modal-close" onclick="closeViewModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <div style="text-align: center; margin-bottom: 20px;">
                    <img id="view_foto" src="../assets/img/default-avatar.png" 
                         style="width: 150px; height: 150px; border-radius: 50%; object-fit: cover; border: 4px solid #10b981;">
                </div>
                
                <h4 style="margin: 15px 0 10px 0; color: #10b981;">📋 Data Pribadi</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>NIK</label>
                        <p id="view_nik"></p>
                    </div>
                    <div class="detail-item">
                        <label>Nama Lengkap</label>
                        <p id="view_nama"></p>
                    </div>
                    <div class="detail-item">
                        <label>Jenis Kelamin</label>
                        <p id="view_jenis_kelamin"></p>
                    </div>
                    <div class="detail-item">
                        <label>Tempat, Tanggal Lahir</label>
                        <p id="view_ttl"></p>
                    </div>
                    <div class="detail-item">
                        <label>Alamat</label>
                        <p id="view_alamat"></p>
                    </div>
                    <div class="detail-item">
                        <label>No. Telepon</label>
                        <p id="view_telepon"></p>
                    </div>
                    <div class="detail-item">
                        <label>Email</label>
                        <p id="view_email"></p>
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px 0; color: #10b981;">🎓 Pendidikan</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Pendidikan Terakhir</label>
                        <p id="view_pendidikan"></p>
                    </div>
                    <div class="detail-item">
                        <label>Jurusan</label>
                        <p id="view_jurusan"></p>
                    </div>
                </div>

                <h4 style="margin: 15px 0 10px 0; color: #10b981;">💼 Data Kepegawaian</h4>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Kategori</label>
                        <p id="view_kategori"></p>
                    </div>
                    <div class="detail-item">
                        <label>Jabatan</label>
                        <p id="view_jabatan"></p>
                    </div>
                    <div class="detail-item">
                        <label>Guru Kelas</label>
                        <p id="view_guru_kelas"></p>
                    </div>
                    <div class="detail-item">
                        <label>Tanggal Bergabung</label>
                        <p id="view_tanggal_masuk"></p>
                    </div>
                    <div class="detail-item">
                        <label>Status</label>
                        <p id="view_status"></p>
                    </div>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-cancel" onclick="closeViewModal()">Tutup</button>
            </div>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('preview_foto').src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }

        function openModal() {
            document.getElementById('guruModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Tambah Guru';
            document.getElementById('guruForm').reset();
            document.getElementById('guru_id').value = '';
            document.getElementById('preview_foto').src = '../assets/img/default-avatar.png';
        }

        function closeModal() {
            document.getElementById('guruModal').style.display = 'none';
        }

        function editGuru(data) {
            document.getElementById('guruModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = 'Edit Guru';
            document.getElementById('guru_id').value = data.id;
            document.getElementById('nik').value = data.nik;
            document.getElementById('nama').value = data.nama;
            document.getElementById('jenis_kelamin').value = data.jenis_kelamin;
            document.getElementById('tempat_lahir').value = data.tempat_lahir || '';
            document.getElementById('tanggal_lahir').value = data.tanggal_lahir || '';
            document.getElementById('alamat').value = data.alamat || '';
            document.getElementById('telepon').value = data.telepon || '';
            document.getElementById('email').value = data.email || '';
            document.getElementById('pendidikan_terakhir').value = data.pendidikan_terakhir || '';
            document.getElementById('jurusan').value = data.jurusan || '';
            document.getElementById('kategori').value = data.kategori;
            document.getElementById('jabatan').value = data.jabatan || '';
            document.getElementById('guru_kelas').value = data.guru_kelas || '';
            document.getElementById('tanggal_masuk').value = data.tanggal_masuk || '';
            document.getElementById('status').value = data.status;
            
            if (data.foto) {
                document.getElementById('preview_foto').src = '../uploads/guru/' + data.foto;
            } else {
                document.getElementById('preview_foto').src = '../assets/img/default-avatar.png';
            }
        }

        function viewGuru(data) {
            document.getElementById('viewModal').style.display = 'flex';
            
            if (data.foto) {
                document.getElementById('view_foto').src = '../uploads/guru/' + data.foto;
            } else {
                document.getElementById('view_foto').src = '../assets/img/default-avatar.png';
            }
            
            document.getElementById('view_nik').textContent = data.nik;
            document.getElementById('view_nama').textContent = data.nama;
            document.getElementById('view_jenis_kelamin').textContent = data.jenis_kelamin;
            
            let ttl = '-';
            if (data.tempat_lahir && data.tanggal_lahir) {
                const tglLahir = new Date(data.tanggal_lahir);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                ttl = data.tempat_lahir + ', ' + tglLahir.toLocaleDateString('id-ID', options);
            }
            document.getElementById('view_ttl').textContent = ttl;
            
            document.getElementById('view_alamat').textContent = data.alamat || '-';
            document.getElementById('view_telepon').textContent = data.telepon || '-';
            document.getElementById('view_email').textContent = data.email || '-';
            document.getElementById('view_pendidikan').textContent = data.pendidikan_terakhir || '-';
            document.getElementById('view_jurusan').textContent = data.jurusan || '-';
            document.getElementById('view_kategori').textContent = data.kategori;
            document.getElementById('view_jabatan').textContent = data.jabatan || '-';
            document.getElementById('view_guru_kelas').textContent = data.guru_kelas || '-';
            
            let tglMasuk = '-';
            if (data.tanggal_masuk) {
                const date = new Date(data.tanggal_masuk);
                const options = { year: 'numeric', month: 'long', day: 'numeric' };
                tglMasuk = date.toLocaleDateString('id-ID', options);
            }
            document.getElementById('view_tanggal_masuk').textContent = tglMasuk;
            document.getElementById('view_status').textContent = data.status;
        }

        function closeViewModal() {
            document.getElementById('viewModal').style.display = 'none';
        }

        function searchTable() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toUpperCase();
            const table = document.querySelector('.data-table tbody');
            const rows = table.getElementsByTagName('tr');

            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                const nik = cells[1]?.textContent || '';
                const nama = cells[2]?.textContent || '';
                const jk = cells[3]?.textContent || '';
                const telepon = cells[4]?.textContent || '';
                const pendidikan = cells[5]?.textContent || '';
                const kategori = cells[6]?.textContent || '';
                const jabatan = cells[7]?.textContent || '';
                const guruKelas = cells[8]?.textContent || '';
                const status = cells[9]?.textContent || '';
                
                const searchText = (nik + nama + jk + telepon + pendidikan + kategori + jabatan + guruKelas + status).toUpperCase();
                
                if (searchText.indexOf(filter) > -1) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        }

        window.onclick = function(event) {
            const modal = document.getElementById('guruModal');
            const viewModal = document.getElementById('viewModal');
            if (event.target == modal) {
                closeModal();
            }
            if (event.target == viewModal) {
                closeViewModal();
            }
        }

        <?php if (isset($_GET['edit']) && $edit_data): ?>
        editGuru(<?= json_encode($edit_data) ?>);
        <?php endif; ?>
    </script>
</body>
</html>