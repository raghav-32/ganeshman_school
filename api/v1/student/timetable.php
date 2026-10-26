<?php
// GET /api/v1/student/timetable.php  — weekly timetable, Sunday..Friday (all six days always present)
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/_helpers.php';
api_method('GET');
$auth = api_auth('student');

$classId = api_student_class_id($auth);
$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
$by   = array_fill_keys($days, []);

if ($classId) {
    $rows = db()->fetchAll(
        "SELECT tt.*, t.first_name, t.last_name, sub.subject_name
         FROM timetable tt
         JOIN teachers t  ON tt.teacher_id = t.id
         JOIN subjects sub ON tt.subject_id = sub.id
         WHERE tt.class_id = ?
         ORDER BY FIELD(tt.day_of_week,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), tt.start_time",
        'i', [$classId]
    );
    foreach ($rows as $r) {
        $d = trim($r['day_of_week']);
        if (!isset($by[$d])) continue;
        $by[$d][] = [
            'id'           => (int)$r['id'],
            'subject_id'   => (int)$r['subject_id'],
            'subject_name' => $r['subject_name'],
            'teacher_name' => trim($r['first_name'] . ' ' . $r['last_name']),
            'start_time'   => api_time($r['start_time']),
            'end_time'     => api_time($r['end_time']),
            'room_number'  => $r['room_number'],
        ];
    }
}

$today = date('l');
api_ok(['today' => $today, 'days' => array_map(
    fn($d) => ['day' => $d, 'is_today' => $d === $today, 'periods' => $by[$d]], $days
)]);
