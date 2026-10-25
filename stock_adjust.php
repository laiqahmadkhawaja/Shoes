<?php
// stock_adjust.php - Inventory Stock Adjustments & Physical Audit
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin', 'manager', 'inventory']);

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Adjustment Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $variant_id = (int)($_POST['variant_id'] ?? 0);
    $type = clean($_POST['type'] ?? 'in'); // in, out, damaged, physical_audit
    $quantity = (int)($_POST['quantity'] ?? 0);
    $reason = trim($_POST['reason'] ?? '');
    $notes = trim($_POST['notes'] ?? '');

    if ($variant_id <= 0) {
        $error = "Please select a shoe variant.";
    } elseif ($type !== 'physical_audit' && $quantity <= 0) {
        $error = "Quantity must be greater than zero.";
    } else {
        // Fetch current variant
        $stmt = $db->prepare("SELECT pv.*, p.name as product_name, p.article_code, sz.size_number, cl.name as color_name 
                              FROM product_variants pv 
                              JOIN products p ON pv.product_id = p.id 
                              JOIN sizes sz ON pv.size_id = sz.id 
                              JOIN colors cl ON pv.color_id = cl.id 
                              WHERE pv.id = ?");
        $stmt->execute([$variant_id]);
        $variant = $stmt->fetch();

        if (!$variant) {
            $error = "Variant not found.";
        } else {
            $old_qty = (int)$variant['stock_qty'];
            $new_qty = $old_qty;
            $diff_qty = 0;

            if ($type === 'in') {
                $new_qty = $old_qty + $quantity;
                $diff_qty = +$quantity;
            } elseif ($type === 'out') {
                if ($quantity > $old_qty) {
                    $error = "Cannot remove {$quantity} pairs. Current stock is only {$old_qty} pairs.";
                } else {
                    $new_qty = $old_qty - $quantity;
                    $diff_qty = -$quantity;
                }
            } elseif ($type === 'damaged') {
                if ($quantity > $old_qty) {
                    $error = "Cannot mark {$quantity} pairs as damaged. Current stock is only {$old_qty} pairs.";
                } else {
                    $new_qty = $old_qty - $quantity;
                    $diff_qty = -$quantity;
                    // Also increase damaged_qty
                    $db->prepare("UPDATE product_variants SET damaged_qty = damaged_qty + ? WHERE id = ?")->execute([$quantity, $variant_id]);
                }
            } elseif ($type === 'physical_audit') {
                $new_qty = (int)$_POST['physical_count_qty'];
                $diff_qty = $new_qty - $old_qty;
            }

            if (empty($error)) {
                $db->beginTransaction();
                try {
                    $adj_no = 'ADJ-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

                    $stmt = $db->prepare("INSERT INTO stock_adjustments (adjustment_no, user_id, adjustment_date, type, reason, notes) 
                                          VALUES (?, ?, NOW(), ?, ?, ?)");
                    $stmt->execute([$adj_no, $user_id, $type, $reason, $notes]);
                    $adj_id = $db->lastInsertId();

                    $stmt = $db->prepare("INSERT INTO stock_adjustment_items (adjustment_id, variant_id, old_qty, new_qty, diff_qty, unit_cost) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$adj_id, $variant_id, $old_qty, $new_qty, $diff_qty, $variant['purchase_price']]);

                    // Update variant
                    $stmt = $db->prepare("UPDATE product_variants SET stock_qty = ? WHERE id = ?");
                    $stmt->execute([$new_qty, $variant_id]);

                    log_audit('STOCK_ADJUST', 'INVENTORY', $adj_id, "Adjusted {$variant['article_code']} Size {$variant['size_number']} ({$variant['color_name']}): Old {$old_qty} -> New {$new_qty}. Reason: {$reason}");

                    $db->commit();
                    $success = "Stock adjustment #{$adj_no} recorded successfully! New stock for {$variant['article_code']} Size {$variant['size_number']} is {$new_qty} pairs.";
                } catch (Exception $e) {
                    $db->rollBack();
                    $error = "Error saving adjustment: " . $e->getMessage();
                }
            }
        }
    }
}

// Fetch all variants for dropdown
$variants = $db->query("SELECT pv.id, pv.barcode, pv.stock_qty, p.name as product_name, p.article_code, sz.size_number, cl.name as color_name 
                        FROM product_variants pv 
                        JOIN products p ON pv.product_id = p.id 
                        JOIN sizes sz ON pv.size_id = sz.id 
                        JOIN colors cl ON pv.color_id = cl.id 
                        ORDER BY p.article_code ASC, sz.size_number ASC")->fetchAll();

// Recent adjustments history
$recent_adjustments = $db->query("SELECT sa.*, sai.old_qty, sai.new_qty, sai.diff_qty, 
                                         p.article_code, p.name as product_name, sz.size_number, cl.name as color_name,
                                         u.username
                                  FROM stock_adjustments sa
                                  JOIN stock_adjustment_items sai ON sa.id = sai.adjustment_id
                                  JOIN product_variants pv ON sai.variant_id = pv.id
                                  JOIN products p ON pv.product_id = p.id
                                  JOIN sizes sz ON pv.size_id = sz.id
                                  JOIN colors cl ON pv.color_id = cl.id
                                  LEFT JOIN users u ON sa.user_id = u.id
                                  ORDER BY sa.id DESC LIMIT 15")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-sliders text-primary me-2"></i>Stock Adjustment &amp; Physical Audit
            </h2>
            <p class="text-muted small mb-0">Record physical stocktaking corrections, damaged shoes, or sample removals</p>
        </div>
        <a href="inventory.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Size Matrix
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= clean($success) ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Adjustment Form -->
        <div class="col-lg-5">
            <div class="card border-0 shadow-sm rounded-3 p-4 bg-white">
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">Record Adjustment</h5>
                <form method="POST" action="stock_adjust.php">
                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Select Shoe &amp; Variant *</label>
                        <select name="variant_id" id="variant_select" class="form-select" required>
                            <option value="">-- Choose Shoe, Size &amp; Color --</option>
                            <?php foreach ($variants as $v): ?>
                                <option value="<?= $v['id'] ?>" data-stock="<?= $v['stock_qty'] ?>">
                                    <?= clean($v['article_code']) ?> &mdash; <?= clean($v['product_name']) ?> (Size <?= clean($v['size_number']) ?>, <?= clean($v['color_name']) ?>) [Stock: <?= $v['stock_qty'] ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Adjustment Type *</label>
                        <select name="type" id="adj_type" class="form-select" required>
                            <option value="in">Stock In (+) &mdash; Found extra stock / Opening balance</option>
                            <option value="out">Stock Out (-) &mdash; Sample pair / Loss / Missing</option>
                            <option value="damaged">Move to Damaged / Defective Stock (-)</option>
                            <option value="physical_audit">Physical Audit Correction (= Set exact counted count)</option>
                        </select>
                    </div>

                    <div id="qty_relative_block" class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Quantity to Adjust (Pairs) *</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1">
                    </div>

                    <div id="qty_audit_block" class="mb-3" style="display: none;">
                        <label class="form-label small fw-bold text-secondary">Actual Physical Pairs Counted *</label>
                        <input type="number" name="physical_count_qty" id="physical_count_qty" class="form-control" value="0" min="0">
                        <div class="form-text">System stock will be updated to match this exact number.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-bold text-secondary">Reason *</label>
                        <input type="text" name="reason" class="form-control" placeholder="e.g. Month-end physical stock audit, display rack damage" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-bold text-secondary">Additional Notes</label>
                        <textarea name="notes" class="form-control" rows="2" placeholder="Optional notes..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Apply Stock Adjustment
                    </button>
                </form>
            </div>
        </div>

        <!-- Recent Adjustments History -->
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm rounded-3 bg-white">
                <div class="card-header bg-white py-3 border-bottom">
                    <h5 class="fw-bold text-dark mb-0">Recent Stock Adjustments Log</h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0 small">
                        <thead class="table-light">
                            <tr>
                                <th>Ref #</th>
                                <th>Date</th>
                                <th>Shoe &amp; Size</th>
                                <th>Type</th>
                                <th class="text-center">Old &rarr; New</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_adjustments)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No adjustments logged yet.</td></tr>
                            <?php else: ?>
                                <?php foreach ($recent_adjustments as $ra): ?>
                                    <tr>
                                        <td class="fw-bold"><?= clean($ra['adjustment_no']) ?></td>
                                        <td class="text-muted"><?= date('d M, h:i A', strtotime($ra['adjustment_date'])) ?></td>
                                        <td>
                                            <div class="fw-semibold text-dark"><?= clean($ra['article_code']) ?> (Sz <?= clean($ra['size_number']) ?>)</div>
                                            <div class="text-muted" style="font-size:0.75rem;"><?= clean($ra['color_name']) ?></div>
                                        </td>
                                        <td>
                                            <?php if ($ra['type'] === 'in'): ?>
                                                <span class="badge bg-success">Stock In</span>
                                            <?php elseif ($ra['type'] === 'out'): ?>
                                                <span class="badge bg-danger">Stock Out</span>
                                            <?php elseif ($ra['type'] === 'damaged'): ?>
                                                <span class="badge bg-warning text-dark">Damaged</span>
                                            <?php else: ?>
                                                <span class="badge bg-info text-dark">Audit</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center fw-bold">
                                            <?= $ra['old_qty'] ?> &rarr; <?= $ra['new_qty'] ?>
                                            <span class="small <?= $ra['diff_qty'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                                (<?= $ra['diff_qty'] > 0 ? "+{$ra['diff_qty']}" : $ra['diff_qty'] ?>)
                                            </span>
                                        </td>
                                        <td><?= clean($ra['reason']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('adj_type').addEventListener('change', function() {
    if (this.value === 'physical_audit') {
        document.getElementById('qty_relative_block').style.display = 'none';
        document.getElementById('qty_audit_block').style.display = 'block';
    } else {
        document.getElementById('qty_relative_block').style.display = 'block';
        document.getElementById('qty_audit_block').style.display = 'none';
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
