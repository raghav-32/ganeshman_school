<?php
require_once '../includes/auth.php';
requireRole('teacher', '../index.php');

$pageTitle  = 'Mark Attendance';
$activeMenu = 'attendance';
$user       = currentUser();
$teacherId  = $user['ref_id'];

// Get all classes: timetable + assignments + class_teacher
$classRows = db()->fetchAll("
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

$allClasses = []; $classIds = [];
foreach ($classRows as $c) {
    if (!in_array($c['id'], $classIds)) { $allClasses[] = $c; $classIds[] = $c['id']; }
}

$selectedClass = (int)($_GET['class_id'] ?? ($allClasses[0]['id'] ?? 0));
$selectedDate  = sanitize($_GET['date'] ?? date('Y-m-d'));

// Determine if current teacher is class teacher of selected class
$isClassTeacher = false;
foreach ($allClasses as $ac) {
    if ($ac['id'] == $selectedClass && (int)($ac['is_ct']??0) === 1) {
        $isClassTeacher = true; break;
    }
}

$students = [];
$existingAttendance = [];

if ($selectedClass) {
    $students = db()->fetchAll(
        "SELECT id, student_id, first_name, last_name FROM students WHERE class_id=? AND is_active=1 ORDER BY first_name",
        'i', [$selectedClass]
    );

    // Load existing for this date
    $existing = db()->fetchAll(
        "SELECT student_id, status FROM attendance WHERE class_id=? AND attendance_date=?",
        'is', [$selectedClass, $selectedDate]
    );
    foreach ($existing as $e) {
        $map = ['Present' => 'P', 'Absent' => 'A', 'Leave' => 'A'];
        $existingAttendance[$e['student_id']] = $map[$e['status']] ?? 'P';
    }
}

// Teacher header
require_once '../includes/teacher_header.php';
?>

<div class="card" style="margin-bottom:22px;">
    <div class="card-header">
        <h3><i class="fas fa-clipboard-check"></i> Mark Attendance</h3>
        <span class="badge badge-info"><?= date('D, d M Y', strtotime($selectedDate)) ?></span>
    </div>
    <div class="card-body">
        <!-- Filters -->
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;margin-bottom:24px;">
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:180px;">
                <label>Select Class</label>
                <select name="class_id" class="form-control" required>
                    <option value="">-- Choose Class --</option>
                    <?php foreach ($allClasses as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= $selectedClass == $c['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['class_name'].' '.$c['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group" style="margin-bottom:0;">
                <label>Date</label>
                <input type="date" name="date" class="form-control" value="<?= $selectedDate ?>" max="<?= date('Y-m-d') ?>">
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-filter"></i> Load
            </button>
        </form>

        <?php if ($selectedClass && !empty($students)): ?>
        <!-- Summary bar -->
        <div id="attendanceSummary" style="display:flex;gap:16px;margin-bottom:20px;padding:14px;background:var(--bg);border-radius:var(--radius-sm);flex-wrap:wrap;">
            <span style="font-size:0.85rem;font-weight:700;color:var(--text-secondary);">
                Total Students: <strong style="color:var(--text-primary);"><?= count($students) ?></strong>
            </span>
            <span id="presentCount" style="font-size:0.85rem;font-weight:700;color:var(--success);">Present: 0</span>
            <span id="absentCount"  style="font-size:0.85rem;font-weight:700;color:var(--danger);">Absent: 0</span>

        </div>

        <!-- Mark All buttons -->
        <div style="display:flex;gap:10px;margin-bottom:18px;">
            <button class="btn btn-success btn-sm" onclick="markAll('P')">
                <i class="fas fa-check-double"></i> All Present
            </button>
            <button class="btn btn-danger btn-sm" onclick="markAll('A')">
                <i class="fas fa-times-circle"></i> All Absent
            </button>
        </div>

        <!-- Student cards grid -->
        <div class="attendance-grid" id="attendanceGrid">
            <?php foreach ($students as $s): ?>
            <?php $current = $existingAttendance[$s['id']] ?? 'P'; ?>
            <div class="attendance-student-card">
                <div class="student-meta">
                    <div class="student-avatar">
                        <?= strtoupper(substr($s['first_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <h4><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></h4>
                        <p><?= htmlspecialchars($s['student_id']) ?></p>
                    </div>
                </div>
                <div class="attendance-toggles">
                    <?php if ($isClassTeacher): ?>
                    <button class="att-btn P <?= $current === 'P' ? 'active' : '' ?>" data-student="<?= $s['id'] ?>" data-status="P" title="Present">P</button>
                    <button class="att-btn A <?= $current === 'A' ? 'active' : '' ?>" data-student="<?= $s['id'] ?>" data-status="A" title="Absent">A</button>
                    <?php else: ?>
                    <span class="badge badge-<?= $current==='P'?'success':'danger' ?>"><?= $current==='P'?'Present':'Absent' ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php if ($isClassTeacher): ?>
        <div style="margin-top:24px;display:flex;justify-content:flex-end;gap:12px;">
            <button class="btn btn-outline" onclick="window.location.reload()">
                <i class="fas fa-redo"></i> Reset
            </button>
            <button class="btn btn-primary btn-lg" id="submitAttendance"
                onclick="submitAttendance()">
                <i class="fas fa-check"></i> Submit Attendance
            </button>
        </div>
        <?php else: ?>
        <div style="margin-top:16px;padding:12px 16px;background:#fef3c7;border:1px solid #fbbf24;border-radius:var(--radius-sm);font-size:0.84rem;color:#92400e;">
            <i class="fas fa-lock"></i> Only the <strong>class teacher</strong> can mark attendance for this class.
        </div>
        <?php endif; ?>

        <?php elseif ($selectedClass && empty($students)): ?>
        <div class="empty-state">
            <i class="fas fa-users"></i>
            <h3>No Students in This Class</h3>
            <p>Ask admin to assign students to this class.</p>
        </div>
        <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-clipboard-check"></i>
            <h3>Select a Class to Mark Attendance</h3>
            <p>Choose a class and date from the filters above.</p>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Attendance History -->
<?php if ($selectedClass): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Attendance Records</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php
        $history = db()->fetchAll(
            "SELECT a.attendance_date, a.status, s.first_name, s.last_name, s.student_id as sid
             FROM attendance a
             JOIN students s ON a.student_id = s.id
             WHERE a.class_id = ?
             ORDER BY a.attendance_date DESC, s.first_name
             LIMIT 30",
            'i', [$selectedClass]
        );
        ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Date</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-secondary);">No records yet.</td></tr>
                    <?php else: ?>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td><?= htmlspecialchars($h['first_name'].' '.$h['last_name']) ?></td>
                        <td><?= htmlspecialchars($h['sid']) ?></td>
                        <td><?= formatDate($h['attendance_date']) ?></td>
                        <td>
                            <?php $badgeMap = ['Present'=>'success','Absent'=>'danger','Leave'=>'info']; ?>
                            <span class="badge badge-<?= $badgeMap[$h['status']] ?? 'gray' ?>">
                                <?= $h['status'] ?>
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
<?php endif; ?>

<script>
const CLASS_ID = <?= $selectedClass ?: 0 ?>;
const DATE     = '<?= $selectedDate ?>';

function updateSummary() {
    let p = 0, a = 0;
    for (const [id, status] of Object.entries(Attendance.data)) {
        if (status === 'P') p++;
        else if (status === 'A') a++;
    }
    document.getElementById('presentCount').textContent = `Present: ${p}`;
    document.getElementById('absentCount').textContent  = `Absent: ${a}`;
}

// Override Attendance.init to also update summary
const _originalAttInit = Attendance.init.bind(Attendance);
Attendance.init = function() {
    _originalAttInit();
    // Re-attach with summary update
    document.querySelectorAll('.att-btn').forEach(btn => {
        btn.addEventListener('click', updateSummary);
    });
    updateSummary();
};

function markAll(status) {
    document.querySelectorAll(`.att-btn[data-status="${status}"]`).forEach(btn => btn.click());
}

async function submitAttendance() {
    await Attendance.submit(CLASS_ID, DATE);
}
</script>

<?php require_once '../includes/teacher_footer.php'; ?>