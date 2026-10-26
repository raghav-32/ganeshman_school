<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Attendance Monitor';
$activeMenu = 'attendance';

$filterClass = (int)($_GET['class_id'] ?? 0);
$filterDate  = sanitize($_GET['date'] ?? '');

// Accept BS year/month directly from GET, convert to AD for DB query
$bsMonths = ['','Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'];

// Get today in BS to use as default
$todayBS    = NepaliDate::adToBs(date('Y-m-d'));
$bsYear     = (int)($_GET['bsy'] ?? $todayBS[0]);
$bsMonth    = (int)($_GET['bsm'] ?? $todayBS[1]);

// Convert selected BS month start to AD for DB filtering
$adMonthStart = NepaliDate::bsToAd($bsYear, $bsMonth, 1);
$filterMonth  = date('Y-m', strtotime($adMonthStart));

$classes = db()->fetchAll("SELECT * FROM classes ORDER BY class_name, section");

// Summary by date — fetch range covering entire BS month
// BS month spans ~30 days; get 35 days from BS month start to cover full month
$adStart = $adMonthStart;
$adEnd   = date('Y-m-d', strtotime($adMonthStart . ' +35 days'));

$summary = db()->fetchAll("
    SELECT a.attendance_date,
           COUNT(*) as total,
           SUM(a.status='Present') as present,
           SUM(a.status='Absent')  as absent
    FROM attendance a
    WHERE a.attendance_date >= ? AND a.attendance_date <= ?
    " . ($filterClass ? "AND a.class_id = $filterClass" : '') . "
    GROUP BY a.attendance_date
    ORDER BY a.attendance_date DESC
", 'ss', [$adStart, $adEnd]);

// Filter summary to only dates actually in selected BS month
$filteredSummary = [];
foreach ($summary as $row) {
    $bs = NepaliDate::adToBs($row['attendance_date']);
    if ($bs[0] === $bsYear && $bs[1] === $bsMonth) {
        $filteredSummary[] = $row;
    }
}

// Detailed records for single date click
$records = [];
if ($filterDate) {
    $sql = "SELECT a.*, s.first_name, s.last_name, s.student_id as sid, c.class_name, c.section
            FROM attendance a
            JOIN students s ON a.student_id = s.id
            JOIN classes c ON a.class_id = c.id
            WHERE a.attendance_date = ?";
    $params = [$filterDate]; $types = 's';
    if ($filterClass) { $sql .= " AND a.class_id = ?"; $params[] = $filterClass; $types .= 'i'; }
    $sql .= " ORDER BY s.first_name";
    $records = db()->fetchAll($sql, $types, $params);
}

$bsYears = range(2075, 2090);

include '../includes/admin_header.php';
?>

<style>
.bs-picker{display:flex;gap:8px;align-items:center;}
.bs-picker select{padding:9px 12px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.84rem;font-family:inherit;outline:none;background:#fff;cursor:pointer;transition:border-color .2s;}
.bs-picker select:focus{border-color:var(--primary);}
</style>

<!-- Filters -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-body">
        <form method="GET" id="attFilterForm" style="display:flex;gap:14px;align-items:flex-end;flex-wrap:wrap;">
            <!-- Class -->
            <div class="form-group" style="margin-bottom:0;min-width:160px;">
                <label>Class</label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                    <option value="">All Classes</option>
                    <?php foreach ($classes as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>>
                        <?= htmlspecialchars($c['class_name'].' '.$c['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- BS Month — post directly as bsy/bsm, no JS conversion needed -->
            <div class="form-group" style="margin-bottom:0;">
                <label>Month (BS)</label>
                <div class="bs-picker">
                    <select name="bsm" onchange="this.form.submit()">
                        <?php foreach ($bsMonths as $i => $mn): if(!$i) continue; ?>
                        <option value="<?= $i ?>" <?= $bsMonth===$i?'selected':'' ?>><?= $mn ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select name="bsy" onchange="this.form.submit()">
                        <?php foreach ($bsYears as $yr): ?>
                        <option value="<?= $yr ?>" <?= $bsYear===$yr?'selected':'' ?>><?= $yr ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <?php if ($filterDate): ?>
            <input type="hidden" name="date" value="<?= $filterDate ?>">
            <?php endif; ?>

            <a href="attendance" class="btn btn-outline" style="margin-bottom:0;">Reset</a>
        </form>
    </div>
</div>

<!-- Summary Card -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-header">
        <h3><i class="fas fa-calendar-check"></i>
            Daily Summary — <?= $bsMonths[$bsMonth] ?> <?= $bsYear ?>
        </h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Date</th><th>Total</th><th>Present</th><th>Absent</th><th>Rate</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($filteredSummary)): ?>
                    <tr><td colspan="5" style="text-align:center;padding:24px;color:var(--text-secondary);">No attendance data for this period.</td></tr>
                    <?php else: ?>
                    <?php foreach ($filteredSummary as $s):
                        $rate = $s['total'] > 0 ? round(($s['present']/$s['total'])*100,1) : 0;
                    ?>
                    <tr>
                        <td>
                            <a href="?bsy=<?= $bsYear ?>&bsm=<?= $bsMonth ?>&class_id=<?= $filterClass ?>&date=<?= $s['attendance_date'] ?>"
                               style="font-weight:700;color:var(--primary-light);">
                                <?= formatDate($s['attendance_date']) ?>
                            </a>
                        </td>
                        <td><?= $s['total'] ?></td>
                        <td><span style="color:var(--success);font-weight:700;"><?= $s['present'] ?></span></td>
                        <td><span style="color:var(--danger);font-weight:700;"><?= $s['absent'] ?></span></td>
                        <td>
                            <span style="font-weight:800;color:<?= $rate>=75?'var(--success)':($rate>=50?'var(--warning)':'var(--danger)') ?>">
                                <?= $rate ?>%
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Detailed records for selected date -->
<?php if ($filterDate && !empty($records)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> Records — <?= formatDate($filterDate) ?></h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>Student</th><th>Class</th><th>Status</th></tr></thead>
                <tbody>
                    <?php foreach ($records as $r): ?>
                    <?php $badgeMap = ['Present'=>'success','Absent'=>'danger','Leave'=>'info']; ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($r['first_name'].' '.$r['last_name']) ?></strong><br>
                            <small style="color:var(--text-secondary);"><?= htmlspecialchars($r['sid']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['class_name'].' '.$r['section']) ?></td>
                        <td><span class="badge badge-<?= $badgeMap[$r['status']] ?? 'gray' ?>"><?= $r['status'] ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php elseif ($filterDate): ?>
<div class="card"><div class="card-body"><div class="empty-state">
    <i class="fas fa-clipboard-check"></i>
    <h3>No Records for <?= formatDate($filterDate) ?></h3>
</div></div></div>
<?php endif; ?>

<?php include '../includes/admin_footer.php'; ?>