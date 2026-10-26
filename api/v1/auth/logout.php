<?php
// POST /api/v1/auth/logout.php   (Authorization: Bearer <token>)  — revokes this device's token
require __DIR__ . '/../_bootstrap.php';
api_method('POST');
$auth = api_auth();
db()->execute("DELETE FROM api_tokens WHERE id=?", 'i', [$auth['token_id']]);
api_ok([], 'Signed out.');
