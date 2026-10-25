<?php
// supplier_ledger.php - Supplier Khata Ledger & Statement
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$supplier_id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM suppliers WHERE id = ?");
$stmt->execute([$supplier_id]);
$supplier = $stmt->fetch();

if (!$supplier) {
    die("Supplier not found.");
}

// Fetch purchases
$purchases = $db->prepare("SELECT id, po_number as ref_no, purchase_date as tx_date, 'purchase' as type, grand_total as amount, notes 
                           FROM purchases WHERE supplier_id = ?");
$purchases->execute([$supplier_id]);
$p_list = $purchases->fetchAll();

// Fetch payments
$payments = $db->prepare("SELECT id, reference_no as ref_no, payment_date as tx_date, 'payment' as type, amount, payment_method, notes 
                          FROM supplier_payments WHERE supplier_id = ?");
$payments->execute([$supplier_id]);
$pay_list = $payments->fetchAll();

// Merge and sort by date
$ledger = array_merge($p_list, $pay_list);
usort($ledger, function($a, $b) {
    return strtotime($a['tx_date']) <=> strtotime($b['tx_date']);
});

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-book-open text-primary me-2"></i>Supplier Khata: <?= clean($supplier['name']) ?>
            </h2>
            <p class="text-muted small mb-0"><?= clean($supplier['company'] ?? 'Wholesaler') ?> &mdash; Ph: <?= clean($supplier['phone'] ?? '-') ?></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark">
                <i class="fa-solid fa-print me-1"></i> Print Statement
            </button>
            <a href="suppliers.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Suppliers
            </a>
        </div>
    </div>

    <!-- Balance Banner -->
    <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
        <div class="row align-items-center">
            <div class="col-md-4">
                <span class="text-muted small fw-semibold text-uppercase">Opening Balance</span>
                <h4 class="fw-bold text-dark mt-1 mb-0"><?= format_currency($supplier['opening_balance']) ?></h4>
            </div>
            <div class="col-md-4 border-start">
                <span class="text-muted small fw-semibold text-uppercase">Current Outstanding Payable</span>
                <h3 class="fw-bold text-danger mt-1 mb-0"><?= format_currency($supplier['current_balance']) ?></h3>
            </div>
            <div class="col-md-4 border-start">
                <span class="text-muted small fw-semibold text-uppercase">Contact &amp; Address</span>
                <div class="small text-muted mt-1"><?= clean($supplier['address'] ?? 'No address recorded') ?></div>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="fw-bold text-dark mb-0">Chronological Account Statement</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Transaction Description</th>
                        <th>Ref #</th>
                        <th class="text-end">Debit (We Paid)</th>
                        <th class="text-end">Credit (Purchased)</th>
                        <th class="text-end">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Opening balance row -->
                    <tr class="table-light">
                        <td>&mdash;</td>
                        <td><strong>Opening Balance</strong></td>
                        <td>&mdash;</td>
                        <td class="text-end">&mdash;</td>
                        <td class="text-end">&mdash;</td>
                        <td class="text-end fw-bold"><?= format_currency($supplier['opening_balance']) ?></td>
                    </tr>
                    <?php 
                    $running_balance = (float)$supplier['opening_balance'];
                    if (empty($ledger)): 
                    ?>
                        <tr><td colspan="6" class="text-center py-4 text-muted">No transactions found for this supplier.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $tx): 
                            if ($tx['type'] === 'purchase') {
                                $credit = (float)$tx['amount'];
                                $debit = 0.00;
                                $running_balance += $credit;
                                $desc = "Purchase Invoice / Stock Received";
                            } else {
                                $debit = (float)$tx['amount'];
                                $credit = 0.00;
                                $running_balance -= $debit;
                                $desc = "Payment Made (" . ucfirst($tx['payment_method'] ?? 'cash') . ")";
                            }
                        ?>
                            <tr>
                                <td class="text-muted"><?= date('d M Y', strtotime($tx['tx_date'])) ?></td>
                                <td>
                                    <strong><?= $desc ?></strong>
                                    <?php if (!empty($tx['notes'])): ?>
                                        <div class="small text-muted"><?= clean($tx['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= clean($tx['ref_no']) ?></span></td>
                                <td class="text-end text-success fw-semibold"><?= $debit > 0 ? format_currency($debit) : '-' ?></td>
                                <td class="text-end text-primary fw-semibold"><?= $credit > 0 ? format_currency($credit) : '-' ?></td>
                                <td class="text-end fw-bold <?= $running_balance > 0 ? 'text-danger' : 'text-success' ?>">
                                    <?= format_currency($running_balance) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
