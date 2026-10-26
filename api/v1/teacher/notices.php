<?php
// GET /api/v1/teacher/notices.php?limit=50&offset=0
require __DIR__ . '/../_bootstrap.php';
api_method('GET');
api_auth('teacher');
[$limit, $offset] = api_page();
api_ok(['notices' => api_notices('teacher', $limit, $offset), 'limit' => $limit, 'offset' => $offset]);
