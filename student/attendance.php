<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'My Attendance';
$activeMenu = 'attendance';
$user       = currentUser();
$studentId  = $user['ref_id'];

$bsMonthNames = ['','Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'];
$todayBs = NepaliDate::adToBs(date('Y-m-d'));
$bsYear  = max(NepaliDate::startYear(), (int)($_GET['bsy'] ?? $todayBs[0]));
$bsMonth = min(12, max(1, (int)($_GET['bsm'] ?? $todayBs[1])));
$bsDays  = NepaliDate::daysInMonth($bsYear, $bsMonth);
$adFrom  = NepaliDate::bsToAd($bsYear, $bsMonth, 1);
$adTo    = NepaliDate::bsToAd($bsYear, $bsMonth, $bsDays);

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
    WHERE student_id = ? AND attendance_date BETWEEN ? AND ?
    ORDER BY attendance_date DESC
", 'iss', [$studentId, $adFrom, $adTo]);
$byDay = [];
foreach ($records as $r) { $b = NepaliDate::adToBs($r['attendance_date']); $byDay[$b[2]] = $r['status']; }
$prevM = $bsMonth - 1; $prevY = $bsYear; if ($prevM < 1)  { $prevM = 12; $prevY--; }
$nextM = $bsMonth + 1; $nextY = $bsYear; if ($nextM > 12) { $nextM = 1;  $nextY++; }
$lead  = (int)date('w', strtotime($adFrom));   // Sunday = 0

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
        <form method="GET" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
            <a class="btn btn-sm btn-outline" href="?bsy=<?= $prevY ?>&bsm=<?= $prevM ?>" title="Previous month">&#8249;</a>
            <select name="bsm" class="form-control" style="width:auto;">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $m === $bsMonth ? 'selected' : '' ?>><?= $bsMonthNames[$m] ?></option>
                <?php endfor; ?>
            </select>
            <select name="bsy" class="form-control" style="width:auto;">
                <?php for ($y = $todayBs[0]; $y >= max(NepaliDate::startYear(), $todayBs[0] - 12); $y--): ?>
                <option value="<?= $y ?>" <?= $y === $bsYear ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
            <button type="submit" class="btn btn-primary btn-sm">Go</button>
            <a class="btn btn-sm btn-outline" href="?bsy=<?= $nextY ?>&bsm=<?= $nextM ?>" title="Next month">&#8250;</a>
        </form>
    </div>
    <div class="card-body" style="padding:16px 20px;">
        <style>
        .att-cal{display:grid;grid-template-columns:repeat(7,1fr);gap:6px;max-width:420px;margin:0 auto;text-align:center;}
        .att-cal .h{font-size:.72rem;font-weight:700;color:var(--text-secondary);padding:4px 0;}
        .att-cal .d{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border-radius:50%;font-size:.85rem;color:var(--text-primary);}
        .att-cal .d.Present{background:#dcfce7;color:#166534;font-weight:700;}
        .att-cal .d.Absent{background:#fee2e2;color:#991b1b;font-weight:700;}
        .att-cal .d.Late{background:#fef9c3;color:#854d0e;font-weight:700;}
        .att-cal .d.Leave{background:#dbeafe;color:#1e40af;font-weight:700;}
        .att-cal .d.today{outline:2px solid var(--primary-light,#2563a8);}
        </style>
        <div style="text-align:center;font-weight:700;margin-bottom:10px;"><?= $bsMonthNames[$bsMonth] ?> <?= $bsYear ?></div>
        <div class="att-cal">
            <?php foreach (['S','M','T','W','T','F','S'] as $h): ?><div class="h"><?= $h ?></div><?php endforeach; ?>
            <?php for ($i = 0; $i < $lead; $i++): ?><div></div><?php endfor; ?>
            <?php for ($d = 1; $d <= $bsDays; $d++):
                $isToday = ($bsYear === $todayBs[0] && $bsMonth === $todayBs[1] && $d === $todayBs[2]); ?>
            <div class="d <?= $byDay[$d] ?? '' ?> <?= $isToday ? 'today' : '' ?>"><?= $d ?></div>
            <?php endfor; ?>
        </div>
    </div>
    <div class="card-body" style="padding:0;border-top:1px solid var(--border,#e5e7eb);">
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
                            <p>No attendance records for <?= $bsMonthNames[$bsMonth] . ' ' . $bsYear ?>.</p>
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