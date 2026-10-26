<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Faculty & Semester Management';
$activeMenu = 'classes';

// Semester suggestions
$semesterList = ['1st Sem','2nd Sem','3rd Sem','4th Sem','5th Sem','6th Sem','7th Sem','8th Sem'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $faculty   = sanitize($_POST['class_name'] ?? '');
        $semester  = sanitize($_POST['section']    ?? '');
        $teacherId = (int)($_POST['class_teacher_id'] ?? 0) ?: null;

        if (!$faculty || !$semester) {
            setFlash('danger', 'Faculty and Semester are required.');
            redirect('classes.php');
        }

        $ok = db()->execute(
            "INSERT INTO classes (class_name, section, class_teacher_id) VALUES (?,?,?)",
            'ssi', [$faculty, $semester, $teacherId]
        );
        setFlash($ok ? 'success' : 'danger',
            $ok ? "\"$faculty — $semester\" added successfully."
                : "\"$faculty — $semester\" already exists.");
        redirect('classes.php');
    }

    if ($action === 'edit') {
        $id        = (int)($_POST['id']            ?? 0);
        $faculty   = sanitize($_POST['class_name'] ?? '');
        $semester  = sanitize($_POST['section']    ?? '');
        $teacherId = (int)($_POST['class_teacher_id'] ?? 0) ?: null;

        db()->execute(
            "UPDATE classes SET class_name=?, section=?, class_teacher_id=? WHERE id=?",
            'ssii', [$faculty, $semester, $teacherId, $id]
        );
        setFlash('success', "\"$faculty — $semester\" updated.");
        redirect('classes.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->execute("UPDATE students SET class_id=NULL WHERE class_id=?", 'i', [$id]);
        db()->execute("DELETE FROM classes WHERE id=?", 'i', [$id]);
        setFlash('success', 'Deleted. Students have been unassigned.');
        redirect('classes.php');
    }
}

// Filters
$filterFaculty = sanitize($_GET['faculty'] ?? '');
$filterSem     = sanitize($_GET['semester'] ?? '');

$where  = "WHERE 1=1";
$params = []; $types = '';
if ($filterFaculty) { $where .= " AND c.class_name = ?"; $params[] = $filterFaculty; $types .= 's'; }
if ($filterSem)     { $where .= " AND c.section = ?";    $params[] = $filterSem;     $types .= 's'; }

$classes = db()->fetchAll("
    SELECT c.*, t.first_name, t.last_name,
           (SELECT COUNT(*) FROM students s WHERE s.class_id=c.id AND s.is_active=1) as student_count
    FROM classes c
    LEFT JOIN teachers t ON c.class_teacher_id = t.id
    $where
    ORDER BY c.class_name, c.section
", $types, $params);

$teachers = db()->fetchAll("SELECT id, first_name, last_name FROM teachers WHERE is_active=1 ORDER BY first_name");

// Unique faculty and semester lists for filter dropdowns
$allFaculties = db()->fetchAll("SELECT DISTINCT class_name FROM classes ORDER BY class_name");
$allSemesters = db()->fetchAll("SELECT DISTINCT section FROM classes ORDER BY section");

include '../includes/admin_header.php';
?>

<div class="card">
    <div class="card-header" style="flex-direction:column;align-items:flex-start;gap:14px;">
        <div style="display:flex;align-items:center;justify-content:space-between;width:100%;flex-wrap:wrap;gap:10px;">
            <h3><i class="fas fa-university"></i> Faculty & Semesters (<?= count($classes) ?>)</h3>
            <button class="btn btn-primary btn-sm" onclick="Modal.open('addClassModal')">
                <i class="fas fa-plus"></i> Add Faculty/Semester
            </button>
        </div>
        <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;width:100%;">
            <select id="filterFaculty" class="form-control"
                    style="min-width:160px;padding:7px 10px;font-size:0.84rem;"
                    onchange="applyClassFilter()">
                <option value="">All Faculties</option>
                <?php foreach ($allFaculties as $f): ?>
                <option value="<?= htmlspecialchars($f['class_name']) ?>"
                        <?= $filterFaculty===$f['class_name']?'selected':'' ?>>
                    <?= htmlspecialchars($f['class_name']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <select id="filterSem" class="form-control"
                    style="min-width:130px;padding:7px 10px;font-size:0.84rem;"
                    onchange="applyClassFilter()">
                <option value="">All Semesters</option>
                <?php foreach ($allSemesters as $s): ?>
                <option value="<?= htmlspecialchars($s['section']) ?>"
                        <?= $filterSem===$s['section']?'selected':'' ?>>
                    <?= htmlspecialchars($s['section']) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <?php if ($filterFaculty || $filterSem): ?>
            <a href="classes.php" class="btn btn-outline btn-sm">
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
                        <th>Faculty</th>
                        <th>Semester</th>
                        <th>Class Teacher</th>
                        <th>Students</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($classes)): ?>
                    <tr><td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-university"></i>
                            <h3>No Records Found</h3>
                            <p><?= ($filterFaculty||$filterSem) ? 'Try changing your filters.' : 'Add your first faculty and semester.' ?></p>
                        </div>
                    </td></tr>
                    <?php else: ?>
                    <?php foreach ($classes as $i => $c): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($c['class_name']) ?></strong></td>
                        <td><span class="badge badge-primary"><?= htmlspecialchars($c['section']) ?></span></td>
                        <td>
                            <?php if ($c['first_name']): ?>
                            <div style="display:flex;align-items:center;gap:8px;">
                                <div class="student-avatar" style="width:28px;height:28px;font-size:0.70rem;background:linear-gradient(135deg,#059669,#10b981);">
                                    <?= strtoupper(substr($c['first_name'], 0, 1)) ?>
                                </div>
                                <?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?>
                            </div>
                            <?php else: ?>
                            <span style="color:var(--text-light);font-style:italic;">Not assigned</span>
                            <?php endif; ?>
                        </td>
                        <td><span class="badge badge-info"><?= $c['student_count'] ?> students</span></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-info btn-sm"
                                        onclick="editClass(<?= htmlspecialchars(json_encode($c)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Delete \'<?= htmlspecialchars($c['class_name'].' — '.$c['section']) ?>\'? Students will be unassigned.')">
                                        <i class="fas fa-trash"></i> Delete
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

<!-- ADD MODAL -->
<div class="modal-overlay" id="addClassModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Faculty & Semester</h3>
            <button class="modal-close" onclick="Modal.close('addClassModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Faculty <span style="color:red;">*</span></label>
                    <input type="text" name="class_name" id="addFacultyInput" class="form-control"
                           placeholder="e.g. Diploma IT, Diploma Civil, BCA" required
                           list="facultySuggestions" autocomplete="off">
                    <datalist id="facultySuggestions">
                        <?php foreach ($allFaculties as $f): ?>
                        <option value="<?= htmlspecialchars($f['class_name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                    <small style="color:var(--text-secondary);">
                        Type a new faculty or select an existing one.
                    </small>
                </div>
                <div class="form-group">
                    <label>Semester <span style="color:red;">*</span></label>
                    <input type="text" name="section" class="form-control"
                           placeholder="e.g. 1st Sem, 2nd Sem" required
                           list="semesterSuggestions" autocomplete="off">
                    <datalist id="semesterSuggestions">
                        <?php foreach ($semesterList as $sem): ?>
                        <option value="<?= $sem ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Class Teacher <span style="color:var(--text-light);font-size:0.8rem;">(Optional)</span></label>
                    <select name="class_teacher_id" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addClassModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editClassModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Faculty & Semester</h3>
            <button class="modal-close" onclick="Modal.close('editClassModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editClassId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Faculty <span style="color:red;">*</span></label>
                    <input type="text" name="class_name" id="editClassName" class="form-control"
                           required list="facultySuggestions2" autocomplete="off">
                    <datalist id="facultySuggestions2">
                        <?php foreach ($allFaculties as $f): ?>
                        <option value="<?= htmlspecialchars($f['class_name']) ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Semester <span style="color:red;">*</span></label>
                    <input type="text" name="section" id="editSection" class="form-control"
                           required list="semesterSuggestions2" autocomplete="off">
                    <datalist id="semesterSuggestions2">
                        <?php foreach ($semesterList as $sem): ?>
                        <option value="<?= $sem ?>">
                        <?php endforeach; ?>
                    </datalist>
                </div>
                <div class="form-group">
                    <label>Class Teacher <span style="color:var(--text-light);font-size:0.8rem;">(Optional)</span></label>
                    <select name="class_teacher_id" id="editClassTeacher" class="form-control">
                        <option value="">— None —</option>
                        <?php foreach ($teachers as $t): ?>
                        <option value="<?= $t['id'] ?>"><?= htmlspecialchars($t['first_name'].' '.$t['last_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div style="background:var(--bg);border-radius:var(--radius-sm);padding:12px 14px;font-size:0.83rem;color:var(--text-secondary);">
                    <i class="fas fa-info-circle" style="color:var(--info);"></i>
                    Changes apply everywhere — attendance, marks, timetable and fees.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editClassModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function applyClassFilter() {
    var fac = document.getElementById('filterFaculty').value;
    var sem = document.getElementById('filterSem').value;
    var url = 'classes.php';
    var parts = [];
    if (fac) parts.push('faculty=' + encodeURIComponent(fac));
    if (sem) parts.push('semester=' + encodeURIComponent(sem));
    if (parts.length) url += '?' + parts.join('&');
    window.location.href = url;
}

function editClass(data) {
    document.getElementById('editClassId').value      = data.id;
    document.getElementById('editClassName').value    = data.class_name;
    document.getElementById('editSection').value      = data.section;
    document.getElementById('editClassTeacher').value = data.class_teacher_id || '';
    Modal.open('editClassModal');
}
</script>

<?php include '../includes/admin_footer.php'; ?>