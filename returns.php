<?php
// returns.php - Sales Returns & Shoe Size/Color Exchanges List
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

$stmt = $db->query("SELECT re.*, s.invoice_no, c.name as customer_name, u.full_name as cashier_name 
                    FROM returns_exchanges re 
                    JOIN sales s ON re.sale_id = s.id 
                    LEFT JOIN customers c ON re.customer_id = c.id 
                    LEFT JOIN users u ON re.user_id = u.id 
                    ORDER BY re.id DESC");
$returns = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-arrow-rotate-left text-primary me-2"></i>Sales Returns &amp; Size Exchanges
            </h2>
            <p class="text-muted small mb-0">Exchange shoe size/color or process customer refunds and defective returns</p>
        </div>
        <div class="d-flex gap-2">
            <a href="return_create.php" class="btn btn-primary fw-bold shadow-sm">
                <i class="fa-solid fa-plus-circle me-1"></i> New Return / Size Exchange
            </a>
            <a href="pos.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-cash-register me-1"></i> Back to POS
            </a>
        </div>
    </div>

    <!-- Returns Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Return #</th>
                        <th>Original Invoice</th>
                        <th>Date</th>
                        <th>Customer</th>
                        <th>Type</th>
                        <th>Returned Value</th>
                        <th>New Item Value</th>
                        <th>Difference Settled</th>
                        <th>Cashier</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($returns)): ?>
                        <tr><td colspan="9" class="text-center py-5 text-muted">No returns or exchanges recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($returns as $r): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= clean($r['return_no']) ?></td>
                                <td>
                                    <a href="invoice_print.php?id=<?= $r['sale_id'] ?>" target="_blank" class="fw-bold text-dark text-decoration-none">
                                        <?= clean($r['invoice_no']) ?>
                                    </a>
                                </td>
                                <td class="text-muted"><?= date('d M Y, h:i A', strtotime($r['return_date'])) ?></td>
                                <td><?= clean($r['customer_name'] ?? 'Walk-in') ?></td>
                                <td>
                                    <?php if ($r['type'] === 'exchange'): ?>
                                        <span class="badge bg-primary"><i class="fa-solid fa-arrows-rotate me-1"></i> Size Exchange</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark"><i class="fa-solid fa-reply me-1"></i> Return / Refund</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-danger fw-semibold">-<?= format_currency($r['returned_items_total']) ?></td>
                                <td class="text-success fw-semibold"><?= format_currency($r['new_items_total']) ?></td>
                                <td>
                                    <?php if ($r['difference_amount'] > 0): ?>
                                        <span class="badge bg-danger">Customer Paid: <?= format_currency($r['difference_amount']) ?></span>
                                    <?php elseif ($r['difference_amount'] < 0): ?>
                                        <span class="badge bg-success">Store Refunded: <?= format_currency(abs($r['difference_amount'])) ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Even Exchange (Rs. 0)</span>
                                    <?php endif; ?>
                                </td>
                                <td class="small text-muted"><?= clean($r['cashier_name'] ?? 'Staff') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
