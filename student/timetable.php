<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'My Timetable';
$activeMenu = 'timetable';
$user       = currentUser();
$studentId  = $user['ref_id'];

$student = db()->fetchOne("SELECT class_id FROM students WHERE id=?", 'i', [$studentId]);
$classId = $student['class_id'] ?? 0;

$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'];
$timetable = [];

if ($classId) {
    $rows = db()->fetchAll("
        SELECT tt.*, t.first_name, t.last_name, sub.subject_name
        FROM timetable tt
        JOIN teachers t ON tt.teacher_id = t.id
        JOIN subjects sub ON tt.subject_id = sub.id
        WHERE tt.class_id = ?
        ORDER BY FIELD(tt.day_of_week,'Sunday','Monday','Tuesday','Wednesday','Thursday','Friday'), tt.start_time
    ", 'i', [$classId]);
    foreach ($rows as $r) {
        $timetable[$r['day_of_week']][] = $r;
    }
}

$colors = ['#eff6ff','#ecfdf5','#fffbeb','#fef2f2','#f5f3ff','#f0fdfa'];

require_once '../includes/student_header.php';
?>

<?php if (!$classId || empty($timetable)): ?>
<div class="empty-state card" style="padding:60px;">
    <i class="fas fa-calendar-alt"></i>
    <h3>No Timetable Available</h3>
    <p>Your class timetable hasn't been set up yet. Please check with your teacher.</p>
</div>
<?php else: ?>
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-calendar-alt"></i> Weekly Class Timetable</h3>
        <button onclick="window.print()" class="btn btn-outline btn-sm no-print">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
    <div class="card-body" style="padding:0;overflow-x:auto;">
        <table class="table" style="min-width:650px;">
            <thead>
                <tr>
                    <th style="width:110px;">Day</th>
                    <th>Schedule</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($days as $i => $day): ?>
                <tr>
                    <td>
                        <strong style="color:<?= date('l')===$day?'var(--primary-light)':'var(--text-primary)' ?>">
                            <?= $day ?>
                        </strong>
                        <?php if (date('l') === $day): ?>
                        <br><span class="badge badge-primary" style="font-size:0.65rem;">Today</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!empty($timetable[$day])): ?>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;padding:8px 0;">
                            <?php foreach ($timetable[$day] as $p): ?>
                            <div style="background:<?= $colors[$i % count($colors)] ?>;border-radius:var(--radius-sm);padding:10px 14px;min-width:130px;border:1px solid var(--border-light);">
                                <div style="font-weight:800;font-size:0.83rem;color:var(--primary);">
                                    <?= htmlspecialchars($p['subject_name']) ?>
                                </div>
                                <div style="font-size:0.75rem;color:var(--text-secondary);margin-top:3px;">
                                    <?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?>
                                </div>
                                <div style="font-size:0.72rem;color:var(--text-light);margin-top:2px;">
                                    <?= substr($p['start_time'],0,5) ?> – <?= substr($p['end_time'],0,5) ?>
                                </div>
                                <?php if ($p['room_number']): ?>
                                <div style="font-size:0.70rem;color:var(--text-light);">Room: <?= htmlspecialchars($p['room_number']) ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <span style="color:var(--text-light);font-size:0.83rem;padding:8px 0;display:inline-block;">No classes</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php require_once '../includes/student_footer.php'; ?>
