<?php
require_once 'includes/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect($_SESSION['role'] . '/dashboard.php');
}

$error   = '';
$success = '';

// ── Fetch classes for dropdown ────────────────────────────────
$classes = db()->fetchAll("SELECT id, class_name, section FROM classes ORDER BY class_name, section");

// ── POST Handler ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Required fields
    $firstName     = sanitize($_POST['first_name']        ?? '');
    $lastName      = sanitize($_POST['last_name']         ?? '');
    $dob           = sanitize($_POST['dob']               ?? '');
    $gender        = sanitize($_POST['gender']            ?? '');
    $classId       = (int)($_POST['class_id']             ?? 0);
    $phone         = sanitize($_POST['phone']             ?? '');
    $email         = sanitize($_POST['email']              ?? '');
    $address       = sanitize($_POST['address']           ?? '');
    $guardianName  = sanitize($_POST['guardian_name']     ?? '');
    $guardianPhone = sanitize($_POST['guardian_phone']    ?? '');
    $username      = sanitize($_POST['username']          ?? '');
    $password      = $_POST['password']                   ?? '';
    $confirmPass   = $_POST['confirm_password']           ?? '';

    // Optional fields
    $guardianRel   = sanitize($_POST['guardian_relation'] ?? '');
    $admDate       = sanitize($_POST['admission_date']    ?? '');

    // Validation
    $errors = [];
    if (!$firstName)     $errors[] = 'First Name is required.';
    if (!$lastName)      $errors[] = 'Last Name is required.';
    if (!$dob)           $errors[] = 'Date of Birth is required.';
    if (!$gender)        $errors[] = 'Gender is required.';
    if (!$classId)       $errors[] = 'Class is required.';
    if (!$phone)         $errors[] = 'Phone Number is required.';
    if (!$email)         $errors[] = 'Email is required.';
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address.';
    if (!$address)       $errors[] = 'Address is required.';
    if (!$guardianName)  $errors[] = 'Guardian Name is required.';
    if (!$guardianPhone) $errors[] = 'Guardian Phone is required.';
    if (!$username)      $errors[] = 'Username is required.';
    if (strlen($username) < 4) $errors[] = 'Username must be at least 4 characters.';
    if (!$password)      $errors[] = 'Password is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirmPass) $errors[] = 'Passwords do not match.';

    // Photo validation
    $photoName = null;
    if (empty($_FILES['photo']['name'])) {
        $errors[] = 'Student photo is required.';
    } else {
        $allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($_FILES['photo']['type'], $allowed)) {
            $errors[] = 'Photo must be JPG, PNG, GIF, or WEBP.';
        } elseif ($_FILES['photo']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Photo must be under 2MB.';
        }
    }

    // Check username uniqueness
    if (!$errors) {
        $existing = db()->fetchOne("SELECT id FROM users WHERE username=?", 's', [$username]);
        if ($existing) $errors[] = 'Username "' . htmlspecialchars($username) . '" is already taken.';
    }
    // Check email uniqueness (across active users and other pending requests)
    if (!$errors) {
        $existingEmail = db()->fetchOne("SELECT id FROM users WHERE email=?", 's', [$email]);
        if ($existingEmail) {
            $errors[] = 'Email "' . htmlspecialchars($email) . '" is already registered.';
        } else {
            $pendingEmail = db()->fetchOne("SELECT id FROM pending_registrations WHERE email=? AND status='pending'", 's', [$email]);
            if ($pendingEmail) $errors[] = 'Email "' . htmlspecialchars($email) . '" already has a pending registration.';
        }
    }

    if ($errors) {
        $error = implode('<br>', $errors);
    } else {
        // Save photo to temp location (pending approval)
        $dir = 'assets/uploads/students/';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $ext       = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
        $photoName = 'student_' . time() . '_' . mt_rand(1000, 9999) . '.' . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], $dir . $photoName);

        // Hash password
        $hashedPass = hashPassword($password);

        // Insert into pending_registrations table
        $ok = db()->execute(
            "INSERT INTO pending_registrations
             (first_name, last_name, dob, gender, class_id, phone, email, address,
              guardian_name, guardian_phone, guardian_relation,
              admission_date, photo, username, password_hash)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
            'ssssissssssssss',
            [
                $firstName, $lastName, $dob, $gender, $classId, $phone, $email, $address,
                $guardianName, $guardianPhone, $guardianRel,
                $admDate ?: '', $photoName, $username, $hashedPass
            ]
        );

        if ($ok) {
            $success = 'Your registration request has been submitted successfully! Please wait for admin approval. You will be able to login once your account is approved.';
        } else {
            // Clean up photo on failure
            @unlink($dir . $photoName);
            $error = 'Registration failed: ' . htmlspecialchars(db()->lastError ?: 'Unknown error. Please try again.');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Student Registration — Ganeshman Singh Academy</title>
<link rel="icon" href="assets/images/gmscrop.png">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --primary:      #1a3c5e;
    --primary-l:    #2563a8;
    --primary-d:    #0f2540;
    --white:        #ffffff;
    --bg:           #f0f4f8;
    --border:       #e2e8f0;
    --border-l:     #f1f5f9;
    --text:         #1e293b;
    --muted:        #64748b;
    --light:        #94a3b8;
    --red:          #ef4444;
    --green:        #10b981;
    --r:            12px;
}

html { scroll-behavior: smooth; }

body {
    font-family: 'Plus Jakarta Sans', sans-serif;
    background: var(--bg);
    min-height: 100vh;
    color: var(--text);
    padding: 40px 20px 72px;
}

/* ── WRAP ── */
.wrap { max-width: 720px; margin: 0 auto; }

/* ── HEADER ── */
.hd {
    display: flex; align-items: center; gap: 16px;
    background: linear-gradient(135deg, var(--primary-d) 0%, var(--primary) 50%, var(--primary-l) 100%);
    border-radius: 16px 16px 0 0;
    padding: 28px 32px;
    margin-bottom: 0;
}
.hd-logo {
    width: 56px; height: 56px; border-radius: 50%; object-fit: cover;
    border: 2.5px solid rgba(255,255,255,0.35); flex-shrink: 0;
}
.hd-school { font-size: 0.6rem; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.55); font-weight: 600; margin-bottom: 4px; }
.hd-title  { font-size: 1.35rem; font-weight: 800; color: var(--white); letter-spacing: -0.3px; }

/* ── MAIN CARD ── */
.main-card {
    background: var(--white);
    border-radius: 0 0 16px 16px;
    box-shadow: 0 20px 60px rgba(15,37,64,0.15);
    overflow: hidden;
    margin-bottom: 0;
}

/* ── SECTION HEAD ── */
.sec-head {
    display: flex; align-items: center; gap: 10px;
    padding: 16px 28px; border-bottom: 1px solid var(--border);
    background: var(--border-l);
}
.sec-icon {
    width: 30px; height: 30px; border-radius: 8px;
    background: linear-gradient(135deg, var(--primary), var(--primary-l));
    display: flex; align-items: center; justify-content: center; flex-shrink: 0;
}
.sec-icon i { color: #fff; font-size: 0.72rem; }
.sec-title { font-size: 0.8rem; font-weight: 700; color: var(--primary); }
.sec-sub   { font-size: 0.68rem; color: var(--muted); margin-top: 1px; }

/* ── FORM BODY ── */
.form-body { padding: 24px 28px; border-bottom: 1px solid var(--border); }
.form-body:last-child { border-bottom: none; }

/* ── GRID ── */
.grid { display: grid; gap: 16px; }
.g2   { grid-template-columns: 1fr 1fr; }
.g3   { grid-template-columns: 1fr 1fr 1fr; }
.span2 { grid-column: span 2; }
.span3 { grid-column: span 3; }

/* ── FIELD ── */
.field { display: flex; flex-direction: column; gap: 6px; }

.field label {
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.9px; text-transform: uppercase; color: var(--muted);
}
.req { color: var(--primary-l); margin-left: 2px; }
.opt { font-size: 0.58rem; color: var(--light); font-weight: 500;
       text-transform: none; letter-spacing: 0; font-style: italic; margin-left: 4px; }

.field-wrap { position: relative; }
.field-wrap .fi {
    position: absolute; left: 13px; top: 50%; transform: translateY(-50%);
    color: var(--light); font-size: 0.78rem; pointer-events: none; transition: color 0.2s;
}
.field-wrap:focus-within .fi { color: var(--primary-l); }

.fi-input {
    width: 100%; padding: 10px 13px 10px 36px;
    background: var(--bg); border: 1.5px solid var(--border); border-radius: 8px;
    font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.875rem; color: var(--text);
    outline: none; transition: all 0.2s; -webkit-appearance: none; appearance: none;
}
.fi-input::placeholder { color: #c1c9d4; }
.fi-input:focus { background: var(--white); border-color: var(--primary-l); box-shadow: 0 0 0 3px rgba(37,99,168,0.1); }

select.fi-input { cursor: pointer; }
textarea.fi-input { resize: none; height: 72px; line-height: 1.5; }

/* Password eye */
.eye-btn {
    position: absolute; right: 11px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer; color: var(--light);
    font-size: 0.78rem; padding: 4px; transition: color 0.2s;
}
.eye-btn:hover { color: var(--primary); }
.eye-input { padding-right: 36px; }

/* ── PHOTO UPLOAD ── */
.photo-zone {
    border: 2px dashed var(--border); border-radius: 10px;
    padding: 24px; text-align: center; cursor: pointer;
    transition: all 0.2s; background: var(--bg);
    display: flex; flex-direction: column; align-items: center; gap: 10px;
}
.photo-zone:hover { border-color: var(--primary-l); background: #eff6ff; }
.photo-zone.has-photo { border-style: solid; border-color: var(--primary-l); }
#photoInput { display: none; }
.photo-ring {
    width: 80px; height: 80px; border-radius: 50%;
    overflow: hidden; border: 3px solid var(--border);
    display: flex; align-items: center; justify-content: center; background: var(--white);
    transition: border-color 0.2s;
}
.photo-zone:hover .photo-ring,
.photo-zone.has-photo .photo-ring { border-color: var(--primary-l); }
.photo-ring img  { width: 100%; height: 100%; object-fit: cover; display: none; }
.photo-ring i    { color: var(--light); font-size: 1.6rem; }
.photo-cta       { font-size: 0.82rem; font-weight: 600; color: var(--primary); }
.photo-hint      { font-size: 0.7rem; color: var(--muted); }

/* ── ALERT ── */
.alert {
    display: flex; gap: 11px; align-items: flex-start;
    padding: 13px 16px; border-radius: var(--r);
    font-size: 0.82rem; line-height: 1.65; margin-bottom: 20px;
    border: 1.5px solid; animation: fadeIn 0.25s ease;
}
.alert-err { background: #fef2f2; border-color: #fecaca; color: var(--red); }
@keyframes fadeIn { from { opacity:0; transform:translateY(-5px); } to { opacity:1; transform:none; } }

/* ── FOOTER ── */
.form-footer {
    padding: 20px 28px;
    background: var(--border-l); border-top: 1px solid var(--border);
    display: flex; align-items: center; justify-content: space-between; gap: 14px;
    flex-wrap: wrap;
}
.login-hint { font-size: 0.8rem; color: var(--muted); }
.login-hint a { color: var(--primary-l); font-weight: 700; text-decoration: none; }
.login-hint a:hover { text-decoration: underline; }

.btn-submit {
    padding: 12px 30px;
    background: linear-gradient(135deg, var(--primary-l), var(--primary));
    color: var(--white); font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.88rem; font-weight: 700; border: none; border-radius: 8px;
    cursor: pointer; display: flex; align-items: center; gap: 8px;
    transition: all 0.25s; box-shadow: 0 4px 14px rgba(26,60,94,0.3);
    letter-spacing: 0.2px;
}
.btn-submit:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,60,94,0.35); }
.btn-submit:active { transform: none; opacity: 0.9; }
.btn-submit.loading .btn-text { display: none; }
.btn-spinner { display: none; width: 15px; height: 15px; border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff; border-radius: 50%; animation: spin 0.7s linear infinite; }
.btn-submit.loading .btn-spinner { display: block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* ── SUCCESS ── */
.success-card {
    background: var(--white); border-radius: 16px;
    box-shadow: 0 20px 60px rgba(15,37,64,0.15);
    padding: 64px 40px; text-align: center;
}
.success-icon {
    width: 60px; height: 60px; border-radius: 50%;
    background: linear-gradient(135deg, var(--green), #059669);
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 20px; box-shadow: 0 8px 24px rgba(16,185,129,0.3);
}
.success-icon i { color: #fff; font-size: 1.3rem; }
.success-title { font-size: 1.5rem; font-weight: 800; color: var(--text); margin-bottom: 10px; letter-spacing: -0.3px; }
.success-msg   { font-size: 0.84rem; color: var(--muted); line-height: 1.8; max-width: 360px; margin: 0 auto 28px; }
.btn-back {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 12px 28px;
    background: linear-gradient(135deg, var(--primary-l), var(--primary));
    color: var(--white); border-radius: 8px; text-decoration: none;
    font-size: 0.85rem; font-weight: 700;
    box-shadow: 0 4px 14px rgba(26,60,94,0.3); transition: all 0.25s;
}
.btn-back:hover { transform: translateY(-2px); box-shadow: 0 8px 24px rgba(26,60,94,0.35); }

/* ── MOBILE ── */
@media (max-width: 600px) {
    body { padding: 20px 12px 56px; }
    .hd { padding: 22px 20px; border-radius: 12px 12px 0 0; }
    .form-body { padding: 18px 16px; }
    .sec-head { padding: 14px 16px; }
    .g2, .g3 { grid-template-columns: 1fr; }
    .span2, .span3 { grid-column: span 1; }
    .form-footer { flex-direction: column; align-items: stretch; }
    .btn-submit { justify-content: center; }
    .success-card { padding: 44px 20px; }
}
</style>
</head>
<body>
<div class="wrap">

<?php if ($success): ?>
<!-- SUCCESS -->
<div class="success-card">
    <div class="success-icon"><i class="fas fa-check"></i></div>
    <div class="success-title">Application Submitted!</div>
    <p class="success-msg">Your registration is under review. Once the admin approves your application, you'll be able to sign in with your chosen credentials.</p>
    <a href="login.php" class="btn-back"><i class="fas fa-arrow-left"></i> Back to Login</a>
</div>

<?php else: ?>

<!-- Header bar -->
<div class="hd">
    <img src="assets/images/gmscrop.png" alt="GMS" class="hd-logo">
    <div>
        <div class="hd-school">Ganeshman Singh Academy</div>
        <div class="hd-title">Student Registration</div>
    </div>
</div>

<div class="main-card">

    <?php if ($error): ?>
    <div style="padding:20px 28px 0;">
        <div class="alert alert-err">
            <i class="fas fa-exclamation-circle" style="flex-shrink:0;margin-top:2px;"></i>
            <span><?= $error ?></span>
        </div>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="regForm">

        <!-- ── PHOTO ── -->
        <div class="sec-head">
            <div class="sec-title">Student Photo</div>
        </div>
        <div class="form-body">
            <input type="file" id="photoInput" name="photo" accept="image/jpeg,image/png,image/gif,image/webp">
            <div class="photo-zone" onclick="document.getElementById('photoInput').click()" id="photoZone">
                <div class="photo-ring" id="photoRing">
                    <img id="photoPreview" src="" alt="">
                    <i class="fas fa-user" id="photoIcon"></i>
                </div>
                <div class="photo-cta" id="photoLabel">Click to upload photo</div>
                <div class="photo-hint">Or drag and drop · JPG, PNG, WEBP</div>
            </div>
        </div>

        <!-- ── PERSONAL ── -->
        <div class="sec-head">
            <div class="sec-title">Personal Information</div>
        </div>
        <div class="form-body">
            <div class="grid g2">
                <div class="field">
                    <label>First Name <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="text" name="first_name" class="fi-input" placeholder="e.g. Ram" required value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>">
                        <i class="fas fa-user fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Last Name <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="text" name="last_name" class="fi-input" placeholder="e.g. Sharma" required value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>">
                        <i class="fas fa-user fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Date of Birth <span class="req">*</span></label>
                    <div class="field-wrap">
                        <?= bsDatePicker('dob', htmlspecialchars($_POST['dob'] ?? ''), true) ?>
                        <i class="fas fa-calendar fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Gender <span class="req">*</span></label>
                    <div class="field-wrap">
                        <select name="gender" class="fi-input" required>
                            <option value="">Select gender</option>
                            <option value="Male"   <?= (($_POST['gender'] ?? '') === 'Male')   ? 'selected' : '' ?>>Male</option>
                            <option value="Female" <?= (($_POST['gender'] ?? '') === 'Female') ? 'selected' : '' ?>>Female</option>
                            <option value="Other"  <?= (($_POST['gender'] ?? '') === 'Other')  ? 'selected' : '' ?>>Other</option>
                        </select>
                        <i class="fas fa-venus-mars fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Class <span class="req">*</span></label>
                    <div class="field-wrap">
                        <select name="class_id" class="fi-input" required>
                            <option value="">Select class</option>
                            <?php foreach ($classes as $cls): ?>
                            <option value="<?= $cls['id'] ?>" <?= (($_POST['class_id'] ?? 0) == $cls['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cls['class_name'] . ' — ' . $cls['section']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-school fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Phone <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="tel" name="phone" class="fi-input" placeholder="98XXXXXXXX" required value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                        <i class="fas fa-phone fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Email <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="email" name="email" class="fi-input" placeholder="you@example.com" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        <i class="fas fa-envelope fi"></i>
                    </div>
                </div>
                <div class="field span2">
                    <label>Address <span class="req">*</span></label>
                    <div class="field-wrap">
                        <textarea name="address" class="fi-input" placeholder="Village/Tole, Municipality, District" required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
                        <i class="fas fa-map-marker-alt fi" style="top:14px;transform:none;"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Admission Date <span class="opt">optional</span></label>
                    <div class="field-wrap">
                        <?= bsDatePicker('admission_date', htmlspecialchars($_POST['admission_date'] ?? date('Y-m-d')), false) ?>
                        <i class="fas fa-calendar-check fi"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── GUARDIAN ── -->
        <div class="sec-head">
            <div class="sec-title">Guardian Information</div>
        </div>
        <div class="form-body">
            <div class="grid g3">
                <div class="field">
                    <label>Guardian Name <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="text" name="guardian_name" class="fi-input" placeholder="Full name" required value="<?= htmlspecialchars($_POST['guardian_name'] ?? '') ?>">
                        <i class="fas fa-user-shield fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Guardian Phone <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="tel" name="guardian_phone" class="fi-input" placeholder="98XXXXXXXX" required value="<?= htmlspecialchars($_POST['guardian_phone'] ?? '') ?>">
                        <i class="fas fa-phone fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Relation <span class="opt">optional</span></label>
                    <div class="field-wrap">
                        <select name="guardian_relation" class="fi-input">
                            <option value="">Select</option>
                            <?php foreach (['Father','Mother','Uncle','Aunt','Grandfather','Grandmother','Brother','Sister','Other'] as $rel): ?>
                            <option value="<?= $rel ?>" <?= (($_POST['guardian_relation'] ?? '') === $rel) ? 'selected' : '' ?>><?= $rel ?></option>
                            <?php endforeach; ?>
                        </select>
                        <i class="fas fa-sitemap fi"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── CREDENTIALS ── -->
        <div class="sec-head">
            <div class="sec-icon"><i class="fas fa-key"></i></div>
            <div><div class="sec-title">Login Credentials</div><div class="sec-sub">Used to sign in after approval</div></div>
        </div>
        <div class="form-body">
            <div class="grid g3">
                <div class="field">
                    <label>Username <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="text" name="username" class="fi-input" placeholder="Min. 4 characters" required autocomplete="username" value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                        <i class="fas fa-at fi"></i>
                    </div>
                </div>
                <div class="field">
                    <label>Password <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="password" name="password" id="pw1" class="fi-input eye-input" placeholder="Min. 6 characters" required autocomplete="new-password">
                        <i class="fas fa-lock fi"></i>
                        <button type="button" class="eye-btn" onclick="togglePw('pw1','eye1')"><i class="fas fa-eye" id="eye1"></i></button>
                    </div>
                </div>
                <div class="field">
                    <label>Confirm Password <span class="req">*</span></label>
                    <div class="field-wrap">
                        <input type="password" name="confirm_password" id="pw2" class="fi-input eye-input" placeholder="Repeat password" required autocomplete="new-password">
                        <i class="fas fa-lock fi"></i>
                        <button type="button" class="eye-btn" onclick="togglePw('pw2','eye2')"><i class="fas fa-eye" id="eye2"></i></button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── FOOTER ── -->
        <div class="form-footer">
            <div class="login-hint">Already have an account? <a href="login.php">Sign in here</a></div>
            <button type="submit" class="btn-submit" id="submitBtn">
                <span class="btn-text"><i class="fas fa-paper-plane"></i> Submit Application</span>
                <span class="btn-spinner"></span>
            </button>
        </div>

    </form>
</div><!-- /main-card -->
<?php endif; ?>

</div><!-- /wrap -->

<script>
// Photo preview
const photoInput = document.getElementById('photoInput');
photoInput?.addEventListener('change', function() {
    if (!this.files || !this.files[0]) return;
    const reader = new FileReader();
    reader.onload = e => {
        document.getElementById('photoPreview').src          = e.target.result;
        document.getElementById('photoPreview').style.display = 'block';
        document.getElementById('photoIcon').style.display    = 'none';
        document.getElementById('photoLabel').textContent     = 'Click to change photo';
        document.getElementById('photoZone').classList.add('has-photo');
    };
    reader.readAsDataURL(this.files[0]);
});

// Drag & drop
const zone = document.getElementById('photoZone');
if (zone) {
    zone.addEventListener('dragover', e => { e.preventDefault(); zone.style.borderColor = 'var(--primary-l)'; });
    zone.addEventListener('dragleave',    () => zone.style.borderColor = '');
    zone.addEventListener('drop', e => {
        e.preventDefault(); zone.style.borderColor = '';
        if (e.dataTransfer.files[0]) { photoInput.files = e.dataTransfer.files; photoInput.dispatchEvent(new Event('change')); }
    });
}

// Password toggle
function togglePw(inputId, iconId) {
    const inp  = document.getElementById(inputId);
    const icon = document.getElementById(iconId);
    const show = inp.type === 'password';
    inp.type = show ? 'text' : 'password';
    icon.className = show ? 'fas fa-eye-slash' : 'fas fa-eye';
}

// Submit loading
document.getElementById('regForm')?.addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading'); btn.disabled = true;
    setTimeout(() => { btn.classList.remove('loading'); btn.disabled = false; }, 10000);
});
</script>
</body>
</html>