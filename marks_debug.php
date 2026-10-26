<?php
require_once 'includes/auth.php';
requireRole('teacher', 'login');

$user      = currentUser();
$teacherId = $user['ref_id'];
$userIdCT  = (int)$user['id'];

echo '<pre>';
echo "user array:\n"; print_r($user);
echo "\nteacherId (ref_id): $teacherId\n";
echo "userIdCT (id): $userIdCT\n\n";

// Check all classes and their class_teacher_id
$classes = db()->fetchAll("SELECT id, class_name, section, class_teacher_id FROM classes");
echo "Classes:\n";
foreach ($classes as $c) {
    $match = ((int)$c['class_teacher_id'] === $userIdCT) ? " ← MATCH (userIdCT=$userIdCT)" : "";
    $match2 = ((int)$c['class_teacher_id'] === (int)$teacherId) ? " ← MATCH (teacherId=$teacherId)" : "";
    echo "  id={$c['id']} {$c['class_name']} {$c['section']} class_teacher_id={$c['class_teacher_id']}$match$match2\n";
}

// Check selected exam
$examId = (int)($_GET['exam_id'] ?? 0);
if ($examId) {
    echo "\nExam ID: $examId\n";
    $exam = db()->fetchOne("SELECT e.*, c.class_name, c.section, c.class_teacher_id FROM exams e JOIN classes c ON e.class_id=c.id WHERE e.id=?", 'i', [$examId]);
    print_r($exam);
    echo "\nCheck: class_teacher_id={$exam['class_teacher_id']} == userIdCT=$userIdCT ? " . ((int)$exam['class_teacher_id'] === $userIdCT ? "YES" : "NO") . "\n";
}
echo '</pre>';