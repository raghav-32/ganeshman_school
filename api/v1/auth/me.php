<?php
// GET /api/v1/auth/me.php  — the signed-in student's or teacher's profile
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
$auth = api_auth();
api_ok(['role' => $auth['role'], 'profile' => api_profile($auth)]);
