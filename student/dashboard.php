<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'My Dashboard';
$activeMenu = 'dashboard';
$user       = currentUser();
$studentId  = $user['ref_id'];

// Get student info
$student = db()->fetchOne("
    SELECT s.*, c.class_name, c.section
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.id = ?
", 'i', [$studentId]);

if (!$student) { logoutUser(); redirect('../index.php'); }

// Attendance summary
$att = db()->fetchOne("
    SELECT COUNT(*) as total,
           SUM(status='Present') as present,
           SUM(status='Absent') as absent,
           SUM(status='Late') as late
    FROM attendance WHERE student_id=?
", 'i', [$studentId]);
$attPct = ($att['total'] > 0) ? round(($att['present'] / $att['total']) * 100, 1) : 0;

// Latest exam results
$latestResults = db()->fetchAll("
    SELECT e.exam_name, e.exam_type,
           SUM(m.marks_obtained) as total_obtained,
           SUM(m.full_marks) as total_full
    FROM marks m
    JOIN exams e ON m.exam_id = e.id
    WHERE m.student_id = ? AND e.is_published = 1
    GROUP BY m.exam_id
    ORDER BY e.created_at DESC LIMIT 4
", 'i', [$studentId]);

// Recent notices
$notices = db()->fetchAll("
    SELECT * FROM notices WHERE is_active=1 AND (target_role='all' OR target_role='student')
    ORDER BY created_at DESC LIMIT 4
");

// Fee status
$feePaid = db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as total FROM fee_payments WHERE student_id=?", 'i', [$studentId]);

// Issued books
$issuedBook = db()->fetchOne("SELECT ib.*, b.title FROM issued_books ib JOIN books b ON ib.book_id=b.id WHERE ib.student_id=? AND ib.status='Issued' LIMIT 1", 'i', [$studentId]);

require_once '../includes/student_header.php';
?>

<!-- Welcome Banner -->
<div style="background:linear-gradient(135deg,var(--primary),var(--primary-light));border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:24px;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div>
        <h2 style="font-family:'Playfair Display',serif;font-size:1.6rem;margin-bottom:6px;">
            Welcome back, <?= htmlspecialchars($student['first_name']) ?>! 👋
        </h2>
        <p style="opacity:0.85;font-size:0.90rem;">
            <?= htmlspecialchars($student['class_name'].' '.$student['section']) ?> &nbsp;•&nbsp;
            ID: <?= htmlspecialchars($student['student_id']) ?> &nbsp;•&nbsp;
            <?= date('D') ?>, <?= NepaliDate::today() ?>
        </p>
    </div>
    <div style="text-align:right;">
        <div style="font-size:2.2rem;font-weight:900;line-height:1;"><?= $attPct ?>%</div>
        <div style="font-size:0.78rem;opacity:0.75;">Attendance Rate</div>
    </div>
</div>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info">
            <h3><?= $att['present'] ?? 0 ?></h3>
            <p>Days Present</p>
        </div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info">
            <h3><?= $att['absent'] ?? 0 ?></h3>
            <p>Days Absent</p>
        </div>
    </div>
    <div class="stat-card purple">
        <div class="stat-icon purple"><i class="fas fa-rupee-sign"></i></div>
        <div class="stat-info">
            <h3>Rs <?= number_format($feePaid['total'] ?? 0, 0) ?></h3>
            <p>Fee Paid</p>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-book"></i></div>
        <div class="stat-info">
            <h3><?= $issuedBook ? '1' : '0' ?></h3>
            <p>Books Borrowed</p>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- Exam Results -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-star-half-alt"></i> Recent Results</h3>
            <a href="results.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if (empty($latestResults)): ?>
            <div class="empty-state" style="padding:24px;">
                <i class="fas fa-star-half-alt"></i>
                <p>No published results yet.</p>
            </div>
            <?php else: ?>
            <?php foreach ($latestResults as $r):
                $pct = ($r['total_full'] > 0) ? round(($r['total_obtained']/$r['total_full'])*100,1) : 0;
                $grade = calculateGrade($r['total_obtained'], $r['total_full'] ?: 100);
                $gradeColors = ['A+'=>'success','A'=>'success','B+'=>'info','B'=>'info','C+'=>'warning','C'=>'warning','D'=>'warning','F'=>'danger'];
            ?>
            <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 0;border-bottom:1px solid var(--border-light);">
                <div>
                    <div style="font-weight:700;font-size:0.88rem;"><?= htmlspecialchars($r['exam_name']) ?></div>
                    <div style="font-size:0.75rem;color:var(--text-secondary);"><?= htmlspecialchars($r['exam_type']) ?> &bull; <?= $r['total_obtained'] ?>/<?= $r['total_full'] ?></div>
                </div>
                <div style="display:flex;align-items:center;gap:10px;">
                    <div style="text-align:right;">
                        <div style="font-weight:900;font-size:1.05rem;"><?= $pct ?>%</div>
                    </div>
                    <span class="badge badge-<?= $gradeColors[$grade] ?? 'gray' ?>"><?= $grade ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notices -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullhorn"></i> Recent Notices</h3>
            <a href="notices.php" class="btn btn-outline btn-sm">All</a>
        </div>
        <div class="card-body">
            <?php if (empty($notices)): ?>
            <div class="empty-state" style="padding:24px;">
                <i class="fas fa-bullhorn"></i>
                <p>No notices at this time.</p>
            </div>
            <?php else: ?>
            <?php foreach ($notices as $n): ?>
            <div class="notice-item">
                <h4><?= htmlspecialchars($n['title']) ?></h4>
                <p><?= htmlspecialchars(substr($n['content'], 0, 100)) ?>...</p>
                <div class="notice-meta">
                    <span><i class="fas fa-calendar"></i> <?= formatDate($n['created_at']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Borrowed book alert -->
<?php if ($issuedBook): ?>
<?php $overdue = $issuedBook['due_date'] < date('Y-m-d'); ?>
<div class="alert alert-<?= $overdue ? 'danger' : 'info' ?>" style="margin-top:22px;">
    <i class="fas fa-book"></i>
    <strong>Library:</strong> You have borrowed
    "<strong><?= htmlspecialchars($issuedBook['title']) ?></strong>".
    Due: <strong><?= formatDate($issuedBook['due_date']) ?></strong>
    <?= $overdue ? '– <strong>OVERDUE! Please return immediately to avoid fine.</strong>' : '' ?>
</div>
<?php endif; ?>

<?php require_once '../includes/student_footer.php'; ?>
