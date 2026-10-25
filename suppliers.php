<?php
// suppliers.php - Suppliers & Accounts Payable
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();
$error = '';
$success = '';

// Handle Add Supplier
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_supplier'])) {
    $name = trim($_POST['name'] ?? '');
    $company = trim($_POST['company'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $opening_balance = (float)($_POST['opening_balance'] ?? 0);

    if (empty($name)) {
        $error = "Supplier name is required.";
    } else {
        $stmt = $db->prepare("INSERT INTO suppliers (name, company, phone, email, address, opening_balance, current_balance) 
                              VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $company, $phone, $email, $address, $opening_balance, $opening_balance]);
        $success = "Supplier {$name} added successfully!";
    }
}

// Handle Record Supplier Payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_payment'])) {
    $supplier_id = (int)$_POST['supplier_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = clean($_POST['payment_method'] ?? 'cash');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_date = clean($_POST['payment_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');

    if ($supplier_id <= 0 || $amount <= 0) {
        $error = "Please enter a valid supplier and payment amount.";
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO supplier_payments (supplier_id, payment_method, amount, reference_no, payment_date, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$supplier_id, $payment_method, $amount, $reference_no, $payment_date, $notes]);

            // Deduct from supplier balance
            $db->prepare("UPDATE suppliers SET current_balance = current_balance - ? WHERE id = ?")
               ->execute([$amount, $supplier_id]);

            log_audit('SUPPLIER_PAY', 'SUPPLIERS', $supplier_id, "Paid Rs. {$amount} via {$payment_method} to supplier #{$supplier_id}");
            $db->commit();
            $success = "Payment of Rs. " . number_format($amount, 2) . " recorded successfully!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Payment failed: " . $e->getMessage();
        }
    }
}

// Fetch all suppliers
$suppliers = $db->query("SELECT s.*, 
    (SELECT COUNT(*) FROM purchases WHERE supplier_id = s.id) as total_invoices,
    (SELECT COALESCE(SUM(grand_total), 0) FROM purchases WHERE supplier_id = s.id) as total_purchased,
    (SELECT COALESCE(SUM(amount), 0) FROM supplier_payments WHERE supplier_id = s.id) as total_paid
FROM suppliers s ORDER BY s.name ASC")->fetchAll();

$total_payable = array_sum(array_column($suppliers, 'current_balance'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-handshake text-primary me-2"></i>Suppliers &amp; Accounts Payable
            </h2>
            <p class="text-muted small mb-0">Track shoe factories, wholesalers, purchase invoices, and Khata payments</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
                <i class="fa-solid fa-plus-circle me-1"></i> Add Supplier
            </button>
            <a href="purchases.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-cart-shopping me-1"></i> Purchases
            </a>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= clean($success) ?></div>
    <?php endif; ?>

    <!-- Summary Box -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Total Active Suppliers</span>
                <h3 class="fw-bold text-dark mt-2 mb-0"><?= count($suppliers) ?> Companies</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Total Outstanding Payables</span>
                <h3 class="fw-bold text-danger mt-2 mb-0"><?= format_currency($total_payable) ?></h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Total Purchases Value</span>
                <h3 class="fw-bold text-primary mt-2 mb-0"><?= format_currency(array_sum(array_column($suppliers, 'total_purchased'))) ?></h3>
            </div>
        </div>
    </div>

    <!-- Suppliers Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Supplier / Contact Person</th>
                        <th>Company Name</th>
                        <th>Phone</th>
                        <th>Address</th>
                        <th class="text-center">Invoices</th>
                        <th>Total Purchases</th>
                        <th>Payable Khata Balance</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($suppliers)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No suppliers registered.</td></tr>
                    <?php else: ?>
                        <?php foreach ($suppliers as $s): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= clean($s['name']) ?></div>
                                    <div class="small text-muted"><?= clean($s['email'] ?? '') ?></div>
                                </td>
                                <td class="fw-semibold text-secondary"><?= clean($s['company'] ?? 'Wholesaler') ?></td>
                                <td><?= clean($s['phone'] ?? '-') ?></td>
                                <td class="small text-muted"><?= clean($s['address'] ?? '-') ?></td>
                                <td class="text-center">
                                    <span class="badge bg-light text-dark border"><?= $s['total_invoices'] ?> bills</span>
                                </td>
                                <td class="fw-semibold"><?= format_currency($s['total_purchased']) ?></td>
                                <td>
                                    <span class="fw-bold <?= $s['current_balance'] > 0 ? 'text-danger fs-6' : 'text-success' ?>">
                                        <?= format_currency($s['current_balance']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <button class="btn btn-outline-success" onclick="openPayModal(<?= $s['id'] ?>, '<?= htmlspecialchars(addslashes($s['name']), ENT_QUOTES) ?>', <?= $s['current_balance'] ?>)" title="Pay Supplier">
                                            <i class="fa-solid fa-money-bill-transfer me-1"></i> Pay
                                        </button>
                                        <a href="supplier_ledger.php?id=<?= $s['id'] ?>" class="btn btn-outline-dark" title="View Ledger">
                                            <i class="fa-solid fa-book-open"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Add Supplier -->
<div class="modal fade" id="addSupplierModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Add New Shoe Supplier / Factory</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <input type="hidden" name="add_supplier" value="1">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Supplier Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. Haji Tariq">
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Company / Factory Name</label>
                        <input type="text" name="company" class="form-control" placeholder="e.g. Apex Sole Works">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Phone Number</label>
                            <input type="text" name="phone" class="form-control" placeholder="+92 300 1234567">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Opening Balance (Rs.)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Address / Market Location</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="e.g. Moti Bazaar, Lahore"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Supplier</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Record Supplier Payment -->
<div class="modal fade" id="payModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-money-bill-transfer me-2"></i>Pay Supplier Khata</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="suppliers.php">
                <input type="hidden" name="record_payment" value="1">
                <input type="hidden" name="supplier_id" id="pay_supplier_id" value="">
                <div class="modal-body p-3">
                    <div class="bg-light p-3 rounded-3 mb-3 border text-center">
                        <span class="text-muted small fw-semibold">Paying To:</span>
                        <h5 class="fw-bold text-dark mt-1 mb-1" id="pay_supplier_name">Haji Tariq</h5>
                        <div class="text-danger fw-bold small">Current Payable: <span id="pay_supplier_bal">Rs. 0.00</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Payment Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" id="pay_amount_input" class="form-control form-control-lg fw-bold" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Cheque / Transaction Ref #</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. TR-98234 / Chq #409">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Part payment against carton shipment">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success fw-bold">Confirm Payment</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openPayModal(id, name, balance) {
    document.getElementById('pay_supplier_id').value = id;
    document.getElementById('pay_supplier_name').innerText = name;
    document.getElementById('pay_supplier_bal').innerText = 'Rs. ' + parseFloat(balance).toFixed(2);
    document.getElementById('pay_amount_input').value = Math.max(0, balance);
    new bootstrap.Modal(document.getElementById('payModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
