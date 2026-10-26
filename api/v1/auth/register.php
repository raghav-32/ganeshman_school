<?php
// POST /api/v1/auth/register.php   (public, no token) — student self-registration.
// The request goes to the admin's "Pending registrations" list (same as signup.php);
// the student can sign in only after the admin approves it.
//
// Send as multipart/form-data (recommended, Retrofit @Multipart):
//   first_name, last_name, dob (YYYY-MM-DD), gender (Male|Female|Other), class_id, phone, email,
//   address, guardian_name, guardian_phone, username, password, confirm_password,
//   guardian_relation?, admission_date? (YYYY-MM-DD), photo (file: jpg/png/gif/webp, max 2 MB)
// ...or application/json with the same fields and "photo_base64" instead of the file.
require __DIR__ . '/../_bootstrap.php';
api_method('POST');

const REG_MAX_PER_HOUR = 30;     // flood guard for this public endpoint (all callers combined)

$in = api_input();
$f  = fn(string $k) => sanitize((string)($in[$k] ?? ''));       // same storage convention as signup.php

$firstName = $f('first_name');   $lastName  = $f('last_name');
$dob       = $f('dob');          $gender    = $f('gender');
$classId   = api_int($in['class_id'] ?? 0);
$phone     = $f('phone');        $email     = $f('email');
$address   = $f('address');
$gName     = $f('guardian_name'); $gPhone   = $f('guardian_phone'); $gRel = $f('guardian_relation');
$admDate   = $f('admission_date');
$username  = $f('username');
$password  = (string)($in['password'] ?? '');
$confirm   = $in['confirm_password'] ?? null;

$e = [];
$req = ['first_name' => $firstName, 'last_name' => $lastName, 'dob' => $dob, 'gender' => $gender, 'phone' => $phone,
        'email' => $email, 'address' => $address, 'guardian_name' => $gName, 'guardian_phone' => $gPhone, 'username' => $username];
foreach ($req as $k => $v) if ($v === '') $e[$k] = 'Required.';
if (!$classId) $e['class_id'] = 'Required.';

// field length limits match the pending_registrations columns
$lens = ['first_name' => [$firstName, 50], 'last_name' => [$lastName, 50], 'phone' => [$phone, 15], 'email' => [$email, 100],
         'guardian_name' => [$gName, 100], 'guardian_phone' => [$gPhone, 15], 'guardian_relation' => [$gRel, 30], 'username' => [$username, 50]];
foreach ($lens as $k => [$v, $n]) if (!isset($e[$k]) && mb_strlen($v) > $n) $e[$k] = "Must be at most $n characters.";

if (!isset($e['dob']) && (!api_valid_date($dob) || $dob > date('Y-m-d'))) $e['dob'] = 'Use a valid past date, YYYY-MM-DD.';
if (!isset($e['gender']) && !in_array($gender, ['Male', 'Female', 'Other'], true)) $e['gender'] = 'Must be Male, Female or Other.';
if (!isset($e['email']) && !filter_var($email, FILTER_VALIDATE_EMAIL)) $e['email'] = 'Enter a valid email address.';
if ($admDate !== '' && !api_valid_date($admDate)) $e['admission_date'] = 'Use YYYY-MM-DD.';
if (!isset($e['username']) && mb_strlen($username) < 4) $e['username'] = 'Must be at least 4 characters.';
if (strlen($password) < 6) $e['password'] = 'Must be at least 6 characters.';
elseif ($confirm !== null && $password !== (string)$confirm) $e['confirm_password'] = 'Passwords do not match.';
if ($classId && !db()->fetchOne("SELECT id FROM classes WHERE id=?", 'i', [$classId])) $e['class_id'] = 'Unknown class.';

// ---- photo (required): multipart file or base64, verified from the actual image bytes ----
$bytes = null;
if (!empty($_FILES['photo']['name'])) {
    if ($_FILES['photo']['error'] !== UPLOAD_ERR_OK) {
        $e['photo'] = $_FILES['photo']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['photo']['error'] === UPLOAD_ERR_FORM_SIZE
            ? 'Photo must be under 2MB.' : 'Photo upload failed. Try again.';
    } else {
        $bytes = file_get_contents($_FILES['photo']['tmp_name']);
    }
} elseif (!empty($in['photo_base64']) && is_string($in['photo_base64'])) {
    $b64   = preg_replace('#^data:image/[a-z+.-]+;base64,#i', '', $in['photo_base64']);
    $bytes = base64_decode($b64, true);
    if ($bytes === false) $e['photo'] = 'photo_base64 is not valid base64.';
} else {
    $e['photo'] = 'Student photo is required.';
}
$ext = null;
if ($bytes !== null && !isset($e['photo'])) {
    $types = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp'];
    $info  = strlen($bytes) ? @getimagesizefromstring($bytes) : false;
    if (strlen($bytes) > 2 * 1024 * 1024)            $e['photo'] = 'Photo must be under 2MB.';
    elseif (!$info || !isset($types[$info[2]]))       $e['photo'] = 'Photo must be a JPG, PNG, GIF or WEBP image.';
    else                                              $ext = $types[$info[2]];
}

// ---- uniqueness (users, plus other pending requests — a pending username would collide on approval) ----
if (!isset($e['username']) && $username !== '') {
    if (db()->fetchOne("SELECT id FROM users WHERE username=?", 's', [$username])
     || db()->fetchOne("SELECT id FROM pending_registrations WHERE username=? AND status='pending'", 's', [$username])) {
        $e['username'] = 'This username is already taken.';
    }
}
if (!isset($e['email']) && $email !== '') {
    if (db()->fetchOne("SELECT id FROM users WHERE email=?", 's', [$email])) $e['email'] = 'This email is already registered.';
    elseif (db()->fetchOne("SELECT id FROM pending_registrations WHERE email=? AND status='pending'", 's', [$email])) {
        $e['email'] = 'This email already has a pending registration.';
    }
}
if ($e) api_fail(422, 'Please fix the highlighted fields.', $e);

// submitted_at is filled by the database clock, so compare with the database clock (PHP runs on Nepal time).
$recent = db()->fetchOne("SELECT COUNT(*) AS c FROM pending_registrations WHERE submitted_at > NOW() - INTERVAL 1 HOUR");
if ((int)$recent['c'] >= REG_MAX_PER_HOUR) api_fail(429, 'Too many registrations right now. Please try again later.');

// ---- save photo, then the request ----
$dir = __DIR__ . '/../../../assets/uploads/students/';
if (!is_dir($dir)) @mkdir($dir, 0755, true);
$photoName = 'student_' . time() . '_' . random_int(1000, 9999) . '.' . $ext;
if (!is_writable($dir) || file_put_contents($dir . $photoName, $bytes) === false) {
    error_log('[api/v1] cannot write registration photo to ' . $dir);
    api_fail(500, 'Could not save the photo. Please try again later.');
}

$ok = db()->execute(
    "INSERT INTO pending_registrations
     (first_name, last_name, dob, gender, class_id, phone, email, address, guardian_name, guardian_phone,
      guardian_relation, admission_date, photo, username, password_hash)
     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
    'ssssissssssssss',
    [$firstName, $lastName, $dob, $gender, $classId, $phone, $email, $address, $gName, $gPhone,
     $gRel, $admDate !== '' ? $admDate : null, $photoName, $username, hashPassword($password)]
);
if (!$ok) {
    @unlink($dir . $photoName);
    error_log('[api/v1] registration insert failed: ' . db()->lastError);
    api_fail(500, 'Registration failed. Please try again.');
}

api_ok(
    ['registration' => ['id' => db()->lastInsertId(), 'username' => $username, 'status' => 'pending']],
    'Registration submitted. You can sign in once the school approves your request.', 201
);
