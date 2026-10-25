<?php
// customer_ledger.php - Customer Khata Ledger & Statement
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$customer_id = (int)($_GET['id'] ?? 0);
$stmt = $db->prepare("SELECT * FROM customers WHERE id = ?");
$stmt->execute([$customer_id]);
$customer = $stmt->fetch();

if (!$customer) {
    die("Customer not found.");
}

// Fetch sales invoices
$sales = $db->prepare("SELECT id, invoice_no as ref_no, sale_date as tx_date, 'sale' as type, grand_total, paid_amount, payment_status, payment_method, notes 
                       FROM sales WHERE customer_id = ? AND status = 'completed'");
$sales->execute([$customer_id]);
$s_list = $sales->fetchAll();

// Fetch payments
$payments = $db->prepare("SELECT id, reference_no as ref_no, payment_date as tx_date, 'payment' as type, amount, payment_method, notes 
                          FROM customer_payments WHERE customer_id = ?");
$payments->execute([$customer_id]);
$pay_list = $payments->fetchAll();

// Construct chronological ledger
$ledger = [];
foreach ($s_list as $s) {
    // If credit or partial, the unpaid portion increases customer debt
    $unpaid = $s['grand_total'] - $s['paid_amount'];
    if ($s['payment_method'] === 'credit' || $unpaid > 0) {
        $ledger[] = [
            'id' => $s['id'],
            'ref_no' => $s['ref_no'],
            'tx_date' => $s['tx_date'],
            'type' => 'sale',
            'amount' => $s['grand_total'],
            'paid_on_bill' => $s['paid_amount'],
            'net_charge' => $unpaid,
            'desc' => "Sales Invoice (" . strtoupper($s['payment_method']) . ")",
            'notes' => $s['notes']
        ];
    }
}

foreach ($pay_list as $p) {
    $ledger[] = [
        'id' => $p['id'],
        'ref_no' => $p['ref_no'] ?: 'RCPT-' . $p['id'],
        'tx_date' => $p['tx_date'],
        'type' => 'payment',
        'amount' => $p['amount'],
        'paid_on_bill' => 0,
        'net_charge' => 0,
        'desc' => "Khata Repayment (" . ucfirst($p['payment_method']) . ")",
        'notes' => $p['notes']
    ];
}

usort($ledger, function($a, $b) {
    return strtotime($a['tx_date']) <=> strtotime($b['tx_date']);
});

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-book-bookmark text-primary me-2"></i>Customer Khata: <?= clean($customer['name']) ?>
            </h2>
            <p class="text-muted small mb-0">Phone: <?= clean($customer['phone'] ?? '-') ?> &bull; Preferred Shoe Size: <strong class="text-primary"><?= clean($customer['preferred_size'] ?? 'N/A') ?></strong></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark">
                <i class="fa-solid fa-print me-1"></i> Print Statement
            </button>
            <a href="customers.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Customers
            </a>
        </div>
    </div>

    <!-- Balance Banner -->
    <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
        <div class="row align-items-center">
            <div class="col-md-3">
                <span class="text-muted small fw-semibold text-uppercase">Opening Balance</span>
                <h4 class="fw-bold text-dark mt-1 mb-0"><?= format_currency($customer['opening_balance']) ?></h4>
            </div>
            <div class="col-md-3 border-start">
                <span class="text-muted small fw-semibold text-uppercase">Current Khata Due (Receivable)</span>
                <h3 class="fw-bold text-danger mt-1 mb-0"><?= format_currency($customer['current_balance']) ?></h3>
            </div>
            <div class="col-md-3 border-start">
                <span class="text-muted small fw-semibold text-uppercase">Credit Limit</span>
                <h4 class="fw-bold text-secondary mt-1 mb-0"><?= format_currency($customer['credit_limit']) ?></h4>
            </div>
            <div class="col-md-3 border-start">
                <span class="text-muted small fw-semibold text-uppercase">Preferred Shoe Size</span>
                <h4 class="fw-bold text-primary mt-1 mb-0">Size <?= clean($customer['preferred_size'] ?? 'None') ?></h4>
            </div>
        </div>
    </div>

    <!-- Ledger Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="fw-bold text-dark mb-0">Customer Account Statement</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Transaction Description</th>
                        <th>Invoice / Receipt #</th>
                        <th class="text-end">Bill Amount</th>
                        <th class="text-end">Paid</th>
                        <th class="text-end">Khata Added</th>
                        <th class="text-end">Running Balance</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="table-light">
                        <td>&mdash;</td>
                        <td><strong>Opening Balance</strong></td>
                        <td>&mdash;</td>
                        <td class="text-end">&mdash;</td>
                        <td class="text-end">&mdash;</td>
                        <td class="text-end">&mdash;</td>
                        <td class="text-end fw-bold"><?= format_currency($customer['opening_balance']) ?></td>
                    </tr>
                    <?php 
                    $running_balance = (float)$customer['opening_balance'];
                    if (empty($ledger)): 
                    ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No credit or payment transactions recorded for this customer.</td></tr>
                    <?php else: ?>
                        <?php foreach ($ledger as $tx): 
                            if ($tx['type'] === 'sale') {
                                $added = (float)$tx['net_charge'];
                                $running_balance += $added;
                                $paid = (float)$tx['paid_on_bill'];
                                $amt = (float)$tx['amount'];
                            } else {
                                $added = 0.00;
                                $paid = (float)$tx['amount'];
                                $amt = 0.00;
                                $running_balance -= $paid;
                            }
                        ?>
                            <tr>
                                <td class="text-muted"><?= date('d M Y, h:i A', strtotime($tx['tx_date'])) ?></td>
                                <td>
                                    <strong><?= $tx['desc'] ?></strong>
                                    <?php if (!empty($tx['notes'])): ?>
                                        <div class="small text-muted"><?= clean($tx['notes']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($tx['type'] === 'sale'): ?>
                                        <a href="invoice_print.php?id=<?= $tx['id'] ?>" target="_blank" class="fw-bold text-primary text-decoration-none">
                                            <?= clean($tx['ref_no']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border"><?= clean($tx['ref_no']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end"><?= $amt > 0 ? format_currency($amt) : '-' ?></td>
                                <td class="text-end text-success fw-semibold"><?= $paid > 0 ? format_currency($paid) : '-' ?></td>
                                <td class="text-end text-danger fw-semibold"><?= $added > 0 ? format_currency($added) : '-' ?></td>
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
