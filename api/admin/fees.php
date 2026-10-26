<?php
require_once '../includes/auth.php';
requireRole('admin', '../index.php');

$pageTitle = 'Fee Management';
$activeMenu = 'fees';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_structure') {
        $classId  = (int)($_POST['class_id'] ?? 0);
        $feeType  = sanitize($_POST['fee_type'] ?? '');
        $amount   = (float)($_POST['amount'] ?? 0);
        $dueDate  = (int)($_POST['due_date'] ?? 10);
        $year     = sanitize($_POST['academic_year'] ?? date('Y'));

        db()->execute(
            "INSERT INTO fee_structure (class_id,fee_type,amount,due_date,academic_year) VALUES (?,?,?,?,?)",
            'isdis', [$classId,$feeType,$amount,$dueDate,$year]
        );
        setFlash('success', 'Fee structure added.');
        redirect('fees.php');
    }

    if ($action === 'record_payment') {
        $studentId  = (int)($_POST['student_id'] ?? 0);
        $feeStructId= (int)($_POST['fee_structure_id'] ?? 0);
        $amountPaid = (float)($_POST['amount_paid'] ?? 0);
        $payDate    = sanitize($_POST['payment_date'] ?? date('Y-m-d'));
        $method     = sanitize($_POST['payment_method'] ?? 'Cash');
        $remarks    = sanitize($_POST['remarks'] ?? '');

        // Generate receipt
        $receipt = 'RCP' . strtoupper(uniqid());

        db()->execute(
            "INSERT INTO fee_payments (student_id,fee_structure_id,amount_paid,payment_date,receipt_number,payment_method,collected_by,remarks)
             VALUES (?,?,?,?,?,?,?,?)",
            'iidsssss', [$studentId,$feeStructId,$amountPaid,$payDate,$receipt,$method,1,$remarks]
        );
        setFlash('success', "Payment recorded. Receipt: $receipt");
        redirect('fees.php');
    }
}

$classes    = db()->fetchAll("SELECT * FROM classes ORDER BY class_name, section");
$feeStructs = db()->fetchAll("SELECT fs.*, c.class_name, c.section FROM fee_structure fs JOIN classes c ON fs.class_id=c.id ORDER BY c.class_name");

$totalCollected = db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as total FROM fee_payments")['total'] ?? 0;
$totalDue       = db()->fetchOne("SELECT COALESCE(SUM(amount),0) as total FROM fee_structure")['total'] ?? 0;

$recentPayments = db()->fetchAll("
    SELECT fp.*, s.first_name, s.last_name, s.student_id as sid, fs.fee_type, c.class_name, c.section
    FROM fee_payments fp
    JOIN students s ON fp.student_id = s.id
    JOIN fee_structure fs ON fp.fee_structure_id = fs.id
    JOIN classes c ON fs.class_id = c.id
    ORDER BY fp.created_at DESC LIMIT 20
");

$students = db()->fetchAll("SELECT s.id, s.student_id, s.first_name, s.last_name, c.class_name as faculty, c.section as semester FROM students s LEFT JOIN classes c ON s.class_id=c.id WHERE s.is_active=1 ORDER BY c.class_name, c.section, s.first_name");
$faculties = db()->fetchAll("SELECT DISTINCT class_name FROM classes ORDER BY class_name");

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

<!-- Stats -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-rupee-sign"></i></div>
        <div class="stat-info">
            <h3>Rs <?= number_format($totalCollected, 0) ?></h3>
            <p>Total Collected</p>
        </div>
    </div>
    <div class="stat-card amber">
        <div class="stat-icon amber"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-info">
            <h3><?= count($feeStructs) ?></h3>
            <p>Fee Types</p>
        </div>
    </div>
    <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
        <div class="stat-info">
            <h3><?= count($recentPayments) ?></h3>
            <p>Recent Payments</p>
        </div>
    </div>
</div>

<div class="grid-2" style="margin-bottom:24px;">
    <!-- Fee Structure -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-list-alt"></i> Fee Structure</h3>
            <button class="btn btn-primary btn-sm" onclick="Modal.open('addFeeStructModal')">
                <i class="fas fa-plus"></i> Add Fee Type
            </button>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Class</th><th>Fee Type</th><th>Amount</th><th>Due</th><th>Year</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeStructs as $fs): ?>
                        <tr>
                            <td><?= htmlspecialchars($fs['class_name'].' '.$fs['section']) ?></td>
                            <td><?= htmlspecialchars($fs['fee_type']) ?></td>
                            <td><strong>Rs <?= number_format($fs['amount'], 0) ?></strong></td>
                            <td>Day <?= $fs['due_date'] ?></td>
                            <td><?= htmlspecialchars($fs['academic_year'] ?? '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($feeStructs)): ?>
                        <tr><td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary);">No fee structures defined.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Record Payment -->
    <div class="card">
        <div class="card-header">
            <h3><i class="fas fa-money-bill-wave"></i> Record Payment</h3>
        </div>
        <div class="card-body">
            <form method="POST">
                <input type="hidden" name="action" value="record_payment">
                <?php
                $studentsJson = json_encode(array_values($students));
                $facultiesJson = json_encode(array_column($faculties, 'class_name'));
                ?>
                <?= studentFilterWidget('fees_student', $studentsJson, $facultiesJson) ?>
                <div class="form-group">
                    <label>Fee Type *</label>
                    <select name="fee_structure_id" class="form-control" required>
                        <option value="">Select Fee Type</option>
                        <?php foreach ($feeStructs as $fs): ?>
                        <option value="<?= $fs['id'] ?>"><?= htmlspecialchars($fs['fee_type'].' – '.$fs['class_name'].' '.$fs['section'].' (Rs '.number_format($fs['amount'],0).')') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Amount Paid (Rs) *</label>
                    <input type="number" name="amount_paid" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Payment Date *</label>
                    <?= bsDatePicker('payment_date', date('Y-m-d'), true) ?>
                </div>
                <div class="form-group">
                    <label>Method</label>
                    <select name="payment_method" class="form-control">
                        <option>Cash</option>
                        <option>Cheque</option>
                        <option>Online</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Remarks</label>
                    <input type="text" name="remarks" class="form-control" placeholder="Optional">
                </div>
                <button type="submit" class="btn btn-success btn-full">
                    <i class="fas fa-check"></i> Record Payment & Generate Receipt
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Recent Payments -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Recent Payments</h3>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Receipt #</th><th>Student</th><th>Class</th><th>Fee Type</th><th>Paid</th><th>Due</th><th>Date</th><th>Method</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($recentPayments as $p): ?>
                    <tr>
                        <td><span class="badge badge-info"><?= htmlspecialchars($p['receipt_number']) ?></span></td>
                        <td><?= htmlspecialchars($p['first_name'].' '.$p['last_name']) ?><br><small style="color:var(--text-secondary);"><?= htmlspecialchars($p['sid']) ?></small></td>
                        <td><?= htmlspecialchars($p['class_name'].' '.$p['section']) ?></td>
                        <td><?= htmlspecialchars($p['fee_type']) ?></td>
                        <td><strong style="color:var(--success);">Rs <?= number_format($p['amount_paid'], 0) ?></strong></td>
                        <td>
                            <?php
                            $feeTotal = db()->fetchOne("SELECT amount FROM fee_structure WHERE id=?", 'i', [$p['fee_structure_id']]);
                            $paidSoFar = db()->fetchOne("SELECT COALESCE(SUM(amount_paid),0) as t FROM fee_payments WHERE student_id=? AND fee_structure_id=?", 'ii', [$p['student_id'],$p['fee_structure_id']]);
                            $dueAmt = max(0, ($feeTotal['amount']??0) - ($paidSoFar['t']??0));
                            ?>
                            <?php if ($dueAmt > 0): ?>
                            <span class="due-badge"><i class="fas fa-exclamation-circle"></i> Rs <?= number_format($dueAmt,0) ?></span>
                            <?php else: ?>
                            <span class="paid-badge"><i class="fas fa-check"></i> Paid</span>
                            <?php endif; ?>
                        </td>
                        <td><?= formatDate($p['payment_date']) ?></td>
                        <td><span class="badge badge-gray"><?= $p['payment_method'] ?></span></td>
                        <td>
                            <button class="btn btn-outline btn-sm" onclick="showReceipt(<?= htmlspecialchars(json_encode($p)) ?>)">
                                <i class="fas fa-print"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($recentPayments)): ?>
                    <tr><td colspan="9" style="text-align:center;padding:24px;color:var(--text-secondary);">No payments recorded yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ADD FEE STRUCTURE MODAL -->
<div class="modal-overlay" id="addFeeStructModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-plus"></i> Add Fee Type</h3>
            <button class="modal-close" onclick="Modal.close('addFeeStructModal')"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add_structure">
            <div class="modal-body">
                <div class="form-group">
                    <label>Faculty / Semester *</label>
                    <select name="class_id" class="form-control" required>
                        <option value="">— Select Faculty & Semester —</option>
                        <?php
                        // Group by faculty (class_name)
                        $feeClassGroups = [];
                        foreach ($classes as $c) { $feeClassGroups[$c['class_name']][] = $c; }
                        foreach ($feeClassGroups as $facName => $facClasses):
                        ?>
                        <optgroup label="<?= htmlspecialchars($facName) ?>">
                            <?php foreach ($facClasses as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['section']) ?></option>
                            <?php endforeach; ?>
                        </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fee Type *</label>
                    <input type="text" name="fee_type" class="form-control" placeholder="Tuition Fee / Exam Fee / etc." required>
                </div>
                <div class="form-group">
                    <label>Amount (NPR) *</label>
                    <input type="number" name="amount" class="form-control" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Due Day of Month</label>
                    <input type="number" name="due_date" class="form-control" value="10" min="1" max="31">
                </div>
                <div class="form-group">
                    <label>Academic Year</label>
                    <input type="text" name="academic_year" class="form-control" value="<?= date('Y') ?>">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="Modal.close('addFeeStructModal')">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Add</button>
            </div>
        </form>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-receipt"></i> Fee Receipt</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-outline btn-sm" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
                <button class="modal-close" onclick="Modal.close('receiptModal')"><i class="fas fa-times"></i></button>
            </div>
        </div>
        <div class="modal-body">
            <div class="receipt" id="receiptContent">
                <div class="receipt-header">
                    <h2>Ganeshman Singh Academy</h2>
                    <p style="font-size:0.78rem;color:var(--text-secondary);">Jhiljhille, Jhapa, Nepal</p>
                    <p style="font-size:0.82rem;font-weight:700;margin-top:8px;">FEE RECEIPT</p>
                </div>
                <div id="receiptBody"></div>
            </div>
        </div>
    </div>
</div>



<?php include '../includes/admin_footer.php'; ?>
<!-- This file already has fee recording. The due amount feature is in student/fees.php -->
<!-- The admin view below already shows payments. For admin due-amount view see reports.php -->