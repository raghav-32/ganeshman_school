<?php
// GET /api/v1/student/profile.php
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth = api_auth('student');
api_ok(['profile' => api_profile($auth)]);
