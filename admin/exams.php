<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Exam Management';
$activeMenu = 'exams';

// AJAX: return fresh schedule data for a specific exam
if (isset($_GET['ajax_schedule']) && isset($_GET['exam_id'])) {
    header('Content-Type: application/json');
    $aExamId = (int)$_GET['exam_id'];
    $rows = db()->fetchAll("SELECT * FROM exam_schedule WHERE exam_id=?", 'i', [$aExamId]);
    echo json_encode($rows);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name      = sanitize($_POST['exam_name'] ?? '');
        $type      = sanitize($_POST['exam_type'] ?? 'Unit Test');
        $classId   = (int)($_POST['class_id'] ?? 0);
        $start     = sanitize($_POST['start_date'] ?? '');
        $end       = sanitize($_POST['end_date'] ?? '');
        $startTime = sanitize($_POST['start_time'] ?? '');
        $endTime   = sanitize($_POST['end_time'] ?? '');

        if ($action === 'add') {
            db()->execute(
                "INSERT INTO exams (exam_name,exam_type,class_id,start_date,end_date,start_time,end_time) VALUES (?,?,?,?,?,?,?)",
                'ssissss',
                [$name,$type,$classId,$start,$end,$startTime ?: null,$endTime ?: null]
            );
            setFlash('success', "Exam '$name' created.");
        } else {
            $id = (int)($_POST['id'] ?? 0);
            db()->execute(
                "UPDATE exams SET exam_name=?,exam_type=?,class_id=?,start_date=?,end_date=?,start_time=?,end_time=? WHERE id=?",
                'ssissssi',
                [$name,$type,$classId,$start,$end,$startTime ?: null,$endTime ?: null,$id]
            );
            setFlash('success', "Exam '$name' updated.");
        }
        redirect('exams.php');
    }

    if ($action === 'toggle_publish') {
        $id = (int)($_POST['id'] ?? 0);
        $cur = (int)($_POST['current'] ?? 0);
        db()->execute("UPDATE exams SET is_published=? WHERE id=?", 'ii', [!$cur,$id]);
        setFlash('success', $cur ? 'Exam unpublished.' : 'Exam published to students!');
        redirect('exams.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute("DELETE FROM exams WHERE id=?", 'i', [$id]);
        setFlash('success', 'Exam deleted.');
        redirect('exams.php');
    }

    if ($action === 'save_schedule') {
        $examId   = (int)($_POST['sched_exam_id'] ?? 0);
        $subjects = $_POST['sched_subject'] ?? [];
        $dates    = $_POST['sched_date']    ?? [];
        $starts   = $_POST['sched_start']   ?? [];
        $ends     = $_POST['sched_end']     ?? [];
        $venues   = $_POST['sched_venue']   ?? [];
        // Delete old schedule for this exam
        db()->execute("DELETE FROM exam_schedule WHERE exam_id=?", 'i', [$examId]);
        $savedCount = 0;
        $skipped    = [];
        foreach ($subjects as $idx => $subId) {
            $subId = (int)$subId;
            $date  = sanitize($dates[$idx]  ?? '');
            $start = sanitize($starts[$idx] ?? '');
            $end   = sanitize($ends[$idx]   ?? '');
            $venue = sanitize($venues[$idx] ?? '');
            if ($subId && $date) {
                db()->execute(
                    "INSERT INTO exam_schedule (exam_id,subject_id,exam_date,start_time,end_time,venue) VALUES (?,?,?,?,?,?)",
                    'iissss', [$examId, $subId, $date, $start ?: null, $end ?: null, $venue]
                );
                $savedCount++;
            } elseif ($subId) {
                // Subject had no date submitted — look up its name for a clear warning
                $subRow = db()->fetchOne("SELECT subject_name FROM subjects WHERE id=?", 'i', [$subId]);
                $skipped[] = $subRow['subject_name'] ?? "Subject #$subId";
            }
        }
        if ($skipped) {
            setFlash('error', 'Schedule saved for ' . $savedCount . ' subject(s), but skipped (no date set): ' . implode(', ', $skipped) . '. Please reopen the schedule and set a date for these.');
        } else {
            setFlash('success', 'Exam schedule saved.');
        }
        redirect('exams.php');
    }
}

$classes = db()->fetchAll("SELECT * FROM classes ORDER BY class_name, section");
$exams   = db()->fetchAll("
    SELECT e.*, c.class_name, c.section,
           (SELECT COUNT(*) FROM marks m WHERE m.exam_id=e.id) as marks_count
    FROM exams e JOIN classes c ON e.class_id=c.id
    ORDER BY e.created_at DESC
");

include '../includes/admin_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> All Exams (<?= count($exams) ?>)</h3>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addExamModal')">
            <i class="fas fa-plus"></i> Create Exam
        </button>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>#</th><th>Exam Name</th><th>Type</th><th>Class</th><th>Start (BS)</th><th>End (BS)</th><th>Marks</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="fas fa-file-alt"></i><h3>No Exams Yet</h3></div></td></tr>
                    <?php else: ?>
                    <?php foreach ($exams as $i => $e): ?>
                    <?php $tc=['Unit Test'=>'info','Terminal'=>'warning','Final'=>'danger','Other'=>'gray']; ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($e['exam_name']) ?></strong></td>
                        <td><span class="badge badge-<?= $tc[$e['exam_type']]??'gray' ?>"><?= htmlspecialchars($e['exam_type']) ?></span></td>
                        <td><?= htmlspecialchars($e['class_name'].' '.$e['section']) ?></td>
                        <td><span data-bs-date="<?= $e['start_date'] ?>"><?= $e['start_date']?:'–' ?></span></td>
                        <td><span data-bs-date="<?= $e['end_date'] ?>"><?= $e['end_date']?:'–' ?></span></td>
                        <td><span class="badge badge-<?= $e['marks_count']>0?'success':'gray' ?>"><?= $e['marks_count'] ?> records</span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle_publish">
                                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                <input type="hidden" name="current" value="<?= $e['is_published'] ?>">
                                <button type="submit" class="badge badge-<?= $e['is_published']?'success':'gray' ?>" style="border:none;cursor:pointer;padding:4px 10px;">
                                    <?= $e['is_published'] ? '✓ Published' : 'Draft' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div style="display:flex;gap:5px;">
                                <button class="btn btn-info btn-sm" onclick="editExam(<?= htmlspecialchars(json_encode($e)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-warning btn-sm" title="Schedule" onclick="openSchedule(<?= $e['id'] ?>, <?= htmlspecialchars(json_encode($e['class_id'])) ?>, <?= htmlspecialchars(json_encode($e['start_date'] ?: '')) ?>)">
                                    <i class="fas fa-calendar-alt"></i>
                                </button>
                                <a href="marks.php?exam_id=<?= $e['id'] ?>" class="btn btn-success btn-sm" title="Enter Marks">
                                    <i class="fas fa-star-half-alt"></i>
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $e['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete exam and all its marks?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD MODAL -->
<div class="modal-overlay" id="addExamModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Create Exam</h3>
            <button class="modal-close" onclick="Modal.close('addExamModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group"><label>Exam Name *</label><input type="text" name="exam_name" class="form-control" placeholder="e.g. First Terminal 2081" required></div>
                <div class="form-group"><label>Exam Type</label>
                    <select name="exam_type" class="form-control"><option value="Unit Test">Unit Test</option><option value="Terminal">Terminal</option><option value="Final">Final</option><option value="Other">Other</option></select>
                </div>
                <div class="form-group"><label>Class *</label>
                    <select name="class_id" class="form-control" required><option value="">Select Class</option>
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>Start Date</label><?= bsDatePicker('start_date', '', false) ?></div>
                    <div class="form-group"><label>End Date</label><?= bsDatePicker('end_date', '', false) ?></div>
                    <div class="form-group"><label>Exam Start Time</label><input type="time" name="start_time" class="form-control" placeholder="e.g. 10:00"></div>
                    <div class="form-group"><label>Exam End Time</label><input type="time" name="end_time" class="form-control" placeholder="e.g. 13:00"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addExamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editExamModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Exam</h3>
            <button class="modal-close" onclick="Modal.close('editExamModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editExamId">
            <div class="modal-body">
                <div class="form-group"><label>Exam Name *</label><input type="text" name="exam_name" id="ee_name" class="form-control" required></div>
                <div class="form-group"><label>Exam Type</label>
                    <select name="exam_type" id="ee_type" class="form-control"><option value="Unit Test">Unit Test</option><option value="Terminal">Terminal</option><option value="Final">Final</option><option value="Other">Other</option></select>
                </div>
                <div class="form-group"><label>Class *</label>
                    <select name="class_id" id="ee_class" class="form-control" required>
                        <?php foreach ($classes as $c): ?><option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['class_name'].' '.$c['section']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>Start Date</label><?= bsDatePicker('start_date', '', false, 'ee_start') ?></div>
                    <div class="form-group"><label>End Date</label><?= bsDatePicker('end_date', '', false, 'ee_end') ?></div>
                    <div class="form-group"><label>Exam Start Time</label><input type="time" name="start_time" id="ee_start_time" class="form-control"></div>
                    <div class="form-group"><label>Exam End Time</label><input type="time" name="end_time" id="ee_end_time" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editExamModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<!-- SCHEDULE MODAL -->
<div class="modal-overlay" id="scheduleModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-calendar-alt"></i> Exam Schedule / Routine</h3>
            <button class="modal-close" onclick="Modal.close('scheduleModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" id="scheduleForm" data-full-submit>
            <input type="hidden" name="action" value="save_schedule">
            <input type="hidden" name="sched_exam_id" id="sched_exam_id">
            <div class="modal-body" id="scheduleBody">
                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading subjects...</p></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('scheduleModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Schedule</button>
            </div>
        </form>
    </div>
</div>

<script>
var ALL_SUBJECTS = <?= json_encode(db()->fetchAll("SELECT id, subject_name, class_id FROM subjects ORDER BY subject_name")) ?>;
// ALL_SCHEDULES is fetched fresh via AJAX each time modal opens (avoids stale data)

function openSchedule(examId, classId, examStartDate) {
    document.getElementById('sched_exam_id').value = examId;
    document.getElementById('scheduleBody').innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Loading schedule...</p></div>';
    Modal.open('scheduleModal');

    // Fetch fresh schedule data via AJAX (avoids stale page-load data)
    fetch('exams.php?ajax_schedule=1&exam_id=' + examId)
        .then(function(r){ return r.json(); })
        .then(function(schedRows){
            renderScheduleForm(examId, classId, schedRows, examStartDate);
        })
        .catch(function(){
            document.getElementById('scheduleBody').innerHTML = '<div class="empty-state"><i class="fas fa-exclamation-triangle"></i><p>Could not load schedule.</p></div>';
        });
}

function renderScheduleForm(examId, classId, schedRows, examStartDate) {
    var subjects = ALL_SUBJECTS.filter(function(s){ return s.class_id == classId; });
    var schedMap = {};
    schedRows.forEach(function(s){ schedMap[s.subject_id] = s; });

    var body = '';
    if (!subjects.length) {
        body = '<div class="empty-state"><i class="fas fa-book-open"></i><p>No subjects found for this class. Add subjects first.</p></div>';
    } else {
        body = '<div style="overflow-x:auto;"><table class="table" style="min-width:420px;">'
             + '<thead><tr><th>Subject</th><th>Exam Date (BS)</th><th>Venue/Room</th></tr></thead><tbody>';
        subjects.forEach(function(sub, i) {
            var sc = schedMap[sub.id] || {};
            // A row with no date already saved for it defaults to the exam's own start
            // date rather than being left truly empty — otherwise saving without manually
            // clicking every single row silently drops that subject from the schedule.
            var dateField = renderBsDateInput('sched_date[]', sc.exam_date || examStartDate || '');
            body += '<tr>'
                + '<td><strong>' + escapeHtml(sub.subject_name) + '</strong>'
                + '<input type="hidden" name="sched_subject[]" value="' + sub.id + '"></td>'
                + '<td>' + dateField.html + '</td>'
                + '<td><input type="text" name="sched_venue[]" class="form-control" value="' + escapeAttr(sc.venue||'') + '" placeholder="Room/Hall" style="min-width:110px;"></td>'
                + '</tr>';
        });
        body += '</tbody></table></div>';
    }
    document.getElementById('scheduleBody').innerHTML = body;
}

function escapeHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
function escapeAttr(str) {
    return String(str).replace(/&/g,'&amp;').replace(/"/g,'&quot;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function renderBsDateInput(name, adVal) {
    var uid = 'sd_' + Math.random().toString(36).substr(2,8);
    var html = bsdpBuildHTML(name, adVal || '', false, uid);
    return { html: html, uid: uid };
}
function editExam(e) {
    document.getElementById('editExamId').value = e.id;
    document.getElementById('ee_name').value    = e.exam_name;
    document.getElementById('ee_type').value    = e.exam_type;
    document.getElementById('ee_class').value   = e.class_id;
    document.getElementById('ee_start').value   = e.start_date || '';
    if (e.start_date) bsdpSyncFromAd('ee_start');
    document.getElementById('ee_end').value       = e.end_date || '';
    if (e.end_date) bsdpSyncFromAd('ee_end');
    document.getElementById('ee_start_time').value = e.start_time || '';
    document.getElementById('ee_end_time').value   = e.end_time   || '';
    Modal.open('editExamModal');
}
</script>

<?php include '../includes/admin_footer.php'; ?>