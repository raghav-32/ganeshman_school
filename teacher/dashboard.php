<?php
require_once '../includes/auth.php';
requireRole('teacher', '../index.php');

$pageTitle  = 'Teacher Dashboard';
$activeMenu = 'dashboard';
$user       = currentUser();
$teacherId  = $user['ref_id'];

$teacher = db()->fetchOne("SELECT * FROM teachers WHERE id=?", 'i', [$teacherId]);

// Assigned classes — from timetable, teacher_assignments, or class_teacher_id
$classes = db()->fetchAll("
    SELECT DISTINCT c.id, c.class_name, c.section,
           (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id AND s.is_active=1) as student_count
    FROM classes c
    WHERE c.class_teacher_id = ?
    UNION
    SELECT DISTINCT c.id, c.class_name, c.section,
           (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id AND s.is_active=1)
    FROM timetable tt
    JOIN classes c ON tt.class_id = c.id
    WHERE tt.teacher_id = ?
    UNION
    SELECT DISTINCT c.id, c.class_name, c.section,
           (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id AND s.is_active=1)
    FROM teacher_assignments ta
    JOIN classes c ON ta.class_id = c.id
    WHERE ta.teacher_id = ?
    ORDER BY class_name
", 'iii', [$teacherId, $teacherId, $teacherId]);

// Today's attendance marks
$today = date('Y-m-d');
$todayMarked = db()->fetchOne("SELECT COUNT(DISTINCT class_id) as c FROM attendance WHERE marked_by=? AND attendance_date=?", 'is', [$teacherId, $today]);

// Recent notices
$notices = db()->fetchAll("
    SELECT * FROM notices WHERE is_active=1 AND (target_role='all' OR target_role='teacher')
    ORDER BY created_at DESC LIMIT 4
");

require_once '../includes/teacher_header.php';
?>

<!-- Welcome -->
<div style="background:linear-gradient(135deg,#059669,#10b981);border-radius:var(--radius-lg);padding:26px 30px;margin-bottom:24px;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div>
        <h2 style="font-family:'Playfair Display',serif;font-size:1.5rem;margin-bottom:4px;">
            Welcome, <?= htmlspecialchars($teacher['first_name'] ?? $user['name']) ?>!
        </h2>
        <p style="opacity:0.85;font-size:0.88rem;">
            Teacher ID: <?= htmlspecialchars($teacher['teacher_id'] ?? '–') ?> &bull; <?= date('D') ?>, <?= NepaliDate::today() ?>
        </p>
    </div>
    <div style="text-align:right;">
        <div style="font-size:2rem;font-weight:900;"><?= count($classes) ?></div>
        <div style="font-size:0.78rem;opacity:0.75;">Assigned Classes</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-school"></i></div>
        <div class="stat-info"><h3><?= count($classes) ?></h3><p>My Classes</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-clipboard-check"></i></div>
        <div class="stat-info"><h3><?= $todayMarked['c'] ?></h3><p>Attendance Marked Today</p></div>
    </div>
</div>

<div class="grid-2">
    <!-- My Classes -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-school"></i> My Classes</h3>
            <a href="attendance.php" class="btn btn-success btn-sm"><i class="fas fa-clipboard-check"></i> Mark Attendance</a>
        </div>
        <div class="card-body" style="padding:0;">
            <?php if (empty($classes)): ?>
            <div class="empty-state" style="padding:32px;">
                <i class="fas fa-school"></i>
                <p>No classes assigned yet. Contact admin.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>Class</th><th>Section</th><th>Students</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach ($classes as $c): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['class_name']) ?></strong></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($c['section']) ?></span></td>
                            <td><?= $c['student_count'] ?> students</td>
                            <td>
                                <a href="attendance.php?class_id=<?= $c['id'] ?>" class="btn btn-success btn-sm">
                                    <i class="fas fa-check"></i> Attendance
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notices -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-bullhorn"></i> Notices</h3></div>
        <div class="card-body">
            <?php if (empty($notices)): ?>
            <div class="empty-state" style="padding:24px;"><i class="fas fa-bullhorn"></i><p>No notices.</p></div>
            <?php else: ?>
            <?php foreach ($notices as $n): ?>
            <div class="notice-item">
                <h4><?= htmlspecialchars($n['title']) ?></h4>
                <p><?= htmlspecialchars(substr($n['content'],0,100)) ?>...</p>
                <div class="notice-meta"><span><i class="fas fa-calendar"></i> <?= formatDate($n['created_at']) ?></span></div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require_once '../includes/teacher_footer.php'; ?>