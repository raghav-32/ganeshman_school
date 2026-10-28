<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Marks & Results';
$activeMenu = 'marks';

// ── AJAX: return marks JSON for marksheet ─────────────────────────────────
if (isset($_GET['ajax_marks']) && isset($_GET['student_id'])) {
    header('Content-Type: application/json');
    header('Cache-Control: no-cache');
    $aExamId = (int)($_GET['exam_id']    ?? 0);
    $aStuId  = (int)($_GET['student_id'] ?? 0);

    // Student info
    $aStu = db()->fetchOne("
        SELECT s.first_name, s.last_name, s.student_id, c.class_name, c.section
        FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=?
    ", 'i', [$aStuId]);

    // Marks with subject info
    $aMarks = db()->fetchAll("
        SELECT m.*, m.subject_id, sub.subject_name,
               sub.full_marks as sub_full, sub.pass_marks as sub_pass
        FROM marks m JOIN subjects sub ON m.subject_id=sub.id
        WHERE m.exam_id=? AND m.student_id=?
        ORDER BY sub.subject_name
    ", 'ii', [$aExamId, $aStuId]);

    // All subjects for this exam's class
    $aExamRow = db()->fetchOne("SELECT class_id FROM exams WHERE id=?", 'i', [$aExamId]);
    $aSubjects = $aExamRow ? db()->fetchAll(
        "SELECT id, subject_name, full_marks, pass_marks FROM subjects WHERE class_id=? ORDER BY subject_name",
        'i', [(int)$aExamRow['class_id']]
    ) : [];

    echo json_encode([
        'student'  => $aStu,
        'marks'    => $aMarks,
        'subjects' => $aSubjects,
    ]);
    exit;
}

// Add results_published column if it doesn't exist yet
$cols = db()->fetchAll("SHOW COLUMNS FROM exams LIKE 'results_published'");
if (empty($cols)) {
    db()->query("ALTER TABLE exams ADD COLUMN results_published TINYINT(1) NOT NULL DEFAULT 0 AFTER is_published");
}

// Handle result publish toggle (separate from exam schedule publish)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_publish') {
    $id  = (int)($_POST['id'] ?? 0);
    $cur = (int)($_POST['current'] ?? 0);
    db()->execute("UPDATE exams SET results_published=? WHERE id=?", 'ii', [1 - $cur, $id]);
    setFlash('success', $cur ? 'Results unpublished. Students can no longer see marks.' : 'Results published! Students can now view their marks.');
    redirect("marks.php?exam_id=$id");
}

// Handle save marks (bulk)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'save_marks') {
    $examId    = (int)($_POST['exam_id'] ?? 0);
    $subjectId = (int)($_POST['subject_id'] ?? 0);
    // Use subject's defined full/pass marks
    $subjectInfo = db()->fetchOne("SELECT full_marks, pass_marks FROM subjects WHERE id=?", 'i', [$subjectId]);
    $fullMarks   = (int)($subjectInfo['full_marks'] ?? 100);
    $passMarks   = (int)($subjectInfo['pass_marks'] ?? 40);
    $marksData = $_POST['marks'] ?? [];

    $saved = 0;
    foreach ($marksData as $studentId => $obtained) {
        $studentId = (int)$studentId;
        $obtained  = (float)$obtained;
        $grade     = calculateGrade($obtained, $fullMarks);

        // Upsert
        $existing = db()->fetchOne(
            "SELECT id FROM marks WHERE student_id=? AND exam_id=? AND subject_id=?",
            'iii', [$studentId, $examId, $subjectId]
        );
        if ($existing) {
            db()->execute(
                "UPDATE marks SET marks_obtained=?, full_marks=?, pass_marks=?, grade=? WHERE id=?",
                'diisi', [$obtained, $fullMarks, $passMarks, $grade, $existing['id']]
            );
        } else {
            db()->execute(
                "INSERT INTO marks (student_id, exam_id, subject_id, marks_obtained, full_marks, pass_marks, grade, entered_by) VALUES (?,?,?,?,?,?,?,?)",
                'iiidiisi', [$studentId, $examId, $subjectId, $obtained, $fullMarks, $passMarks, $grade, 1]
            );
        }
        $saved++;
    }
    setFlash('success', "Marks saved for $saved students.");
    redirect("marks.php?exam_id=$examId");
}

// Filters
$examId    = (int)($_GET['exam_id'] ?? 0);
$subjectId = (int)($_GET['subject_id'] ?? 0);

$exams    = db()->fetchAll("SELECT e.*, c.class_name, c.section FROM exams e JOIN classes c ON e.class_id=c.id ORDER BY e.created_at DESC");
$subjects = [];
$students = [];
$existingMarks = [];
$selectedExam = null;

if ($examId) {
    $selectedExam = db()->fetchOne("SELECT e.*, c.class_name, c.section, c.id as cid FROM exams e JOIN classes c ON e.class_id=c.id WHERE e.id=?", 'i', [$examId]);
    if ($selectedExam) {
        $subjects = db()->fetchAll("SELECT * FROM subjects WHERE class_id=? ORDER BY subject_name", 'i', [$selectedExam['cid']]);
        if ($subjectId) {
            $students = db()->fetchAll(
                "SELECT s.id, s.student_id, s.first_name, s.last_name FROM students s WHERE s.class_id=? AND s.is_active=1 ORDER BY s.first_name",
                'i', [$selectedExam['cid']]
            );
            foreach (db()->fetchAll("SELECT * FROM marks WHERE exam_id=? AND subject_id=?", 'ii', [$examId, $subjectId]) as $m) {
                $existingMarks[$m['student_id']] = $m;
            }
        }
    }
}


// Preload all subjects for this exam's class (for marksheet JS)
$examSubjects = [];
$examClassRow = $selectedExam ? db()->fetchOne("SELECT class_id FROM exams WHERE id=?", 'i', [(int)($selectedExam['id'] ?? 0)]) : null;
if ($examClassRow) {
    $examSubjects = db()->fetchAll(
        "SELECT id, subject_name, full_marks, pass_marks FROM subjects WHERE class_id=? ORDER BY subject_name",
        'i', [(int)$examClassRow['class_id']]
    );
}
// Logo base64 for marksheet
$logoBase64 = '';
$logoPath   = '../assets/images/gms.png';
if (file_exists($logoPath)) {
    $logoBase64 = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

include '../includes/admin_header.php';
?>

<!-- Filter Row -->
<div class="card" style="margin-bottom:20px;">
    <div class="card-body">
        <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group" style="margin-bottom:0;flex:1;min-width:200px;">
                <label>Select Exam</label>
                <select name="exam_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Choose Exam --</option>
                    <?php foreach ($exams as $e): ?>
                    <option value="<?= $e['id'] ?>" <?= $examId==$e['id']?'selected':'' ?>>
                        <?= htmlspecialchars($e['exam_name'].' – '.$e['class_name'].' '.$e['section']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php if ($subjects): ?>
            <div class="form-group" style="margin-bottom:0;min-width:180px;">
                <label>Select Subject</label>
                <select name="subject_id" class="form-control">
                    <option value="">-- Choose Subject --</option>
                    <?php foreach ($subjects as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $subjectId==$s['id']?'selected':'' ?>>
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

<?php if ($selectedExam): ?>
<!-- Exam Header -->
<div style="margin-bottom:18px;padding:16px 20px;background:#fff;border-radius:var(--radius);border:1px solid var(--border-light);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;">
    <div>
        <h3 style="font-size:1.05rem;font-weight:800;"><?= htmlspecialchars($selectedExam['exam_name']) ?></h3>
        <p style="font-size:0.82rem;color:var(--text-secondary);">
            <?= htmlspecialchars($selectedExam['class_name'].' '.$selectedExam['section']) ?> &bull;
            <?= htmlspecialchars($selectedExam['exam_type']) ?> &bull;
            <?= formatDate($selectedExam['start_date']) ?> – <?= formatDate($selectedExam['end_date']) ?>
        </p>
    </div>
    <form method="POST" style="display:inline;">
        <input type="hidden" name="action" value="toggle_publish">
        <input type="hidden" name="id" value="<?= $selectedExam['id'] ?>">
        <input type="hidden" name="current" value="<?= $selectedExam['results_published'] ?>">
        <button type="submit"
            onclick="return confirm('<?= $selectedExam['results_published'] ? 'Hide results? Students will no longer see marks.' : 'Publish results? Students will be able to view their marks.' ?>')"
            class="btn btn-<?= $selectedExam['results_published'] ? 'success' : 'outline' ?> btn-sm">
            <i class="fas fa-<?= $selectedExam['results_published'] ? 'eye' : 'eye-slash' ?>"></i>
            <?= $selectedExam['results_published'] ? '✓ Results Published – Click to Hide' : 'Results Hidden – Click to Publish' ?>
        </button>
    </form>
</div>
<?php endif; ?>

<?php if ($selectedExam && $subjectId && !empty($students)): ?>
<!-- ENTER MARKS -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-header">
        <h3><i class="fas fa-pencil-alt"></i> Enter Marks
            – <?= htmlspecialchars(db()->fetchOne("SELECT subject_name FROM subjects WHERE id=?", 'i', [$subjectId])['subject_name'] ?? '') ?>
        </h3>
    </div>
    <form method="POST">
        <input type="hidden" name="action" value="save_marks">
        <input type="hidden" name="exam_id" value="<?= $examId ?>">
        <input type="hidden" name="subject_id" value="<?= $subjectId ?>">
        <div class="card-body">
            <?php
            $subInfo = db()->fetchOne("SELECT full_marks, pass_marks FROM subjects WHERE id=?", 'i', [$subjectId]);
            $subFM   = (int)($subInfo['full_marks'] ?? 100);
            $subPM   = (int)($subInfo['pass_marks'] ?? 40);
            ?>
            <input type="hidden" name="full_marks" id="fullMarksInput" value="<?= $subFM ?>">
            <input type="hidden" name="pass_marks" value="<?= $subPM ?>">
            <div style="display:flex;gap:16px;margin-bottom:16px;">
                <span style="background:rgba(37,99,232,.08);padding:8px 16px;border-radius:var(--radius-sm);font-size:.84rem;">
                    <strong>Full Marks:</strong> <?= $subFM ?>
                </span>
                <span style="background:rgba(16,185,129,.08);padding:8px 16px;border-radius:var(--radius-sm);font-size:.84rem;">
                    <strong>Pass Marks:</strong> <?= $subPM ?>
                </span>
            </div>

            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Marks Obtained</th>
                            <th>Grade (Preview)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $i => $s): ?>
                        <?php $existing = $existingMarks[$s['id']] ?? null; ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td>
                                <div style="display:flex;align-items:center;gap:9px;">
                                    <div class="student-avatar" style="width:30px;height:30px;font-size:0.72rem;">
                                        <?= strtoupper(substr($s['first_name'],0,1)) ?>
                                    </div>
                                    <?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?>
                                </div>
                            </td>
                            <td><?= htmlspecialchars($s['student_id']) ?></td>
                            <td>
                                <input type="number" name="marks[<?= $s['id'] ?>]"
                                    class="form-control marks-input"
                                    style="max-width:110px;"
                                    min="0" max="100" step="0.5"
                                    value="<?= $existing ? $existing['marks_obtained'] : '' ?>"
                                    placeholder="0–100"
                                    data-row="<?= $i ?>">
                            </td>
                            <td>
                                <span class="grade-preview badge badge-gray" id="grade_<?= $i ?>">
                                    <?= $existing ? $existing['grade'] : '–' ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card-footer" style="display:flex;justify-content:flex-end;gap:12px;">
            <a href="marks.php?exam_id=<?= $examId ?>" class="btn btn-outline">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> Save All Marks
            </button>
        </div>
    </form>
</div>

<script>
// Live grade preview — uses percentage like PHP calculateGrade()
const fullMarksVal = parseInt(document.getElementById('fullMarksInput')?.value || 100);
const gradeTable   = [
    [90,'A+'], [80,'A'], [70,'B+'], [60,'B'],
    [50,'C+'], [40,'C'], [35,'D'],  [0,'F']
];
const badgeMap = {
    'A+':'success','A':'success','B+':'info','B':'info',
    'C+':'warning','C':'warning','D':'warning','F':'danger'
};

document.querySelectorAll('.marks-input').forEach(input => {
    input.addEventListener('input', function() {
        const v   = parseFloat(this.value);
        const row = this.dataset.row;
        const el  = document.getElementById('grade_' + row);
        if (isNaN(v) || v < 0) { el.textContent = '–'; el.className = 'grade-preview badge badge-gray'; return; }
        const pct   = (fullMarksVal > 0) ? (v / fullMarksVal) * 100 : 0;
        const grade = gradeTable.find(g => pct >= g[0]);
        el.textContent = grade ? grade[1] : 'F';
        el.className   = 'grade-preview badge badge-' + (badgeMap[grade ? grade[1] : 'F'] || 'gray');
    });
});
</script>

<?php elseif ($selectedExam && $subjectId): ?>
<div class="empty-state card" style="padding:40px;">
    <i class="fas fa-users"></i>
    <h3>No Students in This Class</h3>
    <p>Assign students to the class first.</p>
</div>

<?php elseif ($selectedExam): ?>
<!-- Results Overview for this exam -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chart-bar"></i> Results Overview – <?= htmlspecialchars($selectedExam['exam_name']) ?></h3>
    </div>
    <div class="card-body" style="padding:0;">
        <?php
        $classStudents = db()->fetchAll(
            "SELECT s.id, s.student_id, s.first_name, s.last_name,
                    COALESCE(SUM(m.marks_obtained),0) as total_obtained,
                    COALESCE(SUM(m.full_marks),0) as total_full,
                    COUNT(m.id) as subjects_done
             FROM students s
             LEFT JOIN marks m ON s.id = m.student_id AND m.exam_id = ?
             WHERE s.class_id = ? AND s.is_active = 1
             GROUP BY s.id
             ORDER BY total_obtained DESC",
            'ii', [$examId, $selectedExam['cid']]
        );
        ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>ID</th>
                        <th>Subjects Done</th>
                        <th>Total Marks</th>
                        <th>Percentage</th>
                        <th>Overall Grade</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($classStudents as $rank => $cs): ?>
                    <?php
                    $pct = ($cs['total_full'] > 0) ? round(($cs['total_obtained'] / $cs['total_full']) * 100, 1) : 0;
                    $grade = calculateGrade($cs['total_obtained'], $cs['total_full'] ?: 100);
                    $gradeColors = ['A+'=>'success','A'=>'success','B+'=>'info','B'=>'info','C+'=>'warning','C'=>'warning','D'=>'warning','F'=>'danger','NG'=>'danger'];
                    ?>
                    <tr>
                        <td>
                            <?php if ($rank < 3): ?>
                            <span style="font-size:1.1rem;"><?= ['🥇','🥈','🥉'][$rank] ?></span>
                            <?php else: ?>
                            <strong>#<?= $rank+1 ?></strong>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div class="student-avatar" style="width:30px;height:30px;font-size:0.72rem;">
                                    <?= strtoupper(substr($cs['first_name'],0,1)) ?>
                                </div>
                                <?= htmlspecialchars($cs['first_name'].' '.$cs['last_name']) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($cs['student_id']) ?></td>
                        <td><?= $cs['subjects_done'] ?></td>
                        <td><strong><?= $cs['total_obtained'] ?>/<?= $cs['total_full'] ?></strong></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div style="flex:1;height:6px;background:var(--border);border-radius:3px;overflow:hidden;">
                                    <div style="width:<?= $pct ?>%;height:100%;background:<?= $pct>=50?'var(--success)':'var(--danger)' ?>;border-radius:3px;"></div>
                                </div>
                                <span style="font-size:0.82rem;font-weight:700;"><?= $pct ?>%</span>
                            </div>
                        </td>
                        <td><span class="badge badge-<?= $gradeColors[$grade] ?? 'gray' ?>"><?= $grade ?></span></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="openMarksheetForStudent(<?= $cs['id'] ?>)">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>


<script src="../assets/js/marksheet.js?v=1"></script>
<script>
var EXAM_ID      = <?= json_encode($examId ?? 0) ?>;
var EXAM_NAME    = <?= json_encode($selectedExam['exam_name'] ?? '') ?>;
var EXAM_TYPE    = <?= json_encode($selectedExam['exam_type'] ?? '') ?>;
var EXAM_DATE    = <?= json_encode(formatDate($selectedExam['start_date'] ?? '')) ?>;
var EXAM_SUBJECTS= <?= json_encode($examSubjects) ?>;
var LOGO_SRC     = <?= json_encode($logoBase64) ?>;
var PRINTED_ON   = <?= json_encode(formatDate(date('Y-m-d'))) ?>;

function gradeCalc(obtained, full) {
    if (!full) return 'NG';
    var pct = (obtained / full) * 100;
    if (pct >= 90) return 'A+';
    if (pct >= 80) return 'A';
    if (pct >= 70) return 'B+';
    if (pct >= 60) return 'B';
    if (pct >= 50) return 'C+';
    if (pct >= 40) return 'C';
    if (pct >= 35) return 'D';
    return 'NG';
}

function openMarksheetForStudent(studentId) {
    // Fetch this student's marks via AJAX
    fetch('marks.php?exam_id=' + EXAM_ID + '&ajax_marks=1&student_id=' + studentId, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(function(r){ return r.json(); })
    .then(function(data) {
        buildMarksheet(data.student, data.marks, data.subjects);
    })
    .catch(function() {
        alert('Could not load marks. Please try again.');
    });
}

function buildMarksheet(student, marks, subjects) {
    gmsOpenMarksheet(student, marks, subjects, {
        examName: EXAM_NAME, examType: EXAM_TYPE, examDate: EXAM_DATE, printedOn: PRINTED_ON, logo: LOGO_SRC
    });
}
</script>

<?php include '../includes/admin_footer.php'; ?>