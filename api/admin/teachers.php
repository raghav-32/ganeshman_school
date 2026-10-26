<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Teacher Management';
$activeMenu = 'teachers';

function uploadTeacherPhoto(): ?string {
    if (empty($_FILES['photo']['name'])) return null;
    $file    = $_FILES['photo'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    if ($file['size'] > 2 * 1024 * 1024) return null;
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'teacher_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    $dir  = '../assets/uploads/teachers/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    if (move_uploaded_file($file['tmp_name'], $dir . $name)) return $name;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $firstName = sanitize($_POST['first_name']   ?? '');
        $lastName  = sanitize($_POST['last_name']    ?? '');
        $dob       = sanitize($_POST['dob']          ?? '');
        $gender    = sanitize($_POST['gender']       ?? '');
        $address   = sanitize($_POST['address']      ?? '');
        $phone     = sanitize($_POST['phone']        ?? '');
        $email     = sanitize($_POST['email']        ?? '');
        $qual      = sanitize($_POST['qualification']?? '');
        $joinDate  = sanitize($_POST['joining_date'] ?? date('Y-m-d'));
        $salary    = (float)($_POST['salary']        ?? 0);
        $photo     = uploadTeacherPhoto();

        if ($action === 'add') {
            $username   = sanitize($_POST['username'] ?? '');
            $password   = $_POST['password'] ?? '';
            $hashedPass = hashPassword($password ?: 'teacher123');

            // Check username before insert
            $existingUser = db()->fetchOne("SELECT id FROM users WHERE username=?", 's', [$username]);
            if ($existingUser) {
                setFlash('danger', 'Username "' . htmlspecialchars($username) . '" already exists. Please choose a different username.');
                redirect('teachers.php');
            }

            $ok = db()->execute(
                "INSERT INTO users (username, password, role, email) VALUES (?,?,?,?)",
                'ssss', [$username, $hashedPass, 'teacher', $email]
            );
            if ($ok) {
                $userId   = db()->lastInsertId();
                $photoVal = $photo ?? 'default.png';

                // Insert with TMP placeholder, then update with real GSA-style ID
                $placeholder = 'TMP' . $userId;
                $teacherOk = db()->execute(
                    "INSERT INTO teachers
                     (user_id,teacher_id,first_name,last_name,dob,gender,address,phone,email,qualification,joining_date,salary,photo)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'issssssssssds',
                    [$userId,$placeholder,$firstName,$lastName,$dob,$gender,$address,$phone,$email,$qual,$joinDate,$salary,$photoVal]
                );
                if ($teacherOk) {
                    $newId     = db()->lastInsertId();
                    $teacherId = 'TCH' . str_pad($newId, 4, '0', STR_PAD_LEFT);
                    db()->execute("UPDATE teachers SET teacher_id=? WHERE id=?", 'si', [$teacherId, $newId]);
                    setFlash('success', "Teacher $firstName $lastName added! ID: $teacherId");
                } else {
                    db()->execute("DELETE FROM users WHERE id=?", 'i', [$userId]);
                    $errMsg = db()->lastError ?: 'Unknown error.';
                    setFlash('danger', 'Teacher record could not be saved: ' . htmlspecialchars($errMsg));
                }
            } else {
                setFlash('danger', 'Username already exists.');
            }

        } else {
            $id = (int)($_POST['id'] ?? 0);
            if ($photo) {
                $old = db()->fetchOne("SELECT photo FROM teachers WHERE id=?", 'i', [$id]);
                if ($old && !empty($old['photo']) && $old['photo'] !== 'default.png') {
                    @unlink('../assets/uploads/teachers/' . $old['photo']);
                }
                db()->execute(
                    "UPDATE teachers SET first_name=?,last_name=?,dob=?,gender=?,address=?,phone=?,email=?,qualification=?,joining_date=?,salary=?,photo=? WHERE id=?",
                    'sssssssssdsi',
                    [$firstName,$lastName,$dob,$gender,$address,$phone,$email,$qual,$joinDate,$salary,$photo,$id]
                );
            } else {
                db()->execute(
                    "UPDATE teachers SET first_name=?,last_name=?,dob=?,gender=?,address=?,phone=?,email=?,qualification=?,joining_date=?,salary=? WHERE id=?",
                    'sssssssssdi',
                    [$firstName,$lastName,$dob,$gender,$address,$phone,$email,$qual,$joinDate,$salary,$id]
                );
            }
            // Keep the login account's email in sync with the teacher's contact email
            db()->execute(
                "UPDATE users u INNER JOIN teachers t ON u.id = t.user_id SET u.email=? WHERE t.id=?",
                'si', [$email, $id]
            );
            setFlash('success', 'Teacher updated.');
        }
        redirect('teachers.php');
    }

    if ($action === 'delete') {
        $id      = (int)($_POST['id'] ?? 0);
        $teacher = db()->fetchOne("SELECT user_id, photo FROM teachers WHERE id=?", 'i', [$id]);
        if ($teacher) {
            if (!empty($teacher['photo']) && $teacher['photo'] !== 'default.png') {
                @unlink('../assets/uploads/teachers/' . $teacher['photo']);
            }
            db()->execute("DELETE FROM users WHERE id=?", 'i', [$teacher['user_id']]);
            setFlash('success', 'Teacher deleted.');
        }
        redirect('teachers.php');
    }
}

$teachers = db()->fetchAll("SELECT t.*, u.username FROM teachers t LEFT JOIN users u ON t.user_id=u.id WHERE t.is_active=1 ORDER BY t.created_at DESC");

include '../includes/admin_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-chalkboard-teacher"></i> All Teachers (<?= count($teachers) ?>)</h3>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addTeacherModal')">
            <i class="fas fa-plus"></i> Add Teacher
        </button>
    </div>
    <div class="card-body">
        <div class="search-bar">
            <div class="search-input-wrap">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Search teachers...">
            </div>
        </div>
        <div class="table-responsive">
            <table class="table" id="dataTable">
                <thead>
                    <tr><th>#</th><th>Photo</th><th>Teacher</th><th>ID</th><th>Qualification</th><th>Phone</th><th>Email</th><th>Salary</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($teachers)): ?>
                    <tr><td colspan="9"><div class="empty-state"><i class="fas fa-chalkboard-teacher"></i><h3>No Teachers Added</h3></div></td></tr>
                    <?php else: ?>
                    <?php foreach ($teachers as $i => $t): ?>
                    <?php
                    $photoFile = $t['photo'] ?? 'default.png';
                    $hasPhoto  = ($photoFile !== 'default.png' && file_exists('../assets/uploads/teachers/' . $photoFile));
                    ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td>
                            <?php if ($hasPhoto): ?>
                            <img src="../assets/uploads/teachers/<?= htmlspecialchars($photoFile) ?>"
                                style="width:38px;height:38px;border-radius:50%;object-fit:cover;cursor:zoom-in;border:2px solid var(--border);"
                                onclick="Lightbox.open(this.src,'<?= htmlspecialchars(addslashes($t['first_name'].' '.$t['last_name'])) ?>')">
                            <?php else: ?>
                            <div class="student-avatar" style="width:38px;height:38px;font-size:0.85rem;background:linear-gradient(135deg,#059669,#10b981);">
                                <?= strtoupper(substr($t['first_name'],0,1)) ?>
                            </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="font-weight:700;"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></div>
                            <div style="font-size:0.72rem;color:var(--text-secondary);"><?= htmlspecialchars($t['gender']??'') ?></div>
                        </td>
                        <td><span class="badge badge-success"><?= htmlspecialchars($t['teacher_id']) ?></span></td>
                        <td><?= htmlspecialchars($t['qualification']??'–') ?></td>
                        <td><?= htmlspecialchars($t['phone']??'–') ?></td>
                        <td><?= htmlspecialchars($t['email']??'–') ?></td>
                        <td>Rs <?= number_format($t['salary'],0) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-info btn-sm" onclick="editTeacher(<?= htmlspecialchars(json_encode($t)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $t['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Delete teacher?')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
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

<!-- ADD TEACHER MODAL -->
<div class="modal-overlay" id="addTeacherModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-plus"></i> Add New Teacher</h3>
            <button class="modal-close" onclick="Modal.close('addTeacherModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <!-- Photo -->
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:var(--radius-sm);">
                    <div id="addTPhotoPreview" style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;flex-shrink:0;overflow:hidden;">
                        <i class="fas fa-user-tie"></i>
                    </div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;">Teacher Photo</label>
                        <input type="file" name="photo" class="form-control" accept="image/*"
                            onchange="previewTeacherPhoto(this,'addTPhotoPreview')" style="margin-bottom:0;">
                        <small style="color:var(--text-light);">JPG/PNG, max 2MB</small>
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" class="form-control" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" class="form-control" required></div>
                    <div class="form-group"><label>Date of Birth</label><?= bsDatePicker('dob', '', false) ?></div>
                    <div class="form-group"><label>Gender</label>
                        <select name="gender" class="form-control"><option value="Male">Male</option><option value="Female">Female</option></select>
                    </div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" class="form-control"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" class="form-control"></div>
                    <div class="form-group"><label>Qualification</label><input type="text" name="qualification" class="form-control" placeholder="B.Ed, M.Ed..."></div>
                    <div class="form-group"><label>Joining Date</label><?= bsDatePicker('joining_date', date('Y-m-d'), false) ?></div>
                    <div class="form-group"><label>Salary (NPR)</label><input type="number" name="salary" class="form-control" value="0"></div>
                    <div class="form-group"><label>Address</label><input type="text" name="address" class="form-control"></div>
                    <div class="form-group"><label>Username *</label><input type="text" name="username" class="form-control" required></div>
                    <div class="form-group"><label>Password <span style="font-weight:400;color:var(--text-light);">(default: teacher123)</span></label><input type="password" name="password" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addTeacherModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Teacher</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT TEACHER MODAL -->
<div class="modal-overlay" id="editTeacherModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-user-edit"></i> Edit Teacher</h3>
            <button class="modal-close" onclick="Modal.close('editTeacherModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="etId">
            <div class="modal-body">
                <!-- Photo -->
                <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;padding:16px;background:var(--bg);border-radius:var(--radius-sm);">
                    <div id="editTPhotoPreview" style="width:70px;height:70px;border-radius:50%;background:linear-gradient(135deg,#059669,#10b981);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.6rem;flex-shrink:0;overflow:hidden;"></div>
                    <div style="flex:1;">
                        <label style="display:block;font-size:0.82rem;font-weight:700;color:var(--text-secondary);margin-bottom:6px;text-transform:uppercase;">Change Photo <span style="font-weight:400;color:var(--text-light);">(leave blank to keep)</span></label>
                        <input type="file" name="photo" class="form-control" accept="image/*"
                            onchange="previewTeacherPhoto(this,'editTPhotoPreview')" style="margin-bottom:0;">
                    </div>
                </div>
                <div class="grid-2">
                    <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="etFirstName" class="form-control" required></div>
                    <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="etLastName" class="form-control" required></div>
                    <div class="form-group"><label>Date of Birth</label><?= bsDatePicker('dob', '', false, 'etDob') ?></div>
                    <div class="form-group"><label>Gender</label><select name="gender" id="etGender" class="form-control"><option value="Male">Male</option><option value="Female">Female</option></select></div>
                    <div class="form-group"><label>Phone</label><input type="text" name="phone" id="etPhone" class="form-control"></div>
                    <div class="form-group"><label>Email</label><input type="email" name="email" id="etEmail" class="form-control"></div>
                    <div class="form-group"><label>Qualification</label><input type="text" name="qualification" id="etQual" class="form-control"></div>
                    <div class="form-group"><label>Joining Date</label><?= bsDatePicker('joining_date', '', false, 'etJoinDate') ?></div>
                    <div class="form-group"><label>Salary (NPR)</label><input type="number" name="salary" id="etSalary" class="form-control"></div>
                    <div class="form-group"><label>Address</label><input type="text" name="address" id="etAddress" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editTeacherModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Update Teacher</button>
            </div>
        </form>
    </div>
</div>

<script>
function editTeacher(t) {
    document.getElementById('etId').value        = t.id;
    document.getElementById('etFirstName').value = t.first_name;
    document.getElementById('etLastName').value  = t.last_name;
    document.getElementById('etDob').value       = t.dob || '';
    document.getElementById('etGender').value    = t.gender || 'Male';
    document.getElementById('etPhone').value     = t.phone || '';
    document.getElementById('etEmail').value     = t.email || '';
    document.getElementById('etQual').value      = t.qualification || '';
    document.getElementById('etJoinDate').value  = t.joining_date || '';
    document.getElementById('etSalary').value    = t.salary || 0;
    document.getElementById('etAddress').value   = t.address || '';

    const prev  = document.getElementById('editTPhotoPreview');
    const photo = t.photo && t.photo !== 'default.png' ? t.photo : null;
    if (photo) {
        prev.innerHTML = `<img src="../assets/uploads/teachers/${photo}" style="width:100%;height:100%;object-fit:cover;">`;
    } else {
        prev.innerHTML = `<span style="font-size:1.4rem;font-weight:800;">${t.first_name.charAt(0).toUpperCase()}</span>`;
    }
    Modal.open('editTeacherModal');
}

function previewTeacherPhoto(input, previewId) {
    const prev = document.getElementById(previewId);
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            prev.innerHTML = `<img src="${e.target.result}" style="width:100%;height:100%;object-fit:cover;">`;
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../includes/admin_footer.php'; ?>