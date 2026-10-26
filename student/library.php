<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'Library';
$activeMenu = 'library';
$user       = currentUser();
$studentId  = $user['ref_id'];

// Auto-create book_requests table (lightweight — just a request queue)
db()->query("
    CREATE TABLE IF NOT EXISTS book_requests (
        id           INT AUTO_INCREMENT PRIMARY KEY,
        student_id   INT NOT NULL,
        book_id      INT NOT NULL,
        request_date DATE NOT NULL,
        status       ENUM('Pending','Issued','Cancelled') DEFAULT 'Pending',
        note         VARCHAR(255) DEFAULT NULL,
        created_at   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
        FOREIGN KEY (book_id)    REFERENCES books(id)    ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// POST handlers
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'request_book') {
        $bookId = (int)($_POST['book_id'] ?? 0);
        $book   = db()->fetchOne("SELECT * FROM books WHERE id=? AND available_copies > 0", 'i', [$bookId]);
        if (!$book) {
            setFlash('danger', 'This book is not available.');
            redirect('library.php');
        }
        // No duplicate pending request for same book
        $dup = db()->fetchOne(
            "SELECT id FROM book_requests WHERE student_id=? AND book_id=? AND status='Pending'",
            'ii', [$studentId, $bookId]
        );
        if ($dup) {
            setFlash('warning', 'You already have a pending request for this book.');
            redirect('library.php');
        }
        db()->execute(
            "INSERT INTO book_requests (student_id, book_id, request_date) VALUES (?,?,CURDATE())",
            'ii', [$studentId, $bookId]
        );
        setFlash('success', 'Request submitted! Go to the library and show this request to the librarian to collect your book.');
        redirect('library.php');
    }

    if ($action === 'cancel_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        db()->execute(
            "UPDATE book_requests SET status='Cancelled' WHERE id=? AND student_id=? AND status='Pending'",
            'ii', [$reqId, $studentId]
        );
        setFlash('success', 'Request cancelled.');
        redirect('library.php');
    }
}

// Search / filter
$search   = sanitize($_GET['q']   ?? '');
$category = '';

$whereParts = ["1=1"];
$params = [$studentId];
$types  = 'i';

if ($search) {
    $whereParts[] = "(b.title LIKE ? OR b.author LIKE ?)";
    $like = "%$search%";
    $params[] = $like; $params[] = $like;
    $types .= 'ss';
}
$where = implode(' AND ', $whereParts);

$books = db()->fetchAll(
    "SELECT b.*,
        (SELECT COUNT(*) FROM book_requests br
         WHERE br.book_id=b.id AND br.student_id=? AND br.status='Pending') as my_pending
     FROM books b
     WHERE $where
     ORDER BY b.title",
    $types, $params
);


// My currently issued books (from issued_books table — single source of truth)
$myIssued = db()->fetchAll("
    SELECT ib.*, b.title, b.author, b.shelf_location
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    WHERE ib.student_id = ? AND ib.status = 'Issued'
    ORDER BY ib.issue_date DESC
", 'i', [$studentId]);

// My pending requests
$myPending = db()->fetchAll("
    SELECT br.*, b.title, b.author, b.available_copies
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    WHERE br.student_id = ? AND br.status = 'Pending'
    ORDER BY br.created_at DESC
", 'i', [$studentId]);

require_once '../includes/student_header.php';
?>

<style>
.lib-bar{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;}
.lib-bar input,.lib-bar select{flex:1;min-width:160px;padding:10px 14px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.875rem;font-family:inherit;outline:none;transition:border-color .2s;}
.lib-bar input:focus,.lib-bar select:focus{border-color:var(--primary);}
.lib-bar button{padding:10px 20px;border-radius:var(--radius-sm);background:var(--primary);color:#fff;border:none;cursor:pointer;font-weight:700;font-size:.85rem;}
.book-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(250px,1fr));gap:16px;}
.book-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);padding:18px 20px;display:flex;flex-direction:column;gap:8px;transition:box-shadow .2s,border-color .2s;}
.book-card:hover{box-shadow:0 4px 18px rgba(0,0,0,.07);border-color:var(--primary-light);}
.book-title{font-size:.95rem;font-weight:800;color:var(--text-primary);line-height:1.3;}
.book-author{font-size:.78rem;color:var(--text-secondary);}
.book-tags{display:flex;gap:5px;flex-wrap:wrap;}
.btag{font-size:.65rem;font-weight:700;padding:2px 8px;border-radius:50px;}
.btag-cat{background:rgba(59,130,246,.1);color:#3b82f6;}
.btag-shelf{background:rgba(139,92,246,.1);color:#8b5cf6;}
.btag-ok{background:rgba(16,185,129,.1);color:#10b981;}
.btag-no{background:rgba(239,68,68,.1);color:#ef4444;}
.btag-pend{background:rgba(245,158,11,.1);color:#f59e0b;}
.issued-row{display:flex;align-items:center;gap:14px;padding:14px 0;border-bottom:1px solid var(--border);}
.issued-row:last-child{border-bottom:none;padding-bottom:0;}
.issued-icon{width:38px;height:38px;border-radius:9px;background:rgba(37,99,232,.1);color:#2563eb;display:flex;align-items:center;justify-content:center;font-size:.9rem;flex-shrink:0;}
.issued-info{flex:1;min-width:0;}
.issued-info h4{font-size:.88rem;font-weight:700;margin-bottom:2px;}
.issued-info p{font-size:.75rem;color:var(--text-secondary);}

</style>

<!-- Currently issued to me -->
<?php if (!empty($myIssued)): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3><i class="fas fa-book-open"></i> Books I Currently Have</h3>
        <span class="badge badge-primary"><?= count($myIssued) ?></span>
    </div>
    <div class="card-body">
        <?php foreach ($myIssued as $ib):
            $overdue = $ib['due_date'] < date('Y-m-d');
        ?>
        <div class="issued-row">
            <div class="issued-icon"><i class="fas fa-book-open"></i></div>
            <div class="issued-info">
                <h4><?= htmlspecialchars($ib['title']) ?></h4>
                <p>
                    <?php if ($ib['author']): ?>By <?= htmlspecialchars($ib['author']) ?> &bull; <?php endif; ?>
                    Issued: <?= formatDate($ib['issue_date']) ?>
                </p>
            </div>
            <div style="text-align:right;flex-shrink:0;">
                <div style="font-size:.82rem;font-weight:700;color:<?= $overdue ? 'var(--danger)' : 'var(--text-primary)' ?>;">
                    Due: <?= formatDate($ib['due_date']) ?>
                </div>
                <?php if ($overdue): ?>
                <span class="badge badge-danger" style="font-size:.65rem;margin-top:3px;">
                    <i class="fas fa-exclamation-triangle"></i> Overdue — return ASAP
                </span>
                <?php else: ?>
                <?php
                    $daysLeft = (int)ceil((strtotime($ib['due_date']) - strtotime(date('Y-m-d'))) / 86400);
                ?>
                <span style="font-size:.73rem;color:var(--text-secondary);"><?= $daysLeft ?> day<?= $daysLeft !== 1 ? 's' : '' ?> left</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Pending requests (waiting to go pick up) -->
<?php if (!empty($myPending)): ?>
<div class="card" style="margin-bottom:20px;">
    <div class="card-header">
        <h3><i class="fas fa-clock"></i> Pending Requests</h3>
        <span class="badge badge-warning"><?= count($myPending) ?></span>
    </div>
    <div class="card-body">
        <?php foreach ($myPending as $r): ?>
        <div class="issued-row">
            <div class="issued-icon" style="background:rgba(245,158,11,.1);color:#d97706;"><i class="fas fa-clock"></i></div>
            <div class="issued-info">
                <h4><?= htmlspecialchars($r['title']) ?></h4>
                <p>Requested <?= formatDate($r['request_date']) ?> &bull; <?= $r['available_copies'] ?> cop<?= $r['available_copies'] !== 1 ? 'ies' : 'y' ?> available</p>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="cancel_request">
                <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                <button type="submit" class="btn btn-outline btn-sm"
                    onclick="return confirm('Cancel this request?')" style="font-size:.75rem;">
                    <i class="fas fa-times"></i> Cancel
                </button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Book catalog -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-book"></i> Book Catalog</h3>
        <span class="badge badge-info"><?= count($books) ?> book<?= count($books) !== 1 ? 's' : '' ?></span>
    </div>
    <div class="card-body">
        <form method="GET" class="lib-bar">
            <input type="text" name="q" placeholder="Search by title or author…" value="<?= htmlspecialchars($search) ?>">

            <button type="submit"><i class="fas fa-search"></i> Search</button>
            <?php if ($search): ?>
            <a href="library.php" class="btn btn-outline btn-sm" style="padding:10px 16px;">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($books)): ?>
        <div class="empty-state"><i class="fas fa-book-open"></i><h3>No books found</h3></div>
        <?php else: ?>
        <div class="book-grid">
        <?php foreach ($books as $b):
            $avail      = (int)$b['available_copies'] > 0;
            $hasPending = (int)($b['my_pending'] ?? 0) > 0;
            // Also check if student already has this book issued
            $alreadyHas = false;
            foreach ($myIssued as $ib) {
                if ((int)$ib['book_id'] === (int)$b['id']) { $alreadyHas = true; break; }
            }
        ?>
        <div class="book-card">
            <div>
                <div class="book-title"><?= htmlspecialchars($b['title']) ?></div>
                <?php if ($b['author']): ?>
                <div class="book-author"><i class="fas fa-user-pen" style="font-size:.7rem;margin-right:3px;"></i><?= htmlspecialchars($b['author']) ?></div>
                <?php endif; ?>
            </div>
            <div class="book-tags">
                <?php if ($b['shelf_location']): ?>
                <span class="btag btag-shelf"><i class="fas fa-map-pin"></i> <?= htmlspecialchars($b['shelf_location']) ?></span>
                <?php endif; ?>
                <?php if ($avail): ?>
                <span class="btag btag-ok"><?= $b['available_copies'] ?> available</span>
                <?php else: ?>
                <span class="btag btag-no">Not available</span>
                <?php endif; ?>
            </div>
            <?php if ($b['publisher'] || $b['publish_year']): ?>
            <div style="font-size:.72rem;color:var(--text-secondary);">
                <?= htmlspecialchars(trim(($b['publisher'] ?? '') . ($b['publish_year'] ? ' · ' . $b['publish_year'] : ''))) ?>
            </div>
            <?php endif; ?>
            <div style="margin-top:auto;padding-top:4px;">
                <?php if ($alreadyHas): ?>
                <span class="btag btag-ok" style="font-size:.75rem;padding:5px 10px;"><i class="fas fa-check"></i> Currently with you</span>
                <?php elseif ($hasPending): ?>
                <span class="btag btag-pend" style="font-size:.75rem;padding:5px 10px;"><i class="fas fa-clock"></i> Request pending — go collect!</span>
                <?php elseif (!$avail): ?>
                <span style="font-size:.75rem;color:var(--text-secondary);">All copies currently issued</span>
                <?php else: ?>
                <form method="POST">
                    <input type="hidden" name="action" value="request_book">
                    <input type="hidden" name="book_id" value="<?= $b['id'] ?>">
                    <button type="submit" class="btn btn-primary btn-sm"
                        onclick="return confirm('Request this book?\n\nGo to the library afterwards — the librarian will issue it to you on the spot.')">
                        <i class="fas fa-hand-holding-heart"></i> Request
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/student_footer.php'; ?>