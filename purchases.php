<?php
// purchases.php - Purchases & Supplier Invoices
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$stmt = $db->query("SELECT p.*, s.name as supplier_name, s.company, u.full_name as user_name,
                           (SELECT COUNT(*) FROM purchase_items WHERE purchase_id = p.id) as item_count,
                           (SELECT SUM(quantity) FROM purchase_items WHERE purchase_id = p.id) as total_pairs
                    FROM purchases p
                    JOIN suppliers s ON p.supplier_id = s.id
                    LEFT JOIN users u ON p.user_id = u.id
                    ORDER BY p.id DESC");
$purchases = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>Purchase Invoices &amp; Stock Receiving
            </h2>
            <p class="text-muted small mb-0">Record wholesale purchases, size assortment cartons, and supplier payables</p>
        </div>
        <div class="d-flex gap-2">
            <a href="purchase_add.php" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-cart-plus me-1"></i> New Purchase (Size Matrix)
            </a>
            <a href="suppliers.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-handshake me-1"></i> Suppliers &amp; Khata
            </a>
        </div>
    </div>

    <!-- Purchases Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>PO / Invoice #</th>
                        <th>Date</th>
                        <th>Supplier</th>
                        <th class="text-center">Total Pairs</th>
                        <th>Grand Total</th>
                        <th>Paid</th>
                        <th>Balance Due</th>
                        <th>Payment Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($purchases)): ?>
                        <tr><td colspan="8" class="text-center py-5 text-muted">No purchase invoices recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($purchases as $p): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= clean($p['po_number']) ?></td>
                                <td class="text-muted"><?= date('d M Y', strtotime($p['purchase_date'])) ?></td>
                                <td>
                                    <div class="fw-semibold text-dark"><?= clean($p['supplier_name']) ?></div>
                                    <div class="small text-muted"><?= clean($p['company'] ?? '') ?></div>
                                </td>
                                <td class="text-center fw-bold fs-6">
                                    <span class="badge bg-light text-dark border px-3"><?= (int)$p['total_pairs'] ?> Pairs</span>
                                </td>
                                <td class="fw-bold"><?= format_currency($p['grand_total']) ?></td>
                                <td class="text-success"><?= format_currency($p['paid_amount']) ?></td>
                                <td class="text-danger fw-bold"><?= format_currency($p['balance_amount']) ?></td>
                                <td>
                                    <?php if ($p['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success">Paid</span>
                                    <?php elseif ($p['payment_status'] === 'partial'): ?>
                                        <span class="badge bg-warning text-dark">Partial</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Unpaid</span>
                                    <?php endif; ?>
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
