<?php
// ============================================================
// Ganeshman Singh Academy - Attendance API (AJAX)
// ============================================================

require_once '../includes/auth.php';
requireLogin('../index.php');

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// -----------------------------------------------
// SUBMIT ATTENDANCE (Teacher/Admin)
// -----------------------------------------------
if ($action === 'submit') {
    $classId  = (int)($_POST['class_id'] ?? 0);
    $date     = sanitize($_POST['date'] ?? date('Y-m-d'));
    $records  = json_decode($_POST['records'] ?? '{}', true);
    $role     = $_SESSION['role'];
    $teacherRefId = $_SESSION['ref_id'] ?? null;

    if (!$classId || empty($records)) {
        jsonResponse(false, 'Invalid data provided.');
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        jsonResponse(false, 'Invalid date format.');
    }

    // Determine marked_by teacher ID
    $markedBy = null;
    if ($role === 'teacher' && $teacherRefId) {
        $markedBy = $teacherRefId;
    }

    $successCount = 0;
    $errors = [];

    foreach ($records as $studentId => $status) {
        $studentId = (int)$studentId;
        $validStatuses = ['P' => 'Present', 'A' => 'Absent'];
        $statusFull = $validStatuses[$status] ?? 'Present';

        // Upsert: insert or update if already exists
        $existing = db()->fetchOne(
            "SELECT id FROM attendance WHERE student_id=? AND attendance_date=?",
            'is', [$studentId, $date]
        );

        if ($existing) {
            $ok = db()->execute(
                "UPDATE attendance SET status=?, marked_by=? WHERE student_id=? AND attendance_date=?",
                'siis', [$statusFull, $markedBy, $studentId, $date]
            );
        } else {
            $ok = db()->execute(
                "INSERT INTO attendance (student_id, class_id, attendance_date, status, marked_by) VALUES (?,?,?,?,?)",
                'iissi', [$studentId, $classId, $date, $statusFull, $markedBy]
            );
        }

        if ($ok) {
            $successCount++;
        } else {
            $errors[] = $studentId;
        }
    }

    if ($successCount > 0) {
        jsonResponse(true, "Attendance saved for $successCount student(s).", [
            'count'  => $successCount,
            'errors' => $errors
        ]);
    } else {
        jsonResponse(false, 'Failed to save attendance.');
    }
}

// -----------------------------------------------
// GET ATTENDANCE FOR A CLASS + DATE
// -----------------------------------------------
if ($action === 'get') {
    $classId = (int)($_GET['class_id'] ?? 0);
    $date    = sanitize($_GET['date'] ?? date('Y-m-d'));

    $records = db()->fetchAll(
        "SELECT a.student_id, a.status, s.first_name, s.last_name, s.student_id as sid
         FROM attendance a
         JOIN students s ON a.student_id = s.id
         WHERE a.class_id = ? AND a.attendance_date = ?",
        'is', [$classId, $date]
    );

    jsonResponse(true, 'OK', ['records' => $records]);
}

// -----------------------------------------------
// GET STUDENT ATTENDANCE SUMMARY
// -----------------------------------------------
if ($action === 'student_summary') {
    $studentId = (int)($_GET['student_id'] ?? $_SESSION['ref_id'] ?? 0);

    // Only students can only view their own
    if ($_SESSION['role'] === 'student' && $studentId !== (int)$_SESSION['ref_id']) {
        jsonResponse(false, 'Access denied.');
    }

    $summary = db()->fetchOne(
        "SELECT
            COUNT(*) as total,
            SUM(status = 'Present') as present,
            SUM(status = 'Absent')  as absent,
            0 as late
         FROM attendance WHERE student_id=?",
        'i', [$studentId]
    );

    $percentage = ($summary['total'] > 0)
        ? round(($summary['present'] / $summary['total']) * 100, 1)
        : 0;

    jsonResponse(true, 'OK', [
        'summary'    => $summary,
        'percentage' => $percentage
    ]);
}

// -----------------------------------------------
// GET CLASS STUDENTS FOR ATTENDANCE MARKING
// -----------------------------------------------
if ($action === 'get_students') {
    $classId = (int)($_GET['class_id'] ?? 0);
    if (!$classId) jsonResponse(false, 'Class ID required.');

    $students = db()->fetchAll(
        "SELECT id, student_id, first_name, last_name FROM students WHERE class_id=? AND is_active=1 ORDER BY first_name",
        'i', [$classId]
    );

    jsonResponse(true, 'OK', ['students' => $students]);
}

jsonResponse(false, 'Invalid action.');