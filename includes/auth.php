<?php
// ============================================================
// Ganeshman Singh Academy - Auth & Session Helpers
// ============================================================

// Force Nepal timezone (Asia/Kathmandu, UTC+5:45) regardless of server location
// This ensures date()/time() always reflect Nepal's current date — critical for
// the Bikram Sambat calendar conversion and all date displays across the app.
date_default_timezone_set('Asia/Kathmandu');

require_once __DIR__ . '/config.php';

// Start session safely
// (The mobile API under api/v1 defines API_NO_SESSION: it authenticates by token, not cookie.)
if (!defined('API_NO_SESSION') && session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400,
        'path'     => '/',
        'secure'   => false, // Set true on HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
    session_start();
    if (!headers_sent()) {   // pages must always show fresh data
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
    }
}

// -----------------------------------------------
// Check if logged in
// -----------------------------------------------
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

// -----------------------------------------------
// Require login - redirect if not
// -----------------------------------------------
function requireLogin(string $redirect = '../index.php'): void {
    if (!isLoggedIn()) {
        header("Location: $redirect");
        exit;
    }
}

// -----------------------------------------------
// Require specific role
// -----------------------------------------------
function requireRole(string $role, string $redirect = '../index.php'): void {
    requireLogin($redirect);
    if ($_SESSION['role'] !== $role) {
        header("Location: $redirect");
        exit;
    }
}

// -----------------------------------------------
// Get current user info
// -----------------------------------------------
function currentUser(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? 0,
        'username' => $_SESSION['username']  ?? '',
        'role'     => $_SESSION['role']      ?? '',
        'name'     => $_SESSION['full_name'] ?? '',
        'ref_id'   => $_SESSION['ref_id']    ?? 0,
    ];
}

// -----------------------------------------------
// Authenticate user
// -----------------------------------------------
function loginUser(string $usernameOrEmail, string $password): array {
    // Accept either the username or the registered email in the same field
    $user = db()->fetchOne(
        "SELECT * FROM users WHERE (username = ? OR email = ?) AND is_active = 1",
        'ss', [$usernameOrEmail, $usernameOrEmail]
    );

    if (!$user) {
        // Check if this username/email is pending admin approval
        $pending = db()->fetchOne(
            "SELECT id FROM pending_registrations WHERE (username=? OR email=?) AND status='pending'",
            'ss', [$usernameOrEmail, $usernameOrEmail]
        );
        if ($pending) {
            return ['success' => false, 'message' => 'Your registration is pending approval. Please wait for the admin to review your application before logging in.'];
        }
        // Check if it was rejected
        $rejected = db()->fetchOne(
            "SELECT id FROM pending_registrations WHERE (username=? OR email=?) AND status='rejected'",
            'ss', [$usernameOrEmail, $usernameOrEmail]
        );
        if ($rejected) {
            return ['success' => false, 'message' => 'Your registration was not approved. Please contact the school for more information.'];
        }
        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    // First-run: password not yet hashed — redirect to reset tool
    if ($user['password'] === 'RESET_REQUIRED') {
        return ['success' => false, 'message' => 'Password not set. Please open <a href="reset_password.php" style="color:#2563a8;font-weight:700;">reset_password.php</a> to set your password first.'];
    }

    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'message' => 'Invalid username/email or password.'];
    }

    // Get full name based on role
    $fullName = $user['username'];
    $refId = 0;
    if ($user['role'] === 'student') {
        $s = db()->fetchOne("SELECT id, CONCAT(first_name,' ',last_name) as name FROM students WHERE user_id=?", 'i', [$user['id']]);
        if ($s) { $fullName = $s['name']; $refId = $s['id']; }
    } elseif ($user['role'] === 'teacher') {
        $t = db()->fetchOne("SELECT id, CONCAT(first_name,' ',last_name) as name FROM teachers WHERE user_id=?", 'i', [$user['id']]);
        if ($t) { $fullName = $t['name']; $refId = $t['id']; }
    } else {
        $fullName = 'Administrator';
    }

    // Set session
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['username']  = $user['username'];
    $_SESSION['role']      = $user['role'];
    $_SESSION['full_name'] = $fullName;
    $_SESSION['ref_id']    = $refId;

    // Update last login
    db()->execute("UPDATE users SET last_login=NOW() WHERE id=?", 'i', [$user['id']]);

    return ['success' => true, 'role' => $user['role']];
}

// -----------------------------------------------
// Logout
// -----------------------------------------------
function logoutUser(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

// -----------------------------------------------
// Hash password
// -----------------------------------------------
function hashPassword(string $password): string {
    return password_hash($password, PASSWORD_BCRYPT);
}

// -----------------------------------------------
// Sanitize input
// -----------------------------------------------
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

// -----------------------------------------------
// Generate unique ID (student/teacher)
// -----------------------------------------------
function generateId(string $prefix, int $number): string {
    return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
}

// -----------------------------------------------
// Grade calculation
// -----------------------------------------------
function calculateGrade(float $marks, int $full): string {
    $pct = ($full > 0) ? ($marks / $full) * 100 : 0;
    if ($pct >= 90) return 'A+';
    if ($pct >= 80) return 'A';
    if ($pct >= 70) return 'B+';
    if ($pct >= 60) return 'B';
    if ($pct >= 50) return 'C+';
    if ($pct >= 40) return 'C';
    if ($pct >= 35) return 'D';
    return 'F';
}


// ============================================================
// Nepali Date (Bikram Sambat) Converter
// ============================================================
class NepaliDate {

    // BS month data: each row = one BS year, 12 values = days in each month
    // Starting from BS 2000
    private static array $bsMonthData = [
        [30,32,31,32,31,30,30,30,29,30,29,31], // 2000
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2001
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2002
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2003
        [30,32,31,32,31,30,30,30,29,30,29,31], // 2004
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2005
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2006
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2007
        [31,31,31,32,31,31,29,30,30,29,29,31], // 2008
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2009
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2010
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2011
        [31,31,31,32,31,31,29,30,30,29,30,30], // 2012
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2013
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2014
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2015
        [31,31,31,32,31,31,29,30,30,29,30,30], // 2016
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2017
        [31,32,31,32,31,30,30,29,30,29,30,30], // 2018
        [31,32,31,32,31,30,30,30,29,30,29,31], // 2019
        [31,31,31,32,31,31,30,29,30,29,30,30], // 2020
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2021
        [31,32,31,32,31,30,30,30,29,29,30,30], // 2022
        [31,32,31,32,31,30,30,30,29,30,29,31], // 2023
        [31,31,31,32,31,31,30,29,30,29,30,30], // 2024
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2025
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2026
        [30,32,31,32,31,30,30,30,29,30,29,31], // 2027
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2028
        [31,31,32,31,32,30,30,29,30,29,30,30], // 2029
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2030
        [30,32,31,32,31,30,30,30,29,30,29,31], // 2031
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2032
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2033
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2034
        [30,32,31,32,31,31,29,30,30,29,29,31], // 2035
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2036
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2037
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2038
        [31,31,31,32,31,31,29,30,30,29,30,30], // 2039
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2040
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2041
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2042
        [31,31,31,32,31,31,29,30,30,29,30,30], // 2043
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2044
        [31,32,31,32,31,30,30,29,30,29,30,30], // 2045
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2046
        [31,31,31,32,31,31,30,29,30,29,30,30], // 2047
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2048
        [31,32,31,32,31,30,30,30,29,29,30,30], // 2049
        [31,32,31,32,31,30,30,30,29,30,29,31], // 2050
        [31,31,31,32,31,31,30,29,30,29,30,30], // 2051
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2052
        [31,32,31,32,31,30,30,30,29,29,30,30], // 2053
        [31,32,31,32,31,30,30,30,29,30,29,31], // 2054
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2055
        [31,31,32,31,32,30,30,29,30,29,30,30], // 2056
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2057
        [30,32,31,32,31,30,30,30,29,30,29,31], // 2058
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2059
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2060
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2061
        [30,32,31,32,31,31,29,30,29,30,29,31], // 2062
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2063
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2064
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2065
        [31,31,31,32,31,31,29,30,30,29,29,31], // 2066
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2067
        [31,31,32,32,31,30,30,29,30,29,30,30], // 2068
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2069
        [31,31,31,32,31,31,29,30,30,29,30,30], // 2070
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2071
        [31,32,31,32,31,30,30,29,30,29,30,30], // 2072
        [31,32,31,32,31,30,30,30,29,29,30,31], // 2073
        [31,31,31,32,31,31,30,29,30,29,30,30], // 2074
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2075
        [31,32,31,32,31,30,30,30,29,29,30,30], // 2076
        [31,32,31,32,31,30,30,30,29,30,29,31], // 2077
        [31,31,31,32,31,31,30,29,30,29,30,30], // 2078
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2079
        [31,32,31,32,31,30,30,30,29,29,30,30], // 2080
        [31,31,32,32,31,30,30,30,29,30,30,30], // 2081
        [30,32,31,32,31,30,30,30,29,30,30,30], // 2082
        [31,31,32,31,31,31,30,29,30,29,30,30], // 2083 — independently verified against Hamro Patro live data + Wikipedia's Nepal public-holidays table (see chat); NOT from the whitphx source below
        [31,31,32,31,31,30,30,30,29,30,30,30], // 2084
        [31,32,31,32,30,31,30,30,29,30,30,30], // 2085
        [30,32,31,32,31,30,30,30,29,30,30,30], // 2086
        [31,31,32,31,31,31,30,30,29,30,30,30], // 2087
        [30,31,32,32,30,31,30,30,29,30,30,30], // 2088
        [30,32,31,32,31,30,30,30,29,30,30,30], // 2089
        [30,32,31,32,31,30,30,30,29,30,30,30], // 2090
    ];

    private static int $bsStartYear = 2000;

    // Expose the month-length table for the client-side calendar widget, so
    // the JS date picker uses the exact same data as this class (no drift).
    public static function monthDataJson(): string {
        return json_encode(self::$bsMonthData);
    }

    public static function startYear(): int {
        return self::$bsStartYear;
    }

    // AD reference: 1943 April 14 = BS 2000 Baisakh 1.
    // Cross-checked against whitphx/nepali-calendar-js's own declared epoch
    // (Julian Day 2430829 = 14 April 1943) and against the fact that BS New
    // Year consistently falls in mid-April every year (verified against
    // live BS 2081-2084 Baisakh-1 dates, all April 13-14).
    private static int $adRefYear  = 1943;
    private static int $adRefMonth = 4;
    private static int $adRefDay   = 14;

    public static array $monthNames = [
        1  => 'Baisakh',  2  => 'Jestha',   3  => 'Ashadh',
        4  => 'Shrawan',  5  => 'Bhadra',   6  => 'Ashwin',
        7  => 'Kartik',   8  => 'Mangsir',  9  => 'Poush',
        10 => 'Magh',     11 => 'Falgun',   12 => 'Chaitra'
    ];

    public static array $monthNamesNepali = [
        1  => 'बैशाख',  2  => 'जेठ',    3  => 'असार',
        4  => 'साउन',   5  => 'भदौ',    6  => 'असोज',
        7  => 'कार्तिक', 8  => 'मंसिर',  9  => 'पुष',
        10 => 'माघ',    11 => 'फागुन',  12 => 'चैत'
    ];

    public static array $dayNamesNepali = [
        'Sun' => 'आइत', 'Mon' => 'सोम', 'Tue' => 'मंगल',
        'Wed' => 'बुध', 'Thu' => 'बिही', 'Fri' => 'शुक्र',
        'Sat' => 'शनि'
    ];

    // Convert AD date string (Y-m-d) to BS array [year, month, day]
    public static function adToBs(string $adDate): array {
        if (empty($adDate) || $adDate === '0000-00-00') return [0, 0, 0];

        // Parse Y-m-d parts directly to avoid timezone/DST rounding errors
        $parts = explode('-', substr($adDate, 0, 10));
        if (count($parts) !== 3) return [0, 0, 0];
        $adY = (int)$parts[0];
        $adM = (int)$parts[1];
        $adD = (int)$parts[2];

        // Use gregoriantojd (timezone-independent Julian day) for exact day counting
        $targetJd = gregoriantojd($adM, $adD, $adY);
        $refJd    = gregoriantojd(self::$adRefMonth, self::$adRefDay, self::$adRefYear);
        $diffDays = $targetJd - $refJd;

        if ($diffDays < 0) return [0, 0, 0];

        $bsYear  = self::$bsStartYear;
        $bsMonth = 1;
        $bsDay   = 1;

        // Walk through BS months
        $remaining = $diffDays;
        $yearIdx   = 0;
        $maxYears  = count(self::$bsMonthData);

        while ($yearIdx < $maxYears) {
            for ($m = 0; $m < 12; $m++) {
                $days = self::$bsMonthData[$yearIdx][$m];
                if ($remaining < $days) {
                    $bsYear  = self::$bsStartYear + $yearIdx;
                    $bsMonth = $m + 1;
                    $bsDay   = $remaining + 1;
                    return [$bsYear, $bsMonth, $bsDay];
                }
                $remaining -= $days;
            }
            $yearIdx++;
        }

        return [0, 0, 0]; // out of range
    }

    // Convert BS date to AD date string (Y-m-d)
    public static function bsToAd(int $bsYear, int $bsMonth, int $bsDay): string {
        $totalDays = 0;
        for ($y = self::$bsStartYear; $y < $bsYear; $y++) {
            $idx = $y - self::$bsStartYear;
            if (isset(self::$bsMonthData[$idx])) {
                foreach (self::$bsMonthData[$idx] as $d) $totalDays += $d;
            }
        }
        $idx = $bsYear - self::$bsStartYear;
        for ($m = 1; $m < $bsMonth; $m++) {
            $totalDays += self::$bsMonthData[$idx][$m - 1] ?? 30;
        }
        $totalDays += $bsDay - 1;
        // Use Julian day arithmetic to avoid timezone/DST issues
        $refJd = gregoriantojd(self::$adRefMonth, self::$adRefDay, self::$adRefYear);
        $targetJd = $refJd + $totalDays;
        // jdtounix() only supports dates from 1970 onward and throws for anything
        // earlier (which covers most BS years before ~2027) — jdtogregorian() has
        // no such restriction and works across the whole supported range.
        [$gm, $gd, $gy] = explode('/', jdtogregorian((int)$targetJd));
        return sprintf('%04d-%02d-%02d', (int)$gy, (int)$gm, (int)$gd);
    }

    // Format BS date nicely: "15 Baisakh 2081" or with Nepali month name
    public static function format(string $adDate, bool $nepaliMonth = false): string {
        [$y, $m, $d] = self::adToBs($adDate);
        if ($y === 0) return '-';
        $monthName = $nepaliMonth
            ? (self::$monthNamesNepali[$m] ?? '')
            : (self::$monthNames[$m] ?? '');
        return sprintf('%02d %s %d', $d, $monthName, $y);
    }

    // Get today in BS
    public static function today(bool $nepaliMonth = false): string {
        return self::format(date('Y-m-d'), $nepaliMonth);
    }

    // Get BS day name (Nepali)
    public static function dayName(string $adDate): string {
        $dayEn = date('D', strtotime($adDate));
        return self::$dayNamesNepali[$dayEn] ?? $dayEn;
    }

    // Get today's BS day name
    public static function todayDayName(): string {
        return self::dayName(date('Y-m-d'));
    }

    // Format with time: "15 Baisakh 2081, 10:30 AM"
    public static function formatWithTime(string $adDatetime, bool $nepaliMonth = false): string {
        if (empty($adDatetime) || $adDatetime === '0000-00-00 00:00:00') return '-';
        $datePart = substr($adDatetime, 0, 10);
        $timePart = date('g:i A', strtotime($adDatetime));
        return self::format($datePart, $nepaliMonth) . ', ' . $timePart;
    }
}

// -----------------------------------------------
// Format date in Bikram Sambat (Nepali calendar)
// -----------------------------------------------
function formatDate(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    return NepaliDate::format($date);
}

function formatDateTime(string $datetime): string {
    if (empty($datetime) || $datetime === '0000-00-00 00:00:00') return '-';
    return NepaliDate::formatWithTime($datetime);
}

// Nepali date with day name: "Monday, 15 Baisakh 2081"
function formatDateFull(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    $dayName = date('l', strtotime($date)); // English day name
    return $dayName . ', ' . NepaliDate::format($date);
}

// Short: "15 Baisakh"
function formatDateShort(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    [$y, $m, $d] = NepaliDate::adToBs($date);
    $months = ['','Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'];
    return sprintf('%02d %s', $d, $months[$m] ?? '');
}

// Today in BS
function todayBS(): string {
    return NepaliDate::today();
}

// ── BS Date Picker Widget (calendar popup) ─────────────────────────────────
// Prints the shared CSS/JS for every bsDatePicker() on the page exactly once.
function bsDatePickerAssets(): string {
    $monthNames = json_encode(array_values(array_slice(['','Baisakh','Jestha','Ashadh','Shrawan','Bhadra','Ashwin','Kartik','Mangsir','Poush','Magh','Falgun','Chaitra'], 1)));
    $monthData  = NepaliDate::monthDataJson();
    $startYear  = NepaliDate::startYear();
    [$tY, $tM, $tD] = NepaliDate::adToBs(date('Y-m-d'));

    return <<<HTML
<style>
.bsdp-wrap{position:relative;display:inline-block;width:100%;}
.bsdp-input-group{position:relative;display:flex;align-items:center;}
.bsdp-display{width:100%;box-sizing:border-box;padding:11px 14px 11px 40px;border:1.5px solid var(--border,#dde3ea);border-radius:var(--radius-sm,8px);font-family:inherit;font-size:0.9rem;color:var(--text-primary,var(--text,#1e293b));background:var(--bg-card,var(--white,#fff));cursor:pointer;outline:none;transition:border-color 0.2s,box-shadow 0.2s;}
.bsdp-display:focus,.bsdp-display:hover{border-color:var(--primary-light,var(--primary-l,#2563a8));}
.bsdp-display:focus{box-shadow:0 0 0 3px rgba(37,99,168,0.1);}
.bsdp-display::placeholder{color:#c1c9d4;}
.bsdp-icon{position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-light,var(--light,#94a3b8));cursor:pointer;pointer-events:none;font-size:0.85rem;}
.bsdp-popup{display:none;position:absolute;top:calc(100% + 8px);left:0;z-index:400;background:#fff;border-radius:16px;box-shadow:0 10px 40px rgba(0,0,0,0.12),0 2px 8px rgba(0,0,0,0.06);padding:18px;width:292px;border:1px solid rgba(0,0,0,0.04);}
.bsdp-popup.open{display:block;}
.bsdp-nav{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;}
.bsdp-navbtn{background:none;border:none;color:#adb5bd;cursor:pointer;font-size:1.3rem;width:30px;height:30px;display:flex;align-items:center;justify-content:center;border-radius:8px;flex-shrink:0;line-height:1;}
.bsdp-navbtn:hover{background:#f1f3f5;color:#495057;}
.bsdp-monthyear{display:flex;gap:2px;justify-content:center;flex:1;}
.bsdp-monthyear select{appearance:none;-webkit-appearance:none;-moz-appearance:none;border:none;background:transparent;font-size:0.95rem;font-weight:700;color:#212529;text-align:center;text-align-last:center;cursor:pointer;padding:2px 3px;}
.bsdp-monthyear select:focus{outline:none;}
.bsdp-weekdays{display:grid;grid-template-columns:repeat(7,1fr);text-align:center;font-size:0.74rem;font-weight:600;color:#adb5bd;margin-bottom:8px;}
.bsdp-weekdays span.bsdp-sat-label{color:#fa5252;}
.bsdp-days{display:grid;grid-template-columns:repeat(7,1fr);gap:3px;}
.bsdp-day{aspect-ratio:1;display:flex;align-items:center;justify-content:center;border:none;background:none;border-radius:10px;cursor:pointer;font-size:0.85rem;color:#343a40;}
.bsdp-day:hover:not(.bsdp-selected){background:#f1f3f5;}
.bsdp-day.bsdp-saturday{color:#fa5252;}
.bsdp-day.bsdp-today{color:#4dabf7;font-weight:700;}
.bsdp-day.bsdp-selected{background:#4dabf7;color:#fff;font-weight:700;}
.bsdp-day.bsdp-empty{visibility:hidden;cursor:default;}
.bsdp-clear-link{display:block;width:100%;text-align:center;margin-top:12px;padding-top:10px;border-top:1px solid #f1f3f5;font-size:0.75rem;color:#adb5bd;cursor:pointer;background:none;border-left:none;border-right:none;border-bottom:none;}
.bsdp-clear-link:hover{color:#495057;text-decoration:underline;}
</style>
<script>
if (typeof BSDP_DATA === 'undefined') {
    var BSDP_DATA = {$monthData};
    var BSDP_START_YEAR = {$startYear};
    var BSDP_MONTHS = {$monthNames};
    var BSDP_TODAY = [{$tY},{$tM},{$tD}];
    var BSDP_REF_UTC = Date.UTC(1943, 3, 14); // BS {$startYear}-01-01 = 14 April 1943
    var BSDP_STATE = {};

    function bsdpPad(n) { return String(n).padStart(2,'0'); }

    function bsdpDaysInMonth(y, m) {
        var idx = y - BSDP_START_YEAR;
        return (BSDP_DATA[idx] && BSDP_DATA[idx][m-1]) ? BSDP_DATA[idx][m-1] : 30;
    }

    function bsdpBsToAd(y, m, d) {
        var total = 0, idx;
        for (var yy = BSDP_START_YEAR; yy < y; yy++) {
            idx = yy - BSDP_START_YEAR;
            if (BSDP_DATA[idx]) total += BSDP_DATA[idx].reduce(function(a,b){return a+b;}, 0);
        }
        idx = y - BSDP_START_YEAR;
        for (var mm = 1; mm < m; mm++) total += (BSDP_DATA[idx] && BSDP_DATA[idx][mm-1]) ? BSDP_DATA[idx][mm-1] : 30;
        total += d - 1;
        var dt = new Date(BSDP_REF_UTC + total * 86400000);
        return dt.getUTCFullYear() + '-' + bsdpPad(dt.getUTCMonth()+1) + '-' + bsdpPad(dt.getUTCDate());
    }

    function bsdpWeekdayOfFirst(y, m) {
        var ad = bsdpBsToAd(y, m, 1).split('-').map(Number);
        return new Date(Date.UTC(ad[0], ad[1]-1, ad[2])).getUTCDay();
    }

    function bsdpFormat(y, m, d) {
        return bsdpPad(d) + ' ' + BSDP_MONTHS[m-1] + ' ' + y;
    }

    function bsdpEl(uid, part) { return document.getElementById(uid + (part || '')); }

    function bsdpRenderDays(uid) {
        var st = BSDP_STATE[uid];
        if (!st) return;
        var container = bsdpEl(uid, '_days');
        if (!container) return;
        var firstWd = bsdpWeekdayOfFirst(st.y, st.m);
        var numDays = bsdpDaysInMonth(st.y, st.m);
        var html = '';
        for (var i = 0; i < firstWd; i++) html += '<div class="bsdp-day bsdp-empty">.</div>';
        for (var d = 1; d <= numDays; d++) {
            var wd = (firstWd + d - 1) % 7;
            var cls = 'bsdp-day';
            if (wd === 6) cls += ' bsdp-saturday';
            if (d === BSDP_TODAY[2] && st.m === BSDP_TODAY[1] && st.y === BSDP_TODAY[0]) cls += ' bsdp-today';
            if (st.hasValue && d === st.d && st.m === st.curM && st.y === st.curY) cls += ' bsdp-selected';
            html += '<button type="button" class="' + cls + '" onclick="bsdpSelectDay(\'' + uid + '\',' + d + ')">' + d + '</button>';
        }
        container.innerHTML = html;
        var msel = bsdpEl(uid, '_msel'), ysel = bsdpEl(uid, '_ysel');
        if (msel) msel.value = st.m;
        if (ysel) ysel.value = st.y;
    }

    function bsdpToggle(uid) {
        var popup = bsdpEl(uid, '_popup');
        if (!popup) return;
        var willOpen = !popup.classList.contains('open');
        document.querySelectorAll('.bsdp-popup.open').forEach(function(p){ p.classList.remove('open'); });
        if (willOpen) {
            popup.classList.add('open');
            bsdpRenderDays(uid);
        }
    }

    function bsdpNav(uid, dir) {
        var st = BSDP_STATE[uid];
        if (!st) return;
        st.m += dir;
        if (st.m < 1) { st.m = 12; st.y--; }
        if (st.m > 12) { st.m = 1; st.y++; }
        bsdpRenderDays(uid);
    }

    function bsdpJump(uid) {
        var st = BSDP_STATE[uid];
        if (!st) return;
        var msel = bsdpEl(uid, '_msel'), ysel = bsdpEl(uid, '_ysel');
        if (msel) st.m = parseInt(msel.value, 10);
        if (ysel) st.y = parseInt(ysel.value, 10);
        bsdpRenderDays(uid);
    }

    function bsdpSelectDay(uid, d) {
        var st = BSDP_STATE[uid];
        if (!st) return;
        st.d = d; st.curM = st.m; st.curY = st.y; st.hasValue = true;
        var adVal = bsdpBsToAd(st.y, st.m, d);
        var hidden = bsdpEl(uid, '');
        var display = bsdpEl(uid, '_display');
        if (hidden) { hidden.value = adVal; hidden.dispatchEvent(new Event('change', {bubbles:true})); }
        if (display) display.value = bsdpFormat(st.y, st.m, d);
        var popup = bsdpEl(uid, '_popup');
        if (popup) popup.classList.remove('open');
    }

    function bsdpToday(uid) {
        var st = BSDP_STATE[uid];
        if (!st) return;
        st.y = BSDP_TODAY[0]; st.m = BSDP_TODAY[1];
        bsdpSelectDay(uid, BSDP_TODAY[2]);
        bsdpRenderDays(uid);
    }

    function bsdpClear(uid) {
        var st = BSDP_STATE[uid];
        if (!st) return;
        st.hasValue = false;
        var hidden = bsdpEl(uid, '');
        var display = bsdpEl(uid, '_display');
        if (hidden) { hidden.value = ''; hidden.dispatchEvent(new Event('change', {bubbles:true})); }
        if (display) display.value = '';
        var popup = bsdpEl(uid, '_popup');
        if (popup) popup.classList.remove('open');
        bsdpRenderDays(uid);
    }

    // Re-syncs a picker's visible display + internal state from its hidden
    // input's current value — for cases where JS sets the hidden input
    // directly (e.g. populating an edit modal) rather than via user click.
    function bsdpSyncFromAd(uid) {
        var hidden = bsdpEl(uid, '');
        if (!hidden) return;
        var adVal = hidden.value;
        var display = bsdpEl(uid, '_display');
        if (!adVal) {
            if (BSDP_STATE[uid]) BSDP_STATE[uid].hasValue = false;
            if (display) display.value = '';
            return;
        }
        var parts = adVal.split('-').map(Number);
        var total = Math.round((Date.UTC(parts[0], parts[1]-1, parts[2]) - BSDP_REF_UTC) / 86400000);
        var y = BSDP_START_YEAR, m = 1, d = 1, remaining = total;
        outer2: for (var i = 0; i < BSDP_DATA.length; i++) {
            for (var mm = 0; mm < 12; mm++) {
                var dim = BSDP_DATA[i][mm];
                if (remaining < dim) { y = BSDP_START_YEAR + i; m = mm + 1; d = remaining + 1; break outer2; }
                remaining -= dim;
            }
        }
        BSDP_STATE[uid] = {y:y, m:m, d:d, curY:y, curM:m, hasValue:true};
        if (display) display.value = bsdpFormat(y, m, d);
    }

    // Legacy no-op kept so any older code that still calls bsdpUpdate(uid)
    // (e.g. after injecting rows built by bsdpBuildHTML) doesn't error out —
    // the new widget keeps its hidden input in sync immediately on selection.
    function bsdpUpdate(uid) { bsdpRenderDays(uid); }

    // Build one full picker's HTML from JS (used where rows are generated
    // dynamically, e.g. exam schedule rows) — mirrors bsDatePicker() in PHP.
    function bsdpBuildHTML(name, adValue, required, uid) {
        var y = BSDP_TODAY[0], m = BSDP_TODAY[1], d = BSDP_TODAY[2];
        if (adValue) {
            var parts = adValue.split('-').map(Number);
            var total = Math.round((Date.UTC(parts[0], parts[1]-1, parts[2]) - BSDP_REF_UTC) / 86400000);
            var remaining = total;
            outer: for (var i = 0; i < BSDP_DATA.length; i++) {
                for (var mm = 0; mm < 12; mm++) {
                    var dim = BSDP_DATA[i][mm];
                    if (remaining < dim) { y = BSDP_START_YEAR + i; m = mm + 1; d = remaining + 1; break outer; }
                    remaining -= dim;
                }
            }
        } else {
            // No value given: default to today, both visually and as the actual submitted value
            adValue = bsdpBsToAd(BSDP_TODAY[0], BSDP_TODAY[1], BSDP_TODAY[2]);
        }
        BSDP_STATE[uid] = {y:y, m:m, d:d, curY:y, curM:m, hasValue:true};
        var displayVal = bsdpFormat(y, m, d);
        var req = required ? ' required' : '';
        var html = '<div class="bsdp-wrap" data-id="' + uid + '">';
        html += '<div class="bsdp-input-group">';
        html += '<input type="text" class="bsdp-display" id="' + uid + '_display" readonly placeholder="Select date" value="' + displayVal + '" onclick="bsdpToggle(\'' + uid + '\')">';
        html += '<i class="fas fa-calendar-alt bsdp-icon"></i>';
        html += '</div>';
        html += '<input type="hidden" name="' + name + '" id="' + uid + '" value="' + adValue + '"' + req + '>';
        html += '<div class="bsdp-popup" id="' + uid + '_popup">';
        html += '<div class="bsdp-nav"><button type="button" class="bsdp-navbtn" onclick="bsdpNav(\'' + uid + '\',-1)">&#8249;</button>';
        html += '<div class="bsdp-monthyear"><select id="' + uid + '_msel" onchange="bsdpJump(\'' + uid + '\')">';
        for (var mi = 1; mi <= 12; mi++) html += '<option value="' + mi + '">' + BSDP_MONTHS[mi-1] + '</option>';
        html += '</select><select id="' + uid + '_ysel" onchange="bsdpJump(\'' + uid + '\')">';
        for (var yi = BSDP_START_YEAR; yi < BSDP_START_YEAR + BSDP_DATA.length; yi++) html += '<option value="' + yi + '">' + yi + '</option>';
        html += '</select></div>';
        html += '<button type="button" class="bsdp-navbtn" onclick="bsdpNav(\'' + uid + '\',1)">&#8250;</button></div>';
        html += '<div class="bsdp-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span class="bsdp-sat-label">S</span></div>';
        html += '<div class="bsdp-days" id="' + uid + '_days"></div>';
        if (!required) html += '<button type="button" class="bsdp-clear-link" onclick="bsdpClear(\'' + uid + '\')">Clear date</button>';
        html += '</div></div>';
        return html;
    }

    document.addEventListener('click', function(e) {
        document.querySelectorAll('.bsdp-popup.open').forEach(function(popup) {
            var wrap = popup.closest('.bsdp-wrap');
            if (wrap && !wrap.contains(e.target)) popup.classList.remove('open');
        });
    });
}
</script>
HTML;
}

function bsDatePicker(string $name, string $value = '', bool $required = false, string $id = ''): string {
    static $assetsPrinted = false;
    $uid = $id ?: ('bsdp_' . substr(md5($name . $id), 0, 8));

    $hasValue = false; $bsD = 1; $bsM = 1; $bsY = 2081;
    if ($value && strlen($value) >= 8 && $value !== '0000-00-00') {
        try { [$bsY, $bsM, $bsD] = NepaliDate::adToBs($value); $hasValue = true; } catch (\Throwable $e) {}
    }
    if (!$hasValue) {
        // No usable value given: default to today, both visually (selected in
        // the grid) and as the actual value that will be submitted.
        $value = date('Y-m-d');
        [$bsY, $bsM, $bsD] = NepaliDate::adToBs($value);
        $hasValue = true;
    }
    $displayVal = sprintf('%02d %s %d', $bsD, NepaliDate::$monthNames[$bsM] ?? '', $bsY);

    $html = '';
    if (!$assetsPrinted) {
        $html .= bsDatePickerAssets();
        $assetsPrinted = true;
    }

    $req = $required ? ' required' : '';
    $html .= '<div class="bsdp-wrap" data-id="' . $uid . '">';
    $html .= '<div class="bsdp-input-group">';
    $html .= '<input type="text" class="bsdp-display" id="' . $uid . '_display" readonly placeholder="Select date" value="' . htmlspecialchars($displayVal) . '" onclick="bsdpToggle(\'' . $uid . '\')">';
    $html .= '<i class="fas fa-calendar-alt bsdp-icon"></i>';
    $html .= '</div>';
    $html .= '<input type="hidden" name="' . htmlspecialchars($name) . '" id="' . $uid . '" value="' . htmlspecialchars($value) . '"' . $req . '>';

    $html .= '<div class="bsdp-popup" id="' . $uid . '_popup">';
    $html .= '<div class="bsdp-nav"><button type="button" class="bsdp-navbtn" onclick="bsdpNav(\'' . $uid . '\',-1)">&#8249;</button>';
    $html .= '<div class="bsdp-monthyear"><select id="' . $uid . '_msel" onchange="bsdpJump(\'' . $uid . '\')">';
    for ($m = 1; $m <= 12; $m++) {
        $sel = ($m === $bsM) ? ' selected' : '';
        $html .= '<option value="' . $m . '"' . $sel . '>' . (NepaliDate::$monthNames[$m] ?? '') . '</option>';
    }
    $html .= '</select><select id="' . $uid . '_ysel" onchange="bsdpJump(\'' . $uid . '\')">';
    foreach (range(NepaliDate::startYear(), NepaliDate::startYear() + 83) as $y) {
        $sel = ($y === $bsY) ? ' selected' : '';
        $html .= '<option value="' . $y . '"' . $sel . '>' . $y . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<button type="button" class="bsdp-navbtn" onclick="bsdpNav(\'' . $uid . '\',1)">&#8250;</button></div>';
    $html .= '<div class="bsdp-weekdays"><span>S</span><span>M</span><span>T</span><span>W</span><span>T</span><span>F</span><span class="bsdp-sat-label">S</span></div>';
    $html .= '<div class="bsdp-days" id="' . $uid . '_days"></div>';
    if (!$required) $html .= '<button type="button" class="bsdp-clear-link" onclick="bsdpClear(\'' . $uid . '\')">Clear date</button>';
    $html .= '</div>'; // .bsdp-popup
    $html .= '</div>'; // .bsdp-wrap

    // Register this widget's initial state and render its grid once the DOM is ready
    $html .= '<script>(function(){';
    $html .= 'BSDP_STATE["' . $uid . '"] = {y:' . $bsY . ',m:' . $bsM . ',d:' . $bsD . ',curY:' . $bsY . ',curM:' . $bsM . ',hasValue:' . ($hasValue ? 'true' : 'false') . '};';
    $html .= 'if (document.readyState === "loading") { document.addEventListener("DOMContentLoaded", function(){ bsdpRenderDays("' . $uid . '"); }); } else { bsdpRenderDays("' . $uid . '"); }';
    $html .= '})();</script>';

    return $html;
}

// Nepali date chart label "15 Bai" style
function formatDateChart(string $date): string {
    if (empty($date) || $date === '0000-00-00') return '-';
    [$y, $m, $d] = NepaliDate::adToBs($date);
    $months = ['','Bai','Jes','Ash','Shr','Bha','Asw','Kar','Man','Pou','Mag','Fal','Cha'];
    return sprintf('%d %s', $d, $months[$m] ?? '');
}

// -----------------------------------------------
// JSON response helper
// -----------------------------------------------
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

// -----------------------------------------------
// Redirect helper
// -----------------------------------------------
function redirect(string $url): void {
    header("Location: $url");
    exit;
}

// -----------------------------------------------
// Flash message
// -----------------------------------------------
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): array {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}