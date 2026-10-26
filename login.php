<?php
require_once 'includes/auth.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    header("Location: {$role}/dashboard.php");
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username/email and password.';
    } else {
        $result = loginUser($username, $password);
        if ($result['success']) {
            redirect("{$result['role']}/dashboard.php");
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>Login – Ganeshman Singh Academy</title>
<meta name="robots" content="noindex, nofollow">
<link rel="icon" type="image/png" href="assets/images/gmscrop.png">
<link rel="shortcut icon" href="assets/images/gmscrop.png">
<link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
<!-- PWA: installable app support -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#1a3c5e">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="GMS App">
<script src="assets/js/pwa-install.js" defer></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

:root {
    --navy:  #0b1f3a;
    --navy2: #132d50;
    --gold:  #c9a84c;
    --gold2: #e0be6e;
    --gold3: #f5dfa0;
    --white: #ffffff;
    --cream: #faf9f7;
    --slate: #64748b;
    --r:     12px;
}

html, body {
    height: 100%;
    font-family: 'Plus Jakarta Sans', sans-serif;
    overflow: hidden;
}

/* ─── BACKGROUND ─── */
body {
    background: linear-gradient(145deg, #071526 0%, #0d2240 60%, #0a1c35 100%);
    display: flex;
    align-items: stretch;
    min-height: 100vh;
}

.bg-orb {
    position: fixed; border-radius: 50%;
    filter: blur(90px); pointer-events: none; z-index: 0;
}
.orb1 {
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(201,168,76,0.25) 0%, transparent 70%);
    top: -120px; right: -120px;
    animation: float 9s ease-in-out infinite;
}
.orb2 {
    width: 400px; height: 400px;
    background: radial-gradient(circle, rgba(26,58,99,0.5) 0%, transparent 70%);
    bottom: -100px; left: -100px;
    animation: float 7s ease-in-out infinite reverse;
}
@keyframes float {
    0%,100% { transform: translateY(0); }
    50%      { transform: translateY(-25px); }
}

/* ─── PAGE LAYOUT ─── */
.page {
    position: relative; z-index: 1;
    width: 100%; display: flex;
    height: 100vh; overflow: hidden;
}

/* ─── LEFT PANEL (desktop only) ─── */
.left-panel {
    flex: 1;
    display: flex; flex-direction: column; justify-content: space-between;
    padding: 52px 56px;
    overflow: hidden;
}

.school-brand {
    display: flex; align-items: center; gap: 16px;
}
.school-logo {
    width: 64px; height: 64px; border-radius: 50%; object-fit: cover;
    border: 2.5px solid var(--gold);
    box-shadow: 0 0 0 7px rgba(201,168,76,0.1), 0 16px 40px rgba(0,0,0,0.4);
    flex-shrink: 0;
}
.school-name strong {
    display: block; font-size: 1.05rem; font-weight: 700;
    color: var(--white); line-height: 1.3;
}
.school-name span {
    font-size: 0.62rem; color: var(--gold);
    letter-spacing: 2px; text-transform: uppercase; font-weight: 500;
}

.left-middle { padding: 20px 0; }

.left-headline {
    font-size: clamp(2rem, 3.5vw, 3.2rem);
    font-weight: 800; color: var(--white);
    line-height: 1.1; margin-bottom: 20px;
    letter-spacing: -0.5px;
}
.left-headline .hl {
    background: linear-gradient(135deg, var(--gold), var(--gold3));
    -webkit-background-clip: text; -webkit-text-fill-color: transparent;
    background-clip: text;
}
.left-desc {
    font-size: 0.88rem; color: rgba(255,255,255,0.4);
    line-height: 1.85; max-width: 340px; font-weight: 400;
}

.left-footer {
    font-size: 0.62rem; color: rgba(255,255,255,0.15);
    letter-spacing: 2px; text-transform: uppercase;
}

/* ─── RIGHT PANEL (login form) ─── */
.right-panel {
    width: 45%; flex-shrink: 0;
    background: var(--white);
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    padding: 52px 48px;
    height: 100vh; overflow: hidden;
}

.form-wrap { width: 100%; max-width: 340px; }

/* Logo centered */
.form-logo-wrap {
    text-align: center; margin-bottom: 28px;
}
.form-logo {
    width: 76px; height: 76px; border-radius: 50%; object-fit: cover;
    border: 3px solid var(--gold);
    box-shadow: 0 0 0 8px rgba(201,168,76,0.1), 0 16px 40px rgba(11,31,58,0.15);
    display: inline-block;
}
.form-logo-name {
    display: block; margin-top: 10px;
    font-size: 0.65rem; font-weight: 700; letter-spacing: 2px;
    text-transform: uppercase; color: var(--gold);
}

.form-title {
    font-size: 1.85rem; font-weight: 800;
    color: var(--navy); line-height: 1.15;
    margin-bottom: 6px; letter-spacing: -0.4px;
    text-align: center;
}
.form-sub {
    font-size: 0.82rem; color: var(--slate);
    line-height: 1.6; margin-bottom: 28px;
    text-align: center;
}

/* Error */
.alert-err {
    display: flex; align-items: flex-start; gap: 10px;
    padding: 12px 14px; border-radius: var(--r); margin-bottom: 18px;
    background: #fef2f2; border: 1.5px solid #fecaca;
    color: #dc2626; font-size: 0.82rem; line-height: 1.5;
    animation: shake 0.35s ease;
}
.alert-err i { flex-shrink: 0; margin-top: 1px; }

.alert-warn {
    background: #fffbeb !important;
    border-color: #fcd34d !important;
    color: #92400e !important;
}
.alert-reject {
    background: #fdf2f8 !important;
    border-color: #f0abfc !important;
    color: #701a75 !important;
}

@keyframes shake {
    0%,100% { transform: translateX(0); }
    25%,75%  { transform: translateX(-5px); }
    50%      { transform: translateX(5px); }
}

/* Fields */
.field { margin-bottom: 16px; }
.field-label {
    display: block; font-size: 0.68rem; font-weight: 700;
    letter-spacing: 1px; text-transform: uppercase;
    color: #374151; margin-bottom: 7px;
}
.field-wrap { position: relative; }
.field-wrap > i {
    position: absolute; left: 14px; top: 50%; transform: translateY(-50%);
    color: #9ca3af; font-size: 0.8rem; pointer-events: none;
    transition: color 0.2s;
}
.field-input {
    width: 100%; padding: 14px 14px 14px 42px;
    background: #f9fafb; border: 1.5px solid #e5e7eb;
    border-radius: var(--r);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.9rem; color: var(--navy);
    outline: none; transition: all 0.2s;
    -webkit-appearance: none; appearance: none;
}
.field-input::placeholder { color: #c1c9d4; }
.field-input:focus {
    background: #fff; border-color: var(--navy2);
    box-shadow: 0 0 0 4px rgba(11,31,58,0.08);
}
.field-input:focus ~ i,
.field-wrap:focus-within > i { color: var(--navy2); }
.eye-btn {
    position: absolute; right: 14px; top: 50%; transform: translateY(-50%);
    background: none; border: none; cursor: pointer;
    color: #9ca3af; font-size: 0.82rem; padding: 6px;
    display: flex; align-items: center; transition: color 0.2s;
    -webkit-tap-highlight-color: transparent;
}
.eye-btn:hover { color: var(--navy); }

/* Submit */
.btn-submit {
    width: 100%; padding: 15px;
    background: linear-gradient(135deg, var(--navy), var(--navy2));
    color: var(--white);
    font-family: 'Plus Jakarta Sans', sans-serif;
    font-size: 0.92rem; font-weight: 700;
    border: none; border-radius: var(--r);
    cursor: pointer; letter-spacing: 0.3px;
    display: flex; align-items: center; justify-content: center; gap: 9px;
    transition: all 0.25s;
    box-shadow: 0 6px 20px rgba(11,31,58,0.25);
    margin-top: 20px;
    -webkit-appearance: none; appearance: none;
    -webkit-tap-highlight-color: transparent;
}
.btn-submit:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 32px rgba(11,31,58,0.35);
}
.btn-submit:active { transform: translateY(0); opacity: 0.9; }
.btn-spinner {
    display: none; width: 18px; height: 18px;
    border: 2px solid rgba(255,255,255,0.3); border-top-color: #fff;
    border-radius: 50%; animation: spin 0.7s linear infinite;
}
.btn-submit.loading .btn-text { display: none; }
.btn-submit.loading .btn-spinner { display: block; }
@keyframes spin { to { transform: rotate(360deg); } }

/* Credit */
.credit {
    margin-top: 24px; text-align: center;
    font-size: 0.7rem; color: #c1c9d4;
}
.credit a { color: var(--gold); text-decoration: none; font-weight: 600; }
.credit a:hover { text-decoration: underline; }

/* ─── MOBILE ─── */
@media (max-width: 768px) {
    html, body { overflow: hidden; height: 100%; }

    .bg-orb { display: none; }

    body {
        background: linear-gradient(160deg, #0b1f3a 0%, #0d2645 100%);
    }

    .page {
        flex-direction: column;
        height: 100vh; overflow: hidden;
        align-items: center; justify-content: center;
    }

    /* Hide left panel completely on mobile */
    .left-panel { display: none; }

    /* Full screen centered form */
    .right-panel {
        width: 100%;
        height: 100vh;
        background: transparent;
        padding: 32px 28px;
        justify-content: center;
    }

    .form-wrap { max-width: 360px; width: 100%; }

    /* Logo on dark background on mobile */
    .form-logo {
        width: 84px; height: 84px;
        box-shadow: 0 0 0 10px rgba(201,168,76,0.12), 0 20px 50px rgba(0,0,0,0.4);
    }
    .form-logo-name { color: var(--gold2); }

    .form-title { color: var(--white); }
    .form-sub   { color: rgba(255,255,255,0.45); }

    .field-label { color: rgba(255,255,255,0.6); }

    .field-input {
        background: rgba(255,255,255,0.08);
        border-color: rgba(255,255,255,0.12);
        color: var(--white);
    }
    .field-input::placeholder { color: rgba(255,255,255,0.25); }
    .field-input:focus {
        background: rgba(255,255,255,0.12);
        border-color: var(--gold);
        box-shadow: 0 0 0 4px rgba(201,168,76,0.12);
    }
    .field-wrap > i { color: rgba(255,255,255,0.35); }
    .field-input:focus ~ i,
    .field-wrap:focus-within > i { color: var(--gold); }

    .eye-btn { color: rgba(255,255,255,0.35); }
    .eye-btn:hover { color: var(--gold); }

    .btn-submit {
        background: linear-gradient(135deg, var(--gold), var(--gold2));
        color: var(--navy);
        box-shadow: 0 8px 24px rgba(201,168,76,0.35);
    }
    .btn-submit:hover {
        box-shadow: 0 16px 40px rgba(201,168,76,0.45);
    }

    .alert-err {
        background: rgba(254,242,242,0.1);
        border-color: rgba(254,202,202,0.3);
        color: #fca5a5;
    }
    .alert-warn {
        background: rgba(255,251,235,0.12) !important;
        border-color: rgba(252,211,77,0.3) !important;
        color: #fcd34d !important;
    }
    .alert-reject {
        background: rgba(253,242,248,0.1) !important;
        border-color: rgba(240,171,252,0.3) !important;
        color: #e879f9 !important;
    }

    .credit { color: rgba(255,255,255,0.2); }
    .credit a { color: var(--gold2); }
}

@media (max-width: 380px) {
    .right-panel { padding: 24px 20px; }
    .form-logo { width: 72px; height: 72px; }
    .form-title { font-size: 1.6rem; }
}
</style>
</head>
<body>

<div class="bg-orb orb1"></div>
<div class="bg-orb orb2"></div>

<div class="page">

    <!-- LEFT PANEL — desktop only -->
    <div class="left-panel">
        <div class="school-brand">
            <img src="assets/images/gmscrop.png" alt="GMS" class="school-logo">
            <div class="school-name">
                <strong>Ganeshman Singh Academy</strong>
                <span>Jhiljhille · Jhapa · Nepal</span>
            </div>
        </div>

        <div class="left-middle">
            <h2 class="left-headline">
                Your school.<br>
                Your <span class="hl">portal.</span><br>
                All in one place.
            </h2>
            <p class="left-desc">
                Access your results, attendance, fee records, notices and timetable — securely and instantly, from any device.
            </p>
        </div>

        <div class="left-footer">
            © <?= date('Y') ?> Ganeshman Singh Academy
        </div>
    </div>

    <!-- RIGHT PANEL — login form -->
    <div class="right-panel">
        <div class="form-wrap">

            <!-- Logo centered -->
            <div class="form-logo-wrap">
                <img src="assets/images/gmscrop.png" alt="GMS" class="form-logo">
                <span class="form-logo-name">Ganeshman Singh Academy</span>
            </div>

            <h1 class="form-title">Welcome back</h1>
            <p class="form-sub">Sign in to access your portal</p>

            <?php if ($error): ?>
            <?php
                $isPending  = str_contains($error, 'pending approval');
                $isRejected = str_contains($error, 'not approved');
            ?>
            <div class="alert-err<?= $isPending ? ' alert-warn' : ($isRejected ? ' alert-reject' : '') ?>">
                <?php if ($isPending): ?>
                <i class="fas fa-clock"></i>
                <?php elseif ($isRejected): ?>
                <i class="fas fa-ban"></i>
                <?php else: ?>
                <i class="fas fa-exclamation-circle"></i>
                <?php endif; ?>
                <span><?= $error ?></span>
            </div>
            <?php endif; ?>

            <form method="POST" action="" id="loginForm">
                <div class="field">
                    <label class="field-label">Username or Email</label>
                    <div class="field-wrap">
                        <input type="text" name="username" class="field-input"
                               placeholder="Enter your username or email"
                               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                               autocomplete="username" required>
                        <i class="fas fa-user"></i>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Password</label>
                    <div class="field-wrap">
                        <input type="password" name="password" id="passInput"
                               class="field-input" placeholder="Enter your password"
                               autocomplete="current-password" required>
                        <i class="fas fa-lock"></i>
                        <button type="button" class="eye-btn" id="eyeBtn">
                            <i class="fas fa-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-submit" id="submitBtn">
                    <span class="btn-text"><i class="fas fa-sign-in-alt"></i> Sign In</span>
                    <span class="btn-spinner"></span>
                </button>
            </form>

            <div class="credit" style="margin-top:16px;">
                New student? <a href="signup.php" style="color:var(--gold);font-weight:700;">Register here</a>
            </div>
            <div class="credit">
                Developed with <span style="color:#ef4444">♥</span> by
                <a href="https://raghavdhakal.com.np" target="_blank" rel="noopener noreferrer">Raghav Dhakal</a>| Sandip Neupane | Bijay Baniya | Pujan BK | Rojina Thapa | Mirak Limbu
            </div>
        </div>
    </div>

</div>

<script>
// Password toggle
document.getElementById('eyeBtn').addEventListener('click', function() {
    const input = document.getElementById('passInput');
    const icon  = document.getElementById('eyeIcon');
    const show  = input.type === 'password';
    input.type  = show ? 'text' : 'password';
    icon.classList.toggle('fa-eye',       !show);
    icon.classList.toggle('fa-eye-slash',  show);
});

// Loading spinner on submit
document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.classList.add('loading');
    btn.disabled = true;
    setTimeout(() => { btn.classList.remove('loading'); btn.disabled = false; }, 5000);
});

// Auto focus
const u = document.querySelector('input[name="username"]');
if (u && !u.value) u.focus();
</script>
</body>
</html>