<?php
$user  = currentUser();
$flash = getFlash();
// Fetch student photo
$_sPhotoRow  = db()->fetchOne("SELECT photo FROM students WHERE user_id=?", 'i', [$user['id']]);
$_sPhoto     = $_sPhotoRow['photo'] ?? 'default.png';
$_sPhotoPath = '../assets/uploads/students/' . $_sPhoto;
$_sHasPhoto  = $_sPhoto && $_sPhoto !== 'default.png' && $_sPhoto !== '0' && file_exists($_sPhotoPath);
$notifNotices = db()->fetchAll("SELECT title,content,created_at FROM notices WHERE is_active=1 AND (target_role='all' OR target_role='student') ORDER BY created_at DESC LIMIT 5");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?? 'Dashboard' ?> – Student | Ganeshman Singh Academy</title>
    <!-- Favicon -->
    <link rel="icon" type="image/png" href="../assets/images/gmscrop.png">
    <link rel="shortcut icon" type="image/png" href="../assets/images/gmscrop.png">
    <link rel="apple-touch-icon" href="../assets/icons/apple-touch-icon.png">
    <!-- PWA: installable app support -->
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#1a3c5e">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="GMS App">
    <script src="../assets/js/pwa-install.js" defer></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>
<div class="app-wrapper">
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);"><img src="../assets/images/gms.png" alt="gms"></div>
        <div class="brand-text"><h3>Ganeshman Singh</h3><span>Student Portal</span></div>
    </div>
    <nav class="sidebar-nav">
        <span class="nav-section-title">My Portal</span>
        <a href="dashboard" class="nav-item <?= $activeMenu==='dashboard'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="profile"   class="nav-item <?= $activeMenu==='profile'?'active':'' ?>"><i class="fas fa-id-card"></i> My Profile</a>
        <a href="attendance" class="nav-item <?= $activeMenu==='attendance'?'active':'' ?>"><i class="fas fa-clipboard-check"></i> My Attendance</a>
        <a href="results"   class="nav-item <?= $activeMenu==='results'?'active':'' ?>"><i class="fas fa-star-half-alt"></i> My Results</a>
        <a href="exams"     class="nav-item <?= $activeMenu==='exams'?'active':'' ?>"><i class="fas fa-file-alt"></i> Exam Schedule</a>
        <a href="fees"      class="nav-item <?= $activeMenu==='fees'?'active':'' ?>"><i class="fas fa-rupee-sign"></i> My Fees</a>
        <a href="timetable" class="nav-item <?= $activeMenu==='timetable'?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Timetable</a>
        <a href="notices"   class="nav-item <?= $activeMenu==='notices'?'active':'' ?>"><i class="fas fa-bullhorn"></i> Notices</a>
        <a href="library"   class="nav-item <?= $activeMenu==='library'?'active':'' ?>"><i class="fas fa-book-open"></i> Library</a>
    </nav>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar" style="background:linear-gradient(135deg,#f59e0b,#fbbf24);overflow:hidden;padding:0;">
                <?php if($_sHasPhoto): ?>
                    <img src="<?= htmlspecialchars($_sPhotoPath) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
                <?php else: ?>
                    <?= strtoupper(substr($user['name'],0,1)) ?>
                <?php endif; ?>
            </div>
            <div class="user-info"><h4><?= htmlspecialchars($user['name']) ?></h4><span>Student</span></div>
            <a href="../logout" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>
<div class="main-content" id="mainContent">
    <header class="topbar">
        <button class="hamburger-btn" onclick="toggleSidebar()"><i class="fas fa-bars"></i></button>
        <div class="topbar-title">
            <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
            <div class="breadcrumb">Student Portal › <?= $pageTitle ?? 'Dashboard' ?></div>
        </div>
        <div class="topbar-actions">
            <div class="topbar-date"><i class="fas fa-calendar" style="margin-right:5px;color:var(--accent);"></i><?= NepaliDate::todayDayName() ?>, <?= NepaliDate::today() ?> &nbsp;|&nbsp; <span id="liveClock"></span></div>
            <div class="topbar-notification" style="position:relative;">
                <i class="fas fa-bell"></i>
                <?php if(!empty($notifNotices)): ?><span class="notif-dot"></span><?php endif; ?>
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-header"><h4><i class="fas fa-bell" style="color:var(--accent);margin-right:5px;"></i> Notices</h4><span class="badge badge-warning"><?= count($notifNotices) ?></span></div>
                    <div class="notif-panel-body">
                        <?php if(empty($notifNotices)): ?><div class="notif-empty"><i class="fas fa-bell-slash"></i>No notifications</div>
                        <?php else: foreach($notifNotices as $n): ?>
                        <div class="notif-item" onclick="window.location='notices.php'">
                            <h5><?= htmlspecialchars($n['title']) ?></h5>
                            <p><?= htmlspecialchars(substr($n['content'],0,70)) ?>...</p>
                            <div class="notif-time"><?= NepaliDate::formatWithTime($n['created_at']) ?></div>
                        </div>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>
    <?php if(!empty($flash)): ?><div style="padding:16px 28px 0;"><div class="alert alert-<?= $flash['type'] ?>"><i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle'?>"></i> <?= htmlspecialchars($flash['message']) ?></div></div><?php endif; ?>
    <div class="page-content">