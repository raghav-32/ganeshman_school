<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Timetable Management';
$activeMenu = 'timetable';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $classId   = (int)($_POST['class_id']   ?? 0);
        $teacherId = (int)($_POST['teacher_id'] ?? 0);
        $subjectId = (int)($_POST['subject_id'] ?? 0);
        $days      = $_POST['day_of_week'] ?? [];
        if (is_array($days)) $days = implode(',', array_map('trim', $days));
        $startTime = sanitize($_POST['start_time']   ?? '');
        $endTime   = sanitize($_POST['end_time']     ?? '');
        $room      = sanitize($_POST['room_number']  ?? '');
        $filterClass = (int)($_POST['filter_class']  ?? 0);

        if ($action === 'add') {
            // Insert one row per day selected (avoids ENUM column limitation)
            $dayList = array_filter(array_map('trim', is_array($_POST['day_of_week'] ?? []) ? $_POST['day_of_week'] : explode(',', $days)));
            $added   = 0;
            $skipped = 0;
            foreach ($dayList as $singleDay) {
                $conflict = db()->fetchOne(
                    "SELECT id FROM timetable WHERE teacher_id=? AND day_of_week=? AND ((start_time<=? AND end_time>?) OR (start_time<? AND end_time>=?))",
                    'isssss', [$teacherId, $singleDay, $startTime, $startTime, $endTime, $endTime]
                );
                if ($conflict) {
                    $skipped++;
                } else {
                    db()->execute(
                        "INSERT INTO timetable (class_id,teacher_id,subject_id,day_of_week,start_time,end_time,room_number) VALUES (?,?,?,?,?,?,?)",
                        'iiissss', [$classId, $teacherId, $subjectId, $singleDay, $startTime, $endTime, $room]
                    );
                    $added++;
                }
            }
            if ($added > 0 && $skipped === 0) {
                setFlash('success', "Period added for $added day(s) successfully.");
            } elseif ($added > 0 && $skipped > 0) {
                setFlash('warning', "Added $added day(s). Skipped $skipped day(s) due to teacher conflict.");
            } else {
                setFlash('danger', 'Conflict: Teacher already has a class at this time on all selected days.');
            }
        } else {
            $id         = (int)($_POST['id'] ?? 0);
            $singleDay  = is_array($_POST['day_of_week'] ?? '') ? (trim($_POST['day_of_week'][0]) ?? '') : trim($days);
            db()->execute(
                "UPDATE timetable SET class_id=?,teacher_id=?,subject_id=?,day_of_week=?,start_time=?,end_time=?,room_number=? WHERE id=?",
                'iiissssi', [$classId, $teacherId, $subjectId, $singleDay, $startTime, $endTime, $room, $id]
            );
            setFlash('success', 'Period updated.');
        }
        redirect("timetable.php?class_id=$filterClass");
    }

    if ($action === 'delete') {
        $id          = (int)($_POST['id']          ?? 0);
        $filterClass = (int)($_POST['filter_class'] ?? 0);
        db()->execute("DELETE FROM timetable WHERE id=?", 'i', [$id]);
        setFlash('success', 'Period removed.');
        redirect("timetable.php?class_id=$filterClass");
    }
}

// Filters
$filterFaculty = sanitize($_GET['faculty']   ?? '');
$filterClass   = (int)($_GET['class_id']     ?? 0);
$days          = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];

// All faculties (distinct class_name)
$allFaculties = db()->fetchAll("SELECT DISTINCT class_name FROM classes ORDER BY class_name");

// Semesters for selected faculty
$semesterOptions = [];
if ($filterFaculty) {
    $semesterOptions = db()->fetchAll(
        "SELECT * FROM classes WHERE class_name=? ORDER BY section",
        's', [$filterFaculty]
    );
}

// All classes for modals
$allClasses = db()->fetchAll("SELECT * FROM classes ORDER BY class_name, section");

// Group classes by faculty for modal dropdown
$classesByFaculty = [];
foreach ($allClasses as $c) {
    $classesByFaculty[$c['class_name']][] = $c;
}

// Selected class info
$selectedClass = null;
if ($filterClass) {
    $selectedClass = db()->fetchOne("SELECT * FROM classes WHERE id=?", 'i', [$filterClass]);
    // Auto-set faculty filter from selected class
    if ($selectedClass && !$filterFaculty) {
        $filterFaculty = $selectedClass['class_name'];
        $semesterOptions = db()->fetchAll(
            "SELECT * FROM classes WHERE class_name=? ORDER BY section",
            's', [$filterFaculty]
        );
    }
}

$teachers = db()->fetchAll("SELECT id, first_name, last_name FROM teachers WHERE is_active=1 ORDER BY first_name");

// Subjects for selected class only
$subjects = [];
if ($filterClass) {
    $subjects = db()->fetchAll(
        "SELECT * FROM subjects WHERE class_id=? ORDER BY subject_name",
        'i', [$filterClass]
    );
}

// All subjects for edit modal fallback
$allSubjects = db()->fetchAll("SELECT s.*, c.class_name, c.section FROM subjects s LEFT JOIN classes c ON s.class_id=c.id ORDER BY c.class_name, s.subject_name");

// Timetable data
$timetableData = [];
if ($filterClass) {
    $rows = db()->fetchAll("
        SELECT tt.*, t.first_name, t.last_name, sub.subject_name, sub.subject_code
        FROM timetable tt
        JOIN teachers t   ON tt.teacher_id = t.id
        JOIN subjects sub ON tt.subject_id  = sub.id
        WHERE tt.class_id = ?
        ORDER BY tt.start_time
    ", 'i', [$filterClass]);

    foreach ($rows as $r) {
        $timetableData[trim($r['day_of_week'])][] = $r;
    }
    foreach ($timetableData as &$dayRows) {
        usort($dayRows, fn($a, $b) => strcmp($a['start_time'], $b['start_time']));
    }
    unset($dayRows);
}

include '../includes/admin_header.php';
?>

<!-- STEP SELECTOR -->
<div class="card" style="margin-bottom:18px;">
    <div class="card-body" style="padding:20px 24px;">
        <div style="display:flex;align-items:flex-end;gap:14px;flex-wrap:wrap;">

            <!-- Faculty -->
            <div class="form-group" style="margin-bottom:0;min-width:180px;flex:1;">
                <label>Faculty</label>
                <select id="ttFaculty" class="form-control" onchange="applyTTFilter(true)">
                    <option value="">All Faculties</option>
                    <?php foreach ($allFaculties as $f): ?>
                    <option value="<?= htmlspecialchars($f['class_name']) ?>"
                            <?= $filterFaculty===$f['class_name']?'selected':'' ?>>
                        <?= htmlspecialchars($f['class_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Semester -->
            <div class="form-group" style="margin-bottom:0;min-width:160px;flex:1;">
                <label>Semester</label>
                <select id="ttClass" class="form-control" onchange="applyTTFilter(false)">
                    <option value="">All Semesters</option>
                    <?php
                    $semList = $filterFaculty ? $semesterOptions : $allClasses;
                    foreach ($semList as $sc):
                    ?>
                    <option value="<?= $sc['id'] ?>" <?= $filterClass==$sc['id']?'selected':'' ?>>
                        <?= $filterFaculty ? htmlspecialchars($sc['section']) : htmlspecialchars($sc['class_name'].' — '.$sc['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($filterFaculty || $filterClass): ?>
            <a href="timetable.php" class="btn btn-outline btn-sm" style="margin-bottom:0;">
                <i class="fas fa-times"></i> Reset
            </a>
            <?php endif; ?>

        </div>
    </div>
</div>

<!-- TIMETABLE CARD -->
<div class="card">
    <div class="card-header">
        <h3>
            <i class="fas fa-calendar-alt"></i>
            <?php if ($selectedClass): ?>
                Timetable — <?= htmlspecialchars($selectedClass['class_name'].' — '.$selectedClass['section']) ?>
            <?php elseif ($filterFaculty): ?>
                Timetable — <?= htmlspecialchars($filterFaculty) ?>
            <?php else: ?>
                Timetable
            <?php endif; ?>
        </h3>
        <?php if ($filterClass): ?>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addPeriodModal')">
            <i class="fas fa-plus"></i> Add Period
        </button>
        <?php endif; ?>
    </div>

    <div class="card-body" <?= $filterClass ? 'style="padding:0;"' : '' ?>>

        <?php if (!$filterFaculty): ?>
        <!-- No faculty selected -->
        <div class="empty-state">
            <i class="fas fa-university"></i>
            <h3>Select a Faculty</h3>
            <p>Choose a faculty from Step 1 to get started.</p>
        </div>

        <?php elseif ($filterFaculty && !$filterClass): ?>
        <!-- Faculty selected but no semester -->
        <div class="empty-state">
            <i class="fas fa-layer-group"></i>
            <h3>Select a Semester</h3>
            <p>Now choose a semester from Step 2 to view its timetable.</p>
        </div>

        <?php else: ?>
        <!-- Show timetable -->
        <?php if (empty($subjects)): ?>
        <div style="padding:20px;">
            <div class="alert" style="background:#fef9ec;border:1px solid #f5c518;border-radius:var(--radius-sm);padding:14px 18px;font-size:0.85rem;color:#7a6000;">
                <i class="fas fa-exclamation-triangle" style="margin-right:8px;"></i>
                No subjects found for this semester. <a href="subjects.php">Add subjects first</a> before building the timetable.
            </div>
        </div>
        <?php endif; ?>

        <div style="overflow-x:auto;">
            <table class="table" style="min-width:700px;">
                <thead>
                    <tr>
                        <th style="width:110px;">Day</th>
                        <th>Scheduled Periods</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($days as $day): ?>
                    <?php
                        $todayName = date('l');
                        $isToday   = $todayName === $day;
                    ?>
                    <tr style="<?= $isToday ? 'background:rgba(var(--primary-rgb),0.03);' : '' ?>">
                        <td style="vertical-align:top;padding-top:14px;">
                            <strong style="color:<?= $isToday ? 'var(--primary)' : 'inherit' ?>">
                                <?= $day ?>
                            </strong>
                            <?php if ($isToday): ?>
                            <br><span class="badge badge-primary" style="font-size:0.62rem;margin-top:4px;">Today</span>
                            <?php endif; ?>
                        </td>
                        <td style="padding:8px 12px;">
                            <?php if (!empty($timetableData[$day])): ?>
                            <div style="display:flex;gap:10px;flex-wrap:wrap;padding:4px 0;">
                                <?php foreach ($timetableData[$day] as $p): ?>
                                <div style="background:var(--bg);border:1px solid var(--border);border-radius:var(--radius-sm);padding:10px 14px;min-width:160px;max-width:200px;position:relative;border-left:3px solid var(--primary);">
                                    <div style="font-weight:800;font-size:0.85rem;color:var(--primary);margin-bottom:3px;">
                                        <?= htmlspecialchars($p['subject_name']) ?>
                                        <?php if ($p['subject_code']): ?>
                                        <span style="font-weight:400;font-size:0.7rem;color:var(--text-light);"> (<?= htmlspecialchars($p['subject_code']) ?>)</span>
                                        <?php endif; ?>
                                    </div>
                                    <div style="font-size:0.78rem;color:var(--text-secondary);">
                                        <i class="fas fa-chalkboard-teacher" style="font-size:0.7rem;margin-right:3px;"></i>
                                        <?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?>
                                    </div>
                                    <div style="font-size:0.73rem;color:var(--text-light);margin-top:4px;">
                                        <i class="fas fa-clock" style="font-size:0.68rem;margin-right:3px;"></i>
                                        <?= substr($p['start_time'],0,5) ?> – <?= substr($p['end_time'],0,5) ?>
                                        <?php if ($p['room_number']): ?>
                                        &nbsp;·&nbsp;<i class="fas fa-door-open" style="font-size:0.68rem;"></i> <?= htmlspecialchars($p['room_number']) ?>
                                        <?php endif; ?>
                                    </div>
                                    <!-- Edit / Delete -->
                                    <div style="position:absolute;top:6px;right:6px;display:flex;gap:4px;">
                                        <button onclick="editPeriod(<?= htmlspecialchars(json_encode($p)) ?>)"
                                                style="background:none;border:none;cursor:pointer;color:var(--info);font-size:0.78rem;padding:2px 4px;"
                                                title="Edit">
                                            <i class="fas fa-pencil-alt"></i>
                                        </button>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                                            <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
                                            <button type="submit"
                                                    style="background:none;border:none;cursor:pointer;color:var(--danger);font-size:0.78rem;padding:2px 4px;"
                                                    onclick="return confirm('Remove this period?')"
                                                    title="Delete">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--text-light);font-size:0.83rem;font-style:italic;">No periods scheduled</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- ADD PERIOD MODAL -->
<div class="modal-overlay" id="addPeriodModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Period</h3>
            <button class="modal-close" onclick="Modal.close('addPeriodModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
            <div class="modal-body">

                <!-- Class (pre-selected and locked if class already chosen) -->
                <div class="form-group">
                    <label>Faculty / Semester <span style="color:red;">*</span></label>
                    <?php if ($filterClass && $selectedClass): ?>
                    <input type="hidden" name="class_id" value="<?= $filterClass ?>">
                    <div class="form-control" style="background:var(--bg);color:var(--text-secondary);cursor:default;">
                        <?= htmlspecialchars($selectedClass['class_name'].' — '.$selectedClass['section']) ?>
                    </div>
                    <?php else: ?>
                    <select name="class_id" class="form-control" required>
                        <option value="">— Select —</option>
                        <?php foreach ($classesByFaculty as $facName => $grpClasses): ?>
                        <optgroup label="<?= htmlspecialchars($facName) ?>">
                            <?php foreach ($grpClasses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['section']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <label>Subject <span style="color:red;">*</span></label>
                    <select name="subject_id" class="form-control" required>
                        <option value="">— Select Subject —</option>
                        <?php if (!empty($subjects)): ?>
                            <?php foreach ($subjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['subject_name']) ?><?= $s['subject_code'] ? ' ('.$s['subject_code'].')' : '' ?></option>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <?php foreach ($allSubjects as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars(($s['class_name']??'').' — '.$s['subject_name']) ?></option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Teacher <span style="color:red;">*</span></label>
                    <select name="teacher_id" class="form-control" required>
                        <option value="">— Select Teacher —</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Days <span style="color:red;">*</span> <span style="color:var(--text-light);font-weight:400;font-size:0.8rem;">(select one or more)</span></label>
                    <div class="day-checkboxes">
                        <?php foreach ($days as $d): ?>
                        <label class="day-check-label" onclick="this.classList.toggle('checked')">
                            <input type="checkbox" name="day_of_week[]" value="<?= $d ?>">
                            <?= $d ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Start Time <span style="color:red;">*</span></label>
                        <input type="time" name="start_time" class="form-control" value="10:00" required>
                    </div>
                    <div class="form-group">
                        <label>End Time <span style="color:red;">*</span></label>
                        <input type="time" name="end_time" class="form-control" value="10:45" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" class="form-control" placeholder="e.g. 101 / Lab 1">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addPeriodModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Period</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT PERIOD MODAL -->
<div class="modal-overlay" id="editPeriodModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Period</h3>
            <button class="modal-close" onclick="Modal.close('editPeriodModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="ep_id">
            <input type="hidden" name="filter_class" value="<?= $filterClass ?>">
            <div class="modal-body">

                <div class="form-group">
                    <label>Faculty / Semester</label>
                    <select name="class_id" id="ep_class" class="form-control">
                        <?php foreach ($classesByFaculty as $facName => $grpClasses): ?>
                        <optgroup label="<?= htmlspecialchars($facName) ?>">
                            <?php foreach ($grpClasses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' — '.$c['section']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Subject</label>
                    <select name="subject_id" id="ep_subject" class="form-control">
                        <?php foreach ($allSubjects as $s): ?>
                        <option value="<?= $s['id'] ?>"><?= htmlspecialchars(($s['class_name']??'General').' — '.$s['subject_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Teacher</label>
                    <select name="teacher_id" id="ep_teacher" class="form-control">
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Day</label>
                    <select name="day_of_week[]" id="ep_day_select" class="form-control">
                        <?php foreach ($days as $d): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Start Time</label>
                        <input type="time" name="start_time" id="ep_start" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>End Time</label>
                        <input type="time" name="end_time" id="ep_end" class="form-control">
                    </div>
                </div>

                <div class="form-group">
                    <label>Room Number</label>
                    <input type="text" name="room_number" id="ep_room" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editPeriodModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editPeriod(p) {
    document.getElementById('ep_id').value      = p.id;
    document.getElementById('ep_class').value   = p.class_id;
    document.getElementById('ep_teacher').value = p.teacher_id;
    document.getElementById('ep_subject').value = p.subject_id;
    document.getElementById('ep_start').value   = p.start_time.substring(0, 5);
    document.getElementById('ep_end').value     = p.end_time.substring(0, 5);
    document.getElementById('ep_room').value    = p.room_number || '';

    document.getElementById('ep_day_select').value = p.day_of_week.trim();

    Modal.open('editPeriodModal');
}
</script>


<script>
function applyTTFilter(facultyChanged) {
    var fac = document.getElementById('ttFaculty').value;
    var cls = facultyChanged ? '' : document.getElementById('ttClass').value;
    var url = 'timetable.php';
    var parts = [];
    if (fac) parts.push('faculty=' + encodeURIComponent(fac));
    if (cls) parts.push('class_id=' + encodeURIComponent(cls));
    if (parts.length) url += '?' + parts.join('&');
    window.location.href = url;
}
</script>
<?php include '../includes/admin_footer.php'; ?>