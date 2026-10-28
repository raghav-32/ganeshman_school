<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle  = 'Library Management';
$activeMenu = 'library';

// Auto-create book_requests table
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Add book ─────────────────────────────────────────────────────────────
    if ($action === 'add_book') {
        $title     = sanitize($_POST['title'] ?? '');
        $author    = sanitize($_POST['author'] ?? '');
        $publisher = sanitize($_POST['publisher'] ?? '');
        $year      = (int)($_POST['publish_year'] ?? 0);
        $copies    = (int)($_POST['total_copies'] ?? 1);
        $shelf     = sanitize($_POST['shelf_location'] ?? '');
        db()->execute(
            "INSERT INTO books (title,author,publisher,publish_year,total_copies,available_copies,shelf_location) VALUES (?,?,?,?,?,?,?)",
            'sssiiis', [$title,$author,$publisher,$year,$copies,$copies,$shelf]
        );
        setFlash('success', "Book '$title' added.");
        redirect('library.php');
    }

    if ($action === 'edit_book') {
        $id        = (int)($_POST['id'] ?? 0);
        $title     = sanitize($_POST['title'] ?? '');
        $author    = sanitize($_POST['author'] ?? '');
        $publisher = sanitize($_POST['publisher'] ?? '');
        $year      = (int)($_POST['publish_year'] ?? 0);
        $copies    = (int)($_POST['total_copies'] ?? 1);
        $shelf     = sanitize($_POST['shelf_location'] ?? '');
        $inUse     = (int)(db()->fetchOne(
            "SELECT COUNT(*) as c FROM issued_books WHERE book_id=? AND status='Issued'", 'i', [$id]
        )['c'] ?? 0);
        $available = max(0, $copies - $inUse);
        db()->execute(
            "UPDATE books SET title=?,author=?,publisher=?,publish_year=?,total_copies=?,available_copies=?,shelf_location=? WHERE id=?",
            'ssssiisi', [$title,$author,$publisher,$year,$copies,$available,$shelf,$id]
        );
        setFlash('success', "Book '$title' updated.");
        redirect('library.php');
    }

    if ($action === 'delete_book') {
        $id     = (int)($_POST['id'] ?? 0);
        $inUse  = (int)(db()->fetchOne("SELECT COUNT(*) as c FROM issued_books WHERE book_id=? AND status='Issued'", 'i', [$id])['c'] ?? 0);
        if ($inUse > 0) {
            setFlash('danger', 'Cannot delete — book is currently issued to a student.');
        } else {
            db()->execute("DELETE FROM book_requests WHERE book_id=? AND status='Pending'", 'i', [$id]);
            db()->execute("DELETE FROM books WHERE id=?", 'i', [$id]);
            setFlash('success', 'Book deleted.');
        }
        redirect('library.php');
    }

    // ── Issue book to student (from a pending request OR direct) ─────────────
    // This is the SINGLE issue action — used both for request-based and direct
    if ($action === 'issue_book') {
        $bookId    = (int)($_POST['book_id'] ?? 0);
        $studentId = (int)($_POST['student_id'] ?? 0);
        $dueDate   = sanitize($_POST['due_date'] ?? '');
        $reqId     = (int)($_POST['request_id'] ?? 0); // 0 = direct issue

        $book = db()->fetchOne("SELECT * FROM books WHERE id=? AND available_copies > 0", 'i', [$bookId]);
        if (!$book) {
            setFlash('danger', 'Book not available.');
            redirect('library.php');
        }

        // Insert into issued_books
        $adminId = (int)(currentUser()['id'] ?? 1);
        db()->execute(
            "INSERT INTO issued_books (book_id, student_id, issue_date, due_date, issued_by) VALUES (?,?,CURDATE(),?,?)",
            'iisi', [$bookId, $studentId, $dueDate, $adminId]
        );
        // Decrement available
        db()->execute("UPDATE books SET available_copies=available_copies-1 WHERE id=?", 'i', [$bookId]);

        // If issued from a request, mark that request as Issued
        if ($reqId > 0) {
            db()->execute("UPDATE book_requests SET status='Issued' WHERE id=?", 'i', [$reqId]);
        }

        setFlash('success', 'Book issued successfully.');
        redirect('library.php');
    }

    // ── Return issued book ────────────────────────────────────────────────────
    if ($action === 'return_book') {
        $issueId = (int)($_POST['issue_id'] ?? 0);
        $issue   = db()->fetchOne("SELECT * FROM issued_books WHERE id=?", 'i', [$issueId]);
        if ($issue) {
            $today = date('Y-m-d');
            $fine  = 0;
            if ($today > $issue['due_date']) {
                $days = (int)floor((strtotime($today) - strtotime($issue['due_date'])) / 86400);
                $fine = max(0, $days * 5);
            }
            db()->execute(
                "UPDATE issued_books SET return_date=CURDATE(), status='Returned', fine_amount=? WHERE id=?",
                'di', [$fine, $issueId]
            );
            db()->execute("UPDATE books SET available_copies=available_copies+1 WHERE id=?", 'i', [$issue['book_id']]);
            $msg = 'Book returned.';
            if ($fine > 0) $msg .= " Fine: Rs $fine.";
            setFlash('success', $msg);
        }
        redirect('library.php');
    }

    // ── Dismiss / cancel a pending request (e.g. student changed mind) ───────
    if ($action === 'dismiss_request') {
        $reqId = (int)($_POST['request_id'] ?? 0);
        db()->execute("UPDATE book_requests SET status='Cancelled' WHERE id=? AND status='Pending'", 'i', [$reqId]);
        setFlash('success', 'Request dismissed.');
        redirect('library.php');
    }
}

// ── Data ─────────────────────────────────────────────────────────────────────
$books    = db()->fetchAll("SELECT * FROM books ORDER BY title");
$students = db()->fetchAll("SELECT s.id, s.student_id, s.first_name, s.last_name, c.class_name as faculty, c.section as semester FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.is_active=1 ORDER BY c.class_name, c.section, s.first_name");
$faculties = db()->fetchAll("SELECT DISTINCT class_name FROM classes ORDER BY class_name");

// All currently issued books (single source of truth)
$issued = db()->fetchAll("
    SELECT ib.*, b.title, b.author,
           s.first_name, s.last_name, s.student_id as sid
    FROM issued_books ib
    JOIN books b ON ib.book_id = b.id
    JOIN students s ON ib.student_id = s.id
    WHERE ib.status = 'Issued'
    ORDER BY ib.issue_date DESC
");

// Pending student requests — what students are coming to collect
$pendingRequests = db()->fetchAll("
    SELECT br.*, b.title, b.author, b.available_copies, b.shelf_location,
           CONCAT(s.first_name,' ',s.last_name) as student_name,
           s.student_id as sid, s.id as s_id,
           b.id as b_id
    FROM book_requests br
    JOIN books b ON br.book_id = b.id
    JOIN students s ON br.student_id = s.id
    WHERE br.status = 'Pending'
    ORDER BY br.created_at ASC
");

$totalBooks   = db()->fetchOne("SELECT COUNT(*) as c FROM books")['c'] ?? 0;
$issuedCount  = count($issued);
$overdueCount = (int)(db()->fetchOne(
    "SELECT COUNT(*) as c FROM issued_books WHERE status='Issued' AND due_date < CURDATE()"
)['c'] ?? 0);
$pendingCount = count($pendingRequests);

// ── Student filter widget: Faculty → Semester → Student dropdowns ─────────────
function studentFilterWidget(string $name, string $studentsJson, string $facultiesJson): string {
    $uid = 'sfw_' . $name;
    return <<<HTML
<div class="form-group">
    <label>Faculty</label>
    <select id="{$uid}_faculty" class="form-control" onchange="sfw_{$uid}_onFaculty()">
        <option value="">All Faculties</option>
    </select>
</div>
<div class="form-group">
    <label>Semester</label>
    <select id="{$uid}_sem" class="form-control" onchange="sfw_{$uid}_onSem()">
        <option value="">All Semesters</option>
    </select>
</div>
<div class="form-group">
    <label>Student *</label>
    <select id="{$uid}_student" name="student_id" class="form-control" required>
        <option value="">— Select Student —</option>
    </select>
</div>
<script>
(function(){
    var DATA = {$studentsJson};
    var FACS = {$facultiesJson};
    var uid  = '{$uid}';
    var facEl = document.getElementById(uid+'_faculty');
    var semEl = document.getElementById(uid+'_sem');
    var stuEl = document.getElementById(uid+'_student');

    // Populate faculty dropdown once
    FACS.forEach(function(f){
        var o = document.createElement('option');
        o.value = f; o.textContent = f;
        facEl.appendChild(o);
    });

    function rebuildSems(fac){
        var sems = [];
        DATA.forEach(function(s){
            if((!fac || s.faculty === fac) && s.semester && sems.indexOf(s.semester) === -1)
                sems.push(s.semester);
        });
        sems.sort();
        semEl.innerHTML = '<option value="">All Semesters</option>';
        sems.forEach(function(sv){
            var o = document.createElement('option');
            o.value = sv; o.textContent = sv;
            semEl.appendChild(o);
        });
    }

    function rebuildStudents(fac, sem){
        var list = DATA.filter(function(s){
            if(fac && s.faculty !== fac) return false;
            if(sem && s.semester !== sem) return false;
            return true;
        });
        stuEl.innerHTML = '<option value="">— Select Student —</option>';
        list.forEach(function(s){
            var o = document.createElement('option');
            o.value = s.id;
            o.textContent = s.student_id + ' — ' + s.first_name + ' ' + s.last_name;
            stuEl.appendChild(o);
        });
    }

    // Faculty changed: rebuild sems, reset sem selection, then rebuild students
    window['sfw_'+uid+'_onFaculty'] = function(){
        var fac = facEl.value;
        rebuildSems(fac);
        semEl.value = '';          // reset semester
        rebuildStudents(fac, ''); // show all students in this faculty
    };

    // Semester changed: just filter students (faculty already set)
    window['sfw_'+uid+'_onSem'] = function(){
        rebuildStudents(facEl.value, semEl.value);
    };

    // Init
    rebuildSems('');
    rebuildStudents('', '');
})();
</script>
HTML;
}


include '../includes/admin_header.php';
?>

<style>
.req-card{background:#fff;border:1.5px solid var(--border);border-radius:var(--radius);margin-bottom:12px;overflow:visible;}
.req-card:has(.bsdp-popup.open){position:relative;z-index:50;}
.card:has(.bsdp-popup.open){overflow:visible;}
.req-card-head{border-radius:var(--radius) var(--radius) 0 0;}
.req-card-head{display:flex;align-items:center;gap:12px;padding:14px 18px;background:#fffbeb;border-bottom:1.5px solid #fef3c7;}
.req-card-head .req-icon{width:36px;height:36px;border-radius:8px;background:rgba(245,158,11,.15);color:#d97706;display:flex;align-items:center;justify-content:center;font-size:.88rem;flex-shrink:0;}
.req-card-head h4{font-size:.9rem;font-weight:800;margin:0;}
.req-card-head p{font-size:.75rem;color:var(--text-secondary);margin:2px 0 0;}
.req-card-body{padding:14px 18px;}
.req-inline-form{display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap;}
.req-inline-form .fg{display:flex;flex-direction:column;gap:4px;flex:1;min-width:130px;}
.req-inline-form .fg label{font-size:.7rem;font-weight:700;color:var(--text-secondary);}
.req-inline-form .fg input,.req-inline-form .fg select{padding:8px 10px;border:1.5px solid var(--border);border-radius:var(--radius-sm);font-size:.83rem;font-family:inherit;outline:none;}
.req-inline-form .fg input:focus,.req-inline-form .fg select:focus{border-color:var(--primary);}
</style>

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card blue"><div class="stat-icon blue"><i class="fas fa-book"></i></div><div class="stat-info"><h3><?= $totalBooks ?></h3><p>Total Books</p></div></div>
    <div class="stat-card amber"><div class="stat-icon amber"><i class="fas fa-book-open"></i></div><div class="stat-info"><h3><?= $issuedCount ?></h3><p>Currently Issued</p></div></div>
    <div class="stat-card red"><div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div><div class="stat-info"><h3><?= $overdueCount ?></h3><p>Overdue</p></div></div>
    <div class="stat-card <?= $pendingCount > 0 ? 'amber' : 'green' ?>">
        <div class="stat-icon <?= $pendingCount > 0 ? 'amber' : 'green' ?>"><i class="fas fa-hand-holding-heart"></i></div>
        <div class="stat-info"><h3><?= $pendingCount ?></h3><p>Student Requests</p></div>
    </div>
</div>

<!-- ═══ PENDING STUDENT REQUESTS ═══════════════════════════════════════════ -->
<?php if (!empty($pendingRequests)): ?>
<div class="card" style="margin-bottom:22px;">
    <div class="card-header">
        <h3><i class="fas fa-hand-holding-heart"></i> Students Waiting to Collect</h3>
        <span class="badge badge-danger"><?= $pendingCount ?></span>
    </div>
    <div class="card-body">
        <?php foreach ($pendingRequests as $r): ?>
        <div class="req-card">
            <div class="req-card-head">
                <div class="req-icon"><i class="fas fa-user"></i></div>
                <div style="flex:1;min-width:0;">
                    <h4><?= htmlspecialchars($r['student_name']) ?> <span style="font-weight:400;font-size:.78rem;color:var(--text-secondary);">(<?= htmlspecialchars($r['sid']) ?>)</span></h4>
                    <p>Wants: <strong><?= htmlspecialchars($r['title']) ?></strong>
                    <?php if ($r['author']): ?> by <?= htmlspecialchars($r['author']) ?><?php endif; ?>
                    <?php if ($r['shelf_location']): ?> · Shelf: <strong><?= htmlspecialchars($r['shelf_location']) ?></strong><?php endif; ?>
                    · Requested <?= formatDate($r['request_date']) ?>
                    </p>
                </div>
                <?php if ((int)$r['available_copies'] > 0): ?>
                <span class="badge badge-success" style="flex-shrink:0;"><?= $r['available_copies'] ?> available</span>
                <?php else: ?>
                <span class="badge badge-danger" style="flex-shrink:0;">None left</span>
                <?php endif; ?>
            </div>
            <div class="req-card-body">
                <?php if ((int)$r['available_copies'] > 0): ?>
                <div class="req-inline-form">
                    <form method="POST" style="display:contents;">
                        <input type="hidden" name="action"     value="issue_book">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <input type="hidden" name="book_id"    value="<?= $r['b_id'] ?>">
                        <input type="hidden" name="student_id" value="<?= $r['s_id'] ?>">
                        <div class="fg">
                            <label>Due Date</label>
                            <?= bsDatePicker('due_date', date('Y-m-d', strtotime('+14 days')), true) ?>
                        </div>
                        <button type="submit" class="btn btn-success"
                            onclick="return confirm('Issue \'<?= htmlspecialchars(addslashes($r['title'])) ?>\' to <?= htmlspecialchars(addslashes($r['student_name'])) ?>?')">
                            <i class="fas fa-check"></i> Issue Book
                        </button>
                    </form>
                    <form method="POST" style="margin:0;">
                        <input type="hidden" name="action"     value="dismiss_request">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm"
                            onclick="return confirm('Dismiss this request?')">
                            <i class="fas fa-times"></i> Dismiss
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">
                    <span style="font-size:.82rem;color:var(--danger);"><i class="fas fa-exclamation-triangle"></i> No copies available to issue right now.</span>
                    <form method="POST">
                        <input type="hidden" name="action"     value="dismiss_request">
                        <input type="hidden" name="request_id" value="<?= $r['id'] ?>">
                        <button type="submit" class="btn btn-outline btn-sm"
                            onclick="return confirm('Dismiss this request?')">
                            <i class="fas fa-times"></i> Dismiss
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- ═══ ALL ISSUED BOOKS ═══════════════════════════════════════════════════ -->
<div class="grid-2" style="margin-bottom:22px;">

    <!-- Currently Issued — full list -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list"></i> Currently Issued Books</h3>
            <span class="badge badge-info"><?= $issuedCount ?></span>
        </div>
        <div class="card-body" style="padding:0;max-height:420px;overflow-y:auto;">
            <table class="table">
                <thead><tr><th>Student</th><th>Book</th><th>Due</th><th>Return</th></tr></thead>
                <tbody>
                    <?php foreach ($issued as $ib):
                        $overdue = $ib['due_date'] < date('Y-m-d');
                    ?>
                    <tr>
                        <td style="font-size:.82rem;">
                            <?= htmlspecialchars($ib['first_name'].' '.$ib['last_name']) ?><br>
                            <small style="color:var(--text-secondary);"><?= htmlspecialchars($ib['sid']) ?></small>
                        </td>
                        <td style="font-size:.82rem;">
                            <?= htmlspecialchars(mb_substr($ib['title'],0,28)) ?><?= mb_strlen($ib['title'])>28?'…':'' ?>
                        </td>
                        <td style="font-size:.79rem;color:<?= $overdue?'var(--danger)':'inherit' ?>;font-weight:<?= $overdue?'700':'400' ?>;">
                            <?= formatDate($ib['due_date']) ?>
                            <?php if($overdue): ?><br><span class="badge badge-danger" style="font-size:.6rem;">Overdue</span><?php endif; ?>
                        </td>
                        <td>
                            <form method="POST">
                                <input type="hidden" name="action"   value="return_book">
                                <input type="hidden" name="issue_id" value="<?= $ib['id'] ?>">
                                <button type="submit" class="btn btn-success btn-sm"
                                    onclick="return confirm('Mark as returned?<?= $overdue ? ' Fine will be calculated.' : '' ?>')">
                                    <i class="fas fa-undo"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($issued)): ?>
                    <tr><td colspan="4" style="text-align:center;padding:24px;color:var(--text-secondary);">No books currently issued.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Direct issue form (for walk-ins, no prior request) -->
    <div class="card">
        <div class="card-header"><h3><i class="fas fa-hand-holding-heart"></i> Issue Book</h3></div>
        <div class="card-body">

            <form method="POST">
                <input type="hidden" name="action"     value="issue_book">
                <input type="hidden" name="request_id" value="0">
                <?php
                $studentsJson = json_encode(array_values($students));
                $facultiesJson = json_encode(array_column($faculties, 'class_name'));
                ?>
                <?= studentFilterWidget('lib_student', $studentsJson, $facultiesJson) ?>
                <div class="form-group">
                    <label>Book *</label>
                    <select name="book_id" class="form-control" required>
                        <option value="">Select Book</option>
                        <?php foreach ($books as $b): ?>
                        <option value="<?= $b['id'] ?>" <?= $b['available_copies']<1?'disabled':'' ?>>
                            <?= htmlspecialchars($b['title']) ?> (<?= $b['available_copies'] ?> available)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Due Date *</label>
                    <?= bsDatePicker('due_date', date('Y-m-d', strtotime('+14 days')), true) ?>
                </div>
                <button type="submit" class="btn btn-success btn-full">
                    <i class="fas fa-check"></i> Issue Book
                </button>
            </form>
        </div>
    </div>
</div>

<!-- ═══ BOOK CATALOG ════════════════════════════════════════════════════════ -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-header">
        <h3><i class="fas fa-book"></i> Book Catalog</h3>
        <button class="btn btn-primary btn-sm" onclick="Modal.open('addBookModal')">
            <i class="fas fa-plus"></i> Add Book
        </button>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table" id="dataTable">
                <thead>
                    <tr><th>#</th><th>Title</th><th>Author</th><th>Copies</th><th>Available</th><th>Shelf</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($books as $i => $b): ?>
                    <tr>
                        <td><?= $i+1 ?></td>
                        <td><strong><?= htmlspecialchars($b['title']) ?></strong></td>
                        <td><?= htmlspecialchars($b['author'] ?? '–') ?></td>
                        <td><?= htmlspecialchars($b['category'] ?? '–') ?></td>
                        <td><?= $b['total_copies'] ?></td>
                        <td><span class="badge badge-<?= $b['available_copies'] > 0 ? 'success' : 'danger' ?>"><?= $b['available_copies'] ?></span></td>
                        <td><?= htmlspecialchars($b['shelf_location'] ?? '–') ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-info btn-sm" onclick="editBook(<?= htmlspecialchars(json_encode($b)) ?>)"><i class="fas fa-edit"></i></button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="action" value="delete_book">
                                    <input type="hidden" name="id" value="<?= $b['id'] ?>">
                                    <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Delete this book?')"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(empty($books)): ?>
                    <tr><td colspan="8" style="text-align:center;padding:24px;color:var(--text-secondary);">No books yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD BOOK MODAL -->
<div class="modal-overlay" id="addBookModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Book</h3>
            <button class="modal-close" onclick="Modal.close('addBookModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_book">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group"><label>Title *</label><input type="text" name="title" class="form-control" required></div>
                    <div class="form-group"><label>Author</label><input type="text" name="author" class="form-control"></div>
                    <div class="form-group"><label>Publisher</label><input type="text" name="publisher" class="form-control"></div>
                    <div class="form-group"><label>Year</label><input type="number" name="publish_year" class="form-control" value="<?= date('Y') ?>"></div>
                    <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" class="form-control" value="1" min="1"></div>
                    <div class="form-group"><label>Shelf</label><input type="text" name="shelf_location" class="form-control" placeholder="A1 / B2…"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addBookModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add Book</button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT BOOK MODAL -->
<div class="modal-overlay" id="editBookModal">
    <div class="modal modal-lg">
        <div class="modal-header">
            <h3><i class="fas fa-edit"></i> Edit Book</h3>
            <button class="modal-close" onclick="Modal.close('editBookModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="edit_book">
            <input type="hidden" name="id" id="editBookId">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group"><label>Title *</label><input type="text" name="title" id="eb_title" class="form-control" required></div>
                    <div class="form-group"><label>Author</label><input type="text" name="author" id="eb_author" class="form-control"></div>
                    <div class="form-group"><label>Publisher</label><input type="text" name="publisher" id="eb_publisher" class="form-control"></div>
                    <div class="form-group"><label>Year</label><input type="number" name="publish_year" id="eb_year" class="form-control"></div>
                    <div class="form-group"><label>Total Copies</label><input type="number" name="total_copies" id="eb_copies" class="form-control" min="1"></div>
                    <div class="form-group"><label>Shelf</label><input type="text" name="shelf_location" id="eb_shelf" class="form-control"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('editBookModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button>
            </div>
        </form>
    </div>
</div>

<script>
function editBook(b) {
    document.getElementById('editBookId').value   = b.id;
    document.getElementById('eb_title').value     = b.title;
    document.getElementById('eb_author').value    = b.author    || '';
    document.getElementById('eb_publisher').value = b.publisher || '';
    document.getElementById('eb_year').value      = b.publish_year || '';
    document.getElementById('eb_copies').value    = b.total_copies || 1;
    document.getElementById('eb_shelf').value     = b.shelf_location || '';
    Modal.open('editBookModal');
}

// Scroll persistence is handled globally in admin_footer.php
</script>

<?php include '../includes/admin_footer.php'; ?>