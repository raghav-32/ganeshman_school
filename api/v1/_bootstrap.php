<?php
// ============================================================
// Ganeshman Singh Academy - Mobile API v1 (student + teacher)
// Shared bootstrap: JSON envelope, token auth, validators, helpers.
//
// Every endpoint starts with:
//     require __DIR__ . '/../_bootstrap.php';
// ============================================================

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === '_bootstrap.php') {
    http_response_code(404);
    exit;
}

define('API_NO_SESSION', true);            // token auth, no PHP session cookie
require_once __DIR__ . '/../../includes/auth.php';   // db(), calculateGrade(), NepaliDate, hashPassword()

const API_TOKEN_DAYS   = 30;               // token lifetime, extended while the app is in use
const API_FINE_PER_DAY = 5;                // Rs per overdue day (same as admin/library.php)

error_reporting(E_ALL);
ini_set('display_errors', '0');            // never let a PHP warning corrupt the JSON body
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

// -----------------------------------------------
// Response helpers
// -----------------------------------------------

// Text in the DB was stored through sanitize() (HTML-escaped) by the web forms,
// so decode it here: the Android app shows plain text, not HTML.
function api_clean($v) {
    if (is_string($v)) return html_entity_decode($v, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if (is_array($v))  return array_map('api_clean', $v);
    return $v;
}

function api_send(int $status, array $body): void {
    http_response_code($status);
    echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    exit;
}

function api_ok(array $data = [], string $message = 'OK', int $status = 200): void {
    api_send($status, [
        'success' => true,
        'message' => $message,
        'data'    => $data ? api_clean($data) : new stdClass(),
    ]);
}

function api_fail(int $status, string $message, array $errors = []): void {
    $body = ['success' => false, 'message' => $message];
    if ($errors) $body['errors'] = $errors;
    api_send($status, $body);
}

set_exception_handler(function (Throwable $e) {
    error_log('[api/v1] ' . $e);
    if (!headers_sent()) api_fail(500, 'Server error. Please try again.');
});
register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true) && !headers_sent()) {
        error_log('[api/v1] fatal: ' . $err['message']);
        api_fail(500, 'Server error. Please try again.');
    }
});

// -----------------------------------------------
// Request helpers
// -----------------------------------------------
function api_method(string ...$allowed): string {
    $m = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    if (!in_array($m, $allowed, true)) {
        header('Allow: ' . implode(', ', $allowed));
        api_fail(405, 'Method not allowed. Use ' . implode(' or ', $allowed) . '.');
    }
    return $m;
}

// JSON body (what Retrofit sends) with form-encoded fallback.
function api_input(): array {
    static $in = null;
    if ($in !== null) return $in;
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $in  = $raw === '' ? [] : json_decode($raw, true);
        if (!is_array($in)) api_fail(400, 'Request body must be valid JSON.');
    } else {
        $in = $_POST;
    }
    return $in;
}

function api_valid_date(string $d): bool {
    $dt = DateTime::createFromFormat('!Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

function api_valid_month(string $m): bool {
    $dt = DateTime::createFromFormat('!Y-m', $m);
    return $dt && $dt->format('Y-m') === $m;
}

// Positive int from the query string / body, or 0 when missing/invalid.
function api_int($v): int {
    return (is_int($v) || (is_string($v) && ctype_digit($v))) ? (int)$v : 0;
}

// limit/offset with sane caps
function api_page(int $defaultLimit = 50): array {
    $limit  = api_int($_GET['limit'] ?? 0) ?: $defaultLimit;
    $offset = api_int($_GET['offset'] ?? 0);
    return [min($limit, 100), $offset];
}

// -----------------------------------------------
// Schema helpers (older databases miss some lazily-created objects)
// -----------------------------------------------
function api_has_column(string $table, string $column): bool {
    static $cache = [];
    $k = "$table.$column";
    if (!isset($cache[$k])) {
        $cache[$k] = !empty(db()->fetchAll(
            "SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
            'ss', [$table, $column]
        ));
    }
    return $cache[$k];
}

function api_has_table(string $table): bool {
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = !empty(db()->fetchAll(
            "SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            's', [$table]
        ));
    }
    return $cache[$table];
}

function api_ensure_token_table(): void {
    db()->query("CREATE TABLE IF NOT EXISTS api_tokens (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        token_hash CHAR(64) NOT NULL UNIQUE,
        device_name VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        last_used_at DATETIME DEFAULT NULL,
        expires_at DATETIME NOT NULL,
        INDEX idx_api_tokens_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function api_ensure_book_requests_table(): void {
    // Same DDL as student/library.php (the web portal creates it lazily too).
    db()->query("CREATE TABLE IF NOT EXISTS book_requests (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   INT NOT NULL,
        book_id      INT NOT NULL,
        request_date DATE NOT NULL,
        status       ENUM('Pending','Issued','Cancelled') DEFAULT 'Pending',
        note         VARCHAR(255) DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id)    REFERENCES books(id)    ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// -----------------------------------------------
// Token authentication
// -----------------------------------------------
function api_bearer_token(): ?string {
    $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if ($h === '' && function_exists('getallheaders')) {
        foreach (getallheaders() as $k => $v) {
            if (strcasecmp($k, 'Authorization') === 0) { $h = $v; break; }
        }
    }
    if (preg_match('/^Bearer\s+([A-Za-z0-9]{64})$/', trim($h), $m)) return $m[1];
    // Some shared hosts strip Authorization; X-Auth-Token is a safe fallback.
    $x = trim($_SERVER['HTTP_X_AUTH_TOKEN'] ?? '');
    return preg_match('/^[A-Za-z0-9]{64}$/', $x) ? $x : null;
}

function api_issue_token(int $userId, ?string $device): array {
    api_ensure_token_table();
    $token   = bin2hex(random_bytes(32));
    $now     = date('Y-m-d H:i:s');
    $expires = date('Y-m-d H:i:s', time() + API_TOKEN_DAYS * 86400);
    db()->execute(
        "INSERT INTO api_tokens (user_id, token_hash, device_name, created_at, last_used_at, expires_at) VALUES (?,?,?,?,?,?)",
        'isssss', [$userId, hash('sha256', $token), $device ? mb_substr($device, 0, 100) : null, $now, $now, $expires]
    );
    // housekeeping: drop this user's expired tokens
    db()->execute("DELETE FROM api_tokens WHERE user_id=? AND expires_at < ?", 'is', [$userId, $now]);
    return ['token' => $token, 'expires_at' => $expires];
}

// Returns the authenticated context, or ends the request with 401/403.
// api_auth('student'), api_auth('teacher'), api_auth('student','teacher')
function api_auth(string ...$roles): array {
    $token = api_bearer_token();
    if (!$token) api_fail(401, 'Authentication required.');
    if (!api_has_table('api_tokens')) api_fail(401, 'Session expired. Please sign in again.');

    $now = date('Y-m-d H:i:s');
    $row = db()->fetchOne(
        "SELECT t.id AS token_id, t.last_used_at, u.id AS user_id, u.username, u.role, u.email
         FROM api_tokens t JOIN users u ON u.id = t.user_id
         WHERE t.token_hash = ? AND t.expires_at > ? AND u.is_active = 1",
        'ss', [hash('sha256', $token), $now]
    );
    if (!$row) api_fail(401, 'Session expired. Please sign in again.');
    if (!in_array($row['role'], ['student', 'teacher'], true) || ($roles && !in_array($row['role'], $roles, true))) {
        api_fail(403, 'Your account does not have access to this resource.');
    }

    // Sliding expiry, written at most once an hour
    if (!$row['last_used_at'] || strtotime($row['last_used_at']) < time() - 3600) {
        db()->execute(
            "UPDATE api_tokens SET last_used_at=?, expires_at=? WHERE id=?",
            'ssi', [$now, date('Y-m-d H:i:s', time() + API_TOKEN_DAYS * 86400), $row['token_id']]
        );
    }

    $table = $row['role'] === 'student' ? 'students' : 'teachers';
    $p = db()->fetchOne("SELECT id, CONCAT(first_name,' ',last_name) AS name FROM $table WHERE user_id=?", 'i', [$row['user_id']]);
    if (!$p) api_fail(403, 'No ' . $row['role'] . ' profile is linked to this account.');

    ensureStudentColumns();
    return [
        'token_id' => (int)$row['token_id'],
        'user_id'  => (int)$row['user_id'],
        'username' => $row['username'],
        'role'     => $row['role'],
        'email'    => $row['email'],
        'ref_id'   => (int)$p['id'],        // students.id or teachers.id
        'name'     => $p['name'],
    ];
}

// -----------------------------------------------
// Formatting helpers
// -----------------------------------------------
function api_upload_url(string $folder, ?string $file): ?string {
    if (!$file || $file === 'default.png') return null;
    $file = basename($file);
    if (!is_file(__DIR__ . "/../../assets/uploads/$folder/$file")) return null;
    return rtrim(BASE_URL, '/') . "/assets/uploads/$folder/" . rawurlencode($file);
}

function api_bs(?string $date): ?string {
    if (!$date || $date === '0000-00-00') return null;
    $s = NepaliDate::format($date);
    return $s === '-' ? null : $s;
}

function api_time(?string $t): ?string {
    return $t ? substr($t, 0, 5) : null;      // "09:30:00" -> "09:30"
}

function api_class_label(?array $c): ?string {
    return $c ? trim(($c['class_name'] ?? '') . ' ' . ($c['section'] ?? '')) : null;
}

function api_class_payload(?array $r): ?array {
    if (!$r || empty($r['class_id'])) return null;
    return [
        'id'      => (int)$r['class_id'],
        'name'    => $r['class_name'],
        'section' => $r['section'],
        'label'   => api_class_label($r),
    ];
}

function api_notice_payload(array $n): array {
    return [
        'id'         => (int)$n['id'],
        'title'      => $n['title'],
        'content'    => $n['content'],
        'image_url'  => api_upload_url('notices', $n['image'] ?? null),
        'audience'   => $n['target_role'],
        'posted_by'  => $n['posted_by_name'] ?? null,
        'created_at' => $n['created_at'],
        'date_bs'    => api_bs(substr($n['created_at'], 0, 10)),
    ];
}

// Notices visible to a role ('student' or 'teacher'), newest first.
function api_notices(string $role, int $limit, int $offset = 0): array {
    $rows = db()->fetchAll(
        "SELECT n.*, u.username AS posted_by_name
         FROM notices n LEFT JOIN users u ON n.posted_by = u.id
         WHERE n.is_active = 1 AND (n.target_role = 'all' OR n.target_role = ?)
         ORDER BY n.created_at DESC, n.id DESC LIMIT ? OFFSET ?",
        'sii', [$role, $limit, $offset]
    );
    return array_map('api_notice_payload', $rows);
}

function api_attendance_summary(array $r): array {
    $total   = (int)($r['total'] ?? 0);
    $present = (int)($r['present'] ?? 0);
    return [
        'total'      => $total,
        'present'    => $present,
        'absent'     => (int)($r['absent'] ?? 0),
        'late'       => (int)($r['late'] ?? 0),
        'leave'      => (int)($r['leave_days'] ?? 0),
        'percentage' => $total > 0 ? round($present / $total * 100, 1) : 0,
    ];
}

const API_ATT_SUMMARY_SQL = "COUNT(*) AS total,
    COALESCE(SUM(status='Present'),0) AS present,
    COALESCE(SUM(status='Absent'),0)  AS absent,
    COALESCE(SUM(status='Late'),0)    AS late,
    COALESCE(SUM(status='Leave'),0)   AS leave_days";

// -----------------------------------------------
// Student helpers
// -----------------------------------------------
function api_student_profile(int $studentId): ?array {
    $s = db()->fetchOne(
        "SELECT s.*, c.class_name, c.section, u.username, u.email AS login_email, u.last_login
         FROM students s
         LEFT JOIN classes c ON s.class_id = c.id
         LEFT JOIN users u   ON s.user_id = u.id
         WHERE s.id = ?", 'i', [$studentId]
    );
    if (!$s) return null;
    return [
        'role'              => 'student',
        'id'                => (int)$s['id'],
        'student_code'      => $s['student_id'],
        'username'          => $s['username'],
        'first_name'        => $s['first_name'],
        'last_name'         => $s['last_name'],
        'full_name'         => trim($s['first_name'] . ' ' . $s['last_name']),
        'dob'               => $s['dob'],
        'dob_bs'            => api_bs($s['dob']),
        'gender'            => $s['gender'],
        'address'           => $s['address'],
        'phone'             => $s['phone'],
        'email'             => $s['email'] ?: $s['login_email'],
        'guardian_name'     => $s['guardian_name'],
        'guardian_phone'    => $s['guardian_phone'],
        'guardian_relation' => $s['guardian_relation'],
        'class'             => api_class_payload($s),
        'admission_date'    => $s['admission_date'],
        'admission_date_bs' => api_bs($s['admission_date']),
        'photo_url'         => api_upload_url('students', $s['photo']),
        'last_login'        => $s['last_login'],
    ];
}

function api_teacher_profile(int $teacherId): ?array {
    $t = db()->fetchOne(
        "SELECT t.*, u.username, u.email AS login_email, u.last_login
         FROM teachers t LEFT JOIN users u ON t.user_id = u.id WHERE t.id = ?", 'i', [$teacherId]
    );
    if (!$t) return null;
    // salary is deliberately not exposed
    return [
        'role'              => 'teacher',
        'id'                => (int)$t['id'],
        'teacher_code'      => $t['teacher_id'],
        'username'          => $t['username'],
        'first_name'        => $t['first_name'],
        'last_name'         => $t['last_name'],
        'full_name'         => trim($t['first_name'] . ' ' . $t['last_name']),
        'dob'               => $t['dob'],
        'dob_bs'            => api_bs($t['dob']),
        'gender'            => $t['gender'],
        'address'           => $t['address'],
        'phone'             => $t['phone'],
        'email'             => $t['email'] ?: $t['login_email'],
        'qualification'     => $t['qualification'],
        'joining_date'      => $t['joining_date'],
        'joining_date_bs'   => api_bs($t['joining_date']),
        'photo_url'         => api_upload_url('teachers', $t['photo']),
        'last_login'        => $t['last_login'],
    ];
}

function api_profile(array $auth): array {
    $p = $auth['role'] === 'student' ? api_student_profile($auth['ref_id']) : api_teacher_profile($auth['ref_id']);
    if (!$p) api_fail(404, 'Profile not found.');
    return $p;
}

// Fee numbers exactly as student/fees.php computes them.
function api_student_fees(int $studentId, int $classId): array {
    $structures = $classId
        ? db()->fetchAll("SELECT * FROM fee_structure WHERE class_id=? ORDER BY fee_type", 'i', [$classId]) : [];
    $payments = db()->fetchAll(
        "SELECT fp.*, fs.fee_type, fs.amount AS fee_amount
         FROM fee_payments fp JOIN fee_structure fs ON fp.fee_structure_id = fs.id
         WHERE fp.student_id = ? ORDER BY fp.payment_date DESC, fp.id DESC", 'i', [$studentId]
    );
    $paidMap = [];
    foreach ($payments as $p) {
        $paidMap[$p['fee_structure_id']] = ($paidMap[$p['fee_structure_id']] ?? 0) + (float)$p['amount_paid'];
    }
    $fees = [];
    foreach ($structures as $fs) {
        $amount = (float)$fs['amount'];
        $paid   = (float)($paidMap[$fs['id']] ?? 0);
        $fees[] = [
            'fee_structure_id' => (int)$fs['id'],
            'fee_type'         => $fs['fee_type'],
            'academic_year'    => $fs['academic_year'],
            'due_day_of_month' => (int)$fs['due_date'],
            'amount'           => $amount,
            'paid'             => $paid,
            'due'              => max(0, $amount - $paid),
            'percentage'       => $amount > 0 ? min(100, (int)round($paid / $amount * 100)) : 0,
            'status'           => $paid >= $amount ? 'Paid' : ($paid > 0 ? 'Partial' : 'Unpaid'),
        ];
    }
    $totalFee  = array_sum(array_column($fees, 'amount'));
    $totalPaid = array_sum($paidMap);
    return [
        'summary'  => [
            'total_fee'  => $totalFee,
            'total_paid' => $totalPaid,
            'total_due'  => max(0, $totalFee - $totalPaid),
        ],
        'fees'     => $fees,
        'payments' => array_map(fn($p) => [
            'id'             => (int)$p['id'],
            'receipt_number' => $p['receipt_number'],
            'fee_type'       => $p['fee_type'],
            'amount_paid'    => (float)$p['amount_paid'],
            'payment_date'   => $p['payment_date'],
            'payment_date_bs'=> api_bs($p['payment_date']),
            'payment_method' => $p['payment_method'],
            'is_partial'     => !empty($p['is_partial']),
            'remarks'        => $p['remarks'],
        ], $payments),
    ];
}

function api_issued_books(int $studentId): array {
    $rows = db()->fetchAll(
        "SELECT ib.*, b.title, b.author, b.shelf_location
         FROM issued_books ib JOIN books b ON ib.book_id = b.id
         WHERE ib.student_id = ? AND ib.status = 'Issued' ORDER BY ib.issue_date DESC", 'i', [$studentId]
    );
    $today = date('Y-m-d');
    return array_map(function ($ib) use ($today) {
        $daysLate = max(0, (int)floor((strtotime($today) - strtotime($ib['due_date'])) / 86400));
        return [
            'id'             => (int)$ib['id'],
            'book_id'        => (int)$ib['book_id'],
            'title'          => $ib['title'],
            'author'         => $ib['author'],
            'shelf_location' => $ib['shelf_location'],
            'issue_date'     => $ib['issue_date'],
            'issue_date_bs'  => api_bs($ib['issue_date']),
            'due_date'       => $ib['due_date'],
            'due_date_bs'    => api_bs($ib['due_date']),
            'is_overdue'     => $daysLate > 0,
            'days_left'      => (int)ceil((strtotime($ib['due_date']) - strtotime($today)) / 86400),
            'overdue_days'   => $daysLate,
            'estimated_fine' => $daysLate * API_FINE_PER_DAY,
        ];
    }, $rows);
}

// Students must have published results; falls back to "nothing published" on old schemas.
function api_results_published_sql(): string {
    return api_has_column('exams', 'results_published') ? 'e.results_published' : '0';
}

// -----------------------------------------------
// Teacher helpers
// -----------------------------------------------
// Classes a teacher is linked to: class teacher, timetable, or assignment (same union the web panel uses).
function api_teacher_classes(int $teacherId): array {
    return array_map(function ($c) {
        return [
            'id'               => (int)$c['id'],
            'name'             => $c['class_name'],
            'section'          => $c['section'],
            'label'            => api_class_label($c),
            'is_class_teacher' => (bool)$c['is_class_teacher'],
            'student_count'    => (int)$c['student_count'],
        ];
    }, db()->fetchAll(
        "SELECT c.id, c.class_name, c.section, (c.class_teacher_id = ?) AS is_class_teacher,
                (SELECT COUNT(*) FROM students s WHERE s.class_id = c.id AND s.is_active = 1) AS student_count
         FROM classes c
         WHERE c.class_teacher_id = ?
            OR c.id IN (SELECT class_id FROM timetable WHERE teacher_id = ?)
            OR c.id IN (SELECT class_id FROM teacher_assignments WHERE teacher_id = ?)
         ORDER BY c.class_name, c.section",
        'iiii', [$teacherId, $teacherId, $teacherId, $teacherId]
    ));
}

// The teacher's class with this id, or ends the request with 403.
function api_teacher_class_or_fail(int $teacherId, int $classId): array {
    if (!$classId) api_fail(422, 'class_id is required.', ['class_id' => 'Required.']);
    foreach (api_teacher_classes($teacherId) as $c) {
        if ($c['id'] === $classId) return $c;
    }
    api_fail(403, 'You are not assigned to this class.');
}
