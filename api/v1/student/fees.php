<?php
// GET /api/v1/student/fees.php
require __DIR__ . '/../_bootstrap.php';
require __DIR__ . '/_helpers.php';
api_method('GET');
$auth = api_auth('student');
api_ok(api_student_fees($auth['ref_id'], api_student_class_id($auth)));
