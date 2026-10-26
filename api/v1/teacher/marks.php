<?php
// GET  /api/v1/teacher/marks.php?exam_id=5[&subject_id=9]
//      -> subjects of the exam's class, its students, and (when subject_id is given) their marks
// POST /api/v1/teacher/marks.php
//      {"exam_id":5,"subject_id":9,"marks":[{"student_id":12,"marks_obtained":78.5},{"student_id":13,"marks_obtained":40}]}
// Only the class teacher of the exam's class may enter marks (same rule as the web panel).
require __DIR__ . '/../_bootstrap.php';
$method = api_method('GET', 'POST');
$auth   = api_auth('teacher');

$in     = $method === 'POST' ? api_input() : $_GET;
$examId = api_int($in['exam_id'] ?? 0);
if (!$examId) api_fail(422, 'exam_id is required.', ['exam_id' => 'Required.']);

$exam = db()->fetchOne("SELECT e.*, c.class_name, c.section FROM exams e JOIN classes c ON e.class_id = c.id WHERE e.id = ?", 'i', [$examId]);
if (!$exam) api_fail(404, 'Exam not found.');
$class = api_teacher_class_or_fail($auth['ref_id'], (int)$exam['class_id']);   // 403 if not my class

$subjects = db()->fetchAll("SELECT * FROM subjects WHERE class_id = ? ORDER BY subject_name", 'i', [$class['id']]);
$subjectId = api_int($in['subject_id'] ?? 0);
$subject = null;
foreach ($subjects as $s) { if ((int)$s['id'] === $subjectId) $subject = $s; }
if ($subjectId && !$subject) api_fail(404, 'Subject not found in this exam\'s class.');

$subjectPayload = fn($s) => [
    'id' => (int)$s['id'], 'subject_name' => $s['subject_name'], 'subject_code' => $s['subject_code'],
    'full_marks' => (int)$s['full_marks'], 'pass_marks' => (int)$s['pass_marks'],
];

if ($method === 'GET') {
    $students = db()->fetchAll(
        "SELECT s.id, s.student_id, s.first_name, s.last_name, m.marks_obtained, m.grade
         FROM students s
         LEFT JOIN marks m ON m.student_id = s.id AND m.exam_id = ? AND m.subject_id = ?
         WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.first_name, s.last_name",
        'iii', [$examId, $subjectId, $class['id']]
    );
    api_ok([
        'exam'            => [
            'id' => (int)$exam['id'], 'exam_name' => $exam['exam_name'], 'exam_type' => $exam['exam_type'],
            'class' => $class, 'start_date' => $exam['start_date'], 'start_date_bs' => api_bs($exam['start_date']),
        ],
        'can_enter_marks' => $class['is_class_teacher'],
        'subjects'        => array_map($subjectPayload, $subjects),
        'subject'         => $subject ? $subjectPayload($subject) : null,
        'students'        => array_map(fn($s) => [
            'id'             => (int)$s['id'],
            'student_code'   => $s['student_id'],
            'full_name'      => trim($s['first_name'] . ' ' . $s['last_name']),
            'marks_obtained' => $s['marks_obtained'] === null ? null : (float)$s['marks_obtained'],
            'grade'          => $s['grade'],
        ], $students),
    ]);
}

// ---- POST: save marks ----
if (!$class['is_class_teacher']) api_fail(403, 'Only the class teacher can enter marks for this class.');
if (!$subject) api_fail(422, 'subject_id is required.', ['subject_id' => 'Required.']);
$marks = $in['marks'] ?? null;
if (!is_array($marks) || !$marks || count($marks) > 500) api_fail(422, 'marks must be a non-empty list.', ['marks' => 'Required.']);

$full = (int)$subject['full_marks']; $pass = (int)$subject['pass_marks'];
$clean = [];
foreach ($marks as $i => $m) {
    $sid = api_int($m['student_id'] ?? 0);
    $raw = $m['marks_obtained'] ?? null;
    if (!$sid || !is_numeric($raw)) api_fail(422, "Invalid entry at position $i.", ['marks' => 'Each entry needs student_id and a numeric marks_obtained.']);
    $v = round((float)$raw, 2);
    if ($v < 0 || $v > $full) api_fail(422, "Marks for student $sid must be between 0 and $full.", ['marks' => "Out of range at position $i."]);
    $clean[$sid] = $v;
}

$ids   = array_keys($clean);
$valid = db()->fetchAll(
    "SELECT id FROM students WHERE class_id = ? AND is_active = 1 AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
    'i' . str_repeat('i', count($ids)), array_merge([$class['id']], $ids)
);
if (count($valid) !== count($ids)) api_fail(422, 'Some students do not belong to this class.', ['marks' => 'Unknown student_id for this class.']);

$conn = db()->getConnection();
$conn->begin_transaction();
try {
    foreach ($clean as $sid => $v) {
        $ok = db()->execute(
            "INSERT INTO marks (student_id, exam_id, subject_id, marks_obtained, full_marks, pass_marks, grade, entered_by) VALUES (?,?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE marks_obtained = VALUES(marks_obtained), full_marks = VALUES(full_marks),
                                     pass_marks = VALUES(pass_marks), grade = VALUES(grade), entered_by = VALUES(entered_by)",
            'iiidiisi', [$sid, $examId, $subject['id'], $v, $full, $pass, calculateGrade($v, $full), $auth['ref_id']]
        );
        if (!$ok) throw new RuntimeException(db()->lastError);
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
api_ok(['exam_id' => $examId, 'subject_id' => (int)$subject['id'], 'saved' => count($clean)], 'Marks saved for ' . count($clean) . ' student(s).');
