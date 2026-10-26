<?php
// GET /api/v1/student/dashboard.php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/_helpers.php';
api_method('GET');
$auth = api_auth('student');

$profile = api_student_profile($auth['ref_id']);
if (!$profile) api_fail(404, 'Student not found.');
$classId = $profile['class']['id'] ?? 0;

$att = db()->fetchOne("SELECT " . API_ATT_SUMMARY_SQL . " FROM attendance WHERE student_id=?", 'i', [$auth['ref_id']]);

// Only results the school has published (the web dashboard used the exam "published" flag here,
// which could show marks before results were released).
$latest = array_map('api_result_exam_payload', array_slice(api_student_result_exams($auth['ref_id'], $classId), 0, 4));

api_ok([
    'today'         => ['date' => date('Y-m-d'), 'date_bs' => api_bs(date('Y-m-d')), 'weekday' => date('l')],
    'student'       => [
        'id' => $profile['id'], 'student_code' => $profile['student_code'], 'first_name' => $profile['first_name'],
        'full_name' => $profile['full_name'], 'class' => $profile['class'], 'photo_url' => $profile['photo_url'],
    ],
    'attendance'    => api_attendance_summary($att ?: []),
    'latest_results'=> $latest,
    'fees'          => api_student_fees($auth['ref_id'], $classId)['summary'],
    'issued_books'  => api_issued_books($auth['ref_id']),
    'notices'       => api_notices('student', 4),
]);
