<?php
// return_create.php - Process Shoe Size Exchange or Return/Refund
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';
$success = '';

$search_invoice = trim($_GET['invoice_no'] ?? $_POST['invoice_no'] ?? '');
$sale = null;
$sale_items = [];

// 1. If invoice provided, fetch it
if (!empty($search_invoice)) {
    $stmt = $db->prepare("SELECT s.*, c.name as customer_name, c.phone as customer_phone 
                          FROM sales s 
                          LEFT JOIN customers c ON s.customer_id = c.id 
                          WHERE s.invoice_no = ? AND s.status = 'completed'");
    $stmt->execute([$search_invoice]);
    $sale = $stmt->fetch();

    if ($sale) {
        $stmt = $db->prepare("SELECT si.*, pv.barcode, p.article_code 
                              FROM sale_items si 
                              JOIN product_variants pv ON si.variant_id = pv.id 
                              JOIN products p ON si.product_id = p.id 
                              WHERE si.sale_id = ?");
        $stmt->execute([$sale['id']]);
        $sale_items = $stmt->fetchAll();

        // Check return policy days
        $policy_days = (int)get_setting('return_policy_days', 7);
        $sale_date = new DateTime($sale['sale_date']);
        $today = new DateTime();
        $days_diff = $today->diff($sale_date)->days;
        if ($days_diff > $policy_days) {
            $error = "Warning: This invoice is {$days_diff} days old. The standard return/exchange policy is {$policy_days} days.";
        }
    } else {
        $error = "Invoice '{$search_invoice}' not found or already cancelled.";
    }
}

// 2. Process Return / Exchange Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_action'])) {
    $sale_id = (int)$_POST['sale_id'];
    $operation_type = clean($_POST['operation_type'] ?? 'exchange'); // 'return' or 'exchange'
    $item_id = (int)$_POST['return_item_id'];
    $return_qty = (int)($_POST['return_qty'] ?? 1);
    $condition = clean($_POST['condition'] ?? 'resalable'); // resalable or defective_damaged
    $notes = trim($_POST['notes'] ?? '');

    // Fetch the original sale item
    $stmt = $db->prepare("SELECT * FROM sale_items WHERE id = ? AND sale_id = ?");
    $stmt->execute([$item_id, $sale_id]);
    $orig_item = $stmt->fetch();

    if (!$orig_item) {
        $error = "Original sale item not found.";
    } elseif ($return_qty <= 0 || $return_qty > $orig_item['quantity']) {
        $error = "Invalid return quantity. Maximum refundable pairs for this item is {$orig_item['quantity']}.";
    } else {
        $unit_price = (float)$orig_item['unit_price'];
        $returned_total = $unit_price * $return_qty;
        $new_items_total = 0.00;
        $difference_amount = 0.00;
        $new_variant_id = 0;

        if ($operation_type === 'exchange') {
            $new_variant_id = (int)($_POST['new_variant_id'] ?? 0);
            if ($new_variant_id <= 0) {
                $error = "Please select the new shoe size/variant for exchange.";
            } else {
                // Fetch new variant price and check stock
                $v_stmt = $db->prepare("SELECT * FROM product_variants WHERE id = ?");
                $v_stmt->execute([$new_variant_id]);
                $new_var = $v_stmt->fetch();

                if (!$new_var) {
                    $error = "Selected exchange variant not found.";
                } elseif ($new_var['stock_qty'] < $return_qty) {
                    $error = "Insufficient stock for exchange! Only {$new_var['stock_qty']} pairs available in the selected size.";
                } else {
                    $new_unit_price = (float)$new_var['selling_price'];
                    $new_items_total = $new_unit_price * $return_qty;
                    // Positive if customer pays extra, negative if store refunds
                    $difference_amount = $new_items_total - $returned_total;
                }
            }
        } else {
            // Direct return / refund: difference is negative of returned total
            $difference_amount = -$returned_total;
        }

        if (empty($error)) {
            $db->beginTransaction();
            try {
                $return_no = 'RET-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
                $refund_method = clean($_POST['settlement_method'] ?? 'cash');

                // 1. Insert Return Record
                $stmt = $db->prepare("INSERT INTO returns_exchanges (return_no, sale_id, customer_id, user_id, type, return_date, returned_items_total, new_items_total, difference_amount, refund_method, notes) 
                                      VALUES (?, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $return_no, $sale_id, $sale['customer_id'], $user_id,
                    $operation_type, $returned_total, $new_items_total, $difference_amount,
                    $refund_method, $notes
                ]);
                $return_id = $db->lastInsertId();

                // 2. Insert Return Item
                $stmt = $db->prepare("INSERT INTO return_items (return_id, original_sale_item_id, variant_id, quantity, unit_price, refund_amount, condition_status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $return_id, $orig_item['id'], $orig_item['variant_id'], $return_qty,
                    $unit_price, $returned_total, $condition
                ]);

                // 3. Update Old Variant Stock
                if ($condition === 'resalable') {
                    // Restock available pairs
                    $db->prepare("UPDATE product_variants SET stock_qty = stock_qty + ? WHERE id = ?")
                       ->execute([$return_qty, $orig_item['variant_id']]);
                } else {
                    // Mark as damaged
                    $db->prepare("UPDATE product_variants SET damaged_qty = damaged_qty + ? WHERE id = ?")
                       ->execute([$return_qty, $orig_item['variant_id']]);
                }

                // 4. If Exchange, record new item and deduct new variant stock
                if ($operation_type === 'exchange') {
                    $stmt = $db->prepare("INSERT INTO return_exchange_new_items (return_id, new_variant_id, quantity, unit_price, total_amount) 
                                          VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$return_id, $new_variant_id, $return_qty, $new_var['selling_price'], $new_items_total]);

                    // Deduct stock of new pair
                    $db->prepare("UPDATE product_variants SET stock_qty = stock_qty - ? WHERE id = ?")
                       ->execute([$return_qty, $new_variant_id]);
                }

                // 5. Drawer adjustment
                $active_reg = get_active_register($user_id);
                if ($active_reg) {
                    if ($difference_amount < 0 && $refund_method === 'cash') {
                        // Store paid cash refund to customer
                        $db->prepare("UPDATE cash_registers SET cash_out = cash_out + ? WHERE id = ?")
                           ->execute([abs($difference_amount), $active_reg['id']]);
                    } elseif ($difference_amount > 0 && $refund_method === 'cash') {
                        // Customer paid difference in cash
                        $db->prepare("UPDATE cash_registers SET cash_sales = cash_sales + ? WHERE id = ?")
                           ->execute([$difference_amount, $active_reg['id']]);
                    }
                }

                log_audit('RETURN_EXCHANGE', 'RETURNS', $return_id, "Processed {$operation_type} #{$return_no} for Inv {$sale['invoice_no']}. Diff: {$difference_amount}");

                $db->commit();
                set_flash('success', "Return / Size Exchange #{$return_no} processed successfully!");
                header("Location: returns.php");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error processing return: " . $e->getMessage();
            }
        }
    }
}

// Fetch all variants for exchange selection dropdown
$exchange_variants = $db->query("SELECT pv.id, pv.selling_price, pv.stock_qty, p.name as product_name, p.article_code, sz.size_number, cl.name as color_name 
                                 FROM product_variants pv 
                                 JOIN products p ON pv.product_id = p.id 
                                 JOIN sizes sz ON pv.size_id = sz.id 
                                 JOIN colors cl ON pv.color_id = cl.id 
                                 WHERE pv.stock_qty > 0 
                                 ORDER BY p.article_code ASC, sz.size_number ASC")->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-arrow-rotate-left text-primary me-2"></i>New Return or Size/Color Exchange
            </h2>
            <p class="text-muted small mb-0">Search the original customer invoice to validate return days and size exchange</p>
        </div>
        <a href="returns.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Returns
        </a>
    </div>

    <!-- Step 1: Search Invoice -->
    <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
        <h5 class="fw-bold text-dark mb-3">1. Search Original Invoice</h5>
        <form method="GET" action="return_create.php" class="row g-2 align-items-center">
            <div class="col-md-9">
                <div class="input-group input-group-lg">
                    <span class="input-group-text bg-light"><i class="fa-solid fa-receipt text-muted"></i></span>
                    <input type="text" name="invoice_no" class="form-control" placeholder="Enter Invoice Number (e.g. INV-20261025-001)..." value="<?= clean($search_invoice) ?>" required autofocus>
                </div>
            </div>
            <div class="col-md-3">
                <button type="submit" class="btn btn-primary btn-lg w-100 fw-bold">
                    <i class="fa-solid fa-magnifying-glass me-1"></i> Find Invoice
                </button>
            </div>
        </form>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-warning shadow-sm mb-4"><i class="fa-solid fa-circle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>

    <!-- Step 2: If invoice found, show items and exchange form -->
    <?php if ($sale && !empty($sale_items)): ?>
        <form method="POST" action="return_create.php" id="return_form">
            <input type="hidden" name="process_action" value="1">
            <input type="hidden" name="sale_id" value="<?= $sale['id'] ?>">
            <input type="hidden" name="invoice_no" value="<?= clean($sale['invoice_no']) ?>">

            <!-- Invoice Overview -->
            <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
                <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-3">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">Invoice: <?= clean($sale['invoice_no']) ?></h5>
                        <span class="small text-muted">Date: <?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?> &bull; Customer: <strong><?= clean($sale['customer_name'] ?? 'Walk-in') ?></strong></span>
                    </div>
                    <span class="badge bg-success fs-6">Total Bill: <?= format_currency($sale['grand_total']) ?></span>
                </div>

                <!-- Select Item to Return -->
                <label class="form-label small fw-bold text-secondary mb-2">Select Item from Bill to Return or Exchange:</label>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle mb-3">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px;">Select</th>
                                <th>Shoe Model &amp; Article</th>
                                <th class="text-center">Size</th>
                                <th class="text-center">Color</th>
                                <th class="text-center">Qty Bought</th>
                                <th class="text-end">Unit Price</th>
                                <th class="text-end">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sale_items as $index => $it): ?>
                                <tr>
                                    <td class="text-center">
                                        <input type="radio" name="return_item_id" value="<?= $it['id'] ?>" 
                                               data-price="<?= $it['unit_price'] ?>" data-max="<?= $it['quantity'] ?>" 
                                               class="form-check-input item-radio" <?= $index === 0 ? 'checked' : '' ?>>
                                    </td>
                                    <td>
                                        <strong><?= clean($it['product_name']) ?></strong>
                                        <div class="small text-muted">Art: <?= clean($it['article_code']) ?></div>
                                    </td>
                                    <td class="text-center fw-bold fs-6"><?= clean($it['size_number']) ?></td>
                                    <td class="text-center"><?= clean($it['color_name']) ?></td>
                                    <td class="text-center fw-bold"><?= $it['quantity'] ?></td>
                                    <td class="text-end">Rs. <?= number_format($it['unit_price'], 2) ?></td>
                                    <td class="text-end fw-bold">Rs. <?= number_format($it['total'], 2) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary">Return Quantity (Pairs):</label>
                        <input type="number" name="return_qty" id="return_qty" class="form-control" value="1" min="1" max="10">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary">Operation Type:</label>
                        <select name="operation_type" id="operation_type" class="form-select">
                            <option value="exchange">Size / Color Exchange (Swap for another pair)</option>
                            <option value="return">Direct Return &amp; Refund</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-secondary">Returned Shoe Condition:</label>
                        <select name="condition" class="form-select">
                            <option value="resalable">Resalable / Good Condition (Restock to Inventory)</option>
                            <option value="defective_damaged">Defective / Damaged (Send to Defective Stock)</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Step 3: Exchange Selection & Difference Settlement -->
            <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
                <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">Exchange &amp; Price Settlement</h5>

                <!-- New Shoe Variant Selector (Shown when exchange selected) -->
                <div id="exchange_block" class="mb-4">
                    <label class="form-label small fw-bold text-secondary">Pick New Shoe Model &amp; Desired Size to Exchange *</label>
                    <select name="new_variant_id" id="new_variant_id" class="form-select form-select-lg">
                        <option value="">-- Choose New Shoe Model &amp; Size --</option>
                        <?php foreach ($exchange_variants as $ev): ?>
                            <option value="<?= $ev['id'] ?>" data-price="<?= $ev['selling_price'] ?>">
                                <?= clean($ev['article_code']) ?> &mdash; <?= clean($ev['product_name']) ?> (Size <?= clean($ev['size_number']) ?>, <?= clean($ev['color_name']) ?>) &mdash; Rs. <?= number_format($ev['selling_price'], 0) ?> [Available: <?= $ev['stock_qty'] ?> pairs]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Difference Calculation Box -->
                <div class="bg-light p-4 rounded-3 border mb-3">
                    <div class="row align-items-center">
                        <div class="col-md-4">
                            <span class="text-muted small">Returned Item Credit:</span>
                            <h4 class="fw-bold text-danger mb-0" id="returned_credit_display">Rs. 0.00</h4>
                        </div>
                        <div class="col-md-4 border-start">
                            <span class="text-muted small">New Exchange Item Price:</span>
                            <h4 class="fw-bold text-success mb-0" id="new_price_display">Rs. 0.00</h4>
                        </div>
                        <div class="col-md-4 border-start">
                            <span class="text-muted small">Net Settlement Difference:</span>
                            <h3 class="fw-bold text-primary mb-0" id="net_diff_display">Rs. 0.00</h3>
                        </div>
                    </div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Settlement Method (Refund / Payment)</label>
                        <select name="settlement_method" class="form-select">
                            <option value="cash">Cash (Drawer)</option>
                            <option value="jazzcash">JazzCash</option>
                            <option value="easypaisa">Easypaisa</option>
                            <option value="bank">Bank</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-secondary">Exchange / Return Reason &amp; Notes</label>
                        <input type="text" name="notes" class="form-control" placeholder="e.g. Size 40 was tight, customer exchanged for Size 41">
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top text-end">
                    <button type="submit" class="btn btn-primary px-5 py-3 fw-bold shadow-sm">
                        <i class="fa-solid fa-check me-2"></i> Confirm Exchange &amp; Update Inventory
                    </button>
                </div>
            </div>
        </form>
    <?php endif; ?>
</div>

<script>
function recalcReturn() {
    let checkedRadio = document.querySelector('.item-radio:checked');
    if (!checkedRadio) return;

    let origPrice = parseFloat(checkedRadio.getAttribute('data-price')) || 0;
    let qty = parseInt(document.getElementById('return_qty').value) || 1;
    let returnedTotal = origPrice * qty;

    let opType = document.getElementById('operation_type').value;
    let newPrice = 0;

    if (opType === 'exchange') {
        document.getElementById('exchange_block').style.display = 'block';
        let newSelect = document.getElementById('new_variant_id');
        if (newSelect.selectedIndex > 0) {
            let opt = newSelect.options[newSelect.selectedIndex];
            let nPrice = parseFloat(opt.getAttribute('data-price')) || 0;
            newPrice = nPrice * qty;
        }
    } else {
        document.getElementById('exchange_block').style.display = 'none';
        newPrice = 0;
    }

    let diff = newPrice - returnedTotal;

    document.getElementById('returned_credit_display').innerText = 'Rs. ' + returnedTotal.toFixed(2);
    document.getElementById('new_price_display').innerText = 'Rs. ' + newPrice.toFixed(2);

    if (diff > 0) {
        document.getElementById('net_diff_display').innerText = 'Customer Pays: Rs. ' + diff.toFixed(2);
        document.getElementById('net_diff_display').className = 'fw-bold text-danger mb-0';
    } else if (diff < 0) {
        document.getElementById('net_diff_display').innerText = 'Store Refunds: Rs. ' + Math.abs(diff).toFixed(2);
        document.getElementById('net_diff_display').className = 'fw-bold text-success mb-0';
    } else {
        document.getElementById('net_diff_display').innerText = 'Even Swap (Rs. 0.00)';
        document.getElementById('net_diff_display').className = 'fw-bold text-primary mb-0';
    }
}

document.querySelectorAll('.item-radio').forEach(r => r.addEventListener('change', recalcReturn));
if (document.getElementById('return_qty')) document.getElementById('return_qty').addEventListener('input', recalcReturn);
if (document.getElementById('operation_type')) document.getElementById('operation_type').addEventListener('change', recalcReturn);
if (document.getElementById('new_variant_id')) document.getElementById('new_variant_id').addEventListener('change', recalcReturn);

document.addEventListener('DOMContentLoaded', recalcReturn);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
