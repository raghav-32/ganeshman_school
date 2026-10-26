<?php
require_once '../includes/auth.php';
requireRole('teacher', '../index.php');

$pageTitle  = 'Enter Marks';
$activeMenu = 'marks';
$user       = currentUser();
$teacherId  = (int)$user['ref_id'];

// Handle marks save
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save') {
    $examId    = (int)($_POST['exam_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    // Get full/pass marks from the subject definition
    $subjectInfo = db()->fetchOne("SELECT full_marks, pass_marks FROM subjects WHERE id=?", 'i', [$subjectId]);
    $fullMarks   = (int)($subjectInfo['full_marks'] ?? 100);
    $passMarks   = (int)($subjectInfo['pass_marks'] ?? 40);
    $marksData = $_POST['marks'] ?? [];

    foreach ($marksData as $studentId => $obtained) {
        $studentId = (int)$studentId;
        $obtained  = (float)$obtained;
        $grade     = calculateGrade($obtained, $fullMarks);

        $existing = db()->fetchOne("SELECT id FROM marks WHERE student_id=? AND exam_id=? AND subject_id=?", 'iii', [$studentId, $examId, $subjectId]);
        if ($existing) {
            db()->execute("UPDATE marks SET marks_obtained=?,full_marks=?,pass_marks=?,grade=?,entered_by=? WHERE id=?", 'diiisi', [$obtained,$fullMarks,$passMarks,$grade,$teacherId,$existing['id']]);
        } else {
            db()->execute("INSERT INTO marks (student_id,exam_id,subject_id,marks_obtained,full_marks,pass_marks,grade,entered_by) VALUES (?,?,?,?,?,?,?,?)", 'iiidiiis', [$studentId,$examId,$subjectId,$obtained,$fullMarks,$passMarks,$grade,$teacherId]);
        }
    }
    setFlash('success', 'Marks saved successfully.');
    redirect("marks.php?exam_id=$examId&subject_id=$subjectId");
}

// Get teacher's classes from all sources
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

$classIds        = array_column($myClasses, 'id');
$exams           = $students = $subjects = [];
$existingMarks   = [];
$selectedExam    = (int)($_GET['exam_id']    ?? 0);
$selectedSubject = (int)($_GET['subject_id'] ?? 0);
$filterClassId   = (int)($_GET['class_id']   ?? ($myClasses[0]['id'] ?? 0));

// Only allow marks entry if teacher is class teacher of THIS exam's class specifically
$isClassTeacherMk = false;
if ($selectedExam) {
    $isClassTeacherMk = (bool) db()->fetchOne(
        "SELECT c.id FROM exams e
         JOIN classes c ON e.class_id = c.id
         WHERE e.id = ? AND c.class_teacher_id = ?",
        'ii', [$selectedExam, $teacherId]
    );
}

if (!empty($classIds)) {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $exams = db()->fetchAll(
        "SELECT e.*, c.class_name, c.section FROM exams e JOIN classes c ON e.class_id=c.id WHERE e.class_id IN ($placeholders) ORDER BY e.created_at DESC",
        str_repeat('i', count($classIds)), $classIds
    );
}

if ($selectedExam) {
    $exam = db()->fetchOne("SELECT e.*, c.id as cid FROM exams e JOIN classes c ON e.class_id=c.id WHERE e.id=?", 'i', [$selectedExam]);
    if ($exam) {
        $subjects = db()->fetchAll("SELECT * FROM subjects WHERE class_id=? ORDER BY subject_name", 'i', [$exam['cid']]);
        $students = db()->fetchAll("SELECT id, student_id, first_name, last_name FROM students WHERE class_id=? AND is_active=1 ORDER BY first_name", 'i', [$exam['cid']]);
        if ($selectedSubject) {
            foreach (db()->fetchAll("SELECT * FROM marks WHERE exam_id=? AND subject_id=?", 'ii', [$selectedExam, $selectedSubject]) as $m) {
                $existingMarks[$m['student_id']] = $m;
            }
        }
    }
}

require_once '../includes/teacher_header.php';
?>

<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;">
                <label>Exam</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Select Exam --</option>
                    <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $selectedExam==$e['id']?'selected':'' ?>>
                        <?= htmlspecialchars($e['exam_name'].' – '.$e['class_name'].' '.$e['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($subjects): ?>
            <div class="form-group" style="margin-bottom:0;min-width:160px;">
                <label>Subject</label>
                <select name="subject_id" class="form-control">
                    <option value="">-- Select Subject --</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $selectedSubject==$s['id']?'selected':'' ?>>
                        <?= htmlspecialchars($s['subject_name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Load</button>
            <?php endif; ?>
        </form>
    </div>
</div>

<?php if ($selectedExam && $selectedSubject && !empty($students)): ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-pencil-alt"></i> Enter Marks</h3>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save">
        <input type="hidden" name="exam_id" value="<?= $selectedExam ?>">
        <input type="hidden" name="subject_id" value="<?= $selectedSubject ?>">
        <div class="card-body">

            <?php
            $subFM = db()->fetchOne("SELECT full_marks, pass_marks FROM subjects WHERE id=?", 'i', [$selectedSubject]);
            if ($subFM): ?>
            <div style="display:flex;gap:16px;margin-bottom:12px;font-size:.83rem;">
                <span style="background:rgba(37,99,232,.08);padding:6px 14px;border-radius:var(--radius-sm);">
                    <strong>Full Marks:</strong> <?= $subFM['full_marks'] ?>
                </span>
                <span style="background:rgba(16,185,129,.08);padding:6px 14px;border-radius:var(--radius-sm);">
                    <strong>Pass Marks:</strong> <?= $subFM['pass_marks'] ?>
                </span>
            </div>
            <?php endif; ?>
            <div class="table-responsive">
                <table class="table">
                    <thead><tr><th>#</th><th>Student</th><th>ID</th><th>Marks</th><th>Grade</th></tr></thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <?php $ex = $existingMarks[$s['id']] ?? null; ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></td>
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td>
                                <?php if ($isClassTeacherMk): ?>
                                <input type="number" name="marks[<?= $s['id'] ?>]"
                                    class="form-control marks-input"
                                    style="max-width:100px;"
                                    min="0" max="100" step="0.5"
                                    value="<?= $ex ? $ex['marks_obtained'] : '' ?>"
                                    data-row="<?= $i ?>">
                                <?php else: ?>
                                <span style="font-weight:700;"><?= $ex ? $ex['marks_obtained'] : '—' ?></span>
                                <?php endif; ?>
                            </td>
                            <td><span class="badge badge-gray grade-preview" id="grade_<?= $i ?>"><?= $ex ? $ex['grade'] : '–' ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;gap:10px;align-items:center;">
            <?php if (!$isClassTeacherMk): ?>
            <span style="font-size:0.82rem;color:#92400e;background:#fef3c7;padding:8px 14px;border-radius:var(--radius-sm);border:1px solid #fbbf24;">
                <i class="fas fa-lock"></i> Only class teacher can enter marks
            </span>
            <?php endif; ?>
            <a href="marks.php" class="btn btn-outline">Cancel</a>
            <?php if ($isClassTeacherMk): ?>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Marks</button>
            <?php endif; ?>
        </div>
    </form>
</div>
<script>
const gradeTable = [[90,'A+'],[80,'A'],[70,'B+'],[60,'B'],[50,'C+'],[40,'C'],[35,'D'],[0,'F']];
const badgeMap = {'A+':'success','A':'success','B+':'info','B':'info','C+':'warning','C':'warning','D':'warning','F':'danger'};
document.querySelectorAll('.marks-input').forEach(input => {
    input.addEventListener('input', function() {
        const v = parseFloat(this.value), row = this.dataset.row, el = document.getElementById('grade_'+row);
        if (isNaN(v)) { el.textContent='–'; el.className='grade-preview badge badge-gray'; return; }
        const grade = gradeTable.find(g => v>=g[0]);
        el.textContent = grade ? grade[1] : 'F';
        el.className = 'grade-preview badge badge-'+(badgeMap[grade?grade[1]:'F']||'gray');
    });
});
</script>
<?php elseif (!empty($exams)): ?>
<div class="empty-state card" style="padding:40px;">
    <i class="fas fa-pencil-alt"></i>
    <h3>Select Exam and Subject</h3>
    <p>Choose an exam and subject from the filters above to enter marks.</p>
</div>
<?php else: ?>
<div class="empty-state card" style="padding:40px;">
    <i class="fas fa-file-alt"></i>
    <h3>No Exams Available</h3>
    <p>No exams found for your classes. Ask admin to create exams.</p>
</div>
<?php endif; ?>

<?php require_once '../includes/teacher_footer.php'; ?>