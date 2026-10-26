<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Subjects Management';
$activeMenu = 'subjects';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name      = sanitize($_POST['subject_name'] ?? '');
        $code      = sanitize($_POST['subject_code'] ?? '');
        $classId   = (int)($_POST['class_id']  ?? 0);
        $fullMarks = (int)($_POST['full_marks'] ?? 100);
        $passMarks = (int)($_POST['pass_marks'] ?? 40);

        if (!$name) { setFlash('danger', 'Subject name is required.'); redirect('subjects.php'); }

        // Allow same subject name in different classes but not duplicate in same class
        $exists = db()->fetchOne(
            "SELECT id FROM subjects WHERE subject_name=? AND class_id=?",
            'si', [$name, $classId]
        );
        if ($exists) {
            setFlash('danger', "\"$name\" already exists for this faculty/semester.");
            redirect('subjects.php');
        }

        $ok = db()->execute(
            "INSERT INTO subjects (subject_name, subject_code, class_id, full_marks, pass_marks) VALUES (?,?,?,?,?)",
            'ssiii', [$name, $code, $classId, $fullMarks, $passMarks]
        );
        setFlash($ok ? 'success' : 'danger', $ok ? "Subject \"$name\" added." : 'Error adding subject.');
        redirect('subjects.php');
    }

    if ($action === 'edit') {
        $id        = (int)($_POST['id']          ?? 0);
        $name      = sanitize($_POST['subject_name'] ?? '');
        $code      = sanitize($_POST['subject_code'] ?? '');
        $classId   = (int)($_POST['class_id']    ?? 0);
        $fullMarks = (int)($_POST['full_marks']   ?? 100);
        $passMarks = (int)($_POST['pass_marks']   ?? 40);

        // Check duplicate only against other rows
        $exists = db()->fetchOne(
            "SELECT id FROM subjects WHERE subject_name=? AND class_id=? AND id!=?",
            'sii', [$name, $classId, $id]
        );
        if ($exists) {
            setFlash('danger', "\"$name\" already exists for this faculty/semester.");
            redirect('subjects.php');
        }

        db()->execute(
            "UPDATE subjects SET subject_name=?, subject_code=?, class_id=?, full_marks=?, pass_marks=? WHERE id=?",
            'ssiiii', [$name, $code, $classId, $fullMarks, $passMarks, $id]
        );
        setFlash('success', "Subject \"$name\" updated.");
        redirect('subjects.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute("DELETE FROM subjects WHERE id=?", 'i', [$id]);
        setFlash('success', 'Subject deleted.');
        redirect('subjects.php');
    }
}

// Filters
$filterFaculty = sanitize($_GET['faculty'] ?? '');
$filterClass   = (int)($_GET['class_id']  ?? 0);
$search        = sanitize($_GET['search'] ?? '');

$where  = "WHERE 1=1";
$params = []; $types = '';

if ($filterFaculty) {
    $where .= " AND c.class_name = ?"; $params[] = $filterFaculty; $types .= 's';
}
if ($filterClass) {
    $where .= " AND s.class_id = ?"; $params[] = $filterClass; $types .= 'i';
}
if ($search) {
    $where .= " AND s.subject_name LIKE ?"; $params[] = "%$search%"; $types .= 's';
}

$subjects = db()->fetchAll("
    SELECT s.*, c.class_name, c.section
    FROM subjects s
    LEFT JOIN classes c ON s.class_id = c.id
    $where
    ORDER BY c.class_name, c.section, s.subject_name
", $types, $params);

// All classes for dropdowns — grouped by faculty
$allClasses   = db()->fetchAll("SELECT * FROM classes ORDER BY class_name, section");
$allFaculties = db()->fetchAll("SELECT DISTINCT class_name FROM classes ORDER BY class_name");

// Group classes by faculty for optgroup
$classesByFaculty = [];
foreach ($allClasses as $c) {
    $classesByFaculty[$c['class_name']][] = $c;
}

// Classes filtered by selected faculty (for second dropdown)
$semesterOptions = $filterFaculty
    ? db()->fetchAll("SELECT * FROM classes WHERE class_name=? ORDER BY section", 's', [$filterFaculty])
    : [];

include '../includes/admin_header.php';
?>

<div class="card">
    <div class="card-header" style="flex-direction:column;align-items:flex-start;gap:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;flex-wrap:wrap;gap:10px;">
            <h3><i class="fas fa-book-open"></i> Subjects (<?= count($subjects) ?>)</h3>
            <button class="btn btn-primary btn-sm" onclick="Modal.open('addSubjectModal')">
                <i class="fas fa-plus"></i> Add Subject
            </button>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;width:100%;">
            <select id="subFaculty" class="form-control"
                    style="min-width:160px;padding:7px 10px;font-size:0.84rem;"
                    onchange="applySubFilter(true)">
                <option value="">All Faculties</option>
                <?php foreach ($allFaculties as $f): ?>
                <option value="<?= htmlspecialchars($f['class_name']) ?>"
                        <?= $filterFaculty===$f['class_name']?'selected':'' ?>>
                    <?= htmlspecialchars($f['class_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select id="subClass" class="form-control"
                    style="min-width:140px;padding:7px 10px;font-size:0.84rem;"
                    onchange="applySubFilter(false)">
                <option value="">All Semesters</option>
                <?php
                $semList2 = $filterFaculty ? $semesterOptions : $allClasses;
                foreach ($semList2 as $sc):
                ?>
                <option value="<?= $sc['id'] ?>" <?= $filterClass==$sc['id']?'selected':'' ?>>
                    <?= $filterFaculty ? htmlspecialchars($sc['section']) : htmlspecialchars($sc['class_name'].' — '.$sc['section']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <!-- Search still uses regular form submit (text search) -->
            <form method="GET" style="display:contents;">
                <?php if ($filterFaculty): ?><input type="hidden" name="faculty" value="<?= htmlspecialchars($filterFaculty) ?>"><?php endif; ?>
                <?php if ($filterClass): ?><input type="hidden" name="class_id" value="<?= $filterClass ?>"><?php endif; ?>
                <div style="position:relative;flex:1;min-width:140px;">
                    <i class="fas fa-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-light);font-size:0.78rem;pointer-events:none;"></i>
                    <input type="text" name="search" value="<?= htmlspecialchars($search) ?>"
                           placeholder="Search subject..."
                           class="form-control"
                           style="padding-left:30px;width:100%;padding-top:7px;padding-bottom:7px;font-size:0.84rem;">
                </div>
                <button type="submit" class="btn btn-outline btn-sm"><i class="fas fa-search"></i></button>
            </form>
            <?php if ($filterFaculty || $filterClass || $search): ?>
            <a href="subjects.php" class="btn btn-outline btn-sm">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Subject Name</th>
                        <th>Code</th>
                        <th>Faculty</th>
                        <th>Semester</th>
                        <th>Full Marks</th>
                        <th>Pass Marks</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($subjects)): ?>
                    <tr><td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-book-open"></i>
                            <h3>No Subjects Found</h3>
                            <p><?= ($filterFaculty||$filterClass||$search) ? 'Try changing your filters.' : 'Add your first subject.' ?></p>
                        </div>
                    </td></tr>
                    <?php else: ?>
                    <?php foreach ($subjects as $i => $s): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($s['subject_name']) ?></strong></td>
                        <td>
                            <?php if ($s['subject_code']): ?>
                            <span class="badge badge-primary"><?= htmlspecialchars($s['subject_code']) ?></span>
                            <?php else: ?><span style="color:var(--text-light);">—</span><?php endif; ?>
                        </td>
                        <td><?= htmlspecialchars($s['class_name'] ?? '—') ?></td>
                        <td>
                            <?php if ($s['section']): ?>
                            <span class="badge badge-warning"><?= htmlspecialchars($s['section']) ?></span>
                            <?php else: ?><span style="color:var(--text-light);">—</span><?php endif; ?>
                        </td>
                        <td><?= $s['full_marks'] ?></td>
                        <td><?= $s['pass_marks'] ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-info btn-sm"
                                        onclick="editSubject(<?= htmlspecialchars(json_encode($s)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $s['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Delete \'<?= htmlspecialchars($s['subject_name']) ?>\'? Related marks will also be removed.')">
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

<!-- ADD SUBJECT MODAL -->
<div class="modal-overlay" id="addSubjectModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Subject</h3>
            <button class="modal-close" onclick="Modal.close('addSubjectModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Subject Name <span style="color:red;">*</span></label>
                    <input type="text" name="subject_name" class="form-control"
                           placeholder="e.g. Applied English, Engineering Mathematics I" required>
                    <small style="color:var(--text-secondary);">
                        Same subject can be added to different faculties/semesters.
                    </small>
                </div>
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" class="form-control" placeholder="e.g. EG1101SH">
                </div>
                <div class="form-group">
                    <label>Faculty / Semester <span style="color:red;">*</span></label>
                    <select name="class_id" class="form-control" required>
                        <option value="">— Select Faculty & Semester —</option>
                        <?php foreach ($classesByFaculty as $facName => $grpClasses): ?>
                        <optgroup label="<?= htmlspecialchars($facName) ?>">
                            <?php foreach ($grpClasses as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['section']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Marks</label>
                        <input type="number" name="full_marks" class="form-control" value="100" min="1">
                    </div>
                    <div class="form-group">
                        <label>Pass Marks</label>
                        <input type="number" name="pass_marks" class="form-control" value="40" min="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addSubjectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Subject</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT SUBJECT MODAL -->
<div class="modal-overlay" id="editSubjectModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Subject</h3>
            <button class="modal-close" onclick="Modal.close('editSubjectModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="es_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Subject Name <span style="color:red;">*</span></label>
                    <input type="text" name="subject_name" id="es_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Subject Code</label>
                    <input type="text" name="subject_code" id="es_code" class="form-control">
                </div>
                <div class="form-group">
                    <label>Faculty / Semester</label>
                    <select name="class_id" id="es_class" class="form-control">
                        <option value="0">— Select Faculty & Semester —</option>
                        <?php foreach ($classesByFaculty as $facName => $grpClasses): ?>
                        <optgroup label="<?= htmlspecialchars($facName) ?>">
                            <?php foreach ($grpClasses as $c): ?>
                            <option value="<?= $c['id'] ?>">
                                <?= htmlspecialchars($c['section']) ?>
                            </option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="grid-2">
                    <div class="form-group">
                        <label>Full Marks</label>
                        <input type="number" name="full_marks" id="es_full" class="form-control" min="1">
                    </div>
                    <div class="form-group">
                        <label>Pass Marks</label>
                        <input type="number" name="pass_marks" id="es_pass" class="form-control" min="1">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editSubjectModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editSubject(s) {
    document.getElementById('es_id').value    = s.id;
    document.getElementById('es_name').value  = s.subject_name;
    document.getElementById('es_code').value  = s.subject_code || '';
    document.getElementById('es_class').value = s.class_id || '0';
    document.getElementById('es_full').value  = s.full_marks;
    document.getElementById('es_pass').value  = s.pass_marks;
    Modal.open('editSubjectModal');
}
</script>


<script>
function applySubFilter(facultyChanged) {
    var fac = document.getElementById('subFaculty').value;
    var cls = facultyChanged ? '' : document.getElementById('subClass').value;
    var url = 'subjects.php';
    var parts = [];
    if (fac) parts.push('faculty=' + encodeURIComponent(fac));
    if (cls) parts.push('class_id=' + encodeURIComponent(cls));
    if (parts.length) url += '?' + parts.join('&');
    window.location.href = url;
}
</script>
<?php include '../includes/admin_footer.php'; ?>