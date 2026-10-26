<?php
// GET /api/v1/teacher/classes.php — classes I teach / am class teacher of
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth = api_auth('teacher');
api_ok(['classes' => api_teacher_classes($auth['ref_id'])]);
