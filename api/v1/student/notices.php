<?php
// GET /api/v1/student/notices.php?limit=50&offset=0
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
api_auth('student');
[$limit, $offset] = api_page();
api_ok(['notices' => api_notices('student', $limit, $offset), 'limit' => $limit, 'offset' => $offset]);
