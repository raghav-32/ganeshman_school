<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'Notices';
$activeMenu = 'notices';

$notices = db()->fetchAll("
    SELECT n.*, u.username as posted_by_name
    FROM notices n LEFT JOIN users u ON n.posted_by=u.id
    WHERE n.is_active=1 AND (n.target_role='all' OR n.target_role='student')
    ORDER BY n.created_at DESC
");

require_once '../includes/student_header.php';
?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-bullhorn"></i> School Notices</h3>
        <span class="badge badge-info"><?= count($notices) ?></span>
    </div>
    <div class="card-body">
        <?php if (empty($notices)): ?>
        <div class="empty-state"><i class="fas fa-bullhorn"></i><h3>No Notices</h3></div>
        <?php else: ?>
        <?php foreach ($notices as $n): ?>
        <div style="background:#fff;border:1px solid var(--border);border-radius:var(--radius-sm);padding:20px;margin-bottom:16px;border-left:4px solid var(--primary-light);">
            <h4 style="font-size:1rem;font-weight:800;margin-bottom:8px;"><?= htmlspecialchars($n['title']) ?></h4>
            <p style="font-size:0.87rem;color:var(--text-secondary);line-height:1.6;"><?= nl2br(htmlspecialchars($n['content'])) ?></p>
            <?php if (!empty($n['image'])): ?>
            <div class="notice-image" style="margin-top:12px;">
                <img src="../assets/uploads/notices/<?= htmlspecialchars($n['image']) ?>"
                    alt="Notice Image"
                    onclick="Lightbox.open(this.src, '<?= htmlspecialchars($n['title'], ENT_QUOTES) ?>')"
                    style="max-width:100%;max-height:280px;object-fit:cover;border-radius:var(--radius-sm);cursor:zoom-in;display:block;">
                <small style="color:var(--text-secondary);font-size:0.73rem;margin-top:4px;display:block;">
                    <i class="fas fa-search-plus"></i> Click image to zoom
                </small>
            </div>
            <?php endif; ?>
            <div style="display:flex;gap:14px;margin-top:12px;font-size:0.75rem;color:var(--text-light);flex-wrap:wrap;">
                <span><i class="fas fa-calendar"></i> <span data-bs-date="<?= date('Y-m-d', strtotime($n['created_at'])) ?>"><?= formatDate(substr($n['created_at'],0,10)) ?></span></span>
                <span><i class="fas fa-user"></i> <?= htmlspecialchars($n['posted_by_name']??'Admin') ?></span>
                <span><i class="fas fa-users"></i> <?= ucfirst($n['target_role']) ?></span>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once '../includes/student_footer.php'; ?>