<?php
require_once '../includes/auth.php';
requireRole('teacher', '../index.php');

$pageTitle  = 'My Students';
$activeMenu = 'students';
$user       = currentUser();
$teacherId  = $user['ref_id'];

$filterClass = (int)($_GET['class_id'] ?? 0);

// My classes — from timetable, teacher_assignments, or class_teacher
$myClassRows = db()->fetchAll("
    SELECT DISTINCT c.id, c.class_name, c.section, (c.class_teacher_id=?) as is_ct
    FROM classes c WHERE c.class_teacher_id=?
    UNION
    SELECT DISTINCT c.id, c.class_name, c.section, (c.class_teacher_id=?) as is_ct
    FROM timetable tt JOIN classes c ON tt.class_id=c.id WHERE tt.teacher_id=?
    UNION
    SELECT DISTINCT c.id, c.class_name, c.section, (c.class_teacher_id=?) as is_ct
    FROM teacher_assignments ta JOIN classes c ON ta.class_id=c.id WHERE ta.teacher_id=?
    ORDER BY class_name, section
", 'iiiiii', [$teacherId,$teacherId,$teacherId,$teacherId,$teacherId,$teacherId]);

$myClasses = []; $seen = [];
foreach ($myClassRows as $c) {
    if (!in_array($c['id'], $seen)) { $myClasses[] = $c; $seen[] = $c['id']; }
}

$students = [];
if ($filterClass) {
    $students = db()->fetchAll("
        SELECT s.*, c.class_name, c.section,
               (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id AND a.status='Present') as present_days,
               (SELECT COUNT(*) FROM attendance a WHERE a.student_id=s.id) as total_days
        FROM students s
        LEFT JOIN classes c ON s.class_id=c.id
        WHERE s.class_id=? AND s.is_active=1
        ORDER BY s.first_name
    ", 'i', [$filterClass]);
}

require_once '../includes/teacher_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-graduate"></i> My Students</h3>
    </div>
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;margin-bottom:20px;flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0;min-width:180px;">
                <label>Select Class</label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($myClasses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $filterClass==$c['id']?'selected':'' ?>>
                        <?= htmlspecialchars($c['class_name'].' — '.$c['section']) ?>
                        <?php if((int)($c['is_ct']??0)===1): ?> (Class Teacher)<?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </form>

        <?php if ($filterClass && !empty($students)): ?>
        <div class="table-responsive">
            <table class="table">
                <thead><tr><th>#</th><th>Student</th><th>ID</th><th>Gender</th><th>Phone</th><th>Attendance</th></tr></thead>
                <tbody>
                    <?php foreach ($students as $i => $s):
                        $pct = ($s['total_days'] > 0) ? round(($s['present_days']/$s['total_days'])*100,1) : 0;
                    ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:10px;">
                                <div class="student-avatar"><?= strtoupper(substr($s['first_name'],0,1)) ?></div>
                                <div>
                                    <div style="font-weight:700;"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div>
                                    <div style="font-size:0.72rem;color:var(--text-secondary);"><?= formatDate($s['dob']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($s['student_id']) ?></span></td>
                        <td><?= htmlspecialchars($s['gender'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($s['phone'] ?? '–') ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;min-width:120px;">
                                <div style="flex:1;height:5px;background:var(--border);border-radius:3px;">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=75?'var(--success)':($pct>=50?'var(--warning)':'var(--danger)') ?>;border-radius:3px;"></div>
                                </div>
                                <span style="font-size:0.78rem;font-weight:700;"><?= $pct ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php elseif ($filterClass): ?>
        <div class="empty-state"><i class="fas fa-users"></i><h3>No Students</h3><p>No students in this class.</p></div>
        <?php else: ?>
        <div class="empty-state"><i class="fas fa-hand-point-up"></i><h3>Select a Class</h3></div>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/teacher_footer.php'; ?>