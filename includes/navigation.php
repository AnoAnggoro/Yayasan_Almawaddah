<?php
$current_page = basename($_SERVER['PHP_SELF']);
?>
<nav class="navigation">
    <ul class="nav-menu">
        <li><a href="beranda.php" class="<?= $current_page == 'beranda.php' ? 'active' : '' ?>">
            <i class="icon-home"></i> Beranda
        </a></li>
        <li><a href="guru.php" class="<?= $current_page == 'guru.php' ? 'active' : '' ?>">
            <i class="icon-teacher"></i> Guru
        </a></li>
        <li><a href="murid.php" class="<?= $current_page == 'murid.php' ? 'active' : '' ?>">
            <i class="icon-student"></i> Murid
        </a></li>
        <li><a href="absensi.php" class="<?= $current_page == 'absensi.php' ? 'active' : '' ?>">
            <i class="icon-attendance"></i> Absensi
        </a></li>
        <li><a href="nilai.php" class="<?= $current_page == 'nilai.php' ? 'active' : '' ?>">
            <i class="icon-grade"></i> Nilai
        </a></li>
        <li><a href="rapot.php" class="<?= $current_page == 'rapot.php' ? 'active' : '' ?>">
            <i class="icon-report"></i> Rapot
        </a></li>
         <li><a href="jadwal_kbm.php" class="<?= $current_page == 'jadwal_kbm.php' ? 'active' : '' ?>">
            <i class="icon-announcement"></i> 📢 Jadwal Kbm
        </a></li>
        <li><a href="pembayaran.php" class="<?= $current_page == 'pembayaran.php' ? 'active' : '' ?>">
            <i class="icon-payment"></i> Pembayaran
        </a></li>
          <li><a href="laporan.php" class="<?= $current_page == 'laporan.php' ? 'active' : '' ?>">
            <i class="icon-report"></i> 📊 Laporan
        </a></li>
        <li><a href="pengumuman.php" class="<?= $current_page == 'pengumuman.php' ? 'active' : '' ?>">
            <i class="icon-announcement"></i> 📢 Pengumuman
        </a></li>
                <li><a href="administrator.php" class="<?= $current_page == 'administrator.php' ? 'active' : '' ?>">
            <i class="icon-user"></i> 👥 Admin
        </a></li>
    </ul>
</nav>
