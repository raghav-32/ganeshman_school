<?php
// Admin Layout Header v2
$user  = currentUser();
$flash = getFlash();

// Fetch recent notices for notification panel
$notifNotices = db()->fetchAll("
    SELECT title, content, created_at FROM notices
    WHERE is_active=1 AND (target_role='all' OR target_role='admin')
    ORDER BY created_at DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= ($pageTitle ?? 'Dashboard') ?> – Admin | Ganeshman Singh Academy</title>
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
    <a href="dashboard.php" class="sidebar-brand" style="text-decoration:none;color:inherit;display:flex;align-items:center;">
        <div class="brand-icon"><img src="../assets/images/gms.png" alt="gms"></div>
        <div class="brand-text">
            <h3>Ganeshman Singh</h3>
            <span>Academy – Admin</span>
        </div>
    </a>

    <nav class="sidebar-nav">
        <span class="nav-section-title">Main</span>
        <a href="dashboard.php" class="nav-item <?= $activeMenu==='dashboard'?'active':'' ?>"><i class="fas fa-th-large"></i> Dashboard</a>

        <span class="nav-section-title">Academic</span>
        <a href="students.php"  class="nav-item <?= $activeMenu==='students'?'active':'' ?>"><i class="fas fa-user-graduate"></i> Students</a>
        <a href="teachers.php"  class="nav-item <?= $activeMenu==='teachers'?'active':'' ?>"><i class="fas fa-chalkboard-teacher"></i> Teachers</a>
        <a href="classes.php"   class="nav-item <?= $activeMenu==='classes'?'active':'' ?>"><i class="fas fa-school"></i> Classes</a>
        <a href="subjects.php"  class="nav-item <?= $activeMenu==='subjects'?'active':'' ?>"><i class="fas fa-book-open"></i> Subjects</a>
        <a href="timetable.php" class="nav-item <?= $activeMenu==='timetable'?'active':'' ?>"><i class="fas fa-calendar-alt"></i> Timetable</a>

        <span class="nav-section-title">Attendance & Exams</span>
        <a href="attendance.php" class="nav-item <?= $activeMenu==='attendance'?'active':'' ?>"><i class="fas fa-clipboard-check"></i> Attendance</a>
        <a href="exams.php"      class="nav-item <?= $activeMenu==='exams'?'active':'' ?>"><i class="fas fa-file-alt"></i> Exams</a>
        <a href="marks.php"      class="nav-item <?= $activeMenu==='marks'?'active':'' ?>"><i class="fas fa-star-half-alt"></i> Marks & Results</a>

        <span class="nav-section-title">Finance & Library</span>
        <a href="fees.php"    class="nav-item <?= $activeMenu==='fees'?'active':'' ?>"><i class="fas fa-rupee-sign"></i> Fee Management</a>
        <a href="library.php" class="nav-item <?= $activeMenu==='library'?'active':'' ?>"><i class="fas fa-book"></i> Library</a>

        <span class="nav-section-title">Communication</span>
        <a href="notices.php" class="nav-item <?= $activeMenu==='notices'?'active':'' ?>"><i class="fas fa-bullhorn"></i> Notices</a>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="user-avatar"><?= strtoupper(substr($user['name'],0,1)) ?></div>
            <div class="user-info">
                <h4><?= htmlspecialchars($user['name']) ?></h4>
                <span><?= $user['role'] ?></span>
            </div>
            <a href="../logout.php" class="logout-btn" title="Logout"><i class="fas fa-sign-out-alt"></i></a>
        </div>
    </div>
</aside>

<div class="main-content" id="mainContent">
    <header class="topbar">
        <button class="hamburger-btn" onclick="toggleSidebar()" title="Toggle Menu">
            <i class="fas fa-bars"></i>
        </button>
        <div class="topbar-title">
            <h2><?= $pageTitle ?? 'Dashboard' ?></h2>
            <div class="breadcrumb">Admin Panel › <?= $pageTitle ?? 'Dashboard' ?></div>
        </div>
        <div class="topbar-actions">
            <div class="topbar-date">
                <i class="fas fa-calendar" style="margin-right:5px;color:var(--primary-light);"></i>
                <?= NepaliDate::todayDayName() ?>, <?= NepaliDate::today() ?> &nbsp;|&nbsp; <span id="liveClock"></span>
            </div>
            <!-- Notification Bell -->
            <div class="topbar-notification" style="position:relative;">
                <i class="fas fa-bell"></i>
                <?php if (!empty($notifNotices)): ?>
                <span class="notif-dot"></span>
                <?php endif; ?>
                <!-- Notification Panel -->
                <div class="notif-panel" id="notifPanel">
                    <div class="notif-panel-header">
                        <h4><i class="fas fa-bell" style="color:var(--primary-light);margin-right:5px;"></i> Notifications</h4>
                        <span class="badge badge-primary"><?= count($notifNotices) ?></span>
                    </div>
                    <div class="notif-panel-body">
                        <?php if (empty($notifNotices)): ?>
                        <div class="notif-empty"><i class="fas fa-bell-slash"></i>No new notifications</div>
                        <?php else: ?>
                        <?php foreach ($notifNotices as $n): ?>
                        <div class="notif-item" onclick="window.location='notices.php'">
                            <h5><?= htmlspecialchars($n['title']) ?></h5>
                            <p><?= htmlspecialchars(substr($n['content'],0,70)) ?>...</p>
                            <div class="notif-time"><i class="fas fa-clock"></i> <?= NepaliDate::formatWithTime($n['created_at']) ?></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <?php if (!empty($flash)): ?>
    <div style="padding:0 28px;padding-top:16px;">
        <div class="alert alert-<?= $flash['type'] ?>">
            <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="page-content">