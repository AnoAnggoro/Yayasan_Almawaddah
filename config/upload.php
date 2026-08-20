<?php
// Simpan satu berkas unggahan dengan pemeriksaan tipe dan ukuran.
// Mengembalikan nama berkas baru, atau null kalau ditolak ($pesan berisi alasannya).
function simpan_upload(array $file, $folder, $prefix, array $ext_diizinkan, &$pesan = null, $maks_byte = 2097152) {
    $mime_resmi = [
        'jpg'  => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png'  => 'image/png',
        'gif'  => 'image/gif',
        'pdf'  => 'application/pdf'
    ];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $pesan = 'Berkas gagal diunggah.';
        return null;
    }
    if ($file['size'] > $maks_byte) {
        $pesan = 'Ukuran berkas melebihi ' . round($maks_byte / 1048576, 1) . ' MB.';
        return null;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $ext_diizinkan, true) || !isset($mime_resmi[$ext])) {
        $pesan = 'Format berkas tidak diizinkan.';
        return null;
    }

    // Periksa isi berkasnya, jangan cuma percaya nama file: .jpg bisa saja berisi skrip.
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    if ($finfo->file($file['tmp_name']) !== $mime_resmi[$ext]) {
        $pesan = 'Isi berkas tidak cocok dengan formatnya.';
        return null;
    }

    if (!is_dir($folder) && !mkdir($folder, 0755, true)) {
        $pesan = 'Folder unggahan tidak bisa dibuat.';
        return null;
    }

    $nama_baru = $prefix . '_' . time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], rtrim($folder, '/') . '/' . $nama_baru)) {
        $pesan = 'Berkas gagal disimpan di server.';
        return null;
    }

    return $nama_baru;
}
