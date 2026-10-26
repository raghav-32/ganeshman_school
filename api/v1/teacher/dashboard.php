<?php
// GET /api/v1/teacher/dashboard.php
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth = api_auth('teacher');

$today  = date('Y-m-d');
$marked = db()->fetchOne(
    "SELECT COUNT(DISTINCT class_id) AS c FROM attendance WHERE marked_by=? AND attendance_date=?",
    'is', [$auth['ref_id'], $today]
);

api_ok([
    'today'                 => ['date' => $today, 'date_bs' => api_bs($today), 'weekday' => date('l')],
    'teacher'               => api_teacher_profile($auth['ref_id']),
    'classes'               => api_teacher_classes($auth['ref_id']),
    'classes_marked_today'  => (int)($marked['c'] ?? 0),
    'notices'               => api_notices('teacher', 4),
]);
