<?php
// GET  /api/v1/teacher/attendance.php?class_id=3&date=2026-10-26
//      -> students of the class with their status for that date (null = not marked yet)
// POST /api/v1/teacher/attendance.php
//      {"class_id":3,"date":"2026-10-26","records":[{"student_id":12,"status":"Present"},{"student_id":13,"status":"Absent"}]}
//      status: Present | Absent | Late | Leave   (P / A also accepted)
// Only the class teacher of a class may mark its attendance (same rule as the web panel).
require __DIR__ . '/../_bootstrap.php';
$method = api_method('GET', 'POST');
$auth   = api_auth('teacher');

if ($method === 'GET') {
    $class = api_teacher_class_or_fail($auth['ref_id'], api_int($_GET['class_id'] ?? 0));
    $date  = (string)($_GET['date'] ?? date('Y-m-d'));
    if (!api_valid_date($date)) api_fail(422, 'date must look like 2026-10-26.', ['date' => 'Invalid.']);

    $rows = db()->fetchAll(
        "SELECT s.id, s.student_id, s.first_name, s.last_name, s.photo, a.status
         FROM students s
         LEFT JOIN attendance a ON a.student_id = s.id AND a.attendance_date = ?
         WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.first_name, s.last_name",
        'si', [$date, $class['id']]
    );

    $count = ['Present' => 0, 'Absent' => 0, 'Late' => 0, 'Leave' => 0];
    $unmarked = 0;
    foreach ($rows as $r) { if ($r['status']) $count[$r['status']]++; else $unmarked++; }

    api_ok([
        'class'    => $class,
        'date'     => $date,
        'date_bs'  => api_bs($date),
        'can_mark' => $class['is_class_teacher'],
        'summary'  => [
            'present' => $count['Present'], 'absent' => $count['Absent'], 'late' => $count['Late'],
            'leave'   => $count['Leave'],   'unmarked' => $unmarked,
        ],
        'students' => array_map(fn($s) => [
            'id'           => (int)$s['id'],
            'student_code' => $s['student_id'],
            'full_name'    => trim($s['first_name'] . ' ' . $s['last_name']),
            'photo_url'    => api_upload_url('students', $s['photo']),
            'status'       => $s['status'],
        ], $rows),
    ]);
}

// ---- POST: save attendance ----
$in      = api_input();
$classId = api_int($in['class_id'] ?? 0);
$date    = (string)($in['date'] ?? date('Y-m-d'));
$records = $in['records'] ?? null;

$class = api_teacher_class_or_fail($auth['ref_id'], $classId);
if (!$class['is_class_teacher']) api_fail(403, 'Only the class teacher can mark attendance for this class.');
if (!api_valid_date($date))      api_fail(422, 'date must look like 2026-10-26.', ['date' => 'Invalid.']);
if ($date > date('Y-m-d'))       api_fail(422, 'You cannot mark attendance for a future date.', ['date' => 'In the future.']);
if (!is_array($records) || !$records || count($records) > 500) {
    api_fail(422, 'records must be a non-empty list.', ['records' => 'Required.']);
}

$map = ['p' => 'Present', 'present' => 'Present', 'a' => 'Absent', 'absent' => 'Absent', 'late' => 'Late', 'leave' => 'Leave'];
$clean = [];
foreach ($records as $i => $r) {
    $sid = api_int($r['student_id'] ?? 0);
    $st  = $map[strtolower(trim((string)($r['status'] ?? '')))] ?? null;
    if (!$sid || !$st) api_fail(422, "Invalid record at position $i.", ['records' => "Each record needs student_id and status (Present, Absent, Late or Leave)."]);
    $clean[$sid] = $st;                      // a repeated student keeps the last value
}

// every student must be an active member of this class
$ids   = array_keys($clean);
$valid = db()->fetchAll(
    "SELECT id FROM students WHERE class_id = ? AND is_active = 1 AND id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")",
    'i' . str_repeat('i', count($ids)), array_merge([$classId], $ids)
);
if (count($valid) !== count($ids)) {
    api_fail(422, 'Some students do not belong to this class.', ['records' => 'Unknown student_id for this class.']);
}

$conn = db()->getConnection();
$conn->begin_transaction();
try {
    foreach ($clean as $sid => $st) {
        $ok = db()->execute(
            "INSERT INTO attendance (student_id, class_id, attendance_date, status, marked_by) VALUES (?,?,?,?,?)
             ON DUPLICATE KEY UPDATE status = VALUES(status), marked_by = VALUES(marked_by)",
            'iissi', [$sid, $classId, $date, $st, $auth['ref_id']]
        );
        if (!$ok) throw new RuntimeException(db()->lastError);
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    throw $e;
}
api_ok(['class_id' => $classId, 'date' => $date, 'saved' => count($clean)], 'Attendance saved for ' . count($clean) . ' student(s).');
