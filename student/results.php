<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'My Results';
$activeMenu = 'results';
$user       = currentUser();
$studentId  = $user['ref_id'];

// Get student's class
$student = db()->fetchOne("SELECT s.class_id, s.first_name, s.last_name, s.student_id, c.class_name, c.section FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.id=?", 'i', [$studentId]);
$classId = (int)($student['class_id'] ?? 0);

// Only show exams for THIS student's class
$exams = [];
if ($classId) {
    $exams = db()->fetchAll("
        SELECT e.id, e.exam_name, e.exam_type, e.start_date,
               c.class_name, c.section,
               SUM(m.marks_obtained) as total_obtained,
               SUM(m.full_marks)     as total_full,
               COUNT(m.id)           as subject_count
        FROM exams e
        JOIN classes c ON e.class_id = c.id
        LEFT JOIN marks m ON e.id = m.exam_id AND m.student_id = ?
        WHERE e.results_published = 1 AND e.class_id = ?
        GROUP BY e.id
        ORDER BY e.created_at DESC
    ", 'ii', [$studentId, $classId]);
}

$selectedExamId = (int)($_GET['exam_id'] ?? ($exams[0]['id'] ?? 0));
$details = [];
if ($selectedExamId) {
    $details = db()->fetchAll("
        SELECT m.*, sub.subject_name, sub.full_marks as sub_full, sub.pass_marks as sub_pass
        FROM marks m
        JOIN subjects sub ON m.subject_id = sub.id
        WHERE m.exam_id = ? AND m.student_id = ?
        ORDER BY sub.subject_name
    ", 'ii', [$selectedExamId, $studentId]);
}

$classSubjects = $classId ? db()->fetchAll(
    "SELECT id, subject_name, full_marks, pass_marks FROM subjects WHERE class_id=? ORDER BY subject_name", 'i', [$classId]
) : [];

require_once '../includes/student_header.php';
?>

<?php if (empty($exams)): ?>
<div class="empty-state card" style="padding:60px;">
    <i class="fas fa-star-half-alt"></i>
    <h3>No Results Published Yet</h3>
    <p>Your results will appear here once the admin publishes them. Please check back later.</p>
</div>
<?php else: ?>

<!-- Exam selector tabs -->
<div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:22px;">
    <?php foreach ($exams as $e): ?>
    <?php $pct = ($e['total_full'] > 0) ? round(($e['total_obtained']/$e['total_full'])*100,1) : 0; ?>
    <a href="results.php?exam_id=<?= $e['id'] ?>"
       style="padding:12px 18px;background:<?= $selectedExamId==$e['id']?'var(--primary)':'#fff' ?>;color:<?= $selectedExamId==$e['id']?'#fff':'var(--text-primary)' ?>;border-radius:var(--radius-sm);border:1.5px solid <?= $selectedExamId==$e['id']?'var(--primary)':'var(--border)' ?>;text-decoration:none;font-size:0.85rem;font-weight:700;transition:var(--transition);">
        <div><?= htmlspecialchars($e['exam_name']) ?></div>
        <div style="font-size:0.73rem;opacity:0.75;margin-top:2px;"><?= $e['exam_type'] ?> &bull; <?= $pct ?>%</div>
    </a>
    <?php endforeach; ?>
</div>

<?php if ($selectedExamId && !empty($details)):
    $totalObtained = array_sum(array_column($details, 'marks_obtained'));
    $totalFull     = array_sum(array_column($details, 'sub_full')) ?: array_sum(array_column($details, 'full_marks'));
    $overallPct    = ($totalFull > 0) ? round(($totalObtained / $totalFull) * 100, 1) : 0;
    $overallGrade  = calculateGrade($totalObtained, $totalFull ?: 100);
    $gradeColors   = ['A+'=>'success','A'=>'success','B+'=>'info','B'=>'info','C+'=>'warning','C'=>'warning','D'=>'warning','F'=>'danger'];
    // Fail overall if ANY subject is below its pass marks
    $failedSubject = false;
    foreach ($details as $d) {
        $dP = (int)($d['sub_pass'] ?: $d['pass_marks'] ?: 40);
        if ((float)$d['marks_obtained'] < $dP) { $failedSubject = true; break; }
    }
    $passed = ($overallGrade !== 'F') && !$failedSubject;

    // Find selected exam info
    $selExam = array_values(array_filter($exams, fn($e) => $e['id'] == $selectedExamId))[0] ?? null;
?>

<!-- Result Summary Card -->
<div style="background:linear-gradient(135deg,<?= $passed ? 'var(--success),#059669' : 'var(--danger),#b91c1c' ?>);border-radius:var(--radius-lg);padding:28px 32px;margin-bottom:22px;color:#fff;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
    <div>
        <div style="font-size:0.80rem;opacity:0.80;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><?= htmlspecialchars($selExam['exam_name'] ?? '') ?></div>
        <div style="font-size:2.5rem;font-weight:900;line-height:1;"><?= $overallPct ?>%</div>
        <div style="opacity:0.85;margin-top:6px;"><?= $totalObtained ?> / <?= $totalFull ?> marks &bull; <?= count($details) ?> subjects</div>
    </div>
    <div style="text-align:center;">
        <div style="font-size:3rem;font-weight:900;line-height:1;"><?= $overallGrade ?></div>
        <div style="font-size:0.85rem;opacity:0.85;margin-top:4px;"><?= $passed ? '✓ PASSED' : '✕ FAILED' ?></div>
    </div>
</div>

<!-- Subject-wise Marks -->
<div class="card" id="resultCard">
    <div class="card-header">
        <h3><i class="fas fa-table"></i> Subject-wise Result</h3>
        <div style="display:flex;gap:8px;" class="no-print">
            <button onclick="openMyMarksheet()" class="btn btn-primary btn-sm">
                <i class="fas fa-file-alt"></i> View Marksheet
            </button>
            <button onclick="printSection('resultCard')" class="btn btn-outline btn-sm">
                <i class="fas fa-print"></i> Print
            </button>
        </div>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Subject</th>
                        <th>Full Marks</th>
                        <th>Pass Marks</th>
                        <th>Obtained</th>
                        <th>Grade</th>
                        <th>Status</th>
                        <th>Bar</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $i => $d):
                        $dFull      = (int)($d['sub_full'] ?: $d['full_marks'] ?: 100);
                        $dPass      = (int)($d['sub_pass'] ?: $d['pass_marks'] ?: 40);
                        $passed_sub = $d['marks_obtained'] >= $dPass;
                        $sub_pct    = ($dFull > 0) ? round(($d['marks_obtained']/$dFull)*100,1) : 0;
                        $dGrade     = calculateGrade((float)$d['marks_obtained'], $dFull);
                    ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($d['subject_name']) ?></strong></td>
                        <td><?= $dFull ?></td>
                        <td><?= $dPass ?></td>
                        <td style="font-size:1.05rem;font-weight:800;color:<?= $passed_sub?'var(--success)':'var(--danger)' ?>">
                            <?= $d['marks_obtained'] ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $gradeColors[$d['grade']] ?? 'gray' ?>">
                                <?= htmlspecialchars($d['grade']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $passed_sub ? 'success' : 'danger' ?>">
                                <?= $passed_sub ? 'Pass' : 'Fail' ?>
                            </span>
                        </td>
                        <td style="min-width:120px;">
                            <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden;">
                                <div style="width:<?= $sub_pct ?>%;height:100%;background:<?= $passed_sub?'var(--success)':'var(--danger)' ?>;border-radius:3px;"></div>
                            </div>
                            <span style="font-size:0.70rem;color:var(--text-secondary);"><?= $sub_pct ?>%</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr style="background:var(--bg);">
                        <td colspan="4"><strong>Total</strong></td>
                        <td><strong style="font-size:1.05rem;"><?= $totalObtained ?>/<?= $totalFull ?></strong></td>
                        <td><span class="badge badge-<?= $gradeColors[$overallGrade] ?? 'gray' ?>"><?= $overallGrade ?></span></td>
                        <td colspan="2"><strong><?= $overallPct ?>% – <?= $passed ? 'PASSED' : 'FAILED' ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php elseif ($selectedExamId): ?>
<div class="empty-state card" style="padding:40px;">
    <i class="fas fa-star-half-alt"></i>
    <h3>No Marks Entered Yet</h3>
    <p>Marks for this exam have not been entered yet.</p>
</div>
<?php endif; ?>
<?php endif; ?>

<?php if ($selectedExamId && !empty($details)): ?>
<script src="../assets/js/marksheet.js?v=1"></script>
<script>
function openMyMarksheet() {
    gmsOpenMarksheet(
        <?= json_encode(['first_name'=>$student['first_name'] ?? '', 'last_name'=>$student['last_name'] ?? '', 'student_id'=>$student['student_id'] ?? '', 'class_name'=>$student['class_name'] ?? '', 'section'=>$student['section'] ?? '']) ?>,
        <?= json_encode(array_map(fn($d) => ['subject_id'=>(int)$d['subject_id'], 'marks_obtained'=>$d['marks_obtained']], $details)) ?>,
        <?= json_encode($classSubjects) ?>,
        {
            examName:  <?= json_encode($selExam['exam_name'] ?? '') ?>,
            examType:  <?= json_encode($selExam['exam_type'] ?? '') ?>,
            examDate:  <?= json_encode(formatDate($selExam['start_date'] ?? '')) ?>,
            printedOn: <?= json_encode(formatDate(date('Y-m-d'))) ?>,
            logo:      new URL('../assets/images/gms.png', location.href).href
        }
    );
}
</script>
<?php endif; ?>

<?php require_once '../includes/student_footer.php'; ?>