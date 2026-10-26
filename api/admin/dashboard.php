<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';

// Fetch stats
$totalStudents = db()->fetchOne("SELECT COUNT(*) as c FROM students WHERE is_active=1")['c'] ?? 0;
$totalTeachers = db()->fetchOne("SELECT COUNT(*) as c FROM teachers WHERE is_active=1")['c'] ?? 0;
$totalClasses  = db()->fetchOne("SELECT COUNT(*) as c FROM classes")['c'] ?? 0;

// Fee stats
$feePaid = db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as total FROM fee_payments")['total'] ?? 0;

// Today's attendance
$today = date('Y-m-d');
$todayPresent = db()->fetchOne("SELECT COUNT(*) as c FROM attendance WHERE attendance_date=? AND status='Present'", 's', [$today])['c'] ?? 0;
$todayAbsent  = db()->fetchOne("SELECT COUNT(*) as c FROM attendance WHERE attendance_date=? AND status='Absent'",  's', [$today])['c'] ?? 0;

// Books
$totalBooks = db()->fetchOne("SELECT COUNT(*) as c FROM books")['c'] ?? 0;

// Recent students
$recentStudents = db()->fetchAll("
    SELECT s.*, c.class_name, c.section
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    WHERE s.is_active = 1
    ORDER BY s.created_at DESC LIMIT 6
");

// Recent notices
$notices = db()->fetchAll("
    SELECT n.*, u.username as posted_by_name
    FROM notices n
    LEFT JOIN users u ON n.posted_by = u.id
    WHERE n.is_active=1
    ORDER BY n.created_at DESC LIMIT 4
");

// Attendance last 7 days for chart
$attendanceChart = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} days"));
    $cnt = db()->fetchOne("SELECT COUNT(*) as c FROM attendance WHERE attendance_date=? AND status='Present'", 's', [$d])['c'] ?? 0;
    $attendanceChart[] = ['date' => formatDateChart($d), 'count' => (int)$cnt];
}

include '../includes/admin_header.php';
?>

<!-- Stats -->
<style>
.stat-card-link{text-decoration:none;color:inherit;display:block;border-radius:var(--radius);transition:transform .15s,box-shadow .15s;height:100%;}
.stat-card-link:hover{transform:translateY(-2px);box-shadow:0 8px 24px rgba(0,0,0,.10);}
.stat-card-link:hover .stat-card{border-color:var(--primary-light,#93c5fd);}
.stats-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:24px;}
.stats-grid .stat-card{height:100%;margin:0;}
@media(max-width:768px){.stats-grid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:480px){.stats-grid{grid-template-columns:1fr;}}
</style>
<div class="stats-grid">
    <a href="students.php" class="stat-card-link">
        <div class="stat-card blue">
            <div class="stat-icon blue"><i class="fas fa-user-graduate"></i></div>
            <div class="stat-info"><h3><?= number_format($totalStudents) ?></h3><p>Total Students</p></div>
        </div>
    </a>
    <a href="teachers.php" class="stat-card-link">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fas fa-chalkboard-teacher"></i></div>
            <div class="stat-info"><h3><?= number_format($totalTeachers) ?></h3><p>Total Teachers</p></div>
        </div>
    </a>
    <a href="classes.php" class="stat-card-link">
        <div class="stat-card amber">
            <div class="stat-icon amber"><i class="fas fa-school"></i></div>
            <div class="stat-info"><h3><?= number_format($totalClasses) ?></h3><p>Classes</p></div>
        </div>
    </a>
    <a href="fees.php" class="stat-card-link">
        <div class="stat-card purple">
            <div class="stat-icon purple"><i class="fas fa-rupee-sign"></i></div>
            <div class="stat-info"><h3>Rs <?= number_format($feePaid, 0) ?></h3><p>Fee Collected</p></div>
        </div>
    </a>
    <a href="attendance.php" class="stat-card-link">
        <div class="stat-card green">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info"><h3><?= $todayPresent ?></h3><p>Present Today</p></div>
        </div>
    </a>
    <a href="attendance.php" class="stat-card-link">
        <div class="stat-card red">
            <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
            <div class="stat-info"><h3><?= $todayAbsent ?></h3><p>Absent Today</p></div>
        </div>
    </a>
</div>

<!-- Charts Row -->
<style>
.chart-card-body{padding:20px;min-height:260px;display:flex;align-items:stretch;}
.chart-canvas-wrap{position:relative;width:100%;height:220px;}
.chart-canvas-wrap canvas{position:absolute;top:0;left:0;width:100%!important;height:100%!important;}
.donut-wrap{display:flex;align-items:center;justify-content:center;gap:28px;width:100%;flex-wrap:wrap;}
.donut-canvas-box{position:relative;width:160px;height:160px;flex-shrink:0;}
.donut-canvas-box canvas{position:absolute;top:0;left:0;width:100%!important;height:100%!important;}
.donut-legend{display:flex;flex-direction:column;gap:12px;}
.donut-legend-item{display:flex;align-items:center;gap:10px;font-size:.84rem;}
.donut-legend-dot{width:13px;height:13px;border-radius:3px;flex-shrink:0;}
.donut-legend-label{color:var(--text-secondary);}
.donut-legend-val{font-weight:800;color:var(--text-primary);font-size:1rem;margin-left:4px;}
</style>
<div class="grid-2" style="margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-bar"></i> Attendance (Last 7 Days)</h3>
        </div>
        <div class="chart-card-body">
            <div class="chart-canvas-wrap">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-chart-pie"></i> Today's Summary</h3>
        </div>
        <div class="chart-card-body">
            <div class="donut-wrap">
                <div class="donut-canvas-box">
                    <canvas id="donutChart"></canvas>
                </div>
                <div class="donut-legend">
                    <div class="donut-legend-item">
                        <span class="donut-legend-dot" style="background:#10b981;"></span>
                        <span class="donut-legend-label">Present</span>
                        <span class="donut-legend-val"><?= $todayPresent ?></span>
                    </div>
                    <div class="donut-legend-item">
                        <span class="donut-legend-dot" style="background:#ef4444;"></span>
                        <span class="donut-legend-label">Absent</span>
                        <span class="donut-legend-val"><?= $todayAbsent ?></span>
                    </div>
                    <div class="donut-legend-item">
                        <span class="donut-legend-dot" style="background:#f59e0b;"></span>
                        <span class="donut-legend-label">Not Marked</span>
                        <span class="donut-legend-val"><?= max(0, $totalStudents - $todayPresent - $todayAbsent) ?></span>
                    </div>
                    <div class="donut-legend-item" style="margin-top:4px;border-top:1px solid var(--border);padding-top:10px;">
                        <span class="donut-legend-dot" style="background:var(--primary);border-radius:50%;"></span>
                        <span class="donut-legend-label">Total</span>
                        <span class="donut-legend-val"><?= $totalStudents ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Students & Notices -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-user-graduate"></i> Recent Students</h3>
            <a href="students.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Class</th>
                            <th>ID</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentStudents)): ?>
                        <tr><td colspan="4">
                            <div class="empty-state" style="padding:24px;">
                                <i class="fas fa-user-graduate"></i>
                                <p>No students added yet.</p>
                            </div>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($recentStudents as $s): ?>
                        <tr>
                            <td>
                                <div style="display:flex;align-items:center;gap:10px;">
                                    <?php
                                    $sPhoto = $s['photo'] ?? '';
                                    $sPhotoPath = '../assets/uploads/students/' . $sPhoto;
                                    $hasPhoto = $sPhoto && $sPhoto !== 'default.png' && $sPhoto !== '0' && file_exists($sPhotoPath);
                                    ?>
                                    <div class="student-avatar" style="width:36px;height:36px;font-size:0.75rem;flex-shrink:0;overflow:hidden;padding:0;">
                                        <?php if ($hasPhoto): ?>
                                        <img src="<?= htmlspecialchars($sPhotoPath) ?>" alt=""
                                             style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                                        <?php else: ?>
                                        <span style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;">
                                            <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <div style="font-weight:700;font-size:0.87rem;"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                        <div style="font-size:0.72rem;color:var(--text-secondary);"><?= htmlspecialchars($s['gender'] ?? '') ?></div>
                                    </div>
                                </div>
                            </td>
                            <td><?= htmlspecialchars(($s['class_name'] ?? '-').' '.($s['section'] ?? '')) ?></td>
                            <td><span class="badge badge-primary"><?= htmlspecialchars($s['student_id']) ?></span></td>
                            <td><span class="badge badge-success">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-bullhorn"></i> Recent Notices</h3>
            <a href="notices.php" class="btn btn-outline btn-sm">Manage</a>
        </div>
        <div class="card-body">
            <?php if (empty($notices)): ?>
            <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <p>No notices posted.</p>
            </div>
            <?php else: ?>
            <?php foreach ($notices as $notice): ?>
            <div class="notice-item">
                <h4><?= htmlspecialchars($notice['title']) ?></h4>
                <p><?= htmlspecialchars(substr($notice['content'], 0, 120)) ?>...</p>
                <div class="notice-meta">
                    <span><i class="fas fa-calendar"></i> <?= formatDate($notice['created_at']) ?></span>
                    <span><i class="fas fa-users"></i> <?= ucfirst($notice['target_role']) ?></span>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const labels  = <?= json_encode(array_column($attendanceChart, 'date')) ?>;
const counts  = <?= json_encode(array_column($attendanceChart, 'count')) ?>;
const present  = <?= $todayPresent ?>;
const absent   = <?= $todayAbsent ?>;
const unmarked = <?= max(0, $totalStudents - $todayPresent - $todayAbsent) ?>;

function initCharts() {
    // Size canvases to their wrapper's actual pixel dimensions before drawing
    function sizeCanvas(id, wrapSel) {
        const canvas = document.getElementById(id);
        if (!canvas) return false;
        const wrap = canvas.closest(wrapSel);
        if (wrap) {
            canvas.width  = wrap.offsetWidth  || 400;
            canvas.height = wrap.offsetHeight || 220;
        }
        return true;
    }

    if (sizeCanvas('attendanceChart', '.chart-canvas-wrap')) {
        drawBarChart('attendanceChart', labels, counts, '#2563a8');
    }
    if (sizeCanvas('donutChart', '.donut-canvas-box')) {
        drawDonut('donutChart',
            [present || 1, absent || 0, unmarked || 0],
            ['#10b981', '#ef4444', '#f59e0b'],
            ['Present', 'Absent', 'Unmarked']
        );
    }
}

// Run after layout is complete
if (document.readyState === 'complete') {
    initCharts();
} else {
    window.addEventListener('load', initCharts);
}
// Also run after a tick in case AJAX loaded this page
setTimeout(initCharts, 80);
</script>

<?php include '../includes/admin_footer.php'; ?>