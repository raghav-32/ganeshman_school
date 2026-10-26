<?php
// POST /api/v1/auth/change_password.php   {current_password, new_password}
// Signs out every OTHER device; the current token stays valid.
require __DIR__ . '/../_bootstrap.php';
api_method('POST');
$auth = api_auth();

$in  = api_input();
$cur = (string)($in['current_password'] ?? '');
$new = (string)($in['new_password'] ?? '');

$errors = [];
if ($cur === '') $errors['current_password'] = 'Required.';
if (strlen($new) < 6) $errors['new_password'] = 'Must be at least 6 characters.';
if ($errors) api_fail(422, 'Please fix the highlighted fields.', $errors);

$u = db()->fetchOne("SELECT password FROM users WHERE id=?", 'i', [$auth['user_id']]);
if (!$u || !password_verify($cur, $u['password'])) {
    api_fail(422, 'Current password is incorrect.', ['current_password' => 'Incorrect.']);
}
if ($cur === $new) {
    api_fail(422, 'New password must be different.', ['new_password' => 'Must differ from the current password.']);
}

db()->execute("UPDATE users SET password=? WHERE id=?", 'si', [hashPassword($new), $auth['user_id']]);
db()->execute("DELETE FROM api_tokens WHERE user_id=? AND id<>?", 'ii', [$auth['user_id'], $auth['token_id']]);
api_ok([], 'Password changed.');
