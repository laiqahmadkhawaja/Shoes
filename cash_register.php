<?php
// cash_register.php - Daily Cash Drawer & Shift Management
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Check active register
$active_reg = get_active_register($user_id);

// Handle Open Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['open_register'])) {
    $opening_amount = (float)($_POST['opening_amount'] ?? 0);
    $notes = trim($_POST['notes'] ?? 'Morning Shift Opening Cash');

    if ($active_reg) {
        $error = "You already have an open cash drawer session!";
    } else {
        $stmt = $db->prepare("INSERT INTO cash_registers (user_id, opening_time, opening_amount, cash_sales, cash_in, cash_out, status, notes) 
                              VALUES (?, NOW(), ?, 0.00, 0.00, 0.00, 'open', ?)");
        $stmt->execute([$user_id, $opening_amount, $notes]);
        $reg_id = $db->lastInsertId();
        log_audit('DRAWER_OPEN', 'REGISTER', $reg_id, "Opened drawer with Rs. {$opening_amount}");
        set_flash('success', "Cash drawer opened with Rs. " . number_format($opening_amount, 2));
        header("Location: cash_register.php");
        exit;
    }
}

// Handle Cash In / Cash Out (Manual entry)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cash_movement'])) {
    $type = clean($_POST['movement_type'] ?? 'in'); // 'in' or 'out'
    $amount = (float)$_POST['amount'];
    $reason = trim($_POST['reason'] ?? '');

    if (!$active_reg) {
        $error = "No open cash drawer. Please open drawer first.";
    } elseif ($amount <= 0) {
        $error = "Amount must be greater than zero.";
    } else {
        if ($type === 'in') {
            $db->prepare("UPDATE cash_registers SET cash_in = cash_in + ? WHERE id = ?")
               ->execute([$amount, $active_reg['id']]);
            log_audit('CASH_IN', 'REGISTER', $active_reg['id'], "Cash in of Rs. {$amount}: {$reason}");
            $success = "Cash In of Rs. " . number_format($amount, 2) . " added to drawer.";
        } else {
            $db->prepare("UPDATE cash_registers SET cash_out = cash_out + ? WHERE id = ?")
               ->execute([$amount, $active_reg['id']]);
            log_audit('CASH_OUT', 'REGISTER', $active_reg['id'], "Cash out of Rs. {$amount}: {$reason}");
            $success = "Cash Out of Rs. " . number_format($amount, 2) . " deducted from drawer.";
        }
        $active_reg = get_active_register($user_id); // refresh
    }
}

// Handle Close Register & Reconcile
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['close_register'])) {
    $actual_amount = (float)$_POST['closing_actual_amount'];
    $closing_notes = trim($_POST['closing_notes'] ?? '');

    if (!$active_reg) {
        $error = "No open cash drawer to close.";
    } else {
        // Expected amount in drawer = opening + cash_sales + cash_in - cash_out
        $expected = (float)$active_reg['opening_amount'] + (float)$active_reg['cash_sales'] + (float)$active_reg['cash_in'] - (float)$active_reg['cash_out'];
        $diff = $actual_amount - $expected;

        $stmt = $db->prepare("UPDATE cash_registers SET closing_time = NOW(), closing_amount = ?, expected_amount = ?, difference = ?, notes = CONCAT(COALESCE(notes, ''), ' | Closing: ', ?), status = 'closed' WHERE id = ?");
        $stmt->execute([$actual_amount, $expected, $diff, $closing_notes, $active_reg['id']]);

        log_audit('DRAWER_CLOSE', 'REGISTER', $active_reg['id'], "Closed drawer. Expected: {$expected}, Counted: {$actual_amount}, Diff: {$diff}");
        set_flash('success', "Drawer closed successfully! Expected: Rs. " . number_format($expected, 2) . ", Counted: Rs. " . number_format($actual_amount, 2) . ", Difference: Rs. " . number_format($diff, 2));
        header("Location: cash_register.php");
        exit;
    }
}

// History of previous registers
$past_registers = $db->query("SELECT cr.*, u.full_name as cashier_name 
                              FROM cash_registers cr 
                              LEFT JOIN users u ON cr.user_id = u.id 
                              ORDER BY cr.id DESC LIMIT 10")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-vault text-primary me-2"></i>Cash Drawer &amp; Daily Reconciliation
            </h2>
            <p class="text-muted small mb-0">Track shift opening balance, cash sales, manual cash movements, and cash reconciliation</p>
        </div>
        <a href="pos.php" class="btn btn-primary fw-bold shadow-sm">
            <i class="fa-solid fa-cash-register me-1"></i> Open POS Billing
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= clean($success) ?></div>
    <?php endif; ?>

    <!-- Current Shift Card -->
    <?php if ($active_reg): 
        $expected = (float)$active_reg['opening_amount'] + (float)$active_reg['cash_sales'] + (float)$active_reg['cash_in'] - (float)$active_reg['cash_out'];
    ?>
        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center pb-3 border-bottom mb-3">
                <div>
                    <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-lock-open me-1"></i> Cash Drawer is ACTIVE</span>
                    <span class="text-muted small ms-2">Opened: <?= date('d M Y, h:i A', strtotime($active_reg['opening_time'])) ?></span>
                </div>
                <div class="d-flex gap-2">
                    <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#cashMovementModal">
                        <i class="fa-solid fa-arrows-up-down me-1"></i> Cash In / Out
                    </button>
                    <button class="btn btn-danger btn-sm fw-bold" data-bs-toggle="modal" data-bs-target="#closeDrawerModal">
                        <i class="fa-solid fa-lock me-1"></i> Close Drawer &amp; Reconcile
                    </button>
                </div>
            </div>

            <div class="row g-3">
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small fw-semibold">Opening Float</span>
                        <h4 class="fw-bold text-dark mt-1 mb-0"><?= format_currency($active_reg['opening_amount']) ?></h4>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small fw-semibold">Cash Sales Received</span>
                        <h4 class="fw-bold text-success mt-1 mb-0"><?= format_currency($active_reg['cash_sales']) ?></h4>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-light rounded-3 border">
                        <span class="text-muted small fw-semibold">Cash Movements (In - Out)</span>
                        <h4 class="fw-bold text-secondary mt-1 mb-0">
                            +<?= number_format($active_reg['cash_in'], 0) ?> / -<?= number_format($active_reg['cash_out'], 0) ?>
                        </h4>
                    </div>
                </div>
                <div class="col-sm-6 col-lg-3">
                    <div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary">
                        <span class="text-primary small fw-bold text-uppercase">Expected in Drawer Now</span>
                        <h3 class="fw-bold text-primary mt-1 mb-0"><?= format_currency($expected) ?></h3>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Open Drawer Prompt -->
        <div class="card border-0 shadow-sm rounded-3 p-5 bg-white mb-4 text-center">
            <div class="d-inline-flex align-items-center justify-content-center bg-warning bg-opacity-10 text-warning rounded-circle mx-auto mb-3" style="width: 72px; height: 72px; font-size: 32px;">
                <i class="fa-solid fa-lock"></i>
            </div>
            <h4 class="fw-bold text-dark mb-1">Cash Drawer is Currently Closed</h4>
            <p class="text-muted small mb-4">Please count physical cash in register and enter opening balance to begin sales shift</p>
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <form method="POST" action="cash_register.php" class="card p-3 border">
                        <input type="hidden" name="open_register" value="1">
                        <label class="form-label small fw-bold text-secondary text-start">Opening Cash in Drawer (Rs.) *</label>
                        <input type="number" step="0.01" name="opening_amount" class="form-control form-control-lg fw-bold mb-3" placeholder="e.g. 5000.00" value="5000.00" required>
                        <input type="text" name="notes" class="form-control form-control-sm mb-3" placeholder="Shift notes (e.g. Morning Shift)">
                        <button type="submit" class="btn btn-primary py-2 fw-bold">
                            <i class="fa-solid fa-key me-1"></i> Open Drawer Session
                        </button>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Drawer History Log -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="fw-bold text-dark mb-0">Previous Shifts History &amp; Reconciliation</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Opened Time</th>
                        <th>Closed Time</th>
                        <th>Cashier</th>
                        <th>Opening Cash</th>
                        <th>Cash Sales</th>
                        <th>Expected Drawer</th>
                        <th>Actual Counted</th>
                        <th>Difference</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($past_registers)): ?>
                        <tr><td colspan="9" class="text-center py-4 text-muted">No register history found.</td></tr>
                    <?php else: ?>
                        <?php foreach ($past_registers as $pr): ?>
                            <tr>
                                <td><?= date('d M Y, h:i A', strtotime($pr['opening_time'])) ?></td>
                                <td><?= $pr['closing_time'] ? date('d M Y, h:i A', strtotime($pr['closing_time'])) : '<span class="badge bg-success">Active Now</span>' ?></td>
                                <td><?= clean($pr['cashier_name'] ?? 'Staff') ?></td>
                                <td><?= format_currency($pr['opening_amount']) ?></td>
                                <td class="text-success fw-semibold"><?= format_currency($pr['cash_sales']) ?></td>
                                <td><?= $pr['expected_amount'] ? format_currency($pr['expected_amount']) : '-' ?></td>
                                <td class="fw-bold"><?= $pr['closing_amount'] ? format_currency($pr['closing_amount']) : '-' ?></td>
                                <td>
                                    <?php if ($pr['status'] === 'closed'): ?>
                                        <?php if ((float)$pr['difference'] == 0): ?>
                                            <span class="badge bg-success">Balanced (Rs. 0)</span>
                                        <?php elseif ((float)$pr['difference'] > 0): ?>
                                            <span class="badge bg-info text-dark">Over +<?= format_currency($pr['difference']) ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Short <?= format_currency($pr['difference']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        &mdash;
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $pr['status'] === 'open' ? 'bg-success' : 'bg-secondary' ?> text-uppercase">
                                        <?= clean($pr['status']) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal 1: Cash In / Out Movement -->
<div class="modal fade" id="cashMovementModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-light">
                <h5 class="modal-title fw-bold">Manual Cash Movement</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="cash_register.php">
                <input type="hidden" name="cash_movement" value="1">
                <div class="modal-body p-3">
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Movement Type</label>
                        <select name="movement_type" class="form-select">
                            <option value="in">Cash In (+) &mdash; Float addition / Owner injection</option>
                            <option value="out">Cash Out (-) &mdash; Bank deposit / Quick petty cash</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Amount (Rs.) *</label>
                        <input type="number" step="0.01" name="amount" class="form-control form-control-lg fw-bold" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label small fw-bold">Reason / Description *</label>
                        <input type="text" name="reason" class="form-control" required placeholder="e.g. Deposit to bank, change coins">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary fw-bold">Record Movement</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal 2: Close Drawer & Reconcile -->
<?php if ($active_reg): ?>
<div class="modal fade" id="closeDrawerModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-lock me-2"></i>Close Shift &amp; Cash Reconciliation</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="cash_register.php">
                <input type="hidden" name="close_register" value="1">
                <div class="modal-body p-4">
                    <div class="bg-light p-3 rounded-3 text-center border mb-3">
                        <span class="text-muted small fw-semibold text-uppercase">System Expected Cash in Drawer</span>
                        <h2 class="fw-bold text-primary mt-1 mb-0"><?= format_currency($expected) ?></h2>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Physical Counted Cash in Drawer (Rs.) *</label>
                        <input type="number" step="0.01" name="closing_actual_amount" class="form-control form-control-lg fw-bold" value="<?= $expected ?>" required>
                        <div class="form-text">Count all currency notes and coins physically inside drawer.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Closing Notes</label>
                        <input type="text" name="closing_notes" class="form-control" placeholder="e.g. Handover to evening cashier / Rs. 10,000 kept as float">
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger fw-bold">Close Drawer &amp; Save Reconciliation</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
