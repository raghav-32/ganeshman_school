<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');
ensureStudentColumns();

$pageTitle  = 'My Profile';
$activeMenu = 'profile';
$user       = currentUser();
$studentId  = $user['ref_id'];

$student = db()->fetchOne("
    SELECT s.*, c.class_name, c.section, u.username, u.email AS login_email, u.last_login
    FROM students s
    LEFT JOIN classes c ON s.class_id = c.id
    LEFT JOIN users u ON s.user_id = u.id
    WHERE s.id = ?
", 'i', [$studentId]);

// students.email is the source of truth; fall back to the login account's
// email for any older record that predates the sync fix
if ($student && empty($student['email']) && !empty($student['login_email'])) {
    $student['email'] = $student['login_email'];
}

require_once '../includes/student_header.php';
?>

<div class="grid-2">
    <!-- Profile Card -->
    <div class="card">
        <div class="profile-card">
            <div class="profile-avatar" style="overflow:hidden;padding:0;">
                <?php
                $photo = $student['photo'] ?? 'default.png';
                $photoPath = '../assets/uploads/students/' . $photo;
                if ($photo && $photo !== 'default.png' && file_exists($photoPath)):
                ?>
                    <img src="<?= htmlspecialchars($photoPath) ?>" alt="Profile Photo"
                         style="width:100%;height:100%;object-fit:cover;border-radius:50%;">
                <?php else: ?>
                    <?= strtoupper(substr($student['first_name'] ?? 'S', 0, 1)) ?>
                <?php endif; ?>
            </div>
            <h3><?= htmlspecialchars($student['first_name'].' '.$student['last_name']) ?></h3>
            <span class="profile-role">Student</span>

            <div style="margin-top:4px;margin-bottom:20px;">
                <span class="badge badge-primary" style="font-size:0.85rem;padding:5px 14px;">
                    ID: <?= htmlspecialchars($student['student_id']) ?>
                </span>
            </div>

            <div class="profile-details">
                <div class="detail-row">
                    <span class="label"><i class="fas fa-school"></i> Class</span>
                    <span class="value"><?= htmlspecialchars(($student['class_name'] ?? '–').' '.($student['section'] ?? '')) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-venus-mars"></i> Gender</span>
                    <span class="value"><?= htmlspecialchars($student['gender'] ?? '–') ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-birthday-cake"></i> Date of Birth</span>
                    <span class="value"><?= formatDate($student['dob']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-calendar-plus"></i> Admission Date</span>
                    <span class="value"><?= formatDate($student['admission_date']) ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-user"></i> Username</span>
                    <span class="value"><?= htmlspecialchars($student['username'] ?? '–') ?></span>
                </div>
                <div class="detail-row">
                    <span class="label"><i class="fas fa-clock"></i> Last Login</span>
                    <span class="value"><?= NepaliDate::formatWithTime($student['last_login'] ?? '') ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Contact & Guardian Info -->
    <div style="display:flex;flex-direction:column;gap:20px;">
        <div class="card">
            <div class="card-header"><h3><i class="fas fa-address-book"></i> Contact Information</h3></div>
            <div class="card-body">
                <div class="profile-details">
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-phone"></i> Phone</span>
                        <span class="value"><?= htmlspecialchars($student['phone'] ?? '–') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-envelope"></i> Email</span>
                        <span class="value"><?= htmlspecialchars($student['email'] ?? '–') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-map-marker-alt"></i> Address</span>
                        <span class="value"><?= htmlspecialchars($student['address'] ?? '–') ?></span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3><i class="fas fa-users"></i> Guardian Information</h3></div>
            <div class="card-body">
                <div class="profile-details">
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-user-friends"></i> Guardian Name</span>
                        <span class="value"><?= htmlspecialchars($student['guardian_name'] ?? '–') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-link"></i> Relation</span>
                        <span class="value"><?= htmlspecialchars($student['guardian_relation'] ?? '–') ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label"><i class="fas fa-phone-alt"></i> Guardian Phone</span>
                        <span class="value"><?= htmlspecialchars($student['guardian_phone'] ?? '–') ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once '../includes/student_footer.php'; ?>