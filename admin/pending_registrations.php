<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Pending Registrations';
$activeMenu = 'students';

// ── POST: Approve or Reject ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $id     = (int)($_POST['id'] ?? 0);

    if ($action === 'approve' && $id) {
        $reg = db()->fetchOne("SELECT * FROM pending_registrations WHERE id=? AND status='pending'", 'i', [$id]);
        if ($reg) {
            // Create user account
            $ok = db()->execute(
                "INSERT INTO users (username, password, role, email, is_active) VALUES (?,?,'student',?,1)",
                'sss', [$reg['username'], $reg['password_hash'], $reg['email'] ?? '']
            );
            if ($ok) {
                $userId = db()->lastInsertId();
                $placeholder = 'TMP' . $userId;
                $admDate = !empty($reg['admission_date']) ? $reg['admission_date'] : date('Y-m-d');

                $studentOk = db()->execute(
                    "INSERT INTO students
                     (user_id, student_id, first_name, last_name, dob, gender, address, phone, email,
                      guardian_name, guardian_phone, guardian_relation, class_id, admission_date, photo)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)",
                    'isssssssssssiss',
                    [$userId, $placeholder,
                     $reg['first_name'], $reg['last_name'], $reg['dob'], $reg['gender'],
                     $reg['address'], $reg['phone'], $reg['email'] ?? '', $reg['guardian_name'], $reg['guardian_phone'],
                     $reg['guardian_relation'], $reg['class_id'], $admDate, $reg['photo'] ?? 'default.png']
                );
                if ($studentOk) {
                    $newSId    = db()->lastInsertId();
                    $studentId = 'GSA' . str_pad($newSId, 4, '0', STR_PAD_LEFT);
                    db()->execute("UPDATE students SET student_id=? WHERE id=?", 'si', [$studentId, $newSId]);
                    db()->execute("UPDATE pending_registrations SET status='approved', reviewed_at=NOW() WHERE id=?", 'i', [$id]);
                    setFlash('success', "Registration approved! Student ID: <strong>$studentId</strong>, Username: <strong>" . htmlspecialchars($reg['username']) . "</strong>");
                } else {
                    db()->execute("DELETE FROM users WHERE id=?", 'i', [$userId]);
                    setFlash('danger', 'Failed to create student record: ' . htmlspecialchars(db()->lastError));
                }
            } else {
                setFlash('danger', 'Failed to create user account: ' . htmlspecialchars(db()->lastError));
            }
        } else {
            setFlash('danger', 'Registration not found or already processed.');
        }
        redirect('pending_registrations.php');
    }

    if ($action === 'reject' && $id) {
        $reg = db()->fetchOne("SELECT photo FROM pending_registrations WHERE id=? AND status='pending'", 'i', [$id]);
        if ($reg) {
            // Remove uploaded photo
            if (!empty($reg['photo'])) {
                @unlink('../assets/uploads/students/' . $reg['photo']);
            }
            db()->execute("UPDATE pending_registrations SET status='rejected', reviewed_at=NOW() WHERE id=?", 'i', [$id]);
            setFlash('success', 'Registration has been rejected and removed.');
        }
        redirect('pending_registrations.php');
    }
}

// ── Fetch pending ─────────────────────────────────────────────
$filter = $_GET['filter'] ?? 'pending';
$allowed = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$sql    = "SELECT pr.*, c.class_name, c.section FROM pending_registrations pr LEFT JOIN classes c ON pr.class_id=c.id";
$params = [];
$types  = '';
if ($filter !== 'all') {
    $sql   .= " WHERE pr.status=?";
    $params = [$filter];
    $types  = 's';
}
$sql .= " ORDER BY pr.submitted_at DESC";
$registrations = db()->fetchAll($sql, $types, $params);

$flash = getFlash();

// Counts for badges
$counts = [];
foreach (['pending','approved','rejected'] as $s) {
    $r = db()->fetchOne("SELECT COUNT(*) as cnt FROM pending_registrations WHERE status=?", 's', [$s]);
    $counts[$s] = $r['cnt'] ?? 0;
}
$counts['all'] = array_sum($counts);

require_once '../includes/admin_header.php';
?>

<style>
/* ── Page-specific styles ─── */
.pr-filter-bar {
    display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 22px;
}
.pr-filter-btn {
    padding: 7px 18px; border-radius: 50px; font-size: 0.78rem; font-weight: 600;
    border: 1.5px solid #e2e8f0; background: #fff; color: #64748b;
    cursor: pointer; text-decoration: none; transition: all 0.2s;
    display: inline-flex; align-items: center; gap: 7px;
}
.pr-filter-btn:hover  { border-color: #0b1f3a; color: #0b1f3a; background: #f8fafc; }
.pr-filter-btn.active { background: #0b1f3a; color: #fff; border-color: #0b1f3a; }
.pr-badge {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 20px; height: 20px; padding: 0 6px;
    border-radius: 50px; font-size: 0.65rem; font-weight: 700;
    background: rgba(255,255,255,0.2); color: inherit;
}
.pr-filter-btn:not(.active) .pr-badge { background: #e2e8f0; color: #475569; }
.badge-pending  { background: #fef3c7!important; color: #92400e!important; }
.badge-approved { background: #dcfce7!important; color: #15803d!important; }
.badge-rejected { background: #fee2e2!important; color: #b91c1c!important; }

/* Table */
.pr-table-wrap { overflow-x: auto; }
.pr-table { width: 100%; border-collapse: collapse; font-size: 0.84rem; }
.pr-table th {
    background: #f8fafc; border-bottom: 2px solid #e2e8f0;
    padding: 12px 14px; text-align: left; font-size: 0.68rem;
    font-weight: 700; letter-spacing: 0.8px; text-transform: uppercase; color: #64748b;
    white-space: nowrap;
}
.pr-table td { padding: 14px 14px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }
.pr-table tr:last-child td { border-bottom: none; }
.pr-table tr:hover td { background: #fafbfc; }

/* Student info cell */
.stu-info { display: flex; align-items: center; gap: 12px; }
.stu-photo {
    width: 44px; height: 44px; border-radius: 50%; object-fit: cover;
    border: 2px solid #e2e8f0; flex-shrink: 0; background: #f1f5f9;
}
.stu-name { font-weight: 700; color: #0b1f3a; font-size: 0.88rem; }
.stu-username { font-size: 0.72rem; color: #64748b; margin-top: 2px; }

/* Status pill */
.status-pill {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 4px 12px; border-radius: 50px; font-size: 0.7rem; font-weight: 700;
}
.pill-pending  { background: #fef3c7; color: #92400e; }
.pill-approved { background: #dcfce7; color: #15803d; }
.pill-rejected { background: #fee2e2; color: #b91c1c; }

/* Action buttons */
.btn-approve {
    padding: 7px 16px; background: #16a34a; color: #fff;
    border: none; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
}
.btn-approve:hover { background: #15803d; transform: translateY(-1px); }
.btn-reject {
    padding: 7px 16px; background: #fff; color: #dc2626;
    border: 1.5px solid #fecaca; border-radius: 8px; font-size: 0.78rem; font-weight: 600;
    cursor: pointer; transition: all 0.2s; display: inline-flex; align-items: center; gap: 6px;
}
.btn-reject:hover { background: #fef2f2; transform: translateY(-1px); }
.action-group { display: flex; gap: 8px; flex-wrap: wrap; }

/* Detail expand */
.detail-toggle {
    background: none; border: none; color: #0b1f3a; cursor: pointer;
    font-size: 0.75rem; font-weight: 600; display: inline-flex; align-items: center; gap: 4px;
    padding: 4px 0; text-decoration: underline dotted;
}
.detail-row { display: none; }
.detail-row.open { display: table-row; }
.detail-inner {
    background: #f8fafc; border-radius: 12px; padding: 18px 20px;
    display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 14px;
}
.detail-item label { display: block; font-size: 0.62rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #94a3b8; margin-bottom: 3px; }
.detail-item span  { font-size: 0.82rem; color: #0b1f3a; font-weight: 600; }

/* Empty state */
.empty-state { text-align: center; padding: 60px 20px; }
.empty-state i { font-size: 3rem; color: #d1d5db; margin-bottom: 16px; }
.empty-state h3 { font-size: 1.1rem; font-weight: 700; color: #374151; margin-bottom: 8px; }
.empty-state p  { font-size: 0.84rem; color: #9ca3af; }

@media (max-width: 640px) {
    .pr-table th:nth-child(3),
    .pr-table td:nth-child(3),
    .pr-table th:nth-child(4),
    .pr-table td:nth-child(4) { display: none; }
}
</style>

<div class="content-header">
    <div>
        <h1 class="content-title"><i class="fas fa-user-clock"></i> Pending Registrations</h1>
        <p class="content-sub">Review and approve or reject student sign-up requests</p>
    </div>
    <?php if ($counts['pending'] > 0): ?>
    <span style="background:#fef3c7;color:#92400e;padding:6px 16px;border-radius:50px;font-size:0.78rem;font-weight:700;display:inline-flex;align-items:center;gap:6px;">
        <i class="fas fa-clock"></i> <?= $counts['pending'] ?> awaiting approval
    </span>
    <?php endif; ?>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= $flash['type'] === 'success' ? 'success' : 'danger' ?> alert-dismissible mb-3" role="alert">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= $flash['message'] ?>
    <button type="button" onclick="this.parentElement.remove()" style="background:none;border:none;float:right;cursor:pointer;font-size:1.1rem;color:inherit;opacity:0.7;">&times;</button>
</div>
<?php endif; ?>

<!-- Filter Bar -->
<div class="pr-filter-bar">
    <?php foreach (['pending'=>'Clock','approved'=>'Check-circle','rejected'=>'Times-circle','all'=>'List'] as $f => $ic): ?>
    <a href="?filter=<?= $f ?>" class="pr-filter-btn <?= $filter === $f ? 'active' : '' ?>">
        <i class="fas fa-<?= strtolower($ic) ?>"></i>
        <?= ucfirst($f) ?>
        <span class="pr-badge <?= !($filter===$f) ? 'badge-'.$f : '' ?>"><?= $counts[$f] ?></span>
    </a>
    <?php endforeach; ?>
</div>

<!-- Table Card -->
<div class="card">
    <?php if (empty($registrations)): ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <h3>No registrations found</h3>
        <p>There are no <?= $filter !== 'all' ? $filter : '' ?> registration requests at the moment.</p>
    </div>
    <?php else: ?>
    <div class="pr-table-wrap">
        <table class="pr-table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Class</th>
                    <th>Date of Birth</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($registrations as $i => $reg): ?>
                <tr>
                    <td>
                        <div class="stu-info">
                            <?php if (!empty($reg['photo'])): ?>
                            <img src="../assets/uploads/students/<?= htmlspecialchars($reg['photo']) ?>" class="stu-photo" alt="Photo" onerror="this.src='../assets/images/gmscrop.png'">
                            <?php else: ?>
                            <div class="stu-photo" style="display:flex;align-items:center;justify-content:center;"><i class="fas fa-user" style="color:#9ca3af;"></i></div>
                            <?php endif; ?>
                            <div>
                                <div class="stu-name"><?= htmlspecialchars($reg['first_name'] . ' ' . $reg['last_name']) ?></div>
                                <div class="stu-username"><i class="fas fa-at" style="font-size:0.65rem;"></i> <?= htmlspecialchars($reg['username']) ?></div>
                                <button class="detail-toggle" onclick="toggleDetail(<?= $i ?>)">
                                    <i class="fas fa-chevron-down" id="icon-<?= $i ?>"></i> Details
                                </button>
                            </div>
                        </div>
                    </td>
                    <td><?= htmlspecialchars($reg['class_name'] . ' – ' . $reg['section']) ?></td>
                    <td><?= formatDate((string)$reg['dob']) ?></td>
                    <td style="font-size:0.78rem;color:#64748b;"><?= formatDateTime($reg['submitted_at']) ?></td>
                    <td>
                        <span class="status-pill pill-<?= $reg['status'] ?>">
                            <i class="fas fa-<?= $reg['status']==='pending' ? 'clock' : ($reg['status']==='approved' ? 'check' : 'times') ?>"></i>
                            <?= ucfirst($reg['status']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($reg['status'] === 'pending'): ?>
                        <div class="action-group">
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Approve registration for <?= htmlspecialchars(addslashes($reg['first_name'] . ' ' . $reg['last_name'])) ?>?')">
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="id" value="<?= $reg['id'] ?>">
                                <button type="submit" class="btn-approve"><i class="fas fa-check"></i> Approve</button>
                            </form>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Reject and delete this registration?')">
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="id" value="<?= $reg['id'] ?>">
                                <button type="submit" class="btn-reject"><i class="fas fa-times"></i> Reject</button>
                            </form>
                        </div>
                        <?php else: ?>
                        <span style="font-size:0.75rem;color:#94a3b8;"><i class="fas fa-check-double"></i> Reviewed<br><span style="font-size:0.68rem;"><?= $reg['reviewed_at'] ? formatDate(substr($reg['reviewed_at'],0,10)) : '' ?></span></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <!-- Detail Expand Row -->
                <tr class="detail-row" id="detail-<?= $i ?>">
                    <td colspan="6" style="padding: 0 14px 16px;">
                        <div class="detail-inner">
                            <div class="detail-item"><label>Phone</label><span><?= htmlspecialchars($reg['phone']) ?></span></div>
                            <div class="detail-item"><label>Email</label><span><?= htmlspecialchars($reg['email'] ?: '–') ?></span></div>
                            <div class="detail-item"><label>Gender</label><span><?= htmlspecialchars($reg['gender']) ?></span></div>
                            <div class="detail-item"><label>Address</label><span><?= htmlspecialchars($reg['address']) ?></span></div>
                            <div class="detail-item"><label>Guardian Name</label><span><?= htmlspecialchars($reg['guardian_name']) ?></span></div>
                            <div class="detail-item"><label>Guardian Phone</label><span><?= htmlspecialchars($reg['guardian_phone']) ?></span></div>
                            <div class="detail-item"><label>Guardian Relation</label><span><?= htmlspecialchars($reg['guardian_relation'] ?: '–') ?></span></div>
                            <div class="detail-item"><label>Admission Date</label><span><?= $reg['admission_date'] ? formatDate((string)$reg['admission_date']) : 'Not specified' ?></span></div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleDetail(i) {
    const row  = document.getElementById('detail-' + i);
    const icon = document.getElementById('icon-' + i);
    const open = row.classList.toggle('open');
    icon.className = 'fas fa-chevron-' + (open ? 'up' : 'down');
}
</script>

<?php require_once '../includes/admin_footer.php'; ?>