<?php
// customers.php - Customer Profiles & Khata (Receivables)
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();
$error = '';
$success = '';

// Handle Add Customer
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_customer'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $preferred_size = trim($_POST['preferred_size'] ?? '');
    $credit_limit = (float)($_POST['credit_limit'] ?? 50000.00);
    $opening_balance = (float)($_POST['opening_balance'] ?? 0.00);

    if (empty($name)) {
        $error = "Customer name is required.";
    } else {
        $stmt = $db->prepare("INSERT INTO customers (name, phone, email, address, preferred_size, credit_limit, opening_balance, current_balance) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $phone, $email, $address, $preferred_size, $credit_limit, $opening_balance, $opening_balance]);
        $success = "Customer {$name} added successfully!";
    }
}

// Handle Record Customer Khata Payment (Repayment)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['receive_customer_payment'])) {
    $customer_id = (int)$_POST['customer_id'];
    $amount = (float)$_POST['amount'];
    $payment_method = clean($_POST['payment_method'] ?? 'cash');
    $reference_no = trim($_POST['reference_no'] ?? '');
    $payment_date = clean($_POST['payment_date'] ?? date('Y-m-d'));
    $notes = trim($_POST['notes'] ?? '');

    if ($customer_id <= 0 || $amount <= 0) {
        $error = "Please specify a valid customer and payment amount.";
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO customer_payments (customer_id, payment_method, amount, reference_no, payment_date, notes) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$customer_id, $payment_method, $amount, $reference_no, $payment_date, $notes]);

            // Deduct from customer's receivable khata balance
            $db->prepare("UPDATE customers SET current_balance = current_balance - ? WHERE id = ?")
               ->execute([$amount, $customer_id]);

            // If active register and cash, record cash in
            $user_id = $_SESSION['user_id'];
            $active_reg = get_active_register($user_id);
            if ($active_reg && $payment_method === 'cash') {
                $db->prepare("UPDATE cash_registers SET cash_in = cash_in + ? WHERE id = ?")
                   ->execute([$amount, $active_reg['id']]);
            }

            log_audit('CUSTOMER_PAY', 'CUSTOMERS', $customer_id, "Received Rs. {$amount} via {$payment_method} from customer #{$customer_id}");
            $db->commit();
            $success = "Payment of Rs. " . number_format($amount, 2) . " received and Khata updated!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Payment error: " . $e->getMessage();
        }
    }
}

// Search
$q = trim($_GET['q'] ?? '');
$sql = "SELECT c.*, 
               (SELECT COUNT(*) FROM sales WHERE customer_id = c.id) as total_bills,
               (SELECT COALESCE(SUM(grand_total), 0) FROM sales WHERE customer_id = c.id) as total_purchased
        FROM customers c WHERE 1=1 ";
$params = [];
if (!empty($q)) {
    $sql .= " AND (c.name LIKE ? OR c.phone LIKE ? OR c.preferred_size LIKE ?)";
    $term = "%{$q}%";
    $params = [$term, $term, $term];
}
$sql .= " ORDER BY c.current_balance DESC, c.id ASC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$customers = $stmt->fetchAll();

$total_receivable = array_sum(array_column($customers, 'current_balance'));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-users text-primary me-2"></i>Customers &amp; Khata (Accounts Receivable)
            </h2>
            <p class="text-muted small mb-0">Customer directory, preferred shoe sizes, credit limit, and Khata ledger</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addCustomerModal">
                <i class="fa-solid fa-user-plus me-1"></i> Add Customer
            </button>
            <a href="pos.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-cash-register me-1"></i> POS
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
                <span class="text-muted small fw-semibold text-uppercase">Registered Customers</span>
                <h3 class="fw-bold text-dark mt-2 mb-0"><?= count($customers) ?> People</h3>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Total Khata Receivables</span>
                <h3 class="fw-bold text-danger mt-2 mb-0"><?= format_currency($total_receivable) ?></h3>
                <span class="small text-danger">Uncollected credit balances</span>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Total Customer Sales</span>
                <h3 class="fw-bold text-primary mt-2 mb-0"><?= format_currency(array_sum(array_column($customers, 'total_purchased'))) ?></h3>
            </div>
        </div>
    </div>

    <!-- Search Form -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-4">
        <form method="GET" action="customers.php" class="row g-2 align-items-center">
            <div class="col-md-10">
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" name="q" class="form-control" placeholder="Search by Customer Name, Phone, Preferred Shoe Size..." value="<?= clean($q) ?>">
                </div>
            </div>
            <div class="col-md-2 d-flex gap-1">
                <button type="submit" class="btn btn-primary w-100">Search</button>
                <a href="customers.php" class="btn btn-outline-secondary" title="Reset"><i class="fa-solid fa-arrow-rotate-right"></i></a>
            </div>
        </form>
    </div>

    <!-- Customers Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Customer Name</th>
                        <th>Mobile / Phone</th>
                        <th class="text-center">Shoe Size</th>
                        <th>Credit Limit</th>
                        <th>Total Spent</th>
                        <th>Khata Receivable (Due)</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($customers)): ?>
                        <tr><td colspan="7" class="text-center py-5 text-muted">No customers found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($customers as $c): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold text-dark"><?= clean($c['name']) ?></div>
                                    <div class="small text-muted"><?= clean($c['address'] ?? '') ?></div>
                                </td>
                                <td>
                                    <span class="fw-semibold text-secondary"><?= clean($c['phone'] ?? '-') ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if (!empty($c['preferred_size'])): ?>
                                        <span class="badge bg-primary fs-6 px-3">Size <?= clean($c['preferred_size']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= format_currency($c['credit_limit']) ?></td>
                                <td class="fw-semibold"><?= format_currency($c['total_purchased']) ?></td>
                                <td>
                                    <span class="fw-bold <?= $c['current_balance'] > 0 ? 'text-danger fs-6' : 'text-success' ?>">
                                        <?= format_currency($c['current_balance']) ?>
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="btn-group btn-group-sm">
                                        <?php if ($c['id'] != 1): ?>
                                            <button class="btn btn-outline-success" onclick="openReceivePayModal(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name']), ENT_QUOTES) ?>', <?= $c['current_balance'] ?>)" title="Receive Khata Payment">
                                                <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive
                                            </button>
                                            <a href="customer_ledger.php?id=<?= $c['id'] ?>" class="btn btn-outline-dark" title="Khata Ledger">
                                                <i class="fa-solid fa-book-bookmark"></i>
                                            </a>
                                        <?php else: ?>
                                            <span class="badge bg-light text-muted border">Walk-in Counter</span>
                                        <?php endif; ?>
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

<!-- Modal 1: Add Customer -->
<div class="modal fade" id="addCustomerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Register Customer Profile</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="add_customer" value="1">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Full Name *</label>
                        <input type="text" name="name" class="form-control" required placeholder="e.g. M. Tariq">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Mobile / Phone *</label>
                            <input type="text" name="phone" class="form-control" required placeholder="0300 1234567">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Preferred Shoe Size</label>
                            <input type="text" name="preferred_size" class="form-control" placeholder="e.g. 42 or 43">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Credit Limit (Rs.)</label>
                            <input type="number" step="0.01" name="credit_limit" class="form-control" value="50000.00">
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Opening Balance (Rs.)</label>
                            <input type="number" step="0.01" name="opening_balance" class="form-control" value="0.00">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Home / Delivery Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="e.g. House #10, Street 4, Lahore"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Customer</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Receive Customer Payment -->
<div class="modal fade" id="receivePayModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-hand-holding-dollar me-2"></i>Receive Khata Payment</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="customers.php">
                <input type="hidden" name="receive_customer_payment" value="1">
                <input type="hidden" name="customer_id" id="rcv_customer_id" value="">
                <div class="modal-body p-3">
                    <div class="bg-light p-3 rounded-3 mb-3 border text-center">
                        <span class="text-muted small fw-semibold">Customer:</span>
                        <h5 class="fw-bold text-dark mt-1 mb-1" id="rcv_customer_name">Customer</h5>
                        <div class="text-danger fw-bold small">Current Due Balance: <span id="rcv_customer_bal">Rs. 0.00</span></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Payment Amount Received (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" id="rcv_amount_input" class="form-control form-control-lg fw-bold" required>
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Mode</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="card">Card</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Tx / Receipt Reference</label>
                        <input type="text" name="reference_no" class="form-control" placeholder="e.g. Cash recovery / JazzCash TID">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold">Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="Optional notes...">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Record Khata Recovery</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function openReceivePayModal(id, name, balance) {
    document.getElementById('rcv_customer_id').value = id;
    document.getElementById('rcv_customer_name').innerText = name;
    document.getElementById('rcv_customer_bal').innerText = 'Rs. ' + parseFloat(balance).toFixed(2);
    document.getElementById('rcv_amount_input').value = Math.max(0, balance);
    new bootstrap.Modal(document.getElementById('receivePayModal')).show();
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
