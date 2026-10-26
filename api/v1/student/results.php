<?php
// GET /api/v1/student/results.php              -> published exams with totals
// GET /api/v1/student/results.php?exam_id=12   -> that exam, subject by subject
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/_helpers.php';
api_method('GET');
$auth = api_auth('student');

$classId = api_student_class_id($auth);
$exams   = api_student_result_exams($auth['ref_id'], $classId);
$list    = array_map('api_result_exam_payload', $exams);

if (!isset($_GET['exam_id'])) api_ok(['exams' => $list]);

$examId = api_int($_GET['exam_id']);
$exam   = null;
foreach ($list as $e) { if ($e['exam_id'] === $examId) { $exam = $e; break; } }
if (!$exam) api_fail(404, 'Result not found or not published yet.');

$rows = db()->fetchAll(
    "SELECT m.*, sub.subject_name, sub.full_marks AS sub_full, sub.pass_marks AS sub_pass
     FROM marks m JOIN subjects sub ON m.subject_id = sub.id
     WHERE m.exam_id = ? AND m.student_id = ? ORDER BY sub.subject_name",
    'ii', [$examId, $auth['ref_id']]
);

// Same rules as student/results.php
$subjects = []; $totalObtained = 0.0; $totalFull = 0; $failedSubject = false;
foreach ($rows as $d) {
    $full  = (int)($d['sub_full'] ?: $d['full_marks'] ?: 100);
    $pass  = (int)($d['sub_pass'] ?: $d['pass_marks'] ?: 40);
    $got   = (float)$d['marks_obtained'];
    $ok    = $got >= $pass;
    if (!$ok) $failedSubject = true;
    $totalObtained += $got; $totalFull += $full;
    $subjects[] = [
        'subject_id'     => (int)$d['subject_id'],
        'subject_name'   => $d['subject_name'],
        'marks_obtained' => $got,
        'full_marks'     => $full,
        'pass_marks'     => $pass,
        'percentage'     => $full > 0 ? round($got / $full * 100, 1) : 0,
        'grade'          => calculateGrade($got, $full),
        'passed'         => $ok,
        'remarks'        => $d['remarks'],
    ];
}
$grade = calculateGrade($totalObtained, $totalFull ?: 100);

api_ok(['result' => $exam + [
    'subjects'       => $subjects,
    'total_obtained' => $totalObtained,
    'total_full'     => $totalFull,
    'percentage'     => $totalFull > 0 ? round($totalObtained / $totalFull * 100, 1) : 0,
    'grade'          => $grade,
    'passed'         => $grade !== 'F' && !$failedSubject,
]]);
