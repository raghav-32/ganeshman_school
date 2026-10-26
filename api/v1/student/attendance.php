<?php
// GET /api/v1/student/attendance.php?month=YYYY-MM   (month defaults to the current month, AD)
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth = api_auth('student');

$month = (string)($_GET['month'] ?? date('Y-m'));
if (!api_valid_month($month)) api_fail(422, 'month must look like 2026-10.', ['month' => 'Invalid.']);
$from = $month . '-01';
$to   = date('Y-m-d', strtotime("$from +1 month"));

$overall = db()->fetchOne("SELECT " . API_ATT_SUMMARY_SQL . " FROM attendance WHERE student_id=?", 'i', [$auth['ref_id']]);
$monthly = db()->fetchOne(
    "SELECT " . API_ATT_SUMMARY_SQL . " FROM attendance WHERE student_id=? AND attendance_date >= ? AND attendance_date < ?",
    'iss', [$auth['ref_id'], $from, $to]
);
$rows = db()->fetchAll(
    "SELECT id, attendance_date, status, remarks FROM attendance
     WHERE student_id=? AND attendance_date >= ? AND attendance_date < ? ORDER BY attendance_date DESC",
    'iss', [$auth['ref_id'], $from, $to]
);

api_ok([
    'month'         => $month,
    'summary'       => api_attendance_summary($overall ?: []),
    'month_summary' => api_attendance_summary($monthly ?: []),
    'records'       => array_map(fn($r) => [
        'id'      => (int)$r['id'],
        'date'    => $r['attendance_date'],
        'date_bs' => api_bs($r['attendance_date']),
        'weekday' => date('l', strtotime($r['attendance_date'])),
        'status'  => $r['status'],
        'remarks' => $r['remarks'],
    ], $rows),
]);
