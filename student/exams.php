<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'Exam Schedule';
$activeMenu = 'exams';
$user       = currentUser();
$studentId  = $user['ref_id'];

$student = db()->fetchOne("SELECT class_id FROM students WHERE id=?", 'i', [$studentId]);
$classId = $student['class_id'] ?? 0;

// Ensure exams table has time columns
$tcols = db()->fetchAll("SHOW COLUMNS FROM exams LIKE 'start_time'");
if (empty($tcols)) {
    db()->query("ALTER TABLE exams ADD COLUMN start_time TIME DEFAULT NULL");
    db()->query("ALTER TABLE exams ADD COLUMN end_time TIME DEFAULT NULL");
}

// Ensure exam_schedule table exists
db()->query("CREATE TABLE IF NOT EXISTS exam_schedule (
    id INT AUTO_INCREMENT PRIMARY KEY,
    exam_id INT NOT NULL,
    subject_id INT NOT NULL,
    exam_date DATE NOT NULL,
    start_time TIME DEFAULT NULL,
    end_time TIME DEFAULT NULL,
    venue VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (exam_id) REFERENCES exams(id) ON DELETE CASCADE,
    FOREIGN KEY (subject_id) REFERENCES subjects(id) ON DELETE CASCADE,
    UNIQUE KEY uq_exam_sub (exam_id, subject_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$exams = [];
if ($classId) {
    $exams = db()->fetchAll("
        SELECT e.*, c.class_name, c.section
        FROM exams e
        JOIN classes c ON e.class_id = c.id
        WHERE e.class_id = ? AND e.is_published = 1
        ORDER BY e.start_date ASC
    ", 'i', [$classId]);
}

$schedules = [];
if (!empty($exams)) {
    $examIds = implode(',', array_map('intval', array_column($exams, 'id')));
    $schedRows = db()->fetchAll("
        SELECT es.*, sub.subject_name
        FROM exam_schedule es
        JOIN subjects sub ON es.subject_id = sub.id
        WHERE es.exam_id IN ($examIds)
        ORDER BY es.exam_date ASC
    ");
    foreach ($schedRows as $sr) {
        $schedules[(int)$sr['exam_id']][] = $sr;
    }
}

require_once '../includes/student_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-file-alt"></i> Exam Schedule</h3>
        <span class="badge badge-info"><?= count($exams) ?> exams</span>
    </div>
    <div class="card-body">
        <?php if (empty($exams)): ?>
        <div class="empty-state">
            <i class="fas fa-calendar-times"></i>
            <h3>No Exams Scheduled</h3>
            <p>No published exams found for your class. Check back later.</p>
        </div>
        <?php else: ?>
        <?php foreach ($exams as $e):
            $today   = date('Y-m-d');
            $ended   = $e['end_date'] && $e['end_date'] < $today;
            $started = $e['start_date'] && $e['start_date'] <= $today;
            $status  = $ended ? 'Completed' : ($started ? 'Ongoing' : 'Upcoming');
            $statusColor = ['Completed'=>'gray','Ongoing'=>'success','Upcoming'=>'info'][$status];
            $typeColor   = ['Unit Test'=>'info','Terminal'=>'warning','Final'=>'danger','Other'=>'gray'];
            $examSched   = $schedules[$e['id']] ?? [];
        ?>
        <div class="card" style="margin-bottom:18px;">
            <div class="card-header" style="flex-wrap:wrap;gap:8px;">
                <div>
                    <h3 style="margin-bottom:3px;"><?= htmlspecialchars($e['exam_name']) ?></h3>
                    <div style="font-size:.78rem;color:var(--text-secondary);">
                        <span class="badge badge-<?= $typeColor[$e['exam_type']]??'gray' ?>"><?= $e['exam_type'] ?></span>
                        &nbsp;<?= htmlspecialchars($e['class_name'].' '.$e['section']) ?>
                        <?php if ($e['start_date']): ?>
                        &nbsp;&middot;&nbsp;<i class="fas fa-calendar-check"></i>
                        <?= formatDate($e['start_date']) ?>
                        <?php if ($e['end_date']): ?> &ndash; <?= formatDate($e['end_date']) ?><?php endif; ?>
                        <?php endif; ?>
                    </div>
                </div>
                <div style="display:flex;gap:8px;align-items:center;margin-left:auto;">
                    <span class="badge badge-<?= $statusColor ?>"><?= $status ?></span>
                    <?php if ($e['results_published']): ?>
                    <a href="results?exam_id=<?= $e['id'] ?>" class="btn btn-outline btn-sm">
                        <i class="fas fa-star-half-alt"></i> Results
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($examSched)): ?>
            <div class="card-body" style="padding:0;">
                <table class="table" style="margin:0;">
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Venue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($examSched as $sc):
                            $scDate  = $sc['exam_date'];
                            $scToday = ($scDate === $today);
                            $scPast  = ($scDate < $today);
                            $rowStyle = $scToday ? 'background:rgba(16,185,129,.06);font-weight:700;' : ($scPast ? 'opacity:.6;' : '');
                        ?>
                        <tr style="<?= $rowStyle ?>">
                            <td>
                                <?php if ($scToday): ?><span style="color:var(--success);font-size:.7rem;font-weight:800;margin-right:4px;">&#9679; TODAY</span><?php endif; ?>
                                <?= htmlspecialchars($sc['subject_name']) ?>
                            </td>
                            <td><?= formatDate($scDate) ?></td>
                            <td>
                                <?php
                                $st = $e['start_time'] ?? '';
                                $et = $e['end_time']   ?? '';
                                if ($st) {
                                    echo date('g:i A', strtotime($st));
                                    if ($et) echo ' &ndash; ' . date('g:i A', strtotime($et));
                                } else {
                                    echo '&ndash;';
                                }
                                ?>
                            </td>
                            <td><?= $sc['venue'] ? htmlspecialchars($sc['venue']) : '&ndash;' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-body" style="padding:14px 18px;">
                <p style="font-size:.82rem;color:var(--text-secondary);"><i class="fas fa-info-circle"></i> Detailed schedule not available yet.</p>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/student_footer.php'; ?>