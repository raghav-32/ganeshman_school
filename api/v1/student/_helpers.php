<?php
// Shared by the student endpoints. Not an endpoint itself.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === '_helpers.php') { http_response_code(404); exit; }

// class_id of the signed-in student (0 when unassigned)
function api_student_class_id(array $auth): int {
    $r = db()->fetchOne("SELECT class_id FROM students WHERE id=?", 'i', [$auth['ref_id']]);
    return (int)($r['class_id'] ?? 0);
}

// Published results for the student's class, newest first. Only exams the student has marks in.
function api_student_result_exams(int $studentId, int $classId): array {
    if (!$classId) return [];
    $rp = api_results_published_sql();
    return db()->fetchAll(
        "SELECT e.id, e.exam_name, e.exam_type, e.start_date, c.class_name, c.section, e.class_id,
                SUM(m.marks_obtained) AS total_obtained, SUM(m.full_marks) AS total_full, COUNT(m.id) AS subject_count
         FROM exams e
         JOIN classes c ON e.class_id = c.id
         JOIN marks m ON m.exam_id = e.id AND m.student_id = ?
         WHERE $rp = 1 AND e.class_id = ?
         GROUP BY e.id, c.class_name, c.section
         ORDER BY e.created_at DESC, e.id DESC",
        'ii', [$studentId, $classId]
    );
}

function api_result_exam_payload(array $e): array {
    $full = (float)$e['total_full'];
    return [
        'exam_id'        => (int)$e['id'],
        'exam_name'      => $e['exam_name'],
        'exam_type'      => $e['exam_type'],
        'start_date'     => $e['start_date'],
        'start_date_bs'  => api_bs($e['start_date']),
        'class'          => api_class_payload($e),
        'total_obtained' => (float)$e['total_obtained'],
        'total_full'     => $full,
        'percentage'     => $full > 0 ? round($e['total_obtained'] / $full * 100, 1) : 0,
        'subject_count'  => (int)$e['subject_count'],
    ];
}
