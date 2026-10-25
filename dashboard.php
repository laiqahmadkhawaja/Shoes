<?php
// dashboard.php
require_once __DIR__ . '/includes/auth_check.php';
$db = getDB();

// 1. KPI Metrics
// Today's Sales
$stmt = $db->query("SELECT COALESCE(SUM(grand_total), 0) as today_sales, COUNT(id) as today_bills FROM sales WHERE DATE(sale_date) = CURDATE() AND status = 'completed'");
$sales_stat = $stmt->fetch();
$today_sales = (float)$sales_stat['today_sales'];
$today_bills = (int)$sales_stat['today_bills'];

// Gross Profit Today = (selling - cost) * qty - item discount
$stmt = $db->query("SELECT COALESCE(SUM((si.unit_price - si.unit_cost) * si.quantity - si.discount), 0) as gross_profit 
                    FROM sale_items si 
                    JOIN sales s ON si.sale_id = s.id 
                    WHERE DATE(s.sale_date) = CURDATE() AND s.status = 'completed'");
$today_gross_profit = (float)$stmt->fetchColumn();

// Total Pairs in Stock
$stmt = $db->query("SELECT COALESCE(SUM(stock_qty), 0) as total_pairs, COALESCE(SUM(stock_qty * purchase_price), 0) as cost_val, COALESCE(SUM(stock_qty * selling_price), 0) as retail_val FROM product_variants");
$stock_stat = $stmt->fetch();
$total_pairs = (int)$stock_stat['total_pairs'];
$cost_valuation = (float)$stock_stat['cost_val'];
$retail_valuation = (float)$stock_stat['retail_val'];

// Customer Receivables (Khata)
$stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE current_balance > 0");
$customer_receivables = (float)$stmt->fetchColumn();

// Supplier Payables
$stmt = $db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE current_balance > 0");
$supplier_payables = (float)$stmt->fetchColumn();

// Low Stock Variants Count
$stmt = $db->query("SELECT COUNT(*) FROM product_variants WHERE stock_qty <= alert_qty");
$low_stock_count = (int)$stmt->fetchColumn();

// 2. Recent Sales
$stmt = $db->query("SELECT s.*, c.name as customer_name, u.username 
                    FROM sales s 
                    LEFT JOIN customers c ON s.customer_id = c.id 
                    LEFT JOIN users u ON s.user_id = u.id 
                    ORDER BY s.id DESC LIMIT 6");
$recent_sales = $stmt->fetchAll();

// 3. Low Stock Items List
$stmt = $db->query("SELECT pv.*, p.name as product_name, p.article_code, sz.size_number, cl.name as color_name 
                    FROM product_variants pv 
                    JOIN products p ON pv.product_id = p.id 
                    JOIN sizes sz ON pv.size_id = sz.id 
                    JOIN colors cl ON pv.color_id = cl.id 
                    WHERE pv.stock_qty <= pv.alert_qty 
                    ORDER BY pv.stock_qty ASC LIMIT 5");
$low_stock_items = $stmt->fetchAll();

// 4. Last 7 Days Sales Trend for Chart
$days_data = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $day_label = date('D (d M)', strtotime($date));
    $stmt = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) as total_revenue, 
                          COALESCE(SUM((si.unit_price - si.unit_cost) * si.quantity - si.discount), 0) as total_profit
                          FROM sales s
                          LEFT JOIN sale_items si ON s.id = si.sale_id
                          WHERE DATE(s.sale_date) = ? AND s.status = 'completed'");
    $stmt->execute([$date]);
    $res = $stmt->fetch();
    $days_data[] = [
        'date' => $day_label,
        'revenue' => (float)$res['total_revenue'],
        'profit' => (float)$res['total_profit']
    ];
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-2 px-lg-3 pt-3">
    <!-- Header with Action Buttons -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div>
            <h2 class="h4 fw-bold text-dark mb-0">
                <i class="fa-solid fa-chart-pie text-primary me-2"></i>Dashboard
            </h2>
            <p class="text-muted small mb-0">Footwear Retail, Size Inventory, Customer Khata &amp; Operations</p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="pos.php" class="btn btn-primary px-3 shadow-sm fw-bold">
                <i class="fa-solid fa-cash-register me-1"></i> Quick POS
            </a>
            <a href="inventory.php" class="btn btn-outline-dark shadow-sm">
                <i class="fa-solid fa-table-cells me-1"></i> Stock Matrix
            </a>
            <?php if (has_permission(['admin', 'manager', 'inventory'])): ?>
            <a href="purchase_add.php" class="btn btn-outline-secondary shadow-sm">
                <i class="fa-solid fa-cart-plus me-1"></i> Assortment Purchase
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- 4 Main Primary KPI Cards -->
    <div class="row g-2 g-lg-3 mb-3">
        <!-- Today's Sales -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width: 0;">
                        <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.3px;">Today's Sales</span>
                        <h4 class="fw-bold text-dark mt-1 mb-1 text-truncate"><?= format_currency($today_sales) ?></h4>
                        <span class="badge bg-primary bg-opacity-10 text-primary fw-medium" style="font-size: 0.72rem;"><?= $today_bills ?> Invoices Today</span>
                    </div>
                    <div class="kpi-icon bg-primary bg-opacity-10 text-primary">
                        <i class="fa-solid fa-money-bill-wave"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Gross Profit -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width: 0;">
                        <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.3px;">Gross Profit</span>
                        <h4 class="fw-bold text-success mt-1 mb-1 text-truncate"><?= format_currency($today_gross_profit) ?></h4>
                        <span class="badge bg-success bg-opacity-10 text-success fw-medium" style="font-size: 0.72rem;">Realized Margin</span>
                    </div>
                    <div class="kpi-icon bg-success bg-opacity-10 text-success">
                        <i class="fa-solid fa-chart-line"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Total Pairs in Stock -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width: 0;">
                        <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.3px;">Total Pairs in Stock</span>
                        <h4 class="fw-bold text-dark mt-1 mb-1 text-truncate"><?= number_format($total_pairs) ?> <small class="fs-6 fw-normal text-muted">Pairs</small></h4>
                        <span class="badge bg-info bg-opacity-10 text-info fw-medium" style="font-size: 0.72rem;">Cost: <?= format_currency($cost_valuation) ?></span>
                    </div>
                    <div class="kpi-icon bg-info bg-opacity-10 text-info">
                        <i class="fa-solid fa-shoe-prints"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Customer Receivables -->
        <div class="col-6 col-lg-3">
            <div class="card kpi-card h-100 p-3">
                <div class="d-flex justify-content-between align-items-start gap-2">
                    <div style="min-width: 0;">
                        <span class="text-muted text-uppercase fw-semibold" style="font-size: 0.72rem; letter-spacing: 0.3px;">Customer Receivables</span>
                        <h4 class="fw-bold text-danger mt-1 mb-1 text-truncate"><?= format_currency($customer_receivables) ?></h4>
                        <a href="customers.php" class="badge bg-danger bg-opacity-10 text-danger text-decoration-none fw-medium" style="font-size: 0.72rem;">Khata Ledgers &rarr;</a>
                    </div>
                    <div class="kpi-icon bg-danger bg-opacity-10 text-danger">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Secondary Row: Supplier Payables & Low Stock Warnings -->
    <div class="row g-2 g-lg-3 mb-3">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">Supplier Payables</span>
                        <h5 class="fw-bold text-warning-emphasis mb-0 mt-1"><?= format_currency($supplier_payables) ?></h5>
                    </div>
                    <a href="suppliers.php" class="btn btn-sm btn-outline-warning">View Suppliers</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">Retail Stock Valuation</span>
                        <h5 class="fw-bold text-primary mb-0 mt-1"><?= format_currency($retail_valuation) ?></h5>
                    </div>
                    <a href="inventory.php" class="btn btn-sm btn-outline-primary">Stock Details</a>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <span class="text-muted small fw-semibold">Low Stock Alerts</span>
                        <h5 class="fw-bold <?= $low_stock_count > 0 ? 'text-danger' : 'text-success' ?> mb-0 mt-1">
                            <?= $low_stock_count ?> <small class="text-muted fs-6">Variants Low/Empty</small>
                        </h5>
                    </div>
                    <a href="inventory.php?filter=low" class="btn btn-sm btn-outline-danger">Review Alert Items</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Chart & Low Stock Section -->
    <div class="row g-2 g-lg-3 mb-3">
        <!-- Sales & Profit Trend -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-chart-column text-primary me-2"></i>Weekly Sales &amp; Profit Trend
                    </h6>
                    <span class="badge bg-light text-secondary border">Last 7 Days</span>
                </div>
                <div style="height: 260px; position: relative;">
                    <canvas id="salesTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert Box -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold text-dark mb-0">
                        <i class="fa-solid fa-triangle-exclamation text-warning me-2"></i>Low Stock Warnings
                    </h6>
                    <a href="inventory.php?filter=low" class="small text-decoration-none">All Stock &rarr;</a>
                </div>

                <?php if (empty($low_stock_items)): ?>
                    <div class="text-center py-4 text-muted">
                        <i class="fa-solid fa-circle-check text-success fs-2 mb-2"></i>
                        <p class="mb-0 small">All shoe sizes and colors have adequate stock levels!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($low_stock_items as $item): ?>
                            <div class="list-group-item px-0 py-2 border-light d-flex justify-content-between align-items-center">
                                <div style="min-width: 0;" class="pe-2">
                                    <div class="fw-semibold text-dark text-truncate" style="font-size: 0.82rem;"><?= clean($item['article_code']) ?> &mdash; <?= clean($item['product_name']) ?></div>
                                    <div class="text-muted" style="font-size: 0.74rem;">
                                        Size: <strong class="text-dark"><?= clean($item['size_number']) ?></strong> | Color: <strong><?= clean($item['color_name']) ?></strong>
                                    </div>
                                </div>
                                <div class="text-end flex-shrink-0">
                                    <span class="badge <?= $item['stock_qty'] <= 0 ? 'bg-danger' : 'bg-warning text-dark' ?> fw-bold" style="font-size: 0.7rem;">
                                        <?= $item['stock_qty'] ?> <?= $item['stock_qty'] <= 0 ? 'Out of Stock' : 'Pairs Left' ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Sales Table -->
    <div class="card border-0 shadow-sm rounded-3 bg-white mb-3">
        <div class="card-header bg-white py-2 px-3 border-bottom d-flex justify-content-between align-items-center">
            <h6 class="fw-bold text-dark mb-0">
                <i class="fa-solid fa-receipt text-primary me-2"></i>Recent Sales &amp; Transactions
            </h6>
            <a href="pos.php" class="btn btn-sm btn-primary py-1 px-2">
                <i class="fa-solid fa-plus me-1"></i> New Invoice
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 small">
                <thead class="table-light">
                    <tr>
                        <th>Invoice #</th>
                        <th>Date &amp; Time</th>
                        <th>Customer</th>
                        <th>Payment Method</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th class="text-center">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($recent_sales)): ?>
                        <tr><td colspan="7" class="text-center py-4 text-muted">No sales invoices recorded yet.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent_sales as $sale): ?>
                            <tr>
                                <td class="fw-bold text-primary"><?= clean($sale['invoice_no']) ?></td>
                                <td class="text-muted"><?= date('d M Y, h:i A', strtotime($sale['sale_date'])) ?></td>
                                <td><?= clean($sale['customer_name'] ?? 'Walk-in Customer') ?></td>
                                <td>
                                    <span class="badge bg-secondary text-uppercase" style="font-size: 0.65rem;"><?= clean($sale['payment_method']) ?></span>
                                </td>
                                <td class="fw-bold"><?= format_currency($sale['grand_total']) ?></td>
                                <td>
                                    <?php if ($sale['payment_status'] === 'paid'): ?>
                                        <span class="badge bg-success" style="font-size: 0.65rem;">Paid</span>
                                    <?php elseif ($sale['payment_status'] === 'due'): ?>
                                        <span class="badge bg-danger" style="font-size: 0.65rem;">Khata (Due)</span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark" style="font-size: 0.65rem;">Partial</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <a href="invoice_print.php?id=<?= $sale['id'] ?>" target="_blank" class="btn btn-sm btn-outline-dark py-0 px-2" title="Print Invoice">
                                        <i class="fa-solid fa-print"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('salesTrendChart').getContext('2d');
    const chartLabels = <?= json_encode(array_column($days_data, 'date')) ?>;
    const revenueData = <?= json_encode(array_column($days_data, 'revenue')) ?>;
    const profitData = <?= json_encode(array_column($days_data, 'profit')) ?>;

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: chartLabels,
            datasets: [
                {
                    label: 'Sales Revenue (Rs.)',
                    data: revenueData,
                    backgroundColor: 'rgba(37, 99, 235, 0.85)',
                    borderRadius: 4
                },
                {
                    label: 'Gross Profit (Rs.)',
                    data: profitData,
                    backgroundColor: 'rgba(16, 185, 129, 0.85)',
                    borderRadius: 4
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'top',
                    labels: { boxWidth: 12, font: { size: 11 } }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { size: 10 } }
                },
                x: {
                    ticks: { font: { size: 10 } }
                }
            }
        }
    });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
