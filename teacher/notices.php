<?php
require_once '../includes/auth.php';
requireRole('teacher', '../index.php');

$pageTitle  = 'Notices';
$activeMenu = 'notices';

$notices = db()->fetchAll("
    SELECT n.*, u.username as posted_by_name FROM notices n
    LEFT JOIN users u ON n.posted_by=u.id
    WHERE n.is_active=1 AND (n.target_role='all' OR n.target_role='teacher')
    ORDER BY n.created_at DESC
");

require_once '../includes/teacher_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bullhorn"></i> School Notices</h3>
        <span class="badge badge-success"><?= count($notices) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($notices)): ?>
        <div class="empty-state"><i class="fas fa-bullhorn"></i><h3>No Notices</h3></div>
        <?php else: ?>
        <?php foreach ($notices as $n): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;margin-bottom:14px;border-left:4px solid var(--success);">
            <h4 style="font-weight:800;margin-bottom:8px;"><?= htmlspecialchars($n['title']) ?></h4>
            <p style="color:var(--text-secondary);font-size:0.87rem;line-height:1.6;"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
            <?php if (!empty($n['image'])): ?>
            <div class="notice-image" style="margin-top:10px;">
                <img src="../assets/uploads/notices/<?= htmlspecialchars($n['image']) ?>"
                    onclick="Lightbox.open(this.src,'<?= htmlspecialchars($n['title'], ENT_QUOTES) ?>')"
                    style="max-width:100%;max-height:200px;object-fit:cover;border-radius:var(--radius-sm);cursor:zoom-in;display:block;">
                <small style="color:var(--text-secondary);font-size:0.72rem;margin-top:4px;display:block;"><i class="fas fa-search-plus"></i> Click to zoom</small>
            </div>
            <?php endif; ?>
            <div class="notice-meta" style="margin-top:10px;">
                <span><i class="fas fa-calendar"></i> <span data-bs-date="<?= date('Y-m-d',strtotime($n['created_at'])) ?>"><?= formatDate(substr($n['created_at'],0,10)) ?></span></span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($n['posted_by_name']??'Admin') ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/teacher_footer.php'; ?>