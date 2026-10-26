<?php
// GET /api/v1/teacher/exams.php — exams of my classes (newest first)
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth    = api_auth('teacher');
$classes = api_teacher_classes($auth['ref_id']);
if (!$classes) api_ok(['exams' => []]);

$byId = []; foreach ($classes as $c) $byId[$c['id']] = $c;
$ids  = array_keys($byId);
$rp   = api_results_published_sql();
$rows = db()->fetchAll(
    "SELECT e.*, $rp AS results_flag FROM exams e
     WHERE e.class_id IN (" . implode(',', array_fill(0, count($ids), '?')) . ")
     ORDER BY e.created_at DESC, e.id DESC",
    str_repeat('i', count($ids)), $ids
);

api_ok(['exams' => array_map(fn($e) => [
    'id'                => (int)$e['id'],
    'exam_name'         => $e['exam_name'],
    'exam_type'         => $e['exam_type'],
    'class'             => ['id' => (int)$e['class_id'], 'name' => $byId[$e['class_id']]['name'], 'section' => $byId[$e['class_id']]['section'], 'label' => $byId[$e['class_id']]['label']],
    'start_date'        => $e['start_date'],
    'start_date_bs'     => api_bs($e['start_date']),
    'end_date'          => $e['end_date'],
    'end_date_bs'       => api_bs($e['end_date']),
    'is_published'      => (bool)$e['is_published'],
    'results_published' => (bool)$e['results_flag'],
    'can_enter_marks'   => $byId[$e['class_id']]['is_class_teacher'],
], $rows)]);
