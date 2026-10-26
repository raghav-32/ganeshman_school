<?php
// GET /api/v1/teacher/students.php?class_id=3   — students of one of my classes, with attendance rate
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth  = api_auth('teacher');
$class = api_teacher_class_or_fail($auth['ref_id'], api_int($_GET['class_id'] ?? 0));

$rows = db()->fetchAll(
    "SELECT s.id, s.student_id, s.first_name, s.last_name, s.gender, s.dob, s.phone, s.photo,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id AND a.status = 'Present') AS present_days,
            (SELECT COUNT(*) FROM attendance a WHERE a.student_id = s.id) AS total_days
     FROM students s WHERE s.class_id = ? AND s.is_active = 1 ORDER BY s.first_name, s.last_name",
    'i', [$class['id']]
);

api_ok([
    'class'    => $class,
    'students' => array_map(fn($s) => [
        'id'                    => (int)$s['id'],
        'student_code'          => $s['student_id'],
        'first_name'            => $s['first_name'],
        'last_name'             => $s['last_name'],
        'full_name'             => trim($s['first_name'] . ' ' . $s['last_name']),
        'gender'                => $s['gender'],
        'dob'                   => $s['dob'],
        'dob_bs'                => api_bs($s['dob']),
        'phone'                 => $s['phone'],
        'photo_url'             => api_upload_url('students', $s['photo']),
        'present_days'          => (int)$s['present_days'],
        'total_days'            => (int)$s['total_days'],
        'attendance_percentage' => $s['total_days'] > 0 ? round($s['present_days'] / $s['total_days'] * 100, 1) : 0,
    ], $rows),
]);
