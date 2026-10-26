<?php
require_once '../includes/auth.php';
requireRole('student', '../index.php');

$pageTitle  = 'My Fees';
$activeMenu = 'fees';
$user       = currentUser();
$studentId  = $user['ref_id'];

$student = db()->fetchOne("SELECT * FROM students WHERE id=?", 'i', [$studentId]);
$classId = $student['class_id'] ?? 0;

// Fee structures for this class
$feeStructures = $classId ? db()->fetchAll("SELECT * FROM fee_structure WHERE class_id=? ORDER BY fee_type", 'i', [$classId]) : [];

// Payments made by this student
$payments = db()->fetchAll("
    SELECT fp.*, fs.fee_type, fs.amount as fee_amount
    FROM fee_payments fp
    JOIN fee_structure fs ON fp.fee_structure_id = fs.id
    WHERE fp.student_id = ?
    ORDER BY fp.payment_date DESC
", 'i', [$studentId]);

// Build payment map: fee_structure_id => total paid
$paidMap = [];
foreach ($payments as $p) {
    $fsid = $p['fee_structure_id'];
    $paidMap[$fsid] = ($paidMap[$fsid] ?? 0) + $p['amount_paid'];
}

$totalFee  = array_sum(array_column($feeStructures, 'amount'));
$totalPaid = array_sum($paidMap);
$totalDue  = max(0, $totalFee - $totalPaid);

require_once '../includes/student_header.php';
?>

<!-- Summary Cards -->
<div class="stats-grid" style="margin-bottom:24px;">
    <div class="stat-card blue">
        <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
        <div class="stat-info"><h3>Rs <?= number_format($totalFee,0) ?></h3><p>Total Fee</p></div>
    </div>
    <div class="stat-card green">
        <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
        <div class="stat-info"><h3>Rs <?= number_format($totalPaid,0) ?></h3><p>Total Paid</p></div>
    </div>
    <div class="stat-card <?= $totalDue > 0 ? 'red' : 'green' ?>">
        <div class="stat-icon <?= $totalDue > 0 ? 'red' : 'green' ?>">
            <i class="fas fa-<?= $totalDue > 0 ? 'exclamation-circle' : 'check-double' ?>"></i>
        </div>
        <div class="stat-info">
            <h3>Rs <?= number_format($totalDue,0) ?></h3>
            <p><?= $totalDue > 0 ? 'Amount Due' : 'Fully Paid' ?></p>
        </div>
    </div>
</div>

<?php if ($totalDue > 0): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-triangle"></i>
    <strong>Fee Due:</strong> You have Rs <?= number_format($totalDue,0) ?> outstanding. Please pay at the school office.
</div>
<?php endif; ?>

<!-- Fee-wise Breakdown -->
<div class="card" style="margin-bottom:22px;">
    <div class="card-header"><h3><i class="fas fa-list-alt"></i> Fee-wise Status</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Fee Type</th><th>Total Amount</th><th>Paid</th><th>Due</th><th>Status</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($feeStructures as $fs):
                        $paid = $paidMap[$fs['id']] ?? 0;
                        $due  = max(0, $fs['amount'] - $paid);
                        $pct  = $fs['amount'] > 0 ? min(100, round(($paid / $fs['amount']) * 100)) : 0;

                        if ($paid >= $fs['amount']) {
                            $statusLabel = 'Fully Paid';
                            $statusClass = 'paid-badge';
                            $iconClass   = 'check';
                        } elseif ($paid > 0) {
                            $statusLabel = 'Partial';
                            $statusClass = 'partial-badge';
                            $iconClass   = 'adjust';
                        } else {
                            $statusLabel = 'Unpaid';
                            $statusClass = 'due-badge';
                            $iconClass   = 'times';
                        }
                    ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($fs['fee_type']) ?></strong></td>
                        <td>Rs <?= number_format($fs['amount'],0) ?></td>
                        <td>
                            <strong style="color:var(--success);">Rs <?= number_format($paid,0) ?></strong>
                            <div class="fee-progress" style="margin-top:4px;">
                                <div class="fee-progress-fill"
                                    style="width:<?= $pct ?>%;background:<?= $pct>=100 ? 'var(--success)' : ($pct>0 ? 'var(--warning)' : 'var(--border)') ?>;">
                                </div>
                            </div>
                            <small style="color:var(--text-secondary);"><?= $pct ?>%</small>
                        </td>
                        <td>
                            <?php if ($due > 0): ?>
                            <strong style="color:var(--danger);">Rs <?= number_format($due,0) ?></strong>
                            <?php else: ?>
                            <span style="color:var(--success);">–</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="<?= $statusClass ?>">
                                <i class="fas fa-<?= $iconClass ?>"></i> <?= $statusLabel ?>
                            </span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($feeStructures)): ?>
                    <tr>
                        <td colspan="5" style="text-align:center;padding:20px;color:var(--text-secondary);">
                            No fee structure defined for your class.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Payment History -->
<div class="card">
    <div class="card-header"><h3><i class="fas fa-history"></i> Payment History</h3></div>
    <div class="card-body" style="padding:0;">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr><th>Receipt #</th><th>Fee Type</th><th>Paid</th><th>Date (BS)</th><th>Method</th><th>Receipt</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($payments)): ?>
                    <tr>
                        <td colspan="6" style="text-align:center;padding:20px;color:var(--text-secondary);">
                            No payments recorded yet.
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($payments as $p): ?>
                    <tr>
                        <td><span class="badge badge-primary" style="font-size:0.72rem;"><?= htmlspecialchars($p['receipt_number']) ?></span></td>
                        <td><?= htmlspecialchars($p['fee_type']) ?></td>
                        <td><strong style="color:var(--success);">Rs <?= number_format($p['amount_paid'],0) ?></strong></td>
                        <td><span data-bs-date="<?= $p['payment_date'] ?>"><?= $p['payment_date'] ?></span></td>
                        <td><span class="badge badge-gray"><?= $p['payment_method'] ?></span></td>
                        <td>
                            <?php
                            $receiptData = [
                                'receipt_number' => $p['receipt_number'],
                                'student_name'   => $user['name'],
                                'fee_type'       => $p['fee_type'],
                                'fee_amount'     => $p['fee_amount'],
                                'amount_paid'    => $p['amount_paid'],
                                'payment_date'   => $p['payment_date'],
                                'payment_method' => $p['payment_method'],
                            ];
                            ?>
                            <button class="btn btn-outline btn-sm"
                                onclick="viewReceipt(<?= htmlspecialchars(json_encode($receiptData)) ?>)">
                                <i class="fas fa-file-invoice"></i> View
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- RECEIPT MODAL -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal">
        <div class="modal-header">
            <h3><i class="fas fa-receipt"></i> Payment Receipt</h3>
            <div style="display:flex;gap:8px;">
                <button class="btn btn-primary btn-sm" onclick="downloadReceipt()">
                    <i class="fas fa-download"></i> Download
                </button>
                <button class="btn btn-outline btn-sm" onclick="printSection('receiptPrint')">
                    <i class="fas fa-print"></i> Print
                </button>
                <button class="modal-close" onclick="Modal.close('receiptModal')">
                    <i class="fas fa-times"></i>
                </button>
            </div>
        </div>
        <div class="modal-body">
            <div class="receipt" id="receiptPrint">
                <div class="receipt-header">
                    <h2 style="font-family:'Playfair Display',serif;color:var(--primary);">Ganeshman Singh Academy</h2>
                    <p style="color:var(--text-secondary);font-size:0.78rem;">Jhiljhille, Jhapa, Nepal</p>
                    <div style="margin-top:10px;padding:8px;background:var(--bg);border-radius:var(--radius-sm);">
                        <strong style="font-size:0.88rem;color:var(--primary);">FEE PAYMENT RECEIPT</strong>
                    </div>
                </div>
                <div id="receiptBody"></div>
            </div>
        </div>
    </div>
</div>

<script>
let currentReceiptData = null;

function viewReceipt(p) {
    currentReceiptData = p;
    const bsDate = NepaliDate.format(p.payment_date);
    const feeAmt = parseFloat(p.fee_amount) || 0;
    const paid   = parseFloat(p.amount_paid) || 0;
    const due    = Math.max(0, feeAmt - paid);

    const html = `
        <div class="receipt-row"><span style="color:var(--text-secondary);">Receipt No.</span><span style="font-weight:800;">${p.receipt_number}</span></div>
        <div class="receipt-row"><span style="color:var(--text-secondary);">Student</span><span>${p.student_name}</span></div>
        <div class="receipt-row"><span style="color:var(--text-secondary);">Fee Type</span><span>${p.fee_type}</span></div>
        <div class="receipt-row"><span style="color:var(--text-secondary);">Fee Amount</span><span>Rs ${feeAmt.toLocaleString()}</span></div>
        <div class="receipt-row"><span style="color:var(--text-secondary);">Amount Paid</span><span style="color:var(--success);font-weight:700;">Rs ${paid.toLocaleString()}</span></div>
        <div class="receipt-row"><span style="color:var(--text-secondary);">Payment Date</span><span>${bsDate}</span></div>
        <div class="receipt-row"><span style="color:var(--text-secondary);">Method</span><span>${p.payment_method}</span></div>
        ${due > 0 ? `<div class="receipt-row" style="background:#fef2f2;padding:6px 4px;border-radius:4px;"><span style="color:var(--danger);font-weight:700;">Due Amount</span><span style="color:var(--danger);font-weight:800;">Rs ${due.toLocaleString()}</span></div>` : ''}
        <div class="receipt-total"><span>Total Paid</span><span>Rs ${paid.toLocaleString()}</span></div>
        <p style="text-align:center;font-size:0.75rem;color:var(--text-secondary);margin-top:16px;border-top:1px dashed var(--border);padding-top:12px;">
            Thank you for your payment. Keep this receipt for your records.
        </p>
    `;
    document.getElementById('receiptBody').innerHTML = html;
    Modal.open('receiptModal');
}

function downloadReceipt() {
    if (!currentReceiptData) return;
    const p      = currentReceiptData;
    const bsDate = NepaliDate.format(p.payment_date);
    const feeAmt = parseFloat(p.fee_amount) || 0;
    const paid   = parseFloat(p.amount_paid) || 0;
    const due    = Math.max(0, feeAmt - paid);

    const content = [
        'GANESHMAN SINGH ACADEMY',
        'Jhiljhille, Jhapa, Nepal',
        '================================',
        'FEE PAYMENT RECEIPT',
        '================================',
        'Receipt No : ' + p.receipt_number,
        'Student    : ' + p.student_name,
        'Fee Type   : ' + p.fee_type,
        'Fee Amount : Rs ' + feeAmt.toLocaleString(),
        'Paid       : Rs ' + paid.toLocaleString(),
        'Due        : Rs ' + due.toLocaleString(),
        'Date (BS)  : ' + bsDate,
        'Method     : ' + p.payment_method,
        '================================',
        'Thank you for your payment.',
    ].join('\n');

    const blob = new Blob([content], { type: 'text/plain' });
    const a    = document.createElement('a');
    a.href     = URL.createObjectURL(blob);
    a.download = 'receipt_' + p.receipt_number + '.txt';
    a.click();
    URL.revokeObjectURL(a.href);
    Toast.success('Receipt downloaded!');
}
</script>

<?php require_once '../includes/student_footer.php'; ?>
