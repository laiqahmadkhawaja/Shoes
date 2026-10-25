<?php
// purchase_add.php - New Purchase with Assortment Matrix
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin', 'manager', 'inventory']);

$db = getDB();
$user_id = $_SESSION['user_id'];
$error = '';

$suppliers = $db->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
$products = $db->query("SELECT p.*, b.name as brand_name FROM products p LEFT JOIN brands b ON p.brand_id = b.id ORDER BY p.name ASC")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $supplier_id = (int)($_POST['supplier_id'] ?? 0);
    $po_number = trim($_POST['po_number'] ?? '');
    $purchase_date = clean($_POST['purchase_date'] ?? date('Y-m-d'));
    $freight_cost = (float)($_POST['freight_cost'] ?? 0);
    $other_cost = (float)($_POST['other_cost'] ?? 0);
    $paid_amount = (float)($_POST['paid_amount'] ?? 0);
    $payment_method = clean($_POST['payment_method'] ?? 'cash');
    $notes = trim($_POST['notes'] ?? '');

    $quantities = $_POST['qty'] ?? []; // [variant_id => qty]
    $costs = $_POST['cost'] ?? [];       // [variant_id => cost]

    if ($supplier_id <= 0) {
        $error = "Please select a supplier.";
    } elseif (empty($po_number)) {
        $error = "Purchase / Invoice number is required.";
    } else {
        // Calculate subtotal
        $subtotal = 0.00;
        $items_to_save = [];

        foreach ($quantities as $v_id => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $cost = (float)($costs[$v_id] ?? 0);
                $line_total = $qty * $cost;
                $subtotal += $line_total;

                // Fetch product_id
                $stmt = $db->prepare("SELECT product_id FROM product_variants WHERE id = ?");
                $stmt->execute([(int)$v_id]);
                $p_id = (int)$stmt->fetchColumn();

                $items_to_save[] = [
                    'variant_id' => (int)$v_id,
                    'product_id' => $p_id,
                    'quantity' => $qty,
                    'cost' => $cost,
                    'line_total' => $line_total
                ];
            }
        }

        if (empty($items_to_save)) {
            $error = "Please enter received quantities for at least one shoe variant.";
        } else {
            $grand_total = $subtotal + $freight_cost + $other_cost;
            $balance_amount = max(0, $grand_total - $paid_amount);
            $payment_status = ($balance_amount <= 0) ? 'paid' : (($paid_amount > 0) ? 'partial' : 'unpaid');

            try {
                $db->beginTransaction();

                // 1. Insert Purchase
                $stmt = $db->prepare("INSERT INTO purchases (po_number, supplier_id, user_id, purchase_date, subtotal, freight_cost, other_cost, grand_total, paid_amount, balance_amount, payment_status, notes) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $po_number, $supplier_id, $user_id, $purchase_date,
                    $subtotal, $freight_cost, $other_cost, $grand_total,
                    $paid_amount, $balance_amount, $payment_status, $notes
                ]);
                $purchase_id = $db->lastInsertId();

                // 2. Insert Items & Increment Stock
                $item_stmt = $db->prepare("INSERT INTO purchase_items (purchase_id, product_id, variant_id, unit_cost, quantity, total) 
                                           VALUES (?, ?, ?, ?, ?, ?)");
                $stock_stmt = $db->prepare("UPDATE product_variants SET stock_qty = stock_qty + ?, purchase_price = ? WHERE id = ?");

                foreach ($items_to_save as $it) {
                    $item_stmt->execute([
                        $purchase_id, $it['product_id'], $it['variant_id'], $it['cost'], $it['quantity'], $it['line_total']
                    ]);
                    $stock_stmt->execute([$it['quantity'], $it['cost'], $it['variant_id']]);
                }

                // 3. Update Supplier Balance (Khata)
                if ($balance_amount > 0) {
                    $db->prepare("UPDATE suppliers SET current_balance = current_balance + ? WHERE id = ?")
                       ->execute([$balance_amount, $supplier_id]);
                }

                // 4. Record Supplier Payment if paid > 0
                if ($paid_amount > 0) {
                    $stmt = $db->prepare("INSERT INTO supplier_payments (supplier_id, purchase_id, payment_method, amount, payment_date, notes) 
                                          VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$supplier_id, $purchase_id, $payment_method, $paid_amount, $purchase_date, 'Payment upon purchase receipt']);
                }

                log_audit('PURCHASE_CREATE', 'PURCHASES', $purchase_id, "Recorded purchase {$po_number} with " . count($items_to_save) . " variants. Total: {$grand_total}");

                $db->commit();
                set_flash('success', "Purchase Invoice {$po_number} recorded successfully! Stock and supplier balance updated.");
                header("Location: purchases.php");
                exit;
            } catch (Exception $e) {
                $db->rollBack();
                $error = "Error saving purchase: " . $e->getMessage();
            }
        }
    }
}

// Default PO number
$default_po = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-cart-plus text-primary me-2"></i>New Purchase &amp; Assortment Receiving
            </h2>
            <p class="text-muted small mb-0">Receive cartons with mixed sizes and colors directly into shoe stock</p>
        </div>
        <a href="purchases.php" class="btn btn-outline-secondary">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Purchases
        </a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= clean($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="purchase_add.php" id="purchase_form">
        <!-- Purchase Metadata Card -->
        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">Invoice &amp; Supplier Information</h5>
            <div class="row g-3">
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Supplier / Vendor *</label>
                    <select name="supplier_id" class="form-select" required>
                        <option value="">Select Supplier</option>
                        <?php foreach ($suppliers as $s): ?>
                            <option value="<?= $s['id'] ?>">
                                <?= clean($s['name']) ?> (<?= clean($s['company'] ?? 'Wholesaler') ?>) &mdash; Bal: Rs. <?= number_format($s['current_balance'], 2) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">PO / Supplier Invoice # *</label>
                    <input type="text" name="po_number" class="form-control fw-bold" value="<?= clean($_POST['po_number'] ?? $default_po) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label small fw-bold text-secondary">Receiving Date *</label>
                    <input type="date" name="purchase_date" class="form-control" value="<?= date('Y-m-d') ?>" required>
                </div>
            </div>
        </div>

        <!-- Shoe Model Selector -->
        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                <h5 class="fw-bold text-dark mb-0">Size &amp; Color Assortment Quantities</h5>
                <div class="col-md-4">
                    <select id="select_product_for_purchase" class="form-select">
                        <option value="">-- Choose Shoe Model to Add Assortment --</option>
                        <?php foreach ($products as $p): ?>
                            <option value="<?= $p['id'] ?>" data-name="<?= clean($p['name']) ?>" data-art="<?= clean($p['article_code']) ?>" data-cost="<?= $p['base_purchase_price'] ?>">
                                <?= clean($p['article_code']) ?> &mdash; <?= clean($p['name']) ?> (<?= clean($p['brand_name'] ?? 'Generic') ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <p class="text-muted small">Select shoe models above to load their size &amp; color grid, then enter received quantities (pairs) and purchase unit cost.</p>

            <div id="assortment_tables_container">
                <!-- Dynamically loaded product size grids -->
            </div>
        </div>

        <!-- Costing & Settlement Card -->
        <div class="card border-0 shadow-sm rounded-3 p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-3 pb-2 border-bottom">Landed Costs &amp; Supplier Settlement</h5>
            <div class="row g-3">
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Freight / Bilty / Transport (Rs.)</label>
                    <input type="number" step="0.01" name="freight_cost" id="freight_cost" class="form-control calc-trigger" value="0.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Other Landed Costs (Rs.)</label>
                    <input type="number" step="0.01" name="other_cost" id="other_cost" class="form-control calc-trigger" value="0.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Paid Amount Now (Rs.)</label>
                    <input type="number" step="0.01" name="paid_amount" id="paid_amount" class="form-control calc-trigger" value="0.00">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold text-secondary">Payment Method</label>
                    <select name="payment_method" class="form-select">
                        <option value="cash">Cash</option>
                        <option value="bank">Bank Transfer</option>
                        <option value="jazzcash">JazzCash</option>
                        <option value="easypaisa">Easypaisa</option>
                    </select>
                </div>
            </div>

            <div class="row mt-4 pt-3 border-top align-items-center">
                <div class="col-md-8">
                    <textarea name="notes" class="form-control" rows="2" placeholder="Supplier invoice notes, carton numbers, driver details..."></textarea>
                </div>
                <div class="col-md-4 text-end">
                    <div class="text-muted small">Subtotal: <strong id="subtotal_display" class="text-dark">Rs. 0.00</strong></div>
                    <div class="text-muted small">Landed Cost: <strong id="landed_display" class="text-dark">Rs. 0.00</strong></div>
                    <div class="fs-4 fw-bold text-primary mt-1">Total: <span id="grand_display">Rs. 0.00</span></div>
                    <div class="small text-danger fw-bold">Balance Payable to Khata: <span id="balance_display">Rs. 0.00</span></div>
                </div>
            </div>

            <div class="mt-4 pt-3 border-top text-end">
                <button type="submit" class="btn btn-primary px-5 py-3 fw-bold shadow-sm">
                    <i class="fa-solid fa-check me-2"></i> Save Purchase Invoice &amp; Add Stock
                </button>
            </div>
        </div>
    </form>
</div>

<script>
let loadedProducts = {};

document.getElementById('select_product_for_purchase').addEventListener('change', function() {
    let pId = this.value;
    if (!pId || loadedProducts[pId]) return;

    let opt = this.options[this.selectedIndex];
    let pName = opt.getAttribute('data-name');
    let pArt = opt.getAttribute('data-art');
    let baseCost = parseFloat(opt.getAttribute('data-cost')) || 0;

    fetch(`pos_actions.php?action=get_product_variants&product_id=${pId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.variants.length > 0) {
                loadedProducts[pId] = true;
                renderProductAssortment(pId, pArt, pName, baseCost, data.variants);
            }
        });
});

function renderProductAssortment(pId, pArt, pName, baseCost, variants) {
    const container = document.getElementById('assortment_tables_container');

    let html = `
    <div class="card mb-3 border shadow-sm" id="prod_box_${pId}">
        <div class="card-header bg-light d-flex justify-content-between align-items-center py-2">
            <div>
                <span class="badge bg-dark me-2">${pArt}</span>
                <strong class="text-dark">${pName}</strong>
            </div>
            <button type="button" class="btn btn-sm btn-link text-danger p-0 text-decoration-none" onclick="removeProductBox(${pId})">
                <i class="fa-solid fa-xmark me-1"></i> Remove
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0 small">
                    <thead class="table-light">
                        <tr>
                            <th>Color</th>
                            <th>Size</th>
                            <th>Barcode</th>
                            <th>Current Stock</th>
                            <th style="width: 120px;">Received (Pairs)</th>
                            <th style="width: 130px;">Unit Cost (Rs.)</th>
                            <th style="width: 120px;" class="text-end">Line Total</th>
                        </tr>
                    </thead>
                    <tbody>`;

    variants.forEach(v => {
        let vCost = parseFloat(v.purchase_price) || baseCost;
        html += `
        <tr>
            <td>
                <span class="color-swatch-sm" style="background-color: ${v.hex_code};"></span>
                ${v.color_name}
            </td>
            <td class="fw-bold fs-6">${v.size_number}</td>
            <td><code>${v.barcode}</code></td>
            <td class="text-muted">${v.stock_qty} pairs</td>
            <td>
                <input type="number" name="qty[${v.id}]" class="form-control form-control-sm text-center fw-bold p_qty" 
                       value="0" min="0" data-vid="${v.id}" oninput="recalcTotals()">
            </td>
            <td>
                <input type="number" step="0.01" name="cost[${v.id}]" class="form-control form-control-sm p_cost" 
                       value="${vCost.toFixed(2)}" data-vid="${v.id}" oninput="recalcTotals()">
            </td>
            <td class="text-end fw-bold line_total_display" id="line_tot_${v.id}">Rs. 0.00</td>
        </tr>`;
    });

    html += `</tbody></table></div></div></div>`;
    container.innerHTML += html;
    recalcTotals();
}

function removeProductBox(pId) {
    let el = document.getElementById(`prod_box_${pId}`);
    if (el) el.remove();
    delete loadedProducts[pId];
    recalcTotals();
}

function recalcTotals() {
    let subtotal = 0;
    document.querySelectorAll('.p_qty').forEach(qInput => {
        let vid = qInput.getAttribute('data-vid');
        let qty = parseInt(qInput.value) || 0;
        let cInput = document.querySelector(`.p_cost[data-vid="${vid}"]`);
        let cost = parseFloat(cInput ? cInput.value : 0) || 0;
        let line = qty * cost;
        subtotal += line;
        let lineEl = document.getElementById(`line_tot_${vid}`);
        if (lineEl) lineEl.innerText = 'Rs. ' + line.toFixed(2);
    });

    let freight = parseFloat(document.getElementById('freight_cost').value) || 0;
    let other = parseFloat(document.getElementById('other_cost').value) || 0;
    let paid = parseFloat(document.getElementById('paid_amount').value) || 0;
    let landed = freight + other;
    let grand = subtotal + landed;
    let balance = Math.max(0, grand - paid);

    document.getElementById('subtotal_display').innerText = 'Rs. ' + subtotal.toFixed(2);
    document.getElementById('landed_display').innerText = 'Rs. ' + landed.toFixed(2);
    document.getElementById('grand_display').innerText = 'Rs. ' + grand.toFixed(2);
    document.getElementById('balance_display').innerText = 'Rs. ' + balance.toFixed(2);
}

document.querySelectorAll('.calc-trigger').forEach(el => {
    el.addEventListener('input', recalcTotals);
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
