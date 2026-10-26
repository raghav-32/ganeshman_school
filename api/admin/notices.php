<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Notices & Announcements';
$activeMenu = 'notices';

// Ensure image column exists (safe migration)
try {
    db()->query("ALTER TABLE notices ADD COLUMN image VARCHAR(255) DEFAULT NULL");
} catch (Exception $e) { /* column already exists */ }
@db()->query("ALTER TABLE notices ADD COLUMN IF NOT EXISTS image VARCHAR(255) DEFAULT NULL");

function handleNoticeImage(): ?string {
    if (empty($_FILES['notice_image']['name'])) return null;
    $file    = $_FILES['notice_image'];
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    if (!in_array($file['type'], $allowed)) return null;
    $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
    $name = 'notice_' . time() . '_' . mt_rand(1000,9999) . '.' . $ext;
    $dir  = '../assets/uploads/notices/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $dest = $dir . $name;
    if (move_uploaded_file($file['tmp_name'], $dest)) return $name;
    return null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $title   = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $target  = sanitize($_POST['target_role'] ?? 'all');
        $userId  = currentUser()['id'];
        $image   = handleNoticeImage();

        db()->execute(
            "INSERT INTO notices (title, content, target_role, posted_by, image) VALUES (?,?,?,?,?)",
            'sssis', [$title, $content, $target, $userId, $image]
        );
        setFlash('success', 'Notice posted successfully.');
        redirect('notices.php');
    }

    if ($action === 'edit') {
        $id      = (int)($_POST['id'] ?? 0);
        $title   = sanitize($_POST['title'] ?? '');
        $content = sanitize($_POST['content'] ?? '');
        $target  = sanitize($_POST['target_role'] ?? 'all');
        $image   = handleNoticeImage();

        if ($image) {
            // Remove old image file
            $old = db()->fetchOne("SELECT image FROM notices WHERE id=?", 'i', [$id]);
            if ($old && !empty($old['image']) && file_exists('../assets/uploads/notices/' . $old['image'])) {
                @unlink('../assets/uploads/notices/' . $old['image']);
            }
            db()->execute(
                "UPDATE notices SET title=?, content=?, target_role=?, image=? WHERE id=?",
                'ssssi', [$title, $content, $target, $image, $id]
            );
        } else {
            db()->execute(
                "UPDATE notices SET title=?, content=?, target_role=? WHERE id=?",
                'sssi', [$title, $content, $target, $id]
            );
        }
        setFlash('success', 'Notice updated successfully.');
        redirect('notices.php');
    }

    if ($action === 'delete') {
        $id  = (int)($_POST['id'] ?? 0);
        $old = db()->fetchOne("SELECT image FROM notices WHERE id=?", 'i', [$id]);
        if ($old && !empty($old['image'])) {
            @unlink('../assets/uploads/notices/' . $old['image']);
        }
        db()->execute("UPDATE notices SET is_active=0 WHERE id=?", 'i', [$id]);
        setFlash('success', 'Notice removed.');
        redirect('notices.php');
    }
}

$notices = db()->fetchAll("
    SELECT n.*, u.username as posted_by_name
    FROM notices n
    LEFT JOIN users u ON n.posted_by = u.id
    WHERE n.is_active = 1
    ORDER BY n.created_at DESC
");

include '../includes/admin_header.php';
?>

<div class="grid-2">

    <!-- POST NOTICE -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-bullhorn"></i> Post New Notice</h3></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add">
                <div class="form-group">
                    <label>Notice Title *</label>
                    <input type="text" name="title" class="form-control" placeholder="e.g. Annual Sports Day" required>
                </div>
                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" class="form-control" rows="5"
                        placeholder="Write notice content here..." required style="resize:vertical;"></textarea>
                </div>
                <div class="form-group">
                    <label>Attach Image <span style="font-weight:400;color:var(--text-light);">(Optional – JPG/PNG/GIF)</span></label>
                    <input type="file" name="notice_image" class="form-control" accept="image/*"
                        onchange="previewNoticeImage(this,'addPreview')">
                    <div id="addPreview" style="display:none;margin-top:8px;">
                        <img src="" style="max-height:120px;border-radius:var(--radius-sm);width:100%;object-fit:cover;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_role" class="form-control">
                        <option value="all">Everyone (All Users)</option>
                        <option value="student">Students Only</option>
                        <option value="teacher">Teachers Only</option>
                        <option value="admin">Admin Only</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary btn-full">
                    <i class="fas fa-paper-plane"></i> Post Notice
                </button>
            </form>
        </div>
    </div>

    <!-- NOTICES LIST -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> All Notices (<?= count($notices) ?>)</h3>
        </div>
        <div class="card-body" style="padding:0 22px;max-height:640px;overflow-y:auto;">
            <?php if (empty($notices)): ?>
            <div class="empty-state" style="padding:32px;">
                <i class="fas fa-bullhorn"></i>
                <h3>No Notices Yet</h3>
            </div>
            <?php else: ?>
            <?php foreach ($notices as $n): ?>
            <?php $imgVal = isset($n['image']) ? $n['image'] : ''; ?>
            <div style="padding:16px 0;border-bottom:1px solid var(--border-light);">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:10px;">
                    <div style="flex:1;min-width:0;">
                        <div style="display:flex;align-items:center;gap:8px;margin-bottom:5px;flex-wrap:wrap;">
                            <h4 style="font-size:0.92rem;font-weight:800;color:var(--text-primary);">
                                <?= htmlspecialchars($n['title']) ?>
                            </h4>
                            <?php
                            $tc = ['all'=>'primary','student'=>'info','teacher'=>'success','admin'=>'warning'];
                            $badgeColor = $tc[$n['target_role']] ?? 'gray';
                            ?>
                            <span class="badge badge-<?= $badgeColor ?>">
                                <?= ucfirst($n['target_role']) ?>
                            </span>
                        </div>
                        <p style="font-size:0.83rem;color:var(--text-secondary);line-height:1.5;word-break:break-word;">
                            <?= htmlspecialchars(substr($n['content'], 0, 100)) ?>...
                        </p>
                        <?php if (!empty($imgVal)): ?>
                        <div style="margin-top:8px;">
                            <img src="../assets/uploads/notices/<?= htmlspecialchars($imgVal) ?>"
                                onclick="Lightbox.open(this.src, '<?= htmlspecialchars(addslashes($n['title'])) ?>')"
                                style="max-height:80px;max-width:160px;object-fit:cover;border-radius:6px;cursor:zoom-in;display:block;">
                        </div>
                        <?php endif; ?>
                        <div style="font-size:0.73rem;color:var(--text-light);margin-top:6px;display:flex;gap:12px;flex-wrap:wrap;">
                            <span><i class="fas fa-calendar"></i> <?= formatDate(substr($n['created_at'],0,10)) ?></span>
                            <span><i class="fas fa-user"></i> <?= htmlspecialchars($n['posted_by_name'] ?? 'Admin') ?></span>
                        </div>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:5px;flex-shrink:0;">
                        <button class="btn btn-info btn-sm"
                            onclick='editNotice(<?= json_encode(['id'=>$n['id'],'title'=>$n['title'],'content'=>$n['content'],'target_role'=>$n['target_role'],'image'=>$imgVal]) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <form method="POST">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= $n['id'] ?>">
                            <button type="submit" class="btn btn-danger btn-sm"
                                onclick="return confirm('Remove this notice?')">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- EDIT NOTICE MODAL -->
<div class="modal-overlay" id="editNoticeModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Notice</h3>
            <button class="modal-close" onclick="Modal.close('editNoticeModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editNoticeId">
            <div class="modal-body">
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="editNoticeTitle" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Content *</label>
                    <textarea name="content" id="editNoticeContent" class="form-control"
                        rows="4" required style="resize:vertical;"></textarea>
                </div>
                <div class="form-group">
                    <label>Replace Image <span style="font-weight:400;color:var(--text-light);">(leave blank to keep current)</span></label>
                    <div id="editCurrentImage" style="margin-bottom:8px;"></div>
                    <input type="file" name="notice_image" class="form-control" accept="image/*"
                        onchange="previewNoticeImage(this,'editPreview')">
                    <div id="editPreview" style="display:none;margin-top:6px;">
                        <img src="" style="max-height:100px;border-radius:6px;object-fit:cover;">
                    </div>
                </div>
                <div class="form-group">
                    <label>Target Audience</label>
                    <select name="target_role" id="editNoticeTarget" class="form-control">
                        <option value="all">Everyone</option>
                        <option value="student">Students Only</option>
                        <option value="teacher">Teachers Only</option>
                        <option value="admin">Admin Only</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editNoticeModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function editNotice(n) {
    document.getElementById('editNoticeId').value      = n.id;
    document.getElementById('editNoticeTitle').value   = n.title;
    document.getElementById('editNoticeContent').value = n.content;
    document.getElementById('editNoticeTarget').value  = n.target_role;

    const imgDiv = document.getElementById('editCurrentImage');
    if (n.image) {
        imgDiv.innerHTML = `
            <img src="../assets/uploads/notices/${n.image}"
                style="max-height:70px;border-radius:6px;cursor:zoom-in;object-fit:cover;"
                onclick="Lightbox.open(this.src,'${n.title}')">
            <small style="color:var(--text-secondary);display:block;margin-top:3px;">
                <i class="fas fa-image"></i> Current image (upload new to replace)
            </small>`;
    } else {
        imgDiv.innerHTML = '<small style="color:var(--text-light);"><i class="fas fa-image"></i> No image attached</small>';
    }
    Modal.open('editNoticeModal');
}

function previewNoticeImage(input, previewId) {
    const div = document.getElementById(previewId);
    const img = div.querySelector('img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => { img.src = e.target.result; div.style.display = 'block'; };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>

<?php include '../includes/admin_footer.php'; ?>