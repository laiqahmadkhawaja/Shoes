<?php
// expenses.php - Daily Shop Expenses Management
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Add Expense
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_expense'])) {
    $expense_date = clean($_POST['expense_date'] ?? date('Y-m-d'));
    $category = clean($_POST['category'] ?? 'Miscellaneous');
    $amount = (float)$_POST['amount'];
    $payment_method = clean($_POST['payment_method'] ?? 'cash');
    $description = trim($_POST['description'] ?? '');

    if ($amount <= 0) {
        $error = "Expense amount must be greater than zero.";
    } else {
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO expenses (expense_date, category, amount, payment_method, description, user_id) 
                                  VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$expense_date, $category, $amount, $payment_method, $description, $user_id]);
            $exp_id = $db->lastInsertId();

            // If cash and active register, record cash_out
            $active_reg = get_active_register($user_id);
            if ($active_reg && $payment_method === 'cash') {
                $db->prepare("UPDATE cash_registers SET cash_out = cash_out + ? WHERE id = ?")
                   ->execute([$amount, $active_reg['id']]);
            }

            log_audit('EXPENSE_ADD', 'EXPENSES', $exp_id, "Added expense Rs. {$amount} for {$category} ({$payment_method})");
            $db->commit();
            $success = "Expense of Rs. " . number_format($amount, 2) . " recorded successfully!";
        } catch (Exception $e) {
            $db->rollBack();
            $error = "Error adding expense: " . $e->getMessage();
        }
    }
}

// Filters
$month = clean($_GET['month'] ?? date('Y-m'));
$cat_filter = clean($_GET['category'] ?? '');

$sql = "SELECT e.*, u.full_name as user_name 
        FROM expenses e 
        LEFT JOIN users u ON e.user_id = u.id 
        WHERE DATE_FORMAT(e.expense_date, '%Y-%m') = ? ";
$params = [$month];

if (!empty($cat_filter)) {
    $sql .= " AND e.category = ?";
    $params[] = $cat_filter;
}
$sql .= " ORDER BY e.expense_date DESC, e.id DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Expense stats
$month_total = array_sum(array_column($expenses, 'amount'));
$today_exp = $db->query("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date = CURDATE()")->fetchColumn();

// Predefined categories
$categories = [
    'Shop Rent',
    'Electricity & Utilities',
    'Staff Salaries & Commissions',
    'Transport & Freight Carriage',
    'Shoe Boxes & Shopping Bags',
    'Tea & Staff Refreshment',
    'Shop Maintenance & Cleaning',
    'Marketing & Banner Prints',
    'Miscellaneous'
];

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-receipt text-primary me-2"></i>Daily Shop Expenses
            </h2>
            <p class="text-muted small mb-0">Record shop rent, electricity, salaries, transport, tea, and operational overheads</p>
        </div>
        <div class="d-flex gap-2">
            <button class="btn btn-primary fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                <i class="fa-solid fa-plus-circle me-1"></i> Add Expense
            </button>
            <a href="reports.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-chart-line me-1"></i> Profit &amp; Loss Report
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
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Today's Expenses</span>
                <h3 class="fw-bold text-danger mt-2 mb-0"><?= format_currency($today_exp) ?></h3>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <span class="text-muted small fw-semibold text-uppercase">Month Total (<?= date('F Y', strtotime($month . '-01')) ?>)</span>
                <h3 class="fw-bold text-dark mt-2 mb-0"><?= format_currency($month_total) ?></h3>
            </div>
        </div>
        <div class="col-md-12 col-xl-6">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white">
                <form method="GET" action="expenses.php" class="row g-2 align-items-center">
                    <div class="col-sm-5">
                        <label class="small text-muted fw-semibold">Month:</label>
                        <input type="month" name="month" class="form-control form-control-sm" value="<?= clean($month) ?>">
                    </div>
                    <div class="col-sm-5">
                        <label class="small text-muted fw-semibold">Category:</label>
                        <select name="category" class="form-select form-select-sm">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= clean($cat) ?>" <?= $cat_filter === $cat ? 'selected' : '' ?>><?= clean($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-sm-2 d-flex align-items-end pt-3">
                        <button type="submit" class="btn btn-sm btn-primary w-100">Filter</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Expenses Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Date</th>
                        <th>Category</th>
                        <th>Amount</th>
                        <th>Payment Mode</th>
                        <th>Description / Details</th>
                        <th>Recorded By</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($expenses)): ?>
                        <tr><td colspan="6" class="text-center py-5 text-muted">No expenses found for selected period.</td></tr>
                    <?php else: ?>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td class="text-muted"><?= date('d M Y', strtotime($exp['expense_date'])) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= clean($exp['category']) ?></span>
                                </td>
                                <td class="fw-bold text-danger fs-6"><?= format_currency($exp['amount']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border text-uppercase"><?= clean($exp['payment_method']) ?></span>
                                </td>
                                <td><?= clean($exp['description'] ?? '-') ?></td>
                                <td class="small text-muted"><?= clean($exp['user_name'] ?? 'Staff') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal: Add Expense -->
<div class="modal fade" id="addExpenseModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Record Shop Expense</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="expenses.php">
                <input type="hidden" name="add_expense" value="1">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Expense Category *</label>
                        <select name="category" class="form-select" required>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= clean($cat) ?>"><?= clean($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold" required placeholder="0.00">
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label small fw-bold">Payment Method</label>
                            <select name="payment_method" class="form-select">
                                <option value="cash">Cash (Drawer)</option>
                                <option value="bank">Bank Transfer</option>
                                <option value="jazzcash">JazzCash</option>
                                <option value="easypaisa">Easypaisa</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label small fw-bold">Expense Date</label>
                            <input type="date" name="expense_date" class="form-control" value="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Description / Purpose</label>
                        <textarea name="description" class="form-control" rows="2" placeholder="e.g. Paid LESCO electricity bill, morning refreshment for staff"></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Save Expense</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
