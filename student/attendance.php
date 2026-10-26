<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'My Attendance';
$activeMenu = 'attendance';
$user       = currentUser();
$studentId  = $user['ref_id'];

$filterMonth = sanitize($_GET['month'] ?? date('Y-m'));

// Overall summary
$summary = db()->fetchOne("
    SELECT COUNT(*) as total,
           SUM(status='Present') as present,
           SUM(status='Absent') as absent,
           0 as late
    FROM attendance WHERE student_id=?
", 'i', [$studentId]);
$pct = ($summary['total'] > 0) ? round(($summary['present']/$summary['total'])*100, 1) : 0;

// Monthly records
$records = db()->fetchAll("
    SELECT * FROM attendance
    WHERE student_id = ? AND DATE_FORMAT(attendance_date, '%Y-%m') = ?
    ORDER BY attendance_date DESC
", 'is', [$studentId, $filterMonth]);

require_once '../includes/student_header.php';
?>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-calendar"></i></div>
        <div class="stat-info"><h3><?= $summary['total'] ?></h3><p>Total Days</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><h3><?= $summary['present'] ?? 0 ?></h3><p>Present</p></div>
    </div>
    <div class="stat-card red">
        <div class="stat-icon red"><i class="fas fa-times-circle"></i></div>
        <div class="stat-info"><h3><?= $summary['absent'] ?? 0 ?></h3><p>Absent</p></div>
    </div>

</div>

<!-- Attendance Percentage Bar -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-body">
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
            <span style="font-weight:800;font-size:0.95rem;">Overall Attendance Rate</span>
            <span style="font-size:1.4rem;font-weight:900;color:<?= $pct>=75?'var(--success)':($pct>=50?'var(--warning)':'var(--danger)') ?>">
                <?= $pct ?>%
            </span>
        </div>
        <div style="height:16px;background:var(--border);border-radius:var(--radius-full);overflow:hidden;">
            <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=75?'linear-gradient(90deg,#34d399,var(--success))':($pct>=50?'linear-gradient(90deg,#fbbf24,var(--warning))':'linear-gradient(90deg,#f87171,var(--danger))') ?>;border-radius:var(--radius-full);transition:width 1s ease;"></div>
        </div>
        <?php if ($pct < 75): ?>
        <div class="alert alert-warning" style="margin-top:14px;margin-bottom:0;">
            <i class="fas fa-exclamation-triangle"></i>
            Your attendance is below 75%. Please improve attendance to avoid issues.
        </div>
        <?php else: ?>
        <p style="color:var(--success);font-weight:600;font-size:0.83rem;margin-top:10px;">
            <i class="fas fa-check-circle"></i> Great! Your attendance meets the required 75% threshold.
        </p>
        <?php endif; ?>
    </div>
</div>

<!-- Monthly Filter & Records -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-check"></i> Monthly Records</h3>
        <form method="GET" style="display:flex;gap:8px;align-items:center;">
            <input type="month" name="month" class="form-control" value="<?= $filterMonth ?>" style="width:auto;">
            <button type="submit" class="btn btn-primary btn-sm">Go</button>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Day</th><th>Status</th><th>Remarks</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                    <tr><td colspan="4">
                        <div class="empty-state" style="padding:24px;">
                            <i class="fas fa-calendar-times"></i>
                            <p>No attendance records for <?= NepaliDate::format($filterMonth.'-01') ?>.</p>
                        </div>
                    </td></tr>
                    <?php else: ?>
                    <?php $badgeMap = ['Present'=>'success','Absent'=>'danger','Leave'=>'info']; ?>
                    <?php foreach ($records as $r): ?>
                    <tr>
                        <td><strong><?= formatDate($r['attendance_date']) ?></strong></td>
                        <td><?= NepaliDate::dayName($r['attendance_date']) ?></td>
                        <td><span class="badge badge-<?= $badgeMap[$r['status']] ?? 'gray' ?>"><?= $r['status'] ?></span></td>
                        <td style="color:var(--text-secondary);font-size:0.83rem;"><?= htmlspecialchars($r['remarks'] ?? '–') ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../includes/student_footer.php'; ?>