<?php
// POST /api/v1/auth/login.php   {username, password, device_name?}
// username may be the username or the registered email (same as the web login).
require __DIR__ . '/../_bootstrap.php';
api_method('POST');

$in       = api_input();
$login    = trim((string)($in['username'] ?? ''));
$password = (string)($in['password'] ?? '');
if ($login === '' || $password === '') {
    api_fail(422, 'Enter your username/email and password.');
}

$user = db()->fetchOne(
    "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
    'ss', [$login, $login]
);

if (!$user) {
    $pending = db()->fetchOne("SELECT status FROM pending_registrations WHERE (username=? OR email=?) AND status IN ('pending','rejected') ORDER BY id DESC LIMIT 1", 'ss', [$login, $login]);
    if ($pending && $pending['status'] === 'pending') {
        api_fail(403, 'Your registration is pending approval. Please wait for the school to review your application.');
    }
    if ($pending) {
        api_fail(403, 'Your registration was not approved. Please contact the school.');
    }
    api_fail(401, 'Invalid username/email or password.');
}
if ($user['password'] === 'RESET_REQUIRED') {
    api_fail(403, 'Your password has not been set yet. Please contact the school office.');
}
if (!password_verify($password, $user['password'])) {
    api_fail(401, 'Invalid username/email or password.');
}
if (!in_array($user['role'], ['student', 'teacher'], true)) {
    api_fail(403, 'This app is for students and teachers only.');
}

$table = $user['role'] === 'student' ? 'students' : 'teachers';
$p = db()->fetchOne("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM $table WHERE user_id=?", 'i', [$user['id']]);
if (!$p) api_fail(403, 'No ' . $user['role'] . ' profile is linked to this account.');

db()->execute("UPDATE users SET last_login=? WHERE id=?", 'si', [date('Y-m-d H:i:s'), $user['id']]);
$t = api_issue_token((int)$user['id'], isset($in['device_name']) ? trim((string)$in['device_name']) : null);

$auth = ['role' => $user['role'], 'ref_id' => (int)$p['id']];
api_ok([
    'token'      => $t['token'],
    'token_type' => 'Bearer',
    'expires_at' => $t['expires_at'],
    'role'       => $user['role'],
    'profile'    => api_profile($auth),
], 'Signed in.');
