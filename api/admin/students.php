<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Student Management';
$activeMenu = 'students';

// ── Photo upload helper ──────────────────────────────────────
function uploadStudentPhoto(): ?string {
    if (empty($_FILES['photo']['name'])) return null;
    $file    = $_FILES['photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null; // 2MB max
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'student_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    $dir  = '../assets/uploads/students/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) return $name;
    return null;
}

// ── POST Handlers ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $firstName    = sanitize($_POST['first_name']       ?? '');
        $lastName     = sanitize($_POST['last_name']        ?? '');
        $dob          = sanitize($_POST['dob']              ?? '');
        $gender       = sanitize($_POST['gender']           ?? '');
        $address      = sanitize($_POST['address']          ?? '');
        $phone        = sanitize($_POST['phone']            ?? '');
        $email        = sanitize($_POST['email']             ?? '');
        $guardianName = sanitize($_POST['guardian_name']    ?? '');
        $guardianPhone= sanitize($_POST['guardian_phone']   ?? '');
        $guardianRel  = sanitize($_POST['guardian_relation']?? '');
        $classId      = (int)($_POST['class_id']            ?? 0);
        $admDate      = sanitize($_POST['admission_date']   ?? date('Y-m-d'));
        $photo        = uploadStudentPhoto();

        if ($action === 'add') {
            $username   = sanitize($_POST['username'] ?? '');
            $password   = $_POST['password'] ?? '';
            $hashedPass = hashPassword($password ?: 'student123');

            // Check if username already exists before inserting
            $existingUser = db()->fetchOne("SELECT id FROM users WHERE username=?", 's', [$username]);
            if ($existingUser) {
                setFlash('danger', 'Username &quot;' . htmlspecialchars($username) . '&quot; already exists. Please choose a different username.');
                header('Location: students.php');
                exit;
            }

            $ok = db()->execute(
                "INSERT INTO users (username, password, role, email) VALUES (?,?,?,?)",
                'ssss', [$username, $hashedPass, 'student', $email]
            );
            if ($ok) {
                $userId   = db()->lastInsertId();
                $photoVal = $photo ?? 'default.png';

                // Step 1: Insert with a placeholder student_id using user_id (guaranteed unique)
                $placeholder = 'TMP' . $userId;
                $studentOk = db()->execute(
                    "INSERT INTO students
                     (user_id,student_id,first_name,last_name,dob,gender,address,phone,email,
                      guardian_name,guardian_phone,guardian_relation,class_id,admission_date,photo)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'isssssssssssiss',
                    [$userId,$placeholder,$firstName,$lastName,$dob,$gender,$address,$phone,$email,
                     $guardianName,$guardianPhone,$guardianRel,$classId,$admDate,$photoVal]
                );
                if ($studentOk) {
                    // Step 2: Now use the real auto-increment id to build the proper GSA id
                    $newId     = db()->lastInsertId();
                    $studentId = 'GSA' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                    db()->execute("UPDATE students SET student_id=? WHERE id=?", 'si', [$studentId, $newId]);
                    setFlash('success', "Student $firstName $lastName added! ID: $studentId");
                } else {
                    // Student insert failed — delete the user we just created to avoid orphan
                    db()->execute("DELETE FROM users WHERE id=?", 'i', [$userId]);
                    $errMsg = db()->lastError ?: 'Unknown database error while saving student.';
                    setFlash('danger', 'Student record could not be saved: ' . htmlspecialchars($errMsg));
                }
            } else {
                setFlash('danger', 'Username already exists or error creating account.');
            }

        } else { // edit
            $id = (int)($_POST['id'] ?? 0);
            if ($photo) {
                // Remove old photo
                $old = db()->fetchOne("SELECT photo FROM students WHERE id=?", 'i', [$id]);
                if ($old && !empty($old['photo']) && $old['photo'] !== 'default.png') {
                    @unlink('../assets/uploads/students/' . $old['photo']);
                }
                db()->execute(
                    "UPDATE students SET first_name=?,last_name=?,dob=?,gender=?,address=?,phone=?,email=?,
                     guardian_name=?,guardian_phone=?,guardian_relation=?,class_id=?,admission_date=?,photo=?
                     WHERE id=?",
                    'ssssssssssissi',
                    [$firstName,$lastName,$dob,$gender,$address,$phone,$email,
                     $guardianName,$guardianPhone,$guardianRel,$classId,$admDate,$photo,$id]
                );
            } else {
                db()->execute(
                    "UPDATE students SET first_name=?,last_name=?,dob=?,gender=?,address=?,phone=?,email=?,
                     guardian_name=?,guardian_phone=?,guardian_relation=?,class_id=?,admission_date=?
                     WHERE id=?",
                    'ssssssssssisi',
                    [$firstName,$lastName,$dob,$gender,$address,$phone,$email,
                     $guardianName,$guardianPhone,$guardianRel,$classId,$admDate,$id]
                );
            }
            // Keep the login account's email in sync with the student's contact email
            db()->execute(
                "UPDATE users u INNER JOIN students s ON u.id = s.user_id SET u.email=? WHERE s.id=?",
                'si', [$email, $id]
            );
            setFlash('success', 'Student updated successfully.');
        }
        redirect('students.php');
    }

    if ($action === 'delete') {
        $id      = (int)($_POST['id'] ?? 0);
        $student = db()->fetchOne("SELECT user_id, photo FROM students WHERE id=?", 'i', [$id]);
        if ($student) {
            if (!empty($student['photo']) && $student['photo'] !== 'default.png') {
                @unlink('../assets/uploads/students/' . $student['photo']);
            }
            db()->execute("DELETE FROM users WHERE id=?", 'i', [$student['user_id']]);
            setFlash('success', 'Student deleted.');
        }
        redirect('students.php');
    }
}

// ── Fetch Data ───────────────────────────────────────────────
$search      = sanitize($_GET['search']   ?? '');
$classFilter = (int)($_GET['class_id']    ?? 0);

$sql    = "SELECT s.*, c.class_name, c.section, u.username
           FROM students s
           LEFT JOIN classes c ON s.class_id=c.id
           LEFT JOIN users u ON s.user_id=u.id
           WHERE s.is_active=1";
$params = [];
$types  = '';

if ($search) {
    $sql   .= " AND (s.first_name LIKE ? OR s.last_name LIKE ? OR s.student_id LIKE ? OR s.email LIKE ?)";
    $like   = "%$search%";
    $params = [$like,$like,$like,$like];
    $types  = 'ssss';
}
if ($classFilter) {
    $sql    .= " AND s.class_id=?";
    $params[] = $classFilter;
    $types   .= 'i';
}
$sql .= " ORDER BY s.created_at DESC";

$students = db()->fetchAll($sql, $types, $params);
$classes  = db()->fetchAll("SELECT * FROM classes ORDER BY class_name, section");

// ── Handle approve/reject BEFORE any output ───────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'approve_reg') {
        $id  = (int)($_POST['id'] ?? 0);
        $reg = db()->fetchOne("SELECT * FROM pending_registrations WHERE id=? AND status='pending'", 'i', [$id]);
        if ($reg) {
            $ok = db()->execute("INSERT INTO users (username,password,role,email,is_active) VALUES (?,?,'student',?,1)", 'sss', [$reg['username'],$reg['password_hash'],$reg['email']??'']);
            if ($ok) {
                $uid  = db()->lastInsertId();
                $adm  = !empty($reg['admission_date']) ? $reg['admission_date'] : date('Y-m-d');
                $sok  = db()->execute(
                    "INSERT INTO students (user_id,student_id,first_name,last_name,dob,gender,address,phone,email,guardian_name,guardian_phone,guardian_relation,class_id,admission_date,photo) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'isssssssssssiss',
                    [$uid,'TMP'.$uid,$reg['first_name'],$reg['last_name'],$reg['dob'],$reg['gender'],$reg['address'],$reg['phone'],$reg['email']??'',$reg['guardian_name'],$reg['guardian_phone'],$reg['guardian_relation'],$reg['class_id'],$adm,$reg['photo']??'default.png']
                );
                if ($sok) {
                    $sid = db()->lastInsertId();
                    $gsaId = 'GSA'.str_pad($sid,4,'0',STR_PAD_LEFT);
                    db()->execute("UPDATE students SET student_id=? WHERE id=?", 'si', [$gsaId,$sid]);
                    db()->execute("UPDATE pending_registrations SET status='approved',reviewed_at=NOW() WHERE id=?", 'i', [$id]);
                    setFlash('success', "Approved! Student ID: $gsaId — " . htmlspecialchars($reg['first_name'].' '.$reg['last_name']));
                } else { db()->execute("DELETE FROM users WHERE id=?", 'i', [$uid]); setFlash('danger','Failed to create student record.'); }
            } else { setFlash('danger','Failed to create user account.'); }
        }
        redirect('students.php?tab=pending');
    }
    if ($action === 'reject_reg') {
        $id  = (int)($_POST['id'] ?? 0);
        $reg = db()->fetchOne("SELECT photo FROM pending_registrations WHERE id=?", 'i', [$id]);
        if ($reg && !empty($reg['photo'])) @unlink('../assets/uploads/students/'.$reg['photo']);
        db()->execute("UPDATE pending_registrations SET status='rejected',reviewed_at=NOW() WHERE id=?", 'i', [$id]);
        setFlash('success','Registration rejected.');
        redirect('students.php?tab=pending');
    }
}

$activeTab = $_GET['tab'] ?? 'students';

// ── Fetch Pending Registrations ───────────────────────────────
$pendingRegs = db()->fetchAll(
    "SELECT pr.*, c.class_name, c.section FROM pending_registrations pr
     LEFT JOIN classes c ON pr.class_id=c.id
     ORDER BY pr.submitted_at DESC"
);
$pendingCount = count(array_filter($pendingRegs, fn($r) => $r['status']==='pending'));

include '../includes/admin_header.php';
?>
<style>
.tab-btn {
    display:inline-flex;align-items:center;gap:8px;padding:10px 22px 12px;
    font-size:0.85rem;font-weight:700;color:var(--text-secondary);
    text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;
    transition:all 0.18s;white-space:nowrap;
}
.tab-btn:hover { color:var(--primary); }
.tab-active { color:var(--primary)!important; border-bottom-color:var(--primary)!important; }
.tab-count {
    display:inline-flex;align-items:center;justify-content:center;
    min-width:20px;height:20px;padding:0 6px;border-radius:50px;
    font-size:0.65rem;font-weight:800;background:var(--border);color:var(--text-secondary);
}
.tab-count-warn { background:#fef3c7!important;color:#92400e!important; }

/* Pending table styles */
.pr-photo { width:40px;height:40px;border-radius:50%;object-fit:cover;border:2px solid var(--border); }
.pr-pill { display:inline-flex;align-items:center;gap:5px;padding:3px 10px;border-radius:50px;font-size:0.7rem;font-weight:700; }
.pill-pending  { background:#fef3c7;color:#92400e; }
.pill-approved { background:#dcfce7;color:#15803d; }
.pill-rejected { background:#fee2e2;color:#b91c1c; }
.btn-approve { padding:6px 14px;background:#16a34a;color:#fff;border:none;border-radius:6px;font-size:0.76rem;font-weight:700;cursor:pointer;transition:all 0.2s; }
.btn-approve:hover { background:#15803d; }
.btn-reject2 { padding:6px 14px;background:#fff;color:#dc2626;border:1.5px solid #fecaca;border-radius:6px;font-size:0.76rem;font-weight:700;cursor:pointer;transition:all 0.2s; }
.btn-reject2:hover { background:#fef2f2; }
.detail-expand { font-size:0.72rem;color:var(--primary);cursor:pointer;text-decoration:underline dotted;background:none;border:none;padding:2px 0;margin-top:3px;display:block; }
.detail-box { display:none;margin-top:8px;padding:10px 14px;background:var(--bg);border-radius:8px;font-size:0.78rem;line-height:1.8; }
.detail-box.open { display:block; }
</style>

<?php $flash = getFlash(); if ($flash): ?>
<div class="alert alert-<?= $flash['type']==='success'?'success':'danger' ?> alert-dismissible mb-3">
    <i class="fas fa-<?= $flash['type']==='success'?'check-circle':'exclamation-circle' ?>"></i>
    <?= $flash['message'] ?>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;float:right;cursor:pointer;font-size:1.1rem;color:inherit;opacity:0.7;">&times;</button>
</div>
<?php endif; ?>

<!-- TABS -->
<div style="display:flex;align-items:center;gap:0;margin-bottom:24px;border-bottom:2px solid var(--border);">
    <a href="?tab=students" class="tab-btn <?= $activeTab==='students'?'tab-active':'' ?>">
        <i class="fas fa-user-graduate"></i> All Students
        <span class="tab-count" style="background:var(--primary);color:#fff;"><?= count($students) ?></span>
    </a>
    <a href="?tab=pending" class="tab-btn <?= $activeTab==='pending'?'tab-active':'' ?>">
        <i class="fas fa-user-clock"></i> Pending Registrations
        <?php if ($pendingCount > 0): ?>
        <span class="tab-count tab-count-warn"><?= $pendingCount ?></span>
        <?php endif; ?>
    </a>
</div>


<?php if ($activeTab === 'students'): ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB: ALL STUDENTS                                          -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-graduate"></i> All Students (<?= count($students) ?>)</h3>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addStudentModal')">
            <i class="fas fa-plus"></i> Add Student
        </button>
    </div>
    <div class="card-body">
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search by name or ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <select class="form-control" style="max-width:180px;" onchange="filterByClass(this.value)">
                <option value="">All Classes</option>
                <?php foreach ($classes as $cls): ?>
                <option value="<?= $cls['id'] ?>" <?= $classFilter==$cls['id']?'selected':'' ?>>
                    <?= htmlspecialchars($cls['class_name'].' '.$cls['section']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <a href="students.php" class="btn btn-outline btn-sm">Reset</a>
        </div>
        <div class="table-responsive">
            <table class="table" id="dataTable">
                <thead>
                    <tr><th>#</th><th>Photo</th><th>Student</th><th>Student ID</th><th>Class</th><th>Guardian</th><th>Phone</th><th>Email</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="fas fa-user-graduate"></i><h3>No Students Found</h3><p>Add a new student to get started.</p></div></td></tr>
                    <?php else: ?>
                    <?php foreach ($students as $i => $s): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <?php $pf=$s['photo']??'default.png'; $pp='../assets/uploads/students/'.$pf; $hp=($pf!=='default.png'&&file_exists($pp)); ?>
                            <?php if ($hp): ?>
                            <img src="../assets/uploads/students/<?= htmlspecialchars($pf) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;cursor:zoom-in;border:2px solid var(--border);" onclick="Lightbox.open(this.src,'<?= htmlspecialchars(addslashes($s['first_name'].' '.$s['last_name'])) ?>')">
                            <?php else: ?>
                            <div class="student-avatar" style="width:38px;height:38px;font-size:0.85rem;"><?= strtoupper(substr($s['first_name'],0,1)) ?></div>
                            <?php endif; ?>
                        </td>
                        <td><div style="font-weight:700;"><?= htmlspecialchars($s['first_name'].' '.$s['last_name']) ?></div><div style="font-size:0.72rem;color:var(--text-secondary);"><?= htmlspecialchars($s['gender']??'') ?></div></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($s['student_id']) ?></span></td>
                        <td><?= htmlspecialchars(($s['class_name']??'–').' '.($s['section']??'')) ?></td>
                        <td><div style="font-size:0.83rem;"><?= htmlspecialchars($s['guardian_name']??'–') ?></div><div style="font-size:0.72rem;color:var(--text-secondary);"><?= htmlspecialchars($s['guardian_relation']??'') ?></div></td>
                        <td><?= htmlspecialchars($s['phone']??'–') ?></td>
                        <td><?= htmlspecialchars($s['email']??'–') ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-info btn-sm" onclick="editStudent(<?= htmlspecialchars(json_encode($s)) ?>)"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-danger btn-sm" onclick="deleteStudent(<?= $s['id'] ?>,'<?= htmlspecialchars(addslashes($s['first_name'].' '.$s['last_name'])) ?>')"><i class="fas fa-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php else: ?>
<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TAB: PENDING REGISTRATIONS                                 -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-user-clock"></i> Pending Registrations</h3>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
            <span class="pr-pill pill-pending"><i class="fas fa-clock"></i> <?= $pendingCount ?> Pending</span>
        </div>
    </div>
    <div class="card-body">
        <?php if (empty($pendingRegs)): ?>
        <div class="empty-state"><i class="fas fa-inbox"></i><h3>No Registrations Yet</h3><p>Self-registration requests from students will appear here.</p></div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Photo</th><th>Student</th><th>Class</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($pendingRegs as $i => $reg): ?>
                <tr>
                    <td>
                        <?php if (!empty($reg['photo'])): ?>
                        <img src="../assets/uploads/students/<?= htmlspecialchars($reg['photo']) ?>" class="pr-photo" onerror="this.src='../assets/images/gmscrop.png'">
                        <?php else: ?>
                        <div class="student-avatar" style="width:40px;height:40px;font-size:0.85rem;"><?= strtoupper(substr($reg['first_name'],0,1)) ?></div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-weight:700;"><?= htmlspecialchars($reg['first_name'].' '.$reg['last_name']) ?></div>
                        <div style="font-size:0.72rem;color:var(--text-secondary);"><i class="fas fa-at" style="font-size:0.65rem;"></i> <?= htmlspecialchars($reg['username']) ?></div>
                        <button class="detail-expand" onclick="toggleDetail(<?= $i ?>)"><i class="fas fa-chevron-down" id="dicon-<?= $i ?>"></i> View details</button>
                        <div class="detail-box" id="dbox-<?= $i ?>">
                            <b>DOB:</b> <?= htmlspecialchars($reg['dob']) ?> &nbsp;|&nbsp;
                            <b>Gender:</b> <?= htmlspecialchars($reg['gender']) ?> &nbsp;|&nbsp;
                            <b>Phone:</b> <?= htmlspecialchars($reg['phone']) ?> &nbsp;|&nbsp;
                            <b>Email:</b> <?= htmlspecialchars($reg['email'] ?: '–') ?><br>
                            <b>Address:</b> <?= htmlspecialchars($reg['address']) ?><br>
                            <b>Guardian:</b> <?= htmlspecialchars($reg['guardian_name']) ?> (<?= htmlspecialchars($reg['guardian_phone']) ?>)
                            <?php if ($reg['guardian_relation']): ?> — <?= htmlspecialchars($reg['guardian_relation']) ?><?php endif; ?>
                            <?php if ($reg['admission_date']): ?><br><b>Admission Date:</b> <?= htmlspecialchars($reg['admission_date']) ?><?php endif; ?>
                        </div>
                    </td>
                    <td><?= htmlspecialchars(($reg['class_name']??'–').' '.($reg['section']??'')) ?></td>
                    <td style="font-size:0.78rem;color:var(--text-secondary);"><?= date('d M Y', strtotime($reg['submitted_at'])) ?><br><?= date('g:i A', strtotime($reg['submitted_at'])) ?></td>
                    <td><span class="pr-pill pill-<?= $reg['status'] ?>"><i class="fas fa-<?= $reg['status']==='pending'?'clock':($reg['status']==='approved'?'check':'times') ?>"></i> <?= ucfirst($reg['status']) ?></span></td>
                    <td>
                        <?php if ($reg['status'] === 'pending'): ?>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Approve <?= htmlspecialchars(addslashes($reg['first_name'].' '.$reg['last_name'])) ?>?')">
                                <input type="hidden" name="action" value="approve_reg">
                                <input type="hidden" name="id" value="<?= $reg['id'] ?>">
                                <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject this registration?')">
                                <input type="hidden" name="action" value="reject_reg">
                                <input type="hidden" name="id" value="<?= $reg['id'] ?>">
                                <button type="submit" class="btn-reject2"><i class="fas fa-times"></i> Reject</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span style="font-size:0.75rem;color:var(--text-light);">Reviewed<br><?= $reg['reviewed_at'] ? date('d M Y', strtotime($reg['reviewed_at'])) : '' ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ADD STUDENT MODAL -->
<div class="modal-overlay" id="addStudentModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Student</h3>
            <button class="modal-close" onclick="Modal.close('addStudentModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:var(--radius-sm);">
                    <div id="addPhotoPreview" style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;font-weight:800;flex-shrink:0;overflow:hidden;"><i class="fas fa-user"></i></div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;">Student Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewStudentPhoto(this,'addPhotoPreview')" style="margin-bottom:0;">
                        <small style="color:var(--text-light);">JPG/PNG, max 2MB</small>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                    <div class="form-group"><label>Date of Birth</label><?= bsDatePicker('dob', '', false) ?></div>
                    <div class="form-group"><label>Gender</label><select name="gender" class="form-control"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                    <div class="form-group"><label>Class *</label><select name="class_id" class="form-control" required><option value="">Select Class</option><?php foreach ($classes as $cls): ?><option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name'].' '.$cls['section']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Admission Date</label><?= bsDatePicker('admission_date', date('Y-m-d'), false) ?></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control" placeholder="98XXXXXXXX"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control" placeholder="student@example.com"></div>
                    <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control"></div>
                    <div class="form-group"><label>Guardian Name</label><input type="text" name="guardian_name" class="form-control"></div>
                    <div class="form-group"><label>Guardian Phone</label><input type="text" name="guardian_phone" class="form-control"></div>
                    <div class="form-group"><label>Guardian Relation</label><input type="text" name="guardian_relation" class="form-control" placeholder="Father / Mother / etc."></div>
                </div>
                <hr style="margin:16px 0;border-color:var(--border-light);">
                <p style="font-weight:700;font-size:0.85rem;margin-bottom:12px;color:var(--text-secondary);">Login Credentials</p>
                <div class="grid-2">
                    <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label>Password <span style="font-weight:400;color:var(--text-light);">(default: student123)</span></label><input type="password" name="password" class="form-control" placeholder="Leave blank for default"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Student</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT STUDENT MODAL -->
<div class="modal-overlay" id="editStudentModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Student</h3>
            <button class="modal-close" onclick="Modal.close('editStudentModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editStudentId">
            <div class="modal-body">
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:var(--radius-sm);">
                    <div id="editPhotoPreview" style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,var(--primary),var(--primary-light));display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;font-weight:800;flex-shrink:0;overflow:hidden;"></div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;">Change Photo <span style="font-weight:400;color:var(--text-light);">(leave blank to keep current)</span></label>
                        <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewStudentPhoto(this,'editPhotoPreview')" style="margin-bottom:0;">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="esFirstName" class="form-control" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="esLastName" class="form-control" required></div>
                    <div class="form-group"><label>Date of Birth</label><?= bsDatePicker('dob', '', false, 'esDob') ?></div>
                    <div class="form-group"><label>Gender</label><select name="gender" id="esGender" class="form-control"><option value="Male">Male</option><option value="Female">Female</option><option value="Other">Other</option></select></div>
                    <div class="form-group"><label>Class *</label><select name="class_id" id="esClassId" class="form-control" required><option value="">Select Class</option><?php foreach ($classes as $cls): ?><option value="<?= $cls['id'] ?>"><?= htmlspecialchars($cls['class_name'].' '.$cls['section']) ?></option><?php endforeach; ?></select></div>
                    <div class="form-group"><label>Admission Date</label><?= bsDatePicker('admission_date', '', false, 'esAdmDate') ?></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" id="esPhone" class="form-control"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="esEmail" class="form-control" placeholder="student@example.com"></div>
                    <div class="form-group"><label>Address</label><input type="text" name="address" id="esAddress" class="form-control"></div>
                    <div class="form-group"><label>Guardian Name</label><input type="text" name="guardian_name" id="esGuardianName" class="form-control"></div>
                    <div class="form-group"><label>Guardian Phone</label><input type="text" name="guardian_phone" id="esGuardianPhone" class="form-control"></div>
                    <div class="form-group"><label>Guardian Relation</label><input type="text" name="guardian_relation" id="esGuardianRel" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editStudentModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Student</button>
            </div>
        </form>
    </div>
</div>

<!-- DELETE FORM -->
<form method="POST" id="deleteForm" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="id" id="deleteStudentId">
</form>

<script>
function editStudent(s) {
    document.getElementById('editStudentId').value   = s.id;
    document.getElementById('esFirstName').value     = s.first_name;
    document.getElementById('esLastName').value      = s.last_name;
    document.getElementById('esDob').value           = s.dob || '';
    document.getElementById('esGender').value        = s.gender || 'Male';
    document.getElementById('esClassId').value       = s.class_id || '';
    document.getElementById('esAdmDate').value       = s.admission_date || '';
    document.getElementById('esPhone').value         = s.phone || '';
    document.getElementById('esEmail').value         = s.email || '';
    document.getElementById('esAddress').value       = s.address || '';
    document.getElementById('esGuardianName').value  = s.guardian_name || '';
    document.getElementById('esGuardianPhone').value = s.guardian_phone || '';
    document.getElementById('esGuardianRel').value   = s.guardian_relation || '';
    const prev = document.getElementById('editPhotoPreview');
    const photo = s.photo && s.photo !== 'default.png' ? s.photo : null;
    if (photo) { prev.innerHTML = `<img src="../assets/uploads/students/${photo}" style="width:100%;height:100%;object-fit:cover;">`; }
    else        { prev.innerHTML = `<span style="font-size:1.4rem;font-weight:800;">${s.first_name.charAt(0).toUpperCase()}</span>`; }
    Modal.open('editStudentModal');
}
function deleteStudent(id, name) {
    if (confirm(`Delete "${name}"? This removes their login account too.`)) {
        document.getElementById('deleteStudentId').value = id;
        document.getElementById('deleteForm').submit();
    }
}
function filterByClass(classId) {
    const url = new URL(window.location.href);
    if (classId) url.searchParams.set('class_id', classId);
    else url.searchParams.delete('class_id');
    window.location.href = url.toString();
}
function previewStudentPhoto(input, previewId) {
    const prev = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { prev.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`; };
        reader.readAsDataURL(input.files[0]);
    }
}
function toggleDetail(i) {
    const box  = document.getElementById('dbox-'+i);
    const icon = document.getElementById('dicon-'+i);
    const open = box.classList.toggle('open');
    icon.className = 'fas fa-chevron-'+(open?'up':'down');
}

// Live search
document.getElementById('searchInput')?.addEventListener('input', function() {
    const q = this.value.toLowerCase();
    document.querySelectorAll('#dataTable tbody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
});
</script>

<?php include '../includes/admin_footer.php'; ?>