<?php
// GET /api/v1/student/exams.php   — published exam schedules for the student's class
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/_helpers.php';
api_method('GET');
$auth = api_auth('student');

$classId = api_student_class_id($auth);
$exams   = [];
if ($classId) {
    $rp = api_results_published_sql();
    $exams = db()->fetchAll(
        "SELECT e.*, $rp AS results_flag, c.class_name, c.section
         FROM exams e JOIN classes c ON e.class_id = c.id
         WHERE e.class_id = ? AND e.is_published = 1 ORDER BY e.start_date ASC, e.id ASC",
        'i', [$classId]
    );
}

$sched = [];
if ($exams && api_has_table('exam_schedule')) {
    $ids = implode(',', array_map('intval', array_column($exams, 'id')));
    foreach (db()->fetchAll(
        "SELECT es.*, sub.subject_name FROM exam_schedule es JOIN subjects sub ON es.subject_id = sub.id
         WHERE es.exam_id IN ($ids) ORDER BY es.exam_date ASC, es.start_time ASC"
    ) as $s) {
        $sched[(int)$s['exam_id']][] = $s;
    }
}

$today = date('Y-m-d');
api_ok(['exams' => array_map(function ($e) use ($sched, $today) {
    return [
        'id'                => (int)$e['id'],
        'exam_name'         => $e['exam_name'],
        'exam_type'         => $e['exam_type'],
        'class'             => api_class_payload($e),
        'start_date'        => $e['start_date'],
        'start_date_bs'     => api_bs($e['start_date']),
        'end_date'          => $e['end_date'],
        'end_date_bs'       => api_bs($e['end_date']),
        'status'            => ($e['end_date'] && $e['end_date'] < $today) ? 'completed'
                               : (($e['start_date'] && $e['start_date'] <= $today) ? 'ongoing' : 'upcoming'),
        'results_published' => (bool)$e['results_flag'],
        'schedule'          => array_map(fn($s) => [
            'subject_id'   => (int)$s['subject_id'],
            'subject_name' => $s['subject_name'],
            'date'         => $s['exam_date'],
            'date_bs'      => api_bs($s['exam_date']),
            'weekday'      => date('l', strtotime($s['exam_date'])),
            'start_time'   => api_time($s['start_time'] ?: ($e['start_time'] ?? null)),
            'end_time'     => api_time($s['end_time'] ?: ($e['end_time'] ?? null)),
            'venue'        => $s['venue'],
            'notes'        => $s['notes'],
            'is_today'     => $s['exam_date'] === $today,
        ], $sched[(int)$e['id']] ?? []),
    ];
}, $exams)]);
