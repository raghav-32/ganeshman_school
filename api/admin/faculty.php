<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Faculty Management';
$activeMenu = 'faculty';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = sanitize($_POST['faculty_name'] ?? '');
        $code = sanitize($_POST['faculty_code'] ?? '');
        $desc = sanitize($_POST['description']  ?? '');
        if ($name) {
            $ok = db()->execute(
                "INSERT INTO faculties (faculty_name, faculty_code, description) VALUES (?,?,?)",
                'sss', [$name, $code, $desc]
            );
            setFlash($ok ? 'success' : 'danger', $ok ? "Faculty '$name' added." : 'Faculty already exists.');
        }
        redirect('faculty.php');
    }

    if ($action === 'edit') {
        $id   = (int)($_POST['id'] ?? 0);
        $name = sanitize($_POST['faculty_name'] ?? '');
        $code = sanitize($_POST['faculty_code'] ?? '');
        $desc = sanitize($_POST['description']  ?? '');
        db()->execute(
            "UPDATE faculties SET faculty_name=?, faculty_code=?, description=? WHERE id=?",
            'sssi', [$name, $code, $desc, $id]
        );
        setFlash('success', "Faculty '$name' updated.");
        redirect('faculty.php');
    }

    if ($action === 'toggle') {
        $id  = (int)($_POST['id'] ?? 0);
        $cur = (int)($_POST['current'] ?? 1);
        db()->execute("UPDATE faculties SET is_active=? WHERE id=?", 'ii', [!$cur, $id]);
        setFlash('success', $cur ? 'Faculty deactivated.' : 'Faculty activated.');
        redirect('faculty.php');
    }

    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        // Check if any classes use this faculty
        $inUse = db()->fetchOne("SELECT COUNT(*) as c FROM classes WHERE faculty_id=?", 'i', [$id])['c'] ?? 0;
        if ($inUse > 0) {
            setFlash('danger', "Cannot delete — $inUse class(es) are using this faculty. Remove them first.");
        } else {
            db()->execute("DELETE FROM faculties WHERE id=?", 'i', [$id]);
            setFlash('success', 'Faculty deleted.');
        }
        redirect('faculty.php');
    }
}

$faculties = db()->fetchAll("
    SELECT f.*,
           (SELECT COUNT(*) FROM classes c WHERE c.faculty_id = f.id) as class_count
    FROM faculties f
    ORDER BY f.faculty_name
");

include '../includes/admin_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-university"></i> Faculties (<?= count($faculties) ?>)</h3>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addFacultyModal')">
            <i class="fas fa-plus"></i> Add Faculty
        </button>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Faculty Name</th>
                        <th>Code</th>
                        <th>Description</th>
                        <th>Classes</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($faculties)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <i class="fas fa-university"></i>
                            <h3>No Faculties Added</h3>
                            <p>Add your first faculty like "Diploma in IT" or "BCA"</p>
                        </div>
                    </td></tr>
                    <?php else: ?>
                    <?php foreach ($faculties as $i => $f): ?>
                    <tr>
                        <td><?= $i + 1 ?></td>
                        <td><strong><?= htmlspecialchars($f['faculty_name']) ?></strong></td>
                        <td>
                            <?php if ($f['faculty_code']): ?>
                            <span class="badge badge-primary"><?= htmlspecialchars($f['faculty_code']) ?></span>
                            <?php else: ?><span style="color:var(--text-light);">—</span><?php endif; ?>
                        </td>
                        <td style="font-size:0.83rem;color:var(--text-secondary);">
                            <?= htmlspecialchars($f['description'] ?: '—') ?>
                        </td>
                        <td><span class="badge badge-info"><?= $f['class_count'] ?> classes</span></td>
                        <td>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                <input type="hidden" name="current" value="<?= $f['is_active'] ?>">
                                <button type="submit" class="badge badge-<?= $f['is_active'] ? 'success' : 'gray' ?>"
                                        style="border:none;cursor:pointer;padding:4px 10px;">
                                    <?= $f['is_active'] ? '✓ Active' : 'Inactive' ?>
                                </button>
                            </form>
                        </td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-info btn-sm"
                                        onclick="editFaculty(<?= htmlspecialchars(json_encode($f)) ?>)">
                                    <i class="fas fa-edit"></i> Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $f['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm"
                                            onclick="return confirm('Delete faculty \'<?= htmlspecialchars($f['faculty_name']) ?>\'? This cannot be undone.')">
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

<!-- ADD MODAL -->
<div class="modal-overlay" id="addFacultyModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Faculty</h3>
            <button class="modal-close" onclick="Modal.close('addFacultyModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="form-group">
                    <label>Faculty Name <span style="color:red;">*</span></label>
                    <input type="text" name="faculty_name" class="form-control"
                           placeholder="e.g. Diploma in IT, BCA, +2 Science" required>
                </div>
                <div class="form-group">
                    <label>Short Code</label>
                    <input type="text" name="faculty_code" class="form-control"
                           placeholder="e.g. DIT, BCA, SCI" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" class="form-control"
                           placeholder="Brief description (optional)">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addFacultyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Faculty</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT MODAL -->
<div class="modal-overlay" id="editFacultyModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Faculty</h3>
            <button class="modal-close" onclick="Modal.close('editFacultyModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="ef_id">
            <div class="modal-body">
                <div class="form-group">
                    <label>Faculty Name <span style="color:red;">*</span></label>
                    <input type="text" name="faculty_name" id="ef_name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Short Code</label>
                    <input type="text" name="faculty_code" id="ef_code" class="form-control" maxlength="20">
                </div>
                <div class="form-group">
                    <label>Description</label>
                    <input type="text" name="description" id="ef_desc" class="form-control">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editFacultyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editFaculty(f) {
    document.getElementById('ef_id').value   = f.id;
    document.getElementById('ef_name').value = f.faculty_name;
    document.getElementById('ef_code').value = f.faculty_code || '';
    document.getElementById('ef_desc').value = f.description  || '';
    Modal.open('editFacultyModal');
}
</script>

<?php include '../includes/admin_footer.php'; ?>