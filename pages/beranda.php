<?php
require_once '../config/session.php';
require_once '../config/database.php';
requireLogin();

// Set timezone to Indonesia
date_default_timezone_set('Asia/Jakarta');

$database = new Database();
$db = $database->getConnection();

// Get statistics
$stats = [
    'total_guru' => $db->query("SELECT COUNT(*) FROM guru WHERE status='Aktif'")->fetchColumn(),
    'total_murid' => $db->query("SELECT COUNT(*) FROM murid WHERE (status_murid='Aktif' OR status_murid IS NULL)")->fetchColumn(),
    'total_aspek' => $db->query("SELECT COUNT(*) FROM aspek_penilaian")->fetchColumn(),
    'murid_aktif' => $db->query("SELECT COUNT(*) FROM murid WHERE (status_murid='Aktif' OR status_murid IS NULL)")->fetchColumn()
];

// Get today's day name in Indonesian
$days_indo = [
    'Sunday' => 'Minggu',
    'Monday' => 'Senin',
    'Tuesday' => 'Selasa',
    'Wednesday' => 'Rabu',
    'Thursday' => 'Kamis',
    'Friday' => 'Jumat',
    'Saturday' => 'Sabtu'
];
$today = $days_indo[date('l')];

// Get current time (format: HH:MM)
$current_hour = (int)date('H');
$current_minute = (int)date('i');
$current_time_minutes = ($current_hour * 60) + $current_minute;

// Get today's schedule
$query_today = "SELECT j.*, g.nama as nama_guru 
                FROM jadwal_kbm j 
                LEFT JOIN guru g ON j.guru_id = g.id 
                WHERE j.hari = :today 
                ORDER BY j.waktu";
$stmt_today = $db->prepare($query_today);
$stmt_today->bindParam(':today', $today);
$stmt_today->execute();
$today_schedules = $stmt_today->fetchAll(PDO::FETCH_ASSOC);

// Function to convert time string to minutes
function timeToMinutes($time) {
    $parts = explode(':', $time);
    return ((int)$parts[0] * 60) + (int)$parts[1];
}

// Prepare notification data
$notification = null;
$total_schedules = count($today_schedules);

if ($total_schedules > 0) {
    // Find next schedule and ongoing schedule
    $next_schedule = null;
    $ongoing_schedule = null;
    
    foreach ($today_schedules as $schedule) {
        // Split waktu (format: HH:MM - HH:MM)
        $waktu_parts = explode(' - ', $schedule['waktu']);
        $waktu_mulai = trim($waktu_parts[0]);
        $waktu_selesai = trim($waktu_parts[1]);
        
        // Convert to minutes for comparison
        $start_minutes = timeToMinutes($waktu_mulai);
        $end_minutes = timeToMinutes($waktu_selesai);
        
        // Check if currently ongoing
        if ($current_time_minutes >= $start_minutes && $current_time_minutes <= $end_minutes) {
            $ongoing_schedule = $schedule;
            break; // Found ongoing, no need to continue
        }
        
        // Check if upcoming (and no ongoing schedule found)
        if ($current_time_minutes < $start_minutes && !$next_schedule) {
            $next_schedule = $schedule;
        }
    }
    
    // Set notification based on status
    if ($ongoing_schedule) {
        $waktu_parts = explode(' - ', $ongoing_schedule['waktu']);
        $notification = [
            'type' => 'ongoing',
            'icon' => '▶️',
            'title' => 'Jadwal Sedang Berlangsung',
            'message' => $ongoing_schedule['tema'] . ' - ' . $ongoing_schedule['tingkat'] . ' (' . $ongoing_schedule['nama_guru'] . ')',
            'color' => 'linear-gradient(135deg, #27ae60 0%, #229954 100%)',
            'time' => 'Sampai ' . trim($waktu_parts[1])
        ];
    } else if ($next_schedule) {
        $waktu_parts = explode(' - ', $next_schedule['waktu']);
        
        // Calculate time difference
        $next_start = timeToMinutes(trim($waktu_parts[0]));
        $diff_minutes = $next_start - $current_time_minutes;
        $diff_hours = floor($diff_minutes / 60);
        $diff_mins = $diff_minutes % 60;
        
        $time_until = '';
        if ($diff_hours > 0) {
            $time_until = $diff_hours . ' jam ' . $diff_mins . ' menit lagi';
        } else {
            $time_until = $diff_mins . ' menit lagi';
        }
        
        $notification = [
            'type' => 'upcoming',
            'icon' => '⏰',
            'title' => 'Jadwal Berikutnya',
            'message' => $next_schedule['tema'] . ' - ' . $next_schedule['tingkat'] . ' (' . $next_schedule['nama_guru'] . ')',
            'color' => 'linear-gradient(135deg, #3498db 0%, #2980b9 100%)',
            'time' => $time_until
        ];
    } else {
        // All schedules for today are finished
        $notification = [
            'type' => 'finished',
            'icon' => '✅',
            'title' => 'Semua Jadwal Selesai',
            'message' => 'Semua jadwal mengajar hari ini telah selesai. Terima kasih atas kerja keras Anda!',
            'color' => 'linear-gradient(135deg, #95a5a6 0%, #7f8c8d 100%)',
            'time' => $total_schedules . ' Jadwal'
        ];
    }
}

// Get recent announcements (limit 3, newest first)
try {
    $query_announcements = "SELECT * FROM pengumuman WHERE status='Aktif' ORDER BY created_at DESC LIMIT 3";
    $stmt_announcements = $db->query($query_announcements);
    $announcements = $stmt_announcements->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Jika tabel belum ada, set announcements kosong
    $announcements = [];
}

$user = getUserData();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Beranda - Yayasan Al Mawaddah</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="stylesheet" href="../assets/css/style.css">
  <!-- <meta name="theme-color" content="#4a7c59">  -->
    <style>
        .notification-banner {
            background: <?= $notification ? $notification['color'] : 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)' ?>;
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            animation: slideDown 0.5s ease-out;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .notification-icon {
            font-size: 32px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.1);
            }
        }
        
        .notification-content {
            flex: 1;
        }
        
        .notification-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .notification-text {
            font-size: 14px;
            opacity: 0.95;
        }
        
        .notification-time {
            background: rgba(255,255,255,0.2);
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
        }
        
        .schedule-card {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border-left: 4px solid #5a8c6a;
            margin-bottom: 10px;
            transition: all 0.3s;
        }
        
        .schedule-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        
        .schedule-card.active {
            background: linear-gradient(135deg, #5a8c6a 0%, #4a7c59 100%);
            color: white;
            border-left-color: #fff;
        }
        
        .schedule-time {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .schedule-theme {
            font-size: 14px;
            margin-bottom: 3px;
        }
        
        .schedule-teacher {
            font-size: 13px;
            opacity: 0.8;
        }
        
        .schedule-badge {
            display: inline-block;
            background: rgba(255,255,255,0.3);
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            margin-left: 8px;
        }
        
        .empty-schedule {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .empty-schedule-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .current-time-display {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 10px 20px;
            border-radius: 8px;
            text-align: center;
            margin-bottom: 15px;
            font-size: 18px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        .time-clock {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .time-date {
            font-size: 14px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <?php include '../includes/header.php'; ?>
    <?php include '../includes/navigation.php'; ?>

    <div class="main-content">
        <div class="page-header">
            <i class="icon-home"></i>
            <h2>Beranda</h2>
        </div>

        <!-- Current Time Display -->
        <div class="current-time-display">
            <div class="time-clock" id="currentTime">
                🕐 <?= date('H:i:s') ?> WIB
            </div>
            <div class="time-date">
                <?= $today ?>, <?= date('d F Y') ?>
            </div>
        </div>

        <!-- Schedule Notification -->
        <?php if ($notification): ?>
        <div class="notification-banner">
            <div class="notification-icon"><?= $notification['icon'] ?></div>
            <div class="notification-content">
                <div class="notification-title"><?= $notification['title'] ?></div>
                <div class="notification-text"><?= $notification['message'] ?></div>
            </div>
            <div class="notification-time"><?= $notification['time'] ?></div>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">👨‍🏫</div>
                <div class="stat-value"><?= $stats['total_guru'] ?></div>
                <div class="stat-label">Total Guru</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">👨‍🎓</div>
                <div class="stat-value"><?= $stats['total_murid'] ?></div>
                <div class="stat-label">Total Murid</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">📚</div>
                <div class="stat-value"><?= $stats['total_aspek'] ?></div>
                <div class="stat-label">Aspek Penilaian</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon">✅</div>
                <div class="stat-value"><?= $stats['murid_aktif'] ?></div>
                <div class="stat-label">Murid Aktif</div>
            </div>
        </div>

        <!-- Today's Schedule -->
        <div class="content-card">
            <h3 style="margin-bottom: 15px;">🗓️ Jadwal Hari Ini (<?= $today ?>)</h3>
            <div class="schedule-grid">
                <?php if (count($today_schedules) > 0): ?>
                    <?php foreach ($today_schedules as $schedule): 
                        $waktu_parts = explode(' - ', $schedule['waktu']);
                        $waktu_mulai = trim($waktu_parts[0]);
                        $waktu_selesai = trim($waktu_parts[1]);
                        
                        // Convert to minutes for comparison
                        $start_minutes = timeToMinutes($waktu_mulai);
                        $end_minutes = timeToMinutes($waktu_selesai);
                        
                        // Check if this schedule is currently active
                        $is_active = ($current_time_minutes >= $start_minutes && 
                                     $current_time_minutes <= $end_minutes);
                        
                        // Check if upcoming (within 30 minutes)
                        $is_upcoming = ($current_time_minutes < $start_minutes && 
                                       ($start_minutes - $current_time_minutes) <= 30);
                        
                        // Check if finished
                        $is_finished = ($current_time_minutes > $end_minutes);
                    ?>
                    <div class="schedule-card <?= $is_active ? 'active' : '' ?>" 
                         style="<?= $is_finished ? 'opacity: 0.5;' : '' ?>">
                        <div class="schedule-time">
                            <?php if ($is_active): ?>
                                ▶️ <?= htmlspecialchars($schedule['waktu']) ?>
                                <span class="schedule-badge">Sedang Berlangsung</span>
                            <?php elseif ($is_upcoming): ?>
                                ⏰ <?= htmlspecialchars($schedule['waktu']) ?>
                                <span class="schedule-badge" style="background: rgba(255,193,7,0.3); color: #f57f17;">Segera Dimulai</span>
                            <?php elseif ($is_finished): ?>
                                ✅ <?= htmlspecialchars($schedule['waktu']) ?>
                                <span class="schedule-badge" style="background: rgba(158,158,158,0.3);">Selesai</span>
                            <?php else: ?>
                                🕐 <?= htmlspecialchars($schedule['waktu']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="schedule-theme">📖 <?= htmlspecialchars($schedule['tema']) ?></div>
                        <div class="schedule-teacher">
                            <?= htmlspecialchars($schedule['tingkat']) ?> - 
                            👨‍🏫 <?= htmlspecialchars($schedule['nama_guru'] ?? 'Belum ditentukan') ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-schedule">
                        <div class="empty-schedule-icon">📅</div>
                        <div>Tidak ada jadwal untuk hari ini</div>
                        <div style="font-size: 13px; margin-top: 5px;">Selamat beristirahat! 😊</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="announcement-section">
            <h3 style="margin-bottom: 15px;">📢 Pengumuman Terbaru</h3>
            
            <?php if (count($announcements) > 0): ?>
                <?php foreach ($announcements as $announcement): ?>
                <div class="announcement-card">
                    <h4><?= htmlspecialchars($announcement['judul']) ?></h4>
                    <p><?= nl2br(htmlspecialchars($announcement['isi'])) ?></p>
                    <div class="announcement-date">
                        Diposting: <?= date('d F Y, H:i', strtotime($announcement['created_at'])) ?> WIB
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="empty-schedule">
                    <div class="empty-schedule-icon">📭</div>
                    <div>Tidak ada pengumuman saat ini</div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/app.js"></script>
    <script>
        // Update clock every second
        function updateClock() {
            const now = new Date();
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            document.getElementById('currentTime').innerHTML = 
                '🕐 ' + hours + ':' + minutes + ':' + seconds + ' WIB';
        }
        
        // Update every second
        setInterval(updateClock, 1000);
        
        // Auto refresh page every 1 minute to update schedule status
        setTimeout(function() {
            location.reload();
        }, 60000); // 1 minute

        // Show browser notification if there are schedules today
        <?php if ($notification && $notification['type'] === 'upcoming'): ?>
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('<?= $notification['title'] ?>', {
                body: '<?= addslashes($notification['message']) ?>',
                icon: '../assets/images/logo.png',
                badge: '../assets/images/logo.png',
                tag: 'schedule-reminder'
            });
        } else if ('Notification' in window && Notification.permission !== 'denied') {
            Notification.requestPermission().then(function(permission) {
                if (permission === 'granted') {
                    new Notification('<?= $notification['title'] ?>', {
                        body: '<?= addslashes($notification['message']) ?>',
                        icon: '../assets/images/logo.png',
                        tag: 'schedule-reminder'
                    });
                }
            });
        }
        <?php endif; ?>
        
        console.log('Current time (minutes): <?= $current_time_minutes ?>');
        console.log('Current time: <?= date('H:i') ?>');
    </script>
</body>
</html>
