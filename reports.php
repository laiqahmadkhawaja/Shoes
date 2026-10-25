<?php
// reports.php - Comprehensive Business Analytics, Accounting Statements, Charts & Reports
require_once __DIR__ . '/includes/auth_check.php';
require_permission(['admin', 'manager', 'accountant']);

$db = getDB();

// Date range filters
$start_date = clean($_GET['start_date'] ?? date('Y-m-01'));
$end_date = clean($_GET['end_date'] ?? date('Y-m-d'));
$active_tab = clean($_GET['tab'] ?? 'charts');

// Handle CSV Export
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Shoes_Sales_Report_' . $start_date . '_to_' . $end_date . '.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Invoice No', 'Date', 'Customer', 'Payment Method', 'Subtotal', 'Discount', 'Grand Total', 'Paid', 'Status']);

    $stmt = $db->prepare("SELECT s.*, c.name as customer_name 
                          FROM sales s 
                          LEFT JOIN customers c ON s.customer_id = c.id 
                          WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
                          ORDER BY s.id DESC");
    $stmt->execute([$start_date, $end_date]);
    while ($row = $stmt->fetch()) {
        fputcsv($output, [
            $row['invoice_no'],
            $row['sale_date'],
            $row['customer_name'] ?? 'Walk-in',
            $row['payment_method'],
            $row['subtotal'],
            $row['discount_amount'],
            $row['grand_total'],
            $row['paid_amount'],
            $row['payment_status']
        ]);
    }
    fclose($output);
    exit;
}

// ---------------------------------------------------------------------
// 1. Core Financial & P&L Statements
// ---------------------------------------------------------------------
// Sales Revenue & Discounts
$stmt = $db->prepare("SELECT COALESCE(SUM(grand_total), 0) as total_revenue,
                             COALESCE(SUM(subtotal), 0) as total_subtotal,
                             COALESCE(SUM(discount_amount), 0) as total_discounts,
                             COUNT(id) as total_invoices 
                      FROM sales 
                      WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed'");
$stmt->execute([$start_date, $end_date]);
$sales_stat = $stmt->fetch();
$total_revenue = (float)$sales_stat['total_revenue'];
$total_subtotal = (float)$sales_stat['total_subtotal'];
$total_discounts = (float)$sales_stat['total_discounts'];
$total_invoices = (int)$sales_stat['total_invoices'];

// Cost of Goods Sold (COGS) based on sold variants
$stmt = $db->prepare("SELECT COALESCE(SUM(si.unit_cost * si.quantity), 0) as total_cogs,
                             COALESCE(SUM((si.unit_price - si.unit_cost) * si.quantity - si.discount), 0) as gross_profit,
                             COALESCE(SUM(si.quantity), 0) as total_pairs_sold
                      FROM sale_items si 
                      JOIN sales s ON si.sale_id = s.id 
                      WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'");
$stmt->execute([$start_date, $end_date]);
$cost_stat = $stmt->fetch();
$total_cogs = (float)$cost_stat['total_cogs'];
$gross_profit = (float)$cost_stat['gross_profit'];
$total_pairs_sold = (int)$cost_stat['total_pairs_sold'];
$gross_margin_pct = $total_revenue > 0 ? ($gross_profit / $total_revenue) * 100 : 0;

// Returns & Refunds Total
$stmt = $db->prepare("SELECT COALESCE(SUM(returned_items_total), 0) FROM returns_exchanges WHERE DATE(return_date) BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$total_returns_amount = (float)$stmt->fetchColumn();

// Total Operating Expenses in Period
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) FROM expenses WHERE expense_date BETWEEN ? AND ?");
$stmt->execute([$start_date, $end_date]);
$total_expenses = (float)$stmt->fetchColumn();

// Net Operating Profit = Gross Profit - Expenses
$net_profit = $gross_profit - $total_expenses;
$net_margin_pct = $total_revenue > 0 ? ($net_profit / $total_revenue) * 100 : 0;

// ---------------------------------------------------------------------
// 2. Balance Sheet & Working Capital Position
// ---------------------------------------------------------------------
$khata_receivables = (float)$db->query("SELECT COALESCE(SUM(current_balance), 0) FROM customers WHERE current_balance > 0")->fetchColumn();
$khata_advances = (float)$db->query("SELECT COALESCE(SUM(ABS(current_balance)), 0) FROM customers WHERE current_balance < 0")->fetchColumn();
$supplier_payables = (float)$db->query("SELECT COALESCE(SUM(current_balance), 0) FROM suppliers WHERE current_balance > 0")->fetchColumn();
$inventory_cost = (float)$db->query("SELECT COALESCE(SUM(stock_qty * purchase_price), 0) FROM product_variants")->fetchColumn();
$inventory_retail = (float)$db->query("SELECT COALESCE(SUM(stock_qty * selling_price), 0) FROM product_variants")->fetchColumn();
$total_stock_pairs = (int)$db->query("SELECT COALESCE(SUM(stock_qty), 0) FROM product_variants")->fetchColumn();
$cash_in_register = (float)$db->query("SELECT COALESCE(SUM(opening_amount + cash_sales + cash_in - cash_out), 0) FROM cash_registers WHERE status = 'open'")->fetchColumn();
$total_current_assets = $inventory_cost + $khata_receivables + $cash_in_register;
$total_current_liabilities = $supplier_payables + $khata_advances;
$net_working_capital = $total_current_assets - $total_current_liabilities;

// ---------------------------------------------------------------------
// 3. Daily Sales & Expense Trend for Selected Range (Chart Data)
// ---------------------------------------------------------------------
$daily_map = [];
$c_ts = strtotime($start_date);
$e_ts = strtotime($end_date);
while ($c_ts <= $e_ts) {
    $d_str = date('Y-m-d', $c_ts);
    $daily_map[$d_str] = [
        'date' => date('d M', $c_ts),
        'revenue' => 0.0,
        'expense' => 0.0
    ];
    $c_ts = strtotime('+1 day', $c_ts);
}

$stmt = $db->prepare("SELECT DATE(sale_date) as d, COALESCE(SUM(grand_total), 0) as rev 
                      FROM sales 
                      WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed' 
                      GROUP BY DATE(sale_date)");
$stmt->execute([$start_date, $end_date]);
while ($r = $stmt->fetch()) {
    if (isset($daily_map[$r['d']])) {
        $daily_map[$r['d']]['revenue'] = (float)$r['rev'];
    }
}

$stmt = $db->prepare("SELECT expense_date as d, COALESCE(SUM(amount), 0) as exp 
                      FROM expenses 
                      WHERE expense_date BETWEEN ? AND ? 
                      GROUP BY expense_date");
$stmt->execute([$start_date, $end_date]);
while ($r = $stmt->fetch()) {
    if (isset($daily_map[$r['d']])) {
        $daily_map[$r['d']]['expense'] = (float)$r['exp'];
    }
}
$daily_chart_labels = array_column($daily_map, 'date');
$daily_chart_revenue = array_column($daily_map, 'revenue');
$daily_chart_expense = array_column($daily_map, 'expense');

// ---------------------------------------------------------------------
// 4. Operating Expenses by Category (Chart & Breakdown)
// ---------------------------------------------------------------------
$stmt = $db->prepare("SELECT category, COALESCE(SUM(amount), 0) as total_amount, COUNT(id) as count 
                      FROM expenses 
                      WHERE expense_date BETWEEN ? AND ? 
                      GROUP BY category 
                      ORDER BY total_amount DESC");
$stmt->execute([$start_date, $end_date]);
$expense_categories = $stmt->fetchAll();
$expense_labels = array_column($expense_categories, 'category');
$expense_values = array_map('floatval', array_column($expense_categories, 'total_amount'));

// ---------------------------------------------------------------------
// 5. Shoe Size Demand Velocity (Chart & Table)
// ---------------------------------------------------------------------
$stmt = $db->prepare("SELECT si.size_number, SUM(si.quantity) as pairs_sold, SUM(si.total) as total_revenue 
                      FROM sale_items si 
                      JOIN sales s ON si.sale_id = s.id 
                      WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
                      GROUP BY si.size_number 
                      ORDER BY pairs_sold DESC");
$stmt->execute([$start_date, $end_date]);
$size_sales = $stmt->fetchAll();
$size_labels = array_column($size_sales, 'size_number');
$size_pairs = array_map('intval', array_column($size_sales, 'pairs_sold'));

// ---------------------------------------------------------------------
// 6. Brand & Category Market Share (Chart & Table)
// ---------------------------------------------------------------------
$stmt = $db->prepare("SELECT COALESCE(b.name, 'Generic') as brand_name, 
                             SUM(si.quantity) as pairs_sold, 
                             SUM(si.total) as total_sales,
                             SUM((si.unit_price - si.unit_cost) * si.quantity - si.discount) as brand_profit
                      FROM sale_items si
                      JOIN sales s ON si.sale_id = s.id
                      JOIN products p ON si.product_id = p.id
                      LEFT JOIN brands b ON p.brand_id = b.id
                      WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
                      GROUP BY b.id
                      ORDER BY total_sales DESC");
$stmt->execute([$start_date, $end_date]);
$brand_sales = $stmt->fetchAll();
$brand_labels = array_column($brand_sales, 'brand_name');
$brand_totals = array_map('floatval', array_column($brand_sales, 'total_sales'));

// Category breakdown
$stmt = $db->prepare("SELECT COALESCE(c.name, 'Other') as category_name, 
                             SUM(si.quantity) as pairs_sold, 
                             SUM(si.total) as total_sales
                      FROM sale_items si
                      JOIN sales s ON si.sale_id = s.id
                      JOIN products p ON si.product_id = p.id
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
                      GROUP BY c.id
                      ORDER BY total_sales DESC");
$stmt->execute([$start_date, $end_date]);
$category_sales = $stmt->fetchAll();

// ---------------------------------------------------------------------
// 7. Payment Channels Breakdown (Chart & Table)
// ---------------------------------------------------------------------
$stmt = $db->prepare("SELECT payment_method, COUNT(id) as bill_count, SUM(grand_total) as method_total 
                      FROM sales 
                      WHERE DATE(sale_date) BETWEEN ? AND ? AND status = 'completed'
                      GROUP BY payment_method ORDER BY method_total DESC");
$stmt->execute([$start_date, $end_date]);
$payment_breakdown = $stmt->fetchAll();
$payment_labels = array_map('ucfirst', array_column($payment_breakdown, 'payment_method'));
$payment_totals = array_map('floatval', array_column($payment_breakdown, 'method_total'));

// ---------------------------------------------------------------------
// 8. 12-Month Historical Accounting Comparison
// ---------------------------------------------------------------------
$monthly_sales_raw = $db->query("
    SELECT 
        DATE_FORMAT(s.sale_date, '%Y-%m') as ym,
        DATE_FORMAT(s.sale_date, '%b %Y') as month_label,
        COUNT(DISTINCT s.id) as invoice_count,
        COALESCE(SUM(s.grand_total), 0) as revenue,
        COALESCE(SUM(si.quantity), 0) as pairs_sold,
        COALESCE(SUM(si.unit_cost * si.quantity), 0) as cogs,
        COALESCE(SUM((si.unit_price - si.unit_cost) * si.quantity - si.discount), 0) as gross_profit
    FROM sales s
    LEFT JOIN sale_items si ON s.id = si.sale_id
    WHERE s.status = 'completed' AND s.sale_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(s.sale_date, '%Y-%m')
    ORDER BY ym ASC
")->fetchAll(PDO::FETCH_ASSOC);

$monthly_exp_raw = $db->query("
    SELECT 
        DATE_FORMAT(expense_date, '%Y-%m') as ym,
        COALESCE(SUM(amount), 0) as total_expenses
    FROM expenses
    WHERE expense_date >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
    GROUP BY DATE_FORMAT(expense_date, '%Y-%m')
")->fetchAll(PDO::FETCH_KEY_PAIR);

$monthly_history = [];
foreach ($monthly_sales_raw as $m) {
    $ym = $m['ym'];
    $exp = (float)($monthly_exp_raw[$ym] ?? 0);
    $gp = (float)$m['gross_profit'];
    $net = $gp - $exp;
    $rev = (float)$m['revenue'];
    $margin = $rev > 0 ? ($net / $rev) * 100 : 0;
    $monthly_history[] = [
        'ym' => $ym,
        'label' => $m['month_label'],
        'invoices' => $m['invoice_count'],
        'pairs_sold' => $m['pairs_sold'],
        'revenue' => $rev,
        'cogs' => (float)$m['cogs'],
        'gross_profit' => $gp,
        'expenses' => $exp,
        'net_profit' => $net,
        'net_margin' => $margin
    ];
}

// ---------------------------------------------------------------------
// 9. Slow-Moving Stock (Stock Ageing)
// ---------------------------------------------------------------------
$slow_movers = $db->query("SELECT p.article_code, p.name, b.name as brand_name,
                                  (SELECT SUM(stock_qty) FROM product_variants WHERE product_id = p.id) as current_stock,
                                  (SELECT COALESCE(SUM(si.quantity), 0) FROM sale_items si WHERE si.product_id = p.id) as lifetime_sold
                           FROM products p 
                           LEFT JOIN brands b ON p.brand_id = b.id 
                           HAVING current_stock > 3 
                           ORDER BY lifetime_sold ASC, current_stock DESC LIMIT 15")->fetchAll();

// Product Profitability Detail
$stmt = $db->prepare("SELECT p.article_code, p.name as product_name, b.name as brand_name, c.name as category_name,
                             SUM(si.quantity) as pairs_sold,
                             SUM(si.total) as total_sales,
                             SUM(si.unit_cost * si.quantity) as total_cost,
                             SUM((si.unit_price - si.unit_cost) * si.quantity - si.discount) as product_profit
                      FROM sale_items si
                      JOIN sales s ON si.sale_id = s.id
                      JOIN products p ON si.product_id = p.id
                      LEFT JOIN brands b ON p.brand_id = b.id
                      LEFT JOIN categories c ON p.category_id = c.id
                      WHERE DATE(s.sale_date) BETWEEN ? AND ? AND s.status = 'completed'
                      GROUP BY p.id
                      ORDER BY pairs_sold DESC");
$stmt->execute([$start_date, $end_date]);
$product_reports = $stmt->fetchAll();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-3 pt-3">
    <!-- Top Action Header -->
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="h3 fw-bold text-dark mb-1">
                <i class="fa-solid fa-chart-line text-primary me-2"></i>Business &amp; Accounting Reports
            </h2>
            <p class="text-muted small mb-0">Financial Statements, Working Capital, Visual Analytics Charts, Profit &amp; Loss, and Footwear Intelligence</p>
        </div>
        <div class="d-flex gap-2">
            <a href="reports.php?export=csv&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>" class="btn btn-outline-success fw-bold shadow-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export Sales CSV
            </a>
            <button onclick="window.print()" class="btn btn-outline-dark shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Statement
            </button>
        </div>
    </div>

    <!-- Date Range Filter Bar -->
    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white mb-4">
        <form method="GET" action="reports.php" class="row g-2 align-items-end">
            <input type="hidden" name="tab" value="<?= clean($active_tab) ?>">
            <div class="col-sm-6 col-md-3">
                <label class="small text-muted fw-bold">From Date:</label>
                <input type="date" name="start_date" class="form-control" value="<?= clean($start_date) ?>" required>
            </div>
            <div class="col-sm-6 col-md-3">
                <label class="small text-muted fw-bold">To Date:</label>
                <input type="date" name="end_date" class="form-control" value="<?= clean($end_date) ?>" required>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary w-100 fw-bold">
                    <i class="fa-solid fa-filter me-1"></i> Apply Filter
                </button>
            </div>
            <div class="col-md-3">
                <div class="btn-group w-100" role="group">
                    <a href="reports.php?tab=<?= $active_tab ?>&start_date=<?= date('Y-m-d') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">Today</a>
                    <a href="reports.php?tab=<?= $active_tab ?>&start_date=<?= date('Y-m-01') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">This Month</a>
                    <a href="reports.php?tab=<?= $active_tab ?>&start_date=<?= date('Y-01-01') ?>&end_date=<?= date('Y-m-d') ?>" class="btn btn-sm btn-outline-secondary">This Year</a>
                </div>
            </div>
        </form>
    </div>

    <!-- Core Financial KPI Summary Cards -->
    <div class="row g-2 g-md-3 mb-4">
        <!-- Sales Revenue -->
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Sales Revenue</span>
                    <i class="fa-solid fa-coins text-primary fs-5 opacity-75"></i>
                </div>
                <h4 class="fw-bold text-primary mt-2 mb-0 text-truncate"><?= format_currency($total_revenue) ?></h4>
                <div class="d-flex justify-content-between text-muted small mt-2">
                    <span><?= $total_pairs_sold ?> Pairs</span>
                    <span><?= $total_invoices ?> Bills</span>
                </div>
            </div>
        </div>

        <!-- COGS -->
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-secondary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">COGS (Cost)</span>
                    <i class="fa-solid fa-boxes-packing text-secondary fs-5 opacity-75"></i>
                </div>
                <h4 class="fw-bold text-secondary mt-2 mb-0 text-truncate"><?= format_currency($total_cogs) ?></h4>
                <span class="small text-muted mt-2 d-block text-truncate">Wholesale cost</span>
            </div>
        </div>

        <!-- Gross Margin -->
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Gross Profit</span>
                    <i class="fa-solid fa-scale-balanced text-success fs-5 opacity-75"></i>
                </div>
                <h4 class="fw-bold text-success mt-2 mb-0 text-truncate"><?= format_currency($gross_profit) ?></h4>
                <span class="small text-success fw-semibold mt-2 d-block text-truncate"><?= number_format($gross_margin_pct, 1) ?>% Margin</span>
            </div>
        </div>

        <!-- Net Operating Profit -->
        <div class="col-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 <?= $net_profit >= 0 ? 'border-primary' : 'border-danger' ?> h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="text-muted small fw-bold text-uppercase">Net Profit</span>
                    <i class="fa-solid fa-money-bill-trend-up <?= $net_profit >= 0 ? 'text-primary' : 'text-danger' ?> fs-5 opacity-75"></i>
                </div>
                <h4 class="fw-bold <?= $net_profit >= 0 ? 'text-primary' : 'text-danger' ?> mt-2 mb-0 text-truncate">
                    <?= format_currency($net_profit) ?>
                </h4>
                <span class="small text-muted mt-2 d-block text-truncate"><?= number_format($net_margin_pct, 1) ?>% Net</span>
            </div>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <ul class="nav nav-pills mb-4 bg-white p-2 rounded-3 shadow-sm border overflow-x-auto flex-nowrap" style="white-space: nowrap;">
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'charts' ? 'active' : '' ?>" href="reports.php?tab=charts&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-chart-pie me-1"></i> Visual Charts &amp; Analytics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'profit_loss' ? 'active' : '' ?>" href="reports.php?tab=profit_loss&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-file-invoice-dollar me-1"></i> Profit &amp; Loss Statement
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'balance_sheet' ? 'active' : '' ?>" href="reports.php?tab=balance_sheet&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-building-columns me-1"></i> Balance Sheet &amp; Khata
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'monthly' ? 'active' : '' ?>" href="reports.php?tab=monthly&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-calendar-days me-1"></i> 12-Month Performance
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'expenses' ? 'active' : '' ?>" href="reports.php?tab=expenses&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-receipt me-1"></i> Expenses &amp; Cost Centers
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'products' ? 'active' : '' ?>" href="reports.php?tab=products&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-shoe-prints me-1"></i> Shoe &amp; Brand Profitability
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'sizes' ? 'active' : '' ?>" href="reports.php?tab=sizes&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-ruler-horizontal me-1"></i> Size Demand Curve
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'payments' ? 'active' : '' ?>" href="reports.php?tab=payments&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-money-bill-transfer me-1"></i> Payment Gateways
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $active_tab === 'slow' ? 'active' : '' ?>" href="reports.php?tab=slow&start_date=<?= $start_date ?>&end_date=<?= $end_date ?>">
                <i class="fa-solid fa-clock-rotate-left me-1"></i> Stock Ageing &amp; Slow Movers
            </a>
        </li>
    </ul>

    <!-- =================================================================== -->
    <!-- TAB 1: VISUAL CHARTS & ANALYTICS DASHBOARD                          -->
    <!-- =================================================================== -->
    <?php if ($active_tab === 'charts'): ?>
        <!-- Row 1: P&L Structure & Daily Cash Flow Trend -->
        <div class="row g-3 mb-4">
            <!-- P&L Financial Comparison Bar Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-chart-column text-primary me-2"></i>Financial P&amp;L Structure (PKR)
                        </h6>
                        <span class="badge bg-light text-secondary border">Revenue vs Cost vs Profit</span>
                    </div>
                    <p class="text-muted small mb-2">Accounting breakdown of gross billing against wholesale cost and shop expenses</p>
                    <div style="height: 280px; position: relative;">
                        <canvas id="plWaterfallChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Daily Revenue vs Expense Cash Flow Line Chart -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-chart-line text-success me-2"></i>Daily Sales vs. Expenses Cash Flow
                        </h6>
                        <span class="badge bg-light text-secondary border"><?= count($daily_chart_labels) ?> Days Range</span>
                    </div>
                    <p class="text-muted small mb-2">Track daily cash generation against operational cash outflows</p>
                    <div style="height: 280px; position: relative;">
                        <canvas id="cashFlowTrendChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 2: Expense Breakdown & Size Demand Curve -->
        <div class="row g-3 mb-4">
            <!-- Operating Expenses by Category Doughnut -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-chart-pie text-warning me-2"></i>Expense Cost Centers
                        </h6>
                        <span class="badge bg-light text-secondary border"><?= format_currency($total_expenses) ?></span>
                    </div>
                    <p class="text-muted small mb-2">Distribution of store operational overheads</p>
                    <div style="height: 260px; position: relative;">
                        <?php if (empty($expense_categories)): ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted small">No expenses logged in this range.</div>
                        <?php else: ?>
                            <canvas id="expenseCategoryChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Shoe Size Demand Distribution Column Chart -->
            <div class="col-lg-8">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-ruler-horizontal text-info me-2"></i>Shoe Size Demand Curve
                        </h6>
                        <span class="badge bg-light text-secondary border"><?= $total_pairs_sold ?> Pairs Analyzed</span>
                    </div>
                    <p class="text-muted small mb-2">Identify high-velocity sizes to optimize factory replenishment and prevent stockouts</p>
                    <div style="height: 260px; position: relative;">
                        <?php if (empty($size_sales)): ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted small">No shoe size sales in this range.</div>
                        <?php else: ?>
                            <canvas id="sizeDemandChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Row 3: Brand Revenue Share & Payment Channels -->
        <div class="row g-3">
            <!-- Brand Revenue Contribution -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-tags text-primary me-2"></i>Brand Sales Contribution
                        </h6>
                        <span class="badge bg-light text-secondary border"><?= count($brand_sales) ?> Brands</span>
                    </div>
                    <p class="text-muted small mb-2">Sales turnover generated by each footwear brand</p>
                    <div style="height: 260px; position: relative;">
                        <?php if (empty($brand_sales)): ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted small">No brand sales data available.</div>
                        <?php else: ?>
                            <canvas id="brandShareChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Payment Methods Breakdown -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 p-3 bg-white h-100">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h6 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-wallet text-success me-2"></i>Payment Collection Channels
                        </h6>
                        <span class="badge bg-light text-secondary border">Cash vs Digital vs Khata</span>
                    </div>
                    <p class="text-muted small mb-2">Settlement volume via Cash, Bank, JazzCash, Easypaisa, and Credit</p>
                    <div style="height: 260px; position: relative;">
                        <?php if (empty($payment_breakdown)): ?>
                            <div class="d-flex align-items-center justify-content-center h-100 text-muted small">No payments recorded.</div>
                        <?php else: ?>
                            <canvas id="paymentMethodChart"></canvas>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 2: NET PROFIT & LOSS INCOME STATEMENT                           -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'profit_loss'): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom">
                <div>
                    <h5 class="fw-bold text-dark mb-0">Formal Income &amp; Expenditure Statement (P&amp;L)</h5>
                    <span class="text-muted small">Accounting Period: <?= date('d M Y', strtotime($start_date)) ?> &mdash; <?= date('d M Y', strtotime($end_date)) ?></span>
                </div>
                <span class="badge bg-primary px-3 py-2 fs-6">Net Margin: <?= number_format($net_margin_pct, 2) ?>%</span>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Accounting Heads &amp; Line Items</th>
                            <th class="text-end" style="width: 220px;">Amount (PKR)</th>
                            <th class="text-end" style="width: 140px;">% of Revenue</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="table-light fw-bold">
                            <td colspan="3"><i class="fa-solid fa-arrow-trend-up text-primary me-2"></i> REVENUE FROM FOOTWEAR SALES</td>
                        </tr>
                        <tr>
                            <td class="ps-4">Gross Invoiced Sales (<?= $total_pairs_sold ?> pairs sold)</td>
                            <td class="text-end fw-semibold"><?= format_currency($total_subtotal) ?></td>
                            <td class="text-end text-muted">100.0%</td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-muted">&bull; Less: Promotional Customer Discounts Awarded</td>
                            <td class="text-end text-danger">-<?= format_currency($total_discounts) ?></td>
                            <td class="text-end text-danger"><?= $total_subtotal > 0 ? number_format(($total_discounts / $total_subtotal) * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr>
                            <td class="ps-4 text-muted">&bull; Less: Sales Returns &amp; Customer Cash Refunds</td>
                            <td class="text-end text-danger">-<?= format_currency($total_returns_amount) ?></td>
                            <td class="text-end text-danger"><?= $total_subtotal > 0 ? number_format(($total_returns_amount / $total_subtotal) * 100, 1) : 0 ?>%</td>
                        </tr>
                        <tr class="fw-bold bg-light">
                            <td class="ps-3 text-primary">NET SALES REVENUE</td>
                            <td class="text-end fw-bold text-primary"><?= format_currency($total_revenue) ?></td>
                            <td class="text-end fw-bold text-primary">100.0%</td>
                        </tr>

                        <tr class="table-light fw-bold">
                            <td colspan="3"><i class="fa-solid fa-boxes-packing text-secondary me-2"></i> COST OF GOODS SOLD (COGS)</td>
                        </tr>
                        <tr>
                            <td class="ps-4">Wholesale Direct Cost of Sold Shoes</td>
                            <td class="text-end text-secondary fw-semibold">-<?= format_currency($total_cogs) ?></td>
                            <td class="text-end text-secondary"><?= $total_revenue > 0 ? number_format(($total_cogs / $total_revenue) * 100, 1) : 0 ?>%</td>
                        </tr>

                        <tr class="table-success fw-bold fs-6">
                            <td><i class="fa-solid fa-check-double me-2"></i> GROSS OPERATING PROFIT</td>
                            <td class="text-end text-success"><?= format_currency($gross_profit) ?></td>
                            <td class="text-end text-success"><?= number_format($gross_margin_pct, 1) ?>%</td>
                        </tr>

                        <tr class="table-light fw-bold">
                            <td colspan="3"><i class="fa-solid fa-receipt text-danger me-2"></i> OPERATING OVERHEAD EXPENSES</td>
                        </tr>
                        <?php if (empty($expense_categories)): ?>
                            <tr>
                                <td class="ps-4 text-muted fst-italic">No overhead expenses recorded in this period</td>
                                <td class="text-end text-muted">Rs. 0.00</td>
                                <td class="text-end text-muted">0.0%</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($expense_categories as $ec): ?>
                                <tr>
                                    <td class="ps-4 text-muted">&bull; <?= clean($ec['category']) ?> (<?= $ec['count'] ?> entries)</td>
                                    <td class="text-end text-danger">-<?= format_currency($ec['total_amount']) ?></td>
                                    <td class="text-end text-muted"><?= $total_revenue > 0 ? number_format(($ec['total_amount'] / $total_revenue) * 100, 1) : 0 ?>%</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr class="fw-semibold bg-light">
                            <td class="ps-3 text-danger">TOTAL OVERHEAD EXPENSES</td>
                            <td class="text-end fw-bold text-danger">-<?= format_currency($total_expenses) ?></td>
                            <td class="text-end text-danger"><?= $total_revenue > 0 ? number_format(($total_expenses / $total_revenue) * 100, 1) : 0 ?>%</td>
                        </tr>

                        <tr class="table-primary fw-bold fs-5">
                            <td><i class="fa-solid fa-trophy me-2"></i> NET PROFIT (BOTTOM LINE EARNINGS)</td>
                            <td class="text-end text-primary"><?= format_currency($net_profit) ?></td>
                            <td class="text-end text-primary"><?= number_format($net_margin_pct, 1) ?>%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 3: BALANCE SHEET & WORKING CAPITAL                              -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'balance_sheet'): ?>
        <div class="row g-3">
            <!-- Current Assets Card -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-vault text-success me-2"></i>Current Assets
                        </h5>
                        <span class="badge bg-success-subtle text-success fs-6 fw-bold"><?= format_currency($total_current_assets) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Shoe Inventory Valuation (At Cost)</div>
                                        <span class="small text-muted"><?= number_format($total_stock_pairs) ?> Total pairs in stock across all racks</span>
                                    </td>
                                    <td class="text-end fw-bold text-dark"><?= format_currency($inventory_cost) ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Customer Khata Receivables</div>
                                        <span class="small text-muted">Outstanding credit sales owed by registered customers</span>
                                    </td>
                                    <td class="text-end fw-bold text-primary"><?= format_currency($khata_receivables) ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Active Cash Register / Till Cash</div>
                                        <span class="small text-muted">Physical cash on hand in store registers</span>
                                    </td>
                                    <td class="text-end fw-bold text-success"><?= format_currency($cash_in_register) ?></td>
                                </tr>
                                <tr class="table-light fw-bold fs-6">
                                    <td>TOTAL CURRENT ASSETS</td>
                                    <td class="text-end text-success"><?= format_currency($total_current_assets) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 bg-light rounded-3 mt-3">
                        <div class="d-flex justify-content-between small text-muted">
                            <span>Inventory Retail Valuation:</span>
                            <span class="fw-bold text-dark"><?= format_currency($inventory_retail) ?></span>
                        </div>
                        <div class="d-flex justify-content-between small text-muted mt-1">
                            <span>Unrealized Potential Stock Profit:</span>
                            <span class="fw-bold text-success"><?= format_currency($inventory_retail - $inventory_cost) ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Current Liabilities & Working Capital -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm rounded-3 bg-white p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-hand-holding-dollar text-danger me-2"></i>Current Liabilities
                        </h5>
                        <span class="badge bg-danger-subtle text-danger fs-6 fw-bold"><?= format_currency($total_current_liabilities) ?></span>
                    </div>
                    <div class="table-responsive">
                        <table class="table align-middle">
                            <tbody>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Supplier Khata Payables</div>
                                        <span class="small text-muted">Pending invoice balances owed to shoe wholesalers/factories</span>
                                    </td>
                                    <td class="text-end fw-bold text-danger"><?= format_currency($supplier_payables) ?></td>
                                </tr>
                                <tr>
                                    <td>
                                        <div class="fw-bold">Customer Advances / Credit Balances</div>
                                        <span class="small text-muted">Advance customer deposits or store credits</span>
                                    </td>
                                    <td class="text-end fw-bold text-secondary"><?= format_currency($khata_advances) ?></td>
                                </tr>
                                <tr class="table-light fw-bold fs-6">
                                    <td>TOTAL CURRENT LIABILITIES</td>
                                    <td class="text-end text-danger"><?= format_currency($total_current_liabilities) ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Net Working Capital Box -->
                    <div class="card border-0 bg-primary bg-opacity-10 p-4 rounded-3 mt-4">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <span class="text-uppercase fw-bold text-primary small">Net Working Capital</span>
                                <h3 class="fw-bold text-primary mb-0 mt-1"><?= format_currency($net_working_capital) ?></h3>
                                <span class="small text-muted">Current Assets minus Current Liabilities</span>
                            </div>
                            <div class="text-end">
                                <span class="badge bg-primary px-3 py-2 fs-6">
                                    Current Ratio: <?= $total_current_liabilities > 0 ? number_format($total_current_assets / $total_current_liabilities, 2) : '&infin;' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 4: 12-MONTH HISTORICAL ACCOUNTING TREND                         -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'monthly'): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
            <h5 class="fw-bold text-dark mb-1">12-Month Financial Performance Comparison</h5>
            <p class="text-muted small mb-4">Historical monthly revenue, cost of goods, gross margin, expenses, and net profit trends</p>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Bills</th>
                            <th class="text-center">Pairs Sold</th>
                            <th class="text-end">Gross Sales</th>
                            <th class="text-end">COGS</th>
                            <th class="text-end">Gross Profit</th>
                            <th class="text-end">Expenses</th>
                            <th class="text-end">Net Profit</th>
                            <th class="text-end">Net Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($monthly_history)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No monthly sales data found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($monthly_history as $mh): ?>
                                <tr>
                                    <td class="fw-bold text-dark"><?= clean($mh['label']) ?></td>
                                    <td class="text-center"><?= $mh['invoices'] ?></td>
                                    <td class="text-center fw-semibold"><?= $mh['pairs_sold'] ?></td>
                                    <td class="text-end fw-bold text-primary"><?= format_currency($mh['revenue']) ?></td>
                                    <td class="text-end text-muted"><?= format_currency($mh['cogs']) ?></td>
                                    <td class="text-end text-success fw-semibold"><?= format_currency($mh['gross_profit']) ?></td>
                                    <td class="text-end text-danger"><?= format_currency($mh['expenses']) ?></td>
                                    <td class="text-end fw-bold <?= $mh['net_profit'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= format_currency($mh['net_profit']) ?>
                                    </td>
                                    <td class="text-end">
                                        <span class="badge <?= $mh['net_margin'] >= 15 ? 'bg-success' : ($mh['net_margin'] > 0 ? 'bg-primary' : 'bg-danger') ?>">
                                            <?= number_format($mh['net_margin'], 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 5: OPERATING EXPENSES & COST CENTERS                            -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'expenses'): ?>
        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 bg-white p-4 h-100">
                    <h5 class="fw-bold text-dark mb-3">Cost Center Distribution</h5>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Category</th>
                                    <th class="text-center">Entries</th>
                                    <th class="text-end">Amount</th>
                                    <th class="text-end">% Share</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($expense_categories)): ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No expenses recorded.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($expense_categories as $ec): ?>
                                        <tr>
                                            <td class="fw-semibold text-dark"><?= clean($ec['category']) ?></td>
                                            <td class="text-center"><?= $ec['count'] ?></td>
                                            <td class="text-end fw-bold text-danger"><?= format_currency($ec['total_amount']) ?></td>
                                            <td class="text-end text-muted">
                                                <?= $total_expenses > 0 ? number_format(($ec['total_amount'] / $total_expenses) * 100, 1) : 0 ?>%
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                            <tfoot class="table-light fw-bold">
                                <tr>
                                    <td colspan="2">TOTAL EXPENSES</td>
                                    <td class="text-end text-danger"><?= format_currency($total_expenses) ?></td>
                                    <td class="text-end">100.0%</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Expenses Detail Table -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 bg-white p-4 h-100">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h5 class="fw-bold text-dark mb-0">Recent Expense Transactions</h5>
                        <a href="expenses.php" class="btn btn-sm btn-outline-primary">
                            <i class="fa-solid fa-plus me-1"></i> Add Expense
                        </a>
                    </div>
                    <?php
                    $stmt = $db->prepare("SELECT e.*, u.full_name as recorded_by 
                                          FROM expenses e 
                                          LEFT JOIN users u ON e.user_id = u.id 
                                          WHERE e.expense_date BETWEEN ? AND ? 
                                          ORDER BY e.expense_date DESC, e.id DESC LIMIT 20");
                    $stmt->execute([$start_date, $end_date]);
                    $recent_expenses = $stmt->fetchAll();
                    ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Category</th>
                                    <th>Description</th>
                                    <th>Payment</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_expenses)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No expense items found.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_expenses as $re): ?>
                                        <tr>
                                            <td class="text-nowrap"><?= date('d M, Y', strtotime($re['expense_date'])) ?></td>
                                            <td><span class="badge bg-light text-dark border"><?= clean($re['category']) ?></span></td>
                                            <td class="text-truncate" style="max-width: 200px;"><?= clean($re['description'] ?? '-') ?></td>
                                            <td><span class="badge bg-secondary-subtle text-secondary text-uppercase"><?= clean($re['payment_method']) ?></span></td>
                                            <td class="text-end fw-bold text-danger"><?= format_currency($re['amount']) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 6: SHOE & BRAND PROFITABILITY                                  -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'products'): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
                <h5 class="fw-bold text-dark mb-0">Shoe Article &amp; Brand Profitability Analysis</h5>
                <span class="text-muted small">Realized Gross Margin per Shoe Model</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Article #</th>
                            <th>Shoe Model</th>
                            <th>Brand</th>
                            <th>Category</th>
                            <th class="text-center">Pairs Sold</th>
                            <th class="text-end">Sales Revenue</th>
                            <th class="text-end">Total Cost</th>
                            <th class="text-end">Gross Profit</th>
                            <th class="text-end">Profit Margin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($product_reports)): ?>
                            <tr><td colspan="9" class="text-center py-4 text-muted">No product sales in selected date range.</td></tr>
                        <?php else: ?>
                            <?php foreach ($product_reports as $pr): 
                                $pr_margin = $pr['total_sales'] > 0 ? ($pr['product_profit'] / $pr['total_sales']) * 100 : 0;
                            ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= clean($pr['article_code']) ?></td>
                                    <td class="fw-semibold text-dark"><?= clean($pr['product_name']) ?></td>
                                    <td><?= clean($pr['brand_name'] ?? 'Generic') ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= clean($pr['category_name'] ?? 'Footwear') ?></span></td>
                                    <td class="text-center fw-bold fs-6"><?= $pr['pairs_sold'] ?></td>
                                    <td class="text-end"><?= format_currency($pr['total_sales']) ?></td>
                                    <td class="text-end text-muted"><?= format_currency($pr['total_cost']) ?></td>
                                    <td class="text-end fw-bold text-success"><?= format_currency($pr['product_profit']) ?></td>
                                    <td class="text-end">
                                        <span class="badge <?= $pr_margin >= 30 ? 'bg-success' : 'bg-primary' ?>">
                                            <?= number_format($pr_margin, 1) ?>%
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 7: SIZE DEMAND CURVE                                            -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'sizes'): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
            <h5 class="fw-bold text-dark mb-1">Shoe Size Sales Velocity &amp; Demand Breakdown</h5>
            <p class="text-muted small mb-4">Ranked distribution of customer shoe size purchases to optimize inventory restocking:</p>
            <div class="row g-3">
                <?php if (empty($size_sales)): ?>
                    <div class="col-12 text-center py-5 text-muted">No shoe size sales recorded in this date range.</div>
                <?php else: ?>
                    <?php foreach ($size_sales as $ss): ?>
                        <div class="col-6 col-sm-4 col-md-3 col-xl-2">
                            <div class="card p-3 border text-center shadow-sm h-100 rounded-3">
                                <span class="text-muted small fw-semibold text-uppercase">Size Number</span>
                                <h2 class="fw-bold text-primary my-1"><?= clean($ss['size_number']) ?></h2>
                                <span class="badge bg-success fs-6 my-1"><?= $ss['pairs_sold'] ?> Pairs Sold</span>
                                <span class="small text-muted mt-1"><?= format_currency($ss['total_revenue']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 8: PAYMENT GATEWAYS & CHANNELS                                 -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'payments'): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white p-4">
            <h5 class="fw-bold text-dark mb-1">Payment Gateways &amp; Settlement Breakdown</h5>
            <p class="text-muted small mb-4">Reconcile payments received across counter cash, electronic wallets, bank transfers, and customer credit:</p>
            <div class="row g-3">
                <?php if (empty($payment_breakdown)): ?>
                    <div class="col-12 text-center py-5 text-muted">No payment transactions recorded in this date range.</div>
                <?php else: ?>
                    <?php foreach ($payment_breakdown as $pb): ?>
                        <div class="col-md-4">
                            <div class="card p-3 border shadow-sm rounded-3 h-100">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="badge bg-secondary text-uppercase fs-6 px-3 py-2"><?= clean($pb['payment_method']) ?></span>
                                    <span class="text-muted small fw-semibold"><?= $pb['bill_count'] ?> Invoices</span>
                                </div>
                                <h3 class="fw-bold text-dark mt-3 mb-1"><?= format_currency($pb['method_total']) ?></h3>
                                <span class="text-muted small">
                                    <?= $total_revenue > 0 ? number_format(($pb['method_total'] / $total_revenue) * 100, 1) : 0 ?>% of total collections
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    <!-- =================================================================== -->
    <!-- TAB 9: STOCK AGEING & SLOW MOVERS                                   -->
    <!-- =================================================================== -->
    <?php elseif ($active_tab === 'slow'): ?>
        <div class="card border-0 shadow-sm rounded-3 bg-white">
            <div class="card-header bg-white py-3 border-bottom">
                <h5 class="fw-bold text-dark mb-0">Stock Ageing &amp; Slow-Moving Shoes</h5>
                <span class="small text-muted">Shoes with substantial rack inventory remaining but low customer turnover:</span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Article Code</th>
                            <th>Shoe Name</th>
                            <th>Brand</th>
                            <th class="text-center">Pairs Remaining in Stock</th>
                            <th class="text-center">Lifetime Sold</th>
                            <th>Clearance Recommendation</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($slow_movers)): ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No slow-moving inventory detected. All active products have steady sales velocity.</td></tr>
                        <?php else: ?>
                            <?php foreach ($slow_movers as $sm): ?>
                                <tr>
                                    <td class="fw-bold text-primary"><?= clean($sm['article_code']) ?></td>
                                    <td class="fw-semibold text-dark"><?= clean($sm['name']) ?></td>
                                    <td><?= clean($sm['brand_name'] ?? 'Generic') ?></td>
                                    <td class="text-center fw-bold text-danger fs-6"><?= $sm['current_stock'] ?> Pairs</td>
                                    <td class="text-center"><?= $sm['lifetime_sold'] ?> Pairs</td>
                                    <td>
                                        <span class="badge bg-warning text-dark">
                                            <i class="fa-solid fa-percent me-1"></i> Clearance Discount Recommended
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- =================================================================== -->
<!-- CHART.JS SCRIPTS INITIALIZATION                                     -->
<!-- =================================================================== -->
<?php if ($active_tab === 'charts'): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Financial P&L Structure Waterfall / Bar Chart
    const plCtx = document.getElementById('plWaterfallChart');
    if (plCtx) {
        new Chart(plCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: ['Sales Revenue', 'COGS (Shoe Cost)', 'Gross Profit', 'Expenses', 'Net Profit'],
                datasets: [{
                    label: 'Amount (PKR)',
                    data: [
                        <?= $total_revenue ?>,
                        <?= $total_cogs ?>,
                        <?= $gross_profit ?>,
                        <?= $total_expenses ?>,
                        <?= $net_profit ?>
                    ],
                    backgroundColor: [
                        'rgba(37, 99, 235, 0.85)',   // Blue
                        'rgba(100, 116, 139, 0.85)', // Slate
                        'rgba(16, 185, 129, 0.85)',  // Emerald Green
                        'rgba(239, 68, 68, 0.85)',   // Red
                        '<?= $net_profit >= 0 ? "rgba(37, 99, 235, 0.95)" : "rgba(220, 38, 38, 0.95)" ?>' // Net
                    ],
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Rs. ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return 'Rs. ' + val.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    // 2. Daily Sales vs Expenses Cash Flow Line Chart
    const cashFlowCtx = document.getElementById('cashFlowTrendChart');
    if (cashFlowCtx) {
        new Chart(cashFlowCtx.getContext('2d'), {
            type: 'line',
            data: {
                labels: <?= json_encode($daily_chart_labels) ?>,
                datasets: [
                    {
                        label: 'Sales Revenue',
                        data: <?= json_encode($daily_chart_revenue) ?>,
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    },
                    {
                        label: 'Overhead Expenses',
                        data: <?= json_encode($daily_chart_expense) ?>,
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        fill: true,
                        tension: 0.3,
                        pointRadius: 3
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': Rs. ' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(val) { return 'Rs. ' + val.toLocaleString(); }
                        }
                    }
                }
            }
        });
    }

    // 3. Operating Expenses by Category Doughnut
    const expenseCtx = document.getElementById('expenseCategoryChart');
    if (expenseCtx) {
        new Chart(expenseCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($expense_labels) ?>,
                datasets: [{
                    data: <?= json_encode($expense_values) ?>,
                    backgroundColor: [
                        '#3b82f6', '#ef4444', '#10b981', '#f59e0b', '#8b5cf6',
                        '#06b6d4', '#ec4899', '#64748b'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': Rs. ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // 4. Shoe Size Demand Curve Column Chart
    const sizeCtx = document.getElementById('sizeDemandChart');
    if (sizeCtx) {
        new Chart(sizeCtx.getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?= json_encode($size_labels) ?>,
                datasets: [{
                    label: 'Pairs Sold',
                    data: <?= json_encode($size_pairs) ?>,
                    backgroundColor: 'rgba(14, 165, 233, 0.85)',
                    borderRadius: 6
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1 }
                    },
                    x: {
                        title: { display: true, text: 'Shoe Size' }
                    }
                }
            }
        });
    }

    // 5. Brand Revenue Share Pie Chart
    const brandCtx = document.getElementById('brandShareChart');
    if (brandCtx) {
        new Chart(brandCtx.getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?= json_encode($brand_labels) ?>,
                datasets: [{
                    data: <?= json_encode($brand_totals) ?>,
                    backgroundColor: [
                        '#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#ef4444',
                        '#06b6d4', '#64748b', '#ec4899'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': Rs. ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }

    // 6. Payment Methods Breakdown Doughnut
    const paymentCtx = document.getElementById('paymentMethodChart');
    if (paymentCtx) {
        new Chart(paymentCtx.getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?= json_encode($payment_labels) ?>,
                datasets: [{
                    data: <?= json_encode($payment_totals) ?>,
                    backgroundColor: [
                        '#10b981', // Cash
                        '#2563eb', // Bank
                        '#ef4444', // JazzCash
                        '#06b6d4', // Easypaisa
                        '#8b5cf6'  // Credit
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.label + ': Rs. ' + context.parsed.toLocaleString();
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
